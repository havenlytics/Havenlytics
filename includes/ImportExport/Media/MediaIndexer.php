<?php
/**
 * Indexes local media stubs for HPTP packaging.
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
 * Resolves export media stubs to local files; never fetches remote URLs.
 *
 * @since 3.6.0
 */
final class MediaIndexer {

	/**
	 * Index stubs collected by PortableFieldEncoder.
	 *
	 * @param array<int, array<string, mixed>> $stubs Catalog stubs with source_attachment_id.
	 * @return PackageResult data={entries,warnings,skipped}
	 */
	public static function index( array $stubs ): PackageResult {
		$uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return PackageResult::failure(
				'hvnly_ie_media_uploads_unavailable',
				'WordPress uploads directory is unavailable for media packaging.',
				array( 'error' => isset( $uploads['error'] ) ? (string) $uploads['error'] : '' )
			);
		}

		$uploads_base = wp_normalize_path( (string) $uploads['basedir'] );
		$warnings     = array();
		$entries      = array();
		$skipped      = array();

		/** @var array<string, string> checksum => archive_path for binary dedupe */
		$checksum_paths = array();

		foreach ( $stubs as $stub ) {
			if ( ! is_array( $stub ) ) {
				continue;
			}

			$export_key = isset( $stub['export_key'] ) ? (string) $stub['export_key'] : '';
			$attachment = isset( $stub['source_attachment_id'] ) ? (int) $stub['source_attachment_id'] : 0;

			if ( '' === $export_key || $attachment <= 0 ) {
				$skipped[] = array(
					'export_key' => $export_key,
					'reason'     => 'invalid_stub',
				);
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_stub_invalid',
					'message' => 'Media stub is missing export_key or attachment id.',
					'context' => array( 'export_key' => $export_key ),
				);
				continue;
			}

			$file = get_attached_file( $attachment );
			if ( ! is_string( $file ) || '' === $file || ! is_file( $file ) || ! is_readable( $file ) ) {
				$skipped[] = array(
					'export_key' => $export_key,
					'reason'     => 'missing_file',
				);
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_file_missing',
					'message' => 'Referenced media file is missing or unreadable; export continues without it.',
					'context' => array(
						'export_key'    => $export_key,
						'attachment_id' => $attachment,
					),
				);
				continue;
			}

			$normalized_file = wp_normalize_path( $file );
			$base_cmp        = strtolower( rtrim( $uploads_base, '/' ) );
			$file_cmp        = strtolower( $normalized_file );
			if ( $file_cmp !== $base_cmp && 0 !== strpos( $file_cmp, $base_cmp . '/' ) ) {
				$skipped[] = array(
					'export_key' => $export_key,
					'reason'     => 'outside_uploads',
				);
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_outside_uploads',
					'message' => 'Media file is outside the WordPress uploads directory and was skipped.',
					'context' => array( 'export_key' => $export_key ),
				);
				continue;
			}

			$filename = isset( $stub['filename'] ) && '' !== (string) $stub['filename']
				? (string) $stub['filename']
				: wp_basename( $normalized_file );
			$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			if ( '' === $ext ) {
				$ext = strtolower( pathinfo( $normalized_file, PATHINFO_EXTENSION ) );
			}
			if ( '' === $ext ) {
				$skipped[] = array(
					'export_key' => $export_key,
					'reason'     => 'extension_missing',
				);
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_extension_missing',
					'message' => 'Media file has no extension and was skipped.',
					'context' => array( 'export_key' => $export_key ),
				);
				continue;
			}

			$archive_path = ManifestSchema::DIR_MEDIA . '/' . $export_key . '.' . $ext;
			$path_check   = PathSanitizer::sanitize_archive_entry( $archive_path );
			if ( ! $path_check->ok() ) {
				foreach ( $path_check->errors() as $error ) {
					$warnings[] = $error;
				}
				$skipped[] = array(
					'export_key' => $export_key,
					'reason'     => 'unsafe_path',
				);
				continue;
			}
			$archive_path = (string) $path_check->data();

			$mime = MimeGuard::validate_media_entry( $archive_path, $normalized_file );
			if ( ! $mime->ok() ) {
				foreach ( $mime->errors() as $error ) {
					$warnings[] = $error;
				}
				$skipped[] = array(
					'export_key' => $export_key,
					'reason'     => 'mime_rejected',
				);
				continue;
			}

			$hash = Checksum::hash_file( $normalized_file );
			if ( ! $hash->ok() ) {
				foreach ( $hash->errors() as $error ) {
					$warnings[] = $error;
				}
				$skipped[] = array(
					'export_key' => $export_key,
					'reason'     => 'checksum_failed',
				);
				continue;
			}

			$checksum = (string) $hash->data();
			$pack_path = $archive_path;
			$shared    = false;

			if ( isset( $checksum_paths[ $checksum ] ) ) {
				$pack_path = $checksum_paths[ $checksum ];
				$shared    = true;
			} else {
				$checksum_paths[ $checksum ] = $archive_path;
			}

			$entries[] = array(
				'export_key'   => $export_key,
				'filename'     => $filename,
				'mime_type'    => isset( $stub['mime_type'] ) ? (string) $stub['mime_type'] : '',
				'path'         => $pack_path,
				'source'       => $normalized_file,
				'checksum'     => $checksum,
				'size'         => (int) filesize( $normalized_file ),
				'shared_binary'=> $shared,
				'status'       => 'pack',
			);
		}

		return PackageResult::success(
			array(
				'entries' => $entries,
				'skipped' => $skipped,
			),
			$warnings
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
