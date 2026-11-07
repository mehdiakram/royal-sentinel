<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Files Scan Module (Lightweight)
 */
class RS_Scan_Files {
    
    private $max_files = 1000;
    private $scanned_count = 0;
    
    public function scan() {
        $issues = array();
        
        // Check for PHP files in uploads directory
        $uploads_dir = wp_upload_dir();
        $php_files = $this->find_php_files_in_uploads( $uploads_dir['basedir'] );
        
        foreach ( $php_files as $file ) {
            $issues[] = array(
                'item_reference' => 'file-' . md5( $file ),
                'title' => __( 'PHP File in Uploads Directory', 'royal-sentinel' ),
                'description' => sprintf(
                    __( 'PHP file found in uploads: %s. PHP files should not exist in the uploads directory as they can be executed by attackers.', 'royal-sentinel' ),
                    str_replace( ABSPATH, '', $file )
                ),
                'risk' => 'high',
                'recommendation' => __( 'Review and delete this file if it\'s not legitimate. Ensure .htaccess blocks PHP execution in uploads.', 'royal-sentinel' )
            );
        }
        
        // Check for recently modified core files
        $recent_core_files = $this->find_recently_modified_core_files();
        
        foreach ( $recent_core_files as $file ) {
            $issues[] = array(
                'item_reference' => 'core-modified-' . md5( $file ),
                'title' => __( 'Recently Modified Core File', 'royal-sentinel' ),
                'description' => sprintf(
                    __( 'WordPress core file was recently modified: %s. This requires manual review to ensure it\'s legitimate.', 'royal-sentinel' ),
                    str_replace( ABSPATH, '', $file )
                ),
                'risk' => 'medium',
                'recommendation' => __( 'Review this file for unauthorized changes. Consider reinstalling WordPress core if suspicious.', 'royal-sentinel' )
            );
        }
        
        return $issues;
    }
    
    /**
     * Find PHP files in uploads directory
     */
    private function find_php_files_in_uploads( $dir ) {
        $php_files = array();
        
        if ( ! is_dir( $dir ) ) {
            return $php_files;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ( $iterator as $file ) {
            if ( $this->scanned_count >= $this->max_files ) {
                break;
            }
            
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $php_files[] = $file->getPathname();
            }
            
            $this->scanned_count++;
        }
        
        return $php_files;
    }
    
    /**
     * Find recently modified files in WordPress core directories
     */
    private function find_recently_modified_core_files() {
        $modified_files = array();
        $core_dirs = array( ABSPATH . 'wp-admin', ABSPATH . 'wp-includes' );
        $seven_days_ago = time() - ( 7 * DAY_IN_SECONDS );
        
        foreach ( $core_dirs as $dir ) {
            if ( ! is_dir( $dir ) ) {
                continue;
            }
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ( $iterator as $file ) {
                if ( $this->scanned_count >= $this->max_files ) {
                    break 2;
                }
                
                if ( $file->isFile() && $file->getExtension() === 'php' ) {
                    if ( $file->getMTime() > $seven_days_ago ) {
                        $modified_files[] = $file->getPathname();
                    }
                }
                
                $this->scanned_count++;
            }
        }
        
        return $modified_files;
    }
}
