<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// সেটিংস ভেরিয়েবল
$db_backup_on  = isset($rs_settings['db_backups']) ? $rs_settings['db_backups'] : 0;
$logs_export_on= isset($rs_settings['logs_export']) ? $rs_settings['logs_export'] : 0;

// নতুন ভেরিয়েবল
$backup_freq   = isset($rs_settings['db_backup_freq']) ? $rs_settings['db_backup_freq'] : 'manual';
$backup_email  = isset($rs_settings['db_backup_email_addr']) ? $rs_settings['db_backup_email_addr'] : get_option('admin_email');
$backup_zip    = isset($rs_settings['db_backup_zip']) ? $rs_settings['db_backup_zip'] : 0;

// [FIX] ডাউললোড ইউআরএল তৈরি করা (GET Request)
$download_db_url = wp_nonce_url( admin_url( 'admin-post.php?action=rs_download_db_backup' ), 'rs_backup_action' );
$export_logs_url = wp_nonce_url( admin_url( 'admin-post.php?action=rs_export_security_logs' ), 'rs_backup_action' );
?>

<div class="rs-backups-wrapper">

    <div class="rs-section-box" style="border-left: 4px solid #2271b1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px;">
        <h4 style="margin-top: 0; color: #2271b1; border-bottom: 1px solid #eee; padding-bottom: 15px;">Database Backup & Recovery</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row" style="width: 50px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="db_backups" id="rs_db_backup_toggle" value="1" <?php checked(1, $db_backup_on); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td>
                    <label for="rs_db_backup_toggle" style="font-weight:600; color: #3c434a;">Enable Backup System</label>
                    <p class="description">Activate automated or manual database backups.</p>
                </td>
            </tr>
        </table>

        <div id="rs_backup_options" style="display: <?php echo $db_backup_on ? 'block' : 'none'; ?>; margin-top: 20px; padding-left: 10px;">
            <table class="form-table">
                <tr>
                    <th scope="row"><label>Schedule Frequency</label></th>
                    <td>
                        <select name="db_backup_freq" class="regular-text">
                            <option value="manual" <?php selected('manual', $backup_freq); ?>>Manual Only</option>
                            <option value="daily" <?php selected('daily', $backup_freq); ?>>Daily (Once a day)</option>
                            <option value="weekly" <?php selected('weekly', $backup_freq); ?>>Weekly (Once a week)</option>
                        </select>
                        <p class="description">Automated backups will be sent to your email.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label>Send Backup To</label></th>
                    <td>
                        <input type="email" name="db_backup_email_addr" value="<?php echo esc_attr($backup_email); ?>" class="regular-text" placeholder="admin@example.com">
                        <p class="description">Enter the email address where the backup file will be sent.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label>Compression</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="db_backup_zip" value="1" <?php checked(1, $backup_zip); ?>>
                            <strong>Enable ZIP Compression</strong> (Recommended)
                        </label>
                        <p class="description">Reduces file size significantly before sending.</p>
                    </td>
                </tr>
            </table>

            <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border: 1px solid #c5d9ed; border-radius: 4px;">
                <p style="margin-top:0;"><strong>Instant Backup:</strong> Download the database right now.</p>
                <a href="<?php echo esc_url($download_db_url); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-download" style="vertical-align: middle;"></span> Download Now
                </a>
            </div>
        </div>
    </div>

    <div class="rs-section-box" style="border-left: 4px solid #d63638; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px;">
        <h4 style="margin-top: 0; color: #d63638; border-bottom: 1px solid #eee; padding-bottom: 15px;">Security Logs Export</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row" style="width: 50px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="logs_export" id="rs_logs_toggle" value="1" <?php checked(1, $logs_export_on); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td>
                    <label for="rs_logs_toggle" style="font-weight:600; color: #3c434a;">Enable Logs Export</label>
                    <p class="description">Allows exporting security logs (bans, logins, firewall hits) to CSV.</p>
                </td>
            </tr>
        </table>

        <?php if ($logs_export_on): ?>
        <div style="margin-top: 15px; padding: 15px; background: #fff8e5; border: 1px solid #f0c33c; border-radius: 4px;">
            <p style="margin-top:0;"><strong>Export Data:</strong> Download all security logs as a CSV file for analysis.</p>
            <a href="<?php echo esc_url($export_logs_url); ?>" class="button button-secondary">
                <span class="dashicons dashicons-media-spreadsheet" style="vertical-align: middle;"></span> Export Logs to CSV
            </a>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
jQuery(document).ready(function($) {
    function toggleOpts(id, box) {
        if($(id).is(':checked')) $(box).slideDown();
        else $(box).slideUp();
    }
    $('#rs_db_backup_toggle').change(function(){ toggleOpts(this, '#rs_backup_options'); });
});
</script>