<?php
// File: includes/license-admin-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================
// ACTIVATION — AJAX Handler
// (License_Integration_Guide.md — Step 5: LicenseController)
//
// NOTE: We do NOT call royalsentinel_is_license_valid() here
// because that function has a fast-path status check. Instead,
// we independently verify with the server and set all options
// directly — exactly as the guide specifies for activation.
// ============================================================

add_action( 'wp_ajax_rs_activate_license', 'rs_ajax_activate_license' );

function rs_ajax_activate_license() {

    // 1. Verify Nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'rs_license_nonce' ) ) {
        wp_send_json_error( [ 'message' => __( 'Security check failed!', 'royal-sentinel' ) ] );
    }

    // 2. Check Permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'royal-sentinel' ) ] );
    }

    // 3. Get and Sanitize License Key
    $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';
    if ( empty( $license_key ) ) {
        wp_send_json_error( [ 'message' => __( 'License key cannot be empty.', 'royal-sentinel' ) ] );
    }

    // 4. Clear any stale state
    delete_option( 'royalsentinel_license_cache' );
    delete_option( 'royalsentinel_license_status' );
    delete_option( 'royalsentinel_license_last_check' );
    delete_option( 'royalsentinel_license_expiry_enc' );
    delete_transient( 'royalsentinel_license_check_lock' );

    // 5. Determine domain
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    if ( strpos( $host, ':' ) !== false ) {
        $host = explode( ':', $host )[0];
    }
    $domain = function_exists( 'royalsentinel_get_base_domain' )
        ? royalsentinel_get_base_domain( $host )
        : preg_replace( '/^www\./', '', $host );

    // 6. Call auth server directly
    global $wp_version;
    $active_theme = wp_get_theme();
    $theme_name   = $active_theme ? $active_theme->get( 'Name' ) : 'Unknown';
    $user_agent   = sprintf(
        'WordPress/%s; PHP/%s; Theme/%s; Url/%s',
        $wp_version, PHP_VERSION, $theme_name, home_url()
    );

    $response = wp_remote_post( 'https://auth.royaltechbd.com/api/verify', [
        'timeout'    => 15,
        'user-agent' => $user_agent,
        'sslverify'  => false,
        'body'       => [
            'software'    => 'royalsentinel',
            'license_key' => $license_key,
            'domain'      => $domain,
        ],
    ] );

    // 7. Handle connection error
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( [
            'message' => __( 'Could not connect to the license server. Please try again later.', 'royal-sentinel' ),
        ] );
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    // 8. Server rejected the license
    if ( ! isset( $data['success'] ) || $data['success'] !== true ) {
        $server_msg = $data['message'] ?? __( 'Invalid or incorrect license key.', 'royal-sentinel' );
        wp_send_json_error( [ 'message' => $server_msg ] );
    }

    // 9. Verify Ed25519 signature before trusting (as per Step 5 of the guide)
    if ( ! function_exists( 'royalsentinel_verify_license_cache' ) || ! royalsentinel_verify_license_cache( $data ) ) {
        wp_send_json_error( [
            'message' => __( 'Invalid server response. Possible tampering detected.', 'royal-sentinel' ),
        ] );
    }

    // 10. All checks passed — persist license state
    update_option( 'royalsentinel_license_settings', [ 'royalsentinel_license_key' => $license_key ] );
    update_option( 'royalsentinel_license_key',         $license_key );
    update_option( 'royalsentinel_license_cache',       $data );
    update_option( 'royalsentinel_license_status',      'active' );
    update_option( 'royalsentinel_license_last_check',  gmdate( 'Y-m-d H:i:s' ) );
    update_option( 'royalsentinel_license_version',     (int) ( $data['license_data']['version'] ?? 1 ) );

    // Store encrypted expiry for offline fallback (non-lifetime licenses)
    $expiresAt = $data['license_data']['expires_at'] ?? null;
    if ( $expiresAt && function_exists( 'royalsentinel_encrypt_expiry' ) ) {
        $enc = royalsentinel_encrypt_expiry( $expiresAt );
        if ( $enc ) {
            update_option( 'royalsentinel_license_expiry_enc', $enc );
        }
    } else {
        delete_option( 'royalsentinel_license_expiry_enc' ); // lifetime — no expiry needed
    }

    wp_send_json_success( [
        'message'  => __( 'License activated successfully!', 'royal-sentinel' ),
        'redirect' => admin_url( 'admin.php?page=royal-sentinel' ),
    ] );
}

// ============================================================
// DEACTIVATION — admin-post Handler
// ============================================================

add_action( 'admin_post_royalsentinel_deactivate_license', 'rs_handle_license_deactivation' );

function rs_handle_license_deactivation() {

    // 1. Verify Nonce
    if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'royalsentinel_license_deactivation_nonce' ) ) {
        wp_die( __( 'Security check failed!', 'royal-sentinel' ) );
    }

    // 2. Check Permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to perform this action.', 'royal-sentinel' ) );
    }

    // 3. Clear ALL license state (Guide-compliant full reset)
    update_option( 'royalsentinel_license_settings',  [ 'royalsentinel_license_key' => '' ] );
    delete_option( 'royalsentinel_license_key' );
    delete_option( 'royalsentinel_license_cache' );
    delete_option( 'royalsentinel_license_status' );
    delete_option( 'royalsentinel_license_last_check' );
    delete_option( 'royalsentinel_license_expiry_enc' );
    delete_transient( 'royalsentinel_license_check_lock' );

    // 4. Redirect back to license page
    wp_redirect( add_query_arg(
        [ 'status' => 'deactivated' ],
        admin_url( 'admin.php?page=royal-sentinel' )
    ) );
    exit;
}
