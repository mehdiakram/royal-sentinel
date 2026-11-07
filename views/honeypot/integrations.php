<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$rs_hp_integrations = get_option( 'rs_honeypot_integrations', array() );

// Define all integrations with categories
$integration_groups = array(
    __( 'WordPress Core', 'royal-sentinel' ) => array(
        'wp_comments'      => array( 'name' => __( 'WP Comments', 'royal-sentinel' ),      'desc' => __( 'Protect comment forms from spam bots', 'royal-sentinel' ) ),
        'wp_registration'  => array( 'name' => __( 'WP Registration', 'royal-sentinel' ),  'desc' => __( 'Protect user registration forms', 'royal-sentinel' ) ),
    ),
    __( 'Form Builders', 'royal-sentinel' ) => array(
        'contact_form_7'   => array( 'name' => 'Contact Form 7',   'desc' => __( 'Most popular contact form plugin', 'royal-sentinel' ) ),
        'wpforms'          => array( 'name' => 'WP Forms',         'desc' => __( 'User-friendly form builder', 'royal-sentinel' ) ),
        'gravity_forms'    => array( 'name' => 'Gravity Forms',    'desc' => __( 'Advanced form builder (Non-AJAX)', 'royal-sentinel' ) ),
        'formidable_forms' => array( 'name' => 'Formidable Forms', 'desc' => __( 'Form builder for WordPress', 'royal-sentinel' ) ),
        'caldera_forms'    => array( 'name' => 'Caldera Forms',    'desc' => __( 'Drag and drop form builder', 'royal-sentinel' ) ),
        'toolset_forms'    => array( 'name' => 'Toolset Forms',    'desc' => __( 'CRED forms integration', 'royal-sentinel' ) ),
        'elementor_form'   => array( 'name' => 'Elementor Form',   'desc' => __( 'Elementor Pro form widget', 'royal-sentinel' ) ),
        'fluent_forms'     => array( 'name' => 'Fluent Forms',     'desc' => __( 'Fast and lightweight forms', 'royal-sentinel' ) ),
        'ninja_forms'      => array( 'name' => 'Ninja Forms',      'desc' => __( 'Free form builder plugin', 'royal-sentinel' ) ),
        'jetform_builder'  => array( 'name' => 'JetForm Builder',  'desc' => __( 'JetEngine form builder', 'royal-sentinel' ) ),
        'quform'           => array( 'name' => 'QuForm',           'desc' => __( 'Premium form builder', 'royal-sentinel' ) ),
        'ws_form'          => array( 'name' => 'WS Form',          'desc' => __( 'Advanced WordPress forms', 'royal-sentinel' ) ),
        'sure_forms'       => array( 'name' => 'Sure Forms',       'desc' => __( 'SureCart form builder', 'royal-sentinel' ) ),
        'everest_forms'    => array( 'name' => 'Everest Forms',    'desc' => __( 'Contact form builder', 'royal-sentinel' ) ),
        'formcraft'        => array( 'name' => 'FormCraft',        'desc' => __( 'Premium drag and drop forms', 'royal-sentinel' ) ),
        'html_forms'       => array( 'name' => 'HTML Forms',       'desc' => __( 'Simple HTML form plugin', 'royal-sentinel' ) ),
    ),
    __( 'eCommerce', 'royal-sentinel' ) => array(
        'woocommerce'      => array( 'name' => 'WooCommerce',      'desc' => __( 'Checkout & registration protection', 'royal-sentinel' ) ),
        'edd'              => array( 'name' => 'Easy Digital Downloads', 'desc' => __( 'Digital product store', 'royal-sentinel' ) ),
    ),
    __( 'Membership & Community', 'royal-sentinel' ) => array(
        's2_members'       => array( 'name' => 'S2 Members',       'desc' => __( 'Membership & content restriction', 'royal-sentinel' ) ),
        'ultimate_member'  => array( 'name' => 'Ultimate Member',  'desc' => __( 'Community & user profiles', 'royal-sentinel' ) ),
        'buddypress'       => array( 'name' => 'BuddyPress',       'desc' => __( 'Social networking plugin', 'royal-sentinel' ) ),
        'buddyboss'        => array( 'name' => 'BuddyBoss',        'desc' => __( 'Community platform', 'royal-sentinel' ) ),
        'bbpress'          => array( 'name' => 'BB Press Forum',   'desc' => __( 'Forum topics & replies', 'royal-sentinel' ) ),
        'theme_my_login'   => array( 'name' => 'Theme My Login',   'desc' => __( 'Frontend login pages', 'royal-sentinel' ) ),
        'yith_login'       => array( 'name' => 'YITH Easy Login & Register', 'desc' => __( 'WooCommerce popup login', 'royal-sentinel' ) ),
    ),
    __( 'Email Marketing', 'royal-sentinel' ) => array(
        'mc4wp'            => array( 'name' => 'MC4WP: Mailchimp', 'desc' => __( 'Mailchimp for WordPress', 'royal-sentinel' ) ),
        'mailpoet'         => array( 'name' => 'MailPoet',         'desc' => __( 'Email & newsletters', 'royal-sentinel' ) ),
    ),
    __( 'Page Builders', 'royal-sentinel' ) => array(
        'bricks_builder'   => array( 'name' => 'Bricks Builder',   'desc' => __( 'Visual site builder forms', 'royal-sentinel' ) ),
        'bricksforge'      => array( 'name' => 'Bricksforge Pro Form', 'desc' => __( 'Bricks addon forms', 'royal-sentinel' ) ),
        'divi_form'        => array( 'name' => 'Divi Theme Form',  'desc' => __( 'Elegant Themes Divi forms', 'royal-sentinel' ) ),
        'avia_enfold'      => array( 'name' => 'Avia Enfold Theme', 'desc' => __( 'Enfold theme forms', 'royal-sentinel' ) ),
        'toolset'          => array( 'name' => 'Toolset',          'desc' => __( 'General Toolset integration', 'royal-sentinel' ) ),
    ),
);

// Check if integration is enabled (default: all enabled)
function rs_is_integration_on( $slug, $integrations ) {
    if ( empty( $integrations ) ) {
        return true;
    }
    return isset( $integrations[ $slug ] ) && $integrations[ $slug ];
}
?>

<form method="post">
    <?php wp_nonce_field( 'rs_honeypot_save', '_wpnonce' ); ?>
    <input type="hidden" name="rs_save_integrations" value="1">

    <div style="margin-bottom: 20px;">
        <p class="description" style="font-size: 14px;">
            <?php esc_html_e( 'Enable or disable honeypot protection for specific form plugins. All integrations are enabled by default.', 'royal-sentinel' ); ?>
        </p>
        <p>
            <button type="button" id="rs_enable_all" class="button"><?php esc_html_e( 'Enable All', 'royal-sentinel' ); ?></button>
            <button type="button" id="rs_disable_all" class="button"><?php esc_html_e( 'Disable All', 'royal-sentinel' ); ?></button>
        </p>
    </div>

    <?php foreach ( $integration_groups as $group_name => $integrations ): ?>
        <div class="rs-section-box" style="border-left: 4px solid #27ae60;">
            <h4 class="rs-section-title" style="color: #27ae60;"><?php echo esc_html( $group_name ); ?></h4>
            
            <div class="rs-hp-grid">
                <?php foreach ( $integrations as $slug => $info ): 
                    $is_on = rs_is_integration_on( $slug, $rs_hp_integrations );
                ?>
                    <div class="rs-hp-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <h4 style="margin: 0 0 5px;"><?php echo esc_html( $info['name'] ); ?></h4>
                                <p style="margin: 0; color: #666; font-size: 12px;"><?php echo esc_html( $info['desc'] ); ?></p>
                            </div>
                            <label class="rs-switch">
                                <input type="checkbox" name="integrations[<?php echo esc_attr($slug); ?>]" 
                                       value="1" class="rs-integration-toggle" <?php checked( true, $is_on ); ?>>
                                <span class="rs-slider round"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="margin-top: 20px;">
        <button type="submit" class="button button-primary button-large">
            <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span> <?php esc_html_e( 'Save Integrations', 'royal-sentinel' ); ?>
        </button>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    $('#rs_enable_all').on('click', function() {
        $('.rs-integration-toggle').prop('checked', true);
    });
    
    $('#rs_disable_all').on('click', function() {
        $('.rs-integration-toggle').prop('checked', false);
    });
});
</script>
