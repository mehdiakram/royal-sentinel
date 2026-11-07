<?php
/**
 * Royal Sentinel Honeypot - WooCommerce Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! rs_honeypot_is_integration_enabled( 'woocommerce' ) ) {
    return;
}

// Checkout validation
add_action( 'woocommerce_checkout_process', 'rs_hp_validate_wc_checkout', 5 );
function rs_hp_validate_wc_checkout() {
    if ( rs_honeypot_handle_spam( 'woocommerce_checkout', $_POST ) ) {
        wc_add_notice( rs_honeypot_get_error_message(), 'error' );
    }
}

// Registration validation
add_filter( 'woocommerce_registration_errors', 'rs_hp_validate_wc_registration', 5, 3 );
function rs_hp_validate_wc_registration( $errors, $username, $email ) {
    if ( rs_honeypot_handle_spam( 'woocommerce_registration', $_POST ) ) {
        $errors->add( 'honeypot_spam', rs_honeypot_get_error_message() );
    }
    return $errors;
}

// Review validation
add_action( 'pre_comment_on_post', 'rs_hp_validate_wc_review', 5 );
function rs_hp_validate_wc_review( $comment_post_ID ) {
    $post = get_post( $comment_post_ID );
    if ( $post && $post->post_type === 'product' ) {
        if ( rs_honeypot_handle_spam( 'woocommerce_review', $_POST ) ) {
            wp_die( rs_honeypot_get_error_message(), 'Review Blocked', array( 'response' => 403 ) );
        }
    }
}
