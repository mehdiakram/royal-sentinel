<?php
/**
 * Royal Sentinel Honeypot - Ninja Forms Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'ninja_forms' ) ) {
    return;
}

add_filter( 'ninja_forms_submit_data', 'rs_hp_validate_ninja_forms', 5 );
function rs_hp_validate_ninja_forms( $form_data ) {
    if ( rs_honeypot_handle_spam( 'ninja_forms', $_POST ) ) {
        $form_data['errors']['form']['rs_honeypot'] = rs_honeypot_get_error_message();
    }
    return $form_data;
}
