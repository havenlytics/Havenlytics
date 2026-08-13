<?php
/**
 * Structured Import / Export job reports.
 *
 * @package HvnlyNab\ImportExport\Jobs
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * ReportBuilder — completion / partial / failure report payloads.
 *
 * @since 3.6.0
 */
final class ReportBuilder {

	/**
	 * Build a report from the current job state.
	 *
	 * @param array<string, mixed> $job Job.
	 * @return array<string, mixed>
	 */
	public static function from_job( array $job ): array {
		$status = (string) ( $job['status'] ?? '' );
		$type   = (string) ( $job['type'] ?? '' );

		return array(
			'job_id'       => (string) ( $job['id'] ?? '' ),
			'type'         => $type,
			'status'       => $status,
			'phase'        => (string) ( $job['phase'] ?? '' ),
			'outcome'      => self::outcome_label( $status ),
			'counts'       => isset( $job['counts'] ) && is_array( $job['counts'] ) ? $job['counts'] : array(),
			'progress'     => isset( $job['progress'] ) && is_array( $job['progress'] ) ? $job['progress'] : array(),
			'warnings'     => isset( $job['warnings'] ) && is_array( $job['warnings'] ) ? $job['warnings'] : array(),
			'errors'       => isset( $job['errors'] ) && is_array( $job['errors'] ) ? $job['errors'] : array(),
			'warning_count' => isset( $job['warnings'] ) && is_array( $job['warnings'] ) ? count( $job['warnings'] ) : 0,
			'error_count'  => isset( $job['errors'] ) && is_array( $job['errors'] ) ? count( $job['errors'] ) : 0,
			'builder'      => isset( $job['builder'] ) && is_array( $job['builder'] ) ? $job['builder'] : null,
			'media'        => isset( $job['media'] ) && is_array( $job['media'] ) ? $job['media'] : null,
			'download'     => self::download_meta( $job ),
			'timestamps'   => array(
				'created_at'   => (string) ( $job['created_at'] ?? '' ),
				'updated_at'   => (string) ( $job['updated_at'] ?? '' ),
				'completed_at' => (string) ( $job['completed_at'] ?? '' ),
			),
			'summary'      => self::summary_text( $job ),
		);
	}

	/**
	 * @param string $status Status.
	 * @return string
	 */
	private static function outcome_label( string $status ): string {
		switch ( $status ) {
			case JobStateStore::STATUS_COMPLETED:
				return 'completed';
			case JobStateStore::STATUS_CANCELLED:
				return 'cancelled_partial';
			case JobStateStore::STATUS_FAILED:
				return 'failed';
			default:
				return 'in_progress';
		}
	}

	/**
	 * @param array<string, mixed> $job Job.
	 * @return array<string, mixed>|null
	 */
	private static function download_meta( array $job ): ?array {
		if ( JobStateStore::TYPE_EXPORT !== ( $job['type'] ?? '' ) ) {
			return null;
		}
		if ( JobStateStore::STATUS_COMPLETED !== ( $job['status'] ?? '' ) ) {
			return null;
		}
		if ( empty( $job['zip_path'] ) || ! is_string( $job['zip_path'] ) ) {
			return null;
		}
		return array(
			'available' => true,
			'job_id'    => (string) ( $job['id'] ?? '' ),
			// Token is returned only to the owning capability-checked AJAX session.
			'token'     => (string) ( $job['download_token'] ?? '' ),
			'filename'  => ! empty( $job['zip_filename'] ) ? (string) $job['zip_filename'] : wp_basename( (string) $job['zip_path'] ),
		);
	}

	/**
	 * @param array<string, mixed> $job Job.
	 * @return string
	 */
	private static function summary_text( array $job ): string {
		$type   = (string) ( $job['type'] ?? 'job' );
		$status = (string) ( $job['status'] ?? '' );
		$counts = isset( $job['counts'] ) && is_array( $job['counts'] ) ? $job['counts'] : array();

		if ( JobStateStore::TYPE_EXPORT === $type ) {
			$props = isset( $counts['properties'] ) ? (int) $counts['properties'] : 0;
			return sprintf( 'Export %s — %d properties packaged.', $status, $props );
		}

		$created = isset( $counts['properties']['created'] ) ? (int) $counts['properties']['created'] : 0;
		$updated = isset( $counts['properties']['updated'] ) ? (int) $counts['properties']['updated'] : 0;
		$skipped = isset( $counts['properties']['skipped'] ) ? (int) $counts['properties']['skipped'] : 0;
		return sprintf(
			'Import %s — properties created:%d updated:%d skipped:%d.',
			$status,
			$created,
			$updated,
			$skipped
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
