<?php
/**
 * Central Free / Pro limits for the HPTP Migration Engine.
 *
 * Single source of truth — never duplicate these checks elsewhere.
 *
 * @package HvnlyNab\ImportExport\Capability
 * @since   3.6.1
 */

namespace HvnlyNab\ImportExport\Capability;

use HvnlyNab\Agent\AgentConstants;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * MigrationLimits — extensible capability gate for migration import / export.
 *
 * Single source of truth — never duplicate these checks elsewhere.
 *
 * @since 3.6.1
 */
final class MigrationLimits {

	public const FREE_MAX_PROPERTIES = 50;

	public const DIMENSION_PROPERTIES = 'properties';

	/**
	 * Whether this install is treated as Pro for migration.
	 *
	 * Detection order:
	 * 1. HVNLY_IS_PRO constant
	 * 2. hvnly_migration_is_pro filter
	 *
	 * @return bool
	 */
	public static function is_pro(): bool {
		if ( defined( 'HVNLY_IS_PRO' ) && HVNLY_IS_PRO ) {
			return true;
		}

		/**
		 * Filter whether Migration Pro (unlimited) is unlocked.
		 *
		 * @since 3.6.1
		 *
		 * @param bool $is_pro Default false (Free).
		 */
		return (bool) apply_filters( 'hvnly_migration_is_pro', false );
	}

	/**
	 * Maximum properties allowed for export/import (PHP_INT_MAX when Pro).
	 *
	 * @param string $dimension Limit dimension (future: agents, media, …).
	 * @return int
	 */
	public static function max_for( string $dimension = self::DIMENSION_PROPERTIES ): int {
		if ( self::is_pro() ) {
			return PHP_INT_MAX;
		}

		$defaults = array(
			self::DIMENSION_PROPERTIES => self::FREE_MAX_PROPERTIES,
		);

		/**
		 * Filter Free-tier migration limits by dimension.
		 *
		 * @since 3.6.1
		 *
		 * @param array<string, int> $defaults Dimension => max.
		 */
		$limits = (array) apply_filters( 'hvnly_migration_free_limits', $defaults );

		return isset( $limits[ $dimension ] ) ? max( 0, (int) $limits[ $dimension ] ) : self::FREE_MAX_PROPERTIES;
	}

	/**
	 * @return int
	 */
	public static function max_properties(): int {
		return self::max_for( self::DIMENSION_PROPERTIES );
	}

	/**
	 * Count properties that would be included in a migration export.
	 *
	 * @param array<string, mixed> $options Export options (statuses optional).
	 * @return int
	 */
	public static function count_exportable_properties( array $options = array() ): int {
		$statuses = isset( $options['statuses'] ) && is_array( $options['statuses'] )
			? array_map( 'strval', $options['statuses'] )
			: array( 'publish', 'draft', 'pending', 'private', 'expired' );

		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'            => $statuses,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Resolve property count from a package manifest and/or entities payload.
	 *
	 * Prefer manifest counts; fall back to counting entities.properties.
	 *
	 * @param array<string, mixed>|null $manifest Manifest array.
	 * @param array<string, mixed>|null $entities Entities array.
	 * @return int
	 */
	public static function count_package_properties( $manifest = null, $entities = null ): int {
		if ( is_array( $manifest ) && isset( $manifest['counts'] ) && is_array( $manifest['counts'] ) ) {
			if ( isset( $manifest['counts']['properties'] ) ) {
				return max( 0, (int) $manifest['counts']['properties'] );
			}
		}

		if ( is_array( $entities ) && isset( $entities['properties'] ) && is_array( $entities['properties'] ) ) {
			return count( $entities['properties'] );
		}

		return 0;
	}

	/**
	 * Whether an export of $count properties is allowed.
	 *
	 * @param int                  $count   Property count.
	 * @param array<string, mixed> $options Unused reserved for future dimensions.
	 * @return true|WP_Error
	 */
	public static function can_export_properties( int $count, array $options = array() ) {
		unset( $options );

		$max = self::max_properties();
		if ( $count <= $max ) {
			return true;
		}

		$upgrade = self::upgrade_url();

		return new WP_Error(
			'hvnly_ie_migration_limit',
			sprintf(
				/* translators: 1: property count on site, 2: free max */
				__(
					'This website contains %1$d properties. Havenlytics Free allows migration of up to %2$d properties. Upgrade to Havenlytics Pro to migrate unlimited listings.',
					'havenlytics'
				),
				$count,
				self::FREE_MAX_PROPERTIES
			),
			array(
				'count'       => $count,
				'max'         => self::FREE_MAX_PROPERTIES,
				'is_pro'      => false,
				'upgrade_url' => $upgrade,
				'dimension'   => self::DIMENSION_PROPERTIES,
				'operation'   => 'export',
			)
		);
	}

	/**
	 * Whether importing a package with $count properties is allowed.
	 *
	 * @param int $count Property count from package manifest / entities.
	 * @return true|WP_Error
	 */
	public static function can_import_properties( int $count ) {
		$max = self::max_properties();
		if ( $count <= $max ) {
			return true;
		}

		$upgrade = self::upgrade_url();

		return new WP_Error(
			'hvnly_ie_migration_limit',
			sprintf(
				/* translators: 1: properties in package, 2: free max */
				__(
					'This migration package contains %1$d properties. The Free edition supports migration of up to %2$d properties. Upgrade to Havenlytics Pro to migrate unlimited properties.',
					'havenlytics'
				),
				$count,
				self::FREE_MAX_PROPERTIES
			),
			array(
				'count'       => $count,
				'max'         => self::FREE_MAX_PROPERTIES,
				'is_pro'      => false,
				'upgrade_url' => $upgrade,
				'dimension'   => self::DIMENSION_PROPERTIES,
				'operation'   => 'import',
			)
		);
	}

	/**
	 * Assert Free import limit from manifest and/or entities before any DB writes.
	 *
	 * @param array<string, mixed>|null $manifest Manifest.
	 * @param array<string, mixed>|null $entities Entities.
	 * @return true|WP_Error
	 */
	public static function assert_import_allowed( $manifest = null, $entities = null ) {
		$count = self::count_package_properties( $manifest, $entities );
		return self::can_import_properties( $count );
	}

	/**
	 * Evaluate limits for the current site (used by export_start / prepare / finalize).
	 *
	 * @param array<string, mixed> $options Export options.
	 * @return true|WP_Error
	 */
	public static function assert_export_allowed( array $options = array() ) {
		if ( empty( $options['include_properties'] ) && array_key_exists( 'include_properties', $options ) ) {
			return true;
		}

		$count = self::count_exportable_properties( $options );
		return self::can_export_properties( $count, $options );
	}

	/**
	 * Public payload for Settings UI.
	 *
	 * @return array<string, mixed>
	 */
	public static function public_status(): array {
		$count = self::count_exportable_properties();
		$max   = self::max_properties();
		$ok    = self::is_pro() || $count <= self::FREE_MAX_PROPERTIES;

		return array(
			'is_pro'           => self::is_pro(),
			'property_count'   => $count,
			'free_max'         => self::FREE_MAX_PROPERTIES,
			'max_properties'   => $max === PHP_INT_MAX ? null : $max,
			'export_allowed'   => $ok,
			'upgrade_url'      => self::upgrade_url(),
		);
	}

	/**
	 * @return string
	 */
	public static function upgrade_url(): string {
		/**
		 * Filter the Havenlytics Pro upgrade URL shown in Migration notices.
		 *
		 * @since 3.6.1
		 *
		 * @param string $url Default marketing URL.
		 */
		return (string) apply_filters(
			'hvnly_pro_upgrade_url',
			'https://havenlytics.com/pricing/'
		);
	}
}
