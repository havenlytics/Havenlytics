<?php
/**
 * Phased import runner for AJAX job batches.
 *
 * @package HvnlyNab\ImportExport\Jobs
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Jobs;

use HvnlyNab\ImportExport\Cache\ImportExportCacheCoordinator;
use HvnlyNab\ImportExport\Capability\MigrationLimits;
use HvnlyNab\ImportExport\Import\AgenciesImporter;
use HvnlyNab\ImportExport\Import\AgentsImporter;
use HvnlyNab\ImportExport\Import\BuilderImportPolicy;
use HvnlyNab\ImportExport\Import\DuplicateDetector;
use HvnlyNab\ImportExport\Import\EntityReader;
use HvnlyNab\ImportExport\Import\IdRemapper;
use HvnlyNab\ImportExport\Import\PropertiesImporter;
use HvnlyNab\ImportExport\Import\TermsImporter;
use HvnlyNab\ImportExport\Media\MediaRemapper;
use HvnlyNab\ImportExport\Media\MediaUnpacker;
use HvnlyNab\ImportExport\Media\UrlRewriter;

defined( 'ABSPATH' ) || exit;

/**
 * ImportBatchRunner — one phase/slice per AJAX tick.
 *
 * @since 3.6.0
 */
final class ImportBatchRunner {

	public const BATCH_PROPERTIES = 10;
	public const BATCH_MEDIA      = 5;

	/**
	 * @param array<string, mixed> $job Job.
	 * @return array<string, mixed>
	 */
	public static function tick( array $job ): array {
		$job['status'] = JobStateStore::STATUS_RUNNING;
		$phase         = (string) ( $job['phase'] ?? 'prepare' );

		switch ( $phase ) {
			case 'prepare':
				return self::phase_prepare( $job );
			case 'terms':
				return self::run_support_phase( $job, 'terms', TermsImporter::class, 'agencies', 15 );
			case 'agencies':
				return self::run_support_phase( $job, 'agencies', AgenciesImporter::class, 'agents', 25 );
			case 'agents':
				return self::run_support_phase( $job, 'agents', AgentsImporter::class, 'builder', 35 );
			case 'builder':
				return self::phase_builder( $job );
			case 'properties':
				return self::phase_properties( $job );
			case 'media_unpack':
				return self::phase_media_unpack( $job );
			case 'media_remap':
				return self::phase_media_remap( $job );
			case 'finalize':
				return self::phase_finalize( $job );
			default:
				$job['status'] = JobStateStore::STATUS_FAILED;
				return JobStateStore::push_error(
					$job,
					array(
						'code'    => 'hvnly_ie_import_phase_unknown',
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
		$options  = isset( $job['options'] ) && is_array( $job['options'] ) ? $job['options'] : array();
		$dir      = (string) ( $job['workdir'] ?? '' );
		$entities = JobWorkspace::read_json( $dir, 'entities.json', null );
		if ( '' === $dir || ! is_array( $entities ) ) {
			$job['status'] = JobStateStore::STATUS_FAILED;
			return JobStateStore::push_error(
				$job,
				array(
					'code'    => 'hvnly_ie_import_prepare_missing',
					'message' => 'Import job is missing validated package data.',
					'context' => array(),
				)
			);
		}

		// Final Free-limit gate before any term/property/media writes.
		$manifest = JobWorkspace::read_json( $dir, 'manifest.json', null );
		$allowed  = MigrationLimits::assert_import_allowed(
			is_array( $manifest ) ? $manifest : null,
			$entities
		);
		if ( is_wp_error( $allowed ) ) {
			$job['status'] = JobStateStore::STATUS_FAILED;
			return JobStateStore::push_error(
				$job,
				array(
					'code'    => (string) $allowed->get_error_code(),
					'message' => (string) $allowed->get_error_message(),
					'context' => (array) $allowed->get_error_data(),
				)
			);
		}

		JobWorkspace::write_json( (string) $job['workdir'], 'id_map.json', array() );
		JobWorkspace::write_json( (string) $job['workdir'], 'media_map.json', array() );
		JobWorkspace::write_json( (string) $job['workdir'], 'media_by_path.json', array() );

		$next = ! empty( $options['include_terms'] ) ? 'terms'
			: ( ! empty( $options['include_agencies'] ) ? 'agencies'
			: ( ! empty( $options['include_agents'] ) ? 'agents'
			: ( ! empty( $options['include_properties'] ) || ! empty( $options['apply_builder_policy'] ) ? 'builder'
			: ( ! empty( $options['include_media'] ) ? 'media_unpack' : 'finalize' ) ) ) );

		$job['phase']    = $next;
		$job['progress'] = array(
			'percent' => 5,
			'message' => 'Import prepared.',
		);
		$job['counts']   = array(
			'terms'      => self::empty_counts(),
			'agencies'   => self::empty_counts(),
			'agents'     => self::empty_counts(),
			'properties' => self::empty_counts(),
			'media'      => array(
				'created' => 0,
				'skipped' => 0,
				'failed' => 0,
			),
		);
		return $job;
	}

	/**
	 * @param array  $job Job.
	 * @param string $key Counts key.
	 * @param string $importer Importer class.
	 * @param string $next Next phase.
	 * @param int    $percent Progress.
	 * @return array
	 */
	private static function run_support_phase( array $job, string $key, string $importer, string $next, int $percent ): array {
		$options = isset( $job['options'] ) && is_array( $job['options'] ) ? $job['options'] : array();
		$include = 'terms' === $key ? 'include_terms' : ( 'agencies' === $key ? 'include_agencies' : 'include_agents' );
		if ( empty( $options[ $include ] ) ) {
			$job['phase'] = $next;
			return $job;
		}

		$reader   = self::reader( $job );
		$remapper = self::remapper( $job );
		$detector = new DuplicateDetector();
		$policy   = DuplicateDetector::normalize_policy( (string) ( $options['duplicate_policy'] ?? 'skip' ) );

		$result = $importer::import( $reader, $detector, $remapper, $policy );
		$job    = JobStateStore::merge_warnings( $job, $result->warnings() );
		if ( ! $result->ok() ) {
			$job['status'] = JobStateStore::STATUS_FAILED;
			foreach ( $result->errors() as $error ) {
				$job = JobStateStore::push_error( $job, $error );
			}
			$job['completed_at'] = gmdate( 'c' );
			$job['report']       = ReportBuilder::from_job( $job );
			return $job;
		}

		$data = $result->data();
		if ( is_array( $data ) ) {
			$job['counts'][ $key ] = array(
				'created' => absint( $data['created'] ?? 0 ),
				'updated' => absint( $data['updated'] ?? 0 ),
				'skipped' => absint( $data['skipped'] ?? 0 ),
				'failed'  => absint( $data['failed'] ?? 0 ),
			);
		}
		self::save_remapper( $job, $remapper );
		$job['phase']    = $next;
		$job['progress'] = array(
			'percent' => $percent,
			'message' => ucfirst( $key ) . ' imported.',
		);
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_builder( array $job ): array {
		$options = isset( $job['options'] ) && is_array( $job['options'] ) ? $job['options'] : array();
		$reader  = self::reader( $job );

		// Honor UI toggle: when apply_builder_policy is off, always Keep.
		$apply  = ! array_key_exists( 'apply_builder_policy', $options ) || ! empty( $options['apply_builder_policy'] );
		$policy = $apply
			? BuilderImportPolicy::normalize_policy( (string) ( $options['builder_policy'] ?? 'keep' ) )
			: BuilderImportPolicy::POLICY_KEEP;

		$result = BuilderImportPolicy::apply( $reader, $policy );
		$job    = JobStateStore::merge_warnings( $job, $result->warnings() );
		if ( ! $result->ok() ) {
			$job['status'] = JobStateStore::STATUS_FAILED;
			foreach ( $result->errors() as $error ) {
				$job = JobStateStore::push_error( $job, $error );
			}
			$job['completed_at'] = gmdate( 'c' );
			$job['report']       = ReportBuilder::from_job( $job );
			return $job;
		}

		$data                                   = $result->data();
		$job['builder']                         = is_array( $data ) ? $data : array();
		$job['builder']['apply_builder_policy'] = $apply;
		$total                                  = count( self::reader( $job )->read_section( 'properties' ) );
		$job['cursor']                          = array(
			'index' => 0,
			'total' => $total,
		);
		$job['phase']                           = ! empty( $options['include_properties'] ) ? 'properties'
			: ( ! empty( $options['include_media'] ) ? 'media_unpack' : 'finalize' );
		$job['progress']                        = array(
			'percent' => 45,
			'message' => sprintf( 'Builder policy applied (%s).', $policy ),
		);
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_properties( array $job ): array {
		$options  = isset( $job['options'] ) && is_array( $job['options'] ) ? $job['options'] : array();
		$reader   = self::reader( $job );
		$remapper = self::remapper( $job );
		$detector = new DuplicateDetector();
		$policy   = DuplicateDetector::normalize_policy( (string) ( $options['duplicate_policy'] ?? 'skip' ) );
		$index    = isset( $job['cursor']['index'] ) ? (int) $job['cursor']['index'] : 0;
		$total    = isset( $job['cursor']['total'] ) ? (int) $job['cursor']['total'] : 0;

		$result = PropertiesImporter::import(
			$reader,
			$detector,
			$remapper,
			$policy,
			array(
				'offset' => $index,
				'limit'  => self::BATCH_PROPERTIES,
			)
		);
		$job    = JobStateStore::merge_warnings( $job, $result->warnings() );
		if ( ! $result->ok() ) {
			$job['status'] = JobStateStore::STATUS_FAILED;
			foreach ( $result->errors() as $error ) {
				$job = JobStateStore::push_error( $job, $error );
			}
			$job['completed_at'] = gmdate( 'c' );
			$job['report']       = ReportBuilder::from_job( $job );
			return $job;
		}

		$data                        = $result->data();
		$prev                        = isset( $job['counts']['properties'] ) && is_array( $job['counts']['properties'] )
			? $job['counts']['properties']
			: self::empty_counts();
		$job['counts']['properties'] = array(
			'created' => (int) $prev['created'] + absint( $data['created'] ?? 0 ),
			'updated' => (int) $prev['updated'] + absint( $data['updated'] ?? 0 ),
			'skipped' => (int) $prev['skipped'] + absint( $data['skipped'] ?? 0 ),
			'failed'  => (int) $prev['failed'] + absint( $data['failed'] ?? 0 ),
		);

		$next_index             = isset( $data['next'] ) ? (int) $data['next'] : ( $index + self::BATCH_PROPERTIES );
		$job['cursor']['index'] = $next_index;
		self::save_remapper( $job, $remapper );

		$pct             = $total > 0 ? 45 + (int) floor( 30 * min( 1, $next_index / $total ) ) : 75;
		$job['progress'] = array(
			'percent' => $pct,
			'message' => sprintf( 'Imported properties %d / %d.', $next_index, $total ),
		);

		if ( ! empty( $data['done'] ) || $next_index >= $total ) {
			$failed = (int) ( $job['counts']['properties']['failed'] ?? 0 );
			if ( $failed > 0 ) {
				$job['status']       = JobStateStore::STATUS_FAILED;
				$job                 = JobStateStore::push_error(
					$job,
					array(
						'code'    => 'hvnly_ie_property_remap_failed',
						'message' => sprintf(
							'%d propert%s failed during import because Builder fields/groups could not be remapped without data loss. See job warnings for details.',
							$failed,
							1 === $failed ? 'y' : 'ies'
						),
						'context' => array( 'failed' => $failed ),
					)
				);
				$job['completed_at'] = gmdate( 'c' );
				$job['report']       = ReportBuilder::from_job( $job );
				return $job;
			}
			$job['phase']  = ! empty( $options['include_media'] ) ? 'media_unpack' : 'finalize';
			$job['cursor'] = array(
				'index' => 0,
				'total' => 0,
			);
		}
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_media_unpack( array $job ): array {
		$dir         = (string) $job['workdir'];
		$media_index = JobWorkspace::read_json( $dir, 'media_index.json', null );
		$files       = JobWorkspace::read_json( $dir, 'files.json', array() );
		$files       = is_array( $files ) ? $files : array();
		if ( ! is_array( $media_index ) || empty( $media_index['files'] ) ) {
			$job['phase']    = 'finalize';
			$job['progress'] = array(
				'percent' => 90,
				'message' => 'No media to unpack.',
			);
			return $job;
		}
		$map                    = JobWorkspace::read_json( $dir, 'media_map.json', array() );
		$by_path                = JobWorkspace::read_json( $dir, 'media_by_path.json', array() );
		$map                    = is_array( $map ) ? $map : array();
		$by_path                = is_array( $by_path ) ? $by_path : array();
		$index                  = isset( $job['cursor']['index'] ) ? (int) $job['cursor']['index'] : 0;
		$total                  = count( $media_index['files'] );
		$job['cursor']['total'] = $total;

		$result = MediaUnpacker::unpack(
			$media_index,
			$files,
			0,
			array(
				'offset' => $index,
				'limit'  => self::BATCH_MEDIA,
			),
			$map,
			$by_path
		);
		$job    = JobStateStore::merge_warnings( $job, $result->warnings() );
		$data   = $result->data();
		if ( is_array( $data ) ) {
			JobWorkspace::write_json( $dir, 'media_map.json', isset( $data['map'] ) ? $data['map'] : $map );
			JobWorkspace::write_json( $dir, 'media_by_path.json', isset( $data['by_path'] ) ? $data['by_path'] : $by_path );
			$prev                   = isset( $job['counts']['media'] ) && is_array( $job['counts']['media'] ) ? $job['counts']['media'] : array(
				'created' => 0,
				'skipped' => 0,
				'failed' => 0,
			);
			$job['counts']['media'] = array(
				'created' => (int) $prev['created'] + absint( $data['created'] ?? 0 ),
				'skipped' => (int) $prev['skipped'] + absint( $data['skipped'] ?? 0 ),
				'failed'  => (int) $prev['failed'] + absint( $data['failed'] ?? 0 ),
			);
			$next                   = isset( $data['next'] ) ? (int) $data['next'] : ( $index + self::BATCH_MEDIA );
			$job['cursor']['index'] = $next;
			$pct                    = $total > 0 ? 75 + (int) floor( 15 * min( 1, $next / $total ) ) : 90;
			$job['progress']        = array(
				'percent' => $pct,
				'message' => sprintf( 'Unpacked media %d / %d.', $next, $total ),
			);
			if ( ! empty( $data['done'] ) || $next >= $total ) {
				$job['phase'] = 'media_remap';
			}
		}
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_media_remap( array $job ): array {
		$dir      = (string) $job['workdir'];
		$map      = JobWorkspace::read_json( $dir, 'media_map.json', array() );
		$map      = is_array( $map ) ? $map : array();
		$remapper = self::remapper( $job );
		foreach ( $map as $key => $attachment_id ) {
			$remapper->set_media( (string) $key, (int) $attachment_id );
		}
		$rewriter     = new UrlRewriter( $map );
		$result       = MediaRemapper::apply( $rewriter, self::reader( $job ), $remapper );
		$job          = JobStateStore::merge_warnings( $job, $result->warnings() );
		$data         = $result->data();
		$job['media'] = is_array( $data ) ? $data : array();
		self::save_remapper( $job, $remapper );
		$job['phase']    = 'finalize';
		$job['progress'] = array(
			'percent' => 95,
			'message' => 'Media remapped.',
		);
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_finalize( array $job ): array {
		$builder = isset( $job['builder'] ) && is_array( $job['builder'] ) ? $job['builder'] : array();
		ImportExportCacheCoordinator::after_import_success(
			array(
				'builder_replaced' => ( BuilderImportPolicy::POLICY_REPLACE === ( $builder['policy'] ?? '' ) ),
			)
		);

		$job['status']       = JobStateStore::STATUS_COMPLETED;
		$job['phase']        = 'completed';
		$job['progress']     = array(
			'percent' => 100,
			'message' => 'Import complete.',
		);
		$job['completed_at'] = gmdate( 'c' );
		$job['report']       = ReportBuilder::from_job( $job );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @return EntityReader
	 */
	private static function reader( array $job ): EntityReader {
		$dir      = (string) ( $job['workdir'] ?? '' );
		$entities = JobWorkspace::read_json( $dir, 'entities.json', null );
		if ( ! is_array( $entities ) && isset( $job['entities'] ) && is_array( $job['entities'] ) ) {
			$entities = $job['entities'];
		}
		if ( ! is_array( $entities ) ) {
			$entities = array();
		}
		return new EntityReader( $entities );
	}

	/**
	 * @param array $job Job.
	 * @return IdRemapper
	 */
	private static function remapper( array $job ): IdRemapper {
		$dir  = (string) ( $job['workdir'] ?? '' );
		$data = JobWorkspace::read_json( $dir, 'id_map.json', array() );
		return IdRemapper::from_array( is_array( $data ) ? $data : array() );
	}

	/**
	 * @param array      $job Job.
	 * @param IdRemapper $remapper Remapper.
	 * @return void
	 */
	private static function save_remapper( array $job, IdRemapper $remapper ): void {
		$dir = (string) ( $job['workdir'] ?? '' );
		if ( '' !== $dir ) {
			JobWorkspace::write_json( $dir, 'id_map.json', $remapper->to_array() );
		}
	}

	/**
	 * @return array{created:int,updated:int,skipped:int,failed:int}
	 */
	private static function empty_counts(): array {
		return array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
