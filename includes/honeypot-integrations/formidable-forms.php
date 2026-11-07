<?php
/**
 * Royal Sentinel Honeypot - Formidable Forms Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'formidable_forms' ) ) {
    return;
}

add_filter( 'frm_validate_entry', 'rs_hp_validate_formidable', 5, 2 );
function rs_hp_validate_formidable( $errors, $values ) {
    if ( rs_honeypot_handle_spam( 'formidable_forms', $_POST ) ) {
        $errors['rs_honeypot'] = rs_honeypot_get_error_message();
    }
    return $errors;
}
