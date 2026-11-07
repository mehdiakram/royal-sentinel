<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// Settings Helper
$auth_method   = isset($rs_settings['2fa_method']) ? $rs_settings['2fa_method'] : 'all';
$rs_2fa_roles  = isset($rs_settings['2fa_roles']) ? $rs_settings['2fa_roles'] : 'all';
// Normalize to array for backwards compatibility
$rs_2fa_roles_array = ($rs_2fa_roles === 'all') ? [] : (is_array($rs_2fa_roles) ? $rs_2fa_roles : array($rs_2fa_roles));
$rs_2fa_roles_all = ($rs_2fa_roles === 'all');
$disable_first = isset($rs_settings['2fa_disable_first']) ? $rs_settings['2fa_disable_first'] : 0;
$welcome_text  = isset($rs_settings['2fa_welcome_text']) ? $rs_settings['2fa_welcome_text'] : "When you login using Two-factor authenticator you will be prompted to enter a secondary Authentication Code from your Phone or Email.\n\nTwo-Factor authentication adds an important extra layer of protection to your login.";

// Password Policy Settings
$pp_min_len     = isset($rs_settings['pp_min_len']) ? $rs_settings['pp_min_len'] : 8;
$pp_req_upper   = isset($rs_settings['pp_req_upper']) ? $rs_settings['pp_req_upper'] : 0;
$pp_req_digit   = isset($rs_settings['pp_req_digit']) ? $rs_settings['pp_req_digit'] : 0;
$pp_req_special = isset($rs_settings['pp_req_special']) ? $rs_settings['pp_req_special'] : 0;
$pp_expiry_days = isset($rs_settings['pp_expiry_days']) ? $rs_settings['pp_expiry_days'] : 0;

// Passwordless Settings
$pl_roles       = isset($rs_settings['pl_roles']) ? $rs_settings['pl_roles'] : 'administrator';

// Magic Links Settings
$ml_expiry      = isset($rs_settings['ml_expiry']) ? $rs_settings['ml_expiry'] : 15;
$ml_one_time    = isset($rs_settings['ml_one_time']) ? $rs_settings['ml_one_time'] : 1;

// Trusted Devices Settings
$td_duration    = isset($rs_settings['td_duration']) ? $rs_settings['td_duration'] : 30;
?>

<style>
    /* Main Panel Styling */
    .rs-advanced-panel {
        background: #fff;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        border-radius: 4px;
        margin-top: 15px;
        margin-bottom: 20px;
        overflow: hidden;
    }

    /* Row Layout */
    .rs-setting-row {
        display: flex;
        padding: 20px;
        border-bottom: 1px solid #f0f0f1;
    }
    .rs-setting-row:last-child {
        border-bottom: none;
    }

    /* Left Column (Labels like 'Methods') */
    .rs-col-label {
        width: 200px;
        flex-shrink: 0;
        font-weight: 600;
        font-size: 14px;
        color: #1d2327;
        padding-top: 5px; 
    }

    /* Right Column (Inputs) */
    .rs-col-input {
        flex-grow: 1;
    }

    /* Inner Field Labels (Uppercase small) */
    .rs-sub-label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        color: #646970;
        margin-bottom: 8px;
        letter-spacing: 0.5px;
    }

    /* Inputs Styling */
    .rs-advanced-panel select, 
    .rs-advanced-panel textarea,
    .rs-advanced-panel input[type="number"],
    .rs-advanced-panel input[type="text"] {
        width: 100%;
        max-width: 400px;
        border-color: #8c8f94;
        color: #2c3338;
    }
    .rs-advanced-panel input[type="number"] {
        max-width: 150px;
    }
    .rs-advanced-panel select:focus,
    .rs-advanced-panel textarea:focus,
    .rs-advanced-panel input:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 1px #2271b1;
    }

    /* Checkbox Group */
    .rs-checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }
    .rs-checkbox-group input[type="checkbox"] {
        margin: 0;
        border: 1px solid #8c8f94;
    }
    
    /* Description Text */
    .rs-desc-text {
        font-size: 13px;
        color: #646970;
        margin: 5px 0 0 0;
        line-height: 1.5;
    }
</style>

<h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">Login Security</h3>

<table class="form-table">
    <?php 
    $fields = [
        'webauthn_passkeys' => ['label' => 'Passkeys',         'desc' => 'Allow users to login securely with device biometrics (Windows Hello, TouchID). Users can register their device from <a href="' . admin_url('profile.php') . '" target="_blank">My Profile ↗</a>.'],
        '2fa'             => ['label' => 'Two-Factor Authentication', 'desc' => 'Adds an extra layer of security requiring a verification code.'],
        'pass_policy'     => ['label' => 'Password Policy',           'desc' => 'Enforce strong passwords and expiration rules for users.'],
        'passless_login'  => ['label' => 'Passwordless Login',        'desc' => 'Allow users to login via secure email links instead of passwords.'],
        'magic_links'     => ['label' => 'Magic Links',               'desc' => 'Generate temporary login links for emergency or admin access.'],
        'trusted_devices' => ['label' => 'Trusted Devices',           'desc' => 'Skip 2FA on devices marked as trusted for a period of time.'],
    ];

    foreach($fields as $key => $info): 
        $disabled_attr  = '';
        $disabled_class = '';
        $label_suffix   = '';
        
        $is_enabled     = (isset($rs_settings[$key]) && $rs_settings[$key] == 1);
        $label          = $info['label'];
        $desc           = $info['desc'];
    ?>
    
    <tr>
        <th scope="row" style="width: 50px; padding-top: 15px;">
            <label class="rs-switch <?php echo $disabled_class; ?>">
                <input type="checkbox" 
                       name="<?php echo $key; ?>" 
                       id="rs_switch_<?php echo $key; ?>" 
                       value="1" 
                       <?php checked(1, $rs_settings[$key] ?? 0); ?> 
                       <?php echo $disabled_attr; ?>>
                <span class="rs-slider round"></span>
            </label>
        </th>
        <td style="padding-top: 15px;">
            <label for="rs_switch_<?php echo $key; ?>" style="font-weight:600; color: #3c434a; font-size: 14px;">
                <?php echo $label . $label_suffix; ?>
            </label>
            <p class="description" style="margin-top: 5px;"><?php echo $desc; ?></p>
        </td>
    </tr>

    <?php 
    // --- WebAuthn Passkeys Options ---
    if ($key === 'webauthn_passkeys'): 
    ?>
    <tr id="rs_webauthn_passkeys_options_row" style="display: <?php echo $is_enabled ? 'table-row' : 'none'; ?>;">
        <td colspan="2" style="padding: 0;">
            <div class="rs-advanced-panel">
                <div class="rs-setting-row">
                    <div class="rs-col-label">User Experience</div>
                    <div class="rs-col-input">
                        <p class="rs-desc-text">When enabled, users can register devices (fingerprint, FaceID, Windows Hello) from their profile page. A "Login with Passkey" button will also appear on default WordPress login forms.</p>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    <?php endif; ?>

    <?php 
    // --- 2FA Options ---
    if ($key === '2fa'): 
    ?>
    <tr id="rs_2fa_options_row" style="display: <?php echo $is_enabled ? 'table-row' : 'none'; ?>;">
        <td colspan="2" style="padding: 0;">
            <div class="rs-advanced-panel">
                <div class="rs-setting-row">
                    <div class="rs-col-label">Methods</div>
                    <div class="rs-col-input">
                        <label class="rs-sub-label">Authentication Types</label>
                        <select name="2fa_method">
                            <option value="all" <?php selected('all', $auth_method); ?>>All Methods (Recommended)</option>
                            <option value="email_only" <?php selected('email_only', $auth_method); ?>>Email Only</option>
                            <option value="app_only" <?php selected('app_only', $auth_method); ?>>App Only (TOTP)</option>
                        </select>
                        <br><br>
                        <label class="rs-sub-label">Enabled For Roles</label>
                        <div class="rs-checkbox-group" style="margin-bottom: 10px;">
                            <input type="checkbox" id="2fa_roles_all" name="2fa_roles_all" value="1" <?php checked($rs_2fa_roles_all); ?>>
                            <label for="2fa_roles_all"><strong>All Users</strong></label>
                        </div>
                        <div id="rs_2fa_roles_list" style="<?php echo $rs_2fa_roles_all ? 'opacity: 0.5; pointer-events: none;' : ''; ?> padding-left: 5px; border-left: 3px solid #eee;">
                            <?php
                            // Get all WordPress roles dynamically
                            global $wp_roles;
                            $all_roles = $wp_roles->get_names();
                            foreach ($all_roles as $role_slug => $role_name) :
                            ?>
                            <div class="rs-checkbox-group">
                                <input type="checkbox" 
                                       id="2fa_role_<?php echo esc_attr($role_slug); ?>" 
                                       name="2fa_roles[]" 
                                       value="<?php echo esc_attr($role_slug); ?>" 
                                       <?php checked(in_array($role_slug, $rs_2fa_roles_array)); ?>>
                                <label for="2fa_role_<?php echo esc_attr($role_slug); ?>"><?php echo esc_html($role_name); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="rs-desc-text" style="margin-top: 10px;">Select specific roles that require 2FA, or check "All Users" to apply to everyone.</p>
                    </div>
                </div>
                <div class="rs-setting-row">
                    <div class="rs-col-label">Setup Flow</div>
                    <div class="rs-col-input">
                        <div class="rs-checkbox-group">
                            <input type="checkbox" id="2fa_disable_first" name="2fa_disable_first" value="1" <?php checked(1, $disable_first); ?>>
                            <label for="2fa_disable_first">Disable on First Login</label>
                        </div>
                        <p class="rs-desc-text">Simplifies signup by not requiring 2FA immediately upon registration.</p>
                    </div>
                </div>
                <div class="rs-setting-row">
                    <div class="rs-col-label">Welcome Message</div>
                    <div class="rs-col-input">
                        <label class="rs-sub-label">Onboarding Text</label>
                        <textarea name="2fa_welcome_text" rows="3"><?php echo esc_textarea($welcome_text); ?></textarea>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    <?php endif; ?>

    <?php 
    // --- Password Policy Options ---
    if ($key === 'pass_policy'): 
    ?>
    <tr id="rs_pass_policy_options_row" style="display: <?php echo $is_enabled ? 'table-row' : 'none'; ?>;">
        <td colspan="2" style="padding: 0;">
            <div class="rs-advanced-panel">
                <div class="rs-setting-row">
                    <div class="rs-col-label">Complexity</div>
                    <div class="rs-col-input">
                        <label class="rs-sub-label">Minimum Length</label>
                        <input type="number" name="pp_min_len" value="<?php echo esc_attr($pp_min_len); ?>" min="6" max="32">
                        <br><br>
                        <label class="rs-sub-label">Requirements</label>
                        <div class="rs-checkbox-group">
                            <input type="checkbox" id="pp_req_upper" name="pp_req_upper" value="1" <?php checked(1, $pp_req_upper); ?>>
                            <label for="pp_req_upper">Require Uppercase Letters (A-Z)</label>
                        </div>
                        <div class="rs-checkbox-group">
                            <input type="checkbox" id="pp_req_digit" name="pp_req_digit" value="1" <?php checked(1, $pp_req_digit); ?>>
                            <label for="pp_req_digit">Require Digits (0-9)</label>
                        </div>
                        <div class="rs-checkbox-group">
                            <input type="checkbox" id="pp_req_special" name="pp_req_special" value="1" <?php checked(1, $pp_req_special); ?>>
                            <label for="pp_req_special">Require Special Characters (!@#$%...)</label>
                        </div>
                    </div>
                </div>
                <div class="rs-setting-row">
                    <div class="rs-col-label">Expiration</div>
                    <div class="rs-col-input">
                        <label class="rs-sub-label">Password Max Age (Days)</label>
                        <input type="number" name="pp_expiry_days" value="<?php echo esc_attr($pp_expiry_days); ?>" min="0">
                        <p class="rs-desc-text">Set to 0 to disable password expiration.</p>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    <?php endif; ?>

    <?php 
    // --- Passwordless Login Options ---
    if ($key === 'passless_login'): 
    ?>
    <tr id="rs_passless_login_options_row" style="display: <?php echo $is_enabled ? 'table-row' : 'none'; ?>;">
        <td colspan="2" style="padding: 0;">
            <div class="rs-advanced-panel">
                <div class="rs-setting-row">
                    <div class="rs-col-label">Access Control</div>
                    <div class="rs-col-input">
                        <label class="rs-sub-label">Enabled For Roles</label>
                        <select name="pl_roles">
                            <option value="administrator" <?php selected('administrator', $pl_roles); ?>>Administrators Only</option>
                            <option value="editor" <?php selected('editor', $pl_roles); ?>>Editors and above</option>
                            <option value="all" <?php selected('all', $pl_roles); ?>>All Users</option>
                        </select>
                        <p class="rs-desc-text">Users will receive a secure login link via email instead of entering a password.</p>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    <?php endif; ?>

    <?php 
    // --- Magic Links Options ---
    if ($key === 'magic_links'): 
    ?>
    <tr id="rs_magic_links_options_row" style="display: <?php echo $is_enabled ? 'table-row' : 'none'; ?>;">
        <td colspan="2" style="padding: 0;">
            <div class="rs-advanced-panel">
                <div class="rs-setting-row">
                    <div class="rs-col-label">Link Settings</div>
                    <div class="rs-col-input">
                        <label class="rs-sub-label">Link Expiration (Minutes)</label>
                        <input type="number" name="ml_expiry" value="<?php echo esc_attr($ml_expiry); ?>" min="1" max="1440">
                        <br><br>
                        <div class="rs-checkbox-group">
                            <input type="checkbox" id="ml_one_time" name="ml_one_time" value="1" <?php checked(1, $ml_one_time); ?>>
                            <label for="ml_one_time">One-Time Use Only</label>
                        </div>
                        <p class="rs-desc-text">If enabled, the magic link will become invalid immediately after a successful login.</p>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    <?php endif; ?>

    <?php 
    // --- Trusted Devices Options ---
    if ($key === 'trusted_devices'): 
    ?>
    <tr id="rs_trusted_devices_options_row" style="display: <?php echo $is_enabled ? 'table-row' : 'none'; ?>;">
        <td colspan="2" style="padding: 0;">
            <div class="rs-advanced-panel">
                <div class="rs-setting-row">
                    <div class="rs-col-label">Trust Duration</div>
                    <div class="rs-col-input">
                        <label class="rs-sub-label">Days to Remember Device</label>
                        <input type="number" name="td_duration" value="<?php echo esc_attr($td_duration); ?>" min="1" max="365">
                        <p class="rs-desc-text">During this period, users won't be asked for 2FA on the same device.</p>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    <?php endif; ?>

    <?php endforeach; ?>

    <?php
    // --- Turnstile (Cloudflare CAPTCHA) ---
    $s = get_option('rs_security_settings', []);
    $turnstile_on = ! empty($s['turnstile_enable']);
    ?>
    <tr>
        <th scope="row" style="width: 50px; padding-top: 15px;">
            <label class="rs-switch">
                <input type="checkbox"
                       name="turnstile_enable"
                       id="rs_switch_turnstile_enable"
                       value="1"
                       <?php checked(1, $turnstile_on); ?>>
                <span class="rs-slider round"></span>
            </label>
        </th>
        <td style="padding-top: 15px;">
            <label for="rs_switch_turnstile_enable" style="font-weight:600; color: #3c434a; font-size: 14px;">
                Cloudflare Turnstile (CAPTCHA)
            </label>
            <p class="description" style="margin-top: 5px;">Add an invisible CAPTCHA to login and registration forms. <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank">Get keys ↗</a></p>
        </td>
    </tr>
    <tr id="rs_turnstile_enable_options_row" style="display: <?php echo $turnstile_on ? 'table-row' : 'none'; ?>;">
        <td colspan="2" style="padding: 0;">
            <div class="rs-advanced-panel">
                <div class="rs-setting-row">
                    <div class="rs-col-label">API Keys</div>
                    <div class="rs-col-input">
                        <label class="rs-sub-label">Site Key</label>
                        <input type="text" name="turnstile_site_key" value="<?php echo esc_attr($s['turnstile_site_key'] ?? ''); ?>" class="regular-text" placeholder="0x4AAAAAAAxxxxxxxx...">
                        <br><br>
                        <label class="rs-sub-label">Secret Key</label>
                        <input type="password" name="turnstile_secret_key" value="<?php echo esc_attr($s['turnstile_secret_key'] ?? ''); ?>" class="regular-text">
                    </div>
                </div>
                <div class="rs-setting-row">
                    <div class="rs-col-label">Protect Forms</div>
                    <div class="rs-col-input">
                        <div class="rs-checkbox-group">
                            <input type="checkbox" id="turnstile_login" name="turnstile_login" value="1" <?php checked(1, $s['turnstile_login'] ?? 0); ?>>
                            <label for="turnstile_login">Login Form</label>
                        </div>
                        <div class="rs-checkbox-group">
                            <input type="checkbox" id="turnstile_register" name="turnstile_register" value="1" <?php checked(1, $s['turnstile_register'] ?? 0); ?>>
                            <label for="turnstile_register">Registration Form</label>
                        </div>
                        <div class="rs-checkbox-group">
                            <input type="checkbox" id="turnstile_woo_checkout" name="turnstile_woo_checkout" value="1" <?php checked(1, $s['turnstile_woo_checkout'] ?? 0); ?>>
                            <label for="turnstile_woo_checkout">WooCommerce Checkout</label>
                        </div>
                        <p class="rs-desc-text">Settings will be saved when you click <strong>Save Changes</strong> above.</p>
                    </div>
                </div>
            </div>
        </td>
    </tr>

</table>

<script>
jQuery(document).ready(function($) {
    // Helper function to toggle rows
    function toggleRow(checkboxId, rowId) {
        $(checkboxId).on('change', function() {
            if($(this).is(':checked')) {
                $(rowId).fadeIn(300);
            } else {
                $(rowId).fadeOut(200);
            }
        });
    }

    // Initialize Toggles
    toggleRow('#rs_switch_webauthn_passkeys', '#rs_webauthn_passkeys_options_row');
    toggleRow('#rs_switch_2fa', '#rs_2fa_options_row');
    toggleRow('#rs_switch_pass_policy', '#rs_pass_policy_options_row');
    toggleRow('#rs_switch_passless_login', '#rs_passless_login_options_row');
    toggleRow('#rs_switch_magic_links', '#rs_magic_links_options_row');
    toggleRow('#rs_switch_trusted_devices', '#rs_trusted_devices_options_row');
    toggleRow('#rs_switch_turnstile_enable', '#rs_turnstile_enable_options_row');

    // 2FA Roles - All Users toggle
    $('#2fa_roles_all').on('change', function() {
        var $rolesList = $('#rs_2fa_roles_list');
        if ($(this).is(':checked')) {
            $rolesList.css({'opacity': '0.5', 'pointer-events': 'none'});
            // Uncheck all individual roles when "All Users" is checked
            $rolesList.find('input[type="checkbox"]').prop('checked', false);
        } else {
            $rolesList.css({'opacity': '1', 'pointer-events': 'auto'});
        }
    });
});
</script>

