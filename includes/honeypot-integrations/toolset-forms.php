<?php
/**
 * Royal Sentinel Honeypot - Toolset Forms Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'toolset_forms' ) && ! rs_honeypot_is_integration_enabled( 'toolset' ) ) {
    return;
}

add_filter( 'cred_form_validate', 'rs_hp_validate_toolset', 5, 2 );
function rs_hp_validate_toolset( $error_fields, $form_data ) {
    if ( rs_honeypot_handle_spam( 'toolset_forms', $_POST ) ) {
        $error_fields[] = array(
            'name' => 'rs_honeypot',
            'value' => rs_honeypot_get_error_message()
        );
    }
    return $error_fields;
}
