<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$rs_audit_nonce = wp_create_nonce('rs_audit_nonce');
?>
<div class="wrap">
    <div class="rs-page-header dark-gradient">
        <div class="rs-header-content">
            <h1 class="rs-page-title">
                <div class="rs-process-icon">📋</div>
                Activity Audit Log
            </h1>
            <p class="rs-page-subtitle">Real-time log of all key admin actions — logins, plugin changes, post edits, and more.</p>
        </div>
    </div>

    <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-top:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <div style="display:flex; gap:10px;">
                <select id="rs_audit_filter_action" style="height:35px;">
                    <option value="">All Actions</option>
                    <option value="login">Login</option>
                    <option value="logout">Logout</option>
                    <option value="magic_login">Magic Login</option>
                    <option value="post_saved">Post Saved</option>
                    <option value="post_deleted">Post Deleted</option>
                    <option value="plugin_activated">Plugin Activated</option>
                    <option value="plugin_deactivated">Plugin Deactivated</option>
                    <option value="theme_switched">Theme Switched</option>
                    <option value="profile_updated">Profile Updated</option>
                    <option value="user_registered">User Registered</option>
                    <option value="user_deleted">User Deleted</option>
                </select>
                <button id="rs_refresh_audit" class="button">🔄 Refresh</button>
            </div>
            <button id="rs_clear_audit" class="button button-link-delete" style="color:#e74c3c;">🗑 Clear All Logs</button>
        </div>

        <table id="rs_audit_table" class="wp-list-table widefat fixed striped" style="width:100%;">
            <thead>
                <tr>
                    <th style="width:155px;">📅 Date & Time</th>
                    <th style="width:120px;">👤 User</th>
                    <th style="width:150px;">⚡ Action</th>
                    <th style="width:90px;">Type</th>
                    <th>Object</th>
                    <th style="width:130px;">🌐 IP Address</th>
                </tr>
            </thead>
            <tbody id="rs_audit_tbody">
                <tr><td colspan="6" style="text-align:center; padding:40px; color:#888;">
                    <span class="dashicons dashicons-update spin" style="font-size:24px; width:24px; height:24px;"></span>
                    Loading...
                </td></tr>
            </tbody>
        </table>

        <p style="color:#888; font-size:12px; margin-top:10px;">
            Last 200 entries shown. Auto-refreshes every 30 seconds. 
            <span id="rs_audit_last_updated"></span>
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var allLogs = [];

    const actionLabels = {
        login:              '🔓 Login',
        logout:             '🔒 Logout',
        magic_login:        '✨ Magic Login',
        post_saved:         '📝 Post Saved',
        post_deleted:       '🗑️ Post Deleted',
        plugin_activated:   '✅ Plugin On',
        plugin_deactivated: '❌ Plugin Off',
        theme_switched:     '🎨 Theme Changed',
        profile_updated:    '👤 Profile Updated',
        user_registered:    '🆕 User Registered',
        user_deleted:       '🚫 User Deleted'
    };

    const actionColors = {
        login:              '#27ae60',
        logout:             '#7f8c8d',
        magic_login:        '#9b59b6',
        post_saved:         '#2271b1',
        post_deleted:       '#e74c3c',
        plugin_activated:   '#27ae60',
        plugin_deactivated: '#e74c3c',
        theme_switched:     '#f39c12',
        profile_updated:    '#2271b1',
        user_registered:    '#27ae60',
        user_deleted:       '#e74c3c'
    };

    function renderLogs(logs) {
        if (!logs.length) {
            $('#rs_audit_tbody').html('<tr><td colspan="6" style="text-align:center;padding:40px;color:#888;">No activity recorded yet. Actions like logins, plugin changes, and post edits will appear here.</td></tr>');
            return;
        }

        var filterAction = $('#rs_audit_filter_action').val();
        var filtered = filterAction ? logs.filter(r => r.action === filterAction) : logs;

        if (!filtered.length) {
            $('#rs_audit_tbody').html('<tr><td colspan="6" style="text-align:center;padding:30px;color:#888;">No entries match the selected filter.</td></tr>');
            return;
        }

        var html = '';
        filtered.forEach(function(row) {
            var label = actionLabels[row.action] || row.action;
            var color = actionColors[row.action] || '#666';
            var objName = row.object_name.length > 60 ? row.object_name.substring(0, 60) + '…' : row.object_name;
            html += '<tr>' +
                '<td style="font-size:12px;color:#666;">' + row.created_at + '</td>' +
                '<td><strong style="color:#2271b1;">' + row.user_login + '</strong></td>' +
                '<td><span style="background:' + color + '18;color:' + color + ';padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;white-space:nowrap;">' + label + '</span></td>' +
                '<td style="font-size:12px;color:#888;text-transform:capitalize;">' + row.object_type + '</td>' +
                '<td style="font-size:13px;" title="' + row.object_name + '">' + objName + '</td>' +
                '<td style="font-size:12px;font-family:monospace;color:#666;">' + row.ip + '</td>' +
                '</tr>';
        });
        $('#rs_audit_tbody').html(html);
    }

    function loadAuditLog() {
        $.post(ajaxurl, {
            action: 'rs_get_audit_log',
            nonce:  '<?php echo $rs_audit_nonce; ?>'
        }, function(res) {
            if (res.success) {
                allLogs = res.data;
                renderLogs(allLogs);
                var now = new Date();
                $('#rs_audit_last_updated').text('Updated: ' + now.toLocaleTimeString());
            }
        });
    }

    loadAuditLog();
    var autoRefresh = setInterval(loadAuditLog, 30000);

    $('#rs_refresh_audit').on('click', loadAuditLog);
    $('#rs_audit_filter_action').on('change', function() { renderLogs(allLogs); });

    $('#rs_clear_audit').on('click', function() {
        Swal.fire({
            title: 'Clear All Logs?',
            text: 'All activity records will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, clear it!'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post(ajaxurl, {
                    action: 'rs_clear_audit_log',
                    nonce:  '<?php echo $rs_audit_nonce; ?>'
                }, function() {
                    allLogs = [];
                    renderLogs([]);
                    Swal.fire({ icon:'success', title:'Cleared!', timer:1500, showConfirmButton:false });
                });
            }
        });
    });
});
</script>
