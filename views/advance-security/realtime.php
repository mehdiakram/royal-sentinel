<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Settings
$rt_rate_limit   = isset($rs_settings['rt_rate_limit']) ? $rs_settings['rt_rate_limit'] : 0;
$rt_bad_query    = isset($rs_settings['rt_bad_query']) ? $rs_settings['rt_bad_query'] : 0;
$rt_bad_ua       = isset($rs_settings['rt_bad_ua']) ? $rs_settings['rt_bad_ua'] : 0;
$rt_404_detect   = isset($rs_settings['rt_404_detect']) ? $rs_settings['rt_404_detect'] : 0;

// Advanced Settings
$rt_rate_limit_max     = isset($rs_settings['rt_rate_limit_max']) ? $rs_settings['rt_rate_limit_max'] : '60';
$rt_rate_limit_window  = isset($rs_settings['rt_rate_limit_window']) ? $rs_settings['rt_rate_limit_window'] : '60';
$rt_404_threshold      = isset($rs_settings['rt_404_threshold']) ? $rs_settings['rt_404_threshold'] : '20';
$rt_404_window         = isset($rs_settings['rt_404_window']) ? $rs_settings['rt_404_window'] : '60';

// IP Whitelist Settings
$rt_ip_whitelist       = isset($rs_settings['rt_ip_whitelist']) ? $rs_settings['rt_ip_whitelist'] : [];
$rt_auto_whitelist     = isset($rs_settings['rt_auto_whitelist_admin']) ? $rs_settings['rt_auto_whitelist_admin'] : 1;
$rt_whitelist_localhost = isset($rs_settings['rt_whitelist_localhost']) ? $rs_settings['rt_whitelist_localhost'] : 1;
$current_ip            = function_exists('rs_get_client_ip') ? rs_get_client_ip() : '';

// Recent Blocked Requests (from monitor_logs)
$table_logs = class_exists('RS_Database') ? RS_Database::get_table_name('monitor_logs') : $wpdb->prefix . 'royalsentinel_monitor_logs';
$recent_blocks = [];
if ($wpdb->get_var("SHOW TABLES LIKE '$table_logs'") == $table_logs) {
    $recent_blocks = $wpdb->get_results(
        "SELECT * FROM $table_logs WHERE type LIKE 'realtime_%' ORDER BY created_at DESC LIMIT 10"
    );
}
?>

<div class="rs-realtime-wrapper">
    
    <div style="margin-bottom: 20px;">
        <h3 style="margin:0;">⚡ Real-time Threat Defense</h3>
        <p class="description">Protect your site from automated attacks and malicious requests in real-time.</p>
    </div>

    <!-- IP Whitelist Section -->
    <div class="rs-section-box" style="border-left: 4px solid #27ae60; margin-bottom: 30px;">
        <h4 class="rs-section-title" style="color: #27ae60;">🛡️ <?php _e('IP Whitelist', 'royal-sentinel'); ?></h4>
        <p class="description" style="margin-bottom: 20px;"><?php _e('Whitelisted IPs will bypass all rate limiting and threat detection. Supports single IPs and CIDR ranges.', 'royal-sentinel'); ?></p>
        
        <!-- Current IP Display -->
        <div style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); color: #fff; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div>
                <span style="opacity: 0.9; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;"><?php _e('Your Current IP', 'royal-sentinel'); ?></span>
                <div style="font-size: 20px; font-weight: 600; font-family: monospace;" id="rs-current-ip"><?php echo esc_html($current_ip ?: 'Detecting...'); ?></div>
            </div>
            <button type="button" id="rs-whitelist-my-ip" class="button" style="background: #fff; color: #27ae60; border: none; font-weight: 600; padding: 8px 20px;">
                ⚡ <?php _e('Whitelist My IP', 'royal-sentinel'); ?>
            </button>
        </div>

        <!-- Add New IP -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <label style="font-weight: 600; display: block; margin-bottom: 8px;">➕ <?php _e('Add IP or CIDR Range', 'royal-sentinel'); ?></label>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="text" id="rs-new-whitelist-ip" placeholder="e.g., 192.168.1.100 or 10.0.0.0/24" style="flex: 1; min-width: 250px; padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px;">
                <button type="button" id="rs-add-whitelist-ip" class="button button-primary" style="padding: 10px 20px;">
                    <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span> <?php _e('Add to Whitelist', 'royal-sentinel'); ?>
                </button>
            </div>
            <p class="description" style="margin-top: 8px; color: #666;">
                <?php _e('Examples:', 'royal-sentinel'); ?> 
                <code>192.168.1.100</code> (single IP), 
                <code>192.168.1.0/24</code> (256 IPs), 
                <code>10.0.0.0/8</code> (Class A network)
            </p>
        </div>

        <!-- Whitelist Table -->
        <div style="background: #fff; border: 1px solid #e1e5e9; border-radius: 8px; overflow: hidden;">
            <div style="background: #f1f3f4; padding: 12px 15px; border-bottom: 1px solid #e1e5e9; font-weight: 600;">
                📋 <?php _e('Whitelisted IPs', 'royal-sentinel'); ?> (<span id="rs-whitelist-count"><?php echo count($rt_ip_whitelist); ?></span>)
            </div>
            <div id="rs-whitelist-table-container" style="max-height: 300px; overflow-y: auto;">
                <table class="widefat" style="border: none; margin: 0;">
                    <thead style="position: sticky; top: 0; background: #f8f9fa;">
                        <tr>
                            <th style="padding: 12px 15px;"><?php _e('IP / CIDR', 'royal-sentinel'); ?></th>
                            <th style="padding: 12px 15px; width: 180px;"><?php _e('Added', 'royal-sentinel'); ?></th>
                            <th style="padding: 12px 15px; width: 100px; text-align: center;"><?php _e('Action', 'royal-sentinel'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="rs-whitelist-tbody">
                        <?php if (!empty($rt_ip_whitelist)): ?>
                            <?php foreach ($rt_ip_whitelist as $entry): 
                                $ip = isset($entry['ip']) ? $entry['ip'] : '';
                                $added = isset($entry['added']) ? $entry['added'] : '';
                                $is_auto = isset($entry['auto']) && $entry['auto'];
                            ?>
                                <tr data-ip="<?php echo esc_attr($ip); ?>">
                                    <td style="padding: 12px 15px; font-family: monospace; font-size: 14px;">
                                        <?php echo esc_html($ip); ?>
                                        <?php if ($is_auto): ?>
                                            <span style="background: #e8f5e9; color: #27ae60; font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-left: 8px;"><?php _e('AUTO', 'royal-sentinel'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 15px; color: #666; font-size: 13px;">
                                        <?php echo $added ? esc_html(human_time_diff(strtotime($added), current_time('timestamp')) . ' ' . __('ago', 'royal-sentinel')) : '-'; ?>
                                    </td>
                                    <td style="padding: 12px 15px; text-align: center;">
                                        <button type="button" class="rs-remove-whitelist-ip button" data-ip="<?php echo esc_attr($ip); ?>" style="color: #d63638; border-color: #d63638; padding: 4px 12px;">
                                            🗑️ <?php _e('Remove', 'royal-sentinel'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="rs-whitelist-empty">
                                <td colspan="3" style="padding: 30px; text-align: center; color: #888;">
                                    <span class="dashicons dashicons-info" style="font-size: 24px; width: 24px; height: 24px; margin-bottom: 10px;"></span>
                                    <p style="margin: 0;"><?php _e('No IPs whitelisted yet. Add your IP above to get started.', 'royal-sentinel'); ?></p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Options -->
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <label style="font-weight: 600; display: block; margin-bottom: 12px;">⚙️ <?php _e('Whitelist Options', 'royal-sentinel'); ?></label>
            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="rt_auto_whitelist_admin" value="1" <?php checked(1, $rt_auto_whitelist); ?>>
                    <span><?php _e('Auto-whitelist Admin IPs on login', 'royal-sentinel'); ?></span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="rt_whitelist_localhost" value="1" <?php checked(1, $rt_whitelist_localhost); ?>>
                    <span><?php _e('Always whitelist localhost (127.0.0.1)', 'royal-sentinel'); ?></span>
                </label>
            </div>
        </div>
    </div>

    <div class="rs-section-box" style="border-left: 4px solid #e74c3c;">
        <h4 class="rs-section-title" style="color: #e74c3c;">🚦 Rate Limiting</h4>
        
        <div style="margin-bottom: 15px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="rt_rate_limit" id="rs_rt_rate_limit" value="1" <?php checked(1, $rt_rate_limit); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_rt_rate_limit" style="font-weight: 600;">Enable Rate Limiting</label>
            <p class="description" style="margin-left: 55px; margin-top: 5px;">Block IPs that send too many requests within a time window.</p>
        </div>

        <div id="rs_rate_limit_options" style="display: <?php echo $rt_rate_limit ? 'block' : 'none'; ?>; padding: 15px; background: #f9f9f9; border: 1px solid #eee; margin-top: 10px;">
            <table class="form-table" style="margin: 0;">
                <tr>
                    <th style="width: 200px;"><label>Max Requests</label></th>
                    <td>
                        <input type="number" name="rt_rate_limit_max" value="<?php echo esc_attr($rt_rate_limit_max); ?>" min="10" max="500" style="width: 100px;">
                        <span class="description">requests per</span>
                        <input type="number" name="rt_rate_limit_window" value="<?php echo esc_attr($rt_rate_limit_window); ?>" min="10" max="300" style="width: 80px;">
                        <span class="description">seconds</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #9b59b6;">
        <h4 class="rs-section-title" style="color: #9b59b6;">🔍 Request Inspection</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="rt_bad_query" value="1" <?php checked(1, $rt_bad_query); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Block Malicious Query Strings</label>
                    <p class="description">Blocks requests with SQL injection patterns, XSS attempts, and path traversal attacks in URL parameters.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="rt_bad_ua" value="1" <?php checked(1, $rt_bad_ua); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Block Bad User Agents</label>
                    <p class="description">Blocks known malicious bots, scanners, and automated tools (e.g., sqlmap, nikto, nmap).</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #f39c12;">
        <h4 class="rs-section-title" style="color: #f39c12;">🎯 404 Attack Detection</h4>
        
        <div style="margin-bottom: 15px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="rt_404_detect" id="rs_rt_404_detect" value="1" <?php checked(1, $rt_404_detect); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_rt_404_detect" style="font-weight: 600;">Enable 404 Attack Detection</label>
            <p class="description" style="margin-left: 55px; margin-top: 5px;">Block IPs that repeatedly request non-existent URLs (vulnerability scanners).</p>
        </div>

        <div id="rs_404_detect_options" style="display: <?php echo $rt_404_detect ? 'block' : 'none'; ?>; padding: 15px; background: #f9f9f9; border: 1px solid #eee; margin-top: 10px;">
            <table class="form-table" style="margin: 0;">
                <tr>
                    <th style="width: 200px;"><label>Block After</label></th>
                    <td>
                        <input type="number" name="rt_404_threshold" value="<?php echo esc_attr($rt_404_threshold); ?>" min="5" max="100" style="width: 80px;">
                        <span class="description">404 errors within</span>
                        <input type="number" name="rt_404_window" value="<?php echo esc_attr($rt_404_window); ?>" min="30" max="600" style="width: 80px;">
                        <span class="description">seconds</span>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #3498db;">
        <h4 class="rs-section-title" style="color: #3498db;">📊 Recent Blocked Threats</h4>
        
        <?php if (!empty($recent_blocks)): ?>
            <table class="widefat striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Severity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_blocks as $log): 
                        $severity_color = '#2271b1';
                        $severity_text = 'Info';
                        if ($log->severity == 2) { $severity_color = '#f0b849'; $severity_text = 'Warning'; }
                        if ($log->severity == 3) { $severity_color = '#d63638'; $severity_text = 'Critical'; }
                    ?>
                        <tr>
                            <td style="width: 150px;"><?php echo esc_html(date('M d, H:i:s', strtotime($log->created_at))); ?></td>
                            <td style="width: 150px;"><code><?php echo esc_html(str_replace('realtime_', '', $log->type)); ?></code></td>
                            <td><?php echo esc_html($log->message); ?></td>
                            <td style="width: 100px;">
                                <span style="color: <?php echo $severity_color; ?>; font-weight: 600;"><?php echo $severity_text; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="padding: 30px; text-align: center; color: #888; background: #f9f9f9; border-radius: 4px;">
                <span class="dashicons dashicons-shield" style="font-size: 40px; width: 40px; height: 40px; color: #27ae60;"></span>
                <p style="margin: 10px 0 0;">No threats blocked yet. Your site is protected!</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; }
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle Options
    function toggleOptions(checkboxId, optionsId) {
        $(checkboxId).on('change', function() {
            if($(this).is(':checked')) {
                $(optionsId).slideDown();
            } else {
                $(optionsId).slideUp();
            }
        });
    }
    
    toggleOptions('#rs_rt_rate_limit', '#rs_rate_limit_options');
    toggleOptions('#rs_rt_404_detect', '#rs_404_detect_options');

    // Whitelist Nonce
    var whitelistNonce = '<?php echo wp_create_nonce("rs_whitelist_nonce"); ?>';
    var currentIP = $('#rs-current-ip').text().trim();

    // Whitelist My IP Button
    $('#rs-whitelist-my-ip').on('click', function() {
        var btn = $(this);
        var ip = currentIP;
        
        if (!ip || ip === 'Detecting...') {
            Swal.fire({
                icon: 'error',
                title: '<?php _e("Error", "royal-sentinel"); ?>',
                text: '<?php _e("Could not detect your IP address", "royal-sentinel"); ?>',
                timer: 2000,
                showConfirmButton: false
            });
            return;
        }

        btn.prop('disabled', true).text('<?php _e("Adding...", "royal-sentinel"); ?>');

        $.post(ajaxurl, {
            action: 'rs_add_whitelist_ip',
            nonce: whitelistNonce,
            ip: ip
        }, function(res) {
            btn.prop('disabled', false).html('⚡ <?php _e("Whitelist My IP", "royal-sentinel"); ?>');
            
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '<?php _e("Success!", "royal-sentinel"); ?>',
                    text: res.data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                refreshWhitelistTable(res.data.whitelist);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '<?php _e("Error", "royal-sentinel"); ?>',
                    text: res.data.message,
                    timer: 2500,
                    showConfirmButton: false
                });
            }
        }).fail(function() {
            btn.prop('disabled', false).html('⚡ <?php _e("Whitelist My IP", "royal-sentinel"); ?>');
            Swal.fire({
                icon: 'error',
                title: '<?php _e("Error", "royal-sentinel"); ?>',
                text: '<?php _e("Request failed. Please try again.", "royal-sentinel"); ?>',
                timer: 2000,
                showConfirmButton: false
            });
        });
    });

    // Add IP Button
    $('#rs-add-whitelist-ip').on('click', function() {
        var btn = $(this);
        var ip = $('#rs-new-whitelist-ip').val().trim();
        
        if (!ip) {
            Swal.fire({
                icon: 'warning',
                title: '<?php _e("Required", "royal-sentinel"); ?>',
                text: '<?php _e("Please enter an IP address or CIDR range", "royal-sentinel"); ?>',
                timer: 2000,
                showConfirmButton: false
            });
            $('#rs-new-whitelist-ip').focus();
            return;
        }

        btn.prop('disabled', true);

        $.post(ajaxurl, {
            action: 'rs_add_whitelist_ip',
            nonce: whitelistNonce,
            ip: ip
        }, function(res) {
            btn.prop('disabled', false);
            
            if (res.success) {
                $('#rs-new-whitelist-ip').val('');
                Swal.fire({
                    icon: 'success',
                    title: '<?php _e("Added!", "royal-sentinel"); ?>',
                    text: res.data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                refreshWhitelistTable(res.data.whitelist);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: '<?php _e("Error", "royal-sentinel"); ?>',
                    text: res.data.message,
                    timer: 2500,
                    showConfirmButton: false
                });
            }
        }).fail(function() {
            btn.prop('disabled', false);
            Swal.fire({
                icon: 'error',
                title: '<?php _e("Error", "royal-sentinel"); ?>',
                text: '<?php _e("Request failed. Please try again.", "royal-sentinel"); ?>',
                timer: 2000,
                showConfirmButton: false
            });
        });
    });

    // Remove IP Button (delegated)
    $(document).on('click', '.rs-remove-whitelist-ip', function() {
        var btn = $(this);
        var ip = btn.data('ip');

        Swal.fire({
            title: '<?php _e("Remove IP?", "royal-sentinel"); ?>',
            html: '<?php _e("Are you sure you want to remove", "royal-sentinel"); ?> <strong>' + ip + '</strong> <?php _e("from the whitelist?", "royal-sentinel"); ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d63638',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<?php _e("Yes, Remove", "royal-sentinel"); ?>',
            cancelButtonText: '<?php _e("Cancel", "royal-sentinel"); ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).text('<?php _e("Removing...", "royal-sentinel"); ?>');

                $.post(ajaxurl, {
                    action: 'rs_remove_whitelist_ip',
                    nonce: whitelistNonce,
                    ip: ip
                }, function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '<?php _e("Removed!", "royal-sentinel"); ?>',
                            text: res.data.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        refreshWhitelistTable(res.data.whitelist);
                    } else {
                        btn.prop('disabled', false).html('🗑️ <?php _e("Remove", "royal-sentinel"); ?>');
                        Swal.fire({
                            icon: 'error',
                            title: '<?php _e("Error", "royal-sentinel"); ?>',
                            text: res.data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                }).fail(function() {
                    btn.prop('disabled', false).html('🗑️ <?php _e("Remove", "royal-sentinel"); ?>');
                    Swal.fire({
                        icon: 'error',
                        title: '<?php _e("Error", "royal-sentinel"); ?>',
                        text: '<?php _e("Request failed. Please try again.", "royal-sentinel"); ?>',
                        timer: 2000,
                        showConfirmButton: false
                    });
                });
            }
        });
    });

    // Refresh Whitelist Table
    function refreshWhitelistTable(whitelist) {
        var tbody = $('#rs-whitelist-tbody');
        tbody.empty();

        $('#rs-whitelist-count').text(whitelist.length);

        if (whitelist.length === 0) {
            tbody.append(
                '<tr id="rs-whitelist-empty">' +
                '<td colspan="3" style="padding: 30px; text-align: center; color: #888;">' +
                '<span class="dashicons dashicons-info" style="font-size: 24px; width: 24px; height: 24px; margin-bottom: 10px;"></span>' +
                '<p style="margin: 0;"><?php _e("No IPs whitelisted yet. Add your IP above to get started.", "royal-sentinel"); ?></p>' +
                '</td></tr>'
            );
            return;
        }

        $.each(whitelist, function(i, entry) {
            var ip = entry.ip || '';
            var added = entry.added || '';
            var isAuto = entry.auto || false;
            var autoTag = isAuto ? '<span style="background: #e8f5e9; color: #27ae60; font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-left: 8px;"><?php _e("AUTO", "royal-sentinel"); ?></span>' : '';
            var timeAgo = added ? '<?php _e("Just now", "royal-sentinel"); ?>' : '-';

            tbody.append(
                '<tr data-ip="' + ip + '">' +
                '<td style="padding: 12px 15px; font-family: monospace; font-size: 14px;">' + ip + autoTag + '</td>' +
                '<td style="padding: 12px 15px; color: #666; font-size: 13px;">' + timeAgo + '</td>' +
                '<td style="padding: 12px 15px; text-align: center;">' +
                '<button type="button" class="rs-remove-whitelist-ip button" data-ip="' + ip + '" style="color: #d63638; border-color: #d63638; padding: 4px 12px;">🗑️ <?php _e("Remove", "royal-sentinel"); ?></button>' +
                '</td></tr>'
            );
        });
    }

    // Enter key on IP input
    $('#rs-new-whitelist-ip').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#rs-add-whitelist-ip').click();
        }
    });
});
</script>

