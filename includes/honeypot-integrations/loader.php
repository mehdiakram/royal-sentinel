<?php
/**
 * Royal Sentinel Honeypot - Integration Loader
 * 
 * Loads all honeypot integrations based on enabled settings
 * 
 * @package RoyalSentinel
 * @since 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Load honeypot integrations on init (frontend only for most)
 */
add_action( 'init', 'rs_honeypot_load_integrations' );
function rs_honeypot_load_integrations() {
    
    $integration_dir = ROYALSENTINEL_PLUGIN_DIR . 'includes/honeypot-integrations/';
    
    // Core WordPress (always load on frontend)
    if ( ! is_admin() ) {
        include_once $integration_dir . 'wp-comments.php';
        include_once $integration_dir . 'wp-registration.php';
    }
    
    // Form plugins (these need their hooks to be available)
    include_once $integration_dir . 'contact-form-7.php';
    include_once $integration_dir . 'wpforms.php';
    include_once $integration_dir . 'gravity-forms.php';
    include_once $integration_dir . 'formidable-forms.php';
    include_once $integration_dir . 'elementor-form.php';
    include_once $integration_dir . 'fluent-forms.php';
    include_once $integration_dir . 'ninja-forms.php';
    include_once $integration_dir . 'caldera-forms.php';
    include_once $integration_dir . 'toolset-forms.php';
    include_once $integration_dir . 'divi-form.php';
    
    // eCommerce
    include_once $integration_dir . 'woocommerce.php';
    
    // Community
    include_once $integration_dir . 'bbpress.php';
    include_once $integration_dir . 'buddypress.php';
    include_once $integration_dir . 'ultimate-member.php';
    
    // Other integrations (consolidated file)
    include_once $integration_dir . 'other-integrations.php';
}
