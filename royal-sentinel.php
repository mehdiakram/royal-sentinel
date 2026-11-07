<?php
/*
Plugin Name: Royal Sentinel
Description: Royal Sentinel is an all-in-one security and performance optimization toolkit designed to keep your WordPress site safe and lean.
Version: 26.11.08
Author: Royal Technologies
Author URI: https://www.royaltechbd.com
Requires at least: 5.8
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: royal-sentinel
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * =================================================================
 * 1. DEFINE GLOBAL CONSTANTS
 * =================================================================
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/database.php';

if ( ! defined( 'ROYALSENTINEL_VERSION' ) ) {
    $plugin_data = get_file_data( __FILE__, array( 'version' => 'Version' ) );
    define( 'ROYALSENTINEL_VERSION', !empty( $plugin_data['version'] ) ? $plugin_data['version'] : '26.11.08' );
}

define( 'ROYALSENTINEL_PLUGIN_FILE', __FILE__ );
define( 'ROYALSENTINEL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ROYALSENTINEL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Global DB Table Name
global $wpdb;
define( 'RS_TABLE_BADIPS', RS_Database::get_table_name('badips') );

/**
 * =================================================================
 * 2. INCLUDE FILES
 * =================================================================
 */
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/ip-utils.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/ai-control-handler.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/cloudflare-api.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/countries.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/enqueue.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/admin-menu.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/login-watcher.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/scan-engine.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/vuln-scanner.php';

require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/cf-ie-handler.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/cf-sync.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/cf-domain-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/hide-login.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/db-prefix.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/user-hardening.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/local-ban.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/logic-2fa.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/login-extras.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/login-protection.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/firewall-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/hardening-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/backup-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/monitoring-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/tweaks-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/malware-scanner-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/honeypot-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/honeypot-integrations/loader.php';

// New Security Modules
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/security-headers-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/realtime-defense-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/ip-whitelist-ajax.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/session-management-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/geoblocking-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/securitytxt-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/audit-log-logic.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/magic-login.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/turnstile-logic.php';

// Passkeys Module initialization (native PHP — no vendor dependency)
add_action( 'plugins_loaded', function() {
    $rs_settings = get_option('rs_security_settings', array());
    if ( ! empty( $rs_settings['webauthn_passkeys'] ) ) {
        require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/modules/passkeys/class-passkeys.php';
        RS_Passkeys_Module::get_instance();
    }
} );


/**
 * =================================================================
 * 3. LICENSE MANAGEMENT
 * =================================================================
 */
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/royalsentinel-license-functions.php';
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/license-admin-handler.php';

if ( ! defined('ROYALSENTINEL_LICENSE_ACTIVE') ) {
    /**
     * [FIX] License check কে synchronous HTTP block থেকে মুক্ত করা হয়েছে।
     * আগে royalsentinel_is_license_valid() সরাসরি কল হতো যা প্রতিটি request-এ
     * auth.royaltechbd.com-এ 10s timeout-সহ HTTP POST করত — এটিই Cloudflare 524-এর কারণ ছিল।
     *
     * এখন শুধু cached status (DB option) পড়া হচ্ছে — কোনো HTTP call নেই।
     * প্রকৃত re-validation wp_cron (background) এ চলবে।
     */
    $rs_cached_status = get_option( 'royalsentinel_license_status', 'inactive' );
    define( 'ROYALSENTINEL_LICENSE_ACTIVE', ( $rs_cached_status === 'active' ) );
}

/**
 * [FIX] License re-validation — background cron-এ চলে, page load block করে না।
 * প্রতি ১২ ঘণ্টায় একবার async HTTP call করে license verify করে cache update করে।
 *
 * [BUG FIX #1] royalsentinel_cron_license_ping()-এ সরাসরি bind করা হয়েছে।
 * আগে royalsentinel_run_bg_license_check() কল হতো যেটা শুধু local cache পড়ত —
 * কোনো HTTP server ping করত না। ফলে license cache কখনো refresh হতো না।
 */
add_action( 'royalsentinel_bg_license_check', 'royalsentinel_cron_license_ping' );

/**
 * [BUG FIX #5] cron scheduling plugins_loaded hook-এ নিয়ে যাওয়া হয়েছে।
 * আগে এটা প্রতিটি request-এ সরাসরি চলত — এখন proper hook-এ।
 */
add_action( 'plugins_loaded', function() {
    if ( ! wp_next_scheduled( 'royalsentinel_bg_license_check' ) ) {
        wp_schedule_event( time(), 'twicedaily', 'royalsentinel_bg_license_check' );
    }
} );


/**
 * =================================================================
 * 4. PLUGIN UPDATER
 * =================================================================
 */
require_once ROYALSENTINEL_PLUGIN_DIR . 'includes/updater.php';

/**
 * Initialize the plugin updater.
 */
function royal_sentinel_initialize_updater() {

    if ( class_exists( 'Royal_Sentinel_Updater' ) ) {
        $update_url = 'https://raw.githubusercontent.com/mehdiakram/royal-sentinel/main/royal-sentinel.json';
        new Royal_Sentinel_Updater( ROYALSENTINEL_PLUGIN_FILE, $update_url );
    }
}
add_action( 'init', 'royal_sentinel_initialize_updater' );

/**
 * Force update check via URL parameter.
 */
function royal_sentinel_force_update_check() {
    if ( isset( $_GET['royal_sentinel_check_update'] ) && current_user_can( 'update_plugins' ) ) {
        delete_site_transient( 'update_plugins' );
        wp_safe_redirect( admin_url( 'plugins.php' ) );
        exit;
    }
}
add_action( 'admin_init', 'royal_sentinel_force_update_check' );


// Activation Hook for DB
register_activation_hook( __FILE__, array( 'RS_Database', 'create_tables' ) );

// Auto-run DB update if version changes
add_action( 'plugins_loaded', function() {
    if ( get_option( 'rs_db_version' ) !== '1.3.2' ) {
        RS_Database::create_tables();
    }
});

// Load Text Domain
add_action( 'plugins_loaded', 'rs_load_textdomain' );
function rs_load_textdomain() {
    load_plugin_textdomain( 'royal-sentinel', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}