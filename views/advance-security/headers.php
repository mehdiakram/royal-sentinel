<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// Settings Variables
$header_csp   = isset($rs_settings['header_csp']) ? $rs_settings['header_csp'] : 0;
$header_xfo   = isset($rs_settings['header_xfo']) ? $rs_settings['header_xfo'] : 0;
$header_xcto  = isset($rs_settings['header_xcto']) ? $rs_settings['header_xcto'] : 0;
$header_xss   = isset($rs_settings['header_xss']) ? $rs_settings['header_xss'] : 0;
$header_rp    = isset($rs_settings['header_rp']) ? $rs_settings['header_rp'] : 0;
$header_pp    = isset($rs_settings['header_pp']) ? $rs_settings['header_pp'] : 0;
$header_coop  = isset($rs_settings['header_coop']) ? $rs_settings['header_coop'] : 0;
$header_corp  = isset($rs_settings['header_corp']) ? $rs_settings['header_corp'] : 0;
$header_xpcdp = isset($rs_settings['header_xpcdp']) ? $rs_settings['header_xpcdp'] : 0;
$header_hsts  = isset($rs_settings['header_hsts']) ? $rs_settings['header_hsts'] : 0;
$header_hide_powered_by = isset($rs_settings['header_hide_powered_by']) ? $rs_settings['header_hide_powered_by'] : 0;

// Advanced Settings
$csp_policy   = isset($rs_settings['csp_policy']) ? stripslashes($rs_settings['csp_policy']) : "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';";
$xfo_mode     = isset($rs_settings['xfo_mode']) ? $rs_settings['xfo_mode'] : 'SAMEORIGIN';
$rp_mode      = isset($rs_settings['rp_mode']) ? $rs_settings['rp_mode'] : 'strict-origin-when-cross-origin';
$coop_mode    = isset($rs_settings['coop_mode']) ? $rs_settings['coop_mode'] : 'same-origin';
$corp_mode    = isset($rs_settings['corp_mode']) ? $rs_settings['corp_mode'] : 'same-origin';
$hsts_max_age = isset($rs_settings['hsts_max_age']) ? $rs_settings['hsts_max_age'] : '31536000';
?>

<div class="rs-headers-wrapper">
    
    <div style="margin-bottom: 20px;">
        <h3 style="margin:0;">🔒 Security Headers Configuration</h3>
        <p class="description">HTTP security headers protect your site from various attacks like XSS, clickjacking, MIME sniffing, and version leakage.</p>
    </div>

    <div class="rs-section-box" style="border-left: 4px solid #3498db;">
        <h4 class="rs-section-title" style="color: #3498db;">Essential Headers</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_xcto" value="1" <?php checked(1, $header_xcto); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">X-Content-Type-Options</label>
                    <p class="description">Prevents browsers from MIME-sniffing. Adds: <code>X-Content-Type-Options: nosniff</code></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_xss" value="1" <?php checked(1, $header_xss); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">X-XSS-Protection</label>
                    <p class="description">Enables browser XSS filter. Adds: <code>X-XSS-Protection: 1; mode=block</code></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_xfo" id="rs_header_xfo" value="1" <?php checked(1, $header_xfo); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">X-Frame-Options</label>
                    <p class="description">Prevents clickjacking by controlling iframe embedding.</p>
                    
                    <div id="rs_xfo_options" style="margin-top: 10px; display: <?php echo $header_xfo ? 'block' : 'none'; ?>;">
                        <select name="xfo_mode" style="min-width: 200px;">
                            <option value="SAMEORIGIN" <?php selected('SAMEORIGIN', $xfo_mode); ?>>SAMEORIGIN (Allow from same domain)</option>
                            <option value="DENY" <?php selected('DENY', $xfo_mode); ?>>DENY (Block all iframes)</option>
                        </select>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #27ae60;">
        <h4 class="rs-section-title" style="color: #27ae60;">Advanced Headers</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_rp" id="rs_header_rp" value="1" <?php checked(1, $header_rp); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Referrer-Policy</label>
                    <p class="description">Controls how much referrer information is shared when navigating.</p>
                    
                    <div id="rs_rp_options" style="margin-top: 10px; display: <?php echo $header_rp ? 'block' : 'none'; ?>;">
                        <select name="rp_mode" style="min-width: 300px;">
                            <option value="strict-origin-when-cross-origin" <?php selected('strict-origin-when-cross-origin', $rp_mode); ?>>strict-origin-when-cross-origin (Recommended)</option>
                            <option value="no-referrer" <?php selected('no-referrer', $rp_mode); ?>>no-referrer (Maximum Privacy)</option>
                            <option value="same-origin" <?php selected('same-origin', $rp_mode); ?>>same-origin</option>
                            <option value="origin" <?php selected('origin', $rp_mode); ?>>origin</option>
                        </select>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_pp" value="1" <?php checked(1, $header_pp); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Permissions-Policy</label>
                    <p class="description">Restricts browser features like camera, microphone, and geolocation. Adds: <code>Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()</code></p>
                </td>
            </tr>

            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_coop" id="rs_header_coop" value="1" <?php checked(1, $header_coop); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Cross-Origin-Opener-Policy (COOP)</label>
                    <p class="description">Protects against cross-origin attacks (Spectre, XS-Leaks) by isolating browsing context.</p>
                    
                    <div id="rs_coop_options" style="margin-top: 10px; display: <?php echo $header_coop ? 'block' : 'none'; ?>;">
                        <select name="coop_mode" style="min-width: 320px;">
                            <option value="same-origin" <?php selected('same-origin', $coop_mode); ?>>same-origin (Recommended - Isolates from cross-origin)</option>
                            <option value="same-origin-allow-popups" <?php selected('same-origin-allow-popups', $coop_mode); ?>>same-origin-allow-popups (Allows OAuth popups)</option>
                            <option value="unsafe-none" <?php selected('unsafe-none', $coop_mode); ?>>unsafe-none</option>
                        </select>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_corp" id="rs_header_corp" value="1" <?php checked(1, $header_corp); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Cross-Origin-Resource-Policy (CORP)</label>
                    <p class="description">Protects your site's resources (images, fonts, scripts) from being loaded by other origins.</p>
                    
                    <div id="rs_corp_options" style="margin-top: 10px; display: <?php echo $header_corp ? 'block' : 'none'; ?>;">
                        <select name="corp_mode" style="min-width: 300px;">
                            <option value="same-origin" <?php selected('same-origin', $corp_mode); ?>>same-origin (Strict - Only your origin can load resources)</option>
                            <option value="same-site" <?php selected('same-site', $corp_mode); ?>>same-site (Allows subdomains)</option>
                            <option value="cross-origin" <?php selected('cross-origin', $corp_mode); ?>>cross-origin (Permissive - CDN / public assets)</option>
                        </select>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_xpcdp" value="1" <?php checked(1, $header_xpcdp); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">X-Permitted-Cross-Domain-Policies</label>
                    <p class="description">Prevents Adobe Flash and Acrobat from loading data from your domain. Adds: <code>X-Permitted-Cross-Domain-Policies: none</code></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_hsts" id="rs_header_hsts" value="1" <?php checked(1, $header_hsts); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Strict-Transport-Security (HSTS)</label>
                    <p class="description">Forces browsers to only use HTTPS. <strong style="color:#d63638;">⚠️ Only enable if you have SSL!</strong></p>
                    
                    <div id="rs_hsts_options" style="margin-top: 10px; display: <?php echo $header_hsts ? 'block' : 'none'; ?>;">
                        <label style="display: block; margin-bottom: 8px;">Max Age (seconds):</label>
                        <select name="hsts_max_age" style="min-width: 200px;">
                            <option value="86400" <?php selected('86400', $hsts_max_age); ?>>1 Day (Testing)</option>
                            <option value="2592000" <?php selected('2592000', $hsts_max_age); ?>>30 Days</option>
                            <option value="15768000" <?php selected('15768000', $hsts_max_age); ?>>6 Months</option>
                            <option value="31536000" <?php selected('31536000', $hsts_max_age); ?>>1 Year (Recommended)</option>
                        </select>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #c0392b;">
        <h4 class="rs-section-title" style="color: #c0392b;">Server & Technology Masking</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_hide_powered_by" id="rs_header_hide_powered_by" value="1" <?php checked(1, $header_hide_powered_by); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Hide Server & PHP Versions (Remove X-Powered-By / Server)</label>
                    <p class="description">Prevents leaking PHP and web server versions in response headers. Removes <code>X-Powered-By</code> in PHP runtime and sets <code>ServerSignature Off</code> &amp; <code>Header unset Server</code> in <code>.htaccess</code>.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #e67e22;">
        <h4 class="rs-section-title" style="color: #e67e22;">Content Security Policy (CSP)</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="header_csp" id="rs_header_csp" value="1" <?php checked(1, $header_csp); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Enable Content-Security-Policy</label>
                    <p class="description">Controls which resources can be loaded. <strong style="color:#d63638;">⚠️ May break some plugins if misconfigured!</strong></p>
                    
                    <div id="rs_csp_options" style="margin-top: 15px; display: <?php echo $header_csp ? 'block' : 'none'; ?>;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600;">CSP Policy Directives:</label>
                        <textarea name="csp_policy" rows="4" style="width: 100%; max-width: 600px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($csp_policy); ?></textarea>
                        <p class="description" style="margin-top: 5px;">
                            <a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP" target="_blank">Learn about CSP →</a>
                        </p>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #9b59b6; background: #f8f9fa;">
        <h4 class="rs-section-title" style="color: #9b59b6;">📊 Current Headers Status</h4>
        <p class="description" style="margin-bottom: 15px;">Preview of headers that will be sent based on your configuration.</p>
        
        <div style="background: #fff; border: 1px solid #ddd; padding: 15px; font-family: monospace; font-size: 12px; border-radius: 4px;">
            <?php if ($header_xcto): ?>
                <div style="color: #27ae60;">✓ X-Content-Type-Options: nosniff</div>
            <?php endif; ?>
            <?php if ($header_xss): ?>
                <div style="color: #27ae60;">✓ X-XSS-Protection: 1; mode=block</div>
            <?php endif; ?>
            <?php if ($header_xfo): ?>
                <div style="color: #27ae60;">✓ X-Frame-Options: <?php echo esc_html($xfo_mode); ?></div>
            <?php endif; ?>
            <?php if ($header_rp): ?>
                <div style="color: #27ae60;">✓ Referrer-Policy: <?php echo esc_html($rp_mode); ?></div>
            <?php endif; ?>
            <?php if ($header_pp): ?>
                <div style="color: #27ae60;">✓ Permissions-Policy: camera=(), microphone=(), geolocation=()</div>
            <?php endif; ?>
            <?php if ($header_coop): ?>
                <div style="color: #27ae60;">✓ Cross-Origin-Opener-Policy: <?php echo esc_html($coop_mode); ?></div>
            <?php endif; ?>
            <?php if ($header_corp): ?>
                <div style="color: #27ae60;">✓ Cross-Origin-Resource-Policy: <?php echo esc_html($corp_mode); ?></div>
            <?php endif; ?>
            <?php if ($header_xpcdp): ?>
                <div style="color: #27ae60;">✓ X-Permitted-Cross-Domain-Policies: none</div>
            <?php endif; ?>
            <?php if ($header_hsts): ?>
                <div style="color: #27ae60;">✓ Strict-Transport-Security: max-age=<?php echo esc_html($hsts_max_age); ?>; includeSubDomains; preload</div>
            <?php endif; ?>
            <?php if ($header_hide_powered_by): ?>
                <div style="color: #27ae60;">✓ Hide Server &amp; PHP Info: X-Powered-By &amp; Server version masked</div>
            <?php endif; ?>
            <?php if ($header_csp): ?>
                <div style="color: #27ae60;">✓ Content-Security-Policy: [Custom Policy]</div>
            <?php endif; ?>
            <?php if (!$header_xcto && !$header_xss && !$header_xfo && !$header_rp && !$header_pp && !$header_coop && !$header_corp && !$header_xpcdp && !$header_hsts && !$header_hide_powered_by && !$header_csp): ?>
                <div style="color: #888;">No security headers enabled yet.</div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 200px; }
</style>

<script>
jQuery(document).ready(function($) {
    function toggleOptions(checkboxId, optionsId) {
        $(checkboxId).on('change', function() {
            if($(this).is(':checked')) {
                $(optionsId).slideDown();
            } else {
                $(optionsId).slideUp();
            }
        });
    }
    
    toggleOptions('#rs_header_xfo', '#rs_xfo_options');
    toggleOptions('#rs_header_rp', '#rs_rp_options');
    toggleOptions('#rs_header_coop', '#rs_coop_options');
    toggleOptions('#rs_header_corp', '#rs_corp_options');
    toggleOptions('#rs_header_hsts', '#rs_hsts_options');
    toggleOptions('#rs_header_csp', '#rs_csp_options');
});
</script>
