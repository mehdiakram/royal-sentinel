<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================================
 * Magic Login Module — Royal Sentinel
 * Generates time-limited, one-use admin login links
 * ============================================================
 */

// ── Generate link (AJAX) ──────────────────────────────────
add_action( 'wp_ajax_rs_generate_magic_link', function() {
    if ( ! current_user_can('manage_options') ) wp_die();
    check_ajax_referer( 'rs_tools_nonce', 'nonce' );

    $user_id  = intval( $_POST['user_id'] ?? get_current_user_id() );
    $duration = intval( $_POST['duration'] ?? 86400 ); // seconds
    $token    = wp_generate_password( 48, false, false );
    $expires  = time() + $duration;

    set_transient( 'rs_magic_' . $token, [
        'user_id' => $user_id,
        'expires' => $expires,
    ], $duration );

    $link = add_query_arg( [
        'rs_magic' => $token,
    ], site_url() );

    wp_send_json_success( [
        'link'    => $link,
        'expires' => date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $expires ),
    ] );
} );

// ── Intercept magic link visit ────────────────────────────
add_action( 'init', function() {
    if ( ! isset( $_GET['rs_magic'] ) ) return;
    $token = sanitize_text_field( $_GET['rs_magic'] );
    $data  = get_transient( 'rs_magic_' . $token );

    if ( ! $data || time() > $data['expires'] ) {
        wp_die( 'This magic login link has expired or is invalid.', 'Invalid Link', [ 'response' => 403 ] );
    }

    delete_transient( 'rs_magic_' . $token ); // One-use only

    $user = get_userdata( (int) $data['user_id'] );
    if ( ! $user ) {
        wp_die( 'User not found.', 'Error', [ 'response' => 404 ] );
    }

    wp_set_auth_cookie( $user->ID );
    wp_set_current_user( $user->ID );

    // Log the magic login
    if ( function_exists('rs_audit_log') ) {
        rs_audit_log( 'magic_login', 'user', $user->user_login );
    }

    $redirect = admin_url();
    $swal_url = ROYALSENTINEL_PLUGIN_URL . 'lib/sweetalert2/sweetalert2.all.min.js';
    $css_url  = ROYALSENTINEL_PLUGIN_URL . 'lib/sweetalert2/sweetalert2.min.css';

    // Intermediate page with SweetAlert before redirect
    echo '<!DOCTYPE html><html><head>
        <meta charset="UTF-8">
        <title>Logging in...</title>
        <link rel="stylesheet" href="' . esc_url($css_url) . '">
        <style>
            body { background: #0f1117; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: sans-serif; }
        </style>
    </head><body>
        <script src="' . esc_url($swal_url) . '"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                icon: "success",
                title: "Login Successful! \u2705",
                text: "Welcome back, ' . esc_js($user->display_name) . '! Redirecting...",
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                background: "#1e2130",
                color: "#e2e8f0",
                iconColor: "#27ae60"
            }).then(function() {
                window.location.href = "' . esc_js($redirect) . '";
            });
        });
        </script>
    </body></html>';
    exit;
} );
