<?php
// views/advance-security/monitoring.php

global $wpdb;
$table_logs = class_exists('RS_Database') ? RS_Database::get_table_name('monitor_logs') : $wpdb->prefix . 'royalsentinel_monitor_logs';

$settings   = get_option('rs_security_settings', []);

// সেটিংস
$mon_file_integrity = isset($settings['mon_file_integrity']) ? $settings['mon_file_integrity'] : 0;
$mon_site_scanner   = isset($settings['mon_site_scanner']) ? $settings['mon_site_scanner'] : 0;
$mon_vuln_detection = isset($settings['mon_vuln_detection']) ? $settings['mon_vuln_detection'] : 0;
$mon_activity_log   = isset($settings['mon_activity_log']) ? $settings['mon_activity_log'] : 0;
$mon_login_monitor  = isset($settings['mon_login_monitor']) ? $settings['mon_login_monitor'] : 0;
$mon_db_monitor     = isset($settings['mon_db_monitor']) ? $settings['mon_db_monitor'] : 0;
$mon_alert_mode = isset($settings['mon_alert_mode']) ? $settings['mon_alert_mode'] : 'all';
$mon_alert_specific_users = isset($settings['mon_alert_specific_users']) ? $settings['mon_alert_specific_users'] : [];
$admin_users = get_users(['role' => 'administrator']);

// Logs Fetch
$logs = $wpdb->get_results("SELECT * FROM $table_logs ORDER BY created_at DESC LIMIT 15");

// Health Logic
$health_score = 100;
if(empty($mon_file_integrity)) $health_score -= 20;
if(empty($mon_site_scanner)) $health_score -= 20;
if(!empty($logs) && isset($logs[0]) && $logs[0]->severity == 3) $health_score -= 10;
?>

<div class="rs-monitoring-wrapper">
    
    <div style="display:flex; gap:20px; margin-bottom:20px;">
        <div class="rs-card" style="flex:1; background:#fff; padding:20px; border-left:4px solid <?php echo $health_score > 70 ? '#46b450' : '#d63638'; ?>; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin:0; font-size:2em;"><?php echo $health_score; ?>%</h2>
            <p style="margin:0; color:#666;">Protection Score</p>
        </div>
        <div class="rs-card" style="flex:1; background:#fff; padding:20px; border-left:4px solid #2271b1; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
             <h3 style="margin-top:0;">Manual Action</h3>
             <p style="margin:0 0 10px; color:#666;">Run a quick integrity & malware scan.</p>
             <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=rs_run_manual_scan'), 'rs_manual_scan'); ?>" id="rs-manual-scan-btn" class="button button-secondary">
                <span class="dashicons dashicons-search" style="vertical-align:middle;"></span> Run Quick Scan
             </a>
        </div>
    </div>

    <div style="display:flex; gap:20px; flex-wrap:wrap;">
        
        <div style="flex: 2; min-width: 300px;">
            <div class="rs-section-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 0 20px 20px 20px;">
                <h3 style="border-bottom: 1px solid #eee; padding-bottom: 15px;">Monitoring Configuration</h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row" style="padding:15px 0;">
                            <label class="rs-switch">
                                <input type="checkbox" name="mon_file_integrity" value="1" <?php checked(1, $mon_file_integrity); ?>>
                                <span class="rs-slider round"></span>
                            </label>
                        </th>
                        <td style="padding:15px 0 15px 15px;">
                            <strong>File Integrity Monitor</strong>
                            <p class="description">Watches wp-config, index.php & core files for changes.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" style="padding:15px 0;">
                            <label class="rs-switch">
                                <input type="checkbox" name="mon_site_scanner" value="1" <?php checked(1, $mon_site_scanner); ?>>
                                <span class="rs-slider round"></span>
                            </label>
                        </th>
                        <td style="padding:15px 0 15px 15px;">
                            <strong>Lightweight Pattern Scan</strong>
                            <p class="description">Checks for suspicious code (eval, base64) in critical files.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" style="padding:15px 0;">
                            <label class="rs-switch">
                                <input type="checkbox" name="mon_vuln_detection" value="1" <?php checked(1, $mon_vuln_detection); ?>>
                                <span class="rs-slider round"></span>
                            </label>
                        </th>
                        <td style="padding:15px 0 15px 15px;">
                            <strong>Vulnerability Detection</strong>
                            <p class="description">Checks plugin versions for known risks.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" style="padding:15px 0;">
                            <label class="rs-switch">
                                <input type="checkbox" name="mon_activity_log" value="1" <?php checked(1, $mon_activity_log); ?>>
                                <span class="rs-slider round"></span>
                            </label>
                        </th>
                        <td style="padding:15px 0 15px 15px;">
                            <strong>Activity Logger</strong>
                            <p class="description">Logs logins, plugin changes, and theme switches.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" style="padding:15px 0;">
                            <label class="rs-switch">
                                <input type="checkbox" name="mon_login_monitor" value="1" <?php checked(1, $mon_login_monitor); ?>>
                                <span class="rs-slider round"></span>
                            </label>
                        </th>
                        <td style="padding:15px 0 15px 15px;">
                            <strong>Login Monitor</strong>
                            <p class="description">Detects and logs brute-force attempts.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row" style="padding:15px 0;">
                            <label class="rs-switch">
                                <input type="checkbox" name="mon_db_monitor" value="1" <?php checked(1, $mon_db_monitor); ?>>
                                <span class="rs-slider round"></span>
                            </label>
                        </th>
                        <td style="padding:15px 0 15px 15px;">
                            <strong>DB Supervisor</strong>
                            <p class="description">Alerts on critical option changes (siteurl, admin_email).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" style="padding:15px 0; vertical-align: top;">
                            <label style="font-weight: 600;">📧 Alert Recipients</label>
                            <p class="description" style="font-weight: normal; margin-top: 5px;">Emails sent on critical events only</p>
                        </th>
                        <td style="padding:15px 0 15px 15px;">
                            <div class="rs-alert-mode-selector" style="display: flex; gap: 15px; margin-bottom: 20px;">
                                <label class="rs-mode-card <?php echo $mon_alert_mode !== 'specific' ? 'active' : ''; ?>" style="flex: 1; cursor: pointer;">
                                    <input type="radio" name="mon_alert_mode" value="all" <?php checked('all', $mon_alert_mode); ?> style="display: none;">
                                    <div class="rs-mode-content" style="padding: 15px; border: 2px solid <?php echo $mon_alert_mode !== 'specific' ? '#2271b1' : '#ddd'; ?>; border-radius: 8px; text-align: center; transition: all 0.2s;">
                                        <span class="dashicons dashicons-groups" style="font-size: 28px; width: 28px; height: 28px; color: <?php echo $mon_alert_mode !== 'specific' ? '#2271b1' : '#888'; ?>;"></span>
                                        <div style="font-weight: 600; margin-top: 8px; color: #1d2327;">All Administrators</div>
                                        <div style="font-size: 12px; color: #666; margin-top: 4px;"><?php echo count($admin_users); ?> admins</div>
                                    </div>
                                </label>
                                <label class="rs-mode-card <?php echo $mon_alert_mode === 'specific' ? 'active' : ''; ?>" style="flex: 1; cursor: pointer;">
                                    <input type="radio" name="mon_alert_mode" value="specific" <?php checked('specific', $mon_alert_mode); ?> style="display: none;">
                                    <div class="rs-mode-content" style="padding: 15px; border: 2px solid <?php echo $mon_alert_mode === 'specific' ? '#2271b1' : '#ddd'; ?>; border-radius: 8px; text-align: center; transition: all 0.2s;">
                                        <span class="dashicons dashicons-admin-users" style="font-size: 28px; width: 28px; height: 28px; color: <?php echo $mon_alert_mode === 'specific' ? '#2271b1' : '#888'; ?>;"></span>
                                        <div style="font-weight: 600; margin-top: 8px; color: #1d2327;">Specific Admins</div>
                                        <div style="font-size: 12px; color: #666; margin-top: 4px;">Choose who gets alerts</div>
                                    </div>
                                </label>
                            </div>
                            
                            <div id="rs-specific-admins-row" style="display: <?php echo $mon_alert_mode === 'specific' ? 'block' : 'none'; ?>; animation: fadeIn 0.3s ease;">
                                <div style="background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%); border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                        <span style="font-weight: 600; color: #1d2327;">👥 Select Administrators</span>
                                        <span class="rs-selected-count" style="background: #2271b1; color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 12px;">
                                            <?php echo count($mon_alert_specific_users); ?> selected
                                        </span>
                                    </div>
                                    
                                    <div class="rs-admin-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
                                        <?php foreach ($admin_users as $user): 
                                            $is_selected = in_array($user->ID, $mon_alert_specific_users);
                                            $avatar_url = get_avatar_url($user->ID, ['size' => 40]);
                                        ?>
                                        <label class="rs-admin-card" style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #fff; border: 2px solid <?php echo $is_selected ? '#2271b1' : '#e5e5e5'; ?>; border-radius: 8px; cursor: pointer; transition: all 0.2s;">
                                            <input type="checkbox" name="mon_alert_specific_users[]" value="<?php echo esc_attr($user->ID); ?>" <?php checked($is_selected); ?> style="display: none;">
                                            <img src="<?php echo esc_url($avatar_url); ?>" style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid <?php echo $is_selected ? '#2271b1' : '#ddd'; ?>;">
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="font-weight: 600; color: #1d2327; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <?php echo esc_html($user->display_name); ?>
                                                </div>
                                                <div style="font-size: 11px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    <?php echo esc_html($user->user_email); ?>
                                                </div>
                                            </div>
                                            <span class="rs-check-icon dashicons <?php echo $is_selected ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>" style="color: <?php echo $is_selected ? '#2271b1' : '#ccc'; ?>; font-size: 20px;"></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div style="flex: 3; min-width: 300px;">
            <div class="rs-section-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 0 20px 20px 20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid #eee; margin-bottom:15px;">
                    <h3>Recent Events</h3>
                    <span class="description">Auto-clears every 30 days</span>
                </div>

                <?php if($logs): ?>
                    <ul style="margin:0; padding:0; list-style:none;">
                        <?php foreach($logs as $log): 
                            $color = '#2271b1'; // Info
                            if($log->severity == 2) $color = '#ffb900'; // Warning
                            if($log->severity == 3) $color = '#d63638'; // Critical
                        ?>
                        <li style="padding: 12px 0; border-bottom: 1px solid #f0f0f0; display:flex; align-items:flex-start;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:<?php echo $color; ?>; margin-top:5px; margin-right:12px; flex-shrink:0;"></span>
                            <div>
                                <strong style="display:block; font-size:13px; color:#333;"><?php echo esc_html($log->message); ?></strong>
                                <span style="font-size:11px; color:#888;">
                                    <?php echo strtoupper(str_replace('_', ' ', $log->type)); ?> • <?php echo date('M d, H:i', strtotime($log->created_at)); ?>
                                </span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div style="padding:40px; text-align:center; color:#ccc;">
                        <span class="dashicons dashicons-yes-alt" style="font-size:40px; width:40px; height:40px;"></span>
                        <p>No security events recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // ১. বাটনে ক্লিক করলে লোডিং পপআপ
    const scanBtn = document.getElementById('rs-manual-scan-btn');
    if(scanBtn) {
        scanBtn.addEventListener('click', function(e) {
            e.preventDefault(); 
            const targetUrl = this.href;

            Swal.fire({
                title: 'Scanning System...',
                html: 'Checking file integrity & malware patterns.<br>Please wait...',
                icon: 'info',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            setTimeout(() => {
                window.location.href = targetUrl;
            }, 500); 
        });
    }

    // ২. স্ক্যান শেষ হয়ে ফিরে আসলে সাকসেস পপআপ
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('scan') === 'success') {
        Swal.fire({
            title: 'Scan Complete!',
            text: 'System integrity check and malware pattern scan finished successfully.',
            icon: 'success',
            confirmButtonText: 'Great!',
            confirmButtonColor: '#2271b1'
        }).then(() => {
            const newUrl = window.location.href.split('&scan=success')[0];
            window.history.replaceState({}, document.title, newUrl);
        });
    }

    // ৩. Alert Mode Card Selector
    document.querySelectorAll('.rs-mode-card input[type="radio"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            // Update all card visuals
            document.querySelectorAll('.rs-mode-card').forEach(function(card) {
                const content = card.querySelector('.rs-mode-content');
                const icon = card.querySelector('.dashicons');
                const isChecked = card.querySelector('input[type="radio"]').checked;
                
                content.style.borderColor = isChecked ? '#2271b1' : '#ddd';
                icon.style.color = isChecked ? '#2271b1' : '#888';
            });
            
            // Toggle specific admins section
            const specificRow = document.getElementById('rs-specific-admins-row');
            if (this.value === 'specific') {
                specificRow.style.display = 'block';
            } else {
                specificRow.style.display = 'none';
            }
        });
    });
    
    // ৪. Admin Card Checkbox Styling
    document.querySelectorAll('.rs-admin-card input[type="checkbox"]').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const card = this.closest('.rs-admin-card');
            const icon = card.querySelector('.rs-check-icon');
            const avatar = card.querySelector('img');
            
            if (this.checked) {
                card.style.borderColor = '#2271b1';
                card.style.background = '#f0f7fc';
                icon.className = 'rs-check-icon dashicons dashicons-yes-alt';
                icon.style.color = '#2271b1';
                avatar.style.borderColor = '#2271b1';
            } else {
                card.style.borderColor = '#e5e5e5';
                card.style.background = '#fff';
                icon.className = 'rs-check-icon dashicons dashicons-marker';
                icon.style.color = '#ccc';
                avatar.style.borderColor = '#ddd';
            }
            
            // Update count
            const count = document.querySelectorAll('.rs-admin-card input[type="checkbox"]:checked').length;
            document.querySelector('.rs-selected-count').textContent = count + ' selected';
        });
    });
    
    // ৫. Hover effect for cards
    document.querySelectorAll('.rs-admin-card').forEach(function(card) {
        card.addEventListener('mouseenter', function() {
            if (!this.querySelector('input').checked) {
                this.style.borderColor = '#b8d4e8';
                this.style.boxShadow = '0 2px 8px rgba(34,113,177,0.1)';
            }
        });
        card.addEventListener('mouseleave', function() {
            if (!this.querySelector('input').checked) {
                this.style.borderColor = '#e5e5e5';
                this.style.boxShadow = 'none';
            }
        });
    });
    
    // Mode card hover
    document.querySelectorAll('.rs-mode-card').forEach(function(card) {
        card.addEventListener('mouseenter', function() {
            if (!this.querySelector('input').checked) {
                this.querySelector('.rs-mode-content').style.borderColor = '#b8d4e8';
            }
        });
        card.addEventListener('mouseleave', function() {
            if (!this.querySelector('input').checked) {
                this.querySelector('.rs-mode-content').style.borderColor = '#ddd';
            }
        });
    });
});
</script>