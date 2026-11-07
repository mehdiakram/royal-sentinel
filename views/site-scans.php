<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$last_scan = RS_Scan_Engine::get_last_scan();
$nonce = wp_create_nonce( 'rs_scan_nonce' );
?>

<div class="wrap rs-scan-wrap">
    <div class="rs-page-header green-gradient">
        <div class="rs-header-content">
            <h1 class="rs-page-title">
                <div class="rs-process-icon">🔍</div>
                <?php _e('Site Security Scans', 'royal-sentinel'); ?>
            </h1>
            <p class="rs-page-subtitle"><?php _e('Scan your WordPress site for security issues and misconfigurations.', 'royal-sentinel'); ?></p>
        </div>
    </div>

    <!-- Last Scan Info Card -->
    <?php if ( $last_scan ): ?>
    <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding:25px; border-radius:10px; margin-bottom:20px; color:#fff; box-shadow: 0 4px 15px rgba(102,126,234,0.4);">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:14px; opacity:0.9; margin-bottom:5px;">
                    <span class="dashicons dashicons-clock" style="vertical-align:middle;"></span> 
                    <?php _e('Last Scan:', 'royal-sentinel'); ?>
                </div>
                <div style="font-size:24px; font-weight:bold;">
                    <?php echo esc_html( human_time_diff( strtotime( $last_scan->scan_date ), current_time( 'timestamp' ) ) ); ?> <?php _e('ago', 'royal-sentinel'); ?>
                </div>
                <div style="font-size:12px; opacity:0.8; margin-top:3px;">
                    <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_scan->scan_date ) ) ); ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:14px; opacity:0.9; margin-bottom:5px;">
                    <?php _e('Issues Found', 'royal-sentinel'); ?>
                </div>
                <div style="font-size:48px; font-weight:bold; line-height:1;">
                    <?php echo esc_html( $last_scan->total_issues ); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Start Scan Card -->
    <div style="background:#fff; padding:30px; border-radius:10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="margin:0 0 10px 0;"><?php _e('Security Scan', 'royal-sentinel'); ?></h2>
                <p style="margin:0; color:#666; font-size:14px;">
                    <?php _e('Comprehensive security check covering plugins, themes, core, users, files, and configuration.', 'royal-sentinel'); ?>
                </p>
            </div>
            <button id="rs-start-scan-btn" class="button button-primary button-hero" style="background:#00a32a; border-color:#00a32a; box-shadow: 0 3px 10px rgba(0,163,42,0.3);">
                <span class="dashicons dashicons-search" style="vertical-align:middle;"></span> 
                <?php _e('Start Scan', 'royal-sentinel'); ?>
            </button>
        </div>
    </div>

    <!-- Scan Progress -->
    <div id="rs-scan-progress" style="display:none; background:#fff; padding:30px; border-radius:10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom:20px;">
        <h2 style="margin-top:0;"><?php _e('Scanning...', 'royal-sentinel'); ?></h2>
        <div class="rs-progress-bar" style="background:#e0e0e0; height:8px; border-radius:10px; overflow:hidden; margin-bottom:25px;">
            <div id="rs-progress-fill" style="background:linear-gradient(90deg, #00a32a 0%, #00d084 100%); height:100%; width:0%; transition:width 0.3s;"></div>
        </div>
        
        <!-- Modules in Single Row -->
        <div id="rs-module-grid" style="display:grid; grid-template-columns:repeat(7, 1fr); gap:10px; margin-top:20px;">
            <div class="rs-module-card" data-module="plugins">
                <div class="rs-module-icon">📦</div>
                <div class="rs-module-name"><?php _e('Plugins', 'royal-sentinel'); ?></div>
                <div class="rs-module-status">⏳</div>
            </div>
            <div class="rs-module-card" data-module="themes">
                <div class="rs-module-icon">🎨</div>
                <div class="rs-module-name"><?php _e('Themes', 'royal-sentinel'); ?></div>
                <div class="rs-module-status">⏳</div>
            </div>
            <div class="rs-module-card" data-module="core">
                <div class="rs-module-icon">⚙️</div>
                <div class="rs-module-name"><?php _e('Core', 'royal-sentinel'); ?></div>
                <div class="rs-module-status">⏳</div>
            </div>
            <div class="rs-module-card" data-module="users">
                <div class="rs-module-icon">👥</div>
                <div class="rs-module-name"><?php _e('Users', 'royal-sentinel'); ?></div>
                <div class="rs-module-status">⏳</div>
            </div>
            <div class="rs-module-card" data-module="files">
                <div class="rs-module-icon">📁</div>
                <div class="rs-module-name"><?php _e('Files', 'royal-sentinel'); ?></div>
                <div class="rs-module-status">⏳</div>
            </div>
            <div class="rs-module-card" data-module="config">
                <div class="rs-module-icon">🔧</div>
                <div class="rs-module-name"><?php _e('Config', 'royal-sentinel'); ?></div>
                <div class="rs-module-status">⏳</div>
            </div>
            <div class="rs-module-card" data-module="external">
                <div class="rs-module-icon">🌐</div>
                <div class="rs-module-name"><?php _e('External', 'royal-sentinel'); ?></div>
                <div class="rs-module-status">⏳</div>
            </div>
        </div>
    </div>

    <!-- Scan Results -->
    <div id="rs-scan-results" style="<?php echo $last_scan ? '' : 'display:none;'; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0;"><?php _e('Scan Results', 'royal-sentinel'); ?></h2>
            <div class="rs-results-tabs">
                <button class="rs-tab-btn active" data-risk="all"><?php _e('All', 'royal-sentinel'); ?></button>
                <button class="rs-tab-btn rs-tab-high" data-risk="high"><?php _e('High', 'royal-sentinel'); ?></button>
                <button class="rs-tab-btn rs-tab-medium" data-risk="medium"><?php _e('Medium', 'royal-sentinel'); ?></button>
                <button class="rs-tab-btn rs-tab-low" data-risk="low"><?php _e('Low', 'royal-sentinel'); ?></button>
            </div>
        </div>
        
        <div id="rs-issues-container"></div>
    </div>
</div>

<style>
.rs-scan-wrap .rs-badge-success {
    background:#d1f4d1; 
    color:#00a32a; 
    padding:2px 8px; 
    border-radius:3px; 
    font-weight:bold;
}
.rs-scan-wrap .rs-badge-warning {
    background:#f8d7da; 
    color:#d63638; 
    padding:2px 8px; 
    border-radius:3px; 
    font-weight:bold;
}
.rs-scan-wrap .rs-module-card {
    background:#f8f9fa;
    border:2px solid #e0e0e0;
    border-radius:8px;
    padding:15px 10px;
    text-align:center;
    transition:all 0.3s;
}
.rs-scan-wrap .rs-module-card.running {
    background:#e3f2fd;
    border-color:#2196f3;
    transform:scale(1.05);
    box-shadow: 0 4px 12px rgba(33,150,243,0.3);
    animation: pulse 1.5s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% {
        box-shadow: 0 4px 12px rgba(33,150,243,0.3);
    }
    50% {
        box-shadow: 0 4px 20px rgba(33,150,243,0.6);
    }
}
.rs-scan-wrap .rs-module-card.completed {
    background:#e8f5e9;
    border-color:#4caf50;
    animation: checkmark 0.5s ease-in-out;
}
@keyframes checkmark {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}
.rs-scan-wrap .rs-module-card.failed {
    background:#ffebee;
    border-color:#f44336;
}
.rs-scan-wrap .rs-module-icon {
    font-size:32px;
    margin-bottom:8px;
}
.rs-scan-wrap .rs-module-name {
    font-size:12px;
    font-weight:600;
    color:#333;
    margin-bottom:5px;
}
.rs-scan-wrap .rs-module-status {
    font-size:11px;
    color:#666;
}
.rs-scan-wrap .rs-tab-btn {
    background:#fff;
    border:2px solid #e0e0e0;
    padding:8px 20px;
    margin-right:8px;
    cursor:pointer;
    border-radius:20px;
    font-weight:600;
    transition:all 0.2s;
}
.rs-scan-wrap .rs-tab-btn:hover {
    transform:translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.rs-scan-wrap .rs-tab-btn.active {
    background:#2271b1;
    color:#fff;
    border-color:#2271b1;
}
.rs-scan-wrap .rs-tab-high.active {
    background:#d63638;
    border-color:#d63638;
}
.rs-scan-wrap .rs-tab-medium.active {
    background:#dba617;
    border-color:#dba617;
}
.rs-scan-wrap .rs-tab-low.active {
    background:#00a32a;
    border-color:#00a32a;
}
.rs-scan-wrap .rs-issue-card {
    background:#fff;
    border:1px solid #e0e0e0;
    padding:20px;
    margin-bottom:15px;
    border-radius:8px;
    border-left:4px solid #ccd0d4;
    transition:all 0.2s;
}
.rs-scan-wrap .rs-issue-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform:translateX(5px);
}
.rs-scan-wrap .rs-issue-card.risk-high {
    border-left-color:#d63638;
}
.rs-scan-wrap .rs-issue-card.risk-medium {
    border-left-color:#dba617;
}
.rs-scan-wrap .rs-issue-card.risk-low {
    border-left-color:#00a32a;
}
.rs-scan-wrap .rs-issue-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:10px;
}
.rs-scan-wrap .rs-risk-badge {
    padding:4px 12px;
    border-radius:12px;
    font-size:11px;
    font-weight:bold;
    text-transform:uppercase;
}
.rs-scan-wrap .rs-risk-badge.high {
    background:#ffebee;
    color:#d63638;
}
.rs-scan-wrap .rs-risk-badge.medium {
    background:#fff8e1;
    color:#dba617;
}
.rs-scan-wrap .rs-risk-badge.low {
    background:#e8f5e9;
    color:#00a32a;
}
</style>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo $nonce; ?>';
    let currentScanId = null;
    const modules = ['plugins', 'themes', 'core', 'users', 'files', 'config', 'external'];
    let allIssues = [];
    
    // Load last scan results if available
    <?php if ( $last_scan ): ?>
    loadLastScanResults();
    <?php endif; ?>
    
    function loadLastScanResults() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rs_get_scan_results',
                nonce: nonce,
                scan_id: <?php echo $last_scan ? $last_scan->id : 0; ?>
            },
            success: function(response) {
                if (response.success && response.data.issues) {
                    allIssues = response.data.issues;
                    displayResults();
                }
            }
        });
    }
    
    $('#rs-start-scan-btn').on('click', function() {
        startScan();
    });
    
    function startScan() {
        $('#rs-start-scan-btn').prop('disabled', true).text('<?php _e('Scanning...', 'royal-sentinel'); ?>');
        $('#rs-scan-progress').show();
        $('#rs-scan-results').hide();
        
        // Reset module cards
        $('.rs-module-card').removeClass('running completed failed').find('.rs-module-status').text('⏳');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rs_start_scan',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    currentScanId = response.data.scan_id;
                    allIssues = [];
                    runModules(0);
                } else {
                    Swal.fire('Error', response.data.message, 'error');
                    resetUI();
                }
            },
            error: function() {
                Swal.fire('Error', '<?php _e('Failed to start scan', 'royal-sentinel'); ?>', 'error');
                resetUI();
            }
        });
    }
    
    function runModules(index) {
        if (index >= modules.length) {
            completeScan();
            return;
        }
        
        const module = modules[index];
        const progress = ((index + 1) / modules.length) * 100;
        
        $('#rs-progress-fill').css('width', progress + '%');
        $('.rs-module-card[data-module="' + module + '"]').addClass('running').find('.rs-module-status').text('⏳');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rs_run_module',
                nonce: nonce,
                scan_id: currentScanId,
                module: module
            },
            success: function(response) {
                $('.rs-module-card[data-module="' + module + '"]')
                    .removeClass('running')
                    .addClass('completed')
                    .find('.rs-module-status').text('✓');
                
                if (response.success && response.data.issues) {
                    allIssues = allIssues.concat(response.data.issues.map(issue => ({...issue, module: module})));
                }
                
                runModules(index + 1);
            },
            error: function() {
                $('.rs-module-card[data-module="' + module + '"]')
                    .removeClass('running')
                    .addClass('failed')
                    .find('.rs-module-status').text('⚠');
                runModules(index + 1);
            }
        });
    }
    
    function completeScan() {
        // Fetch results from database
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rs_get_scan_results',
                nonce: nonce,
                scan_id: currentScanId
            },
            success: function(response) {
                if (response.success && response.data.issues) {
                    allIssues = response.data.issues;
                    displayResults();
                }
                
                resetUI();
                
                Swal.fire({
                    title: '<?php _e('Scan Complete', 'royal-sentinel'); ?>',
                    text: allIssues.length + ' <?php _e('issue(s) found', 'royal-sentinel'); ?>',
                    icon: allIssues.length > 0 ? 'warning' : 'success',
                    timer: 3000
                });
                
                setTimeout(() => location.reload(), 3500);
            }
        });
    }
    
    function displayResults() {
        $('#rs-scan-results').show();
        filterIssues('all');
    }
    
    function filterIssues(risk) {
        let filtered = risk === 'all' ? allIssues : allIssues.filter(i => i.risk === risk);
        
        if (filtered.length === 0) {
            $('#rs-issues-container').html('<div style="text-align:center; padding:60px; background:#f8f9fa; border-radius:8px;"><div style="font-size:48px; margin-bottom:15px;">✓</div><p style="color:#666; font-size:16px; margin:0;"><?php _e('No issues found in this category.', 'royal-sentinel'); ?></p></div>');
            return;
        }
        
        let html = '';
        filtered.forEach(issue => {
            html += `
                <div class="rs-issue-card risk-${issue.risk}">
                    <div class="rs-issue-header">
                        <h3 style="margin:0; font-size:16px;">${issue.title}</h3>
                        <div>
                            <span class="rs-risk-badge ${issue.risk}">${issue.risk}</span>
                            <button class="button button-small rs-mute-btn" data-id="${issue.id}" style="margin-left:10px;">
                                <?php _e('Mute', 'royal-sentinel'); ?>
                            </button>
                        </div>
                    </div>
                    <p style="color:#666; margin:15px 0; line-height:1.6;">${issue.description}</p>
                    <div style="padding:15px; background:#f8f9fa; border-radius:6px; border-left:3px solid #00a32a;">
                        <strong style="color:#00a32a;">→ <?php _e('Recommendation:', 'royal-sentinel'); ?></strong> ${issue.recommendation}
                    </div>
                </div>
            `;
        });
        
        $('#rs-issues-container').html(html);
    }
    
    $('.rs-tab-btn').on('click', function() {
        $('.rs-tab-btn').removeClass('active');
        $(this).addClass('active');
        filterIssues($(this).data('risk'));
    });
    
    $(document).on('click', '.rs-mute-btn', function() {
        const btn = $(this);
        const issueId = btn.data('id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rs_mute_issue',
                nonce: nonce,
                issue_id: issueId
            },
            success: function() {
                btn.closest('.rs-issue-card').fadeOut();
            }
        });
    });
    
    function resetUI() {
        $('#rs-start-scan-btn').prop('disabled', false).html('<span class="dashicons dashicons-search" style="vertical-align:middle;"></span> <?php _e('Start Scan', 'royal-sentinel'); ?>');
    }
});
</script>
