<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// মিডিয়া আপলোডার স্ক্রিপ্ট এনকিউ
if ( ! did_action( 'wp_enqueue_media' ) ) {
    wp_enqueue_media();
}

// সেটিংস ভ্যারিয়েবল
$disable_bar     = isset($rs_tweaks['disable_admin_bar']) ? $rs_tweaks['disable_admin_bar'] : 0;
$hide_welcome    = isset($rs_tweaks['hide_welcome']) ? $rs_tweaks['hide_welcome'] : 0;
$hide_widgets    = isset($rs_tweaks['hide_dash_widgets']) ? $rs_tweaks['hide_dash_widgets'] : 0;
$hide_footer     = isset($rs_tweaks['hide_footer_credit']) ? $rs_tweaks['hide_footer_credit'] : 0;

$disable_email   = isset($rs_tweaks['disable_email_nag']) ? $rs_tweaks['disable_email_nag'] : 0;
$disable_update  = isset($rs_tweaks['disable_update_nags']) ? $rs_tweaks['disable_update_nags'] : 0;
$hide_menus      = isset($rs_tweaks['hide_non_admin_menus']) ? $rs_tweaks['hide_non_admin_menus'] : 0;


?>

<div class="rs-tweaks-section">

    <div class="rs-section-box" style="border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">Dashboard Interface</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label>Admin Toolbar</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_admin_bar]" value="1" <?php checked(1, $disable_bar); ?>> 
                        Disable Toolbar for Non-Admins
                    </label>
                    <p class="description">Hides the top admin bar for all users except administrators.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Welcome Panel</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[hide_welcome]" value="1" <?php checked(1, $hide_welcome); ?>> 
                        Hide "Welcome to WordPress"
                    </label>
                    <p class="description">Removes the welcome panel from the dashboard.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Widgets</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[hide_dash_widgets]" value="1" <?php checked(1, $hide_widgets); ?>> 
                        Hide Dashboard Widgets
                    </label>
                    <p class="description">Removes default widgets like Activity, Quick Draft, and Events.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Footer Credit</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[hide_footer_credit]" value="1" <?php checked(1, $hide_footer); ?>> 
                        Hide "Thank you for creating with WordPress"
                    </label>
                    <p class="description">Removes the copyright text from the admin footer.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #2980b9;">
        <h4 class="rs-section-title" style="color: #2980b9;">Notifications & Nags</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Email Check</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_email_nag]" value="1" <?php checked(1, $disable_email); ?>> 
                        Disable Admin Email Verification
                    </label>
                    <p class="description">Stops WordPress from asking "Is this email correct?" periodically.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Update Nags</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_update_nags]" value="1" <?php checked(1, $disable_update); ?>> 
                        Disable Update Notifications
                    </label>
                    <p class="description">Hides core, theme, and plugin update notices for non-admins.</p>
                </td>
            </tr>
        </table>
    </div>


    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #c0392b;">
        <h4 class="rs-section-title" style="color: #c0392b;">Access Control</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Menu Restrictions</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[hide_non_admin_menus]" value="1" <?php checked(1, $hide_menus); ?>> 
                        Hide Menu Items for Non-Admins
                    </label>
                    <p class="description">Hides 'Tools' and 'Settings' menus for users who are not administrators.</p>
                </td>
            </tr>
        </table>
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
    $('.rs-upload-btn').click(function(e) {
        e.preventDefault();
        
        var image = wp.media({ 
            title: 'Select Login Logo',
            multiple: false
        }).open()
        .on('select', function(e){
            var uploaded_image = image.state().get('selection').first();
            var image_url = uploaded_image.toJSON().url;
            $('#rs_login_logo_url').val(image_url);
        });
    });
});
</script>