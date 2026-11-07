<?php
/*
 * --------------------------------------------------------------------------
 * ROYAL SECURITY: BACKUP AUTOMATION & LOGS (Renamed & Fixed)
 * --------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Backup_Logic {

    public function __construct() {
        // Manual Downloads
        add_action('admin_post_rs_download_db_backup', [$this, 'handle_manual_backup']);
        add_action('admin_post_rs_export_security_logs', [$this, 'handle_logs_export']);

        // Schedule Management
        add_action('admin_init', [$this, 'manage_schedules']);
        
        // Cron Hook
        add_action('rs_scheduled_backup_event', [$this, 'execute_scheduled_backup']);
    }

    // ১. শিডিউল ম্যানেজমেন্ট
    public function manage_schedules() {
        $settings = get_option('rs_security_settings', []);
        $freq = isset($settings['db_backup_freq']) ? $settings['db_backup_freq'] : 'manual';
        $is_enabled = isset($settings['db_backups']) && $settings['db_backups'] == 1;

        $timestamp = wp_next_scheduled('rs_scheduled_backup_event');

        if ( !$is_enabled || $freq === 'manual' ) {
            if ( $timestamp ) {
                wp_clear_scheduled_hook('rs_scheduled_backup_event');
            }
            return;
        }

        if ( ! $timestamp ) {
            wp_schedule_event(time(), $freq, 'rs_scheduled_backup_event');
        } 
    }

    // ২. অটোমেটিক ব্যাকআপ এক্সিকিউশন (Cron Job)
    public function execute_scheduled_backup() {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $settings = get_option('rs_security_settings', []);
        if ( empty($settings['db_backups']) ) return;

        $email_to = isset($settings['db_backup_email_addr']) ? $settings['db_backup_email_addr'] : get_option('admin_email');
        $use_zip  = isset($settings['db_backup_zip']) && $settings['db_backup_zip'] == 1;

        $file_info = $this->generate_sql_dump($use_zip);
        $file_path = $file_info['path'];

        if ( file_exists($file_path) ) {
            $subject = 'Database Backup - ' . get_bloginfo('name');
            $message = "Hello,\n\nHere is the automated database backup for your site: " . site_url() . "\n\nDate: " . date('Y-m-d H:i:s');
            $headers = ['Content-Type: text/plain; charset=UTF-8'];

            wp_mail($email_to, $subject, $message, $headers, [$file_path]);

            @unlink($file_path);
        }
    }

    // ৩. ম্যানুয়াল ডাউনলোড হ্যান্ডলার
    public function handle_manual_backup() {
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        check_admin_referer('rs_backup_action');

        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $settings = get_option('rs_security_settings', []);
        $use_zip  = isset($settings['db_backup_zip']) && $settings['db_backup_zip'] == 1;

        $file_info = $this->generate_sql_dump($use_zip);
        $file_path = $file_info['path'];
        $file_name = $file_info['name'];

        if ( file_exists($file_path) ) {
            if (ob_get_length()) ob_end_clean(); // বাফার ক্লিয়ার

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_name) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            
            readfile($file_path);
            
            @unlink($file_path);
            exit;
        } else {
            wp_die('Error: Backup file could not be created.');
        }
    }

    // ৪. কোর ব্যাকআপ জেনারেটর (নাম পরিবর্তন সহ)
    private function generate_sql_dump($zip_enabled = false) {
        global $wpdb;
        $db_name = DB_NAME;

        $upload_dir = wp_upload_dir();
        $temp_dir   = $upload_dir['basedir'] . '/rs-backups-temp';
        if ( ! file_exists($temp_dir) ) wp_mkdir_p($temp_dir);

        if (!file_exists($temp_dir . '/.htaccess')) {
            file_put_contents($temp_dir . '/.htaccess', 'deny from all');
        }

        // [CHANGE] ওয়েবসাইটের ক্লিন নাম বের করা
        $site_url = site_url();
        $clean_url = str_replace(['http://', 'https://', 'www.'], '', $site_url);
        $clean_url = sanitize_file_name($clean_url); // স্ল্যাশ বা বিশেষ ক্যারেক্টার রিমুভ করবে

        // [CHANGE] নতুন নাম ফরম্যাট: URL_db-backup-TIME
        $base_name = $clean_url . '_db-backup-' . date('Y-m-d-H-i-s');
        $sql_file  = $temp_dir . '/' . $base_name . '.sql';

        $f = fopen($sql_file, 'w');

        fwrite($f, "-- Royal Sentinel Backup\n-- Site: $clean_url\n-- Date: " . date('Y-m-d H:i:s') . "\n\n");

        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        
        foreach ($tables as $table_row) {
            $table = $table_row[0];
            $create = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
            
            fwrite($f, "\nDROP TABLE IF EXISTS `$table`;\n");
            fwrite($f, $create[1] . ";\n\n");

            $rows = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);
            if ($rows) {
                foreach ($rows as $row) {
                    $values = array_map(function($val) {
                        return $val === null ? 'NULL' : "'" . esc_sql($val) . "'";
                    }, $row);
                    $line = "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                    fwrite($f, $line);
                }
            }
        }
        fclose($f);

        if ( $zip_enabled && class_exists('ZipArchive') ) {
            $zip_file = $temp_dir . '/' . $base_name . '.zip';
            $zip = new ZipArchive();
            if ( $zip->open($zip_file, ZipArchive::CREATE) === TRUE ) {
                $zip->addFile($sql_file, $base_name . '.sql');
                $zip->close();
                
                @unlink($sql_file);
                return ['path' => $zip_file, 'name' => $base_name . '.zip'];
            }
        }

        return ['path' => $sql_file, 'name' => $base_name . '.sql'];
    }

    // ৫. লগ এক্সপোর্ট হ্যান্ডলার (নাম পরিবর্তন সহ)
    public function handle_logs_export() {
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        check_admin_referer('rs_backup_action');

        global $wpdb;

        // [CHANGE] ওয়েবসাইটের ক্লিন নাম বের করা
        $site_url = site_url();
        $clean_url = str_replace(['http://', 'https://', 'www.'], '', $site_url);
        $clean_url = sanitize_file_name($clean_url);

        // [CHANGE] নতুন নাম ফরম্যাট: URL_security-logs-TIME
        $filename = $clean_url . '_security-logs-' . date('Y-m-d-H-i-s') . '.csv';

        if (ob_get_length()) ob_end_clean();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $output = fopen('php://output', 'w');
        
        $table_ban = isset($wpdb->rs_bad_ips) ? $wpdb->rs_bad_ips : $wpdb->prefix . 'rs_bad_ips';

        fputcsv($output, ['IP Address', 'Attempts', 'Last Attempt', 'Status']);

        if ( $wpdb->get_var("SHOW TABLES LIKE '$table_ban'") == $table_ban ) {
            $logs = $wpdb->get_results("SELECT bad_ip, attempt_count, last_attempt FROM $table_ban ORDER BY last_attempt DESC", ARRAY_A);
            if($logs) {
                foreach ($logs as $log) {
                    fputcsv($output, [$log['bad_ip'], $log['attempt_count'], $log['last_attempt'], 'Banned']);
                }
            } else {
                 fputcsv($output, ['No logs found in database.']);
            }
        } else {
            fputcsv($output, ['Table not found.']);
        }
        fclose($output);
        exit;
    }
}
new RS_Backup_Logic();