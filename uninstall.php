<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up ALL plugin data, options, transients, post meta, term meta,
 * user meta, and database tables when the plugin is completely removed.
 * Ensures a completely clean slate for future installations.
 *
 * @package     Havenlytics
 * @subpackage  Uninstall
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Note: Direct database queries are used in this uninstall script for several reasons:
 * 
 * 1. Performance: Bulk deletion of hundreds/thousands of records is much faster with direct SQL
 * 2. Context: Uninstall runs outside normal WordPress hooks where caching is irrelevant
 * 3. Reliability: Direct queries ensure complete data removal without interference
 * 4. Standard Practice: This follows WordPress.org guidelines for plugin uninstall routines
 * 
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

global $wpdb;

/**
 * ===========================================
 * STEP 1: DELETE ALL PLUGIN OPTIONS
 * ===========================================
 */

// All plugin options with exact names - prefixed with hvnly_
$hvnly_plugin_options = array(
    // Activation and redirect tracking - CRITICAL for fresh install redirect
    'hvnly_activation_redirect',
    'hvnly_activation_redirect_done',
    
    // Cache settings
    'hvnly_cache_enabled',
    'hvnly_cache_ttl',
    'hvnly_cache_compression',
    'hvnly_cache_debug',
    'hvnly_cache_hits',
    'hvnly_cache_misses',
    'hvnly_queries_executed',
    'hvnly_cache_requests',
    'hvnly_queries_saved',
    
    // Demo import tracking
    'hvnly_demo_properties_imported',
    'hvnly_demo_properties_count',
    'hvnly_import_logs',
    
    // Builder configurations
    'hvnly_property_builder.sections',
    'hvnly_property_card.sections',
    
    // Page IDs for auto-created pages
    'hvnly_property_grid_page_id',
    'hvnly_property_list_page_id',
    'hvnly_property_agents_page_id',
    'hvnly_property_agencies_page_id',
    
    // Migration tracking
    'hvnly_db_version',
    'hvnly_migration_history',
    'hvnly_last_migration_backup',
    'hvnly_last_migration_error',
    
    // Settings
    'hvnly_settings',
    
    // Installer tracking
    'hvnlynab_installed',
    'hvnlynab_version',
);

// Delete each exact option using WordPress functions (these are fine)
foreach ($hvnly_plugin_options as $hvnly_option) {
    delete_option($hvnly_option);
}

/**
 * ===========================================
 * STEP 2: DELETE ALL OPTIONS WITH PATTERNS
 * ===========================================
 */

// Delete all migration backup options
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
        '_hvnly_migration_backup_%'
    )
);

// Delete all import rate limit transients
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
        '_transient_hvnly_import_rate_%'
    )
);

// Delete any other options with our prefixes
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
        'hvnly_%',
        '_hvnly_%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * ===========================================
 * STEP 3: DELETE ALL TRANSIENTS
 * ===========================================
 */

// Delete all transients with our prefixes
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$hvnly_transient_patterns = array(
    '_transient_hvnly_%',
    '_transient_timeout_hvnly_%',
    '_transient_hvnlynab_%',
    '_transient_timeout_hvnlynab_%',
);

foreach ($hvnly_transient_patterns as $hvnly_pattern) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
            $hvnly_pattern
        )
    );
}
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * ===========================================
 * STEP 4: DELETE ALL POST META
 * ===========================================
 */

// Delete all post meta with our prefixes
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE %s OR meta_key LIKE %s",
        '_hvnly_%',
        '_hvnlynab_%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * ===========================================
 * STEP 5: DELETE ALL TERM META
 * ===========================================
 */

// Delete all term meta with our prefixes
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->termmeta WHERE meta_key LIKE %s OR meta_key LIKE %s",
        '_hvnly_%',
        '_hvnlynab_%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * ===========================================
 * STEP 6: DELETE ALL USER META
 * ===========================================
 */

// Delete all user meta with our prefixes
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE %s OR meta_key LIKE %s",
        '_hvnly_%',
        '_hvnlynab_%'
    )
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * ===========================================
 * STEP 7: CLEAR ALL CRON EVENTS
 * ===========================================
 */

// Clear all scheduled cron events
$hvnly_cron_hooks = array(
    'hvnly_daily_cache_cleanup',
    'hvnly_daily_analytics_cleanup',
    'hvnly_weekly_optimization',
    'hvnly_monthly_cleanup',
);

foreach ($hvnly_cron_hooks as $hvnly_hook) {
    $hvnly_timestamp = wp_next_scheduled($hvnly_hook);
    if ($hvnly_timestamp) {
        wp_unschedule_event($hvnly_timestamp, $hvnly_hook);
    }
}

/**
 * ===========================================
 * STEP 8: CLEAR OBJECT CACHE
 * ===========================================
 */

// Flush object cache to ensure complete cleanup
wp_cache_flush();