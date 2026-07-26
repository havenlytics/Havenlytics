<?php
/**
 * Phased export runner for AJAX job batches.
 *
 * @package HvnlyNab\ImportExport\Jobs
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Jobs;

use HvnlyNab\ImportExport\Capability\MigrationLimits;
use HvnlyNab\ImportExport\Export\AgenciesExporter;
use HvnlyNab\ImportExport\Export\AgentsExporter;
use HvnlyNab\ImportExport\Export\BuildersExporter;
use HvnlyNab\ImportExport\Export\PropertiesExporter;
use HvnlyNab\ImportExport\Export\TermsExporter;
use HvnlyNab\ImportExport\Media\MediaIndexer;
use HvnlyNab\ImportExport\Media\MediaPacker;
use HvnlyNab\ImportExport\Package\ManifestSchema;
use HvnlyNab\ImportExport\Package\PackageWriter;
use HvnlyNab\ImportExport\Package\TempStorage;
use HvnlyNab\ImportExport\Support\PortableFieldEncoder;
use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * ExportBatchRunner — one phase/slice per AJAX tick.
 *
 * @since 3.6.0
 */
final class ExportBatchRunner {

	public const BATCH_PROPERTIES = 10;

	/**
	 * Process the next export batch for a job.
	 *
	 * @param array<string, mixed> $job Job state.
	 * @return array<string, mixed> Updated job.
	 */
	public static function tick( array $job ): array {
		$job['status'] = JobStateStore::STATUS_RUNNING;
		$phase         = (string) ( $job['phase'] ?? 'prepare' );
		$options       = isset( $job['options'] ) && is_array( $job['options'] ) ? $job['options'] : array();

		switch ( $phase ) {
			case 'prepare':
				return self::phase_prepare( $job );
			case 'builders':
				return self::phase_builders( $job, $options );
			case 'terms':
				return self::phase_terms( $job, $options );
			case 'agencies':
				return self::phase_agencies( $job, $options );
			case 'agents':
				return self::phase_agents( $job, $options );
			case 'properties':
				return self::phase_properties( $job, $options );
			case 'media':
				return self::phase_media( $job, $options );
			case 'finalize':
				return self::phase_finalize( $job, $options );
			default:
				$job['status'] = JobStateStore::STATUS_FAILED;
				$job           = JobStateStore::push_error(
					$job,
					array(
						'code'    => 'hvnly_ie_export_phase_unknown',
						'message' => 'Unknown export phase.',
						'context' => array( 'phase' => $phase ),
					)
				);
				return $job;
		}
	}

	/**
	 * @param array $job Job.
	 * @return array
	 */
	private static function phase_prepare( array $job ): array {
		$options = isset( $job['options'] ) && is_array( $job['options'] ) ? $job['options'] : array();
		$allowed = MigrationLimits::assert_export_allowed( $options );
		if ( is_wp_error( $allowed ) ) {
			$job['status'] = JobStateStore::STATUS_FAILED;
			$job           = JobStateStore::push_error(
				$job,
				array(
					'code'    => (string) $allowed->get_error_code(),
					'message' => (string) $allowed->get_error_message(),
					'context' => (array) $allowed->get_error_data(),
				)
			);
			$job['completed_at'] = gmdate( 'c' );
			$job['report']       = ReportBuilder::from_job( $job );
			return $job;
		}

		$workdir = TempStorage::create_workdir( 'export-job' );
		if ( ! $workdir->ok() ) {
			$job['status'] = JobStateStore::STATUS_FAILED;
			foreach ( $workdir->errors() as $error ) {
				$job = JobStateStore::push_error( $job, $error );
			}
			return $job;
		}
		$dir = (string) $workdir->data()['dir'];
		$job['workdir'] = $dir;
		JobWorkspace::write_json( $dir, 'entities/builders.json', array() );
		JobWorkspace::write_json( $dir, 'entities/terms.json', array() );
		JobWorkspace::write_json( $dir, 'entities/agencies.json', array() );
		JobWorkspace::write_json( $dir, 'entities/agents.json', array() );
		JobWorkspace::write_json( $dir, 'entities/properties.json', array() );
		JobWorkspace::write_json( $dir, 'media_catalog.json', array() );
		$job['phase'] = 'builders';
		$job['progress'] = array(
			'percent' => 5,
			'message' => 'Export workspace ready.',
		);
		$job['cursor'] = array( 'index' => 0, 'total' => 0 );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @param array $options Options.
	 * @return array
	 */
	private static function phase_builders( array $job, array $options ): array {
		$dir = (string) $job['workdir'];
		$builders = ! empty( $options['include_builders'] ) ? BuildersExporter::export() : array();
		JobWorkspace::write_json( $dir, 'entities/builders.json', $builders );
		$job['counts']['builders'] = ! empty( $builders ) ? 1 : 0;
		$job['phase'] = 'terms';
		$job['progress'] = array( 'percent' => 15, 'message' => 'Builders exported.' );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @param array $options Options.
	 * @return array
	 */
	private static function phase_terms( array $job, array $options ): array {
		$dir     = (string) $job['workdir'];
		$encoder = self::encoder_from_disk( $dir );
		$terms   = ! empty( $options['include_taxonomies'] ) ? TermsExporter::export( $encoder ) : array();
		JobWorkspace::write_json( $dir, 'entities/terms.json', $terms );
		self::encoder_to_disk( $dir, $encoder );
		$job['counts']['terms'] = count( $terms );
		$job['phase'] = 'agencies';
		$job['progress'] = array( 'percent' => 25, 'message' => 'Taxonomies exported.' );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @param array $options Options.
	 * @return array
	 */
	private static function phase_agencies( array $job, array $options ): array {
		$dir     = (string) $job['workdir'];
		$encoder = self::encoder_from_disk( $dir );
		$agencies = ! empty( $options['include_agencies'] ) ? AgenciesExporter::export( $encoder ) : array();
		JobWorkspace::write_json( $dir, 'entities/agencies.json', $agencies );
		self::encoder_to_disk( $dir, $encoder );
		$job['counts']['agencies'] = count( $agencies );
		$job['phase'] = 'agents';
		$job['progress'] = array( 'percent' => 35, 'message' => 'Agencies exported.' );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @param array $options Options.
	 * @return array
	 */
	private static function phase_agents( array $job, array $options ): array {
		$dir     = (string) $job['workdir'];
		$encoder = self::encoder_from_disk( $dir );
		$agents  = ! empty( $options['include_agents'] ) ? AgentsExporter::export( $encoder ) : array();
		JobWorkspace::write_json( $dir, 'entities/agents.json', $agents );
		self::encoder_to_disk( $dir, $encoder );
		$job['counts']['agents'] = count( $agents );
		$total = self::count_properties( $options );
		$job['cursor'] = array( 'index' => 0, 'total' => $total );
		$job['phase'] = ! empty( $options['include_properties'] ) ? 'properties' : ( ! empty( $options['include_media'] ) ? 'media' : 'finalize' );
		$job['progress'] = array( 'percent' => 45, 'message' => 'Agents exported.' );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @param array $options Options.
	 * @return array
	 */
	private static function phase_properties( array $job, array $options ): array {
		$dir     = (string) $job['workdir'];
		$encoder = self::encoder_from_disk( $dir );
		$index   = isset( $job['cursor']['index'] ) ? (int) $job['cursor']['index'] : 0;
		$total   = isset( $job['cursor']['total'] ) ? (int) $job['cursor']['total'] : 0;

		$opts = array_merge(
			$options,
			array(
				'offset' => $index,
				'limit'  => self::BATCH_PROPERTIES,
			)
		);
		$batch = PropertiesExporter::export( $encoder, $opts );
		$existing = JobWorkspace::read_json( $dir, 'entities/properties.json', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		$existing = array_merge( $existing, $batch );
		JobWorkspace::write_json( $dir, 'entities/properties.json', $existing );
		self::encoder_to_disk( $dir, $encoder );

		$index += count( $batch );
		$job['cursor']['index'] = $index;
		$job['counts']['properties'] = count( $existing );
		$pct = $total > 0 ? 45 + (int) floor( 30 * min( 1, $index / $total ) ) : 75;
		$job['progress'] = array(
			'percent' => $pct,
			'message' => sprintf( 'Exported properties %d / %d.', $index, $total ),
		);

		if ( $index >= $total || empty( $batch ) ) {
			$job['phase'] = ! empty( $options['include_media'] ) ? 'media' : 'finalize';
			$job['cursor']['index'] = 0;
		}
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @param array $options Options.
	 * @return array
	 */
	private static function phase_media( array $job, array $options ): array {
		$dir     = (string) $job['workdir'];
		$encoder = self::encoder_from_disk( $dir );
		$catalog = $encoder->media_catalog();
		$warnings = array();
		$media_files = array();
		$media_index = array( 'files' => array() );

		if ( ! empty( $catalog ) ) {
			$indexed = MediaIndexer::index( $catalog );
			$job     = JobStateStore::merge_warnings( $job, $indexed->warnings() );
			if ( $indexed->ok() ) {
				$entries = isset( $indexed->data()['entries'] ) ? $indexed->data()['entries'] : array();
				$packed  = MediaPacker::pack( is_array( $entries ) ? $entries : array() );
				$job     = JobStateStore::merge_warnings( $job, $packed->warnings() );
				if ( $packed->ok() ) {
					$pack_data   = $packed->data();
					$media_files = isset( $pack_data['binaries'] ) ? $pack_data['binaries'] : array();
					$media_index = isset( $pack_data['index'] ) ? $pack_data['index'] : array( 'files' => array() );
					$job['counts']['media_files'] = isset( $pack_data['packaged_count'] ) ? (int) $pack_data['packaged_count'] : 0;
				} else {
					foreach ( $packed->errors() as $error ) {
						$job = JobStateStore::push_error( $job, $error );
					}
				}
			} else {
				foreach ( $indexed->errors() as $error ) {
					$job = JobStateStore::push_error( $job, $error );
				}
			}
		}

		JobWorkspace::write_json( $dir, 'media_files.json', $media_files );
		JobWorkspace::write_json( $dir, 'media_index.json', $media_index );
		$job['phase'] = 'finalize';
		$job['progress'] = array( 'percent' => 85, 'message' => 'Media packaged.' );
		unset( $warnings );
		return $job;
	}

	/**
	 * @param array $job Job.
	 * @param array $options Options.
	 * @return array
	 */
	private static function phase_finalize( array $job, array $options ): array {
		$dir = (string) $job['workdir'];
		$entities = array(
			'schema_version' => ManifestSchema::SCHEMA_VERSION,
			'builders'       => JobWorkspace::read_json( $dir, 'entities/builders.json', array() ),
			'terms'          => JobWorkspace::read_json( $dir, 'entities/terms.json', array() ),
			'agencies'       => JobWorkspace::read_json( $dir, 'entities/agencies.json', array() ),
			'agents'         => JobWorkspace::read_json( $dir, 'entities/agents.json', array() ),
			'properties'     => JobWorkspace::read_json( $dir, 'entities/properties.json', array() ),
			'media_catalog'  => array(),
		);

		$prop_count = is_array( $entities['properties'] ) ? count( $entities['properties'] ) : 0;
		$allowed    = MigrationLimits::can_export_properties( $prop_count, $options );
		if ( is_wp_error( $allowed ) ) {
			$job['status'] = JobStateStore::STATUS_FAILED;
			$job           = JobStateStore::push_error(
				$job,
				array(
					'code'    => (string) $allowed->get_error_code(),
					'message' => (string) $allowed->get_error_message(),
					'context' => (array) $allowed->get_error_data(),
				)
			);
			$job['completed_at'] = gmdate( 'c' );
			$job['report']       = ReportBuilder::from_job( $job );
			return $job;
		}

		$media_files = JobWorkspace::read_json( $dir, 'media_files.json', array() );
		$media_index = JobWorkspace::read_json( $dir, 'media_index.json', array( 'files' => array() ) );
		if ( ! is_array( $media_files ) ) {
			$media_files = array();
		}
		if ( ! is_array( $media_index ) ) {
			$media_index = array( 'files' => array() );
		}

		$site_label = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$manifest   = array(
			'format'                  => ManifestSchema::FORMAT,
			'schema_version'          => ManifestSchema::SCHEMA_VERSION,
			'plugin_version_exported' => defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '',
			'exported_at'             => gmdate( 'c' ),
			'package_name'            => (string) ( $options['package_name'] ?? ( 'havenlytics-export-' . gmdate( 'Y-m-d-Hi' ) ) ),
			'description'             => (string) ( $options['description'] ?? '' ),
			'source_site_label'       => (string) ( $options['source_site_label'] ?? $site_label ),
			'contents'                => array(
				'properties' => ! empty( $options['include_properties'] ),
				'media'      => ! empty( $media_files ),
				'taxonomies' => ! empty( $options['include_taxonomies'] ),
				'agents'     => ! empty( $options['include_agents'] ),
				'agencies'   => ! empty( $options['include_agencies'] ),
				'builders'   => ! empty( $options['include_builders'] ),
			),
			'counts'                  => array(
				'terms'      => is_array( $entities['terms'] ) ? count( $entities['terms'] ) : 0,
				'agencies'   => is_array( $entities['agencies'] ) ? count( $entities['agencies'] ) : 0,
				'agents'     => is_array( $entities['agents'] ) ? count( $entities['agents'] ) : 0,
				'properties' => is_array( $entities['properties'] ) ? count( $entities['properties'] ) : 0,
			),
		);

		$write = PackageWriter::write( $manifest, $entities, $media_files, $media_index );
		$job   = JobStateStore::merge_warnings( $job, $write->warnings() );
		if ( ! $write->ok() ) {
			$job['status'] = JobStateStore::STATUS_FAILED;
			foreach ( $write->errors() as $error ) {
				$job = JobStateStore::push_error( $job, $error );
			}
			$job['completed_at'] = gmdate( 'c' );
			$job['report']       = ReportBuilder::from_job( $job );
			return $job;
		}

		$data = $write->data();
		$job['zip_path']      = isset( $data['zip_path'] ) ? (string) $data['zip_path'] : '';
		$job['zip_filename']  = $job['zip_path'] ? wp_basename( $job['zip_path'] ) : '';
		$job['package_workdir'] = isset( $data['workdir'] ) ? (string) $data['workdir'] : '';
		$job['status']        = JobStateStore::STATUS_COMPLETED;
		$job['phase']         = 'completed';
		$job['progress']      = array( 'percent' => 100, 'message' => 'Export complete.' );
		$job['completed_at']  = gmdate( 'c' );
		$job['report']        = ReportBuilder::from_job( $job );

		\HvnlyNab\ImportExport\Cache\ImportExportCacheCoordinator::after_export_success();
		return $job;
	}

	/**
	 * @param string $dir Workdir.
	 * @return PortableFieldEncoder
	 */
	private static function encoder_from_disk( string $dir ): PortableFieldEncoder {
		$encoder = new PortableFieldEncoder();
		$state   = JobWorkspace::read_json( $dir, 'media_encoder_state.json', array() );
		if ( is_array( $state ) && ! empty( $state ) ) {
			$encoder->import_media_state( $state );
		}
		return $encoder;
	}

	/**
	 * @param string               $dir Dir.
	 * @param PortableFieldEncoder $encoder Encoder.
	 * @return void
	 */
	private static function encoder_to_disk( string $dir, PortableFieldEncoder $encoder ): void {
		JobWorkspace::write_json( $dir, 'media_encoder_state.json', $encoder->export_media_state() );
		JobWorkspace::write_json( $dir, 'media_catalog.json', $encoder->media_catalog() );
	}

	/**
	 * @param array $options Options.
	 * @return int
	 */
	private static function count_properties( array $options ): int {
		if ( empty( $options['include_properties'] ) ) {
			return 0;
		}
		$statuses = isset( $options['statuses'] ) && is_array( $options['statuses'] )
			? array_map( 'strval', $options['statuses'] )
			: array( 'publish', 'draft', 'pending', 'private', 'expired' );
		$query = new \WP_Query(
			array(
				'post_type'      => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'    => $statuses,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
