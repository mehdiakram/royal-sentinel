<?php
/**
 * Royal Sentinel Honeypot - Remaining Integrations
 * 
 * This file includes integrations for:
 * - MC4WP (Mailchimp)
 * - MailPoet
 * - Theme My Login
 * - YITH Easy Login
 * - S2 Members
 * - QuForm
 * - WS Form
 * - Sure Forms
 * - Everest Forms
 * - FormCraft
 * - HTML Forms
 * - Bricks Builder
 * - Bricksforge
 * - JetForm Builder
 * - Easy Digital Downloads
 * - Avia Enfold Theme
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// MC4WP: Mailchimp for WP
if ( rs_honeypot_is_integration_enabled( 'mc4wp' ) ) {
    add_filter( 'mc4wp_form_errors', function( $errors, $form ) {
        if ( rs_honeypot_handle_spam( 'mc4wp', $_POST ) ) {
            $errors[] = 'rs_honeypot_spam';
        }
        return $errors;
    }, 5, 2 );
}

// MailPoet
if ( rs_honeypot_is_integration_enabled( 'mailpoet' ) ) {
    add_action( 'mailpoet_subscription_before_subscribe', function() {
        if ( rs_honeypot_handle_spam( 'mailpoet', $_POST ) ) {
            wp_die( rs_honeypot_get_error_message() );
        }
    }, 5 );
}

// Theme My Login
if ( rs_honeypot_is_integration_enabled( 'theme_my_login' ) ) {
    add_filter( 'tml_errors', function( $errors ) {
        if ( rs_honeypot_handle_spam( 'theme_my_login', $_POST ) ) {
            $errors->add( 'rs_honeypot', rs_honeypot_get_error_message() );
        }
        return $errors;
    }, 5 );
}

// YITH Easy Login & Register Popup
if ( rs_honeypot_is_integration_enabled( 'yith_login' ) ) {
    add_action( 'woocommerce_login_form_end', function() {
        // JS honeypot is injected, this is backup validation
    } );
}

// S2 Members
if ( rs_honeypot_is_integration_enabled( 's2_members' ) ) {
    add_action( 's2member_before_process_registration_form', function() {
        if ( rs_honeypot_handle_spam( 's2_members', $_POST ) ) {
            wp_die( rs_honeypot_get_error_message() );
        }
    }, 5 );
}

// QuForm
if ( rs_honeypot_is_integration_enabled( 'quform' ) ) {
    add_filter( 'quform_pre_validate', function( $result, $form ) {
        if ( rs_honeypot_handle_spam( 'quform', $_POST ) ) {
            $result['valid'] = false;
            $result['errors']['rs_honeypot'] = rs_honeypot_get_error_message();
        }
        return $result;
    }, 5, 2 );
}

// WS Form
if ( rs_honeypot_is_integration_enabled( 'ws_form' ) ) {
    add_filter( 'wsf_submit_validate', function( $errors, $form_id ) {
        if ( rs_honeypot_handle_spam( 'ws_form', $_POST ) ) {
            $errors[] = rs_honeypot_get_error_message();
        }
        return $errors;
    }, 5, 2 );
}

// Sure Forms
if ( rs_honeypot_is_integration_enabled( 'sure_forms' ) ) {
    add_action( 'sureforms_before_submit', function() {
        if ( rs_honeypot_handle_spam( 'sure_forms', $_POST ) ) {
            wp_send_json_error( array( 'message' => rs_honeypot_get_error_message() ) );
        }
    }, 5 );
}

// Everest Forms
if ( rs_honeypot_is_integration_enabled( 'everest_forms' ) ) {
    add_filter( 'everest_forms_process_errors', function( $errors, $form_data ) {
        if ( rs_honeypot_handle_spam( 'everest_forms', $_POST ) ) {
            $errors['header'] = rs_honeypot_get_error_message();
        }
        return $errors;
    }, 5, 2 );
}

// FormCraft
if ( rs_honeypot_is_integration_enabled( 'formcraft' ) ) {
    add_filter( 'formcraft3_validation_result', function( $result ) {
        if ( rs_honeypot_handle_spam( 'formcraft', $_POST ) ) {
            $result['failed'] = true;
            $result['messages']['rs_honeypot'] = rs_honeypot_get_error_message();
        }
        return $result;
    }, 5 );
}

// HTML Forms
if ( rs_honeypot_is_integration_enabled( 'html_forms' ) ) {
    add_filter( 'hf_validate_form', function( $error_code, $form, $data ) {
        if ( rs_honeypot_handle_spam( 'html_forms', $_POST ) ) {
            return 'rs_honeypot_spam';
        }
        return $error_code;
    }, 5, 3 );
}

// Bricks Builder
if ( rs_honeypot_is_integration_enabled( 'bricks_builder' ) ) {
    add_filter( 'bricks/form/validate', function( $errors ) {
        if ( rs_honeypot_handle_spam( 'bricks_builder', $_POST ) ) {
            $errors[] = rs_honeypot_get_error_message();
        }
        return $errors;
    }, 5 );
}

// Bricksforge Pro Form
if ( rs_honeypot_is_integration_enabled( 'bricksforge' ) ) {
    add_filter( 'bricksforge/pro_forms/validate', function( $errors ) {
        if ( rs_honeypot_handle_spam( 'bricksforge', $_POST ) ) {
            $errors[] = rs_honeypot_get_error_message();
        }
        return $errors;
    }, 5 );
}

// JetForm Builder
if ( rs_honeypot_is_integration_enabled( 'jetform_builder' ) ) {
    add_filter( 'jet-form-builder/validate-field', function( $is_valid, $field ) {
        static $checked = false;
        if ( ! $checked && rs_honeypot_handle_spam( 'jetform_builder', $_POST ) ) {
            $checked = true;
            return false;
        }
        return $is_valid;
    }, 5, 2 );
}

// Easy Digital Downloads
if ( rs_honeypot_is_integration_enabled( 'edd' ) ) {
    add_action( 'edd_checkout_before_gateway', function( $data, $valid ) {
        if ( rs_honeypot_handle_spam( 'edd_checkout', $_POST ) ) {
            edd_set_error( 'rs_honeypot', rs_honeypot_get_error_message() );
        }
    }, 5, 2 );
    
    add_filter( 'edd_register_form_errors', function( $errors ) {
        if ( rs_honeypot_handle_spam( 'edd_registration', $_POST ) ) {
            $errors['rs_honeypot'] = rs_honeypot_get_error_message();
        }
        return $errors;
    }, 5 );
}

// Avia Enfold Theme
if ( rs_honeypot_is_integration_enabled( 'avia_enfold' ) ) {
    add_filter( 'avf_form_send', function( $send, $new_post, $form_params ) {
        if ( rs_honeypot_handle_spam( 'avia_enfold', $_POST ) ) {
            return false;
        }
        return $send;
    }, 5, 3 );
}
