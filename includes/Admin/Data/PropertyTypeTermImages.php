<?php
/**
 * Demo Property Type term image map for import seeding.
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
 * Maps default demo hvnly_prop_types slugs to stock image URLs.
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
	 * @return array<string, string> Slug => image URL.
	 */
	public static function get_image_map(): array {
		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$images = DemoData::get_property_images();
		$map    = array();

		if ( empty( $images ) ) {
			return $map;
		}

		$total = count( $images );
		foreach ( self::DEMO_TYPE_SLUGS as $index => $slug ) {
			$map[ $slug ] = esc_url_raw( $images[ $index % $total ] );
		}

		return $map;
	}
}
