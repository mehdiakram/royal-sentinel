<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// পরিসংখ্যান সংগ্রহ
$count_posts = wp_count_posts();
$trash_count = isset($count_posts->trash) ? intval($count_posts->trash) : 0;

// সেটিংস ভ্যারিয়েবল
$disable_rev = isset($rs_tweaks['disable_revisions']) ? $rs_tweaks['disable_revisions'] : 0;
$limit_rev   = isset($rs_tweaks['limit_revisions']) ? $rs_tweaks['limit_revisions'] : 5;
$delete_days = isset($rs_tweaks['delete_trash_days']) ? $rs_tweaks['delete_trash_days'] : 30;
?>

<div class="rs-tweaks-section">

    <div class="rs-section-box" style="border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">Revisions Management</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Disable Revisions</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_revisions]" value="1" <?php checked(1, $disable_rev); ?>> 
                        Disable Post Revisions Globally
                    </label>
                    <p class="description">Prevents WordPress from storing revisions for any post type to save database space.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Limit Revisions</label></th>
                <td>
                    Limit to 
                    <input type="number" name="rs_tweaks[limit_revisions]" value="<?php echo esc_attr($limit_rev); ?>" class="small-text" style="text-align:center;"> 
                    latest revisions per post.
                    <p class="description">Older revisions will be automatically deleted as new ones are created.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #2980b9;">
        <h4 class="rs-section-title" style="color: #2980b9;">Auto Maintenance</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Auto-Delete Trash</label></th>
                <td>
                    Permanently delete trashed posts older than 
                    <input type="number" name="rs_tweaks[delete_trash_days]" value="<?php echo esc_attr($delete_days); ?>" class="small-text" style="text-align:center;"> 
                    days.
                    <p class="description">Keeps your database clean by removing old trash items automatically.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #c0392b;">
        <h4 class="rs-section-title" style="color: #c0392b;">Cleanup & Optimization</h4>
        
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 10px;">
            
            <div class="rs-stat-box" style="border: 1px solid #e74c3c; background: #fdf2f2; padding: 20px; border-radius: 5px; width: 300px; text-align: center;">
                <h3 style="margin: 0; color: #c0392b; font-size: 32px;"><?php echo $trash_count; ?></h3>
                <span style="display: block; font-size: 13px; font-weight: 600; color: #777; margin: 10px 0; letter-spacing: 0.5px;">TRASHED POSTS</span>
                
                <?php if($trash_count > 0): ?>
                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="empty_post_trash" 
                            data-title="Empty Trash?" 
                            style="color: #c0392b; border-color: #c0392b; width: 100%;">
                        Empty Posts Trash
                    </button>
                <?php else: ?>
                    <button type="button" class="button button-secondary" disabled style="width: 100%;">Clean</button>
                <?php endif; ?>
            </div>

            <div style="flex: 1; padding: 10px; color: #646970; font-size: 14px; line-height: 1.6;">
                <p><strong>Note:</strong> Emptying the trash will permanently delete all posts, pages, and custom post types currently in the trash bin. This action cannot be undone.</p>
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
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                
                // Show Loading
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    showConfirmButton: false, 
                    didOpen: () => { Swal.showLoading(); }
                });
                
                // Ajax Request
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
                        // Error Handling
                        console.error(xhr.responseText);
                        Swal.fire('Failed!', 'Server Error: ' + error + '. Check console for details.', 'error');
                    }
                });
            }
        });
    });
});
</script>