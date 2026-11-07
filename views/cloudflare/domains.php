<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Force Refresh Logic
if ( isset( $_POST['rs_refresh_domains'] ) ) {
    delete_transient( 'rs_cached_zones' );
}

// Fetch Data
$zones_data = [];
$error_msg = null;
if ( function_exists( 'rs_get_cf_zones' ) ) {
    $fetch = rs_get_cf_zones();
    $zones_data = $fetch['items'];
    $error_msg  = $fetch['error'];
}
?>

<div class="rs-domains-wrap">

    <div class="rs-header-box">
        <div>
            <h2 class="rs-title">🌐 Connected Domains (<?php echo count($zones_data); ?>)</h2>
            <p class="rs-subtitle">Manage cache, security modes, and view DNS summaries.</p>
        </div>
        <form method="post">
            <button type="submit" name="rs_refresh_domains" class="button button-secondary">
                <span class="dashicons dashicons-update"></span> Sync Domains
            </button>
        </form>
    </div>

    <?php if ( $error_msg ): ?>
        <div class="notice notice-error inline"><p>⚠️ API Error: <?php echo esc_html($error_msg); ?></p></div>
    <?php endif; ?>

    <div class="rs-table-box">
        <table id="rs-domains-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>Domain Name</th>
                    <th>Status</th>
                    <th>Plan</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $zones_data ) ): ?>
                    <?php foreach ( $zones_data as $zone ): ?>
                        <?php 
                            $status_color = ($zone['status'] == 'active') ? '#2ecc71' : '#e74c3c';
                            $zid = esc_attr($zone['id']);
                            $zname = esc_attr($zone['name']);
                        ?>
                        <tr data-id="<?php echo $zid; ?>" data-name="<?php echo $zname; ?>">
                            <td>
                                <a href="#" class="rs-domain-link rs-open-drawer">
                                    <strong><?php echo esc_html($zone['name']); ?></strong>
                                    <span class="dashicons dashicons-arrow-right-alt2" style="font-size:12px;vertical-align:middle;"></span>
                                </a>
                            </td>
                            <td>
                                <span class="rs-badge" style="border-color:<?php echo $status_color; ?>; color:<?php echo $status_color; ?>;">
                                    <?php echo esc_html($zone['status']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($zone['plan']['name']); ?></td>
                            <td>
                                <div class="rs-actions-wrapper">
                                    <span class="rs-status-text">Pending...</span>
                                    
                                    <div class="rs-actions-group" style="display:none;">
                                        <button type="button" class="button rs-action-btn" data-action="purge_cache" title="Purge Cache">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                        <button type="button" class="button rs-action-btn" data-action="toggle_dev_mode" title="Toggle Dev Mode">
                                            <span class="dashicons dashicons-code-standards"></span>
                                        </button>
                                        <button type="button" class="button rs-action-btn" data-action="toggle_under_attack" title="Under Attack Mode">
                                            <span class="dashicons dashicons-shield"></span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<div id="rs-zone-drawer" class="rs-drawer">
    <div class="rs-drawer-header">
        <h3 id="rs-drawer-title">Zone Settings</h3>
        <button type="button" id="rs-close-drawer"><span class="dashicons dashicons-no-alt"></span></button>
    </div>
    
    <div class="rs-drawer-content">
        <div id="rs-drawer-loader" style="text-align:center; padding:30px; display:none;">
            <span class="dashicons dashicons-update spin" style="font-size:30px; width:30px; height:30px; color:#2271b1;"></span>
            <p>Fetching Zone Data...</p>
        </div>

        <div id="rs-drawer-body" style="display:none;">
            <div class="rs-panel-section">
                <h4>📊 DNS Summary</h4>
                <div class="rs-dns-stats-grid">
                    <div class="rs-stat">
                        <span class="rs-stat-val" id="dns-total">0</span>
                        <span class="rs-stat-label">Total Records</span>
                    </div>
                    <div class="rs-stat">
                        <span class="rs-stat-val" id="dns-proxied" style="color:#F38020">0</span>
                        <span class="rs-stat-label">Proxy ON</span>
                    </div>
                </div>
                <button type="button" id="rs-view-dns-modal" class="button button-secondary" style="width:100%; margin-top:10px;">
                    View Detailed DNS Breakdown
                </button>
            </div>
            <hr>
            <div class="rs-panel-section">
                <h4>🛡️ Security & Performance</h4>
                <div class="rs-toggle-row">
                    <label>Development Mode</label>
                    <label class="rs-switch-sm">
                        <input type="checkbox" class="rs-setting-toggle" data-setting="development_mode">
                        <span class="slider round"></span>
                    </label>
                </div>
                <p class="description">Temporarily bypass cache.</p>

                <div class="rs-toggle-row">
                    <label>Under Attack Mode</label>
                    <label class="rs-switch-sm">
                        <input type="checkbox" class="rs-setting-toggle" data-setting="security_level" data-on="under_attack" data-off="medium">
                        <span class="slider round"></span>
                    </label>
                </div>
                <p class="description">Show JS challenge to all visitors.</p>

                <div class="rs-toggle-row">
                    <label>Browser Integrity Check</label>
                    <label class="rs-switch-sm">
                        <input type="checkbox" class="rs-setting-toggle" data-setting="browser_check">
                        <span class="slider round"></span>
                    </label>
                </div>

                <div class="rs-toggle-row">
                    <label>Hotlink Protection</label>
                    <label class="rs-switch-sm">
                        <input type="checkbox" class="rs-setting-toggle" data-setting="hotlink_protection">
                        <span class="slider round"></span>
                    </label>
                </div>
            </div>
            <hr>
            <div class="rs-panel-section">
                <button type="button" class="button button-large rs-action-btn" data-action="purge_cache" style="width:100%; color:#d63638; border-color:#d63638;">
                    <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> Purge Everything
                </button>
            </div>
        </div>
    </div>
</div>
<div id="rs-drawer-overlay"></div>

<style>
    /* Table & General */
    .rs-header-box { background:#fff; padding:20px; border-left: 5px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom:20px; display:flex; justify-content: space-between; align-items:center; }
    .rs-title { margin:0; color:#2271b1; }
    .rs-table-box { background:#fff; padding:20px; border:1px solid #ccd0d4; }
    .rs-badge { font-weight: 600; text-transform: uppercase; font-size: 11px; border: 1px solid; padding: 2px 6px; border-radius: 3px; }
    .rs-domain-link { text-decoration: none; font-size: 14px; color: #1d2327; }
    .rs-domain-link:hover { color: #2271b1; }
    
    .rs-actions-group { display: flex; gap: 5px; align-items: center; }
    
    /* Loading Status Text */
    .rs-status-text { font-size: 11px; color: #e67e22; font-weight: 600; display: inline-block; }
    .rs-status-text.show { animation: blink 1.5s infinite; }
    @keyframes blink { 50% { opacity: 0.5; } }

    /* Action Buttons Styles */
    .rs-action-btn { transition: all 0.2s; }
    
    /* Fix 1: Dev Mode Color - High Contrast Neon */
    .rs-action-btn.rs-btn-active {
        background-color: #00e676 !important; /* Neon Green */
        color: #000000 !important; /* Solid Black Text */
        border-color: #00e676 !important;
        font-weight: 900 !important;
        box-shadow: 0 0 8px rgba(0, 230, 118, 0.6) !important; /* Glow Effect */
    }
    
    .rs-action-btn.rs-btn-danger-active {
        background-color: #fff0f0 !important;
        color: #d63638 !important;
        border-color: #d63638 !important;
        font-weight: bold !important;
        box-shadow: inset 0 0 0 1px #d63638;
        animation: rs-pulse 2s infinite;
    }

    @keyframes rs-pulse { 
        0% { box-shadow: 0 0 0 0 rgba(214, 54, 56, 0.4); } 
        70% { box-shadow: 0 0 0 6px rgba(214, 54, 56, 0); } 
        100% { box-shadow: 0 0 0 0 rgba(214, 54, 56, 0); } 
    }

    /* Drawer Styles */
    .rs-drawer { position: fixed; top: 0; right: -400px; width: 380px; height: 100%; background: #fff; box-shadow: -2px 0 10px rgba(0,0,0,0.1); z-index: 99999; transition: right 0.3s ease; display: flex; flex-direction: column; }
    .rs-drawer.open { right: 0; }
    .rs-drawer-header { padding: 20px; background: #f0f0f1; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
    .rs-drawer-header h3 { margin: 0; }
    #rs-close-drawer { background: none; border: none; font-size: 20px; cursor: pointer; color: #666; }
    .rs-drawer-content { flex: 1; overflow-y: auto; padding: 20px; }
    #rs-drawer-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.3); z-index: 99998; display: none; }
    
    .rs-panel-section { margin-bottom: 20px; }
    .rs-panel-section h4 { margin-top: 0; color: #444; border-bottom: 1px solid #eee; padding-bottom: 10px; }
    .rs-dns-stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; text-align: center; }
    .rs-stat { background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #eee; }
    .rs-stat-val { display: block; font-size: 20px; font-weight: bold; color: #2271b1; }
    .rs-stat-label { font-size: 11px; color: #666; text-transform: uppercase; }
    
    .rs-toggle-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
    .rs-switch-sm { position: relative; display: inline-block; width: 34px; height: 20px; }
    .rs-switch-sm input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .slider { background-color: #2271b1; }
    input:checked + .slider:before { transform: translateX(14px); }
</style>

<script>
jQuery(document).ready(function($) {
    
    var endpoint = (typeof rs_ajax_url !== 'undefined') ? rs_ajax_url : ajaxurl;
    var domainQueue = [];
    var isProcessing = false;
    
    // Fix 3: Caching Object (Pagination Solution)
    // আমরা ডাটা এখানে জমিয়ে রাখব, যাতে পেজ চেঞ্জ করলে আবার লোড না নেয়
    var zoneStatusCache = {}; 

    // ------------------------------------
    // DATATABLE INIT
    // ------------------------------------
    var table = $("#rs-domains-table").DataTable({
        "pageLength": 50,
        "language": { "search": "🔍 Search Domain:" },
        "drawCallback": function( settings ) {
            var api = this.api();
            domainQueue = [];
            
            api.rows( {page:'current'} ).nodes().each(function(row) {
                var id = $(row).data('id');
                if(id) {
                    // যদি আগে থেকেই ক্যাশে ডাটা থাকে, সরাসরি আপডেট করো (No Loading)
                    if (zoneStatusCache[id]) {
                        updateRowUI($(row), zoneStatusCache[id]);
                    } else {
                        // যদি ক্যাশে না থাকে, লোডিং দেখাও এবং কিউতে ঢোকাও
                        $(row).find('.rs-status-text').text('Checking Cloudflare...').show();
                        $(row).find('.rs-actions-group').hide(); // Fix 2: বাটন লুকাও
                        domainQueue.push(id);
                    }
                }
            });

            if(!isProcessing && domainQueue.length > 0) {
                processNextDomain();
            }
        }
    });

    // ------------------------------------
    // SEQUENTIAL LOADING LOGIC
    // ------------------------------------
    function processNextDomain() {
        if(domainQueue.length === 0) {
            isProcessing = false;
            return;
        }

        isProcessing = true;
        var zoneId = domainQueue.shift();
        
        // যদি ইতিমধ্যে অন্য কোনো প্রসেসে এটি ক্যাশ হয়ে গিয়ে থাকে
        if(zoneStatusCache[zoneId]) {
             processNextDomain(); // Skip fetch, go next
             return;
        }

        var row = $('#rs-domains-table').find('tr[data-id="' + zoneId + '"]');

        $.post(endpoint, {
            action: 'rs_cf_batch_status',
            zone_ids: [zoneId],
            nonce: '<?php echo wp_create_nonce("rs_domain_action"); ?>'
        }, function(resp) {
            
            if(resp.success && resp.data[zoneId]) {
                var status = resp.data[zoneId];
                
                // Cache the result!
                zoneStatusCache[zoneId] = status;
                
                // UI আপডেট করো
                updateRowUI(row, status);
            }
            processNextDomain();

        }).fail(function() {
            row.find('.rs-status-text').text('Error').css('color', 'red');
            processNextDomain();
        });
    }

    // Helper: UI Update (Reused by Cache & Ajax)
    function updateRowUI(row, status) {
        // Fix 2: লোডিং টেক্সট লুকাও, বাটন দেখাও
        row.find('.rs-status-text').hide();
        row.find('.rs-actions-group').fadeIn(200);
        
        row.find('.rs-action-btn').addClass('rs-ready');

        // Dev Mode Button Style
        var devBtn = row.find('button[data-action="toggle_dev_mode"]');
        if (status.dev_mode === 'on') {
            devBtn.addClass('rs-btn-active');
        } else {
            devBtn.removeClass('rs-btn-active');
        }

        // Under Attack Mode Style
        var uamBtn = row.find('button[data-action="toggle_under_attack"]');
        if (status.under_attack === true) {
            uamBtn.addClass('rs-btn-danger-active');
        } else {
            uamBtn.removeClass('rs-btn-danger-active');
        }
    }

    // ------------------------------------
    // DRAWER & MODAL LOGIC
    // ------------------------------------
    var currentZoneId = '';
    var currentDNSData = {}; 

    $(document).on('click', '.rs-open-drawer', function(e) {
        e.preventDefault();
        var row = $(this).closest('tr');
        openDrawer(row.data('id'), row.data('name'));
    });

    $('#rs-close-drawer, #rs-drawer-overlay').click(function() {
        $('#rs-zone-drawer').removeClass('open');
        $('#rs-drawer-overlay').fadeOut();
    });

    function openDrawer(zoneId, zoneName) {
        currentZoneId = zoneId;
        $('#rs-drawer-title').text(zoneName);
        $('#rs-zone-drawer').addClass('open');
        $('#rs-drawer-overlay').fadeIn();
        $('#rs-drawer-body').hide();
        $('#rs-drawer-loader').show();

        $.post(endpoint, {
            action: 'rs_cf_fetch_details',
            zone_id: zoneId,
            nonce: '<?php echo wp_create_nonce("rs_domain_action"); ?>'
        }, function(resp) {
            if(resp.success) {
                var data = resp.data;
                currentDNSData = data.dns;
                $('#dns-total').text(data.dns.total);
                $('#dns-proxied').text(data.dns.proxied);
                updateToggle('development_mode', data.settings.development_mode, 'on');
                updateToggle('browser_check', data.settings.browser_check, 'on');
                updateToggle('hotlink_protection', data.settings.hotlink_protection, 'on');
                updateToggle('security_level', data.settings.security_level, 'under_attack');
                $('#rs-drawer-loader').hide();
                $('#rs-drawer-body').fadeIn();
            } else {
                Swal.fire('Error', resp.data || 'Fetch failed', 'error');
                $('#rs-close-drawer').click();
            }
        });
    }

    function updateToggle(settingKey, value, onValue) {
        var chk = $(`.rs-setting-toggle[data-setting="${settingKey}"]`);
        var targetOn = chk.data('on') || 'on';
        chk.prop('checked', value === targetOn);
    }

    $('.rs-setting-toggle').change(function() {
        var chk = $(this);
        var setting = chk.data('setting');
        var isChecked = chk.is(':checked');
        var sendVal = isChecked ? (chk.data('on') || 'on') : (chk.data('off') || 'off');
        chk.prop('disabled', true);
        $.post(endpoint, {
            action: 'rs_cf_update_setting',
            zone_id: currentZoneId,
            setting_id: setting,
            value: sendVal,
            nonce: '<?php echo wp_create_nonce("rs_domain_action"); ?>'
        }, function(resp) {
            chk.prop('disabled', false);
            if(resp.success) {
                // আপডেট হলে টেবিলের বাটন ও ক্যাশও আপডেট করতে হবে (যাতে সিঙ্ক থাকে)
                if(setting === 'development_mode') {
                    // Update Cache
                    if(zoneStatusCache[currentZoneId]) {
                        zoneStatusCache[currentZoneId].dev_mode = sendVal;
                    }
                    // Update Table Row Button
                    var row = $('#rs-domains-table').find('tr[data-id="' + currentZoneId + '"]');
                    updateRowUI(row, zoneStatusCache[currentZoneId]);
                }
                
                const Toast = Swal.mixin({ toast: true, position: 'bottom-end', showConfirmButton: false, timer: 3000 });
                Toast.fire({ icon: 'success', title: 'Setting Updated' });
            } else {
                chk.prop('checked', !isChecked);
                Swal.fire('Error', resp.data || 'Failed', 'error');
            }
        });
    });

    $(document).on('click', '.rs-action-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        if (!btn.hasClass('rs-ready')) return; 

        var action = btn.data('action');
        var row = btn.closest('tr');
        var zoneId = row.length ? row.data('id') : currentZoneId;
        if(!zoneId) return;

        var origIcon = btn.html();
        btn.html('<span class="dashicons dashicons-update spin"></span>').prop('disabled', true);

        $.post(endpoint, {
            action: 'rs_cf_domain_action',
            cmd: action,
            zone_id: zoneId,
            nonce: '<?php echo wp_create_nonce("rs_domain_action"); ?>'
        }, function(resp) {
            btn.html(origIcon).prop('disabled', false);
            if(resp.success) {
                Swal.fire({ icon: 'success', title: resp.message, showConfirmButton: false, timer: 1500 });
                
                // বাটন আপডেট এবং ক্যাশ আপডেট
                if(zoneStatusCache[zoneId]) {
                    if (action === 'toggle_dev_mode') {
                        zoneStatusCache[zoneId].dev_mode = resp.state; // Update JS Cache
                        resp.state === 'on' ? btn.addClass('rs-btn-active') : btn.removeClass('rs-btn-active');
                    }
                    if (action === 'toggle_under_attack') {
                        zoneStatusCache[zoneId].under_attack = (resp.state === 'under_attack'); // Update JS Cache
                        resp.state === 'under_attack' ? btn.addClass('rs-btn-danger-active') : btn.removeClass('rs-btn-danger-active');
                    }
                }

            } else {
                Swal.fire('Failed', resp.message || 'Error', 'error');
            }
        }).fail(function() {
            btn.html(origIcon).prop('disabled', false);
            Swal.fire('Error', 'Server Error', 'error');
        });
    });

    $('#rs-view-dns-modal').click(function() {
        if(!currentDNSData) return;
        var htmlContent = `
            <div style="text-align:left; font-size:14px; line-height:1.6;">
                <p><strong>Total Records:</strong> ${currentDNSData.total}</p>
                <hr style="margin:10px 0; border:0; border-top:1px solid #eee;">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div><strong>A:</strong> ${currentDNSData.A || 0}</div>
                    <div><strong>CNAME:</strong> ${currentDNSData.CNAME || 0}</div>
                    <div><strong>MX:</strong> ${currentDNSData.MX || 0}</div>
                    <div><strong>TXT:</strong> ${currentDNSData.TXT || 0}</div>
                </div>
                <hr style="margin:10px 0; border:0; border-top:1px solid #eee;">
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:#F38020">☁️ Proxied: ${currentDNSData.proxied}</span>
                    <span style="color:#666">📁 DNS Only: ${currentDNSData.unproxied}</span>
                </div>
            </div>`;
        Swal.fire({ title: 'DNS Summary', html: htmlContent, showCloseButton: true, showConfirmButton: false, width: 400 });
    });

});
</script>
