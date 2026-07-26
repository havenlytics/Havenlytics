<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes Havenlytics plugin infrastructure: options, transients, plugin meta,
 * custom database tables, cron events, and Appsero telemetry leftovers.
 *
 * Intentionally retains WordPress content created while the plugin was active
 * (property/agent CPT posts, taxonomies, and Media Library attachments) so
 * site content is not silently destroyed. Delete those from wp-admin if needed.
 *
 * @package     Havenlytics
 * @subpackage  Uninstall
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
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
	'hvnly_property_search_page_id',
	'hvnly_sign_in_page_id',
	'hvnly_workspace_page_id',
	'hvnly_favorites_page_id',

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

	// Leftover temporary QA option keys (no-ops if absent)
	'hvnly_qa_last_ie_snapshot',
	'hvnly_qa_generate_job',
	'hvnly_qa_export_baseline',
	'hvnly_qa_import_compare',
	'hvnly_qa_full_report',

	// CSV Transfer (spreadsheet import/export) state
	'hvnly_csv_mapping_profiles',
	'hvnly_csv_job_state',
	'hvnly_csv_job_lock',

	// Appsero SDK options (slug = plugin folder "havenlytics")
	'havenlytics_allow_tracking',
	'havenlytics_tracking_notice',
	'havenlytics_tracking_last_send',
	'havenlytics_tracking_skipped',
);

// Delete each exact option using WordPress functions (these are fine)
foreach ( $hvnly_plugin_options as $hvnly_option ) {
	delete_option( $hvnly_option );
}

/**
 * ===========================================
 * STEP 2: DELETE ALL OPTIONS WITH PATTERNS
 * ===========================================
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
		'_hvnly_migration_backup_%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
		'_transient_hvnly_import_rate_%'
	)
);

// Havenlytics + Appsero leftover option prefixes.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		'hvnly_%',
		'_hvnly_%',
		'havenlytics_%',
		'_havenlytics_%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * ===========================================
 * STEP 3: DELETE ALL TRANSIENTS
 * ===========================================
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$hvnly_transient_patterns = array(
	'_transient_hvnly_%',
	'_transient_timeout_hvnly_%',
	'_transient_hvnlynab_%',
	'_transient_timeout_hvnlynab_%',
	'_transient_havenlytics_%',
	'_transient_timeout_havenlytics_%',
);

foreach ( $hvnly_transient_patterns as $hvnly_pattern ) {
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

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM $wpdb->postmeta WHERE meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s",
		'_hvnly_%',
		'_hvnlynab_%',
		'hvnly_%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * ===========================================
 * STEP 5: DELETE ALL TERM META
 * ===========================================
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM $wpdb->termmeta WHERE meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s",
		'_hvnly_%',
		'_hvnlynab_%',
		'hvnly_%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * ===========================================
 * STEP 6: DELETE ALL USER META
 * ===========================================
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM $wpdb->usermeta WHERE meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s",
		'_hvnly_%',
		'_hvnlynab_%',
		'hvnly_%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * ===========================================
 * STEP 7: DROP CUSTOM DATABASE TABLES
 * ===========================================
 */

$hvnly_custom_tables = array(
	'hvnly_favorites',
	'hvnly_inquiries',
	'hvnly_inquiry_replies',
	'hvnly_portal_notifications',
	'hvnly_taxonomy_requests',
	'hvnly_taxonomy_request_logs',
	'hvnly_section_cache',
	'hvnly_field_index',
	'hvnly_audit_log',
	'hvnly_webhook_queue',
);

foreach ( $hvnly_custom_tables as $hvnly_table_suffix ) {
	$hvnly_table = $wpdb->prefix . $hvnly_table_suffix;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$hvnly_table}`" );
}

/**
 * ===========================================
 * STEP 8: CLEAR ALL CRON EVENTS
 * ===========================================
 */

$hvnly_cron_hooks = array(
	'hvnly_daily_cache_cleanup',
	'hvnly_daily_analytics_cleanup',
	'hvnly_weekly_optimization',
	'hvnly_monthly_cleanup',
	'hvnly_csv_temp_cleanup',
	'hvnly_ie_temp_cleanup',
	'havenlytics_tracker_send_event',
);

foreach ( $hvnly_cron_hooks as $hvnly_hook ) {
	wp_clear_scheduled_hook( $hvnly_hook );
}

/**
 * ===========================================
 * STEP 9: CLEAR OBJECT CACHE
 * ===========================================
 */

wp_cache_flush();
