<?php
/**
 * Export exclusion lists for HPTP entity packages.
 *
 * @package HvnlyNab\ImportExport\Support
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Keys and patterns that must never appear in exported property packages.
 *
 * @since 3.6.0
 */
final class ExportExclusions {

	/**
	 * Property post meta keys never exported.
	 *
	 * @return string[]
	 */
	public static function property_meta_keys(): array {
		return array(
			'_hvnly_importing',
			'_hvnly_import_session_id',
			'_hvnly_import_index',
			'_hvnly_orphan_candidates',
			'_hvnly_property_views',
			'_hvnly_property_view_analytics',
			'_hvnly_last_emailed_listing_status',
			'_hvnly_property_agent', // legacy WP user ID — not portable.
			'_hvnly_ie_pending_media',
			'_hvnly_ie_quarantine_fields',
		);
	}

	/**
	 * Agent post meta keys never exported as raw values.
	 *
	 * @return string[]
	 */
	public static function agent_meta_keys(): array {
		return array(
			'_hvnly_agent_linked_user_id',
			'_hvnly_agent_registered_at',
		);
	}

	/**
	 * Whether a property meta key is excluded.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public static function is_excluded_property_meta( string $key ): bool {
		if ( in_array( $key, self::property_meta_keys(), true ) ) {
			return true;
		}

		// Transients / internal bookkeeping prefixes.
		if ( 0 === strpos( $key, '_transient_' ) || 0 === strpos( $key, '_site_transient_' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
