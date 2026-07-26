<?php
/**
 * Safe workspace / temp cleanup for HPTP jobs.
 *
 * @package HvnlyNab\ImportExport\Jobs
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Jobs;

use HvnlyNab\ImportExport\Package\ManifestSchema;
use HvnlyNab\ImportExport\Package\TempStorage;

defined( 'ABSPATH' ) || exit;

/**
 * JobCleanup — terminal-job and retention housekeeping (no new AJAX).
 *
 * @since 3.6.0
 */
final class JobCleanup {

	public const CRON_HOOK = 'hvnly_ie_temp_cleanup';

	/**
	 * Transient key used to throttle opportunistic maintenance.
	 *
	 * @var string
	 */
	private const MAINTENANCE_TRANSIENT = 'hvnly_ie_maint_ran';

	/**
	 * Register WP-Cron for expired temp directories.
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
		TempStorage::cleanup_expired();
		self::prune_stale_terminal_job();
		JobLock::release_if_stale();
	}

	/**
	 * Throttled maintenance from existing AJAX (job_status).
	 *
	 * @return void
	 */
	public static function maybe_run_maintenance(): void {
		if ( get_transient( self::MAINTENANCE_TRANSIENT ) ) {
			return;
		}
		set_transient( self::MAINTENANCE_TRANSIENT, 1, 15 * MINUTE_IN_SECONDS );
		TempStorage::cleanup_expired();
		self::prune_stale_terminal_job();
		JobLock::release_if_stale();
	}

	/**
	 * After a job reaches a terminal status, remove non-essential workdirs.
	 *
	 * Completed exports keep the ZIP (and its package workdir) for download
	 * until retention prune. Import / failed / cancelled jobs delete workdirs.
	 *
	 * @param array<string, mixed> $job Job.
	 * @return array<string, mixed>
	 */
	public static function after_terminal( array $job ): array {
		$status = (string) ( $job['status'] ?? '' );
		$type   = (string) ( $job['type'] ?? '' );

		if ( ! in_array( $status, array( JobStateStore::STATUS_COMPLETED, JobStateStore::STATUS_FAILED, JobStateStore::STATUS_CANCELLED ), true ) ) {
			return $job;
		}

		$keep_export_zip = (
			JobStateStore::TYPE_EXPORT === $type
			&& JobStateStore::STATUS_COMPLETED === $status
			&& ! empty( $job['zip_path'] )
		);

		// Drop ephemeral scratch workdir (entities JSON, encoder state, import maps).
		if ( ! empty( $job['workdir'] ) && is_string( $job['workdir'] ) ) {
			$workdir = wp_normalize_path( (string) $job['workdir'] );
			$zip_dir = ( ! empty( $job['zip_path'] ) && is_string( $job['zip_path'] ) )
				? wp_normalize_path( dirname( (string) $job['zip_path'] ) )
				: '';

			// Never delete the directory that still holds a retained download ZIP.
			if ( ! ( $keep_export_zip && '' !== $zip_dir && $workdir === $zip_dir ) ) {
				TempStorage::delete_workdir( $workdir );
				$job['workdir'] = '';
			}
		}

		if ( ! empty( $job['upload_workdir'] ) && is_string( $job['upload_workdir'] ) ) {
			TempStorage::delete_workdir( (string) $job['upload_workdir'] );
			$job['upload_workdir'] = '';
		}

		if ( $keep_export_zip ) {
			return $job;
		}

		if ( ! empty( $job['package_workdir'] ) && is_string( $job['package_workdir'] ) ) {
			TempStorage::delete_workdir( (string) $job['package_workdir'] );
			$job['package_workdir'] = '';
		}

		if ( ! empty( $job['zip_path'] ) && is_string( $job['zip_path'] ) ) {
			TempStorage::delete_workdir( wp_normalize_path( dirname( (string) $job['zip_path'] ) ) );
			$job['zip_path']      = '';
			$job['zip_filename'] = '';
		}

		if ( ! empty( $job['upload_path'] ) && is_string( $job['upload_path'] ) ) {
			$upload_dir = wp_normalize_path( dirname( (string) $job['upload_path'] ) );
			TempStorage::delete_workdir( $upload_dir );
			$job['upload_path'] = '';
		}

		return $job;
	}

	/**
	 * Clear job option when a terminal job is older than retention.
	 *
	 * @return void
	 */
	public static function prune_stale_terminal_job(): void {
		$store = new JobStateStore();
		$job   = $store->get_job();
		if ( ! $job ) {
			return;
		}

		$status = (string) ( $job['status'] ?? '' );
		if ( ! in_array( $status, array( JobStateStore::STATUS_COMPLETED, JobStateStore::STATUS_FAILED, JobStateStore::STATUS_CANCELLED ), true ) ) {
			return;
		}

		$completed = (string) ( $job['completed_at'] ?? $job['updated_at'] ?? '' );
		$ts        = $completed ? strtotime( $completed ) : false;
		if ( false === $ts ) {
			return;
		}

		if ( ( time() - $ts ) < ManifestSchema::TEMP_RETENTION_SECONDS ) {
			return;
		}

		// Force-remove retained export ZIPs after retention window.
		if ( ! empty( $job['zip_path'] ) && is_string( $job['zip_path'] ) ) {
			TempStorage::delete_workdir( wp_normalize_path( dirname( (string) $job['zip_path'] ) ) );
		}
		if ( ! empty( $job['package_workdir'] ) && is_string( $job['package_workdir'] ) ) {
			TempStorage::delete_workdir( (string) $job['package_workdir'] );
		}
		if ( ! empty( $job['workdir'] ) && is_string( $job['workdir'] ) ) {
			TempStorage::delete_workdir( (string) $job['workdir'] );
		}
		if ( ! empty( $job['upload_workdir'] ) && is_string( $job['upload_workdir'] ) ) {
			TempStorage::delete_workdir( (string) $job['upload_workdir'] );
		}

		$store->clear_job();
		JobLock::release( (string) ( $job['id'] ?? '' ) );
	}

	/**
	 * Delete an upload session transient (and optionally its workdir).
	 *
	 * @param string               $token   Upload token.
	 * @param array<string, mixed> $session Session payload; set delete_workdir=true to remove disk.
	 * @return void
	 */
	public static function dispose_upload_session( string $token, array $session = array() ): void {
		if ( '' !== $token ) {
			delete_transient( 'hvnly_ie_upload_' . $token );
		}

		if ( ! empty( $session['delete_workdir'] ) && ! empty( $session['workdir'] ) && is_string( $session['workdir'] ) ) {
			TempStorage::delete_workdir( (string) $session['workdir'] );
		}
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
