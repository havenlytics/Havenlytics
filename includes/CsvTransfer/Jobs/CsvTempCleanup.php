<?php
/**
 * Temporary file cleanup for CSV Transfer uploads/exports.
 *
 * @package HvnlyNab\CsvTransfer\Jobs
 * @since   3.7.1
 */

namespace HvnlyNab\CsvTransfer\Jobs;

use HvnlyNab\CsvTransfer\Import\CsvParser;
use HvnlyNab\CsvTransfer\Import\RemoteMediaFetcher;

defined( 'ABSPATH' ) || exit;

/**
 * CsvTempCleanup — terminal-job and retention housekeeping for havenlytics-csv/.
 *
 * Only touches files under {@see CsvParser::UPLOAD_DIR_NAME}. Never deletes
 * Media Library attachments or unrelated uploads.
 *
 * @since 3.7.1
 */
final class CsvTempCleanup {

	public const CRON_HOOK = 'hvnly_csv_temp_cleanup';

	/** Default retention for abandoned CSV temp files (12 hours). */
	public const RETENTION_SECONDS = 43200;

	private const MAINTENANCE_TRANSIENT = 'hvnly_csv_maint_ran';

	/**
	 * Register WP-Cron for expired CSV temp files.
	 *
	 * @return void
	 */
	public static function register_cron(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_cleanup' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
		}
	}

	/**
	 * Cron callback.
	 *
	 * @return void
	 */
	public static function run_scheduled_cleanup(): void {
		self::cleanup_expired();
	}

	/**
	 * Opportunistic maintenance (throttled).
	 *
	 * @return void
	 */
	public static function maybe_run_maintenance(): void {
		if ( get_transient( self::MAINTENANCE_TRANSIENT ) ) {
			return;
		}
		set_transient( self::MAINTENANCE_TRANSIENT, 1, HOUR_IN_SECONDS );
		self::cleanup_expired();
	}

	/**
	 * After a job reaches a terminal status, remove non-essential temp files.
	 *
	 * Completed exports keep the CSV for download until retention prune or
	 * explicit post-download cleanup. Import / failed / cancelled jobs delete
	 * their source/export files immediately.
	 *
	 * @param array<string, mixed> $job Job blob.
	 * @return array<string, mixed> Job with cleared path fields where deleted.
	 */
	public static function after_terminal( array $job ): array {
		$status = (string) ( $job['status'] ?? '' );
		$type   = (string) ( $job['type'] ?? '' );

		$keep_export_download = (
			CsvJobStateStore::TYPE_EXPORT === $type
			&& CsvJobStateStore::STATUS_COMPLETED === $status
		);

		if ( ! $keep_export_download ) {
			$job = self::delete_job_files( $job );
		}

		if ( CsvJobStateStore::TYPE_IMPORT === $type ) {
			$job_id = (string) ( $job['id'] ?? '' );
			if ( '' !== $job_id ) {
				// Completed leftovers, cancelled, or failed — never leave queues for a later import.
				RemoteMediaFetcher::discard_job_queue( $job_id );
			}
		}

		return $job;
	}

	/**
	 * Delete CSV paths referenced by a job (import source and/or export output).
	 *
	 * @param array<string, mixed> $job Job blob.
	 * @return array<string, mixed>
	 */
	public static function delete_job_files( array $job ): array {
		$paths = array();

		if ( ! empty( $job['csv_path'] ) && is_string( $job['csv_path'] ) ) {
			$paths[]         = (string) $job['csv_path'];
			$job['csv_path'] = '';
		}

		if ( isset( $job['options'] ) && is_array( $job['options'] ) && ! empty( $job['options']['csv_path'] ) ) {
			$paths[]                    = (string) $job['options']['csv_path'];
			$job['options']['csv_path'] = '';
		}

		foreach ( array_unique( $paths ) as $path ) {
			self::delete_managed_file( $path );
		}

		return $job;
	}

	/**
	 * Delete a single file if it lives under the Havenlytics CSV temp directory.
	 *
	 * @param string $path Absolute path.
	 * @return bool True when deleted or already absent.
	 */
	public static function delete_managed_file( string $path ): bool {
		$path = wp_normalize_path( $path );
		if ( '' === $path || ! CsvParser::is_under_base( $path ) ) {
			return false;
		}

		// Never delete directory protection files.
		$base = wp_basename( $path );
		if ( in_array( $base, array( 'index.php', '.htaccess' ), true ) ) {
			return false;
		}

		if ( is_dir( $path ) ) {
			return false;
		}

		if ( ! file_exists( $path ) ) {
			return true;
		}

		return @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
	}

	/**
	 * Remove abandoned CSV temp files older than the retention window.
	 *
	 * Skips files still referenced by the active / downloadable job.
	 *
	 * @return int Number of files removed.
	 */
	public static function cleanup_expired(): int {
		$base = CsvParser::base_dir();
		if ( is_wp_error( $base ) || ! is_dir( $base ) ) {
			return 0;
		}

		/**
		 * Filter CSV temp file retention in seconds.
		 *
		 * @since 3.7.1
		 *
		 * @param int $seconds Retention window.
		 */
		$retention = (int) apply_filters( 'hvnly_csv_temp_retention_seconds', self::RETENTION_SECONDS );
		if ( $retention < HOUR_IN_SECONDS ) {
			$retention = HOUR_IN_SECONDS;
		}

		$cutoff    = time() - $retention;
		$protected = self::protected_paths();
		$removed   = 0;
		$entries   = scandir( $base );

		if ( ! is_array( $entries ) ) {
			return 0;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry || 'index.php' === $entry || '.htaccess' === $entry ) {
				continue;
			}

			$path = wp_normalize_path( trailingslashit( $base ) . $entry );
			if ( ! is_file( $path ) ) {
				continue;
			}

			if ( in_array( $path, $protected, true ) ) {
				continue;
			}

			$mtime = filemtime( $path );
			if ( false === $mtime || $mtime >= $cutoff ) {
				continue;
			}

			if ( self::delete_managed_file( $path ) ) {
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * Absolute paths that must not be pruned while a job still needs them.
	 *
	 * @return array<int, string>
	 */
	private static function protected_paths(): array {
		$job = CsvJobStateStore::get_job();
		if ( ! is_array( $job ) ) {
			return array();
		}

		$status = (string) ( $job['status'] ?? '' );
		$active = in_array(
			$status,
			array(
				CsvJobStateStore::STATUS_QUEUED,
				CsvJobStateStore::STATUS_RUNNING,
			),
			true
		);

		if ( ! $active ) {
			return array();
		}

		$paths = array();
		if ( ! empty( $job['csv_path'] ) && is_string( $job['csv_path'] ) ) {
			$paths[] = wp_normalize_path( (string) $job['csv_path'] );
		}
		if ( isset( $job['options']['csv_path'] ) && is_string( $job['options']['csv_path'] ) && '' !== $job['options']['csv_path'] ) {
			$paths[] = wp_normalize_path( (string) $job['options']['csv_path'] );
		}

		return array_values( array_unique( array_filter( $paths ) ) );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
