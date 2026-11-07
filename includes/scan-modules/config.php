<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Configuration Security Scan Module
 */
class RS_Scan_Config {
    
    public function scan() {
        $issues = array();
        
        // Check WP_DEBUG
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            $issues[] = array(
                'item_reference' => 'wp-debug',
                'title' => __( 'WP_DEBUG is Enabled', 'royal-sentinel' ),
                'description' => __( 'Debug mode is enabled in production. This can expose sensitive information about your site structure and errors to visitors.', 'royal-sentinel' ),
                'risk' => 'medium',
                'recommendation' => __( 'Set WP_DEBUG to false in wp-config.php for production sites.', 'royal-sentinel' )
            );
        }
        
        // 2. File Editor Enabled
        if ( ! defined( 'DISALLOW_FILE_EDIT' ) || DISALLOW_FILE_EDIT !== true ) {
            $issues[] = array(
                'item_reference' => 'file_editor',
                'title' => __( 'File Editor is Enabled', 'royal-sentinel' ),
                'description' => __( 'WordPress file editor allows editing PHP files from the admin panel, which is a security risk if an attacker gains admin access.', 'royal-sentinel' ),
                'risk' => 'medium',
                'recommendation' => sprintf(
                    __( 'Disable from %s', 'royal-sentinel' ),
                    '<a href="' . admin_url( 'admin.php?page=rs-advance-security&tab=hardening' ) . '" target="_blank">Royal Sentinel → Advanced Security → Hardening</a>'
                )
            );
        }
        
        // 3. XML-RPC Enabled
        if ( $this->is_xmlrpc_enabled() ) {
            $issues[] = array(
                'item_reference' => 'xmlrpc',
                'title' => __( 'XML-RPC is Enabled', 'royal-sentinel' ),
                'description' => __( 'XML-RPC can be exploited for brute force attacks and DDoS amplification. Unless you specifically need it (e.g., for mobile apps or remote publishing), it should be disabled.', 'royal-sentinel' ),
                'risk' => 'low',
                'recommendation' => __( 'Disable XML-RPC using a security plugin or add a filter to block it.', 'royal-sentinel' )
            );
        }
        
        // Check directory listing
        if ( $this->check_directory_listing() ) {
            $issues[] = array(
                'item_reference' => 'directory-listing',
                'title' => __( 'Directory Listing May Be Enabled', 'royal-sentinel' ),
                'description' => __( 'Directory listing allows visitors to browse your site\'s file structure. This can expose sensitive files and folder organization.', 'royal-sentinel' ),
                'risk' => 'low',
                'recommendation' => __( 'Add "Options -Indexes" to your .htaccess file or ensure your server configuration blocks directory listing.', 'royal-sentinel' )
            );
        }
        
        // Check if using default table prefix
        global $wpdb;
        if ( $wpdb->prefix === 'wp_' ) {
            $issues[] = array(
                'item_reference' => 'table-prefix',
                'title' => __( 'Using Default Database Prefix', 'royal-sentinel' ),
                'description' => __( 'Your database uses the default "wp_" prefix. This makes it easier for attackers to target your database tables in SQL injection attacks.', 'royal-sentinel' ),
                'risk' => 'low',
                'recommendation' => __( 'Change the database prefix using a security plugin or manually (requires database expertise).', 'royal-sentinel' )
            );
        }
        
        return $issues;
    }
    
    /**
     * Check if XML-RPC is enabled
     */
    private function is_xmlrpc_enabled() {
        return apply_filters( 'xmlrpc_enabled', true );
    }
    
    /**
     * Check directory listing (basic check)
     */
    private function check_directory_listing() {
        $uploads_dir = wp_upload_dir();
        $test_url = trailingslashit( $uploads_dir['baseurl'] );
        
        $response = wp_remote_get( $test_url, array( 'timeout' => 5 ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        
        // Simple check for directory listing indicators
        if ( stripos( $body, 'Index of' ) !== false || stripos( $body, 'Parent Directory' ) !== false ) {
            return true;
        }
        
        return false;
    }
}
