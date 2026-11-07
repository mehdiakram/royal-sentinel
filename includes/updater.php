<?php
/**
 * Royal Sentinel Plugin Updater
 *
 * Handles checking for updates from a custom remote server.
 *
 * @package Royal_Sentinel
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Royal_Sentinel_Updater {

    private $plugin_slug;
    private $current_version;
    private $update_json_url;
    private $plugin_file;

    /**
     * Constructor.
     *
     * @param string $plugin_file     Full path to the main plugin file.
     * @param string $update_json_url URL to the remote JSON file containing update info.
     */
    public function __construct( $plugin_file, $update_json_url ) {
        
        $this->plugin_file     = $plugin_file;
        $this->plugin_slug     = plugin_basename( $this->plugin_file );
        $this->update_json_url = $update_json_url;

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data           = get_plugin_data( $this->plugin_file );
        $this->current_version = $plugin_data['Version'];

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_api_details' ), 10, 3 );
    }

    /**
     * Check for plugin updates from the remote server.
     *
     * @param object $transient The update_plugins transient.
     * @return object Modified transient with update info if available.
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $response = wp_remote_get( $this->update_json_url, array( 'timeout' => 10, 'sslverify' => false ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return $transient;
        }

        $update_data = json_decode( wp_remote_retrieve_body( $response ) );

        if ( is_object( $update_data ) && version_compare( $this->current_version, $update_data->version, '<' ) ) {
            $transient->response[ $this->plugin_slug ] = (object) array(
                'slug'        => basename( $this->plugin_slug, '.php' ),
                'plugin'      => $this->plugin_slug,
                'new_version' => $update_data->version,
                'package'     => $update_data->download_url,
                'url'         => $update_data->homepage,
            );
        }

        return $transient;
    }

    /**
     * Provide plugin API details for the "View Details" popup.
     *
     * @param mixed  $result The result object or array.
     * @param string $action The type of API action.
     * @param object $args   The plugin API arguments.
     * @return mixed Modified result with plugin details.
     */
    public function plugin_api_details( $result, $action, $args ) {
        
        if ( isset( $args->slug ) && $args->slug === basename( $this->plugin_slug, '.php' ) ) {
            
            $response = wp_remote_get( $this->update_json_url, array( 'timeout' => 10, 'sslverify' => false ) );

            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
                return $result;
            }

            $update_data = json_decode( wp_remote_retrieve_body( $response ) );

            if ( is_object( $update_data ) ) {
                $plugin_data = get_plugin_data( $this->plugin_file );
                $result      = (object) array(
                    'name'          => $plugin_data['Name'],
                    'slug'          => basename( $this->plugin_slug, '.php' ),
                    'version'       => $update_data->version,
                    'author'        => $plugin_data['Author'],
                    'homepage'      => $update_data->homepage,
                    'download_link' => $update_data->download_url,
                    'sections'      => (array) ( $update_data->sections ?? array() ),
                    'banners'       => (array) ( $update_data->banners ?? array() ),
                );
            }
        }
        return $result;
    }
}
