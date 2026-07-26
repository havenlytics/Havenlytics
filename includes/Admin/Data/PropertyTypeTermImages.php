<?php
/**
 * Local placeholder URLs for Property Type taxonomy terms (import wizard).
 *
 * @package     Havenlytics
 * @subpackage  Admin/Data
 * @since       3.1.2
 */

namespace HvnlyNab\Admin\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared local property-type placeholder for demo type terms.
 *
 * No external CDN / demo.havenlytics.com downloads — keeps demo import
 * offline-safe and lightweight (same strategy as UkLocationTermImages).
 */
class PropertyTypeTermImages {

	/**
	 * Default demo import property type slugs (do not overwrite custom types).
	 *
	 * @var string[]
	 */
	private const DEMO_TYPE_SLUGS = array(
		'cottage',
		'duplex',
		'flat',
		'land',
		'garage',
		'mews',
		'triplex',
	);

	/**
	 * Plugin-relative path to the shared property-type placeholder SVG.
	 *
	 * @var string
	 */
	private const PLACEHOLDER_REL = 'images/placeholders/property-type-placeholder.svg';

	/**
	 * @return string[]
	 */
	public static function get_demo_type_slugs(): array {
		return self::DEMO_TYPE_SLUGS;
	}

	/**
	 * Whether a slug is a built-in demo property type.
	 *
	 * @param string $slug Term slug.
	 * @return bool
	 */
	public static function is_demo_type_slug( string $slug ): bool {
		return in_array( sanitize_title( $slug ), self::DEMO_TYPE_SLUGS, true );
	}

	/**
	 * Absolute URL for the shared property-type placeholder asset.
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
	 * Absolute filesystem path for the shared property-type placeholder asset.
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
	 * @return array<string, string> Slug => placeholder URL (same asset for every demo type).
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

		foreach ( self::DEMO_TYPE_SLUGS as $slug ) {
			$map[ $slug ] = $url;
		}

		return $map;
	}

	/**
	 * @param string $slug Property type term slug.
	 * @return string Shared local placeholder URL.
	 */
	public static function get_image_url_for_slug( string $slug ): string {
		unset( $slug );
		return self::placeholder_url();
	}

	/**
	 * Demo fallback for property type terms — always the local placeholder (no remote CDN).
	 *
	 * @param string $slug Property type term slug.
	 * @return string
	 */
	public static function get_demo_fallback_url( string $slug ): string {
		unset( $slug );
		return self::placeholder_url();
	}
}
