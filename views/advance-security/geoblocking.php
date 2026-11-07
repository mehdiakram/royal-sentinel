<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// Settings
$geo_enabled       = isset($rs_settings['geo_enabled']) ? $rs_settings['geo_enabled'] : 0;
$geo_mode          = isset($rs_settings['geo_mode']) ? $rs_settings['geo_mode'] : 'blacklist';
$geo_countries     = isset($rs_settings['geo_countries']) ? $rs_settings['geo_countries'] : [];
$geo_whitelist_ips = isset($rs_settings['geo_whitelist_ips']) ? $rs_settings['geo_whitelist_ips'] : '';
$geo_action        = isset($rs_settings['geo_action']) ? $rs_settings['geo_action'] : 'block';
$geo_redirect_url  = isset($rs_settings['geo_redirect_url']) ? $rs_settings['geo_redirect_url'] : '';

// Get countries from shared file
$countries = function_exists('rs_get_countries') ? rs_get_countries() : [];
?>

<div class="rs-geo-wrapper">
    
    <div style="margin-bottom: 20px;">
        <h3 style="margin:0;">🌍 Country Blocking (Geo-Blocking)</h3>
        <p class="description">Block or allow access based on visitor's country using IP geolocation.</p>
    </div>

    <div class="rs-section-box" style="border-left: 4px solid #e74c3c;">
        <h4 class="rs-section-title" style="color: #e74c3c;">⚙️ Geo-Blocking Settings</h4>
        
        <div style="margin-bottom: 20px;">
            <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                <input type="checkbox" name="geo_enabled" id="rs_geo_enabled" value="1" <?php checked(1, $geo_enabled); ?>>
                <span class="rs-slider round"></span>
            </label>
            <label for="rs_geo_enabled" style="font-weight: 600; font-size: 14px;">Enable Country Blocking</label>
            <p class="description" style="margin-left: 55px; margin-top: 5px;">Block or allow visitors based on their geographic location.</p>
        </div>

        <div id="rs_geo_settings" style="display: <?php echo $geo_enabled ? 'block' : 'none'; ?>;">
            
            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px;">
                <strong style="color: #856404;">⚠️ Note:</strong> 
                <span style="color: #856404;">This feature uses free IP geolocation API. For high-traffic sites, consider using a local GeoIP database.</span>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row"><label>Blocking Mode</label></th>
                    <td>
                        <div style="display: flex; gap: 20px;">
                            <label class="rs-mode-option" style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; border: 2px solid <?php echo $geo_mode !== 'whitelist' ? '#e74c3c' : '#ddd'; ?>; border-radius: 8px; cursor: pointer;">
                                <input type="radio" name="geo_mode" value="blacklist" <?php checked('blacklist', $geo_mode); ?>>
                                <div>
                                    <strong style="color: #e74c3c;">🚫 Blacklist Mode</strong>
                                    <div style="font-size: 12px; color: #666;">Block selected countries</div>
                                </div>
                            </label>
                            <label class="rs-mode-option" style="display: flex; align-items: center; gap: 8px; padding: 12px 20px; border: 2px solid <?php echo $geo_mode === 'whitelist' ? '#27ae60' : '#ddd'; ?>; border-radius: 8px; cursor: pointer;">
                                <input type="radio" name="geo_mode" value="whitelist" <?php checked('whitelist', $geo_mode); ?>>
                                <div>
                                    <strong style="color: #27ae60;">✅ Whitelist Mode</strong>
                                    <div style="font-size: 12px; color: #666;">Only allow selected countries</div>
                                </div>
                            </label>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row" style="vertical-align: top; padding-top: 20px;"><label>Select Countries</label></th>
                    <td style="padding-top: 20px;">
                        <div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; max-height: 300px; overflow-y: auto;">
                            <div style="margin-bottom: 10px;">
                                <button type="button" id="rs_select_all_countries" class="button button-small">Select All</button>
                                <button type="button" id="rs_clear_all_countries" class="button button-small">Clear All</button>
                                <span style="margin-left: 15px; color: #666;" id="rs_country_count"><?php echo count($geo_countries); ?> selected</span>
                            </div>
                            <div class="rs-country-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px;">
                                <?php foreach ($countries as $code => $name): 
                                    $is_selected = in_array($code, $geo_countries);
                                ?>
                                <label class="rs-country-item" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: <?php echo $is_selected ? '#e8f4f8' : '#fff'; ?>; border: 1px solid <?php echo $is_selected ? '#2271b1' : '#ddd'; ?>; border-radius: 5px; cursor: pointer; font-size: 13px;">
                                    <input type="checkbox" name="geo_countries[]" value="<?php echo esc_attr($code); ?>" <?php checked($is_selected); ?> style="margin: 0;">
                                    <span><?php echo esc_html($name); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label>Block Action</label></th>
                    <td>
                        <select name="geo_action" id="rs_geo_action" style="min-width: 250px;">
                            <option value="block" <?php selected('block', $geo_action); ?>>Show 403 Forbidden Page</option>
                            <option value="redirect" <?php selected('redirect', $geo_action); ?>>Redirect to URL</option>
                        </select>
                        
                        <div id="rs_redirect_url_wrap" style="margin-top: 10px; display: <?php echo $geo_action === 'redirect' ? 'block' : 'none'; ?>;">
                            <input type="url" name="geo_redirect_url" value="<?php echo esc_attr($geo_redirect_url); ?>" placeholder="https://example.com/blocked" style="width: 100%; max-width: 400px;">
                            <p class="description">Users from blocked countries will be redirected to this URL.</p>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row" style="vertical-align: top;"><label>IP Whitelist</label></th>
                    <td>
                        <textarea name="geo_whitelist_ips" rows="4" style="width: 100%; max-width: 400px;" placeholder="192.168.1.1&#10;10.0.0.1"><?php echo esc_textarea($geo_whitelist_ips); ?></textarea>
                        <p class="description">Enter IP addresses (one per line) that should bypass country blocking. Useful for your own IP or trusted services.</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #3498db;">
        <h4 class="rs-section-title" style="color: #3498db;">🔍 Test Your IP</h4>
        <p class="description" style="margin-bottom: 15px;">Check which country an IP address is detected from.</p>
        
        <div style="display: flex; gap: 10px; align-items: center;">
            <input type="text" id="rs_test_ip" placeholder="Enter IP address" style="width: 200px;">
            <button type="button" id="rs_test_ip_btn" class="button button-secondary">
                <span class="dashicons dashicons-search" style="vertical-align: middle;"></span> Lookup
            </button>
            <span id="rs_test_result" style="margin-left: 10px; font-weight: 600;"></span>
        </div>
        
        <p style="margin-top: 15px; color: #666;">
            Your current IP: <code><?php echo esc_html($_SERVER['REMOTE_ADDR']); ?></code>
        </p>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 180px; }
    .rs-country-item:hover { border-color: #2271b1 !important; }
    .rs-mode-option:hover { border-color: #888 !important; }
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle settings visibility
    $('#rs_geo_enabled').on('change', function() {
        if($(this).is(':checked')) {
            $('#rs_geo_settings').slideDown();
        } else {
            $('#rs_geo_settings').slideUp();
        }
    });
    
    // Toggle redirect URL
    $('#rs_geo_action').on('change', function() {
        if($(this).val() === 'redirect') {
            $('#rs_redirect_url_wrap').slideDown();
        } else {
            $('#rs_redirect_url_wrap').slideUp();
        }
    });
    
    // Mode radio styling
    $('input[name="geo_mode"]').on('change', function() {
        $('.rs-mode-option').each(function() {
            var radio = $(this).find('input[type="radio"]');
            if (radio.is(':checked')) {
                $(this).css('border-color', radio.val() === 'blacklist' ? '#e74c3c' : '#27ae60');
            } else {
                $(this).css('border-color', '#ddd');
            }
        });
    });
    
    // Country selection styling
    $('input[name="geo_countries[]"]').on('change', function() {
        var item = $(this).closest('.rs-country-item');
        if ($(this).is(':checked')) {
            item.css({'background': '#e8f4f8', 'border-color': '#2271b1'});
        } else {
            item.css({'background': '#fff', 'border-color': '#ddd'});
        }
        updateCountryCount();
    });
    
    function updateCountryCount() {
        var count = $('input[name="geo_countries[]"]:checked').length;
        $('#rs_country_count').text(count + ' selected');
    }
    
    // Select all / Clear all
    $('#rs_select_all_countries').on('click', function() {
        $('input[name="geo_countries[]"]').prop('checked', true).trigger('change');
    });
    $('#rs_clear_all_countries').on('click', function() {
        $('input[name="geo_countries[]"]').prop('checked', false).trigger('change');
    });
    
    // IP Lookup Test
    $('#rs_test_ip_btn').on('click', function() {
        var ip = $('#rs_test_ip').val().trim();
        if (!ip) {
            ip = '<?php echo esc_js($_SERVER['REMOTE_ADDR']); ?>';
            $('#rs_test_ip').val(ip);
        }
        
        var btn = $(this);
        btn.prop('disabled', true);
        $('#rs_test_result').html('<span style="color:#888;">Looking up...</span>');
        
        // Using free IP API
        $.getJSON('https://ipapi.co/' + ip + '/json/', function(data) {
            if (data.country_code && data.country_name) {
                $('#rs_test_result').html('<span style="color:#27ae60;">✓ ' + data.country_name + ' (' + data.country_code + ')</span>');
            } else {
                $('#rs_test_result').html('<span style="color:#e74c3c;">Could not detect country</span>');
            }
        }).fail(function() {
            $('#rs_test_result').html('<span style="color:#e74c3c;">Lookup failed</span>');
        }).always(function() {
            btn.prop('disabled', false);
        });
    });
});
</script>
