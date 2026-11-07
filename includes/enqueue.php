<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_enqueue_scripts', 'rs_enqueue_assets' );

function rs_enqueue_assets( $hook ) {
    // 1. Check: শুধু আমাদের প্লাগিনের পেজ কিনা
    if ( strpos( $hook, 'royal-sentinel' ) === false && strpos( $hook, 'rs-settings' ) === false && strpos( $hook, 'rs-cloudflare' ) === false && strpos( $hook, 'rs-advance-security' ) === false && strpos( $hook, 'rs-tweaks' ) === false ) {
        return;
    }

    // লাইব্রেরি ফোল্ডারের বেস URL
    $lib_url = ROYALSENTINEL_PLUGIN_URL . 'lib/';
    $assets_url = ROYALSENTINEL_PLUGIN_URL . 'assets/';

    /**
     * -----------------------------------------------------------
     * 2. CSS Load (All from Local Lib Folder)
     * -----------------------------------------------------------
     */
    
    // Color Picker
    wp_enqueue_style( 'wp-color-picker' );

    // DataTables CSS
    wp_enqueue_style( 'rs-dt-css', $lib_url . 'datatables/jquery.dataTables.min.css', array(), ROYALSENTINEL_VERSION );
    
    // SweetAlert2 CSS
    wp_enqueue_style( 'rs-swal-css', $lib_url . 'sweetalert2/sweetalert2.min.css', array(), ROYALSENTINEL_VERSION );
    
    // Select2 CSS
    wp_enqueue_style( 'rs-select2-css', $lib_url . 'select2/select2.min.css', array(), '4.1.0' );
    
    // Plugin Custom Style (এটা assets ফোল্ডারেই থাকে সাধারণত)
    wp_enqueue_style( 'rs-style', $assets_url . 'css/style.css', array(), ROYALSENTINEL_VERSION );


    /**
     * -----------------------------------------------------------
     * 3. JS Load (All from Local Lib Folder)
     * -----------------------------------------------------------
     */

    // Color Picker
    wp_enqueue_script( 'wp-color-picker' );

    // DataTables JS
    wp_enqueue_script( 'rs-dt-js', $lib_url . 'datatables/jquery.dataTables.min.js', array('jquery'), ROYALSENTINEL_VERSION, true );
    
    // SweetAlert2 JS
    wp_enqueue_script( 'rs-swal-js', $lib_url . 'sweetalert2/sweetalert2.all.min.js', array(), ROYALSENTINEL_VERSION, true );
    
    // Chart.js
    wp_enqueue_script( 'rs-chart-js', $lib_url . 'chart/chart.js', array(), ROYALSENTINEL_VERSION, true );
    
    // Select2 JS
    wp_enqueue_script( 'rs-select2-js', $lib_url . 'select2/select2.min.js', array('jquery'), '4.1.0', true );

    // 4. Global Script for AJAX URL
    wp_add_inline_script( 'rs-swal-js', 'var rs_ajax_url = "' . admin_url('admin-ajax.php') . '";' );

    // 5. Global Admin JS (Dark Mode etc.)
    wp_enqueue_script( 'rs-admin-js', $assets_url . 'js/rs-admin.js', array(), ROYALSENTINEL_VERSION, true );
}