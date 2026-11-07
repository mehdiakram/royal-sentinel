<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Royal Sentinel - Vulnerability Scanner
 * Free, lightweight vulnerability monitoring using WordPress.org APIs
 */

class RS_Vuln_Scanner {
    
    private $risky_plugins = array();
    private $scan_start_time = 0;
    
    public function __construct() {
        $this->load_risky_plugins();
        
        // Register AJAX handlers
        add_action( 'wp_ajax_rs_run_vuln_scan', array( $this, 'ajax_run_scan' ) );
        add_action( 'wp_ajax_rs_get_vuln_results', array( $this, 'ajax_get_results' ) );
    }
    
    /**
     * Load static risky plugins database
     */
    private function load_risky_plugins() {
        $json_file = ROYALSENTINEL_PLUGIN_DIR . 'includes/data/risky-plugins.json';
        
        if ( file_exists( $json_file ) ) {
            $json_data = file_get_contents( $json_file );
            $data = json_decode( $json_data, true );
            $this->risky_plugins = $data['known_risky'] ?? array();
        }
    }
    
    /**
     * AJAX: Run vulnerability scan
     */
    public function ajax_run_scan() {
        check_ajax_referer( 'rs_vuln_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'royal-sentinel' ) ) );
        }
        
        $results = $this->run_full_scan();
        
        wp_send_json_success( $results );
    }
    
    /**
     * Run full vulnerability scan
     */
    public function run_full_scan() {
        $this->scan_start_time = microtime( true );
        @set_time_limit( 120 );
        
        $results = array(
            'core' => $this->scan_wordpress_core(),
            'plugins' => $this->scan_plugins(),
            'themes' => $this->scan_themes(),
            'environment' => $this->scan_environment(),
            'scanned_at' => current_time( 'mysql' )
        );
        
        // Calculate summary
        $summary = $this->calculate_summary( $results );
        
        // Store in transient (24h expiry)
        set_transient( 'rs_vuln_scan_results', $results, DAY_IN_SECONDS );
        
        // Store summary in database
        $this->store_summary( $summary );
        
        return array(
            'results' => $results,
            'summary' => $summary
        );
    }
    
    /**
     * Scan WordPress core version
     */
    private function scan_wordpress_core() {
        $current_version = get_bloginfo( 'version' );
        $latest_version = $this->get_latest_wp_version();
        
        if ( ! $latest_version ) {
            return array();
        }
        
        $issues = array();
        
        if ( version_compare( $current_version, $latest_version, '<' ) ) {
            $version_parts_current = explode( '.', $current_version );
            $version_parts_latest = explode( '.', $latest_version );
            
            $severity = 'low';
            if ( $version_parts_current[0] < $version_parts_latest[0] ) {
                $severity = 'critical'; // Major version behind
            } elseif ( isset( $version_parts_current[1] ) && isset( $version_parts_latest[1] ) && 
                       $version_parts_current[1] < $version_parts_latest[1] ) {
                $severity = 'high'; // Minor version behind
            }
            
            $issues[] = array(
                'name' => 'WordPress Core',
                'current_version' => $current_version,
                'latest_version' => $latest_version,
                'risk_type' => 'outdated',
                'severity' => $severity,
                'recommendation' => sprintf(
                    __( 'Update WordPress from %s', 'royal-sentinel' ),
                    '<a href="' . admin_url( 'update-core.php' ) . '" target="_blank">Dashboard → Updates</a>'
                )
            );
        }
        
        return $issues;
    }
    
    /**
     * Scan installed plugins
     */
    private function scan_plugins() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $issues = array();
        
        foreach ( $all_plugins as $plugin_path => $plugin_data ) {
            $slug = dirname( $plugin_path );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_path, '.php' );
            }
            
            // Check against risky plugins list first
            if ( isset( $this->risky_plugins[ $slug ] ) ) {
                $risky_data = $this->risky_plugins[ $slug ];
                $issues[] = array(
                    'name' => $plugin_data['Name'],
                    'slug' => $slug,
                    'current_version' => $plugin_data['Version'],
                    'latest_version' => 'N/A',
                    'risk_type' => 'known_risk',
                    'severity' => $risky_data['severity'],
                    'reason' => $risky_data['reason'],
                    'recommendation' => $risky_data['recommendation']
                );
                continue;
            }
            
            // Check WordPress.org for updates
            $plugin_info = $this->get_plugin_info( $slug );
            
            if ( ! $plugin_info ) {
                continue; // Skip if can't fetch info
            }
            
            // Check if outdated
            if ( isset( $plugin_info['version'] ) && 
                 version_compare( $plugin_data['Version'], $plugin_info['version'], '<' ) ) {
                
                $severity = $this->calculate_version_severity( 
                    $plugin_data['Version'], 
                    $plugin_info['version'] 
                );
                
                $issues[] = array(
                    'name' => $plugin_data['Name'],
                    'slug' => $slug,
                    'current_version' => $plugin_data['Version'],
                    'latest_version' => $plugin_info['version'],
                    'risk_type' => 'outdated',
                    'severity' => $severity,
                    'recommendation' => sprintf(
                        __( 'Update from %s', 'royal-sentinel' ),
                        '<a href="' . admin_url( 'plugins.php' ) . '" target="_blank">Dashboard → Plugins</a>'
                    )
                );
            }
            
            // Check if abandoned
            if ( isset( $plugin_info['last_updated'] ) ) {
                $last_update = strtotime( $plugin_info['last_updated'] );
                $months_since_update = ( time() - $last_update ) / ( 30 * DAY_IN_SECONDS );
                
                if ( $months_since_update >= 18 ) {
                    $issues[] = array(
                        'name' => $plugin_data['Name'],
                        'slug' => $slug,
                        'current_version' => $plugin_data['Version'],
                        'latest_version' => $plugin_info['version'] ?? 'N/A',
                        'risk_type' => 'abandoned',
                        'severity' => 'high',
                        'last_updated' => human_time_diff( $last_update ) . ' ago',
                        'recommendation' => __( 'Consider finding an alternative plugin', 'royal-sentinel' )
                    );
                }
            }
            
            // Small delay to respect API rate limits
            usleep( 200000 ); // 0.2 seconds
        }
        
        return $issues;
    }
    
    /**
     * Scan installed themes
     */
    private function scan_themes() {
        $all_themes = wp_get_themes();
        $issues = array();
        
        foreach ( $all_themes as $theme_slug => $theme ) {
            $theme_info = $this->get_theme_info( $theme_slug );
            
            if ( ! $theme_info ) {
                continue;
            }
            
            // Check if outdated
            if ( isset( $theme_info['version'] ) && 
                 version_compare( $theme->get( 'Version' ), $theme_info['version'], '<' ) ) {
                
                $issues[] = array(
                    'name' => $theme->get( 'Name' ),
                    'slug' => $theme_slug,
                    'current_version' => $theme->get( 'Version' ),
                    'latest_version' => $theme_info['version'],
                    'risk_type' => 'outdated',
                    'severity' => 'medium',
                    'recommendation' => sprintf(
                        __( 'Update from %s', 'royal-sentinel' ),
                        '<a href="' . admin_url( 'themes.php' ) . '" target="_blank">Dashboard → Themes</a>'
                    )
                );
            }
            
            usleep( 200000 );
        }
        
        return $issues;
    }
    
    /**
     * Scan environment configuration
     */
    private function scan_environment() {
        $issues = array();
        
        // Check PHP version
        $php_version = phpversion();
        if ( version_compare( $php_version, '7.4', '<' ) ) {
            $issues[] = array(
                'name' => 'PHP Version',
                'current_version' => $php_version,
                'risk_type' => 'environment',
                'severity' => 'critical',
                'recommendation' => __( 'Upgrade PHP to 8.0 or higher. Contact your hosting provider.', 'royal-sentinel' )
            );
        } elseif ( version_compare( $php_version, '8.0', '<' ) ) {
            $issues[] = array(
                'name' => 'PHP Version',
                'current_version' => $php_version,
                'risk_type' => 'environment',
                'severity' => 'high',
                'recommendation' => __( 'PHP 7.4 is end-of-life. Upgrade to PHP 8.0+', 'royal-sentinel' )
            );
        }
        
        // Check WP_DEBUG
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            $issues[] = array(
                'name' => 'Debug Mode Enabled',
                'current_version' => 'Enabled',
                'risk_type' => 'environment',
                'severity' => 'medium',
                'recommendation' => __( 'Disable WP_DEBUG in wp-config.php for production sites', 'royal-sentinel' )
            );
        }
        
        // Check XML-RPC
        if ( apply_filters( 'xmlrpc_enabled', true ) ) {
            $issues[] = array(
                'name' => 'XML-RPC Enabled',
                'current_version' => 'Enabled',
                'risk_type' => 'environment',
                'severity' => 'low',
                'recommendation' => __( 'Disable XML-RPC if not needed for remote publishing', 'royal-sentinel' )
            );
        }
        
        return $issues;
    }
    
    /**
     * Get latest WordPress version
     */
    private function get_latest_wp_version() {
        $response = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/' );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        return $data['offers'][0]['version'] ?? false;
    }
    
    /**
     * Get plugin info from WordPress.org
     */
    private function get_plugin_info( $slug ) {
        $url = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=' . $slug;
        
        $response = wp_remote_get( $url, array( 
            'timeout' => 15,
            'sslverify' => false // For local development
        ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }
    
    /**
     * Get theme info from WordPress.org
     */
    private function get_theme_info( $slug ) {
        $url = 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&slug=' . $slug;
        
        $response = wp_remote_get( $url, array( 
            'timeout' => 15,
            'sslverify' => false // For local development
        ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }
    
    /**
     * Calculate version severity
     */
    private function calculate_version_severity( $current, $latest ) {
        $current_parts = explode( '.', $current );
        $latest_parts = explode( '.', $latest );
        
        // Major version difference
        if ( isset( $current_parts[0] ) && isset( $latest_parts[0] ) && 
             $current_parts[0] < $latest_parts[0] ) {
            return 'high';
        }
        
        // Minor version difference
        if ( isset( $current_parts[1] ) && isset( $latest_parts[1] ) && 
             $current_parts[1] < $latest_parts[1] ) {
            return 'medium';
        }
        
        // Patch version difference
        return 'low';
    }
    
    /**
     * Calculate scan summary
     */
    private function calculate_summary( $results ) {
        $total_software = 0;
        $vulnerable_count = 0;
        $outdated_count = 0;
        $abandoned_count = 0;
        $environment_risks = count( $results['environment'] );
        
        // Count plugins and themes
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $total_software = count( get_plugins() ) + count( wp_get_themes() ) + 1; // +1 for WP core
        
        // Count issues
        foreach ( array( 'core', 'plugins', 'themes', 'environment' ) as $type ) {
            if ( isset( $results[ $type ] ) ) {
                foreach ( $results[ $type ] as $issue ) {
                    $vulnerable_count++;
                    
                    if ( isset( $issue['risk_type'] ) && $issue['risk_type'] === 'outdated' ) {
                        $outdated_count++;
                    } elseif ( isset( $issue['risk_type'] ) && $issue['risk_type'] === 'abandoned' ) {
                        $abandoned_count++;
                    }
                }
            }
        }
        
        $scan_duration = round( ( microtime( true ) - $this->scan_start_time ), 2 );
        
        return array(
            'total_software' => $total_software,
            'vulnerable_count' => $vulnerable_count,
            'outdated_count' => $outdated_count,
            'abandoned_count' => $abandoned_count,
            'environment_risks' => $environment_risks,
            'scan_duration' => $scan_duration
        );
    }
    
    /**
     * Store summary in database
     */
    private function store_summary( $summary ) {
        global $wpdb;
        $table = RS_Database::get_table_name('vuln_summary');
        
        $wpdb->insert( $table, array(
            'scan_date' => current_time( 'mysql' ),
            'total_software' => $summary['total_software'],
            'vulnerable_count' => $summary['vulnerable_count'],
            'outdated_count' => $summary['outdated_count'],
            'abandoned_count' => $summary['abandoned_count'],
            'environment_risks' => $summary['environment_risks'],
            'scan_duration' => $summary['scan_duration']
        ) );
        
        // Keep only last 30 scans
        $wpdb->query( "DELETE FROM $table WHERE id NOT IN (SELECT id FROM (SELECT id FROM $table ORDER BY scan_date DESC LIMIT 30) AS t)" );
    }
    
    /**
     * AJAX: Get cached results
     */
    public function ajax_get_results() {
        check_ajax_referer( 'rs_vuln_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'royal-sentinel' ) ) );
        }
        
        $results = get_transient( 'rs_vuln_scan_results' );
        
        if ( ! $results ) {
            wp_send_json_error( array( 'message' => __( 'No scan results found. Please run a scan.', 'royal-sentinel' ) ) );
        }
        
        wp_send_json_success( $results );
    }
    
    /**
     * Get last scan summary
     */
    public static function get_last_summary() {
        global $wpdb;
        $table = RS_Database::get_table_name('vuln_summary');
        
        return $wpdb->get_row( "SELECT * FROM $table ORDER BY scan_date DESC LIMIT 1" );
    }
}

// Initialize
new RS_Vuln_Scanner();
