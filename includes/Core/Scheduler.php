<?php

/**
 * Plugin Scheduler - Manages scheduled tasks and cron jobs
 *
 * Handles all automated scheduling and cron job management for the Havenlytics plugin.
 * Provides custom cron intervals, scheduled maintenance tasks, and proper cleanup
 * during plugin deactivation. Ensures optimal performance through regular automation.
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
 * Scheduler Class
 *
 * Comprehensive scheduler system that manages all plugin cron jobs and scheduled events.
 * Provides automated maintenance, cache cleanup, and optimization tasks on daily,
 * weekly, and monthly intervals. Ensures clean removal of scheduled events during
 * plugin deactivation to prevent orphaned cron jobs.
 *
 * Key features:
 * - Custom cron interval definitions (weekly, monthly)
 * - Automated cache cleanup and maintenance
 * - Proper event cleanup on plugin deactivation
 * - Prevention of duplicate scheduled events
 *
 * @since 2.0.0
 */
class Scheduler
{
    /**
     * Schedule all plugin events with WordPress cron system
     *
     * Sets up recurring events for maintenance and optimization tasks.
     * Uses wp_next_scheduled() to prevent duplicate event registration.
     * Events are scheduled with appropriate intervals for optimal performance.
     *
     * Scheduled events:
     * - Daily cache cleanup: Removes expired cache entries
     * - Weekly optimization: Performance and database optimization
     * - Monthly cleanup: Comprehensive system maintenance
     *
     * @return void
     * @since 2.0.0
     */
    public static function schedule_events()
    {
        /**
         * Schedule daily cache cleanup event
         * Removes expired cache entries to prevent database bloat
         */
        if (!wp_next_scheduled('hvnly_daily_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'hvnly_daily_cache_cleanup');
        }

        /**
         * Schedule weekly optimization event
         * Performs performance optimization and database maintenance
         */
        if (!wp_next_scheduled('hvnly_weekly_optimization')) {
            wp_schedule_event(time(), 'weekly', 'hvnly_weekly_optimization');
        }

        /**
         * Schedule monthly comprehensive cleanup event
         * Handles extensive maintenance tasks and system optimization
         */
        if (!wp_next_scheduled('hvnly_monthly_cleanup')) {
            wp_schedule_event(time(), 'monthly', 'hvnly_monthly_cleanup');
        }
    }

    /**
     * Clear all scheduled events during plugin deactivation
     *
     * Removes all plugin-specific cron jobs from WordPress scheduling system.
     * Prevents orphaned events from running after plugin deactivation and
     * ensures clean removal without leaving residual scheduled tasks.
     *
     * @return void
     * @since 2.0.0
     */
    public static function clear_scheduled_events()
    {
        /**
         * Remove daily cache cleanup event
         */
        wp_clear_scheduled_hook('hvnly_daily_cache_cleanup');

        /**
         * Remove weekly optimization event
         */
        wp_clear_scheduled_hook('hvnly_weekly_optimization');

        /**
         * Remove monthly cleanup event
         */
        wp_clear_scheduled_hook('hvnly_monthly_cleanup');
    }

    /**
     * Define custom cron schedule intervals
     *
     * Extends WordPress default cron intervals with custom schedules
     * for weekly and monthly tasks. These intervals are used by
     * the plugin's scheduled events for regular maintenance.
     *
     * @param array $schedules Existing WordPress cron schedules
     * @return array Modified schedules with custom intervals added
     * @since 2.0.0
     */
    public static function custom_cron_intervals($schedules)
    {
        /**
         * Add weekly interval (7 days / 604,800 seconds)
         * Used for weekly optimization tasks
         */
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __('Once Weekly', 'havenlytics'),
        ];

        /**
         * Add monthly interval (approximately 30 days)
         * Used for comprehensive monthly maintenance
         */
        $schedules['monthly'] = [
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => __('Once Monthly', 'havenlytics'),
        ];

        return $schedules;
    }

    /**
     * Initialize scheduler system and register all hooks
     *
     * Main initialization method that sets up the complete scheduling system.
     * Called during plugin bootstrap to register custom intervals, schedule events,
     * and set up deactivation cleanup hooks.
     *
     * Initialization steps:
     * 1. Register custom cron intervals with WordPress
     * 2. Schedule plugin events when WordPress is ready
     * 3. Register deactivation hook for proper cleanup
     *
     * @return void
     * @since 2.0.0
     */
    public static function init()
    {
        /**
         * Register custom cron intervals with WordPress
         * This allows using 'weekly' and 'monthly' intervals in scheduling
         */
        add_filter('cron_schedules', [self::class, 'custom_cron_intervals']);

        /**
         * Schedule plugin events when all plugins are loaded
         * Ensures WordPress is fully initialized before scheduling
         */
        add_action('plugins_loaded', [self::class, 'schedule_events']);

        /**
         * Register deactivation hook to clear scheduled events
         * Ensures clean removal of cron jobs when plugin is deactivated
         */
        register_deactivation_hook(HVNLYNAB_FILE, [self::class, 'clear_scheduled_events']);
    }
}