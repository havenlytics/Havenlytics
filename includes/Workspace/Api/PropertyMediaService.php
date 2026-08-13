<?php
/**
 * Property media helpers — featured image + gallery attachment IDs.
 *
 * Writes BOTH legacy `_hvnly_property_gallery_images` and the Property Builder
 * gallery field key from {@see \HvnlyNab\Core\UnifiedFieldGenerator}.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Reads/writes featured + gallery media for Workspace drafts.
 *
 * @since 3.2.0
 */
final class PropertyMediaService {

	public const META_GALLERY = '_hvnly_property_gallery_images';

	/** @var string[] */
	public const ALLOWED_MIME = array(
		'image/jpeg',
		'image/png',
		'image/webp',
	);

	public const MAX_BYTES = 5242880; // 5MB

	/**
	 * @param int $property_id Property ID.
	 * @return array{featured: ?array, gallery: array<int, array>}
	 */
	public static function get_media( int $property_id ): array {
		$property_id = absint( $property_id );
		$featured_id = (int) get_post_thumbnail_id( $property_id );
		$gallery_ids = self::get_gallery_ids( $property_id );

		return array(
			'featured' => $featured_id > 0 ? self::serialize_attachment( $featured_id ) : null,
			'gallery'  => array_values(
				array_filter(
					array_map( array( self::class, 'serialize_attachment' ), $gallery_ids )
				)
			),
		);
	}

	/**
	 * Resolve Property Builder gallery images meta key (fallback: legacy).
	 *
	 * Sprint 28C: prefer the Workspace portal schema remapped `{master}_images`
	 * key so reads/writes match {@see PropertyFormMapper}. Fall back to
	 * UnifiedFieldGenerator (admin instance key), then the legacy singleton.
	 *
	 * @return string
	 */
	public static function builder_gallery_meta_key(): string {
		if ( class_exists( PropertyBuilderSchemaService::class ) ) {
			foreach ( PropertyBuilderSchemaService::collect_storage_fields() as $row ) {
				$meta  = (string) ( $row['metaKey'] ?? '' );
				$group = (string) ( $row['groupType'] ?? '' );
				$name  = (string) ( $row['name'] ?? '' );
				if ( '' === $name ) {
					continue;
				}
				if ( 'images' === $meta || ( 'gallery' === $group && substr( $name, -7 ) === '_images' ) ) {
					return $name;
				}
			}
		}

		if ( class_exists( '\HvnlyNab\Core\UnifiedFieldGenerator' ) ) {
			$generator = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
			if ( $generator && method_exists( $generator, 'get_standardized_field_name' ) ) {
				$key = (string) $generator->get_standardized_field_name( 'gallery', 'images' );
				if ( $key !== '' ) {
					return $key;
				}
			}
		}

		return self::META_GALLERY;
	}

	/**
	 * Read gallery attachment IDs for one Builder storage meta key.
	 *
	 * Never merges other gallery instances — use {@see get_gallery_ids()} for the
	 * property-wide union (Media tab, search cards, legacy templates).
	 *
	 * @param int    $property_id Property ID.
	 * @param string $meta_key    Builder `{base}_images` meta key.
	 * @return int[]
	 */
	public static function get_gallery_ids_for_meta_key( int $property_id, string $meta_key ): array {
		$property_id = absint( $property_id );
		$meta_key    = (string) $meta_key;
		if ( $property_id <= 0 || '' === $meta_key ) {
			return array();
		}

		$raw = get_post_meta( $property_id, $meta_key, true );
		if ( ! self::has_value( $raw ) && self::META_GALLERY === $meta_key ) {
			return array();
		}

		// Sole-gallery legacy: allow singleton when this key is the canonical builder key.
		if ( ! self::has_value( $raw )
			&& class_exists( PropertyBuilderSchemaService::class )
			&& PropertyBuilderSchemaService::has_sole_group_instance( 'gallery' )
			&& $meta_key === self::builder_gallery_meta_key()
		) {
			$raw = get_post_meta( $property_id, self::META_GALLERY, true );
		}

		return array_values(
			array_filter(
				array_map( 'absint', self::parse_ids( $raw ) ),
				static function ( $id ) {
					return $id > 0 && wp_attachment_is_image( $id );
				}
			)
		);
	}

	/**
	 * Whether a meta value is non-empty.
	 *
	 * @param mixed $raw Raw meta.
	 * @return bool
	 */
	private static function has_value( $raw ): bool {
		return $raw !== '' && $raw !== false && $raw !== null;
	}

	/**
	 * @param int $property_id Property ID.
	 * @return int[]
	 */
	public static function get_gallery_ids( int $property_id ): array {
		// Prefer the same resolution path as frontend templates when available.
		if ( function_exists( 'hvnly_get_property_gallery_ids' ) ) {
			$from_helper = hvnly_get_property_gallery_ids( $property_id );
			if ( is_array( $from_helper ) && ! empty( $from_helper ) ) {
				return array_values(
					array_filter(
						array_map( 'absint', $from_helper ),
						static function ( $id ) {
							return $id > 0 && wp_attachment_is_image( $id );
						}
					)
				);
			}
		}

		$keys = array_unique(
			array_filter(
				array(
					self::builder_gallery_meta_key(),
					self::META_GALLERY,
				)
			)
		);

		$ids = array();
		foreach ( $keys as $key ) {
			$raw = get_post_meta( $property_id, $key, true );
			foreach ( self::parse_ids( $raw ) as $id ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param int   $property_id Property ID.
	 * @param int[] $ids         Ordered attachment IDs.
	 * @return void
	 */
	public static function set_gallery_ids( int $property_id, array $ids ): void {
		$clean = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && wp_attachment_is_image( $id ) ) {
				$clean[] = $id;
			}
		}
		$clean      = array_values( array_unique( $clean ) );
		$serialized = implode( ',', $clean );

		$builder_key = self::builder_gallery_meta_key();
		update_post_meta( $property_id, self::META_GALLERY, $serialized );
		if ( $builder_key !== '' && $builder_key !== self::META_GALLERY ) {
			update_post_meta( $property_id, $builder_key, $serialized );
		}
	}

	/**
	 * @param int $property_id   Property ID.
	 * @param int $attachment_id Attachment ID.
	 * @return true|\WP_Error
	 */
	public static function set_featured( int $property_id, int $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return new \WP_Error(
				'hvnly_media_invalid',
				__( 'Invalid image attachment.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$result = set_post_thumbnail( $property_id, $attachment_id );
		if ( ! $result ) {
			return new \WP_Error(
				'hvnly_media_featured_failed',
				__( 'Could not set featured image.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		// Ensure featured is also in gallery for consistency.
		$ids = self::get_gallery_ids( $property_id );
		if ( ! in_array( $attachment_id, $ids, true ) ) {
			array_unshift( $ids, $attachment_id );
			self::set_gallery_ids( $property_id, $ids );
		}

		return true;
	}

	/**
	 * Detach from featured/gallery. Optionally delete the attachment file.
	 *
	 * @param int  $property_id   Property ID.
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $delete_file   Delete media library item when owned.
	 * @return array{featured: ?array, gallery: array}
	 */
	public static function detach( int $property_id, int $attachment_id, bool $delete_file = false ): array {
		$attachment_id = absint( $attachment_id );
		$featured_id   = (int) get_post_thumbnail_id( $property_id );

		if ( $featured_id === $attachment_id ) {
			delete_post_thumbnail( $property_id );
		}

		$ids = array_values(
			array_filter(
				self::get_gallery_ids( $property_id ),
				static function ( $id ) use ( $attachment_id ) {
					return (int) $id !== $attachment_id;
				}
			)
		);
		self::set_gallery_ids( $property_id, $ids );

		if ( $delete_file && self::user_owns_attachment( $attachment_id ) ) {
			wp_delete_attachment( $attachment_id, true );
		}

		return self::get_media( $property_id );
	}

	/**
	 * Append attachment to gallery (no duplicates).
	 *
	 * @param int $property_id   Property ID.
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function attach_to_gallery( int $property_id, int $attachment_id ): void {
		$attachment_id = absint( $attachment_id );
		$ids           = self::get_gallery_ids( $property_id );
		if ( ! in_array( $attachment_id, $ids, true ) ) {
			$ids[] = $attachment_id;
			self::set_gallery_ids( $property_id, $ids );
		}
	}

	/**
	 * Validate uploaded file array ($_FILES item).
	 *
	 * @param array<string, mixed> $file File array.
	 * @return true|\WP_Error
	 */
	public static function validate_upload( array $file ) {
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
			return new \WP_Error(
				'hvnly_media_upload_error',
				__( 'Upload failed.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			return new \WP_Error(
				'hvnly_media_too_large',
				__( 'Image must be 5MB or smaller.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$check = wp_check_filetype_and_ext( $file['tmp_name'], isset( $file['name'] ) ? $file['name'] : '' );
		$mime  = isset( $check['type'] ) ? (string) $check['type'] : '';
		if ( $mime === '' || ! in_array( $mime, self::ALLOWED_MIME, true ) ) {
			return new \WP_Error(
				'hvnly_media_type',
				__( 'Only JPEG, PNG, and WebP images are allowed.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function user_owns_attachment( int $attachment_id ): bool {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}
		if ( current_user_can( 'delete_post', $attachment_id ) ) {
			return true;
		}
		return (int) $post->post_author === get_current_user_id();
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>|null
	 */
	public static function serialize_attachment( int $attachment_id ): ?array {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$full   = wp_get_attachment_image_url( $attachment_id, 'full' );
		$medium = wp_get_attachment_image_url( $attachment_id, 'medium' );
		$thumb  = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		return array(
			'id'        => $attachment_id,
			'url'       => $full ? (string) $full : '',
			'mediumUrl' => $medium ? (string) $medium : '',
			'thumbUrl'  => $thumb ? (string) $thumb : '',
			'alt'       => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'title'     => (string) get_the_title( $attachment_id ),
		);
	}

	/**
	 * @param mixed $raw Meta value.
	 * @return int[]
	 */
	private static function parse_ids( $raw ): array {
		if ( is_array( $raw ) ) {
			$parts = $raw;
		} else {
			$parts = $raw !== '' && $raw !== null ? explode( ',', (string) $raw ) : array();
		}

		$ids = array();
		foreach ( $parts as $part ) {
			$id = absint( $part );
			if ( $id > 0 && wp_attachment_is_image( $id ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Whether attachment is featured or in the gallery for this property.
	 *
	 * @param int $property_id   Property ID.
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_linked( int $property_id, int $attachment_id ): bool {
		$attachment_id = absint( $attachment_id );
		if ( (int) get_post_thumbnail_id( $property_id ) === $attachment_id ) {
			return true;
		}
		return in_array( $attachment_id, self::get_gallery_ids( $property_id ), true );
	}
}
