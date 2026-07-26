<?php
/**
 * Orchestrates HPTP media unpack + entity remapping.
 *
 * @package HvnlyNab\ImportExport\Media
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Media;

use HvnlyNab\ImportExport\Import\EntityReader;
use HvnlyNab\ImportExport\Import\IdRemapper;
use HvnlyNab\ImportExport\Package\PackageResult;

defined( 'ABSPATH' ) || exit;

/**
 * MediaImporter — Phase 6 entry point (no UI / AJAX).
 *
 * @since 3.6.0
 */
final class MediaImporter {

	/**
	 * Unpack binaries and reconnect media to imported entities.
	 *
	 * Soft-fails missing/invalid files; never aborts the whole package for one bad file.
	 *
	 * @param array|null           $media_index Decoded media-index.json or null.
	 * @param array<string,string> $files       Relative => absolute extracted paths.
	 * @param EntityReader         $reader      Entity reader.
	 * @param IdRemapper           $remapper    Entity remapper.
	 * @return PackageResult
	 */
	public static function import(
		?array $media_index,
		array $files,
		EntityReader $reader,
		IdRemapper $remapper
	): PackageResult {
		$warnings = array();

		if ( null === $media_index || empty( $media_index['files'] ) ) {
			return PackageResult::success(
				array(
					'created'  => 0,
					'skipped'  => 0,
					'failed'   => 0,
					'map'      => array(),
					'remap'    => array(),
					'reason'   => 'no_media_index',
				),
				array(
					array(
						'code'    => 'hvnly_ie_media_not_in_package',
						'message' => 'Package has no media-index; entity import completed without media restoration.',
						'context' => array(),
					),
				)
			);
		}

		$unpacked = MediaUnpacker::unpack( $media_index, $files, 0 );
		// Unpack always soft-succeeds with warnings for individual files.
		foreach ( $unpacked->warnings() as $warning ) {
			$warnings[] = $warning;
		}
		if ( ! $unpacked->ok() ) {
			// Unexpected hard failure (should be rare) — still do not abort callers that want soft-fail:
			// convert to success with warnings so ImportJob can finish.
			foreach ( $unpacked->errors() as $error ) {
				$warnings[] = $error;
			}
			return PackageResult::success(
				array(
					'created' => 0,
					'skipped' => 0,
					'failed'  => 1,
					'map'     => array(),
					'remap'   => array(),
					'reason'  => 'unpack_failed',
				),
				$warnings
			);
		}

		$data = $unpacked->data();
		$map  = isset( $data['map'] ) && is_array( $data['map'] ) ? $data['map'] : array();

		foreach ( $map as $export_key => $attachment_id ) {
			$remapper->set_media( (string) $export_key, (int) $attachment_id );
		}

		$rewriter = new UrlRewriter( $map );
		$remapped = MediaRemapper::apply( $rewriter, $reader, $remapper );
		foreach ( $remapped->warnings() as $warning ) {
			$warnings[] = $warning;
		}

		$remap_data = $remapped->ok() && is_array( $remapped->data() ) ? $remapped->data() : array();

		return PackageResult::success(
			array(
				'created' => absint( $data['created'] ?? 0 ),
				'skipped' => absint( $data['skipped'] ?? 0 ),
				'failed'  => absint( $data['failed'] ?? 0 ),
				'map'     => $map,
				'remap'   => isset( $remap_data['stats'] ) ? $remap_data['stats'] : array(),
			),
			$warnings
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
