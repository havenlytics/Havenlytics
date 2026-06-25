<?php

/**
 * Frontend service handler for Havenlytics
 *
 * @package     Havenlytics
 * @subpackage  Includes
 * @copyright   Copyright (c) 2024, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Service Manager Class
 * 
 * Handles service registration, dependency management, and conditional loading.
 * Maintains full backward compatibility with existing code.
 *
 * @since 2.0.0
 */
class Frontend
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Registered service instances
     *
     * @var array
     */
    private $services = [];

    /**
     * Whether functions have been loaded
     *
     * @var bool
     */
    private $functions_loaded = false;

    /**
     * Flag to prevent infinite recursion
     *
     * @var bool
     */
    private static $registering = false;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        // Prevent recursive construction when helpers call get_instance() mid-bootstrap.
        if (self::$registering) {
            return;
        }

        self::$registering = true;
        self::$instance    = $this;

        self::register_services();
        $this->init_hooks();
        $this->load_template_functions();

        self::$registering = false;
    }

    /**
     * Get services list - BACKWARD COMPATIBLE VERSION
     * Returns simple array of class names like original
     *
     * @return array
     */
    public static function get_services()
    {
        /**
         * Filter: hvnly_frontend_services
         * 
         * Allows 3rd party plugins to modify frontend services.
         */
        return apply_filters('hvnly_frontend_services', array(
            Frontend\TemplateLoader::class,
            Frontend\Assets::class,
            Frontend\AjaxHandler::class,
            Frontend\SidebarAjaxHandler::class,
            Frontend\PropertyViewTracker::class,
            Frontend\PropertyCardRenderer::class, 
            Frontend\PropertySingleRenderer::class, 
            Frontend\EnhancedTemplateLoader::class,
            Frontend\LayoutManager::class,
            Frontend\Shortcodes\PropertySearch::class,
            Frontend\Shortcodes\PropertyGrid::class,
            Frontend\Shortcodes\PropertyList::class,
            Frontend\Shortcodes\PropertyAgents::class,
            Frontend\Shortcodes\PropertyAgencies::class,
            Frontend\Shortcodes\FeaturedProperties::class,
            Frontend\Shortcodes\LegacyCompatibility::class,
            Frontend\Shortcodes\CacheInvalidation::class,
            Frontend\Shortcodes\Assets::class,
            // ========== CONTACT AGENT (scaffold) ==========
            ContactAgent\ContactAgentBootstrap::class,
            // ========== QUERY MANAGER ==========
            Frontend\Query\PropertyQueryManager::class,
            // ========== ELEMENTOR INTEGRATION ==========
            Integrations\Elementor\ElementorIntegration::class,
        ));
    }

    /**
     * Register services - EXACTLY LIKE ORIGINAL
     */
    public static function register_services()
    {
        if (null === self::$instance) {
            return;
        }

        $instance = self::$instance;
        $services = self::get_services();
        
        foreach ($services as $class) {
            // Skip Elementor integration if Elementor is not active
            if ($class === Integrations\Elementor\ElementorIntegration::class) {
                if (!self::is_elementor_active()) {
                    continue;
                }
            }
            $instance->instantiate($class);
        }
        
        do_action('hvnly_frontend_services_registered');
    }

    /**
     * Check if Elementor is active
     *
     * @return bool
     */
    private static function is_elementor_active(): bool
    {
        if (did_action('elementor/loaded')) {
            return true;
        }
        
        if (class_exists('\Elementor\Plugin')) {
            return true;
        }
        
        if (defined('ELEMENTOR_VERSION')) {
            return true;
        }
        
        return false;
    }

    /**
     * Instantiate a class - EXACTLY LIKE ORIGINAL
     *
     * @param string $class Class name.
     * @return object|null
     */
    private function instantiate($class)
    {
        if (!class_exists($class)) {
            return null;
        }
        
        // Special handling for LayoutManager to use singleton
        if ($class === Frontend\LayoutManager::class) {
            return Frontend\LayoutManager::instance();
        }

        // Special handling for Query Manager to use singleton
        if ($class === Frontend\Query\PropertyQueryManager::class) {
            $service = $class::get_instance();
            $this->services[$class] = $service;
            return $service;
        }

        // Special handling for ElementorIntegration to use get_instance()
        if ($class === Integrations\Elementor\ElementorIntegration::class) {
            $service = $class::get_instance();
            $this->services[$class] = $service;
            return $service;
        }
        
        // Prevent instantiating self recursively
        if ($class === self::class) {
            return null;
        }
        
        $service = new $class();
        
        // Store service for later retrieval
        $this->services[$class] = $service;
        
        return $service;
    }

    /**
     * Initialize hooks - EXACTLY LIKE ORIGINAL
     */
    private function init_hooks()
    {
        add_action('init', array($this, 'load_template_functions'));
    }

    /**
     * Load template functions - EXACTLY LIKE ORIGINAL
     */
    public function load_template_functions()
    {
        if ($this->functions_loaded) {
            return;
        }

        if ( function_exists( 'hvnly_load_template_functions' ) ) {
            hvnly_load_template_functions();
            $this->functions_loaded = true;
            return;
        }
        
        $functions_files = array(
            'field-options.php',
            'template-functions.php',
            'template-hook-functions.php',
            'template-hooks.php',
            'property-functions.php',
            'agent-functions.php',
            'layout-functions.php',
            'utility-functions.php',
            'ajax-functions.php',
            'shortcode-functions.php',
            'filter-sidebar-functions.php',
            'top-search-functions.php',
            'main-top-search-functions.php',
            'map-functions.php',
            'contact-agent-functions.php',
        );

        foreach ($functions_files as $file) {
            $file_path = HVNLYNAB_INCLUDES . '/Functions/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }

        if ( function_exists( 'hvnly_register_template_function_fallbacks' ) ) {
            hvnly_register_template_function_fallbacks();
        }
        
        $this->functions_loaded = true;
    }

    /**
     * Get a registered service instance
     *
     * @param string $class Class name
     * @return object|null
     */
    public function get_service($class)
    {
        return $this->services[$class] ?? null;
    }

    /**
     * Get the registered template loader service instance.
     *
     * @return Frontend\EnhancedTemplateLoader|Frontend\TemplateLoader|null
     */
    public function get_template_loader_service()
    {
        $loader = $this->get_service(Frontend\EnhancedTemplateLoader::class);
        if ( $loader ) {
            return $loader;
        }

        return $this->get_service(Frontend\TemplateLoader::class);
    }

    /**
     * Get Elementor integration instance
     *
     * @return Integrations\Elementor\ElementorIntegration|null
     */
    public function get_elementor_integration()
    {
        return $this->get_service(Integrations\Elementor\ElementorIntegration::class);
    }

    /**
     * Get Query Manager instance
     *
     * @return Frontend\Query\PropertyQueryManager|null
     */
    public function get_query_manager()
    {
        return $this->get_service(Frontend\Query\PropertyQueryManager::class);
    }

    /**
     * Magic method for backward compatibility
     */
    public function __get($name)
    {
        $legacy_map = [
            'template_loader' => Frontend\TemplateLoader::class,
            'assets' => Frontend\Assets::class,
            'ajax_handler' => Frontend\AjaxHandler::class,
            'sidebar_ajax_handler' => Frontend\SidebarAjaxHandler::class,
            'view_tracker' => Frontend\PropertyViewTracker::class,
            'card_renderer' => Frontend\PropertyCardRenderer::class,
            'single_renderer' => Frontend\PropertySingleRenderer::class,
            'layout_manager' => Frontend\LayoutManager::class,
            'query_manager' => Frontend\Query\PropertyQueryManager::class,
            'elementor_integration' => Integrations\Elementor\ElementorIntegration::class,
        ];
        
        if (isset($legacy_map[$name])) {
            return $this->get_service($legacy_map[$name]);
        }
        
        return null;
    }

    /**
     * Magic isset for backward compatibility
     */
    public function __isset($name)
    {
        $legacy_map = [
            'template_loader' => Frontend\TemplateLoader::class,
            'assets' => Frontend\Assets::class,
            'ajax_handler' => Frontend\AjaxHandler::class,
            'sidebar_ajax_handler' => Frontend\SidebarAjaxHandler::class,
            'view_tracker' => Frontend\PropertyViewTracker::class,
            'card_renderer' => Frontend\PropertyCardRenderer::class,
            'single_renderer' => Frontend\PropertySingleRenderer::class,
            'layout_manager' => Frontend\LayoutManager::class,
            'query_manager' => Frontend\Query\PropertyQueryManager::class,
            'elementor_integration' => Integrations\Elementor\ElementorIntegration::class,
        ];
        
        return isset($legacy_map[$name]) && isset($this->services[$legacy_map[$name]]);
    }
}

/**
 * Legacy instantiation for backward compatibility
 */
if (!function_exists('hvnly_frontend_init')) {
    function hvnly_frontend_init() {
        return Frontend::get_instance();
    }
}

// Initialize frontend
if (!defined('HVNLY_FRONTEND_INITIALIZED')) {
    define('HVNLY_FRONTEND_INITIALIZED', true);
}