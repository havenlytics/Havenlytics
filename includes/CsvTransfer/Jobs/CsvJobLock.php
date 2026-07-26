<?php
/**
 * Site-wide one-job lock for CSV Transfer (import/export).
 *
 * @package HvnlyNab\CsvTransfer\Jobs
 * @since   3.7.2
 */

namespace HvnlyNab\CsvTransfer\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * CsvJobLock — prevents concurrent CSV Transfer jobs overlapping.
 *
 * Mirrors the HPTP Import/Export JobLock pattern with a TTL so abandoned
 * browser sessions cannot permanently block new imports/exports.
 *
 * @since 3.7.2
 */
final class CsvJobLock {

	public const OPTION_KEY = 'hvnly_csv_job_lock';

	/** Lock TTL seconds (stale lock recovery). */
	public const TTL_SECONDS = 3600;

	/**
	 * Whether a lock is currently held by an active job.
	 *
	 * @return bool
	 */
	public static function is_locked(): bool {
		$lock = self::read();
		if ( empty( $lock['job_id'] ) ) {
			return false;
		}
		if ( self::is_expired( $lock ) ) {
			self::release();
			return false;
		}
		return true;
	}

	/**
	 * Acquire lock for a job (or refresh if already owned by the same job_id).
	 *
	 * @param string $job_id Job ID.
	 * @param int    $user_id Owner user ID.
	 * @return bool True if acquired.
	 */
	public static function acquire( string $job_id, int $user_id ): bool {
		$job_id = (string) $job_id;
		if ( '' === $job_id ) {
			return false;
		}

		$lock = self::read();
		if ( ! empty( $lock['job_id'] ) && ! self::is_expired( $lock ) && $lock['job_id'] !== $job_id ) {
			return false;
		}

		$payload = array(
			'job_id'     => $job_id,
			'user_id'    => $user_id,
			'locked_at'  => time(),
			'expires_at' => time() + self::TTL_SECONDS,
		);

		update_option( self::OPTION_KEY, $payload, false );
		return true;
	}

	/**
	 * Refresh lock expiry for a running job.
	 *
	 * @param string $job_id Job ID.
	 * @return bool
	 */
	public static function heartbeat( string $job_id ): bool {
		$lock = self::read();
		if ( empty( $lock['job_id'] ) || $lock['job_id'] !== $job_id ) {
			return false;
		}
		$lock['expires_at'] = time() + self::TTL_SECONDS;
		update_option( self::OPTION_KEY, $lock, false );
		return true;
	}

	/**
	 * Release lock (optionally only if owned by job_id).
	 *
	 * @param string $job_id Optional job id gate.
	 * @return void
	 */
	public static function release( string $job_id = '' ): void {
		if ( '' !== $job_id ) {
			$lock = self::read();
			if ( ! empty( $lock['job_id'] ) && $lock['job_id'] !== $job_id ) {
				return;
			}
		}
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Current lock payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function read(): array {
		$lock = get_option( self::OPTION_KEY, array() );
		return is_array( $lock ) ? $lock : array();
	}

	/**
	 * @param array<string, mixed> $lock Lock.
	 * @return bool
	 */
	private static function is_expired( array $lock ): bool {
		$expires = isset( $lock['expires_at'] ) ? (int) $lock['expires_at'] : 0;
		return $expires > 0 && time() > $expires;
	}

	/**
	 * Release lock when TTL expired.
	 *
	 * @return bool True if a stale lock was cleared.
	 */
	public static function release_if_stale(): bool {
		$lock = self::read();
		if ( empty( $lock['job_id'] ) ) {
			return false;
		}
		if ( ! self::is_expired( $lock ) ) {
			return false;
		}
		self::release();
		return true;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
