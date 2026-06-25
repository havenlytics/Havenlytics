<?php
/**
 * Free stock image URLs for UK Property Location taxonomy terms (import wizard).
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
 * Curated Pexels URLs keyed by hvnly_prop_locations slug.
 */
class UkLocationTermImages {

	/**
	 * City-specific skyline / landmark photos (images.pexels.com — allowed import domain).
	 *
	 * @var array<string, string>
	 */
	private const CITY_IMAGES = array(
		'london'          => 'https://images.pexels.com/photos/672532/pexels-photo-672532.jpeg',
		'manchester'      => 'https://images.pexels.com/photos/631595/pexels-photo-631595.jpeg',
		'birmingham'      => 'https://images.pexels.com/photos/1427107/pexels-photo-1427107.jpeg',
		'liverpool'       => 'https://images.pexels.com/photos/586021/pexels-photo-586021.jpeg',
		'leeds'           => 'https://images.pexels.com/photos/1469854/pexels-photo-1469854.jpeg',
		'bristol'         => 'https://images.pexels.com/photos/1517932/pexels-photo-1517932.jpeg',
		'nottingham'      => 'https://images.pexels.com/photos/161901/nottingham-canal-uk-nottinghamshire-161901.jpeg',
		'sheffield'       => 'https://images.pexels.com/photos/1387147/pexels-photo-1387147.jpeg',
		'newcastle'       => 'https://images.pexels.com/photos/672358/pexels-photo-672358.jpeg',
		'leicester'       => 'https://images.pexels.com/photos/1118875/pexels-photo-1118875.jpeg',
		'edinburgh'       => 'https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg',
		'glasgow'         => 'https://images.pexels.com/photos/1365441/pexels-photo-1365441.jpeg',
		'cardiff'         => 'https://images.pexels.com/photos/32870/bridge-suspension-bridge-climate-cold-bridge.jpg',
		'belfast'         => 'https://images.pexels.com/photos/672358/pexels-photo-672358.jpeg',
		'southampton'     => 'https://images.pexels.com/photos/460672/pexels-photo-460672.jpeg',
		'brighton'        => 'https://images.pexels.com/photos/163236/luxury-home-beach-front-property-163236.jpeg',
		'plymouth'        => 'https://images.pexels.com/photos/460672/pexels-photo-460672.jpeg',
		'reading'         => 'https://images.pexels.com/photos/1118875/pexels-photo-1118875.jpeg',
		'derby'           => 'https://images.pexels.com/photos/1387147/pexels-photo-1387147.jpeg',
		'stoke-on-trent'  => 'https://images.pexels.com/photos/1427107/pexels-photo-1427107.jpeg',
		'wolverhampton'   => 'https://images.pexels.com/photos/1427107/pexels-photo-1427107.jpeg',
		'coventry'        => 'https://images.pexels.com/photos/1118875/pexels-photo-1118875.jpeg',
		'hull'            => 'https://images.pexels.com/photos/460672/pexels-photo-460672.jpeg',
		'sunderland'      => 'https://images.pexels.com/photos/672358/pexels-photo-672358.jpeg',
		'york'            => 'https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg',
		'oxford'          => 'https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg',
		'cambridge'       => 'https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg',
		'norwich'         => 'https://images.pexels.com/photos/1118875/pexels-photo-1118875.jpeg',
		'exeter'          => 'https://images.pexels.com/photos/163236/luxury-home-beach-front-property-163236.jpeg',
		'bournemouth'     => 'https://images.pexels.com/photos/163236/luxury-home-beach-front-property-163236.jpeg',
		'swindon'         => 'https://images.pexels.com/photos/1118875/pexels-photo-1118875.jpeg',
		'milton-keynes'   => 'https://images.pexels.com/photos/1118875/pexels-photo-1118875.jpeg',
		'northampton'     => 'https://images.pexels.com/photos/1387147/pexels-photo-1387147.jpeg',
		'aberdeen'        => 'https://images.pexels.com/photos/1365441/pexels-photo-1365441.jpeg',
		'dundee'          => 'https://images.pexels.com/photos/1365441/pexels-photo-1365441.jpeg',
		'swansea'         => 'https://images.pexels.com/photos/32870/bridge-suspension-bridge-climate-cold-bridge.jpg',
		'newport'         => 'https://images.pexels.com/photos/32870/bridge-suspension-bridge-climate-cold-bridge.jpg',
		'preston'         => 'https://images.pexels.com/photos/631595/pexels-photo-631595.jpeg',
		'blackpool'       => 'https://images.pexels.com/photos/163236/luxury-home-beach-front-property-163236.jpeg',
		'middlesbrough'   => 'https://images.pexels.com/photos/672358/pexels-photo-672358.jpeg',
		'bolton'          => 'https://images.pexels.com/photos/631595/pexels-photo-631595.jpeg',
		'bradford'        => 'https://images.pexels.com/photos/1469854/pexels-photo-1469854.jpeg',
		'luton'           => 'https://images.pexels.com/photos/672532/pexels-photo-672532.jpeg',
		'slough'          => 'https://images.pexels.com/photos/672532/pexels-photo-672532.jpeg',
		'warrington'      => 'https://images.pexels.com/photos/631595/pexels-photo-631595.jpeg',
		'telford'         => 'https://images.pexels.com/photos/1427107/pexels-photo-1427107.jpeg',
		'peterborough'    => 'https://images.pexels.com/photos/1118875/pexels-photo-1118875.jpeg',
		'colchester'      => 'https://images.pexels.com/photos/460672/pexels-photo-460672.jpeg',
		'cheltenham'      => 'https://images.pexels.com/photos/1517932/pexels-photo-1517932.jpeg',
		'bath'            => 'https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg',
		'chester'         => 'https://images.pexels.com/photos/586021/pexels-photo-586021.jpeg',
		'lancaster'       => 'https://images.pexels.com/photos/631595/pexels-photo-631595.jpeg',
		'canterbury'      => 'https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg',
		'ipswich'         => 'https://images.pexels.com/photos/460672/pexels-photo-460672.jpeg',
		'hastings'        => 'https://images.pexels.com/photos/163236/luxury-home-beach-front-property-163236.jpeg',
	);

	/**
	 * Generic UK urban / architecture pool for any slug without a dedicated photo.
	 *
	 * @var string[]
	 */
	private const FALLBACK_IMAGES = array(
		'https://images.pexels.com/photos/672532/pexels-photo-672532.jpeg',
		'https://images.pexels.com/photos/631595/pexels-photo-631595.jpeg',
		'https://images.pexels.com/photos/1427107/pexels-photo-1427107.jpeg',
		'https://images.pexels.com/photos/586021/pexels-photo-586021.jpeg',
		'https://images.pexels.com/photos/1469854/pexels-photo-1469854.jpeg',
		'https://images.pexels.com/photos/1517932/pexels-photo-1517932.jpeg',
		'https://images.pexels.com/photos/1365425/pexels-photo-1365425.jpeg',
		'https://images.pexels.com/photos/1365441/pexels-photo-1365441.jpeg',
	);

	/**
	 * @return array<string, string> Slug => image URL.
	 */
	public static function get_image_map(): array {
		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$map     = self::CITY_IMAGES;
		$pool    = self::FALLBACK_IMAGES;
		$pool_sz = count( $pool );

		foreach ( UkImportLocations::get_locations() as $location ) {
			$slug = (string) ( $location['slug'] ?? '' );
			if ( '' === $slug ) {
				continue;
			}

			if ( empty( $map[ $slug ] ) ) {
				$map[ $slug ] = $pool[ abs( crc32( $slug ) ) % $pool_sz ];
			}

			$map[ $slug ] = esc_url_raw( $map[ $slug ] );
		}

		return $map;
	}

	/**
	 * @param string $slug Location term slug.
	 * @return string
	 */
	public static function get_image_url_for_slug( string $slug ): string {
		$map = self::get_image_map();

		return $map[ $slug ] ?? '';
	}

	/**
	 * Stable demo CDN fallback (same source as property import gallery images).
	 *
	 * @param string $slug Location term slug.
	 * @return string
	 */
	public static function get_demo_fallback_url( string $slug ): string {
		$images = DemoData::get_property_images();
		if ( empty( $images ) ) {
			return '';
		}

		$index = abs( crc32( sanitize_title( $slug ) ) ) % count( $images );

		return esc_url_raw( $images[ $index ] );
	}
}
