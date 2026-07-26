<?php
/**
 * Exports property taxonomy terms (portable by slug).
 *
 * @package HvnlyNab\ImportExport\Export
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Export;

use HvnlyNab\ImportExport\Support\PortableFieldEncoder;

defined( 'ABSPATH' ) || exit;

/**
 * Property taxonomy exporter.
 *
 * @since 3.6.0
 */
final class TermsExporter {

	/**
	 * Active property taxonomies.
	 *
	 * @var string[]
	 */
	public const TAXONOMIES = array(
		'hvnly_prop_depts',
		'hvnly_prop_types',
		'hvnly_prop_features',
		'hvnly_prop_locations',
		'hvnly_prop_status',
		'hvnly_prop_badges',
		'hvnly_prop_tags',
	);

	/**
	 * @param PortableFieldEncoder $encoder Encoder for term image stubs.
	 * @return array<int, array<string, mixed>>
	 */
	public static function export( PortableFieldEncoder $encoder ): array {
		$out = array();

		foreach ( self::TAXONOMIES as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}

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

				$row = array(
					'taxonomy'    => $taxonomy,
					'slug'        => (string) $term->slug,
					'name'        => (string) $term->name,
					'description' => (string) $term->description,
					'parent_slug' => $parent_slug,
					'meta'        => self::portable_term_meta( $term, $encoder ),
				);

				$out[] = $row;
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				$c = strcmp( (string) $a['taxonomy'], (string) $b['taxonomy'] );
				return 0 !== $c ? $c : strcmp( (string) $a['slug'], (string) $b['slug'] );
			}
		);

		return $out;
	}

	/**
	 * @param \WP_Term             $term    Term.
	 * @param PortableFieldEncoder $encoder Encoder.
	 * @return array<string, mixed>
	 */
	private static function portable_term_meta( \WP_Term $term, PortableFieldEncoder $encoder ): array {
		$meta = array();
		$tid  = (int) $term->term_id;

		$image = get_term_meta( $tid, '_hvnly_term_advanced_image_data', true );
		if ( is_array( $image ) && ! empty( $image['id'] ) ) {
			$stub = $encoder->attachment_stub( (int) $image['id'] );
			if ( null !== $stub ) {
				$meta['image'] = $stub;
			}
		}

		$icon = get_term_meta( $tid, '_hvnly_advanced_icon_data', true );
		if ( is_array( $icon ) && ! empty( $icon ) ) {
			$meta['icon'] = array(
				'class'   => isset( $icon['class'] ) ? (string) $icon['class'] : '',
				'library' => isset( $icon['library'] ) ? (string) $icon['library'] : '',
			);
		}

		$color = get_term_meta( $tid, 'hvnly_badge_background_color', true );
		if ( '' !== (string) $color ) {
			$meta['badge_background_color'] = (string) $color;
		}

		$display = get_term_meta( $tid, 'hvnly_badge_display_option', true );
		if ( '' !== (string) $display ) {
			$meta['badge_display_option'] = (string) $display;
		}

		return $meta;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
