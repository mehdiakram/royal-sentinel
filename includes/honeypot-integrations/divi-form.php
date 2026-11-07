<?php
/**
 * Royal Sentinel Honeypot - Divi Theme Form Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'divi_form' ) ) {
    return;
}

add_filter( 'et_contact_page_headers', 'rs_hp_validate_divi', 5, 4 );
function rs_hp_validate_divi( $headers, $contact_name, $contact_email, $module_slug ) {
    if ( rs_honeypot_handle_spam( 'divi_form', $_POST ) ) {
        wp_die( rs_honeypot_get_error_message(), 'Form Blocked', array( 'response' => 403 ) );
    }
    return $headers;
}
