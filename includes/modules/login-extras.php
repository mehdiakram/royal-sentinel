<?php
/*
 * --------------------------------------------------------------------------
 * ROYAL SECURITY: PASSWORD POLICY & MAGIC LINKS
 * --------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Login_Extras {

    public function __construct() {
        // Password Policy Hooks
        add_action('user_profile_update_errors', [$this, 'validate_password_policy'], 10, 3);
        add_action('validate_password_reset', [$this, 'validate_password_policy_reset'], 10, 2);

        // Magic Link Hooks
        // [FIXED] 'login_form_bottom' এর পরিবর্তে 'login_form' ব্যবহার করা হয়েছে
        add_action('login_form', [$this, 'render_magic_link_button']);
        
        add_action('init', [$this, 'process_magic_link_request']);
        add_action('init', [$this, 'process_magic_link_login']);
    }

    // === ১. পাসওয়ার্ড পলিসি লজিক ===
    public function validate_password_policy($errors, $update, $user) {
        $password = isset($_POST['pass1']) ? $_POST['pass1'] : '';
        if ( empty($password) ) return;
        $this->check_password_strength($password, $errors);
    }

    public function validate_password_policy_reset($errors, $user) {
        $password = isset($_POST['pass1']) ? $_POST['pass1'] : '';
        if ( empty($password) ) return;
        $this->check_password_strength($password, $errors);
    }

    private function check_password_strength($password, $errors) {
        $settings = get_option('rs_security_settings', []);
        
        // সেটিং চেক - যদি পলিসি অফ থাকে তবে রিটার্ন করবে
        if ( empty($settings['pass_policy']) ) return;

        // মিনিমাম লেংথ
        $min_len = isset($settings['pp_min_len']) ? intval($settings['pp_min_len']) : 8;
        if ( strlen($password) < $min_len ) {
            $errors->add('pass_too_short', "<strong>ERROR</strong>: Password must be at least $min_len characters long.");
        }

        // আপারকেস লেটার
        if ( !empty($settings['pp_req_upper']) && !preg_match('/[A-Z]/', $password) ) {
            $errors->add('pass_no_upper', "<strong>ERROR</strong>: Password must contain at least one uppercase letter (A-Z).");
        }

        // ডিজিট বা সংখ্যা
        if ( !empty($settings['pp_req_digit']) && !preg_match('/[0-9]/', $password) ) {
            $errors->add('pass_no_digit', "<strong>ERROR</strong>: Password must contain at least one number (0-9).");
        }

        // স্পেশাল ক্যারেক্টার
        if ( !empty($settings['pp_req_special']) && !preg_match('/[^a-zA-Z0-9]/', $password) ) {
            $errors->add('pass_no_special', "<strong>ERROR</strong>: Password must contain at least one special character (!@#$...).");
        }
    }

    // === ২. ম্যাজিক লিংক লজিক ===
    public function render_magic_link_button() {
        $settings = get_option('rs_security_settings', []);
        
        // যদি ম্যাজিক লিংক এবং পাসওয়ার্ডলেস লগিন দুটিই বন্ধ থাকে, তবে কিছুই দেখাবে না
        if ( empty($settings['magic_links']) && empty($settings['passless_login']) ) return;

        // বাটন রেন্ডার (লগইন বাটনের ঠিক উপরে দেখাবে)
        ?>
        <div style="margin: 10px 0 15px; text-align: center;">
            <a href="<?php echo esc_url( site_url('?action=rs_magic_login_req') ); ?>" 
               style="text-decoration: none; font-weight: 500; font-size: 14px; color: #2271b1;">
               ✨ Email me a login link
            </a>
        </div>
        <?php
    }

    // রিকোয়েস্ট প্রসেস করা (ইমেইল ইনপুট ফর্ম দেখানো)
    public function process_magic_link_request() {
        if ( isset($_GET['action']) && $_GET['action'] == 'rs_magic_login_req' ) {
            
            $msg = '';
            $msg_type = '';

            if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_login']) ) {
                $login = sanitize_text_field($_POST['user_login']);
                $user = get_user_by('login', $login) ?: get_user_by('email', $login);
                
                if ( $user ) {
                    // টোকেন জেনারেট
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (15 * 60); // 15 মিনিট মেয়াদ
                    
                    update_user_meta($user->ID, '_rs_magic_token', $token);
                    update_user_meta($user->ID, '_rs_magic_expiry', $expiry);
                    
                    // লিংক তৈরি
                    $link = site_url("?rs_magic_token=$token&uid={$user->ID}");
                    
                    // ইমেইল পাঠানো
                    $subject = 'Magic Login Link';
                    $message = "Click the link below to login automatically:\n\n" . $link . "\n\n(This link expires in 15 minutes)";
                    $headers = ['Content-Type: text/plain; charset=UTF-8'];
                    
                    wp_mail($user->user_email, $subject, $message, $headers);
                    
                    $msg = "Success! Check your email for the login link.";
                    $msg_type = 'success';
                } else {
                    $msg = "Error: User not found.";
                    $msg_type = 'error';
                }
            }
            
            // সিম্পল ফর্ম রেন্ডার
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Magic Login</title>
                <style>
                    body { background:#f0f0f1; display:flex; align-items:center; justify-content:center; height:100vh; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif; margin:0; }
                    .rs-box { background:#fff; padding:40px 30px; width:100%; max-width:320px; box-shadow:0 3px 6px rgba(0,0,0,.05); border-radius:8px; text-align:center; }
                    input[type="text"] { width:100%; padding:12px; border:1px solid #ccd0d4; border-radius:4px; margin-bottom:15px; box-sizing:border-box; font-size:16px; }
                    .rs-btn { background:#2271b1; color:#fff; border:none; padding:12px; width:100%; border-radius:4px; font-size:16px; cursor:pointer; font-weight:500; }
                    .rs-btn:hover { background:#135e96; }
                    .rs-msg { margin-bottom:20px; padding:10px; border-radius:4px; font-size:14px; }
                    .success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
                    .error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
                </style>
            </head>
            <body>
                <div class="rs-box">
                    <h2 style="margin-top:0; color:#3c434a;">Magic Login</h2>
                    <p style="color:#646970; font-size:14px; margin-bottom:25px;">Enter your username or email to receive a login link.</p>
                    
                    <?php if($msg): ?>
                        <div class="rs-msg <?php echo $msg_type; ?>"><?php echo esc_html($msg); ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="text" name="user_login" placeholder="Username or Email" required autofocus>
                        <button type="submit" class="rs-btn">Send Login Link</button>
                    </form>
                    
                    <a href="<?php echo wp_login_url(); ?>" style="display:block; margin-top:20px; color:#2271b1; text-decoration:none; font-size:13px;">&larr; Back to Login</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }

    // লিংক ভ্যালিডেশন এবং লগিন করানো
    public function process_magic_link_login() {
        if ( isset($_GET['rs_magic_token']) && isset($_GET['uid']) ) {
            $user_id = intval($_GET['uid']);
            $token   = sanitize_text_field($_GET['rs_magic_token']);
            
            $saved_token = get_user_meta($user_id, '_rs_magic_token', true);
            $expiry      = get_user_meta($user_id, '_rs_magic_expiry', true);
            
            if ( $token === $saved_token && time() < $expiry ) {
                // ইউজার লগিন করানো
                wp_clear_auth_cookie();
                wp_set_auth_cookie($user_id);
                wp_set_current_user($user_id);
                
                // সেটিংস চেক: One-time use হলে টোকেন ডিলিট
                $settings = get_option('rs_security_settings', []);
                if ( isset($settings['ml_one_time']) && $settings['ml_one_time'] == 1 ) {
                    delete_user_meta($user_id, '_rs_magic_token');
                }
                
                // ড্যাশবোর্ডে রিডাইরেক্ট
                wp_safe_redirect(admin_url());
                exit;
            } else {
                wp_die('Invalid or expired login link. <a href="'.wp_login_url().'">Try again</a>');
            }
        }
    }
}

// [IMPORTANT] ক্লাসটি রান করা
new RS_Login_Extras();