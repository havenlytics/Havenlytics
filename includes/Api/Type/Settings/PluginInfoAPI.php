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
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Info API Class
 * 
 * @since 2.1.1
 */
class PluginInfoAPI
{
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
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    /**
     * Register REST API routes
     *
     * @since 2.1.1
     */
    public function routes()
    {
        register_rest_route($this->namespace, '/' . $this->route_base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_plugin_info'],
            'permission_callback' => static function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * Get plugin information (admin settings fallback only).
     *
     * @since 2.1.1
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_plugin_info($request)
    {
        unset($request);

        return rest_ensure_response([
            'success' => true,
            'version' => HVNLYNAB_VERSION,
            'name' => 'Havenlytics',
            'api_namespace' => $this->namespace,
        ]);
    }
}