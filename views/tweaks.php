<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$success_script = '';

// 1. Save Settings Logic
if ( isset( $_POST['rs_save_tweaks'] ) ) {
    // সিকিউরিটি চেক (ঐচ্ছিক)
    // check_admin_referer('rs_tweaks_save');

    $current_settings = get_option('rs_tweaks_settings', []);
    $new_input = isset($_POST['rs_tweaks']) ? $_POST['rs_tweaks'] : [];
    
    // Checkboxes Fix: Explicitly set 0 for unchecked fields
    // সমস্ত বুলিয়ান টুগলগুলি (checkbox) এখানে সেট করা হয়েছে।
    $all_bool_fields = [
        // Performance.php
        'disable_emojis', 'disable_embeds', 'disable_oembed_discovery', 'disable_rest_api_public', 'disable_rss', 
        // Admin.php
        'disable_admin_bar', 'hide_welcome', 'hide_dash_widgets', 'hide_footer_credit', 'disable_email_nag', 'disable_update_nags', 'hide_non_admin_menus',
        // Media.php
        'disable_scaled_images', 'disable_extra_sizes',
        // Comments.php
        'disable_comments', 'disable_post_comments', 'disable_page_comments', 'disable_pingbacks', 'remove_author_url',
        // Revisions.php
        'disable_revisions',
        // Cron.php
        'disable_wp_cron'
    ];
    
    foreach ($all_bool_fields as $key) {
        // যদি $_POST এ না থাকে, তবে 0 সেট করো
        $new_settings[$key] = isset($new_input[$key]) ? 1 : 0;
    }

    // Scalar fields (Text inputs, Selects, etc.)
    $scalar_fields = [
        'heartbeat_freq', 'limit_revisions', 'auto_del_revisions_days', 'big_image_threshold',
        'close_comments_days', 'comment_max_length', 'login_logo_url', 'login_header_url',
        'login_bg_image', 'login_text_color', 'login_button_text_color',
        // Gradient UI fields
        'login_bg_type', 'login_bg_color1', 'login_bg_color2', 'login_bg_angle',
        'login_form_bg_type', 'login_form_bg_color1', 'login_form_bg_color2', 'login_form_bg_angle',
        'login_btn_bg_type', 'login_btn_bg_color1', 'login_btn_bg_color2', 'login_btn_bg_angle',
        'login_btn_hover_type', 'login_btn_hover_color1', 'login_btn_hover_color2', 'login_btn_hover_angle'
    ];
    
    // Merge only the scalar fields which are passed in $_POST['rs_tweaks']
    foreach ($scalar_fields as $key) {
        if (isset($new_input[$key])) {
            $new_settings[$key] = sanitize_text_field($new_input[$key]);
        }
    }
    
    // ডাটাবেসে সেভ
    update_option( 'rs_tweaks_settings', $new_settings );
    $rs_tweaks = $new_settings;

    // সফল পপআপ স্ক্রিপ্ট
    $success_script = "
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Settings Saved!',
                text: 'Tweaks updated successfully.',
                showConfirmButton: false,
                timer: 1500
            });
        });
    </script>";
}

// সেটিংস লোড
$rs_tweaks = get_option( 'rs_tweaks_settings', [] );
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'comments';

$tabs = [
    'comments'    => 'Comments',
    'posts'       => 'Posts',
    'revisions'   => 'Revisions',
    'media'       => 'Media',
    'database'    => 'Database',
    'admin'       => 'Admin',
    'performance' => 'Performance',
    'cron'        => 'Cron',
    'misc'        => 'Misc Tools',
    'login_customizer' => 'Login Customizer',
];
?>

<div class="wrap">
    <div class="rs-page-header purple-gradient">
        <div class="rs-header-content">
            <h1 class="rs-page-title">
                <div class="rs-process-icon">🛠️</div>
                Tweaks & Optimization
            </h1>
            <p class="rs-page-subtitle">Fine-tune your WordPress installation for better performance and management.</p>
        </div>
    </div>

    <h2 class="nav-tab-wrapper">
        <?php foreach ( $tabs as $slug => $label ): ?>
            <a href="?page=rs-tweaks&tab=<?php echo $slug; ?>" class="nav-tab <?php echo $active_tab == $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-top:none;">
        
        <form method="post" id="rs-tweaks-form">
            <input type="hidden" name="rs_save_tweaks" value="1">
            
            <?php 
                $file = ROYALSENTINEL_PLUGIN_DIR . 'views/tweaks/' . $active_tab . '.php';
                if ( file_exists( $file ) ) {
                    // [Added] Hidden input to identify this as a Tweak Save action
                    echo '<input type="hidden" name="is_tweak_save" value="1">';
                    include $file;
                } else {
                    echo "<p style='color:red;'>File not found: $file</p>";
                }
            ?>

            <div style="margin-top:20px; border-top:1px solid #eee; padding-top:20px;">
                <button type="submit" id="rs-save-btn" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php echo $success_script; ?>

<script>
jQuery(document).ready(function($) {
    
    // 1. Save Button Animation
    $("#rs-tweaks-form").on("submit", function() {
        var btn = $("#rs-save-btn");
        btn.prop('disabled', true);
        btn.html('<span class="dashicons dashicons-update spin" style="vertical-align:middle;"></span> Saving...');
        
        Swal.fire({
            title: 'Saving...',
            text: 'Please wait while we update your settings.',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => { Swal.showLoading(); }
        });
    });

    // 2. AJAX Actions (Delete/Clear/Optimize)
    window.rsRunAction = function(action) {
        
        // কনফার্মেশন পপআপ
        Swal.fire({
            title: 'Are you sure?',
            text: "Do you really want to run this action?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, do it!'
        }).then((result) => {
            if (result.isConfirmed) {
                
                // লোডিং পপআপ
                Swal.fire({
                    title: 'Processing...',
                    showConfirmButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.post(ajaxurl, {
                    action: 'rs_tweaks_action',
                    run_action: action,
                    _wpnonce: '<?php echo wp_create_nonce("rs_tweaks_nonce"); ?>'
                }, function(resp) {
                    if(resp.success) {
                        Swal.fire({icon:'success', title:'Done!', text:resp.data, showConfirmButton:false, timer:1500}).then(() => location.reload());
                    } else {
                        Swal.fire('Error', resp.data, 'error');
                    }
                });
            }
        });
    };
});
</script>

<style>
/* স্পিন অ্যানিমেশন */
.spin { animation: spin 2s infinite linear; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
</style>