<?php
/**
 * Royal Sentinel Honeypot - WPForms Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check if integration is enabled
if ( ! rs_honeypot_is_integration_enabled( 'wpforms' ) ) {
    return;
}

/**
 * Validate WPForms submission for honeypot spam
 */
add_action( 'wpforms_process', 'rs_hp_validate_wpforms', 5, 3 );
function rs_hp_validate_wpforms( $fields, $entry, $form_data ) {
    // Check for spam
    if ( rs_honeypot_handle_spam( 'wpforms', $_POST ) ) {
        wpforms()->process->errors[ $form_data['id'] ]['header'] = rs_honeypot_get_error_message();
    }
}
