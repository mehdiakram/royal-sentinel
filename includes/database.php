<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Database {

    public static function get_table_name( $base_name ) {
        global $wpdb;
        return $wpdb->prefix . 'royalsentinel_' . $base_name;
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $sql_statements = [];

        // ১. Bad IPs (স্থায়ী ব্যান - যেমন 'admin' দিয়ে চেষ্টা করলে)
        $table_badips = self::get_table_name('badips');
        $sql_statements[] = "CREATE TABLE $table_badips (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            bad_ip varchar(45) NOT NULL,
            reason varchar(50) DEFAULT 'manual',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY bad_ip (bad_ip)
        ) $charset_collate;";

        // ২. Lockouts (অস্থায়ী বা কাউন্ট ভিত্তিক - সাধারণ ভুল পাসওয়ার্ড)
        $table_lockouts = self::get_table_name('lockouts');
        $sql_statements[] = "CREATE TABLE $table_lockouts (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            lockout_ip varchar(45) NOT NULL,
            attempt_count int(11) DEFAULT 1,
            last_attempt datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY lockout_ip (lockout_ip),
            KEY last_attempt (last_attempt)
        ) $charset_collate;";        

        // ২.৫ Login Stats
        $table_login_stats = self::get_table_name('login_stats');
        $sql_statements[] = "CREATE TABLE $table_login_stats (
            ip_hash varchar(32) NOT NULL,
            count int(11) DEFAULT 1,
            last_attempt datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (ip_hash)
        ) $charset_collate;";


        // ৩. Monitoring Logs
        $table_logs = self::get_table_name('monitor_logs');
        $sql_statements[] = "CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            type varchar(50) NOT NULL,
            severity tinyint(1) NOT NULL DEFAULT 1,
            message text NOT NULL,
            metadata json DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY severity (severity),
            KEY created_at (created_at)
        ) $charset_collate;";

        // ৪. File Hashes
        $table_hashes = self::get_table_name('file_hashes');
        $sql_statements[] = "CREATE TABLE $table_hashes (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_path varchar(255) NOT NULL,
            content_hash varchar(32) NOT NULL,
            last_checked datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY file_path (file_path)
        ) $charset_collate;";

        // ৫. Firewall Rules & Permissions (নতুন টেবিল)
        $table_fw_rules = self::get_table_name('fw_rules');
        $sql_statements[] = "CREATE TABLE $table_fw_rules (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            rule_slug varchar(50) NOT NULL,
            scope varchar(20) DEFAULT 'all_admins', 
            allowed_users text DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY rule_slug (rule_slug)
        ) $charset_collate;";

        // ৬. Scan History
        $table_scan_history = self::get_table_name('scan_history');
        $sql_statements[] = "CREATE TABLE $table_scan_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            scan_date datetime DEFAULT CURRENT_TIMESTAMP,
            total_issues int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'running',
            modules_completed text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY scan_date (scan_date),
            KEY status (status)
        ) $charset_collate;";

        // ৭. Scan Issues
        $table_scan_issues = self::get_table_name('scan_issues');
        $sql_statements[] = "CREATE TABLE $table_scan_issues (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            scan_id bigint(20) NOT NULL,
            module varchar(50) NOT NULL,
            item_reference varchar(255) DEFAULT NULL,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            risk varchar(20) NOT NULL,
            status varchar(20) DEFAULT 'open',
            recommendation text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY scan_id (scan_id),
            KEY module (module),
            KEY risk (risk),
            KEY status (status)
        ) $charset_collate;";

        // ৮. Vulnerability Scan Summary
        $table_vuln_summary = self::get_table_name('vuln_summary');
        $sql_statements[] = "CREATE TABLE $table_vuln_summary (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            scan_date datetime DEFAULT CURRENT_TIMESTAMP,
            total_software int(11) DEFAULT 0,
            vulnerable_count int(11) DEFAULT 0,
            outdated_count int(11) DEFAULT 0,
            abandoned_count int(11) DEFAULT 0,
            environment_risks int(11) DEFAULT 0,
            scan_duration int(11) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY scan_date (scan_date)
        ) $charset_collate;";

        // ৯. Honeypot Logs (spam attempt logs with auto-cleanup support)
        $table_honeypot_logs = self::get_table_name('honeypot_logs');
        $sql_statements[] = "CREATE TABLE $table_honeypot_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            source varchar(50) NOT NULL,
            blocked_reason varchar(50) DEFAULT 'honeypot',
            user_agent text,
            form_data text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_created_at (created_at),
            KEY idx_ip (ip_address),
            KEY idx_source (source)
        ) $charset_collate;";


        dbDelta( $sql_statements );
        update_option( 'rs_db_version', '1.3.2' );
    }

    // --- Helper: সরাসরি ব্যান (badips টেবিলে) ---
    public static function block_ip_permanently($ip, $reason = 'admin_user') {
        // Global trusted IP check
        if ( function_exists('rs_is_trusted_ip') && rs_is_trusted_ip($ip) ) {
            return;
        }

        // লোকালহোস্ট আইপি কখনো ব্লক হবে না
        if ( in_array($ip, ['127.0.0.1', '::1']) ) {
            return;
        }

        global $wpdb;
        $table = self::get_table_name('badips');
        // আগে চেক করি আছে কিনা
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE bad_ip = %s", $ip));
        
        if (!$exists) {
            $wpdb->insert($table, [
                'bad_ip' => $ip,
                'reason' => $reason,
                'created_at' => current_time('mysql')
            ]);
        }
    }

    // --- Helper: লকআউট কাউন্ট (lockouts টেবিলে) ---
    public static function track_login_failure($ip) {
        global $wpdb;
        $table = self::get_table_name('lockouts');
        
        $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE lockout_ip = %s", $ip));

        if ($exists) {
            $wpdb->update($table, 
                ['attempt_count' => $exists->attempt_count + 1, 'last_attempt' => current_time('mysql')],
                ['lockout_ip' => $ip]
            );
            return $exists->attempt_count + 1;
        } else {
            $wpdb->insert($table, [
                'lockout_ip' => $ip, 
                'attempt_count' => 1, 
                'last_attempt' => current_time('mysql')
            ]);
            return 1;
        }
    }
}

// ইনিট
function rs_init_database() { RS_Database::create_tables(); }
register_activation_hook( dirname(dirname(__FILE__)) . '/royal-sentinel.php', 'rs_init_database' );