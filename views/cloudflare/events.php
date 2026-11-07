<?php 
if ( ! defined( 'ABSPATH' ) ) exit; 

// জোন লিস্ট আনা
$zones = function_exists('rs_get_cf_zones') ? rs_get_cf_zones()['items'] : [];

// বর্তমান ডোমেইন অটো ডিটেক্ট (www. ছাড়া)
$current_domain = strtolower(parse_url(get_site_url(), PHP_URL_HOST));
$current_domain = str_replace('www.', '', $current_domain);
?>

<div class="rs-cf-wrapper">
    
    <div class="rs-global-selector" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
        <label style="font-weight:600;"><?php _e( 'Select Domain:', 'royal-sentinel' ); ?></label>
        
        <select id="rs-event-zone" style="width: 300px;">
            <option value=""><?php _e( '-- Choose Domain --', 'royal-sentinel' ); ?></option>
            <?php foreach($zones as $z): ?>
                <option value="<?php echo esc_attr($z['id']); ?>" <?php selected($z['name'], $current_domain); ?>>
                    <?php echo esc_html($z['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="rs-card" style="background:#fff; padding:0; border:1px solid #ccd0d4;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 140px;"><?php _e( 'Action', 'royal-sentinel' ); ?></th>
                    <th style="width: 250px;"><?php _e( 'IP Address', 'royal-sentinel' ); ?></th>
                    <th><?php _e( 'Country', 'royal-sentinel' ); ?></th>
                    <th><?php _e( 'Path', 'royal-sentinel' ); ?></th>
                    <th><?php _e( 'Source', 'royal-sentinel' ); ?></th>
                    <th style="width: 180px;"><?php _e( 'Date', 'royal-sentinel' ); ?></th>
                </tr>
            </thead>
            <tbody id="rs-event-body">
                <tr>
                    <td colspan="6" style="text-align:center; color:#888; padding:20px;">
                        <?php _e( 'Select a domain to load events.', 'royal-sentinel' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<script>
jQuery(document).ready(function($) {
    
    // 1. Initialize Select2
    $('#rs-event-zone').select2({
        placeholder: "<?php _e( 'Select a domain', 'royal-sentinel' ); ?>",
        allowClear: true
    });

    // 2. Load Events Function (Re-usable)
    function loadEvents(zid) {
        if(!zid) {
            $('#rs-event-body').html('<tr><td colspan="6" style="text-align:center; color:#888; padding:20px;"><?php _e( 'Select a domain to load events.', 'royal-sentinel' ); ?></td></tr>');
            return;
        }

        // Loading State
        $('#rs-event-body').html('<tr><td colspan="6" style="text-align:center; padding:20px;"><span class="dashicons dashicons-update spin"></span> <?php _e( 'Loading events from Cloudflare...', 'royal-sentinel' ); ?></td></tr>');
        
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>',
            action: 'rs_cf_load_events', 
            zone_id: zid, 
            nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>'
        }, function(res) {
            
            if(res.success) {
                var html = '';
                
                if (res.data.length === 0) {
                    html = '<tr><td colspan="6" style="text-align:center; padding:20px;"><?php _e( 'No recent security events found.', 'royal-sentinel' ); ?></td></tr>';
                } else {
                    $.each(res.data, function(i, e) {
                        // Action Color Logic
                        var actionStyle = 'background:#eee; color:#333;';
                        if(e.action === 'block') actionStyle = 'background:#ffe6e6; color:#d63638; border:1px solid #d63638;';
                        if(e.action === 'challenge' || e.action === 'managed_challenge') actionStyle = 'background:#fff4e5; color:#F38020; border:1px solid #F38020;';

                        // Date Formatting
                        var date = new Date(e.datetime).toLocaleString();

                        html += '<tr>';
                        html += '<td><span style="font-size:11px; font-weight:bold; padding:2px 6px; border-radius:3px; text-transform:uppercase; ' + actionStyle + '">' + e.action + '</span></td>';
                        
                        // --- IP Address & Block Button ---
                        html += '<td><strong style="margin-right:10px;">' + e.clientIP + '</strong>';
                        if (e.is_blocked === true) {
                            html += '<span style="color:#d63638; font-weight:bold; border:1px solid #d63638; padding:2px 8px; border-radius:3px; background:#ffe6e6; font-size:12px;">🚫 Blocked</span>';
                        } else {
                            html += '<button class="button button-small rs-block-ip-btn" data-ip="' + e.clientIP + '" style="color:#d63638; border-color:#d63638;">🚫 Block</button>';
                        }
                        html += '</td>';
                        
                        html += '<td>' + e.clientCountryName + '</td>';
                        html += '<td style="word-break:break-all;">' + e.clientRequestPath + '</td>';
                        html += '<td>' + e.source + '</td>';
                        html += '<td style="font-size:12px; color:#666;">' + date + '</td>';
                        html += '</tr>';
                    });
                }
                $('#rs-event-body').html(html);

            } else {
                var errorMsg = res.data ? res.data : "<?php _e( 'Unknown error occurred.', 'royal-sentinel' ); ?>";
                $('#rs-event-body').html('<tr><td colspan="6" style="text-align:center; color:red; padding:20px;">⚠️ ' + errorMsg + '</td></tr>');
            }
        }).fail(function() {
            $('#rs-event-body').html('<tr><td colspan="6" style="text-align:center; color:red; padding:20px;">⚠️ <?php _e( 'Server connection failed.', 'royal-sentinel' ); ?></td></tr>');
        });
    }

    // 3. Trigger Load on Change
    $('#rs-event-zone').on('change', function() {
        loadEvents($(this).val());
    });

    // 4. [NEW] Initial Auto Load
    // যদি পেজ লোড হওয়ার সময় কোনো ডোমেইন সিলেক্টেড থাকে (অটো সিলেক্টের কারণে), তবে ইভেন্ট লোড করো।
    var initialZone = $('#rs-event-zone').val();
    if (initialZone) {
        loadEvents(initialZone);
    }

    // 5. Block IP Handler
    $(document).on('click', '.rs-block-ip-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var ip = btn.data('ip');
        
        Swal.fire({
            title: 'Block IP?',
            text: "Are you sure you want to block " + ip + "?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d63638',
            confirmButtonText: 'Yes, Block it!'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).text('Blocking...');
                
                $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>',
                    action: 'rs_cf_block_event_ip',
                    ip: ip,
                    nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>'
                }, function(res) {
                    if(res.success) {
                        Swal.fire('Blocked!', res.data, 'success');
                        btn.replaceWith('<span style="color:#d63638; font-weight:bold; border:1px solid #d63638; padding:2px 8px; border-radius:3px; background:#ffe6e6; font-size:12px;">🚫 Blocked</span>');
                    } else {
                        Swal.fire('Error', res.data, 'error');
                        btn.prop('disabled', false).text('🚫 Block');
                    }
                }).fail(function() {
                    Swal.fire('Error', 'Server Request Failed', 'error');
                    btn.prop('disabled', false).text('🚫 Block');
                });
            }
        });
    });

});
</script>

<style>
    .spin { animation: spin 2s infinite linear; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .select2-container .select2-selection--single { height: 35px !important; border-color: #8c8f94 !important; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 33px !important; }
</style>
