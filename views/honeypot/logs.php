<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$table_logs = $wpdb->prefix . 'royalsentinel_honeypot_logs';

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_logs'") === $table_logs;

// Get filter values
$filter_source = isset($_GET['source']) ? sanitize_key($_GET['source']) : '';
$filter_reason = isset($_GET['reason']) ? sanitize_key($_GET['reason']) : '';

// Build query
$where_clauses = array();
$where_values = array();

if ( $filter_source ) {
    $where_clauses[] = 'source = %s';
    $where_values[] = $filter_source;
}
if ( $filter_reason ) {
    $where_clauses[] = 'blocked_reason = %s';
    $where_values[] = $filter_reason;
}

$where_sql = '';
if ( ! empty( $where_clauses ) ) {
    $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
}

// Get logs
$logs = array();
$sources = array();
$reasons = array();

if ( $table_exists ) {
    if ( ! empty( $where_values ) ) {
        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table_logs $where_sql ORDER BY created_at DESC LIMIT 500",
            ...$where_values
        ) );
    } else {
        $logs = $wpdb->get_results( "SELECT * FROM $table_logs ORDER BY created_at DESC LIMIT 500" );
    }
    
    $sources = $wpdb->get_col( "SELECT DISTINCT source FROM $table_logs ORDER BY source" );
    $reasons = $wpdb->get_col( "SELECT DISTINCT blocked_reason FROM $table_logs ORDER BY blocked_reason" );
}

$retention = get_option('rs_honeypot_settings', array());
$retention_days = isset($retention['retention_days']) ? $retention['retention_days'] : 30;
?>

<div style="margin-bottom: 20px;">
    <h3 style="margin: 0 0 10px;">📋 <?php esc_html_e( 'Spam Attempt Logs', 'royal-sentinel' ); ?></h3>
    <p class="description"><?php printf( esc_html__( 'Detailed logs of spam attempts blocked by honeypot. Logs older than %d days are automatically deleted.', 'royal-sentinel' ), $retention_days ); ?></p>
</div>

<!-- Filters -->
<div class="rs-section-box" style="border-left: 4px solid #3498db; margin-bottom: 20px;">
    <form method="get" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="page" value="rs-honeypot">
        <input type="hidden" name="tab" value="logs">
        
        <div>
            <label><strong><?php esc_html_e( 'Source:', 'royal-sentinel' ); ?></strong></label>
            <select name="source" style="min-width: 150px;">
                <option value=""><?php esc_html_e( 'All Sources', 'royal-sentinel' ); ?></option>
                <?php foreach ($sources as $src): ?>
                    <option value="<?php echo esc_attr($src); ?>" <?php selected($filter_source, $src); ?>>
                        <?php echo esc_html(ucwords(str_replace('_', ' ', $src))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div>
            <label><strong><?php esc_html_e( 'Reason:', 'royal-sentinel' ); ?></strong></label>
            <select name="reason" style="min-width: 150px;">
                <option value=""><?php esc_html_e( 'All Reasons', 'royal-sentinel' ); ?></option>
                <?php foreach ($reasons as $rsn): ?>
                    <option value="<?php echo esc_attr($rsn); ?>" <?php selected($filter_reason, $rsn); ?>>
                        <?php echo esc_html(ucwords(str_replace('_', ' ', $rsn))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="button"><?php esc_html_e( 'Filter', 'royal-sentinel' ); ?></button>
        <a href="?page=rs-honeypot&tab=logs" class="button"><?php esc_html_e( 'Reset', 'royal-sentinel' ); ?></a>
    </form>
</div>

<!-- Logs Table -->
<div class="rs-section-box" style="border-left: 4px solid #27ae60;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h4 class="rs-section-title" style="color: #27ae60; margin: 0; border: none; padding: 0;">
            <?php esc_html_e( 'Recent Logs (Max 500)', 'royal-sentinel' ); ?>
        </h4>
        <button type="button" id="rs_hp_clear_logs" class="button button-link-delete" style="color: #d33;">
            <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> <?php esc_html_e( 'Clear All Logs', 'royal-sentinel' ); ?>
        </button>
    </div>
    
    <?php if ( ! $table_exists ): ?>
        <div style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px;">
            <p style="margin: 0; color: #856404;">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e( 'Logs table not found. Please deactivate and reactivate the plugin to create the table.', 'royal-sentinel' ); ?>
            </p>
        </div>
    <?php else: ?>
        <table id="rs_hp_logs_table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'IP Address', 'royal-sentinel' ); ?></th>
                    <th><?php esc_html_e( 'Source', 'royal-sentinel' ); ?></th>
                    <th><?php esc_html_e( 'Reason', 'royal-sentinel' ); ?></th>
                    <th><?php esc_html_e( 'User Agent', 'royal-sentinel' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'royal-sentinel' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $logs ) ): ?>
                    <?php foreach ( $logs as $log ): ?>
                        <tr>
                            <td><strong><?php echo esc_html( $log->ip_address ); ?></strong></td>
                            <td>
                                <span style="background: #e8f5e9; padding: 2px 8px; border-radius: 3px; font-size: 12px;">
                                    <?php echo esc_html( ucwords( str_replace( '_', ' ', $log->source ) ) ); ?>
                                </span>
                            </td>
                            <td>
                                <span style="background: #ffebee; padding: 2px 8px; border-radius: 3px; font-size: 12px; color: #c62828;">
                                    <?php echo esc_html( ucwords( str_replace( '_', ' ', $log->blocked_reason ) ) ); ?>
                                </span>
                            </td>
                            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr( $log->user_agent ); ?>">
                                <?php echo esc_html( substr( $log->user_agent, 0, 50 ) ); ?>...
                            </td>
                            <td><?php echo esc_html( $log->created_at ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #666;">
                            <?php esc_html_e( 'No logs found. Logs will appear here when honeypot blocks spam attempts.', 'royal-sentinel' ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    <?php if ( ! empty( $logs ) ): ?>
    if ( $.fn.DataTable && !$.fn.DataTable.isDataTable('#rs_hp_logs_table') ) {
        $('#rs_hp_logs_table').DataTable({
            "pageLength": 25,
            "order": [[ 4, "desc" ]]
        });
    }
    <?php endif; ?>
    
    $('#rs_hp_clear_logs').on('click', function() {
        Swal.fire({
            title: '<?php echo esc_js( __( 'Clear All Logs?', 'royal-sentinel' ) ); ?>',
            text: '<?php echo esc_js( __( 'This will permanently delete all honeypot logs. Statistics will remain unchanged.', 'royal-sentinel' ) ); ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?php echo esc_js( __( 'Yes, Clear All', 'royal-sentinel' ) ); ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(ajaxurl, {
                    action: 'rs_honeypot_clear_logs',
                    _wpnonce: '<?php echo wp_create_nonce("rs_honeypot_nonce"); ?>'
                }, function(resp) {
                    if (resp.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '<?php echo esc_js( __( 'Logs Cleared!', 'royal-sentinel' ) ); ?>',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('<?php echo esc_js( __( 'Error', 'royal-sentinel' ) ); ?>', '<?php echo esc_js( __( 'Failed to clear logs', 'royal-sentinel' ) ); ?>', 'error');
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
