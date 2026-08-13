<?php
/**
 * Unpacks HPTP media binaries into WordPress attachments.
 *
 * @package HvnlyNab\ImportExport\Media
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Media;

use HvnlyNab\ImportExport\Package\Checksum;
use HvnlyNab\ImportExport\Package\ManifestSchema;
use HvnlyNab\ImportExport\Package\PackageResult;
use HvnlyNab\ImportExport\Security\MimeGuard;
use HvnlyNab\ImportExport\Security\PathSanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * MediaUnpacker — create local attachments from media-index.json + media/.
 *
 * Never fetches remote URLs. Missing/invalid files warn and continue.
 *
 * @since 3.6.0
 */
final class MediaUnpacker {

	/**
	 * Unpack packaged media into the Media Library.
	 *
	 * @param array                $media_index Decoded media-index.json (`files` list).
	 * @param array<string,string> $files       Relative archive path => absolute extracted path.
	 * @param int                  $parent_id   Optional parent post for attachments (0 = unattached).
	 * @param array<string,int>    $batch       Optional {offset,limit}.
	 * @param array<string,int>    $existing_map Existing export_key => attachment_id.
	 * @param array<string,int>    $existing_by_path Existing archive path => attachment_id.
	 * @return PackageResult data={map,created,skipped,failed,by_path,next,total,done}
	 */
	public static function unpack(
		array $media_index,
		array $files,
		int $parent_id = 0,
		array $batch = array(),
		array $existing_map = array(),
		array $existing_by_path = array()
	): PackageResult {
		self::ensure_media_includes();

		$index_files = isset( $media_index['files'] ) && is_array( $media_index['files'] )
			? $media_index['files']
			: array();

		$total  = count( $index_files );
		$offset = isset( $batch['offset'] ) ? max( 0, (int) $batch['offset'] ) : 0;
		$limit  = isset( $batch['limit'] ) ? max( 0, (int) $batch['limit'] ) : 0;
		if ( $limit > 0 ) {
			$index_files = array_slice( $index_files, $offset, $limit );
		} elseif ( $offset > 0 ) {
			$index_files = array_slice( $index_files, $offset );
		}

		$map      = $existing_map;
		$by_path  = $existing_by_path;
		$created  = 0;
		$skipped  = 0;
		$failed   = 0;
		$warnings = array();

		if ( empty( $index_files ) ) {
			return PackageResult::success(
				array(
					'map'     => $map,
					'by_path' => $by_path,
					'created' => 0,
					'skipped' => 0,
					'failed'  => 0,
					'next'    => $offset,
					'total'   => $total,
					'done'    => $offset >= $total || 0 === $total,
				),
				( 0 === $total )
					? array(
						array(
							'code'    => 'hvnly_ie_media_index_empty',
							'message' => 'media-index.json has no files; media import skipped.',
							'context' => array(),
						),
					)
					: array()
			);
		}

		foreach ( $index_files as $row ) {
			if ( ! is_array( $row ) ) {
				++$skipped;
				continue;
			}

			$export_key = (string) ( $row['export_key'] ?? '' );
			$rel_path   = (string) ( $row['path'] ?? '' );
			$filename   = (string) ( $row['filename'] ?? '' );
			$checksum   = (string) ( $row['checksum'] ?? '' );
			$mime_hint  = (string) ( $row['mime_type'] ?? '' );

			if ( '' === $export_key || '' === $rel_path ) {
				++$skipped;
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_index_row_invalid',
					'message' => 'Media index row missing export_key or path; skipped.',
					'context' => array(
						'export_key' => $export_key,
						'path' => $rel_path,
					),
				);
				continue;
			}

			if ( isset( $map[ $export_key ] ) && (int) $map[ $export_key ] > 0 ) {
				continue;
			}

			$path_check = PathSanitizer::sanitize_archive_entry( $rel_path );
			if ( ! $path_check->ok() ) {
				++$failed;
				foreach ( $path_check->errors() as $error ) {
					$warnings[] = $error;
				}
				continue;
			}
			$rel_path = (string) $path_check->data();

			if ( 0 !== strpos( $rel_path, ManifestSchema::DIR_MEDIA . '/' ) ) {
				++$failed;
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_path_outside',
					'message' => 'Media entry path is outside media/; rejected.',
					'context' => array(
						'export_key' => $export_key,
						'path' => $rel_path,
					),
				);
				continue;
			}

			if ( isset( $by_path[ $rel_path ] ) ) {
				$map[ $export_key ] = (int) $by_path[ $rel_path ];
				continue;
			}

			if ( empty( $files[ $rel_path ] ) || ! is_string( $files[ $rel_path ] ) ) {
				++$skipped;
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_file_missing',
					'message' => 'Packaged media file is missing; import continues without it.',
					'context' => array(
						'export_key' => $export_key,
						'path' => $rel_path,
					),
				);
				continue;
			}

			$absolute = wp_normalize_path( (string) $files[ $rel_path ] );
			if ( ! is_file( $absolute ) || ! is_readable( $absolute ) ) {
				++$skipped;
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_file_unreadable',
					'message' => 'Packaged media file is unreadable; import continues without it.',
					'context' => array(
						'export_key' => $export_key,
						'path' => $rel_path,
					),
				);
				continue;
			}

			$mime_check = MimeGuard::validate_media_entry( $rel_path, $absolute );
			if ( ! $mime_check->ok() ) {
				++$failed;
				foreach ( $mime_check->errors() as $error ) {
					$warnings[] = $error;
				}
				continue;
			}

			if ( '' === $checksum ) {
				++$failed;
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_checksum_required',
					'message' => 'Media entry is missing a checksum and was skipped.',
					'context' => array(
						'export_key' => $export_key,
						'path' => $rel_path,
					),
				);
				continue;
			}

			$verify = Checksum::verify_file( $absolute, $checksum );
			if ( ! $verify->ok() ) {
				++$failed;
				foreach ( $verify->errors() as $error ) {
					$warnings[] = $error;
				}
				continue;
			}

			if ( '' === $filename ) {
				$filename = wp_basename( $rel_path );
			}
			$filename = sanitize_file_name( $filename );
			if ( '' === $filename ) {
				$filename = sanitize_file_name( $export_key . '.' . pathinfo( $rel_path, PATHINFO_EXTENSION ) );
			}

			$attachment_id = self::create_attachment_from_file(
				$absolute,
				$filename,
				$parent_id,
				$mime_hint,
				$row
			);

			if ( $attachment_id <= 0 ) {
				++$failed;
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_attach_failed',
					'message' => 'Failed to create WordPress attachment for packaged media.',
					'context' => array(
						'export_key' => $export_key,
						'path' => $rel_path,
					),
				);
				continue;
			}

			$by_path[ $rel_path ] = $attachment_id;
			$map[ $export_key ]   = $attachment_id;
			++$created;
		}

		$next = $offset + count( $index_files );

		return PackageResult::success(
			array(
				'map'     => $map,
				'by_path' => $by_path,
				'created' => $created,
				'skipped' => $skipped,
				'failed'  => $failed,
				'next'    => $next,
				'total'   => $total,
				'done'    => $next >= $total,
			),
			$warnings
		);
	}

	/**
	 * Create a Media Library attachment from a local extracted file.
	 *
	 * Uses a temp copy + media_handle_sideload so WP owns the final uploads path.
	 * Does not fetch remote URLs.
	 *
	 * @param string               $source_path Absolute source file.
	 * @param string               $filename    Destination filename.
	 * @param int                  $parent_id   Parent post ID.
	 * @param string               $mime_hint   Optional MIME from index.
	 * @param array<string, mixed> $row         Index row (alt/title/caption stubs optional).
	 * @return int Attachment ID or 0.
	 */
	private static function create_attachment_from_file(
		string $source_path,
		string $filename,
		int $parent_id,
		string $mime_hint,
		array $row
	): int {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return 0;
		}

		$tmp = wp_tempnam( $filename );
		if ( ! is_string( $tmp ) || '' === $tmp ) {
			return 0;
		}

		if ( ! copy( $source_path, $tmp ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return 0;
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $parent_id > 0 ? $parent_id : 0 );

		if ( is_wp_error( $attachment_id ) ) {
			if ( is_file( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return 0;
		}

		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return 0;
		}

		// Optional stub metadata (alt/title/caption) when present on index-linked stubs later.
		$alt = isset( $row['alt'] ) ? sanitize_text_field( (string) $row['alt'] ) : '';
		if ( '' !== $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		$post_update = array( 'ID' => $attachment_id );
		$touch       = false;
		if ( ! empty( $row['title'] ) ) {
			$post_update['post_title'] = sanitize_text_field( (string) $row['title'] );
			$touch                     = true;
		}
		if ( ! empty( $row['caption'] ) ) {
			$post_update['post_excerpt'] = sanitize_text_field( (string) $row['caption'] );
			$touch                       = true;
		}
		if ( $touch ) {
			wp_update_post( $post_update );
		}

		unset( $mime_hint ); // MIME already validated; WP detects from file.

		return $attachment_id;
	}

	/**
	 * @return void
	 */
	private static function ensure_media_includes(): void {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
