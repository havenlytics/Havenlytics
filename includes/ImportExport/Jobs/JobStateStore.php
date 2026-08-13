<?php
/**
 * Options-backed durable job state for HPTP Import / Export.
 *
 * @package HvnlyNab\ImportExport\Jobs
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Jobs;

use HvnlyNab\ImportExport\Contracts\JobStoreInterface;
use HvnlyNab\ImportExport\Package\ManifestSchema;

defined( 'ABSPATH' ) || exit;

/**
 * JobStateStore — single active job blob in {@see ManifestSchema::OPTION_JOB_STATE}.
 *
 * @since 3.6.0
 */
final class JobStateStore implements JobStoreInterface {

	public const STATUS_QUEUED    = 'queued';
	public const STATUS_RUNNING   = 'running';
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_CANCELLED = 'cancelled';

	public const TYPE_EXPORT = 'export';
	public const TYPE_IMPORT = 'import';

	/**
	 * {@inheritdoc}
	 */
	public function get_job(): ?array {
		$job = get_option( ManifestSchema::OPTION_JOB_STATE, null );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function save_job( array $job ): void {
		$job['updated_at'] = gmdate( 'c' );
		update_option( ManifestSchema::OPTION_JOB_STATE, $job, false );
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear_job(): void {
		delete_option( ManifestSchema::OPTION_JOB_STATE );
	}

	/**
	 * Create a new job skeleton.
	 *
	 * @param string               $type export|import.
	 * @param array<string, mixed> $options Job options.
	 * @param int                  $user_id Owner.
	 * @return array<string, mixed>
	 */
	public static function new_job( string $type, array $options, int $user_id ): array {
		$now = gmdate( 'c' );
		return array(
			'id'             => 'ie_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 8, false, false ),
			'type'           => $type,
			'status'         => self::STATUS_QUEUED,
			'phase'          => 'prepare',
			'cursor'         => array(
				'index' => 0,
				'total' => 0,
			),
			'options'        => $options,
			'counts'         => array(),
			'warnings'       => array(),
			'errors'         => array(),
			'workdir'        => '',
			'zip_path'       => '',
			'upload_path'    => '',
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
	 * Append a warning (capped).
	 *
	 * @param array                $job Job.
	 * @param array<string, mixed> $warning Warning row.
	 * @return array
	 */
	public static function push_warning( array $job, array $warning ): array {
		if ( ! isset( $job['warnings'] ) || ! is_array( $job['warnings'] ) ) {
			$job['warnings'] = array();
		}
		$job['warnings'][] = $warning;
		if ( count( $job['warnings'] ) > 200 ) {
			$job['warnings'] = array_slice( $job['warnings'], -200 );
		}
		return $job;
	}

	/**
	 * Append an error (capped).
	 *
	 * @param array                $job Job.
	 * @param array<string, mixed> $error Error row.
	 * @return array
	 */
	public static function push_error( array $job, array $error ): array {
		if ( ! isset( $job['errors'] ) || ! is_array( $job['errors'] ) ) {
			$job['errors'] = array();
		}
		$job['errors'][] = $error;
		if ( count( $job['errors'] ) > 100 ) {
			$job['errors'] = array_slice( $job['errors'], -100 );
		}
		return $job;
	}

	/**
	 * Merge warning lists from a PackageResult-like array.
	 *
	 * @param array $job Job.
	 * @param array $warnings Warnings.
	 * @return array
	 */
	public static function merge_warnings( array $job, array $warnings ): array {
		foreach ( $warnings as $warning ) {
			if ( is_array( $warning ) ) {
				$job = self::push_warning( $job, $warning );
			}
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
		return array(
			'id'           => (string) ( $job['id'] ?? '' ),
			'type'         => (string) ( $job['type'] ?? '' ),
			'status'       => (string) ( $job['status'] ?? '' ),
			'phase'        => (string) ( $job['phase'] ?? '' ),
			'cursor'       => isset( $job['cursor'] ) && is_array( $job['cursor'] ) ? $job['cursor'] : array(),
			'counts'       => isset( $job['counts'] ) && is_array( $job['counts'] ) ? $job['counts'] : array(),
			'progress'     => isset( $job['progress'] ) && is_array( $job['progress'] ) ? $job['progress'] : array(),
			'warnings'     => isset( $job['warnings'] ) && is_array( $job['warnings'] ) ? $job['warnings'] : array(),
			'errors'       => isset( $job['errors'] ) && is_array( $job['errors'] ) ? $job['errors'] : array(),
			'created_at'   => (string) ( $job['created_at'] ?? '' ),
			'updated_at'   => (string) ( $job['updated_at'] ?? '' ),
			'completed_at' => (string) ( $job['completed_at'] ?? '' ),
			'has_download' => ( self::TYPE_EXPORT === ( $job['type'] ?? '' )
				&& self::STATUS_COMPLETED === ( $job['status'] ?? '' )
				&& ! empty( $job['zip_path'] ) ),
			'report'       => $job['report'] ?? null,
		);
	}
}
