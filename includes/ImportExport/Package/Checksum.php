<?php
/**
 * Checksum helpers for HPTP packages.
 *
 * @package HvnlyNab\ImportExport\Package
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Package;

defined( 'ABSPATH' ) || exit;

/**
 * SHA-256 hashing and verification (no silent mismatch).
 *
 * @since 3.6.0
 */
final class Checksum {

	/**
	 * Hash a string.
	 *
	 * @param string $contents Contents.
	 * @return PackageResult data=string lowercase hex hash.
	 */
	public static function hash_string( string $contents ): PackageResult {
		$hash = hash( ManifestSchema::CHECKSUM_ALGORITHM, $contents );
		if ( ! is_string( $hash ) || '' === $hash ) {
			return PackageResult::failure(
				'hvnly_ie_checksum_failed',
				'Unable to compute content checksum.',
				array( 'algo' => ManifestSchema::CHECKSUM_ALGORITHM )
			);
		}

		return PackageResult::success( strtolower( $hash ) );
	}

	/**
	 * Hash a file on disk.
	 *
	 * @param string $path Absolute path.
	 * @return PackageResult data=string lowercase hex hash.
	 */
	public static function hash_file( string $path ): PackageResult {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return PackageResult::failure(
				'hvnly_ie_checksum_unreadable',
				'Cannot checksum missing or unreadable file.',
				array( 'path' => $path )
			);
		}

		$hash = hash_file( ManifestSchema::CHECKSUM_ALGORITHM, $path );
		if ( ! is_string( $hash ) || '' === $hash ) {
			return PackageResult::failure(
				'hvnly_ie_checksum_failed',
				'Unable to compute file checksum.',
				array(
					'path' => $path,
					'algo' => ManifestSchema::CHECKSUM_ALGORITHM,
				)
			);
		}

		return PackageResult::success( strtolower( $hash ) );
	}

	/**
	 * Verify a file against an expected hex digest.
	 *
	 * @param string $path     Absolute path.
	 * @param string $expected Expected hash.
	 * @return PackageResult
	 */
	public static function verify_file( string $path, string $expected ): PackageResult {
		$expected = strtolower( trim( $expected ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected ) ) {
			return PackageResult::failure(
				'hvnly_ie_checksum_format',
				'Expected checksum is not a valid SHA-256 hex digest.',
				array( 'expected' => $expected )
			);
		}

		$actual = self::hash_file( $path );
		if ( ! $actual->ok() ) {
			return $actual;
		}

		if ( ! hash_equals( $expected, (string) $actual->data() ) ) {
			return PackageResult::failure(
				'hvnly_ie_checksum_mismatch',
				'Checksum verification failed.',
				array(
					'path'     => $path,
					'expected' => $expected,
					'actual'   => (string) $actual->data(),
				)
			);
		}

		return PackageResult::success(
			array(
				'path' => $path,
				'hash' => (string) $actual->data(),
			)
		);
	}

	/**
	 * Verify a string against an expected hex digest.
	 *
	 * @param string $contents Contents.
	 * @param string $expected Expected hash.
	 * @return PackageResult
	 */
	public static function verify_string( string $contents, string $expected ): PackageResult {
		$expected = strtolower( trim( $expected ) );
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected ) ) {
			return PackageResult::failure(
				'hvnly_ie_checksum_format',
				'Expected checksum is not a valid SHA-256 hex digest.',
				array( 'expected' => $expected )
			);
		}

		$actual = self::hash_string( $contents );
		if ( ! $actual->ok() ) {
			return $actual;
		}

		if ( ! hash_equals( $expected, (string) $actual->data() ) ) {
			return PackageResult::failure(
				'hvnly_ie_checksum_mismatch',
				'Checksum verification failed.',
				array(
					'expected' => $expected,
					'actual'   => (string) $actual->data(),
				)
			);
		}

		return PackageResult::success(
			array(
				'hash' => (string) $actual->data(),
			)
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
