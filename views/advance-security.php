<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ১. সেটিংস লোড করা
$rs_settings_raw = get_option( 'rs_security_settings', [] );
$rs_settings = is_array($rs_settings_raw) ? $rs_settings_raw : [];

$active_tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'login';
$success_script = '';

// ফিচার তালিকা (ফাঁকা রাখা হয়েছে যাতে সব অপশন এনাবল থাকে)
$upcoming_features = []; 

// ৩. ট্যাব কনফিগারেশন
$tabs = [
    'login'           => ['label' => 'Login Security',   'icon' => 'dashicons-admin-network'],
    'bot'             => ['label' => 'Bot Protection',   'icon' => 'dashicons-shield'],
    'hardening'       => ['label' => 'Hardening',        'icon' => 'dashicons-admin-links'],
    'headers'         => ['label' => 'Security Headers', 'icon' => 'dashicons-admin-generic'],
    'realtime'        => ['label' => 'Real-time Defense','icon' => 'dashicons-superhero'],
    'sessions'        => ['label' => 'Sessions',         'icon' => 'dashicons-groups'],
    'geoblocking'     => ['label' => 'Geo-Blocking',     'icon' => 'dashicons-admin-site'],
    'securitytxt'     => ['label' => 'Security.txt',     'icon' => 'dashicons-media-text'],
    'monitoring'      => ['label' => 'Monitoring',       'icon' => 'dashicons-visibility'],
    'backups'         => ['label' => 'Backups',          'icon' => 'dashicons-backup'],
    'tools'           => ['label' => 'Tools',            'icon' => 'dashicons-admin-tools'],
];

// ৪. সেভ লজিক - [ROBUST SAVE LOGIC]
if ( isset( $_POST['rs_save_security'] ) ) {
    
    // [FIXED] প্রতিটি ট্যাবের জন্য নির্দিষ্ট চেকবক্স ফিল্ডের তালিকা
    $fields_by_tab = [
        'login' => [
            'webauthn_passkeys',
            '2fa', '2fa_disable_first', 'pass_policy', 'passless_login', 'magic_links', 'trusted_devices',
            'pp_req_upper', 'pp_req_digit', 'pp_req_special', 'ml_one_time'
        ],
        'bot' => [
            'local_brute', 'lockout_admin', 'firewall_engine', 'ban_users'
        ],
        'hardening' => [
            // [নতুন যুক্ত করা হয়েছে যা আগে মিসিং ছিল]
            'hide_login', 'enable_redirection', 
            'force_unique_nickname', 'disable_extra_archives', 
            'xmlrpc_mult_auth', 'rest_block_user_enum',
            
            // আগের সিস্টেম ফিল্ডগুলো
            'hide_wp_version', 'sys_tweaks', 'disable_install_update', 'protect_system_files', 
            'disable_directory_browsing', 'disable_php_uploads', 'disable_php_plugins', 
            'disable_php_themes', 'config_hardening', 'ssl_enforce', 'recaptcha'
        ],
        'monitoring' => [
            'mon_file_integrity', 
            'mon_site_scanner', 
            'mon_vuln_detection', 
            'mon_activity_log', 
            'mon_login_monitor', 
            'mon_db_monitor'
        ],
        'backups' => [
            'db_backups', 'logs_export', 'db_backup_zip'
        ],
        'headers' => [
            'header_csp', 'header_xfo', 'header_xcto', 'header_xss', 
            'header_rp', 'header_pp', 'header_coop', 'header_corp', 'header_xpcdp', 'header_hsts', 'header_hide_powered_by'
        ],
        'realtime' => [
            'rt_rate_limit', 'rt_bad_query', 'rt_bad_ua', 'rt_404_detect',
            'rt_auto_whitelist_admin', 'rt_whitelist_localhost'
        ],
        'sessions' => [
            'sess_tracking', 'sess_limit_concurrent', 'sess_notify_new'
        ],
        'geoblocking' => [
            'geo_enabled'
        ],
        'securitytxt' => [
            'securitytxt_enable', 'securitytxt_credit'
        ]
    ];

    // বর্তমান ট্যাবের জন্য কোন ফিল্ডগুলো প্রসেস করতে হবে তা বের করা
    $target_fields = isset($fields_by_tab[$active_tab]) ? $fields_by_tab[$active_tab] : [];

    $scalar_fields = [
        'login_slug'          => 'sanitize_text_field',
        'register_slug'       => 'sanitize_text_field',
        'redirect_slug'       => 'sanitize_text_field',
        'max_attempts_ip'     => 'sanitize_text_field',
        'max_attempts_user'   => 'sanitize_text_field',
        'retry_period'        => 'sanitize_text_field',
        'max_fw_violations'   => 'sanitize_text_field',
        'fw_check_period'     => 'sanitize_text_field',
        'login_restriction'   => 'sanitize_text_field',
        'xmlrpc_mode'         => 'sanitize_text_field',
        'rest_api_mode'       => 'sanitize_text_field',
        '2fa_method'          => 'sanitize_text_field',
        '2fa_welcome_text'    => 'sanitize_textarea_field',
        'pp_min_len'          => 'sanitize_text_field',
        'pp_expiry_days'      => 'sanitize_text_field',
        'pl_roles'            => 'sanitize_text_field',
        'ml_expiry'           => 'sanitize_text_field',
        'td_duration'         => 'sanitize_text_field',
        'db_backup_freq'       => 'sanitize_text_field',
        'db_backup_email_addr' => 'sanitize_email',
        'mon_alert_mode'       => 'sanitize_text_field',
        
        // Security Headers
        'csp_policy'          => 'sanitize_textarea_field',
        'xfo_mode'            => 'sanitize_text_field',
        'rp_mode'             => 'sanitize_text_field',
        'coop_mode'           => 'sanitize_text_field',
        'corp_mode'           => 'sanitize_text_field',
        'hsts_max_age'        => 'sanitize_text_field',
        
        // Real-time Defense
        'rt_rate_limit_max'    => 'sanitize_text_field',
        'rt_rate_limit_window' => 'sanitize_text_field',
        'rt_404_threshold'     => 'sanitize_text_field',
        'rt_404_window'        => 'sanitize_text_field',
        
        // Sessions
        'sess_max_concurrent'  => 'sanitize_text_field',
        'sess_idle_timeout'    => 'sanitize_text_field',
        
        // Geo-Blocking
        'geo_mode'             => 'sanitize_text_field',
        'geo_action'           => 'sanitize_text_field',
        'geo_redirect_url'     => 'esc_url_raw',
        'geo_whitelist_ips'    => 'sanitize_textarea_field',
        
        // Security.txt
        'securitytxt_contact'         => 'sanitize_text_field',
        'securitytxt_encryption'      => 'esc_url_raw',
        'securitytxt_policy'          => 'esc_url_raw',
        'securitytxt_acknowledgments' => 'esc_url_raw',
        'securitytxt_hiring'          => 'esc_url_raw',
        'securitytxt_langs'           => 'sanitize_text_field',
        'securitytxt_expiry'          => 'sanitize_text_field',
        
        // Recaptcha Keys
        'recaptcha_site_key'   => 'sanitize_text_field',
        'recaptcha_secret_key' => 'sanitize_text_field',
    ];


    $new_settings = $rs_settings; 

    if ( $active_tab === 'monitoring' ) {
        if ( isset($_POST['mon_alert_specific_users']) && is_array($_POST['mon_alert_specific_users']) ) {
            $new_settings['mon_alert_specific_users'] = array_map('intval', $_POST['mon_alert_specific_users']);
        } else {
            // যদি সব আনচেক করে দেয়, তাহলে অ্যারে ফাঁকা হবে
            $new_settings['mon_alert_specific_users'] = [];
        }
    }
    
    // Handle 2fa_roles array for login tab
    if ( $active_tab === 'login' ) {
        if ( isset($_POST['2fa_roles']) && is_array($_POST['2fa_roles']) ) {
            $new_settings['2fa_roles'] = array_map('sanitize_text_field', $_POST['2fa_roles']);
        } elseif ( isset($_POST['2fa_roles_all']) && $_POST['2fa_roles_all'] == '1' ) {
            $new_settings['2fa_roles'] = 'all';
        } else {
            // If nothing selected, keep existing value or default to 'all'
            $new_settings['2fa_roles'] = isset($rs_settings['2fa_roles']) ? $rs_settings['2fa_roles'] : 'all';
        }
    }

    // Handle geo_countries array
    if ( $active_tab === 'geoblocking' ) {
        if ( isset($_POST['geo_countries']) && is_array($_POST['geo_countries']) ) {
            $new_settings['geo_countries'] = array_map('sanitize_text_field', $_POST['geo_countries']);
        } else {
            $new_settings['geo_countries'] = [];
        }
    }

    // 1. Handle Boolean Fields (বর্তমান ট্যাবের জন্য)
    foreach ($target_fields as $key) {
        $new_settings[$key] = isset($_POST[$key]) ? 1 : 0;
    }

    // 2. Handle Scalar Fields
    foreach ($scalar_fields as $key => $sanitizer_function) {
        $value = $_POST[$key] ?? null; 
        
        if ($value !== null) {
            $value = wp_unslash( $value );
            $new_settings[$key] = call_user_func($sanitizer_function, $value); 
        } 
    }

    update_option( 'rs_security_settings', $new_settings );
    $rs_settings = $new_settings; // ভেরিয়েবল আপডেট

    $success_script = "
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Saved!',
                text: 'Security configuration updated successfully.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        });
    </script>";
}
?>

<div class="wrap">
    <div class="rs-page-header purple-gradient">
        <div class="rs-header-content">
            <h1 class="rs-page-title">
                <div class="rs-process-icon">🛡️</div>
                Advance Security
            </h1>
            <p class="rs-page-subtitle">Configure advanced protection layers based on category.</p>
        </div>
    </div>

    <h2 class="nav-tab-wrapper">
        <?php foreach ( $tabs as $slug => $info ): 
            if ( ! is_array($info) ) continue;
        ?>
            <a href="?page=<?php echo esc_attr($_GET['page']); ?>&tab=<?php echo esc_attr($slug); ?>" 
               class="nav-tab <?php echo $active_tab == $slug ? 'nav-tab-active' : ''; ?>">
               <span class="dashicons <?php echo esc_attr($info['icon']); ?>" style="vertical-align:text-bottom; margin-right:5px;"></span>
               <?php echo esc_html( $info['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <div style="background:#fff; padding:30px; border:1px solid #ccd0d4; border-top:none; box-shadow: 0 1px 3px rgba(0,0,0,.04);">
        
        <form id="rs-security-form" method="post">
            <input type="hidden" name="rs_save_security" value="1">

            <?php 
                // ট্যাব ফাইল লোড
                $file_path = ROYALSENTINEL_PLUGIN_DIR . 'views/advance-security/' . $active_tab . '.php';
                
                if ( file_exists( $file_path ) ) {
                    include $file_path;
                } else {
                    echo '<p style="color:red;">Error: Tab file not found.</p>';
                }
            ?>

            <div style="margin-top:30px; border-top:1px solid #eee; padding-top:20px;">
                <button type="submit" id="rs-submit-btn" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="vertical-align:middle; margin-right:5px;"></span>
                    <?php esc_html_e( 'Save Settings', 'royal-sentinel' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php echo $success_script; ?>