<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// Settings
$txt_enabled       = isset($rs_settings['securitytxt_enable']) ? $rs_settings['securitytxt_enable'] : 0;
$txt_credit        = isset($rs_settings['securitytxt_credit']) ? $rs_settings['securitytxt_credit'] : 1; // Default true if not set
$txt_contact       = isset($rs_settings['securitytxt_contact']) ? $rs_settings['securitytxt_contact'] : 'mailto:' . get_option('admin_email');
$txt_encryption    = isset($rs_settings['securitytxt_encryption']) ? $rs_settings['securitytxt_encryption'] : '';
$txt_policy        = isset($rs_settings['securitytxt_policy']) ? $rs_settings['securitytxt_policy'] : '';
$txt_acks          = isset($rs_settings['securitytxt_acknowledgments']) ? $rs_settings['securitytxt_acknowledgments'] : '';
$txt_hiring        = isset($rs_settings['securitytxt_hiring']) ? $rs_settings['securitytxt_hiring'] : '';
$txt_langs         = isset($rs_settings['securitytxt_langs']) ? $rs_settings['securitytxt_langs'] : 'en';
$txt_expiry        = isset($rs_settings['securitytxt_expiry']) ? $rs_settings['securitytxt_expiry'] : '1y';

$domainUrl = get_site_url();
$securityTxtUrl = $domainUrl . '/.well-known/security.txt';
?>

<div class="rs-securitytxt-wrapper">
    
    <div style="margin-bottom: 20px;">
        <h3 style="margin:0;">📄 Security.txt Manager</h3>
        <p class="description">Define security policies and contact info for security researchers in accordance with RFC 9116.</p>
    </div>

    <!-- MAIN SETTINGS BOX -->
    <div class="rs-section-box" style="border-left: 4px solid #27ae60;">
        <h4 class="rs-section-title" style="color: #27ae60;">⚙️ File Configuration</h4>
        
        <div style="margin-bottom: 20px; display:flex; gap: 40px;">
            <div>
                <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                    <input type="checkbox" name="securitytxt_enable" id="rs_securitytxt_enable" value="1" <?php checked(1, $txt_enabled); ?>>
                    <span class="rs-slider round"></span>
                </label>
                <label for="rs_securitytxt_enable" style="font-weight: 600; font-size: 14px;">Enable Security.txt</label>
            </div>
            
            <div>
                <label class="rs-switch" style="vertical-align: middle; margin-right: 10px;">
                    <input type="checkbox" name="securitytxt_credit" id="rs_securitytxt_credit" value="1" <?php checked(1, $txt_credit); ?>>
                    <span class="rs-slider round"></span>
                </label>
                <label for="rs_securitytxt_credit" style="font-weight: 600; font-size: 14px;">Show Plugin Credit</label>
                <span style="font-size: 12px; color: #666; margin-left:10px;">(Appends "Secured by Royal Sentinel")</span>
            </div>
        </div>

        <div id="rs_securitytxt_settings" style="display: <?php echo $txt_enabled ? 'flex' : 'none'; ?>; flex-wrap: wrap; gap: 30px;">
            
            <!-- Left Side: Form -->
            <div style="flex: 1; min-width: 350px;">
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th scope="row"><label for="rs_contact">Contact (Required)</label></th>
                        <td>
                            <input type="text" id="rs_contact" name="securitytxt_contact" value="<?php echo esc_attr($txt_contact); ?>" class="regular-text rs-txt-live" placeholder="mailto:security@example.com">
                            <p class="description">Use <code>mailto:</code> for emails, or standard <code>https://</code> for URLs.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rs_expiry">Expiration</label></th>
                        <td>
                            <select id="rs_expiry" name="securitytxt_expiry" class="rs-txt-live">
                                <option value="6m" <?php selected('6m', $txt_expiry); ?>>6 Months</option>
                                <option value="1y" <?php selected('1y', $txt_expiry); ?>>1 Year (Recommended)</option>
                                <option value="never" <?php selected('never', $txt_expiry); ?>>Never Expire</option>
                            </select>
                            <p class="description">How long this file stays valid.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rs_encryption">Encryption Key URL</label></th>
                        <td>
                            <input type="url" id="rs_encryption" name="securitytxt_encryption" value="<?php echo esc_attr($txt_encryption); ?>" class="regular-text rs-txt-live" placeholder="https://example.com/pgp-key.txt">
                            <p class="description">Link to your public PGP key for secure reporting.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rs_policy">Disclosure Policy URL</label></th>
                        <td>
                            <input type="url" id="rs_policy" name="securitytxt_policy" value="<?php echo esc_attr($txt_policy); ?>" class="regular-text rs-txt-live" placeholder="https://example.com/bug-bounty">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rs_acks">Acknowledgments URL</label></th>
                        <td>
                            <input type="url" id="rs_acks" name="securitytxt_acknowledgments" value="<?php echo esc_attr($txt_acks); ?>" class="regular-text rs-txt-live" placeholder="https://example.com/hall-of-fame">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rs_hiring">Hiring URL</label></th>
                        <td>
                            <input type="url" id="rs_hiring" name="securitytxt_hiring" value="<?php echo esc_attr($txt_hiring); ?>" class="regular-text rs-txt-live" placeholder="https://example.com/jobs">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rs_langs">Preferred Languages</label></th>
                        <td>
                            <input type="text" id="rs_langs" name="securitytxt_langs" value="<?php echo esc_attr($txt_langs); ?>" class="regular-text rs-txt-live" placeholder="en, bn">
                            <p class="description">Comma separated ISO language codes.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Right Side: Live Preview -->
            <div style="flex: 1; min-width: 350px;">
                <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 13px; line-height: 1.5; box-shadow: inset 0 0 10px rgba(0,0,0,0.5);">
                    <div style="margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid #444; color: #888; font-weight: bold; display:flex; justify-content:space-between;">
                        <span>Live Preview</span>
                        <span style="font-size:11px;">/.well-known/security.txt</span>
                    </div>
                    <pre id="rs_securitytxt_preview" style="margin:0; white-space: pre-wrap; word-wrap: break-word; color:#a6e22e;"></pre>
                </div>
                
                <div style="margin-top: 20px; padding: 15px; background: #e8f4f8; border: 1px solid #b8daff; border-radius: 5px;">
                    <h4 style="margin: 0 0 10px 0; color: #004085;">✅ Validation Tools</h4>
                    <p style="margin:0 0 15px 0; font-size: 13px; color: #333;">Check if your domain's file is accessible and publicly compliant.</p>
                    <div style="display: flex; gap: 10px;">
                        <a href="<?php echo esc_url($securityTxtUrl); ?>" target="_blank" class="button button-secondary">View File</a>
                        <a href="https://securitytxt.org/" target="_blank" class="button button-primary">Verify on securitytxt.org <span class="dashicons dashicons-external" style="vertical-align: middle; font-size: 14px; margin-top:2px;"></span></a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 180px; }
</style>

<script>
jQuery(document).ready(function($) {
    // Generate ISO date for preview
    function getFutureDate(period) {
        var date = new Date();
        if (period === '6m') {
            date.setMonth(date.getMonth() + 6);
        } else if (period === '1y') {
            date.setFullYear(date.getFullYear() + 1);
        } else {
            return '';
        }
        return date.toISOString().split('.')[0] + 'Z';
    }

    // Live Preview Updater
    function updatePreview() {
        var pre = '';
        var contact = $('#rs_contact').val().trim();
        var expiry = $('#rs_expiry').val();
        var enc = $('#rs_encryption').val().trim();
        var ack = $('#rs_acks').val().trim();
        var pol = $('#rs_policy').val().trim();
        var hir = $('#rs_hiring').val().trim();
        var lang = $('#rs_langs').val().trim();
        var doCredit = $('#rs_securitytxt_credit').is(':checked');

        if(contact) pre += "Contact: " + contact + "\n";
        
        var dateStr = getFutureDate(expiry);
        if(dateStr) pre += "Expires: " + dateStr + "\n";

        if(enc) pre += "Encryption: " + enc + "\n";
        if(ack) pre += "Acknowledgments: " + ack + "\n";
        if(pol) pre += "Policy: " + pol + "\n";
        if(hir) pre += "Hiring: " + hir + "\n";
        if(lang) pre += "Preferred-Languages: " + lang + "\n";
        
        pre += "Canonical: <?php echo esc_js($securityTxtUrl); ?>\n";

        if (doCredit) {
            pre += "\n# Secured by Royal Sentinel | Developed by Royal Technologies\n";
        }

        if(!contact) {
            pre = "# Need to add a Contact first!\n" + pre;
        }

        $('#rs_securitytxt_preview').text(pre);
    }

    // Bind events
    $('.rs-txt-live').on('input change', updatePreview);
    $('#rs_securitytxt_credit').on('change', updatePreview);
    
    // Initial load
    updatePreview();

    // Toggle settings section
    $('#rs_securitytxt_enable').on('change', function() {
        if($(this).is(':checked')) {
            $('#rs_securitytxt_settings').slideDown({
                start: function () {
                    $(this).css({ display: "flex" })
                }
            });
        } else {
            $('#rs_securitytxt_settings').slideUp();
        }
    });
});
</script>
