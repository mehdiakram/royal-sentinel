<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$rs_hp_stats = json_decode( get_option( 'rs_honeypot_stats', '{}' ), true );
if ( ! is_array( $rs_hp_stats ) ) {
    $rs_hp_stats = array();
}

// Helper to get stat value safely
function rs_hp_get_stat( $stats, $source, $period ) {
    if ( ! isset( $stats[ $source ][ $period ] ) ) {
        return 0;
    }
    
    $data = $stats[ $source ][ $period ];
    
    if ( $period === 'all_time' ) {
        return intval( $data );
    }
    
    if ( ! isset( $data['date'] ) || ! isset( $data['count'] ) ) {
        return 0;
    }
    
    if ( function_exists( 'rs_honeypot_check_period' ) && rs_honeypot_check_period( $data['date'], $period ) ) {
        return intval( $data['count'] );
    }
    
    return 0;
}

// Get sources (exclude 'total')
$sources = array_keys( $rs_hp_stats );
$sources = array_filter( $sources, function( $s ) { return $s !== 'total'; } );
?>

<div style="margin-bottom: 20px;">
    <h3 style="margin: 0 0 10px;">📊 <?php esc_html_e( 'Spam Blocking Statistics', 'royal-sentinel' ); ?></h3>
    <p class="description"><?php esc_html_e( 'Overview of spam attempts blocked by honeypot protection.', 'royal-sentinel' ); ?></p>
</div>

<!-- Summary Cards -->
<div class="rs-hp-grid" style="margin-bottom: 30px;">
    <div class="rs-hp-card" style="text-align: center; border-left: 4px solid #3498db;">
        <h2 style="margin: 0; font-size: 36px; color: #3498db;">
            <?php echo rs_hp_get_stat( $rs_hp_stats, 'total', 'today' ); ?>
        </h2>
        <p style="margin: 5px 0 0; font-weight: 600;"><?php esc_html_e( 'Today', 'royal-sentinel' ); ?></p>
    </div>
    <div class="rs-hp-card" style="text-align: center; border-left: 4px solid #9b59b6;">
        <h2 style="margin: 0; font-size: 36px; color: #9b59b6;">
            <?php echo rs_hp_get_stat( $rs_hp_stats, 'total', 'week' ); ?>
        </h2>
        <p style="margin: 5px 0 0; font-weight: 600;"><?php esc_html_e( 'This Week', 'royal-sentinel' ); ?></p>
    </div>
    <div class="rs-hp-card" style="text-align: center; border-left: 4px solid #e67e22;">
        <h2 style="margin: 0; font-size: 36px; color: #e67e22;">
            <?php echo rs_hp_get_stat( $rs_hp_stats, 'total', 'month' ); ?>
        </h2>
        <p style="margin: 5px 0 0; font-weight: 600;"><?php esc_html_e( 'This Month', 'royal-sentinel' ); ?></p>
    </div>
    <div class="rs-hp-card" style="text-align: center; border-left: 4px solid #27ae60;">
        <h2 style="margin: 0; font-size: 36px; color: #27ae60;">
            <?php echo rs_hp_get_stat( $rs_hp_stats, 'total', 'all_time' ); ?>
        </h2>
        <p style="margin: 5px 0 0; font-weight: 600;"><?php esc_html_e( 'All Time', 'royal-sentinel' ); ?></p>
    </div>
</div>

<!-- Source Breakdown Table -->
<div class="rs-section-box" style="border-left: 4px solid #27ae60;">
    <h4 class="rs-section-title" style="color: #27ae60;">📈 <?php esc_html_e( 'Breakdown by Source', 'royal-sentinel' ); ?></h4>
    
    <table id="rs_hp_stats_table" class="display" style="width:100%">
        <thead>
            <tr>
                <th><strong><?php esc_html_e( 'Source', 'royal-sentinel' ); ?></strong></th>
                <th><strong><?php esc_html_e( 'Today', 'royal-sentinel' ); ?></strong></th>
                <th><strong><?php esc_html_e( 'This Week', 'royal-sentinel' ); ?></strong></th>
                <th><strong><?php esc_html_e( 'This Month', 'royal-sentinel' ); ?></strong></th>
                <th><strong><?php esc_html_e( 'All Time', 'royal-sentinel' ); ?></strong></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $sources ) ): ?>
                <?php foreach ( $sources as $source ): ?>
                    <tr>
                        <td><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $source ) ) ); ?></strong></td>
                        <td><?php echo rs_hp_get_stat( $rs_hp_stats, $source, 'today' ); ?></td>
                        <td><?php echo rs_hp_get_stat( $rs_hp_stats, $source, 'week' ); ?></td>
                        <td><?php echo rs_hp_get_stat( $rs_hp_stats, $source, 'month' ); ?></td>
                        <td><?php echo rs_hp_get_stat( $rs_hp_stats, $source, 'all_time' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #666;"><?php esc_html_e( 'No spam blocked yet. Statistics will appear here once honeypot catches spam.', 'royal-sentinel' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Reset Button -->
<div style="margin-top: 20px;">
    <button type="button" id="rs_reset_stats" class="button button-secondary">
        <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> <?php esc_html_e( 'Reset Statistics', 'royal-sentinel' ); ?>
    </button>
</div>

<script>
jQuery(document).ready(function($) {
    <?php if ( ! empty( $sources ) ): ?>
    if ( $.fn.DataTable && !$.fn.DataTable.isDataTable('#rs_hp_stats_table') ) {
        $('#rs_hp_stats_table').DataTable({
            "pageLength": 25,
            "order": [[ 4, "desc" ]]
        });
    }
    <?php endif; ?>
    
    $('#rs_reset_stats').on('click', function() {
        Swal.fire({
            title: '<?php echo esc_js( __( 'Reset Statistics?', 'royal-sentinel' ) ); ?>',
            text: '<?php echo esc_js( __( 'This will reset all honeypot statistics to zero. This action cannot be undone.', 'royal-sentinel' ) ); ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: '<?php echo esc_js( __( 'Yes, Reset', 'royal-sentinel' ) ); ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(ajaxurl, {
                    action: 'rs_honeypot_reset_stats',
                    _wpnonce: '<?php echo wp_create_nonce("rs_honeypot_nonce"); ?>'
                }, function(resp) {
                    if (resp.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '<?php echo esc_js( __( 'Statistics Reset!', 'royal-sentinel' ) ); ?>',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
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
