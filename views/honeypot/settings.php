<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$rs_hp_field_name = get_option( 'rs_honeypot_field_name', '' );
$rs_hp_settings = get_option( 'rs_honeypot_settings', array() );

// Defaults
$error_message = isset( $rs_hp_settings['error_message'] ) ? $rs_hp_settings['error_message'] : __( 'Spam detected! Your submission has been blocked.', 'royal-sentinel' );
$time_check_enabled = isset( $rs_hp_settings['time_check_enabled'] ) ? $rs_hp_settings['time_check_enabled'] : 1;
$time_min_seconds = isset( $rs_hp_settings['time_min_seconds'] ) ? $rs_hp_settings['time_min_seconds'] : 3;
$time_max_seconds = isset( $rs_hp_settings['time_max_seconds'] ) ? $rs_hp_settings['time_max_seconds'] : 0;
$auto_block_enabled = isset( $rs_hp_settings['auto_block_enabled'] ) ? $rs_hp_settings['auto_block_enabled'] : 1;
$auto_block_threshold = isset( $rs_hp_settings['auto_block_threshold'] ) ? $rs_hp_settings['auto_block_threshold'] : 5;
$retention_days = isset( $rs_hp_settings['retention_days'] ) ? $rs_hp_settings['retention_days'] : 30;
$test_widget_disabled = isset( $rs_hp_settings['test_widget_disabled'] ) ? $rs_hp_settings['test_widget_disabled'] : 0;
?>

<form method="post">
    <?php wp_nonce_field( 'rs_honeypot_save', '_wpnonce' ); ?>
    <input type="hidden" name="rs_save_honeypot" value="1">

    <!-- General Settings -->
    <div class="rs-section-box" style="border-left: 4px solid #27ae60;">
        <h4 class="rs-section-title" style="color: #27ae60;">🍯 <?php esc_html_e( 'General Settings', 'royal-sentinel' ); ?></h4>
        
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e( 'Honeypot Field Name', 'royal-sentinel' ); ?></label></th>
                <td>
                    <input type="text" name="rs_honeypot_field_name" id="rs_hp_field_name" 
                           value="<?php echo esc_attr( $rs_hp_field_name ); ?>" 
                           class="regular-text" readonly style="width:200px;">
                    <button type="button" id="rs_regenerate_field" class="button" style="vertical-align:middle;">
                        <span class="dashicons dashicons-update" style="vertical-align:middle;"></span> <?php esc_html_e( 'Regenerate', 'royal-sentinel' ); ?>
                    </button>
                    <p class="description"><?php esc_html_e( 'Unique field name for honeypot trap. Regenerate periodically for extra security.', 'royal-sentinel' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Error Message', 'royal-sentinel' ); ?></label></th>
                <td>
                    <input type="text" name="error_message" 
                           value="<?php echo esc_attr( $error_message ); ?>" 
                           class="large-text" style="max-width:500px;">
                    <p class="description"><?php esc_html_e( 'Message shown to blocked spam bots (humans won\'t see this).', 'royal-sentinel' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e( 'Disable Test Widget', 'royal-sentinel' ); ?></label></th>
                <td>
                    <label class="rs-switch">
                        <input type="checkbox" name="test_widget_disabled" value="1" <?php checked(1, $test_widget_disabled); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                    <span style="margin-left:10px;"><?php esc_html_e( 'Hide test widget below forms (only visible to admins)', 'royal-sentinel' ); ?></span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Time-Based Validation -->
    <div class="rs-section-box" style="border-left: 4px solid #3498db;">
        <h4 class="rs-section-title" style="color: #3498db;">⏱️ <?php esc_html_e( 'Time-Based Validation', 'royal-sentinel' ); ?></h4>
        
        <div style="margin-bottom: 15px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="time_check_enabled" id="rs_time_check_toggle" value="1" <?php checked(1, $time_check_enabled); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_time_check_toggle" style="font-weight: 600;"><?php esc_html_e( 'Enable Time-Based Validation', 'royal-sentinel' ); ?></label>
            <p class="description" style="margin-left: 55px;"><?php esc_html_e( 'Block submissions that happen too quickly (bot behavior).', 'royal-sentinel' ); ?></p>
        </div>

        <div id="rs_time_settings" style="display: <?php echo $time_check_enabled ? 'block' : 'none'; ?>;">
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Minimum Time (seconds)', 'royal-sentinel' ); ?></label></th>
                    <td>
                        <input type="number" name="time_min_seconds" value="<?php echo esc_attr($time_min_seconds); ?>" min="0" max="60" class="small-text">
                        <p class="description"><?php esc_html_e( 'Block if form is submitted faster than this. Default: 3 seconds.', 'royal-sentinel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e( 'Maximum Time (seconds)', 'royal-sentinel' ); ?></label></th>
                    <td>
                        <input type="number" name="time_max_seconds" value="<?php echo esc_attr($time_max_seconds); ?>" min="0" class="small-text">
                        <p class="description"><?php esc_html_e( 'Block if form takes longer than this. Set 0 for unlimited.', 'royal-sentinel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Auto IP Blocking -->
    <div class="rs-section-box" style="border-left: 4px solid #e74c3c;">
        <h4 class="rs-section-title" style="color: #e74c3c;">🚫 <?php esc_html_e( 'Auto IP Blocking', 'royal-sentinel' ); ?></h4>
        
        <div style="margin-bottom: 15px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="auto_block_enabled" id="rs_auto_block_toggle" value="1" <?php checked(1, $auto_block_enabled); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_auto_block_toggle" style="font-weight: 600;"><?php esc_html_e( 'Enable Auto IP Blocking', 'royal-sentinel' ); ?></label>
            <p class="description" style="margin-left: 55px;"><?php esc_html_e( 'Automatically ban IPs that trigger honeypot multiple times.', 'royal-sentinel' ); ?></p>
        </div>

        <div id="rs_auto_block_settings" style="display: <?php echo $auto_block_enabled ? 'block' : 'none'; ?>;">
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e( 'Block After X Attempts', 'royal-sentinel' ); ?></label></th>
                    <td>
                        <input type="number" name="auto_block_threshold" value="<?php echo esc_attr($auto_block_threshold); ?>" min="1" max="100" class="small-text">
                        <p class="description"><?php esc_html_e( 'Number of spam attempts within 24 hours before permanent ban.', 'royal-sentinel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Log Retention -->
    <div class="rs-section-box" style="border-left: 4px solid #9b59b6;">
        <h4 class="rs-section-title" style="color: #9b59b6;">🗑️ <?php esc_html_e( 'Log Retention', 'royal-sentinel' ); ?></h4>
        
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e( 'Keep Logs For', 'royal-sentinel' ); ?></label></th>
                <td>
                    <select name="retention_days" class="regular-text" style="width:150px;">
                        <option value="7" <?php selected(7, $retention_days); ?>><?php esc_html_e( '7 Days', 'royal-sentinel' ); ?></option>
                        <option value="14" <?php selected(14, $retention_days); ?>><?php esc_html_e( '14 Days', 'royal-sentinel' ); ?></option>
                        <option value="30" <?php selected(30, $retention_days); ?>><?php esc_html_e( '30 Days', 'royal-sentinel' ); ?></option>
                        <option value="60" <?php selected(60, $retention_days); ?>><?php esc_html_e( '60 Days', 'royal-sentinel' ); ?></option>
                        <option value="90" <?php selected(90, $retention_days); ?>><?php esc_html_e( '90 Days', 'royal-sentinel' ); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Logs older than this will be automatically deleted daily.', 'royal-sentinel' ); ?></p>
                </td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 20px;">
        <button type="submit" class="button button-primary button-large">
            <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span> <?php esc_html_e( 'Save Settings', 'royal-sentinel' ); ?>
        </button>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    // Toggle sections
    $('#rs_time_check_toggle').on('change', function() {
        $('#rs_time_settings').slideToggle(this.checked);
    });
    
    $('#rs_auto_block_toggle').on('change', function() {
        $('#rs_auto_block_settings').slideToggle(this.checked);
    });
    
    // Regenerate field name
    $('#rs_regenerate_field').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'rs_honeypot_regenerate_field',
            _wpnonce: '<?php echo wp_create_nonce("rs_honeypot_nonce"); ?>'
        }, function(resp) {
            btn.prop('disabled', false);
            if (resp.success) {
                $('#rs_hp_field_name').val(resp.data.field_name);
                Swal.fire({
                    icon: 'success',
                    title: '<?php echo esc_js( __( 'Field Regenerated!', 'royal-sentinel' ) ); ?>',
                    text: '<?php echo esc_js( __( 'New field name:', 'royal-sentinel' ) ); ?> ' + resp.data.field_name,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        });
    });
});
</script>
