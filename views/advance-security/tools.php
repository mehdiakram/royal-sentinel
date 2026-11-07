<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$rs_tools_nonce = wp_create_nonce('rs_tools_nonce');
$all_users = get_users(['role__in' => ['administrator', 'editor'], 'number' => 50]);
?>

<div class="rs-section-box" style="border-left:4px solid #9b59b6;">
    <h4 class="rs-section-title" style="color:#9b59b6;">✨ Magic Login Link Generator</h4>
    <p class="description">Generate a temporary, one-use admin login link. Perfect for giving secure temporary access to a developer or trusted person — without sharing your password.</p>

    <div style="display:flex; gap:15px; flex-wrap:wrap; margin-top:20px; align-items:flex-end;">
        <div>
            <label style="display:block; font-weight:600; margin-bottom:6px;">Login as User</label>
            <select id="rs_magic_user" style="min-width:200px; height:35px;">
                <?php foreach ($all_users as $u): ?>
                    <option value="<?php echo $u->ID; ?>"><?php echo esc_html($u->user_login); ?> (<?php echo esc_html(implode(', ', $u->roles)); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block; font-weight:600; margin-bottom:6px;">Link Valid For</label>
            <select id="rs_magic_duration" style="min-width:160px; height:35px;">
                <option value="3600">1 Hour</option>
                <option value="21600">6 Hours</option>
                <option value="86400" selected>24 Hours</option>
                <option value="604800">7 Days</option>
            </select>
        </div>
        <div>
            <button id="rs_generate_magic" class="button button-primary" style="height:35px; line-height:33px;">
                ✨ Generate Link
            </button>
        </div>
    </div>

    <div id="rs_magic_result" style="display:none; margin-top:20px;">
        <div style="background:#1e1e2e; padding:20px; border-radius:8px; font-family:monospace;">
            <div style="color:#94a3b8; font-size:12px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                <span>🔗 Your Magic Login URL</span>
                <span id="rs_magic_expiry" style="background:#2d3548; color:#a6e22e; padding:2px 10px; border-radius:10px; font-size:11px;"></span>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <input id="rs_magic_link_input" type="text" readonly
                    style="flex:1; background:#2d3548; border:1px solid #3d4a63; color:#a6e22e; padding:10px 12px; border-radius:5px; font-size:12px; outline:none;">
                <button onclick="navigator.clipboard.writeText(document.getElementById('rs_magic_link_input').value).then(function(){this.textContent='✅ Copied!'; setTimeout(function(){document.querySelector('[onclick*=magic_link]').textContent='📋 Copy';},2000)}.bind(this))"
                    class="button" style="flex-shrink:0; height:38px;">
                    📋 Copy
                </button>
            </div>
            <p style="color:#e74c3c; font-size:12px; margin:12px 0 0; border-top:1px solid #2d3548; padding-top:10px;">
                ⚠️ <strong>Important:</strong> This link is single-use and will self-destruct after first login. Share it securely (e.g. via encrypted message, not plain email).
            </p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#rs_generate_magic').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Generating...');

        $.post(ajaxurl, {
            action:   'rs_generate_magic_link',
            nonce:    '<?php echo $rs_tools_nonce; ?>',
            user_id:  $('#rs_magic_user').val(),
            duration: $('#rs_magic_duration').val()
        }, function(res) {
            btn.prop('disabled', false).text('✨ Generate Link');
            if (res.success) {
                $('#rs_magic_link_input').val(res.data.link);
                $('#rs_magic_expiry').text('Expires: ' + res.data.expires);
                $('#rs_magic_result').slideDown();
            } else {
                Swal.fire('Error', 'Could not generate magic link.', 'error');
            }
        }).fail(function() {
            btn.prop('disabled', false).text('✨ Generate Link');
            Swal.fire('Error', 'Server error. Please try again.', 'error');
        });
    });
});
</script>
