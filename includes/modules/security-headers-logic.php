<?php
/*
 * --------------------------------------------------------------------------
 * ROYAL SECURITY: SECURITY HEADERS LOGIC MODULE
 * --------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Security_Headers_Logic {

    public function __construct() {
        // Send headers early in the request lifecycle
        add_action( 'send_headers', array( $this, 'send_security_headers' ), 1 );
        add_action( 'login_init', array( $this, 'send_security_headers' ), 1 );
        add_action( 'init', array( $this, 'early_header_cleanup' ), 1 );
    }

    /**
     * Remove leaking technology headers as early as possible
     */
    public function early_header_cleanup() {
        if ( headers_sent() ) {
            return;
        }

        $settings = get_option( 'rs_security_settings', [] );
        if ( ! empty( $settings['header_hide_powered_by'] ) ) {
            @header_remove( 'X-Powered-By' );
            @header_remove( 'Server' );
            @header_remove( 'X-Turbo-Charged-By' );
            @ini_set( 'expose_php', 'off' );
        }
    }

    /**
     * Send HTTP security headers based on settings
     */
    public function send_security_headers() {
        if ( headers_sent() ) {
            return;
        }

        $settings = get_option( 'rs_security_settings', [] );

        // Hide Server & PHP Information (Server / X-Powered-By / X-Turbo-Charged-By)
        if ( ! empty( $settings['header_hide_powered_by'] ) ) {
            @header_remove( 'X-Powered-By' );
            @header_remove( 'Server' );
            @header_remove( 'X-Turbo-Charged-By' );
            @ini_set( 'expose_php', 'off' );
        }
        
        // X-Content-Type-Options
        if ( ! empty( $settings['header_xcto'] ) ) {
            header( 'X-Content-Type-Options: nosniff' );
        }
        
        // X-XSS-Protection
        if ( ! empty( $settings['header_xss'] ) ) {
            header( 'X-XSS-Protection: 1; mode=block' );
        }
        
        // X-Frame-Options
        if ( ! empty( $settings['header_xfo'] ) ) {
            $xfo_mode = isset( $settings['xfo_mode'] ) ? $settings['xfo_mode'] : 'SAMEORIGIN';
            header( 'X-Frame-Options: ' . $xfo_mode );
        }
        
        // Referrer-Policy
        if ( ! empty( $settings['header_rp'] ) ) {
            $rp_mode = isset( $settings['rp_mode'] ) ? $settings['rp_mode'] : 'strict-origin-when-cross-origin';
            header( 'Referrer-Policy: ' . $rp_mode );
        }
        
        // Permissions-Policy
        if ( ! empty( $settings['header_pp'] ) ) {
            header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
        }

        // Cross-Origin-Opener-Policy (COOP)
        if ( ! empty( $settings['header_coop'] ) ) {
            $coop_mode = isset( $settings['coop_mode'] ) ? $settings['coop_mode'] : 'same-origin';
            header( 'Cross-Origin-Opener-Policy: ' . $coop_mode );
        }

        // Cross-Origin-Resource-Policy (CORP)
        if ( ! empty( $settings['header_corp'] ) ) {
            $corp_mode = isset( $settings['corp_mode'] ) ? $settings['corp_mode'] : 'same-origin';
            header( 'Cross-Origin-Resource-Policy: ' . $corp_mode );
        }

        // X-Permitted-Cross-Domain-Policies
        if ( ! empty( $settings['header_xpcdp'] ) ) {
            header( 'X-Permitted-Cross-Domain-Policies: none' );
        }
        
        // Strict-Transport-Security (HSTS)
        $is_https = is_ssl() 
            || ( isset( $_SERVER['HTTPS'] ) && strtolower( $_SERVER['HTTPS'] ) === 'on' ) 
            || ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && strtolower( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) === 'https' )
            || ( isset( $_SERVER['HTTP_CF_VISITOR'] ) && strpos( $_SERVER['HTTP_CF_VISITOR'], 'https' ) !== false );

        if ( ! empty( $settings['header_hsts'] ) && $is_https ) {
            $max_age = isset( $settings['hsts_max_age'] ) ? intval( $settings['hsts_max_age'] ) : 31536000;
            header( 'Strict-Transport-Security: max-age=' . $max_age . '; includeSubDomains; preload' );
        }
        
        // Content-Security-Policy
        if ( ! empty( $settings['header_csp'] ) ) {
            $csp_policy = isset( $settings['csp_policy'] ) 
                ? stripslashes( $settings['csp_policy'] ) 
                : "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';";
            
            // Clean up any remaining backslashes
            $csp_policy = stripslashes( $csp_policy );
            
            // Sanitize the policy - remove newlines and extra spaces
            $csp_policy = preg_replace( '/\s+/', ' ', $csp_policy );
            $csp_policy = trim( $csp_policy );
            
            header( 'Content-Security-Policy: ' . $csp_policy );
        }
    }
}

new RS_Security_Headers_Logic();
