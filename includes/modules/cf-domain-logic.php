<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_CF_Domain_Logic {

    public function __construct() {
        // --- Hooks ---
        add_action( 'wp_ajax_rs_cf_domain_action', [ $this, 'handle_quick_action' ] );
        add_action( 'wp_ajax_rs_cf_fetch_details', [ $this, 'fetch_zone_details' ] );
        add_action( 'wp_ajax_rs_cf_update_setting', [ $this, 'update_zone_setting' ] );
        add_action( 'wp_ajax_rs_cf_batch_status', [ $this, 'fetch_batch_zone_status' ] );
        add_action( 'wp_ajax_rs_cf_load_dashboard', [ $this, 'ajax_load_dashboard' ] );
        add_action( 'wp_ajax_rs_cf_load_events', [ $this, 'ajax_load_events' ] );
        add_action( 'wp_ajax_rs_cf_cache_action', [ $this, 'ajax_cache_action' ] );
        add_action( 'wp_ajax_rs_cf_load_settings', [ $this, 'ajax_load_settings' ] );
        add_action( 'wp_ajax_rs_cf_save_settings', [ $this, 'ajax_save_settings' ] );
        add_action( 'wp_ajax_rs_cf_dns_overview', [ $this, 'ajax_dns_overview' ] );
        add_action( 'wp_ajax_rs_cf_add_dns', [ $this, 'ajax_add_dns' ] );
        add_action( 'wp_ajax_rs_cf_delete_dns', [ $this, 'ajax_delete_dns' ] );
        add_action( 'wp_ajax_rs_cf_update_dns', [ $this, 'ajax_update_dns' ] );
        add_action( 'wp_ajax_rs_cf_tools_action', [ $this, 'ajax_tools_action' ] );
        add_action( 'wp_ajax_rs_cf_block_event_ip', [ $this, 'ajax_block_ip_from_event' ] );
        add_action( 'admin_post_rs_export_dns', [ $this, 'handle_dns_export' ] );
        add_action( 'admin_post_rs_import_dns', [ $this, 'handle_dns_import' ] );
    }

    // --- 1. API HELPERS ---
    private function safe_get_setting($zone_id, $setting) {
        $response = $this->cf_request("zones/$zone_id/settings/$setting");
        return $response['result']['value'] ?? 'off'; // Default safely
    }

    private function cf_request( $endpoint, $method = 'GET', $body = null ) {
        if ( ! function_exists('rs_headers') ) {
             return [ 'success' => false, 'message' => 'API Helper Missing' ];
        }
        $headers = rs_headers();
        if ( ! $headers ) {
            return [ 'success' => false, 'message' => 'Missing API Credentials' ];
        }

        $url = "https://api.cloudflare.com/client/v4/" . $endpoint;
        $args = [
            'headers' => $headers,
            'method'  => $method, 
            'timeout' => 30,
            'sslverify' => false
        ];

        if ( $body ) $args['body'] = json_encode( $body );
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }
        
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    private function cf_graphql_request( $query, $variables = [] ) {
        if ( ! function_exists('rs_headers') ) {
             return [ 'success' => false, 'message' => 'API Helper Missing' ];
        }
        $headers = rs_headers();
        if ( ! $headers ) {
            return [ 'success' => false, 'message' => 'Missing API Credentials' ];
        }

        $url = "https://api.cloudflare.com/client/v4/graphql";
        $body = [ 'query' => $query, 'variables' => $variables ];
        
        $args = [
            'headers' => $headers,
            'method'  => 'POST', 
            'timeout' => 30, 
            'body'    => json_encode( $body ),
            'sslverify' => false
        ];
        
        $response = wp_remote_post( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return [ 'success' => false, 'message' => $response->get_error_message() ];
        }
        
        $result = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset($result['errors']) && !empty($result['errors']) ) {
            $msg = $result['errors'][0]['message'] ?? 'Unknown GraphQL Error';
            return [ 'success' => false, 'message' => "CF API: " . $msg ];
        }
        
        return [ 'success' => true, 'data' => $result['data'] ];
    }

    // --- 2. DASHBOARD LOGIC (UPDATED) ---
    public function ajax_load_dashboard() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        set_time_limit(0);

        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        $zones = function_exists('rs_get_cf_zones') ? rs_get_cf_zones()['items'] : [];

        // --- SMART ID SELECTION ---
        if ( $zone_id === 'all' ) {
            if ( empty($zones) ) {
                wp_send_json_error( 'No domains found in Cloudflare.' );
            }
            
            // ফিক্স: যদি মাত্র একটি জোন থাকে, তবে 'account' মোড ব্যবহার না করে সরাসরি 'zone' মোড ব্যবহার করবো।
            // কারণ অধিকাংশ টোকেনের 'Account Analytics' পারমিশন থাকে না।
            if ( count($zones) === 1 ) {
                $id = $zones[0]['id'];
                $type = 'zone';
            } else {
                // একাধিক জোন থাকলে একাউন্ট মোড ট্রাই করবো
                $id = $zones[0]['account']['id'] ?? '';
                $type = 'account';
            }
        } else {
            // নির্দিষ্ট জোন সিলেক্ট করা হলে
            $id = $zone_id;
            $type = 'zone';
        }

        if ( empty($id) ) wp_send_json_error( 'ID not found.' );

        $fetch_res = $this->fetch_simple_analytics( $id, $type );

        if ( ! $fetch_res['success'] ) {
            // যদি অ্যাকাউন্ট মোডে এরর আসে, ইউজারকে নির্দিষ্ট ডোমেইন সিলেক্ট করতে বলবো
            if ( $type === 'account' ) {
                wp_send_json_error( "Note: Your API Token cannot view global account stats. Please select a specific domain from the dropdown above." );
            }
            wp_send_json_error( $fetch_res['message'] );
        }

        $data = $fetch_res['data'];
        $total_req = $data['requests'];
        $total_bw = $data['bandwidth'];

        $response = [
            'top' => [
                'requests'  => number_format_i18n( $data['requests'] ),
                'bandwidth' => size_format( $data['bandwidth'] ),
                'visits'    => number_format_i18n( $data['requests'] ),
                'pageviews' => number_format_i18n( $data['page_views'] )
            ],
            'chart' => $data['chart'],
            'security' => [
                'enc_req'      => number_format_i18n( $data['encrypted_req'] ),
                'enc_req_rate' => ($total_req > 0) ? round(($data['encrypted_req'] / $total_req) * 100, 2) . '%' : '0%',
                'enc_bw'       => size_format( $data['encrypted_bw'] ),
                'enc_bw_rate'  => ($total_bw > 0) ? round(($data['encrypted_bw'] / $total_bw) * 100, 2) . '%' : '0%'
            ],
            'cache' => [
                'cached_req'      => number_format_i18n( $data['cached'] ),
                'cached_req_rate' => ($total_req > 0) ? round(($data['cached'] / $total_req) * 100, 2) . '%' : '0%',
                'cached_bw'       => size_format( $data['cached_bytes'] ),
                'cached_bw_rate'  => ($total_bw > 0) ? round(($data['cached_bytes'] / $total_bw) * 100, 2) . '%' : '0%'
            ],
            'errors' => [ 'err_4xx' => 0, 'rate_4xx' => '0%', 'err_5xx' => 0, 'rate_5xx' => '0%' ],
            'network' => [ 'http' => [], 'ssl' => [], 'content' => [] ]
        ];

        wp_send_json_success( $response );
    }

    // --- 3. ANALYTICS FETCHER ---
    private function fetch_simple_analytics( $id, $type ) {
        $now = gmdate("Y-m-d\TH:i:s\Z");
        $start = gmdate("Y-m-d\TH:i:s\Z", strtotime('-3 days')); 
        
        $node = ($type === 'account') ? 'accounts' : 'zones';
        $filterTag = ($type === 'account') ? 'accountTag' : 'zoneTag';

        $query = <<<GRAPHQL
            query GetSimple(\$tag: string, \$start: Time, \$end: Time) {
              viewer {
                $node(filter: { $filterTag: \$tag }) {
                  httpRequests1hGroups(
                    limit: 168, 
                    filter: {datetime_geq: \$start, datetime_leq: \$end}, 
                    orderBy: [datetime_ASC]
                  ) {
                    dimensions { datetime }
                    sum { requests threats pageViews bytes cachedBytes cachedRequests encryptedBytes encryptedRequests }
                  }
                }
              }
            }
GRAPHQL;

        $variables = [ 'tag' => $id, 'start' => $start, 'end' => $now ];
        $res = $this->cf_graphql_request( $query, $variables );

        if ( !$res['success'] ) {
            return [ 'success' => false, 'message' => $res['message'] ];
        }
        
        if ( empty($res['data']['viewer'][$node]) ) {
             return [ 'success' => false, 'message' => 'No data available or Access Denied.' ];
        }

        $timeseries = $res['data']['viewer'][$node][0]['httpRequests1hGroups'];
        if ( empty($timeseries) ) {
             $timeseries = [];
        }

        $total_req = 0; $total_bw = 0; $total_threats = 0; $total_pv = 0;
        $cached_req = 0; $cached_bw = 0; $enc_req = 0; $enc_bw = 0;
        $chart_labels = []; $chart_req = []; $chart_thr = [];

        foreach($timeseries as $t) {
            $sum = $t['sum'];
            
            $r = $sum['requests'] ?? 0;
            $th = $sum['threats'] ?? 0;
            $pv = $sum['pageViews'] ?? 0;
            $bw = $sum['bytes'] ?? 0;

            $total_req += $r;
            $total_threats += $th;
            $total_pv += $pv;
            $total_bw += $bw;
            
            $cached_req += $sum['cachedRequests'] ?? 0;
            $cached_bw += $sum['cachedBytes'] ?? 0;
            $enc_req += $sum['encryptedRequests'] ?? 0;
            $enc_bw += $sum['encryptedBytes'] ?? 0;

            $chart_labels[] = gmdate('d M H:i', strtotime($t['dimensions']['datetime']));
            $chart_req[] = $r;
            $chart_thr[] = $th;
        }

        return [
            'success' => true,
            'data' => [
                'requests'       => $total_req,
                'bandwidth'      => $total_bw,
                'threats'        => $total_threats,
                'page_views'     => $total_pv,
                'cached'         => $cached_req,
                'cached_bytes'   => $cached_bw,
                'encrypted_req'  => $enc_req,
                'encrypted_bw'   => $enc_bw,
                'chart'          => [ 'labels' => $chart_labels, 'requests' => $chart_req, 'threats' => $chart_thr ]
            ]
        ];
    }

    // --- Events (Same as before) ---
    public function ajax_load_events() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        $now = gmdate("Y-m-d\TH:i:s\Z");
        $past = gmdate("Y-m-d\TH:i:s\Z", strtotime('-24 hours')); 

        $query = <<<'GRAPHQL'
            query GetFirewallEvents($zoneTag: string, $start: Time, $end: Time) {
              viewer {
                zones(filter: {zoneTag: $zoneTag}) {
                  firewallEventsAdaptive(
                    filter: { datetime_geq: $start, datetime_leq: $end }
                    limit: 20
                    orderBy: [datetime_DESC]
                  ) {
                    action clientIP clientCountryName clientRequestPath datetime source
                  }
                }
              }
            }
GRAPHQL;

        $variables = [ 'zoneTag' => $zone_id, 'start' => $past, 'end' => $now ];
        $res = $this->cf_graphql_request( $query, $variables );

        if ( $res['success'] ) {
            $events = $res['data']['viewer']['zones'][0]['firewallEventsAdaptive'] ?? [];
            $blocked_data = function_exists('rs_get_list_items') ? rs_get_list_items() : ['items' => []];
            $blocked_ips = !empty($blocked_data['items']) ? array_column($blocked_data['items'], 'ip') : [];

            foreach ( $events as &$event ) {
                $event['is_blocked'] = in_array( $event['clientIP'], $blocked_ips );
            }
            wp_send_json_success( $events );
        } else {
            wp_send_json_error( $res['message'] ?? 'Failed' );
        }
    }

    public function ajax_block_ip_from_event() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $ip = sanitize_text_field( $_POST['ip'] );
        if ( ! filter_var($ip, FILTER_VALIDATE_IP) ) wp_send_json_error( 'Invalid IP Address' );
        if ( ! function_exists('rs_api_add_ip') ) wp_send_json_error( 'API Function Not Found' );

        $res = rs_api_add_ip( $ip, 'Blocked from Events Tab' );
        if ( $res['success'] ) {
            delete_transient( 'rs_cached_ips' );
            wp_send_json_success( 'IP has been blocked!' );
        } else {
            wp_send_json_error( $res['message'] );
        }
    }

    public function ajax_cache_action() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        $type = sanitize_text_field( $_POST['type'] );

        if ( $type === 'purge_all' ) {
            $res = $this->cf_request( "zones/$zone_id/purge_cache", 'POST', ['purge_everything' => true] );
        } elseif ( $type === 'purge_urls' ) {
            $urls = array_map( 'trim', explode( "\n", $_POST['urls'] ) );
            $res = $this->cf_request( "zones/$zone_id/purge_cache", 'POST', ['files' => $urls] );
        }
        
        if ( isset($res['success']) && $res['success'] ) wp_send_json_success( 'Cache Purged!' );
        else wp_send_json_error( $res['errors'][0]['message'] ?? 'Failed' );
    }

    public function ajax_load_settings() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        
        // Fetch Settings Parallelly (Optimized)
        $settings = [
            'security_level'     => $this->safe_get_setting($zone_id, 'security_level'),
            'browser_check'      => $this->safe_get_setting($zone_id, 'browser_check'),
            'hotlink_protection' => $this->safe_get_setting($zone_id, 'hotlink_protection'),
            'always_online'      => $this->safe_get_setting($zone_id, 'always_online'),
            
            // New Additions
            'development_mode'   => $this->safe_get_setting($zone_id, 'development_mode'),
            'rocket_loader'      => $this->safe_get_setting($zone_id, 'rocket_loader'),
            'ssl'                => $this->safe_get_setting($zone_id, 'ssl'),
            'min_tls_version'    => $this->safe_get_setting($zone_id, 'min_tls_version'),
        ];

        wp_send_json_success( $settings );
    }

    public function ajax_save_settings() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        $setting_id = sanitize_text_field( $_POST['setting_id'] );
        $value = sanitize_text_field( $_POST['value'] );

        $res = $this->cf_request( "zones/$zone_id/settings/$setting_id", 'PATCH', ['value' => $value] );
        if ( isset($res['success']) && $res['success'] ) wp_send_json_success( 'Updated' );
        else wp_send_json_error( 'Failed' );
    }

    public function ajax_dns_overview() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        $res = $this->cf_request( "zones/$zone_id/dns_records?per_page=100" );

        if ( isset($res['success']) && $res['success'] ) {
            $records = $res['result'];
            $stats = [ 'A' => 0, 'CNAME' => 0, 'MX' => 0, 'TXT' => 0, 'PROXIED' => 0, 'TOTAL' => count($records) ];
            foreach($records as $rec) {
                if(isset($stats[$rec['type']])) $stats[$rec['type']]++;
                if($rec['proxied']) $stats['PROXIED']++;
            }
            wp_send_json_success( [ 'records' => $records, 'stats' => $stats ] );
        } else {
            wp_send_json_error( 'Failed to fetch DNS' );
        }
    }

    // 1. Add New Record
    public function ajax_add_dns() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        
        $data = [
            'type'    => sanitize_text_field($_POST['type']),
            'name'    => sanitize_text_field($_POST['name']),
            'content' => sanitize_textarea_field($_POST['content']),
            'ttl'     => intval($_POST['ttl']),
            'proxied' => filter_var($_POST['proxied'], FILTER_VALIDATE_BOOLEAN)
        ];

        // MX রেকর্ডের জন্য প্রায়োরিটি লাগে
        if($data['type'] === 'MX') {
            $data['priority'] = intval($_POST['priority'] ?? 10);
        }

        $res = $this->cf_request( "zones/$zone_id/dns_records", 'POST', $data );
        
        if ( isset($res['success']) && $res['success'] ) {
            wp_send_json_success( 'Record Added Successfully' );
        } else {
            $error = $res['errors'][0]['message'] ?? 'Failed to add record';
            wp_send_json_error( $error );
        }
    }

    // 2. Update Record (Proxy Toggle or Edit)
    public function ajax_update_dns() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $zone_id   = sanitize_text_field( $_POST['zone_id'] );
        $record_id = sanitize_text_field( $_POST['record_id'] );
        
        $data = [];
        
        // বর্তমানে আমরা শুধু প্রক্সি স্ট্যাটাস আপডেট করছি
        if ( isset($_POST['proxied']) ) {
            $data['proxied'] = filter_var($_POST['proxied'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // ভবিষ্যতে নাম বা কন্টেন্ট এডিট করতে চাইলে এখানে ডাটা যুক্ত করতে হবে

        $res = $this->cf_request( "zones/$zone_id/dns_records/$record_id", 'PATCH', $data );
        
        if ( isset($res['success']) && $res['success'] ) {
            wp_send_json_success( 'DNS Updated' );
        } else {
            wp_send_json_error( $res['errors'][0]['message'] ?? 'Update Failed' );
        }
    }

    // 3. Delete Record
    public function ajax_delete_dns() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $zone_id   = sanitize_text_field( $_POST['zone_id'] );
        $record_id = sanitize_text_field( $_POST['record_id'] );

        $res = $this->cf_request( "zones/$zone_id/dns_records/$record_id", 'DELETE' );
        
        if ( isset($res['success']) && $res['success'] ) {
            wp_send_json_success( 'Record Deleted' );
        } else {
            wp_send_json_error( $res['errors'][0]['message'] ?? 'Delete Failed' );
        }
    }


    public function ajax_tools_action() {
        check_ajax_referer( 'rs_cf_tabs', 'nonce' );
        $cmd = sanitize_text_field( $_POST['cmd'] );
        if ( $cmd === 'test_connection' ) {
            $res = $this->cf_request( 'user/tokens/verify' );
            if ( isset($res['success']) && $res['success'] ) wp_send_json_success( 'Active' );
            else wp_send_json_error( 'Invalid Key' );
        }
    }

    // --- Legacy Handlers ---
    public function handle_quick_action() {
        check_ajax_referer( 'rs_domain_action', 'nonce' );
        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        $action  = sanitize_text_field( $_POST['cmd'] );
        
        if ( $action == 'purge_cache' ) {
            $res = $this->cf_request( "zones/$zone_id/purge_cache", 'POST', [ 'purge_everything' => true ] );
            wp_send_json( $res['success'] ? ['success'=>true, 'message'=>'Purged!'] : ['success'=>false, 'message'=>'Failed'] );
        }
        
        $endpoint = ($action == 'toggle_dev_mode') ? 'development_mode' : 'security_level';
        $current = $this->cf_request( "zones/$zone_id/settings/$endpoint" );
        $curr_val = $current['result']['value'] ?? 'off';
        
        if ($action == 'toggle_dev_mode') {
            $new_val = ($curr_val == 'on') ? 'off' : 'on';
            $res = $this->cf_request( "zones/$zone_id/settings/development_mode", 'PATCH', [ 'value' => $new_val ] );
            wp_send_json( ['success'=>$res['success'], 'message'=>'Dev Mode '.($new_val=='on'?'ON':'OFF'), 'state'=>$new_val] );
        } else {
            $new_val = ($curr_val == 'under_attack') ? 'medium' : 'under_attack';
            $res = $this->cf_request( "zones/$zone_id/settings/security_level", 'PATCH', [ 'value' => $new_val ] );
            wp_send_json( ['success'=>$res['success'], 'message'=>'Under Attack '.($new_val=='under_attack'?'ON':'OFF'), 'state'=>$new_val] );
        }
    }

    public function fetch_zone_details() {
        check_ajax_referer( 'rs_domain_action', 'nonce' );
        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        $dns_res = $this->cf_request( "zones/$zone_id/dns_records?per_page=100" );
        $dns_summary = [ 'total' => 0, 'A' => 0, 'CNAME' => 0, 'MX' => 0, 'TXT' => 0, 'proxied' => 0, 'unproxied' => 0 ];
        
        if ( !empty($dns_res['result']) ) {
            $dns_summary['total'] = count($dns_res['result']);
            foreach ( $dns_res['result'] as $r ) {
                if(isset($dns_summary[$r['type']])) $dns_summary[$r['type']]++;
                $r['proxied'] ? $dns_summary['proxied']++ : $dns_summary['unproxied']++;
            }
        }
        $settings = [];
        $keys = ['development_mode', 'security_level', 'browser_check', 'hotlink_protection'];
        $keys = ['development_mode', 'security_level', 'browser_check', 'hotlink_protection'];
        foreach($keys as $k) {
            $settings[$k] = $this->safe_get_setting($zone_id, $k);
        }
        wp_send_json_success( [ 'dns' => $dns_summary, 'settings' => $settings ] );
    }

    public function update_zone_setting() {
        check_ajax_referer( 'rs_domain_action', 'nonce' );
        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        $setting = sanitize_text_field( $_POST['setting_id'] );
        $value = sanitize_text_field( $_POST['value'] );
        $res = $this->cf_request( "zones/$zone_id/settings/$setting", 'PATCH', [ 'value' => $value ] );
        wp_send_json( ['success' => $res['success']] );
    }

    public function fetch_batch_zone_status() {
        check_ajax_referer( 'rs_domain_action', 'nonce' );
        $zone_ids = isset($_POST['zone_ids']) ? $_POST['zone_ids'] : [];
        $results = [];
        if(!empty($zone_ids)) {
             $zid = $zone_ids[0]; 
             $dev_val = $this->safe_get_setting($zid, 'development_mode');
             $sec_val = $this->safe_get_setting($zid, 'security_level');
             $results[$zid] = [
                'dev_mode' => $dev_val,
                'under_attack' => ($sec_val === 'under_attack')
             ];
        }
        wp_send_json_success($results);
    }

    // Export DNS (CSV/JSON)
    public function handle_dns_export() {
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        
        $zone_id = sanitize_text_field( $_GET['zone_id'] );
        $zone_name = sanitize_text_field( $_GET['zone_name'] );
        
        $res = $this->cf_request( "zones/$zone_id/dns_records?per_page=100" );
        if ( empty($res['result']) ) wp_die('No records found or API error.');
        
        $records = $res['result'];
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="dns_backup_'.$zone_name.'_'.date('Y-m-d').'.json"');
        echo json_encode($records, JSON_PRETTY_PRINT);
        exit;
    }

    // Import DNS
    public function handle_dns_import() {
        if ( ! current_user_can('manage_options') ) wp_die('Unauthorized');
        check_admin_referer('rs_dns_import_nonce', 'rs_nonce');

        $zone_id = sanitize_text_field( $_POST['zone_id'] );
        
        if ( !empty($_FILES['dns_file']['tmp_name']) ) {
            $content = file_get_contents($_FILES['dns_file']['tmp_name']);
            $records = json_decode($content, true);
            
            if ( !is_array($records) ) wp_die('Invalid JSON file.');
            
            $count = 0;
            foreach ($records as $rec) {
                // Only import valid types
                if ( isset($rec['type'], $rec['name'], $rec['content']) ) {
                    $data = [
                        'type' => $rec['type'],
                        'name' => $rec['name'],
                        'content' => $rec['content'],
                        'ttl' => 1, // Auto
                        'proxied' => $rec['proxied'] ?? false
                    ];
                    $this->cf_request( "zones/$zone_id/dns_records", 'POST', $data );
                    $count++;
                }
            }
            
            // Redirect back with message
            wp_redirect( admin_url('admin.php?page=rs-cloudflare&tab=tools&msg=imported&count='.$count) );
            exit;
        }
        wp_die('No file uploaded.');
    }

}

new RS_CF_Domain_Logic();