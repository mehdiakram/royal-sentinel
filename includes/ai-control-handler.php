<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ======================================================
 * AI CRAWL CONTROL HANDLER
 * ======================================================
 */

// ------------------------------------------------------
// 1. AJAX: FETCH STATUS
// ------------------------------------------------------
add_action('wp_ajax_rs_ai_control_fetch', 'rs_ai_control_fetch_handler');
function rs_ai_control_fetch_handler() {
    // Security Check
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    
    $zone_id = isset($_POST['zone_id']) ? sanitize_text_field($_POST['zone_id']) : '';
    if(!$zone_id) wp_send_json_error('Missing Zone ID');

    $rule = rs_get_ai_rule_details($zone_id);
    
    // Parse expression to find blocked bots
    $blocked_bots = [];
    if($rule && isset($rule['expression'])) {
        $exp = $rule['expression'];
        // Simple parsing logic: check which UA strings are present
        // This list must match the frontend list
        $all_bots = rs_get_supported_ai_bots();
        foreach($all_bots as $key => $bot) {
            $ua = $bot['ua'];
            if(strpos($exp, $ua) !== false) {
                $blocked_bots[] = $key;
            }
        }
    }

    wp_send_json_success([
        'rule_id' => $rule['id'] ?? null,
        'blocked_bots' => $blocked_bots,
        'enabled' => $rule['enabled'] ?? false,
        'all_bots' => rs_get_supported_ai_bots() // Include metadata for UI
    ]);
}

// ------------------------------------------------------
// 2. AJAX: SAVE RULES
// ------------------------------------------------------
add_action('wp_ajax_rs_ai_control_save', 'rs_ai_control_save_handler');
function rs_ai_control_save_handler() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');

    $zone_id = isset($_POST['zone_id']) ? sanitize_text_field($_POST['zone_id']) : '';
    $blocked_bots = isset($_POST['blocked_bots']) ? (array)$_POST['blocked_bots'] : [];
    
    if(!$zone_id) wp_send_json_error('Missing Zone ID');

    $res = rs_update_ai_bot_rule($zone_id, $blocked_bots);
    
    if($res['success']) {
        wp_send_json_success($res);
    } else {
        wp_send_json_error($res['message']);
    }
}

// ------------------------------------------------------
// 3. AJAX: GET ANALYTICS
// ------------------------------------------------------
add_action('wp_ajax_rs_ai_analytics', 'rs_ai_analytics_handler');
function rs_ai_analytics_handler() {
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    
    $zone_id = isset($_POST['zone_id']) ? sanitize_text_field($_POST['zone_id']) : '';
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '24h';
    
    if(!$zone_id) wp_send_json_error('Missing Zone ID');

    // Need Rule ID to filter events
    $rule = rs_get_ai_rule_details($zone_id);
    // if(!$rule) wp_send_json_success([]); // Removed to allow general analytics

    $data = rs_get_ai_analytics_data($zone_id, $rule['id'] ?? null, $period);
    
    if($data['success']) {
        wp_send_json_success($data['data']);
    } else {
        wp_send_json_error($data['message']);
    }
}

// ------------------------------------------------------
// 4. CORE HELPERS
// ------------------------------------------------------

function rs_get_supported_ai_bots() {
    return [
        'gptbot'              => ['ua' => 'GPTBot', 'name' => 'GPTBot', 'entity' => 'OpenAI', 'cat' => 'AI Crawler'],
        'chatgpt'             => ['ua' => 'ChatGPT-User', 'name' => 'ChatGPT User', 'entity' => 'OpenAI', 'cat' => 'AI Assistant'],
        'oai-searchbot'       => ['ua' => 'OAI-SearchBot', 'name' => 'OpenAI SearchBot', 'entity' => 'OpenAI', 'cat' => 'Search Engine Crawler'],
        'claudebot'           => ['ua' => 'ClaudeBot', 'name' => 'ClaudeBot', 'entity' => 'Anthropic', 'cat' => 'AI Crawler'],
        'claude-searchbot'    => ['ua' => 'Claude-SearchBot', 'name' => 'Claude SearchBot', 'entity' => 'Anthropic', 'cat' => 'Search Engine Crawler'],
        'ccbot'               => ['ua' => 'CCBot', 'name' => 'CCBot', 'entity' => 'Common Crawl', 'cat' => 'AI Crawler'],
        'google-extended'     => ['ua' => 'Google-Extended', 'name' => 'Google Extended', 'entity' => 'Google', 'cat' => 'AI Crawler'],
        'googlebot'           => ['ua' => 'Googlebot', 'name' => 'Googlebot', 'entity' => 'Google', 'cat' => 'Search Engine Crawler'],
        'google-vertex'       => ['ua' => 'Google-CloudVertexBot', 'name' => 'Vertex AI Bot', 'entity' => 'Google', 'cat' => 'AI Crawler'],
        'anthropic'           => ['ua' => 'anthropic-ai', 'name' => 'Anthropic Bot', 'entity' => 'Anthropic', 'cat' => 'AI Crawler'],
        'omgilibot'           => ['ua' => 'Omgilibot', 'name' => 'Omgilibot', 'entity' => 'Omgili', 'cat' => 'AI Crawler'],
        'facebookbot'         => ['ua' => 'FacebookBot', 'name' => 'FacebookBot', 'entity' => 'Meta', 'cat' => 'AI Crawler'],
        'meta-externalagent'  => ['ua' => 'Meta-ExternalAgent', 'name' => 'Meta-ExternalAgent', 'entity' => 'Meta', 'cat' => 'AI Crawler'],
        'meta-externalfetcher'=> ['ua' => 'Meta-ExternalFetcher', 'name' => 'Meta-ExternalFetcher', 'entity' => 'Meta', 'cat' => 'AI Crawler'],
        'applebot'            => ['ua' => 'Applebot', 'name' => 'Applebot', 'entity' => 'Apple', 'cat' => 'AI Crawler'],
        'twitterbot'          => ['ua' => 'Twitterbot', 'name' => 'Twitterbot', 'entity' => 'X (Twitter)', 'cat' => 'AI Crawler'],
        'bytespider'          => ['ua' => 'Bytespider', 'name' => 'Bytespider', 'entity' => 'Bytedance', 'cat' => 'AI Crawler'],
        'cohere'              => ['ua' => 'Cohere-magpie', 'name' => 'Cohere Bot', 'entity' => 'Cohere', 'cat' => 'AI Crawler'],
        'amazonbot'           => ['ua' => 'Amazonbot', 'name' => 'Amazonbot', 'entity' => 'Amazon', 'cat' => 'AI Crawler'],
        'petalbot'            => ['ua' => 'PetalBot', 'name' => 'PetalBot', 'entity' => 'Huawei', 'cat' => 'AI Crawler'],
        'bingbot'             => ['ua' => 'BingBot', 'name' => 'BingBot', 'entity' => 'Microsoft', 'cat' => 'Search Engine Crawler'],
        'perplexity'          => ['ua' => 'PerplexityBot', 'name' => 'PerplexityBot', 'entity' => 'Perplexity', 'cat' => 'AI Crawler'],
        'novellum'            => ['ua' => 'Novellum', 'name' => 'Novellum AI Crawl', 'entity' => 'Novellum', 'cat' => 'AI Crawler'],
        'mistral'             => ['ua' => 'MistralAI-User', 'name' => 'Mistral AI User', 'entity' => 'Mistral AI', 'cat' => 'AI Crawler'],
        'duckassist'          => ['ua' => 'DuckAssistBot', 'name' => 'DuckAssistBot', 'entity' => 'DuckDuckGo', 'cat' => 'AI Crawler'],
        'terracotta'          => ['ua' => 'Terracotta', 'name' => 'Terracotta', 'entity' => 'Terracotta AI', 'cat' => 'AI Crawler']
    ];
}

function rs_get_ai_rule_details($zone_id) {
    $rules = rs_get_cf_rules_via_api($zone_id);
    $rule_name = "Royal Sentinel - AI Control";
    
    if (!empty($rules['success']) && !empty($rules['data'])) {
        foreach($rules['data'] as $r) {
            if (isset($r['description']) && $r['description'] === $rule_name) {
                return [
                    'id' => $r['id'],
                    'expression' => $r['filter']['expression'],
                    'enabled' => !($r['paused'] ?? false)
                ];
            }
        }
    }
    return null;
}

function rs_update_ai_bot_rule($zone_id, $blocked_bots) {
    $rule_name = "Royal Sentinel - AI Control";
    $all_bots = rs_get_supported_ai_bots();
    
    // 1. Build Expression
    // Logic: (http.user_agent contains "GPTBot") or (http.user_agent contains "ClaudeBot") ...
    $parts = [];
    foreach($blocked_bots as $bot_key) {
        if(isset($all_bots[$bot_key])) {
            $ua = $all_bots[$bot_key]['ua'];
            $parts[] = '(http.user_agent contains "' . $ua . '")';
        }
    }

    if(empty($parts)) {
        // If no bots are blocked, delete the rule if it exists
        $existing = rs_get_ai_rule_details($zone_id);
        if($existing) {
             // We need filter ID to delete properly
             // Re-fetching full object to get filter ID as handy helper only returns basic info
             // Actually rs_get_cf_rules_via_api returns everything.
             // Let's rely on standard delete.
             // We need rule ID.
             rs_delete_cf_rule_and_filter($zone_id, $existing['id'], null); 
             // Note: rs_delete_cf_rule_and_filter handles the filter delete if we knew the ID, 
             // but if we pass null it might leave filter. 
             // Let's do it properly:
             $all = rs_get_cf_rules_via_api($zone_id);
             foreach($all['data'] as $r) {
                 if($r['id'] === $existing['id']) {
                     rs_delete_cf_rule_and_filter($zone_id, $existing['id'], $r['filter']['id']);
                     break;
                 }
             }
        }
        return ['success' => true, 'message' => 'Rule deleted (No bots selected)'];
    }

    $expression = implode(" or ", $parts);
    $action = 'block'; // Or 'managed_challenge' if preferred, but user requested Control (Block/Allow)

    // 2. Check Existing
    $existing = rs_get_ai_rule_details($zone_id);
    
    if ($existing) {
        // UPDATE: Find Filter ID and Update Filter
        // For Simplicity in this API helper set, we often Delete & Recreate or Patch.
        // Cloudflare API allows PUT on the rule to update it.
        // Let's use Delete & Create for consistency with other modules to avoid Filter ID tracking complexity,
        // or cleaner: Update the Filter Expression.
        
        // Let's go with Delete & Re-Create to ensure clean state and avoid "Filter used by other rules" errors if filter logic changed.
        $all = rs_get_cf_rules_via_api($zone_id);
        foreach($all['data'] as $r) {
            if($r['id'] === $existing['id']) {
                rs_delete_cf_rule_and_filter($zone_id, $existing['id'], $r['filter']['id']);
                break;
            }
        }
    }

    // 3. Create New
    return rs_add_cf_rule_via_api($zone_id, $rule_name, $expression, $action);
}

function rs_get_ai_analytics_data($zone_id, $rule_id, $period) {
    // GraphQL Query
    $api_email = get_option( 'rs_cf_email' ); 
    $api_key = get_option( 'rs_cf_api_key' );
    $headers = rs_headers();
    
    // Calculate Time Range
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $start = gmdate('Y-m-d\TH:i:s\Z', strtotime("-24 hours"));
    if($period === '7d') $start = gmdate('Y-m-d\TH:i:s\Z', strtotime("-7 days"));
    if($period === '30d') $start = gmdate('Y-m-d\TH:i:s\Z', strtotime("-30 days"));

    // Query 1: Firewall Events (Blocked/Challenged)
    // Only run if we have a rule_id, otherwise this dataset is empty for our specific rule
    $fw_query_part = "";
    if($rule_id) {
        $fw_query_part = <<<GRAPHQL
          firewallEvents: firewallEventsAdaptiveGroups(
            limit: 100,
            filter: {
              datetime_geq: "{$start}",
              datetime_leq: "{$now}",
              firewallRuleId: "{$rule_id}"
            },
            orderBy: [datetimeHour_ASC]
          ) {
            count
            dimensions {
              datetimeHour
              action
            }
          }
          
          firewallGrouped: firewallEventsAdaptiveGroups(
            limit: 50,
            filter: {
              datetime_geq: "{$start}",
              datetime_leq: "{$now}",
              firewallRuleId: "{$rule_id}"
            },
            orderBy: [count_DESC]
          ) {
            count
            sum {
              edgeResponseBytes
            }
            dimensions {
              userAgent
              action
            }
          }
GRAPHQL;
    }

    // Query 2: HTTP Requests (Main Traffic & Time Series)
    // We fetch top UAs and filter in PHP because we can't easily OR-filter 10+ strings in GraphQL
    $http_query_part = <<<GRAPHQL
          httpRequests: httpRequestsAdaptiveGroups(
            limit: 500,
            filter: {
              datetime_geq: "{$start}",
              datetime_leq: "{$now}"
            },
            orderBy: [count_DESC]
          ) {
            count
            sum {
              edgeResponseBytes
            }
            dimensions {
              userAgent
              datetimeHour
            }
          }
          
          httpStatus: httpRequestsAdaptiveGroups(
            limit: 500,
            filter: {
              datetime_geq: "{$start}",
              datetime_leq: "{$now}"
            },
            orderBy: [count_DESC]
          ) {
            count
            dimensions {
              userAgent
              edgeResponseStatus
            }
          }

          httpPaths: httpRequestsAdaptiveGroups(
            limit: 500,
            filter: {
              datetime_geq: "{$start}",
              datetime_leq: "{$now}"
            },
            orderBy: [count_DESC]
          ) {
            count
            dimensions {
              userAgent
              clientRequestPath
              clientRequestHTTPHost
            }
          }
GRAPHQL;

    $query = <<<GRAPHQL
    {
      viewer {
        zones(filter: {zoneTag: "{$zone_id}"}) {
          {$fw_query_part}
          {$http_query_part}
        }
      }
    }
GRAPHQL;

    $url = "https://api.cloudflare.com/client/v4/graphql";
    $response = wp_remote_post($url, [
        'headers' => $headers,
        'body' => json_encode(['query' => $query]),
        'timeout' => 20
    ]);

    if(is_wp_error($response)) return ['success' => false, 'message' => $response->get_error_message()];
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if(isset($body['data']['viewer']['zones'][0])) {
        $z = $body['data']['viewer']['zones'][0];
        
        $supported_bots = rs_get_supported_ai_bots(); // ['gptbot' => 'GPTBot', ...]
        $bot_values = array_values($supported_bots);
        
        // Key Helper: Check if UA matches any supported bot
        $is_ai = function($ua) use ($bot_values) {
            foreach($bot_values as $bot_ua) {
                if(stripos($ua, $bot_ua) !== false) return true;
            }
            return false;
        };

        $ua_list = rs_get_supported_ai_bots();
        $stats_per_bot = [];
        foreach($ua_list as $key => $meta) {
            $stats_per_bot[$key] = [
                'name' => $meta['name'],
                'entity' => $meta['entity'],
                'category' => $meta['cat'],
                'total' => 0, 
                'blocked' => 0, 
                'bytes' => 0
            ];
        }

        $ai_timeseries = [];

        if(isset($z['httpRequests'])) {
            foreach($z['httpRequests'] as $req) {
                $ua = $req['dimensions']['userAgent'];
                foreach($ua_list as $key => $meta) {
                    if(strpos($ua, $meta['ua']) !== false) {
                        $stats_per_bot[$key]['total'] += $req['count'];
                        $stats_per_bot[$key]['bytes'] += $req['sum']['edgeResponseBytes'];
                        
                        // Time Series (Total)
                        $hour = $req['dimensions']['datetimeHour'];
                        if(!isset($ai_timeseries[$hour])) $ai_timeseries[$hour] = 0;
                        $ai_timeseries[$hour] += $req['count'];
                        break;
                    }
                }
            }
        }

        // --- 2. Process Status Codes (Distribution) ---
        $status_distribution = [];
        if(isset($z['httpStatus'])) {
            foreach($z['httpStatus'] as $item) {
                $ua = $item['dimensions']['userAgent'];
                $match = false;
                foreach($ua_list as $meta) {
                    if(strpos($ua, $meta['ua']) !== false) { $match = true; break; }
                }
                if($match) {
                    $code = $item['dimensions']['edgeResponseStatus'];
                    if(!isset($status_distribution[$code])) $status_distribution[$code] = 0;
                    $status_distribution[$code] += $item['count'];
                }
            }
        }

        // --- 3. Process Top Paths ---
        $top_paths = [];
        if(isset($z['httpPaths'])) {
            foreach($z['httpPaths'] as $item) {
                $ua = $item['dimensions']['userAgent'];
                $match_ua = '';
                foreach($ua_list as $meta) {
                    if(strpos($ua, $meta['ua']) !== false) { $match_ua = $meta['ua']; break; }
                }
                if($match_ua) {
                    $path = $item['dimensions']['clientRequestPath'];
                    $host = $item['dimensions']['clientRequestHTTPHost'];
                    $key = $host . $path;
                    
                    if(!isset($top_paths[$key])) {
                        $top_paths[$key] = [
                            'path' => $path, 
                            'host' => $host,
                            'count' => 0, 
                            'bots' => []
                        ];
                    }
                    $top_paths[$key]['count'] += $item['count'];
                    // Track which bots hit this path (unique)
                    if(!in_array($match_ua, $top_paths[$key]['bots']) && count($top_paths[$key]['bots']) < 3) {
                         $top_paths[$key]['bots'][] = $match_ua;
                    }
                }
            }
        }
        // Sort paths by count DESC and take top 10
        usort($top_paths, function($a, $b) { return $b['count'] - $a['count']; });
        $top_paths = array_slice($top_paths, 0, 10);


        // --- 4. Process Firewall Events (Explicit Blocks) ---
        $blocked_timeseries = [];
        if(isset($z['firewallGrouped'])) {
            foreach($z['firewallGrouped'] as $evt) {
                $ua = $evt['dimensions']['userAgent'];
                foreach($ua_list as $key => $meta) {
                    if(strpos($ua, $meta['ua']) !== false) {
                        $stats_per_bot[$key]['blocked'] += $evt['count'];
                        break;
                    }
                }
            }
        }

        if(isset($z['firewallEvents'])) {
            foreach($z['firewallEvents'] as $evt) {
                $hour = $evt['dimensions']['datetimeHour'];
                if(!isset($blocked_timeseries[$hour])) $blocked_timeseries[$hour] = 0;
                $blocked_timeseries[$hour] += $evt['count'];
            }
        }

        // --- Format Main Table Data ---
        $final_grouped = [];
        foreach($stats_per_bot as $key => $stat) {
            $allowed = max(0, $stat['total'] - $stat['blocked']);
            $final_grouped[] = [
                'botKey' => $key,
                'name' => $stat['name'],
                'entity' => $stat['entity'],
                'category' => $stat['category'],
                'stats' => [
                    'total' => $stat['total'],
                    'allowed' => $allowed,
                    'blocked' => $stat['blocked'],
                    'bytes' => $stat['bytes']
                ]
            ];
        }

        // --- Format Time Series ---
        $final_timeseries = [];
        $all_hours = array_unique(array_merge(array_keys($ai_timeseries), array_keys($blocked_timeseries)));
        foreach($all_hours as $h) {
            $total = $ai_timeseries[$h] ?? 0;
            $blocked = $blocked_timeseries[$h] ?? 0;
            $allowed = max(0, $total - $blocked);
            
            if($allowed > 0) $final_timeseries[] = ['count' => $allowed, 'dimensions' => ['datetimeHour' => $h, 'action' => 'allow']];
            if($blocked > 0) $final_timeseries[] = ['count' => $blocked, 'dimensions' => ['datetimeHour' => $h, 'action' => 'block']];
        }

        return [
            'success' => true, 
            'data' => [
                'timeseries' => $final_timeseries, // For Line Chart
                'grouped' => $final_grouped,       // For Bot Table
                'status_codes' => $status_distribution, // For Doughnut
                'top_paths' => $top_paths          // For Paths Table
            ]
        ];
    }
    
    if(isset($body['errors']) && !empty($body['errors'])) {
        return ['success' => false, 'message' => 'CF Error: ' . wp_json_encode($body['errors'])];
    }

    if(isset($body['data']['viewer']['zones'][0])) {
        // ... (rest of the processing logic)
    }
    
    return ['success' => false, 'message' => 'No data. Response: ' . substr(wp_json_encode($body), 0, 200)];
}
