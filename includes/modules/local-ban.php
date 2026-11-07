<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Local_Ban {

    public function __construct() {
        // ১. লগিন ফিল্টার (ব্যানড আইপি আটকানোর জন্য)
        add_filter( 'authenticate', [ $this, 'check_login_access' ], 30, 3 );
        
        // ২. AJAX: Add Ban (Manual) - ডাটাবেসে আইপি যোগ করা
        add_action( 'wp_ajax_rs_add_local_ban', [ $this, 'ajax_add_ban' ] );
        
        // ৩. AJAX: Unlock/Unban (Unified) - ব্যান বা লকআউট রিমুভ করা
        add_action( 'wp_ajax_rs_unlock_ip_action', [ $this, 'ajax_unlock_ip' ] );
    }

    /**
     * 1. Check Login Access
     * কেউ লগিন করার চেষ্টা করলে চেক করা হবে সে ব্যান লিস্টে আছে কিনা।
     */
    public function check_login_access( $user, $username, $password ) {
        $ip = rs_get_real_ip();
        
        // Whitelisted/trusted IP check - never block trusted or admin IPs
        if ( function_exists('rs_is_trusted_ip') && rs_is_trusted_ip( $ip ) ) {
            return $user;
        }
        
        global $wpdb;

        // টেবিল নাম নির্ধারণ (RS_Database ক্লাস ব্যবহার করে)
        $table_name = class_exists('RS_Database') ? RS_Database::get_table_name('badips') : $wpdb->prefix . 'royalsentinel_badips';

        // ডাটাবেসে আইপি আছে কিনা চেক করা
        $is_banned = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE bad_ip = %s", $ip ) );

        if ( $is_banned ) {
            // যদি ব্যান থাকে, এরর রিটার্ন করুন
            return new WP_Error( 'rs_banned', '<strong>ERROR</strong>: Your IP address (' . esc_html($ip) . ') has been banned from this site.' );
        }

        return $user;
    }

    /**
     * 2. AJAX: Add IP to Ban List
     * 'Database function missing' এরর ফিক্স করা হয়েছে।
     */
    public function ajax_add_ban() {
        // Nonce verification matches bot.php
        check_ajax_referer( 'rs_ban_action' );

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Forbidden: You are not an administrator.');
        }

        $ip = sanitize_text_field( $_POST['ip'] );
        
        // IP ভ্যালিডেশন
        if ( ! filter_var($ip, FILTER_VALIDATE_IP) ) {
            wp_send_json_error('Invalid IP Address format.');
        }

        // [FIXED] RS_Database ক্লাস এবং মেথড আছে কিনা চেক করে কল করা
        if ( class_exists('RS_Database') && method_exists('RS_Database', 'block_ip_permanently') ) {
            RS_Database::block_ip_permanently( $ip, 'Manual Ban by Admin' );
            wp_send_json_success('IP Banned Successfully');
        } else {
            // ফলব্যাক (যদি কোনো কারণে ক্লাস লোড না হয়)
            global $wpdb;
            $table = $wpdb->prefix . 'royalsentinel_badips';
            
            // ম্যানুয়ালি ইনসার্ট করা
            $result = $wpdb->replace($table, [
                'bad_ip'   => $ip, 
                'reason'   => 'Manual Ban (Fallback)',
                'created_at' => current_time('mysql')
            ]);
            
            if($result !== false) {
                wp_send_json_success('IP Banned (Fallback Mode)');
            } else {
                wp_send_json_error('Database Insert Failed. Check table existence.');
            }
        }
    }

    /**
     * 3. AJAX: Unlock IP (Remove Ban or Lockout)
     * এটি bot.php এর 'Unlock' এবং 'Unban' বাটন হ্যান্ডেল করবে।
     */
    public function ajax_unlock_ip() {
        check_ajax_referer( 'rs_ban_action' ); // bot.php তে এই নামেই nonce তৈরি করা হয়েছে

        if ( ! current_user_can('manage_options') ) wp_send_json_error('Forbidden');

        $ip   = sanitize_text_field( $_POST['ip'] );
        $type = sanitize_text_field( $_POST['type'] ); // 'badip' অথবা 'lockout'

        global $wpdb;

        if ( $type === 'badip' ) {
            // ১. পারমানেন্ট ব্যান রিমুভ
            $table = class_exists('RS_Database') ? RS_Database::get_table_name('badips') : $wpdb->prefix . 'royalsentinel_badips';
            $wpdb->delete( $table, ['bad_ip' => $ip] );
        } elseif ( $type === 'lockout' ) {
            // ২. টেম্পোরারি লকআউট রিমুভ
            $table = class_exists('RS_Database') ? RS_Database::get_table_name('lockouts') : $wpdb->prefix . 'royalsentinel_lockouts';
            $wpdb->delete( $table, ['lockout_ip' => $ip] );
        }

        wp_send_json_success('Unlocked successfully');
    }
}

// ক্লাস ইনিশিলাইজ করা
new RS_Local_Ban();