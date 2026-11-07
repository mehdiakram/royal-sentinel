<?php
/*
 * --------------------------------------------------------------------------
 * ROYAL SECURITY: HARDENING LOGIC MODULE
 * --------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Hardening_Logic {

    public function __construct() {
        $settings = get_option( 'rs_security_settings', [] );

        // ১. রানটাইম লজিক
        $this->apply_runtime_hardening( $settings );

        // [NEW] Hide WordPress Version Hook
        if ( ! empty( $settings['hide_wp_version'] ) ) {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
        }

        // ২. লগিন রেস্ট্রিকশন
        add_filter( 'authenticate', array($this, 'restrict_login_method'), 20, 3 );

        // ৩. ইউনিক নিকনেম
        add_action( 'user_profile_update_errors', array($this, 'enforce_unique_nickname'), 10, 3 );

        // ৪. ইউজার আর্কাইভ ডিজেবল
        add_action( 'template_redirect', array($this, 'disable_empty_author_archives') );

        // ৫. Recaptcha
        add_action( 'login_enqueue_scripts', array($this, 'recaptcha_scripts') );
        add_action( 'login_form', array($this, 'recaptcha_display') );
        add_filter( 'authenticate', array($this, 'recaptcha_verify'), 10, 3 );

        // ৬. API Control
        add_filter( 'xmlrpc_enabled', array($this, 'control_xmlrpc_status') );
        add_filter( 'xmlrpc_methods', array($this, 'control_xmlrpc_methods') );
        add_filter( 'rest_authentication_errors', array($this, 'restrict_rest_api') );

        // ৭. সেটিংস সেভ করার সময় .htaccess আপডেট করা
        add_action( 'update_option_rs_security_settings', array( $this, 'update_htaccess_rules' ), 10, 2 );
    }

    /**
     * রানটাইম হার্ডেনিং (কনস্ট্যান্ট ডিফাইন)
     */
    private function apply_runtime_hardening( $settings ) {
        // সেটিংস পেজে থাকা অবস্থায় প্রোটেকশন সাময়িকভাবে বন্ধ রাখুন
        if ( is_admin() && isset($_GET['page']) && $_GET['page'] === 'rs-advance-security' ) {
            return; 
        }
        
        // Disable File Mods অপশন একটিভ থাকলে কনস্ট্যান্ট ডিফাইন করুন
        if ( ! empty( $settings['disable_install_update'] ) ) {
            if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
                define( 'DISALLOW_FILE_MODS', true );
            }
        }
        
        // Disable File Editor অপশন একটিভ থাকলে কনস্ট্যান্ট ডিফাইন করুন
        if ( ! empty( $settings['sys_tweaks'] ) ) {
            if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
                define( 'DISALLOW_FILE_EDIT', true );
            }
        }
    }

    public function restrict_login_method( $user, $username, $password ) {
        $settings = get_option( 'rs_security_settings', [] );
        $mode = isset($settings['login_restriction']) ? $settings['login_restriction'] : 'both';
        if ( $mode === 'both' || empty($username) ) return $user;
        $is_email = is_email( $username );
        if ( $mode === 'email_only' && ! $is_email ) return new WP_Error( 'invalid_login', '<strong>ERROR</strong>: Login with username disabled.' );
        if ( $mode === 'username_only' && $is_email ) return new WP_Error( 'invalid_login', '<strong>ERROR</strong>: Login with email disabled.' );
        return $user;
    }

    public function enforce_unique_nickname( $errors, $update, $user ) {
        $settings = get_option( 'rs_security_settings', [] );
        if ( empty($settings['force_unique_nickname']) ) return;
        $nickname = isset($_POST['nickname']) ? sanitize_text_field($_POST['nickname']) : '';
        if ( ! empty( $nickname ) ) {
            $args = [ 'meta_key' => 'nickname', 'meta_value' => $nickname, 'exclude' => [$user->ID], 'number' => 1 ];
            if ( ! empty( get_users($args) ) ) $errors->add( 'nickname_exists', '<strong>ERROR</strong>: Nickname already taken.' );
        }
    }

    public function disable_empty_author_archives() {
        $settings = get_option( 'rs_security_settings', [] );
        if ( empty($settings['disable_extra_archives']) ) return;
        if ( is_author() ) {
            $author = get_queried_object();
            if ( isset($author->ID) && count_user_posts( $author->ID ) === 0 ) { wp_redirect( home_url() ); exit; }
        }
    }

    public function recaptcha_scripts() {
        $settings = get_option( 'rs_security_settings', [] );
        if ( ! empty( $settings['recaptcha'] ) && ! empty( $settings['recaptcha_site_key'] ) ) {
            wp_enqueue_script( 'rs-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true );
        }
    }
    public function recaptcha_display() {
        $settings = get_option( 'rs_security_settings', [] );
        $site_key = isset( $settings['recaptcha_site_key'] ) ? $settings['recaptcha_site_key'] : '';
        if ( ! empty( $settings['recaptcha'] ) && ! empty( $site_key ) ) {
            echo '<div class="g-recaptcha" data-sitekey="' . esc_attr( $site_key ) . '" style="margin-bottom:15px;"></div>';
        }
    }
    public function recaptcha_verify( $user, $username, $password ) {
        $settings = get_option( 'rs_security_settings', [] );
        $secret_key = isset( $settings['recaptcha_secret_key'] ) ? $settings['recaptcha_secret_key'] : '';
        if ( empty( $settings['recaptcha'] ) || empty( $secret_key ) ) return $user;
        if ( isset( $_POST['g-recaptcha-response'] ) ) {
            $response = sanitize_text_field( $_POST['g-recaptcha-response'] );
            $verify = wp_remote_get( "https://www.google.com/recaptcha/api/siteverify?secret={$secret_key}&response={$response}" );
            $body = json_decode( wp_remote_retrieve_body( $verify ) );
            if ( ! isset( $body->success ) || ! $body->success ) return new WP_Error( 'recaptcha_failed', '<strong>ERROR</strong>: reCAPTCHA failed.' );
        }
        return $user;
    }

    public function control_xmlrpc_status( $is_enabled ) {
        $settings = get_option( 'rs_security_settings', [] );
        return ( isset($settings['xmlrpc_mode']) && $settings['xmlrpc_mode'] === 'disable_all' ) ? false : $is_enabled;
    }
    public function control_xmlrpc_methods( $methods ) {
        $settings = get_option( 'rs_security_settings', [] );
        if ( isset($settings['xmlrpc_mode']) && $settings['xmlrpc_mode'] === 'disable_pingbacks' ) {
            unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
        }
        return $methods;
    }
    public function restrict_rest_api( $result ) {
        if ( ! empty( $result ) ) return $result;
        
        $settings = get_option( 'rs_security_settings', [] );
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        
        // Block all REST API for non-logged-in users if restricted mode
        if ( isset($settings['rest_api_mode']) && $settings['rest_api_mode'] === 'restricted' && ! is_user_logged_in() ) {
            // Whitelist essential public endpoints (like forms)
            $allowed_endpoints = [
                '/wp-json/contact-form-7/',
                '/wp-json/wpforms/',
                '/wp-json/fluentform/',
                '/wp-json/elementor/',
                '/wp-json/gf/', // Gravity Forms
                '/wp-json/frm/', // Formidable
                '/wp-json/ninja-forms/',
            ];
            
            $is_allowed = false;
            foreach ( $allowed_endpoints as $endpoint ) {
                if ( strpos( $request_uri, $endpoint ) !== false ) {
                    $is_allowed = true;
                    break;
                }
            }
            
            if ( ! $is_allowed ) {
                return new WP_Error( 'rest_forbidden', 'REST API Restricted by Royal Sentinel.', array( 'status' => 401 ) );
            }
        }
        
        // Block user enumeration endpoint
        if ( ! empty( $settings['rest_block_user_enum'] ) && ! is_user_logged_in() ) {
            $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
            
            // Check for /wp/v2/users endpoint
            if ( preg_match( '/\/wp\/v2\/users/i', $request_uri ) || 
                 preg_match( '/\/wp-json\/wp\/v2\/users/i', $request_uri ) ) {
                return new WP_Error( 'rest_user_enum_blocked', 'User enumeration is blocked.', array( 'status' => 403 ) );
            }
        }
        
        return $result;
    }

    public function update_htaccess_rules( $old_value, $new_value ) {
        $this->process_root_htaccess( $new_value );
        $this->process_subdirectory_htaccess( $new_value );
    }

    private function process_root_htaccess( $settings ) {
        $htaccess_path = ABSPATH . '.htaccess';
        
        // 1. Check permissions thoroughly before reading/writing
        if ( ! file_exists( $htaccess_path ) ) {
            return;
        }
        if ( ! is_writable( $htaccess_path ) ) {
            error_log('Royal Sentinel Hardening: .htaccess file not writable at ' . $htaccess_path);
            return; // Skip writing if not writable
        }

        $rules = [];
        $rules[] = '# BEGIN RoyalSentinel';

        if ( ! empty( $settings['protect_system_files'] ) ) {
            $rules[] = '<FilesMatch "^(readme\.html|readme\.txt|wp-config\.php|install\.php|\.htaccess)">';
            $rules[] = 'Order allow,deny';
            $rules[] = 'Deny from all';
            $rules[] = '</FilesMatch>';
        }
        if ( ! empty( $settings['disable_directory_browsing'] ) ) {
            $rules[] = 'Options -Indexes';
        }
        if ( ! empty( $settings['ssl_enforce'] ) ) {
            $rules[] = '<IfModule mod_rewrite.c>';
            $rules[] = 'RewriteEngine On';
            $rules[] = 'RewriteCond %{HTTPS} off';
            $rules[] = 'RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]';
            $rules[] = '</IfModule>';
        }
        $headers_rules = [];
        if ( ! empty( $settings['config_hardening'] ) ) {
            $headers_rules[] = 'Header set X-XSS-Protection "1; mode=block"';
            $headers_rules[] = 'Header set X-Content-Type-Options "nosniff"';
            $headers_rules[] = 'Header set X-Frame-Options "SAMEORIGIN"';
        }
        if ( ! empty( $settings['header_hide_powered_by'] ) || ! empty( $settings['config_hardening'] ) ) {
            $rules[] = 'ServerSignature Off';
            $headers_rules[] = 'Header unset X-Powered-By';
            $headers_rules[] = 'Header always unset X-Powered-By';
            $headers_rules[] = 'Header unset X-Turbo-Charged-By';
            $headers_rules[] = 'Header always unset X-Turbo-Charged-By';
            $headers_rules[] = 'Header unset Server';
            $headers_rules[] = 'Header always unset Server';
        }
        if ( ! empty( $headers_rules ) ) {
            $rules[] = '<IfModule mod_headers.c>';
            foreach ( $headers_rules as $hr ) {
                $rules[] = $hr;
            }
            $rules[] = '</IfModule>';
        }

        $rules[] = '# END RoyalSentinel';
        $rules_content = implode( "\n", $rules );

        $current_content = file_get_contents( $htaccess_path );
        
        // Check if our block already exists
        if ( strpos( $current_content, '# BEGIN RoyalSentinel' ) !== false ) {
            $pattern = '/# BEGIN RoyalSentinel.*?# END RoyalSentinel/s';
            $new_content = preg_replace( $pattern, $rules_content, $current_content );
        } else {
            // Append rules to the end
            $new_content = $current_content . "\n\n" . $rules_content;
        }
        
        // Perform the write and check for failure
        if ( ! file_put_contents( $htaccess_path, $new_content ) ) {
            error_log('Royal Sentinel Hardening: Failed to write to .htaccess after generating rules.');
        }

        // Always flush rewrite rules after .htaccess modifications involving WordPress rules
        if ( ! empty( $settings['hide_login'] ) || ! empty( $settings['ssl_enforce'] ) ) {
             flush_rewrite_rules();
        }
    }

    private function process_subdirectory_htaccess( $settings ) {
        $block_rule = "Options -Indexes\n<Files *.php>\nDeny from all\n</Files>";
        $directories = [
            'disable_php_uploads' => WP_CONTENT_DIR . '/uploads/.htaccess',
            'disable_php_plugins' => WP_PLUGIN_DIR . '/.htaccess',
            'disable_php_themes'  => WP_CONTENT_DIR . '/themes/.htaccess',
        ];

        foreach ( $directories as $key => $file_path ) {
            $dir = dirname($file_path);

            if ( ! empty( $settings[$key] ) ) {
                if ( ! is_dir($dir) ) continue; 

                // Try to create/write the file
                if ( ! file_put_contents( $file_path, $block_rule ) ) {
                    error_log('Royal Sentinel Hardening: Failed to write PHP block rule to ' . $file_path);
                }

            } else {
                // Try to delete the file only if it exists and contains our block rule
                if ( file_exists( $file_path ) ) {
                    $content = file_get_contents( $file_path );
                    
                    // Only delete if it matches the generated rule (to avoid deleting user files)
                    if ( strpos( $content, 'Deny from all' ) !== false && strpos( $content, 'Options -Indexes' ) !== false ) {
                        // Deleting the file requires write permission on the directory
                        if (! unlink( $file_path )) {
                             error_log('Royal Sentinel Hardening: Failed to unlink PHP block rule file ' . $file_path);
                        }
                    }
                }
            }
        }
    }
}

new RS_Hardening_Logic();