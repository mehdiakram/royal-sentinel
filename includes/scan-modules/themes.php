<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Themes Scan Module
 */
class RS_Scan_Themes {
    
    public function scan() {
        $issues = array();
        
        // Get all themes
        $all_themes = wp_get_themes();
        $active_theme = wp_get_theme();
        $active_theme_slug = $active_theme->get_stylesheet();
        
        // Get parent theme if child theme is active
        $parent_theme_slug = $active_theme->get_template();
        
        foreach ( $all_themes as $theme_slug => $theme ) {
            // Skip active theme
            if ( $theme_slug === $active_theme_slug ) {
                continue;
            }
            
            // Skip parent theme of active child theme
            if ( $theme_slug === $parent_theme_slug && $parent_theme_slug !== $active_theme_slug ) {
                continue;
            }
            
            // Flag truly inactive themes
            $issues[] = array(
                'item_reference' => $theme_slug,
                'title' => sprintf( __( 'Inactive Theme: %s', 'royal-sentinel' ), $theme->get( 'Name' ) ),
                'description' => __( 'This theme is installed but not in use. Inactive themes can be a security risk if they contain vulnerabilities.', 'royal-sentinel' ),
                'risk' => 'low',
                'recommendation' => sprintf(
                    __( 'Delete from %s if not needed', 'royal-sentinel' ),
                    '<a href="' . admin_url( 'themes.php' ) . '" target="_blank">Dashboard → Themes</a>'
                )
            );
            
            // Check if outdated
            $latest_version = $this->get_latest_version( $theme_slug );
            
            if ( $latest_version && version_compare( $theme->get( 'Version' ), $latest_version, '<' ) ) {
                $issues[] = array(
                    'item_reference' => $theme_slug,
                    'title' => sprintf( __( 'Theme Outdated: %s', 'royal-sentinel' ), $theme->get( 'Name' ) ),
                    'description' => sprintf(
                        __( 'Theme "%s" version %s is outdated. Latest version is %s.', 'royal-sentinel' ),
                        $theme->get( 'Name' ),
                        $theme->get( 'Version' ),
                        $latest_version
                    ),
                    'risk' => 'medium',
                    'recommendation' => __( 'Update this theme from Dashboard → Appearance → Themes.', 'royal-sentinel' )
                );
            }
        }
        
        return $issues;
    }
    
    /**
     * Get latest theme version from WordPress.org
     */
    private function get_latest_version( $slug ) {
        $url = 'https://api.wordpress.org/themes/info/1.2/?action=theme_information&slug=' . $slug;
        
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
}
