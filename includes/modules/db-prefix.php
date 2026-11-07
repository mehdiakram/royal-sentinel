<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_DB_Prefix_Changer {

    public function __construct() {
        add_action( 'admin_init', [ $this, 'process_prefix_change' ] );
    }

    public function process_prefix_change() {
        // ১. রিকোয়েস্ট ভ্যালিডেশন
        if ( ! isset( $_POST['rs_action_change_prefix'] ) ) {
            return;
        }
        
        // [FIXED] 2. Critical Check: এটি যেন সাধারণ সেটিং সেভের সময় না চলে।
        if ( isset($_POST['rs_save_security']) || isset($_POST['rs_save_tweaks']) ) {
            // A generic save is also in progress, stop the critical action to let the main form process
            return;
        }


        // ২. সিকিউরিটি চেক (Nonce & Permission)
        if ( ! check_admin_referer( 'rs_change_prefix_nonce', 'rs_prefix_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized action.' );
        }

        global $wpdb;
        $old_prefix = $wpdb->prefix;
        $new_prefix = sanitize_text_field( $_POST['new_db_prefix'] );

        // ৩. ইনপুট ভ্যালিডেশন
        if ( empty( $new_prefix ) || $new_prefix === $old_prefix ) {
            $this->redirect_with_message( 'error', 'Prefix cannot be empty or same as current.' );
        }

        // প্রিফিক্স ফরম্যাট চেক (শুধুমাত্র a-z, 0-9 এবং _ এলাউড, শেষে _ থাকতে হবে)
        if ( ! preg_match( '/^[a-zA-Z0-9_]+_$/', $new_prefix ) ) {
            $this->redirect_with_message( 'error', 'Invalid format. Use letters, numbers, and end with an underscore (e.g. wp_secure_).' );
        }

        // ৪. কনফিগ ফাইল রাইটেবল কিনা চেক
        $config_path = ABSPATH . 'wp-config.php';
        if ( ! is_writable( $config_path ) ) {
            $this->redirect_with_message( 'error', 'wp-config.php is not writable. Please check file permissions (e.g., set to 644) and retry.' );
            return;
        }

        // --- অপারেশন শুরু ---
        
        // ৫. টেবিল লিস্ট বের করা
        $tables = $wpdb->get_results( "SHOW TABLE STATUS LIKE '{$old_prefix}%'", ARRAY_N );
        
        if ( empty( $tables ) ) {
            $this->redirect_with_message( 'error', 'No tables found with the current prefix.' );
        }

        // ৬. প্রতিটি টেবিল রিনেম করা
        foreach ( $tables as $table ) {
            $table_name = $table[0];
            // নতুন নাম জেনারেট করা
            $new_table_name = substr_replace( $table_name, $new_prefix, 0, strlen( $old_prefix ) );
            
            // রিনেম কুইরি চালানো
            $wpdb->query( "RENAME TABLE `{$table_name}` TO `{$new_table_name}`" );
        }

        // ৭. options এবং usermeta টেবিলের ভেতরের ডাটা আপডেট করা
        // options টেবিল
        $wpdb->query( "UPDATE {$new_prefix}options SET option_name = '{$new_prefix}user_roles' WHERE option_name = '{$old_prefix}user_roles'" );

        // usermeta টেবিল
        $wpdb->query( "UPDATE {$new_prefix}usermeta SET meta_key = REPLACE(meta_key, '{$old_prefix}', '{$new_prefix}') WHERE meta_key LIKE '{$old_prefix}%'" );

        // ৮. wp-config.php ফাইল আপডেট করা
        $config_content = file_get_contents( $config_path );
        
        // Regex দিয়ে $table_prefix লাইন খুঁজে বের করে রিপ্লেস করা
        $new_config_content = preg_replace( 
            '/\$table_prefix\s*=\s*[\'"]' . preg_quote( $old_prefix, '/' ) . '[\'"];/', 
            '$table_prefix = \'' . $new_prefix . '\';', 
            $config_content 
        );

        if ( $new_config_content && $new_config_content !== $config_content ) {
            if ( file_put_contents( $config_path, $new_config_content ) === false ) {
                $this->redirect_with_message( 'error', 'Tables renamed but failed to write new prefix to wp-config.php. Please manually update $table_prefix.' );
            }
            else {
                $this->redirect_with_message( 'success', 'Database prefix changed! Please login again.' );
            }
        } else {
            // যদি কনফিগ ফাইল কোড দিয়ে আপডেট না করা যায়
            $this->redirect_with_message( 'error', 'Tables renamed but failed to update wp-config.php. Please manually update $table_prefix.' );
        }
    }

    // মেসেজ এবং রিডাইরেক্ট হ্যান্ডলার
    private function redirect_with_message( $type, $message ) {
        set_transient( 'rs_db_msg', [ 'type' => $type, 'text' => $message ], 45 );
        wp_redirect( admin_url( 'admin.php?page=rs-advance-security&tab=hardening' ) );
        exit;
    }
}

// মেসেজ দেখানোর হুক
add_action( 'admin_notices', function() {
    if ( $msg = get_transient( 'rs_db_msg' ) ) {
        $class = ( $msg['type'] === 'success' ) ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html( $msg['text'] ) . '</p></div>';
        delete_transient( 'rs_db_msg' );
    }
});

// ক্লাস ইনিশিয়েট করা
new RS_DB_Prefix_Changer();