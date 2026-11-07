<?php
/*
 * --------------------------------------------------------------------------
 * ROYAL SECURITY: REAL-TIME THREAT DEFENSE MODULE
 * --------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Realtime_Defense_Logic {

    private $settings;
    private $client_ip;

    public function __construct() {
        $this->settings = get_option( 'rs_security_settings', [] );
        $this->client_ip = rs_get_real_ip();
        
        // Hook early in WordPress lifecycle
        add_action( 'init', array( $this, 'run_defense_checks' ), 1 );
        
        // 404 Detection
        add_action( 'template_redirect', array( $this, 'check_404_attack' ), 1 );
    }



    /**
     * Main defense checks runner
     */
    public function run_defense_checks() {
        // Skip for all logged-in users and admins
        if ( is_user_logged_in() || current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Skip for AJAX and REST API internal calls
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return;
        }
        
        // Skip for WordPress AJAX requests (admin-ajax.php)
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }
        
        // Skip for WordPress admin area
        if ( is_admin() ) {
            return;
        }
        
        // Skip for whitelisted IPs
        if ( function_exists( 'rs_is_trusted_ip' ) && rs_is_trusted_ip( $this->client_ip ) ) {
            return;
        }
        
        // Rate Limiting
        if ( ! empty( $this->settings['rt_rate_limit'] ) ) {
            $this->check_rate_limit();
        }
        
        // Bad Query Strings
        if ( ! empty( $this->settings['rt_bad_query'] ) ) {
            $this->check_bad_query_strings();
        }
        
        // Bad User Agents
        if ( ! empty( $this->settings['rt_bad_ua'] ) ) {
            $this->check_bad_user_agents();
        }
    }

    /**
     * Rate Limiting Check
     */
    private function check_rate_limit() {
        if ( empty( $this->client_ip ) ) return;
        
        $max_requests = isset( $this->settings['rt_rate_limit_max'] ) ? intval( $this->settings['rt_rate_limit_max'] ) : 60;
        $window = isset( $this->settings['rt_rate_limit_window'] ) ? intval( $this->settings['rt_rate_limit_window'] ) : 60;
        
        $transient_key = 'rs_rl_' . md5( $this->client_ip );
        $rate_data = get_transient( $transient_key );
        
        if ( $rate_data === false || ! is_array( $rate_data ) ) {
            $rate_data = array( 'count' => 1, 'expires' => time() + $window );
            set_transient( $transient_key, $rate_data, $window );
        } else {
            $rate_data['count'] = intval( $rate_data['count'] ) + 1;
            $remaining = max( 1, $rate_data['expires'] - time() );
            set_transient( $transient_key, $rate_data, $remaining );
            
            if ( $rate_data['count'] > $max_requests ) {
                $this->log_and_block( 'rate_limit', "Rate limit exceeded: {$rate_data['count']} requests from {$this->client_ip}", 2 );
            }
        }
    }

    /**
     * Check for malicious query strings
     */
    private function check_bad_query_strings() {
        $query_string = isset( $_SERVER['QUERY_STRING'] ) ? $_SERVER['QUERY_STRING'] : '';
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        
        // Patterns to detect
        $bad_patterns = array(
            // SQL Injection
            '/union[\s\+]+select/i',
            '/select[\s\+]+.*[\s\+]+from/i',
            '/insert[\s\+]+into/i',
            '/drop[\s\+]+table/i',
            '/update[\s\+]+.*[\s\+]+set/i',
            '/delete[\s\+]+from/i',
            '/;[\s]*shutdown/i',
            
            // XSS Attempts
            '/<script[^>]*>/i',
            '/javascript:/i',
            '/on(click|load|error|mouseover)[\s]*=/i',
            
            // Path Traversal
            '/\.\.\//',
            '/\.\.\\\\/',
            
            // Remote File Inclusion
            '/(https?|ftp):\/\/[^\/\s]+\/.*\.(php|asp|jsp|cgi)/i',
            
            // Null Byte
            '/\x00/',
            
            // Common exploit attempts
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/file_get_contents\s*\(/i',
            '/system\s*\(/i',
            '/passthru\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
        );
        
        $check_string = $query_string . $request_uri;
        
        foreach ( $bad_patterns as $pattern ) {
            if ( preg_match( $pattern, $check_string ) ) {
                $this->log_and_block( 'bad_query', "Malicious query detected from {$this->client_ip}: " . substr($check_string, 0, 100), 3 );
            }
        }
    }

    /**
     * Check for bad user agents
     */
    private function check_bad_user_agents() {
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( $_SERVER['HTTP_USER_AGENT'] ) : '';
        
        if ( empty( $user_agent ) ) {
            // Empty user agent is suspicious but not blocked by default
            return;
        }
        
        $bad_agents = array(
            'sqlmap',
            'nikto',
            'nmap',
            'masscan',
            'nessus',
            'openvas',
            'dirbuster',
            'gobuster',
            'wfuzz',
            'nuclei',
            'xsstrike',
            'acunetix',
            'netsparker',
            'burp',
            'havij',
            'zmeu',
            'morfeus',
            'scanner',
            'exploit',
            'libwww-perl',
            'python-requests', // May need to whitelist for legitimate uses
            'curl/', // Be careful with this one
            'wget/',
        );
        
        foreach ( $bad_agents as $bad ) {
            if ( strpos( $user_agent, $bad ) !== false ) {
                $this->log_and_block( 'bad_ua', "Blocked bad user agent from {$this->client_ip}: {$bad}", 2 );
            }
        }
    }

    /**
     * 404 Attack Detection
     */
    public function check_404_attack() {
        if ( empty( $this->settings['rt_404_detect'] ) ) {
            return;
        }
        
        if ( ! is_404() ) {
            return;
        }
        
        if ( empty( $this->client_ip ) ) {
            return;
        }
        
        // Skip for known safe paths
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $safe_extensions = array( '.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.ico', '.woff', '.woff2', '.ttf', '.svg' );
        
        foreach ( $safe_extensions as $ext ) {
            if ( stripos( $request_uri, $ext ) !== false ) {
                return;
            }
        }
        
        $threshold = isset( $this->settings['rt_404_threshold'] ) ? intval( $this->settings['rt_404_threshold'] ) : 20;
        $window = isset( $this->settings['rt_404_window'] ) ? intval( $this->settings['rt_404_window'] ) : 60;
        
        $transient_key = 'rs_404_' . md5( $this->client_ip );
        $rate_data = get_transient( $transient_key );
        
        if ( $rate_data === false || ! is_array( $rate_data ) ) {
            $rate_data = array( 'count' => 1, 'expires' => time() + $window );
            set_transient( $transient_key, $rate_data, $window );
        } else {
            $rate_data['count'] = intval( $rate_data['count'] ) + 1;
            $remaining = max( 1, $rate_data['expires'] - time() );
            set_transient( $transient_key, $rate_data, $remaining );
            
            if ( $rate_data['count'] >= $threshold ) {
                $this->log_and_block( '404_attack', "404 attack detected: {$rate_data['count']} 404s from {$this->client_ip}", 2 );
            }
        }
    }

    /**
     * Log the threat and block the request
     */
    private function log_and_block( $type, $message, $severity = 2 ) {
        if ( function_exists( 'rs_track_firewall_violation' ) ) {
            rs_track_firewall_violation( $this->client_ip, $message );
        }

        // Log to database
        if ( class_exists( 'RS_Monitoring_Core' ) || function_exists( 'rs_log_security_event' ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'royalsentinel_monitor_logs';
            
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) == $table ) {
                $wpdb->insert( $table, array(
                    'type'       => 'realtime_' . $type,
                    'message'    => $message,
                    'severity'   => $severity,
                    'created_at' => current_time( 'mysql' ),
                ) );
            }
        }
        
        // Block the request
        status_header( 403 );
        nocache_headers();
        
        // Simple block page
        wp_die( 
            '<h1>Access Denied</h1><p>Your request has been blocked by our security system.</p><p><small>Error Code: RS-' . strtoupper($type) . '</small></p>',
            'Access Denied - Royal Sentinel',
            array( 'response' => 403 )
        );
    }
}

new RS_Realtime_Defense_Logic();
