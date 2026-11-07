<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// ================================================================
// ১. ডাটাবেস টেবিল ও চার্ট ডাটা
// ================================================================
$table_badips   = class_exists('RS_Database') ? RS_Database::get_table_name('badips') : $wpdb->prefix . 'royalsentinel_badips';
$table_lockouts = class_exists('RS_Database') ? RS_Database::get_table_name('lockouts') : $wpdb->prefix . 'royalsentinel_lockouts';
$table_logs     = class_exists('RS_Database') ? RS_Database::get_table_name('monitor_logs') : $wpdb->prefix . 'royalsentinel_monitor_logs';

// Threats Chart Data (Last 7 Days)
$last_7_days = [];
$threat_counts = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last_7_days[] = date('M j', strtotime($date)); 
    $threat_counts[$date] = 0;
}
if ($wpdb->get_var("SHOW TABLES LIKE '$table_logs'") == $table_logs) {
    $logs_data = $wpdb->get_results("SELECT DATE(created_at) as log_date, COUNT(id) as count FROM $table_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY log_date");
    foreach ($logs_data as $log) { 
        if (isset($threat_counts[$log->log_date])) $threat_counts[$log->log_date] = $log->count; 
    }
}
$chart_threat_data = array_values($threat_counts);

// Stats Counts
$count_permanent = $wpdb->get_var("SELECT COUNT(id) FROM $table_badips");
$count_temporary = $wpdb->get_var("SELECT COUNT(id) FROM $table_lockouts");
$total_attacks   = $wpdb->get_var("SELECT SUM(attempt_count) FROM $table_lockouts") ?: 0;
$active_today    = $wpdb->get_var("SELECT COUNT(id) FROM $table_lockouts WHERE last_attempt >= CURDATE()");

// Top Attackers
$top_attackers = $wpdb->get_results("SELECT lockout_ip, attempt_count FROM $table_lockouts WHERE last_attempt >= NOW() - INTERVAL 24 HOUR ORDER BY attempt_count DESC LIMIT 10", ARRAY_A);
$top_labels = []; $top_data = [];
if (!empty($top_attackers)) {
    foreach ($top_attackers as $a) { $top_labels[] = $a['lockout_ip']; $top_data[] = $a['attempt_count']; }
} else { 
    $top_labels[] = 'No recent attacks'; $top_data[] = 0; 
}

// Risk Intensity
$low_risk = intval($wpdb->get_var("SELECT COUNT(id) FROM $table_lockouts WHERE attempt_count <= 5"));
$med_risk = intval($wpdb->get_var("SELECT COUNT(id) FROM $table_lockouts WHERE attempt_count > 5 AND attempt_count <= 20"));
$high_risk = intval($wpdb->get_var("SELECT COUNT(id) FROM $table_lockouts WHERE attempt_count > 20"));
$has_risk_data = ($low_risk + $med_risk + $high_risk) > 0;

// Cloudflare Info
$cf_email = get_option('rs_cf_email'); 
$cf_api_key = get_option('rs_cf_api_key');
$auth_mode  = get_option('rs_cf_auth_mode', 'global');
if ( $auth_mode === 'single' ) {
    $has_cf_api = !empty($cf_api_key);
} else {
    $has_cf_api = (!empty($cf_email) && !empty($cf_api_key));
}
$cf_domains_count = 0; $cf_blocked_count = 0; $cf_bandwidth_saved = '0 MB'; 
if ( $has_cf_api ) {
    if ( function_exists('rs_get_cf_zones') ) { $z = rs_get_cf_zones(); if(!empty($z['items'])) $cf_domains_count = count($z['items']); }
    if ( function_exists('rs_get_list_items') ) { $l = rs_get_list_items(); if(!empty($l['items'])) $cf_blocked_count = count($l['items']); }
}

// ================================================================
// ২. SECURITY SCORE CALCULATOR (Using Verified Keys)
// ================================================================
$score = 0;
$checklist = [];

// 1. সব সেটিংস লোড এবং মার্জ করা
$s1 = get_option('rs_settings_option_name', []); // Main Settings
$s2 = get_option('rs_login_settings', []);
$s3 = get_option('rs_bot_settings', []);
$s4 = get_option('rs_hardening_settings', []);
$s5 = get_option('rs_security_settings', []);

$settings = array_merge(
    is_array($s1) ? $s1 : [],
    is_array($s2) ? $s2 : [],
    is_array($s3) ? $s3 : [],
    is_array($s4) ? $s4 : [],
    is_array($s5) ? $s5 : []
);

/**
 * Helper to safely check boolean/on/1 settings
 */
function rs_is_on($key, $data) {
    if (isset($data[$key])) {
        $val = $data[$key];
        return ($val == 1 || $val === 'on' || $val === true);
    }
    return false;
}

// --- CHECKLIST ITEMS ---

// 1. Two-Factor Authentication (10)
if (rs_is_on('2fa', $settings)) {
    $score += 10; 
    $checklist[] = ['name' => 'Two-Factor Authentication', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Two-Factor Authentication', 'ok' => false, 'url' => '?page=rs-advance-security&tab=login'];
}

// 2. Password Policy (10)
if (rs_is_on('pass_policy', $settings)) {
    $score += 10; 
    $checklist[] = ['name' => 'Password Policy', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Password Policy', 'ok' => false, 'url' => '?page=rs-advance-security&tab=login'];
}

// 3. Local Brute Force (10)
if (rs_is_on('local_brute', $settings)) {
    $score += 10; 
    $checklist[] = ['name' => 'Local Brute Force', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Local Brute Force', 'ok' => false, 'url' => '?page=rs-advance-security&tab=bot'];
}

// 4. Firewall Rules Engine (10)
if (rs_is_on('firewall_engine', $settings)) {
    $score += 10; 
    $checklist[] = ['name' => 'Firewall Rules Engine', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Firewall Rules Engine', 'ok' => false, 'url' => '?page=rs-advance-security&tab=bot'];
}

// 5. Enable Hide Backend (10)
if (rs_is_on('hide_login', $settings)) {
    $score += 10; 
    $checklist[] = ['name' => 'Enable Hide Backend', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Enable Hide Backend', 'ok' => false, 'url' => '?page=rs-advance-security&tab=hardening'];
}

// 6. Database & User Security (10)
$xmlrpc_secure = (isset($settings['xmlrpc_mode']) && $settings['xmlrpc_mode'] !== 'enable');
$admin_secure  = !username_exists('admin');

if ($xmlrpc_secure || $admin_secure) {
    $score += 10; 
    $checklist[] = ['name' => 'Database & User Security', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Database & User Security', 'ok' => false, 'url' => '?page=rs-advance-security&tab=hardening'];
}

// 7. Monitoring Configuration (10)
if (rs_is_on('mon_file_integrity', $settings)) {
    $score += 10; 
    $checklist[] = ['name' => 'Monitoring Configuration', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Monitoring Configuration', 'ok' => false, 'url' => '?page=rs-advance-security&tab=monitoring'];
}

// 8. Cloudflare Integration (10)
if ($has_cf_api) {
    $score += 10; 
    $checklist[] = ['name' => 'Cloudflare Integration', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Cloudflare Integration', 'ok' => false, 'url' => '?page=rs-settings&tab=cloudflare'];
}

// 9. Hide WordPress Version (5)
if (rs_is_on('hide_wp_version', $settings)) {
    $score += 5; 
    $checklist[] = ['name' => 'Hide WordPress Version', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Hide WordPress Version', 'ok' => false, 'url' => '?page=rs-advance-security&tab=hardening'];
}

// 10. Disable File Editor (5)
if (rs_is_on('sys_tweaks', $settings)) {
    $score += 5; 
    $checklist[] = ['name' => 'Disable File Editor', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Disable File Editor', 'ok' => false, 'url' => '?page=rs-advance-security&tab=hardening'];
}

// 11. Disable Directory Browsing (5)
if (rs_is_on('disable_directory_browsing', $settings)) {
    $score += 5; 
    $checklist[] = ['name' => 'Disable Directory Browsing', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Disable Directory Browsing', 'ok' => false, 'url' => '?page=rs-advance-security&tab=hardening'];
}

// 12. Disable PHP in Uploads (5)
if (rs_is_on('disable_php_uploads', $settings)) {
    $score += 5; 
    $checklist[] = ['name' => 'Disable PHP in Uploads', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Disable PHP in Uploads', 'ok' => false, 'url' => '?page=rs-advance-security&tab=hardening'];
}

// 13. Enable Passkeys (10)
if (rs_is_on('webauthn_passkeys', $settings)) {
    $score += 10; 
    $checklist[] = ['name' => 'Enable Passkeys', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Enable Passkeys', 'ok' => false, 'url' => '?page=rs-advance-security&tab=login'];
}

// 14. Enable Redirection (10)
if (rs_is_on('enable_redirection', $settings)) {
    $score += 10; 
    $checklist[] = ['name' => 'Enable Redirection', 'ok' => true];
} else {
    $checklist[] = ['name' => 'Enable Redirection', 'ok' => false, 'url' => '?page=rs-advance-security&tab=hardening'];
}

// Cap Maximum Score at 100
if ($score > 100) {
    $score = 100;
}

// Calculate Color
$score_color = '#e74c3c'; // Red
if ($score >= 100) $score_color = '#27ae60'; // Green
elseif ($score >= 70) $score_color = '#f1c40f'; // Orange
?>

<div class="wrap rs-dashboard-wrap">
    
    <div class="rs-page-header dark-gradient">
        <div class="rs-header-content">
            <h1 class="rs-page-title">
                <div class="rs-process-icon">📊</div>
                Security Dashboard
            </h1>
            <p class="rs-page-subtitle">Real-time threat intelligence and system health.</p>
        </div>
        <div style="position: absolute; top: 20px; right: 20px;">
            <span class="rs-version-badge">v<?php echo defined('ROYALSENTINEL_VERSION') ? ROYALSENTINEL_VERSION : '1.0'; ?></span>
        </div>
    </div>

    <div class="rs-card-grid">
        <div class="rs-card" style="border-left-color: #8e44ad;">
            <div class="rs-card-icon" style="background: #f4ecf7; color: #8e44ad;"><span class="dashicons dashicons-shield"></span></div>
            <div class="rs-card-content"><h3><?php echo number_format_i18n($count_permanent); ?></h3><span>Permanent Bans</span></div>
        </div>
        <div class="rs-card" style="border-left-color: #e67e22;">
            <div class="rs-card-icon" style="background: #fdf2e9; color: #e67e22;"><span class="dashicons dashicons-lock"></span></div>
            <div class="rs-card-content"><h3><?php echo number_format_i18n($count_temporary); ?></h3><span>Temp Lockouts</span></div>
        </div>
        <div class="rs-card" style="border-left-color: #c0392b;">
            <div class="rs-card-icon" style="background: #fdedec; color: #c0392b;"><span class="dashicons dashicons-warning"></span></div>
            <div class="rs-card-content"><h3><?php echo number_format_i18n($total_attacks); ?></h3><span>Total Attempts</span></div>
        </div>
        <div class="rs-card" style="border-left-color: #2980b9;">
            <div class="rs-card-icon" style="background: #ebf5fb; color: #2980b9;"><span class="dashicons dashicons-clock"></span></div>
            <div class="rs-card-content"><h3><?php echo number_format_i18n($active_today); ?></h3><span>Active Today</span></div>
        </div>
    </div>

    <h3 style="margin: 30px 0 15px 0; color:#444;">🛡️ System Health</h3>
    <div class="rs-health-wrapper">
        <div class="rs-card rs-score-card" style="border-left-color: <?php echo $score_color; ?>;">
            <div class="rs-card-icon" style="background: <?php echo $score_color; ?>20; color: <?php echo $score_color; ?>;">
                <span class="dashicons dashicons-performance"></span>
            </div>
            <h3 style="font-size:36px; color:#333; margin:0;"><?php echo $score; ?>/100</h3>
            <span style="color: <?php echo $score_color; ?>; font-weight:600;">Security Score</span>
        </div>

        <div class="rs-card rs-checklist-card">
            <h3 style="margin:0 0 10px 0; border-bottom: 1px solid #eee; padding-bottom: 10px; font-size:16px;">
                📋 Security Checklist
                <span style="float:right; font-size:12px; font-weight:normal; color:#888;">
                    <?php echo count(array_filter($checklist, function($i){return $i['ok'];})); ?>/<?php echo count($checklist); ?> Active
                </span>
            </h3>
            
            <ul class="rs-rec-list">
                <?php foreach ($checklist as $item): ?>
                    <li style="display:flex; justify-content:space-between; align-items:center; padding: 6px 0; border-bottom: 1px dashed #eee;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <?php if ($item['ok']): ?>
                                <span class="dashicons dashicons-yes" style="color:#27ae60; font-size:18px;"></span>
                                <span style="color:#333; font-weight:500;"><?php echo esc_html($item['name']); ?></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color:#e74c3c; font-size:18px;"></span>
                                <span style="color:#555;"><?php echo esc_html($item['name']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$item['ok']): ?>
                            <a href="<?php echo esc_url($item['url']); ?>" class="rs-fix-btn">Fix &rarr;</a>
                        <?php else: ?>
                            <span class="status-ok">OK</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php if ( $has_cf_api ) : ?>
    <h3 style="margin: 30px 0 15px 0; color:#444;">☁️ Cloudflare Insights</h3>
    <div class="rs-card-grid" style="grid-template-columns: repeat(3, 1fr);">
        <div class="rs-card" style="border-left-color: #2c3e50;">
            <div class="rs-card-icon" style="background: #e8eaeb; color: #2c3e50;"><span class="dashicons dashicons-admin-site-alt3"></span></div>
            <div class="rs-card-content"><h3><?php echo $cf_domains_count; ?></h3><span>Active Zones</span></div>
        </div>
        <div class="rs-card" style="border-left-color: #1abc9c;">
            <div class="rs-card-icon" style="background: #e0f7fa; color: #1abc9c;"><span class="dashicons dashicons-download"></span></div>
            <div class="rs-card-content"><h3><?php echo $cf_bandwidth_saved; ?></h3><span>Bandwidth Saved</span></div>
        </div>
        <div class="rs-card" style="border-left-color: #d63638;">
            <div class="rs-card-icon" style="background: #ffe6e6; color: #d63638;"><span class="dashicons dashicons-ban"></span></div>
            <div class="rs-card-content"><h3><?php echo $cf_blocked_count; ?></h3><span>Cloud Blocked</span></div>
        </div>
    </div>
    <?php endif; ?>

    <h3 style="margin: 30px 0 15px 0; color:#444;">📈 Attack Analysis</h3>
    <div class="rs-charts-wrapper">
        <div class="rs-chart-box">
            <h3 class="rs-chart-title">Threads Blocked (7 Days)</h3>
            <div class="rs-chart-container" style="height: 300px;"><canvas id="threatsHistoryChart"></canvas></div>
        </div>
        <div class="rs-chart-box">
            <h3 class="rs-chart-title">Bans Overview</h3>
            <div class="rs-chart-container" style="height: 250px;"><canvas id="bansOverviewChart"></canvas></div>
        </div>
        <div class="rs-chart-box">
            <h3 class="rs-chart-title">Top Attackers (24h)</h3>
            <div class="rs-chart-container" style="height: 300px;"><canvas id="topAttackersChart"></canvas></div>
        </div>
        <div class="rs-chart-box">
            <h3 class="rs-chart-title">Threat Intensity</h3>
            <div class="rs-chart-container" style="height: 250px;"><canvas id="riskDistributionChart"></canvas></div>
        </div>
    </div>

    <div class="rs-actions-bar">
        <h3>🚀 Quick Actions</h3>
        <div style="display:flex; gap: 10px;">
            <a href="?page=rs-advance-security&tab=bot" class="button button-secondary">Configure Firewall</a>
            <a href="?page=rs-advance-security&tab=monitoring" class="button button-secondary">View Logs</a>
            <?php if(!$has_cf_api): ?>
                <a href="?page=rs-settings&tab=cloudflare" class="button button-primary">Connect Cloudflare</a>
            <?php else: ?>
                <a href="?page=rs-cloudflare" class="button button-secondary">Cloudflare Rules</a>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
    .rs-dashboard-wrap { max-width: 1200px; margin: 20px auto; }
    .rs-version-badge { background: #e6f7ff; color: #0073aa; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size:12px; border: 1px solid #b3e0ff; }
    
    /* Grid & Cards */
    .rs-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .rs-card { background: #fff; padding: 20px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e5e5; display: flex; align-items: center; gap: 15px; border-left: 4px solid #ddd; }
    .rs-card-icon { width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    .rs-card-content h3 { margin: 0; font-size: 22px; color: #333; font-weight: 700; }
    .rs-card-content span { font-size: 12px; color: #777; text-transform: uppercase; font-weight:500; }

    /* Health Section */
    .rs-health-wrapper { display: flex; gap: 20px; margin-bottom: 30px; }
    .rs-score-card { flex: 0 0 220px; flex-direction: column; text-align: center; justify-content: center; padding: 30px; }
    .rs-checklist-card { flex: 1; display: block; padding: 15px 20px; border-left-color: #555; }
    
    /* Checklist */
    .rs-rec-list { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 0 30px; }
    .rs-fix-btn { text-decoration: none; color: #2271b1; font-weight: 600; font-size: 11px; text-transform: uppercase; border: 1px solid #2271b1; padding: 1px 6px; border-radius: 3px; }
    .rs-fix-btn:hover { background: #2271b1; color: #fff; }
    .status-ok { color: #27ae60; font-weight: bold; font-size: 10px; background: #eafaf1; padding: 2px 6px; border-radius: 3px; }

    /* Charts */
    .rs-charts-wrapper { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
    .rs-chart-box { background: #fff; padding: 15px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e5e5e5; }
    .rs-chart-title { margin: 0 0 10px 0; font-size: 14px; font-weight: 600; color: #444; border-bottom: 1px solid #f0f0f1; padding-bottom: 8px; }
    
    /* Actions Bar */
    .rs-actions-bar { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e5e5e5; display: flex; justify-content: space-between; align-items: center; }

    @media (max-width: 900px) { 
        .rs-charts-wrapper { grid-template-columns: 1fr; } 
        .rs-health-wrapper { flex-direction: column; }
        .rs-rec-list { grid-template-columns: 1fr; }
        .rs-actions-bar { flex-direction: column; align-items: flex-start; gap: 15px; }
    }
</style>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const c1 = document.getElementById('threatsHistoryChart').getContext('2d');
    new Chart(c1, { type: 'line', data: { labels: <?php echo json_encode($last_7_days); ?>, datasets: [{ label: 'Threats', data: <?php echo json_encode($chart_threat_data); ?>, borderColor: '#3498db', backgroundColor: 'rgba(52, 152, 219, 0.1)', borderWidth: 2, fill: true, tension: 0.3 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });
    const c2 = document.getElementById('bansOverviewChart').getContext('2d');
    new Chart(c2, { type: 'doughnut', data: { labels: ['Perm', 'Temp'], datasets: [{ data: [<?php echo $count_permanent; ?>, <?php echo $count_temporary; ?>], backgroundColor: ['#2c3e50', '#f1c40f'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } } });
    const c3 = document.getElementById('topAttackersChart').getContext('2d');
    new Chart(c3, { type: 'bar', data: { labels: <?php echo json_encode($top_labels); ?>, datasets: [{ label: 'Att', data: <?php echo json_encode($top_data); ?>, backgroundColor: '#e74c3c' }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });
    const c4 = document.getElementById('riskDistributionChart').getContext('2d');
    new Chart(c4, { type: 'pie', data: { labels: ['Low', 'Med', 'High'], datasets: [{ data: [<?php echo $low_risk; ?>, <?php echo $med_risk; ?>, <?php echo $high_risk; ?>], backgroundColor: ['#2ecc71', '#f1c40f', '#e74c3c'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } } });
});
</script>