<?php
/**
 * Plugin Info REST API Endpoint
 *
 * @package HvnlyNab\Api\Type\Settings
 * @since 2.1.1
 */

namespace HvnlyNab\Api\Type\Settings;

use WP_REST_Request;
use WP_REST_Response;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Info API Class
 *
 * @since 2.1.1
 */
class PluginInfoAPI {

    /**
     * REST API namespace
     *
     * @var string
     */
    private $namespace = 'hvnlynab/v1';

    /**
     * REST API route base
     *
     * @var string
     */
    private $route_base = 'plugin-info';

    /**
     * Constructor - Register REST API routes
     *
     * @since 2.1.1
     */
    public function __construct() {
        add_action('rest_api_init', array( $this, 'routes' ));
    }

    /**
     * Register REST API routes
     *
     * @since 2.1.1
     */
    public function routes() {
        register_rest_route($this->namespace, '/' . $this->route_base, array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_plugin_info' ),
            'permission_callback' => static function () {
                return current_user_can('manage_options');
            },
        ));
    }

    /**
     * Get plugin information (admin settings fallback only).
     *
     * @since 2.1.1
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_plugin_info( $request ) {
        unset($request);

        $version = self::resolve_plugin_version();

        return rest_ensure_response(array(
            'success' => true,
            'version' => $version,
            'name' => 'Havenlytics',
            'api_namespace' => $this->namespace,
        ));
    }

    /**
     * Resolve installed plugin version from the plugin header / constant.
     *
     * @since 3.6.1
     * @return string
     */
    private static function resolve_plugin_version(): string {
        if ( defined( 'HVNLYNAB_FILE' ) && function_exists( 'get_plugin_data' ) ) {
            $data = get_plugin_data( HVNLYNAB_FILE, false, false );
            if ( is_array( $data ) && ! empty( $data['Version'] ) ) {
                return (string) $data['Version'];
            }
        }

        if ( ! function_exists( 'get_plugin_data' ) && defined( 'HVNLYNAB_FILE' ) && defined( 'ABSPATH' ) ) {
            $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
            if ( is_readable( $plugin_file ) ) {
                require_once $plugin_file;
                if ( function_exists( 'get_plugin_data' ) ) {
                    $data = get_plugin_data( HVNLYNAB_FILE, false, false );
                    if ( is_array( $data ) && ! empty( $data['Version'] ) ) {
                        return (string) $data['Version'];
                    }
                }
            }
        }

        return defined( 'HVNLYNAB_VERSION' ) ? (string) HVNLYNAB_VERSION : '';
    }
}
