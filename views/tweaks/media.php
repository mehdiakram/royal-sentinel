<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// ==========================================================
// ১. সঠিক পরিসংখ্যান সংগ্রহ (Smart Check)
// ==========================================================
// আমরা শুধু post_parent = 0 গুনব না, ডিলিট করার সময় যে লজিক ব্যবহার করি
// সেই একই লজিক দিয়ে চেক করব ইমেজটি আসলে ব্যবহৃত হচ্ছে কিনা।

$attachments = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND post_parent = 0" );
$unused_count = 0;

if ( $attachments ) {
    foreach ( $attachments as $att ) {
        $id = $att->ID;
        $image_url = wp_get_attachment_url($id);

        if ( ! $image_url ) continue;

        $is_used = false;

        // ১. চেক: ফিচার্ড ইমেজ হিসেবে আছে কিনা
        if ( ! $is_used ) {
            $has_featured = $wpdb->get_var( $wpdb->prepare("SELECT meta_id FROM $wpdb->postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1", $id) );
            if ( $has_featured ) $is_used = true;
        }

        // ২. চেক: অপশন টেবিলে (Settings/Theme Mods) আছে কিনা
        if ( ! $is_used ) {
            $in_options = $wpdb->get_var( $wpdb->prepare("SELECT option_id FROM $wpdb->options WHERE option_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like($image_url) . '%') );
            if ( $in_options ) $is_used = true;
        }

        // ৩. চেক: পোস্ট কন্টেন্টের ভেতরে আছে কিনা
        if ( ! $is_used ) {
            $in_content = $wpdb->get_var( $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_content LIKE %s LIMIT 1", '%' . $wpdb->esc_like($image_url) . '%') );
            if ( $in_content ) $is_used = true;
        }

        // ৪. চেক: পোস্ট মেটা ফিল্ডে আছে কিনা
        if ( ! $is_used ) {
            $in_meta = $wpdb->get_var( $wpdb->prepare("SELECT meta_id FROM $wpdb->postmeta WHERE meta_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like($image_url) . '%') );
            if ( $in_meta ) $is_used = true;
        }

        // ৫. ফাইনাল কাউন্ট: যদি কোথাও না পাওয়া যায়, তবেই কাউন্ট বাড়াব
        if ( ! $is_used ) {
            $unused_count++;
        }
    }
}

// ২. সেটিংস ভ্যারিয়েবল
$disable_scaled = isset($rs_tweaks['disable_scaled_images']) ? $rs_tweaks['disable_scaled_images'] : 0;
$disable_sizes  = isset($rs_tweaks['disable_extra_sizes']) ? $rs_tweaks['disable_extra_sizes'] : 0;
$big_threshold  = isset($rs_tweaks['big_image_threshold']) ? $rs_tweaks['big_image_threshold'] : 2560;
?>

<div class="rs-tweaks-section">

    <div class="rs-section-box" style="border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">Image Generation</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label>Big Image Threshold</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_scaled_images]" value="1" <?php checked(1, $disable_scaled); ?>> 
                        Disable Scaled Image Creation
                    </label>
                    <p class="description">Prevents WordPress from creating <code>-scaled</code> versions of large images.</p>
                    
                    <div style="margin-top: 10px; display: <?php echo $disable_scaled ? 'none' : 'block'; ?>;" id="rs_threshold_input">
                        Max Width/Height: 
                        <input type="number" name="rs_tweaks[big_image_threshold]" value="<?php echo esc_attr($big_threshold); ?>" class="small-text" style="text-align:center;"> px
                        <p class="description">Images larger than this will be scaled down. Default: 2560.</p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Extra Image Sizes</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_extra_sizes]" value="1" <?php checked(1, $disable_sizes); ?>> 
                        Disable Automatic Image Sizes
                    </label>
                    <p class="description">Stops generating <code>medium_large</code>, <code>1536x1536</code>, and <code>2048x2048</code> sizes to save disk space.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #c0392b;">
        <h4 class="rs-section-title" style="color: #c0392b;">Cleanup & Optimization</h4>
        
        <div style="display: flex; gap: 20px; align-items: flex-start; margin-top: 10px;">
            
            <div class="rs-stat-box" style="border: 1px solid #e74c3c; background: #fdf2f2; padding: 20px; border-radius: 5px; width: 300px; text-align: center;">
                <h3 style="margin: 0; color: #c0392b; font-size: 32px;"><?php echo number_format_i18n($unused_count); ?></h3>
                <span style="display: block; font-size: 13px; font-weight: 600; color: #777; margin: 10px 0; letter-spacing: 0.5px;">UNATTACHED MEDIA</span>
                
                <?php if($unused_count > 0): ?>
                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="delete_unused_media" 
                            data-title="Delete Unattached Media?" 
                            style="color: #c0392b; border-color: #c0392b; width: 100%;">
                        Delete Unattached
                    </button>
                <?php else: ?>
                    <button type="button" class="button button-secondary" disabled style="width: 100%;">Clean</button>
                <?php endif; ?>
            </div>

            <div style="flex: 1; padding: 10px; color: #646970; font-size: 14px; line-height: 1.6;">
                <p style="margin-top:0;"><strong style="color: #d63638;">⚠️ Warning:</strong> "Unattached" means files not directly attached to a post/page (<code>post_parent = 0</code>).</p>
                <p>However, images uploaded directly to the Media Library and then used in Page Builders or Widgets might still be marked as unattached. <strong>Please backup your site</strong> before running this cleanup.</p>
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
    // Toggle Threshold Input
    $('input[name="rs_tweaks[disable_scaled_images]"]').change(function() {
        if($(this).is(':checked')) {
            $('#rs_threshold_input').slideUp();
        } else {
            $('#rs_threshold_input').slideDown();
        }
    });

    // Ajax Action
    $('.rs-action-btn').click(function(e) {
        e.preventDefault();
        var btn = $(this);
        var action = btn.data('action');
        var title  = btn.data('title');

        Swal.fire({
            title: title,
            text: "This action cannot be undone! Make sure you have a backup.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Delete!'
        }).then((result) => {
            if (result.isConfirmed) {
                
                Swal.fire({
                    title: 'Processing...',
                    text: 'This might take a while...',
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
                        Swal.fire('Failed!', 'Server Error: ' + error, 'error');
                    }
                });
            }
        });
    });
});
</script>