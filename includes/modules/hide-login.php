<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Hide_Login {

    private $settings;
    private $login_slug;

    public function __construct() {
        // ১. এই হুকটি সবার আগে রাখতে হবে (শর্তের বাইরে)
        add_action( 'update_option_rs_security_settings', [ $this, 'flush_rules_on_change' ], 10, 2 );

        $this->settings = get_option( 'rs_security_settings', [] );
        
        // ২. ফিচার অফ থাকলে এখান থেকেই ফিরে যাবে
        if ( empty($this->settings['hide_login']) ) {
            return;
        }

        $this->login_slug = !empty($this->settings['login_slug']) ? $this->settings['login_slug'] : 'royalsentinel-login';

        // ৩. বাকি হুকগুলো
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'load_login_page' ] );

        add_action( 'init', [ $this, 'block_default_login' ] );

        add_filter( 'site_url', [ $this, 'change_site_url' ], 10, 4 );
        add_filter( 'network_site_url', [ $this, 'change_site_url' ], 10, 3 );
        add_filter( 'wp_redirect', [ $this, 'filter_wp_redirect' ], 10, 2 );

        // ৪. অ্যাক্টিভেশন হুক
        register_activation_hook( __FILE__, [ $this, 'flush_rules_on_activation' ] );
    }

    /**
     * ১. Rewrite Rule
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^' . $this->login_slug . '/?$',
            'index.php?rs_custom_login=1',
            'top'
        );
    }

    /**
     * ১.১ Query Var
     */
    public function add_query_var( $vars ) {
        $vars[] = 'rs_custom_login';
        return $vars;
    }

    /**
     * ১.২ লগিন পেজ লোড করা (Fix applied here)
     */
    public function load_login_page() {
        if ( get_query_var( 'rs_custom_login' ) == 1 ) {
            // কনস্ট্যান্ট ডিফাইন
            if (!defined('RS_CUSTOM_LOGIN_HIT')) {
                define('RS_CUSTOM_LOGIN_HIT', true);
            }
            
            // বাফারিং ক্লিন
            if (ob_get_level()) ob_end_clean();
            
            // [FIX] wp-login.php এর জন্য প্রয়োজনীয় ভেরিয়েবলগুলো গ্লোবাল করা হলো
            global $user_login, $error, $errors, $action, $user_identity, $interim_login, $message;
            
            // লগিন পেজ ইনক্লুড
            require_once ABSPATH . 'wp-login.php';
            exit();
        }
    }

    /**
     * ২. ডিফল্ট wp-login.php ব্লক করা
     */
    public function block_default_login() {
        if ( defined('RS_CUSTOM_LOGIN_HIT') || get_query_var('rs_custom_login') == 1 ) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'];

        if ( strpos( $request_uri, 'wp-login.php' ) !== false || 
             strpos( $request_uri, 'wp-signup.php' ) !== false ||
             ( strpos( $request_uri, 'wp-admin' ) !== false && !is_user_logged_in() && strpos( $request_uri, 'admin-ajax.php' ) === false && strpos( $request_uri, 'admin-post.php' ) === false ) ) {
            
            if ( is_user_logged_in() ) {
                return; 
            }

            $do_redirect   = !empty($this->settings['enable_redirection']);
            $redirect_slug = !empty($this->settings['redirect_slug']) ? $this->settings['redirect_slug'] : '404';

            if ( $do_redirect ) {
                wp_redirect( home_url( $redirect_slug ) );
                exit;
            } else {
                global $wp_query;
                $wp_query->set_404();
                status_header( 404 );
                get_template_part( 404 ); 
                exit;
            }
        }
    }

    /**
     * ৩. ইউআরএল রিপ্লেসমেন্ট
     */
    public function change_site_url( $url, $path, $scheme = null ) {
        return str_replace( 'wp-login.php', $this->login_slug, $url );
    }

    public function filter_wp_redirect( $location, $status ) {
        return str_replace( 'wp-login.php', $this->login_slug, $location );
    }
    
    public function flush_rules_on_activation() {
        flush_rewrite_rules();
    }

    public function flush_rules_on_change( $old_value, $new_value ) {
        if ( 
            ( isset($new_value['hide_login']) && $new_value['hide_login'] != $old_value['hide_login'] ) || 
            ( isset($new_value['login_slug']) && $new_value['login_slug'] != $old_value['login_slug'] ) 
        ) {
            flush_rewrite_rules();
        }
    }
}

new RS_Hide_Login();