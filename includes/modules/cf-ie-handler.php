<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RS_CF_Import_Export {

    public function __construct() {
        add_action( 'admin_post_rs_export_ips', [ $this, 'handle_export' ] );
        add_action( 'admin_post_rs_import_ips', [ $this, 'handle_import' ] );
    }

    /**
     * Export Handler (CSV Download without Header)
     */
    public function handle_export() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        // ডাটা সংগ্রহ করা (ক্যাশ বা API থেকে)
        $data = rs_get_list_items();
        $items = $data['items'] ?? [];

        if ( empty( $items ) ) {
            wp_die( 'No IPs found to export.', 'Error' );
        }

        // CSV হেডার সেট করা
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=blocked-ips-' . date('Y-m-d') . '.csv' );

        $output = fopen( 'php://output', 'w' );

        // [CHANGED] এখানে হেডার রো (fputcsv $output, ['IP Address'...]) বাদ দেওয়া হয়েছে 
        // যাতে Cloudflare সরাসরি ডাটা রিড করতে পারে।

        foreach ( $items as $row ) {
            $created = isset($row['created_on']) ? $row['created_on'] : '';
            fputcsv( $output, [ $row['ip'], $row['comment'] ?? '', $created ] );
        }

        fclose( $output );
        exit;
    }

    /**
     * Import Handler (CSV Upload)
     */
    public function handle_import() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'rs_import_nonce', 'rs_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        if ( ! isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            $this->redirect_back( 'error', 'File upload failed.' );
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen( $file, 'r' );
        
        if ( $handle === false ) {
            $this->redirect_back( 'error', 'Could not open file.' );
        }

        $batch_data = [];
        $row_count = 0;
        
        // CSV রিড করা
        while ( ($data = fgetcsv($handle, 1000, ",")) !== FALSE ) {
            $ip = sanitize_text_field( $data[0] ?? '' );
            $comment = sanitize_text_field( $data[1] ?? 'Imported via CSV' );

            if ( filter_var($ip, FILTER_VALIDATE_IP) ) {
                $batch_data[] = [
                    'ip' => $ip,
                    'comment' => $comment
                ];
                $row_count++;
            }
        }
        fclose( $handle );

        if ( empty( $batch_data ) ) {
            $this->redirect_back( 'error', 'No valid IPs found in CSV.' );
        }

        $chunks = array_chunk($batch_data, 1000);
        $success = true;
        $error_msg = '';

        foreach ($chunks as $chunk) {
            $resp = rs_api_batch_add_ips($chunk);
            if ( ! $resp['success'] ) {
                $success = false;
                $error_msg = $resp['message'];
                break;
            }
        }

        delete_transient( 'rs_cached_ips' ); 

        if ( $success ) {
            $this->redirect_back( 'success', "$row_count IPs imported successfully." );
        } else {
            $this->redirect_back( 'error', "Import Failed: $error_msg" );
        }
    }

    private function redirect_back( $type, $msg ) {
        set_transient( 'rs_import_msg', ['type' => $type, 'text' => $msg], 60 );
        wp_redirect( admin_url( 'admin.php?page=rs-cloudflare&tab=iplist' ) );
        exit;
    }
}

new RS_CF_Import_Export();