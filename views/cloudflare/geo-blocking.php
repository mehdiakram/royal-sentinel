<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Get countries from shared file
$countries = function_exists('rs_get_countries') ? rs_get_countries() : [];

// Zone Data Fetch (Same as rules.php)
$zones_data = function_exists('rs_get_cf_zones') ? rs_get_cf_zones()['items'] : [];
$is_global_mode = (get_option('rs_cf_auth_mode', 'global') !== 'single');

// Determine Selected Zone (Default: Current Domain)
$current_domain_clean = strtolower(str_replace('www.', '', parse_url(get_site_url(), PHP_URL_HOST)));
$selected_zone_id = '';

foreach($zones_data as $z) {
    if ($z['name'] === $current_domain_clean) {
        $selected_zone_id = $z['id'];
        break;
    }
}

// Fallback to first zone if no match
if (empty($selected_zone_id) && !empty($zones_data[0]['id'])) {
    $selected_zone_id = $zones_data[0]['id'];
}
?>

<div class="rs-cf-section">
    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-left: 4px solid #00a8e0; margin-bottom: 25px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="margin:0 0 10px 0;">🌍 <?php _e('Cloudflare Geo-Blocking', 'royal-sentinel'); ?></h3>
                
                <table class="form-table" style="margin:0;">
                    <tr>
                        <th style="padding: 5px 10px 5px 0; width: auto;"><label><?php _e('Target Domain', 'royal-sentinel'); ?></label></th>
                        <td style="padding: 5px 0;">
                            <select name="target_zone" id="rs_cf_geo_zone_selector" class="regular-text" style="min-width:300px;">
                                <option value=""><?php _e('Select Domain', 'royal-sentinel'); ?></option>
                                <?php foreach ($zones_data as $zone): ?>
                                    <option value="<?php echo esc_attr($zone['id']); ?>" <?php selected($selected_zone_id, $zone['id']); ?>>
                                        <?php echo esc_html($zone['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            <div id="rs_cf_status_badge">
                <span class="rs-status-checking"><?php _e('Thinking...', 'royal-sentinel'); ?></span>
            </div>
        </div>
    </div>

    <!-- Rule Config -->
    <div class="rs-section-box" style="border-left: 4px solid #f39c12;">
        <h4 class="rs-section-title" style="color: #f39c12;">⚙️ <?php _e('Configure Rule', 'royal-sentinel'); ?></h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label><?php _e('Block Action', 'royal-sentinel'); ?></label></th>
                <td>
                    <select id="rs_cf_geo_action">
                        <option value="block"><?php _e('Block (Forbidden)', 'royal-sentinel'); ?></option>
                        <option value="managed_challenge"><?php _e('Managed Challenge (Captcha) - Recommended', 'royal-sentinel'); ?></option>
                        <option value="js_challenge"><?php _e('JS Challenge', 'royal-sentinel'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row" style="vertical-align: top; padding-top: 20px;"><label><?php _e('Select Countries', 'royal-sentinel'); ?></label></th>
                <td style="padding-top: 20px;">
                    <div style="background: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; max-height: 400px; overflow-y: auto;">
                        <div style="margin-bottom: 10px; display: flex; justify-content: space-between;">
                            <div>
                                <button type="button" id="rs_cf_select_all" class="button button-small"><?php _e('Select All', 'royal-sentinel'); ?></button>
                                <button type="button" id="rs_cf_clear_all" class="button button-small"><?php _e('Clear All', 'royal-sentinel'); ?></button>
                            </div>
                            <span style="color: #666;" id="rs_cf_count"><?php _e('0 selected', 'royal-sentinel'); ?></span>
                        </div>
                        <div class="rs-country-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px;">
                            <?php foreach ($countries as $code => $name): ?>
                            <label class="rs-country-item" style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: #fff; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; font-size: 13px;">
                                <input type="checkbox" name="cf_geo_countries[]" value="<?php echo esc_attr($code); ?>" style="margin: 0;">
                                <span><?php echo esc_html($name); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="description" style="margin-top: 10px;" id="rs_cf_rule_hint"><?php _e('Select countries to block via Cloudflare WAF rule.', 'royal-sentinel'); ?></p>
                </td>
            </tr>
        </table>
        
        <div style="margin-top: 20px;">
            <button type="button" id="rs_deploy_geo_rule" class="button button-primary button-large">
                🚀 <?php _e('Deploy Rule', 'royal-sentinel'); ?>
            </button>
        </div>
    </div>
    
    <!-- Active Rules List -->
    <div class="rs-section-box" style="margin-top: 30px;">
        <h4 class="rs-section-title">🛡️ <?php _e('Active Rules', 'royal-sentinel'); ?></h4>
        <div id="rs_cf_geo_rules_list">
            <p><?php _e('Loading rules...', 'royal-sentinel'); ?></p>
        </div>
    </div>
</div>

<style>
    .rs-country-item:hover { border-color: #2271b1 !important; background: #f0f6fc !important; }
    .rs-country-item.checked { border-color: #2271b1; background: #e8f4f8; }
    .rs-country-tag { display: inline-block; background: #e1e1e1; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin: 2px; border: 1px solid #ccc; }
    /* Select2 Height Fix */
    #rs_cf_geo_zone_selector + .select2-container .select2-selection--single { height: 35px !important; border-color: #8c8f94 !important; display: flex; align-items: center; }
    #rs_cf_geo_zone_selector + .select2-container--default .select2-selection--single .select2-selection__arrow { height: 33px !important; }
</style>

<script>
jQuery(document).ready(function($) {
    const countryMap = <?php echo json_encode($countries); ?>;
    const GEO_RULE_NAME = 'Geo-Blocking (Royal Sentinel)';
    
    let existingRuleId = null;
    let existingFilterId = null;
    
    // Initialize Select2
    $('#rs_cf_geo_zone_selector').select2({ 
        placeholder: "<?php _e('Select a domain', 'royal-sentinel'); ?>", 
        allowClear: true 
    });
    
    // UI Helpers
    $('input[name="cf_geo_countries[]"]').on('change', function() {
        if($(this).is(':checked')) {
            $(this).closest('.rs-country-item').addClass('checked');
        } else {
            $(this).closest('.rs-country-item').removeClass('checked');
        }
        updateCount();
    });
    
    function updateCount() {
        var count = $('input[name="cf_geo_countries[]"]:checked').length;
        $('#rs_cf_count').text(count + ' <?php _e('selected', 'royal-sentinel'); ?>');
    }
    
    $('#rs_cf_select_all').click(function() {
        $('input[name="cf_geo_countries[]"]').prop('checked', true).trigger('change');
    });
    
    $('#rs_cf_clear_all').click(function() {
        $('input[name="cf_geo_countries[]"]').prop('checked', false).trigger('change');
    });

    // Parse Countries from Expression
    function parseCountriesFromExpression(expr) {
        const matches = expr.match(/"([A-Z]{2})"/g);
        if (!matches) return [];
        return matches.map(m => m.replace(/"/g, ''));
    }
    
    // Parse Action from Rule
    function mapActionToSelect(action) {
        if (action === 'block') return 'block';
        if (action === 'managed_challenge') return 'managed_challenge';
        if (action === 'js_challenge') return 'js_challenge';
        return 'block';
    }

    // Fetch and Load Rules
    function fetchGeoRules() {
        var zoneId = $('#rs_cf_geo_zone_selector').val();
        
        if(!zoneId) {
            $('#rs_cf_geo_rules_list').html('<p style="color:#888;"><?php _e('Please select a domain to view rules.', 'royal-sentinel'); ?></p>');
            $('#rs_cf_status_badge').html('<span style="color:#888;"><?php _e('Select Domain', 'royal-sentinel'); ?></span>');
            resetForm();
            return;
        }
        
        $('#rs_cf_status_badge').html('<span class="rs-status-checking"><?php _e('Checking Cloudflare...', 'royal-sentinel'); ?></span>');
        
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>',
            action: 'rs_cf_geoblocking',
            rs_action: 'fetch',
            zone_id: zoneId,
        }, function(res) {
            if(res.success) {
                renderRules(res.data);
                loadExistingRule(res.data);
                $('#rs_cf_status_badge').html('<span style="color:green; font-weight:bold;">✅ <?php _e('Connected', 'royal-sentinel'); ?></span>');
            } else {
                $('#rs_cf_geo_rules_list').html('<p style="color:red;"><?php _e('Error:', 'royal-sentinel'); ?> ' + res.message + '</p>');
                $('#rs_cf_status_badge').html('<span style="color:red; font-weight:bold;">❌ <?php _e('Error', 'royal-sentinel'); ?></span>');
                resetForm();
            }
        }).fail(function() {
            $('#rs_cf_geo_rules_list').html('<p style="color:red;"><?php _e('AJAX Error', 'royal-sentinel'); ?></p>');
            resetForm();
        });
    }
    
    function resetForm() {
        existingRuleId = null;
        existingFilterId = null;
        $('#rs_deploy_geo_rule').html('🚀 <?php _e('Deploy Rule', 'royal-sentinel'); ?>');
        $('#rs_cf_rule_hint').text('<?php _e('Select countries to block via Cloudflare WAF rule.', 'royal-sentinel'); ?>');
        $('input[name="cf_geo_countries[]"]').prop('checked', false).trigger('change');
        $('#rs_cf_geo_action').val('block');
    }
    
    function loadExistingRule(rules) {
        existingRuleId = null;
        existingFilterId = null;
        
        // Find our rule
        var ourRule = rules.find(r => r.description === GEO_RULE_NAME);
        
        if (ourRule) {
            existingRuleId = ourRule.id;
            existingFilterId = ourRule.filter_id || null;
            
            // Load countries
            var countryCodes = parseCountriesFromExpression(ourRule.expression);
            $('input[name="cf_geo_countries[]"]').prop('checked', false);
            countryCodes.forEach(function(code) {
                $('input[name="cf_geo_countries[]"][value="' + code + '"]').prop('checked', true).trigger('change');
            });
            
            // Load action
            $('#rs_cf_geo_action').val(mapActionToSelect(ourRule.action));
            
            // Update button
            $('#rs_deploy_geo_rule').html('🔄 <?php _e('Update Rule', 'royal-sentinel'); ?>');
            $('#rs_cf_rule_hint').html('<span style="color:#2271b1;"><strong><?php _e('Existing rule found!', 'royal-sentinel'); ?></strong> <?php _e('Modify and click Update to save changes.', 'royal-sentinel'); ?></span>');
        } else {
            $('#rs_deploy_geo_rule').html('🚀 <?php _e('Deploy Rule', 'royal-sentinel'); ?>');
            $('#rs_cf_rule_hint').text('<?php _e('Select countries to block via Cloudflare WAF rule.', 'royal-sentinel'); ?>');
        }
        
        updateCount();
    }
    
    // Change Domain Handler
    $('#rs_cf_geo_zone_selector').on('change', function() {
        fetchGeoRules();
    });
    
    function renderCountriesHtml(expr) {
        const codes = parseCountriesFromExpression(expr);
        if (codes.length === 0) return '<?php _e('No countries detected', 'royal-sentinel'); ?>';
        
        let html = '';
        codes.forEach(code => {
            const name = countryMap[code] || code;
            html += `<span class="rs-country-tag" title="${code}">${name}</span>`;
        });
        return html;
    }
    
    function renderRules(rules) {
        if(!rules || rules.length === 0) {
            $('#rs_cf_geo_rules_list').html('<p><?php _e('No active rules found.', 'royal-sentinel'); ?></p>');
            return;
        }
        
        let html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr><th style="width:25%"><?php _e('Rule Name', 'royal-sentinel'); ?></th><th style="width:15%"><?php _e('Action', 'royal-sentinel'); ?></th><th style="width:40%"><?php _e('Target Countries', 'royal-sentinel'); ?></th><th style="width:10%"><?php _e('Status', 'royal-sentinel'); ?></th><th style="width:10%"><?php _e('Actions', 'royal-sentinel'); ?></th></tr></thead><tbody>';
        
        let found = false;
        rules.forEach(function(rule) {
            if(rule.expression.includes('ip.geoip.country') || rule.description.includes('Royal Sentinel')) {
                found = true;
                const countriesHtml = renderCountriesHtml(rule.expression);
                const isOurRule = rule.description === GEO_RULE_NAME;
                const rowStyle = isOurRule ? 'background: #e8f4f8;' : '';
                
                html += `<tr style="${rowStyle}">
                    <td><strong>${rule.description}</strong>${isOurRule ? ' <span style="color:#2271b1;">★</span>' : ''}</td>
                    <td><span class="rs-badge">${rule.action}</span></td>
                    <td>${countriesHtml}</td>
                    <td>${rule.enabled ? '✅ <?php _e('Active', 'royal-sentinel'); ?>' : '⏸️ <?php _e('Paused', 'royal-sentinel'); ?>'}</td>
                    <td>
                        <button class="button rs-delete-rule" data-id="${rule.id}" style="color: #a00; border-color: #a00;"><?php _e('Delete', 'royal-sentinel'); ?></button>
                    </td>
                </tr>`;
            }
        });
        
        if (!found) {
            html = '<p><?php _e('No active Geo-Blocking rules found via API.', 'royal-sentinel'); ?></p>';
        } else {
            html += '</tbody></table>';
        }
        $('#rs_cf_geo_rules_list').html(html);
    }
    
    // Deploy / Update Rule
    $('#rs_deploy_geo_rule').click(function() {
        var zoneId = $('#rs_cf_geo_zone_selector').val();
        
        if (!zoneId) {
            Swal.fire({ title: '<?php _e('Warning', 'royal-sentinel'); ?>', text: '<?php _e('Please select a target domain.', 'royal-sentinel'); ?>', icon: 'warning', timer: 1500, showConfirmButton: false });
            return;
        }
        
        const countries = [];
        $('input[name="cf_geo_countries[]"]:checked').each(function() {
            countries.push($(this).val());
        });
        
        if(countries.length === 0) {
            Swal.fire({ title: '<?php _e('Warning', 'royal-sentinel'); ?>', text: '<?php _e('Please select at least one country.', 'royal-sentinel'); ?>', icon: 'warning', timer: 1500, showConfirmButton: false });
            return;
        }
        
        const action = $('#rs_cf_geo_action').val();
        const btn = $(this);
        const isUpdate = existingRuleId !== null;
        
        btn.prop('disabled', true).text(isUpdate ? '<?php _e('Updating...', 'royal-sentinel'); ?>' : '<?php _e('Deploying...', 'royal-sentinel'); ?>');
        
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>',
            action: 'rs_cf_geoblocking',
            rs_action: 'deploy',
            zone_id: zoneId,
            countries: countries,
            rule_action: action,
            existing_rule_id: existingRuleId || '',
            existing_filter_id: existingFilterId || ''
        }, function(res) {
            btn.prop('disabled', false).html(isUpdate ? '🔄 <?php _e('Update Rule', 'royal-sentinel'); ?>' : '🚀 <?php _e('Deploy Rule', 'royal-sentinel'); ?>');
            if(res.success) {
                Swal.fire({ title: '<?php _e('Success', 'royal-sentinel'); ?>', text: isUpdate ? '<?php _e('Geo-Blocking rule updated successfully!', 'royal-sentinel'); ?>' : '<?php _e('Geo-Blocking rule deployed successfully!', 'royal-sentinel'); ?>', icon: 'success', timer: 1500, showConfirmButton: false });
                fetchGeoRules();
            } else {
                Swal.fire({ title: '<?php _e('Error', 'royal-sentinel'); ?>', text: res.message, icon: 'error', timer: 1500, showConfirmButton: false });
            }
        }).fail(function() {
            btn.prop('disabled', false).html('🚀 <?php _e('Deploy Rule', 'royal-sentinel'); ?>');
            Swal.fire({ title: '<?php _e('Error', 'royal-sentinel'); ?>', text: '<?php _e('AJAX request failed.', 'royal-sentinel'); ?>', icon: 'error', timer: 1500, showConfirmButton: false });
        });
    });
    
    // Delete Rule
    $(document).on('click', '.rs-delete-rule', function() {
        if(!confirm('<?php _e('Are you sure you want to delete this rule?', 'royal-sentinel'); ?>')) return;
        var zoneId = $('#rs_cf_geo_zone_selector').val();
        
        const ruleId = $(this).data('id');
        const btn = $(this);
        btn.text('<?php _e('Deleting...', 'royal-sentinel'); ?>');
        
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>',
            action: 'rs_cf_geoblocking',
            rs_action: 'delete',
            zone_id: zoneId,
            rule_id: ruleId
        }, function(res) {
            if(res.success) {
                fetchGeoRules();
            } else {
                alert('<?php _e('Error:', 'royal-sentinel'); ?> ' + res.message);
                btn.text('<?php _e('Delete', 'royal-sentinel'); ?>');
            }
        });
    });

    // Initial Load - Auto fetch if zone is pre-selected
    var initialZoneId = $('#rs_cf_geo_zone_selector').val();
    if (initialZoneId && initialZoneId !== '') {
        fetchGeoRules();
    } else {
        $('#rs_cf_geo_rules_list').html('<p style="color:#888;"><?php _e('Please select a domain to view rules.', 'royal-sentinel'); ?></p>');
        $('#rs_cf_status_badge').html('<span style="color:#888;"><?php _e('Select Domain', 'royal-sentinel'); ?></span>');
    }
});
</script>
