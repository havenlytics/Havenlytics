<?php
/**
 * Havenlytics Controller
 *
 * @package HvnlyNab\Api
 * @since 2.0.0
 */

namespace HvnlyNab\Api;

use HvnlyNab\Api\Type\Builders\DnDSections;
use HvnlyNab\Api\Type\Builders\DnDCardBuilder;
use HvnlyNab\Api\Type\Builders\MetaCleanup;
use HvnlyNab\Api\Type\Settings\PluginSettingsAPI;
use HvnlyNab\Api\Type\Settings\PluginInfoAPI;
use HvnlyNab\Api\Type\Settings\PriceOnCallTextAPI;
use HvnlyNab\Api\Type\Analytics\AnalyticsAPI;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Class Controller
 *
 * Main REST API controller for registering custom endpoints.
 *
 * @since 2.0.0
 */
class Controller {

    /**
     * Array of controller classes to register.
     *
     * @var array
     */
    protected array $class_map;

    /**
     * Instantiated controllers
     *
     * @var array
     */
    private array $controllers = array();

    public function __construct() {
        // Stop if REST API is not available
        if ( ! class_exists('WP_REST_Server')) {
            return;
        }

        // Map of API classes
        $this->class_map = apply_filters(
            'hvnlynab_rest_api_class_map',
            array(
                DnDSections::class,
                DnDCardBuilder::class,
                MetaCleanup::class,
                PluginSettingsAPI::class,
                PluginInfoAPI::class,
                PriceOnCallTextAPI::class,
                AnalyticsAPI::class,
            )
        );

        // Register REST API routes
        add_action('rest_api_init', array( $this, 'register_rest_routes' ), 10);

        // Support plain permalink API requests
        add_filter('rest_request_before_callbacks', array( $this, 'rest_request_filter' ), 10, 3);
    }

    /**
     * Register REST API routes for all mapped controllers.
     */
    public function register_rest_routes(): void {
        foreach ($this->class_map as $controller_class) {
            $controller_instance                    = new $controller_class();
            $this->controllers[ $controller_class ] = $controller_instance;

            if (method_exists($controller_instance, 'routes')) {
                $controller_instance->routes();
            }
        }
    }

    /**
     * Support plain permalink for REST API requests.
     *
     * @param mixed $response
     * @param mixed $handler
     * @param \WP_REST_Request $request
     * @return \WP_REST_Request
     */
    public function rest_request_filter( $response, $handler, $request ) {
        $permalink_structure = get_option('permalink_structure');
        if ($permalink_structure === '') {
            $params = $request->get_params();
            if (isset($params['rest_route'])) {
                $query_string = wp_parse_url($params['rest_route'], PHP_URL_QUERY);
                parse_str($query_string, $parsed_args);
                foreach ($parsed_args as $key => $val) {
                    $request->set_param($key, $val);
                }
            }
        }

        return $request;
    }
}
