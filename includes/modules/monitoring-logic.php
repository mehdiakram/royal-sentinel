<?php
/*
 * --------------------------------------------------------------------------
 * ROYAL SECURITY: MONITORING CORE (Fixed Prefix & Redirect)
 * --------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Monitoring_Core {

    private $table_logs;
    private $table_hashes;
    private $table_login;
    private $settings;

    public function __construct() {
        // [FIX] নতুন ডাটাবেজ ক্লাস ব্যবহার করে টেবিলের নাম আনা
        if ( class_exists('RS_Database') ) {
            $this->table_logs   = RS_Database::get_table_name('monitor_logs');
            $this->table_hashes = RS_Database::get_table_name('file_hashes');
            $this->table_login  = RS_Database::get_table_name('login_stats');
        } else {
            // ফলব্যাক যদি ক্লাস লোড না হয়
            global $wpdb;
            $this->table_logs   = $wpdb->prefix . 'royalsentinel_monitor_logs';
            $this->table_hashes = $wpdb->prefix . 'royalsentinel_file_hashes';
            $this->table_login  = $wpdb->prefix . 'royalsentinel_login_stats';
        }

        $this->settings = get_option('rs_security_settings', []);

        // Cron Event
        add_action('rs_daily_maintenance_event', [$this, 'run_daily_scans']);
        
        // --- 1. Activity Monitors ---
        if(!empty($this->settings['mon_activity_log'])) {
            add_action('wp_login', [$this, 'log_login'], 10, 2);
            add_action('switch_theme', [$this, 'log_theme_switch']);
            add_action('activated_plugin', [$this, 'log_plugin_activate']);
            add_action('deactivated_plugin', [$this, 'log_plugin_deactivate']);
            add_action('profile_update', [$this, 'log_profile_update'], 10, 2);
        }

        // --- 2. Login Monitor ---
        if(!empty($this->settings['mon_login_monitor'])) {
            add_action('wp_login_failed', [$this, 'log_failed_login']);
        }
        
        // --- 3. Database Monitor ---
        if(!empty($this->settings['mon_db_monitor'])) {
            foreach(['siteurl', 'home', 'admin_email', 'users_can_register'] as $opt) {
                add_action("update_option_{$opt}", [$this, 'log_option_change'], 10, 3);
            }
            add_action('user_register', [$this, 'log_new_admin_user']);
        }

        // --- Manual Scan Trigger (AJAX/Post) ---
        // এই হুকটি কাজ করার জন্য এই ফাইলটি মেইন প্লাগিন ফাইলে ইনক্লুড থাকতে হবে
        add_action('admin_post_rs_run_manual_scan', [$this, 'handle_manual_scan']);
    }

    // --- Daily Automated Scans ---
    public function run_daily_scans() {
        if(!empty($this->settings['mon_file_integrity'])) $this->scan_file_integrity();
        if(!empty($this->settings['mon_site_scanner']))   $this->scan_malware_patterns();
        if(!empty($this->settings['mon_vuln_detection'])) $this->check_vulnerabilities();
        
        $this->maintenance_cleanup();
    }

    // --- A. File Integrity Logic ---
    private function scan_file_integrity() {
        global $wpdb;
        $files = [
            ABSPATH . 'wp-config.php',
            ABSPATH . 'index.php',
            ABSPATH . 'wp-settings.php',
            ABSPATH . 'wp-load.php',
            get_template_directory() . '/functions.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $current_hash = md5_file($file);
                $stored = $wpdb->get_row($wpdb->prepare("SELECT content_hash FROM $this->table_hashes WHERE file_path = %s", $file));

                if ($stored) {
                    if ($stored->content_hash !== $current_hash) {
                        $this->log('file_change', "Critical file modified: " . basename($file), 3, ['path' => $file]);
                        $wpdb->update($this->table_hashes, ['content_hash' => $current_hash, 'last_checked' => current_time('mysql')], ['file_path' => $file]);
                    }
                } else {
                    $wpdb->insert($this->table_hashes, ['file_path' => $file, 'content_hash' => $current_hash]);
                }
            }
        }
    }

    // --- B. Site Scanner ---
    private function scan_malware_patterns() {
        $suspicious_patterns = '/(eval\(|base64_decode\(|shell_exec\(|passthru\(|system\()/i';
        $files_to_check = [ABSPATH . 'index.php', ABSPATH . 'wp-config.php', get_template_directory() . '/functions.php'];

        foreach ($files_to_check as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if (preg_match($suspicious_patterns, $content)) {
                    $this->log('site_scanner', "Suspicious code pattern found in " . basename($file), 3);
                }
            }
        }
    }

    // --- C. Vulnerability Detection ---
    private function check_vulnerabilities() {
        if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
        // Placeholder implementation
    }

    // --- Logger ---
    private function log($type, $message, $severity = 1, $meta = []) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $this->table_logs WHERE type=%s AND message=%s AND created_at > %s",
            $type, $message, date('Y-m-d H:i:s', strtotime('-1 hour'))
        ));

        if(!$exists) {
            $wpdb->insert($this->table_logs, [
                'type'      => $type,
                'severity'  => $severity,
                'message'   => $message,
                'metadata'  => json_encode($meta),
                'created_at'=> current_time('mysql')
            ]);
            if ($severity >= 3) $this->send_alert($type, $message);
        }
    }

    // --- Event Handlers ---
    public function log_login($user_login, $user) {
        if (in_array('administrator', (array) $user->roles)) {
            $this->log('auth', "Admin logged in: $user_login", 1);
        }
    }

    public function log_failed_login($username) {
        global $wpdb;
        
        $ip = rs_get_real_ip();
        $ip_hash = md5( sanitize_text_field( $ip ) );
        
        $query = "INSERT INTO $this->table_login (ip_hash, count, last_attempt) 
                  VALUES (%s, 1, %s) ON DUPLICATE KEY UPDATE count = count + 1, last_attempt = %s";
        $wpdb->query($wpdb->prepare($query, $ip_hash, current_time('mysql'), current_time('mysql')));

        $count = $wpdb->get_var($wpdb->prepare("SELECT count FROM $this->table_login WHERE ip_hash = %s", $ip_hash));
        if ($count == 10) $this->log('brute_force', "High failed login attempts (Hash: $ip_hash)", 2);
    }

    public function log_theme_switch($new_theme) {
        $theme = wp_get_theme($new_theme);
        $this->log('change', "Theme switched to: " . $theme->get('Name'), 2);
    }

    public function log_plugin_activate($plugin) {
        $this->log('change', "Plugin activated: $plugin", 2);
    }

    public function log_plugin_deactivate($plugin) {
        $this->log('change', "Plugin deactivated: $plugin", 2);
    }

    public function log_new_admin_user($user_id) {
        $user = get_userdata($user_id);
        if (in_array('administrator', (array)$user->roles)) {
            $this->log('security', "New Administrator created: " . $user->user_login, 3);
        }
    }

    public function log_profile_update($user_id, $old_user_data) {
        $user = get_userdata($user_id);
        if (in_array('administrator', (array)$user->roles) && !in_array('administrator', (array)$old_user_data->roles)) {
            $this->log('security', "User promoted to Administrator: " . $user->user_login, 3);
        }
    }

    public function log_option_change($option, $old_value, $new_value) {
        if ($old_value != $new_value) {
            $this->log('db_critical', "Critical Option Changed: $option", 3);
        }
    }

    public function maintenance_cleanup() {
        global $wpdb;
        $wpdb->query("DELETE FROM $this->table_logs WHERE created_at < NOW() - INTERVAL 30 DAY");
        $wpdb->query("DELETE FROM $this->table_login WHERE last_attempt < NOW() - INTERVAL 1 DAY");
    }

    private function send_alert($subject, $body) {
        $recipients = [];
        $mode = isset($this->settings['mon_alert_mode']) ? $this->settings['mon_alert_mode'] : 'all';

        if ($mode === 'specific') {
            // ১. নির্দিষ্ট অ্যাডমিনদের মেইল বের করা
            $user_ids = isset($this->settings['mon_alert_specific_users']) ? $this->settings['mon_alert_specific_users'] : [];
            if (!empty($user_ids)) {
                foreach ($user_ids as $user_id) {
                    $user_info = get_userdata($user_id);
                    if ($user_info) {
                        $recipients[] = $user_info->user_email;
                    }
                }
            }
        } else {
            // ২. সব অ্যাডমিনের মেইল বের করা
            $admins = get_users(['role' => 'administrator']);
            foreach ($admins as $admin) {
                $recipients[] = $admin->user_email;
            }
        }

        // ডুপ্লিকেট রিমুভ এবং মেইল সেন্ড
        $recipients = array_unique($recipients);
        
        if (!empty($recipients)) {
            $headers = array('Content-Type: text/html; charset=UTF-8');
            foreach ($recipients as $email) {
                wp_mail($email, "[Royal Sentinel Alert] $subject", $body, $headers);
            }
        }
    }

    // --- Manual Scan Handler (Fixed Redirect) ---
    public function handle_manual_scan() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        check_admin_referer('rs_manual_scan');
        
        // রান স্ক্যান
        $this->scan_file_integrity();
        $this->scan_malware_patterns();
        
        // সঠিক পেজে রিডাইরেক্ট করা
        $redirect_url = admin_url('admin.php?page=rs-advance-security&tab=monitoring&scan=success');
        wp_redirect($redirect_url);
        exit;
    }
}

new RS_Monitoring_Core();