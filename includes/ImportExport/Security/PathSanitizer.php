<?php
/**
 * Archive / filesystem path sanitization for HPTP packages.
 *
 * @package HvnlyNab\ImportExport\Security
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Security;

use HvnlyNab\ImportExport\Package\PackageResult;

defined( 'ABSPATH' ) || exit;

/**
 * Rejects ZIP slip, traversal, absolute paths, and unsafe filenames.
 *
 * @since 3.6.0
 */
final class PathSanitizer {

	/**
	 * Max length for a single archive entry path.
	 *
	 * @var int
	 */
	public const MAX_PATH_LENGTH = 255;

	/**
	 * Sanitize a relative path as it appears inside a ZIP.
	 *
	 * @param string $entry Raw ZipArchive entry name.
	 * @return PackageResult data=string sanitized relative path (no leading slash).
	 */
	public static function sanitize_archive_entry( string $entry ): PackageResult {
		$raw = str_replace( "\0", '', $entry );
		$raw = str_replace( '\\', '/', $raw );
		$raw = trim( $raw );

		if ( '' === $raw || '.' === $raw ) {
			return PackageResult::failure(
				'hvnly_ie_path_empty',
				'Archive entry path is empty.',
				array( 'entry' => $entry )
			);
		}

		if ( strlen( $raw ) > self::MAX_PATH_LENGTH ) {
			return PackageResult::failure(
				'hvnly_ie_path_too_long',
				'Archive entry path exceeds maximum length.',
				array(
					'entry' => $entry,
					'max' => self::MAX_PATH_LENGTH,
				)
			);
		}

		// Absolute / Windows drive / UNC.
		if ( 0 === strpos( $raw, '/' ) || preg_match( '#^[a-zA-Z]:#', $raw ) || 0 === strpos( $raw, '//' ) ) {
			return PackageResult::failure(
				'hvnly_ie_path_absolute',
				'Absolute archive paths are not allowed.',
				array( 'entry' => $entry )
			);
		}

		$parts      = explode( '/', $raw );
		$normalized = array();

		foreach ( $parts as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				return PackageResult::failure(
					'hvnly_ie_path_traversal',
					'Path traversal segments are not allowed.',
					array( 'entry' => $entry )
				);
			}
			if ( ! self::is_safe_segment( $part ) ) {
				return PackageResult::failure(
					'hvnly_ie_path_unsafe_segment',
					'Archive path contains an unsafe filename segment.',
					array(
						'entry' => $entry,
						'segment' => $part,
					)
				);
			}
			$normalized[] = $part;
		}

		if ( empty( $normalized ) ) {
			return PackageResult::failure(
				'hvnly_ie_path_empty',
				'Archive entry path resolved empty.',
				array( 'entry' => $entry )
			);
		}

		$safe = implode( '/', $normalized );

		return PackageResult::success( $safe );
	}

	/**
	 * Resolve a path under a base directory; fail if it escapes the base.
	 *
	 * @param string $base_dir Absolute base directory (must exist).
	 * @param string $relative Relative path (already sanitized preferred).
	 * @return PackageResult data=string absolute path.
	 */
	public static function resolve_under_base( string $base_dir, string $relative ): PackageResult {
		$entry = self::sanitize_archive_entry( $relative );
		if ( ! $entry->ok() ) {
			return $entry;
		}

		$base = self::normalize_fs_path( $base_dir );
		if ( '' === $base ) {
			return PackageResult::failure(
				'hvnly_ie_base_invalid',
				'Package storage base directory is invalid.',
				array( 'base' => $base_dir )
			);
		}

		$target = self::normalize_fs_path( $base . '/' . str_replace( '\\', '/', (string) $entry->data() ) );
		if ( '' === $target ) {
			return PackageResult::failure(
				'hvnly_ie_path_resolve_failed',
				'Failed to resolve path under package storage.',
				array( 'relative' => $relative )
			);
		}

		$base_prefix = rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR;
		// Allow exact base match only for directories; files must be strictly under base.
		if ( $target !== $base && 0 !== strpos( $target . DIRECTORY_SEPARATOR, $base_prefix ) && 0 !== strpos( $target, $base_prefix ) ) {
			// Compare using normalized separators.
			$base_cmp   = strtolower( str_replace( '\\', '/', $base ) );
			$target_cmp = strtolower( str_replace( '\\', '/', $target ) );
			if ( $target_cmp !== $base_cmp && 0 !== strpos( $target_cmp, $base_cmp . '/' ) ) {
				return PackageResult::failure(
					'hvnly_ie_path_escape',
					'Resolved path escapes the package storage directory.',
					array(
						'base'   => $base,
						'target' => $target,
					)
				);
			}
		}

		return PackageResult::success( $target );
	}

	/**
	 * @param string $segment Single path segment.
	 * @return bool
	 */
	private static function is_safe_segment( string $segment ): bool {
		if ( '' === $segment || '.' === $segment || '..' === $segment ) {
			return false;
		}
		// No control chars; allow alnum, dash, underscore, dot.
		if ( ! preg_match( '/^[A-Za-z0-9._-]+$/', $segment ) ) {
			return false;
		}
		// Leading/trailing dots can hide extensions on some FS.
		if ( '.' === $segment[0] || '.' === substr( $segment, -1 ) ) {
			// Allow ".htaccess" denial files we write ourselves — not from archives.
			return false;
		}
		$lower = strtolower( $segment );
		if ( in_array( $lower, array( 'con', 'prn', 'aux', 'nul', 'com1', 'lpt1' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param string $path Path.
	 * @return string Normalized absolute-ish path or empty on failure.
	 */
	private static function normalize_fs_path( string $path ): string {
		$path = str_replace( "\0", '', $path );
		$path = str_replace( '\\', '/', $path );

		// realpath requires existence; rebuild from the nearest existing ancestor.
		$real = realpath( $path );
		if ( false !== $real ) {
			return str_replace( '\\', '/', $real );
		}

		// Nested extract targets (e.g. media/file.jpg) may have missing parents.
		// Walk up until an existing ancestor is found, then append remaining segments.
		$parts  = array();
		$cursor = $path;
		while ( true ) {
			$parent = dirname( $cursor );
			if ( $parent === $cursor || '' === $parent ) {
				return '';
			}
			array_unshift( $parts, basename( $cursor ) );
			$real_parent = realpath( $parent );
			if ( false !== $real_parent ) {
				$resolved = str_replace( '\\', '/', $real_parent );
				if ( ! empty( $parts ) ) {
					$resolved .= '/' . implode( '/', $parts );
				}
				return $resolved;
			}
			$cursor = $parent;
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
