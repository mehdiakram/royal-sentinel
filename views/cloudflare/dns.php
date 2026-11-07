<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// জোন ডেটা
$zones_data = function_exists('rs_get_cf_zones') ? rs_get_cf_zones() : ['items' => []];
$zones = $zones_data['items'];
$error_msg = $zones_data['error'] ?? '';

// বর্তমান ডোমেইন অটো ডিটেক্ট
$current_domain = strtolower(str_replace('www.', '', parse_url(get_site_url(), PHP_URL_HOST)));
$selected_zone_id = '';
if (!empty($zones)) {
    foreach($zones as $z) {
        if ($z['name'] === $current_domain) { $selected_zone_id = $z['id']; break; }
    }
}
?>

<div class="rs-cf-wrapper">

    <?php if ( !empty($error_msg) ): ?>
        <div class="notice notice-error inline"><p>⚠️ Cloudflare API Error: <?php echo esc_html($error_msg); ?></p></div>
    <?php elseif ( empty($zones) ): ?>
        <div class="notice notice-warning inline"><p>No domains found. Check settings.</p></div>
    <?php else: ?>
    
        <div class="rs-global-selector" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; align-items:center; gap:10px;">
                <label style="font-weight:600;"><?php _e( 'Select Domain:', 'royal-sentinel' ); ?></label>
                <select id="rs-dns-zone" style="width: 300px;">
                    <option value=""><?php _e( '-- Choose Domain --', 'royal-sentinel' ); ?></option>
                    <?php foreach($zones as $z): ?>
                        <option value="<?php echo esc_attr($z['id']); ?>" <?php selected($selected_zone_id, $z['id']); ?>>
                            <?php echo esc_html($z['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button id="rs-add-record-btn" class="button button-primary">
                <span class="dashicons dashicons-plus" style="vertical-align:middle;"></span> Add Record
            </button>
        </div>
        
        <div id="rs-dns-stats" style="margin: 0 0 20px 0; display:none;">
            <div class="rs-card-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:15px;">
                <div class="rs-stat-box"><?php _e( 'Total Records', 'royal-sentinel' ); ?>: <strong id="stat-total">0</strong></div>
                <div class="rs-stat-box"><?php _e( 'A Records', 'royal-sentinel' ); ?>: <strong id="stat-a">0</strong></div>
                <div class="rs-stat-box"><?php _e( 'CNAME', 'royal-sentinel' ); ?>: <strong id="stat-cname">0</strong></div>
                <div class="rs-stat-box" style="border-color:#F38020; color:#F38020;"><?php _e( 'Proxied', 'royal-sentinel' ); ?>: <strong id="stat-proxied">0</strong></div>
            </div>
        </div>

        <div id="rs-dns-form-wrap" style="display:none; background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px; border-left:4px solid #2271b1; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0;" id="dns-form-title">➕ Add New DNS Record</h3>
            
            <input type="hidden" id="dns-record-id" value=""> <div style="display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap;">
                
                <div style="flex:0 0 100px;">
                    <label>Type</label>
                    <select id="dns-type" style="width:100%">
                        <option value="A">A</option>
                        <option value="AAAA">AAAA</option>
                        <option value="CNAME">CNAME</option>
                        <option value="TXT">TXT</option>
                        <option value="MX">MX</option>
                    </select>
                </div>
                
                <div style="flex:1; min-width:150px;">
                    <label>Name</label>
                    <input type="text" id="dns-name" style="width:100%" placeholder="@ for root">
                </div>
                
                <div style="flex:2; min-width:200px;">
                    <label>Content</label>
                    <input type="text" id="dns-content" style="width:100%" placeholder="IP or Domain">
                </div>

                <div id="dns-priority-wrap" style="flex:0 0 80px; display:none;">
                    <label>Priority</label>
                    <input type="number" id="dns-priority" style="width:100%" value="10">
                </div>

                <div style="flex:0 0 80px;">
                    <label>TTL</label>
                    <select id="dns-ttl" style="width:100%">
                        <option value="1">Auto</option>
                        <option value="60">1 min</option>
                        <option value="300">5 min</option>
                        <option value="3600">1 hr</option>
                    </select>
                </div>

                <div style="flex:0 0 100px; text-align:center;">
                    <label>Proxy</label><br>
                    <input type="checkbox" id="dns-proxied" checked> <span style="font-size:12px; color:#F38020;">Proxied</span>
                </div>

                <div style="flex:0 0 150px; padding-top:22px;">
                    <button id="dns-save-btn" class="button button-primary">Save Record</button>
                    <button id="dns-cancel-btn" class="button button-link">Cancel</button>
                </div>
            </div>
        </div>

        <div class="rs-card" style="background:#fff; padding:0; border:1px solid #ccd0d4; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;"><?php _e( 'Type', 'royal-sentinel' ); ?></th>
                        <th style="width: 20%;"><?php _e( 'Name', 'royal-sentinel' ); ?></th>
                        <th><?php _e( 'Content', 'royal-sentinel' ); ?></th>
                        <th style="width: 80px; text-align:center;"><?php _e( 'Proxy', 'royal-sentinel' ); ?></th>
                        <th style="width: 80px;"><?php _e( 'TTL', 'royal-sentinel' ); ?></th>
                        <th style="width: 120px; text-align:right;"><?php _e( 'Action', 'royal-sentinel' ); ?></th>
                    </tr>
                </thead>
                <tbody id="rs-dns-body">
                    <tr>
                        <td colspan="6" style="text-align:center; padding:20px; color:#888;">
                            <?php _e( 'Select a domain to view DNS records.', 'royal-sentinel' ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Select2
    $('#rs-dns-zone').select2({ placeholder: "Select a domain", allowClear: true });
    
    var nonce = '<?php echo wp_create_nonce("rs_cf_tabs"); ?>';
    
    // Main Load Function
    function loadDNS(zid) {
        if(!zid) {
            $('#rs-dns-stats').slideUp();
            $('#rs-add-record-btn').prop('disabled', true);
            $('#rs-dns-body').html('<tr><td colspan="6" style="text-align:center; padding:20px;">Select a domain to view records.</td></tr>');
            return;
        }

        $('#rs-add-record-btn').prop('disabled', false);
        $('#rs-dns-body').html('<tr><td colspan="6" style="text-align:center; padding:20px;"><span class="dashicons dashicons-update spin"></span> Fetching DNS Records...</td></tr>');
        
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_dns_overview', zone_id: zid, nonce: nonce }, function(res) {
            if(res.success) {
                $('#rs-dns-stats').slideDown();
                $('#stat-total').text(res.data.stats.TOTAL);
                $('#stat-a').text(res.data.stats.A);
                $('#stat-cname').text(res.data.stats.CNAME);
                $('#stat-proxied').text(res.data.stats.PROXIED);

                var html = '';
                if(res.data.records.length === 0) {
                    html = '<tr><td colspan="6" style="text-align:center; padding:20px;">No DNS records found.</td></tr>';
                } else {
                    $.each(res.data.records, function(i, r) {
                        var isProxied = r.proxied;
                        var cloudIcon = isProxied ? 
                            '<span class="dashicons dashicons-cloud" style="color:#F38020;" title="Proxied"></span>' : 
                            '<span class="dashicons dashicons-cloud" style="color:#ccc;" title="DNS Only"></span>';
                        
                        // JSON Data for Edit
                        var recordData = encodeURIComponent(JSON.stringify(r));

                        html += '<tr>';
                        html += '<td><span style="background:#eee; padding:2px 6px; border-radius:3px; font-size:11px; font-weight:bold;">' + r.type + '</span></td>';
                        html += '<td><strong>' + r.name + '</strong></td>';
                        html += '<td style="word-break:break-all; font-family:monospace;">' + r.content + '</td>';
                        html += '<td style="text-align:center;">' + cloudIcon + '</td>';
                        html += '<td>' + (r.ttl == 1 ? 'Auto' : r.ttl + 's') + '</td>';
                        html += '<td style="text-align:right;">';
                        html += '<button class="button button-small rs-edit-dns" data-record="'+recordData+'">Edit</button> ';
                        html += '<button class="button button-small button-link-delete rs-delete-dns" data-id="'+r.id+'" style="color:#b32d2e;">Delete</button>';
                        html += '</td>';
                        html += '</tr>';
                    });
                }
                $('#rs-dns-body').html(html);
            } else {
                $('#rs-dns-body').html('<tr><td colspan="6" style="text-align:center; color:red; padding:20px;">⚠️ ' + (res.data || 'Error') + '</td></tr>');
            }
        });
    }

    // Trigger Load on Change
    $('#rs-dns-zone').change(function() { loadDNS($(this).val()); });

    // Auto Load Initial
    var initialZone = $('#rs-dns-zone').val();
    if(initialZone) { loadDNS(initialZone); }

    // --- UI INTERACTION ---
    
    // Open Form (Add Mode)
    $('#rs-add-record-btn').click(function() { 
        $('#rs-dns-form-wrap').slideDown(); 
        $('#dns-form-title').text('➕ Add New DNS Record');
        $('#dns-save-btn').text('Save Record');
        $('#dns-record-id').val(''); // Clear ID for Add Mode
        
        // Reset Inputs
        $('#dns-name').val(''); $('#dns-content').val('');
    });

    $('#dns-cancel-btn').click(function() { $('#rs-dns-form-wrap').slideUp(); });
    
    // MX Priority Show/Hide
    $('#dns-type').change(function() {
        if($(this).val() === 'MX') { 
            $('#dns-priority-wrap').show(); 
            $('#dns-proxied').prop('checked', false).prop('disabled', true);
        } else { 
            $('#dns-priority-wrap').hide();
            $('#dns-proxied').prop('disabled', false);
        }
    });

    // Edit Button Click
    $(document).on('click', '.rs-edit-dns', function(e) {
        e.preventDefault();
        var data = JSON.parse(decodeURIComponent($(this).data('record')));
        
        $('#rs-dns-form-wrap').slideDown();
        $('#dns-form-title').text('✏️ Edit Record: ' + data.name);
        $('#dns-save-btn').text('Update Record');
        $('#dns-record-id').val(data.id); // Set ID for Update Mode
        
        $('#dns-type').val(data.type).trigger('change');
        $('#dns-name').val(data.name);
        $('#dns-content').val(data.content);
        $('#dns-ttl').val(data.ttl);
        $('#dns-proxied').prop('checked', data.proxied);
        
        if(data.type === 'MX') $('#dns-priority').val(data.priority || 10);
        
        // Scroll to form
        $('html, body').animate({ scrollTop: $("#rs-dns-form-wrap").offset().top - 100 }, 500);
    });

    // Save/Update Record
    $('#dns-save-btn').click(function() {
        var zid = $('#rs-dns-zone').val();
        var rid = $('#dns-record-id').val();
        
        // Determine Action (Add or Update)
        var actionCmd = rid ? 'rs_cf_update_dns' : 'rs_cf_add_dns';

        var data = {
            action: actionCmd,
            zone_id: zid,
            record_id: rid, // Only needed for update
            nonce: nonce,
            type: $('#dns-type').val(),
            name: $('#dns-name').val(),
            content: $('#dns-content').val(),
            ttl: $('#dns-ttl').val(),
            proxied: $('#dns-proxied').is(':checked'),
            priority: $('#dns-priority').val()
        };

        if(!data.name || !data.content) { 
            Swal.fire('Required', 'Name and Content are required!', 'warning'); 
            return; 
        }

        $(this).prop('disabled', true).text('Processing...');
        
        $.post(ajaxurl, data, function(res) {
            $('#dns-save-btn').prop('disabled', false).text(rid ? 'Update Record' : 'Save Record');
            if(res.success) {
                $('#rs-dns-form-wrap').slideUp();
                $('#dns-name').val(''); $('#dns-content').val('');
                Swal.fire({ icon: 'success', title: 'Success', text: res.data, timer: 1500, showConfirmButton: false });
                loadDNS(zid); // Reload table
            } else {
                Swal.fire('Error', res.data, 'error');
            }
        });
    });

    // Delete Record (With Warning Popup)
    $(document).on('click', '.rs-delete-dns', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        var zid = $('#rs-dns-zone').val();
        
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this! This record will be permanently deleted from Cloudflare.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Deleting...', showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });
                
                $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_cf_delete_dns', zone_id: zid, record_id: id, nonce: nonce }, function(res) {
                    if(res.success) {
                        Swal.fire({
                            title: 'Deleted!',
                            text: 'Your DNS record has been deleted.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        loadDNS(zid);
                    } else {
                        Swal.fire('Error', res.data, 'error');
                    }
                });
            }
        });
    });

});
</script>

<style>
    .rs-stat-box { background: #fff; padding: 15px; border: 1px solid #ccd0d4; text-align: center; border-radius: 4px; font-size: 14px; color: #555; }
    .rs-stat-box strong { display: block; font-size: 24px; margin-top: 5px; color: #2271b1; }
    .spin { animation: spin 2s infinite linear; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .select2-container .select2-selection--single { height: 35px !important; border-color: #8c8f94 !important; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 33px !important; }
</style>
