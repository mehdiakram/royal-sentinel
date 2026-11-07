<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Plugins Scan Module
 */
class RS_Scan_Plugins {
    
    public function scan() {
        $issues = array();
        
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $all_plugins = get_plugins();
        $active_plugins = get_option( 'active_plugins', array() );
        
        foreach ( $all_plugins as $plugin_path => $plugin_data ) {
            $slug = dirname( $plugin_path );
            if ( $slug === '.' ) {
                $slug = basename( $plugin_path, '.php' );
            }
            
            // Check if outdated
            $latest_version = $this->get_latest_version( $slug );
            
            if ( $latest_version && version_compare( $plugin_data['Version'], $latest_version, '<' ) ) {
                $risk = $this->calculate_risk( $plugin_data['Version'], $latest_version );
                
                $issues[] = array(
                    'item_reference' => $slug,
                    'title' => sprintf( __( 'Plugin Outdated: %s', 'royal-sentinel' ), $plugin_data['Name'] ),
                    'description' => sprintf(
                        __( 'Plugin "%s" version %s is outdated. Latest version is %s. Outdated plugins may have security vulnerabilities.', 'royal-sentinel' ),
                        $plugin_data['Name'],
                        $plugin_data['Version'],
                        $latest_version
                    ),
                    'risk' => $risk,
                    'recommendation' => __( 'Update this plugin from Dashboard → Plugins. Always backup before updating.', 'royal-sentinel' )
                );
            }
        }
        
        return $issues;
    }
    
    /**
     * Get latest plugin version from WordPress.org
     */
    private function get_latest_version( $slug ) {
        $url = 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&slug=' . $slug;
        
        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['version'] ) ) {
            return $data['version'];
        }
        
        return false;
    }
    
    /**
     * Calculate risk based on version difference
     */
    private function calculate_risk( $current, $latest ) {
        $current_parts = explode( '.', $current );
        $latest_parts = explode( '.', $latest );
        
        // Major version difference
        if ( isset( $current_parts[0] ) && isset( $latest_parts[0] ) ) {
            if ( intval( $latest_parts[0] ) > intval( $current_parts[0] ) ) {
                return 'high';
            }
        }
        
        // Minor version difference
        if ( isset( $current_parts[1] ) && isset( $latest_parts[1] ) ) {
            $diff = intval( $latest_parts[1] ) - intval( $current_parts[1] );
            if ( $diff >= 3 ) {
                return 'medium';
            }
        }
        
        return 'low';
    }
}
