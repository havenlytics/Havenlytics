<?php

/**
 * Environment Compatibility Checker
 *
 * Validates that the server environment meets the minimum requirements
 * for running the Havenlytics plugin. Prevents plugin activation and
 * displays user-friendly error messages when requirements aren't met.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Core;

// Prevent direct access to WordPress files
defined('ABSPATH') || exit;

/**
 * Environment Checker Class
 *
 * Performs critical environment validation before plugin initialization.
 * Ensures the hosting environment meets minimum PHP and WordPress version
 * requirements to prevent fatal errors and provide graceful degradation.
 *
 * Key features:
 * - Validates PHP version compatibility
 * - Checks WordPress version requirements
 * - Provides clear admin notice messages
 * - Automatically deactivates plugin on failure
 * - Prevents plugin initialization in incompatible environments
 *
 * @since 2.0.0
 */
class EnvironmentChecker
{
    /**
     * Perform environment compatibility check
     *
     * Main entry point that validates all environment requirements.
     * Returns true only if all checks pass, allowing plugin to proceed.
     * If any check fails, displays admin notices and deactivates plugin.
     *
     * @return bool True if environment meets all requirements, false otherwise
     */
    public static function check(): bool
    {
        global $wp_version;

        /**
         * Define minimum requirements and perform version checks
         * Uses version_compare for safe version comparison
         */
        $checks = [
            'php' => version_compare(PHP_VERSION, '7.4', '>='),
            'wp'  => version_compare($wp_version, '6.3', '>='),
        ];

        /**
         * Return true only if ALL checks pass (no false values in array)
         * Uses strict comparison to ensure only boolean false fails
         */
        if (!in_array(false, $checks, true)) {
            return true;
        }

        /**
         * Handle environment failures by showing notices and deactivating
         * Prevents plugin from running in incompatible environments
         */
        self::handle_environment_failure($checks);
        return false;
    }

    /**
     * Handle environment check failures
     *
     * Processes failed environment checks by:
     * - Generating user-friendly error messages
     * - Displaying admin notices with specific requirements
     * - Automatically deactivating the plugin to prevent errors
     *
     * @param array $checks Array of check results with keys 'php' and 'wp'
     * @return void
     */
    private static function handle_environment_failure(array $checks)
    {
        $messages = [];

        /**
         * Generate PHP version requirement message if check failed
         */
        if (!$checks['php']) {
            $messages[] = sprintf('Requires PHP 7.4 or higher. Current: %s.', PHP_VERSION);
        }

        /**
         * Generate WordPress version requirement message if check failed
         */
        if (!$checks['wp']) {
            global $wp_version;
            $messages[] = sprintf('Requires WordPress 6.3 or higher. Current: %s.', $wp_version);
        }

        /**
         * Display admin notice with all failure messages
         * Hooked to admin_notices to show in WordPress admin area
         */
        add_action('admin_notices', function () use ($messages) {
            echo '<div class="notice notice-error"><p><strong>Havenlytics:</strong> ' . esc_html(implode(' ', $messages)) . '</p></div>';
        });

        /**
         * Automatically deactivate plugin if it's currently active
         * Prevents fatal errors and provides graceful failure
         * Hooked to admin_init to ensure proper execution timing
         */
        add_action('admin_init', function () {
            if (is_plugin_active(HVNLYNAB_BASENAME)) {
                deactivate_plugins(HVNLYNAB_BASENAME);
            }
        });
    }
}
