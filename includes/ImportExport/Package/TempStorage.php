<?php
/**
 * Protected temporary storage for HPTP package work.
 *
 * @package HvnlyNab\ImportExport\Package
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Package;

defined( 'ABSPATH' ) || exit;

/**
 * All package filesystem writes must stay under this base directory.
 *
 * @since 3.6.0
 */
final class TempStorage {

	/**
	 * Absolute base directory under uploads, or empty on failure.
	 *
	 * @return PackageResult data=string absolute base path.
	 */
	public static function get_base_dir(): PackageResult {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return PackageResult::failure(
				'hvnly_ie_temp_wp_missing',
				'WordPress upload directory API is unavailable.',
				array()
			);
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return PackageResult::failure(
				'hvnly_ie_temp_uploads_error',
				'Unable to resolve WordPress uploads directory.',
				array( 'error' => (string) $uploads['error'] )
			);
		}

		$base = trailingslashit( (string) $uploads['basedir'] ) . ManifestSchema::TEMP_DIR_NAME;
		$base = wp_normalize_path( $base );

		return PackageResult::success( $base );
	}

	/**
	 * Ensure base directory exists and is web-protected.
	 *
	 * @return PackageResult data=string absolute base path.
	 */
	public static function ensure_base(): PackageResult {
		$base = self::get_base_dir();
		if ( ! $base->ok() ) {
			return $base;
		}

		$dir = (string) $base->data();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return PackageResult::failure(
				'hvnly_ie_temp_mkdir_failed',
				'Failed to create package temporary storage directory.',
				array( 'path' => $dir )
			);
		}

		self::write_protection_files( $dir );

		return PackageResult::success( $dir );
	}

	/**
	 * Create a unique working directory for one package operation.
	 *
	 * @param string $prefix Directory name prefix (sanitized).
	 * @return PackageResult data={dir:string,token:string}
	 */
	public static function create_workdir( string $prefix = 'pkg' ): PackageResult {
		$base = self::ensure_base();
		if ( ! $base->ok() ) {
			return $base;
		}

		$prefix = preg_replace( '/[^a-z0-9_-]+/i', '', $prefix );
		if ( ! is_string( $prefix ) || '' === $prefix ) {
			$prefix = 'pkg';
		}

		$token = strtolower( wp_generate_password( 20, false, false ) );
		$name  = $prefix . '-' . gmdate( 'YmdHis' ) . '-' . $token;
		$dir   = trailingslashit( (string) $base->data() ) . $name;
		$dir   = wp_normalize_path( $dir );

		if ( ! wp_mkdir_p( $dir ) ) {
			return PackageResult::failure(
				'hvnly_ie_temp_workdir_failed',
				'Failed to create package work directory.',
				array( 'path' => $dir )
			);
		}

		self::write_protection_files( $dir );

		return PackageResult::success(
			array(
				'dir'   => $dir,
				'token' => $token,
				'name'  => $name,
			)
		);
	}

	/**
	 * Assert an absolute path is under the temp base (no writes outside).
	 *
	 * @param string $absolute Absolute path.
	 * @return PackageResult
	 */
	public static function assert_under_base( string $absolute ): PackageResult {
		$base = self::ensure_base();
		if ( ! $base->ok() ) {
			return $base;
		}

		$base_path = wp_normalize_path( (string) $base->data() );
		$target    = wp_normalize_path( $absolute );

		$base_cmp   = strtolower( rtrim( $base_path, '/' ) );
		$target_cmp = strtolower( $target );

		if ( $target_cmp !== $base_cmp && 0 !== strpos( $target_cmp, $base_cmp . '/' ) ) {
			return PackageResult::failure(
				'hvnly_ie_temp_path_escape',
				'Path is outside protected package temporary storage.',
				array(
					'base'   => $base_path,
					'target' => $target,
				)
			);
		}

		return PackageResult::success( $target );
	}

	/**
	 * Delete a work directory tree if it is under the temp base.
	 *
	 * @param string $dir Absolute directory.
	 * @return PackageResult
	 */
	public static function delete_workdir( string $dir ): PackageResult {
		$check = self::assert_under_base( $dir );
		if ( ! $check->ok() ) {
			return $check;
		}

		if ( ! is_dir( $dir ) ) {
			return PackageResult::success( array( 'deleted' => false ) );
		}

		self::recursive_delete( $dir );

		return PackageResult::success( array( 'deleted' => ! is_dir( $dir ) ) );
	}

	/**
	 * Remove work directories older than retention window.
	 *
	 * @return PackageResult data={removed:int}
	 */
	public static function cleanup_expired(): PackageResult {
		$base = self::ensure_base();
		if ( ! $base->ok() ) {
			return $base;
		}

		$dir = (string) $base->data();
		$removed = 0;
		$cutoff  = time() - ManifestSchema::TEMP_RETENTION_SECONDS;

		$entries = scandir( $dir );
		if ( ! is_array( $entries ) ) {
			return PackageResult::success( array( 'removed' => 0 ) );
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( ! is_dir( $path ) ) {
				continue;
			}
			$mtime = filemtime( $path );
			if ( false !== $mtime && $mtime < $cutoff ) {
				$del = self::delete_workdir( $path );
				if ( $del->ok() ) {
					++$removed;
				}
			}
		}

		return PackageResult::success( array( 'removed' => $removed ) );
	}

	/**
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function write_protection_files( string $dir ): void {
		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents(
				$htaccess,
				"Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
			);
		}
	}

	/**
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function recursive_delete( string $dir ): void {
		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				self::recursive_delete( $path );
			} elseif ( is_file( $path ) ) {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
