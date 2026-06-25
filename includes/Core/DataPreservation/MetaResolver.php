<?php
/**
 * Universal property meta resolver — never return empty if data exists under legacy keys.
 *
 * @package HvnlyNab\Core\DataPreservation
 * @since   3.0.2
 */

namespace HvnlyNab\Core\DataPreservation;

use HvnlyNab\Core\GroupFieldIdentity;

defined( 'ABSPATH' ) || exit;

class MetaResolver {

	/** @var array<string, array<string>> Legacy flat key aliases by logical suffix. */
	private const LEGACY_ALIASES = array(
		'video' => array(
			'title'     => '_hvnly_property_youtube_video_title',
			'url'       => '_hvnly_property_youtube_video_url',
			'thumbnail' => '_hvnly_property_youtube_video_thumbnail',
		),
		'gallery' => array(
			'title'  => '_hvnly_property_gallery_title',
			'images' => '_hvnly_property_gallery_images',
		),
		'map' => array(
			'address'   => '_hvnly_property_map_location_address',
			'latitude'  => '_hvnly_property_location_Latitude',
			'longitude' => '_hvnly_property_location_Longitude',
		),
		'property_docs' => array(
			'documents' => '_hvnly_property_documents',
		),
	);

	/**
	 * Resolve a field value using full compatibility chain.
	 *
	 * Strictly scoped fields (group_id + group_base_id + name) never resolve by group_type alone.
	 *
	 * @param int          $post_id Post ID.
	 * @param array        $field   Builder field config row.
	 * @param string       $primary Primary meta key (field name).
	 * @return mixed
	 */
	public static function get_field_value( int $post_id, array $field, string $primary = '' ) {
		if ( class_exists( GroupFieldIdentity::class ) ) {
			$field = GroupFieldIdentity::hydrate_field_identity( $field );
		}

		$primary    = $primary !== '' ? $primary : (string) ( $field['name'] ?? $field['id'] ?? '' );
		$group_type = $field['group_type'] ?? '';
		$meta_key   = $field['metaKey'] ?? '';
		$strict     = class_exists( GroupFieldIdentity::class )
			&& GroupFieldIdentity::is_strictly_scoped_field( $field );

		// 1. Current builder key.
		if ( $primary !== '' ) {
			$value = get_post_meta( $post_id, $primary, true );
			if ( self::has_value( $value ) ) {
				return $value;
			}
		}

		// Non-group: done.
		if ( $group_type === '' || $meta_key === '' ) {
			return '';
		}

		// 2. Field map storage base + suffix.
		if ( class_exists( GroupFieldIdentity::class ) ) {
			$storage = GroupFieldIdentity::resolve_storage_base( $post_id, $field );
			if ( $storage ) {
				$mapped_key = $storage . '_' . $meta_key;
				if ( $mapped_key !== $primary ) {
					$value = get_post_meta( $post_id, $mapped_key, true );
					if ( self::has_value( $value ) ) {
						return $value;
					}
				}
			}

			if ( ! $strict ) {
				$legacy_base = GroupFieldIdentity::resolve_legacy_base( $post_id, $field );
				if ( $legacy_base ) {
					$legacy_key = $legacy_base . '_' . $meta_key;
					if ( $legacy_key !== $primary ) {
						$value = get_post_meta( $post_id, $legacy_key, true );
						if ( self::has_value( $value ) ) {
							return $value;
						}
					}
				}
			}
		}

		// Isolated group instances must not read another section's data.
		if ( $strict ) {
			return '';
		}

		// 3. Static legacy aliases (import compatibility).
		if ( isset( self::LEGACY_ALIASES[ $group_type ][ $meta_key ] ) ) {
			$legacy_static = self::LEGACY_ALIASES[ $group_type ][ $meta_key ];
			$value         = get_post_meta( $post_id, $legacy_static, true );
			if ( self::has_value( $value ) ) {
				return $value;
			}
		}

		// 4. Full meta scan — first key matching type suffix for this group instance.
		$map           = class_exists( GroupFieldIdentity::class )
			? GroupFieldIdentity::get_field_map( $post_id )
			: array( 'groups' => array(), 'legacy' => array() );
		$allowed_bases = array();

		if ( ! empty( $field['group_id'] ) && ! empty( $map['groups'][ $field['group_id'] ] ) ) {
			$allowed_bases[] = $map['groups'][ $field['group_id'] ];
		}
		if ( ! empty( $field['group_base_id'] ) ) {
			$allowed_bases[] = $field['group_base_id'];
		}
		if ( ! empty( $map['legacy'][ $group_type ] ) ) {
			$legacy_type_base = (string) $map['legacy'][ $group_type ];
			if ( empty( $field['group_base_id'] ) || $field['group_base_id'] === $legacy_type_base ) {
				$allowed_bases[] = $legacy_type_base;
			}
		}

		if ( empty( $allowed_bases ) ) {
			return '';
		}

		$suffix = '_' . $meta_key;
		foreach ( array_keys( get_post_meta( $post_id ) ) as $key ) {
			if ( strpos( $key, $group_type . '_' ) !== 0 ) {
				continue;
			}
			if ( substr( $key, -strlen( $suffix ) ) !== $suffix ) {
				continue;
			}
			$base = substr( $key, 0, -strlen( $suffix ) );
			if ( ! in_array( $base, $allowed_bases, true ) ) {
				continue;
			}
			$value = get_post_meta( $post_id, $key, true );
			if ( self::has_value( $value ) ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * @param mixed $value Raw meta value.
	 * @return bool
	 */
	private static function has_value( $value ): bool {
		return $value !== '' && $value !== false && $value !== null;
	}
}
