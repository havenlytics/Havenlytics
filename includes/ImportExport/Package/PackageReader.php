<?php
/**
 * Reads and validates HPTP ZIP packages into protected temporary storage.
 *
 * @package HvnlyNab\ImportExport\Package
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Package;

use HvnlyNab\ImportExport\Security\MimeGuard;
use HvnlyNab\ImportExport\Security\ZipGuard;
use ZipArchive;

defined( 'ABSPATH' ) || exit;

/**
 * Package reader — structural + integrity validation only (no entity import).
 *
 * @since 3.6.0
 */
final class PackageReader {

	/**
	 * Validate a ZIP on disk, extract safely, verify checksums and manifest.
	 *
	 * @param string $zip_path Absolute path to a ZIP file.
	 * @return PackageResult data={workdir,manifest,entities,media_index,files,warnings}
	 */
	public static function open( string $zip_path ): PackageResult {
		$zip_path = wp_normalize_path( $zip_path );

		$container = MimeGuard::validate_package_file( $zip_path );
		if ( ! $container->ok() ) {
			return $container;
		}

		$opened = ZipGuard::open_for_read( $zip_path );
		if ( ! $opened->ok() ) {
			return $opened;
		}

		$payload = $opened->data();
		/** @var ZipArchive $zip */
		$zip     = $payload['zip'];
		$entries = $payload['entries'];
		$warnings = $opened->warnings();

		$structure = self::assert_required_structure( $entries );
		if ( ! $structure->ok() ) {
			$zip->close();
			return $structure;
		}
		foreach ( $structure->warnings() as $warning ) {
			$warnings[] = $warning;
		}

		$workdir = TempStorage::create_workdir( 'import' );
		if ( ! $workdir->ok() ) {
			$zip->close();
			return $workdir;
		}

		$dir = (string) $workdir->data()['dir'];

		$extracted = ZipGuard::extract_entries( $zip, $entries, $dir );
		$zip->close();

		if ( ! $extracted->ok() ) {
			TempStorage::delete_workdir( $dir );
			return $extracted;
		}

		$written = $extracted->data()['written'] ?? array();
		foreach ( $extracted->warnings() as $warning ) {
			$warnings[] = $warning;
		}

		if ( empty( $written[ ManifestSchema::FILE_MANIFEST ] ) || empty( $written[ ManifestSchema::FILE_ENTITIES ] ) ) {
			TempStorage::delete_workdir( $dir );
			return PackageResult::failure(
				'hvnly_ie_package_incomplete',
				'Package extraction did not produce required metadata files.',
				array()
			);
		}

		$manifest_raw = file_get_contents( $written[ ManifestSchema::FILE_MANIFEST ] );
		if ( false === $manifest_raw ) {
			TempStorage::delete_workdir( $dir );
			return PackageResult::failure(
				'hvnly_ie_manifest_read_failed',
				'Unable to read extracted manifest.json.',
				array()
			);
		}

		$manifest_result = ManifestValidator::validate_json( $manifest_raw );
		if ( ! $manifest_result->ok() ) {
			TempStorage::delete_workdir( $dir );
			return $manifest_result;
		}
		foreach ( $manifest_result->warnings() as $warning ) {
			$warnings[] = $warning;
		}

		/** @var array<string, mixed> $manifest */
		$manifest = $manifest_result->data();

		$entities_raw = file_get_contents( $written[ ManifestSchema::FILE_ENTITIES ] );
		if ( false === $entities_raw ) {
			TempStorage::delete_workdir( $dir );
			return PackageResult::failure(
				'hvnly_ie_entities_read_failed',
				'Unable to read extracted entities.json.',
				array()
			);
		}

		$entities_decoded = json_decode( $entities_raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $entities_decoded ) ) {
			TempStorage::delete_workdir( $dir );
			return PackageResult::failure(
				'hvnly_ie_entities_json',
				'entities.json is not valid JSON.',
				array( 'json_error' => json_last_error_msg() )
			);
		}

		$checksums = isset( $manifest['checksums'] ) && is_array( $manifest['checksums'] )
			? $manifest['checksums']
			: array();

		$entities_expected = isset( $checksums[ ManifestSchema::FILE_ENTITIES ] )
			? (string) $checksums[ ManifestSchema::FILE_ENTITIES ]
			: '';
		$entities_verify   = Checksum::verify_string( $entities_raw, $entities_expected );
		if ( ! $entities_verify->ok() ) {
			TempStorage::delete_workdir( $dir );
			return $entities_verify;
		}

		$media_index = null;
		$contents    = isset( $manifest['contents'] ) && is_array( $manifest['contents'] ) ? $manifest['contents'] : array();
		$wants_media = ! empty( $contents['media'] );

		if ( $wants_media ) {
			if ( empty( $written[ ManifestSchema::FILE_MEDIA_INDEX ] ) ) {
				TempStorage::delete_workdir( $dir );
				return PackageResult::failure(
					'hvnly_ie_media_index_missing',
					'Manifest declares media but media-index.json is missing.',
					array()
				);
			}

			$media_raw = file_get_contents( $written[ ManifestSchema::FILE_MEDIA_INDEX ] );
			if ( false === $media_raw ) {
				TempStorage::delete_workdir( $dir );
				return PackageResult::failure(
					'hvnly_ie_media_index_read_failed',
					'Unable to read extracted media-index.json.',
					array()
				);
			}

			$media_expected = isset( $checksums[ ManifestSchema::FILE_MEDIA_INDEX ] )
				? (string) $checksums[ ManifestSchema::FILE_MEDIA_INDEX ]
				: '';
			$media_verify = Checksum::verify_string( $media_raw, $media_expected );
			if ( ! $media_verify->ok() ) {
				TempStorage::delete_workdir( $dir );
				return $media_verify;
			}

			$media_index = json_decode( $media_raw, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $media_index ) ) {
				TempStorage::delete_workdir( $dir );
				return PackageResult::failure(
					'hvnly_ie_media_index_json',
					'media-index.json is not valid JSON.',
					array( 'json_error' => json_last_error_msg() )
				);
			}

			$file_checksums = isset( $checksums['media'] ) && is_array( $checksums['media'] )
				? $checksums['media']
				: array();

			$media_files_on_disk = array();
			foreach ( $written as $rel => $abs ) {
				$rel = (string) $rel;
				if ( 0 === strpos( $rel, ManifestSchema::DIR_MEDIA . '/' ) ) {
					$media_files_on_disk[ $rel ] = $abs;
				}
			}

			if ( ! empty( $media_files_on_disk ) && empty( $file_checksums ) ) {
				TempStorage::delete_workdir( $dir );
				return PackageResult::failure(
					'hvnly_ie_media_checksums_missing',
					'Package includes media files but checksums.media is missing.',
					array()
				);
			}

			foreach ( $media_files_on_disk as $rel => $abs ) {
				if ( empty( $file_checksums[ $rel ] ) ) {
					TempStorage::delete_workdir( $dir );
					return PackageResult::failure(
						'hvnly_ie_media_checksum_missing',
						'A packaged media file is missing its checksum.',
						array( 'path' => $rel )
					);
				}
			}

			foreach ( $file_checksums as $rel => $hash ) {
				$rel = (string) $rel;
				if ( empty( $written[ $rel ] ) ) {
					TempStorage::delete_workdir( $dir );
					return PackageResult::failure(
						'hvnly_ie_media_file_missing',
						'Media file listed in checksums is missing from the package.',
						array( 'path' => $rel )
					);
				}
				$verify = Checksum::verify_file( $written[ $rel ], (string) $hash );
				if ( ! $verify->ok() ) {
					TempStorage::delete_workdir( $dir );
					return $verify;
				}
			}
		} elseif ( ! empty( $written[ ManifestSchema::FILE_MEDIA_INDEX ] ) ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_media_index_unexpected',
				'message' => 'Package includes media-index.json but contents.media is false.',
				'context' => array(),
			);
		}

		return PackageResult::success(
			array(
				'workdir'     => $dir,
				'manifest'    => $manifest,
				'entities'    => $entities_decoded,
				'media_index' => $media_index,
				'files'       => $written,
				'source_zip'  => $zip_path,
			),
			$warnings
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $entries Inspected entries.
	 * @return PackageResult
	 */
	private static function assert_required_structure( array $entries ): PackageResult {
		$names = array();
		foreach ( $entries as $entry ) {
			if ( empty( $entry['is_dir'] ) && ! empty( $entry['name'] ) ) {
				$names[ (string) $entry['name'] ] = true;
			}
		}

		$errors   = array();
		$warnings = array();

		if ( empty( $names[ ManifestSchema::FILE_MANIFEST ] ) ) {
			$errors[] = array(
				'code'    => 'hvnly_ie_structure_manifest_missing',
				'message' => 'Package is missing manifest.json.',
				'context' => array(),
			);
		}
		if ( empty( $names[ ManifestSchema::FILE_ENTITIES ] ) ) {
			$errors[] = array(
				'code'    => 'hvnly_ie_structure_entities_missing',
				'message' => 'Package is missing entities.json.',
				'context' => array(),
			);
		}

		if ( ! empty( $errors ) ) {
			return PackageResult::failures( $errors );
		}

		return PackageResult::success( array( 'names' => array_keys( $names ) ), $warnings );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
