<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ================================================================
// ১. হেল্পার ফাংশন
// ================================================================
if (!function_exists('rs_get_ratelimit_id')) {
    function rs_get_ratelimit_id($z_id, $email, $key) {
        // Use direct entrypoint for better permission compatibility
        $url = "https://api.cloudflare.com/client/v4/zones/$z_id/rulesets/phases/http_ratelimit/entrypoint";
        $res = rs_execute_cf_curl($url, [], 'GET', $email, $key);
        
        // DEBUG: Log the raw response to error log
        error_log('[RS Debug] Rate Limit GET Response: ' . print_r($res, true));
        
        // Safe check: success AND data has id
        if ( !empty($res['success']) && !empty($res['data']['id']) ) {
            return ['success' => true, 'id' => $res['data']['id']];
        }
        
        // If entrypoint not found or error, try to create it
        $create_url = "https://api.cloudflare.com/client/v4/zones/$z_id/rulesets";
        $payload = ['name' => 'Royal Sentinel Rate Limit', 'kind' => 'zone', 'phase' => 'http_ratelimit'];
        $create_res = rs_execute_cf_curl($create_url, $payload, 'POST', $email, $key);
        
        // DEBUG: Log the creation response
        error_log('[RS Debug] Rate Limit CREATE Response: ' . print_r($create_res, true));
        
        // Safe check for creation response
        if ( !empty($create_res['success']) && !empty($create_res['data']['id']) ) {
            return ['success' => true, 'id' => $create_res['data']['id']];
        }

        // Return the actual error message from Cloudflare
        $err = isset($create_res['message']) ? $create_res['message'] : (isset($res['message']) ? $res['message'] : 'Ruleset access failed.');
        return ['success' => false, 'message' => $err];
    }
}

// ================================================================
// ২. AJAX হ্যান্ডলার
// ================================================================
if ( isset( $_POST['rs_rl_action'] ) ) {
    // AGGRESSIVE: Kill ALL output and errors
    @error_reporting(0);
    @ini_set('display_errors', 0);
    while ( ob_get_level() ) { ob_end_clean(); }
    ob_start(); // Start fresh buffer
    
    // TEST ACTION: Returns minimal JSON to test if AJAX works at all
    if ( $_POST['rs_rl_action'] == 'test' ) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'test' => 'ok']);
        exit;
    }
    
    $api_email = get_option('rs_cf_email');
    $api_key   = get_option('rs_cf_api_key');
    $zone_id   = sanitize_text_field($_POST['zone_id']);

    if ( $_POST['rs_rl_action'] == 'fetch_rules' ) {
        // DEBUG MODE: Return raw API response for troubleshooting
        $debug_mode = isset($_POST['debug']) && $_POST['debug'] == '1';
        
        $rs_result = rs_get_ratelimit_id($zone_id, $api_email, $api_key);
        
        if ( $debug_mode ) {
            wp_send_json(['debug' => true, 'rs_get_ratelimit_id_result' => $rs_result]);
        }
        
        if ( ! $rs_result['success'] ) {
            $msg = isset($rs_result['message']) ? $rs_result['message'] : 'Unknown error';
            wp_send_json(['success' => false, 'message' => $msg]);
        }
        $ruleset_id = $rs_result['id'];

        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id";
        $res = rs_execute_cf_curl($url, [], 'GET', $api_email, $api_key);
        
        // CF Ruleset API returns rules in ['data']['rules']
        if( !empty($res['success']) && isset($res['data']['rules'])) {
             wp_send_json(['success' => true, 'data' => $res['data']['rules']]);
        } elseif ( empty($res['success']) ) {
            $msg = isset($res['message']) ? $res['message'] : 'Unknown API Error';
            wp_send_json(['success' => false, 'message' => $msg]);
        } else {
             wp_send_json(['success' => true, 'data' => []]); // Empty rules is success
        }
    }

    if ( $_POST['rs_rl_action'] == 'deploy_rule' ) {
        $rs_result = rs_get_ratelimit_id($zone_id, $api_email, $api_key);
        if ( ! $rs_result['success'] ) {
             wp_send_json(['success' => false, 'message' => $rs_result['message']]);
        }
        $ruleset_id = $rs_result['id'];

        $rule_payload = [
            'description' => "Smart Rate Limiting (Free plan friendly)",
            'expression'  => '((http.request.uri.path eq "/xmlrpc.php") or (http.request.uri.path contains "/wp-login.php") or (http.request.uri.path contains "/wp-admin" and not http.request.uri.path contains "/admin-ajax.php")) and (not cf.client.bot) and (not http.cookie contains "wordpress_logged_in_")',

            'action'      => 'block',
            'ratelimit'   => [
                'characteristics' => ['ip.src', 'cf.colo.id'], 
                'period' => 10, 
                'requests_per_period' => 20,
                'mitigation_timeout' => 10
            ],
            'enabled'     => true
        ];
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id/rules";
        $res = rs_execute_cf_curl($url, $rule_payload, 'POST', $api_email, $api_key);
        wp_send_json($res);
    }

    if ( $_POST['rs_rl_action'] == 'bulk_deploy_rule' ) {
        $all_zones = rs_get_cf_zones()['items'];
        $success_count = 0; $skip_count = 0;

        foreach ($all_zones as $zone) {
            $rs_result = rs_get_ratelimit_id($zone['id'], $api_email, $api_key);
            if ( ! $rs_result['success'] ) continue; // Skip if ruleset not available for this zone
            $ruleset_id = $rs_result['id'];

            $rule_payload = [
                'description' => "Smart Rate Limiting (Free plan friendly)",
                'expression'  => '((http.request.uri.path eq "/xmlrpc.php") or (http.request.uri.path contains "/wp-login.php") or (http.request.uri.path contains "/wp-admin" and not http.request.uri.path contains "/admin-ajax.php")) and (not cf.client.bot) and (not http.cookie contains "wordpress_logged_in_")',

                'action'      => 'block',
                'ratelimit'   => [
                    'characteristics' => ['ip.src', 'cf.colo.id'], 
                    'period' => 10, 
                    'requests_per_period' => 20,
                    'mitigation_timeout' => 10
                ],
                'enabled'     => true
            ];
            $url = "https://api.cloudflare.com/client/v4/zones/{$zone['id']}/rulesets/$ruleset_id/rules";
            $res = rs_execute_cf_curl($url, $rule_payload, 'POST', $api_email, $api_key);
            if ($res['success']) {
                $success_count++;
            } else {
                $skip_count++;
            }
        }
        wp_send_json(['success' => true, 'message' => sprintf(__('%d rules deployed, %d skipped.', 'royal-sentinel'), $success_count, $skip_count)]);
    }
    
    if ( $_POST['rs_rl_action'] == 'delete_rule' ) {
        $rule_id = sanitize_text_field($_POST['rule_id']);
        $rs_result = rs_get_ratelimit_id($zone_id, $api_email, $api_key);
        if ( ! $rs_result['success'] ) {
             wp_send_json(['success' => false, 'message' => $rs_result['message']]);
        }
        $ruleset_id = $rs_result['id'];
        
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id/rules/$rule_id";
        $res = rs_execute_cf_curl($url, [], 'DELETE', $api_email, $api_key);
        wp_send_json($res);
    }
    
    // Ensure script stops after handling AJAX
    exit;
}

// ================================================================
// ৩. মূল ইন্টারফেস
// ================================================================
$zones_data = function_exists( 'rs_get_cf_zones' ) ? rs_get_cf_zones()['items'] : [];
$current_domain_clean = strtolower(str_replace('www.', '', parse_url(get_site_url(), PHP_URL_HOST)));
$is_global_mode = (get_option('rs_cf_auth_mode', 'global') !== 'single');
$selected_zone_id = '';
foreach($zones_data as $z) {
    if ($z['name'] === $current_domain_clean) {
        $selected_zone_id = $z['id'];
        break;
    }
}
?>

<div class="rs-waf-wrap">
    <div style="background:#fff; padding:20px; border-left: 5px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom:20px;">
        <h2 style="margin:0; color:#d63638;">⏱️ <?php _e('Custom Rate Limiting', 'royal-sentinel'); ?></h2>
        <p style="margin: 5px 0 0; color: #666;"><?php _e('Protect your login and sensitive pages from Brute Force attacks.', 'royal-sentinel'); ?></p>
    </div>

    <div style="background:#fff; padding:30px; border:1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,.04); margin-bottom: 30px;">
        <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">➕ <?php _e('Deploy Rate Limit', 'royal-sentinel'); ?></h3>
        <form id="rs-rl-form" method="post">
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Strategy', 'royal-sentinel'); ?></label></th>
                    <td>
                        <select id="rl_strategy" class="regular-text" style="width:100%">
                            <option value="smart_login" selected>🔐 <?php _e('Smart Rate Limiting Rule (Free plan friendly)', 'royal-sentinel'); ?></option>
                        </select>
                        <p class="description"><?php _e('Protects login, admin, and XMLRPC pages. Automatically exempts logged-in users &amp; admin-ajax.php so DataTables, Gutenberg, and dashboard AJAX requests never get blocked.', 'royal-sentinel'); ?></p>
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
                <button type="submit" id="rl-submit-btn" class="button button-primary button-hero"><?php _e('Deploy Rate Limit', 'royal-sentinel'); ?></button>
                <?php if ($is_global_mode): ?>
                <button type="button" id="rl-bulk-deploy-btn" class="button button-hero" style="background:#10b981; color:#fff; border:1px solid #059669;">
                    <span class="dashicons dashicons-cloud-upload" style="vertical-align:middle;"></span> <?php _e('Bulk Deploy Rule', 'royal-sentinel'); ?>
                </button>
                <button type="button" id="rl-bulk-delete-btn" class="button button-hero button-link-delete" style="color:#d63638; border:1px solid #d63638;">
                    <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> <?php _e('Bulk Delete Rule', 'royal-sentinel'); ?>
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <h3 style="margin-bottom:10px;">📜 <?php _e('Active Rate Limits (Async Load)', 'royal-sentinel'); ?></h3>
    <div id="rs-rl-container">
        <div style="padding:20px; text-align:center; color:#666;"><?php _e('Select a domain above to view active rules.', 'royal-sentinel'); ?></div>
    </div>
</div>

<style>
    .spin { animation: spin 2s infinite linear; } 
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .select2-container .select2-selection--single { height: 35px !important; border-color: #8c8f94 !important; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 33px !important; }
</style>

<script>
jQuery(document).ready(function($) {
    const allZones = <?php echo json_encode($zones_data); ?>;
    const initialZoneId = "<?php echo $selected_zone_id; ?>";
    const ajaxUrl = ajaxurl; // WordPress admin-ajax.php
    
    // Init Select2
    $('#target_zone').select2({ placeholder: "Select a domain", allowClear: true });

    // Render & Load
    function renderAndLoad(zoneId) {
        var container = $('#rs-rl-container');
        container.empty();

        if (!zoneId) {
            container.html('<div style="padding:20px; text-align:center; color:#666;">Please select a domain to view rules.</div>');
            return;
        }

        var targets = [];
        if (zoneId === 'all') { targets = allZones; } 
        else {
            var found = allZones.find(z => z.id === zoneId);
            if(found) targets.push(found);
        }

        if (targets.length === 0) {
            container.html('<div style="padding:20px; text-align:center; color:#666;">No valid domain selected.</div>');
            return;
        }

        // Render Skeletons
        $.each(targets, function(i, zone) {
            var limit = (zone.plan && zone.plan.name && zone.plan.name.toLowerCase().includes('free')) ? 1 : 10;
            var html = 
            '<div class="rs-domain-box" data-zone-id="' + zone.id + '" data-limit="' + limit + '" style="background:#fff; border:1px solid #ccd0d4; margin-bottom:20px; border-radius:5px; overflow:hidden;">' +
                '<div style="background:#f0f0f1; padding:10px 15px; border-bottom:1px solid #ccd0d4; display:flex; justify-content:space-between; align-items:center;">' +
                    '<div><strong style="color:#d63638;">🌐 ' + zone.name + '</strong> <small style="color:#999;">(' + (zone.plan && zone.plan.name && zone.plan.name.toLowerCase().includes('free') ? 'Free Website' : (zone.plan ? zone.plan.name : 'Unknown')) + ')</small></div>' +
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

    function loadRulesSequence(index, elements) {
        if (index >= elements.length) return;
        var box = $(elements[index]);
        var zID = box.data("zone-id");

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ratelimit', rs_action: 'fetch', zone_id: zID },
            dataType: 'json',
            success: function(response) {
                var html = '';
                if ( response.success === false ) {
                     box.find(".rs-rule-count").text("Error").css({"background":"#f8d7da", "color":"red"});
                     html = '<div style="padding:15px; color:red;">⚠️ Error: ' + response.message + '</div>';
                } 
                else if ( !response.data || response.data.length === 0 ) {
                    box.find(".rs-rule-count").text("0 Rules");
                    html = '<div style="padding:15px; color:#888; font-style:italic;">No rate limits found.</div>';
                } 
                else {
                    var rules = response.data;
                    var limit = box.data('limit');
                    var count = rules.length;
                    
                    var badgeColor = (count >= limit) ? "#f8d7da" : "#d1f4d1";
                    var badgeBorder = (count >= limit) ? "red" : "green";
                    
                    box.find(".rs-rule-count").text(count + " / " + limit + " <?php _e('Rules', 'royal-sentinel'); ?>").css({"background":badgeColor, "border":"1px solid " + badgeBorder});
                    box.data('current-count', count);
                    
                    html += '<table class="wp-list-table widefat fixed striped" style="border:none;"><thead><tr><th>Name</th><th>Expression</th><th>Limit</th><th>Action</th><th style="width:80px;"></th></tr></thead><tbody>';
                    
                    $.each(rules, function(i, rule) {
                        var period = rule.ratelimit ? rule.ratelimit.period : '?';
                        var reqs = rule.ratelimit ? rule.ratelimit.requests_per_period : '?';
                        var limitStr = reqs + ' reqs / ' + period + 's';
                        
                        html += '<tr>' +
                                '<td><strong>' + (rule.description || 'Unnamed') + '</strong></td>' +
                                '<td><code style="color:#d63638;">' + rule.expression + '</code></td>' +
                                '<td>' + limitStr + '</td>' +
                                '<td>' + (rule.action || 'Unknown') + '</td>' +
                                '<td style="text-align:right;"><button type="button" class="button button-small button-link-delete rs-rl-delete-btn" style="color:red;" data-zone-id="' + zID + '" data-rule-id="' + rule.id + '">Delete</button></td>' +
                                '</tr>';
                    });
                    html += '</tbody></table>';
                }
                box.find(".rs-rules-content").html(html);
                loadRulesSequence(index + 1, elements);
            },
            error: function(xhr, status, error) { 
                var errMsg = "Unknown Error";
                if(xhr.responseJSON && xhr.responseJSON.message) errMsg = xhr.responseJSON.message;
                else if(xhr.statusText) errMsg = xhr.status + " " + xhr.statusText;
                
                box.find(".rs-rules-content").html('<div style="padding:15px; color:red;">⚠️ AJAX Error: ' + errMsg + '</div>');
                // Also check console
                console.log("RS RateLimit Error:", xhr.responseText);
                loadRulesSequence(index + 1, elements); 
            }
        });
    }

    $('#target_zone').on('change', function() {
        renderAndLoad($(this).val());
    });

    if(initialZoneId) {
        renderAndLoad(initialZoneId);
    }

    // Deploy
    $("#rs-rl-form").on("submit", function(e) {
        e.preventDefault();
        var zval = $('#target_zone').val();
         if(!zval){
             Swal.fire({ title: '<?php _e('Warning', 'royal-sentinel'); ?>', text: '<?php _e('Please select a target domain.', 'royal-sentinel'); ?>', icon: 'warning', timer: 1500, showConfirmButton: false });
             return;
        }

        // Limit Check (Free Plan = 1 Rule)
        if (zval !== 'all') {
            var box = $('.rs-domain-box[data-zone-id="'+zval+'"]');
            if (box.length > 0 && box.data('current-count') !== undefined) {
                var limit = box.data('limit');
                var count = box.data('current-count');
                
                if (count >= limit) {
                    Swal.fire({ title: '<?php _e('Limit Reached', 'royal-sentinel'); ?>', text: '<?php _e('Free Plan supports only', 'royal-sentinel'); ?> ' + limit + ' <?php _e('Rate Limiting rule.', 'royal-sentinel'); ?>', icon: 'error', timer: 1500, showConfirmButton: false });
                    return;
                }
            }
        }

        // Logic for "All" vs Single
        var targets = [];
        if (zval === 'all') { targets = allZones.map(z => z.id); } 
        else { targets = [zval]; }

        Swal.fire({ title: 'Deploying...', html: 'Applying Rate Limit to selected domain(s)...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });

        // Recursive deploy to handle multiple
        deploySequence(0, targets);
    });

    function deploySequence(index, ids) {
        if(index >= ids.length) {
            Swal.fire({ title: 'Done!', icon: 'success', timer: 1500, showConfirmButton: false });
            var final = $('#target_zone').val();
            renderAndLoad(final); // Refresh current view
            return;
        }
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ratelimit', rs_action: 'deploy', zone_id: ids[index] },
            dataType: 'json',
            success: function(response) { 
                if(response.success === false) {
                     var err = response.message || "Unknown API Error";
                     var isDuplicate = response.duplicate || false;
                     
                     Swal.fire({ 
                         title: isDuplicate ? '<?php _e('Duplicate!', 'royal-sentinel'); ?>' : '<?php _e('Error', 'royal-sentinel'); ?>', 
                         text: err, 
                         icon: isDuplicate ? 'warning' : 'error', 
                         showConfirmButton: true 
                     });
                     return;
                }
                deploySequence(index + 1, ids); 
            },
            error: function(xhr) { 
                Swal.fire({ title: 'Error', text: 'Connection failed: ' + xhr.statusText, icon: 'error', showConfirmButton: true });
            }
        });
    }

    // Delete
    $(document).on("click", ".rs-rl-delete-btn", function(e) {
        e.preventDefault();
        var btn = $(this);
        Swal.fire({ 
            title: "Delete Rule?", icon: "warning", showCancelButton: true, confirmButtonColor: "#d33", confirmButtonText: "Yes, delete!" 
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Deleting...', showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ratelimit', rs_action: 'delete', zone_id: btn.data("zone-id"), rule_id: btn.data("rule-id") },
                    dataType: 'json',
                    success: function() {
                        Swal.fire({ title: 'Deleted!', icon: 'success', timer: 1500, showConfirmButton: false });
                        btn.closest("tr").fadeOut(500, function(){ $(this).remove(); });
                    }
                });
            }
        });
    });

    // ========== BULK OPERATIONS ==========
    <?php if ($is_global_mode): ?>
    
    $('#rl-bulk-deploy-btn').click(function() {
        var selectedRuleText = $('#rl_strategy option:selected').text();
        
        Swal.fire({
            title: 'Bulk Deploy Rate Limit',
            html: '<p>Deploy <strong>' + selectedRuleText + '</strong> to all connected domains?</p><p style="color:#666; font-size:13px;">Duplicates will be automatically skipped.</p>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Deploy to All Domains',
            confirmButtonColor: '#10b981',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                bulkDeployRateLimitWithProgress();
            }
        });
    });
    
    function bulkDeployRateLimitWithProgress() {
        var targets = allZones;
        var total = targets.length;
        var current = 0;
        var success = 0;
        var skipped = 0;
        
        Swal.fire({
            title: 'Deploying Rate Limit Rule',
            html: '<div style="margin: 20px 0;"><div style="background: #e0e0e0; border-radius: 10px; overflow: hidden; height: 30px;"><div id="progress-bar" style="background: linear-gradient(90deg, #d63638, #a02828); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;"></div></div><p id="progress-text" style="margin-top: 10px; color: #666;">Processing 0/' + total + ' domains...</p></div>',
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
                Swal.fire({ title: '<?php _e('Success!', 'royal-sentinel'); ?>', text: msg, icon: 'success', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                return;
            }
            
            current = index + 1;
            var percent = Math.round((current / total) * 100);
            $('#progress-bar').css('width', percent + '%').text(percent + '%');
            $('#progress-text').html('Processing <strong>' + targets[index].name + '</strong> (' + current + '/' + total + ')...');
            
            var zoneId = targets[index].id;
            
            // Check if rule exists
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ratelimit', rs_action: 'fetch', zone_id: zoneId },
                dataType: 'json',
                success: function(response) {
                    var exists = false;
                    var needsUpdate = false;
                    if (response.success && response.data) {
                        response.data.forEach(function(rule) {
                            if (rule.description === 'Smart Rate Limiting (Free plan friendly)') {
                                exists = true;
                                if (!rule.expression || rule.expression.indexOf('wordpress_logged_in_') === -1) {
                                    needsUpdate = true;
                                }
                            }
                        });
                    }
                    
                    if (exists && !needsUpdate) {
                        skipped++;
                        processNextDomain(index + 1);
                    } else {
                        // Deploy rule
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ratelimit', rs_action: 'deploy', zone_id: zoneId },
                            dataType: 'json',
                            success: function() {
                                success++;
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
    }
    
    $('#rl-bulk-delete-btn').click(function() {
        Swal.fire({
            title: 'Bulk Delete Rate Limit',
            input: 'text',
            inputLabel: 'Enter exact Rule Name',
            inputPlaceholder: 'Smart Rate Limiting (Free plan friendly)',
            showCancelButton: true,
            confirmButtonText: 'Delete Everywhere',
            confirmButtonColor: '#d63638'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                bulkDeleteRateLimitWithProgress(result.value);
            }
        });
    });
    
    function bulkDeleteRateLimitWithProgress(ruleName) {
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
                Swal.fire({ title: '<?php _e('Finished!', 'royal-sentinel'); ?>', text: '<?php _e('Deleted from', 'royal-sentinel'); ?> ' + deleted + ' <?php _e('domain(s).', 'royal-sentinel'); ?>', icon: 'success', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                return;
            }
            
            current = index + 1;
            var percent = Math.round((current / total) * 100);
            $('#progress-bar').css('width', percent + '%').text(percent + '%');
            $('#progress-text').html('Checking <strong>' + targets[index].name + '</strong> (' + current + '/' + total + ')...');
            
            var zoneId = targets[index].id;
            
            // Fetch rules and delete if found
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ratelimit', rs_action: 'fetch', zone_id: zoneId },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        var foundRule = null;
                        response.data.forEach(function(rule) {
                            if (rule.description === ruleName) foundRule = rule;
                        });
                        
                        if (foundRule) {
                            $.ajax({
                                url: ajaxUrl,
                                type: 'POST',
                                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ratelimit', rs_action: 'delete', zone_id: zoneId, rule_id: foundRule.id },
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
    
    <?php endif; ?>

});
</script>
