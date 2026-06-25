<?php

/**
 * Error Handler - Comprehensive error and exception management for Havenlytics
 *
 * Provides centralized error handling, fatal error recovery, and user-friendly
 * error reporting. Prevents white screens of death and ensures graceful
 * degradation when critical errors occur.
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
 * Error Handler Class
 *
 * Comprehensive error management system that handles PHP errors, exceptions,
 * and fatal errors. Provides graceful failure modes and user-friendly error
 * reporting while preventing site crashes.
 *
 * Key features:
 * - Centralized error and exception handling
 * - Fatal error detection and recovery
 * - Automatic plugin deactivation on critical failures
 * - User-friendly admin notices with support information
 * - Detailed error logging for debugging
 *
 * @final This class should not be extended
 * @since 2.0.0
 */
final class ErrorHandler
{
    /**
     * Plugin file path for deactivation purposes
     *
     * @var string
     */
    private static $plugin_file;

    /**
     * Transient key for storing fatal error information
     *
     * @var string
     */
    private const TRANSIENT = 'hvnlynab_fatal_error';

    /**
     * Initialize the error handler system
     *
     * Sets up all error handlers and shutdown functions to capture
     * and manage errors throughout the plugin lifecycle.
     *
     * @param string $plugin_file The main plugin file path
     * @return void
     */
    public static function init(string $plugin_file): void
    {
        /**
         * Store plugin file path for deactivation if needed
         */
        self::$plugin_file = $plugin_file;

        /**
         * Set custom error handler to catch PHP errors and warnings
         */
       // set_error_handler([self::class, 'handle_error']);

        /**
         * Set custom exception handler to catch uncaught exceptions
         */
        set_exception_handler([self::class, 'handle_exception']);

        /**
         * Register shutdown function to catch fatal errors
         */
        register_shutdown_function([self::class, 'handle_shutdown']);

        /**
         * Set up admin notice and deactivation handling for fatal errors
         */
        if (is_admin()) {
            add_action('admin_init', [self::class, 'maybe_show_notice_and_deactivate']);
        }
    }

    /**
     * Record a fatal error and trigger appropriate response
     *
     * Stores error details in a transient for later display and
     * either shows a WordPress error screen or exits gracefully.
     *
     * @param string $message The error message
     * @param string $file The file where the error occurred
     * @param int $line The line number where the error occurred
     * @return void
     */
    public static function record_fatal(string $message, string $file = '', int $line = 0): void
    {
        /**
         * Store error details in a transient for 24 hours
         * This allows displaying the error on next page load
         */
        set_transient(self::TRANSIENT, [
            'message' => $message,
            'file'    => $file,
            'line'    => $line,
            'time'    => current_time('timestamp'),
        ], DAY_IN_SECONDS);

        /**
         * If in admin context, show a proper WordPress error screen
         * Otherwise, exit gracefully to prevent further errors
         */
        if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) {
            wp_die(
                esc_html( $message ),
                esc_html__( 'Havenlytics Fatal Error', 'havenlytics' ),
                [ 'response' => 500 ]
            );
        }
        exit;
    }

    /**
     * Check if a fatal error has been recorded
     *
     * Used to prevent plugin initialization when a fatal error
     * has occurred during previous execution.
     *
     * @return bool True if a fatal error is recorded, false otherwise
     */
    public static function has_fatal_recorded(): bool
    {
        return (bool) get_transient(self::TRANSIENT);
    }

    /**
     * Custom error handler for PHP errors
     *
     * Converts PHP errors to exceptions for consistent handling
     * and logs all errors for debugging purposes.
     *
     * @param int $severity The error severity level
     * @param string $message The error message
     * @param string $file The file where the error occurred
     * @param int $line The line number where the error occurred
     * @return bool Always returns false to allow default error handling to continue
     */
    // public static function handle_error($severity, $message, $file, $line): bool
    // {
    //     /**
    //      * Respect error reporting settings - don't handle suppressed errors
    //      * Check error_reporting level without calling the function directly
    //      */
    //     $error_reporting = defined('E_ALL') ? E_ALL : 0;
        
    //     if (!($error_reporting & $severity)) return false;

    //     /**
    //      * Log all errors for debugging purposes - REMOVED FOR PRODUCTION
    //      */
    //     // Debug code removed for production

    //     /**
    //      * Convert fatal errors to exceptions for consistent handling
    //      */
    //     if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
    //         self::record_fatal("PHP Error [$severity]: $message", $file, $line);
    //     }

    //     /**
    //      * Return false to allow WordPress default error handling to continue
    //      */
    //     return false;
    // }

    /**
     * Custom exception handler
     *
     * Catches uncaught exceptions and converts them to fatal errors
     * for consistent error reporting and recovery.
     *
     * @param \Throwable $exception The uncaught exception
     * @return void
     */
    public static function handle_exception($exception): void
    {
        /**
         * Format exception details for logging and reporting
         */
        $msg = $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine();

        /**
         * Log the exception details - REMOVED FOR PRODUCTION
         */
        // Debug code removed for production

        /**
         * Treat uncaught exceptions as fatal errors
         */
        self::record_fatal($msg, $exception->getFile(), $exception->getLine());
    }

    /**
     * Shutdown function to catch fatal errors
     *
     * Checks for fatal errors during script shutdown and handles them
     * appropriately. This is the last chance to catch errors before
     * script termination.
     *
     * @return void
     */
    public static function handle_shutdown(): void
    {
        /**
         * Get the last error that occurred
         */
        $error = error_get_last();

        /**
         * Check if the error was a fatal one that caused shutdown
         */
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $msg = $error['message'] . ' in ' . $error['file'] . ':' . $error['line'];

            /**
             * Log the fatal shutdown error - REMOVED FOR PRODUCTION
             */
            // Debug code removed for production

            /**
             * Record as fatal error for proper handling
             */
            self::record_fatal($msg, $error['file'], $error['line']);
        }
    }

    /**
     * Show admin notice and deactivate plugin if fatal error occurred
     *
     * Checks if a fatal error was recorded and if so:
     * - Displays a user-friendly admin notice with error details
     * - Provides support contact information
     * - Automatically deactivates the plugin to prevent further errors
     * - Clears the transient to prevent repeated notices
     *
     * @return void
     */
    public static function maybe_show_notice_and_deactivate(): void
    {
        /**
         * Only proceed for users who can activate plugins (admins)
         */
        if (!current_user_can('activate_plugins')) return;

        /**
         * Check if a fatal error was recorded
         */
        $payload = get_transient(self::TRANSIENT);
        if (!$payload) return;

        /**
         * Display admin notice with error details and support information
         */
        add_action('admin_notices', function () use ($payload) {
            echo '<div class="notice notice-error is-dismissible"><p>';
            printf(
                '<strong>%s</strong> %s<br><strong>%s</strong> %s:%d<br>%s <a href="mailto:support@havenlytics.com">support@havenlytics.com</a>.',
                esc_html__('Havenlytics Error:', 'havenlytics'),
                esc_html__('Plugin deactivated due to fatal error.', 'havenlytics'),
                esc_html__('Details:', 'havenlytics'),
                esc_html($payload['message']),
                (int)$payload['line'],
                esc_html__('Contact', 'havenlytics')
            );
            echo '</p></div>';
        });

        /**
         * Automatically deactivate the plugin to prevent further errors
         * Only deactivate if the plugin is currently active
         */
        if (is_plugin_active(plugin_basename(self::$plugin_file))) {
            deactivate_plugins(plugin_basename(self::$plugin_file), false, false);
        }

        /**
         * Clear the transient to prevent showing the notice repeatedly
         */
        delete_transient(self::TRANSIENT);
    }
}