<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * =================================================================
 * MODULE: Firewall Access Logic & Violation Tracker
 * =================================================================
 */

/**
 * 1. GLOBAL HELPER: Firewall Access Check (Admin Permission)
 * -----------------------------------------------------------------
 */
function rs_can_access_firewall() {
    global $wpdb;
    $current_user_id = get_current_user_id();
    
    if ( ! user_can( $current_user_id, 'administrator' ) ) return false;

    $table_name = $wpdb->prefix . 'royalsentinel_fw_rules';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) return true;

    $rule = $wpdb->get_row( "SELECT * FROM $table_name WHERE rule_slug = 'main_firewall_access'" );
    if ( ! $rule ) return true; 

    if ( $rule->scope === 'all_admins' ) {
        return true;
    } elseif ( $rule->scope === 'specific_admins' ) {
        $allowed = json_decode( $rule->allowed_users, true );
        if ( is_array( $allowed ) && in_array( $current_user_id, $allowed ) ) return true;
    }

    return false;
}

/**
 * 2. CORE LOGIC: Track Violations & Auto-Ban (The Police) [NEW]
 * -----------------------------------------------------------------
 * এই ফাংশনটি প্রতিটি অ্যাটাকের সময় কল করতে হবে। এটি লগ চেক করবে 
 * এবং লিমিট ক্রস করলে আইপি ব্যান করে দেবে।
 * * ব্যবহার: rs_track_firewall_violation($_SERVER['REMOTE_ADDR'], 'SQL Injection');
 */
function rs_track_firewall_violation( $ip, $reason = 'firewall_rule' ) {
    global $wpdb;
    
    // ১. সেটিংস আনা (ভায়োলেশন লিমিট এবং সময়)
    $settings = get_option( 'rs_security_settings', [] );
    $max_violations = isset($settings['max_fw_violations']) ? intval($settings['max_fw_violations']) : 10;
    $check_period   = isset($settings['fw_check_period']) ? intval($settings['fw_check_period']) : 30; // মিনিট

    // ২. বর্তমান অপরাধটি 'monitor_logs' টেবিলে সেভ করা
    $table_logs = class_exists('RS_Database') ? RS_Database::get_table_name('monitor_logs') : $wpdb->prefix . 'royalsentinel_monitor_logs';
    
    $wpdb->insert( $table_logs, [
        'type'       => 'firewall_violation',
        'severity'   => 2,
        'message'    => 'Firewall Violation: ' . $reason,
        'metadata'   => json_encode( ['ip' => $ip] ),
        'created_at' => current_time( 'mysql' )
    ]);

    // ৩. চেক করা: বিগত $check_period মিনিটে এই আইপি কতবার ভায়োলেট করেছে?
    $time_ago = date( 'Y-m-d H:i:s', strtotime( "-$check_period minutes" ) );
    
    // SQL: JSON metadata থেকে IP খুঁজে বের করা
    $count = $wpdb->get_var( $wpdb->prepare( 
        "SELECT COUNT(*) FROM $table_logs 
         WHERE type = 'firewall_violation' 
         AND created_at >= %s 
         AND metadata LIKE %s", 
        $time_ago, 
        '%"ip":"' . $wpdb->esc_like($ip) . '"%'
    ));

    // ৪. যদি লিমিট ক্রস করে -> ব্যান করো (Move to badips)
    if ( $count >= $max_violations ) {
        if ( class_exists( 'RS_Database' ) ) {
            // ডাটাবেস ক্লাসের হেল্পার দিয়ে পারমানেন্ট ব্যান
            RS_Database::block_ip_permanently( $ip, "Too many violations ($count in $check_period mins)" );
        }
    }
}

/**
 * 3. AJAX HANDLER: Save Firewall Scope
 * -----------------------------------------------------------------
 */
add_action( 'wp_ajax_rs_save_firewall_scope', 'rs_ajax_save_firewall_scope' );

function rs_ajax_save_firewall_scope() {
    check_ajax_referer( 'rs_fw_nonce', '_wpnonce' );
    if ( ! current_user_can( 'administrator' ) ) wp_send_json_error( 'Unauthorized access.' );

    global $wpdb;
    $table_fw_rules = $wpdb->prefix . 'royalsentinel_fw_rules';
    
    $scope = sanitize_text_field( $_POST['scope'] );
    $users = isset( $_POST['users'] ) ? array_map( 'intval', $_POST['users'] ) : [];

    $result = $wpdb->replace( $table_fw_rules, [
        'rule_slug'     => 'main_firewall_access',
        'scope'         => $scope,
        'allowed_users' => json_encode( $users ),
        'updated_at'    => current_time( 'mysql' )
    ]);

    if ( $result === false ) wp_send_json_error( 'Database error.' );
    else wp_send_json_success( 'Firewall access rules updated!' );
}