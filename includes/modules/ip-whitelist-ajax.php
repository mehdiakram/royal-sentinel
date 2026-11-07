<?php
/**
 * IP Whitelist AJAX Handlers
 * Royal Sentinel - Real-time Defense Module
 */

if ( ! defined( 'ABSPATH' ) ) exit;


/**
 * AJAX: Get current IP
 */
add_action( 'wp_ajax_rs_get_current_ip', 'rs_ajax_get_current_ip' );
function rs_ajax_get_current_ip() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied', 'royal-sentinel' ) ] );
    }
    
    $ip = rs_get_client_ip();
    wp_send_json_success( [ 'ip' => $ip ] );
}

/**
 * AJAX: Add IP to whitelist
 */
add_action( 'wp_ajax_rs_add_whitelist_ip', 'rs_ajax_add_whitelist_ip' );
function rs_ajax_add_whitelist_ip() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied', 'royal-sentinel' ) ] );
    }
    
    check_ajax_referer( 'rs_whitelist_nonce', 'nonce' );
    
    $ip = isset( $_POST['ip'] ) ? sanitize_text_field( $_POST['ip'] ) : '';
    
    if ( empty( $ip ) ) {
        wp_send_json_error( [ 'message' => __( 'Please enter an IP address', 'royal-sentinel' ) ] );
    }
    
    if ( ! rs_validate_ip_or_cidr( $ip ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid IP address or CIDR format', 'royal-sentinel' ) ] );
    }
    
    $settings = get_option( 'rs_security_settings', [] );
    $whitelist = isset( $settings['rt_ip_whitelist'] ) ? $settings['rt_ip_whitelist'] : [];
    
    // Check for duplicates
    foreach ( $whitelist as $entry ) {
        if ( isset( $entry['ip'] ) && $entry['ip'] === $ip ) {
            wp_send_json_error( [ 'message' => __( 'This IP is already whitelisted', 'royal-sentinel' ) ] );
        }
    }
    
    // Add new entry
    $whitelist[] = [
        'ip'    => $ip,
        'added' => current_time( 'mysql' ),
        'by'    => get_current_user_id()
    ];
    
    $settings['rt_ip_whitelist'] = $whitelist;
    update_option( 'rs_security_settings', $settings );

    // Immediately purge any existing bans or lockouts for this newly whitelisted IP
    if ( class_exists( 'RS_Database' ) ) {
        global $wpdb;
        $tbl_badips = RS_Database::get_table_name( 'badips' );
        $tbl_lockouts = RS_Database::get_table_name( 'lockouts' );
        if ( $tbl_badips ) {
            $wpdb->delete( $tbl_badips, [ 'bad_ip' => $ip ] );
        }
        if ( $tbl_lockouts ) {
            $wpdb->delete( $tbl_lockouts, [ 'ip' => $ip ] );
        }
    }
    delete_transient( 'rs_rl_' . md5( $ip ) );
    delete_transient( 'rs_temp_ban_' . md5( $ip ) );

    // If Cloudflare is connected, also whitelist in Cloudflare
    if ( function_exists( 'rs_api_whitelist_ip' ) ) {
        rs_api_whitelist_ip( $ip, 'Admin Whitelist' );
    }
    
    wp_send_json_success( [ 
        'message' => sprintf( __( 'IP %s added to whitelist', 'royal-sentinel' ), $ip ),
        'whitelist' => $whitelist
    ] );
}

/**
 * AJAX: Remove IP from whitelist
 */
add_action( 'wp_ajax_rs_remove_whitelist_ip', 'rs_ajax_remove_whitelist_ip' );
function rs_ajax_remove_whitelist_ip() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied', 'royal-sentinel' ) ] );
    }
    
    check_ajax_referer( 'rs_whitelist_nonce', 'nonce' );
    
    $ip = isset( $_POST['ip'] ) ? sanitize_text_field( $_POST['ip'] ) : '';
    
    if ( empty( $ip ) ) {
        wp_send_json_error( [ 'message' => __( 'No IP specified', 'royal-sentinel' ) ] );
    }
    
    $settings = get_option( 'rs_security_settings', [] );
    $whitelist = isset( $settings['rt_ip_whitelist'] ) ? $settings['rt_ip_whitelist'] : [];
    
    // Find and remove
    $found = false;
    foreach ( $whitelist as $key => $entry ) {
        if ( isset( $entry['ip'] ) && $entry['ip'] === $ip ) {
            unset( $whitelist[ $key ] );
            $found = true;
            break;
        }
    }
    
    if ( ! $found ) {
        wp_send_json_error( [ 'message' => __( 'IP not found in whitelist', 'royal-sentinel' ) ] );
    }
    
    // Re-index array
    $whitelist = array_values( $whitelist );
    
    $settings['rt_ip_whitelist'] = $whitelist;
    update_option( 'rs_security_settings', $settings );
    
    wp_send_json_success( [ 
        'message' => sprintf( __( 'IP %s removed from whitelist', 'royal-sentinel' ), $ip ),
        'whitelist' => $whitelist
    ] );
}

/**
 * AJAX: Get whitelist
 */
add_action( 'wp_ajax_rs_get_whitelist', 'rs_ajax_get_whitelist' );
function rs_ajax_get_whitelist() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied', 'royal-sentinel' ) ] );
    }
    
    $settings = get_option( 'rs_security_settings', [] );
    $whitelist = isset( $settings['rt_ip_whitelist'] ) ? $settings['rt_ip_whitelist'] : [];
    
    wp_send_json_success( [ 'whitelist' => $whitelist ] );
}

/**
 * Auto-whitelist admin IP on login
 */
add_action( 'wp_login', 'rs_auto_whitelist_admin_ip', 10, 2 );
function rs_auto_whitelist_admin_ip( $user_login, $user ) {
    $settings = get_option( 'rs_security_settings', [] );
    
    $auto_whitelist = isset( $settings['rt_auto_whitelist_admin'] ) ? $settings['rt_auto_whitelist_admin'] : 1;
    if ( empty( $auto_whitelist ) ) {
        return;
    }
    
    // Only for admins
    if ( ! user_can( $user, 'manage_options' ) ) {
        return;
    }
    
    $ip = rs_get_real_ip();
    if ( empty( $ip ) || in_array( $ip, [ '127.0.0.1', '::1', 'localhost' ] ) ) {
        return;
    }
    
    $whitelist = isset( $settings['rt_ip_whitelist'] ) ? $settings['rt_ip_whitelist'] : [];
    foreach ( $whitelist as $entry ) {
        if ( isset( $entry['ip'] ) && $entry['ip'] === $ip ) {
            return; // Already whitelisted
        }
    }
    
    $whitelist[] = [
        'ip'    => $ip,
        'added' => current_time( 'mysql' ),
        'by'    => $user->ID,
        'auto'  => true
    ];
    
    $settings['rt_ip_whitelist'] = $whitelist;
    update_option( 'rs_security_settings', $settings );

    // Sync with Cloudflare if available
    if ( function_exists( 'rs_api_whitelist_ip' ) ) {
        rs_api_whitelist_ip( $ip, 'Admin Auto-Whitelist: ' . $user_login );
    }
}

/**
 * Auto-whitelist active admin IP in admin dashboard
 */
add_action( 'admin_init', 'rs_auto_whitelist_active_admin_ip', 1 );
function rs_auto_whitelist_active_admin_ip() {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $settings = get_option( 'rs_security_settings', [] );
    $auto_whitelist = isset( $settings['rt_auto_whitelist_admin'] ) ? $settings['rt_auto_whitelist_admin'] : 1;
    if ( empty( $auto_whitelist ) ) {
        return;
    }
    
    $ip = rs_get_real_ip();
    if ( empty( $ip ) || in_array( $ip, [ '127.0.0.1', '::1', 'localhost' ] ) ) {
        return;
    }
    
    $whitelist = isset( $settings['rt_ip_whitelist'] ) ? $settings['rt_ip_whitelist'] : [];
    foreach ( $whitelist as $entry ) {
        if ( isset( $entry['ip'] ) && $entry['ip'] === $ip ) {
            return;
        }
    }
    
    $user = wp_get_current_user();
    $whitelist[] = [
        'ip'    => $ip,
        'added' => current_time( 'mysql' ),
        'by'    => $user->ID,
        'auto'  => true
    ];
    
    $settings['rt_ip_whitelist'] = $whitelist;
    update_option( 'rs_security_settings', $settings );

    if ( function_exists( 'rs_api_whitelist_ip' ) ) {
        rs_api_whitelist_ip( $ip, 'Admin Active Auto-Whitelist: ' . $user->user_login );
    }
}
