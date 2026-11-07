<?php 
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="rs-tweaks-section">

    <div class="rs-section-box" style="border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">System Maintenance</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label>System Cache</label></th>
                <td>
                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="clear_all_system_cache" 
                            data-title="Clear All Caches?" 
                            style="border-color:#2271b1; color:#2271b1;">
                        <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span> Clear System Cache
                    </button>
                    <p class="description">Clears Transients, Object Cache, and flushes Rewrite Rules. Fixes display issues.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Permalinks</label></th>
                <td>
                    <button type="button" class="button button-secondary rs-action-btn" 
                            data-action="regenerate_permalinks" 
                            data-title="Regenerate Permalinks?" 
                            style="border-color:#e67e22; color:#e67e22;">
                        <span class="dashicons dashicons-admin-links" style="vertical-align:middle;"></span> Force Regenerate Permalinks
                    </button>
                    <p class="description">Rebuilds permalink structure. Useful if you are facing 404 errors on posts.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #c0392b;">
        <h4 class="rs-section-title" style="color: #c0392b;">Server Configuration</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label>.htaccess File</label></th>
                <td>
                    <button type="button" class="button button-link-delete rs-action-btn" 
                            data-action="reset_htaccess" 
                            data-title="Reset .htaccess File?" 
                            style="border:1px solid #d63638; color:#d63638;">
                        <span class="dashicons dashicons-warning" style="vertical-align:middle;"></span> Reset .htaccess to Default
                    </button>
                    <p class="description" style="color: #d63638;"><strong>Warning:</strong> This will overwrite your current .htaccess file with default WordPress rules.</p>
                </td>
            </tr>
        </table>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 220px; font-weight: 600; color: #3c434a; }
</style>

<script>
jQuery(document).ready(function($) {
    $('.rs-action-btn').click(function(e) {
        e.preventDefault();
        var btn = $(this);
        var action = btn.data('action');
        var title  = btn.data('title');
        var isDanger = (action === 'reset_htaccess');

        Swal.fire({
            title: title,
            text: isDanger ? "This will overwrite your current .htaccess file!" : "This process may take a moment.",
            icon: isDanger ? 'warning' : 'info',
            showCancelButton: true,
            confirmButtonColor: isDanger ? '#d33' : '#2271b1',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Proceed!'
        }).then((result) => {
            if (result.isConfirmed) {
                
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    showConfirmButton: false, 
                    didOpen: () => { Swal.showLoading(); }
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'rs_tweaks_action',
                        run_action: action,
                        _wpnonce: '<?php echo wp_create_nonce("rs_tweaks_nonce"); ?>'
                    },
                    success: function(resp) {
                        if(resp.success) {
                            Swal.fire({icon:'success', title:'Done!', text:resp.data, showConfirmButton:false, timer:1500}).then(() => location.reload());
                        } else {
                            Swal.fire('Error', resp.data || 'Unknown error occurred.', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Failed!', 'Server Error: ' + error, 'error');
                    }
                });
            }
        });
    });
});
</script>