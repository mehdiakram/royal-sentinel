<?php
/**
 * Royal Sentinel Honeypot - Elementor Form Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'elementor_form' ) ) {
    return;
}

add_action( 'elementor_pro/forms/validation', 'rs_hp_validate_elementor', 5, 2 );
function rs_hp_validate_elementor( $record, $ajax_handler ) {
    if ( rs_honeypot_handle_spam( 'elementor_form', $_POST ) ) {
        $ajax_handler->add_error_message( rs_honeypot_get_error_message() );
        $ajax_handler->is_success = false;
    }
}
