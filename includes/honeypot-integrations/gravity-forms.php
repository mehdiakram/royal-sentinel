<?php
/**
 * Royal Sentinel Honeypot - Gravity Forms Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check if integration is enabled
if ( ! rs_honeypot_is_integration_enabled( 'gravity_forms' ) ) {
    return;
}

/**
 * Validate Gravity Forms submission for honeypot spam
 */
add_filter( 'gform_validation', 'rs_hp_validate_gravity_forms', 5 );
function rs_hp_validate_gravity_forms( $validation_result ) {
    // Check for spam
    if ( rs_honeypot_handle_spam( 'gravity_forms', $_POST ) ) {
        $validation_result['is_valid'] = false;
        
        // Add error to first field
        foreach ( $validation_result['form']['fields'] as &$field ) {
            $field->failed_validation = true;
            $field->validation_message = rs_honeypot_get_error_message();
            break;
        }
    }
    
    return $validation_result;
}
