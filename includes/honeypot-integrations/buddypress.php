<?php
/**
 * Royal Sentinel Honeypot - BuddyPress/BuddyBoss Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// BuddyPress
if ( rs_honeypot_is_integration_enabled( 'buddypress' ) ) {
    add_action( 'bp_signup_validate', 'rs_hp_validate_buddypress' );
    function rs_hp_validate_buddypress() {
        global $bp;
        if ( rs_honeypot_handle_spam( 'buddypress', $_POST ) ) {
            $bp->signup->errors['rs_honeypot'] = rs_honeypot_get_error_message();
        }
    }
}

// BuddyBoss
if ( rs_honeypot_is_integration_enabled( 'buddyboss' ) ) {
    add_action( 'bp_signup_validate', 'rs_hp_validate_buddyboss' );
    function rs_hp_validate_buddyboss() {
        global $bp;
        if ( rs_honeypot_handle_spam( 'buddyboss', $_POST ) ) {
            $bp->signup->errors['rs_honeypot'] = rs_honeypot_get_error_message();
        }
    }
}
