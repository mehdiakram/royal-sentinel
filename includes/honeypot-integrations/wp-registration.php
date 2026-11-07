<?php
/**
 * Royal Sentinel Honeypot - WP Registration Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check if integration is enabled
if ( ! rs_honeypot_is_integration_enabled( 'wp_registration' ) ) {
    return;
}

/**
 * Validate registration for honeypot spam
 */
add_filter( 'registration_errors', 'rs_hp_validate_registration', 5, 3 );
function rs_hp_validate_registration( $errors, $sanitized_user_login, $user_email ) {
    // Check for spam
    if ( rs_honeypot_handle_spam( 'wp_registration', $_POST ) ) {
        $errors->add( 'honeypot_spam', rs_honeypot_get_error_message() );
    }
    
    return $errors;
}

/**
 * Also check on WooCommerce registration if WooCommerce isn't handling it
 */
add_action( 'woocommerce_register_form', 'rs_hp_ensure_wc_honeypot', 5 );
function rs_hp_ensure_wc_honeypot() {
    // Honeypot is injected via JS, this is just a fallback check point
}
