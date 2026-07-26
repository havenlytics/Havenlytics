<?php
/**
 * Handles CSV upload storage + header/sample parsing.
 *
 * @package HvnlyNab\CsvTransfer\Import
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Import;

use HvnlyNab\CsvTransfer\Support\CsvStream;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * CsvParser — upload handling + header/sample extraction.
 *
 * @since 3.7.0
 */
final class CsvParser {

	public const UPLOAD_DIR_NAME = 'havenlytics-csv';

	public const MAX_BYTES = 26214400; // 25 MB.

	/**
	 * Absolute path to the protected CSV working directory (created on demand).
	 *
	 * @return string|WP_Error
	 */
	public static function base_dir() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return new WP_Error( 'hvnly_csv_wp_missing', __( 'WordPress upload directory API is unavailable.', 'havenlytics' ) );
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'hvnly_csv_uploads_error', (string) $uploads['error'] );
		}

		$dir = trailingslashit( (string) $uploads['basedir'] ) . self::UPLOAD_DIR_NAME;
		$dir = wp_normalize_path( $dir );

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'hvnly_csv_mkdir_failed', __( 'Failed to create the CSV working directory.', 'havenlytics' ) );
		}

		self::write_protection_files( $dir );

		return $dir;
	}

	/**
	 * Move an uploaded CSV into protected storage.
	 *
	 * @param array<string, mixed> $file $_FILES['file'] entry.
	 * @return array{path:string,filename:string,size:int}|WP_Error
	 */
	public static function store_upload( array $file ) {
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'hvnly_csv_upload_error', __( 'Upload failed.', 'havenlytics' ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size <= 0 || $size > self::MAX_BYTES ) {
			return new WP_Error( 'hvnly_csv_upload_size', __( 'CSV file exceeds the maximum allowed size (25MB).', 'havenlytics' ) );
		}

		$name = sanitize_file_name( (string) ( $file['name'] ?? 'import.csv' ) );
		if ( ! preg_match( '/\.(csv|txt)$/i', $name ) ) {
			return new WP_Error( 'hvnly_csv_upload_type', __( 'Only .csv files are supported.', 'havenlytics' ) );
		}

		$dir = self::base_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$token = strtolower( wp_generate_password( 16, false, false ) );
		$dest  = trailingslashit( $dir ) . $token . '-' . $name;

		if ( ! empty( $file['tmp_name'] ) && is_uploaded_file( (string) $file['tmp_name'] ) ) {
			if ( ! move_uploaded_file( (string) $file['tmp_name'], $dest ) ) {
				return new WP_Error( 'hvnly_csv_upload_move_failed', __( 'Could not store the uploaded CSV file.', 'havenlytics' ) );
			}
		} elseif ( ! empty( $file['tmp_name'] ) && is_readable( (string) $file['tmp_name'] ) ) {
			// Allows programmatic/test invocation outside a real HTTP upload.
			if ( ! copy( (string) $file['tmp_name'], $dest ) ) {
				return new WP_Error( 'hvnly_csv_upload_move_failed', __( 'Could not store the uploaded CSV file.', 'havenlytics' ) );
			}
		} else {
			return new WP_Error( 'hvnly_csv_upload_missing', __( 'No CSV file uploaded.', 'havenlytics' ) );
		}

		$on_disk = filesize( $dest );

		return array(
			'path'     => $dest,
			'filename' => $name,
			'size'     => false !== $on_disk ? (int) $on_disk : $size,
		);
	}

	/**
	 * Parse headers + a preview sample from a stored CSV file.
	 *
	 * @param string $path Absolute file path.
	 * @param string $delimiter Optional forced delimiter.
	 * @param int    $sample_size Number of preview rows.
	 * @return array{headers:array<int,string>,sample_rows:array<int,array<string,string>>,total_rows:int,delimiter:string}|WP_Error
	 */
	public static function parse( string $path, string $delimiter = '', int $sample_size = 10 ) {
		$stream = CsvStream::open( $path, $delimiter );
		if ( ! $stream ) {
			return new WP_Error( 'hvnly_csv_parse_failed', __( 'Could not read the CSV file.', 'havenlytics' ) );
		}

		$headers = $stream->headers();
		if ( empty( $headers ) ) {
			return new WP_Error( 'hvnly_csv_no_headers', __( 'The CSV file has no header row.', 'havenlytics' ) );
		}

		return array(
			'headers'     => $headers,
			'sample_rows' => $stream->sample_rows( $sample_size ),
			'total_rows'  => $stream->count_rows(),
			'delimiter'   => $stream->delimiter(),
		);
	}

	/**
	 * Assert an absolute path is within the protected CSV base directory.
	 *
	 * @param string $absolute Absolute path.
	 * @return bool
	 */
	public static function is_under_base( string $absolute ): bool {
		$base = self::base_dir();
		if ( is_wp_error( $base ) ) {
			return false;
		}
		$base_cmp   = strtolower( rtrim( wp_normalize_path( $base ), '/' ) );
		$target_cmp = strtolower( wp_normalize_path( $absolute ) );
		return $target_cmp === $base_cmp || 0 === strpos( $target_cmp, $base_cmp . '/' );
	}

	/**
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function write_protection_files( string $dir ): void {
		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$htaccess,
				"Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
			);
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
