<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Royal Sentinel Honeypot Logic
 * Core functionality for honeypot anti-spam protection
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

// ============================
// 1. INITIALIZATION
// ============================

/**
 * Initialize honeypot settings on first run
 */
function rs_honeypot_init_settings() {
    // Generate unique field name if not exists
    if ( ! get_option( 'rs_honeypot_field_name' ) ) {
        add_option( 'rs_honeypot_field_name', rs_honeypot_generate_field_name() );
    }
    
    // Default settings
    $default_settings = array(
        'error_message'      => 'Spam detected! Your submission has been blocked.',
        'time_check_enabled' => 1,
        'time_min_seconds'   => 3,
        'time_max_seconds'   => 0,  // 0 = unlimited
        'auto_block_enabled' => 1,
        'auto_block_threshold' => 5,
        'retention_days'     => 30,
        'test_widget_disabled' => 0,
    );
    
    if ( ! get_option( 'rs_honeypot_settings' ) ) {
        add_option( 'rs_honeypot_settings', $default_settings );
    }
    
    // Initialize stats
    if ( ! get_option( 'rs_honeypot_stats' ) ) {
        $initial_stats = array(
            'total' => array(
                'today' => array( 'date' => date('Ymd'), 'count' => 0 ),
                'week'  => array( 'date' => date('Ymd'), 'count' => 0 ),
                'month' => array( 'date' => date('Ymd'), 'count' => 0 ),
                'all_time' => 0
            )
        );
        add_option( 'rs_honeypot_stats', json_encode( $initial_stats ) );
    }
}
add_action( 'admin_init', 'rs_honeypot_init_settings' );

// ============================
// 2. FIELD GENERATION
// ============================

/**
 * Generate unique honeypot field name
 */
function rs_honeypot_generate_field_name() {
    $chars = 'abcdefghijklmnopqrstuvwxyz';
    $name = '';
    for ( $i = 0; $i < 6; $i++ ) {
        $name .= $chars[ rand( 0, strlen( $chars ) - 1 ) ];
    }
    return $name . rand( 1000, 9999 );
}

/**
 * Generate unique field value for each page load
 */
function rs_honeypot_generate_field_value() {
    return rand( 1111, 999999 );
}

/**
 * Get field info for JavaScript
 * Note: show_test is always 'no' here to avoid caching issues.
 * JavaScript will make an AJAX call to check admin status in real-time.
 */
function rs_honeypot_field_info() {
    $settings = get_option( 'rs_honeypot_settings', array() );
    
    $data = array(
        'field_name'  => get_option( 'rs_honeypot_field_name', 'rs_hp_field' ),
        'field_value' => rs_honeypot_generate_field_value(),
        'show_test'   => 'no', // Always 'no' - JS will check via AJAX to bypass cache
        'time_check'  => ! empty( $settings['time_check_enabled'] ) ? 'yes' : 'no',
        'ajax_url'    => '', // Default empty - no AJAX for guests
    );
    
    // Only pass ajax_url for logged-in users to avoid wasteful AJAX calls from every visitor
    if ( is_user_logged_in() ) {
        $data['ajax_url'] = admin_url( 'admin-ajax.php' );
    }
    
    return $data;
}

// ============================
// 3. SPAM DETECTION
// ============================

/**
 * Check if submission is spam
 * 
 * @param array $post_data The $_POST data
 * @return bool|string False if not spam, reason string if spam
 */
function rs_honeypot_check_is_spam( $post_data ) {
    $field_name = get_option( 'rs_honeypot_field_name', 'rs_hp_field' );
    $settings = get_option( 'rs_honeypot_settings', array() );
    
    // Check 1: Honeypot field must exist and have correct format
    // The hidden field should exist (added by JS) and alt_s should be empty
    if ( ! isset( $post_data[ $field_name ] ) ) {
        return 'honeypot_missing';
    }
    
    // Check 2: Alt field (visible trap) should be empty
    if ( isset( $post_data['rs_alt_email'] ) && ! empty( $post_data['rs_alt_email'] ) ) {
        return 'honeypot_filled';
    }
    
    // Check 3: Time-based validation
    if ( ! empty( $settings['time_check_enabled'] ) ) {
        $min_time = isset( $settings['time_min_seconds'] ) ? intval( $settings['time_min_seconds'] ) : 3;
        $max_time = isset( $settings['time_max_seconds'] ) ? intval( $settings['time_max_seconds'] ) : 0;
        
        if ( isset( $post_data['rs_form_time'] ) ) {
            $form_time = intval( $post_data['rs_form_time'] );
            $current_time = time();
            $elapsed = $current_time - $form_time;
            
            // Too fast (bot behavior)
            if ( $min_time > 0 && $elapsed < $min_time ) {
                return 'time_too_fast';
            }
            
            // Too slow (optional, usually disabled)
            if ( $max_time > 0 && $elapsed > $max_time ) {
                return 'time_too_slow';
            }
        }
    }
    
    return false; // Not spam
}

/**
 * Get error message for display
 */
function rs_honeypot_get_error_message() {
    $settings = get_option( 'rs_honeypot_settings', array() );
    return isset( $settings['error_message'] ) 
        ? $settings['error_message'] 
        : 'Spam detected! Your submission has been blocked.';
}

// ============================
// 4. LOGGING & STATS
// ============================

/**
 * Log spam attempt to database
 */
function rs_honeypot_log_spam( $source, $ip, $reason, $form_data = array() ) {
    global $wpdb;
    $table = RS_Database::get_table_name( 'honeypot_logs' );
    
    $wpdb->insert( $table, array(
        'ip_address'     => $ip,
        'source'         => $source,
        'blocked_reason' => $reason,
        'user_agent'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 500 ) : '',
        'form_data'      => json_encode( $form_data ),
        'created_at'     => current_time( 'mysql' ),
    ), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );
}

/**
 * Update statistics
 */
function rs_honeypot_update_stats( $source ) {
    $stats = json_decode( get_option( 'rs_honeypot_stats', '{}' ), true );
    if ( ! is_array( $stats ) ) {
        $stats = array();
    }
    
    $today = date( 'Ymd' );
    $time_periods = array( 'today', 'week', 'month' );
    
    // Initialize 'total' if not exists
    if ( ! isset( $stats['total'] ) || ! is_array( $stats['total'] ) ) {
        $stats['total'] = array(
            'today' => array( 'date' => $today, 'count' => 0 ),
            'week'  => array( 'date' => $today, 'count' => 0 ),
            'month' => array( 'date' => $today, 'count' => 0 ),
            'all_time' => 0
        );
    }
    
    // Initialize source if not exists
    if ( ! isset( $stats[ $source ] ) || ! is_array( $stats[ $source ] ) ) {
        $stats[ $source ] = array(
            'today' => array( 'date' => $today, 'count' => 0 ),
            'week'  => array( 'date' => $today, 'count' => 0 ),
            'month' => array( 'date' => $today, 'count' => 0 ),
            'all_time' => 0
        );
    }
    
    // Update counts
    foreach ( $time_periods as $period ) {
        // Ensure period arrays exist
        if ( ! isset( $stats['total'][ $period ] ) || ! is_array( $stats['total'][ $period ] ) ) {
            $stats['total'][ $period ] = array( 'date' => $today, 'count' => 0 );
        }
        if ( ! isset( $stats[ $source ][ $period ] ) || ! is_array( $stats[ $source ][ $period ] ) ) {
            $stats[ $source ][ $period ] = array( 'date' => $today, 'count' => 0 );
        }
        
        // Check if we need to reset (new day/week/month)
        if ( rs_honeypot_check_period( $stats['total'][ $period ]['date'], $period ) ) {
            $stats['total'][ $period ]['count'] += 1;
            $stats[ $source ][ $period ]['count'] += 1;
        } else {
            $stats['total'][ $period ]['count'] = 1;
            $stats[ $source ][ $period ]['count'] = 1;
        }
        $stats['total'][ $period ]['date'] = $today;
        $stats[ $source ][ $period ]['date'] = $today;
    }
    
    // All time
    $stats['total']['all_time'] = ( isset( $stats['total']['all_time'] ) ? intval( $stats['total']['all_time'] ) : 0 ) + 1;
    $stats[ $source ]['all_time'] = ( isset( $stats[ $source ]['all_time'] ) ? intval( $stats[ $source ]['all_time'] ) : 0 ) + 1;
    
    update_option( 'rs_honeypot_stats', json_encode( $stats ) );
}

/**
 * Check if date is within period
 */
function rs_honeypot_check_period( $timestamp, $period ) {
    switch ( $period ) {
        case 'today':
            return date( 'Ymd' ) === $timestamp;
            
        case 'week':
            $first_day = date( 'Ymd', strtotime( 'monday this week' ) );
            $last_day  = date( 'Ymd', strtotime( 'sunday this week' ) );
            return $timestamp >= $first_day && $timestamp <= $last_day;
            
        case 'month':
            return date( 'Ym', strtotime( $timestamp ) ) === date( 'Ym' );
    }
    return false;
}

// ============================
// 5. AUTO IP BLOCKING
// ============================

/**
 * Check and auto-block IP if threshold exceeded
 */
function rs_honeypot_maybe_block_ip( $ip, $source ) {
    $settings = get_option( 'rs_honeypot_settings', array() );
    
    if ( empty( $settings['auto_block_enabled'] ) ) {
        return;
    }
    
    $threshold = isset( $settings['auto_block_threshold'] ) ? intval( $settings['auto_block_threshold'] ) : 5;
    
    global $wpdb;
    $table = RS_Database::get_table_name( 'honeypot_logs' );
    
    // Count recent attempts from this IP (last 24 hours)
    $count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE ip_address = %s AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        $ip
    ) );
    
    if ( $count >= $threshold ) {
        // Add to permanent ban list
        RS_Database::block_ip_permanently( $ip, 'honeypot:' . $source );
    }
}

// ============================
// 6. INTEGRATION HELPER
// ============================

/**
 * Check if specific integration is enabled
 */
function rs_honeypot_is_integration_enabled( $slug ) {
    $integrations = get_option( 'rs_honeypot_integrations', array() );
    
    // By default, all integrations are enabled
    if ( empty( $integrations ) ) {
        return true;
    }
    
    return isset( $integrations[ $slug ] ) ? (bool) $integrations[ $slug ] : true;
}

/**
 * Handle spam detection - main entry point for integrations
 */
function rs_honeypot_handle_spam( $source, $post_data = array() ) {
    $ip = rs_honeypot_get_client_ip();
    $reason = rs_honeypot_check_is_spam( $post_data );
    
    if ( $reason !== false ) {
        // Log the attempt
        rs_honeypot_log_spam( $source, $ip, $reason, $post_data );
        
        // Update statistics
        rs_honeypot_update_stats( $source );
        
        // Maybe block IP
        rs_honeypot_maybe_block_ip( $ip, $source );
        
        return true; // Is spam
    }
    
    return false; // Not spam
}

/**
 * Get client IP address
 */
function rs_honeypot_get_client_ip() {
    return rs_get_real_ip();
}

// ============================
// 7. CRON CLEANUP
// ============================

/**
 * Schedule cleanup cron
 */
function rs_honeypot_schedule_cleanup() {
    if ( ! wp_next_scheduled( 'rs_honeypot_cleanup_logs' ) ) {
        wp_schedule_event( time(), 'daily', 'rs_honeypot_cleanup_logs' );
    }
}
add_action( 'wp', 'rs_honeypot_schedule_cleanup' );

/**
 * Cleanup old logs
 */
function rs_honeypot_do_cleanup() {
    global $wpdb;
    $table = RS_Database::get_table_name( 'honeypot_logs' );
    $settings = get_option( 'rs_honeypot_settings', array() );
    $retention = isset( $settings['retention_days'] ) ? intval( $settings['retention_days'] ) : 30;
    
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $retention
    ) );
}
add_action( 'rs_honeypot_cleanup_logs', 'rs_honeypot_do_cleanup' );

// ============================
// 8. FRONTEND SCRIPTS
// ============================

/**
 * Enqueue honeypot scripts on frontend
 */
function rs_honeypot_enqueue_scripts() {
    // Don't load in admin
    if ( is_admin() ) {
        return;
    }
    
    wp_enqueue_script(
        'rs-honeypot',
        ROYALSENTINEL_PLUGIN_URL . 'assets/js/rs-honeypot.js',
        array( 'jquery' ),
        ROYALSENTINEL_VERSION,
        true
    );
    
    wp_localize_script( 'rs-honeypot', 'rs_honeypot_info', rs_honeypot_field_info() );
}
add_action( 'wp_enqueue_scripts', 'rs_honeypot_enqueue_scripts' );
add_action( 'login_enqueue_scripts', 'rs_honeypot_enqueue_scripts' );

/**
 * Add honeypot CSS directly to head (always loads)
 */
function rs_honeypot_inline_css() {
    // Don't load in admin
    if ( is_admin() ) {
        return;
    }
    ?>
    <style id="rs-honeypot-css">
        .rs-hp-container {
            position: absolute !important;
            left: -9999px !important;
            top: -9999px !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
            visibility: hidden !important;
            z-index: -9999 !important;
        }
        .rs-hp-container * {
            display: none !important;
        }
    </style>
    <?php
}
add_action( 'wp_head', 'rs_honeypot_inline_css', 1 );
add_action( 'login_head', 'rs_honeypot_inline_css', 1 );

// ============================
// 9. AJAX HANDLERS
// ============================

/**
 * Check if current user can see test widget (bypasses cache)
 */
add_action( 'wp_ajax_rs_honeypot_check_admin', 'rs_ajax_honeypot_check_admin' );
add_action( 'wp_ajax_nopriv_rs_honeypot_check_admin', 'rs_ajax_honeypot_check_admin' );
function rs_ajax_honeypot_check_admin() {
    $settings = get_option( 'rs_honeypot_settings', array() );
    $show_test = false;
    
    // Only show test widget for logged-in admins with test widget enabled
    if ( is_user_logged_in() && current_user_can( 'manage_options' ) && empty( $settings['test_widget_disabled'] ) ) {
        $show_test = true;
    }
    
    wp_send_json( array( 'show_test' => $show_test ? 'yes' : 'no' ) );
}

/**
 * Save honeypot settings via AJAX
 */
add_action( 'wp_ajax_rs_save_honeypot_settings', 'rs_ajax_save_honeypot_settings' );
function rs_ajax_save_honeypot_settings() {
    check_ajax_referer( 'rs_honeypot_nonce', '_wpnonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
    }
    
    // Save field name
    if ( isset( $_POST['field_name'] ) ) {
        update_option( 'rs_honeypot_field_name', sanitize_key( $_POST['field_name'] ) );
    }
    
    // Save settings array
    if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
        $settings = array_map( 'sanitize_text_field', $_POST['settings'] );
        // Ensure numeric values
        $settings['time_min_seconds'] = isset( $settings['time_min_seconds'] ) ? intval( $settings['time_min_seconds'] ) : 3;
        $settings['time_max_seconds'] = isset( $settings['time_max_seconds'] ) ? intval( $settings['time_max_seconds'] ) : 0;
        $settings['auto_block_threshold'] = isset( $settings['auto_block_threshold'] ) ? intval( $settings['auto_block_threshold'] ) : 5;
        $settings['retention_days'] = isset( $settings['retention_days'] ) ? intval( $settings['retention_days'] ) : 30;
        
        update_option( 'rs_honeypot_settings', $settings );
    }
    
    wp_send_json_success( 'Settings saved' );
}

/**
 * Save integrations via AJAX
 */
add_action( 'wp_ajax_rs_save_honeypot_integrations', 'rs_ajax_save_honeypot_integrations' );
function rs_ajax_save_honeypot_integrations() {
    check_ajax_referer( 'rs_honeypot_nonce', '_wpnonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
    }
    
    if ( isset( $_POST['integrations'] ) && is_array( $_POST['integrations'] ) ) {
        $integrations = array();
        foreach ( $_POST['integrations'] as $key => $value ) {
            $integrations[ sanitize_key( $key ) ] = (int) $value;
        }
        update_option( 'rs_honeypot_integrations', $integrations );
    }
    
    wp_send_json_success( 'Integrations saved' );
}

/**
 * Regenerate field name via AJAX
 */
add_action( 'wp_ajax_rs_honeypot_regenerate_field', 'rs_ajax_honeypot_regenerate_field' );
function rs_ajax_honeypot_regenerate_field() {
    check_ajax_referer( 'rs_honeypot_nonce', '_wpnonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
    }
    
    $new_name = rs_honeypot_generate_field_name();
    update_option( 'rs_honeypot_field_name', $new_name );
    
    wp_send_json_success( array( 'field_name' => $new_name ) );
}

/**
 * Reset statistics via AJAX
 */
add_action( 'wp_ajax_rs_honeypot_reset_stats', 'rs_ajax_honeypot_reset_stats' );
function rs_ajax_honeypot_reset_stats() {
    check_ajax_referer( 'rs_honeypot_nonce', '_wpnonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
    }
    
    $initial_stats = array(
        'total' => array(
            'today' => array( 'date' => date('Ymd'), 'count' => 0 ),
            'week'  => array( 'date' => date('Ymd'), 'count' => 0 ),
            'month' => array( 'date' => date('Ymd'), 'count' => 0 ),
            'all_time' => 0
        )
    );
    update_option( 'rs_honeypot_stats', json_encode( $initial_stats ) );
    
    wp_send_json_success( 'Statistics reset' );
}

/**
 * Clear all logs via AJAX
 */
add_action( 'wp_ajax_rs_honeypot_clear_logs', 'rs_ajax_honeypot_clear_logs' );
function rs_ajax_honeypot_clear_logs() {
    check_ajax_referer( 'rs_honeypot_nonce', '_wpnonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
    }
    
    global $wpdb;
    $table = RS_Database::get_table_name( 'honeypot_logs' );
    $wpdb->query( "TRUNCATE TABLE $table" );
    
    wp_send_json_success( 'Logs cleared' );
}
