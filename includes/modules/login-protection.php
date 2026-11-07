<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Login_Protection {

    public function __construct() {
        // ১. লগিন প্রসেস শুরু হওয়ার আগেই 'admin' চেক করা
        add_filter('authenticate', [$this, 'check_forbidden_username'], 20, 3);
        
        // ২. লগিন ফেইল হলে Lockout টেবিলে কাউন্ট বাড়ানো
        add_action('wp_login_failed', [$this, 'handle_failed_login']);
        
        // ৩. সাইট লোড হওয়ার সময় চেক করা আইপি ব্যান আছে কিনা
        add_action('init', [$this, 'check_ip_status']);
    }

    // --- A. 'admin' ইউজারনেম চেক ---
    public function check_forbidden_username($user, $username, $password) {
        if ( strtolower($username) === 'admin' ) {
            $ip = rs_get_real_ip();
            
            // ডাটাবেজ ক্লাসের হেল্পার দিয়ে badips টেবিলে ঢোকানো
            if (class_exists('RS_Database')) {
                RS_Database::block_ip_permanently($ip, 'banned_username_admin');
            }

            // লগিন আটকাতে এরর রিটার্ন
            return new WP_Error('invalid_username', '<strong>ERROR</strong>: This username is forbidden.');
        }
        return $user;
    }

    // --- B. সাধারণ ফেইল্ড লগিন হ্যান্ডেলিং ---
    public function handle_failed_login($username) {
        $ip = rs_get_real_ip();
        
        if (class_exists('RS_Database')) {
            // lockouts টেবিলে কাউন্ট বাড়ানো
            $count = RS_Database::track_login_failure($ip);
            
            // অপশনাল: যদি ১০ বারের বেশি হয়, তবে কি করবেন?
            // এখানে আপনি চাইলে ১০ বারের পর badips এ মুভ করতে পারেন
            // if ($count > 10) { RS_Database::block_ip_permanently($ip, 'too_many_attempts'); }
        }
    }

    // --- C. আইপি চেক (ফায়ারওয়াল) ---
    public function check_ip_status() {
        if (is_admin() || is_user_logged_in()) return; // অ্যাডমিন প্যানেলে বা লগইন অবস্থায় চেক এড়ানো যেতে পারে (প্রয়োজন ভেদে)

        $ip = rs_get_real_ip();
        
        if ( function_exists('rs_is_trusted_ip') && rs_is_trusted_ip($ip) ) {
            return;
        }
        
        global $wpdb;

        // ১. Bad IPs চেক (স্থায়ী ব্যান)
        if (class_exists('RS_Database')) {
            $bad_table = RS_Database::get_table_name('badips');
            $is_banned = $wpdb->get_var($wpdb->prepare("SELECT id FROM $bad_table WHERE bad_ip = %s", $ip));

            if ($is_banned) {
                wp_die('<h1>Access Denied</h1><p>Your IP has been permanently blocked due to suspicious activity.</p>', 'Access Denied', ['response' => 403]);
            }
        }
    }
}

new RS_Login_Protection();