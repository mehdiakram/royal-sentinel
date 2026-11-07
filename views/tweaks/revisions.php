<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// পরিসংখ্যান সংগ্রহ
global $wpdb;
$rev_count = $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'revision'" );

// সেটিংস ভ্যারিয়েবল
$disable_rev   = isset($rs_tweaks['disable_revisions']) ? $rs_tweaks['disable_revisions'] : 0;
$limit_rev     = isset($rs_tweaks['limit_revisions']) ? $rs_tweaks['limit_revisions'] : 5;
$auto_del_days = isset($rs_tweaks['auto_del_revisions_days']) ? $rs_tweaks['auto_del_revisions_days'] : 0;
?>

<div class="rs-tweaks-section">

    <div class="rs-section-box" style="border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">Revisions Settings</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label>Global Control</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_revisions]" value="1" <?php checked(1, $disable_rev); ?>> 
                        Disable Revisions Completely
                    </label>
                    <p class="description">Prevents WordPress from saving any future revisions.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Limit Revisions</label></th>
                <td>
                    Keep max 
                    <input type="number" name="rs_tweaks[limit_revisions]" value="<?php echo esc_attr($limit_rev); ?>" class="small-text" style="text-align:center;"> 
                    revisions per post.
                    <p class="description">Older revisions will be deleted automatically as new ones arrive.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Auto-Remove Old</label></th>
                <td>
                    Delete revisions older than 
                    <input type="number" name="rs_tweaks[auto_del_revisions_days]" value="<?php echo esc_attr($auto_del_days); ?>" class="small-text" style="text-align:center;"> 
                    days.
                    <p class="description">Set to <strong>0</strong> to disable. Useful for keeping the DB lean.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #9b59b6;">
        <h4 class="rs-section-title" style="color: #9b59b6;">Cleanup & Optimization</h4>
        
        <div style="display: flex; gap: 20px; align-items: center; margin-top: 10px;">
            
            <div class="rs-stat-box" style="border: 1px solid #8e44ad; background: #f4ecf7; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
                <h3 style="margin: 0; color: #8e44ad; font-size: 28px;"><?php echo number_format_i18n($rev_count); ?></h3>
                <span style="display: block; font-size: 12px; font-weight: 600; color: #777; margin: 10px 0; letter-spacing: 0.5px;">TOTAL REVISIONS</span>
                
                <?php if($rev_count > 0): ?>
                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="purge_revisions" 
                            data-title="Delete All Revisions?" 
                            style="color: #8e44ad; border-color: #8e44ad; width: 100%;">
                        Purge All Revisions
                    </button>
                <?php else: ?>
                    <button type="button" class="button button-secondary" disabled style="width: 100%;">Clean</button>
                <?php endif; ?>
            </div>

            <div class="rs-stat-box" style="border: 1px solid #2980b9; background: #ebf5fb; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
                <h3 style="margin: 0; color: #2980b9; font-size: 28px;"><span class="dashicons dashicons-database" style="font-size:28px; width:28px; height:28px; vertical-align:middle;"></span></h3>
                <span style="display: block; font-size: 12px; font-weight: 600; color: #777; margin: 10px 0; letter-spacing: 0.5px;">TABLE OPTIMIZATION</span>
                
                <button type="button" class="button button-secondary rs-action-btn" 
                        data-action="optimize_revisions_db" 
                        data-title="Optimize Revisions Table?" 
                        style="color: #2980b9; border-color: #2980b9; width: 100%;">
                    Optimize Database
                </button>
            </div>

        </div>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
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
            text: "This process may take a moment.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Proceed!'
        }).then((result) => {
            if (result.isConfirmed) {
                
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    showConfirmButton: false, 
                    didOpen: () => { Swal.showLoading(); }
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'rs_tweaks_action',
                        run_action: action,
                        _wpnonce: '<?php echo wp_create_nonce("rs_tweaks_nonce"); ?>'
                    },
                    success: function(resp) {
                        if(resp.success) {
                            Swal.fire({icon:'success', title:'Done!', text:resp.data, showConfirmButton:false, timer:1500}).then(() => location.reload());
                        } else {
                            Swal.fire('Error', resp.data || 'Unknown error occurred.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                        Swal.fire('Failed!', 'Server Error: ' + error, 'error');
                    }
                });
            }
        });
    });
});
</script>