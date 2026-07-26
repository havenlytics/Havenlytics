<?php
/**
 * Manifest.json validation for HPTP packages.
 *
 * @package HvnlyNab\ImportExport\Package
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Package;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and validates package manifests (structure + schema version).
 *
 * @since 3.6.0
 */
final class ManifestValidator {

	/**
	 * Decode and validate a manifest JSON string.
	 *
	 * @param string $json Raw JSON.
	 * @return PackageResult data=array validated manifest (+ warnings).
	 */
	public static function validate_json( string $json ): PackageResult {
		if ( '' === trim( $json ) ) {
			return PackageResult::failure(
				'hvnly_ie_manifest_empty',
				'Package manifest is empty.',
				array()
			);
		}

		$decoded = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return PackageResult::failure(
				'hvnly_ie_manifest_json',
				'Package manifest is not valid JSON.',
				array( 'json_error' => json_last_error_msg() )
			);
		}

		return self::validate_array( $decoded );
	}

	/**
	 * Validate a decoded manifest array.
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 * @return PackageResult
	 */
	public static function validate_array( array $manifest ): PackageResult {
		$errors   = array();
		$warnings = array();

		$required = array(
			'format',
			'schema_version',
			'exported_at',
			'package_name',
			'contents',
			'counts',
			'checksums',
		);

		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $manifest ) ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_manifest_missing_field',
					'message' => 'Package manifest is missing a required field.',
					'context' => array( 'field' => $key ),
				);
			}
		}

		if ( ! empty( $errors ) ) {
			return PackageResult::failures( $errors );
		}

		if ( ManifestSchema::FORMAT !== (string) $manifest['format'] ) {
			$errors[] = array(
				'code'    => 'hvnly_ie_manifest_format',
				'message' => 'This ZIP is not a Havenlytics Property Transfer Package.',
				'context' => array(
					'expected' => ManifestSchema::FORMAT,
					'actual'   => (string) $manifest['format'],
				),
			);
		}

		$version_check = self::validate_schema_version( (string) $manifest['schema_version'] );
		if ( ! $version_check->ok() ) {
			foreach ( $version_check->errors() as $error ) {
				$errors[] = $error;
			}
		} else {
			foreach ( $version_check->warnings() as $warning ) {
				$warnings[] = $warning;
			}
		}

		if ( ! is_array( $manifest['contents'] ) ) {
			$errors[] = array(
				'code'    => 'hvnly_ie_manifest_contents',
				'message' => 'Manifest contents must be an object/array of flags.',
				'context' => array(),
			);
		}

		if ( ! is_array( $manifest['counts'] ) ) {
			$errors[] = array(
				'code'    => 'hvnly_ie_manifest_counts',
				'message' => 'Manifest counts must be an object/array.',
				'context' => array(),
			);
		}

		if ( ! is_array( $manifest['checksums'] ) ) {
			$errors[] = array(
				'code'    => 'hvnly_ie_manifest_checksums',
				'message' => 'Manifest checksums must be an object/array.',
				'context' => array(),
			);
		} else {
			$algo = isset( $manifest['checksums']['algorithm'] )
				? (string) $manifest['checksums']['algorithm']
				: ManifestSchema::CHECKSUM_ALGORITHM;
			if ( ManifestSchema::CHECKSUM_ALGORITHM !== strtolower( $algo ) ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_manifest_checksum_algo',
					'message' => 'Unsupported checksum algorithm in manifest.',
					'context' => array( 'algorithm' => $algo ),
				);
			}
			if ( empty( $manifest['checksums'][ ManifestSchema::FILE_ENTITIES ] ) ) {
				$errors[] = array(
					'code'    => 'hvnly_ie_manifest_entities_checksum',
					'message' => 'Manifest must include a checksum for entities.json.',
					'context' => array(),
				);
			}
		}

		$package_name = (string) $manifest['package_name'];
		if ( '' === trim( $package_name ) || strlen( $package_name ) > 80 ) {
			$errors[] = array(
				'code'    => 'hvnly_ie_manifest_package_name',
				'message' => 'package_name must be 1–80 characters.',
				'context' => array( 'package_name' => $package_name ),
			);
		}

		if ( ! empty( $errors ) ) {
			return PackageResult::failures( $errors, $warnings, $manifest );
		}

		return PackageResult::success( $manifest, $warnings );
	}

	/**
	 * Schema version rules from the functional specification.
	 *
	 * @param string $version Version string (e.g. 1.0).
	 * @return PackageResult
	 */
	public static function validate_schema_version( string $version ): PackageResult {
		$version = trim( $version );
		if ( ! preg_match( '/^(\d+)\.(\d+)$/', $version, $m ) ) {
			return PackageResult::failure(
				'hvnly_ie_schema_version_format',
				'schema_version must be in major.minor form.',
				array( 'schema_version' => $version )
			);
		}

		$major = (int) $m[1];
		$minor = (int) $m[2];
		$warnings = array();

		if ( $major > ManifestSchema::SCHEMA_MAJOR ) {
			return PackageResult::failure(
				'hvnly_ie_schema_version_unsupported',
				'This package requires a newer Havenlytics version.',
				array(
					'schema_version' => $version,
					'supported_major' => ManifestSchema::SCHEMA_MAJOR,
				)
			);
		}

		if ( $major < ManifestSchema::SCHEMA_MAJOR ) {
			return PackageResult::failure(
				'hvnly_ie_schema_version_too_old',
				'This package schema major version is no longer supported.',
				array(
					'schema_version' => $version,
					'supported_major' => ManifestSchema::SCHEMA_MAJOR,
				)
			);
		}

		$current_parts = explode( '.', ManifestSchema::SCHEMA_VERSION );
		$current_minor = isset( $current_parts[1] ) ? (int) $current_parts[1] : 0;

		if ( $minor > $current_minor ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_schema_minor_ahead',
				'message' => 'Package schema minor version is newer; unknown fields may be skipped.',
				'context' => array(
					'schema_version' => $version,
					'plugin_schema'  => ManifestSchema::SCHEMA_VERSION,
				),
			);
		}

		return PackageResult::success(
			array(
				'major' => $major,
				'minor' => $minor,
			),
			$warnings
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
