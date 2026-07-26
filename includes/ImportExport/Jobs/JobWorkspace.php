<?php
/**
 * Disk helpers for durable job workspaces under TempStorage.
 *
 * @package HvnlyNab\ImportExport\Jobs
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Jobs;

use HvnlyNab\ImportExport\Package\TempStorage;
use HvnlyNab\ImportExport\Security\PathSanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * JobWorkspace — JSON read/write inside a job workdir.
 *
 * @since 3.6.0
 */
final class JobWorkspace {

	/**
	 * @param string $dir Workdir.
	 * @param string $relative Relative file.
	 * @param mixed  $data Data.
	 * @return bool
	 */
	public static function write_json( string $dir, string $relative, $data ): bool {
		$path = self::resolve_path( $dir, $relative );
		if ( null === $path ) {
			return false;
		}
		$parent = dirname( $path );
		if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
			return false;
		}
		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) ) {
			return false;
		}
		return false !== file_put_contents( $path, $json );
	}

	/**
	 * @param string $dir Workdir.
	 * @param string $relative Relative file.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function read_json( string $dir, string $relative, $default = null ) {
		$path = self::resolve_path( $dir, $relative );
		if ( null === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
			return $default;
		}
		$raw = file_get_contents( $path );
		if ( false === $raw ) {
			return $default;
		}
		$decoded = json_decode( $raw, true );
		return ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $default;
	}

	/**
	 * Resolve a relative workspace path under TempStorage base.
	 *
	 * @param string $dir Workdir.
	 * @param string $relative Relative path.
	 * @return string|null Absolute path or null if unsafe.
	 */
	private static function resolve_path( string $dir, string $relative ): ?string {
		$dir = wp_normalize_path( $dir );
		$under = TempStorage::assert_under_base( $dir );
		if ( ! $under->ok() ) {
			return null;
		}

		$sanitized = PathSanitizer::sanitize_archive_entry( $relative );
		if ( ! $sanitized->ok() ) {
			// Allow simple relative filenames used by job runners (entities.json, etc.).
			$relative = str_replace( '\\', '/', $relative );
			$relative = ltrim( $relative, '/' );
			if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
				return null;
			}
			if ( ! preg_match( '#^[a-zA-Z0-9._/-]+$#', $relative ) ) {
				return null;
			}
		} else {
			$relative = (string) $sanitized->data();
		}

		$path = trailingslashit( $dir ) . $relative;
		$path = wp_normalize_path( $path );
		$check = TempStorage::assert_under_base( $path );
		if ( ! $check->ok() ) {
			// Parent may not exist yet for nested writes — assert parent dir.
			$parent_check = TempStorage::assert_under_base( dirname( $path ) );
			if ( ! $parent_check->ok() && dirname( $path ) !== $dir ) {
				return null;
			}
			if ( 0 !== strpos( strtolower( $path ), strtolower( rtrim( $dir, '/' ) ) . '/' ) && strtolower( $path ) !== strtolower( rtrim( $dir, '/' ) ) ) {
				return null;
			}
		}

		return $path;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
