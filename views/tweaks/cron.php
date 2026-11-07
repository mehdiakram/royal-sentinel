<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// Cron Stats Calculation
$crons = _get_cron_array();
$cron_count = 0;
if ( is_array( $crons ) ) {
    foreach ( $crons as $cron ) {
        $cron_count += count( $cron );
    }
}

// Settings Variable
$disable_cron = isset($rs_tweaks['disable_wp_cron']) ? $rs_tweaks['disable_wp_cron'] : 0;
?>

<div class="rs-tweaks-section">

    <div class="rs-section-box" style="border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">Cron Configuration</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label>WP-Cron Status</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_wp_cron]" value="1" <?php checked(1, $disable_cron); ?>> 
                        Disable WP-Cron
                    </label>
                    <p class="description">Stops WordPress from checking for scheduled tasks on every page load. Recommended if you have set up a real system cron job.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #2980b9;">
        <h4 class="rs-section-title" style="color: #2980b9;">Events Manager</h4>
        
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 10px; margin-bottom: 20px;">
            
            <div class="rs-stat-box" style="border: 1px solid #2980b9; background: #ebf5fb; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
                <h3 style="margin: 0; color: #2980b9; font-size: 32px;"><?php echo $cron_count; ?></h3>
                <span style="display: block; font-size: 13px; font-weight: 600; color: #777; margin: 10px 0; letter-spacing: 0.5px;">SCHEDULED EVENTS</span>
                
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="clear_cron" 
                            data-title="Clear All Schedules?" 
                            style="border-color:#d63638; color:#d63638;">
                        Clear All
                    </button>

                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="delete_orphaned_crons" 
                            data-title="Delete Orphaned Hooks?"
                            style="border-color:#e67e22; color:#e67e22;">
                        Remove Orphaned
                    </button>
                </div>
            </div>

            <div style="flex: 1; padding: 10px; color: #646970; font-size: 14px; line-height: 1.6;">
                <p style="margin-top:0;"><strong>About Actions:</strong></p>
                <ul style="list-style: disc; margin-left: 20px; margin-bottom: 0;">
                    <li><strong>Clear All:</strong> Removes all tasks. WP and plugins will recreate necessary tasks on the next page load.</li>
                    <li><strong>Remove Orphaned:</strong> Attempts to delete tasks that have no active plugin handler attached.</li>
                </ul>
            </div>

        </div>

        <div style="border-top: 1px solid #eee; padding-top: 15px;">
            <details>
                <summary style="cursor:pointer; color:#2271b1; font-weight:600; outline:none;">
                    View Event List <span class="dashicons dashicons-arrow-down-alt2" style="font-size:14px; vertical-align:middle;"></span>
                </summary>
                
                <div style="margin-top:15px; max-height:300px; overflow-y:auto; background:#f9f9f9; padding:15px; border:1px solid #eee; border-radius:4px;">
                    <?php if ( $cron_count > 0 && is_array($crons) ): ?>
                        <ul style="margin:0; list-style:none;">
                            <?php foreach ( $crons as $timestamp => $cronhooks ): ?>
                                <?php foreach ( $cronhooks as $hook => $events ): ?>
                                    <?php foreach ( $events as $event ): ?>
                                        <li style="padding:8px 0; border-bottom:1px solid #eee; font-size:13px; display:flex; justify-content:space-between;">
                                            <span>
                                                <strong><?php echo esc_html($hook); ?></strong> 
                                                <span style="color:#888;">(<?php echo date('Y-m-d H:i:s', $timestamp); ?>)</span>
                                            </span>
                                            
                                            <?php if ( ! has_action($hook) ): ?>
                                                <span style="color:#d63638; font-size:11px; font-weight:bold; background:#fff0f0; padding:2px 6px; border-radius:3px;">Orphaned</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color:#666;">No scheduled events found.</p>
                    <?php endif; ?>
                </div>
            </details>
        </div>

    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 220px; font-weight: 600; color: #3c434a; }
    .rs-checkbox-label { font-weight: 500; color: #333; }
</style>

<script>
jQuery(document).ready(function($) {
    $('.rs-action-btn').click(function(e) {
        e.preventDefault();
        var btn = $(this);
        var action = btn.data('action');
        var title  = btn.data('title');

        Swal.fire({
            title: title,
            text: "This will modify your scheduled tasks.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2271b1',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Proceed!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({title: 'Processing...', showConfirmButton: false, didOpen: () => { Swal.showLoading(); }});
                
                $.ajax({
                    url: ajaxurl, type: 'POST', dataType: 'json',
                    data: { 
                        action: 'rs_tweaks_action', 
                        run_action: action, 
                        _wpnonce: '<?php echo wp_create_nonce("rs_tweaks_nonce"); ?>' 
                    },
                    success: function(resp) {
                        if(resp.success) { Swal.fire({icon:'success', title:'Done!', text:resp.data, showConfirmButton:false, timer:1500}).then(() => location.reload()); } 
                        else { Swal.fire('Error', resp.data, 'error'); }
                    },
                    error: function(xhr, status, error) { Swal.fire('Failed!', 'Error: ' + error, 'error'); }
                });
            }
        });
    });
});
</script>