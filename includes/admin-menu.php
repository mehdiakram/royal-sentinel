<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Redirect to license page if license is not active and user tries to access other pages
add_action('admin_init', 'rs_check_license_redirect');

function rs_check_license_redirect() {
    // Check if license is active
    $is_licensed = defined('ROYALSENTINEL_LICENSE_ACTIVE') && ROYALSENTINEL_LICENSE_ACTIVE;
    
    // যদি লাইসেন্স অ্যাক্টিভ থাকে, তাহলে কিছু করার দরকার নেই
    if ( $is_licensed ) {
        return;
    }
    
    // Get current page
    $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    
    // যদি লাইসেন্স না থাকে এবং ইউজার লাইসেন্স পেজ ছাড়া অন্য কোন Royal Sentinel পেজে যেতে চায়
    if ( ! $is_licensed && 
         ( strpos($current_page, 'royal-sentinel') === 0 || strpos($current_page, 'rs-') === 0 ) ) {
        
        // যদি ইতিমধ্যে লাইসেন্স পেজে না থাকে, তাহলে রিডাইরেক্ট করবে
        if ( $current_page !== 'royal-sentinel' && $current_page !== 'rs-license' ) {
            wp_redirect( admin_url('admin.php?page=royal-sentinel') );
            exit;
        }
    }
}

add_action('admin_menu', 'rs_register_menu');


function rs_register_menu() {
    // Check license status
    $is_licensed = defined('ROYALSENTINEL_LICENSE_ACTIVE') && ROYALSENTINEL_LICENSE_ACTIVE;

    // 1. Main Menu (Parent)
    add_menu_page(
        'Royal Sentinel', 
        'Royal Sentinel', 
        'manage_options', 
        'royal-sentinel', 
        $is_licensed ? 'rs_render_dashboard' : 'rs_render_license', // যদি লাইসেন্স না থাকে তাহলে লাইসেন্স পেজ দেখাবে
        ROYALSENTINEL_PLUGIN_URL . 'assets/images/icon-20x20.png', 
        20
    );

    // যদি লাইসেন্স না থাকে, তাহলে শুধু লাইসেন্স পেজ দেখাবে
    if ( ! $is_licensed ) {
        add_submenu_page(
            'royal-sentinel', 
            'License', 
            'License', 
            'manage_options', 
            'royal-sentinel', // Main menu slug এর সাথে same রাখা হয়েছে
            'rs_render_license'
        );
        return; // অন্য কোন মেনু দেখাবে না
    }

    // লাইসেন্স থাকলে সব মেনু দেখাবে (লাইসেন্স পেজ ছাড়া)

    // 2. Submenu: Dashboard (প্রথম আইটেম)
    add_submenu_page(
        'royal-sentinel', 
        'Dashboard', 
        'Dashboard', 
        'manage_options', 
        'royal-sentinel', 
        'rs_render_dashboard'
    );


    $is_cf_enabled = get_option('rs_cf_enable', 1);
    if ( $is_cf_enabled ) {
        add_submenu_page(
            'royal-sentinel', 
            'Cloudflare Manager', 
            'Cloudflare', 
            'manage_options', 
            'rs-cloudflare', // নতুন স্লাগ
            'rs_render_cloudflare'
        );
    }

    
    add_submenu_page(
        'royal-sentinel',         // Parent slug (আপনার মেইন প্লাগিন স্ল্যাগ)
        'Advance Security',       // Page Title
        'Advance Security',       // Menu Title
        'manage_options',         // Capability
        'rs-advance-security',    // Menu Slug
        'rs_render_security_page' // Callback Function
    );

    add_submenu_page(
        'royal-sentinel',
        'Site Scans',
        'Site Scans',
        'manage_options',
        'rs-site-scans',
        'rs_render_site_scans'
    );

    add_submenu_page(
        'royal-sentinel',
        'Vulnerables',
        'Vulnerables',
        'manage_options',
        'rs-vulnerables',
        'rs_render_vulnerables'
    );

    add_submenu_page(
        'royal-sentinel',
        'Malware Scanner',
        'Malware Scanner',
        'manage_options',
        'rs-malware-scanner',
        'rs_render_malware_scanner'
    );

    add_submenu_page(
        'royal-sentinel', 
        'Tweaks & Optimization', 
        'Tweaks', 
        'manage_options', 
        'rs-tweaks', 
        'rs_render_tweaks_page'
    );

    add_submenu_page(
        'royal-sentinel', 
        'Honeypot Protection', 
        'Honeypot', 
        'manage_options', 
        'rs-honeypot', 
        'rs_render_honeypot_page'
    );

    // 4. Submenu: Settings
    add_submenu_page(
        'royal-sentinel', 
        'Settings', 
        'Settings', 
        'manage_options', 
        'rs-settings', 
        'rs_render_settings'
    );

    // 5. Submenu: Activity Log
    add_submenu_page(
        'royal-sentinel',
        'Activity Log',
        'Activity Log',
        'manage_options',
        'rs-activity-log',
        'rs_render_activity_log'
    );

    // লাইসেন্স পেজ দেখাবে না যখন লাইসেন্স অ্যাক্টিভ থাকবে
}


// --- View Controllers ---

function rs_render_dashboard() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/dashboard.php';
}

function rs_render_cloudflare() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/cloudflare.php';
}

function rs_render_security_page() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/advance-security.php';
}

function rs_render_site_scans() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/site-scans.php';
}

function rs_render_vulnerables() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/vulnerables.php';
}

function rs_render_tweaks_page() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/tweaks.php';
}

function rs_render_settings() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/admin-settings.php';
}

function rs_render_license() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/license.php';
}

function rs_render_malware_scanner() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/malware-scanner.php';
}

function rs_render_honeypot_page() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/honeypot.php';
}

function rs_render_activity_log() {
    require_once ROYALSENTINEL_PLUGIN_DIR . 'views/activity-log.php';
}