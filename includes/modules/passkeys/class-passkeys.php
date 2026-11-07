<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RS_Passkeys_Module {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
        $this->init_rest_api();
    }

    private function init_hooks() {
        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'login_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

        // Add Passkey login to default WordPress login form
        add_action( 'login_form', array( $this, 'add_passkey_login_button' ) );
        
        // Elementor shortcode fallback
        add_shortcode( 'rs_passkey_login', array( $this, 'render_passkey_shortcode' ) );

        // User Profile Fields for Passkeys
        add_action( 'show_user_profile', array( $this, 'render_user_profile_passkeys' ) );
        add_action( 'edit_user_profile', array( $this, 'render_user_profile_passkeys' ) );
    }

    private function init_rest_api() {
        require_once plugin_dir_path( __FILE__ ) . 'class-rest-api.php';
        RS_Passkeys_REST_API::get_instance();
    }

    public function enqueue_frontend_scripts() {
        $assets_url = ROYALSENTINEL_PLUGIN_URL . 'includes/modules/passkeys/assets/';
        $version    = ROYALSENTINEL_VERSION;

        wp_enqueue_style( 'rs-passkeys-css', $assets_url . 'passkeys.css', array(), $version );

        wp_enqueue_script( 'rs-webauthn-base64-js', $assets_url . 'base64url.js', array(), $version, true );
        wp_enqueue_script( 'rs-passkeys-js', $assets_url . 'passkeys.js', array('jquery', 'rs-webauthn-base64-js'), $version, true );

        wp_localize_script( 'rs-passkeys-js', 'rsPasskeysOpts', array(
            'restUrl'   => esc_url_raw( rest_url() ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'i18n'      => array(
                'error'        => __( 'An error occurred during authentication.', 'royal-sentinel' ),
                'successReg'   => __( 'Passkey registered successfully!', 'royal-sentinel' ),
                'successLogin' => __( 'Login successful! Redirecting...', 'royal-sentinel' ),
                'notSupported' => __( 'Your browser does not support WebAuthn Passkeys.', 'royal-sentinel' )
            )
        ) );
    }

    public function enqueue_admin_scripts( $hook ) {
        if ( 'profile.php' === $hook || 'user-edit.php' === $hook ) {
            $this->enqueue_frontend_scripts();
        }
    }

    public function add_passkey_login_button() {
        // Output the login button HTML for the default WP login form
        include plugin_dir_path( __FILE__ ) . 'views/login-button.php';
    }

    public function render_passkey_shortcode() {
        ob_start();
        include plugin_dir_path( __FILE__ ) . 'views/login-button.php';
        return ob_get_clean();
    }

    public function render_user_profile_passkeys( $user ) {
        if ( ! current_user_can( 'edit_user', $user->ID ) ) {
            return;
        }
        $passkeys = get_user_meta( $user->ID, 'rs_passkeys', true );
        if ( ! is_array( $passkeys ) ) {
            $passkeys = array();
        }
        
        include plugin_dir_path( __FILE__ ) . 'views/user-profile.php';
    }
}
