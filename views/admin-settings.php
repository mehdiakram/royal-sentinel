<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Force Load SweetAlert2 Assets (Local)
echo '<script src="' . ROYALSENTINEL_PLUGIN_URL . 'lib/sweetalert2/sweetalert2.all.min.js"></script>';
echo '<link rel="stylesheet" href="' . ROYALSENTINEL_PLUGIN_URL . 'lib/sweetalert2/sweetalert2.min.css">';

// ১. বর্তমান ট্যাব নির্ধারণ
$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'cloudflare';

// ২. সেভ লজিক (SAVE LOGIC)
if ( isset( $_POST['rs_save_settings'] ) ) {
    
    // Cloudflare Tab Settings Save
    if ( $active_tab == 'cloudflare' ) {
        
        // --- [FIXED] এনাবল সুইচ লজিক ---
        // আগে চেক করছি ইনপুট আছে কিনা, থাকলে সেটার ভ্যালু নিচ্ছি (0 বা 1)
        $enable_cf = isset($_POST['rs_cf_enable']) ? intval($_POST['rs_cf_enable']) : 0;
        update_option('rs_cf_enable', $enable_cf);

        // অন্যান্য সেটিংস
        if ( isset($_POST['rs_cf_auth_mode']) ) update_option('rs_cf_auth_mode', sanitize_text_field($_POST['rs_cf_auth_mode']));
        if ( isset($_POST['rs_cf_email']) ) update_option('rs_cf_email', sanitize_text_field($_POST['rs_cf_email']));
        if ( isset($_POST['rs_cf_api_key']) ) update_option('rs_cf_api_key', sanitize_text_field($_POST['rs_cf_api_key']));
        if ( isset($_POST['rs_cf_acc_id']) ) update_option('rs_cf_acc_id', sanitize_text_field($_POST['rs_cf_acc_id']));
        
        // IP List ID
        if ( isset($_POST['rs_cf_list_id']) ) update_option('rs_cf_list_id', sanitize_text_field($_POST['rs_cf_list_id']));

        // ক্যাশ ক্লিয়ার
        delete_transient('rs_cached_zones');
        delete_transient('rs_cached_ips');
    }

    // ✅ SweetAlert Popup Trigger
    echo "
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Settings Saved!',
                text: 'Configuration updated successfully.',
                showConfirmButton: false, 
                timer: 1500
            });
        });
    </script>
    ";
}

// ৩. ডাটা লোড করা
$is_cf_enabled = get_option('rs_cf_enable', 0);
$auth_mode     = get_option('rs_cf_auth_mode', 'global');
$cf_email      = get_option('rs_cf_email');
$cf_key        = get_option('rs_cf_api_key');
$cf_acc_id     = get_option('rs_cf_acc_id');
$cf_list_id    = get_option('rs_cf_list_id');
?>

<div class="wrap">
    <div class="rs-page-header dark-gradient">
        <div class="rs-header-content">
            <h1 class="rs-page-title">
                <div class="rs-process-icon">⚙️</div>
                Royal Sentinel Settings
            </h1>
            <p class="rs-page-subtitle">Configure cloudflare integration and basic settings.</p>
        </div>
    </div>

    <h2 class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="?page=rs-settings&tab=cloudflare" class="nav-tab <?php echo $active_tab == 'cloudflare' ? 'nav-tab-active' : ''; ?>">
            <?php _e('☁️ Cloudflare API', 'royal-sentinel'); ?>
        </a>
        <a href="?page=rs-settings&tab=backup" class="nav-tab <?php echo $active_tab == 'backup' ? 'nav-tab-active' : ''; ?>">
            📤 Export / Import
        </a>
        <a href="?page=rs-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('ℹ️ About Us', 'royal-sentinel'); ?>
        </a>
    </h2>

    <div class="rs-settings-content" style="background: #fff; padding: 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,.04); max-width: 800px;">
        
        <form method="post" action="">
            
            <?php if ( $active_tab == 'cloudflare' ): ?>
                
                <div style="padding: 20px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; background: #fafafa;">
                    <div>
                        <h3 style="margin: 0; color: #333;"><?php _e('Cloudflare Integration', 'royal-sentinel'); ?></h3>
                        <p style="margin: 5px 0 0; color: #666;"><?php _e('Enable API connection to manage firewall rules.', 'royal-sentinel'); ?></p>
                    </div>
                    
                    <label class="rs-switch">
                        <input type="checkbox" id="rs_cf_toggle" <?php checked($is_cf_enabled, 1); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                    <input type="hidden" name="rs_cf_enable" id="rs_cf_enable_val" value="<?php echo $is_cf_enabled; ?>">
                </div>

                <div id="rs_cf_form_wrapper" style="padding: 20px; <?php echo ($is_cf_enabled) ? '' : 'display:none;'; ?>">
                    
                    <table class="form-table">
                        
                        <tr>
                            <th scope="row"><label for="rs_cf_auth_mode"><?php _e('Connection Mode', 'royal-sentinel'); ?></label></th>
                            <td>
                                <select name="rs_cf_auth_mode" id="rs_cf_auth_mode" style="min-width: 250px;">
                                    <option value="global" <?php selected($auth_mode, 'global'); ?>><?php _e('Global API Key (Master Mode)', 'royal-sentinel'); ?></option>
                                    <option value="single" <?php selected($auth_mode, 'single'); ?>><?php _e('API Token (Single Site Mode)', 'royal-sentinel'); ?></option>
                                </select>

                                <p class="description">
                                    <?php _e('<strong>Global:</strong> View ALL sites (requires Email).', 'royal-sentinel'); ?> <br>
                                    <?php _e('<strong>Single:</strong> View ONLY this site (Email not required).', 'royal-sentinel'); ?>
                                </p>
                                
                                <!-- Single Site Instructions -->
                                <div id="rs_single_token_info" style="margin-top:10px; padding:10px; background:#fff; border-left:4px solid #2271b1; display:none;">
                                    <strong>Required Token Permissions:</strong>
                                    <ul style="list-style:disc; margin-left:20px; font-size:12px; color:#555;">
                                        <?php _e('<li>Zone - <strong>Firewall Services</strong> - Edit</li>', 'royal-sentinel'); ?>
                                        <?php _e('<li>Zone - <strong>DNS</strong> - Edit</li>', 'royal-sentinel'); ?>
                                        <?php _e('<li>Zone - <strong>Zone Settings</strong> - Edit</li>', 'royal-sentinel'); ?>
                                        <?php _e('<li>Zone - <strong>Zone</strong> - Read</li>', 'royal-sentinel'); ?>
                                        <?php _e('<li>Zone - <strong>Analytics</strong> - Read</li>', 'royal-sentinel'); ?>
                                        <?php _e('<li>Zone - <strong>Cache Purge</strong> - Purge</li>', 'royal-sentinel'); ?>
                                    </ul>
                                    <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" style="text-decoration:none;">
                                        <?php _e('🔗 Create Token', 'royal-sentinel'); ?> <span class="dashicons dashicons-external"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <tr id="rs_email_row">
                            <th scope="row"><label for="rs_cf_email"><?php _e('Cloudflare Email', 'royal-sentinel'); ?></label></th>
                            <td>
                                <input name="rs_cf_email" type="text" id="rs_cf_email" value="<?php echo esc_attr($cf_email); ?>" class="regular-text" placeholder="admin@example.com">
                                <p class="description"><?php _e('Required for Global API Key mode.', 'royal-sentinel'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="rs_cf_api_key"><?php _e('API Key / Token', 'royal-sentinel'); ?></label></th>
                            <td>
                                <input name="rs_cf_api_key" type="password" id="rs_cf_api_key" value="<?php echo esc_attr($cf_key); ?>" class="regular-text">
                                <p class="description">
                                    <?php _e('Enter <b>Global API Key</b> or <b>Zone API Token</b>.', 'royal-sentinel'); ?> <br>
                                    <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank"><?php _e('Get your API Key/Token here', 'royal-sentinel'); ?></a>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="rs_cf_acc_id"><?php _e('Account ID', 'royal-sentinel'); ?></label></th>
                            <td>
                                <input name="rs_cf_acc_id" type="text" id="rs_cf_acc_id" value="<?php echo esc_attr($cf_acc_id); ?>" class="regular-text">
                            </td>
                        </tr>

                        <tr id="rs_iplist_row">
                            <th scope="row"><label for="rs_cf_list_id"><?php _e('IP List ID (UUID)', 'royal-sentinel'); ?></label></th>
                            <td>
                                <input name="rs_cf_list_id" type="text" id="rs_cf_list_id" value="<?php echo esc_attr($cf_list_id); ?>" class="regular-text" placeholder="e.g. 84d2a1b0-...">
                                <p class="description">
                                    <?php _e('The UUID of the IP List created in Cloudflare (Manage Account > Configurations > Lists).', 'royal-sentinel'); ?><br>
                                    <a href="https://dash.cloudflare.com/configurations/lists" target="_blank"><?php _e('Find/Create IP List', 'royal-sentinel'); ?></a>
                                </p>
                            </td>
                        </tr>

                    </table>

                    <div style="margin-top: 15px;">
                        <button type="button" id="rs_test_connection" class="button button-secondary">
                            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> <?php _e('Test Connection', 'royal-sentinel'); ?>
                        </button>
                        <span id="rs_test_msg" style="font-weight: 600; margin-left: 10px;"></span>
                    </div>

                </div>

                <div id="rs_disabled_msg" style="padding: 40px; text-align: center; color: #777; <?php echo (!$is_cf_enabled) ? '' : 'display:none;'; ?>">
                    <span class="dashicons dashicons-lock" style="font-size: 40px; height: 40px; width: 40px; margin-bottom: 10px;"></span>
                    <p style="font-size: 16px; margin: 0;"><?php _e('Integration is currently <strong>Disabled</strong>.', 'royal-sentinel'); ?></p>
                </div>

                <div style="padding: 20px; background: #fcfcfc; border-top: 1px solid #eee; text-align: right;">
                     <input type="submit" name="rs_save_settings" id="submit" class="button button-primary" value="<?php _e('Save Changes', 'royal-sentinel'); ?>">
                </div>

            <?php elseif ( $active_tab == 'backup' ): ?>
                <div style="padding: 30px;">
                    <?php
                    // Export handler
                    if ( isset($_POST['rs_export_settings']) && check_admin_referer('rs_backup_action') ) {
                        $data = [
                            'rs_security_settings'     => get_option('rs_security_settings', []),
                            'rs_tweaks_settings'       => get_option('rs_tweaks_settings', []),
                            'rs_honeypot_settings'     => get_option('rs_honeypot_settings', []),
                            'rs_honeypot_integrations' => get_option('rs_honeypot_integrations', []),
                            'rs_cf_enable'             => get_option('rs_cf_enable', 0),
                            'rs_cf_auth_mode'          => get_option('rs_cf_auth_mode', 'global'),
                            'exported_at'              => current_time('mysql'),
                            'site_url'                 => get_site_url(),
                            'rs_version'               => ROYALSENTINEL_VERSION,
                        ];
                        $filename = 'royal-sentinel-' . date('Y-m-d') . '.json';
                        header('Content-Type: application/json');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        echo json_encode($data, JSON_PRETTY_PRINT);
                        exit;
                    }
                    // Import handler
                    $import_msg = '';
                    if ( isset($_POST['rs_import_settings']) && check_admin_referer('rs_backup_action') ) {
                        if ( ! empty($_FILES['rs_import_file']['tmp_name']) ) {
                            $content  = file_get_contents( $_FILES['rs_import_file']['tmp_name'] );
                            $imported = json_decode($content, true);
                            if ( is_array($imported) ) {
                                foreach (['rs_security_settings','rs_tweaks_settings','rs_honeypot_settings','rs_honeypot_integrations'] as $key) {
                                    if ( isset($imported[$key]) ) update_option($key, $imported[$key]);
                                }
                                $import_msg = '<div style="background:#edfaef;border-left:4px solid #27ae60;padding:12px 15px;border-radius:5px;margin-bottom:20px;">✅ Settings imported successfully from <strong>' . esc_html($imported['site_url'] ?? 'unknown') . '</strong></div>';
                            } else {
                                $import_msg = '<div style="background:#fef2f2;border-left:4px solid #e74c3c;padding:12px 15px;border-radius:5px;margin-bottom:20px;">❌ Invalid file. Please upload a valid Royal Sentinel JSON export.</div>';
                            }
                        }
                    }
                    echo $import_msg;
                    ?>

                    <div style="display:flex; gap:30px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:280px; background:#f8f9fa; padding:25px; border-radius:8px; border-left:4px solid #2271b1;">
                            <h3 style="margin-top:0; color:#2271b1;">📤 Export Settings</h3>
                            <p style="color:#666;">Download all your Royal Sentinel settings as a JSON file. Use this to back up or transfer settings to another site.</p>
                            <form method="post">
                                <?php wp_nonce_field('rs_backup_action'); ?>
                                <button type="submit" name="rs_export_settings" class="button button-primary button-large">
                                    <span class="dashicons dashicons-download" style="vertical-align:middle;"></span> Download Settings JSON
                                </button>
                            </form>
                        </div>

                        <div style="flex:1; min-width:280px; background:#f8f9fa; padding:25px; border-radius:8px; border-left:4px solid #27ae60;">
                            <h3 style="margin-top:0; color:#27ae60;">📥 Import Settings</h3>
                            <p style="color:#666;">Upload a previously exported JSON file to restore or migrate your settings.</p>
                            <form method="post" enctype="multipart/form-data">
                                <?php wp_nonce_field('rs_backup_action'); ?>
                                <input type="file" name="rs_import_file" accept=".json" style="display:block; margin-bottom:12px;">
                                <button type="submit" name="rs_import_settings" class="button button-secondary button-large"
                                    onclick="return confirm('This will overwrite your current settings. Continue?')">
                                    <span class="dashicons dashicons-upload" style="vertical-align:middle;"></span> Import Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            <?php elseif ( $active_tab == 'general' ): ?>
                <div style="padding: 30px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <h2 style="color: #2271b1; margin: 0;">🛡️ Royal Sentinel</h2>
                        <p style="color: #666; margin: 10px 0 0;">Advanced WordPress Security Plugin</p>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 10px; color: #fff; margin-bottom: 25px;">
                        <h3 style="margin: 0 0 15px; color: #fff;">👨‍💻 <?php _e('Developer', 'royal-sentinel'); ?></h3>
                        <p style="font-size: 20px; font-weight: 600; margin: 0;">Royal Technologies</p>
                        <p style="margin: 5px 0 0; opacity: 0.9;"><?php _e('Professional Web Development & Security Solutions', 'royal-sentinel'); ?></p>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #2271b1;">
                            <h4 style="margin: 0 0 10px; color: #2271b1;">🌐 <?php _e('Website', 'royal-sentinel'); ?></h4>
                            <a href="https://www.royaltechbd.com/" target="_blank" style="color: #333; text-decoration: none; font-weight: 600;">
                                www.royaltechbd.com
                                <span class="dashicons dashicons-external" style="font-size: 14px;"></span>
                            </a>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #e74c3c;">
                            <h4 style="margin: 0 0 10px; color: #e74c3c;">📧 <?php _e('Email', 'royal-sentinel'); ?></h4>
                            <a href="mailto:info@royaltechbd.com" style="color: #333; text-decoration: none; font-weight: 600;">
                                info@royaltechbd.com
                            </a>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #27ae60;">
                            <h4 style="margin: 0 0 10px; color: #27ae60;">📱 <?php _e('Mobile', 'royal-sentinel'); ?></h4>
                            <a href="tel:+8801552333272" style="color: #333; text-decoration: none; font-weight: 600;">
                                +880 1552-333272
                            </a>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #f39c12;">
                            <h4 style="margin: 0 0 10px; color: #f39c12;">📍 <?php _e('Address', 'royal-sentinel'); ?></h4>
                            <p style="margin: 0; color: #333; font-weight: 500;">
                                D - 408, Housing Estate<br>
                                Kushtia - 7000, Bangladesh
                            </p>
                        </div>
                        
                    </div>
                    
                    <div style="margin-top: 30px; text-align: center; padding: 20px; background: #fafafa; border-radius: 8px;">
                        <p style="margin: 0; color: #888; font-size: 13px;">
                            <?php printf(__('Royal Sentinel v%s | © %s Royal Technologies', 'royal-sentinel'), ROYALSENTINEL_VERSION, date('Y')); ?>
                        </p>
                    </div>

                    <!-- License Reset / Deactivate (Always Visible for Debugging) -->
                    <div style="margin-top: 30px; padding: 20px; border: 1px solid #ffb9b9; background: #fff5f5; border-radius: 8px; text-align: center;">
                        <h3 style="color: #d63638; margin-top: 0;"><?php _e('Danger Zone: License Reset', 'royal-sentinel'); ?></h3>
                        <p><?php _e('Use this to force-clear your license key if you are facing activation issues.', 'royal-sentinel'); ?></p>
                            
                        <!-- Deactivate Button (Direct Link via Admin Post with SweetAlert) -->
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=royalsentinel_deactivate_license' ), 'royalsentinel_license_deactivation_nonce' ); ?>" 
                               id="rs-deactivate-link"
                               class="button button-link-delete" 
                               style="color: #d63638; text-decoration: underline;">
                                <?php _e('Deactivate License', 'royal-sentinel'); ?>
                            </a>
                        </div>

                </div>
            <?php endif; ?>

        </form>
    </div>
</div>

<style>
.rs-switch { position: relative; display: inline-block; width: 50px; height: 24px; }
.rs-switch input { opacity: 0; width: 0; height: 0; }
.rs-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
.rs-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .rs-slider { background-color: #2271b1; }
input:checked + .rs-slider:before { transform: translateX(26px); }
</style>
<script>
jQuery(document).ready(function($) {
    
    // 4. Deactivate Logic (Simplified)
    $('#rs-deactivate-link').on('click', function(e) {
        e.preventDefault();
        var link = $(this).attr('href');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '<?php echo esc_js(__('Are you sure?', 'royal-sentinel')); ?>',
                text: "<?php echo esc_js(__('You will need to re-enter your license key later.', 'royal-sentinel')); ?>",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '<?php echo esc_js(__('Yes, deactivate it!', 'royal-sentinel')); ?>',
                cancelButtonText: '<?php echo esc_js(__('Cancel', 'royal-sentinel')); ?>'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = link;
                }
            });
        } else {
            // Fallback (Should not happen if script loads)
            if(confirm("<?php echo esc_js(__('Are you sure you want to deactivate the license?', 'royal-sentinel')); ?>")) {
                window.location.href = link;
            }
        }
    });

    // 1. Toggle Switch Logic
    $('#rs_cf_toggle').on('change', function() {
        if($(this).is(':checked')) {
            $('#rs_cf_form_wrapper').slideDown();
            $('#rs_disabled_msg').slideUp();
            $('#rs_cf_enable_val').val('1'); // Value 1
        } else {
            $('#rs_cf_form_wrapper').slideUp();
            $('#rs_disabled_msg').slideDown();
            $('#rs_cf_enable_val').val('0'); // Value 0
        }
    });

    // 2. Auth Mode Logic (Show/Hide Email)
    function checkAuthMode() {
        if ($('#rs_cf_auth_mode').val() === 'single') {
            $('#rs_email_row').hide();
            $('#rs_iplist_row').hide();
            $('#rs_single_token_info').slideDown();
        } else {
            $('#rs_email_row').show();
            $('#rs_iplist_row').show();
            $('#rs_single_token_info').slideUp();
        }
    }
    $('#rs_cf_auth_mode').on('change', checkAuthMode);
    checkAuthMode(); // Run on load

    // 3. Test Connection Logic (AJAX)
    $('#rs_test_connection').on('click', function(e) {
        e.preventDefault();
        var btn = $(this);
        var msg = $('#rs_test_msg');
        
        btn.prop('disabled', true).text('Connecting...');
        msg.text('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rs_test_cf_connection',
                security: '<?php echo wp_create_nonce("rs_test_conn_nonce"); ?>',
                auth_mode: $('#rs_cf_auth_mode').val(),
                email: $('#rs_cf_email').val(),
                api_key: $('#rs_cf_api_key').val(),
                acc_id: $('#rs_cf_acc_id').val()
            },
            success: function(response) {
                if (response.success) {
                    msg.css('color', 'green').html('✅ ' + response.data);
                } else {
                    msg.css('color', '#d63638').html('❌ ' + response.data);
                }
                btn.prop('disabled', false).text('Test Connection');
            },
            error: function() {
                msg.css('color', 'red').text('❌ Server Error');
                btn.prop('disabled', false).text('Test Connection');
            }
    });
});
});
</script>
