<?php
/**
 * ZIP archive safety checks and controlled extraction.
 *
 * @package HvnlyNab\ImportExport\Security
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Security;

use HvnlyNab\ImportExport\Package\ManifestSchema;
use HvnlyNab\ImportExport\Package\PackageResult;
use ZipArchive;

defined( 'ABSPATH' ) || exit;

/**
 * Defends against ZIP slip, bombs, corrupt archives, and unsafe entries.
 *
 * @since 3.6.0
 */
final class ZipGuard {

	/**
	 * @return bool
	 */
	public static function ziparchive_available(): bool {
		return class_exists( ZipArchive::class );
	}

	/**
	 * Open a ZIP for reading after container MIME/magic checks.
	 *
	 * @param string $zip_path Absolute path.
	 * @return PackageResult data=ZipArchive on success.
	 */
	public static function open_for_read( string $zip_path ): PackageResult {
		if ( ! self::ziparchive_available() ) {
			return PackageResult::failure(
				'hvnly_ie_ziparchive_missing',
				'The ZipArchive PHP extension is required for Import / Export packages.',
				array()
			);
		}

		$mime = MimeGuard::validate_package_file( $zip_path );
		if ( ! $mime->ok() ) {
			return $mime;
		}

		$size = filesize( $zip_path );
		if ( false === $size || $size <= 0 ) {
			return PackageResult::failure(
				'hvnly_ie_zip_empty',
				'ZIP package is empty.',
				array( 'path' => $zip_path )
			);
		}

		if ( $size > ManifestSchema::MAX_PACKAGE_BYTES ) {
			return PackageResult::failure(
				'hvnly_ie_zip_too_large',
				'ZIP package exceeds the maximum allowed size.',
				array(
					'size' => $size,
					'max'  => ManifestSchema::MAX_PACKAGE_BYTES,
				)
			);
		}

		$zip    = new ZipArchive();
		$opened = $zip->open( $zip_path );

		if ( true !== $opened ) {
			return PackageResult::failure(
				'hvnly_ie_zip_corrupt',
				'ZIP archive could not be opened (corrupt or unsupported).',
				array(
					'path'   => $zip_path,
					'status' => is_int( $opened ) ? $opened : 0,
				)
			);
		}

		if ( $zip->numFiles <= 0 ) {
			$zip->close();
			return PackageResult::failure(
				'hvnly_ie_zip_empty',
				'ZIP archive contains no entries.',
				array( 'path' => $zip_path )
			);
		}

		if ( $zip->numFiles > ManifestSchema::MAX_ZIP_ENTRIES ) {
			$zip->close();
			return PackageResult::failure(
				'hvnly_ie_zip_too_many_entries',
				'ZIP archive has too many entries.',
				array(
					'count' => $zip->numFiles,
					'max'   => ManifestSchema::MAX_ZIP_ENTRIES,
				)
			);
		}

		$inspect = self::inspect_entries( $zip );
		if ( ! $inspect->ok() ) {
			$zip->close();
			return $inspect;
		}

		return PackageResult::success(
			array(
				'zip'     => $zip,
				'entries' => $inspect->data(),
			),
			$inspect->warnings()
		);
	}

	/**
	 * Validate all entry names and uncompressed sizes without extracting.
	 *
	 * @param ZipArchive $zip Open archive.
	 * @return PackageResult data=list of sanitized entry descriptors.
	 */
	public static function inspect_entries( ZipArchive $zip ): PackageResult {
		$entries          = array();
		$uncompressed_sum = 0;
		$warnings         = array();
		$errors           = array();

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = $zip->getNameIndex( $i );

			if ( ! is_string( $name ) || ! is_array( $stat ) ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_zip_entry_unreadable',
					'message' => 'Unable to read ZIP entry metadata.',
					'context' => array( 'index' => $i ),
				);
				continue;
			}

			// Skip pure directory markers after sanitizing.
			$normalized_name = str_replace( '\\', '/', $name );
			$is_dir          = ( '/' === substr( $normalized_name, -1 ) );

			$sanitized = PathSanitizer::sanitize_archive_entry( rtrim( str_replace( '\\', '/', $name ), '/' ) );
			if ( ! $sanitized->ok() ) {
				foreach ( $sanitized->errors() as $error ) {
					$errors[] = $error;
				}
				continue;
			}

			$safe_name = (string) $sanitized->data();
			$size      = isset( $stat['size'] ) ? (int) $stat['size'] : 0;

			if ( $size < 0 ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_zip_entry_size_invalid',
					'message' => 'ZIP entry reports an invalid size.',
					'context' => array( 'entry' => $name ),
				);
				continue;
			}

			if ( $size > ManifestSchema::MAX_SINGLE_ENTRY_BYTES ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_zip_entry_too_large',
					'message' => 'ZIP entry exceeds per-file size limit.',
					'context' => array(
						'entry' => $safe_name,
						'size'  => $size,
						'max'   => ManifestSchema::MAX_SINGLE_ENTRY_BYTES,
					),
				);
				continue;
			}

			$uncompressed_sum += $size;
			if ( $uncompressed_sum > ManifestSchema::MAX_UNCOMPRESSED_BYTES ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_zip_bomb',
					'message' => 'ZIP uncompressed size exceeds safety limit (possible zip bomb).',
					'context' => array(
						'total' => $uncompressed_sum,
						'max'   => ManifestSchema::MAX_UNCOMPRESSED_BYTES,
					),
				);
				break;
			}

			// Compression ratio bomb heuristic for non-tiny files.
			$compressed = isset( $stat['comp_size'] ) ? (int) $stat['comp_size'] : 0;
			if ( $compressed > 0 && $size > 1024 * 1024 && ( $size / $compressed ) > ManifestSchema::MAX_COMPRESSION_RATIO ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_zip_ratio',
					'message' => 'ZIP entry compression ratio exceeds safety limit.',
					'context' => array(
						'entry'      => $safe_name,
						'ratio'      => round( $size / $compressed, 2 ),
						'max_ratio'  => ManifestSchema::MAX_COMPRESSION_RATIO,
					),
				);
				continue;
			}

			if ( $is_dir || '/' === substr( $name, -1 ) ) {
				$entries[] = array(
					'index'     => $i,
					'name'      => $safe_name,
					'is_dir'    => true,
					'size'      => 0,
					'raw_name'  => $name,
				);
				continue;
			}

			$type_check = self::classify_entry( $safe_name );
			if ( ! $type_check->ok() ) {
				foreach ( $type_check->errors() as $error ) {
					$errors[] = $error;
				}
				continue;
			}

			$entries[] = array(
				'index'    => $i,
				'name'     => $safe_name,
				'is_dir'   => false,
				'size'     => $size,
				'raw_name' => $name,
				'kind'     => $type_check->data()['kind'] ?? 'unknown',
			);
		}

		if ( ! empty( $errors ) ) {
			return PackageResult::failures( $errors, $warnings );
		}

		return PackageResult::success( $entries, $warnings );
	}

	/**
	 * Extract validated entries into a destination directory (must be under TempStorage).
	 *
	 * @param ZipArchive $zip Open archive (caller closes).
	 * @param array      $entries From inspect_entries.
	 * @param string     $dest_dir Absolute destination directory.
	 * @return PackageResult data=array of written relative => absolute paths.
	 */
	public static function extract_entries( ZipArchive $zip, array $entries, string $dest_dir ): PackageResult {
		$dest_dir = rtrim( str_replace( '\\', '/', $dest_dir ), '/' );
		if ( ! is_dir( $dest_dir ) ) {
			return PackageResult::failure(
				'hvnly_ie_extract_dest_missing',
				'Extraction destination directory does not exist.',
				array( 'dest' => $dest_dir )
			);
		}

		$written  = array();
		$errors   = array();
		$warnings = array();

		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['is_dir'] ) ) {
				$resolved = PathSanitizer::resolve_under_base( $dest_dir, (string) $entry['name'] );
				if ( ! $resolved->ok() ) {
					foreach ( $resolved->errors() as $error ) {
						$errors[] = $error;
					}
					continue;
				}
				$path = (string) $resolved->data();
				if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
					$errors[] = array(
						'code'    => 'hvnly_ie_extract_mkdir_failed',
						'message' => 'Failed to create directory during extraction.',
						'context' => array( 'path' => $entry['name'] ),
					);
				}
				continue;
			}

			$resolved = PathSanitizer::resolve_under_base( $dest_dir, (string) $entry['name'] );
			if ( ! $resolved->ok() ) {
				foreach ( $resolved->errors() as $error ) {
					$errors[] = $error;
				}
				continue;
			}

			$abs = (string) $resolved->data();
			$dir = dirname( $abs );
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_extract_mkdir_failed',
					'message' => 'Failed to create parent directory during extraction.',
					'context' => array( 'path' => $entry['name'] ),
				);
				continue;
			}

			$stream = $zip->getStream( (string) $entry['raw_name'] );
			if ( false === $stream ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_extract_stream_failed',
					'message' => 'Failed to open ZIP entry stream.',
					'context' => array( 'path' => $entry['name'] ),
				);
				continue;
			}

			$out = fopen( $abs, 'wb' );
			if ( false === $out ) {
				fclose( $stream );
				$errors[] = array(
					'code'    => 'hvnly_ie_extract_write_failed',
					'message' => 'Failed to open destination file for writing.',
					'context' => array( 'path' => $entry['name'] ),
				);
				continue;
			}

			$copied = 0;
			$max    = ManifestSchema::MAX_SINGLE_ENTRY_BYTES;
			$failed = false;

			while ( ! feof( $stream ) ) {
				$chunk = fread( $stream, 8192 );
				if ( false === $chunk ) {
					$failed = true;
					break;
				}
				if ( '' === $chunk ) {
					break;
				}
				$copied += strlen( $chunk );
				if ( $copied > $max ) {
					$failed   = true;
					$errors[] = array(
						'code'    => 'hvnly_ie_extract_size_exceeded',
						'message' => 'Extraction aborted: entry exceeded size limit while streaming.',
						'context' => array( 'path' => $entry['name'] ),
					);
					break;
				}
				if ( false === fwrite( $out, $chunk ) ) {
					$failed   = true;
					$errors[] = array(
						'code'    => 'hvnly_ie_extract_write_failed',
						'message' => 'Failed while writing extracted file.',
						'context' => array( 'path' => $entry['name'] ),
					);
					break;
				}
			}

			fclose( $stream );
			fclose( $out );

			if ( $failed ) {
				if ( is_file( $abs ) ) {
					unlink( $abs );
				}
				continue;
			}

			// Post-extract MIME check for media files.
			if ( isset( $entry['kind'] ) && 'media' === $entry['kind'] ) {
				$mime = MimeGuard::validate_media_entry( (string) $entry['name'], $abs );
				if ( ! $mime->ok() ) {
					unlink( $abs );
					foreach ( $mime->errors() as $error ) {
						$errors[] = $error;
					}
					continue;
				}
			}

			$written[ (string) $entry['name'] ] = $abs;
		}

		if ( ! empty( $errors ) ) {
			return PackageResult::failures( $errors, $warnings, array( 'written' => $written ) );
		}

		return PackageResult::success( array( 'written' => $written ), $warnings );
	}

	/**
	 * @param string $safe_name Sanitized relative path.
	 * @return PackageResult data={kind:string}
	 */
	private static function classify_entry( string $safe_name ): PackageResult {
		$root_files = array(
			ManifestSchema::FILE_MANIFEST,
			ManifestSchema::FILE_ENTITIES,
			ManifestSchema::FILE_MEDIA_INDEX,
			'REPORT.txt',
		);

		if ( in_array( $safe_name, $root_files, true ) ) {
			if ( 'REPORT.txt' === $safe_name ) {
				return PackageResult::success( array( 'kind' => 'meta' ) );
			}
			$json = MimeGuard::validate_json_entry( $safe_name );
			if ( ! $json->ok() ) {
				return $json;
			}
			return PackageResult::success( array( 'kind' => 'meta' ) );
		}

		if ( 0 === strpos( $safe_name, ManifestSchema::DIR_MEDIA . '/' ) ) {
			$media = MimeGuard::validate_media_entry( $safe_name );
			if ( ! $media->ok() ) {
				return $media;
			}
			return PackageResult::success( array( 'kind' => 'media' ) );
		}

		return PackageResult::failure(
			'hvnly_ie_zip_unexpected_entry',
			'ZIP contains an unexpected entry outside the HPTP layout.',
			array( 'path' => $safe_name )
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
