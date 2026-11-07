<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================================
 * Cloudflare Turnstile CAPTCHA — Royal Sentinel
 * Adds invisible CAPTCHA to login, register & WooCommerce
 * ============================================================
 */

// ── Render Turnstile widget ───────────────────────────────
function rs_render_turnstile() {
    $settings  = get_option( 'rs_security_settings', [] );
    $site_key  = $settings['turnstile_site_key'] ?? '';
    if ( empty($site_key) ) return;
    echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($site_key) . '" data-theme="light" style="margin:10px 0;"></div>';
}

// ── Verify server-side ────────────────────────────────────
function rs_verify_turnstile() {
    $settings    = get_option( 'rs_security_settings', [] );
    $secret_key  = $settings['turnstile_secret_key'] ?? '';
    $enabled     = ! empty( $settings['turnstile_enable'] );
    if ( ! $enabled || empty($secret_key) ) return true; // Not configured → pass

    $token = sanitize_text_field( $_POST['cf-turnstile-response'] ?? '' );
    if ( empty($token) ) return false;

    $response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'body' => [
            'secret'   => $secret_key,
            'response' => $token,
            'remoteip' => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        ],
    ] );

    if ( is_wp_error($response) ) return false;
    $body = json_decode( wp_remote_retrieve_body($response), true );
    return ! empty( $body['success'] );
}

// ── Login form ────────────────────────────────────────────
add_action( 'login_form', function() {
    $s = get_option('rs_security_settings', []);
    if ( ! empty($s['turnstile_enable']) && ! empty($s['turnstile_login']) ) {
        rs_render_turnstile();
    }
} );

add_filter( 'authenticate', function( $user, $username, $password ) {
    $s = get_option('rs_security_settings', []);
    if ( empty($s['turnstile_enable']) || empty($s['turnstile_login']) ) return $user;
    
    // Skip Turnstile check for empty submissions to let WP handle default empty errors
    if ( empty($username) && empty($password) ) return $user;

    $is_wp_login = ( isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'wp-login.php') !== false );
    $has_token   = isset($_POST['cf-turnstile-response']);

    // Enforce Turnstile only on native login page or if the token is present
    if ( $is_wp_login || $has_token ) {
        if ( ! rs_verify_turnstile() ) {
            return new WP_Error( 'turnstile_failed', __('CAPTCHA verification failed. Please try again.') );
        }
    }
    return $user;
}, 30, 3 );

// ── Register form ─────────────────────────────────────────
add_action( 'register_form', function() {
    $s = get_option('rs_security_settings', []);
    if ( ! empty($s['turnstile_enable']) && ! empty($s['turnstile_register']) ) {
        rs_render_turnstile();
    }
} );

add_filter( 'registration_errors', function( $errors ) {
    $s = get_option('rs_security_settings', []);
    if ( empty($s['turnstile_enable']) || empty($s['turnstile_register']) ) return $errors;
    
    $is_wp_login = ( isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'wp-login.php') !== false );
    $has_token   = isset($_POST['cf-turnstile-response']);

    // Enforce Turnstile only on native registration page or if the token is present
    if ( $is_wp_login || $has_token ) {
        if ( ! rs_verify_turnstile() ) {
            $errors->add( 'turnstile_failed', __('CAPTCHA verification failed. Please try again.') );
        }
    }
    return $errors;
} );

// ── WooCommerce Checkout ──────────────────────────────────
add_action( 'woocommerce_after_checkout_billing_form', function() {
    $s = get_option('rs_security_settings', []);
    if ( ! empty($s['turnstile_enable']) && ! empty($s['turnstile_woo_checkout']) ) {
        rs_render_turnstile();
    }
} );

add_action( 'woocommerce_checkout_process', function() {
    $s = get_option('rs_security_settings', []);
    if ( empty($s['turnstile_enable']) || empty($s['turnstile_woo_checkout']) ) return;
    if ( ! rs_verify_turnstile() ) {
        wc_add_notice( __('CAPTCHA verification failed. Please try again.'), 'error' );
    }
} );
