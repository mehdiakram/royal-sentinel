<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ======================================================
// 1. CLOUDFLARE AUTH HEADER GENERATOR
// ======================================================
function rs_headers() {
    $mode  = get_option('rs_cf_auth_mode', 'global');
    $email = trim(get_option('rs_cf_email'));
    $key   = trim(get_option('rs_cf_api_key'));

    if (empty($key)) return false;

    // Auto-detect token type: Global Keys are 37 chars, API Tokens are 40+ chars.
    if ( strlen($key) > 37 || $mode === 'single' ) {
        return [ 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ];
    } else {
        if (empty($email)) return false;
        return [ 'X-Auth-Email' => $email, 'X-Auth-Key' => $key, 'Content-Type' => 'application/json' ];
    }
}

// ======================================================
// 2. GET ZONES & HELPERS
// ======================================================
function rs_remote_get($url) {
    $headers = rs_headers();
    if (!$headers) return ['success' => false, 'errors' => [['message' => 'No Credentials']]];

    $res = wp_remote_get($url, [ 'headers' => $headers, 'timeout' => 20, 'sslverify' => false ]);
    if (is_wp_error($res)) return $res;
    return json_decode(wp_remote_retrieve_body($res), true);
}

function rs_get_cf_zones() {
    $cached = get_transient('rs_cached_zones');
    if ($cached !== false && !empty($cached)) return ['items' => $cached, 'error' => null];

    $mode = get_option('rs_cf_auth_mode', 'global');
    $acc_id = get_option('rs_cf_acc_id');
    
    $base_url = "https://api.cloudflare.com/client/v4/zones";
    $params = [ "per_page=50" ];

    // Single Mode এ account.id ফিল্টার বাদ দেওয়া হয়েছে যাতে টোকেনের ডিফল্ট জোন আসে
    if ( $mode === 'global' && $acc_id ) $params[] = "account.id=" . $acc_id;

    $url = $base_url . "?" . implode("&", $params);
    $response = rs_remote_get($url);

    if (is_wp_error($response)) return ['items' => [], 'error' => $response->get_error_message()];

    if (isset($response['success']) && $response['success']) {
        $zones = $response['result'];
        
        // বর্তমান ডোমেইন খুঁজে না পেলে ম্যানুয়াল সার্চ
        $current_domain = strtolower(str_replace('www.', '', parse_url(get_site_url(), PHP_URL_HOST)));
        $found = false;
        foreach($zones as $z) { if ($z['name'] === $current_domain) { $found = true; break; } }

        if (!$found && count($zones) < 50) {
            $res_search = rs_remote_get($base_url . "?name=" . $current_domain);
            if (isset($res_search['success']) && !empty($res_search['result'])) {
                $exists = false;
                foreach($zones as $z) { if($z['id'] === $res_search['result'][0]['id']) $exists = true; }
                if(!$exists) array_unshift($zones, $res_search['result'][0]);
            }
        }

        set_transient('rs_cached_zones', $zones, 3600);
        if (!empty($zones) && isset($zones[0]['account']['id'])) update_option('rs_cf_acc_id', $zones[0]['account']['id']);

        return ['items' => $zones, 'error' => null];
    } else {
        return ['items' => [], 'error' => $response['errors'][0]['message'] ?? 'Unknown API Error'];
    }
}

function rs_get_primary_zone_id() {
    $zones = rs_get_cf_zones();
    if (!empty($zones['items'])) return $zones['items'][0]['id'];
    return false;
}

// ======================================================
// 3. IP MANAGEMENT (Hybrid: Zone Rule vs Account List)
// ======================================================
function rs_get_list_items() {
    // ডিবাগিং বা ফ্রেশ ডাটার জন্য আমরা এখানে ক্যাশ চেক কম সময় রাখতে পারি বা ফোর্স রিফ্রেশ হলে ক্যাশ ডিলিট হয়।
    $cached = get_transient('rs_cached_ips');
    if ($cached !== false) return ['items' => $cached, 'error' => null];

    $mode = get_option('rs_cf_auth_mode', 'global');
    
    // --- MODE A: Single (Zone Access Rules) ---
    if ($mode === 'single') {
        $zone_id = rs_get_primary_zone_id();
        if (!$zone_id) return ['items' => [], 'error' => 'No Zone Found for IP Actions'];

        $mapped = [];
        $page = 1;
        $has_more = true;

        do {
            // [FIX] mode=block ফিল্টার সরিয়ে দেওয়া হয়েছে যাতে সব রুল আসে (Block, Challenge etc)
            $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/access_rules/rules?per_page=100&page=$page";
            $res = rs_remote_get($url);

            if (!isset($res['success']) || !$res['success']) {
                $error = $res['errors'][0]['message'] ?? 'Zone API Error';
                // Return what we have so far, if any
                return ['items' => $mapped, 'error' => $error];
            }

            $current_batch = $res['result'] ?? [];
            foreach($current_batch as $r) {
                // শুধু IP টার্গেটগুলো নিচ্ছি (Country বা ASN বাদ দিচ্ছি লিস্ট ক্লিন রাখার জন্য)
                if ( isset($r['configuration']['target']) && $r['configuration']['target'] === 'ip' ) {
                    $mapped[] = [
                        'id' => $r['id'],
                        'ip' => $r['configuration']['value'],
                        'mode' => $r['mode'], // [NEW] Mode (block, challenge)
                        'comment' => $r['notes'] ?? '',
                        'created_on' => $r['created_on']
                    ];
                }
            }

            $total_pages = $res['result_info']['total_pages'] ?? 1;
            if ($page >= $total_pages) {
                $has_more = false;
            } else {
                $page++;
            }
        } while ($has_more);

        set_transient('rs_cached_ips', $mapped, 3600);
        return ['items' => $mapped, 'error' => null];
    } 
    
    // --- MODE B: Global (Account IP Lists) ---
    else {
        $acc_id  = get_option('rs_cf_acc_id');
        $list_id = get_option('rs_cf_list_id');
        
        if (!$acc_id || !$list_id) return ['items' => [], 'error' => 'Global Mode: Missing List ID'];

        $mapped = [];
        $cursor = '';

        do {
            $url = "https://api.cloudflare.com/client/v4/accounts/$acc_id/rules/lists/$list_id/items?per_page=500";
            if (!empty($cursor)) $url .= "&cursor=" . urlencode($cursor);

            $res = rs_remote_get($url);

            if (!isset($res['success']) || !$res['success']) {
                $error = $res['errors'][0]['message'] ?? 'Account API Error';
                return ['items' => $mapped, 'error' => $error];
            }

            $current_batch = $res['result'] ?? [];
            foreach($current_batch as $r) {
                $mapped[] = [
                    'id' => $r['id'],
                    'ip' => $r['ip'], // Account list এ সরাসরি IP থাকে
                    'mode' => 'account_list', 
                    'comment' => $r['comment'] ?? '',
                    'created_on' => $r['created_on']
                ];
            }

            $cursor = $res['result_info']['cursors']['after'] ?? '';
        } while (!empty($cursor));

        set_transient('rs_cached_ips', $mapped, 3600);
        return ['items' => $mapped, 'error' => null];
    }
}

function rs_api_add_ip($ip, $comment, $cf_mode = 'block') {
    $auth_mode = get_option('rs_cf_auth_mode', 'global');

    // --- MODE A: Single (Zone Rule) ---
    if ($auth_mode === 'single') {
        $zone_id = rs_get_primary_zone_id();
        if (!$zone_id) return ['success' => false, 'message' => 'No Zone Found'];

        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/access_rules/rules";
        $body = json_encode([
            'mode' => $cf_mode, // block, whitelist, challenge, js_challenge
            'configuration' => ['target' => 'ip', 'value' => $ip],
            'notes' => $comment
        ]);
        
        $response = wp_remote_post($url, ['headers' => rs_headers(), 'body' => $body, 'method' => 'POST', 'sslverify' => false]);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if($data['success']) return ['success' => true];
        return ['success' => false, 'message' => $data['errors'][0]['message'] ?? 'Error'];
    }

    // --- MODE B: Global (Account List) ---
    else {
        // whitelist mode-এ Global mode-এ Zone-Level access rule ব্যবহার করতে হবে
        if ( $cf_mode === 'whitelist' ) {
            $zone_id = rs_get_primary_zone_id();
            if (!$zone_id) return ['success' => false, 'message' => 'No Zone Found for whitelist'];

            $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/access_rules/rules";
            $body = json_encode([
                'mode' => 'whitelist',
                'configuration' => ['target' => 'ip', 'value' => $ip],
                'notes' => $comment
            ]);
            
            $response = wp_remote_post($url, ['headers' => rs_headers(), 'body' => $body, 'method' => 'POST', 'sslverify' => false]);
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if($data['success']) return ['success' => true];
            return ['success' => false, 'message' => $data['errors'][0]['message'] ?? 'Error'];
        }

        $acc_id = get_option('rs_cf_acc_id');
        $list_id = get_option('rs_cf_list_id');
        if (!$acc_id || !$list_id) return ['success' => false, 'message' => 'Setup IP List ID'];

        $url = "https://api.cloudflare.com/client/v4/accounts/$acc_id/rules/lists/$list_id/items";
        $body = json_encode([['ip' => $ip, 'comment' => $comment]]);
        
        $response = wp_remote_post($url, ['headers' => rs_headers(), 'body' => $body, 'method' => 'POST', 'sslverify' => false]);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if($data['success']) return ['success' => true];
        return ['success' => false, 'message' => $data['errors'][0]['message'] ?? 'Error'];
    }
}

/**
 * Whitelist an IP in Cloudflare (Zone-Level Access Rule, mode=whitelist)
 * Both Single & Global mode use Zone-Level firewall/access_rules for whitelisting.
 */
function rs_api_whitelist_ip( $ip, $comment = '' ) {
    $comment = empty( $comment ) ? 'Whitelisted via Royal Sentinel' : $comment;

    $zone_id = rs_get_primary_zone_id();
    if ( ! $zone_id ) {
        return [ 'success' => false, 'message' => 'No Cloudflare Zone found. Please check settings.' ];
    }

    $url  = "https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/access_rules/rules";
    $body = json_encode([
        'mode'          => 'whitelist',
        'configuration' => [ 'target' => 'ip', 'value' => $ip ],
        'notes'         => $comment,
    ]);

    $response = wp_remote_post( $url, [
        'headers'   => rs_headers(),
        'body'      => $body,
        'method'    => 'POST',
        'sslverify' => false,
        'timeout'   => 20,
    ]);

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'message' => $response->get_error_message() ];
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $data['success'] ) && $data['success'] ) {
        return [ 'success' => true, 'rule_id' => $data['result']['id'] ?? '' ];
    }

    $err = isset( $data['errors'][0]['message'] ) ? $data['errors'][0]['message'] : 'Cloudflare API Error';
    return [ 'success' => false, 'message' => $err ];
}

// ======================================================
// AJAX: IP Whitelist via Cloudflare (Quick Whitelist)
// ======================================================
add_action( 'wp_ajax_rs_cf_quick_whitelist', 'rs_ajax_cf_quick_whitelist' );
function rs_ajax_cf_quick_whitelist() {
    check_ajax_referer( 'rs_cf_whitelist_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
    }

    $ip      = isset( $_POST['ip'] )      ? sanitize_text_field( $_POST['ip'] )      : '';
    $comment = isset( $_POST['comment'] ) ? sanitize_text_field( $_POST['comment'] ) : '';

    if ( empty( $ip ) ) {
        wp_send_json_error( 'IP address is required.' );
    }

    // Validate IP / CIDR
    if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        // Try CIDR
        $parts = explode( '/', $ip, 2 );
        if ( count( $parts ) !== 2 || ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
            wp_send_json_error( 'Invalid IP address format.' );
        }
    }

    $result = rs_api_whitelist_ip( $ip, $comment );

    if ( $result['success'] ) {
        delete_transient( 'rs_cached_ips' );
        wp_send_json_success( [
            'message' => sprintf( 'IP %s successfully whitelisted in Cloudflare.', $ip ),
            'ip'      => $ip,
        ] );
    } else {
        wp_send_json_error( $result['message'] );
    }
}

function rs_api_delete_item($item_id) {
    $mode = get_option('rs_cf_auth_mode', 'global');
    
    if ($mode === 'single') {
        $zone_id = rs_get_primary_zone_id();
        if (!$zone_id) return;
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/firewall/access_rules/rules/$item_id";
        wp_remote_request($url, ['headers' => rs_headers(), 'method' => 'DELETE', 'sslverify' => false]);
    } else {
        $acc_id = get_option('rs_cf_acc_id');
        $list_id = get_option('rs_cf_list_id');
        $url = "https://api.cloudflare.com/client/v4/accounts/$acc_id/rules/lists/$list_id/items";
        $body = json_encode(['items' => [['id' => $item_id]]]);
        wp_remote_request($url, ['headers' => rs_headers(), 'body' => $body, 'method' => 'DELETE', 'sslverify' => false]);
    }
}

// ======================================================
// BATCH ADD IPs (Used by cf-sync.php and cf-ie-handler.php)
// ======================================================
function rs_api_batch_add_ips( $items ) {
    $mode = get_option('rs_cf_auth_mode', 'global');
    
    // --- MODE A: Single (Zone Rules) - Add one by one ---
    if ( $mode === 'single' ) {
        $added = 0;
        $last_error = '';
        foreach ( $items as $item ) {
            $result = rs_api_add_ip( $item['ip'], $item['comment'] );
            if ( $result['success'] ) {
                $added++;
            } else {
                $last_error = $result['message'] ?? 'Unknown error';
            }
        }
        if ( $added > 0 ) {
            return ['success' => true, 'message' => "Added $added IPs"];
        }
        return ['success' => false, 'message' => $last_error ?: 'No IPs added'];
    }
    
    // --- MODE B: Global (Account List) - Batch add ---
    $acc_id  = get_option('rs_cf_acc_id');
    $list_id = get_option('rs_cf_list_id');
    if ( !$acc_id || !$list_id ) {
        return ['success' => false, 'message' => 'Setup IP List ID first'];
    }
    
    $url = "https://api.cloudflare.com/client/v4/accounts/$acc_id/rules/lists/$list_id/items";
    $headers = rs_headers();
    if ( !$headers ) {
        return ['success' => false, 'message' => 'Credentials Missing'];
    }
    
    $body = json_encode( $items );
    $response = wp_remote_post( $url, [
        'headers' => $headers,
        'body'    => $body,
        'method'  => 'POST',
        'timeout' => 30,
    ]);
    
    if ( is_wp_error( $response ) ) {
        return ['success' => false, 'message' => $response->get_error_message()];
    }
    
    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( isset($data['success']) && $data['success'] ) {
        return ['success' => true, 'message' => 'Batch added successfully'];
    }
    return ['success' => false, 'message' => $data['errors'][0]['message'] ?? 'Batch API Error'];
}
// 4. TEST CONNECTION HANDLER
// ======================================================
add_action('wp_ajax_rs_test_cf_connection', 'rs_test_cf_connection_handler');
function rs_test_cf_connection_handler() {
    check_ajax_referer('rs_test_conn_nonce', 'security');

    if (isset($_POST['auth_mode'])) {
        add_filter('pre_option_rs_cf_auth_mode', function() { return sanitize_text_field($_POST['auth_mode']); });
    }
    if (isset($_POST['email'])) {
        add_filter('pre_option_rs_cf_email', function() { return sanitize_text_field($_POST['email']); });
    }
    if (isset($_POST['api_key'])) {
        add_filter('pre_option_rs_cf_api_key', function() { return sanitize_text_field($_POST['api_key']); });
    }
    if (isset($_POST['acc_id'])) {
        add_filter('pre_option_rs_cf_acc_id', function() { return sanitize_text_field($_POST['acc_id']); });
    }

    $result = rs_get_cf_zones();
    if ( empty($result['error']) ) {
        $count = count($result['items']);
        if($count > 0) {
            $names = implode(", ", array_column($result['items'], 'name'));
            wp_send_json_success("Connected! Found: " . $names);
        } else {
            wp_send_json_error("Connected but No Domains found.");
        }
    } else {
        wp_send_json_error("Connection Failed: " . $result['error']);
    }
}

// ======================================================
// 5. GENERIC CURL HELPER (Moved from rules.php)
// ======================================================

// A. CURL Helper
// A. API Request Helper (Standardized to wp_remote_request)
function rs_execute_cf_curl( $url, $data, $method, $legacy_email = '', $legacy_key = '' ) {
    $headers = rs_headers();
    if ( ! $headers ) {
        return [ 'success' => false, 'message' => 'Credentials Missing' ];
    }

    $args = [
        'method'      => $method,
        'headers'     => $headers,
        'timeout'     => 30,
        'sslverify'   => false,
        'blocking'    => true,
    ];

    if ( ! empty( $data ) && in_array( strtoupper($method), [ 'POST', 'PATCH', 'PUT' ] ) ) {
        $args['body'] = json_encode( $data );
    }

    $response = wp_remote_request( $url, $args );

    if ( is_wp_error( $response ) ) {
        return [ 'success' => false, 'message' => $response->get_error_message() ];
    }

    $body     = wp_remote_retrieve_body( $response );
    $code     = wp_remote_retrieve_response_code( $response );
    $data_res = json_decode( $body, true );

    // Ensure we have a valid array to avoid PHP warnings
    if ( ! is_array( $data_res ) ) {
        return [ 'success' => false, 'message' => 'Invalid API Response or Connection Timeout (' . $code . ')' ];
    }

    if ( isset( $data_res['success'] ) && $data_res['success'] ) {
        return [ 'success' => true, 'data' => $data_res['result'] ?? [] ];
    } else {
        // Handle successful DELETE with 200 OK but no body success
        if ( strtoupper($method) === 'DELETE' && $code === 200 ) {
            return [ 'success' => true, 'data' => [] ];
        }
        
        $err_msg = isset($data_res['errors'][0]['message']) ? $data_res['errors'][0]['message'] : 'API Error (' . $code . ')';
        return [ 'success' => false, 'message' => $err_msg ];
    }
}


function rs_get_waf_ruleset_id($z_id) {
    $url = "https://api.cloudflare.com/client/v4/zones/$z_id/rulesets/phases/http_request_firewall_custom/entrypoint";
    $res = rs_execute_cf_curl($url, [], 'GET', '', '');
    if ( !empty($res['success']) && !empty($res['data']['id']) ) {
        return ['success' => true, 'id' => $res['data']['id']];
    }
    // Try to create it by updating the entrypoint directly (requires only Firewall Services perm)
    $payload = ['rules' => []];
    $create_res = rs_execute_cf_curl($url, $payload, 'PUT', '', '');
    if ( !empty($create_res['success']) && !empty($create_res['data']['id']) ) {
        return ['success' => true, 'id' => $create_res['data']['id']];
    }
    $err = isset($create_res['message']) ? $create_res['message'] : (isset($res['message']) ? $res['message'] : 'Ruleset access failed.');
    return ['success' => false, 'message' => $err];
}

// B. Delete Rule (Rulesets API)
function rs_delete_cf_rule_and_filter( $zone_id, $rule_id, $filter_id = null ) {
    $rs = rs_get_waf_ruleset_id($zone_id);
    if (empty($rs['success'])) return $rs;
    $ruleset_id = $rs['id'];
    
    $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets/{$ruleset_id}/rules/{$rule_id}";
    return rs_execute_cf_curl( $url, [], 'DELETE', '', '' );
}

// C. Update Rule Status (Active/Inactive)
function rs_update_cf_rule_status( $zone_id, $rule_id, $paused ) {
    $rs = rs_get_waf_ruleset_id($zone_id);
    if (empty($rs['success'])) return $rs;
    $ruleset_id = $rs['id'];
    
    $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets/{$ruleset_id}/rules/{$rule_id}";
    $data = [ 'action' => 'block', 'enabled' => !$paused ]; // Simplification for demo
    return rs_execute_cf_curl( $url, $data, 'PATCH', '', '' );
}

// D. Get Active Rules
function rs_get_cf_active_rules( $zone_id ) {
    $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets/phases/http_request_firewall_custom/entrypoint";
    $res = rs_execute_cf_curl( $url, [], 'GET', '', '' );
    
    // Map Rulesets response to old format for frontend compatibility
    if (!empty($res['success']) && isset($res['data']['rules'])) {
        $mapped = [];
        foreach($res['data']['rules'] as $r) {
            $mapped[] = [
                'id' => $r['id'],
                'description' => $r['description'] ?? '',
                'action' => $r['action'] ?? '',
                'paused' => !($r['enabled'] ?? true),
                'filter' => [
                    'id' => 'rulesets_no_filter',
                    'expression' => $r['expression'] ?? ''
                ]
            ];
        }
        $res['data'] = $mapped;
    } elseif (!empty($res['success'])) {
        $res['data'] = []; // empty ruleset
    }
    
    return $res;
}

// Alias for backward compatibility with older views and AJAX handlers
function rs_get_cf_rules_via_api( $zone_id ) {
    return rs_get_cf_active_rules( $zone_id );
}

// E. Add WAF Rule (Custom Rule using Rulesets API)
function rs_add_cf_rule_via_api( $zone_id, $rule_name, $expression, $action ) {
    $rs = rs_get_waf_ruleset_id($zone_id);
    if (empty($rs['success'])) return $rs;
    $ruleset_id = $rs['id'];
    
    $url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets/{$ruleset_id}/rules";
    $data = [
        'action' => $action,
        'expression' => $expression,
        'description' => $rule_name,
        'enabled' => true
    ];
    return rs_execute_cf_curl( $url, $data, 'POST', '', '' );
}

// F. Get List Name by ID
function rs_get_list_name_by_id( $account_id, $list_id ) {
    $api_email = get_option( 'rs_cf_email' ); $api_key = get_option( 'rs_cf_api_key' );
    $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/rules/lists/{$list_id}";
    $resp = rs_execute_cf_curl( $url, [], 'GET', $api_email, $api_key );
    
    if ( $resp['success'] && !empty($resp['data']['name']) ) {
        return $resp['data']['name']; 
    }
    return false;
}

// ======================================================
// 7. RATE LIMITING AJAX HANDLERS
// ======================================================

// Helper function for rate limit ruleset ID (used in Global mode)
function rs_get_ratelimit_ruleset_id($z_id) {
    $url = "https://api.cloudflare.com/client/v4/zones/$z_id/rulesets/phases/http_ratelimit/entrypoint";
    $res = rs_execute_cf_curl($url, [], 'GET', '', '');
    
    if ( !empty($res['success']) && !empty($res['data']['id']) ) {
        return ['success' => true, 'id' => $res['data']['id']];
    }
    
    // Try to create it by updating the entrypoint directly
    $payload = ['rules' => []];
    $create_res = rs_execute_cf_curl($url, $payload, 'PUT', '', '');
    
    if ( !empty($create_res['success']) && !empty($create_res['data']['id']) ) {
        return ['success' => true, 'id' => $create_res['data']['id']];
    }

    $err = isset($create_res['message']) ? $create_res['message'] : (isset($res['message']) ? $res['message'] : 'Ruleset access failed.');
    return ['success' => false, 'message' => $err];
}

// Rate Limiting AJAX Handler - Mode Aware (Hybrid: Legacy for Single, Rulesets for Global)
add_action('wp_ajax_rs_ratelimit', 'rs_handle_ratelimit_ajax');
function rs_handle_ratelimit_ajax() {
    check_ajax_referer( 'rs_cf_tabs', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    
    $action = isset($_POST['rs_action']) ? sanitize_text_field($_POST['rs_action']) : '';
    $zone_id = isset($_POST['zone_id']) ? sanitize_text_field($_POST['zone_id']) : '';
    $mode = get_option('rs_cf_auth_mode', 'global');
    
    // ========================================
    // Use Rulesets API for both modes
    // Full access to new Rate Limiting rules
    // ========================================
    if ($action === 'fetch') {
          $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/phases/http_ratelimit/entrypoint";
          $res = rs_execute_cf_curl($url, [], 'GET', '', '');
        
        if (!empty($res['success']) && isset($res['data']['rules'])) {
            wp_send_json(['success' => true, 'data' => $res['data']['rules'], 'api' => 'rulesets']);
        } elseif (empty($res['success'])) {
            wp_send_json(['success' => false, 'message' => isset($res['message']) ? $res['message'] : 'API Error']);
        } else {
            wp_send_json(['success' => true, 'data' => [], 'api' => 'rulesets']);
        }
    }
    
    if ($action === 'deploy') {
        $rs_result = rs_get_ratelimit_ruleset_id($zone_id);
        if (!$rs_result['success']) {
            wp_send_json(['success' => false, 'message' => $rs_result['message']]);
        }
        $ruleset_id = $rs_result['id'];
        
        // Check for existing rule - if exists, delete it first to update with latest expression
        $rule_name = "Smart Rate Limiting (Free plan friendly)";
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id";
        $existing = rs_execute_cf_curl($url, [], 'GET', '', '');
        if (!empty($existing['success']) && isset($existing['data']['rules'])) {
            foreach ($existing['data']['rules'] as $r) {
                if (isset($r['description']) && $r['description'] === $rule_name) {
                    $del_url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id/rules/" . $r['id'];
                    rs_execute_cf_curl($del_url, [], 'DELETE', '', '');
                    break;
                }
            }
        }
        
        $rule_payload = [
            'description' => $rule_name,
            'expression'  => '((http.request.uri.path eq "/xmlrpc.php") or (http.request.uri.path contains "/wp-login.php") or (http.request.uri.path contains "/wp-admin" and not http.request.uri.path contains "/admin-ajax.php")) and (not cf.client.bot) and (not http.cookie contains "wordpress_logged_in_")',
            'action'      => 'block',
            'ratelimit'   => ['characteristics' => ['ip.src', 'cf.colo.id'], 'period' => 10, 'requests_per_period' => 20, 'mitigation_timeout' => 10],
            'enabled'     => true
        ];
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id/rules";
        $res = rs_execute_cf_curl($url, $rule_payload, 'POST', '', '');
        wp_send_json($res);
    }
    
    if ($action === 'delete') {
        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field($_POST['rule_id']) : '';
        $rs_result = rs_get_ratelimit_ruleset_id($zone_id);
        if (!$rs_result['success']) {
            wp_send_json(['success' => false, 'message' => $rs_result['message']]);
        }
        $ruleset_id = $rs_result['id'];
        
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id/rules/$rule_id";
        $res = rs_execute_cf_curl($url, [], 'DELETE', '', '');
        wp_send_json($res);
    }
    
    wp_send_json(['success' => false, 'message' => 'Invalid action']);
}

// Cache Rules AJAX Handler - Mode Aware
add_action('wp_ajax_rs_cacherules', 'rs_handle_cacherules_ajax');
function rs_handle_cacherules_ajax() {
    check_ajax_referer( 'rs_cf_tabs', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    
    $action = isset($_POST['rs_action']) ? sanitize_text_field($_POST['rs_action']) : '';
    $zone_id = isset($_POST['zone_id']) ? sanitize_text_field($_POST['zone_id']) : '';
    $mode = get_option('rs_cf_auth_mode', 'global');
    
    // SINGLE SITE MODE - No legacy API available for Cache Rules
    if ($mode === 'single') {
        wp_send_json([
            'success' => false, 
            'message' => 'Cache Rules requires Global API Key mode. Single Site API tokens do not have the necessary Account-level permissions for Rulesets API.',
            'mode_error' => true
        ]);
    }
    
    // GLOBAL MODE - Use Rulesets API
    // Helper for cache ruleset ID
    $rs_get_cache_id = function($z_id) {
        $url = "https://api.cloudflare.com/client/v4/zones/$z_id/rulesets/phases/http_request_cache_settings/entrypoint";
        $res = rs_execute_cf_curl($url, [], 'GET', '', '');
        
        if ( !empty($res['success']) && !empty($res['data']['id']) ) {
            return ['success' => true, 'id' => $res['data']['id']];
        }
        
        // Try to create it by updating the entrypoint directly
        $payload = ['rules' => []];
        $create_res = rs_execute_cf_curl($url, $payload, 'PUT', '', '');
        
        if ( !empty($create_res['success']) && !empty($create_res['data']['id']) ) {
            return ['success' => true, 'id' => $create_res['data']['id']];
        }

        $err = isset($create_res['message']) ? $create_res['message'] : (isset($res['message']) ? $res['message'] : 'Cache ruleset access failed.');
        return ['success' => false, 'message' => $err];
    };
    
    if ($action === 'fetch') {
        $rs_result = $rs_get_cache_id($zone_id);
        if (!$rs_result['success']) {
            wp_send_json(['success' => false, 'message' => $rs_result['message']]);
        }
        $ruleset_id = $rs_result['id'];
        
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id";
        $res = rs_execute_cf_curl($url, [], 'GET', '', '');
        
        if (!empty($res['success']) && isset($res['data']['rules'])) {
            wp_send_json(['success' => true, 'data' => $res['data']['rules']]);
        } elseif (empty($res['success'])) {
            wp_send_json(['success' => false, 'message' => isset($res['message']) ? $res['message'] : 'API Error']);
        } else {
            wp_send_json(['success' => true, 'data' => []]);
        }
    }
    
    if ($action === 'deploy') {
        $strategy = isset($_POST['strategy']) ? sanitize_text_field($_POST['strategy']) : 'standard';
        
        $rs_result = $rs_get_cache_id($zone_id);
        if (!$rs_result['success']) {
            wp_send_json(['success' => false, 'message' => $rs_result['message']]);
        }
        $ruleset_id = $rs_result['id'];
        
        // Determine rule name based on strategy
        $rule_name = ($strategy === 'aggressive') ? "HTML Cache / Aggressive (Royal Sentinel)" : "Static Assets Cache (Royal Sentinel)";
        
        // Check for duplicate rule
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id";
        $existing = rs_execute_cf_curl($url, [], 'GET', '', '');
        if (!empty($existing['success']) && isset($existing['data']['rules'])) {
            foreach ($existing['data']['rules'] as $r) {
                if (isset($r['description']) && $r['description'] === $rule_name) {
                    wp_send_json(['success' => false, 'message' => __('Rule already exists! Duplicate not allowed.', 'royal-sentinel'), 'duplicate' => true]);
                }
            }
        }
        
        $rule_payload = [];
        if ($strategy === 'standard') {
            $rule_payload = [
                'description' => $rule_name,
                'expression'  => 'http.request.uri.path.extension in {"jpg" "jpeg" "png" "gif" "webp" "svg" "css" "js" "woff" "woff2" "ttf" "eot" "ico" "mp4" "pdf"}',
                'action'      => 'set_cache_settings',
                'action_parameters' => ['cache' => true, 'edge_ttl' => ['mode' => 'override_origin', 'default' => 2592000]],
                'enabled' => true
            ];
        } elseif ($strategy === 'aggressive') {
            $rule_payload = [
                'description' => $rule_name,
                'expression'  => '(http.request.method eq "GET") and (not http.request.uri.path contains "/wp-admin") and (not http.request.uri.path contains "wp-login.php") and (not http.request.uri.path contains "cart") and (not http.request.uri.path contains "checkout") and (not http.request.uri.path contains "my-account")',
                'action'      => 'set_cache_settings',
                'action_parameters' => ['cache' => true, 'edge_ttl' => ['mode' => 'override_origin', 'default' => 7200]],
                'enabled' => true
            ];
        }
        
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id/rules";
        $res = rs_execute_cf_curl($url, $rule_payload, 'POST', '', '');
        wp_send_json($res);
    }
    
    if ($action === 'delete') {
        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field($_POST['rule_id']) : '';
        $rs_result = $rs_get_cache_id($zone_id);
        if (!$rs_result['success']) {
            wp_send_json(['success' => false, 'message' => $rs_result['message']]);
        }
        $ruleset_id = $rs_result['id'];
        
        $url = "https://api.cloudflare.com/client/v4/zones/$zone_id/rulesets/$ruleset_id/rules/$rule_id";
        $res = rs_execute_cf_curl($url, [], 'DELETE', '', '');
        wp_send_json($res);
    }
    
    wp_send_json(['success' => false, 'message' => 'Invalid action']);
}
// ======================================================
// 8. GEO-BLOCKING AJAX HANDLER (Custom WAF Rules)
// ======================================================
add_action('wp_ajax_rs_cf_geoblocking', 'rs_handle_geoblocking_ajax');
function rs_handle_geoblocking_ajax() {
    check_ajax_referer( 'rs_cf_tabs', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    
    $action = isset($_POST['rs_action']) ? sanitize_text_field($_POST['rs_action']) : '';
    $zone_id = isset($_POST['zone_id']) ? sanitize_text_field($_POST['zone_id']) : '';
    
    // Fixed Rule Name
    $GEO_RULE_NAME = 'Geo-Blocking (Royal Sentinel)';
    
    // Fetch Active Rules
    if ($action === 'fetch') {
        $res = rs_get_cf_rules_via_api($zone_id);
        
        if (!empty($res['success'])) {
            $rules = [];
            if(!empty($res['data'])) {
                foreach($res['data'] as $r) {
                    $rules[] = [
                        'id' => $r['id'],
                        'description' => $r['description'] ?? '',
                        'action' => $r['action'],
                        'expression' => $r['filter']['expression'] ?? '',
                        'filter_id' => $r['filter']['id'] ?? '',
                        'enabled' => !($r['paused'] ?? false)
                    ];
                }
            }
            wp_send_json(['success' => true, 'data' => $rules]);
        } else {
            wp_send_json(['success' => false, 'message' => $res['message'] ?? __('API Error', 'royal-sentinel')]);
        }
    }
    
    // Deploy / Update Rule
    if ($action === 'deploy') {
        $countries = isset($_POST['countries']) ? (array)$_POST['countries'] : [];
        $rule_action = isset($_POST['rule_action']) ? sanitize_text_field($_POST['rule_action']) : 'block';
        $existing_rule_id = isset($_POST['existing_rule_id']) ? sanitize_text_field($_POST['existing_rule_id']) : '';
        $existing_filter_id = isset($_POST['existing_filter_id']) ? sanitize_text_field($_POST['existing_filter_id']) : '';
        
        if(empty($countries)) {
            wp_send_json(['success' => false, 'message' => __('No countries selected', 'royal-sentinel')]);
        }
        
        // Build Expression: (ip.geoip.country in {"US" "CN"})
        $quoted_countries = array_map(function($c) { return '"' . sanitize_text_field($c) . '"'; }, $countries);
        $list_str = implode(" ", $quoted_countries);
        $expression = '(ip.geoip.country in {' . $list_str . '})';
        
        // If existing rule found, delete it first (Update = Delete + Create)
        if (!empty($existing_rule_id)) {
            // Find filter ID if not provided
            if (empty($existing_filter_id)) {
                $all_rules = rs_get_cf_rules_via_api($zone_id);
                if (!empty($all_rules['success']) && !empty($all_rules['data'])) {
                    foreach ($all_rules['data'] as $r) {
                        if ($r['id'] === $existing_rule_id) {
                            $existing_filter_id = $r['filter']['id'] ?? '';
                            break;
                        }
                    }
                }
            }
            
            // Delete existing rule
            rs_delete_cf_rule_and_filter($zone_id, $existing_rule_id, $existing_filter_id);
        } else {
            // Check if rule already exists (maybe user loaded page fresh)
            $all_rules = rs_get_cf_rules_via_api($zone_id);
            if (!empty($all_rules['success']) && !empty($all_rules['data'])) {
                foreach ($all_rules['data'] as $r) {
                    if (isset($r['description']) && $r['description'] === $GEO_RULE_NAME) {
                        $fid = $r['filter']['id'] ?? '';
                        rs_delete_cf_rule_and_filter($zone_id, $r['id'], $fid);
                        break;
                    }
                }
            }
        }
        
        // Create new rule with fixed name
        $res = rs_add_cf_rule_via_api($zone_id, $GEO_RULE_NAME, $expression, $rule_action);
        wp_send_json($res);
    }
    
    // Delete Rule
    if ($action === 'delete') {
        $rule_id = isset($_POST['rule_id']) ? sanitize_text_field($_POST['rule_id']) : '';
        
        // Find the filter ID
        $all_rules = rs_get_cf_rules_via_api($zone_id);
        $filter_id = '';
        
        if(!empty($all_rules['success']) && !empty($all_rules['data'])) {
            foreach($all_rules['data'] as $r) {
                if($r['id'] === $rule_id) {
                    $filter_id = $r['filter']['id'] ?? '';
                    break;
                }
            }
        }
        
        $res = rs_delete_cf_rule_and_filter($zone_id, $rule_id, $filter_id);
        wp_send_json($res);
    }
    
    wp_send_json(['success' => false, 'message' => __('Invalid action', 'royal-sentinel')]);
}

// ======================================================
// 9. WAF RULES AJAX HANDLER (Custom Firewall Rules)
// ======================================================
add_action('wp_ajax_rs_cf_waf_rules', 'rs_handle_waf_rules_ajax');
function rs_handle_waf_rules_ajax() {
    check_ajax_referer( 'rs_cf_tabs', 'nonce' );
    if ( ! current_user_can('manage_options') ) wp_send_json_error('Unauthorized');
    
    $action = isset($_POST['rs_action']) ? sanitize_text_field($_POST['rs_action']) : '';
    $zone_id = isset($_POST['zone_id']) ? sanitize_text_field($_POST['zone_id']) : '';
    
    // Fetch Rules
    if ($action === 'fetch') {
        $response = rs_get_cf_rules_via_api($zone_id);
        wp_send_json($response);
    }
    
    // Delete Rule
    if ($action === 'delete') {
        $rule_id = sanitize_text_field($_POST['rule_id']);
        $filter_id = isset($_POST['filter_id']) ? sanitize_text_field($_POST['filter_id']) : '';
        $response = rs_delete_cf_rule_and_filter($zone_id, $rule_id, $filter_id);
        wp_send_json($response);
    }
    
    // Toggle Rule (Active/Inactive)
    if ($action === 'toggle') {
        $rule_id = sanitize_text_field($_POST['rule_id']);
        $state = sanitize_text_field($_POST['state']);
        $is_paused = ($state === 'off');
        $response = rs_update_cf_rule_status($zone_id, $rule_id, $is_paused);
        wp_send_json($response);
    }
    
    // Deploy Single Rule
    if ($action === 'deploy') {
        $rule_type = sanitize_text_field($_POST['rule_type']);
        $rule_name = ""; $expression = ""; $rule_action = "block";
        
        switch ($rule_type) {
            case 'mega_block':
                $rule_name = "Mega Block (Royal Sentinel)";
                $base_expression = '(http.request.version eq "HTTP/1.0") or (http.request.uri.path contains "/xmlrpc.php") or (http.user_agent contains "Mozlila") or (http.user_agent contains "libwww-perl") or (http.user_agent contains "wget") or (http.request.uri.path contains ".env") or (http.request.uri.path contains ".git")';
                $cf_list_id = get_option('rs_cf_list_id');
                $cf_account_id = get_option('rs_cf_acc_id');
                $list_var_name = '';
                if (get_option('rs_cf_auth_mode') !== 'single' && !empty($cf_list_id) && !empty($cf_account_id)) {
                    $list_var_name = rs_get_list_name_by_id($cf_account_id, $cf_list_id);
                }
                if (!empty($list_var_name)) {
                    $expression = '(ip.src in $' . $list_var_name . ') or ' . $base_expression;
                } else {
                    $expression = $base_expression;
                }
                $rule_action = "block";
                break;
            case 'secure_login':
                $rule_name = "Secure Login (Royal Sentinel)";
                $expression = '((http.request.uri.path contains "/wp-login.php") or (http.request.uri.path contains "/wp-admin") or (http.request.uri.path contains "/admin") or (http.request.uri.path contains "/login")) and (not http.request.uri.path contains "/admin-ajax.php") and (not http.request.uri.path contains "/wp-admin/css/") and (not http.request.uri.path contains "/wp-admin/js/") and (not http.request.uri.path contains "/wp-admin/load-scripts.php") and (not http.request.uri.path contains "/wp-admin/load-styles.php") and (not http.request.uri.query contains "wc-ajax") and (not http.request.uri.query contains "action=elementor") and (not http.request.uri.query contains "action=heartbeat") and (not http.request.uri.query contains "action=wp-remove-post-lock") and (not http.request.uri.path contains "/wp-admin/async-upload.php")';
                $rule_action = "managed_challenge";
                break;
            case 'bad_ips':
                $rule_name = "Challenge Bad IPs (Royal Sentinel)";
                $expression = '(cf.threat_score gt 20)';
                $rule_action = "managed_challenge";
                break;
            case 'sqli_xss':
                $rule_name = "SQLi/XSS Protection (Royal Sentinel)";
                $expression = '((http.request.uri.query contains "select") or (http.request.uri.query contains "union") or (http.request.uri.query contains "exec") or (http.request.uri.query contains "<script")) and (not http.request.uri.query contains "wc-ajax")';
                $rule_action = "block";
                break;
            case 'fake_bots':
                $rule_name = "Block Fake Bots (Royal Sentinel)";
                $expression = '((http.user_agent contains "Googlebot") or (http.user_agent contains "Bingbot")) and (not cf.client.bot)';
                $rule_action = "block";
                break;
            case 'ai_bad_crawlers':
                $rule_name = "Block AI & Bad Crawlers (Royal Sentinel)";
                $expression = '(http.user_agent contains "Perplexity") or (http.user_agent contains "DotBot") or (http.user_agent contains "AhrefsBot") or (http.user_agent contains "Bytespider") or (http.user_agent contains "libredtail") or (http.user_agent contains "SemrushBot") or (http.user_agent contains "MJ12bot") or (http.user_agent eq "-") or (http.request.uri.path contains "/.aws") or (http.request.uri.path contains "/.ssh") or (http.request.uri.path contains "/terraform.tfstate") or (http.request.uri.path contains "/rclone.conf") or (http.request.uri.path contains "/.npmrc") or (http.request.uri.path contains "/.htpasswd") or (http.request.uri.path contains "/config.json") or (http.request.uri.path contains "/.env") or (http.request.uri.path contains "/v1/graphql") or (http.request.uri.path contains "/api/graphql")';
                $rule_action = "block";
                break;
        }
        
        if ($expression) {
            // Check if exists
            $existing_resp = rs_get_cf_rules_via_api($zone_id);
            $existing_rules = $existing_resp['success'] ? $existing_resp['data'] : [];
            $exists = false;
            foreach ($existing_rules as $ex) {
                if (isset($ex['description']) && $ex['description'] === $rule_name) {
                    $exists = true;
                    break;
                }
            }
            
            if ($exists) {
                wp_send_json(['success' => false, 'message' => __('Rule already exists', 'royal-sentinel')]);
            } else {
                $resp = rs_add_cf_rule_via_api($zone_id, $rule_name, $expression, $rule_action);
                wp_send_json($resp);
            }
        } else {
            wp_send_json(['success' => false, 'message' => __('Invalid rule type', 'royal-sentinel')]);
        }
    }
    
    wp_send_json(['success' => false, 'message' => __('Invalid action', 'royal-sentinel')]);
}
?>