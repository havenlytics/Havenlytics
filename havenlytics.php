<?php

/**
 * Havenlytics – Real Estate Listings, Property Search & Agent Workspace
 *
 * @package     havenlytics
 * @author      Havenlytics <support@havenlytics.com>
 * @link        https://havenlytics.com
 * @copyright   © Havenlytics
 * 
 * @wordpress-plugin
 * Plugin Name:       Havenlytics – Real Estate Listings, Property Search & Agent Workspace
 * Plugin URI:        https://wordpress.org/plugins/havenlytics/
 * Description:       Powerful WordPress real estate plugin with property listings, AJAX search, Migration Engine, CSV Import & Export, Gutenberg blocks, Agent Workspace, and multilingual support.
 * Version:           3.7.3
 * Author:            Havenlytics
 * Author URI:        https://havenlytics.com
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Tested up to:      7.0
 * Text Domain:       havenlytics
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access to WordPress files
defined('ABSPATH') || exit;

/**
 * Define plugin constants for paths, URLs, and configuration
 * These constants are used throughout the plugin for easy reference
 */
define('HVNLYNAB_VERSION', '3.7.3');
define('HVNLYNAB_FILE', __FILE__);
define('HVNLYNAB_BASENAME', plugin_basename(HVNLYNAB_FILE));
define('HVNLYNAB_SLUG', 'havenlytics');
define('HVNLYNAB_PATH', plugin_dir_path(HVNLYNAB_FILE));
define('HVNLYNAB_URL', plugin_dir_url(HVNLYNAB_FILE));
define('HVNLYNAB_ASSETS_PATH', HVNLYNAB_PATH . 'assets');
define('HVNLYNAB_ASSETS_URL', HVNLYNAB_URL . 'assets');
define('HVNLYNAB_BUILD_PATH', HVNLYNAB_PATH . 'build');
define('HVNLYNAB_BUILD_URL', HVNLYNAB_URL . 'build');
define('HVNLYNAB_INCLUDES', HVNLYNAB_PATH . 'includes');
define('HVNLYNAB_LANG_DIR', HVNLYNAB_PATH . 'languages');

/**
 * Migration shared types — must load before Composer autoloads handler classes.
 */
$hvnly_migration_bootstrap = HVNLYNAB_INCLUDES . '/Core/Migration/migration-bootstrap.php';
if ( is_readable( $hvnly_migration_bootstrap ) ) {
    require_once $hvnly_migration_bootstrap;
}

/**
 * Shared helpers (debug logging, etc.) — loaded before bootstrap/migrations.
 */
$hvnly_utility_functions = HVNLYNAB_INCLUDES . '/Functions/utility-functions.php';
if ( is_readable( $hvnly_utility_functions ) ) {
    require_once $hvnly_utility_functions;
}

$hvnly_ui_storage_i18n = HVNLYNAB_INCLUDES . '/Functions/ui-storage-i18n.php';
if ( is_readable( $hvnly_ui_storage_i18n ) ) {
    require_once $hvnly_ui_storage_i18n;
}

$hvnly_email_functions = HVNLYNAB_INCLUDES . '/Functions/email-functions.php';
if ( is_readable( $hvnly_email_functions ) ) {
    require_once $hvnly_email_functions;
}

$hvnly_field_options = HVNLYNAB_INCLUDES . '/Functions/field-options.php';
if ( is_readable( $hvnly_field_options ) ) {
    require_once $hvnly_field_options;
}

$hvnly_deletion_safety = HVNLYNAB_INCLUDES . '/Functions/deletion-safety.php';
if ( is_readable( $hvnly_deletion_safety ) ) {
    require_once $hvnly_deletion_safety;
}

$hvnly_template_bootstrap = HVNLYNAB_INCLUDES . '/Functions/template-bootstrap.php';
if ( is_readable( $hvnly_template_bootstrap ) ) {
    require_once $hvnly_template_bootstrap;
}

/**
 * Plugin Check late-escaping compatibility.
 *
 * Plugin Check hardcodes standard=WordPress for EscapeOutput and never loads
 * phpcs.xml.dist, so this bridge points late_escaping at
 * phpcs-rulesets/havenlytics-plugin-check-escape.xml (recognizes hvnly_esc_*_ui).
 */
$hvnly_plugin_check_escape = HVNLYNAB_INCLUDES . '/Compatibility/plugin-check-escape-compat.php';
if ( is_readable( $hvnly_plugin_check_escape ) ) {
	require_once $hvnly_plugin_check_escape;
}

/**
 * Template debugging constant - controls whether to use debug mode for templates
 * Can be overridden by defining HVNLYNAB_TEMPLATE_DEBUG elsewhere
 */
if (!defined('HVNLYNAB_TEMPLATE_DEBUG')) {
    define('HVNLYNAB_TEMPLATE_DEBUG', false);
}

/**
 * Load Composer autoloader for dependency management
 * Resolve from __DIR__ first so packaged releases work even when plugin_dir_path()
 * resolves differently (symlinks, versioned folders, or ZIP install edge cases).
 */
$hvnly_autoload = __DIR__ . '/vendor/autoload.php';
if ( ! is_readable( $hvnly_autoload ) && defined( 'HVNLYNAB_PATH' ) ) {
    $hvnly_autoload = HVNLYNAB_PATH . 'vendor/autoload.php';
}
if ( is_readable( $hvnly_autoload ) ) {
    require_once $hvnly_autoload;

    if ( function_exists( 'hvnly_load_migration_dependencies' ) ) {
        hvnly_load_migration_dependencies();
    }

    /**
     * Initialize error handler if core class is available
     * This provides centralized error handling throughout the plugin
     */
    if (class_exists(\HvnlyNab\Core\ErrorHandler::class)) {
        \HvnlyNab\Core\ErrorHandler::init(__FILE__);
    } else {
        /**
         * Display admin notice if core classes are missing
         * This typically indicates composer dependencies weren't installed
         */
        add_action('admin_notices', function () {
            printf(
                '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
                esc_html__('Havenlytics:', 'havenlytics'),
                esc_html__('Core class missing. Run <code>composer install</code>.', 'havenlytics')
            );

           
        });
        return;
    }
    /**
     * =======================================================
     * SIMPLE CONFLICT CHECKER
     * Shows notice if other real estate plugins are active
     * =======================================================
     */
    if (class_exists(\HvnlyNab\Core\ConflictChecker::class)) {
        // Initialize the conflict checker
        \HvnlyNab\Core\ConflictChecker::get_instance();
    }

    add_action(
        'plugins_loaded',
        static function () {
            if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentAjaxRegistrar::class ) ) {
                \HvnlyNab\ContactAgent\ContactAgentAjaxRegistrar::register();
            }
        },
        4
    );

    
} else {
    /**
     * Display admin notice if Composer autoloader is not found
     * This prevents fatal errors and guides users to install dependencies
     */
    add_action('admin_notices', function () {
        printf(
                '<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
                esc_html__('Havenlytics:', 'havenlytics'),
                esc_html__('Composer autoloader not found. Run composer install.', 'havenlytics')
            );
    });
    return;
}

/**
 * Import core classes for plugin functionality
 * These are the main components that handle plugin operation
 */

use HvnlyNab\Core\EnvironmentChecker;
use HvnlyNab\Core\Bootstrap;
use HvnlyNab\Core\Installer;
use HvnlyNab\Core\Scheduler;

/**
 * Final Class: HvnlyNab
 *
 * Combines:
 *  - Hvnly = Havenlytics (plugin name)
 *  - Nab   = Nababur (developer)
 *  - Profile URL: https://nababur.com
 * Main controller for the Real Estate Property Management plugin
 * - Singleton pattern (one instance)
 * - Manages constants, services, hooks, lifecycle
 * - Loads dependencies: autoload, includes, assets, REST
 *
 * @class HvnlyNab Main class for the plugin
 * @since 2.0.0
 */
final class HvnlyNab
{
    /**
     * Singleton instance storage
     * Ensures only one instance of the plugin class exists
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Service container for dependency injection
     * Stores instances of plugin services (admin, assets, API, etc.)
     *
     * @var array
     */
    private $container = [];

    /**
     * Minimum WordPress version required for plugin operation
     * Prevents activation on incompatible WordPress versions
     */
    private const MIN_WP = '6.3';

    /**
     * Minimum PHP version required for plugin operation
     * Ensures PHP features used by the plugin are available
     */
    private const MIN_PHP = '7.4';

    /**
     * Private constructor for Singleton pattern
     *
     * Performs environment checks and initializes plugin components
     * - Checks PHP and WordPress versions
     * - Loads dependencies
     * - Registers lifecycle hooks
     *
     * @since 2.0.0
     */
    private function __construct()
    {
        /**
         * Perform basic environment check before proceeding
         * If environment doesn't meet requirements, stop initialization
         */
        if (!$this->basic_environment_check()) {
            return;
        }

        /**
         * Perform full environment check using dedicated class
         * This handles more complex environment validation
         */
        if (!EnvironmentChecker::check()) {
            return;
        }

        /**
         * Register activation, deactivation, and uninstall hooks
         * These handle plugin lifecycle events
         */
        $this->register_lifecycle_hooks();

        /**
         * Flush rewrite rules after plugin is loaded
         * Ensures custom post type URLs work correctly
         */
       // add_action('wp_loaded', [$this, 'flush_rewrite_rules']);

        /**
         * Initialize plugin on plugins_loaded hook with priority 5
         * Ensures plugin loads before most other plugins
         */
        add_action('plugins_loaded', [$this, 'init_plugin'], 5);
    }

    /**
     * Basic environment compatibility check
     *
     * Verifies that PHP and WordPress versions meet minimum requirements
     * Shows admin notices and self-deactivates if requirements aren't met
     *
     * @return bool True if environment meets requirements, false otherwise
     */
    private function basic_environment_check(): bool
    {
        global $wp_version;

        /**
         * Check if current PHP and WordPress versions meet minimum requirements
         * Uses version_compare for safe version checking
         */
        if (version_compare(PHP_VERSION, self::MIN_PHP, '<') || version_compare($wp_version, self::MIN_WP, '<')) {
            /**
             * Display admin notice detailing version requirements
             * Shows both PHP and WordPress requirements if both are missing
             */
            add_action('admin_notices', function () use ($wp_version) {
                echo '<div class="notice notice-error"><p><strong>Havenlytics:</strong> ';
                
                if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
                    printf(
                        /* translators: 1: minimum PHP version, 2: current PHP version */
                        esc_html__('Requires PHP %1$s or higher. Current: %2$s.', 'havenlytics'),
                        esc_html(self::MIN_PHP),
                        esc_html(PHP_VERSION)
                    );
                    echo ' ';
                }
                
                if (version_compare($wp_version, self::MIN_WP, '<')) {
                    printf(
                        /* translators: 1: minimum WP version, 2: current WP version */
                        esc_html__('Requires WordPress %1$s or higher. Current: %2$s.', 'havenlytics'),
                        esc_html(self::MIN_WP),
                        esc_html($wp_version)
                    );
                }

                echo '</p></div>';
            });

            /**
             * Self-deactivate plugin to prevent errors on incompatible environments
             * Runs on admin_init to ensure proper deactivation
             */
            add_action('admin_init', function () {
                if (is_plugin_active(HVNLYNAB_BASENAME)) {
                    deactivate_plugins(HVNLYNAB_BASENAME);
                }
            });
            return false;
        }
        return true;
    }

    /**
     * Initialize singleton instance
     *
     * Implements Singleton pattern to ensure only one instance exists
     * Returns existing instance or creates new one if none exists
     *
     * @return self Singleton instance of the plugin
     */
    public static function init(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Magic getter for service container
     *
     * Provides access to services with lazy loading capability
     * Special handling for critical services like Helper and DB
     *
     * @param string $prop The service name to retrieve
     * @return mixed|null The service instance or null if not found
     */
    public function __get($prop)
    {
        /**
         * Lazy load Helper service if accessed but not yet initialized
         * Retrieves helper from bootstrap if available
         */
        if ($prop === 'Helper' && !isset($this->container[$prop])) {
            $bootstrap = $this->container['bootstrap'] ?? null;
            if ($bootstrap && method_exists($bootstrap, 'get_helper')) {
                $this->container[$prop] = $bootstrap->get_helper();
            }
        }

        /**
         * Lazy load Database service if accessed but not yet initialized
         * Retrieves database instance from bootstrap if available
         */
        if ($prop === 'DB' && !isset($this->container[$prop])) {
            $bootstrap = $this->container['bootstrap'] ?? null;
            if ($bootstrap && method_exists($bootstrap, 'get_database')) {
                $this->container[$prop] = $bootstrap->get_database();
            }
        }

        return $this->container[$prop] ?? null;
    }

    /**
     * Magic isset for service container
     *
     * Checks if a service exists in the container
     *
     * @param string $prop The service name to check
     * @return bool True if service exists, false otherwise
     */
    public function __isset($prop)
    {
        return isset($this->container[$prop]);
    }
    /**
     * Get helper service instance
     *
     * Professional method to access helper service without magic getter
     * Provides type safety and better IDE support
     *
     * @return \HvnlyNab\Helpers|null Helper service instance or null if not available
     * @since 2.0.0
     */
    public function get_helper()
    {
        return $this->Helper;
    }
    /**
     * Register plugin lifecycle hooks
     *
     * Sets up activation, deactivation, and uninstall handlers
     * Uses anonymous functions for activation to handle errors gracefully
     */
    private function register_lifecycle_hooks()
    {
        /**
         * Plugin activation handler with error trapping
         * Performs installation tasks and sets up initial data
         */
        register_activation_hook(__FILE__, function () {
            try {
                /**
                 * Safe activation: full install on first run, migrations-only on updates.
                 */
                \HvnlyNab\Core\Installer::activate();

                /**
                 * One-time onboarding redirect, re-armed on every MANUAL activation
                 * (fresh install OR deactivate → reactivate).
                 *
                 * Plugin UPDATES are excluded automatically: WordPress reactivates
                 * plugins silently during an update (activate_plugin( ..., $silent = true )),
                 * so this activation hook never fires on a normal update. The hook firing
                 * is therefore the authoritative signal of a deliberate activation.
                 *
                 * Resetting the "done" marker lets the redirect fire once per activation;
                 * the destination (Import Wizard vs Settings) is resolved later from the
                 * current property count in HookManager::get_activation_redirect_url().
                 */
                delete_option( 'hvnly_activation_redirect_done' );
                update_option( 'hvnly_activation_redirect', 1 );
                set_transient( 'hvnly_activation_redirect', 1, HOUR_IN_SECONDS );


                /**
                 * Register CPT/taxonomies first, then flush rewrite rules.
                 * activation_setup() also schedules a deferred admin flush.
                 */
                if ( class_exists( '\HvnlyNab\Core\RewriteRulesManager' ) ) {
                    \HvnlyNab\Core\RewriteRulesManager::activation_setup();
                } else {
                    if ( '' === get_option( 'permalink_structure', '' ) ) {
                        update_option( 'permalink_structure', '/%postname%/' );
                    }
                    delete_option( 'rewrite_rules' );
                    flush_rewrite_rules( true );
                    update_option( 'hvnly_flush_rewrite_once', 1, false );
                }

            } catch (Throwable $e) {
                /**
                 * Record fatal errors during activation for debugging
                 * Prevents white screen of death during activation
                 */
                \HvnlyNab\Core\ErrorHandler::record_fatal(
                    'Activation failed: ' . $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                );
            }
        });

        /**
         * Plugin deactivation handler
         * Cleans up scheduled events and flushes rewrite rules
         */
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        /**
         * Plugin uninstall handler
         * Performs complete cleanup when plugin is deleted
         */
       // register_uninstall_hook(__FILE__, [__CLASS__, 'uninstall']);
    }

    /**
     * Flush rewrite rules
     *
     * Ensures custom post type and taxonomy URLs work correctly
     * Called after plugin activation and on wp_loaded hook
     */
    // public function flush_rewrite_rules()
    // {
    //     flush_rewrite_rules();
    // }

    /**
     * Plugin deactivation handler
     *
     * Cleans up scheduled events, flushes rewrite rules,
     * and removes activation flags to ensure fresh installs work properly.
     */
    public function deactivate()
    {
        /**
         * Clear all scheduled events and cron jobs
         */
        Scheduler::clear_scheduled_events();

        /**
         * Delete activation flags to ensure clean state on next activation
         * This is critical for the redirect to work on reinstall
         */
        delete_option('hvnly_activation_redirect');
        delete_option('hvnly_activation_redirect_done');
        delete_transient('hvnly_activation_redirect');
        delete_option('hvnly_flush_rewrite_once');

        /**
         * Log deactivation for debugging (only in debug mode) - REMOVED FOR PRODUCTION
         */
        // if (defined('WP_DEBUG') && WP_DEBUG) {
        //     error_log('Havenlytics: Plugin deactivated - Cleaned up activation flags');
        // }

        /**
         * Flush rewrite rules to remove plugin routes
         */
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall handler
     *
     * Performs complete cleanup when plugin is deleted
     * Removes database tables, options, and other data
     */
    // public static function uninstall()
    // {
    //     // Cleanup operations for complete plugin removal
    // }

    /**
     * Initialize plugin components
     *
     * Main initialization method called on plugins_loaded hook
     * Sets up all plugin components and services
     */
    public function init_plugin()
    {
        /**
         * Check if fatal errors were recorded during earlier stages
         * Prevents initialization if plugin is in error state
         */
        if (\HvnlyNab\Core\ErrorHandler::has_fatal_recorded()) {
            return;
        }

        try {

            // ✅ Appsero tracking (SAFE)
            $this->init_appsero();
            /**
             * Initialize bootstrap which handles all core components
             * - Admin interfaces
             * - Asset management
             * - API endpoints
             * - Database operations
             */
            $this->container['bootstrap'] = new Bootstrap();
            $this->container['bootstrap']->init();

       // ==============================================
        // ELEMENTOR INTEGRATION - PROFESSIONAL INITIALIZATION
        // ==============================================
        // Initialize Elementor integration after plugins_loaded with proper priority
        add_action('plugins_loaded', function() {
            // Check if Elementor is active (multiple reliable methods)
            $elementor_active = false;
            
            if (class_exists('\Elementor\Plugin')) {
                $elementor_active = true;
            }
            if (defined('ELEMENTOR_VERSION')) {
                $elementor_active = true;
            }
            
            if ($elementor_active) {
                // Load Elementor integration bootstrap
                $elementor_bootstrap = HVNLYNAB_INCLUDES . '/Integrations/Elementor/Bootstrap.php';
                if (file_exists($elementor_bootstrap)) {
                    require_once $elementor_bootstrap;
                    if (class_exists('\HvnlyNab\Integrations\Elementor\Bootstrap')) {
                        \HvnlyNab\Integrations\Elementor\Bootstrap::get_instance();
                    }
                }
            }
        }, 20); // Priority 20 ensures Elementor is loaded

        // ==============================================
        // GUTENBERG BLOCK INTEGRATION
        // ==============================================
        // Registers native block-editor blocks that reuse the existing query,
        // template, card and caching layers. Additive and independent of
        // Elementor — the registrar self-hooks `init` for block registration.
        if (class_exists('\HvnlyNab\Integrations\Blocks\BlockRegistrar')) {
            \HvnlyNab\Integrations\Blocks\BlockRegistrar::get_instance();
        }

            /**
             * Fire loaded action for other plugins/themes to hook into
             * Allows extensions to run after Havenlytics is fully loaded
             */
            do_action('hvnly_loaded');
        } catch (Throwable $e) {
            /**
             * Record fatal errors during initialization
             * Provides debugging information for support
             */
            \HvnlyNab\Core\ErrorHandler::record_fatal(
                'Plugin init failed: ' . $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
        }
    }
    /**
     * Initialize Appsero tracking safely
     */
    private function init_appsero(): void
    {
        // Prevent fatal error if library missing
        if (!class_exists('Appsero\Client')) {
            $appsero_path = __DIR__ . '/vendor/appsero/client/src/Client.php';

            if ( ! is_readable( $appsero_path ) && defined( 'HVNLYNAB_PATH' ) ) {
                $appsero_path = HVNLYNAB_PATH . 'vendor/appsero/client/src/Client.php';
            }

            if (is_readable($appsero_path)) {
                require_once $appsero_path;
            } else {
                return; // silently fail (safe mode)
            }
        }

        try {
            $client = new \Appsero\Client(
                '0b72982c-0412-43f0-9798-d1f459320a07',
                'Havenlytics – Real Estate Listings, Property Search & Agent Workspace',
                __FILE__
            );

            $client->insights()->init();

        } catch (\Throwable $e) {
            // Never break plugin if analytics fails
            // if (defined('WP_DEBUG') && WP_DEBUG) {
            //     error_log('Appsero error: ' . $e->getMessage());
            // }
        }
    }
    /**
     * Get plugin version
     *
     * @return string Current plugin version
     */
    public function version(): string
    {
        return HVNLYNAB_VERSION;
    }

    /**
     * Get plugin directory path
     *
     * @return string Absolute path to plugin directory
     */
    public function plugin_path(): string
    {
        return HVNLYNAB_PATH;
    }

    /**
     * Get assets URL
     *
     * @param string $file Optional file path to append to assets URL
     * @return string Full URL to assets directory or specific file
     */
    public function assets_url(string $file = ''): string
    {
        return HVNLYNAB_ASSETS_URL . ltrim($file, '/');
    }

    /**
     * Get engine instance
     *
     * Provides access to the main plugin engine
     * The engine handles core business logic and operations
     *
     * @return mixed Engine instance or null if not available
     */
    public function engine()
    {
        return $this->container['bootstrap']->get_engine() ?? null;
    }

    /**
     * Get database instance
     *
     * Provides access to database operations layer
     *
     * @return mixed Database instance or null if not available
     */
    public function database()
    {
        return $this->DB;
    }

    /**
     * Clear all plugin cache
     *
     * Clears cached data if engine is available
     * Useful after data updates or settings changes
     */
    public function clear_cache(): void
    {
        if ($engine = $this->engine()) $engine->clear_all_cache();
    }
}


/**
 * ============================================
 *
 * HVNLY_NAB powers the Havenlytics ecosystem.
 * Built for scalability, AI integration, and
 * enterprise-grade real estate solutions.
 *
 * Usage:
 *   HVNLY_NAB()->engine()->method()
 *   HVNLY_NAB()->get_helper()
 *   HVNLY_NAB()->clear_cache()
 *
 * ============================================
 */

/**
 * Main plugin instance accessor
 *
 * Returns the singleton instance of HvnlyNab.
 * This is the PRIMARY function for all plugin access.
 *
 * @since   2.1.3
 * @return  HvnlyNab  Singleton plugin instance
 */
if (!function_exists('HVNLY_NAB')) {
    function HVNLY_NAB(): HvnlyNab
    {
        return HvnlyNab::init();
    }
}

/**
 * Plugin engine accessor
 *
 * Shortcut to access the engine component directly.
 * The engine handles core business logic and operations.
 *
 * @since   2.1.3
 * @return  mixed  Engine instance or null if not available
 */
if (!function_exists('HVNLY_NAB_engine')) {
    function HVNLY_NAB_engine()
    {
        return HVNLY_NAB()->engine();
    }
}

/**
 * ============================================
 * INITIALIZE PLUGIN
 * ============================================
 */
HVNLY_NAB();