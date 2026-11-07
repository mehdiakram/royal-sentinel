<?php
/*
 * --------------------------------------------------------------------------
 * ROYAL SECURITY: 2FA, TRUSTED DEVICES & MAGIC LINK
 * --------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Two_Factor_Logic {

    public function __construct() {
        // 1. লগিন হুক
        add_filter('authenticate', array($this, 'check_2fa_on_login'), 30, 3);
        
        // 2. 2FA ফর্ম রেন্ডার
        add_action('login_form_rs_2fa_verify', array($this, 'render_2fa_form'));
        
        // 3. কোড ভেরিফিকেশন প্রসেস
        add_action('init', array($this, 'process_2fa_verification'));
        
        // 4. [NEW] ম্যাজিক লিংক প্রসেসর
        add_action('init', array($this, 'process_2fa_magic_link_login'));
    }

    // === ১. লগিন ও ইমেইল পাঠানো ===
    public function check_2fa_on_login($user, $username, $password) {
        if (is_wp_error($user) || !is_a($user, 'WP_User')) {
            return $user;
        }

        $rs_settings = get_option('rs_security_settings', []); 
        $is_2fa_enabled = isset($rs_settings['2fa']) ? $rs_settings['2fa'] : 0;

        if (!$is_2fa_enabled) {
            return $user; 
        }

        // --- ROLE CHECK ---
        $enabled_roles = isset($rs_settings['2fa_roles']) ? $rs_settings['2fa_roles'] : 'all';
        
        // If not 'all', check if user has any of the enabled roles
        if ($enabled_roles !== 'all') {
            // Handle both single role (string) and multiple roles (array)
            $roles_array = is_array($enabled_roles) ? $enabled_roles : array($enabled_roles);
            $user_roles = (array) $user->roles;
            
            // Check if user has any of the enabled roles
            $has_enabled_role = !empty(array_intersect($roles_array, $user_roles));
            
            if (!$has_enabled_role) {
                return $user; // User doesn't have an enabled role, skip 2FA
            }
        }

        // --- TRUSTED DEVICE CHECK ---
        if ( !empty($rs_settings['trusted_devices']) && isset($_COOKIE['rs_trusted_device']) ) {
            $cookie_data = json_decode(stripslashes($_COOKIE['rs_trusted_device']), true);
            if ( isset($cookie_data['uid']) && $cookie_data['uid'] == $user->ID ) {
                $check_hash = md5($user->ID . 'rs_trust_' . wp_salt());
                if ( hash_equals($check_hash, $cookie_data['hash']) ) {
                    return $user; // ডিভাইস ট্রাস্টেড, 2FA স্কিপ
                }
            }
        }

        // --- 1. জেনারেট কোড (6 Digits) ---
        $code = rand(100000, 999999);
        update_user_meta($user->ID, '_rs_2fa_code', $code);
        update_user_meta($user->ID, '_rs_2fa_expires', time() + 600); // ১০ মিনিট মেয়াদ

        // --- 2. [NEW] জেনারেট ম্যাজিক লিংক (5 মিনিট মেয়াদ) ---
        $token = bin2hex(random_bytes(32));
        update_user_meta($user->ID, '_rs_2fa_magic_token', $token);
        update_user_meta($user->ID, '_rs_2fa_magic_expiry', time() + 300); // ৫ মিনিট মেয়াদ

        // লিংক তৈরি
        $magic_link = site_url("wp-login.php?action=rs_2fa_magic_login&uid={$user->ID}&token={$token}");

        // --- ইমেইল পাঠানো ---
        $subject = sprintf('Login Code for %s', get_bloginfo('name'));
        
        $message  = "<div style='font-family:sans-serif; line-height:1.5; color:#333;'>";
        $message .= "<h2 style='color:#2271b1;'>Login Verification</h2>";
        $message .= "<p>Use the verification code below to login:</p>";
        $message .= "<div style='font-size:24px; font-weight:bold; letter-spacing:5px; background:#f0f0f1; padding:10px; display:inline-block; border-radius:4px; margin:10px 0;'>{$code}</div>";
        $message .= "<p><strong>-- OR --</strong></p>";
        $message .= "<p>Click the link below to login instantly (Valid for 5 minutes):</p>";
        $message .= "<p><a href='{$magic_link}' style='background:#2271b1; color:#fff; text-decoration:none; padding:12px 20px; border-radius:4px; display:inline-block; font-weight:bold;'>Login Now &rarr;</a></p>";
        $message .= "<p style='font-size:12px; color:#777; margin-top:20px;'>If you did not request this, please ignore this email.</p>";
        $message .= "</div>";

        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($user->user_email, $subject, $message, $headers);

        // সেশন সেটআপ
        $hash = md5($user->ID . time() . wp_salt());
        set_transient('rs_2fa_pending_' . $hash, $user->ID, 600);
        setcookie('rs_2fa_auth_key', $hash, time() + 600, COOKIEPATH, COOKIE_DOMAIN);

        // AJAX Request Detection - royal-learn এবং অন্যান্য কাস্টম লগইন ফর্মের জন্য
        if ( wp_doing_ajax() || ( defined('DOING_AJAX') && DOING_AJAX ) ) {
            // AJAX এর ক্ষেত্রে redirect না করে WP_Error return করবে
            // Error code 'rs_2fa_required' দিয়ে কাস্টম ফর্ম বুঝতে পারবে
            return new WP_Error(
                'rs_2fa_required',
                __('2FA verification required. Check your email for the verification code.', 'royal-sentinel'),
                array(
                    'redirect_url' => site_url('wp-login.php?action=rs_2fa_verify'),
                    '2fa_hash' => $hash
                )
            );
        }

        // Non-AJAX রিডাইরেক্ট (স্বাভাবিক wp-login.php ফর্মের জন্য)
        wp_redirect(site_url('wp-login.php?action=rs_2fa_verify'));
        exit;
    }

    // === ২. ফর্ম রেন্ডার ===
    public function render_2fa_form() {
        if (!isset($_COOKIE['rs_2fa_auth_key'])) { wp_redirect(wp_login_url()); exit; }
        
        $rs_settings = get_option('rs_security_settings', []);
        $show_trusted = !empty($rs_settings['trusted_devices']);
        
        $error_msg = isset($_GET['error']) && $_GET['error'] == 'invalid' ? '<div style="background:#fce4e4;border:1px solid #cc0033;color:#cc0033;padding:10px;margin-bottom:15px;text-align:center;">Invalid Code. Try Again.</div>' : '';
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verification</title>
            <style>body{background:#f0f0f1;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;margin:0}.rs-login-box{background:#fff;padding:40px 30px;width:100%;max-width:350px;box-shadow:0 3px 6px rgba(0,0,0,.05);border-radius:8px;text-align:center}.rs-btn{background:#2271b1;color:#fff;border:none;padding:12px;width:100%;border-radius:4px;font-size:16px;cursor:pointer;margin-top:15px}.rs-btn:hover{background:#135e96}input[type="text"]{width:100%;padding:12px;font-size:24px;border:1px solid #ccd0d4;border-radius:4px;text-align:center;letter-spacing:8px;box-sizing:border-box}.rs-back{display:block;margin-top:20px;color:#666;text-decoration:none;font-size:13px}.trust-device{margin-top:15px;text-align:left;font-size:14px;color:#555;display:flex;align-items:center;gap:8px;}</style>
        </head>
        <body>
            <div class="rs-login-box">
                <h2 style="margin-top:0;color:#333;">Security Check</h2>
                <p style="color:#666; font-size:14px; margin-bottom: 25px;">Enter the 6-digit code sent to your email or click the magic link.</p>
                <?php echo $error_msg; ?>
                <form method="post" action="">
                    <input type="text" name="rs_2fa_code" placeholder="------" required autofocus autocomplete="off" maxlength="6">
                    
                    <?php if($show_trusted): ?>
                    <div class="trust-device">
                        <input type="checkbox" name="rs_trust_device" id="rs_trust" value="1">
                        <label for="rs_trust">Trust this device for <?php echo intval($rs_settings['td_duration'] ?? 30); ?> days</label>
                    </div>
                    <?php endif; ?>

                    <button type="submit" name="rs_verify_submit" class="rs-btn">Verify Login</button>
                </form>
                <a href="<?php echo wp_login_url(); ?>" class="rs-back">&larr; Back to Login</a>
            </div>
        </body>
        </html>
        <?php exit;
    }

    // === ৩. কোড ভেরিফিকেশন ===
    public function process_2fa_verification() {
        if (isset($_POST['rs_verify_submit']) && isset($_POST['rs_2fa_code'])) {
            if (!isset($_COOKIE['rs_2fa_auth_key'])) { wp_redirect(wp_login_url()); exit; }
            $hash = $_COOKIE['rs_2fa_auth_key'];
            $user_id = get_transient('rs_2fa_pending_' . $hash);
            if (!$user_id) { wp_redirect(wp_login_url()); exit; }

            $saved_code = get_user_meta($user_id, '_rs_2fa_code', true);
            $expires = get_user_meta($user_id, '_rs_2fa_expires', true);
            $input_code = sanitize_text_field($_POST['rs_2fa_code']);

            if ($input_code == $saved_code && time() < $expires) {
                $this->complete_login($user_id, $hash); // সফল লগিন
            } else {
                wp_redirect(site_url('wp-login.php?action=rs_2fa_verify&error=invalid'));
                exit;
            }
        }
    }

    // === ৪. [NEW] ম্যাজিক লিংক প্রসেসিং ===
    public function process_2fa_magic_link_login() {
        if ( isset($_GET['action']) && $_GET['action'] == 'rs_2fa_magic_login' ) {
            $user_id = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
            $token   = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

            if ( !$user_id || !$token ) wp_die('Invalid Request');

            $saved_token = get_user_meta($user_id, '_rs_2fa_magic_token', true);
            $expiry      = get_user_meta($user_id, '_rs_2fa_magic_expiry', true);

            // টোকেন ম্যাচ এবং মেয়াদ চেক (৫ মিনিট)
            if ( $saved_token && hash_equals($saved_token, $token) && time() < $expiry ) {
                
                // যদি সেশন কুকি থাকে, সেটি দিয়ে হ্যাশ বের করা (ক্লিনআপের জন্য)
                $hash = isset($_COOKIE['rs_2fa_auth_key']) ? $_COOKIE['rs_2fa_auth_key'] : '';
                
                $this->complete_login($user_id, $hash); // সফল লগিন
                
            } else {
                wp_die('<h1>Link Expired or Invalid</h1><p>The magic link has expired or is invalid. Please try logging in again.</p><a href="'.wp_login_url().'">Back to Login</a>');
            }
        }
    }

    // === হেল্পার: লগিন সম্পন্ন করা ===
    private function complete_login($user_id, $pending_hash = '') {
        wp_clear_auth_cookie();
        wp_set_auth_cookie($user_id);
        wp_set_current_user($user_id);

        // Trust Device Cookie সেট করা (শুধুমাত্র ম্যানুয়াল সাবমিশনে কাজ করবে, লিংকে নয়)
        if ( isset($_POST['rs_trust_device']) && $_POST['rs_trust_device'] == '1' ) {
            $rs_settings = get_option('rs_security_settings', []);
            $days = isset($rs_settings['td_duration']) ? intval($rs_settings['td_duration']) : 30;
            $cookie_data = ['uid' => $user_id, 'hash' => md5($user_id . 'rs_trust_' . wp_salt())];
            setcookie('rs_trusted_device', json_encode($cookie_data), time() + ($days * 86400), COOKIEPATH, COOKIE_DOMAIN, true, true);
        }

        // ডাটা ক্লিনআপ (একবার ব্যবহারযোগ্য করা)
        delete_user_meta($user_id, '_rs_2fa_code');
        delete_user_meta($user_id, '_rs_2fa_expires');
        delete_user_meta($user_id, '_rs_2fa_magic_token');  // লিংক নষ্ট করে দেওয়া
        delete_user_meta($user_id, '_rs_2fa_magic_expiry');

        if ($pending_hash) {
            delete_transient('rs_2fa_pending_' . $pending_hash);
            setcookie('rs_2fa_auth_key', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        }

        wp_safe_redirect(admin_url());
        exit;
    }
}
new RS_Two_Factor_Logic();