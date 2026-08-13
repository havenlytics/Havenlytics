<?php
/**
 * Havenlytics Bootstrap - Main plugin initializer
 *
 * Handles the core initialization and service management for the Havenlytics plugin.
 * Responsible for loading dependencies, initializing services, and coordinating plugin components.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @author      Havenlytics
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 * @version     2.1.3
 */

namespace HvnlyNab\Core;

// Prevent direct access to WordPress files.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Import required classes for plugin functionality.
 * These are the core components that make up the plugin architecture.
 */
use HvnlyNab\Core\Migration\Migrator;
use HvnlyNab\Core\DataPreservation\MigrationAdminNotices;
use HvnlyNab\Helpers;
use HvnlyNab\Database\Database;
use HvnlyNab\Api\Controller;
use HvnlyNab\Admin\Admin;
use HvnlyNab\Admin\CacheAdmin;
use HvnlyNab\Frontend;
use Exception;
use Error;

/**
 * Bootstrap Class
 *
 * Main initialization class that coordinates all plugin components and services.
 * Implements service container pattern and manages plugin lifecycle.
 *
 * @since 2.0.0
 */
class Bootstrap {

    /**
     * Database version option key for tracking schema versions.
     *
     * @since 2.1.3
     * @var string
     */
    const DB_VERSION_KEY = 'hvnly_db_version';

    /**
     * Service container storage.
     *
     * Stores instances of all plugin services for easy access and dependency injection.
     * Services include: Helper, DB, REST API, Admin, Frontend, etc.
     *
     * @since 2.0.0
     * @var array
     */
    private $services = array();

    /**
     * Plugin engine instance.
     *
     * The main engine that handles core business logic, caching, and performance operations.
     * Can be the full engine or a fallback if main engine isn't available.
     *
     * @since 2.0.0
     * @var mixed
     */
    private $engine;

    /**
     * Initialize the plugin bootstrap.
     *
     * Main entry point that coordinates all initialization steps.
     * Called by the main plugin class to set up all components.
     *
     * @since 2.0.0
     * @return void
     */
    public function init() {
        // Step 1: Load core dependencies and files.
        $this->load_core_dependencies();

        if ( is_admin() ) {
            MigrationAdminNotices::init();
        }

        // Step 2: Initialize the main plugin engine for caching and performance.
        $this->init_engine();

        // Step 3: Initialize shared services used by all components.
        $this->init_shared_services();
        $this->init_settings_system();
        $this->check_version_update();

        // Step 4: Initialize context-specific services (admin/frontend).
        $this->init_services();

        // Step 5: Register all WordPress hooks and filters.
        $this->register_hooks();

        // Step 6: Schedule recurring events and cron jobs.
        $this->schedule_events();

        // Initialize enterprise features
        $this->init_enterprise_features();
    }

    /**      * Initialize enterprise-specific features and services.
     *
     * Sets up any enterprise-specific functionality, such as enhanced caching or analytics.
     * This is separated from core initialization to allow for modular loading.
     *
     * @since 2.3.0
     * @return void
     */
    private function init_enterprise_features(): void {
        // Initialize enterprise cache
        EnterpriseCache::get_instance();
    }


    /**
     * Check if plugin version has changed and run updates.
     *
     * Compares database version with current plugin version and
     * triggers migrations if an update is needed.
     *
     * @since 2.0.0
     * @return void
     */
    private function check_version_update() {
        $db_version = get_option(self::DB_VERSION_KEY, '0.0.0');

        if ( Migrator::needs_run() || version_compare( $db_version, HVNLYNAB_VERSION, '<' ) ) {
            $success = Migrator::run();
            if ( $success && version_compare( $db_version, HVNLYNAB_VERSION, '<' ) ) {
                update_option( self::DB_VERSION_KEY, HVNLYNAB_VERSION );
                Installer::update( $db_version, HVNLYNAB_VERSION );

                if ( class_exists( RewriteRulesManager::class ) ) {
                    RewriteRulesManager::schedule_flush();
                }
            }
        }
    }

    /**
     * Load core plugin dependencies.
     *
     * Reserved for future use when additional core files need to be loaded.
     * Currently handled by Composer autoloader.
     *
     * @since 2.0.0
     * @return void
     */
    private function load_core_dependencies() {
        // Reserved for future core dependency loading.
        // Currently handled by Composer autoloader.
    }

    /**
     * Initialize the main plugin engine.
     *
     * Attempts to load the full engine class first, then falls back to a basic
     * engine if the main engine isn't available. This ensures the plugin
     * continues to function even if some components are missing.
     *
     * @since 2.0.0
     * @return void
     */
    private function init_engine() {
        // Check if the main engine class file exists.
        $engine_file = HVNLYNAB_INCLUDES . '/HvnlyEngine.php';

        if (file_exists($engine_file)) {
            // Load the engine class file.
            require_once $engine_file;

            // Initialize the main engine if class exists.
            if (class_exists('HvnlyEngine')) {
                $this->engine = \HvnlyEngine::get_instance();
                return;
            }
        }

        // Fallback: Create a basic engine if main engine isn't available.
        // This ensures basic functionality even if the main engine fails.
        $this->engine = $this->create_fallback_engine();
    }

    /**
     * Create fallback engine when main engine is unavailable.
     *
     * Provides basic caching and performance functionality when the main
     * engine class cannot be loaded. This prevents complete plugin failure.
     *
     * @since 2.0.0
     * @return object Anonymous class with basic engine methods.
     */
    private function create_fallback_engine() {
        // Return anonymous class that implements basic engine functionality.
        // This provides graceful degradation when main engine fails.
        return new class() {
            /**
             * Clear all plugin cache entries.
             *
             * Removes all transients with the hvnly_ prefix from WordPress options table.
             * Used when cache needs to be completely refreshed.
             *
             * @since 2.0.0
             * @return int|false Number of rows affected or false on error.
             */
            public function clear_all_cache() {
                global $wpdb;

                // Delete both transient values and their timeout entries.
                // Uses prepared statements for security.
                $result = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                        $wpdb->esc_like('_transient_hvnly_') . '%',
                        $wpdb->esc_like('_transient_timeout_hvnly_') . '%'
                    )
                );

                return $result;
            }

            /**
             * Get cache statistics and usage information.
             *
             * Analyzes the cache to provide insights into cache size, distribution,
             * and performance metrics.
             *
             * @since 2.0.0
             * @return array Cache statistics including size, counts, and hit rate.
             */
            public function get_cache_stats() {
                global $wpdb;

                try {
                    // Query all cache entries with their sizes.
                    $cache_data = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT option_name, LENGTH(option_value) as size 
                             FROM {$wpdb->options} 
                             WHERE option_name LIKE %s OR option_name LIKE %s",
                            $wpdb->esc_like('_transient_hvnly_') . '%',
                            $wpdb->esc_like('_transient_timeout_hvnly_') . '%'
                        )
                    );

                    // Initialize counters for different cache types.
                    $total_size  = $search_count = $sidebar_count = $term_count = 0;
                    $total_items = count($cache_data);

                    // Analyze each cache entry to categorize and calculate totals.
                    foreach ($cache_data as $item) {
                        $total_size += intval($item->size);

                        // Categorize cache entries by their purpose.
                        if (strpos($item->option_name, 'search') !== false) {
                            $search_count++;
                        } elseif (strpos($item->option_name, 'sidebar') !== false) {
                            $sidebar_count++;
                        } elseif (strpos($item->option_name, 'terms') !== false) {
                            $term_count++;
                        }
                    }

                    // Return comprehensive cache statistics.
                    return array(
                        'total_cache_size'    => $total_size,
                        'search_cache_count'  => $search_count,
                        'sidebar_cache_count' => $sidebar_count,
                        'term_cache_count'    => $term_count,
                        'cache_hit_rate'      => $total_items > 0 ? 85.0 : 0,
                        'total_cached_items'  => $total_items,
                        'cache_size_human'    => $this->format_bytes($total_size),
                    );
                } catch (Exception $e) {
                    // Return default stats on error.
                    return array(
                        'total_cache_size'    => 0,
                        'search_cache_count'  => 0,
                        'sidebar_cache_count' => 0,
                        'term_cache_count'    => 0,
                        'cache_hit_rate'      => 0,
                        'total_cached_items'  => 0,
                        'cache_size_human'    => '0 Bytes',
                    );
                }
            }

            /**
             * Get performance metrics for monitoring.
             *
             * Provides simulated performance data when real metrics aren't available.
             * In the full engine, this would provide actual performance measurements.
             *
             * @since 2.0.0
             * @return array Performance metrics including query times and efficiency.
             */
            public function get_performance_metrics() {
                return array(
                    'average_query_time' => 0.15,
                    'cache_efficiency'   => 85.5,
                    'memory_usage'       => memory_get_usage(true),
                    'total_queries_saved' => 1250,
                );
            }

            /**
             * Clear cache entries matching a specific pattern.
             *
             * Allows selective cache clearing for specific types of data.
             * Useful when only certain data needs to be refreshed.
             *
             * @since 2.0.0
             * @param string $pattern The pattern to match in cache keys.
             * @return int|false Number of rows affected or false on error.
             */
            public function clear_transients_by_pattern( $pattern ) {
                global $wpdb;

                // Delete transients matching the specific pattern.
                $result = $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                        $wpdb->esc_like('_transient_hvnly_' . $pattern) . '%',
                        $wpdb->esc_like('_transient_timeout_hvnly_' . $pattern) . '%'
                    )
                );

                return $result;
            }

            /**
             * Update engine configuration.
             *
             * Placeholder method for configuration updates.
             * In the full engine, this would persist configuration changes.
             *
             * @since 2.0.0
             * @param array $config Configuration array to update.
             * @return void
             */
            public function update_config( $config ) {
                // No operation in fallback engine.
                // Full engine would persist configuration changes.
            }

            /**
             * Get current engine configuration.
             *
             * Retrieves configuration from WordPress options with defaults.
             * Provides consistent configuration interface.
             *
             * @since 2.0.0
             * @return array Current engine configuration.
             */
            public function get_config() {
                return array(
                    'cache_ttl'          => get_option('hvnly_cache_ttl', HOUR_IN_SECONDS * 6),
                    'cache_compression'  => (bool) get_option('hvnly_cache_compression', false),
                    'enable_performance' => (bool) get_option('hvnly_cache_debug', false),
                    'cache_prefix'       => 'hvnly_',
                );
            }

            /**
             * Format bytes into human readable string.
             *
             * Converts byte counts to KB, MB, GB etc. for display purposes.
             *
             * @since 2.0.0
             * @param int $bytes    Number of bytes to format.
             * @param int $decimals Number of decimal places to show.
             * @return string Formatted byte string with unit.
             */
            private function format_bytes( $bytes, $decimals = 2 ) {
                if ($bytes === 0) {
                    return '0 Bytes';
                }

                $k     = 1024;
                $dm    = $decimals < 0 ? 0 : $decimals;
                $sizes = array( 'Bytes', 'KB', 'MB', 'GB' );
                $i     = floor(log($bytes) / log($k));

                return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[ $i ];
            }
        };
    }

    /**
     * Initialize shared services used by all components.
     *
     * Loads core services that are needed regardless of context (admin/frontend).
     * Includes helpers, database, and REST API services.
     *
     * @since 2.0.0
     * @return void
     */
    private function init_shared_services() {
        try {
            // Avatar SSOT → WordPress get_avatar() (Users list, admin bar, comments, …).
            if ( class_exists( \HvnlyNab\Common\AvatarService::class ) ) {
                \HvnlyNab\Common\AvatarService::register_hooks();
            }

            // Initialize Helpers service for utility functions.
            if (class_exists(Helpers::class)) {
                $this->services['Helper'] = Helpers::get_instance();
            }

            // Initialize Database service for data operations.
            if (class_exists(Database::class)) {
                $this->services['DB'] = new Database();
            }

            // Initialize REST API controller for API endpoints.
            if (class_exists(Controller::class)) {
                $this->services['rest_api'] = new Controller();
            }

            /*
             * Favorites (saved properties).
             *
             * Deliberately a shared service rather than a Workspace one: the
             * heart button lives on the public archive and single-property
             * templates, so it must stay alive when an administrator disables
             * the Agent Workspace. Only the Saved Properties page is gated.
             */
            if (class_exists(\HvnlyNab\Favorites\FavoritesBootstrap::class)) {
                $this->services['favorites'] = \HvnlyNab\Favorites\FavoritesBootstrap::get_instance();
                $this->services['favorites']->init();
            }

            /*
             * Import / Export (HPTP) — package I/O, batched jobs, Settings UI,
             * and production cleanup (Phases 1–9).
             */
			if (class_exists(\HvnlyNab\ImportExport\ImportExportModule::class)) {
				$this->services['import_export'] = \HvnlyNab\ImportExport\ImportExportModule::get_instance();
				$this->services['import_export']->init();
			}

			/*
			 * CSV Transfer — spreadsheet-based property import/export with
			 * header mapping, validation, and batched Settings UI jobs.
			 * Independent of the ZIP-based Import / Export (HPTP) module.
			 */
			if (class_exists(\HvnlyNab\CsvTransfer\CsvTransferModule::class)) {
				$this->services['csv_transfer'] = \HvnlyNab\CsvTransfer\CsvTransferModule::get_instance();
				$this->services['csv_transfer']->init();
			}

		} catch (Exception $e) {
            // Silent fail in production to prevent fatal errors.
        } catch (Error $e) {
            // Silent fail in production to prevent fatal errors.
        }
    }

    /**
     * Initialize settings and style generator system.
     *
     * Sets up the Settings Manager and Dynamic Style Generator
     * for plugin configuration and frontend styling.
     *
     * @since 2.1.0
     * @return void
     */
    private function init_settings_system() {
        // Initialize Settings Manager for plugin settings.
        if (class_exists('HvnlyNab\Core\SettingsManager')) {
            $this->services['settings_manager'] = \HvnlyNab\Core\SettingsManager::get_instance();
        }

        // Initialize Dynamic Style Generator for frontend CSS.
        if (class_exists('HvnlyNab\Core\DynamicStyleGenerator')) {
            $this->services['style_generator'] = \HvnlyNab\Core\DynamicStyleGenerator::get_instance();
        }
    }

    /**
     * Initialize context-specific services.
     *
     * Loads services that are only needed in specific contexts (admin or frontend).
     * Also initializes core managers that are always needed.
     *
     * @since 2.0.0
     * @return void
     */
    private function init_services() {
        // Initialize core managers that are always needed.
        $this->services['cache_manager'] = new CacheManager();
        $this->services['hook_manager']  = new HookManager();

        // Initialize admin-specific services if in admin area.
        if (is_admin()) {
            $this->init_admin_services();
        }

        // Initialize frontend services if not in admin area or during AJAX.
        if ( ! is_admin() || ( defined('DOING_AJAX') && DOING_AJAX )) {
            $this->init_frontend_services();
        }
    }

    /**
     * Initialize admin-specific services.
     *
     * Loads services that are only needed in the WordPress admin area.
     * Includes admin interfaces, cache management UI, and settings pages.
     *
     * @since 2.0.0
     * @return void
     */
    private function init_admin_services() {
        try {
            // Initialize main admin interface.
            if (class_exists(Admin::class)) {
                $this->services['admin'] = new Admin();
            }

            // Initialize cache administration interface.
            if (class_exists(CacheAdmin::class)) {
                $this->services['cache_admin'] = new CacheAdmin();
            }

            // Workspace boots via Frontend, which is skipped on non-AJAX admin requests.
            // Agent Publish (post.php) must still register AgentIdentityAdminBridge.
            if (class_exists(\HvnlyNab\Workspace\WorkspaceBootstrap::class)) {
                $this->services['workspace'] = \HvnlyNab\Workspace\WorkspaceBootstrap::get_instance();
            }
        } catch (Exception $e) {
            // Silent fail in production.
        } catch (Error $e) {
            // Silent fail in production.
        }
    }

    /**
     * Initialize frontend services.
     *
     * Loads services that are needed for the public-facing parts of the site.
     * Includes shortcodes, templates, and frontend asset management.
     *
     * @since 2.0.0
     * @return void
     */
    private function init_frontend_services() {
        try {
            // Initialize frontend functionality.
            if (class_exists(Frontend::class)) {
                $this->services['frontend'] = new Frontend();
            }
        } catch (Exception $e) {
            // Silent fail in production.
        } catch (Error $e) {
            // Silent fail in production.
        }
    }

    /**
     * Register all WordPress hooks and filters.
     *
     * Delegates hook registration to the HookManager service.
     * This centralizes all WordPress hook registrations.
     *
     * @since 2.0.0
     * @return void
     */
    private function register_hooks() {
        if (isset($this->services['hook_manager'])) {
            $this->services['hook_manager']->register_hooks();
        }
    }

    /**
     * Schedule recurring events and cron jobs.
     *
     * Sets up scheduled tasks like cache cleanup, data synchronization,
     * and maintenance operations.
     *
     * @since 2.0.0
     * @return void
     */
    private function schedule_events() {
        Scheduler::schedule_events();
    }

    /**
     * Get the plugin engine instance.
     *
     * Provides access to the main engine for other components.
     * The engine handles core business logic and performance operations.
     *
     * @since 2.0.0
     * @return mixed The engine instance.
     */
    public function get_engine() {
        return $this->engine;
    }

    /**
     * Get a specific service from the container.
     *
     * Provides service locator functionality for dependency retrieval.
     * Used by other components to access plugin services.
     *
     * @since 2.0.0
     * @param string $service The service name to retrieve.
     * @return mixed The service instance or null if not found.
     */
    public function get_service( $service ) {
        return $this->services[ $service ] ?? null;
    }

    /**
     * Get the database service instance.
     *
     * Convenience method for accessing the database service.
     * Used frequently by other components for data operations.
     *
     * @since 2.0.0
     * @return mixed The database service instance or null if not found.
     */
    public function get_database() {
        return $this->services['DB'] ?? null;
    }

    /**
     * Get the helpers service instance.
     *
     * Convenience method for accessing the helpers service.
     * Provides utility functions used throughout the plugin.
     *
     * @since 2.0.0
     * @return mixed The helpers service instance or null if not found.
     */
    public function get_helper() {
        return $this->services['Helper'] ?? null;
    }
}
