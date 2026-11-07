<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// লগিন ফেইল হলে এই হুক ফায়ার হবে
add_action( 'wp_login_failed', 'rs_handle_failed_login', 10, 2 );

function rs_handle_failed_login( $username, $error ) {
    // ১. সেটিংস লোড করা
    $settings = get_option( 'rs_security_settings', [] );
    
    // Proxy/Cloudflare-aware IP detection
    $ip = rs_get_real_ip();

    // Whitelisted/trusted IP check - never ban or block trusted or admin IPs
    if ( function_exists('rs_is_trusted_ip') && rs_is_trusted_ip( $ip ) ) {
        return;
    }

    // ২. লোকাল ডাটাবেজে কাউন্ট সেভ করা (যেকোনো ফেইল্ড এটেম্পট)
    if ( function_exists('rs_log_bad_ip_db') ) {
        rs_log_bad_ip_db( $ip );
    }

    // =========================================================
    // FEATURE: Lockout "admin" user immediately
    // =========================================================
    // যদি সেটিং অন থাকে এবং ইউজারনেম 'admin' হয়
    if ( !empty($settings['lockout_admin']) && strtolower( $username ) === 'admin' ) {
        
        // Cloudflare API দিয়ে ব্লক করা
        if ( function_exists('rs_api_add_ip') ) {
            $reason = "Immediate Lockout: Attempted login as 'admin'";
            
            $result = rs_api_add_ip( $ip, $reason );
            
            // যদি ব্লক সফল হয়, তবে ইউজারকে এরর মেসেজ দেখানো বা প্রসেস থামানো যেতে পারে
            // কিন্তু wp_login_failed হুক ইতিমধ্যে এরর জেনারেট করেছে।
        }
    }

    // =========================================================
    // FEATURE: Max Login Attempts Per IP (Future Logic)
    // =========================================================
    // এখানে পরবর্তীতে লজিক বসবে যা চেক করবে ডাটাবেসে এই আইপির
    // attempt_count > $settings['max_attempts_ip'] কিনা।
}