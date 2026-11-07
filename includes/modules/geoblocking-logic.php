<?php
/*
 * --------------------------------------------------------------------------
 * ROYAL SECURITY: GEO-BLOCKING (COUNTRY BLOCKING) MODULE
 * --------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RS_GeoBlocking_Logic {

    private $settings;

    public function __construct() {
        $this->settings = get_option( 'rs_security_settings', [] );
        
        // Only run if enabled
        if ( empty( $this->settings['geo_enabled'] ) ) {
            return;
        }
        
        // Check on init (early)
        add_action( 'init', array( $this, 'check_country_access' ), 1 );
    }

    /**
     * Main function to check if visitor should be blocked
     */
    public function check_country_access() {
        // Skip for logged-in admins
        if ( current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Skip for AJAX/CRON
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }
        if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
            return;
        }
        
        // Skip for admin area
        if ( is_admin() ) {
            return;
        }
        
        $client_ip = rs_get_real_ip();
        
        if ( empty( $client_ip ) ) {
            return;
        }
        
        // Check global whitelist
        if ( function_exists('rs_is_trusted_ip') && rs_is_trusted_ip( $client_ip ) ) {
            return;
        }
        
        // Check IP whitelist first
        if ( $this->is_ip_whitelisted( $client_ip ) ) {
            return;
        }
        
        // Get country code
        $country_code = $this->get_country_code( $client_ip );
        
        if ( empty( $country_code ) ) {
            return; // Can't determine country, allow access
        }
        
        // Check if should block
        $should_block = $this->should_block_country( $country_code );
        
        if ( $should_block ) {
            $this->block_access( $country_code );
        }
    }



    /**
     * Check if IP is in whitelist
     */
    private function is_ip_whitelisted( $ip ) {
        $whitelist = isset( $this->settings['geo_whitelist_ips'] ) ? $this->settings['geo_whitelist_ips'] : '';
        
        if ( empty( $whitelist ) ) {
            return false;
        }
        
        $whitelist_ips = array_filter( array_map( 'trim', explode( "\n", $whitelist ) ) );
        
        return in_array( $ip, $whitelist_ips );
    }

    /**
     * Get country code from IP using cached API call
     */
    private function get_country_code( $ip ) {
        // Check transient cache first
        $cache_key = 'rs_geo_' . md5( $ip );
        $cached = get_transient( $cache_key );
        
        if ( $cached !== false ) {
            return $cached;
        }
        
        // Use free IP API (ipapi.co)
        $response = wp_remote_get( 
            'https://ipapi.co/' . $ip . '/country/', 
            array( 
                'timeout' => 5,
                'sslverify' => false 
            ) 
        );
        
        if ( is_wp_error( $response ) ) {
            return '';
        }
        
        $country_code = trim( wp_remote_retrieve_body( $response ) );
        
        // Validate country code (should be 2 letters)
        if ( strlen( $country_code ) !== 2 ) {
            return '';
        }
        
        // Cache for 24 hours
        set_transient( $cache_key, $country_code, DAY_IN_SECONDS );
        
        return strtoupper( $country_code );
    }

    /**
     * Determine if country should be blocked based on mode
     */
    private function should_block_country( $country_code ) {
        $mode = isset( $this->settings['geo_mode'] ) ? $this->settings['geo_mode'] : 'blacklist';
        $countries = isset( $this->settings['geo_countries'] ) ? $this->settings['geo_countries'] : [];
        
        if ( ! is_array( $countries ) || empty( $countries ) ) {
            return false;
        }
        
        $is_in_list = in_array( $country_code, $countries );
        
        if ( $mode === 'blacklist' ) {
            // Blacklist mode: block if country IS in the list
            return $is_in_list;
        } else {
            // Whitelist mode: block if country is NOT in the list
            return ! $is_in_list;
        }
    }

    /**
     * Block access - show 403 or redirect
     */
    private function block_access( $country_code ) {
        // Log the block
        $this->log_block( $country_code );
        
        $action = isset( $this->settings['geo_action'] ) ? $this->settings['geo_action'] : 'block';
        
        if ( $action === 'redirect' && ! empty( $this->settings['geo_redirect_url'] ) ) {
            wp_redirect( esc_url( $this->settings['geo_redirect_url'] ), 302 );
            exit;
        }
        
        // Default: Show 403
        status_header( 403 );
        nocache_headers();
        
        wp_die( 
            '<div style="text-align:center; padding: 50px; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">' .
            '<h1 style="font-size: 48px; margin: 0;">🚫</h1>' .
            '<h2>Access Denied</h2>' .
            '<p style="color: #666;">Access from your region is not permitted.</p>' .
            '<p style="font-size: 12px; color: #999;">Error Code: RS-GEO-' . esc_html($country_code) . '</p>' .
            '</div>',
            'Access Denied - Royal Sentinel',
            array( 'response' => 403 )
        );
    }

    /**
     * Log the geo-block event
     */
    private function log_block( $country_code ) {
        global $wpdb;
        $table = $wpdb->prefix . 'royalsentinel_monitor_logs';
        
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) == $table ) {
            $ip = rs_get_real_ip();
            $wpdb->insert( $table, array(
                'type'       => 'geo_block',
                'message'    => "Blocked visitor from {$country_code} (IP: {$ip})",
                'severity'   => 2,
                'created_at' => current_time( 'mysql' ),
            ) );
        }
    }
}

new RS_GeoBlocking_Logic();
