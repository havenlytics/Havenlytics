<?php
/**
 * Phased CSV export runner for AJAX job batches.
 *
 * @package HvnlyNab\CsvTransfer\Jobs
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Jobs;

use HvnlyNab\CsvTransfer\Export\CsvExporter;
use HvnlyNab\CsvTransfer\Import\CsvParser;

defined( 'ABSPATH' ) || exit;

/**
 * CsvExportBatchRunner — one phase/slice per AJAX tick.
 *
 * @since 3.7.0
 */
final class CsvExportBatchRunner {

	public const BATCH_ROWS = 100;

	/**
	 * @param array<string, mixed> $job Job.
	 * @return array<string, mixed>
	 */
	public static function tick( array $job ): array {
		$job['status'] = CsvJobStateStore::STATUS_RUNNING;
		$phase         = (string) ( $job['phase'] ?? 'prepare' );

		switch ( $phase ) {
			case 'prepare':
				return self::phase_prepare( $job );
			case 'rows':
				return self::phase_rows( $job );
			case 'finalize':
				return self::phase_finalize( $job );
			default:
				$job['status'] = CsvJobStateStore::STATUS_FAILED;
				return CsvJobStateStore::push_error(
					$job,
					array(
						'code'    => 'hvnly_csv_export_phase_unknown',
						'message' => 'Unknown export phase.',
						'context' => array( 'phase' => $phase ),
					)
				);
		}
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_prepare( array $job ): array {
		$options = isset( $job['options'] ) && is_array( $job['options'] ) ? $job['options'] : array();

		$dir = CsvParser::base_dir();
		if ( is_wp_error( $dir ) ) {
			$job['status']       = CsvJobStateStore::STATUS_FAILED;
			$job['completed_at'] = gmdate( 'c' );
			return CsvJobStateStore::push_error(
				$job,
				array(
					'code'    => 'hvnly_csv_export_dir_failed',
					'message' => $dir->get_error_message(),
					'context' => array(),
				)
			);
		}

		$filename = 'havenlytics-export-' . gmdate( 'Y-m-d-His' ) . '.csv';
		$token    = strtolower( wp_generate_password( 12, false, false ) );
		$path     = trailingslashit( $dir ) . $token . '-' . $filename;

		$job['csv_path']     = $path;
		$job['csv_filename'] = $filename;

		$filters = isset( $options['filters'] ) && is_array( $options['filters'] ) ? $options['filters'] : array();
		$total   = CsvExporter::count( $filters );

		$job['cursor']   = array(
			'index' => 0,
			'total' => $total,
		);
		$job['phase']    = 'rows';
		$job['progress'] = array(
			'percent' => 5,
			'message' => __( 'Export prepared.', 'havenlytics' ),
		);
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_rows( array $job ): array {
		$options = isset( $job['options'] ) && is_array( $job['options'] ) ? $job['options'] : array();
		$columns = isset( $options['columns'] ) && is_array( $options['columns'] ) ? $options['columns'] : array();
		$filters = isset( $options['filters'] ) && is_array( $options['filters'] ) ? $options['filters'] : array();
		$index   = isset( $job['cursor']['index'] ) ? (int) $job['cursor']['index'] : 0;
		$total   = isset( $job['cursor']['total'] ) ? (int) $job['cursor']['total'] : 0;

		$result = CsvExporter::write_batch(
			(string) $job['csv_path'],
			$columns,
			$filters,
			$index,
			self::BATCH_ROWS,
			0 === $index
		);

		$next_index                = $index + (int) $result['written'];
		$job['cursor']['index']    = $next_index;
		$job['counts']['exported'] = $next_index;

		$pct             = $total > 0 ? 10 + (int) floor( 80 * min( 1, $next_index / $total ) ) : 90;
		$job['progress'] = array(
			'percent' => $pct,
			'message' => sprintf(
				/* translators: 1: rows exported, 2: total rows */
				__( 'Exported %1$d / %2$d properties.', 'havenlytics' ),
				$next_index,
				$total
			),
		);

		if ( ! empty( $result['done'] ) || $next_index >= $total ) {
			$job['phase'] = 'finalize';
		}

		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_finalize( array $job ): array {
		$job['status']       = CsvJobStateStore::STATUS_COMPLETED;
		$job['phase']        = 'completed';
		$job['progress']     = array(
			'percent' => 100,
			'message' => __( 'Export complete.', 'havenlytics' ),
		);
		$job['completed_at'] = gmdate( 'c' );
		return $job;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
