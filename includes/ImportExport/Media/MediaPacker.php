<?php
/**
 * Builds PackageWriter media payloads from indexed entries.
 *
 * @package HvnlyNab\ImportExport\Media
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Media;

use HvnlyNab\ImportExport\Package\PackageResult;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares unique binaries + media-index.json content for packaging.
 *
 * @since 3.6.0
 */
final class MediaPacker {

	/**
	 * Convert indexer entries into writer inputs.
	 *
	 * @param array<int, array<string, mixed>> $entries From MediaIndexer.
	 * @return PackageResult data={binaries,index,catalog,packaged_count,unique_binary_count}
	 */
	public static function pack( array $entries ): PackageResult {
		$binaries_by_path = array();
		$index_files      = array();
		$catalog          = array();
		$warnings         = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ( $entry['status'] ?? '' ) !== 'pack' ) {
				continue;
			}

			$export_key   = (string) ( $entry['export_key'] ?? '' );
			$archive_path = (string) ( $entry['path'] ?? '' );
			$source       = (string) ( $entry['source'] ?? '' );
			$checksum     = (string) ( $entry['checksum'] ?? '' );

			if ( '' === $export_key || '' === $archive_path || '' === $source ) {
				$warnings[] = array(
					'code'    => 'hvnly_ie_media_pack_incomplete',
					'message' => 'Incomplete media pack entry was skipped.',
					'context' => array( 'export_key' => $export_key ),
				);
				continue;
			}

			if ( ! isset( $binaries_by_path[ $archive_path ] ) ) {
				$binaries_by_path[ $archive_path ] = array(
					'archive_path' => $archive_path,
					'source'       => $source,
					'export_key'   => $export_key,
					'filename'     => (string) ( $entry['filename'] ?? '' ),
					'mime_type'    => (string) ( $entry['mime_type'] ?? '' ),
				);
			}

			$index_files[] = array(
				'export_key' => $export_key,
				'path'       => $archive_path,
				'filename'   => (string) ( $entry['filename'] ?? '' ),
				'mime_type'  => (string) ( $entry['mime_type'] ?? '' ),
				'checksum'   => $checksum,
				'size'       => isset( $entry['size'] ) ? (int) $entry['size'] : 0,
			);

			$catalog[] = array(
				'export_key' => $export_key,
				'filename'   => (string) ( $entry['filename'] ?? '' ),
				'mime_type'  => (string) ( $entry['mime_type'] ?? '' ),
				'path'       => $archive_path,
				'bundled'    => true,
				'checksum'   => $checksum,
			);
		}

		// Validate index ↔ binaries consistency.
		$binary_paths = array_keys( $binaries_by_path );
		$index_paths  = array();
		foreach ( $index_files as $row ) {
			$index_paths[ $row['path'] ] = true;
			if ( ! isset( $binaries_by_path[ $row['path'] ] ) ) {
				return PackageResult::failure(
					'hvnly_ie_media_index_orphan',
					'media-index references a path with no packaged binary.',
					array(
						'path' => $row['path'],
						'export_key' => $row['export_key'],
					)
				);
			}
		}

		foreach ( $binary_paths as $path ) {
			if ( empty( $index_paths[ $path ] ) ) {
				return PackageResult::failure(
					'hvnly_ie_media_binary_orphan',
					'Packaged binary has no media-index entry.',
					array( 'path' => $path )
				);
			}
		}

		$index = array(
			'files' => $index_files,
		);

		return PackageResult::success(
			array(
				'binaries'            => array_values( $binaries_by_path ),
				'index'               => $index,
				'catalog'             => $catalog,
				'packaged_count'      => count( $index_files ),
				'unique_binary_count' => count( $binaries_by_path ),
			),
			$warnings
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
