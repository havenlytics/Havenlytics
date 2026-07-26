<?php
/**
 * Local placeholder URLs for UK Property Location taxonomy terms (import wizard).
 *
 * @package     Havenlytics
 * @subpackage  Admin/Data
 * @since       3.0.5
 */

namespace HvnlyNab\Admin\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared local location placeholder for all demo location terms.
 *
 * No external CDN / Pexels URLs — keeps demo import offline-safe and lightweight.
 */
class UkLocationTermImages {

	/**
	 * Plugin-relative path to the shared location placeholder SVG.
	 *
	 * @var string
	 */
	private const PLACEHOLDER_REL = 'images/placeholders/location-placeholder.svg';

	/**
	 * Absolute URL for the shared location placeholder asset.
	 *
	 * @return string
	 */
	public static function placeholder_url(): string {
		if ( defined( 'HVNLYNAB_ASSETS_URL' ) && HVNLYNAB_ASSETS_URL ) {
			return esc_url_raw( trailingslashit( HVNLYNAB_ASSETS_URL ) . self::PLACEHOLDER_REL );
		}

		return '';
	}

	/**
	 * Absolute filesystem path for the shared location placeholder asset.
	 *
	 * @return string
	 */
	public static function placeholder_path(): string {
		if ( defined( 'HVNLYNAB_ASSETS_PATH' ) && HVNLYNAB_ASSETS_PATH ) {
			$path = trailingslashit( HVNLYNAB_ASSETS_PATH ) . self::PLACEHOLDER_REL;
			return is_readable( $path ) ? $path : '';
		}

		return '';
	}

	/**
	 * @return array<string, string> Slug => placeholder URL (same asset for every city).
	 */
	public static function get_image_map(): array {
		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$map = array();
		$url = self::placeholder_url();
		if ( '' === $url ) {
			return $map;
		}

		foreach ( UkImportLocations::get_locations() as $location ) {
			$slug = (string) ( $location['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$map[ $slug ] = $url;
		}

		return $map;
	}

	/**
	 * @param string $slug Location term slug.
	 * @return string Shared local placeholder URL.
	 */
	public static function get_image_url_for_slug( string $slug ): string {
		unset( $slug );
		return self::placeholder_url();
	}

	/**
	 * Demo fallback for location terms — always the local placeholder (no remote CDN).
	 *
	 * @param string $slug Location term slug.
	 * @return string
	 */
	public static function get_demo_fallback_url( string $slug ): string {
		unset( $slug );
		return self::placeholder_url();
	}
}
