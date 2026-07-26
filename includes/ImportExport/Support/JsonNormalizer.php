<?php
/**
 * Deterministic JSON normalization for HPTP packages.
 *
 * @package HvnlyNab\ImportExport\Support
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Support;

use HvnlyNab\ImportExport\Package\PackageResult;

defined( 'ABSPATH' ) || exit;

/**
 * Stable, human-readable JSON encoding.
 *
 * @since 3.6.0
 */
final class JsonNormalizer {

	/**
	 * Recursively sort array keys for deterministic output.
	 *
	 * List (sequential) arrays preserve order; maps are sorted by key.
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 */
	public static function normalize( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );
		if ( ! $is_list ) {
			ksort( $data, SORT_STRING );
		}

		foreach ( $data as $key => $value ) {
			$data[ $key ] = self::normalize( $value );
		}

		return $data;
	}

	/**
	 * Encode normalized data as pretty JSON.
	 *
	 * @param mixed $data Data.
	 * @return PackageResult data=string JSON.
	 */
	public static function encode( $data ): PackageResult {
		$normalized = self::normalize( $data );
		$json       = wp_json_encode(
			$normalized,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ( false === $json ) {
			return PackageResult::failure(
				'hvnly_ie_json_encode',
				'Failed to encode package JSON.',
				array()
			);
		}

		return PackageResult::success( $json );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
