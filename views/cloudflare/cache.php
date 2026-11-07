<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// জোন লিস্ট ফেচ করা
$zones = function_exists('rs_get_cf_zones') ? rs_get_cf_zones()['items'] : [];
?>

<div class="rs-cf-wrapper">
    
    <div class="rs-global-selector" style="background:#fff; padding:20px; border:1px solid #ccd0d4; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
        <label style="font-weight:600;"><?php _e( 'Select Domain:', 'royal-sentinel' ); ?></label>
        
        <select id="rs-cache-zone" style="width: 300px;">
            <option value=""><?php _e( '-- Choose Domain --', 'royal-sentinel' ); ?></option>
            <?php foreach($zones as $z): ?>
                <option value="<?php echo esc_attr($z['id']); ?>"><?php echo esc_html($z['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="rs-card" style="padding:20px; background:#fff; border:1px solid #ccd0d4; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
        
        <h3><?php _e( 'Purge Cache', 'royal-sentinel' ); ?></h3>
        <p class="description" style="margin-bottom:15px;">
            <?php _e( 'Clear cached files to force Cloudflare to fetch a fresh version from your server.', 'royal-sentinel' ); ?>
        </p>
        
        <button id="btn-purge-all" class="button button-primary button-large" style="margin-bottom: 20px;">
            <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> 
            <?php _e( 'Purge Everything', 'royal-sentinel' ); ?>
        </button>
        
        <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">
        
        <h4><?php _e( 'Purge Specific URLs', 'royal-sentinel' ); ?></h4>
        <textarea id="purge-urls" class="large-text" rows="3" placeholder="https://example.com/style.css (One per line)"></textarea>
        <br><br>
        
        <button id="btn-purge-custom" class="button button-secondary">
            <span class="dashicons dashicons-image-rotate" style="vertical-align:middle;"></span> 
            <?php _e( 'Purge URLs', 'royal-sentinel' ); ?>
        </button>

    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // 1. Initialize Select2
    $('#rs-cache-zone').select2({
        placeholder: "<?php _e( 'Select a domain', 'royal-sentinel' ); ?>",
        allowClear: true
    });

    var nonce = '<?php echo wp_create_nonce("rs_cf_tabs"); ?>';
    
    // Purge Helper Function
    function purge(type, urls) {
        var zid = $('#rs-cache-zone').val();
        
        if(!zid) {
            Swal.fire({
                title: "<?php _e( 'Error', 'royal-sentinel' ); ?>", 
                text: "<?php _e( 'Please select a domain first.', 'royal-sentinel' ); ?>", 
                icon: 'warning'
            });
            return;
        }
        
        // লোডিং
        Swal.fire({
            title: "<?php _e( 'Processing...', 'royal-sentinel' ); ?>",
            didOpen: () => { Swal.showLoading(); }
        });
        
        // AJAX Request
        $.post(ajaxurl, { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>',
            action: 'rs_cf_cache_action', 
            zone_id: zid, 
            type: type, 
            urls: urls, 
            nonce: nonce
        }, function(res) {
            
            // 2. Updated Popup Logic (Auto Close 1.5s)
            Swal.fire({
                icon: res.success ? 'success' : 'error',
                title: res.success ? "<?php _e( 'Success', 'royal-sentinel' ); ?>" : "<?php _e( 'Error', 'royal-sentinel' ); ?>",
                text: res.data,
                showConfirmButton: false, // বাটন হাইড
                timer: 1500 // ১.৫ সেকেন্ড পর অটো ক্লোজ
            });

        }).fail(function() {
            Swal.fire({
                icon: 'error',
                title: "<?php _e( 'Error', 'royal-sentinel' ); ?>",
                text: "<?php _e( 'Server connection failed.', 'royal-sentinel' ); ?>"
            });
        });
    }

    // Button Actions
    $('#btn-purge-all').click(() => purge('purge_all'));
    $('#btn-purge-custom').click(() => purge('purge_urls', $('#purge-urls').val()));
});
</script>

<style>
    /* Select2 Fixes for WP Admin */
    .select2-container .select2-selection--single { height: 35px !important; border-color: #8c8f94 !important; display: flex; align-items: center; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 33px !important; }
</style>
