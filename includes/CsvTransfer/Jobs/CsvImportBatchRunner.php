<?php
/**
 * Phased CSV import runner for AJAX job batches.
 *
 * @package HvnlyNab\CsvTransfer\Jobs
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Jobs;

use HvnlyNab\CsvTransfer\Import\RemoteMediaFetcher;
use HvnlyNab\CsvTransfer\Import\RowImporter;
use HvnlyNab\CsvTransfer\Import\RowValidator;
use HvnlyNab\CsvTransfer\Support\CsvStream;

defined( 'ABSPATH' ) || exit;

/**
 * CsvImportBatchRunner — one phase/slice per AJAX tick.
 *
 * @since 3.7.0
 */
final class CsvImportBatchRunner {

	public const BATCH_ROWS  = 25;
	public const BATCH_MEDIA = 10;

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
			case 'media':
				return self::phase_media( $job );
			case 'finalize':
				return self::phase_finalize( $job );
			default:
				$job['status'] = CsvJobStateStore::STATUS_FAILED;
				return CsvJobStateStore::push_error(
					$job,
					array(
						'code'    => 'hvnly_csv_import_phase_unknown',
						'message' => 'Unknown import phase.',
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
		$path    = (string) ( $options['csv_path'] ?? '' );
		$stream  = '' !== $path ? CsvStream::open( $path, (string) ( $options['delimiter'] ?? '' ) ) : null;

		if ( ! $stream ) {
			$job['status'] = CsvJobStateStore::STATUS_FAILED;
			$job['completed_at'] = gmdate( 'c' );
			return CsvJobStateStore::push_error(
				$job,
				array(
					'code'    => 'hvnly_csv_import_file_missing',
					'message' => __( 'CSV file could not be opened for import.', 'havenlytics' ),
					'context' => array(),
				)
			);
		}

		$total          = $stream->count_rows();
		$job['cursor']  = array( 'index' => 0, 'total' => $total );
		$job['phase']   = $total > 0 ? 'rows' : ( ! empty( $options['fetch_media'] ) ? 'media' : 'finalize' );
		$job['progress'] = array( 'percent' => 5, 'message' => __( 'Import prepared.', 'havenlytics' ) );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_rows( array $job ): array {
		$options  = isset( $job['options'] ) && is_array( $job['options'] ) ? $job['options'] : array();
		$mapping  = isset( $options['mapping'] ) && is_array( $options['mapping'] ) ? $options['mapping'] : array();
		$policy   = (string) ( $options['duplicate_policy'] ?? 'skip' );
		$path     = (string) ( $options['csv_path'] ?? '' );
		$index    = isset( $job['cursor']['index'] ) ? (int) $job['cursor']['index'] : 0;
		$total    = isset( $job['cursor']['total'] ) ? (int) $job['cursor']['total'] : 0;

		$stream = CsvStream::open( $path, (string) ( $options['delimiter'] ?? '' ) );
		if ( ! $stream ) {
			$job['status'] = CsvJobStateStore::STATUS_FAILED;
			$job['completed_at'] = gmdate( 'c' );
			return CsvJobStateStore::push_error(
				$job,
				array(
					'code'    => 'hvnly_csv_import_file_missing',
					'message' => __( 'CSV file could not be opened for import.', 'havenlytics' ),
					'context' => array(),
				)
			);
		}

		$rows = $stream->read_rows( $index, self::BATCH_ROWS );

		foreach ( $rows as $i => $row ) {
			$row_number = $index + $i + 1;
			$validated  = RowValidator::validate( $row, $mapping, $row_number );

			if ( ! $validated['valid'] ) {
				$job['counts']['failed'] = (int) ( $job['counts']['failed'] ?? 0 ) + 1;
				foreach ( $validated['errors'] as $error ) {
					$job = CsvJobStateStore::push_error(
						$job,
						array(
							'code'    => 'hvnly_csv_row_invalid',
							'message' => $error,
							'context' => array( 'row' => $row_number ),
						)
					);
				}
				continue;
			}

			$result = RowImporter::import_row(
				$validated['fields'],
				$policy,
				(string) ( $job['id'] ?? '' ),
				array(
					'gallery_as_featured' => ! empty( $options['gallery_as_featured'] ),
				)
			);
			$status = (string) $result['status'];
			if ( isset( $job['counts'][ $status ] ) ) {
				$job['counts'][ $status ] = (int) $job['counts'][ $status ] + 1;
			}

			foreach ( array_merge( $validated['warnings'], $result['warnings'] ) as $warning ) {
				$job = CsvJobStateStore::push_warning(
					$job,
					array(
						'code'    => 'hvnly_csv_row_warning',
						'message' => $warning,
						'context' => array( 'row' => $row_number, 'post_id' => $result['post_id'] ),
					)
				);
			}
		}

		$next_index = $index + count( $rows );
		$job['cursor']['index'] = $next_index;

		$pct = $total > 0 ? 10 + (int) floor( 70 * min( 1, $next_index / $total ) ) : 80;
		$job['progress'] = array(
			'percent' => $pct,
			'message' => sprintf(
				/* translators: 1: rows processed, 2: total rows */
				__( 'Imported rows %1$d / %2$d.', 'havenlytics' ),
				$next_index,
				$total
			),
		);

		if ( $next_index >= $total || empty( $rows ) ) {
			$job['phase']  = ! empty( $options['fetch_media'] ) ? 'media' : 'finalize';
			$job['cursor'] = array( 'index' => 0, 'total' => 0 );
		}

		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_media( array $job ): array {
		$job_id   = (string) ( $job['id'] ?? '' );
		$post_ids = RemoteMediaFetcher::find_pending_post_ids( 25, $job_id );
		if ( empty( $post_ids ) ) {
			$job['phase']    = 'finalize';
			$job['progress'] = array( 'percent' => 95, 'message' => __( 'No media to fetch.', 'havenlytics' ) );
			return $job;
		}

		$budget   = self::BATCH_MEDIA;
		$consumed = 0;
		foreach ( $post_ids as $post_id ) {
			if ( $budget <= 0 ) {
				break;
			}
			$result    = RemoteMediaFetcher::process_post( $post_id, $budget, $job_id );
			$consumed += (int) $result['consumed'];
			$budget   -= (int) $result['consumed'];
			foreach ( $result['warnings'] as $warning ) {
				$job = CsvJobStateStore::push_warning(
					$job,
					array(
						'code'    => 'hvnly_csv_media_warning',
						'message' => $warning,
						'context' => array( 'post_id' => $post_id ),
					)
				);
			}
		}

		$job['counts']['media_fetched'] = (int) ( $job['counts']['media_fetched'] ?? 0 ) + $consumed;
		$remaining = RemoteMediaFetcher::find_pending_post_ids( 1, $job_id );
		$job['phase'] = empty( $remaining ) ? 'finalize' : 'media';
		$job['progress'] = array(
			'percent' => empty( $remaining ) ? 95 : 90,
			'message' => __( 'Fetching remote media…', 'havenlytics' ),
		);

		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_finalize( array $job ): array {
		$job['status']       = CsvJobStateStore::STATUS_COMPLETED;
		$job['phase']        = 'completed';
		$job['progress']     = array( 'percent' => 100, 'message' => __( 'Import complete.', 'havenlytics' ) );
		$job['completed_at'] = gmdate( 'c' );
		return $job;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
