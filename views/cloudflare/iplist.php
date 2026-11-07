<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$msg_script = "";
$mode = get_option('rs_cf_auth_mode', 'global');

// ================================================================
// ১. সেটিংস ও URL জেনারেশন
// ================================================================
$cf_account_id = get_option( 'rs_cf_acc_id' );
$cf_list_id    = get_option( 'rs_cf_list_id' );

// ক্লাউডফ্লেয়ার ড্যাশবোর্ড লিংক তৈরি
if ( $mode === 'single' ) {
    // Single Mode: জোনের ফায়ারওয়াল ড্যাশবোর্ডে রিডাইরেক্ট
    $zones = function_exists('rs_get_cf_zones') ? rs_get_cf_zones() : ['items'=>[]];
    $zone_name = !empty($zones['items']) ? $zones['items'][0]['name'] : '';
    $cf_edit_url = $zone_name && $cf_account_id ? "https://dash.cloudflare.com/{$cf_account_id}/{$zone_name}/security/waf/tools" : "https://dash.cloudflare.com/";
} else {
    // Global Mode: অ্যাকাউন্ট লিস্ট
    $cf_edit_url = ($cf_account_id && $cf_list_id) ? "https://dash.cloudflare.com/{$cf_account_id}/configurations/lists/{$cf_list_id}" : "https://dash.cloudflare.com/";
}

$current_url = remove_query_arg( array('action', 'item_id', 'rs_nonce'), set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) );

// ================================================================
// ২. অ্যাকশন হ্যান্ডলিং
// ================================================================

// --- A. Quick Block ---
if ( isset( $_POST['rs_quick_block'] ) ) {
    $ip = sanitize_text_field( $_POST['ip_address'] );
    
    // ডুপ্লিকেট চেক
    $existing_data = rs_get_list_items();
    $is_duplicate = false;
    if ( ! empty( $existing_data['items'] ) ) {
        foreach ( $existing_data['items'] as $item ) {
            if ( $item['ip'] === $ip ) { $is_duplicate = true; break; }
        }
    }

    if ( $is_duplicate ) {
        $msg_script = "Swal.fire({ title: 'Duplicate!', text: 'IP is already blocked.', icon: 'warning', timer: 2000, showConfirmButton: false });";
    } else {
        $resp = rs_api_add_ip( $ip, "Blocked via Royal Sentinel" );
        if ( $resp['success'] ) {
            delete_transient( 'rs_cached_ips' ); 
            $msg_script = "Swal.fire({ title: 'Blocked!', text: 'IP added to Cloudflare.', icon: 'success', timer: 1500, showConfirmButton: false });";
        } else {
            $msg_script = "Swal.fire('Error!', '" . esc_js($resp['message']) . "', 'error');";
        }
    }
}

// --- B. Force Refresh ---
else if ( isset( $_POST['rs_force_refresh'] ) ) {
    delete_transient( 'rs_cached_ips' );
    $msg_script = "Swal.fire('Refreshed!', 'Local cache cleared. Fetching new data...', 'info');";
}

// --- C. Delete Action ---
else if ( isset( $_GET['action'] ) && $_GET['action'] == 'delete' && isset( $_GET['item_id'] ) ) {
    $item_id = sanitize_text_field( $_GET['item_id'] );
    rs_api_delete_item( $item_id );
    delete_transient( 'rs_cached_ips' );
    $msg_script = "Swal.fire({ title: 'Removed!', text: 'Rule removed.', icon: 'success', timer: 1500, showConfirmButton: false });";
}

// --- D. Import Message Check ---
if ( $imp_msg = get_transient( 'rs_import_msg' ) ) {
    $icon = ($imp_msg['type'] === 'success') ? 'success' : 'error';
    $msg_script .= "Swal.fire({ title: '" . ucfirst($imp_msg['type']) . "!', text: '" . esc_js($imp_msg['text']) . "', icon: '$icon' });";
    delete_transient( 'rs_import_msg' );
}

// ================================================================
// ৩. ডাটা ফেচিং
// ================================================================
$fetch_data = rs_get_list_items(); 
$list_items = $fetch_data['items'];
$error_msg  = $fetch_data['error'];

if ( $msg_script ) { echo "<script>window.onload = function() { $msg_script }</script>"; }
?>

<div class="rs-iplist-wrap">

    <div class="rs-quick-block-area" style="background:#fff; padding:20px; border-left: 5px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom:20px; display:flex; flex-wrap:wrap; justify-content: space-between; align-items:center; gap:15px;">
        
        <form id="rs-block-form" method="post" action="<?php echo esc_url($current_url); ?>" style="display:flex; gap:10px; align-items:center; flex:1; min-width:300px;">
            <h2 style="margin:0; padding-right:15px; color:#d63638;">⚡ Quick Block:</h2>
            <input type="hidden" name="rs_quick_block" value="1">
            <input type="text" name="ip_address" placeholder="Enter IP Address..." required style="width:250px; height: 35px;">
            <button type="submit" id="block-submit-btn" class="button button-primary button-large">Block Now</button>
        </form>

        <div style="display:flex; gap:10px; align-items:center;">
            
            <form method="post" action="<?php echo esc_url($current_url); ?>" style="display:inline;">
                 <button type="submit" name="rs_force_refresh" class="button button-secondary" style="border-color:#2271b1; color:#2271b1;">
                    <span class="dashicons dashicons-update" style="vertical-align:middle;"></span> Sync / Refresh
                </button>
            </form>

            <a href="<?php echo esc_url( $cf_edit_url ); ?>" target="_blank" class="button button-secondary">
                Edit in Cloudflare <span class="dashicons dashicons-external"></span>
            </a>
        </div>
    </div>

    <!-- Quick Whitelist Section -->
    <div class="rs-quick-whitelist-area" style="background:#f0faf4; padding:20px; border-left: 5px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom:20px; display:flex; flex-wrap:wrap; align-items:center; gap:15px;">
        <div style="display:flex; gap:10px; align-items:center; flex:1; min-width:300px;">
            <h2 style="margin:0; padding-right:15px; color:#00a32a; white-space:nowrap;">✅ <?php _e('Quick Whitelist:', 'royal-sentinel'); ?></h2>
            <?php $current_ip = function_exists('rs_get_client_ip') ? rs_get_client_ip() : ''; ?>
            <input type="text" id="rs-whitelist-ip" value="<?php echo esc_attr($current_ip); ?>" placeholder="<?php esc_attr_e('Enter IP Address to whitelist...', 'royal-sentinel'); ?>" style="width:260px; height:35px;">
            <input type="text" id="rs-whitelist-comment" placeholder="<?php esc_attr_e('Note (optional)...', 'royal-sentinel'); ?>" style="width:200px; height:35px;">
            <button type="button" id="rs-whitelist-btn" class="button button-large" style="background:#00a32a; color:#fff; border-color:#00a32a;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span> <?php _e('Whitelist in Cloudflare', 'royal-sentinel'); ?>
            </button>
        </div>
        <div style="font-size:12px; color:#666; max-width:350px;">
            <strong>ℹ️ <?php _e('What happens?', 'royal-sentinel'); ?></strong> <?php printf( __( 'This IP will be added to Cloudflare\'s %s access rule.', 'royal-sentinel' ), '<code>whitelist</code>' ); ?> 
            <?php _e('It will bypass Mega Block and other WAF rules.', 'royal-sentinel'); ?>
        </div>
    </div>


    <?php if ( $error_msg ): ?>
        <div class="notice notice-error inline"><p>⚠️ API Error: <?php echo esc_html($error_msg); ?></p></div>
    <?php endif; ?>

    <div style="margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 10px;">
        <a href="<?php echo admin_url('admin-post.php?action=rs_export_ips'); ?>" class="button button-secondary" style="display:flex; align-items:center; gap:5px;">
            <span class="dashicons dashicons-download"></span> Export CSV
        </a>

        <button type="button" class="button button-secondary" id="rs-import-btn" style="display:flex; align-items:center; gap:5px;">
            <span class="dashicons dashicons-upload"></span> Import CSV
        </button>

        <form id="rs-import-form" action="<?php echo admin_url('admin-post.php'); ?>" method="post" enctype="multipart/form-data" style="display:none;">
            <input type="hidden" name="action" value="rs_import_ips">
            <?php wp_nonce_field('rs_import_nonce', 'rs_nonce'); ?>
            <input type="file" name="csv_file" id="rs-import-file" accept=".csv">
        </form>
    </div>

    <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,.04);">
        <h3 style="margin-top:0;">
            📋 Active Rules 
            <?php if($mode === 'single') echo '<span style="font-size:12px; font-weight:normal; color:#666;">(Zone Access Rules)</span>'; ?>
            (Total: <?php echo count($list_items); ?>)
        </h3>
        
        <?php if(empty($list_items) && $mode === 'single'): ?>
            <div style="padding:15px; background:#f0f0f1; border-radius:5px; margin-bottom:15px;">
                <strong>Note:</strong> You are using "Single Zone Token" mode. This list shows <em>Zone Level Rules</em> (Firewall > Tools). 
                If your IPs are in "Account IP Lists" (Global), they will NOT show here. You can import them using the CSV options above.
            </div>
        <?php endif; ?>

        <table id="rs-ip-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Mode</th>
                    <th>Note</th>
                    <th>Date Added</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $list_items ) ): ?>
                    <?php foreach ( $list_items as $item ): ?>
                        <?php 
                            $created_raw = $item['created_on'] ?? '';
                            $timestamp   = !empty($created_raw) ? strtotime($created_raw) : 0;
                            $pretty_date = !empty($created_raw) ? wp_date("d M Y, h:i A", $timestamp) : '-';
                            $mode_val    = $item['mode'] ?? 'list';
                            
                            $mode_color = '#666';
                            if($mode_val == 'block') $mode_color = '#d63638';
                            elseif($mode_val == 'challenge' || $mode_val == 'js_challenge') $mode_color = '#d48806';
                            elseif($mode_val == 'whitelist') $mode_color = '#00a32a';
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($item['ip']); ?></strong></td>
                            <td><span style="color:<?php echo $mode_color; ?>; font-weight:bold; text-transform:uppercase; font-size:12px;"><?php echo esc_html($mode_val); ?></span></td>
                            <td><?php echo esc_html($item['comment'] ?? '-'); ?></td>
                            <td data-order="<?php echo esc_attr($timestamp); ?>">
                                <?php echo esc_html($pretty_date); ?>
                            </td>
                            <td>
                                <a href="?page=rs-cloudflare&tab=iplist&action=delete&item_id=<?php echo $item['id']; ?>" 
                                   class="button button-link-delete rs-delete-btn" 
                                   style="color: #b32d2e;">Remove</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
jQuery(document).ready(function($) {
    if ( ! $.fn.DataTable.isDataTable('#rs-ip-table') ) {
        $("#rs-ip-table").DataTable({ "order": [[ 3, "desc" ]], "pageLength": 10 });
    }

    $("#rs-block-form").on("submit", function(e) {
        if($(this).find("input[name='ip_address']").val().trim() === "") return;
        var btn = $("#block-submit-btn");
        btn.prop('disabled', true).text('Blocking...');
        Swal.fire({ title: 'Processing...', text: 'Adding Rule to Cloudflare...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
    });

    $(".rs-delete-btn").on("click", function(e) {
        e.preventDefault();
        var link = $(this).attr("href");
        Swal.fire({
            title: "Remove Rule?",
            text: "This action will remove the IP from Cloudflare rules.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            confirmButtonText: "Yes, Remove"
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Removing...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
                window.location.href = link;
            }
        });
    });

    $('#rs-import-btn').click(function() { $('#rs-import-file').click(); });
    $('#rs-import-file').change(function() {
        if ($(this).val()) {
            Swal.fire({ title: 'Importing...', text: 'Processing CSV...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
            $('#rs-import-form').submit();
        }
    });

    // ==============================
    // Quick Whitelist Handler
    // ==============================
    var whitelistNonce = '<?php echo wp_create_nonce("rs_cf_whitelist_nonce"); ?>';

    $('#rs-whitelist-btn').on('click', function() {
        var ip      = $('#rs-whitelist-ip').val().trim();
        var comment = $('#rs-whitelist-comment').val().trim();

        if (!ip) {
            Swal.fire({ title: '<?php esc_attr_e( "Missing IP!", "royal-sentinel" ); ?>', text: '<?php esc_attr_e( "Please enter an IP address to whitelist.", "royal-sentinel" ); ?>', icon: 'warning', timer: 2000, showConfirmButton: false });
            return;
        }

        Swal.fire({
            title: '<?php esc_attr_e( "Whitelist IP?", "royal-sentinel" ); ?>',
            html: '<p><?php _e( "Do you want to", "royal-sentinel" ); ?> <span style="color:#00a32a; font-weight:bold;"><?php _e( "WHITELIST", "royal-sentinel" ); ?></span> <?php _e( "IP", "royal-sentinel" ); ?> <strong>' + ip + '</strong> <?php _e( "in Cloudflare?", "royal-sentinel" ); ?></p>' +
                  '<p style="font-size:13px; color:#666;"><?php _e( "It will bypass Mega Block and all WAF rules.", "royal-sentinel" ); ?></p>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#00a32a',
            confirmButtonText: '<?php esc_attr_e( "✅ Yes, Whitelist It!", "royal-sentinel" ); ?>',
            cancelButtonText: '<?php esc_attr_e( "Cancel", "royal-sentinel" ); ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                var btn = $('#rs-whitelist-btn');
                btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="vertical-align:middle; animation: spin 1s linear infinite;"></span> <?php esc_attr_e( "Processing...", "royal-sentinel" ); ?>');
                Swal.fire({ title: '<?php esc_attr_e( "Whitelisting...", "royal-sentinel" ); ?>', text: '<?php esc_attr_e( "Sending to Cloudflare...", "royal-sentinel" ); ?>', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action:  'rs_cf_quick_whitelist',
                        nonce:   whitelistNonce,
                        ip:      ip,
                        comment: comment || 'Whitelisted via Royal Sentinel'
                    },
                    dataType: 'json',
                    success: function(res) {
                        btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span> <?php esc_attr_e( "Whitelist in Cloudflare", "royal-sentinel" ); ?>');
                        if (res.success) {
                            Swal.fire({
                                title: '<?php esc_attr_e( "Whitelisted! ✅", "royal-sentinel" ); ?>',
                                html: '<p><?php _e( "IP", "royal-sentinel" ); ?> <strong>' + ip + '</strong> <?php _e( "has been successfully whitelisted in Cloudflare.", "royal-sentinel" ); ?></p><p style="font-size:12px; color:#666;"><?php _e( "Reloading page...", "royal-sentinel" ); ?></p>',
                                icon: 'success',
                                timer: 2500,
                                showConfirmButton: false
                            }).then(() => { location.reload(); });
                        } else {
                            Swal.fire({ title: '<?php esc_attr_e( "Error!", "royal-sentinel" ); ?>', text: res.data || 'Something went wrong.', icon: 'error' });
                        }
                    },
                    error: function(xhr) {
                        btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span> Whitelist in Cloudflare');
                        Swal.fire({ title: 'AJAX Error!', text: 'Could not connect to server.', icon: 'error' });
                    }
                });
            }
        });
    });

    // Spin animation for loading
    if (!$('#rs-whitelist-spin-style').length) {
        $('head').append('<style id="rs-whitelist-spin-style">@keyframes spin { 0% { transform:rotate(0deg); } 100% { transform:rotate(360deg); } }</style>');
    }
});
</script>
