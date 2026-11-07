<?php
if ( ! defined( 'ABSPATH' ) ) exit;

set_time_limit(300);

// ================================================================
// ১. হেল্পার ফাংশন (API Calls)
// ================================================================
// Note: Helper functions moved to includes/cloudflare-api.php
// Note: AJAX handlers moved to includes/cloudflare-api.php (rs_cf_waf_rules action)

// ================================================================
// ৩. PHP Form Handler (Add Rule)
// ================================================================
$msg_script = "";

// Zone Data Fetch
$zones_data = function_exists( 'rs_get_cf_zones' ) ? rs_get_cf_zones()['items'] : [];
$rs_acc_id = get_option( 'rs_cf_acc_id' );

// Check if Global Mode (for showing bulk buttons)
$is_global_mode = (get_option('rs_cf_auth_mode', 'global') !== 'single');

// Determine Selected Zone (Default: Current Domain or Posted Value)
$current_domain_clean = strtolower(str_replace('www.', '', parse_url(get_site_url(), PHP_URL_HOST)));
$selected_zone_id = '';

// Check if form was submitted
if ( isset( $_POST['target_zone'] ) ) {
    $selected_zone_id = sanitize_text_field( $_POST['target_zone'] );
} else {
    // Default to Current Domain
    foreach($zones_data as $z) {
        if ($z['name'] === $current_domain_clean) {
            $selected_zone_id = $z['id'];
            break;
        }
    }
}


if ( isset( $_POST['rs_apply_rule'] ) ) {
    $rule_type = sanitize_text_field( $_POST['rule_type'] );
    $target_zone = sanitize_text_field( $_POST['target_zone'] ); // This is captured above too
    $rule_name = ""; $expression = ""; $action = "block"; 

    switch ( $rule_type ) {
        case 'mega_block':
            $rule_name = "Mega Block (Royal Sentinel)";
            $base_expression = '(http.request.version eq "HTTP/1.0") or (http.request.uri.path contains "/xmlrpc.php") or (http.user_agent contains "Mozlila") or (http.user_agent contains "libwww-perl") or (http.user_agent contains "wget") or (http.request.uri.path contains ".env") or (http.request.uri.path contains ".git")';
            $cf_list_id = get_option( 'rs_cf_list_id' );
            $cf_account_id = get_option( 'rs_cf_acc_id' );
            $list_var_name = '';
            // Only fetch list name if Global Mode (Account ID needed)
            if ( get_option('rs_cf_auth_mode') !== 'single' && ! empty( $cf_list_id ) && ! empty( $cf_account_id ) ) {
                $list_var_name = rs_get_list_name_by_id( $cf_account_id, $cf_list_id );
            }
            // Fallback: If we can't get list name, just use base expression
            if ( ! empty( $list_var_name ) ) {
                $expression = '(ip.src in $' . $list_var_name . ') or ' . $base_expression;
            } else {
                $expression = $base_expression;
            }
            $action = "block";
            break;
        case 'secure_login':
            $rule_name = "Secure Login (Royal Sentinel)";
            $expression = '((http.request.uri.path contains "/wp-login.php") or (http.request.uri.path contains "/wp-admin") or (http.request.uri.path contains "/admin") or (http.request.uri.path contains "/login")) and (not http.request.uri.path contains "/admin-ajax.php") and (not http.request.uri.path contains "/wp-admin/css/") and (not http.request.uri.path contains "/wp-admin/js/") and (not http.request.uri.path contains "/wp-admin/load-scripts.php") and (not http.request.uri.path contains "/wp-admin/load-styles.php")';
            $action = "managed_challenge"; 
            break;
        case 'bad_ips':
            $rule_name = "Challenge Bad IPs (Royal Sentinel)";
            $expression = '(cf.threat_score gt 20)';
            $action = "managed_challenge"; 
            break;
        case 'sqli_xss':
            $rule_name = "SQLi/XSS Protection (Royal Sentinel)";
            $expression = '(http.request.uri.query contains "select") or (http.request.uri.query contains "union") or (http.request.uri.query contains "exec") or (http.request.uri.query contains "<script")';
            $action = "block";
            break;
        case 'fake_bots':
            $rule_name = "Block Fake Bots (Royal Sentinel)";
            $expression = '((http.user_agent contains "Googlebot") or (http.user_agent contains "Bingbot")) and (not cf.client.bot)';
            $action = "block";
            break;
        case 'ai_bad_crawlers':
            $rule_name = "Block AI & Bad Crawlers (Royal Sentinel)";
            $expression = '(http.user_agent contains "Perplexity") or (http.user_agent contains "DotBot") or (http.user_agent contains "AhrefsBot") or (http.user_agent contains "Bytespider") or (http.user_agent contains "libredtail") or (http.user_agent contains "SemrushBot") or (http.user_agent contains "MJ12bot") or (http.user_agent eq "-") or (http.request.uri.path contains "/.aws") or (http.request.uri.path contains "/.ssh") or (http.request.uri.path contains "/terraform.tfstate") or (http.request.uri.path contains "/rclone.conf") or (http.request.uri.path contains "/.npmrc") or (http.request.uri.path contains "/.htpasswd") or (http.request.uri.path contains "/config.json") or (http.request.uri.path contains "/.env") or (http.request.uri.path contains "/v1/graphql") or (http.request.uri.path contains "/api/graphql")';
            $action = "block";
            break;
    }

    if ( $expression ) {
        $targets = ($target_zone === 'all') ? array_column($zones_data, 'id') : [$target_zone];
        $success_count = 0; $skip_count = 0;
        
        foreach ( $targets as $z_id ) {
            $existing_resp = rs_get_cf_rules_via_api( $z_id );
            $existing_rules = $existing_resp['success'] ? $existing_resp['data'] : [];
            $exists = false;
            foreach ( $existing_rules as $ex ) { 
                if ( isset($ex['description']) && $ex['description'] === $rule_name ) { $exists = true; break; } 
            }
            if ( $exists ) { $skip_count++; } 
            else { 
                $resp = rs_add_cf_rule_via_api( $z_id, $rule_name, $expression, $action );
                if($resp['success']) $success_count++; 
            }
        }
        $msg_text = '';
        $msg_icon = 'success';
        
        if ($success_count > 0 && $skip_count > 0) {
            $msg_text = __('Applied to', 'royal-sentinel') . " $success_count " . __('domains. Skipped', 'royal-sentinel') . " $skip_count (" . __('Duplicate', 'royal-sentinel') . ").";
        } elseif ($success_count > 0) {
            $msg_text = __('Rule deployed successfully!', 'royal-sentinel');
        } elseif ($skip_count > 0) {
            $msg_text = __('Rule already exists! Duplicate not allowed.', 'royal-sentinel');
            $msg_icon = 'error';
        }
        
        $msg_script = "Swal.fire({ title: '" . ($msg_icon === 'error' ? __('Duplicate!', 'royal-sentinel') : __('Done!', 'royal-sentinel')) . "', text: '$msg_text', icon: '$msg_icon', timer: 1500, showConfirmButton: false });";
    }
}

if ( $msg_script ) echo "<script>window.onload = function() { $msg_script }</script>";
?>

<div class="rs-waf-wrap">
    <div style="background:#fff; padding:20px; border-left: 5px solid #6f42c1; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom:20px;">
        <h2 style="margin:0; color:#6f42c1;">🛡️ <?php _e('Custom WAF Rules', 'royal-sentinel'); ?></h2>
        <p style="margin: 5px 0 0; color: #666;"><?php _e('Apply smart firewall rules. Duplicates will be automatically skipped.', 'royal-sentinel'); ?></p>
    </div>

    <div style="background:#fff; padding:30px; border:1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,.04); margin-bottom: 30px;">
        <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">➕ <?php _e('Deploy Rule Strategy', 'royal-sentinel'); ?></h3>
        <form id="rs-waf-form" method="post">
            <input type="hidden" name="rs_apply_rule" value="1">
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Select Strategy', 'royal-sentinel'); ?></label></th>
                    <td>
                        <select name="rule_type" id="rule_type" class="regular-text" style="width:100%">
                            <optgroup label="<?php esc_attr_e('Core Protection', 'royal-sentinel'); ?>">
                                <option value="mega_block">🛡️ <?php _e('Mega Block (IP List + HTTP 1.0 + XMLRPC + Bad Bots)', 'royal-sentinel'); ?></option>
                                <option value="secure_login">🔐 <?php _e('Secure Login (Challenge Admin & Login Pages)', 'royal-sentinel'); ?></option>
                                <option value="bad_ips">💀 <?php _e('Challenge Bad IPs (Threat Score > 20)', 'royal-sentinel'); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e('Advanced Security', 'royal-sentinel'); ?>">
                                <option value="sqli_xss">💉 <?php _e('SQLi & XSS Protection', 'royal-sentinel'); ?></option>
                                <option value="fake_bots">🤖 <?php _e('Block Fake Bots (Fake Googlebot)', 'royal-sentinel'); ?></option>
                                <option value="ai_bad_crawlers">🚫 <?php _e('Block AI & Bad Crawlers (Bandwidth Saver)', 'royal-sentinel'); ?></option>
                            </optgroup>
                        </select>
                        <p class="description"><?php printf( __('<b>Mega Block</b> attempts to include <code>$badips</code> List if configured.', 'royal-sentinel') ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e('Target Domain', 'royal-sentinel'); ?></label></th>
                    <td>
                        <select name="target_zone" id="target_zone" class="regular-text" style="width:400px">
                            <option value=""><?php _e('Select Domain', 'royal-sentinel'); ?></option>
                            <option value="all">🌐 <?php _e('All Connected Domains', 'royal-sentinel'); ?></option>
                            <?php foreach ( $zones_data as $zone ): ?>
                                <option value="<?php echo esc_attr($zone['id']); ?>" <?php selected($selected_zone_id, $zone['id']); ?>>
                                    <?php echo esc_html($zone['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            
            <div style="margin-top:20px; padding-left: 220px; display:flex; align-items:center; gap:10px;"> 
                <button type="submit" id="waf-submit-btn" class="button button-primary button-hero"><?php _e('Deploy Rule', 'royal-sentinel'); ?></button>
                <?php if ($is_global_mode): ?>
                <button type="button" id="bulk-deploy-btn" class="button button-hero" style="background:#10b981; color:#fff; border:1px solid #059669;">
                    <span class="dashicons dashicons-cloud-upload" style="vertical-align:middle;"></span> <?php _e('Bulk Deploy Rule', 'royal-sentinel'); ?>
                </button>
                <button type="button" id="bulk-delete-btn" class="button button-hero button-link-delete" style="color:#d63638; border:1px solid #d63638;">
                    <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> <?php _e('Bulk Delete Rule', 'royal-sentinel'); ?>
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <h3 style="margin-bottom:10px;">📜 <?php _e('Active Rules (Async Load)', 'royal-sentinel'); ?></h3>
    <div id="rs-rules-container">
        <div style="padding:20px; text-align:center; color:#666;"><?php _e('Select a domain above to view active rules.', 'royal-sentinel'); ?></div>
    </div>
</div>

<style>
    .spin { animation: spin 2s infinite linear; } 
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    
    /* Switch Toggle CSS */
    .rs-switch { position: relative; display: inline-block; width: 34px; height: 18px; margin-bottom: 0; }
    .rs-switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 2px; bottom: 2px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: #2271b1; }
    input:focus + .slider { box-shadow: 0 0 1px #2271b1; }
    input:checked + .slider:before { transform: translateX(16px); }
    
    /* Select2 Height Fix */
    .select2-container .select2-selection--single { height: 35px !important; border-color: #8c8f94 !important; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 33px !important; }
</style>

<script>
jQuery(document).ready(function($) {
    
    // --- Data from PHP ---
    const allZones = <?php echo json_encode($zones_data); ?>;
    const initialZoneId = "<?php echo $selected_zone_id; ?>";
    // Use WordPress admin-ajax.php like Geo-Blocking
    
    // 1. Initialize Select2
    $('#target_zone').select2({ placeholder: "<?php _e('Select a domain', 'royal-sentinel'); ?>", allowClear: true });

    // 2. Main Function to Render & Load Rules
    function renderAndLoad(zoneId) {
        var container = $('#rs-rules-container');
        container.empty();

        if (!zoneId) {
            container.html('<div style="padding:20px; text-align:center; color:#666;">Please select a domain to view rules.</div>');
            return;
        }

        var targets = [];
        if (zoneId === 'all') {
            targets = allZones;
        } else {
            var found = allZones.find(z => z.id == zoneId);
            if(found) targets.push(found);
        }

        if (targets.length === 0) {
            container.html('<div style="padding:20px; text-align:center; color:#666;">No valid domain selected.</div>');
            return;
        }

        // Render Skeletons
        $.each(targets, function(i, zone) {

            var limit = (zone.plan && zone.plan.name && zone.plan.name.toLowerCase().includes('free')) ? 5 : 25;
            var html = 
            '<div class="rs-domain-box" data-zone-id="' + zone.id + '" data-limit="' + limit + '" style="background:#fff; border:1px solid #ccd0d4; margin-bottom:20px; border-radius:5px; overflow:hidden;">' +
                '<div style="background:#f0f0f1; padding:10px 15px; border-bottom:1px solid #ccd0d4; display:flex; justify-content:space-between; align-items:center;">' +
                    '<div>' +
                        '<strong style="color:#2271b1;">🌐 ' + zone.name + '</strong> <small style="color:#999;">(' + (zone.plan && zone.plan.name && zone.plan.name.toLowerCase().includes('free') ? 'Free Website' : (zone.plan ? zone.plan.name : 'Unknown')) + ')</small>' +
                    '</div>' +
                    '<span class="rs-rule-count" style="background:#e5e5e5; padding:2px 8px; border-radius:10px; font-size:11px;">Loading...</span>' +
                '</div>' +
                '<div class="rs-rules-content" style="padding:0;">' +
                    '<div style="padding:20px; text-align:center; color:#666;"><span class="dashicons dashicons-update spin"></span> Checking Cloudflare...</div>' +
                '</div>' +
            '</div>';
            container.append(html);
        });

        // Trigger Async Load
        loadRulesSequence(0, $('.rs-domain-box'));
    }

    // 3. Recursive Loader
    function loadRulesSequence(index, elements) {
        if (index >= elements.length) return;
        var box = $(elements[index]);
        var zID = box.data("zone-id");

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_waf_rules', rs_action: 'fetch', zone_id: zID },
            dataType: 'json',
            success: function(response) {
                var html = '';
                if ( response.success === false ) {
                     box.find(".rs-rule-count").text("Error").css({"background":"#f8d7da", "color":"red"});
                     html = '<div style="padding:15px; color:red;">⚠️ Error: ' + response.message + '</div>';
                } 
                else if ( !response.data || response.data.length === 0 ) {
                    box.find(".rs-rule-count").text("0 Rules");
                    html = '<div style="padding:15px; color:#888; font-style:italic;">No custom firewall rules found.</div>';
                } 
                else {
                    var rules = response.data;
                    var limit = box.data('limit');
                    var count = rules.length;
                    
                    var badgeColor = (count >= limit) ? "#f8d7da" : "#d1f4d1";
                    var badgeBorder = (count >= limit) ? "red" : "green";
                    
                    box.find(".rs-rule-count").text(count + " / " + limit + " <?php _e('Rules', 'royal-sentinel'); ?>").css({"background":badgeColor, "border":"1px solid " + badgeBorder});
                    box.data('current-count', count);
                    
                    html += '<table class="wp-list-table widefat fixed striped" style="border:none;"><thead><tr><th style="width:250px;">Name</th><th>Expression</th><th style="width:100px;">Action</th><th style="width:140px; text-align:right;">Manage</th></tr></thead><tbody>';
                    
                    $.each(rules, function(i, rule) {
                        var ruleName = rule.description ? rule.description : '<span style="color:red; font-weight:bold;">⚠️ Unused Filter</span>';
                        var expression = (rule.filter && rule.filter.expression) ? rule.filter.expression : '<span style="color:#ccc;">No Expression</span>';
                        var filterId = (rule.filter && rule.filter.id) ? rule.filter.id : '';
                        
                        var isPaused = rule.paused;
                        var checked = isPaused ? '' : 'checked';
                        var toggleHtml = '<label class="rs-switch" style="margin-right:10px; vertical-align:middle;" title="Toggle Active/Inactive">' +
                                         '<input type="checkbox" class="rs-rule-toggle" data-zone-id="' + zID + '" data-rule-id="' + rule.id + '" ' + checked + '>' +
                                         '<span class="slider round"></span></label>';

                        html += '<tr>' +
                                '<td><strong>' + ruleName + '</strong></td>' +
                                '<td><code style="color:#d63638; word-break:break-all; white-space: pre-wrap;">' + expression + '</code></td>' +
                                '<td>' + (rule.action ? rule.action.toUpperCase() : 'UNKNOWN') + '</td>' +
                                '<td style="text-align:right; display:flex; justify-content:flex-end; align-items:center;">' + toggleHtml + '<button type="button" class="button button-small button-link-delete rs-rule-delete-btn" style="color:red;" data-zone-id="' + zID + '" data-rule-id="' + rule.id + '" data-filter-id="' + filterId + '">Delete</button></td>' +
                                '</tr>';
                    });
                    html += '</tbody></table>';
                }
                box.find(".rs-rules-content").html(html);
                loadRulesSequence(index + 1, elements);
            },
            error: function(xhr, status, error) { 
                box.find(".rs-rule-count").text("Error").css({"background":"#f8d7da", "color":"red"});
                box.find(".rs-rules-content").html('<div style="padding:15px; color:red;">⚠️ AJAX Error: ' + (error || 'Connection failed') + '</div>');
                loadRulesSequence(index + 1, elements); 
            }
        });
    }

    // 4. Change Event
    $('#target_zone').on('change', function() {
        renderAndLoad($(this).val());
    });

    // 5. Initial Load (If ID exists)
    if(initialZoneId) {
        renderAndLoad(initialZoneId);
    }

    // --- Form Submit Animation ---
    $("#rs-waf-form").on("submit", function(e) {
        var zval = $('#target_zone').val();
        if(!zval){
             e.preventDefault();
             Swal.fire({ title: "Warning", text: "Please select a target domain.", icon: "warning", timer: 1500, showConfirmButton: false });
             return;
        }

        // Limit Check for Single Domain
        if (zval !== 'all') {
            var box = $('.rs-domain-box[data-zone-id="'+zval+'"]');
            // Only check if we have data loaded
            if (box.length > 0 && box.data('current-count') !== undefined) {
                var limit = box.data('limit');
                var count = box.data('current-count');
                
                if (count >= limit) {
                    e.preventDefault();
                    Swal.fire({ title: "Limit Reached", html: "You have used " + count + "/" + limit + " rules. <br>Free Plan limit is 5.", icon: "error", timer: 1500, showConfirmButton: false });
                    return;
                }
            }
            
            $("#waf-submit-btn").prop('disabled', true).text('Processing...');
            Swal.fire({ title: 'Deploying...', text: 'Applying Rule...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
        } else {
            // Deploy to All Domains with Progress
            e.preventDefault();
            deployToAllDomainsWithProgress();
        }
    });
    
    // Deploy to All Domains with Progress Bar
    function deployToAllDomainsWithProgress() {
        var ruleType = $('#rule_type').val();
        var targets = allZones.map(z => z.id);
        var total = targets.length;
        var current = 0;
        var success = 0;
        var skipped = 0;
        
        Swal.fire({
            title: 'Deploying to All Domains',
            html: '<div style="margin: 20px 0;"><div style="background: #e0e0e0; border-radius: 10px; overflow: hidden; height: 30px;"><div id="progress-bar" style="background: linear-gradient(90deg, #2271b1, #135e96); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;"></div></div><p id="progress-text" style="margin-top: 10px; color: #666;">Processing 0/' + total + ' domains...</p></div>',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                processNextDomain(0);
            }
        });
        
        function processNextDomain(index) {
            if (index >= targets.length) {
                var msg = 'Deployed to ' + success + ' domain(s).';
                if (skipped > 0) msg += ' Skipped ' + skipped + ' (Duplicate).';
                Swal.fire({ title: 'Completed!', text: msg, icon: 'success', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                return;
            }
            
            current = index + 1;
            var percent = Math.round((current / total) * 100);
            $('#progress-bar').css('width', percent + '%').text(percent + '%');
            $('#progress-text').text('Processing ' + current + '/' + total + ' domains...');
            
            var zoneId = targets[index];
            var ruleName = getRuleName(ruleType);
            
            // Check if rule exists
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_waf_rules', rs_action: 'fetch', zone_id: zoneId },
                dataType: 'json',
                success: function(response) {
                    var exists = false;
                    if (response.success && response.data) {
                        response.data.forEach(function(rule) {
                            if (rule.description === ruleName) exists = true;
                        });
                    }
                    
                    if (exists) {
                        skipped++;
                        processNextDomain(index + 1);
                    } else {
                        // Deploy rule via AJAX
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_waf_rules', rs_action: 'deploy', rule_type: ruleType, zone_id: zoneId },
                            dataType: 'json',
                            success: function(res) {
                                if (res.success) success++;
                                processNextDomain(index + 1);
                            },
                            error: function() {
                                processNextDomain(index + 1);
                            }
                        });
                    }
                },
                error: function() {
                    processNextDomain(index + 1);
                }
            });
        }
        
        function getRuleName(type) {
            var names = {
                'mega_block': 'Mega Block (Royal Sentinel)',
                'secure_login': 'Secure Login (Royal Sentinel)',
                'bad_ips': 'Challenge Bad IPs (Royal Sentinel)',
                'sqli_xss': 'SQLi/XSS Protection (Royal Sentinel)',
                'fake_bots': 'Block Fake Bots (Royal Sentinel)'
            };
            return names[type] || '';
        }
    }

    // --- Toggle & Delete Handlers (Same as before) ---
    $(document).on("change", ".rs-rule-toggle", function() {
        var checkbox = $(this);
        checkbox.prop('disabled', true);
        var isChecked = checkbox.is(":checked");
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_waf_rules', rs_action: 'toggle', zone_id: checkbox.data("zone-id"), rule_id: checkbox.data("rule-id"), state: isChecked ? 'on' : 'off' },
            dataType: 'json',
            success: function(res) {
                checkbox.prop('disabled', false);
                if(!res.success) {
                    checkbox.prop('checked', !isChecked);
                    Swal.fire({ title: "Error", text: res.message, icon: "error", timer: 1500, showConfirmButton: false });
                } else {
                    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                    Toast.fire({ icon: 'success', title: isChecked ? 'Rule Activated' : 'Rule Paused' });
                }
            },
            error: function() { checkbox.prop('disabled', false); checkbox.prop('checked', !isChecked); }
        });
    });

    $(document).on("click", ".rs-rule-delete-btn", function(e) {
        e.preventDefault();
        var btn = $(this);
        Swal.fire({ 
            title: "Delete Rule?", icon: "warning", showCancelButton: true, confirmButtonColor: "#d33", confirmButtonText: "Yes, delete!" 
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Deleting...', showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_waf_rules', rs_action: 'delete', zone_id: btn.data("zone-id"), rule_id: btn.data("rule-id"), filter_id: btn.data("filter-id") },
                    dataType: 'json',
                    success: function() {
                        Swal.fire({ title: 'Deleted!', icon: 'success', timer: 1500, showConfirmButton: false });
                        btn.closest("tr").fadeOut(500, function(){ $(this).remove(); });
                    }
                });
            }
        });
    });

    $('#bulk-deploy-btn').click(function() {
        var selectedRule = $('#rule_type').val();
        var selectedRuleText = $('#rule_type option:selected').text();
        
        if (!selectedRule) {
            Swal.fire({ title: 'Warning', text: 'Please select a rule strategy first.', icon: 'warning', timer: 1500, showConfirmButton: false });
            return;
        }
        
        Swal.fire({
            title: 'Bulk Deploy Rule',
            html: '<p>Deploy <strong>' + selectedRuleText + '</strong> to all connected domains?</p><p style="color:#666; font-size:13px;">Duplicates will be automatically skipped.</p>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Deploy to All Domains',
            confirmButtonColor: '#10b981',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                bulkDeployWithProgress(selectedRule, selectedRuleText);
            }
        });
    });
    
    // Bulk Deploy with Progress Bar
    function bulkDeployWithProgress(ruleType, ruleText) {
        var targets = allZones;
        var total = targets.length;
        var current = 0;
        var success = 0;
        var skipped = 0;
        
        Swal.fire({
            title: 'Deploying ' + ruleText,
            html: '<div style="margin: 20px 0;"><div style="background: #e0e0e0; border-radius: 10px; overflow: hidden; height: 30px;"><div id="progress-bar" style="background: linear-gradient(90deg, #10b981, #059669); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;"></div></div><p id="progress-text" style="margin-top: 10px; color: #666;">Processing 0/' + total + ' domains...</p></div>',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                processNextDomain(0);
            }
        });
        
        function processNextDomain(index) {
            if (index >= targets.length) {
                var msg = 'Deployed to ' + success + ' domain(s).';
                if (skipped > 0) msg += ' Skipped ' + skipped + ' (Duplicate).';
                Swal.fire({ title: 'Success!', text: msg, icon: 'success', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                return;
            }
            
            current = index + 1;
            var percent = Math.round((current / total) * 100);
            $('#progress-bar').css('width', percent + '%').text(percent + '%');
            $('#progress-text').html('Processing <strong>' + targets[index].name + '</strong> (' + current + '/' + total + ')...');
            
            var zoneId = targets[index].id;
            var ruleName = getRuleName(ruleType);
            
            // Check if exists first
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_waf_rules', rs_action: 'fetch', zone_id: zoneId },
                dataType: 'json',
                success: function(response) {
                    var exists = false;
                    if (response.success && response.data) {
                        response.data.forEach(function(rule) {
                            if (rule.description === ruleName) exists = true;
                        });
                    }
                    
                    if (exists) {
                        skipped++;
                        processNextDomain(index + 1);
                    } else {
                        // Deploy via AJAX
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_waf_rules', rs_action: 'deploy', rule_type: ruleType, zone_id: zoneId },
                            dataType: 'json',
                            success: function(res) {
                                if (res.success) success++;
                                processNextDomain(index + 1);
                            },
                            error: function() {
                                processNextDomain(index + 1);
                            }
                        });
                    }
                },
                error: function() {
                    processNextDomain(index + 1);
                }
            });
        }
        
        function getRuleName(type) {
            var names = {
                'mega_block': 'Mega Block (Royal Sentinel)',
                'secure_login': 'Secure Login (Royal Sentinel)',
                'bad_ips': 'Challenge Bad IPs (Royal Sentinel)',
                'sqli_xss': 'SQLi/XSS Protection (Royal Sentinel)',
                'fake_bots': 'Block Fake Bots (Royal Sentinel)'
            };
            return names[type] || '';
        }
    }

    $('#bulk-delete-btn').click(function() {
        Swal.fire({
            title: 'Bulk Delete Rule',
            input: 'text',
            inputLabel: 'Enter exact Rule Name',
            inputPlaceholder: 'e.g. Mega Block (Royal Sentinel)',
            showCancelButton: true,
            confirmButtonText: 'Delete Everywhere',
            confirmButtonColor: '#d63638'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                bulkDeleteWithProgress(result.value);
            }
        });
    });
    
    // Bulk Delete with Progress Bar
    function bulkDeleteWithProgress(ruleName) {
        var targets = allZones;
        var total = targets.length;
        var current = 0;
        var deleted = 0;
        
        Swal.fire({
            title: 'Deleting "' + ruleName + '"',
            html: '<div style="margin: 20px 0;"><div style="background: #e0e0e0; border-radius: 10px; overflow: hidden; height: 30px;"><div id="progress-bar" style="background: linear-gradient(90deg, #d63638, #a02828); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;"></div></div><p id="progress-text" style="margin-top: 10px; color: #666;">Processing 0/' + total + ' domains...</p></div>',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                processNextDomain(0);
            }
        });
        
        function processNextDomain(index) {
            if (index >= targets.length) {
                Swal.fire({ title: 'Finished!', text: 'Deleted from ' + deleted + ' domain(s).', icon: 'success', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                return;
            }
            
            current = index + 1;
            var percent = Math.round((current / total) * 100);
            $('#progress-bar').css('width', percent + '%').text(percent + '%');
            $('#progress-text').html('Checking <strong>' + targets[index].name + '</strong> (' + current + '/' + total + ')...');
            
            var zoneId = targets[index].id;
            
            // Fetch rules and delete if found
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_waf_rules', rs_action: 'fetch', zone_id: zoneId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        var foundRule = null;
                        response.data.forEach(function(rule) {
                            if (rule.description === ruleName) foundRule = rule;
                        });
                        
                        if (foundRule) {
                            var filterId = (foundRule.filter && foundRule.filter.id) ? foundRule.filter.id : '';
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_waf_rules', rs_action: 'delete', zone_id: zoneId, rule_id: foundRule.id, filter_id: filterId },
                                dataType: 'json',
                                success: function() {
                                    deleted++;
                                    processNextDomain(index + 1);
                                },
                                error: function() {
                                    processNextDomain(index + 1);
                                }
                            });
                        } else {
                            processNextDomain(index + 1);
                        }
                    } else {
                        processNextDomain(index + 1);
                    }
                },
                error: function() {
                    processNextDomain(index + 1);
                }
            });
        }
    }

});
</script>
