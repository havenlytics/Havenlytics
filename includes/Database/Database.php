<?php
/**
 * Service container / manager for database-related classes
 *
 * Central place to register and instantiate classes (custom post types,
 * taxonomies, etc.) used by the plugin. This manager:
 *  - exposes the list of service class names via get_services()
 *  - instantiates each class safely
 *  - stores instances so they are retained for the request lifecycle
 *
 * @package HvnlyNab\Database
 */

namespace HvnlyNab\Database;

if ( ! defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Class Database
 *
 * Lightweight service container that boots plugin services.
 *
 * @since 2.0.0
 */
class Database {

    /**
     * Hold instantiated service objects so they are retained and reusable.
     *
     * @var array<string, object>
     */
    protected static $instances = array();

    /**
     * Flag to prevent infinite recursion
     *
     * @var bool
     */
    private static $registering = false;

    /**
     * Service dependencies map
     *
     * @var array
     */
    private static $dependencies = array();

    /**
     * Service priority order
     *
     * @var array
     */
    private static $priorities = array();

    /**
     * Database constructor.
     *
     * When the plugin initializes, create & register services.
     */
    public function __construct() {
        // Prevent recursion
        if (self::$registering) {
            return;
        }

        self::$registering = true;
        self::register_services();
        self::$registering = false;
    }

    /**
     * Return an array of fully-qualified service class names to instantiate.
     *
     * Now supports advanced configuration with priorities and dependencies.
     *
     * @return array Array of FQCNs or advanced config arrays.
     */
    public static function get_services(): array {
        /**
         * Filter: hvnly_database_services
         *
         * Allows third-party plugins to modify database services.
         * Supports two formats:
         * 1. Simple array of class names (backward compatible)
         * 2. Advanced array with priority, dependencies, and conditions
         */
        $services = apply_filters('hvnly_database_services', array(
            // Core services (highest priority = load first)
            array(
                'class' => Base\FieldRegistry::class,
                'priority' => 5,
                'singleton' => true,
            ),

            // Custom Post Types (priority 10)
            array(
                'class' => Custom_Posts\HavenlyticsNab::class,
                'priority' => 10,
            ),
            array(
                'class' => \HvnlyNab\Agent\AgentBootstrap::class,
                'priority' => 11,
                'singleton' => true,
                'method' => 'get_instance',
                'dependencies' => array( Custom_Posts\HavenlyticsNab::class ),
            ),

            // Custom Taxonomies (priority 15 - depend on post type)
            array(
                'class' => Custom_Taxonomy\Property_Departments::class,
                'priority' => 15,
                'dependencies' => array( Custom_Posts\HavenlyticsNab::class ),
            ),
            array(
                'class' => Custom_Taxonomy\Property_Types::class,
                'priority' => 15,
                'dependencies' => array( Custom_Posts\HavenlyticsNab::class ),
            ),
            array(
                'class' => Custom_Taxonomy\Property_Features::class,
                'priority' => 15,
                'dependencies' => array( Custom_Posts\HavenlyticsNab::class ),
            ),
            array(
                'class' => Custom_Taxonomy\Property_Locations::class,
                'priority' => 15,
                'dependencies' => array( Custom_Posts\HavenlyticsNab::class ),
            ),
            array(
                'class' => Custom_Taxonomy\Property_Status::class,
                'priority' => 15,
                'dependencies' => array( Custom_Posts\HavenlyticsNab::class ),
            ),
            array(
                'class' => Custom_Taxonomy\Property_Badges::class,
                'priority' => 15,
                'dependencies' => array( Custom_Posts\HavenlyticsNab::class ),
            ),
            array(
                'class' => Custom_Taxonomy\Property_Tags::class,
                'priority' => 15,
                'dependencies' => array( Custom_Posts\HavenlyticsNab::class ),
            ),

            // Custom Metabox (priority 20)
            array(
                'class' => Custom_Metabox\Havenlytics_Type::class,
                'priority' => 20,
            ),

            // Custom Widgets (priority 25)
            array(
                'class' => Custom_Widgets\Register_Widgets::class,
                'priority' => 25,
            ),
        ));

        // Backward compatibility: Convert simple class names to metadata format
        $compat_services = array();
        foreach ($services as $service) {
            if (is_string($service)) {
                $compat_services[] = array( 'class' => $service );
            } else {
                $compat_services[] = $service;
            }
        }

        return $compat_services;
    }

    /**
     * Instantiate & register all services returned by get_services().
     *
     * Now with priority sorting and dependency resolution.
     *
     * @return void
     */
    public static function register_services(): void {
        $services = self::get_services();

        // Sort by priority (lower = load first)
        usort($services, function ( $a, $b ) {
            $pri_a = $a['priority'] ?? 10;
            $pri_b = $b['priority'] ?? 10;
            return $pri_a - $pri_b;
        });

        // First pass: Register service metadata for dependency checking
        foreach ($services as $service_config) {
            $class                        = $service_config['class'];
            self::$dependencies[ $class ] = $service_config['dependencies'] ?? array();
            self::$priorities[ $class ]   = $service_config['priority'] ?? 10;
        }

        // Second pass: Instantiate with dependency resolution
        foreach ($services as $service_config) {
            $class = $service_config['class'];

            // Check condition if exists
            if (isset($service_config['condition']) && is_callable($service_config['condition'])) {
                if ( ! $service_config['condition']()) {
                    continue;
                }
            }

            self::instantiate($class, $service_config);
        }

        /**
         * Action: hvnly_database_services_registered
         *
         * Fired after all database services are registered.
         */
        do_action('hvnly_database_services_registered', self::$instances);
    }

    /**
     * Instantiate a single service class safely.
     *
     * @param string $class Fully-qualified class name.
     * @param array $config Optional service configuration.
     * @return object|null The instantiated object or null if class does not exist.
     */
    private static function instantiate( string $class, array $config = array() ) {
        if (isset(self::$instances[ $class ])) {
            return self::$instances[ $class ];
        }

        if ( ! class_exists($class)) {
            return null;
        }

        // Check dependencies
        $dependencies = $config['dependencies'] ?? self::$dependencies[ $class ] ?? array();
        foreach ($dependencies as $dep_class) {
            if ( ! isset(self::$instances[ $dep_class ])) {
                return null;
            }
        }

        try {
            // Handle singleton pattern
            if (isset($config['singleton']) && $config['singleton']) {
                $method = $config['method'] ?? 'get_instance';

                if (method_exists($class, $method)) {
                    $service = call_user_func(array( $class, $method ));
                } elseif (method_exists($class, 'instance')) {
                    $service = $class::instance();
                } else {
                    $service = new $class();
                }
            } else {
                $service = new $class();
            }

            self::$instances[ $class ] = $service;

            /**
             * Action: hvnly_database_service_instantiated
             *
             * Fired when a database service is instantiated.
             *
             * @param string $class Service class name
             * @param object $service Service instance
             */
            do_action('hvnly_database_service_instantiated', $class, $service);

        } catch (\Throwable $e) {
            return null;
        }

        return $service;
    }

    /**
     * Retrieve the cached instance of a service (if instantiated).
     *
     * @param string $class Fully-qualified class name.
     * @return object|null Instance if available, null otherwise.
     */
    public static function get_instance( string $class ) {
        return self::$instances[ $class ] ?? null;
    }

    /**
     * Get FieldRegistry instance
     *
     * @return Base\FieldRegistry|null
     */
    public static function get_field_registry() {
        $registry = self::get_instance(Base\FieldRegistry::class);
        if ( ! $registry) {
            $registry                                     = new Base\FieldRegistry();
            self::$instances[ Base\FieldRegistry::class ] = $registry;
        }
        return $registry;
    }

    /**
     * Get field type handler from registry
     *
     * @param string $type Field type identifier
     * @return object|null Field handler instance
     */
    public static function get_field_handler( $type ) {
        $registry = self::get_field_registry();
        return $registry ? $registry->get_handler($type) : null;
    }

    /**
     * Check if a service is registered
     *
     * @param string $class Service class name
     * @return bool
     */
    public static function has_service( string $class ): bool {
        return isset(self::$instances[ $class ]);
    }

    /**
     * Get all registered services
     *
     * @return array
     */
    public static function get_all_services(): array {
        return self::$instances;
    }

    /**
     * Get service dependencies
     *
     * @param string $class Service class name
     * @return array
     */
    public static function get_service_dependencies( string $class ): array {
        return self::$dependencies[ $class ] ?? array();
    }

    /**
     * Get service priority
     *
     * @param string $class Service class name
     * @return int
     */
    public static function get_service_priority( string $class ): int {
        return self::$priorities[ $class ] ?? 10;
    }

    /**
     * Reset all services (useful for testing)
     *
     * @return void
     */
    public static function reset(): void {
        self::$instances    = array();
        self::$dependencies = array();
        self::$priorities   = array();
        self::$registering  = false;
    }
}
