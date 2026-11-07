<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handle Virtual security.txt file serving
 */
add_action( 'template_redirect', 'rs_serve_security_txt', 0 );

function rs_serve_security_txt() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
    
    // Check if the request is for security.txt
    if ( strpos($request_uri, '/.well-known/security.txt') !== false ) {
        
        $settings = get_option('rs_security_settings', []);
        
        // If not enabled, let WordPress handle it (e.g. 404)
        if ( empty($settings['securitytxt_enable']) ) {
            return;
        }

        // Gather variables
        $contact    = !empty($settings['securitytxt_contact']) ? $settings['securitytxt_contact'] : 'mailto:' . get_option('admin_email');
        $enc        = !empty($settings['securitytxt_encryption']) ? $settings['securitytxt_encryption'] : '';
        $pol        = !empty($settings['securitytxt_policy']) ? $settings['securitytxt_policy'] : '';
        $acks       = !empty($settings['securitytxt_acknowledgments']) ? $settings['securitytxt_acknowledgments'] : '';
        $hiring     = !empty($settings['securitytxt_hiring']) ? $settings['securitytxt_hiring'] : '';
        $langs      = !empty($settings['securitytxt_langs']) ? $settings['securitytxt_langs'] : '';
        $expiry     = !empty($settings['securitytxt_expiry']) ? $settings['securitytxt_expiry'] : '1y';
        $credit     = isset($settings['securitytxt_credit']) ? $settings['securitytxt_credit'] : 1; 

        // Generate Expiry Date
        $expiry_date = '';
        if ( $expiry === '6m' ) {
            $expiry_date = gmdate("Y-m-d\TH:i:s\Z", strtotime('+6 months'));
        } elseif ( $expiry === '1y' ) {
            $expiry_date = gmdate("Y-m-d\TH:i:s\Z", strtotime('+1 year'));
        }

        // Build plain text
        $content = "Contact: " . $contact . "\n";
        if ( !empty($expiry_date) ) {
            $content .= "Expires: " . $expiry_date . "\n";
        }
        if ( !empty($enc) ) {
            $content .= "Encryption: " . $enc . "\n";
        }
        if ( !empty($acks) ) {
            $content .= "Acknowledgments: " . $acks . "\n";
        }
        if ( !empty($pol) ) {
            $content .= "Policy: " . $pol . "\n";
        }
        if ( !empty($hiring) ) {
            $content .= "Hiring: " . $hiring . "\n";
        }
        if ( !empty($langs) ) {
            $content .= "Preferred-Languages: " . $langs . "\n";
        }
        
        $content .= "Canonical: " . get_site_url() . "/.well-known/security.txt\n";

        if ( $credit ) {
            $content .= "\n# Secured by Royal Sentinel | Developed by Royal Technologies\n";
        }

        // Serve file and exit
        if ( ! headers_sent() ) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo $content;
        exit;
    }
}
