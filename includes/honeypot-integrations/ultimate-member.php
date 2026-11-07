<?php
/**
 * Royal Sentinel Honeypot - Ultimate Member Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'ultimate_member' ) ) {
    return;
}

add_action( 'um_submit_form_errors_hook', 'rs_hp_validate_ultimate_member', 5, 1 );
function rs_hp_validate_ultimate_member( $args ) {
    if ( rs_honeypot_handle_spam( 'ultimate_member', $_POST ) ) {
        UM()->form()->add_error( 'rs_honeypot', rs_honeypot_get_error_message() );
    }
}
