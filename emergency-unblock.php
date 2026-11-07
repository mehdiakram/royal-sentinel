<?php
/**
 * Royal Sentinel - Emergency IP Unblock Script
 * 
 * এই স্ক্রিপ্ট ব্রাউজারে সরাসরি রান করুন:
 * http://your-site.test/wp-content/plugins/royal-sentinel/emergency-unblock.php
 * 
 * কাজ শেষে এই ফাইল মুছে ফেলুন!
 */

// WordPress load করি
define('WP_USE_THEMES', false);
require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/wp-load.php';

// Config
$target_ip = '180.148.214.142'; // আপনার IP
$action = isset($_GET['action']) ? $_GET['action'] : 'check';

global $wpdb;

$current_ip = rs_get_real_ip();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Royal Sentinel - Emergency IP Unblock</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; padding: 30px; color: #fff; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #f39c12; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; }
        .card { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 15px; padding: 25px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.1); }
        .card h3 { color: #3498db; margin-bottom: 15px; }
        .info-row { display: flex; gap: 15px; margin-bottom: 10px; padding: 12px 15px; background: rgba(0,0,0,0.2); border-radius: 8px; }
        .info-row strong { min-width: 180px; color: #9b59b6; }
        .status-ok { color: #2ecc71; font-weight: bold; }
        .status-bad { color: #e74c3c; font-weight: bold; }
        .status-warn { color: #f1c40f; font-weight: bold; }
        .btn { display: inline-block; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; margin: 5px; transition: all 0.3s; }
        .btn-success { background: #27ae60; color: #fff; }
        .btn-success:hover { background: #2ecc71; }
        .btn-danger { background: #c0392b; color: #fff; }
        .btn-danger:hover { background: #e74c3c; }
        .btn-info { background: #2980b9; color: #fff; }
        .btn-info:hover { background: #3498db; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.1); }
        table th { background: rgba(0,0,0,0.3); color: #f39c12; }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: rgba(39, 174, 96, 0.2); border: 1px solid #27ae60; color: #2ecc71; }
        .alert-danger { background: rgba(192, 57, 43, 0.2); border: 1px solid #c0392b; color: #e74c3c; }
        .alert-warning { background: rgba(241, 196, 15, 0.2); border: 1px solid #f1c40f; color: #f1c40f; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛡️ Royal Sentinel - Emergency IP Unblock</h1>
        
        <div class="card">
            <h3>📍 IP Information</h3>
            <div class="info-row">
                <strong>আপনার বর্তমান IP:</strong> 
                <span><?php echo esc_html($current_ip); ?></span>
                <?php if ($current_ip === $target_ip): ?>
                    <span class="status-ok">✓ Target IP Match</span>
                <?php else: ?>
                    <span class="status-warn">⚠ Target IP: <?php echo esc_html($target_ip); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php
        // Action: Unblock
        if ($action === 'unblock') {
            $unblock_ip = isset($_GET['ip']) ? sanitize_text_field($_GET['ip']) : $target_ip;
            $results = [];
            
            // 1. Delete from badips table
            $table_badips = $wpdb->prefix . 'royalsentinel_badips';
            $deleted_badips = $wpdb->delete($table_badips, ['bad_ip' => $unblock_ip]);
            $results[] = "badips টেবিল থেকে মোছা: " . ($deleted_badips !== false ? 'সফল' : 'ব্যর্থ');
            
            // 2. Delete from lockouts table
            $table_lockouts = $wpdb->prefix . 'royalsentinel_lockouts';
            $deleted_lockouts = $wpdb->delete($table_lockouts, ['lockout_ip' => $unblock_ip]);
            $results[] = "lockouts টেবিল থেকে মোছা: " . ($deleted_lockouts !== false ? 'সফল' : 'ব্যর্থ');
            
            // 3. Delete rate limit transient
            $transient_key = 'rs_rl_' . md5($unblock_ip);
            delete_transient($transient_key);
            $results[] = "Rate limit transient মোছা: সফল";
            
            // 4. Delete 404 transient
            $transient_404 = 'rs_404_' . md5($unblock_ip);
            delete_transient($transient_404);
            $results[] = "404 transient মোছা: সফল";
            
            // 5. Add to whitelist
            $rs_settings = get_option('rs_security_settings', []);
            $whitelist = isset($rs_settings['rt_ip_whitelist']) ? $rs_settings['rt_ip_whitelist'] : [];
            
            // Check if already in whitelist
            $already_whitelisted = false;
            foreach ($whitelist as $entry) {
                if (isset($entry['ip']) && $entry['ip'] === $unblock_ip) {
                    $already_whitelisted = true;
                    break;
                }
            }
            
            if (!$already_whitelisted) {
                $whitelist[] = [
                    'ip' => $unblock_ip,
                    'added' => current_time('mysql'),
                    'note' => 'Emergency unblock'
                ];
                $rs_settings['rt_ip_whitelist'] = $whitelist;
                update_option('rs_security_settings', $rs_settings);
                $results[] = "Whitelist এ যোগ: সফল";
            } else {
                $results[] = "Whitelist এ আগে থেকেই আছে";
            }
            
            echo '<div class="alert alert-success">✅ IP <strong>' . esc_html($unblock_ip) . '</strong> সফলভাবে আনব্লক করা হয়েছে!</div>';
            echo '<div class="card"><ul>';
            foreach ($results as $r) {
                echo '<li style="padding: 5px 0;">' . esc_html($r) . '</li>';
            }
            echo '</ul></div>';
        }
        ?>

        <div class="card">
            <h3>🔍 Block Status Check</h3>
            
            <?php
            // Check badips table
            $table_badips = $wpdb->prefix . 'royalsentinel_badips';
            $in_badips = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_badips WHERE bad_ip = %s", $target_ip));
            
            // Check lockouts table
            $table_lockouts = $wpdb->prefix . 'royalsentinel_lockouts';
            $in_lockouts = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_lockouts WHERE lockout_ip = %s", $target_ip));
            
            // Check transients
            $rate_limit_key = 'rs_rl_' . md5($target_ip);
            $rate_limit_count = get_transient($rate_limit_key);
            
            $error_404_key = 'rs_404_' . md5($target_ip);
            $error_404_count = get_transient($error_404_key);
            
            // Check whitelist
            $rs_settings = get_option('rs_security_settings', []);
            $whitelist = isset($rs_settings['rt_ip_whitelist']) ? $rs_settings['rt_ip_whitelist'] : [];
            $in_whitelist = false;
            foreach ($whitelist as $entry) {
                if (isset($entry['ip']) && $entry['ip'] === $target_ip) {
                    $in_whitelist = true;
                    break;
                }
            }
            ?>
            
            <table>
                <tr>
                    <th>চেক পয়েন্ট</th>
                    <th>স্ট্যাটাস</th>
                    <th>বিস্তারিত</th>
                </tr>
                <tr>
                    <td>Permanent Ban (badips)</td>
                    <td><?php echo $in_badips ? '<span class="status-bad">❌ ব্লক আছে</span>' : '<span class="status-ok">✓ ক্লিয়ার</span>'; ?></td>
                    <td><?php echo $in_badips ? 'Reason: ' . esc_html($in_badips->reason) . ', Date: ' . esc_html($in_badips->created_at) : '-'; ?></td>
                </tr>
                <tr>
                    <td>Login Lockout (lockouts)</td>
                    <td><?php echo $in_lockouts ? '<span class="status-bad">❌ Lockout আছে</span>' : '<span class="status-ok">✓ ক্লিয়ার</span>'; ?></td>
                    <td><?php echo $in_lockouts ? 'Attempts: ' . esc_html($in_lockouts->attempt_count) . ', Last: ' . esc_html($in_lockouts->last_attempt) : '-'; ?></td>
                </tr>
                <tr>
                    <td>Rate Limit (transient)</td>
                    <td><?php echo $rate_limit_count ? '<span class="status-warn">⚠ Count: ' . esc_html($rate_limit_count) . '</span>' : '<span class="status-ok">✓ ক্লিয়ার</span>'; ?></td>
                    <td><?php echo $rate_limit_count ? 'Current count active' : '-'; ?></td>
                </tr>
                <tr>
                    <td>404 Attack Detection</td>
                    <td><?php echo $error_404_count ? '<span class="status-warn">⚠ Count: ' . esc_html($error_404_count) . '</span>' : '<span class="status-ok">✓ ক্লিয়ার</span>'; ?></td>
                    <td><?php echo $error_404_count ? '404 errors tracked' : '-'; ?></td>
                </tr>
                <tr>
                    <td>IP Whitelist</td>
                    <td><?php echo $in_whitelist ? '<span class="status-ok">✓ Whitelisted</span>' : '<span class="status-warn">⚠ Not whitelisted</span>'; ?></td>
                    <td><?php echo $in_whitelist ? 'Protected from blocks' : 'Vulnerable to blocks'; ?></td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h3>🔧 Actions</h3>
            <a href="?action=unblock&ip=<?php echo esc_attr($target_ip); ?>" class="btn btn-success">✅ <?php echo esc_html($target_ip); ?> আনব্লক করুন</a>
            <a href="?action=unblock&ip=<?php echo esc_attr($current_ip); ?>" class="btn btn-info">🔓 বর্তমান IP (<?php echo esc_html($current_ip); ?>) আনব্লক করুন</a>
            <a href="?" class="btn btn-danger">🔄 Refresh</a>
        </div>

        <div class="card">
            <h3>📋 সব Blocked IPs (badips টেবিল)</h3>
            <?php
            $all_badips = $wpdb->get_results("SELECT * FROM $table_badips ORDER BY created_at DESC LIMIT 50");
            if ($all_badips):
            ?>
            <table>
                <tr>
                    <th>IP</th>
                    <th>Reason</th>
                    <th>তারিখ</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($all_badips as $bad): ?>
                <tr>
                    <td><?php echo esc_html($bad->bad_ip); ?></td>
                    <td><?php echo esc_html($bad->reason); ?></td>
                    <td><?php echo esc_html($bad->created_at); ?></td>
                    <td><a href="?action=unblock&ip=<?php echo esc_attr($bad->bad_ip); ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">আনব্লক</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
            <p class="status-ok">✓ কোনো IP ব্লক নেই!</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>📋 সব Lockouts (lockouts টেবিল)</h3>
            <?php
            $all_lockouts = $wpdb->get_results("SELECT * FROM $table_lockouts ORDER BY last_attempt DESC LIMIT 50");
            if ($all_lockouts):
            ?>
            <table>
                <tr>
                    <th>IP</th>
                    <th>Attempts</th>
                    <th>Last Attempt</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($all_lockouts as $lock): ?>
                <tr>
                    <td><?php echo esc_html($lock->lockout_ip); ?></td>
                    <td><?php echo esc_html($lock->attempt_count); ?></td>
                    <td><?php echo esc_html($lock->last_attempt); ?></td>
                    <td><a href="?action=unblock&ip=<?php echo esc_attr($lock->lockout_ip); ?>" class="btn btn-success" style="padding: 5px 10px; font-size: 12px;">আনব্লক</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php else: ?>
            <p class="status-ok">✓ কোনো lockout নেই!</p>
            <?php endif; ?>
        </div>

        <div class="alert alert-warning" style="margin-top: 20px;">
            ⚠️ <strong>সতর্কতা:</strong> সমস্যা সমাধান হলে এই ফাইলটি মুছে ফেলুন! নিরাপত্তার জন্য এটি রাখা উচিত নয়।
            <br><br>
            মুছতে: <code>royal-sentinel/emergency-unblock.php</code>
        </div>
    </div>
</body>
</html>
