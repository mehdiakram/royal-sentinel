<?php
/*
 * --------------------------------------------------------------------------
 * ROYAL SECURITY: SESSION MANAGEMENT MODULE
 * --------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class RS_Session_Management_Logic {

    private $settings;

    public function __construct() {
        $this->settings = get_option( 'rs_security_settings', [] );
        
        // Enhanced session tracking on login
        add_action( 'wp_login', array( $this, 'on_user_login' ), 10, 2 );
        
        // Limit concurrent sessions
        add_filter( 'attach_session_information', array( $this, 'attach_session_info' ), 10, 1 );
        
        // AJAX handlers for session management
        add_action( 'wp_ajax_rs_terminate_session', array( $this, 'ajax_terminate_session' ) );
        add_action( 'wp_ajax_rs_terminate_all_sessions', array( $this, 'ajax_terminate_all_sessions' ) );
        
        // Idle timeout check
        add_action( 'init', array( $this, 'check_idle_timeout' ) );
    }

    /**
     * Handle user login - enforce concurrent limit and send notifications
     */
    public function on_user_login( $user_login, $user ) {
        // Limit concurrent sessions
        if ( ! empty( $this->settings['sess_limit_concurrent'] ) ) {
            $max_sessions = isset( $this->settings['sess_max_concurrent'] ) ? intval( $this->settings['sess_max_concurrent'] ) : 1;
            $this->enforce_session_limit( $user->ID, $max_sessions );
        }
        
        // Send login notification
        if ( ! empty( $this->settings['sess_notify_new'] ) ) {
            $this->maybe_send_login_notification( $user );
        }
        
        // Update last activity time
        if ( ! empty( $this->settings['sess_tracking'] ) ) {
            update_user_meta( $user->ID, 'rs_last_activity', time() );
        }
    }

    /**
     * Attach additional session information
     */
    public function attach_session_info( $session ) {
        if ( empty( $this->settings['sess_tracking'] ) ) {
            return $session;
        }
        
        // Add IP and user agent
        $session['ip'] = rs_get_real_ip();
        $session['ua'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : 'Unknown';
        
        return $session;
    }

    /**
     * Enforce session limit by removing oldest sessions
     */
    private function enforce_session_limit( $user_id, $max_sessions ) {
        $sessions = WP_Session_Tokens::get_instance( $user_id );
        $all_sessions = $sessions->get_all();
        
        if ( count( $all_sessions ) > $max_sessions ) {
            // Sort by login time (oldest first)
            uasort( $all_sessions, function( $a, $b ) {
                $login_a = isset( $a['login'] ) ? $a['login'] : 0;
                $login_b = isset( $b['login'] ) ? $b['login'] : 0;
                return $login_a - $login_b;
            });
            
            // Remove excess sessions (oldest ones)
            $to_remove = count( $all_sessions ) - $max_sessions;
            $session_keys = array_keys( $all_sessions );
            
            for ( $i = 0; $i < $to_remove; $i++ ) {
                $sessions->destroy( $session_keys[$i] );
            }
            
            // Log this action
            $this->log_event( 'session_limit', "Removed {$to_remove} excess sessions for user ID {$user_id}", 1 );
        }
    }

    /**
     * Send login notification for new device/IP
     */
    private function maybe_send_login_notification( $user ) {
        $current_ip = rs_get_real_ip();
        $known_ips = get_user_meta( $user->ID, 'rs_known_ips', true );
        
        if ( ! is_array( $known_ips ) ) {
            $known_ips = array();
        }
        
        // Check if this IP is new
        if ( ! in_array( $current_ip, $known_ips ) ) {
            // Add to known IPs
            $known_ips[] = $current_ip;
            $known_ips = array_slice( $known_ips, -10 ); // Keep last 10 IPs
            update_user_meta( $user->ID, 'rs_known_ips', $known_ips );
            
            // Send notification email
            $this->send_login_alert( $user, $current_ip );
        }
    }

    /**
     * Send login alert email
     */
    private function send_login_alert( $user, $ip ) {
        $site_name = get_bloginfo( 'name' );
        $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : 'Unknown';
        $time = current_time( 'F j, Y \a\t g:i a' );
        
        $subject = "[{$site_name}] New login detected for your account";
        
        $message = "Hello {$user->display_name},\n\n";
        $message .= "A new login was detected for your account on {$site_name}.\n\n";
        $message .= "Details:\n";
        $message .= "- Time: {$time}\n";
        $message .= "- IP Address: {$ip}\n";
        $message .= "- Browser: {$user_agent}\n\n";
        $message .= "If this was you, you can safely ignore this email.\n\n";
        $message .= "If this wasn't you, please change your password immediately and contact your site administrator.\n\n";
        $message .= "Best regards,\n";
        $message .= "Royal Sentinel Security";
        
        wp_mail( $user->user_email, $subject, $message );
    }

    /**
     * Check idle timeout
     */
    public function check_idle_timeout() {
        if ( ! is_user_logged_in() ) {
            return;
        }
        
        $idle_timeout = isset( $this->settings['sess_idle_timeout'] ) ? intval( $this->settings['sess_idle_timeout'] ) : 0;
        
        if ( $idle_timeout <= 0 ) {
            return;
        }
        
        $user_id = get_current_user_id();
        $last_activity = get_user_meta( $user_id, 'rs_last_activity', true );
        
        if ( $last_activity && ( time() - $last_activity ) > ( $idle_timeout * 60 ) ) {
            // Session expired due to inactivity
            wp_logout();
            wp_redirect( wp_login_url() . '?rs_timeout=1' );
            exit;
        }
        
        // Update last activity on each request (throttled)
        $update_interval = 60; // Update every 60 seconds max
        if ( ! $last_activity || ( time() - $last_activity ) > $update_interval ) {
            update_user_meta( $user_id, 'rs_last_activity', time() );
        }
    }

    /**
     * AJAX: Terminate a single session
     */
    public function ajax_terminate_session() {
        check_ajax_referer( 'rs_session_action', '_wpnonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
        $token_hash = isset( $_POST['token_hash'] ) ? sanitize_text_field( $_POST['token_hash'] ) : '';
        
        if ( ! $user_id || ! $token_hash ) {
            wp_send_json_error( 'Invalid parameters' );
        }
        
        // Find and destroy the session
        $sessions = get_user_meta( $user_id, 'session_tokens', true );
        
        if ( ! is_array( $sessions ) ) {
            wp_send_json_error( 'No sessions found' );
        }
        
        $found = false;
        foreach ( $sessions as $token => $data ) {
            if ( substr( md5( $token ), 0, 12 ) === $token_hash ) {
                unset( $sessions[$token] );
                $found = true;
                break;
            }
        }
        
        if ( $found ) {
            update_user_meta( $user_id, 'session_tokens', $sessions );
            $this->log_event( 'session_terminate', "Admin terminated session for user ID {$user_id}", 1 );
            wp_send_json_success( 'Session terminated' );
        } else {
            wp_send_json_error( 'Session not found' );
        }
    }

    /**
     * AJAX: Terminate all sessions except current
     */
    public function ajax_terminate_all_sessions() {
        check_ajax_referer( 'rs_session_action', '_wpnonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }
        
        $current_user_id = get_current_user_id();
        $current_token = wp_get_session_token();
        
        // Get all users with sessions
        $users = get_users( array( 'meta_key' => 'session_tokens', 'meta_compare' => 'EXISTS' ) );
        
        $terminated_count = 0;
        
        foreach ( $users as $user ) {
            $sessions = get_user_meta( $user->ID, 'session_tokens', true );
            
            if ( ! is_array( $sessions ) || empty( $sessions ) ) {
                continue;
            }
            
            if ( $user->ID == $current_user_id ) {
                // Keep current session, remove others
                $new_sessions = array();
                foreach ( $sessions as $token => $data ) {
                    if ( $token === $current_token ) {
                        $new_sessions[$token] = $data;
                    } else {
                        $terminated_count++;
                    }
                }
                update_user_meta( $user->ID, 'session_tokens', $new_sessions );
            } else {
                // Remove all sessions for other users
                $terminated_count += count( $sessions );
                delete_user_meta( $user->ID, 'session_tokens' );
            }
        }
        
        $this->log_event( 'session_terminate_all', "Admin terminated {$terminated_count} sessions", 2 );
        wp_send_json_success( "Terminated {$terminated_count} sessions" );
    }



    /**
     * Log security event
     */
    private function log_event( $type, $message, $severity = 1 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'royalsentinel_monitor_logs';
        
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) == $table ) {
            $wpdb->insert( $table, array(
                'type'       => 'session_' . $type,
                'message'    => $message,
                'severity'   => $severity,
                'created_at' => current_time( 'mysql' ),
            ) );
        }
    }
}

new RS_Session_Management_Logic();
