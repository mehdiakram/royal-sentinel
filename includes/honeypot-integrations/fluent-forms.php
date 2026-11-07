<?php
/**
 * Royal Sentinel Honeypot - Fluent Forms Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'fluent_forms' ) ) {
    return;
}

add_action( 'fluentform/before_insert_submission', 'rs_hp_validate_fluent', 5, 3 );
function rs_hp_validate_fluent( $insertData, $data, $form ) {
    if ( rs_honeypot_handle_spam( 'fluent_forms', $_POST ) ) {
        wp_send_json([
            'errors' => ['rs_honeypot' => [rs_honeypot_get_error_message()]]
        ], 422);
    }
}
