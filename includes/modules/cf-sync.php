<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_CF_Sync {

    public function __construct() {
        // ১. ক্রন শিডিউল সেট করা (অ্যাক্টিভেশনের সময় বা ইনিশিয়ালাইজেশনে)
        add_action( 'init', [ $this, 'schedule_daily_sync' ] );
        
        // ২. ক্রন ইভেন্ট হুক
        add_action( 'rs_daily_ip_sync_event', [ $this, 'execute_sync' ] );

        // ৩. ম্যানুয়াল সিঙ্কের জন্য AJAX
        add_action( 'wp_ajax_rs_manual_cf_sync', [ $this, 'handle_manual_sync' ] );
    }

    /**
     * ক্রন শিডিউল করা (প্রতিদিন রাত ১২:০১ এ)
     */
    public function schedule_daily_sync() {
        if ( ! wp_next_scheduled( 'rs_daily_ip_sync_event' ) ) {
            // আগামিকাল রাত ১২:০১ এর টাইমস্ট্যাম্প বের করা
            $time = strtotime( 'tomorrow 00:01' );
            wp_schedule_event( $time, 'daily', 'rs_daily_ip_sync_event' );
        }
    }

    /**
     * মূল সিঙ্ক ফাংশন (Local DB -> Cloudflare)
     */
    public function execute_sync() {
        global $wpdb;
        $table_name = RS_TABLE_BADIPS;

        // ১. লোকাল ডাটাবেস থেকে সব আইপি আনা
        $local_ips = $wpdb->get_col( "SELECT bad_ip FROM $table_name" );

        if ( empty( $local_ips ) ) {
            return [ 'success' => false, 'message' => 'Local database is empty.' ];
        }

        // ২. ক্লাউডফ্লেয়ারের বর্তমান লিস্ট আনা (ডুপ্লিকেট চেক করার জন্য)
        if ( ! function_exists('rs_get_list_items') ) return [ 'success' => false, 'message' => 'API function missing.' ];
        
        // ক্যাশ বাইপাস করে ফ্রেশ ডাটা আনা ভালো, তবে পারফরম্যান্সের জন্য ক্যাশ থাকলেও সমস্যা নেই
        // আমরা এখানে ক্যাশ ক্লিয়ার করে ফ্রেশ ডাটা নিচ্ছি
        delete_transient( 'rs_cached_ips' );
        $cf_data = rs_get_list_items();

        if ( ! empty($cf_data['error']) ) {
            return [ 'success' => false, 'message' => 'CF API Error: ' . $cf_data['error'] ];
        }

        $cf_ips = [];
        if ( ! empty( $cf_data['items'] ) ) {
            $cf_ips = array_column( $cf_data['items'], 'ip' );
        }

        // ৩. ডুপ্লিকেট বাদ দিয়ে নতুন আইপি বের করা
        $ips_to_add = array_diff( $local_ips, $cf_ips );

        if ( empty( $ips_to_add ) ) {
            return [ 'success' => true, 'message' => 'All local IPs are already in Cloudflare.' ];
        }

        // ৪. ব্যাচ আকারে আইপি প্রস্তুত করা
        $batch_items = [];
        foreach ( $ips_to_add as $ip ) {
            // ভ্যালিড আইপি চেক
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                $batch_items[] = [
                    'ip'      => $ip,
                    'comment' => 'Auto-synced from Royal Sentinel'
                ];
            }
        }

        if ( empty( $batch_items ) ) {
            return [ 'success' => false, 'message' => 'No valid new IPs to add.' ];
        }

        // ৫. Cloudflare API তে পাঠানো (চ্যাংক করে, ১০০০ এর লিমিট থাকতে পারে)
        $chunks = array_chunk( $batch_items, 1000 );
        $added_count = 0;
        $errors = [];

        foreach ( $chunks as $chunk ) {
            $resp = rs_api_batch_add_ips( $chunk );
            if ( $resp['success'] ) {
                $added_count += count( $chunk );
            } else {
                $errors[] = $resp['message'];
            }
        }

        // শেষে ক্যাশ ক্লিয়ার করা
        delete_transient( 'rs_cached_ips' );

        if ( $added_count > 0 ) {
            return [ 'success' => true, 'message' => "Successfully synced $added_count IPs to Cloudflare." ];
        } else {
            return [ 'success' => false, 'message' => 'Failed to add IPs. ' . implode(', ', $errors) ];
        }
    }

    /**
     * AJAX হ্যান্ডলার (ম্যানুয়াল বাটনের জন্য)
     */
    public function handle_manual_sync() {
        check_ajax_referer( 'rs_sync_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $result = $this->execute_sync();

        if ( $result['success'] ) {
            wp_send_json_success( $result['message'] );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }
}

new RS_CF_Sync();