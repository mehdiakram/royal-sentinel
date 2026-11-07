<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WordPress Core Version Scan Module
 */
class RS_Scan_Core {
    
    public function scan() {
        $issues = array();
        
        // Get current WordPress version
        $current_version = get_bloginfo( 'version' );
        
        // Get latest WordPress version
        $latest_version = $this->get_latest_wp_version();
        
        if ( ! $latest_version ) {
            return $issues; // Skip if can't fetch latest version
        }
        
        // Compare versions
        if ( version_compare( $current_version, $latest_version, '<' ) ) {
            $version_gap = $this->calculate_version_gap( $current_version, $latest_version );
            $risk = $this->determine_risk_level( $version_gap );
            
            $issues[] = array(
                'item_reference' => 'wordpress-core',
                'title' => __( 'WordPress Core is Outdated', 'royal-sentinel' ),
                'description' => sprintf(
                    __( 'Your WordPress version (%s) is outdated. Latest stable version is %s. You are %d version(s) behind.', 'royal-sentinel' ),
                    $current_version,
                    $latest_version,
                    $version_gap
                ),
                'risk' => $risk,
                'recommendation' => __( 'Update WordPress to the latest version from Dashboard → Updates. Always backup before updating.', 'royal-sentinel' )
            );
        }
        
        return $issues;
    }
    
    /**
     * Get latest WordPress version from API
     */
    private function get_latest_wp_version() {
        $response = wp_remote_get( 'https://api.wordpress.org/core/version-check/1.7/' );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['offers'][0]['version'] ) ) {
            return $data['offers'][0]['version'];
        }
        
        return false;
    }
    
    /**
     * Calculate version gap
     */
    private function calculate_version_gap( $current, $latest ) {
        $current_parts = explode( '.', $current );
        $latest_parts = explode( '.', $latest );
        
        // Simple calculation based on minor version difference
        $current_minor = isset( $current_parts[1] ) ? intval( $current_parts[1] ) : 0;
        $latest_minor = isset( $latest_parts[1] ) ? intval( $latest_parts[1] ) : 0;
        
        return abs( $latest_minor - $current_minor );
    }
    
    /**
     * Determine risk level based on version gap
     */
    private function determine_risk_level( $gap ) {
        if ( $gap >= 6 ) {
            return 'high';
        } elseif ( $gap >= 3 ) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
