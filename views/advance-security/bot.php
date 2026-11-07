<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

if ( function_exists('rs_can_access_firewall') && !rs_can_access_firewall() ) {
    echo '<div class="notice notice-error" style="margin: 20px 0; padding: 15px;">
            <h3 style="margin: 0; color: #d63638;">🚫 Access Denied</h3>
            <p>You do not have permission to view or manage Firewall Security settings. Please contact the Super Administrator.</p>
          </div>';
    return;
}


global $wpdb;

// ১. সেটিংস ভেরিয়েবলস (Settings API থেকে)
// --- Brute Force ---
$local_brute_on    = isset($rs_settings['local_brute']) ? $rs_settings['local_brute'] : 0;
$lockout_admin     = isset($rs_settings['lockout_admin']) ? $rs_settings['lockout_admin'] : 0;
$max_attempts_ip   = isset($rs_settings['max_attempts_ip']) ? $rs_settings['max_attempts_ip'] : '5';
$max_attempts_user = isset($rs_settings['max_attempts_user']) ? $rs_settings['max_attempts_user'] : '10'; 
$retry_period      = isset($rs_settings['retry_period']) ? $rs_settings['retry_period'] : '5';

// --- Firewall ---
$fw_engine_on      = isset($rs_settings['firewall_engine']) ? $rs_settings['firewall_engine'] : 0; 
$max_fw_violations = isset($rs_settings['max_fw_violations']) ? $rs_settings['max_fw_violations'] : '10';
$fw_check_period   = isset($rs_settings['fw_check_period']) ? $rs_settings['fw_check_period'] : '30';

// --- Ban Users ---
$ban_users_on      = isset($rs_settings['ban_users']) ? $rs_settings['ban_users'] : 0;

// ২. Firewall Scope ডাটা লোড করা (New Table)
$table_fw_rules = $wpdb->prefix . 'royalsentinel_fw_rules';
// টেবিল আছে কিনা চেক করে ডাটা আনা
$fw_rule_data = null;
if($wpdb->get_var("SHOW TABLES LIKE '$table_fw_rules'") == $table_fw_rules) {
    $fw_rule_data = $wpdb->get_row("SELECT * FROM $table_fw_rules WHERE rule_slug = 'main_firewall_access'");
}

$current_scope = $fw_rule_data ? $fw_rule_data->scope : 'all_admins';
$allowed_ids   = ($fw_rule_data && $fw_rule_data->allowed_users) ? json_decode($fw_rule_data->allowed_users, true) : [];
$all_admins    = get_users(['role' => 'administrator']);

// ৩. লকআউট এবং ব্যান লিস্ট লোড করা
$table_badips = $wpdb->prefix . 'royalsentinel_badips';
$banned_ips   = $wpdb->get_results( "SELECT * FROM $table_badips ORDER BY created_at DESC" );

$table_lockouts = $wpdb->prefix . 'royalsentinel_lockouts';
$locked_ips     = $wpdb->get_results( "SELECT * FROM $table_lockouts ORDER BY last_attempt DESC" );
?>

<div class="rs-bot-wrapper">

    <div style="margin-bottom: 20px;">
        <h3 style="margin:0;">Bot Protection</h3>
        <p class="description">Configure rules to protect against automated attacks and firewall violations.</p>
    </div>

    <div class="rs-section-box" style="border-left: 4px solid #d35400;">
        <h4 class="rs-section-title" style="color: #d35400;">Local Brute Force</h4>
        
        <div style="margin-bottom: 15px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="local_brute" id="rs_local_brute_toggle" value="1" <?php checked(1, $local_brute_on); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_local_brute_toggle" style="font-weight: 600;">Enable Local Brute Force Protection</label>
            <p class="description" style="margin-left: 55px; margin-top: 0;">Limit login attempts to prevent guessing passwords.</p>
        </div>

        <div id="rs_local_brute_settings" style="display: <?php echo $local_brute_on ? 'block' : 'none'; ?>;">
            
            <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #eee;">
                <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                    <input type="checkbox" name="lockout_admin" value="1" <?php checked(1, $lockout_admin); ?>>
                    <span class="rs-slider round"></span>
                </label>
                <label style="font-weight: 600; color: #d63638;">Automatically lockout "admin" user</label>
                <p class="description" style="margin-left: 55px; margin-top: 5px;">Immediately lockout an IP address that attempts to login using the "admin" username.</p>
            </div>

            <table class="form-table">
                <tr>
                    <th><label>MAX LOGIN ATTEMPTS PER IP</label></th>
                    <td>
                        <input type="number" name="max_attempts_ip" value="<?php echo esc_attr($max_attempts_ip); ?>" class="regular-text">
                        <p class="description">Lockout IP after this many failures.</p>
                    </td>
                </tr>
                <tr>
                    <th><label>MAX LOGIN ATTEMPTS PER USER</label></th>
                    <td>
                        <input type="number" name="max_attempts_user" value="<?php echo esc_attr($max_attempts_user); ?>" class="regular-text">
                        <p class="description">Lockout valid username after this many failures (across any IP).</p>
                    </td>
                </tr>
                <tr>
                    <th><label>MINUTES TO REMEMBER BAD LOGIN</label></th>
                    <td>
                        <input type="number" name="retry_period" value="<?php echo esc_attr($retry_period); ?>" class="regular-text">
                        <p class="description">How long the lockout lasts.</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">Firewall Rules Engine</h4>
        
        <div style="margin-bottom: 15px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="firewall_engine" id="rs_fw_engine_toggle" value="1" <?php checked(1, $fw_engine_on); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_fw_engine_toggle" style="font-weight: 600;">Firewall Rules Engine Settings</label>
            <p class="description" style="margin-left: 55px; margin-top: 0;">Protect your site with firewall rules (e.g. 404 detection, bad queries).</p>
        </div>

        <div id="rs_fw_engine_settings" style="display: <?php echo $fw_engine_on ? 'block' : 'none'; ?>;">
            <table class="form-table">
                <tr>
                    <th><label>MAX VIOLATIONS PER IP</label></th>
                    <td>
                        <input type="number" name="max_fw_violations" value="<?php echo esc_attr($max_fw_violations); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label>CHECK PERIOD (MINUTES)</label></th>
                    <td>
                        <input type="number" name="fw_check_period" value="<?php echo esc_attr($fw_check_period); ?>" class="regular-text">
                    </td>
                </tr>
            </table>

            <div style="background: #fdfdfd; padding: 15px; border: 1px solid #e5e5e5; margin-top: 15px;">
                <h4 style="margin-top:0;">🛑 Administrator Access Scope</h4>
                <p class="description" style="margin-bottom:15px;">Control which administrators are affected by firewall rules.</p>
                
                <div class="rs-scope-control">
                    <table class="form-table">
                        <tr>
                            <th><label>Admin Access Scope</label></th>
                            <td>
                                <select id="fw_admin_scope_selector" style="min-width: 250px;">
                                    <option value="all_admins" <?php selected($current_scope, 'all_admins'); ?>>Apply to ALL Administrators</option>
                                    <option value="specific_admins" <?php selected($current_scope, 'specific_admins'); ?>>Specific Administrators Only</option>
                                </select>
                            </td>
                        </tr>

                        <tr id="fw_specific_admin_row" style="display: <?php echo ($current_scope === 'specific_admins') ? 'table-row' : 'none'; ?>;">
                            <th><label>Select Administrators</label></th>
                            <td>
                                <div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff; width: 100%; max-width: 350px;">
                                    <?php foreach ($all_admins as $admin): ?>
                                        <label style="display: flex; align-items: center; margin-bottom: 5px;">
                                            <input type="checkbox" class="fw_specific_user_cb" value="<?php echo esc_attr($admin->ID); ?>" 
                                            <?php echo (is_array($allowed_ids) && in_array($admin->ID, $allowed_ids)) ? 'checked' : ''; ?> style="margin-right: 8px;">
                                            <span><?php echo esc_html($admin->display_name . ' (' . $admin->user_login . ')'); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description">Only selected administrators will be allowed/monitored based on your logic.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th></th>
                            <td>
                                <input type="hidden" id="rs_fw_nonce" value="<?php echo wp_create_nonce('rs_fw_nonce'); ?>">
                                <button type="button" id="rs_save_fw_scope_btn" class="button button-secondary">Update Access Scope</button>
                                <span id="rs_fw_save_msg" style="margin-left: 10px; color: green; display: none; font-weight: bold;">Saved!</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #e67e22;">
        <h4 class="rs-section-title" style="color: #e67e22;">🚫 Temporary Lockouts (Wrong Passwords)</h4>
        <p class="description">IPs listed here are temporarily blocked due to failed login attempts (Brute Force).</p>
        
        <table id="rs_lockout_table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Attempts</th>
                    <th>Last Attempt</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $locked_ips ) ): ?>
                    <?php foreach ( $locked_ips as $row ): ?>
                        <tr>
                            <td><?php echo esc_html($row->lockout_ip); ?></td>
                            <td><?php echo esc_html($row->attempt_count); ?></td>
                            <td><?php echo esc_html($row->last_attempt); ?></td>
                            <td>
                                <button type="button" class="button button-small rs-unlock-ip-btn" 
                                        data-ip="<?php echo esc_attr($row->lockout_ip); ?>" 
                                        data-type="lockout">Unlock</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #c0392b;">
        <h4 class="rs-section-title" style="color: #c0392b;">☠️ Permanent Bans (Blacklist)</h4>
        
        <div style="margin-bottom: 15px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="ban_users" id="rs_ban_users_toggle" value="1" <?php checked(1, $ban_users_on); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_ban_users_toggle" style="font-weight: 600;">Enable Ban Lists</label>
        </div>

        <div id="rs_ban_users_settings" style="display: <?php echo $ban_users_on ? 'block' : 'none'; ?>;">
            <div style="background: #f0f0f1; padding: 15px; margin-bottom: 20px; display: flex; gap: 10px;">
                <input type="text" id="rs_manual_ban_ip" placeholder="Enter IP (e.g. 192.168.1.1)" class="regular-text">
                <button type="button" id="rs_add_ban_ip_btn" class="button button-secondary">Ban IP</button>
            </div>

            <table id="rs_ban_table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Reason</th>
                        <th>Banned Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $banned_ips ) ): ?>
                        <?php foreach ( $banned_ips as $row ): ?>
                            <tr>
                                <td><strong><?php echo esc_html($row->bad_ip); ?></strong></td>
                                <td><?php echo esc_html($row->reason); ?></td>
                                <td><?php echo esc_html($row->created_at); ?></td>
                                <td>
                                    <button type="button" class="button button-link-delete rs-unlock-ip-btn" 
                                            data-ip="<?php echo esc_attr($row->bad_ip); ?>" 
                                            data-type="badip" style="color: #a00;">Unban</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 250px; font-weight: 600; font-size: 12px; text-transform: uppercase; color: #555; }
    .regular-text { width: 100%; max-width: 250px; }
</style>

<script>
jQuery(document).ready(function($) {
    
    // Toggle Section Logic
    function toggleSection(id, wrapperId) {
        if($(id).is(':checked')) { $(wrapperId).slideDown(); } else { $(wrapperId).slideUp(); }
    }
    
    // Event Listeners for Toggles
    $('#rs_local_brute_toggle').on('change', function() { toggleSection(this, '#rs_local_brute_settings'); });
    $('#rs_fw_engine_toggle').on('change', function() { toggleSection(this, '#rs_fw_engine_settings'); });
    $('#rs_ban_users_toggle').on('change', function() { toggleSection(this, '#rs_ban_users_settings'); });

    // Init Toggles on Load
    toggleSection('#rs_local_brute_toggle', '#rs_local_brute_settings');
    toggleSection('#rs_fw_engine_toggle', '#rs_fw_engine_settings');
    toggleSection('#rs_ban_users_toggle', '#rs_ban_users_settings');

    // Init DataTables
    if ( ! $.fn.DataTable.isDataTable('#rs_ban_table') ) { $('#rs_ban_table').DataTable({ "pageLength": 10 }); }
    if ( ! $.fn.DataTable.isDataTable('#rs_lockout_table') ) { $('#rs_lockout_table').DataTable({ "pageLength": 10 }); }

    // --- SCOPE TOGGLE UI ---
    function toggleScopeRow() {
        if ($('#fw_admin_scope_selector').val() === 'specific_admins') {
            $('#fw_specific_admin_row').show();
        } else {
            $('#fw_specific_admin_row').hide();
        }
    }
    $('#fw_admin_scope_selector').change(toggleScopeRow);
    toggleScopeRow(); // Init on load

    // --- AJAX: SAVE SCOPE (Fix for saving issue) ---
    $('#rs_save_fw_scope_btn').click(function(e) {
        e.preventDefault();
        var btn = $(this);
        var msg = $('#rs_fw_save_msg');
        
        btn.prop('disabled', true).text('Saving...');
        msg.hide();

        // Collect Checkbox Data
        var selectedUsers = [];
        $('.fw_specific_user_cb:checked').each(function() {
            selectedUsers.push($(this).val());
        });

        // Send AJAX Request
        $.post(ajaxurl, {
            action: 'rs_save_firewall_scope',
            scope: $('#fw_admin_scope_selector').val(),
            users: selectedUsers,
            _wpnonce: $('#rs_fw_nonce').val()
        }, function(response) {
            btn.prop('disabled', false).text('Update Access Scope');
            
            if (response.success) {
                // Show success feedback
                msg.fadeIn().delay(2000).fadeOut();
                Swal.fire({
                    icon: 'success',
                    title: 'Saved!',
                    text: response.data,
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', response.data, 'error');
            }
        });
    });

    // --- AJAX: ADD BAN ---
    $('#rs_add_ban_ip_btn').click(function() {
        var ip = $('#rs_manual_ban_ip').val();
        if(!ip) { Swal.fire('Error', 'Enter IP address', 'error'); return; }

        $.post(ajaxurl, {
            action: 'rs_add_local_ban',
            ip: ip,
            _wpnonce: '<?php echo wp_create_nonce("rs_ban_action"); ?>'
        }, function(resp) {
            if(resp.success) {
                Swal.fire({ icon: 'success', title: 'Banned!', showConfirmButton: false, timer: 1000 }).then(() => location.reload());
            } else { Swal.fire('Error', resp.data, 'error'); }
        });
    });

    // --- AJAX: UNLOCK / UNBAN (Unified) ---
    $(document).on('click', '.rs-unlock-ip-btn', function() {
        var ip = $(this).data('ip');
        var type = $(this).data('type'); // 'lockout' or 'badip'

        Swal.fire({
            title: 'Are you sure?',
            text: type === 'badip' ? "Unban this IP permanently?" : "Unlock this IP temporarily?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Proceed'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(ajaxurl, {
                    action: 'rs_unlock_ip_action', 
                    ip: ip,
                    type: type,
                    _wpnonce: '<?php echo wp_create_nonce("rs_ban_action"); ?>'
                }, function(resp) {
                    if(resp.success) {
                        Swal.fire({ icon: 'success', title: 'Done!', showConfirmButton: false, timer: 1000 }).then(() => location.reload());
                    } else { Swal.fire('Error', 'Failed.', 'error'); }
                });
            }
        });
    });

});
</script>