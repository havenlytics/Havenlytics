<?php
/**
 * Exports agency taxonomy terms.
 *
 * @package HvnlyNab\ImportExport\Export
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Export;

use HvnlyNab\Agent\AgencyFields;
use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ImportExport\Support\PortableFieldEncoder;

defined( 'ABSPATH' ) || exit;

/**
 * Agency exporter.
 *
 * @since 3.6.0
 */
final class AgenciesExporter {

	/**
	 * Portable agency meta keys (no logo attachment ID).
	 *
	 * @var string[]
	 */
	private const META_KEYS = array(
		AgencyFields::META_ADDRESS,
		AgencyFields::META_LICENSE,
		AgencyFields::META_MAP_PROVIDER,
		AgencyFields::META_MAP_LAT,
		AgencyFields::META_MAP_LNG,
		AgencyFields::META_EMAIL,
		AgencyFields::META_MOBILE,
		AgencyFields::META_FAX,
		AgencyFields::META_OFFICE,
		AgencyFields::META_WEBSITE,
		AgencyFields::META_VIMEO,
		AgencyFields::META_FACEBOOK,
		AgencyFields::META_TWITTER,
		AgencyFields::META_PINTEREST,
		AgencyFields::META_INSTAGRAM,
		AgencyFields::META_YOUTUBE,
		AgencyFields::META_LINKEDIN,
		AgencyFields::META_TIKTOK,
	);

	/**
	 * @param PortableFieldEncoder $encoder Encoder.
	 * @return array<int, array<string, mixed>>
	 */
	public static function export( PortableFieldEncoder $encoder ): array {
		$taxonomy = AgentConstants::TAXONOMY_AGENCY;
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			$parent_slug = '';
			if ( $term->parent > 0 ) {
				$parent = get_term( (int) $term->parent, $taxonomy );
				if ( $parent instanceof \WP_Term ) {
					$parent_slug = (string) $parent->slug;
				}
			}

			$meta = array();
			foreach ( self::META_KEYS as $key ) {
				$val = get_term_meta( (int) $term->term_id, $key, true );
				if ( '' !== (string) $val && false !== $val ) {
					$short          = preg_replace( '/^hvnly_agency_/', '', $key );
					$meta[ $short ] = $val;
				}
			}

			$logo_id = class_exists( AgencyFields::class )
				? AgencyFields::get_logo_id( (int) $term->term_id )
				: 0;
			$logo    = $logo_id > 0 ? $encoder->attachment_stub( $logo_id ) : null;

			$out[] = array(
				'slug'        => (string) $term->slug,
				'name'        => (string) $term->name,
				'description' => (string) $term->description,
				'parent_slug' => $parent_slug,
				'meta'        => $meta,
				'logo'        => $logo,
			);
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( (string) $a['slug'], (string) $b['slug'] );
			}
		);

		return $out;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
