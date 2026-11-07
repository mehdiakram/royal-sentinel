<?php
/**
 * Royal Sentinel Honeypot - Contact Form 7 Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check if integration is enabled
if ( ! rs_honeypot_is_integration_enabled( 'contact_form_7' ) ) {
    return;
}

/**
 * Validate CF7 form submission for honeypot spam
 */
add_filter( 'wpcf7_validate', 'rs_hp_validate_cf7', 5, 2 );
function rs_hp_validate_cf7( $result, $tags ) {
    // Only check if no validation errors yet
    if ( ! empty( $result->get_invalid_fields() ) ) {
        return $result;
    }
    
    // Check for spam
    if ( rs_honeypot_handle_spam( 'contact_form_7', $_POST ) ) {
        $result->invalidate( '', rs_honeypot_get_error_message() );
    }
    
    return $result;
}
