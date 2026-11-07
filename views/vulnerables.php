<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$last_summary = RS_Vuln_Scanner::get_last_summary();
$cached_results = get_transient( 'rs_vuln_scan_results' );
$nonce = wp_create_nonce( 'rs_vuln_nonce' );
?>

<div class="wrap rs-vuln-wrap">
    <div class="rs-page-header red-gradient">
        <div class="rs-header-content">
            <h1 class="rs-page-title">
                <div class="rs-process-icon">⚠️</div>
                <?php _e('Vulnerables', 'royal-sentinel'); ?>
            </h1>
            <p class="rs-page-subtitle"><?php _e('Monitor outdated and potentially risky software on your site.', 'royal-sentinel'); ?></p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:15px; margin-bottom:25px;">
        <div style="background:#fff; padding:20px; border-radius:8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-top:3px solid #2271b1;">
            <div style="font-size:13px; color:#666; margin-bottom:8px;"><?php _e('Total Software', 'royal-sentinel'); ?></div>
            <div style="font-size:32px; font-weight:bold; color:#2271b1;">
                <?php echo $last_summary ? esc_html( $last_summary->total_software ) : '0'; ?>
            </div>
        </div>
        
        <div style="background:#fff; padding:20px; border-radius:8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-top:3px solid #f59e0b;">
            <div style="font-size:13px; color:#666; margin-bottom:8px;"><?php _e('Needs Attention', 'royal-sentinel'); ?></div>
            <div style="font-size:32px; font-weight:bold; color:#f59e0b;">
                <?php echo $last_summary ? esc_html( $last_summary->vulnerable_count ) : '0'; ?>
            </div>
        </div>
        
        <div style="background:#fff; padding:20px; border-radius:8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-top:3px solid #dc2626;">
            <div style="font-size:13px; color:#666; margin-bottom:8px;"><?php _e('Outdated', 'royal-sentinel'); ?></div>
            <div style="font-size:32px; font-weight:bold; color:#dc2626;">
                <?php echo $last_summary ? esc_html( $last_summary->outdated_count ) : '0'; ?>
            </div>
        </div>
        
        <div style="background:#fff; padding:20px; border-radius:8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-top:3px solid #9333ea;">
            <div style="font-size:13px; color:#666; margin-bottom:8px;"><?php _e('Environment', 'royal-sentinel'); ?></div>
            <div style="font-size:32px; font-weight:bold; color:#9333ea;">
                <?php echo $last_summary ? esc_html( $last_summary->environment_risks ) : '0'; ?>
            </div>
        </div>
    </div>

    <!-- Last Scan Info -->
    <?php if ( $last_summary ): ?>
    <div style="background:linear-gradient(135deg, #f59e0b 0%, #dc2626 100%); padding:25px; border-radius:10px; margin-bottom:20px; color:#fff; box-shadow: 0 4px 15px rgba(245,158,11,0.4);">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:14px; opacity:0.9; margin-bottom:5px;">
                    <span class="dashicons dashicons-clock" style="vertical-align:middle;"></span> 
                    <?php _e('Last Scan:', 'royal-sentinel'); ?>
                </div>
                <div style="font-size:24px; font-weight:bold;">
                    <?php echo esc_html( human_time_diff( strtotime( $last_summary->scan_date ), current_time( 'timestamp' ) ) ); ?> <?php _e('ago', 'royal-sentinel'); ?>
                </div>
                <div style="font-size:12px; opacity:0.8; margin-top:3px;">
                    <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_summary->scan_date ) ) ); ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:14px; opacity:0.9; margin-bottom:5px;">
                    <?php _e('Issues Found', 'royal-sentinel'); ?>
                </div>
                <div style="font-size:48px; font-weight:bold; line-height:1;">
                    <?php echo esc_html( $last_summary->vulnerable_count ); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div style="background:#fff; padding:30px; border-radius:10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h2 style="margin:0 0 10px 0;"><?php _e('Vulnerability Scan', 'royal-sentinel'); ?></h2>
                <p style="margin:0; color:#666; font-size:14px;">
                    <?php _e('Check for outdated software and potential security risks.', 'royal-sentinel'); ?>
                </p>
            </div>
            <button id="rs-scan-btn" class="button button-primary button-hero" style="background:#f59e0b; border-color:#f59e0b; box-shadow: 0 3px 10px rgba(245,158,11,0.3);">
                <span class="dashicons dashicons-update" style="vertical-align:middle;"></span> 
                <?php _e('Scan Now', 'royal-sentinel'); ?>
            </button>
        </div>
    </div>
    <?php else: ?>
    <div style="background:#fff; padding:30px; border-radius:10px; text-align:center; margin-bottom:20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="font-size:48px; margin-bottom:15px;">🔍</div>
        <h2 style="margin:0 0 10px 0;"><?php _e('No Scan Data Available', 'royal-sentinel'); ?></h2>
        <p style="color:#666; margin-bottom:20px;"><?php _e('Run your first vulnerability scan to monitor your site\'s software.', 'royal-sentinel'); ?></p>
        <button id="rs-scan-btn" class="button button-primary button-hero" style="background:#f59e0b; border-color:#f59e0b;">
            <span class="dashicons dashicons-search" style="vertical-align:middle;"></span> 
            <?php _e('Start First Scan', 'royal-sentinel'); ?>
        </button>
    </div>
    <?php endif; ?>

    <!-- Scanning Progress -->
    <div id="rs-scan-progress" style="display:none; background:#fff; padding:30px; border-radius:10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom:20px;">
        <h2 style="margin-top:0;"><?php _e('Scanning...', 'royal-sentinel'); ?></h2>
        <div style="background:#e0e0e0; height:8px; border-radius:10px; overflow:hidden; margin-bottom:15px;">
            <div id="rs-scan-progress-bar" style="background:linear-gradient(90deg, #f59e0b 0%, #dc2626 100%); height:100%; width:0%; transition:width 0.3s;"></div>
        </div>
        <div id="rs-scan-status" style="color:#666; text-align:center;"></div>
    </div>

    <!-- Results -->
    <div id="rs-vuln-results" style="<?php echo $cached_results ? '' : 'display:none;'; ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="margin:0;"><?php _e('Detected Issues', 'royal-sentinel'); ?></h2>
            <div class="rs-filter-tabs">
                <button class="rs-filter-btn active" data-filter="all"><?php _e('All', 'royal-sentinel'); ?></button>
                <button class="rs-filter-btn" data-filter="critical"><?php _e('Critical', 'royal-sentinel'); ?></button>
                <button class="rs-filter-btn" data-filter="high"><?php _e('High', 'royal-sentinel'); ?></button>
                <button class="rs-filter-btn" data-filter="medium"><?php _e('Medium', 'royal-sentinel'); ?></button>
                <button class="rs-filter-btn" data-filter="low"><?php _e('Low', 'royal-sentinel'); ?></button>
            </div>
        </div>
        
        <div id="rs-issues-container"></div>
    </div>
</div>

<style>
.rs-vuln-wrap .rs-filter-btn {
    background:#fff;
    border:2px solid #e0e0e0;
    padding:6px 16px;
    margin-right:8px;
    cursor:pointer;
    border-radius:20px;
    font-weight:600;
    font-size:13px;
    transition:all 0.2s;
}
.rs-vuln-wrap .rs-filter-btn:hover {
    transform:translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.rs-vuln-wrap .rs-filter-btn.active {
    background:#2271b1;
    color:#fff;
    border-color:#2271b1;
}
.rs-vuln-wrap .rs-issue-card {
    background:#fff;
    border:1px solid #e0e0e0;
    padding:20px;
    margin-bottom:12px;
    border-radius:8px;
    border-left:4px solid #ccc;
    transition:all 0.2s;
}
.rs-vuln-wrap .rs-issue-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform:translateX(5px);
}
.rs-vuln-wrap .rs-issue-card.severity-critical {
    border-left-color:#dc2626;
}
.rs-vuln-wrap .rs-issue-card.severity-high {
    border-left-color:#f59e0b;
}
.rs-vuln-wrap .rs-issue-card.severity-medium {
    border-left-color:#eab308;
}
.rs-vuln-wrap .rs-issue-card.severity-low {
    border-left-color:#3b82f6;
}
.rs-vuln-wrap .rs-severity-badge {
    padding:4px 12px;
    border-radius:12px;
    font-size:11px;
    font-weight:bold;
    text-transform:uppercase;
}
.rs-vuln-wrap .rs-severity-badge.critical {
    background:#fef2f2;
    color:#dc2626;
}
.rs-vuln-wrap .rs-severity-badge.high {
    background:#fff7ed;
    color:#f59e0b;
}
.rs-vuln-wrap .rs-severity-badge.medium {
    background:#fefce8;
    color:#eab308;
}
.rs-vuln-wrap .rs-severity-badge.low {
    background:#eff6ff;
    color:#3b82f6;
}
</style>

<script>
jQuery(document).ready(function($) {
    const nonce = '<?php echo $nonce; ?>';
    let allIssues = [];
    
    // Load cached results if available
    <?php if ( $cached_results ): ?>
    displayResults(<?php echo json_encode( $cached_results ); ?>);
    <?php endif; ?>
    
    $('#rs-scan-btn').on('click', function() {
        runScan();
    });
    
    function runScan() {
        $('#rs-scan-btn').prop('disabled', true);
        $('#rs-scan-progress').show();
        $('#rs-scan-progress-bar').css('width', '30%');
        $('#rs-scan-status').text('<?php _e('Analyzing installed software...', 'royal-sentinel'); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'rs_run_vuln_scan',
                nonce: nonce
            },
            success: function(response) {
                $('#rs-scan-progress-bar').css('width', '100%');
                
                if (response.success) {
                    $('#rs-scan-status').html('<strong style="color:#00a32a;">✓ <?php _e('Scan Complete!', 'royal-sentinel'); ?></strong>');
                    displayResults(response.data.results);
                    
                    setTimeout(() => {
                        $('#rs-scan-progress').hide();
                        location.reload();
                    }, 2000);
                } else {
                    Swal.fire('Error', response.data.message, 'error');
                    $('#rs-scan-progress').hide();
                }
                
                $('#rs-scan-btn').prop('disabled', false);
            },
            error: function() {
                Swal.fire('Error', '<?php _e('Scan failed. Please try again.', 'royal-sentinel'); ?>', 'error');
                $('#rs-scan-progress').hide();
                $('#rs-scan-btn').prop('disabled', false);
            }
        });
    }
    
    function displayResults(results) {
        allIssues = [];
        
        // Collect all issues
        ['core', 'plugins', 'themes', 'environment'].forEach(type => {
            if (results[type]) {
                results[type].forEach(issue => {
                    allIssues.push({...issue, type: type});
                });
            }
        });
        
        $('#rs-vuln-results').show();
        filterIssues('all');
    }
    
    function filterIssues(severity) {
        let filtered = severity === 'all' ? allIssues : allIssues.filter(i => i.severity === severity);
        
        if (filtered.length === 0) {
            $('#rs-issues-container').html('<div style="text-align:center; padding:60px; background:#f8f9fa; border-radius:8px;"><div style="font-size:48px; margin-bottom:15px;">✓</div><p style="color:#666; font-size:16px; margin:0;"><?php _e('No issues found in this category.', 'royal-sentinel'); ?></p></div>');
            return;
        }
        
        let html = '';
        filtered.forEach(issue => {
            const riskLabel = issue.risk_type === 'outdated' ? '<?php _e('Outdated', 'royal-sentinel'); ?>' :
                            issue.risk_type === 'abandoned' ? '<?php _e('Abandoned', 'royal-sentinel'); ?>' :
                            issue.risk_type === 'known_risk' ? '<?php _e('Known Risk', 'royal-sentinel'); ?>' :
                            '<?php _e('Environment', 'royal-sentinel'); ?>';
            
            html += `
                <div class="rs-issue-card severity-${issue.severity}">
                    <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:12px;">
                        <div>
                            <h3 style="margin:0 0 5px 0; font-size:16px;">${issue.name}</h3>
                            <div style="font-size:13px; color:#666;">
                                ${issue.current_version ? 'v' + issue.current_version : ''}
                                ${issue.latest_version && issue.latest_version !== 'N/A' ? ' → v' + issue.latest_version : ''}
                            </div>
                        </div>
                        <span class="rs-severity-badge ${issue.severity}">${issue.severity}</span>
                    </div>
                    <div style="padding:12px; background:#f8f9fa; border-radius:6px; margin-bottom:12px;">
                        <strong style="color:#666; font-size:13px;">${riskLabel}</strong>
                        ${issue.reason ? '<div style="color:#666; font-size:13px; margin-top:5px;">' + issue.reason + '</div>' : ''}
                    </div>
                    <div style="padding:15px; background:#fff7ed; border-radius:6px; border-left:3px solid #f59e0b;">
                        <strong style="color:#f59e0b; font-size:13px;">→ <?php _e('Recommendation:', 'royal-sentinel'); ?></strong>
                        <div style="color:#666; font-size:13px; margin-top:5px;">${issue.recommendation}</div>
                    </div>
                </div>
            `;
        });
        
        $('#rs-issues-container').html(html);
    }
    
    $('.rs-filter-btn').on('click', function() {
        $('.rs-filter-btn').removeClass('active');
        $(this).addClass('active');
        filterIssues($(this).data('filter'));
    });
});
</script>
