<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ================================================================
// ১. হেল্পার ফাংশন
// ================================================================

if (!function_exists('rs_get_cache_ruleset_id')) {
    function rs_get_cache_ruleset_id($z_id, $email, $key) {
        // Cache Rules live in 'http_request_cache_settings' phase
        $url = "https://api.cloudflare.com/client/v4/zones/$z_id/rulesets/phases/http_request_cache_settings/entrypoint";
        $res = rs_execute_cf_curl($url, [], 'GET', $email, $key);
        
        // Safe check: success AND data has id
        if ( !empty($res['success']) && !empty($res['data']['id']) ) {
            return ['success' => true, 'id' => $res['data']['id']];
        }
        
        // If entrypoint doesn't exist, create it
        $create_url = "https://api.cloudflare.com/client/v4/zones/$z_id/rulesets";
        $payload = [
            'name' => 'Cache Rules', 
            'kind' => 'zone', 
            'phase' => 'http_request_cache_settings'
        ];
        $create_res = rs_execute_cf_curl($create_url, $payload, 'POST', $email, $key);
        
        // Safe check for creation response
        if ( !empty($create_res['success']) && !empty($create_res['data']['id']) ) {
            return ['success' => true, 'id' => $create_res['data']['id']];
        }

        $err = isset($create_res['message']) ? $create_res['message'] : (isset($res['message']) ? $res['message'] : 'Cache ruleset access failed.');
        return ['success' => false, 'message' => $err];
    }
}

// ================================================================
// ২. AJAX হ্যান্ডলার
// ================================================================
if ( isset( $_POST['rs_cache_op'] ) ) {
    // Clean ALL output buffers to prevent PHP notices from breaking JSON
    while ( ob_get_level() ) { ob_end_clean(); }
    @ini_set('display_errors', 0);
    
    $api_email = get_option('rs_cf_email');
    $api_key   = get_option('rs_cf_api_key');
    $zone_id   = sanitize_text_field($_POST['zone_id']);

    if ( $_POST['rs_cache_op'] == 'fetch' ) {
        $rs_result = rs_get_cache_ruleset_id($zone_id, $api_email, $api_key);
        if(!$rs_result['success']) {
            // Check for authentication error and provide helpful message
            $msg = $rs_result['message'];
            if ( stripos($msg, 'authentication') !== false || stripos($msg, 'token') !== false || stripos($msg, '10000') !== false ) {
                $msg = 'Authentication error: Your API Token needs "Zone - Zone Rulesets - Edit" permission for Cache Rules.';
            }
            wp_send_json(['success' => false, 'message' => $msg]); 
        }
        $ruleset_id = $rs_result['id'];

        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id";
        $res = rs_execute_cf_curl($url, [], 'GET', $api_email, $api_key);
        
        if($res['success'] && isset($res['data']['rules'])) {
             wp_send_json(['success' => true, 'data' => $res['data']['rules']]);
        } elseif (!$res['success']) {
            $msg = isset($res['message']) ? $res['message'] : 'Unknown API Error';
            if ( stripos($msg, 'authentication') !== false ) {
                $msg = 'Authentication error: Token needs "Zone - Zone Rulesets - Edit" permission.';
            }
            wp_send_json(['success' => false, 'message' => $msg]);
        } else {
             wp_send_json(['success' => true, 'data' => []]);
        }
    }

    if ( $_POST['rs_cache_op'] == 'deploy' ) {
        $strategy = sanitize_text_field($_POST['strategy']);
        $zone_id = sanitize_text_field($_POST['zone_id']);
        $rs_result = rs_get_cache_ruleset_id($zone_id, $api_email, $api_key);
        if(!$rs_result['success']) {
             wp_send_json(['success' => false, 'message' => $rs_result['message']]); 
        }
        $ruleset_id = $rs_result['id'];

        $rule_payload = [];
        
        if ($strategy === 'standard') {
            $rule_payload = [
                'description' => "Static Assets Cache (Royal Sentinel)",
                'expression'  => 'http.request.uri.path.extension in {"jpg" "jpeg" "png" "gif" "webp" "svg" "css" "js" "woff" "woff2" "ttf" "eot" "ico" "mp4" "pdf"}',
                'action'      => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 2592000, 'status_code_ttl' => [['status_code' => 200, 'value' => 2592000]]], // 1 Month
                    'browser_ttl' => ['mode' => 'respect_origin']
                ],
                'enabled' => true
            ];
        } else if ($strategy === 'aggressive') {
            $rule_payload = [
                'description' => "HTML Cache / Aggressive (Royal Sentinel)",
                'expression'  => '(http.request.method eq "GET") and (not http.request.uri.path contains "/wp-admin") and (not http.request.uri.path contains "wp-login.php") and (not http.request.uri.path contains "cart") and (not http.request.uri.path contains "checkout") and (not http.request.uri.path contains "my-account")',
                'action'      => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 7200], // 2 Hours
                    'browser_ttl' => ['mode' => 'override_origin', 'default' => 7200]
                ],
                'enabled' => true
            ];
        }
        // Laravel Cache Strategies
        else if ($strategy === 'laravel_static') {
            $rule_payload = [
                'description' => "Laravel Static Assets (Laravel)",
                'expression'  => '(http.request.uri.path starts_with "/build/") or (http.request.uri.path starts_with "/storage/")',
                'action'      => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 2592000], // 1 Month
                    'browser_ttl' => ['mode' => 'override_origin', 'default' => 2592000]
                ],
                'enabled' => true
            ];
        } else if ($strategy === 'laravel_api') {
            $rule_payload = [
                'description' => "Laravel API Cache (Laravel)",
                'expression'  => '(http.request.uri.path starts_with "/api/") and (http.request.method eq "GET")',
                'action'      => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 300], // 5 Minutes
                    'browser_ttl' => ['mode' => 'respect_origin']
                ],
                'enabled' => true
            ];
        } else if ($strategy === 'laravel_pages') {
            $rule_payload = [
                'description' => "Laravel Pages Cache (Laravel)",
                'expression'  => '(http.request.method eq "GET") and (not http.request.uri.path starts_with "/admin") and (not http.request.uri.path starts_with "/api") and (not http.request.uri.path contains "/login") and (not http.request.uri.path contains "/register")',
                'action'      => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 3600], // 1 Hour
                    'browser_ttl' => ['mode' => 'override_origin', 'default' => 1800]
                ],
                'enabled' => true
            ];
        }
        // WordPress Cache Strategies
        else if ($strategy === 'wp_rest_api') {
            $rule_payload = [
                'description' => "WordPress REST API Cache (WordPress)",
                'expression'  => '(http.request.uri.path starts_with "/wp-json/") and (http.request.method eq "GET")',
                'action'      => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 600], // 10 Minutes
                    'browser_ttl' => ['mode' => 'respect_origin']
                ],
                'enabled' => true
            ];
        } else if ($strategy === 'wp_uploads') {
            $rule_payload = [
                'description' => "WordPress Uploads Cache (WordPress)",
                'expression'  => 'http.request.uri.path starts_with "/wp-content/uploads/"',
                'action'      => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 2592000], // 1 Month
                    'browser_ttl' => ['mode' => 'override_origin', 'default' => 2592000]
                ],
                'enabled' => true
            ];
        } else if ($strategy === 'wp_pages') {
            $rule_payload = [
                'description' => "WordPress Pages Cache (WordPress)",
                'expression'  => '(http.request.method eq "GET") and (not http.request.uri.path contains "/wp-admin") and (not http.request.uri.path contains "wp-login.php") and (not http.request.uri.path contains "/cart") and (not http.request.uri.path contains "/checkout") and (not http.request.uri.path contains "/my-account") and (not http.request.uri.query contains "s=")',
                'action'      => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 1800], // 30 Minutes
                    'browser_ttl' => ['mode' => 'override_origin', 'default' => 900]
                ],
                'enabled' => true
            ];
        } else if ($strategy === 'wp_feeds') {
            $rule_payload = [
                'description' => "WordPress RSS Feeds Cache (WordPress)",
                'expression'  => '(http.request.uri.path contains "/feed/") or (http.request.uri.path eq "/feed")',
                'action'      => 'set_cache_settings',
                'action_parameters' => [
                    'cache' => true,
                    'edge_ttl' => ['mode' => 'override_origin', 'default' => 3600], // 1 Hour
                    'browser_ttl' => ['mode' => 'override_origin', 'default' => 1800]
                ],
                'enabled' => true
            ];
        }
        
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id/rules";
        $res = rs_execute_cf_curl($url, $rule_payload, 'POST', $api_email, $api_key);
        wp_send_json($res);
    }
    
    if ( $_POST['rs_cache_op'] == 'delete' ) {
        $rule_id = sanitize_text_field($_POST['rule_id']);
        $rs_result = rs_get_cache_ruleset_id($zone_id, $api_email, $api_key);
        if (!$rs_result['success']) {
             wp_send_json(['success' => false, 'message' => $rs_result['message']]); 
        }
        $ruleset_id = $rs_result['id'];
        
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id/rules/$rule_id";
        $res = rs_execute_cf_curl($url, [], 'DELETE', $api_email, $api_key);
        wp_send_json($res);
    }
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
    <div style="background:#fff; padding:20px; border-left: 5px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom:20px;">
        <h2 style="margin:0; color:#2271b1;">🚀 <?php _e('Cloudflare Cache Rules', 'royal-sentinel'); ?></h2>
        <p style="margin: 5px 0 0; color: #666;"><?php _e('Supercharge your website speed with Edge Caching rules.', 'royal-sentinel'); ?></p>
    </div>

    <div style="background:#fff; padding:30px; border:1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,.04); margin-bottom: 30px;">
        <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">➕ <?php _e('Deploy Cache Policy', 'royal-sentinel'); ?></h3>
        <form id="rs-cache-form" method="post">
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Cache Strategy', 'royal-sentinel'); ?></label></th>
                    <td>
                        <select id="cache_strategy" class="regular-text" style="width:100%">
                            <optgroup label="<?php esc_attr_e('General', 'royal-sentinel'); ?>">
                                <option value="standard">📦 <?php _e('Static Assets Cache (Safe)', 'royal-sentinel'); ?></option>
                                <option value="aggressive">⚡ <?php _e('HTML Cache (For mostly static sites)', 'royal-sentinel'); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e('Laravel', 'royal-sentinel'); ?>">
                                <option value="laravel_static">📦 <?php _e('Laravel Static Assets (Laravel)', 'royal-sentinel'); ?></option>
                                <option value="laravel_api">🔌 <?php _e('Laravel API Cache (Laravel)', 'royal-sentinel'); ?></option>
                                <option value="laravel_pages">📄 <?php _e('Laravel Pages Cache (Laravel)', 'royal-sentinel'); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e('WordPress', 'royal-sentinel'); ?>">
                                <option value="wp_rest_api">🔌 <?php _e('WordPress REST API Cache (WordPress)', 'royal-sentinel'); ?></option>
                                <option value="wp_uploads">🖼️ <?php _e('WordPress Uploads Cache (WordPress)', 'royal-sentinel'); ?></option>
                                <option value="wp_pages">📄 <?php _e('WordPress Pages Cache (WordPress)', 'royal-sentinel'); ?></option>
                                <option value="wp_feeds">📡 <?php _e('WordPress RSS Feeds Cache (WordPress)', 'royal-sentinel'); ?></option>
                            </optgroup>
                        </select>
                        <p class="description"><?php _e('<b>Static Assets</b>: Caches images, CSS, JS (1 month). <br><b>HTML Cache</b>: Caches pages for 2 hours (excludes Admin/Login/Cart).', 'royal-sentinel'); ?></p>
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
                <button type="submit" id="cache-submit-btn" class="button button-primary button-hero"><?php _e('Deploy Cache Rule', 'royal-sentinel'); ?></button>
                <?php if ($is_global_mode): ?>
                <button type="button" id="cache-bulk-deploy-btn" class="button button-hero" style="background:#10b981; color:#fff; border:1px solid #059669;">
                    <span class="dashicons dashicons-cloud-upload" style="vertical-align:middle;"></span> <?php _e('Bulk Deploy Rule', 'royal-sentinel'); ?>
                </button>
                <button type="button" id="cache-bulk-delete-btn" class="button button-hero button-link-delete" style="color:#d63638; border:1px solid #d63638;">
                    <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> <?php _e('Bulk Delete Rule', 'royal-sentinel'); ?>
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <h3 style="margin-bottom:10px;">📜 <?php _e('Active Cache Rules (Async Load)', 'royal-sentinel'); ?></h3>
    <div id="rs-cache-container">
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
        var container = $('#rs-cache-container');
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
            var limit = (zone.plan && zone.plan.name && zone.plan.name.toLowerCase().includes('free')) ? 10 : 25; // Cache Rules Free Limit is often 10
            var html = 
            '<div class="rs-domain-box" data-zone-id="' + zone.id + '" data-limit="' + limit + '" style="background:#fff; border:1px solid #ccd0d4; margin-bottom:20px; border-radius:5px; overflow:hidden;">' +
                '<div style="background:#f0f0f1; padding:10px 15px; border-bottom:1px solid #ccd0d4; display:flex; justify-content:space-between; align-items:center;">' +
                    '<div><strong style="color:#2271b1;">🌐 ' + zone.name + '</strong> <small style="color:#999;">(' + (zone.plan && zone.plan.name && zone.plan.name.toLowerCase().includes('free') ? 'Free Website' : (zone.plan ? zone.plan.name : 'Unknown')) + ')</small></div>' +
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
            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cacherules', rs_action: 'fetch', zone_id: zID },
            dataType: 'json',
            success: function(response) {
                var html = '';
                if ( response.success === false ) {
                     box.find(".rs-rule-count").text("Error").css({"background":"#f8d7da", "color":"red"});
                     html = '<div style="padding:15px; color:red;">⚠️ Error: ' + response.message + '</div>';
                } 
                else if ( !response.data || response.data.length === 0 ) {
                    box.find(".rs-rule-count").text("0 Rules");
                    html = '<div style="padding:15px; color:#888; font-style:italic;">No cache rules found.</div>';
                } 
                else {
                    var rules = response.data;
                    var limit = box.data('limit');
                    var count = rules.length;
                    
                    var badgeColor = (count >= limit) ? "#f8d7da" : "#d1f4d1";
                    var badgeBorder = (count >= limit) ? "red" : "green";

                    box.find(".rs-rule-count").text(count + " / " + limit + " <?php _e('Rules', 'royal-sentinel'); ?>").css({"background":badgeColor, "border":"1px solid " + badgeBorder});
                    box.data('current-count', count);
                    
                    html += '<table class="wp-list-table widefat fixed striped" style="border:none;"><thead><tr><th>Name</th><th>Expression</th><th>Action</th><th style="width:80px;"></th></tr></thead><tbody>';
                    
                    $.each(rules, function(i, rule) {
                        html += '<tr>' +
                                '<td><strong>' + (rule.description || 'Unnamed') + '</strong></td>' +
                                '<td><code style="color:#2271b1; font-size:11px;">' + rule.expression + '</code></td>' +
                                '<td>' + (rule.action || 'Unknown') + '</td>' +
                                '<td style="text-align:right;"><button type="button" class="button button-small button-link-delete rs-cache-delete-btn" style="color:red;" data-zone-id="' + zID + '" data-rule-id="' + rule.id + '">Delete</button></td>' +
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
                console.log("RS CacheRule Error:", xhr.responseText);
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
    $("#rs-cache-form").on("submit", function(e) {
        e.preventDefault();
        var zval = $('#target_zone').val();
         if(!zval){
             Swal.fire({ title: '<?php _e('Warning', 'royal-sentinel'); ?>', text: '<?php _e('Please select a target domain.', 'royal-sentinel'); ?>', icon: 'warning', timer: 1500, showConfirmButton: false });
             return;
        }

        var strategy = $('#cache_strategy').val();

        // Logic for "All" vs Single
        var targets = [];
        if (zval === 'all') { targets = allZones.map(z => z.id); } 
        else { targets = [zval]; }

        Swal.fire({ title: 'Deploying...', html: 'Applying Cache Strategy...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });

        deploySequence(0, targets, strategy);
    });

    function deploySequence(index, ids, strat) {
        if(index >= ids.length) {
            Swal.fire({ title: '<?php _e('Done!', 'royal-sentinel'); ?>', icon: 'success', timer: 1500, showConfirmButton: false });
            var final = $('#target_zone').val();
            renderAndLoad(final); 
            return;
        }
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cacherules', rs_action: 'deploy', zone_id: ids[index], strategy: strat },
            dataType: 'json',
            success: function(response) { 
                if(response.success === false) {
                     var err = response.message || "Unknown API Error";
                     var isDuplicate = response.duplicate || false;
                     
                     Swal.fire({ 
                         title: isDuplicate ? '<?php _e('Duplicate!', 'royal-sentinel'); ?>' : '<?php _e('Error', 'royal-sentinel'); ?>', 
                         text: err, 
                         icon: isDuplicate ? 'warning' : 'error', 
                         timer: 1500, 
                         showConfirmButton: false 
                     });
                     return;
                }
                deploySequence(index + 1, ids, strat); 
            },
            error: function(xhr) { 
                Swal.fire({ title: '<?php _e('Error', 'royal-sentinel'); ?>', text: 'Connection failed: ' + xhr.statusText, icon: 'error', timer: 1500, showConfirmButton: false });
            }
        });
    }

    // Delete
    $(document).on("click", ".rs-cache-delete-btn", function(e) {
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
                    data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cacherules', rs_action: 'delete', zone_id: btn.data("zone-id"), rule_id: btn.data("rule-id") },
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
    
    $('#cache-bulk-deploy-btn').click(function() {
        var selectedStrategy = $('#cache_strategy').val();
        var selectedStrategyText = $('#cache_strategy option:selected').text();
        
        if (!selectedStrategy) {
            Swal.fire({ title: '<?php _e('Warning', 'royal-sentinel'); ?>', text: '<?php _e('Please select a cache strategy first.', 'royal-sentinel'); ?>', icon: 'warning', timer: 1500, showConfirmButton: false });
            return;
        }
        
        Swal.fire({
            title: 'Bulk Deploy Cache Rule',
            html: '<p>Deploy <strong>' + selectedStrategyText + '</strong> to all connected domains?</p><p style="color:#666; font-size:13px;">Duplicates will be automatically skipped.</p>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Deploy to All Domains',
            confirmButtonColor: '#10b981',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                bulkDeployCacheWithProgress(selectedStrategy, selectedStrategyText);
            }
        });
    });
    
    function bulkDeployCacheWithProgress(strategy, strategyText) {
        var targets = allZones;
        var total = targets.length;
        var current = 0;
        var success = 0;
        var skipped = 0;
        
        Swal.fire({
            title: 'Deploying ' + strategyText,
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
                Swal.fire({ title: '<?php _e('Success!', 'royal-sentinel'); ?>', text: msg, icon: 'success', timer: 1500, showConfirmButton: false }).then(() => location.reload());
                return;
            }
            
            current = index + 1;
            var percent = Math.round((current / total) * 100);
            $('#progress-bar').css('width', percent + '%').text(percent + '%');
            $('#progress-text').html('Processing <strong>' + targets[index].name + '</strong> (' + current + '/' + total + ')...');
            
            var zoneId = targets[index].id;
            var ruleName = getRuleName(strategy);
            
            // Check if rule exists
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cacherules', rs_action: 'fetch', zone_id: zoneId },
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
                        // Deploy rule
                        $.ajax({
                            url: ajaxUrl,
                            type: 'POST',
                            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cacherules', rs_action: 'deploy', zone_id: zoneId, strategy: strategy },
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
        
        function getRuleName(strat) {
            var names = {
                'standard': 'Static Assets Cache (Royal Sentinel)',
                'aggressive': 'HTML Cache / Aggressive (Royal Sentinel)',
                'laravel_static': 'Laravel Static Assets (Laravel)',
                'laravel_api': 'Laravel API Cache (Laravel)',
                'laravel_pages': 'Laravel Pages Cache (Laravel)',
                'wp_rest_api': 'WordPress REST API Cache (WordPress)',
                'wp_uploads': 'WordPress Uploads Cache (WordPress)',
                'wp_pages': 'WordPress Pages Cache (WordPress)',
                'wp_feeds': 'WordPress RSS Feeds Cache (WordPress)'
            };
            return names[strat] || '';
        }
    }
    
    $('#cache-bulk-delete-btn').click(function() {
        Swal.fire({
            title: 'Bulk Delete Cache Rule',
            input: 'text',
            inputLabel: 'Enter exact Rule Name',
            inputPlaceholder: 'e.g. Static Assets Cache (Royal Sentinel)',
            showCancelButton: true,
            confirmButtonText: 'Delete Everywhere',
            confirmButtonColor: '#d63638'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                bulkDeleteCacheWithProgress(result.value);
            }
        });
    });
    
    function bulkDeleteCacheWithProgress(ruleName) {
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
                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cacherules', rs_action: 'fetch', zone_id: zoneId },
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
                                data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cacherules', rs_action: 'delete', zone_id: zoneId, rule_id: foundRule.id },
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
