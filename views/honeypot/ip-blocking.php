<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_badips = $wpdb->prefix . 'royalsentinel_badips';

// Get only honeypot-blocked IPs
$honeypot_blocked = $wpdb->get_results( 
    "SELECT * FROM $table_badips WHERE reason LIKE 'honeypot:%' ORDER BY created_at DESC" 
);
?>

<div style="margin-bottom: 20px;">
    <h3 style="margin: 0 0 10px;">🚫 <?php esc_html_e( 'Honeypot IP Blocking', 'royal-sentinel' ); ?></h3>
    <p class="description"><?php esc_html_e( 'IPs that have been permanently blocked due to repeated honeypot spam attempts.', 'royal-sentinel' ); ?></p>
</div>

<!-- Manual Block Section -->
<div class="rs-section-box" style="border-left: 4px solid #e74c3c; margin-bottom: 20px;">
    <h4 class="rs-section-title" style="color: #e74c3c;">➕ <?php esc_html_e( 'Manually Block IP', 'royal-sentinel' ); ?></h4>
    
    <div style="display: flex; gap: 10px; align-items: center;">
        <input type="text" id="rs_hp_manual_ip" placeholder="<?php esc_attr_e( 'Enter IP address (e.g., 192.168.1.1)', 'royal-sentinel' ); ?>" class="regular-text" style="width: 250px;">
        <button type="button" id="rs_hp_add_ban" class="button button-secondary">
            <span class="dashicons dashicons-shield-alt" style="vertical-align:middle;"></span> <?php esc_html_e( 'Block IP', 'royal-sentinel' ); ?>
        </button>
    </div>
    <p class="description" style="margin-top: 10px;"><?php esc_html_e( 'Manually add an IP to the honeypot blocklist.', 'royal-sentinel' ); ?></p>
</div>

<!-- Blocked IPs Table -->
<div class="rs-section-box" style="border-left: 4px solid #27ae60;">
    <h4 class="rs-section-title" style="color: #27ae60;">📋 <?php esc_html_e( 'Blocked IPs (Honeypot)', 'royal-sentinel' ); ?></h4>
    
    <table id="rs_hp_blocked_table" class="display" style="width:100%">
        <thead>
            <tr>
                <th><?php esc_html_e( 'IP Address', 'royal-sentinel' ); ?></th>
                <th><?php esc_html_e( 'Source', 'royal-sentinel' ); ?></th>
                <th><?php esc_html_e( 'Blocked Date', 'royal-sentinel' ); ?></th>
                <th><?php esc_html_e( 'Action', 'royal-sentinel' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $honeypot_blocked ) ): ?>
                <?php foreach ( $honeypot_blocked as $row ): 
                    $source = str_replace( 'honeypot:', '', $row->reason );
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $row->bad_ip ); ?></strong></td>
                        <td><?php echo esc_html( ucwords( str_replace( '_', ' ', $source ) ) ); ?></td>
                        <td><?php echo esc_html( $row->created_at ); ?></td>
                        <td>
                            <button type="button" class="button button-link-delete rs-hp-unban-btn" 
                                    data-ip="<?php echo esc_attr( $row->bad_ip ); ?>" 
                                    style="color: #a00;">
                                <?php esc_html_e( 'Unblock', 'royal-sentinel' ); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #666;">
                        <?php esc_html_e( 'No IPs have been blocked by honeypot yet. IPs will appear here after hitting the spam threshold.', 'royal-sentinel' ); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-radius: 5px;">
    <p style="margin: 0;">
        <span class="dashicons dashicons-info" style="color: #3498db;"></span>
        <strong><?php esc_html_e( 'Note:', 'royal-sentinel' ); ?></strong> <?php esc_html_e( 'These IPs are also visible in', 'royal-sentinel' ); ?> 
        <a href="<?php echo admin_url('admin.php?page=rs-advance-security&tab=bot'); ?>"><?php esc_html_e( 'Advance Security → Bot Protection → Permanent Bans', 'royal-sentinel' ); ?></a>.
    </p>
</div>

<script>
jQuery(document).ready(function($) {
    <?php if ( ! empty( $honeypot_blocked ) ): ?>
    if ( $.fn.DataTable && !$.fn.DataTable.isDataTable('#rs_hp_blocked_table') ) {
        $('#rs_hp_blocked_table').DataTable({
            "pageLength": 25,
            "order": [[ 2, "desc" ]]
        });
    }
    <?php endif; ?>
    
    $('#rs_hp_add_ban').on('click', function() {
        var ip = $('#rs_hp_manual_ip').val().trim();
        if (!ip) {
            Swal.fire('<?php echo esc_js( __( 'Error', 'royal-sentinel' ) ); ?>', '<?php echo esc_js( __( 'Please enter an IP address', 'royal-sentinel' ) ); ?>', 'error');
            return;
        }
        
        var ipRegex = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/;
        if (!ipRegex.test(ip)) {
            Swal.fire('<?php echo esc_js( __( 'Error', 'royal-sentinel' ) ); ?>', '<?php echo esc_js( __( 'Invalid IP address format', 'royal-sentinel' ) ); ?>', 'error');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'rs_add_local_ban',
            ip: ip,
            reason: 'honeypot:manual',
            _wpnonce: '<?php echo wp_create_nonce("rs_ban_action"); ?>'
        }, function(resp) {
            if (resp.success) {
                Swal.fire({
                    icon: 'success',
                    title: '<?php echo esc_js( __( 'IP Blocked!', 'royal-sentinel' ) ); ?>',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('<?php echo esc_js( __( 'Error', 'royal-sentinel' ) ); ?>', resp.data || '<?php echo esc_js( __( 'Failed to block IP', 'royal-sentinel' ) ); ?>', 'error');
            }
        });
    });
    
    $(document).on('click', '.rs-hp-unban-btn', function() {
        var ip = $(this).data('ip');
        
        Swal.fire({
            title: '<?php echo esc_js( __( 'Unblock this IP?', 'royal-sentinel' ) ); ?>',
            text: 'IP: ' + ip,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?php echo esc_js( __( 'Yes, Unblock', 'royal-sentinel' ) ); ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(ajaxurl, {
                    action: 'rs_unlock_ip_action',
                    ip: ip,
                    type: 'badip',
                    _wpnonce: '<?php echo wp_create_nonce("rs_ban_action"); ?>'
                }, function(resp) {
                    if (resp.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '<?php echo esc_js( __( 'IP Unblocked!', 'royal-sentinel' ) ); ?>',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('<?php echo esc_js( __( 'Error', 'royal-sentinel' ) ); ?>', '<?php echo esc_js( __( 'Failed to unblock IP', 'royal-sentinel' ) ); ?>', 'error');
                    }
                });
            }
        });
    });
});
</script>

<style>
.dataTables_length select {
    width: 60px !important;
}
</style>
