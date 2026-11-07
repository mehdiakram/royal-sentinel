<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

// সেটিংস ভ্যারিয়েবল
$disable_emojis  = isset($rs_tweaks['disable_emojis']) ? $rs_tweaks['disable_emojis'] : 0;
$disable_embeds  = isset($rs_tweaks['disable_embeds']) ? $rs_tweaks['disable_embeds'] : 0;
$disable_oembed  = isset($rs_tweaks['disable_oembed_discovery']) ? $rs_tweaks['disable_oembed_discovery'] : 0;
$disable_rest    = isset($rs_tweaks['disable_rest_api_public']) ? $rs_tweaks['disable_rest_api_public'] : 0;
$disable_rss     = isset($rs_tweaks['disable_rss']) ? $rs_tweaks['disable_rss'] : 0;
$heartbeat_freq  = isset($rs_tweaks['heartbeat_freq']) ? $rs_tweaks['heartbeat_freq'] : 'default';
?>

<div class="rs-tweaks-section">

    <div class="rs-section-box" style="border-left: 4px solid #8e44ad;">
        <h4 class="rs-section-title" style="color: #8e44ad;">Frontend Optimization</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label>Emojis</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_emojis]" value="1" <?php checked(1, $disable_emojis); ?>> 
                        Disable Emojis
                    </label>
                    <p class="description">Removes WordPress emoji scripts and styles to reduce HTTP requests.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>Embeds</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_embeds]" value="1" <?php checked(1, $disable_embeds); ?>> 
                        Disable Embeds
                    </label>
                    <p class="description">Prevents others from embedding your site and disables embedding other sites.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>oEmbed Discovery</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_oembed_discovery]" value="1" <?php checked(1, $disable_oembed); ?>> 
                        Disable oEmbed Discovery
                    </label>
                    <p class="description">Removes the oEmbed discovery links from the head section.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #2980b9;">
        <h4 class="rs-section-title" style="color: #2980b9;">API & Feeds</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>REST API</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_rest_api_public]" value="1" <?php checked(1, $disable_rest); ?>> 
                        Disable REST API for Non-Logged Users
                    </label>
                    <p class="description">Restricts REST API access to authenticated users only. Blocks bot scans.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label>RSS Feeds</label></th>
                <td>
                    <label class="rs-checkbox-label">
                        <input type="checkbox" name="rs_tweaks[disable_rss]" value="1" <?php checked(1, $disable_rss); ?>> 
                        Disable RSS Feeds
                    </label>
                    <p class="description">Disables generating RSS, Atom, and RDF feeds for the site.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 20px; border-left: 4px solid #c0392b;">
        <h4 class="rs-section-title" style="color: #c0392b;">Heartbeat Control</h4>
        <table class="form-table">
            <tr>
                <th scope="row"><label>Frequency</label></th>
                <td>
                    <select name="rs_tweaks[heartbeat_freq]" class="regular-text">
                        <option value="default" <?php selected('default', $heartbeat_freq); ?>>WordPress Default</option>
                        <option value="60" <?php selected('60', $heartbeat_freq); ?>>Every 60 Seconds (Recommended)</option>
                        <option value="120" <?php selected('120', $heartbeat_freq); ?>>Every 120 Seconds</option>
                        <option value="disable" <?php selected('disable', $heartbeat_freq); ?>>Disable Heartbeat Completely</option>
                    </select>
                    <p class="description">The Heartbeat API runs frequent server calls. Limiting it reduces CPU usage.</p>
                </td>
            </tr>
        </table>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; width: 220px; font-weight: 600; color: #3c434a; }
    .rs-checkbox-label { font-weight: 500; color: #333; }
</style>