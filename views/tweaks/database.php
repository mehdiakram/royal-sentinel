<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// ১. পরিসংখ্যান সংগ্রহ (Counts)
// Transients
$expired_transients = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_%' AND option_value < '" . time() . "'");
$all_transients     = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '_transient_%'");

// Orphaned Data
$orphaned_meta      = $wpdb->get_var("SELECT COUNT(pm.meta_id) FROM $wpdb->postmeta pm LEFT JOIN $wpdb->posts wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL");
$orphaned_terms     = $wpdb->get_var("SELECT COUNT(tr.object_id) FROM $wpdb->term_relationships tr LEFT JOIN $wpdb->posts wp ON wp.ID = tr.object_id WHERE wp.ID IS NULL");
$orphaned_comments  = $wpdb->get_var("SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_post_ID > 0 AND comment_post_ID NOT IN (SELECT ID FROM $wpdb->posts)");

// Database Overhead (Size)
$tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
$overhead = 0;
foreach ($tables as $table) {
    $overhead += $table['Data_free'];
}
$overhead_size = size_format($overhead);
?>

<div class="rs-tweaks-section">

    <div class="rs-section-box" style="border-left: 4px solid #e67e22;">
        <h4 class="rs-section-title" style="color: #e67e22;">Data Cleanup</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label>Expired Transients</label></th>
                <td>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <button type="button" class="button button-secondary rs-action-btn" 
                                data-action="delete_expired_transients" 
                                data-title="Clean Expired Transients?" 
                                <?php disabled($expired_transients, 0); ?>>
                            Clean
                        </button>
                        <span class="description">Found: <strong><?php echo $expired_transients; ?></strong> (Safe to remove)</span>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>All Transients</label></th>
                <td>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <button type="button" class="button button-secondary rs-action-btn" 
                                data-action="delete_all_transients" 
                                data-title="Delete All Transients?" 
                                <?php disabled($all_transients, 0); ?>>
                            Clean All
                        </button>
                        <span class="description">Found: <strong><?php echo $all_transients; ?></strong> (Forces cache refresh)</span>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row"><label>Orphaned Post Meta</label></th>
                <td>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <button type="button" class="button button-secondary rs-action-btn" 
                                data-action="remove_orphaned_postmeta" 
                                data-title="Remove Orphaned Meta?" 
                                <?php disabled($orphaned_meta, 0); ?>>
                            Remove
                        </button>
                        <span class="description">Found: <strong><?php echo $orphaned_meta; ?></strong> (Leftover metadata)</span>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Orphaned Terms</label></th>
                <td>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <button type="button" class="button button-secondary rs-action-btn" 
                                data-action="remove_orphaned_term_relationships" 
                                data-title="Remove Orphaned Terms?" 
                                <?php disabled($orphaned_terms, 0); ?>>
                            Remove
                        </button>
                        <span class="description">Found: <strong><?php echo $orphaned_terms; ?></strong> (Unused relationships)</span>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Orphaned Comments</label></th>
                <td>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <button type="button" class="button button-secondary rs-action-btn" 
                                data-action="remove_orphaned_comments" 
                                data-title="Remove Orphaned Comments?" 
                                <?php disabled($orphaned_comments, 0); ?>>
                            Remove
                        </button>
                        <span class="description">Found: <strong><?php echo $orphaned_comments; ?></strong> (Comments on deleted posts)</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #27ae60;">
        <h4 class="rs-section-title" style="color: #27ae60;">Database Optimization</h4>
        
        <div style="display: flex; gap: 20px; align-items: center; margin-top: 10px;">
            
            <div class="rs-stat-box" style="border: 1px solid #27ae60; background: #eafaf1; padding: 20px; border-radius: 5px; width: 300px; text-align: center;">
                <h3 style="margin: 0; color: #27ae60; font-size: 32px;"><?php echo $overhead_size; ?></h3>
                <span style="display: block; font-size: 13px; font-weight: 600; color: #777; margin: 10px 0; letter-spacing: 0.5px;">DATA OVERHEAD</span>
                
                <button type="button" class="button button-secondary rs-action-btn" 
                        data-action="optimize_db" 
                        data-title="Optimize All Tables?" 
                        style="color: #27ae60; border-color: #27ae60; width: 100%;">
                    Optimize Tables
                </button>
            </div>

            <div style="flex: 1; padding: 10px; color: #646970; font-size: 14px; line-height: 1.6;">
                <p><strong>About Overhead:</strong></p>
                <p>Database overhead is temporary disk space used by the database that can be reclaimed. Optimizing tables reorganizes data storage and reclaims this space, potentially improving I/O performance.</p>
            </div>

        </div>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 220px; font-weight: 600; color: #3c434a; }
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
            text: "This process may take a few seconds.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#27ae60',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Start!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({title: 'Processing...', text: 'Please wait...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); }});
                
                $.ajax({
                    url: ajaxurl, type: 'POST', dataType: 'json',
                    data: { action: 'rs_tweaks_action', run_action: action, _wpnonce: '<?php echo wp_create_nonce("rs_tweaks_nonce"); ?>' },
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