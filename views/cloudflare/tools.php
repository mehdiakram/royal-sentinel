<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

$zones_data = function_exists('rs_get_cf_zones') ? rs_get_cf_zones() : ['items' => []];
$zones = $zones_data['items'];

$current_domain = strtolower(str_replace('www.', '', parse_url(get_site_url(), PHP_URL_HOST)));
$selected_zone_id = '';
if (!empty($zones)) {
    foreach($zones as $z) {
        if ($z['name'] === $current_domain) { $selected_zone_id = $z['id']; break; }
    }
}
?>

<div class="rs-cf-wrapper">

    <?php if ( empty($zones) ): ?>
        <div class="notice notice-warning inline"><p>No domains found. Please check Settings.</p></div>
    <?php else: ?>

        <div class="rs-global-selector" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
            <label style="font-weight:600;"><?php _e( 'Select Domain:', 'royal-sentinel' ); ?></label>
            <select id="rs-tools-zone" style="width: 300px;">
                <option value=""><?php _e( '-- Choose Domain --', 'royal-sentinel' ); ?></option>
                <?php foreach($zones as $z): ?>
                    <option value="<?php echo esc_attr($z['id']); ?>" <?php selected($selected_zone_id, $z['id']); ?>>
                        <?php echo esc_html($z['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="rs-tools-container" style="display:none;">

            <div class="rs-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px; border-top: 4px solid #6f42c1;">
                <h3 style="margin-top:0;">🕵️ IP Intel / Threat Lookup</h3>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="rs-intel-ip" placeholder="Enter IP Address (e.g. 1.1.1.1)" style="width:300px;">
                    <button id="rs-intel-btn" class="button button-primary">Check IP</button>
                </div>
                <div id="rs-intel-result" style="margin-top:15px; display:none; padding:15px; background:#f9f9f9; border:1px solid #eee; border-radius:5px;">
                    </div>
            </div>

            <div class="rs-grid-2" style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                
                <div class="rs-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-top: 4px solid #2271b1;">
                    <h3 style="margin-top:0;">📤 DNS Export & Import</h3>
                    <p class="description">Backup your DNS records before making major changes.</p>
                    
                    <div style="margin-top:15px;">
                        <button id="rs-export-dns" class="button button-secondary">
                            <span class="dashicons dashicons-download"></span> Export JSON
                        </button>
                        
                        <hr style="margin:15px 0;">

                        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="rs_import_dns">
                            <input type="hidden" name="zone_id" id="import-zone-id">
                            <?php wp_nonce_field('rs_dns_import_nonce', 'rs_nonce'); ?>
                            
                            <label style="font-weight:bold;">Import JSON:</label><br>
                            <input type="file" name="dns_file" accept=".json" required style="margin-top:5px;">
                            <button type="submit" class="button button-primary" style="margin-top:5px;">Restote DNS</button>
                        </form>
                    </div>
                </div>

                <div class="rs-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-top: 4px solid #d63638;">
                    <h3 style="margin-top:0;">🚑 Universal SSL Fixer</h3>
                    <p class="description">Is your site showing SSL errors or stuck at "Initializing"? Try restarting the SSL handshake.</p>
                    
                    <div style="margin-top:15px; text-align:center; padding:20px; background:#fff4f4; border:1px solid #ffcccc; border-radius:5px;">
                        <button id="rs-ssl-fix-btn" class="button button-hero button-link-delete" style="color:red; border-color:red;">
                            Restart SSL Handshake
                        </button>
                    </div>
                </div>

            </div>

            <div class="rs-card" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-top:20px;">
                <h3 style="margin-top:0;">🛠️ API Debug</h3>
                <button id="rs-test-api" class="button button-secondary">Test Connection</button>
                <span id="rs-tool-res" style="margin-left:10px; font-weight:bold;"></span>
            </div>

        </div>

    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    
    $('#rs-tools-zone').select2({ placeholder: "Select a domain", allowClear: true });
    var nonce = '<?php echo wp_create_nonce("rs_cf_tabs"); ?>';
    
    // Toggle View & Set Zone ID for Import
    function toggleView(zid) {
        if(!zid) {
            $('#rs-tools-container').slideUp();
        } else {
            $('#rs-tools-container').slideDown();
            $('#import-zone-id').val(zid);
        }
    }
    
    $('#rs-tools-zone').change(function() { toggleView($(this).val()); });
    var initialZone = $('#rs-tools-zone').val();
    if(initialZone) { toggleView(initialZone); }

    // --- 1. IP INTEL ---
    $('#rs-intel-btn').click(function() {
        var ip = $('#rs-intel-ip').val();
        if(!ip) return;
        
        var zid = $('#rs-tools-zone').val();
        var btn = $(this);
        
        btn.prop('disabled', true).text('Scanning...');
        $('#rs-intel-result').slideUp();
        
        // 1. Get Public Info (JS Fetch)
        fetch(`https://ipwhois.app/json/${ip}`)
            .then(response => response.json())
            .then(data => {
                // 2. Check Local Block
                $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_tools_action', cmd: 'ip_intel', ip: ip, zone_id: zid, nonce: nonce }, function(res) {
                    btn.prop('disabled', false).text('Check IP');
                    
                    var isBlocked = res.success && res.data.is_blocked ? '<span style="color:red; font-weight:bold;">🚫 BLOCKED (in your list)</span>' : '<span style="color:green; font-weight:bold;">✅ Clean (not in list)</span>';
                    
                    var html = `
                        <p><strong>IP:</strong> ${ip}</p>
                        <p><strong>Location:</strong> ${data.country || 'N/A'} ${data.country_flag ? '<img src="'+data.country_flag+'" width="20">' : ''}</p>
                        <p><strong>ISP/ASN:</strong> ${data.isp || 'N/A'} (${data.asn || 'N/A'})</p>
                        <p><strong>Status:</strong> ${isBlocked}</p>
                    `;
                    $('#rs-intel-result').html(html).slideDown();
                });
            })
            .catch(err => {
                btn.prop('disabled', false).text('Check IP');
                alert('Failed to fetch IP details.');
            });
    });

    // --- 2. DNS EXPORT ---
    $('#rs-export-dns').click(function() {
        var zid = $('#rs-tools-zone').val();
        var zname = $("#rs-tools-zone option:selected").text().trim();
        if(!zid) return;
        window.location.href = `admin-post.php?action=rs_export_dns&zone_id=${zid}&zone_name=${zname}`;
    });

    // --- 3. SSL FIXER ---
    $('#rs-ssl-fix-btn').click(function() {
        var zid = $('#rs-tools-zone').val();
        Swal.fire({
            title: 'Restart SSL?',
            text: "This will toggle SSL off and on to force re-initialization. Site might be insecure for a few seconds.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d63638',
            confirmButtonText: 'Yes, Restart!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Fixing...', didOpen: () => { Swal.showLoading(); } });
                $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_tools_action', cmd: 'ssl_fix', zone_id: zid, nonce: nonce }, function(res) {
                    Swal.fire(res.success ? 'Success' : 'Error', res.data, res.success ? 'success' : 'error');
                });
            }
        });
    });

    // Debug
    $('#rs-test-api').click(function() {
        $('#rs-tool-res').text('Testing...').css('color', 'black');
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_tools_action', cmd: 'test_connection', nonce: nonce }, function(res) {
            $('#rs-tool-res').text(res.data).css('color', res.success ? 'green' : 'red');
        });
    });
});
</script>

<style>
    .select2-container .select2-selection--single { height: 35px !important; border-color: #8c8f94 !important; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 33px !important; }
</style>
