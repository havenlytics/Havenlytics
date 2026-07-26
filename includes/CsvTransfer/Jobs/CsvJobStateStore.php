<?php
/**
 * Options-backed durable job state for CSV Transfer (import/export).
 *
 * @package HvnlyNab\CsvTransfer\Jobs
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * CsvJobStateStore — single active job blob in `hvnly_csv_job_state`.
 *
 * Static-only store (same pattern as MappingProfileStore / other CsvTransfer
 * services). Persistence helpers: get_job / save_job / clear_job. Job shape
 * helpers: new_job / push_warning / push_error / public_view.
 *
 * @since 3.7.0
 */
final class CsvJobStateStore {

	public const OPTION_KEY = 'hvnly_csv_job_state';

	public const STATUS_QUEUED    = 'queued';
	public const STATUS_RUNNING   = 'running';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_CANCELLED = 'cancelled';

	public const TYPE_IMPORT = 'csv_import';
	public const TYPE_EXPORT = 'csv_export';

	/** Default seconds without heartbeat before a queued/running job is auto-failed. */
	public const STALE_TTL_SECONDS = 3600;

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_job(): ?array {
		$job = get_option( self::OPTION_KEY, null );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * Whether a job status is terminal.
	 *
	 * @param array<string, mixed> $job Job blob.
	 * @return bool
	 */
	public static function is_terminal( array $job ): bool {
		return in_array(
			$job['status'] ?? '',
			array( self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED ),
			true
		);
	}

	/**
	 * Whether a queued/running job has gone stale (no update within TTL).
	 *
	 * @param array<string, mixed> $job Job blob.
	 * @return bool
	 */
	public static function is_stale( array $job ): bool {
		$status = (string) ( $job['status'] ?? '' );
		if ( ! in_array( $status, array( self::STATUS_QUEUED, self::STATUS_RUNNING ), true ) ) {
			return false;
		}

		$updated = (string) ( $job['updated_at'] ?? '' );
		if ( '' === $updated ) {
			$updated = (string) ( $job['created_at'] ?? '' );
		}
		$ts = strtotime( $updated );
		if ( false === $ts ) {
			// Unparseable timestamp — treat as stale so it cannot block forever.
			return true;
		}

		$ttl = (int) apply_filters( 'hvnly_csv_job_stale_ttl', self::STALE_TTL_SECONDS );
		$ttl = max( 300, $ttl ); // Never shorter than 5 minutes.

		return ( time() - $ts ) > $ttl;
	}

	/**
	 * @param array<string, mixed> $job Job.
	 * @return void
	 */
	public static function save_job( array $job ): void {
		$job['updated_at'] = gmdate( 'c' );
		update_option( self::OPTION_KEY, $job, false );
	}

	/**
	 * Persist a job only if a concurrent cancel/terminal did not win the race.
	 *
	 * If the stored job for the same id is already cancelled/failed/completed,
	 * a non-terminal batch write is rejected so cancel cannot be overwritten.
	 *
	 * @param array<string, mixed> $job Candidate job state to persist.
	 * @return array<string, mixed> The job that is actually stored (candidate or winner).
	 */
	public static function save_job_unless_overtaken( array $job ): array {
		$current = self::get_job();
		$job_id  = (string) ( $job['id'] ?? '' );

		if (
			$current
			&& $job_id === (string) ( $current['id'] ?? '' )
			&& self::is_terminal( $current )
			&& ! self::is_terminal( $job )
		) {
			// Cancel (or other terminal) won — keep stored terminal state.
			return $current;
		}

		if (
			$current
			&& $job_id === (string) ( $current['id'] ?? '' )
			&& self::STATUS_CANCELLED === ( $current['status'] ?? '' )
			&& self::STATUS_CANCELLED !== ( $job['status'] ?? '' )
		) {
			return $current;
		}

		self::save_job( $job );
		return $job;
	}

	/**
	 * @return void
	 */
	public static function clear_job(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * @param string               $type csv_import|csv_export.
	 * @param array<string, mixed> $options Job options.
	 * @param int                  $user_id Owner.
	 * @return array<string, mixed>
	 */
	public static function new_job( string $type, array $options, int $user_id ): array {
		$now = gmdate( 'c' );
		return array(
			'id'             => 'csv_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 8, false, false ),
			'type'           => $type,
			'status'         => self::STATUS_QUEUED,
			'phase'          => 'prepare',
			'cursor'         => array(
				'index' => 0,
				'total' => 0,
			),
			'options'        => $options,
			'counts'         => array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'failed'  => 0,
			),
			'warnings'       => array(),
			'errors'         => array(),
			'csv_path'       => '',
			'csv_filename'   => '',
			'download_token' => wp_generate_password( 32, false, false ),
			'report'         => null,
			'progress'       => array(
				'percent' => 0,
				'message' => '',
			),
			'owner_user_id'  => $user_id,
			'created_at'     => $now,
			'updated_at'     => $now,
			'completed_at'   => '',
		);
	}

	/**
	 * @param array                $job Job.
	 * @param array<string, mixed> $warning Warning row.
	 * @return array
	 */
	public static function push_warning( array $job, array $warning ): array {
		if ( ! isset( $job['warnings'] ) || ! is_array( $job['warnings'] ) ) {
			$job['warnings'] = array();
		}
		$job['warnings'][] = $warning;
		if ( count( $job['warnings'] ) > 300 ) {
			$job['warnings'] = array_slice( $job['warnings'], -300 );
		}
		return $job;
	}

	/**
	 * @param array                $job Job.
	 * @param array<string, mixed> $error Error row.
	 * @return array
	 */
	public static function push_error( array $job, array $error ): array {
		if ( ! isset( $job['errors'] ) || ! is_array( $job['errors'] ) ) {
			$job['errors'] = array();
		}
		$job['errors'][] = $error;
		if ( count( $job['errors'] ) > 150 ) {
			$job['errors'] = array_slice( $job['errors'], -150 );
		}
		return $job;
	}

	/**
	 * Public progress view (safe for AJAX clients).
	 *
	 * @param array $job Job.
	 * @return array<string, mixed>
	 */
	public static function public_view( array $job ): array {
		$has_download = ( self::TYPE_EXPORT === ( $job['type'] ?? '' )
			&& self::STATUS_COMPLETED === ( $job['status'] ?? '' )
			&& ! empty( $job['csv_path'] ) );

		return array(
			'id'           => (string) ( $job['id'] ?? '' ),
			'type'         => (string) ( $job['type'] ?? '' ),
			'status'       => (string) ( $job['status'] ?? '' ),
			'phase'        => (string) ( $job['phase'] ?? '' ),
			'cursor'       => isset( $job['cursor'] ) && is_array( $job['cursor'] ) ? $job['cursor'] : array(),
			'counts'       => isset( $job['counts'] ) && is_array( $job['counts'] ) ? $job['counts'] : array(),
			'progress'     => isset( $job['progress'] ) && is_array( $job['progress'] ) ? $job['progress'] : array(),
			'warnings'     => isset( $job['warnings'] ) && is_array( $job['warnings'] ) ? array_slice( $job['warnings'], -50 ) : array(),
			'errors'       => isset( $job['errors'] ) && is_array( $job['errors'] ) ? array_slice( $job['errors'], -50 ) : array(),
			'created_at'   => (string) ( $job['created_at'] ?? '' ),
			'updated_at'   => (string) ( $job['updated_at'] ?? '' ),
			'completed_at' => (string) ( $job['completed_at'] ?? '' ),
			'has_download' => $has_download,
			'csv_filename' => (string) ( $job['csv_filename'] ?? '' ),
			// Only surfaced once the export is complete; guarded again by owner + nonce checks on download.
			'download'     => $has_download
				? array(
					'job_id' => (string) ( $job['id'] ?? '' ),
					'token'  => (string) ( $job['download_token'] ?? '' ),
				)
				: null,
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
