<?php
/**
 * Havenlytics data deletion safety — production preservation layer.
 *
 * Never delete property / dynamic field meta automatically.
 * Use soft orphan flags unless an explicit admin-confirmed context is provided.
 *
 * @package Havenlytics
 * @since   3.0.2
 */

defined( 'ABSPATH' ) || exit;

/** Admin must pass confirm=true on REST/AJAX destructive actions. */
if ( ! defined( 'HVNLY_REQUIRE_DELETE_CONFIRM' ) ) {
	define( 'HVNLY_REQUIRE_DELETE_CONFIRM', true );
}

/** Migrations must not hard-delete property meta (soft-flag only). */
if ( ! defined( 'HVNLY_MIGRATION_SOFT_DELETE_ONLY' ) ) {
	define( 'HVNLY_MIGRATION_SOFT_DELETE_ONLY', true );
}

/** Batch size for property scans during migrations. */
if ( ! defined( 'HVNLY_MIGRATION_BATCH_SIZE' ) ) {
	define( 'HVNLY_MIGRATION_BATCH_SIZE', 100 );
}

/**
 * Meta key prefix patterns that must never be bulk-deleted.
 *
 * @return string[]
 */
function hvnly_protected_meta_prefixes(): array {
	return array(
		'_hvnly_',
		'_havenlytics_',
		'gallery_',
		'video_',
		'map_',
		'property_docs_',
		'preset_hvnly_property_field_',
	);
}

/**
 * Option keys that must never be deleted during upgrades/imports.
 *
 * @return string[]
 */
function hvnly_protected_option_keys(): array {
	return array(
		'hvnly_plugin_settings',
		'hvnly_property_builder.sections',
		'hvnly_property_card.sections',
		'hvnly_pb_card_builder_sections',
		'hvnly_card_builder_config',
		'hvnly_master_base_ids',
		'hvnly_db_version',
		'hvnly_migrations_completed',
		'hvnly_backup_timestamp',
		'hvnly_backup_version',
	);
}

/**
 * Whether a post meta key is protected Havenlytics property data.
 *
 * @param string $meta_key Meta key.
 * @return bool
 */
function hvnly_is_protected_meta_key( string $meta_key ): bool {
	if ( $meta_key === '' ) {
		return false;
	}
	foreach ( hvnly_protected_meta_prefixes() as $prefix ) {
		if ( strpos( $meta_key, $prefix ) === 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * Allowed deletion contexts.
 *
 * @param string $context Context slug.
 * @return bool
 */
function hvnly_is_allowed_delete_context( string $context ): bool {
	if ( $context === 'developer_override_delete' ) {
		return hvnly_allow_protected_meta_delete();
	}
	$allowed = array(
		'user_save_empty',        // User cleared a field in metabox.
		'import_transient',       // _hvnly_importing flag.
		'post_permanent_delete',  // Property post permanently deleted.
		'admin_confirmed_delete', // Explicit REST cleanup with confirm.
		'developer_override_delete', // MetaCleanup + HVNLY_ALLOW_PROTECTED_META_DELETE only.
		'import_wizard_reset',    // Import wizard reset with zero properties.
		'uninstall',              // Plugin uninstall.php only.
		'rollback_restore',       // Restoring from backup snapshot.
	);
	return in_array( $context, $allowed, true );
}

/**
 * Soft-mark a meta key as orphan candidate instead of deleting.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key.
 * @return bool
 */
function hvnly_mark_orphan_candidate( int $post_id, string $meta_key ): bool {
	$flag_key = '_hvnly_orphan_candidates';
	$existing = get_post_meta( $post_id, $flag_key, true );
	$list     = is_string( $existing ) ? json_decode( $existing, true ) : array();
	if ( ! is_array( $list ) ) {
		$list = array();
	}
	if ( ! in_array( $meta_key, $list, true ) ) {
		$list[] = $meta_key;
	}
	return (bool) update_post_meta( $post_id, $flag_key, wp_json_encode( array_values( $list ) ) );
}

/**
 * Safe delete post meta — blocks automatic deletion of protected keys.
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key.
 * @param string $context  Deletion context (see hvnly_is_allowed_delete_context).
 * @return bool True if deleted or soft-flagged; false if blocked.
 */
function hvnly_safe_delete_post_meta( int $post_id, string $meta_key, string $context = '' ): bool {
	if ( hvnly_is_protected_meta_key( $meta_key ) ) {
		if ( ! hvnly_is_allowed_delete_context( $context ) ) {
			if ( HVNLY_MIGRATION_SOFT_DELETE_ONLY || $context === 'migration' ) {
				return hvnly_mark_orphan_candidate( $post_id, $meta_key );
			}
			if ( function_exists( 'hvnly_debug_log' ) ) {
				hvnly_debug_log(
					sprintf( 'Blocked delete_post_meta(%d, %s) context=%s', $post_id, $meta_key, $context ),
					'DataPreservation'
				);
			}
			return false;
		}
	}
	return (bool) delete_post_meta( $post_id, $meta_key );
}

/**
 * Safe delete option — blocks protected builder/settings keys.
 *
 * @param string $option  Option name.
 * @param string $context Deletion context.
 * @return bool
 */
function hvnly_safe_delete_option( string $option, string $context = '' ): bool {
	if ( in_array( $option, hvnly_protected_option_keys(), true ) ) {
		if ( ! hvnly_is_allowed_delete_context( $context ) ) {
			if ( function_exists( 'hvnly_debug_log' ) ) {
				hvnly_debug_log(
					sprintf( 'Blocked delete_option(%s) context=%s', $option, $context ),
					'DataPreservation'
				);
			}
			return false;
		}
	}
	return delete_option( $option );
}

/**
 * REST/AJAX destructive action confirmation.
 *
 * @param bool $confirm Value from request.
 * @return bool
 */
function hvnly_rest_deletion_confirmed( bool $confirm ): bool {
	if ( ! HVNLY_REQUIRE_DELETE_CONFIRM ) {
		return true;
	}
	return $confirm && current_user_can( 'manage_options' );
}

/**
 * Deep-merge settings preserving unknown/future keys.
 *
 * @param array $existing Existing settings tree.
 * @param array $incoming Incoming partial tree.
 * @return array
 */
function hvnly_deep_merge_settings( array $existing, array $incoming ): array {
	foreach ( $incoming as $key => $value ) {
		if ( is_array( $value ) && isset( $existing[ $key ] ) && is_array( $existing[ $key ] ) ) {
			$existing[ $key ] = hvnly_deep_merge_settings( $existing[ $key ], $value );
		} else {
			$existing[ $key ] = $value;
		}
	}
	return $existing;
}

/**
 * Add meta only when missing (never overwrite during migration).
 *
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key.
 * @param mixed  $value    Value.
 * @return bool
 */
function hvnly_safe_add_post_meta( int $post_id, string $meta_key, $value ): bool {
	$existing = get_post_meta( $post_id, $meta_key, true );
	if ( $existing !== '' && $existing !== false && $existing !== null ) {
		return true;
	}
	return (bool) add_post_meta( $post_id, $meta_key, $value, true );
}

/**
 * Copy post meta from one key to another when target is empty.
 *
 * @param int    $post_id   Post ID.
 * @param string $from_key  Source key.
 * @param string $to_key    Target key.
 * @return bool True if copied or target already had data.
 */
function hvnly_copy_post_meta_if_empty( int $post_id, string $from_key, string $to_key ): bool {
	if ( $from_key === $to_key ) {
		return true;
	}
	$target = get_post_meta( $post_id, $to_key, true );
	if ( $target !== '' && $target !== false && $target !== null ) {
		return true;
	}
	$source = get_post_meta( $post_id, $from_key, true );
	if ( $source === '' || $source === false || $source === null ) {
		return false;
	}
	return (bool) update_post_meta( $post_id, $to_key, $source );
}

/**
 * Whether protected Havenlytics meta may be hard-deleted (wp-config developer override).
 *
 * Define in wp-config.php: define( 'HVNLY_ALLOW_PROTECTED_META_DELETE', true );
 *
 * @return bool
 */
function hvnly_allow_protected_meta_delete(): bool {
	return defined( 'HVNLY_ALLOW_PROTECTED_META_DELETE' ) && HVNLY_ALLOW_PROTECTED_META_DELETE;
}

/**
 * Storage bases recorded in a property's _hvnly_field_map.
 *
 * @param int $post_id Property post ID.
 * @return string[]
 */
function hvnly_meta_cleanup_field_map_bases( int $post_id ): array {
	$bases = array();
	if ( ! class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
		return $bases;
	}
	$map = \HvnlyNab\Core\GroupFieldIdentity::get_field_map( $post_id );
	foreach ( (array) ( $map['groups'] ?? array() ) as $base ) {
		if ( is_string( $base ) && $base !== '' ) {
			$bases[] = $base;
		}
	}
	foreach ( (array) ( $map['legacy'] ?? array() ) as $base ) {
		if ( is_string( $base ) && $base !== '' ) {
			$bases[] = $base;
		}
	}
	return array_values( array_unique( $bases ) );
}

/**
 * group_base_id values from the live property builder config.
 *
 * @return string[]
 */
function hvnly_meta_cleanup_builder_storage_bases(): array {
	$sections = get_option( 'hvnly_property_builder.sections', array() );
	$bases    = array();
	if ( ! is_array( $sections ) ) {
		return $bases;
	}
	foreach ( $sections as $section ) {
		foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
			$base = $field['group_base_id'] ?? '';
			if ( is_string( $base ) && $base !== '' ) {
				$bases[] = $base;
			}
		}
	}
	return array_values( array_unique( $bases ) );
}

/**
 * Whether a meta key belongs to a known storage base prefix.
 *
 * @param string   $meta_key Meta key.
 * @param string[] $bases    Base ID prefixes.
 * @return bool
 */
function hvnly_meta_key_matches_storage_base( string $meta_key, array $bases ): bool {
	foreach ( $bases as $base ) {
		if ( $meta_key === $base || strpos( $meta_key, $base . '_' ) === 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * Meta keys that must never be removed by MetaCleanup without a developer override.
 *
 * @param int      $post_id        Property post ID.
 * @param string   $meta_key       Meta key.
 * @param string[] $current_fields Active builder field names.
 * @return bool
 */
function hvnly_meta_cleanup_is_protected( int $post_id, string $meta_key, array $current_fields ): bool {
	$reserved = array(
		'_hvnly_field_map',
		'_hvnly_orphan_candidates',
	);
	if ( in_array( $meta_key, $reserved, true ) ) {
		return true;
	}
	if ( in_array( $meta_key, $current_fields, true ) ) {
		return true;
	}
	if ( function_exists( 'hvnly_is_protected_meta_key' ) && hvnly_is_protected_meta_key( $meta_key ) ) {
		return true;
	}
	$map_bases     = hvnly_meta_cleanup_field_map_bases( $post_id );
	$builder_bases = hvnly_meta_cleanup_builder_storage_bases();
	if ( hvnly_meta_key_matches_storage_base( $meta_key, $map_bases ) ) {
		return true;
	}
	if ( hvnly_meta_key_matches_storage_base( $meta_key, $builder_bases ) ) {
		return true;
	}
	return false;
}

/**
 * Whether MetaCleanup may delete a meta key.
 *
 * Protected / field-map / remapped keys are report-only unless force_protected is set
 * and HVNLY_ALLOW_PROTECTED_META_DELETE is true in wp-config.php.
 *
 * @param int      $post_id         Property post ID.
 * @param string   $meta_key        Meta key.
 * @param string[] $current_fields  Active builder field names.
 * @param bool     $force_protected Requested developer override.
 * @return bool
 */
function hvnly_meta_cleanup_is_deletable( int $post_id, string $meta_key, array $current_fields, bool $force_protected = false ): bool {
	if ( hvnly_meta_cleanup_is_protected( $post_id, $meta_key, $current_fields ) ) {
		return $force_protected && hvnly_allow_protected_meta_delete();
	}
	return true;
}
