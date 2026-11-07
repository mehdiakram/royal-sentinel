<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

$zones_data = function_exists('rs_get_cf_zones') ? rs_get_cf_zones() : ['items' => []];
$zones = $zones_data['items'];
$error_msg = $zones_data['error'] ?? '';

$current_domain = strtolower(str_replace('www.', '', parse_url(get_site_url(), PHP_URL_HOST)));
$selected_zone_id = '';
if (!empty($zones)) {
    foreach($zones as $z) {
        if ($z['name'] === $current_domain) { $selected_zone_id = $z['id']; break; }
    }
}
?>

<div class="rs-cf-wrapper">
    
    <?php if ( !empty($error_msg) ): ?>
        <div class="notice notice-error inline" style="margin-bottom:20px;">
            <p><strong>⚠️ Cloudflare API Error:</strong> <?php echo esc_html($error_msg); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( empty($zones) ): ?>
        <div class="notice notice-warning inline" style="padding:15px;">
            <h3>⚠️ No Domains Found</h3>
            <p>Please ensure your Cloudflare API Key is correctly configured.</p>
        </div>
    <?php else: ?>

        <div class="rs-global-selector" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px; display:flex; align-items:center; gap:10px; border-left: 5px solid #2271b1;">
            <label style="font-weight:600; font-size:14px;"><?php _e( '⚙️ Configure Domain:', 'royal-sentinel' ); ?></label>
            <select id="rs-set-zone" style="width: 300px;">
                <option value=""><?php _e( '-- Select Domain --', 'royal-sentinel' ); ?></option>
                <?php foreach($zones as $z): ?>
                    <option value="<?php echo esc_attr($z['id']); ?>" <?php selected($selected_zone_id, $z['id']); ?>>
                        <?php echo esc_html($z['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="rs-settings-form" style="display:none; background:#fff; padding:0; border:1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,.04); margin-bottom:30px;">
            
            <div style="padding:20px; border-bottom:1px solid #eee; background:#f9f9f9;">
                <h3 style="margin:0;">🛠️ Configuration</h3>
            </div>

            <div style="padding:20px;">
                <table class="form-table">
                    
                    <tr style="border-bottom:1px solid #eee;">
                        <th style="padding:20px 0;">🚧 Development Mode</th>
                        <td style="padding:20px 0;">
                            <select class="rs-opt" data-id="development_mode">
                                <option value="on">On (Bypass Cache)</option>
                                <option value="off">Off</option>
                            </select>
                            <p class="description">Temporarily bypass Cloudflare cache. Useful when making changes to the site.</p>
                        </td>
                    </tr>

                    <tr style="border-bottom:1px solid #eee;">
                        <th style="padding:20px 0;">🚀 Rocket Loader™</th>
                        <td style="padding:20px 0;">
                            <select class="rs-opt" data-id="rocket_loader">
                                <option value="on">On</option>
                                <option value="off">Off</option>
                            </select>
                            <p class="description">Improve paint time for pages with JavaScript.</p>
                        </td>
                    </tr>

                    <tr style="border-bottom:1px solid #eee;">
                         <th style="padding:20px 0;"><label style="font-weight:bold; color:#d63638;">🛡️ Security Level</label></th>
                         <td style="padding:20px 0;">
                             <select class="rs-opt" data-id="security_level" style="border-color:#d63638;">
                                 <option value="off">Off</option>
                                 <option value="essentially_off">Essentially Off</option>
                                 <option value="low">Low</option>
                                 <option value="medium">Medium</option>
                                 <option value="high">High</option>
                                 <option value="under_attack">🚨 I'm Under Attack!</option>
                             </select>
                             <p class="description">Select "I'm Under Attack" if you are experiencing a DDoS attack.</p>
                         </td>
                    </tr>

                    <tr style="border-bottom:1px solid #eee;">
                         <th style="padding:20px 0;"><label style="font-weight:bold;">🤖 Bot Fight Mode</label></th>
                         <td style="padding:20px 0;">
                             <select class="rs-opt" data-id="bot_fight_mode">
                                 <option value="on">On (Block Known Bots)</option>
                                 <option value="off">Off</option>
                             </select>
                             <p class="description">Challenge requests from known automated bots.</p>
                         </td>
                    </tr>

                    <tr style="border-bottom:1px solid #eee;">
                        <th style="padding:20px 0;">🔒 SSL/TLS Mode</th>
                        <td style="padding:20px 0;">
                            <select class="rs-opt" data-id="ssl">
                                <option value="off">Off</option>
                                <option value="flexible">Flexible</option>
                                <option value="full">Full</option>
                                <option value="strict">Full (Strict)</option>
                            </select>
                            <p class="description">Controls how Cloudflare connects to your origin server.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th>Browser Integrity Check</th>
                        <td>
                            <select class="rs-opt" data-id="browser_check">
                                <option value="on">On</option>
                                <option value="off">Off</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Hotlink Protection</th>
                        <td>
                            <select class="rs-opt" data-id="hotlink_protection">
                                <option value="on">On</option>
                                <option value="off">Off</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th>Always Online</th>
                        <td>
                            <select class="rs-opt" data-id="always_online">
                                <option value="on">On</option>
                                <option value="off">Off</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <p class="description" style="margin-top:20px; padding:10px; background:#e7f5ea; color:#106a26; border-left:4px solid #106a26; display:inline-block;">
                    ✅ <i>Changes are saved automatically instantly.</i>
                </p>
            </div>
        </div>

    <?php endif; ?>

</div>

<script>
jQuery(document).ready(function($) {

    $('#rs-set-zone').select2({ placeholder: "Select a domain", allowClear: true });
    var nonce = '<?php echo wp_create_nonce("rs_cf_tabs"); ?>';
    
    function loadSettings(zid) {
        if(!zid) { $('#rs-settings-form').fadeOut(); return; }
        $('#rs-settings-form').fadeIn();
        $('.rs-opt').prop('disabled', true);
        
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_load_settings', zone_id: zid, nonce: nonce }, function(res) {
            $('.rs-opt').prop('disabled', false);
            if(res.success) {
                $.each(res.data, function(k, v) { 
                    $('.rs-opt[data-id="'+k+'"]').val(v); 
                });
            } else {
                 Swal.fire("Error", res.data, 'error');
            }
        });
    }

    $('#rs-set-zone').on('change', function() { loadSettings($(this).val()); });
    var initialZone = $('#rs-set-zone').val();
    if(initialZone) { loadSettings(initialZone); }

    $('.rs-opt').change(function() {
        var zid = $('#rs-set-zone').val();
        var key = $(this).data('id');
        var val = $(this).val();
        
        // Show Toast
        const Toast = Swal.mixin({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 2000 });
        
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_save_settings', zone_id: zid, setting_id: key, value: val, nonce: nonce }, function(res) {
            if(res.success) Toast.fire({ icon: 'success', title: "Updated" });
            else Toast.fire({ icon: 'error', title: "Update Failed" });
        });
    });
});
</script>

<style>
    .select2-container .select2-selection--single { height: 35px !important; border-color: #8c8f94 !important; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 33px !important; }
</style>
