<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================================
 * Audit Log Module — Royal Sentinel
 * Hooks: login, logout, plugin/theme activation, post changes
 * ============================================================
 */

// ── DB Table creation ──────────────────────────────────────
function rs_audit_create_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'rs_audit_log';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        user_login VARCHAR(100) NOT NULL DEFAULT '',
        action VARCHAR(100) NOT NULL DEFAULT '',
        object_type VARCHAR(60) NOT NULL DEFAULT '',
        object_name VARCHAR(255) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY action (action),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
add_action( 'plugins_loaded', 'rs_audit_create_table' );

// ── Log helper ────────────────────────────────────────────
function rs_audit_log( $action, $object_type = '', $object_name = '' ) {
    global $wpdb;
    $user    = wp_get_current_user();
    $user_id = $user ? $user->ID : 0;
    $login   = $user ? $user->user_login : 'guest';
    $ip      = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
    $wpdb->insert( $wpdb->prefix . 'rs_audit_log', [
        'user_id'     => $user_id,
        'user_login'  => $login,
        'action'      => sanitize_text_field( $action ),
        'object_type' => sanitize_text_field( $object_type ),
        'object_name' => sanitize_text_field( $object_name ),
        'ip'          => $ip,
        'created_at'  => current_time('mysql'),
    ] );
}

// ── Hooks ─────────────────────────────────────────────────
add_action( 'wp_login', function( $user_login ) {
    rs_audit_log( 'login', 'user', $user_login );
} );

add_action( 'wp_logout', function() {
    $user = wp_get_current_user();
    rs_audit_log( 'logout', 'user', $user->user_login ?? '' );
} );

add_action( 'save_post', function( $post_id, $post ) {
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( $post->post_status === 'auto-draft' ) return;
    rs_audit_log( 'post_saved', 'post', $post->post_title );
}, 10, 2 );

add_action( 'delete_post', function( $post_id ) {
    $post = get_post( $post_id );
    if ( $post ) rs_audit_log( 'post_deleted', 'post', $post->post_title );
} );

add_action( 'activated_plugin', function( $plugin ) {
    rs_audit_log( 'plugin_activated', 'plugin', $plugin );
} );

add_action( 'deactivated_plugin', function( $plugin ) {
    rs_audit_log( 'plugin_deactivated', 'plugin', $plugin );
} );

add_action( 'switch_theme', function( $new_name ) {
    rs_audit_log( 'theme_switched', 'theme', $new_name );
} );

add_action( 'profile_update', function( $user_id ) {
    $user = get_userdata( $user_id );
    rs_audit_log( 'profile_updated', 'user', $user->user_login ?? '' );
} );

add_action( 'user_register', function( $user_id ) {
    $user = get_userdata( $user_id );
    rs_audit_log( 'user_registered', 'user', $user->user_login ?? '' );
} );

add_action( 'delete_user', function( $user_id ) {
    $user = get_userdata( $user_id );
    rs_audit_log( 'user_deleted', 'user', $user->user_login ?? '' );
} );

// ── AJAX: clear logs ─────────────────────────────────────
add_action( 'wp_ajax_rs_clear_audit_log', function() {
    if ( ! current_user_can('manage_options') ) wp_die();
    check_ajax_referer( 'rs_audit_nonce', 'nonce' );
    global $wpdb;
    $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}rs_audit_log" );
    wp_send_json_success( 'Audit log cleared.' );
} );

// ── AJAX: get logs for DataTable ─────────────────────────
add_action( 'wp_ajax_rs_get_audit_log', function() {
    if ( ! current_user_can('manage_options') ) wp_die();
    check_ajax_referer( 'rs_audit_nonce', 'nonce' );
    global $wpdb;
    $table  = $wpdb->prefix . 'rs_audit_log';
    $rows   = $wpdb->get_results( "SELECT * FROM $table ORDER BY id DESC LIMIT 200", ARRAY_A );
    wp_send_json_success( $rows );
} );
