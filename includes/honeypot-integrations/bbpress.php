<?php
/**
 * Royal Sentinel Honeypot - BBPress Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'bbpress' ) ) {
    return;
}

// New topic validation
add_action( 'bbp_new_topic_pre_extras', 'rs_hp_validate_bbpress_topic', 5 );
function rs_hp_validate_bbpress_topic() {
    if ( rs_honeypot_handle_spam( 'bbpress_topic', $_POST ) ) {
        bbp_add_error( 'rs_honeypot', rs_honeypot_get_error_message() );
    }
}

// New reply validation
add_action( 'bbp_new_reply_pre_extras', 'rs_hp_validate_bbpress_reply', 5 );
function rs_hp_validate_bbpress_reply() {
    if ( rs_honeypot_handle_spam( 'bbpress_reply', $_POST ) ) {
        bbp_add_error( 'rs_honeypot', rs_honeypot_get_error_message() );
    }
}
