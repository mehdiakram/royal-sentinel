<?php
/**
 * Royal Sentinel Honeypot - WP Comments Integration
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Check if integration is enabled
if ( ! rs_honeypot_is_integration_enabled( 'wp_comments' ) ) {
    return;
}

/**
 * Validate comment for honeypot spam
 */
add_filter( 'preprocess_comment', 'rs_hp_validate_comment', 5 );
function rs_hp_validate_comment( $commentdata ) {
    // Skip for logged-in users with edit capability (trusted users)
    if ( current_user_can( 'moderate_comments' ) ) {
        return $commentdata;
    }
    
    // Check for spam
    if ( rs_honeypot_handle_spam( 'wp_comments', $_POST ) ) {
        wp_die( 
            rs_honeypot_get_error_message(), 
            __( 'Comment Blocked', 'royal-sentinel' ), 
            array( 'response' => 403, 'back_link' => true )
        );
    }
    
    return $commentdata;
}
