<?php
/**
 * Creates HPTP ZIP packages in protected temporary storage.
 *
 * @package HvnlyNab\ImportExport\Package
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Package;

use HvnlyNab\ImportExport\Security\MimeGuard;
use HvnlyNab\ImportExport\Security\PathSanitizer;
use HvnlyNab\ImportExport\Security\ZipGuard;
use HvnlyNab\ImportExport\Support\JsonNormalizer;
use ZipArchive;

defined( 'ABSPATH' ) || exit;

/**
 * Package writer — no entity serialization (caller supplies arrays/JSON).
 *
 * @since 3.6.0
 */
final class PackageWriter {

	/**
	 * Build a minimal HPTP ZIP from a manifest array and entities array.
	 *
	 * Optional media files: list of [ 'archive_path', 'source', optional meta ].
	 * Optional prebuilt media_index (from MediaPacker) — when provided, used as media-index.json.
	 *
	 * @param array<string, mixed>              $manifest Manifest fields (format/schema filled if missing).
	 * @param array<string, mixed>              $entities Entities payload.
	 * @param array<int, array<string, string>> $media_files Optional unique binaries to pack.
	 * @param array<string, mixed>              $media_index Optional prebuilt media-index payload.
	 * @return PackageResult data={zip_path,workdir,manifest,checksums}
	 */
	public static function write( array $manifest, array $entities, array $media_files = array(), array $media_index = array() ): PackageResult {
		if ( ! ZipGuard::ziparchive_available() ) {
			return PackageResult::failure(
				'hvnly_ie_ziparchive_missing',
				'The ZipArchive PHP extension is required for Import / Export packages.',
				array()
			);
		}

		$workdir = TempStorage::create_workdir( 'export' );
		if ( ! $workdir->ok() ) {
			return $workdir;
		}

		$work            = $workdir->data();
		$dir             = (string) $work['dir'];
		$warnings        = array();
		$media_checksums = array();
		$validated_media = array();
		$built_index     = array(
			'files' => array(),
		);

		$entities_encoded = JsonNormalizer::encode( $entities );
		if ( ! $entities_encoded->ok() ) {
			TempStorage::delete_workdir( $dir );
			return $entities_encoded;
		}
		$entities_json = (string) $entities_encoded->data();

		$entities_hash = Checksum::hash_string( $entities_json );
		if ( ! $entities_hash->ok() ) {
			TempStorage::delete_workdir( $dir );
			return $entities_hash;
		}

		foreach ( $media_files as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$archive_path = isset( $item['archive_path'] ) ? (string) $item['archive_path'] : '';
			$source       = isset( $item['source'] ) ? (string) $item['source'] : '';

			$path_check = PathSanitizer::sanitize_archive_entry( $archive_path );
			if ( ! $path_check->ok() ) {
				TempStorage::delete_workdir( $dir );
				return $path_check;
			}
			$archive_path = (string) $path_check->data();

			if ( 0 !== strpos( $archive_path, ManifestSchema::DIR_MEDIA . '/' ) ) {
				TempStorage::delete_workdir( $dir );
				return PackageResult::failure(
					'hvnly_ie_media_path_prefix',
					'Media archive paths must be under media/.',
					array( 'path' => $archive_path )
				);
			}

			$mime = MimeGuard::validate_media_entry( $archive_path, is_file( $source ) ? $source : null );
			if ( ! $mime->ok() ) {
				TempStorage::delete_workdir( $dir );
				return $mime;
			}

			if ( ! is_file( $source ) || ! is_readable( $source ) ) {
				TempStorage::delete_workdir( $dir );
				return PackageResult::failure(
					'hvnly_ie_media_source_missing',
					'Media source file is missing or unreadable.',
					array( 'source' => $source )
				);
			}

			$file_hash = Checksum::hash_file( $source );
			if ( ! $file_hash->ok() ) {
				TempStorage::delete_workdir( $dir );
				return $file_hash;
			}

			$checksum                         = (string) $file_hash->data();
			$media_checksums[ $archive_path ] = $checksum;

			$built_index['files'][] = array(
				'export_key' => isset( $item['export_key'] ) ? (string) $item['export_key'] : '',
				'path'       => $archive_path,
				'filename'   => isset( $item['filename'] ) ? (string) $item['filename'] : wp_basename( $source ),
				'mime_type'  => isset( $item['mime_type'] ) ? (string) $item['mime_type'] : '',
				'checksum'   => $checksum,
				'size'       => (int) filesize( $source ),
			);

			$validated_media[] = array(
				'archive_path' => $archive_path,
				'source'       => $source,
			);
		}

		$use_prebuilt_index = ! empty( $media_index['files'] ) && is_array( $media_index['files'] );
		if ( $use_prebuilt_index ) {
			$built_index = $media_index;
			// Ensure checksum map covers every unique path in the prebuilt index.
			foreach ( $built_index['files'] as $row ) {
				if ( ! is_array( $row ) || empty( $row['path'] ) || empty( $row['checksum'] ) ) {
					continue;
				}
				$media_checksums[ (string) $row['path'] ] = (string) $row['checksum'];
			}
		}

		$include_media    = ! empty( $validated_media );
		$media_index_json = '';
		$media_index_hash = '';

		if ( $include_media ) {
			$media_index_encoded = JsonNormalizer::encode( $built_index );
			if ( ! $media_index_encoded->ok() ) {
				TempStorage::delete_workdir( $dir );
				return $media_index_encoded;
			}
			$media_index_json = (string) $media_index_encoded->data();
			$idx_hash         = Checksum::hash_string( $media_index_json );
			if ( ! $idx_hash->ok() ) {
				TempStorage::delete_workdir( $dir );
				return $idx_hash;
			}
			$media_index_hash = (string) $idx_hash->data();
		}

		$manifest = self::normalize_manifest(
			$manifest,
			(string) $entities_hash->data(),
			$include_media ? $media_index_hash : '',
			$media_checksums,
			$include_media
		);

		$manifest_check = ManifestValidator::validate_array( $manifest );
		if ( ! $manifest_check->ok() ) {
			TempStorage::delete_workdir( $dir );
			return $manifest_check;
		}
		foreach ( $manifest_check->warnings() as $warning ) {
			$warnings[] = $warning;
		}

		$manifest_encoded = JsonNormalizer::encode( $manifest );
		if ( ! $manifest_encoded->ok() ) {
			TempStorage::delete_workdir( $dir );
			return $manifest_encoded;
		}
		$manifest_json = (string) $manifest_encoded->data();

		$package_name = preg_replace( '/[^a-z0-9-_]+/i', '-', (string) $manifest['package_name'] );
		$package_name = trim( (string) $package_name, '-' );
		if ( '' === $package_name ) {
			$package_name = 'havenlytics-export';
		}

		$zip_path = trailingslashit( $dir ) . $package_name . '.zip';
		$zip_path = wp_normalize_path( $zip_path );

		$under = TempStorage::assert_under_base( $zip_path );
		if ( ! $under->ok() ) {
			TempStorage::delete_workdir( $dir );
			return $under;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			TempStorage::delete_workdir( $dir );
			return PackageResult::failure(
				'hvnly_ie_zip_create_failed',
				'Failed to create ZIP package.',
				array( 'path' => $zip_path )
			);
		}

		$zip->addFromString( ManifestSchema::FILE_MANIFEST, $manifest_json );
		$zip->addFromString( ManifestSchema::FILE_ENTITIES, $entities_json );

		if ( $include_media ) {
			$zip->addFromString( ManifestSchema::FILE_MEDIA_INDEX, $media_index_json );
			foreach ( $validated_media as $item ) {
				$zip->addFile( $item['source'], $item['archive_path'] );
			}
		}

		$zip->close();

		if ( ! is_file( $zip_path ) ) {
			TempStorage::delete_workdir( $dir );
			return PackageResult::failure(
				'hvnly_ie_zip_missing_after_write',
				'ZIP package was not written to temporary storage.',
				array( 'path' => $zip_path )
			);
		}

		return PackageResult::success(
			array(
				'zip_path'   => $zip_path,
				'workdir'    => $dir,
				'manifest'   => $manifest,
				'checksums'  => $manifest['checksums'],
				'size_bytes' => (int) filesize( $zip_path ),
			),
			$warnings
		);
	}

	/**
	 * @param array<string, mixed> $manifest Input manifest.
	 * @param string               $entities_hash SHA-256 of entities.json.
	 * @param string               $media_index_hash SHA-256 of media-index.json or empty.
	 * @param array<string, string> $media_checksums path => hash.
	 * @param bool                 $include_media Whether media is included.
	 * @return array<string, mixed>
	 */
	private static function normalize_manifest(
		array $manifest,
		string $entities_hash,
		string $media_index_hash,
		array $media_checksums,
		bool $include_media
	): array {
		$manifest['format']         = ManifestSchema::FORMAT;
		$manifest['schema_version'] = isset( $manifest['schema_version'] )
			? (string) $manifest['schema_version']
			: ManifestSchema::SCHEMA_VERSION;
		$manifest['exported_at']    = isset( $manifest['exported_at'] )
			? (string) $manifest['exported_at']
			: gmdate( 'c' );
		$manifest['package_name']   = isset( $manifest['package_name'] )
			? (string) $manifest['package_name']
			: 'havenlytics-export-' . gmdate( 'Y-m-d-Hi' );
		$manifest['contents']       = isset( $manifest['contents'] ) && is_array( $manifest['contents'] )
			? $manifest['contents']
			: array();
		$manifest['counts']         = isset( $manifest['counts'] ) && is_array( $manifest['counts'] )
			? $manifest['counts']
			: array();

		$manifest['contents']['media'] = $include_media;

		$checksums = array(
			'algorithm'                     => ManifestSchema::CHECKSUM_ALGORITHM,
			ManifestSchema::FILE_ENTITIES   => $entities_hash,
		);
		if ( $include_media ) {
			$checksums[ ManifestSchema::FILE_MEDIA_INDEX ] = $media_index_hash;
			$checksums['media']                            = $media_checksums;
		}
		$manifest['checksums'] = $checksums;

		return $manifest;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
