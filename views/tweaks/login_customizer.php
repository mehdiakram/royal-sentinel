<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// মিডিয়া আপলোডার স্ক্রিপ্ট এনকিউ
if ( ! did_action( 'wp_enqueue_media' ) ) {
    wp_enqueue_media();
}

$rs_tweaks = get_option( 'rs_tweaks_settings', [] );

// সেটিংস ভ্যারিয়েবল
$login_logo         = isset($rs_tweaks['login_logo_url']) ? $rs_tweaks['login_logo_url'] : '';
$login_url          = !empty($rs_tweaks['login_header_url']) ? $rs_tweaks['login_header_url'] : home_url();
$login_bg_image     = isset($rs_tweaks['login_bg_image']) ? $rs_tweaks['login_bg_image'] : '';
$login_button_text_color = isset($rs_tweaks['login_button_text_color']) ? $rs_tweaks['login_button_text_color'] : '#ffffff';
$login_text_color   = isset($rs_tweaks['login_text_color']) ? $rs_tweaks['login_text_color'] : '#3c434a';

/**
 * Helper function to render the Solid/Gradient UI block.
 */
function rs_render_gradient_control($prefix, $label, $desc, $rs_tweaks) {
    $type   = isset($rs_tweaks[$prefix.'_type']) ? $rs_tweaks[$prefix.'_type'] : 'solid';
    $color1 = isset($rs_tweaks[$prefix.'_color1']) ? $rs_tweaks[$prefix.'_color1'] : '';
    $color2 = isset($rs_tweaks[$prefix.'_color2']) ? $rs_tweaks[$prefix.'_color2'] : '';
    $angle  = isset($rs_tweaks[$prefix.'_angle']) ? $rs_tweaks[$prefix.'_angle'] : '90deg';
    ?>
    <tr>
        <th scope="row"><label><?php echo esc_html($label); ?></label></th>
        <td>
            <div class="rs-gradient-builder" data-prefix="<?php echo esc_attr($prefix); ?>">
                <div style="margin-bottom: 10px;">
                    <select name="rs_tweaks[<?php echo esc_attr($prefix); ?>_type]" class="rs-bg-type-select">
                        <option value="solid" <?php selected($type, 'solid'); ?>>Solid Color</option>
                        <option value="gradient" <?php selected($type, 'gradient'); ?>>Gradient</option>
                    </select>
                </div>
                <div class="rs-color-pickers" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="rs-color-1-wrap">
                        <input type="text" name="rs_tweaks[<?php echo esc_attr($prefix); ?>_color1]" value="<?php echo esc_attr($color1); ?>" class="rs-color-picker">
                    </div>
                    <div class="rs-color-2-wrap" style="<?php echo ($type === 'solid') ? 'display: none;' : ''; ?>">
                        <input type="text" name="rs_tweaks[<?php echo esc_attr($prefix); ?>_color2]" value="<?php echo esc_attr($color2); ?>" class="rs-color-picker" data-default-color="#000000">
                    </div>
                    <div class="rs-angle-wrap" style="<?php echo ($type === 'solid') ? 'display: none;' : ''; ?> display: flex; align-items: center; gap: 5px;">
                        <input type="text" name="rs_tweaks[<?php echo esc_attr($prefix); ?>_angle]" value="<?php echo esc_attr($angle); ?>" class="small-text" style="width: 70px;" placeholder="90deg">
                        <span class="description">Angle (e.g. 90deg, 180deg)</span>
                    </div>
                </div>
            </div>
            <p class="description"><?php echo esc_html($desc); ?></p>
        </td>
    </tr>
    <?php
}
?>

<div class="rs-tweaks-section">

    <div class="rs-section-box" style="border-left: 4px solid #27ae60;">
        <h4 class="rs-section-title" style="color: #27ae60;">Brand & Logo</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Custom Logo</label></th>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center; max-width: 500px;">
                        <input type="text" id="rs_login_logo_url" name="rs_tweaks[login_logo_url]" value="<?php echo esc_attr($login_logo); ?>" placeholder="https://example.com/logo.png" class="regular-text" style="flex: 1;">
                        <button type="button" class="button button-secondary rs-upload-logo-btn">
                            <span class="dashicons dashicons-upload" style="vertical-align:text-bottom;"></span> Upload
                        </button>
                    </div>
                    <p class="description">Replaces the default WordPress logo on the login page.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Logo Link URL</label></th>
                <td>
                    <input type="text" name="rs_tweaks[login_header_url]" value="<?php echo esc_attr($login_url); ?>" class="regular-text" style="width:100%; max-width: 500px;">
                    <p class="description">Where the logo should link to. Defaults to your site's home URL.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #2980b9;">
        <h4 class="rs-section-title" style="color: #2980b9;">Background Styling</h4>
        <table class="form-table">
            <?php rs_render_gradient_control('login_bg', 'Page Background', 'The main background of the login page.', $rs_tweaks); ?>
            
            <tr>
                <th scope="row"><label>Background Image</label></th>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center; max-width: 500px;">
                        <input type="text" id="rs_login_bg_image" name="rs_tweaks[login_bg_image]" value="<?php echo esc_attr($login_bg_image); ?>" placeholder="https://example.com/bg.jpg" class="regular-text" style="flex: 1;">
                        <button type="button" class="button button-secondary rs-upload-bg-btn">
                            <span class="dashicons dashicons-upload" style="vertical-align:text-bottom;"></span> Upload
                        </button>
                    </div>
                    <p class="description">Set a background image. This will override the background color if provided.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #e67e22;">
        <h4 class="rs-section-title" style="color: #e67e22;">Form & Button Customization</h4>
        <table class="form-table">
            <?php rs_render_gradient_control('login_form_bg', 'Form Background', 'Background of the login form container. Supports opacity/rgba via color picker.', $rs_tweaks); ?>
            
            <tr>
                <th scope="row"><label>Text Color</label></th>
                <td>
                    <input type="text" name="rs_tweaks[login_text_color]" value="<?php echo esc_attr($login_text_color); ?>" class="regular-text rs-color-picker" style="width:100%; max-width: 500px;" placeholder="#333333">
                    <p class="description">Color of the text (labels, links, etc.) inside and below the form.</p>
                </td>
            </tr>
            
            <?php rs_render_gradient_control('login_btn_bg', 'Button Background', 'Background of the "Log In" button.', $rs_tweaks); ?>
            <?php rs_render_gradient_control('login_btn_hover', 'Button Hover Background', 'Background when hovering over the button.', $rs_tweaks); ?>
            
            <tr>
                <th scope="row"><label>Button Text Color</label></th>
                <td>
                    <input type="text" name="rs_tweaks[login_button_text_color]" value="<?php echo esc_attr($login_button_text_color); ?>" class="regular-text rs-color-picker" style="width:100%; max-width: 500px;" placeholder="#ffffff">
                    <p class="description">Color of the text inside the "Log In" button.</p>
                </td>
            </tr>
        </table>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 220px; font-weight: 600; color: #3c434a; }
    .rs-gradient-builder { background: #f9f9f9; padding: 15px; border: 1px solid #e2e4e7; border-radius: 4px; display: inline-block; }
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize color picker
    if($.fn.wpColorPicker) {
        $('.rs-color-picker').wpColorPicker();
    }

    // Toggle gradient fields based on type select
    $('.rs-bg-type-select').on('change', function() {
        var wrap = $(this).closest('.rs-gradient-builder');
        if($(this).val() === 'gradient') {
            wrap.find('.rs-color-2-wrap').show();
            wrap.find('.rs-angle-wrap').css('display', 'flex');
        } else {
            wrap.find('.rs-color-2-wrap').hide();
            wrap.find('.rs-angle-wrap').hide();
        }
    });

    // Logo Upload
    $('.rs-upload-logo-btn').click(function(e) {
        e.preventDefault();
        var image = wp.media({ 
            title: 'Select Login Logo',
            multiple: false
        }).open()
        .on('select', function(){
            var uploaded_image = image.state().get('selection').first();
            $('#rs_login_logo_url').val(uploaded_image.toJSON().url);
        });
    });

    // BG Image Upload
    $('.rs-upload-bg-btn').click(function(e) {
        e.preventDefault();
        var image = wp.media({ 
            title: 'Select Background Image',
            multiple: false
        }).open()
        .on('select', function(){
            var uploaded_image = image.state().get('selection').first();
            $('#rs_login_bg_image').val(uploaded_image.toJSON().url);
        });
    });
});
</script>
