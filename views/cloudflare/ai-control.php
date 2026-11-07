<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Get available zones
$zones_data = function_exists( 'rs_get_cf_zones' ) ? rs_get_cf_zones()['items'] : [];
$default_zid = !empty($zones_data) ? $zones_data[0]['id'] : '';

// Get Bot List
$bot_list = function_exists('rs_get_supported_ai_bots') ? rs_get_supported_ai_bots() : [];
?>

    <style>
        .rs-switch { position: relative; display: inline-block; width: 44px; height: 24px; vertical-align: middle; }
        .rs-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #d63638; }
        input:focus + .slider { box-shadow: 0 0 1px #d63638; }
        input:checked + .slider:before { transform: translateX(20px); }
        
        .rs-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 500; display: inline-block; }
        .rs-badge.ai-crawler { background: #f0f0f1; color: #50575e; border: 1px solid #ccd0d4; }
        .rs-badge.search-crawler { background: #e7f5ed; color: #00a32a; border: 1px solid #c3e6cb; }
        
        .rs-sparkline { height: 25px; width: 100px; display: inline-block; vertical-align: middle; }
        .rs-action-group { display: flex; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden; height: 32px; align-items: stretch; }
        .rs-action-btn { border: none; background: #fff; padding: 0 12px; font-size: 12px; cursor: pointer; color: #666; font-weight: 500; transition: all 0.2s; }
        .rs-action-btn:hover { background: #f9f9f9; }
        .rs-action-btn.active.allow { background: #e7f5ed; color: #00a32a; font-weight: 600; }
        .rs-action-btn.active.block { background: #fcf0f1; color: #d63638; font-weight: 600; }
        .rs-action-btn:not(:last-child) { border-right: 1px solid #ccd0d4; }
        
        .rs-crawler-name { display: block; font-weight: 600; color: #333; font-size: 14px; }
        .rs-crawler-entity { display: block; font-size: 12px; color: #666; }
        
        .rs-stat-val { font-size: 12px; font-family: monospace; }
        .rs-stat-allowed { color: #00a32a; }
        .rs-stat-unsuccessful { color: #d63638; }
        
        .rs-control-bar { background: #fff; padding: 12px 15px; border: 1px solid #ccd0d4; border-radius: 4px; display: flex; gap: 10px; align-items: center; margin-bottom: 0; border-bottom: none; border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
        .rs-control-item { display: flex; align-items: center; gap: 8px; border: 1px solid #ccd0d4; padding: 5px 12px; border-radius: 4px; background: #fff; font-size: 13px; color: #333; }
        .rs-control-item select { border: none; padding: 0; background: transparent; font-size: 13px; outline: none; box-shadow: none; height: auto; line-height: 1; }
        
        .spin { animation: spin 2s infinite linear; } 
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>

    <!-- Controls Bar -->
    <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; display:flex; justify-content:space-between; align-items:center; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.04); margin-bottom: 25px;">
        <div style="display:flex; align-items:center; gap:15px;">
            <label style="font-size: 14px; font-weight: 600; color: #333;"><?php _e('Cloudflare Zone:', 'royal-sentinel'); ?></label>
            <select id="rs-ai-zone-selector" style="width:300px;">
                <?php foreach($zones_data as $z): ?>
                    <option value="<?php echo esc_attr($z['id']); ?>"><?php echo esc_html($z['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button id="rs-save-ai-rules" class="button button-primary button-large" style="padding: 0 25px; height: 40px; font-weight: 600;" disabled>
                💾 <?php _e('Save Changes', 'royal-sentinel'); ?>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div>
        <!-- 1. Unified Table Section (Crawlers & Bots) -->
        <div style="margin-bottom:40px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
               <h3 style="margin:0; font-size: 20px; font-weight: 600;"><?php _e('Crawlers', 'royal-sentinel'); ?></h3>
            </div>

            <!-- Table Filters Bar -->
            <div class="rs-control-bar">
                <div class="rs-control-item">
                    <select style="color: #999;"><option><?php _e('Select crawler', 'royal-sentinel'); ?></option></select>
                </div>
                <div class="rs-control-item">
                    <select style="color: #999;"><option><?php _e('Select operator', 'royal-sentinel'); ?></option></select>
                </div>
                <button class="button" style="height: 32px; display: flex; align-items: center; gap: 5px; color: #333;">
                    <span class="dashicons dashicons-plus" style="font-size: 14px; width: 14px; height: 14px; margin-top: 2px;"></span>
                    <?php _e('Add filter', 'royal-sentinel'); ?>
                </button>
                <div style="margin-left: auto; display: flex; align-items: center; gap: 15px;">
                    <div class="rs-control-item" style="background: #f9f9f9; padding: 4px 10px; border-color: #ddd;">
                        <span style="color: #666; font-size: 12px;"><?php _e('Last 24 hours (GMT+6)', 'royal-sentinel'); ?></span>
                        <span class="dashicons dashicons-calendar-alt" style="font-size: 14px; width: 14px; height: 14px; color: #666;"></span>
                    </div>
                </div>
            </div>
            
            <div style="padding: 10px 15px; background: #fff; border: 1px solid #ccd0d4; border-top: none; display: flex; justify-content: flex-end; align-items: center; gap: 8px;">
                <input type="checkbox" id="rs-show-inactive" style="margin: 0;">
                <label for="rs-show-inactive" style="font-size: 13px; color: #333; font-weight: 500;"><?php _e('Show inactive crawlers', 'royal-sentinel'); ?></label>
                <span class="dashicons dashicons-info-outline" style="font-size: 16px; width: 16px; height: 16px; color: #999; cursor: help;" title="<?php _e('Display bots with 0 traffic in the selected period.', 'royal-sentinel'); ?>"></span>
            </div>

            <table class="wp-list-table widefat fixed rs-analytics-table" style="border-top: none; box-shadow: none; border-radius: 0 0 4px 4px;">
                <thead>
                    <tr style="background: #f9f9f9; border-top: 1px solid #e0e0e0;">
                        <th style="width: 40px; text-align: center; padding: 10px;"><input type="checkbox"></th>
                        <th style="padding: 10px; font-weight: 600; color: #333;"><?php _e('Crawler', 'royal-sentinel'); ?></th>
                        <th style="padding: 10px; font-weight: 600; color: #333;"><?php _e('Category', 'royal-sentinel'); ?></th>
                        <th style="padding: 10px; font-weight: 600; color: #333;"><?php _e('Bytes Transferred', 'royal-sentinel'); ?></th>
                        <th style="padding: 10px; font-weight: 600; color: #333; width: 220px;"><?php _e('Requests', 'royal-sentinel'); ?></th>
                        <th style="padding: 10px; font-weight: 600; color: #333; width: 160px; text-align: center;">
                            <?php _e('Action', 'royal-sentinel'); ?> 
                            <span class="dashicons dashicons-info-outline" style="font-size: 14px; width: 14px; height: 14px; color: #999; vertical-align: middle;"></span>
                        </th>
                    </tr>
                </thead>
                <tbody id="rs-analytics-body">
                    <tr>
                        <td colspan="6" style="text-align:center; padding:15px; color:#666;">
                            <div class="spin" style="display:inline-block; width:12px; height:12px; border:2px solid #666; border-top-color:transparent; border-radius:50%;"></div>
                            <?php _e('Loading data...', 'royal-sentinel'); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- 2. Metrics Section -->
        <div style="border-top:1px solid #eee; padding-top:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0;">📊 <?php _e('Metrics', 'royal-sentinel'); ?></h3>
                <select id="rs-period">
                    <option value="24h"><?php _e('Last 24 Hours', 'royal-sentinel'); ?></option>
                    <option value="7d"><?php _e('Last 7 Days', 'royal-sentinel'); ?></option>
                    <option value="30d"><?php _e('Last 30 Days', 'royal-sentinel'); ?></option>
                </select>
            </div>

            <!-- Charts Row -->
            <div style="display:flex; gap:20px; margin-bottom:30px;">
                <div style="flex:2;">
                    <h4 style="margin-top:0; color:#666; font-size:13px;"><?php _e('Allowed requests over time', 'royal-sentinel'); ?></h4>
                    <div style="position:relative; height:250px; width:100%;">
                        <canvas id="rsAiChart"></canvas>
                    </div>
                </div>
                <div style="flex:1; min-width:250px;">
                    <h4 style="margin-top:0; color:#666; font-size:13px;"><?php _e('Status code distribution', 'royal-sentinel'); ?></h4>
                    <div style="position:relative; height:250px; width:100%;">
                        <canvas id="rsStatusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Stats Boxes -->
            <div id="rs-analytics-stats" style="margin-bottom:30px; display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; text-align:center;">
                <div style="background:#f0f0f1; padding:15px; border-radius:5px;">
                    <span style="display:block; font-size:12px; color:#666;">TOTAL REQUESTS</span>
                    <strong style="font-size:24px; color:#2271b1;" id="stat-total">0</strong>
                </div>
                <div style="background:#f0f0f1; padding:15px; border-radius:5px;">
                    <span style="display:block; font-size:12px; color:#666;">BLOCKED</span>
                    <strong style="font-size:24px; color:#d63638;" id="stat-blocked">0</strong>
                </div>
                <div style="background:#f0f0f1; padding:15px; border-radius:5px;">
                    <span style="display:block; font-size:12px; color:#666;">ALLOWED</span>
                    <strong style="font-size:24px; color:#00a32a;" id="stat-allowed">0</strong>
                </div>
            </div>

            <!-- Most Crawled Paths -->
            <div style="margin-top: 30px;">
                 <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 5px;">
                     <div>
                        <h3 style="margin:0; font-size: 16px; font-weight: 600;"><?php _e('Most crawled paths', 'royal-sentinel'); ?></h3>
                        <p style="margin: 5px 0 15px; color: #666; font-size: 13px;">
                            <?php _e('View all paths crawled by AI crawlers, regardless of content type.', 'royal-sentinel'); ?>
                        </p>
                     </div>
                     <div style="display:flex; gap: 8px; align-items: center;">
                         <div style="background: #f0f0f1; border: 1px solid #ccd0d4; border-radius: 4px; display: flex; overflow: hidden;">
                             <button style="border: none; background: #fff; padding: 4px 12px; font-size: 12px; cursor: pointer; border-right: 1px solid #ccd0d4; font-weight: 600;">All</button>
                             <button style="border: none; background: transparent; padding: 4px 12px; font-size: 12px; cursor: pointer; color: #666;">Patterns</button>
                         </div>
                         <button style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 4px 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-media-document" style="font-size: 16px; width: 16px; height: 16px;"></span>
                         </button>
                     </div>
                 </div>
                 
                 <table class="wp-list-table widefat fixed striped" style="border: 1px solid #e0e0e0; border-radius: 4px; border-collapse: separate; border-spacing: 0; box-shadow: none;">
                     <thead>
                         <tr style="background: #f9f9f9;">
                             <th style="font-weight: 600; color: #333; height: 40px; border-bottom: 2px solid #e0e0e0; padding: 10px 15px;"><?php _e('Path', 'royal-sentinel'); ?></th>
                             <th style="font-weight: 600; color: #333; border-bottom: 2px solid #e0e0e0; padding: 10px 15px;"><?php _e('Host', 'royal-sentinel'); ?></th>
                             <th style="font-weight: 600; color: #333; width:150px; text-align:right; border-bottom: 2px solid #e0e0e0; padding: 10px 15px;">
                                 <span style="display: inline-flex; align-items: center; gap: 4px;">
                                     <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px; color: #2271b1;"></span>
                                     <?php _e('Allowed requests', 'royal-sentinel'); ?>
                                 </span>
                             </th>
                         </tr>
                     </thead>
                     <tbody id="rs-paths-body">
                         <tr><td colspan="3" style="text-align:center; padding: 20px; color:#999;"><?php _e('Loading paths...', 'royal-sentinel'); ?></td></tr>
                     </tbody>
                 </table>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Globals
    let chartInstance = null;
    let statusChartInstance = null;
    let initialBotsState = [];
    
    // 1. Initial Load
    const initialZone = "<?php echo $default_zid; ?>";
    
    $('#rs-ai-zone-selector').select2({
        width: 'resolve',
        placeholder: "<?php _e('Select Domain', 'royal-sentinel'); ?>"
    });

    if(initialZone) {
        loadZoneData(initialZone);
    }

    // 2. Zone Change
    $('#rs-ai-zone-selector').on('change', function() {
        loadZoneData($(this).val());
    });

    // 3. Period Change
    $('#rs-period').on('change', function() {
        loadAnalytics($('#rs-ai-zone-selector').val(), $(this).val());
    });

    // 4. Save Changes
    $('#rs-save-ai-rules').on('click', function() {
        saveRules();
    });

    // 5. Action Button Group Clicks
    $(document).on('click', '.rs-action-btn', function() {
        if($(this).hasClass('active')) return;

        const botKey = $(this).data('bot');
        const action = $(this).data('action');
        const isBlocked = (action === 'block');
        
        // Update visual in table
        const parent = $(this).closest('.rs-action-group');
        parent.find('.rs-action-btn').removeClass('active');
        $(this).addClass('active');

        // Update hidden toggle
        let toggle = $(`.rs-ai-toggle[value="${botKey}"]`);
        if(toggle.length === 0) {
             if($('#rs-hidden-toggles').length === 0) $('body').append('<div id="rs-hidden-toggles" style="display:none;"></div>');
             $('#rs-hidden-toggles').append(`<input type="checkbox" class="rs-ai-toggle" value="${botKey}">`);
             toggle = $(`.rs-ai-toggle[value="${botKey}"]`);
        }
        
        if(toggle.is(':checked') !== isBlocked) {
            toggle.prop('checked', isBlocked).trigger('change');
        }
    });

    // 6. Global Toggle Change (Sync back to table)
    $(document).on('change', '.rs-ai-toggle', function() {
        $('#rs-save-ai-rules').prop('disabled', false).text('💾 Save Changes');
        updateTableButtonState($(this).val(), $(this).is(':checked'));
    });

    function updateTableButtonState(botKey, isBlocked) {
         const btn = $(`.rs-action-btn[data-bot="${botKey}"]`);
         if(btn.length) {
             const group = btn.parent();
             group.find('.rs-action-btn').removeClass('active');
             if(isBlocked) group.find('.block').addClass('active');
             else group.find('.allow').addClass('active');
         }
    }

    // 7. Show Inactive Toggle
    $(document).on('change', '#rs-show-inactive', function() {
        loadAnalytics($('#rs-ai-zone-selector').val(), $('#rs-period').val());
    });

    function loadZoneData(zoneId) {
        $('#rs-ai-loading').show();
        $('.rs-ai-toggle').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ai_control_fetch', zone_id: zoneId },
            success: function(res) {
                $('.rs-ai-toggle').prop('checked', false); 
                if(res.success) {
                    const blocked = res.data.blocked_bots || [];
                    blocked.forEach(botKey => {
                        let toggle = $(`.rs-ai-toggle[value="${botKey}"]`);
                        if(toggle.length === 0) {
                             if($('#rs-hidden-toggles').length === 0) $('body').append('<div id="rs-hidden-toggles" style="display:none;"></div>');
                             $('#rs-hidden-toggles').append(`<input type="checkbox" class="rs-ai-toggle" value="${botKey}">`);
                             toggle = $(`.rs-ai-toggle[value="${botKey}"]`);
                        }
                        toggle.prop('checked', true);
                    });
                    
                    $('#rs-save-ai-rules').prop('disabled', true).text('💾 Save Changes');
                    loadAnalytics(zoneId, $('#rs-period').val());
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data || 'Failed to fetch rules.' });
                }
            }
        });
    }

    function saveRules() {
        const zoneId = $('#rs-ai-zone-selector').val();
        let blocked = [];
        $('.rs-ai-toggle:checked').each(function() {
            blocked.push($(this).val());
        });

        $('#rs-save-ai-rules').prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ai_control_save', zone_id: zoneId, blocked_bots: blocked },
            success: function(res) {
                if(res.success) {
                    Swal.fire({ icon: 'success', title: 'Saved!', text: 'AI Bot rules updated.', timer: 1500, showConfirmButton: false });
                    $('#rs-save-ai-rules').text('💾 Saved');
                    $('#rs-save-ai-rules').prop('disabled', true);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.data || 'Failed to save.' });
                    $('#rs-save-ai-rules').prop('disabled', false).text('💾 Save Changes');
                }
            }
        });
    }

    function loadAnalytics(zoneId, period) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { nonce: '<?php echo wp_create_nonce("rs_cf_tabs"); ?>', action: 'rs_ai_analytics', zone_id: zoneId, period: period },
            success: function(res) {
                if(res.success && res.data) {
                    renderChart(res.data.timeseries || []);
                    renderStatusChart(res.data.status_codes || {});
                    renderTable(res.data.crawlers || []);
                    renderPathsTable(res.data.top_paths || []);
                }
            }
        });
    }

    function renderChart(data) {
        const ctxEl = document.getElementById('rsAiChart');
        if(!ctxEl) return;
        const ctx = ctxEl.getContext('2d');
        
        let labels = [];
        let datasetBlocked = {};
        let datasetAllowed = {};
        
        data.sort((a, b) => new Date(a.dimensions.datetimeHour) - new Date(b.dimensions.datetimeHour));

        let total = 0, blocked_count = 0, allowed_count = 0;

        data.forEach(item => {
            const time = new Date(item.dimensions.datetimeHour).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit' });
            if(!labels.includes(time)) labels.push(time);
            
            const count = item.count;
            total += count;
            
            if(item.dimensions.action === 'block') {
                blocked_count += count;
                datasetBlocked[time] = (datasetBlocked[time] || 0) + count;
            } else {
                allowed_count += count;
                datasetAllowed[time] = (datasetAllowed[time] || 0) + count;
            }
        });

        $('#stat-total').text(total);
        $('#stat-blocked').text(blocked_count);
        $('#stat-allowed').text(allowed_count);

        if(chartInstance) chartInstance.destroy();

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Allowed', data: labels.map(t => datasetAllowed[t] || 0), borderColor: '#00a32a', backgroundColor: 'rgba(0,163,42,0.1)', fill: true, tension: 0.4 },
                    { label: 'Blocked', data: labels.map(t => datasetBlocked[t] || 0), borderColor: '#d63638', backgroundColor: 'rgba(214,54,56,0.1)', fill: true, tension: 0.4 }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
        });
    }

    function renderStatusChart(data) {
        const ctxEl = document.getElementById('rsStatusChart');
        if(!ctxEl) return;
        const ctx = ctxEl.getContext('2d');
        if(statusChartInstance) statusChartInstance.destroy();

        const labels = Object.keys(data);
        const values = Object.values(data);
        const colors = labels.map(code => {
            code = parseInt(code);
            if(code >= 200 && code < 300) return '#00a32a';
            if(code >= 300 && code < 400) return '#2271b1';
            if(code >= 400 && code < 500) return '#d63638';
            if(code >= 500) return '#dba617';
            return '#999';
        });

        statusChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 1 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } } }
        });
    }

    function renderTable(data) {
        const tbody = $('#rs-analytics-body');
        tbody.empty();
        
        if(!Array.isArray(data) || data.length === 0) {
            tbody.html('<tr><td colspan="6" style="text-align:center; padding:30px; color:#666;">No data available.</td></tr>');
            return;
        }

        const showInactive = $('#rs-show-inactive').is(':checked');

        data.forEach(item => {
            const stats = item.stats || {};
            if(!showInactive && (stats.total || 0) === 0) return;

            const botKey = item.botKey;
            const isBlocked = $(`.rs-ai-toggle[value="${botKey}"]`).is(':checked');
            const badgeClass = (item.category || '').toLowerCase().includes('search') ? 'search-crawler' : 'ai-crawler';

            tbody.append(`
                <tr>
                    <td style="text-align:center; vertical-align:middle; padding:12px 10px;"><input type="checkbox"></td>
                    <td style="vertical-align: middle;">
                        <span class="rs-crawler-name">${item.name || botKey}</span>
                        <span class="rs-crawler-entity">${item.entity || 'Unknown'}</span>
                    </td>
                    <td style="vertical-align: middle;"><span class="rs-badge ${badgeClass}">${item.category || 'AI Crawler'}</span></td>
                    <td style="vertical-align: middle; font-weight:500;">${formatBytes(stats.bytes || 0)}</td>
                    <td style="vertical-align: middle;">
                         <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 80px; height: 18px; display: flex; align-items: flex-end; gap: 2px;">
                                ${Array.from({length: 8}).map(() => `<div style="flex: 1; height: ${Math.floor(Math.random() * 80 + 10)}%; background: #2271b1; opacity: 0.3; border-radius: 1px;"></div>`).join('')}
                            </div>
                            <div style="font-size: 11px; line-height: 1.3;">
                                <div><span class="rs-stat-val rs-stat-allowed">Allowed: ${stats.allowed || 0}</span></div>
                                <div><span class="rs-stat-val">Unsuccessful: ${stats.blocked || 0}</span></div>
                            </div>
                         </div>
                    </td>
                    <td style="text-align:center; vertical-align: middle;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <div class="rs-action-group">
                                <button class="rs-action-btn allow ${!isBlocked ? 'active' : ''}" data-bot="${botKey}" data-action="allow">Allow</button>
                                <button class="rs-action-btn block ${isBlocked ? 'active' : ''}" data-bot="${botKey}" data-action="block">Block</button>
                            </div>
                            <span class="dashicons dashicons-ellipsis" style="color: #999; cursor: pointer; margin-left:5px;"></span>
                        </div>
                    </td>
                </tr>
            `);
        });

        if(tbody.children().length === 0) {
             tbody.html('<tr><td colspan="6" style="text-align:center; padding:30px; color:#666;">No active crawlers. Check "Show inactive crawlers".</td></tr>');
        }
    }

    function renderPathsTable(data) {
        const tbody = $('#rs-paths-body');
        tbody.empty();
        if(!Array.isArray(data) || data.length === 0) {
            tbody.html('<tr><td colspan="3" style="text-align:center; padding:20px; color:#666;">No data available.</td></tr>');
            return;
        }
        data.forEach(item => {
             const path = item.path;
             const shortPath = path.length > 70 ? path.substring(0, 70) + '...' : path;
             tbody.append(`
                <tr>
                    <td style="padding: 12px 15px;"><a href="${path}" target="_blank" style="color: #2271b1; text-decoration: underline; font-family: monospace; font-size: 13px;">${shortPath}</a></td>
                    <td style="padding: 12px 15px; color: #50575e; font-size: 13px;">${item.host || '-'}</td>
                    <td style="padding: 12px 15px; text-align: right; font-weight: 500; font-size: 14px;">${item.count}</td>
                </tr>
             `);
        });
    }

    function formatBytes(bytes, decimals = 2) {
        if (!+bytes) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return `${parseFloat((bytes / Math.pow(k, i)).toFixed(decimals < 0 ? 0 : decimals))} ${sizes[i]}`;
    }

});
</script>
