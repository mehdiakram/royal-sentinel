<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Royal Sentinel - Site Scans Engine
 * Core orchestration for security scanning modules
 */

class RS_Scan_Engine {
    
    /**
     * Initialize scan engine
     */
    public static function init() {
        // Register AJAX handlers
        add_action( 'wp_ajax_rs_start_scan', array( __CLASS__, 'ajax_start_scan' ) );
        add_action( 'wp_ajax_rs_run_module', array( __CLASS__, 'ajax_run_module' ) );
        add_action( 'wp_ajax_rs_get_scan_results', array( __CLASS__, 'ajax_get_results' ) );
        add_action( 'wp_ajax_rs_mute_issue', array( __CLASS__, 'ajax_mute_issue' ) );
        add_action( 'wp_ajax_rs_delete_scan', array( __CLASS__, 'ajax_delete_scan' ) );
    }
    
    /**
     * AJAX: Start new scan
     */
    public static function ajax_start_scan() {
        check_ajax_referer( 'rs_scan_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'royal-sentinel' ) ) );
        }
        
        global $wpdb;
        $table = RS_Database::get_table_name('scan_history');
        
        // Create new scan record
        $wpdb->insert( $table, array(
            'scan_date' => current_time( 'mysql' ),
            'total_issues' => 0,
            'status' => 'running',
            'modules_completed' => json_encode( array() )
        ) );
        
        $scan_id = $wpdb->insert_id;
        
        wp_send_json_success( array(
            'scan_id' => $scan_id,
            'message' => __( 'Scan started', 'royal-sentinel' )
        ) );
    }
    
    /**
     * AJAX: Run specific scan module
     */
    public static function ajax_run_module() {
        check_ajax_referer( 'rs_scan_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'royal-sentinel' ) ) );
        }
        
        $scan_id = intval( $_POST['scan_id'] );
        $module = sanitize_text_field( $_POST['module'] );
        
        // Increase execution time for scanning
        @set_time_limit( 300 );
        
        $result = self::run_module( $scan_id, $module );
        
        if ( $result['success'] ) {
            self::update_scan_progress( $scan_id, $module, $result['issues'] );
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }
    
    /**
     * Run a specific scan module
     */
    private static function run_module( $scan_id, $module ) {
        $module_file = ROYALSENTINEL_PLUGIN_DIR . 'includes/scan-modules/' . $module . '.php';
        
        if ( ! file_exists( $module_file ) ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Module %s not found', 'royal-sentinel' ), $module )
            );
        }
        
        require_once $module_file;
        
        $class_name = 'RS_Scan_' . ucfirst( $module );
        
        if ( ! class_exists( $class_name ) ) {
            return array(
                'success' => false,
                'message' => sprintf( __( 'Module class %s not found', 'royal-sentinel' ), $class_name )
            );
        }
        
        try {
            $scanner = new $class_name();
            $issues = $scanner->scan();
            
            // Store issues in database
            self::store_issues( $scan_id, $module, $issues );
            
            return array(
                'success' => true,
                'issues' => $issues,
                'count' => count( $issues ),
                'message' => sprintf( __( '%s scan completed', 'royal-sentinel' ), ucfirst( $module ) )
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Store scan issues in database
     */
    private static function store_issues( $scan_id, $module, $issues ) {
        if ( empty( $issues ) ) {
            return;
        }
        
        global $wpdb;
        $table = RS_Database::get_table_name('scan_issues');
        
        foreach ( $issues as $issue ) {
            $wpdb->insert( $table, array(
                'scan_id' => $scan_id,
                'module' => $module,
                'item_reference' => $issue['item_reference'] ?? '',
                'title' => $issue['title'],
                'description' => $issue['description'],
                'risk' => $issue['risk'],
                'status' => 'open',
                'recommendation' => $issue['recommendation'],
                'created_at' => current_time( 'mysql' )
            ) );
        }
    }
    
    /**
     * Update scan progress
     */
    private static function update_scan_progress( $scan_id, $module, $issues ) {
        global $wpdb;
        $table = RS_Database::get_table_name('scan_history');
        
        // Get current scan
        $scan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $scan_id ) );
        
        if ( ! $scan ) {
            return;
        }
        
        $modules_completed = json_decode( $scan->modules_completed, true ) ?: array();
        $modules_completed[] = $module;
        
        $total_issues = $scan->total_issues + count( $issues );
        
        // Check if all modules are completed
        $all_modules = array( 'plugins', 'themes', 'core', 'users', 'files', 'config', 'external' );
        $status = ( count( $modules_completed ) >= count( $all_modules ) ) ? 'completed' : 'running';
        
        $wpdb->update( $table, 
            array(
                'modules_completed' => json_encode( $modules_completed ),
                'total_issues' => $total_issues,
                'status' => $status
            ),
            array( 'id' => $scan_id )
        );
    }
    
    /**
     * AJAX: Get scan results
     */
    public static function ajax_get_results() {
        check_ajax_referer( 'rs_scan_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'royal-sentinel' ) ) );
        }
        
        $scan_id = intval( $_POST['scan_id'] );
        
        global $wpdb;
        $issues_table = RS_Database::get_table_name('scan_issues');
        
        $issues = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $issues_table WHERE scan_id = %d AND status != 'muted' ORDER BY 
            CASE risk 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'low' THEN 3 
            END",
            $scan_id
        ), ARRAY_A );
        
        wp_send_json_success( array( 'issues' => $issues ) );
    }
    
    /**
     * AJAX: Mute an issue
     */
    public static function ajax_mute_issue() {
        check_ajax_referer( 'rs_scan_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'royal-sentinel' ) ) );
        }
        
        $issue_id = intval( $_POST['issue_id'] );
        
        global $wpdb;
        $table = RS_Database::get_table_name('scan_issues');
        
        $wpdb->update( $table, 
            array( 'status' => 'muted' ),
            array( 'id' => $issue_id )
        );
        
        wp_send_json_success( array( 'message' => __( 'Issue muted', 'royal-sentinel' ) ) );
    }
    
    /**
     * AJAX: Delete scan
     */
    public static function ajax_delete_scan() {
        check_ajax_referer( 'rs_scan_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'royal-sentinel' ) ) );
        }
        
        $scan_id = intval( $_POST['scan_id'] );
        
        global $wpdb;
        $history_table = RS_Database::get_table_name('scan_history');
        $issues_table = RS_Database::get_table_name('scan_issues');
        
        $wpdb->delete( $issues_table, array( 'scan_id' => $scan_id ) );
        $wpdb->delete( $history_table, array( 'id' => $scan_id ) );
        
        wp_send_json_success( array( 'message' => __( 'Scan deleted', 'royal-sentinel' ) ) );
    }
    
    /**
     * Mark scan as completed
     */
    public static function complete_scan( $scan_id ) {
        global $wpdb;
        $table = RS_Database::get_table_name('scan_history');
        
        $wpdb->update( $table, 
            array( 'status' => 'completed' ),
            array( 'id' => $scan_id )
        );
    }
    
    /**
     * Get last scan
     */
    public static function get_last_scan() {
        global $wpdb;
        $table = RS_Database::get_table_name('scan_history');
        
        return $wpdb->get_row( "SELECT * FROM $table WHERE status = 'completed' ORDER BY scan_date DESC LIMIT 1" );
    }
}

// Initialize
RS_Scan_Engine::init();
