<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Settings
$sess_tracking          = isset($rs_settings['sess_tracking']) ? $rs_settings['sess_tracking'] : 0;
$sess_limit_concurrent  = isset($rs_settings['sess_limit_concurrent']) ? $rs_settings['sess_limit_concurrent'] : 0;
$sess_notify_new        = isset($rs_settings['sess_notify_new']) ? $rs_settings['sess_notify_new'] : 0;

// Advanced Settings
$sess_max_concurrent    = isset($rs_settings['sess_max_concurrent']) ? $rs_settings['sess_max_concurrent'] : '1';
$sess_idle_timeout      = isset($rs_settings['sess_idle_timeout']) ? $rs_settings['sess_idle_timeout'] : '30';

// Get all active sessions
$all_sessions = [];
$users_with_sessions = get_users(['meta_key' => 'session_tokens', 'meta_compare' => 'EXISTS']);

foreach ($users_with_sessions as $user) {
    $sessions = get_user_meta($user->ID, 'session_tokens', true);
    if (!empty($sessions) && is_array($sessions)) {
        foreach ($sessions as $token => $session_data) {
            $all_sessions[] = [
                'user_id'    => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'token_hash' => substr(md5($token), 0, 12),
                'ip'         => isset($session_data['ip']) ? $session_data['ip'] : 'Unknown',
                'ua'         => isset($session_data['ua']) ? $session_data['ua'] : 'Unknown',
                'login'      => isset($session_data['login']) ? $session_data['login'] : 0,
                'expiration' => isset($session_data['expiration']) ? $session_data['expiration'] : 0,
            ];
        }
    }
}

// Sort by login time (newest first)
usort($all_sessions, function($a, $b) {
    return $b['login'] - $a['login'];
});
?>

<div class="rs-sessions-wrapper">
    
    <div style="margin-bottom: 20px;">
        <h3 style="margin:0;">👥 Session Management</h3>
        <p class="description">Monitor and control active user sessions across your site.</p>
    </div>

    <div class="rs-section-box" style="border-left: 4px solid #3498db;">
        <h4 class="rs-section-title" style="color: #3498db;">🔧 Session Settings</h4>
        
        <table class="form-table">
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="sess_tracking" value="1" <?php checked(1, $sess_tracking); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Enable Enhanced Session Tracking</label>
                    <p class="description">Track additional session data like IP address, user agent, and login time for each session.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="sess_limit_concurrent" id="rs_sess_limit" value="1" <?php checked(1, $sess_limit_concurrent); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Limit Concurrent Sessions</label>
                    <p class="description">Restrict how many devices a user can be logged in from simultaneously.</p>
                    
                    <div id="rs_concurrent_options" style="margin-top: 10px; display: <?php echo $sess_limit_concurrent ? 'block' : 'none'; ?>;">
                        <label>Maximum sessions per user:</label>
                        <select name="sess_max_concurrent" style="min-width: 100px;">
                            <option value="1" <?php selected('1', $sess_max_concurrent); ?>>1 (Strict)</option>
                            <option value="2" <?php selected('2', $sess_max_concurrent); ?>>2</option>
                            <option value="3" <?php selected('3', $sess_max_concurrent); ?>>3</option>
                            <option value="5" <?php selected('5', $sess_max_concurrent); ?>>5</option>
                        </select>
                        <p class="description" style="margin-top: 5px;">When limit is reached, oldest session will be terminated on new login.</p>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th scope="row" style="width: 50px; padding-top: 15px;">
                    <label class="rs-switch">
                        <input type="checkbox" name="sess_notify_new" value="1" <?php checked(1, $sess_notify_new); ?>>
                        <span class="rs-slider round"></span>
                    </label>
                </th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">New Login Notifications</label>
                    <p class="description">Send email notification to users when a new login is detected from an unrecognized device/IP.</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row" style="padding-top: 15px;"></th>
                <td style="padding-top: 15px;">
                    <label style="font-weight:600; color: #3c434a; font-size: 14px;">Idle Session Timeout</label>
                    <p class="description" style="margin-bottom: 10px;">Automatically log out inactive sessions after this period.</p>
                    <select name="sess_idle_timeout" style="min-width: 150px;">
                        <option value="0" <?php selected('0', $sess_idle_timeout); ?>>Disabled</option>
                        <option value="15" <?php selected('15', $sess_idle_timeout); ?>>15 minutes</option>
                        <option value="30" <?php selected('30', $sess_idle_timeout); ?>>30 minutes</option>
                        <option value="60" <?php selected('60', $sess_idle_timeout); ?>>1 hour</option>
                        <option value="120" <?php selected('120', $sess_idle_timeout); ?>>2 hours</option>
                        <option value="480" <?php selected('480', $sess_idle_timeout); ?>>8 hours</option>
                    </select>
                </td>
            </tr>
        </table>
    </div>

    <div class="rs-section-box" style="margin-top: 30px; border-left: 4px solid #27ae60;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px;">
            <h4 class="rs-section-title" style="color: #27ae60; margin: 0; border: none; padding: 0;">🖥️ Active Sessions (<?php echo count($all_sessions); ?>)</h4>
            <button type="button" id="rs_logout_all_btn" class="button button-secondary" style="color: #d63638; border-color: #d63638;">
                <span class="dashicons dashicons-no" style="vertical-align: middle;"></span> Terminate All Sessions
            </button>
        </div>
        
        <?php if (!empty($all_sessions)): ?>
            <table class="widefat striped" id="rs_sessions_table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Device</th>
                        <th>Login Time</th>
                        <th>Expires</th>
                        <th style="width: 100px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_sessions as $session): 
                        // Parse user agent
                        $ua = $session['ua'];
                        $browser = 'Unknown';
                        if (strpos($ua, 'Chrome') !== false) $browser = 'Chrome';
                        elseif (strpos($ua, 'Firefox') !== false) $browser = 'Firefox';
                        elseif (strpos($ua, 'Safari') !== false) $browser = 'Safari';
                        elseif (strpos($ua, 'Edge') !== false) $browser = 'Edge';
                        elseif (strpos($ua, 'MSIE') !== false || strpos($ua, 'Trident') !== false) $browser = 'IE';
                        
                        $os = 'Unknown';
                        if (strpos($ua, 'Windows') !== false) $os = 'Windows';
                        elseif (strpos($ua, 'Mac') !== false) $os = 'Mac';
                        elseif (strpos($ua, 'Linux') !== false) $os = 'Linux';
                        elseif (strpos($ua, 'iPhone') !== false) $os = 'iPhone';
                        elseif (strpos($ua, 'Android') !== false) $os = 'Android';
                        
                        $is_current = false;
                        // Check if this is the current user's current session
                        $current_user = wp_get_current_user();
                        if ($current_user->ID == $session['user_id']) {
                            $current_token = wp_get_session_token();
                            if ($current_token && substr(md5($current_token), 0, 12) === $session['token_hash']) {
                                $is_current = true;
                            }
                        }
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($session['user_login']); ?></strong>
                                <br><small style="color: #888;"><?php echo esc_html($session['user_email']); ?></small>
                            </td>
                            <td><code><?php echo esc_html($session['ip']); ?></code></td>
                            <td>
                                <span class="dashicons dashicons-<?php echo ($os === 'Windows' || $os === 'Mac' || $os === 'Linux') ? 'desktop' : 'smartphone'; ?>" style="vertical-align: middle;"></span>
                                <?php echo esc_html("$browser / $os"); ?>
                            </td>
                            <td>
                                <?php 
                                if ($session['login'] > 0) {
                                    echo esc_html(date('M d, Y H:i', $session['login']));
                                } else {
                                    echo '<span style="color: #888;">Unknown</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ($session['expiration'] > 0) {
                                    $expires_in = $session['expiration'] - time();
                                    if ($expires_in > 86400) {
                                        echo esc_html(round($expires_in / 86400) . ' days');
                                    } elseif ($expires_in > 3600) {
                                        echo esc_html(round($expires_in / 3600) . ' hours');
                                    } else {
                                        echo esc_html(round($expires_in / 60) . ' mins');
                                    }
                                } else {
                                    echo '<span style="color: #888;">Unknown</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($is_current): ?>
                                    <span style="color: #27ae60; font-weight: 600;">Current</span>
                                <?php else: ?>
                                    <button type="button" class="button button-small rs-logout-session-btn" 
                                            data-user-id="<?php echo esc_attr($session['user_id']); ?>"
                                            data-token="<?php echo esc_attr($session['token_hash']); ?>">
                                        Terminate
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="padding: 30px; text-align: center; color: #888; background: #f9f9f9; border-radius: 4px;">
                <span class="dashicons dashicons-admin-users" style="font-size: 40px; width: 40px; height: 40px;"></span>
                <p style="margin: 10px 0 0;">No active sessions found.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<style>
    .rs-section-box { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; margin-bottom: 20px; }
    .rs-section-title { margin-top: 0; padding-bottom: 15px; border-bottom: 1px solid #eee; font-size: 15px; font-weight: 600; }
    .form-table th { padding: 15px 10px 15px 0; }
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle concurrent options
    $('#rs_sess_limit').on('change', function() {
        if($(this).is(':checked')) {
            $('#rs_concurrent_options').slideDown();
        } else {
            $('#rs_concurrent_options').slideUp();
        }
    });
    
    // Terminate single session
    $(document).on('click', '.rs-logout-session-btn', function() {
        var btn = $(this);
        var userId = btn.data('user-id');
        var token = btn.data('token');
        
        Swal.fire({
            title: 'Terminate Session?',
            text: 'This will immediately log out this session.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Terminate',
            confirmButtonColor: '#d63638'
        }).then((result) => {
            if (result.isConfirmed) {
                btn.prop('disabled', true).text('...');
                
                $.post(ajaxurl, {
                    action: 'rs_terminate_session',
                    user_id: userId,
                    token_hash: token,
                    _wpnonce: '<?php echo wp_create_nonce("rs_session_action"); ?>'
                }, function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Session Terminated',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.data || 'Failed to terminate session', 'error');
                        btn.prop('disabled', false).text('Terminate');
                    }
                });
            }
        });
    });
    
    // Terminate all sessions
    $('#rs_logout_all_btn').on('click', function() {
        Swal.fire({
            title: 'Terminate ALL Sessions?',
            text: 'This will log out all users except you. Are you sure?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Terminate All',
            confirmButtonColor: '#d63638'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(ajaxurl, {
                    action: 'rs_terminate_all_sessions',
                    _wpnonce: '<?php echo wp_create_nonce("rs_session_action"); ?>'
                }, function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'All Sessions Terminated',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', response.data || 'Failed to terminate sessions', 'error');
                    }
                });
            }
        });
    });
    
    // Init DataTable if available
    if ($.fn.DataTable && $('#rs_sessions_table').length) {
        $('#rs_sessions_table').DataTable({
            pageLength: 10,
            order: [[3, 'desc']]
        });
    }
});
</script>
