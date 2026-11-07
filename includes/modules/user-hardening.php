<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_User_Hardening {

    public function __construct() {
        add_action( 'admin_init', [ $this, 'process_user_hardening' ] );
    }

    public function process_user_hardening() {
        // ১. রিকোয়েস্ট ভ্যালিডেশন
        if ( ! isset( $_POST['rs_user_action'] ) ) {
            return;
        }

        // [FIXED] 2. Critical Check: এটি যেন সাধারণ সেটিং সেভের সময় না চলে।
        if ( isset($_POST['rs_save_security']) || isset($_POST['rs_save_tweaks']) ) {
            return;
        }

        if ( ! check_admin_referer( 'rs_user_hardening_nonce', 'rs_user_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized action.' );
        }

        global $wpdb;
        $action = sanitize_text_field( $_POST['rs_user_action'] );

        // === ACTION 1: CHANGE USER ID 1 ===
        if ( $action === 'change_id_1' ) {
            $user_one = get_user_by( 'ID', 1 );
            
            if ( ! $user_one ) {
                $this->redirect_with_message( 'error', 'User ID 1 not found.' );
            }

            // নতুন র‍্যান্ডম আইডি জেনারেট (1000 থেকে 99999 এর মধ্যে)
            $new_id = rand( 1000, 99999 );
            
            // আইডি এক্সিস্ট করে কিনা চেক
            while ( get_user_by( 'ID', $new_id ) ) {
                $new_id = rand( 1000, 99999 );
            }

            // টেবিল আপডেট করা
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->users} SET ID = %d WHERE ID = 1", $new_id ) );
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET user_id = %d WHERE user_id = 1", $new_id ) );
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_author = %d WHERE post_author = 1", $new_id ) );
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->comments} SET user_id = %d WHERE user_id = 1", $new_id ) );
            
            // সফল হলে লগআউট করানো (কারণ সেশন আইডি চেঞ্জ হয়েছে)
            wp_logout();
            wp_redirect( wp_login_url() . '?rs_msg=id_changed' );
            exit;
        }

        // === ACTION 2: RENAME 'ADMIN' USER ===
        if ( $action === 'rename_admin' ) {
            $new_user = sanitize_user( $_POST['rs_new_username'] );
            $admin_user = get_user_by( 'login', 'admin' );

            if ( ! $admin_user ) {
                $this->redirect_with_message( 'error', 'User "admin" not found.' );
            }

            if ( empty($new_user) || username_exists( $new_user ) ) {
                $this->redirect_with_message( 'error', 'Username already exists or invalid.' );
            }

            // আপডেট কুইরি
            $wpdb->update( 
                $wpdb->users, 
                ['user_login' => $new_user], 
                ['ID' => $admin_user->ID] 
            );

            // সফল হলে রিডাইরেক্ট
            $this->redirect_with_message( 'success', 'User "admin" renamed to "' . esc_html($new_user) . '" successfully.' );
        }
    }

    private function redirect_with_message( $type, $message ) {
        set_transient( 'rs_user_msg', [ 'type' => $type, 'text' => $message ], 45 );
        wp_redirect( admin_url( 'admin.php?page=rs-advance-security&tab=hardening' ) );
        exit;
    }
}

// মেসেজ শো করার হুক
add_action( 'admin_notices', function() {
    if ( isset($_GET['rs_msg']) && $_GET['rs_msg'] == 'id_changed' ) {
        echo '<div class="notice notice-success"><p>User ID 1 changed successfully! Please login again.</p></div>';
    }
    if ( $msg = get_transient( 'rs_user_msg' ) ) {
        $class = ( $msg['type'] === 'success' ) ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html( $msg['text'] ) . '</p></div>';
        delete_transient( 'rs_user_msg' );
    }
});

new RS_User_Hardening();