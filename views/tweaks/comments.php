<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// পরিসংখ্যান সংগ্রহ
$spam_count    = wp_count_comments()->spam; 
$trash_count   = wp_count_comments()->trash;
$pending_count = wp_count_comments()->moderated;
$all_comments  = wp_count_comments()->total_comments;
?>

<div class="rs-tweaks-section">
    
    <div class="rs-section-box" style="border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">General Settings</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label>Disable Comments</label></th>
                <td>
                    <label class="rs-checkbox-label" style="display:block; margin-bottom:8px;">
                        <input type="checkbox" name="rs_tweaks[disable_comments]" value="1" <?php checked(1, $rs_tweaks['disable_comments'] ?? 0); ?>> 
                        <strong>Disable all comments globally</strong>
                    </label>

                    <label class="rs-checkbox-label" style="display:block; margin-bottom:5px; margin-left:25px;">
                        <input type="checkbox" name="rs_tweaks[disable_post_comments]" value="1" <?php checked(1, $rs_tweaks['disable_post_comments'] ?? 0); ?>> 
                        Disable Post Comments
                    </label>

                    <label class="rs-checkbox-label" style="display:block; margin-bottom:5px; margin-left:25px;">
                        <input type="checkbox" name="rs_tweaks[disable_page_comments]" value="1" <?php checked(1, $rs_tweaks['disable_page_comments'] ?? 0); ?>> 
                        Disable Page Comments
                    </label>

                    <p class="description" style="margin-top:10px;">Select "Globally" to hide everything, or choose specifically for Posts/Pages.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Disable Pingbacks</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_pingbacks]" value="1" <?php checked(1, $rs_tweaks['disable_pingbacks'] ?? 0); ?>> 
                        Disable Trackbacks & Pingbacks
                    </label>
                    <p class="description">Prevents other blogs from sending notifications to your site.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Author URL</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[remove_author_url]" value="1" <?php checked(1, $rs_tweaks['remove_author_url'] ?? 0); ?>> 
                        Remove "Website" field from comment form
                    </label>
                    <p class="description">Reduces spam by removing the author URL field.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #2980b9;">
        <h4 class="rs-section-title" style="color: #2980b9;">Auto Maintenance</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Auto-Close Comments</label></th>
                <td>
                    Close comments older than 
                    <input type="number" name="rs_tweaks[close_comments_days]" value="<?php echo esc_attr($rs_tweaks['close_comments_days'] ?? 14); ?>" class="small-text" style="text-align:center;"> 
                    days.
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Limit Length</label></th>
                <td>
                    Limit comment length to 
                    <input type="number" name="rs_tweaks[comment_max_length]" value="<?php echo esc_attr($rs_tweaks['comment_max_length'] ?? 5000); ?>" class="regular-text" style="width:80px; text-align:center;"> 
                    characters.
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #c0392b;">
        <h4 class="rs-section-title" style="color: #c0392b;">Cleanup & Optimization</h4>
        
        <div style="display: flex; gap: 20px; align-items: center; margin-top: 10px;">
            
            <div class="rs-stat-box" style="border: 1px solid #e74c3c; background: #fdf2f2; padding: 15px; border-radius: 5px; flex: 1; text-align: center;">
                <h3 style="margin: 0; color: #c0392b; font-size: 24px;"><?php echo $spam_count; ?></h3>
                <span style="display: block; font-size: 12px; color: #777; margin: 10px 0;">SPAM COMMENTS</span>
                
                <?php if($spam_count > 0): ?>
                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="delete_spam_comments" 
                            data-title="Delete Spam?" 
                            style="color: #c0392b; border-color: #c0392b;">
                        Delete All Spam
                    </button>
                <?php else: ?>
                    <button type="button" class="button button-secondary" disabled>Clean</button>
                <?php endif; ?>
            </div>

            <div class="rs-stat-box" style="border: 1px solid #e67e22; background: #fdf5ec; padding: 15px; border-radius: 5px; flex: 1; text-align: center;">
                <h3 style="margin: 0; color: #d35400; font-size: 24px;"><?php echo $trash_count; ?></h3>
                <span style="display: block; font-size: 12px; color: #777; margin: 10px 0;">TRASH COMMENTS</span>
                
                <?php if($trash_count > 0): ?>
                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="delete_trash_comments" 
                            data-title="Empty Trash?" 
                            style="color: #d35400; border-color: #d35400;">
                        Empty Trash
                    </button>
                <?php else: ?>
                    <button type="button" class="button button-secondary" disabled>Clean</button>
                <?php endif; ?>
            </div>

            <div class="rs-stat-box" style="border: 1px solid #f39c12; background: #fef9e7; padding: 15px; border-radius: 5px; flex: 1; text-align: center;">
                <h3 style="margin: 0; color: #e67e22; font-size: 24px;"><?php echo $pending_count; ?></h3>
                <span style="display: block; font-size: 12px; color: #777; margin: 10px 0;">PENDING COMMENTS</span>
                
                <?php if($pending_count > 0): ?>
                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="delete_pending_comments" 
                            data-title="Delete Pending?" 
                            style="color: #e67e22; border-color: #e67e22;">
                        Delete Pending
                    </button>
                <?php else: ?>
                    <button type="button" class="button button-secondary" disabled>Clean</button>
                <?php endif; ?>
            </div>

        </div>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .rs-checkbox-label { font-weight: 500; color: #333; }
    .rs-stat-box button { width: 100%; justify-content: center; }
</style>

<script>
jQuery(document).ready(function($) {
    $('.rs-action-btn').click(function(e) {
        e.preventDefault();
        var action = $(this).data('action');
        var title  = $(this).data('title');

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
                Swal.fire({title: 'Deleting...', showConfirmButton: false, didOpen: () => { Swal.showLoading(); }});
                
                $.post(ajaxurl, {
                    action: 'rs_tweaks_action',
                    run_action: action,
                    _wpnonce: '<?php echo wp_create_nonce("rs_tweaks_nonce"); ?>'
                }, function(resp) {
                    if(resp.success) {
                        Swal.fire({icon:'success', title:'Deleted!', text:resp.data, showConfirmButton:false, timer:1500}).then(() => location.reload());
                    } else {
                        Swal.fire('Error', resp.data, 'error');
                    }
                });
            }
        });
    });
});
</script>