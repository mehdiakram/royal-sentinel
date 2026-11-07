<?php
/**
 * Admin License View (Standalone Page)
 * Shows activation form when inactive.
 * Shows license info, deactivation button option when active.
 *
 * @package    RoyalSentinel
 * @subpackage RoyalSentinel/views
 * @author     S M Mehdi Akram (Royal)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// [BUG FIX #2] SweetAlert2 আলাদাভাবে enqueue — license.js-এ Swal dependency দরকার
wp_enqueue_style( 'rs-swal-css', ROYALSENTINEL_PLUGIN_URL . 'lib/sweetalert2/sweetalert2.min.css', array(), ROYALSENTINEL_VERSION );
wp_enqueue_script( 'rs-swal-js', ROYALSENTINEL_PLUGIN_URL . 'lib/sweetalert2/sweetalert2.all.min.js', array(), ROYALSENTINEL_VERSION, true );

// Enqueue license JavaScript and localize script
wp_enqueue_script( 
    'rs-license-js', 
    ROYALSENTINEL_PLUGIN_URL . 'assets/js/license.js', 
    array('jquery', 'rs-swal-js'),  // ← rs-swal-js dependency যোগ করা হয়েছে
    ROYALSENTINEL_VERSION, 
    true 
);

wp_localize_script( 'rs-license-js', 'rsLicenseData', array(
    'nonce' => wp_create_nonce('rs_license_nonce'),
    'ajaxurl' => admin_url('admin-ajax.php')
));

// Load license details
$license_settings = get_option( 'royalsentinel_license_settings', [ 'royalsentinel_license_key' => '' ] );
$license_key = $license_settings['royalsentinel_license_key'] ?? '';
$is_active = defined('ROYALSENTINEL_LICENSE_ACTIVE') ? ROYALSENTINEL_LICENSE_ACTIVE : false;

// --- Process Status Messages (for deactivation) ---
$status = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
$admin_notice = '';

if ($status === 'deactivated') {
     $admin_notice = '<div id="message" class="notice notice-warning is-dismissible"><p>' . esc_html__( 'License deactivated successfully.', 'royal-sentinel' ) . '</p></div>';
}
// --- End Status Messages ---

?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php echo $admin_notice; // Display deactivation message ?>

    <div class="card">
        <div class="inside">
            <?php if ( ! $is_active ): ?>
                
                <form id="rs-license-activation-form" method="POST">
                    
                    <p><?php echo esc_html(sprintf(__( 'Enter your license key to activate Royal Sentinel.', 'royal-sentinel' ) ) );?></p>
                    
                    
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="royalsentinel-license-key"><?php esc_html_e( 'License Key', 'royal-sentinel' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="royalsentinel-license-key" name="royalsentinel_license_key" value="" class="regular-text" required placeholder="<?php esc_attr_e('Enter your license key', 'royal-sentinel'); ?>">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <?php submit_button( esc_html__( 'Activate License', 'royal-sentinel' ) ); ?>
                
                </form>


            
            <?php else: ?>

                <p><?php esc_html_e( 'Your license is currently active.', 'royal-sentinel' ); ?></p>
                 
                 <table class="form-table">
                    <tbody>
                         <tr>
                            <th scope="row"><?php esc_html_e( 'License Key', 'royal-sentinel' ); ?></th>
                            <td>
                                <code><?php echo esc_html( substr($license_key, 0, 5) . str_repeat('*', max(0, strlen($license_key) - 10)) . substr($license_key, -5) ); ?></code>
                            </td>
                        </tr>
                         <tr>
                            <th scope="row"><?php esc_html_e( 'Status', 'royal-sentinel' ); ?></th>
                            <td>
                                <span style="color: green; font-weight: bold;"><?php esc_html_e( 'Active', 'royal-sentinel' ); ?></span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                     <input type="hidden" name="action" value="royalsentinel_deactivate_license">
                     <?php wp_nonce_field( 'royalsentinel_license_deactivation_nonce' ); ?>
                     <?php submit_button( esc_html__( 'Deactivate License', 'royal-sentinel' ), 'delete button-secondary' ); ?>
                     <p class="description"><?php esc_html_e('Deactivating the license will disable plugin features until reactivated.', 'royal-sentinel'); ?></p>
                </form>
            
            <?php endif; ?>


        </div>
    </div>
</div>
