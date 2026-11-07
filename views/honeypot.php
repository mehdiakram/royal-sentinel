<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$success_script = '';
$rs_hp_nonce = wp_create_nonce('rs_honeypot_nonce');

// Save Settings Logic
if ( isset( $_POST['rs_save_honeypot'] ) && isset( $_POST['_wpnonce'] ) ) {
    if ( wp_verify_nonce( $_POST['_wpnonce'], 'rs_honeypot_save' ) ) {
        
        // Field Name
        if ( isset( $_POST['rs_honeypot_field_name'] ) ) {
            update_option( 'rs_honeypot_field_name', sanitize_key( $_POST['rs_honeypot_field_name'] ) );
        }
        
        // Settings
        $settings = array(
            'error_message'        => isset( $_POST['error_message'] ) ? sanitize_text_field( $_POST['error_message'] ) : '',
            'time_check_enabled'   => isset( $_POST['time_check_enabled'] ) ? 1 : 0,
            'time_min_seconds'     => isset( $_POST['time_min_seconds'] ) ? intval( $_POST['time_min_seconds'] ) : 3,
            'time_max_seconds'     => isset( $_POST['time_max_seconds'] ) ? intval( $_POST['time_max_seconds'] ) : 0,
            'auto_block_enabled'   => isset( $_POST['auto_block_enabled'] ) ? 1 : 0,
            'auto_block_threshold' => isset( $_POST['auto_block_threshold'] ) ? intval( $_POST['auto_block_threshold'] ) : 5,
            'retention_days'       => isset( $_POST['retention_days'] ) ? intval( $_POST['retention_days'] ) : 30,
            'test_widget_disabled' => isset( $_POST['test_widget_disabled'] ) ? 1 : 0,
        );
        update_option( 'rs_honeypot_settings', $settings );
        
        $success_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: '" . esc_js( __( 'Settings Saved!', 'royal-sentinel' ) ) . "',
                    text: '" . esc_js( __( 'Honeypot settings updated successfully.', 'royal-sentinel' ) ) . "',
                    showConfirmButton: false,
                    timer: 1500
                });
            });
        </script>";
    }
}

// Save Integrations
if ( isset( $_POST['rs_save_integrations'] ) && isset( $_POST['_wpnonce'] ) ) {
    if ( wp_verify_nonce( $_POST['_wpnonce'], 'rs_honeypot_save' ) ) {
        $integrations = array();
        if ( isset( $_POST['integrations'] ) && is_array( $_POST['integrations'] ) ) {
            foreach ( $_POST['integrations'] as $key => $value ) {
                $integrations[ sanitize_key( $key ) ] = 1;
            }
        }
        update_option( 'rs_honeypot_integrations', $integrations );
        
        $success_script = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'success',
                    title: '" . esc_js( __( 'Integrations Saved!', 'royal-sentinel' ) ) . "',
                    showConfirmButton: false,
                    timer: 1500
                });
            });
        </script>";
    }
}

// Load current settings
$rs_hp_settings = get_option( 'rs_honeypot_settings', array() );
$rs_hp_field_name = get_option( 'rs_honeypot_field_name', 'rs_hp_field' );
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

$tabs = array(
    'settings'     => array( 'label' => __( 'Settings', 'royal-sentinel' ),     'icon' => 'dashicons-admin-generic' ),
    'integrations' => array( 'label' => __( 'Integrations', 'royal-sentinel' ), 'icon' => 'dashicons-admin-plugins' ),
    'statistics'   => array( 'label' => __( 'Statistics', 'royal-sentinel' ),   'icon' => 'dashicons-chart-bar' ),
    'ip-blocking'  => array( 'label' => __( 'IP Blocking', 'royal-sentinel' ),  'icon' => 'dashicons-shield-alt' ),
    'logs'         => array( 'label' => __( 'Logs', 'royal-sentinel' ),         'icon' => 'dashicons-list-view' ),
);
?>

<div class="wrap">
    <div class="rs-page-header dark-gradient">
        <div class="rs-header-content">
            <h1 class="rs-page-title">
                <div class="rs-process-icon">🍯</div>
                <?php esc_html_e( 'Honeypot Protection', 'royal-sentinel' ); ?>
            </h1>
            <p class="rs-page-subtitle"><?php esc_html_e( 'Block spam bots using invisible honeypot traps and time-based validation.', 'royal-sentinel' ); ?></p>
        </div>
    </div>

    <h2 class="nav-tab-wrapper">
        <?php foreach ( $tabs as $slug => $info ): ?>
            <a href="?page=rs-honeypot&tab=<?php echo esc_attr($slug); ?>" 
               class="nav-tab <?php echo $active_tab == $slug ? 'nav-tab-active' : ''; ?>">
               <span class="dashicons <?php echo esc_attr($info['icon']); ?>" style="vertical-align:text-bottom; margin-right:5px;"></span>
               <?php echo esc_html( $info['label'] ); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <div style="background:#fff; padding:30px; border:1px solid #ccd0d4; border-top:none; box-shadow: 0 1px 3px rgba(0,0,0,.04);">
        <?php 
            $file_path = ROYALSENTINEL_PLUGIN_DIR . 'views/honeypot/' . $active_tab . '.php';
            
            if ( file_exists( $file_path ) ) {
                include $file_path;
            } else {
                echo '<p style="color:red;">' . esc_html__( 'Error: Tab file not found.', 'royal-sentinel' ) . '</p>';
            }
        ?>
    </div>
</div>

<?php echo $success_script; ?>

<style>
.rs-hp-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    transition: box-shadow 0.2s;
}
.rs-hp-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.rs-hp-card h4 {
    margin: 0 0 10px;
    color: #333;
}
.rs-hp-card p {
    margin: 0;
    color: #666;
    font-size: 13px;
}
.rs-hp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
}
.rs-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}
.rs-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.rs-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
}
.rs-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
}
input:checked + .rs-slider {
    background-color: #27ae60;
}
input:checked + .rs-slider:before {
    transform: translateX(24px);
}
.rs-slider.round {
    border-radius: 26px;
}
.rs-slider.round:before {
    border-radius: 50%;
}
.rs-section-box {
    background: #f9f9f9;
    padding: 20px;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    margin-bottom: 20px;
}
.rs-section-title {
    margin-top: 0;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
    font-size: 15px;
    font-weight: 600;
}
</style>
