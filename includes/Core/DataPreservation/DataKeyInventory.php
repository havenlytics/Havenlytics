<?php
/**
 * Static inventory of Havenlytics data keys (Phase 1 report generator).
 *
 * @package HvnlyNab\Core\DataPreservation
 * @since   3.0.2
 */

namespace HvnlyNab\Core\DataPreservation;

defined( 'ABSPATH' ) || exit;

class DataKeyInventory {

	/**
	 * Known static _hvnly_property_* meta keys from builder/import.
	 *
	 * @return string[]
	 */
	public static function static_property_meta_keys(): array {
		return array(
			'_thumbnail_id',
			'_hvnly_unique_property_id',
			'_hvnly_field_map',
			'_hvnly_orphan_candidates',
			'_hvnly_importing',
			'_hvnly_import_session_id',
			'_hvnly_import_index',
			'_hvnly_property_agents',
			'_hvnly_property_agent',
			'_hvnly_ws_listing_status',
			'_hvnly_last_emailed_listing_status',
			'_hvnly_property_price',
			'_hvnly_property_reception_rooms',
			'_hvnly_property_bedrooms',
			'_hvnly_property_bathrooms',
			'_hvnly_property_half_bathrooms',
			'_hvnly_property_kitchens',
			'_hvnly_property_total_rooms',
			'_hvnly_property_floors',
			'_hvnly_property_year_built',
			'_hvnly_property_garage_sqft',
			'_hvnly_property_mls_number',
			'_hvnly_property_sqft',
			'_hvnly_property_lot_size',
			'_hvnly_property_hoa_fee',
			'_hvnly_property_annual_tax_amount',
			'_hvnly_property_heating',
			'_hvnly_property_cooling',
			'_hvnly_property_water',
			'_hvnly_property_reference_number',
			'_hvnly_property_building_number',
			'_hvnly_property_street',
			'_hvnly_property_address_line_1',
			'_hvnly_property_address_line_2',
			'_hvnly_property_town_city',
			'_hvnly_property_country_state',
			'_hvnly_property_zip_code',
			'_hvnly_property_location',
			'_hvnly_property_country_location',
			'_hvnly_property_location_Latitude',
			'_hvnly_property_location_Longitude',
			'_hvnly_property_youtube_video_title',
			'_hvnly_property_youtube_video_url',
			'_hvnly_property_youtube_video_thumbnail',
			'_hvnly_property_gallery_title',
			'_hvnly_property_gallery_images',
			'_hvnly_property_map_location_address',
			'_hvnly_property_documents',
			'_hvnly_property_featured',
			'_hvnly_property_action_tool_is_featured',
			'_hvnly_property_views',
			'_hvnly_property_view_analytics',
		);
	}

	/**
	 * Dynamic meta key prefix patterns.
	 *
	 * @return array<string, string>
	 */
	public static function dynamic_prefix_patterns(): array {
		return array(
			'gallery'       => 'gallery_{timestamp}_{suffix}_*',
			'video'         => 'video_{timestamp}_{suffix}_*',
			'map'           => 'map_{timestamp}_{suffix}_*',
			'property_docs' => 'property_docs_{timestamp}_{suffix}_*',
			'faq'           => 'faq_{timestamp}_{suffix}_*',
			'repeater'      => 'repeater_{timestamp}_{suffix}_*',
			'agents'        => 'agents_{timestamp}_{suffix}_*',
			'features'      => 'features_{timestamp}_{suffix}_*',
		);
	}

	/**
	 * Collect field names from live property builder config.
	 *
	 * @return string[]
	 */
	public static function builder_field_keys(): array {
		$sections = get_option( 'hvnly_property_builder.sections', array() );
		$keys     = array();
		if ( ! is_array( $sections ) ) {
			return $keys;
		}
		foreach ( $sections as $section ) {
			foreach ( $section['fields'] ?? array() as $field ) {
				if ( ! empty( $field['name'] ) ) {
					$keys[] = $field['name'];
				}
				if ( ! empty( $field['id'] ) && ( $field['id'] ?? '' ) !== ( $field['name'] ?? '' ) ) {
					$keys[] = $field['id'];
				}
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Known plugin option keys (non-exhaustive; protected list is authoritative).
	 *
	 * @return string[]
	 */
	public static function option_keys(): array {
		return array_merge(
			function_exists( 'hvnly_protected_option_keys' ) ? hvnly_protected_option_keys() : array(),
			array(
				'hvnly_settings',
				'hvnly_cache_enabled',
				'hvnly_cache_ttl',
				'hvnly_demo_properties_imported',
				'hvnly_migrations_completed',
				'hvnly_migration_failed',
				'hvnly_migration_error',
				'hvnly_migration_last_step',
				'hvnly_latest_snapshot_id',
			)
		);
	}

	/**
	 * Generate full inventory array for reports / REST export.
	 *
	 * @return array<string, mixed>
	 */
	public static function generate_report(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Data preservation inventory requires direct meta key scan.
		$distinct_meta = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND p.post_status != 'trash'
				AND (
					pm.meta_key LIKE %s OR pm.meta_key LIKE %s OR pm.meta_key LIKE %s
					OR pm.meta_key LIKE %s OR pm.meta_key LIKE %s OR pm.meta_key LIKE %s
					OR pm.meta_key LIKE %s OR pm.meta_key LIKE %s OR pm.meta_key LIKE %s
					OR pm.meta_key LIKE %s OR pm.meta_key = %s
				)
				ORDER BY pm.meta_key ASC
				LIMIT 5000",
				'hvnly_property',
				$wpdb->esc_like( '_hvnly_' ) . '%',
				$wpdb->esc_like( 'gallery_' ) . '%',
				$wpdb->esc_like( 'video_' ) . '%',
				$wpdb->esc_like( 'map_' ) . '%',
				$wpdb->esc_like( 'property_docs_' ) . '%',
				$wpdb->esc_like( 'faq_' ) . '%',
				$wpdb->esc_like( 'repeater_' ) . '%',
				$wpdb->esc_like( 'agents_' ) . '%',
				$wpdb->esc_like( 'features_' ) . '%',
				$wpdb->esc_like( 'preset_hvnly_' ) . '%',
				'_thumbnail_id'
			)
		);

		return array(
			'generated_at'           => gmdate( 'c' ),
			'plugin_version'         => defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '',
			'property_count'         => BatchProcessor::count_properties(),
			'static_property_meta'   => self::static_property_meta_keys(),
			'dynamic_prefix_patterns' => self::dynamic_prefix_patterns(),
			'builder_field_keys'     => self::builder_field_keys(),
			'option_keys'            => self::option_keys(),
			'distinct_meta_in_db'    => is_array( $distinct_meta ) ? $distinct_meta : array(),
			'distinct_meta_count'    => is_array( $distinct_meta ) ? count( $distinct_meta ) : 0,
		);
	}
}
