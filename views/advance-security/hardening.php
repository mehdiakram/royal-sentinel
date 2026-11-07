<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// Settings Variables
$hl_enabled    = isset($rs_settings['hide_login']) ? $rs_settings['hide_login'] : 0;
$login_slug    = isset($rs_settings['login_slug']) ? $rs_settings['login_slug'] : 'royalsentinel-login';
$register_slug = isset($rs_settings['register_slug']) ? $rs_settings['register_slug'] : 'royalsentinel-signup';
$do_redirect   = isset($rs_settings['enable_redirection']) ? $rs_settings['enable_redirection'] : 0;
$redirect_slug = isset($rs_settings['redirect_slug']) ? $rs_settings['redirect_slug'] : 'contact-us';

// New Login Restriction Settings
$login_method  = isset($rs_settings['login_restriction']) ? $rs_settings['login_restriction'] : 'both';
$unique_nick   = isset($rs_settings['force_unique_nickname']) ? $rs_settings['force_unique_nickname'] : 0;
$disable_arch  = isset($rs_settings['disable_extra_archives']) ? $rs_settings['disable_extra_archives'] : 0;

// API Access Settings
$xmlrpc_mode     = isset($rs_settings['xmlrpc_mode']) ? $rs_settings['xmlrpc_mode'] : 'enable';
$xmlrpc_mult_auth= isset($rs_settings['xmlrpc_mult_auth']) ? $rs_settings['xmlrpc_mult_auth'] : 1;
$rest_api_mode   = isset($rs_settings['rest_api_mode']) ? $rs_settings['rest_api_mode'] : 'default';

// System Protection Fields (Consolidated from views/advance-security/system-security.php)
$sys_fields = [
    'hide_wp_version'            => ['label' => 'Hide WordPress Version', 'desc' => 'Removes WordPress version number from the site source code to improve security.'],
    'sys_tweaks'                 => ['label' => 'Disable File Editor', 'desc' => 'Disables the built-in file editor for Themes and Plugins.'],
    'disable_install_update'     => ['label' => 'Disable Plugin & Theme Install', 'desc' => 'Prevents users (including admins) from installing or updating plugins/themes.'],
    'protect_system_files'       => ['label' => 'Protect System Files (.htaccess)', 'desc' => 'Prevent public access to readme.html, wp-config.php, .htaccess etc.'],
    'disable_directory_browsing' => ['label' => 'Disable Directory Browsing', 'desc' => 'Prevents users from seeing a list of files in a directory when no index file is present.'],
    'disable_php_uploads'        => ['label' => 'Disable PHP in Uploads', 'desc' => 'Blocks PHP execution in the /uploads directory.'],
    'disable_php_plugins'        => ['label' => 'Disable PHP in Plugins', 'desc' => 'Blocks direct PHP execution inside /plugins directories (Advance).'],
    'disable_php_themes'         => ['label' => 'Disable PHP in Themes', 'desc' => 'Blocks direct PHP execution inside /themes directories (Advance).'],
    'config_hardening'           => ['label' => 'Security Config Hardening (.htaccess)', 'desc' => 'Adds extra layers of security rules (XSS, Clickjacking protection).'],
    'ssl_enforce'                => ['label' => 'SSL Enforcement', 'desc' => 'Forces all traffic to use HTTPS.'],
    'recaptcha'                  => ['label' => 'Recaptcha Integration', 'desc' => 'Enable Google Recaptcha on login and registration forms.'],
];


global $wpdb; 
$current_prefix = $wpdb->prefix;
global $upcoming_features; // Defined in advance-security.php wrapper
$upcoming_list = isset($upcoming_features) ? $upcoming_features : [];

?>

<div class="rs-hardening-wrapper">
    
    <div class="rs-section-box" style="border-left: 4px solid #2271b1;">
        <h4 class="rs-section-title" style="color: #2271b1;">Hide Backend & Redirection</h4>
        <div style="margin-bottom: 15px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="hide_login" id="rs_hide_login_toggle" value="1" <?php checked(1, $hl_enabled); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_hide_login_toggle" style="font-weight: 600; font-size: 14px;">Enable Hide Backend</label>
            <p class="description" style="margin-left: 55px; margin-top: 0;">Change the login URL to prevent brute force attacks.</p>
        </div>
        <div id="rs_hide_login_inputs" style="display: <?php echo $hl_enabled ? 'block' : 'none'; ?>;">
            <table class="form-table">
                <tr>
                    <th><label for="login_slug">Login Slug</label></th>
                    <td>
                        <input type="text" name="login_slug" id="login_slug" value="<?php echo esc_attr($login_slug); ?>" class="regular-text">
                        <p class="description">New login URL (e.g., <code>site.com/royalsentinel-login</code>).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="register_slug">Register Slug</label></th>
                    <td>
                        <input type="text" name="register_slug" id="register_slug" value="<?php echo esc_attr($register_slug); ?>" class="regular-text">
                        <p class="description">New registration URL.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">
        
        <div style="margin-bottom: 15px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="enable_redirection" id="rs_redirect_toggle" value="1" <?php checked(1, $do_redirect); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_redirect_toggle" style="font-weight: 600; font-size: 14px;">Enable Redirection</label>
            <p class="description" style="margin-left: 55px; margin-top: 0;">Redirect unauthorized users trying to access default login pages.</p>
        </div>
        <div id="rs_redirect_inputs" style="display: <?php echo $do_redirect ? 'block' : 'none'; ?>;">
            <table class="form-table">
                <tr>
                    <th><label for="redirect_slug">Redirection Slug</label></th>
                    <td>
                        <input type="text" name="redirect_slug" id="redirect_slug" value="<?php echo esc_attr($redirect_slug); ?>" class="regular-text">
                        <p class="description">Slug to redirect to (e.g., <code>404</code> or <code>contact-us</code>).</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>


    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #d63638;">
        <h4 class="rs-section-title" style="color: #d63638;">Database & Default User Security</h4>
        <div id="rs-db-action-area">
            <table class="form-table">
                <tr>
                    <th><label>Current Prefix</label></th>
                    <td>
                        <input type="text" value="<?php echo esc_attr($current_prefix); ?>" class="regular-text" readonly disabled style="background:#f0f0f1; color:#666;">
                    </td>
                </tr>
                <tr>
                    <th><label for="new_db_prefix">New Prefix</label></th>
                    <td>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" id="new_db_prefix" class="regular-text" placeholder="e.g. rand_">
                            <button type="button" id="rs_gen_prefix_btn" class="button button-secondary">
                                <span class="dashicons dashicons-randomize" style="vertical-align: middle;"></span> Generate
                            </button>
                        </div>
                        <p class="description">Must be at least 3 characters and end with <code>_</code> (underscore).</p>
                    </td>
                </tr>
            </table>
            <div style="margin-top: 15px;">
                <button type="button" id="rs_db_submit_btn" class="button button-secondary" disabled style="color: #b32d2e; border-color: #b32d2e; opacity: 0.5;">
                    Change Prefix Now
                </button>
            </div>
        </div>
        
        <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">

        <?php 
        $user_id_one = get_user_by( 'ID', 1 );
        $user_admin  = get_user_by( 'login', 'admin' );
        ?>
        <table class="form-table">
            <tr>
                <th><label>User ID 1 Status</label></th>
                <td>
                    <?php if ( $user_id_one ): ?>
                        <span style="color: #d63638; font-weight: bold; margin-right: 10px;">⚠️ Found!</span>
                        <button type="button" class="button button-small button-secondary rs-change-id-btn">
                            Change ID 1 to Random
                        </button>
                        <p class="description">User <b><?php echo esc_html($user_id_one->user_login); ?></b> has ID 1.</p>
                    <?php else: ?>
                        <span style="color: green; font-weight: bold;">✅ Secure (No User ID 1)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label>Username 'admin'</label></th>
                <td>
                    <?php if ( $user_admin ): ?>
                        <span style="color: #d63638; font-weight: bold; margin-right: 10px;">⚠️ Found!</span>
                        <button type="button" class="button button-small button-secondary rs-rename-admin-btn">
                            Rename 'admin'
                        </button>
                    <?php else: ?>
                        <span style="color: green; font-weight: bold;">✅ Secure</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">Login & User Restriction</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Login with Email or Username</label></th>
                <td>
                    <select name="login_restriction" class="regular-text">
                        <option value="both" <?php selected('both', $login_method); ?>>Email Address and Username (Default)</option>
                        <option value="email_only" <?php selected('email_only', $login_method); ?>>Email Address Only</option>
                        <option value="username_only" <?php selected('username_only', $login_method); ?>>Username Only</option>
                    </select>
                    <p class="description">Restrict logins to only accept email addresses or usernames.</p>
                </td>
            </tr>
            <tr>
                <th scope="row" style="width: 50px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="force_unique_nickname" value="1" <?php checked(1, $unique_nick); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td>
                    <label style="font-weight:600; color: #3c434a;">Force Unique Nickname</label>
                    <p class="description">Forces users to choose a unique nickname to prevent bots from harvesting usernames.</p>
                </td>
            </tr>
            <tr>
                <th scope="row" style="width: 50px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="disable_extra_archives" value="1" <?php checked(1, $disable_arch); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td>
                    <label style="font-weight:600; color: #3c434a;">Disable Extra User Archives</label>
                    <p class="description">Disables a user’s author page if their post count is 0.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #27ae60;">
        <h4 class="rs-section-title" style="color: #27ae60;">System & File Protection</h4>
        
        <table class="form-table">
            <?php 
            foreach($sys_fields as $key => $info): 
                $is_active = isset($rs_settings[$key]) ? $rs_settings[$key] : 0;
                
                $is_upcoming = in_array( $key, $upcoming_list );
                $disabled_class = $is_upcoming ? 'disabled' : '';
                $disabled_attr  = $is_upcoming ? 'disabled' : '';
                $label_suffix   = $is_upcoming ? ' <span class="coming-soon-badge">Coming Soon</span>' : '';
            ?>
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch <?php echo $disabled_class; ?>">
                        <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php checked(1, $is_active); ?> <?php echo $disabled_attr; ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">
                        <?php echo $info['label'] . $label_suffix; ?>
                    </label>
                    <p class="description" style="margin-top: 5px;"><?php echo $info['desc']; ?></p>
                    <?php if ( $key === 'recaptcha' ): 
                        $rc_site_key   = isset($rs_settings['recaptcha_site_key']) ? $rs_settings['recaptcha_site_key'] : '';
                        $rc_secret_key = isset($rs_settings['recaptcha_secret_key']) ? $rs_settings['recaptcha_secret_key'] : '';
                    ?>
                    <div id="rs_recaptcha_keys" style="margin-top: 12px; padding: 15px; background: #f9f9f9; border: 1px solid #eee; border-radius: 4px; display: <?php echo $is_active ? 'block' : 'none'; ?>;">
                        <table style="width: 100%;">
                            <tr>
                                <td style="padding: 5px 0;"><label for="recaptcha_site_key" style="font-weight: 500;">Site Key</label></td>
                                <td style="padding: 5px 0;">
                                    <input type="text" name="recaptcha_site_key" id="recaptcha_site_key" value="<?php echo esc_attr($rc_site_key); ?>" class="regular-text" style="width:100%; max-width:500px;" placeholder="Enter your reCAPTCHA Site Key">
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 5px 0;"><label for="recaptcha_secret_key" style="font-weight: 500;">Secret Key</label></td>
                                <td style="padding: 5px 0;">
                                    <input type="text" name="recaptcha_secret_key" id="recaptcha_secret_key" value="<?php echo esc_attr($rc_secret_key); ?>" class="regular-text" style="width:100%; max-width:500px;" placeholder="Enter your reCAPTCHA Secret Key">
                                </td>
                            </tr>
                        </table>
                        <p class="description" style="margin-top: 8px;">Get your keys from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a>.</p>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #34495e;">
        <h4 class="rs-section-title" style="color: #34495e;">API Access</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label style="font-weight:600; color:#3c434a;">XML-RPC</label></th>
                <td>
                    <select name="xmlrpc_mode" class="regular-text" style="width:100%; max-width:500px;">
                        <option value="enable" <?php selected('enable', $xmlrpc_mode); ?>>Enable XML-RPC</option>
                        <option value="disable_all" <?php selected('disable_all', $xmlrpc_mode); ?>>Disable XML-RPC</option>
                        <option value="disable_pingbacks" <?php selected('disable_pingbacks', $xmlrpc_mode); ?>>Disable Pingbacks</option>
                    </select>
                    <div style="margin-top: 10px;">
                        <label>
                            <input type="checkbox" name="xmlrpc_mult_auth" value="1" <?php checked(1, $xmlrpc_mult_auth); ?>> 
                            <strong>Allow Multiple Authentication Attempts per XML-RPC Request</strong>
                        </label>
                        <p class="description" style="margin-top: 5px;">
                            By default, the WordPress XML-RPC API allows hundreds of username and password guesses per request. Turn off this setting to prevent attackers from exploiting this feature.
                        </p>
                    </div>
                </td>
            </tr>
            <tr>
                <th scope="row" style="padding-top: 20px;"><label style="font-weight:600; color:#3c434a;">REST API</label></th>
                <td style="padding-top: 20px;">
                    <fieldset>
                        <label style="margin-bottom: 8px; display: block;">
                            <input type="radio" name="rest_api_mode" value="default" <?php checked('default', $rest_api_mode); ?>> 
                            Default Access <span class="description">(Full public access)</span>
                        </label>
                        <label style="margin-bottom: 8px; display: block;">
                            <input type="radio" name="rest_api_mode" value="restricted" <?php checked('restricted', $rest_api_mode); ?>> 
                            <strong>Restricted Access</strong> <span class="description">(Logged-in users only)</span>
                        </label>
                    </fieldset>
                    
                    <div style="margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #eee;">
                        <label style="display: flex; align-items: flex-start; gap: 8px;">
                            <input type="checkbox" name="rest_block_user_enum" value="1" <?php checked(1, isset($rs_settings['rest_block_user_enum']) ? $rs_settings['rest_block_user_enum'] : 0); ?> style="margin-top: 3px;">
                            <div>
                                <strong style="color: #d63638;">Block User Enumeration</strong>
                                <p class="description" style="margin: 5px 0 0;">
                                    Blocks <code>/wp-json/wp/v2/users</code> endpoint for non-authenticated requests. This prevents attackers from discovering usernames.
                                </p>
                            </div>
                        </label>
                    </div>
                    
                    <p class="description" style="margin-top: 10px;">
                        The WordPress REST API provides developers with new ways to manage WordPress. By default, it could give public access to information that you believe is private on your site.
                    </p>
                </td>
            </tr>
        </table>
    </div>

</div>

<style>
    /* Styles are already provided in the original code, including those in hardening.php */
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 200px; }
    .regular-text { width: 100%; max-width: 400px; }
    .coming-soon-badge { display: inline-block; background: #f0f0f1; color: #8c8f94; font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-left: 5px; text-transform: uppercase; font-weight: 600; border: 1px solid #dcdcde; }
    .rs-switch.disabled { opacity: 0.5; cursor: not-allowed; }
</style>

<script>
jQuery(document).ready(function($) {
    function toggleSection(id, wrapper) {
        if($(id).is(':checked')) { $(wrapper).slideDown(); } 
        else { $(wrapper).slideUp(); }
    }
    toggleSection('#rs_hide_login_toggle', '#rs_hide_login_inputs');
    $('#rs_hide_login_toggle').on('change', function() { toggleSection(this, '#rs_hide_login_inputs'); });

    toggleSection('#rs_redirect_toggle', '#rs_redirect_inputs');
    $('#rs_redirect_toggle').on('change', function() { toggleSection(this, '#rs_redirect_inputs'); });

    // Helper to submit actions (for DB, User ID)
    function rsSubmitAction(params) {
        var form = $('<form method="post" action=""></form>');
        $.each(params, function(k, v) { form.append('<input type="hidden" name="'+k+'" value="'+v+'">'); });
        $('body').append(form);
        form.submit();
    }

    // DB Prefix Logic
    function generatePrefix() { return Math.random().toString(36).substring(2, 7) + '_'; }
    function validatePrefix(val) {
        var btn = $('#rs_db_submit_btn');
        // Must be at least 3 chars long (including '_') and end with '_'
        if ( val.length >= 3 && val.slice(-1) === '_' ) { btn.prop('disabled', false).css('opacity', '1'); } 
        else { btn.prop('disabled', true).css('opacity', '0.5'); }
    }
    var initPrefix = generatePrefix();
    $('#new_db_prefix').val(initPrefix);
    validatePrefix(initPrefix);

    $('#rs_gen_prefix_btn').click(function() {
        var newPre = generatePrefix();
        $('#new_db_prefix').val(newPre);
        validatePrefix(newPre);
    });
    $('#new_db_prefix').on('input', function() { validatePrefix($(this).val()); });

    // DB Change Button
    $('#rs_db_submit_btn').click(function(e) {
        e.preventDefault();
        var newPrefix = $('#new_db_prefix').val();
        Swal.fire({
            title: 'Critical Warning!',
            html: "Backup Required.<br>Change prefix?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Change it!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Processing...', showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
                rsSubmitAction({
                    'rs_action_change_prefix': '1',
                    'rs_prefix_nonce': '<?php echo wp_create_nonce("rs_change_prefix_nonce"); ?>',
                    'new_db_prefix': newPrefix
                });
            }
        });
    });

    // Change ID 1 Button
    $('.rs-change-id-btn').click(function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Change User ID 1?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Fix it!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({title: 'Processing...', showConfirmButton:false, didOpen: () => { Swal.showLoading(); }});
                rsSubmitAction({
                    'rs_user_action': 'change_id_1',
                    'rs_user_nonce': '<?php echo wp_create_nonce("rs_user_hardening_nonce"); ?>'
                });
            }
        });
    });

    // Rename Admin Button
    $('.rs-rename-admin-btn').click(function() {
        Swal.fire({
            title: 'Rename "admin" User',
            input: 'text',
            showCancelButton: true,
            confirmButtonText: 'Rename',
            inputValidator: (value) => { if (!value) return 'Required!' }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({title: 'Renaming...', showConfirmButton:false, didOpen: () => { Swal.showLoading(); }});
                rsSubmitAction({
                    'rs_user_action': 'rename_admin',
                    'rs_user_nonce': '<?php echo wp_create_nonce("rs_user_hardening_nonce"); ?>',
                    'rs_new_username': result.value
                });
            }
        });
    });

    // reCAPTCHA key fields toggle
    $('input[name="recaptcha"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('#rs_recaptcha_keys').slideDown();
        } else {
            $('#rs_recaptcha_keys').slideUp();
        }
    });
});
</script>