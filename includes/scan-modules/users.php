<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Users Scan Module
 */
class RS_Scan_Users {
    
    public function scan() {
        $issues = array();
        
        $admin_users = get_users( array( 'role' => 'administrator' ) );
        
        foreach ( $admin_users as $user ) {
            // Check for common attack usernames
            $risky_usernames = array( 'admin', 'administrator', 'test', 'demo', 'user', 'root' );
            
            if ( in_array( strtolower( $user->user_login ), $risky_usernames ) ) {
                $issues[] = array(
                    'item_reference' => 'user-' . $user->ID,
                    'title' => sprintf( __( 'Common Admin Username: %s', 'royal-sentinel' ), $user->user_login ),
                    'description' => sprintf(
                        __( 'Administrator account "%s" uses a common username that attackers often target in brute force attacks.', 'royal-sentinel' ),
                        $user->user_login
                    ),
                    'risk' => 'medium',
                    'recommendation' => __( 'Create a new admin account with a unique username and delete this one. Ensure strong passwords are used.', 'royal-sentinel' )
                );
            }
            
            // Check for inactive admin accounts
            $last_login = get_user_meta( $user->ID, 'rs_last_login', true );
            
            if ( $last_login ) {
                $days_inactive = ( time() - strtotime( $last_login ) ) / DAY_IN_SECONDS;
                
                if ( $days_inactive > 60 ) {
                    $issues[] = array(
                        'item_reference' => 'user-inactive-' . $user->ID,
                        'title' => sprintf( __( 'Inactive Admin Account: %s', 'royal-sentinel' ), $user->user_login ),
                        'description' => sprintf(
                            __( 'Administrator "%s" has not logged in for %d days. Inactive admin accounts are security risks.', 'royal-sentinel' ),
                            $user->user_login,
                            round( $days_inactive )
                        ),
                        'risk' => 'low',
                        'recommendation' => __( 'Remove or demote unused administrator accounts to reduce attack surface.', 'royal-sentinel' )
                    );
                }
            }
        }
        
        return $issues;
    }
}
