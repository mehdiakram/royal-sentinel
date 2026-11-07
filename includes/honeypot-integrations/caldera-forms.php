<?php
/**
 * Royal Sentinel Honeypot - Caldera Forms Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'caldera_forms' ) ) {
    return;
}

add_filter( 'caldera_forms_validate_field_value', 'rs_hp_validate_caldera', 5, 3 );
function rs_hp_validate_caldera( $entry, $field, $form ) {
    static $checked = false;
    if ( ! $checked && rs_honeypot_handle_spam( 'caldera_forms', $_POST ) ) {
        $checked = true;
        return new WP_Error( 'rs_honeypot', rs_honeypot_get_error_message() );
    }
    return $entry;
}
