<?php
/**
 * Entity + media import orchestration (Phases 5–6).
 *
 * Imports terms, agencies, agents, Builder policy, properties, then media.
 * No UI / AJAX / background jobs.
 *
 * @package HvnlyNab\ImportExport\Import
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Import;

use HvnlyNab\ImportExport\Media\MediaImporter;
use HvnlyNab\ImportExport\Package\PackageReader;
use HvnlyNab\ImportExport\Package\PackageResult;
use HvnlyNab\ImportExport\Package\TempStorage;
use HvnlyNab\ImportExport\Security\CapabilityChecker;

defined( 'ABSPATH' ) || exit;

/**
 * ImportJob — full HPTP entity + media import.
 *
 * @since 3.6.0
 */
final class ImportJob {

	/**
	 * Run entity import (+ optional media restoration).
	 *
	 * Options:
	 * - zip_path / entities
	 * - duplicate_policy, builder_policy
	 * - include_terms|agencies|agents|properties|media
	 * - cleanup, check_capability
	 *
	 * @param array<string, mixed> $options Import options.
	 * @return PackageResult
	 */
	public static function run( array $options = array() ): PackageResult {
		$options = self::normalize_options( $options );

		if ( ! empty( $options['check_capability'] ) && ! CapabilityChecker::current_user_can_manage() ) {
			return PackageResult::failure(
				'hvnly_ie_capability_denied',
				'Current user cannot manage Import / Export.',
				array()
			);
		}

		$workdir      = '';
		$warnings     = array();
		$entities     = null;
		$media_index  = null;
		$files        = array();

		if ( ! empty( $options['entities'] ) && is_array( $options['entities'] ) ) {
			$entities = $options['entities'];
			if ( ! empty( $options['media_index'] ) && is_array( $options['media_index'] ) ) {
				$media_index = $options['media_index'];
			}
			if ( ! empty( $options['files'] ) && is_array( $options['files'] ) ) {
				$files = $options['files'];
			}
			if ( ! empty( $options['workdir'] ) && is_string( $options['workdir'] ) ) {
				$workdir = $options['workdir'];
			}
		} elseif ( ! empty( $options['zip_path'] ) ) {
			$opened = PackageReader::open( (string) $options['zip_path'] );
			if ( ! $opened->ok() ) {
				return $opened;
			}
			foreach ( $opened->warnings() as $warning ) {
				$warnings[] = $warning;
			}
			$payload     = $opened->data();
			$entities    = isset( $payload['entities'] ) && is_array( $payload['entities'] )
				? $payload['entities']
				: null;
			$workdir     = isset( $payload['workdir'] ) ? (string) $payload['workdir'] : '';
			$media_index = isset( $payload['media_index'] ) && is_array( $payload['media_index'] )
				? $payload['media_index']
				: null;
			$files       = isset( $payload['files'] ) && is_array( $payload['files'] )
				? $payload['files']
				: array();
		} else {
			return PackageResult::failure(
				'hvnly_ie_import_input_missing',
				'Import requires zip_path or entities.',
				array()
			);
		}

		if ( ! is_array( $entities ) ) {
			self::maybe_cleanup( $workdir, $options );
			return PackageResult::failure(
				'hvnly_ie_entities_missing',
				'Package did not contain a usable entities payload.',
				array()
			);
		}

		$reader   = new EntityReader( $entities );
		$detector = new DuplicateDetector();
		$remapper = new IdRemapper();
		$policy   = (string) $options['duplicate_policy'];

		$counts = array(
			'terms'      => self::empty_counts(),
			'agencies'   => self::empty_counts(),
			'agents'     => self::empty_counts(),
			'properties' => self::empty_counts(),
			'media'      => array(
				'created' => 0,
				'skipped' => 0,
				'failed'  => 0,
			),
		);

		$support = array(
			'terms'    => array( 'enabled' => ! empty( $options['include_terms'] ), 'importer' => TermsImporter::class ),
			'agencies' => array( 'enabled' => ! empty( $options['include_agencies'] ), 'importer' => AgenciesImporter::class ),
			'agents'   => array( 'enabled' => ! empty( $options['include_agents'] ), 'importer' => AgentsImporter::class ),
		);

		foreach ( $support as $key => $section ) {
			if ( empty( $section['enabled'] ) ) {
				continue;
			}

			/** @var class-string $importer */
			$importer = $section['importer'];
			$result   = $importer::import( $reader, $detector, $remapper, $policy );
			if ( ! $result->ok() ) {
				self::maybe_cleanup( $workdir, $options );
				return $result;
			}

			foreach ( $result->warnings() as $warning ) {
				$warnings[] = $warning;
			}

			$data = $result->data();
			if ( is_array( $data ) ) {
				$counts[ $key ] = array(
					'created' => absint( $data['created'] ?? 0 ),
					'updated' => absint( $data['updated'] ?? 0 ),
					'skipped' => absint( $data['skipped'] ?? 0 ),
					'failed'  => absint( $data['failed'] ?? 0 ),
				);
			}
		}

		$builder_result = array(
			'policy'      => BuilderImportPolicy::normalize_policy( (string) $options['builder_policy'] ),
			'action'      => 'skipped',
			'snapshot_id' => '',
		);

		$run_builder = ! empty( $options['apply_builder_policy'] )
			|| BuilderImportPolicy::POLICY_REPLACE === $builder_result['policy']
			|| ! empty( $options['include_properties'] );

		if ( $run_builder ) {
			$builder_run = BuilderImportPolicy::apply( $reader, (string) $options['builder_policy'] );
			if ( ! $builder_run->ok() ) {
				self::maybe_cleanup( $workdir, $options );
				return $builder_run;
			}
			foreach ( $builder_run->warnings() as $warning ) {
				$warnings[] = $warning;
			}
			$data = $builder_run->data();
			if ( is_array( $data ) ) {
				$builder_result = array(
					'policy'      => (string) ( $data['policy'] ?? $builder_result['policy'] ),
					'action'      => (string) ( $data['action'] ?? '' ),
					'snapshot_id' => (string) ( $data['snapshot_id'] ?? '' ),
				);
			}
		}

		if ( ! empty( $options['include_properties'] ) ) {
			$prop_result = PropertiesImporter::import( $reader, $detector, $remapper, $policy );
			if ( ! $prop_result->ok() ) {
				self::maybe_cleanup( $workdir, $options );
				return $prop_result;
			}
			foreach ( $prop_result->warnings() as $warning ) {
				$warnings[] = $warning;
			}
			$data = $prop_result->data();
			if ( is_array( $data ) ) {
				$counts['properties'] = array(
					'created' => absint( $data['created'] ?? 0 ),
					'updated' => absint( $data['updated'] ?? 0 ),
					'skipped' => absint( $data['skipped'] ?? 0 ),
					'failed'  => absint( $data['failed'] ?? 0 ),
				);
			}
		}

		$media_result = array(
			'created' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'remap'   => array(),
		);

		if ( ! empty( $options['include_media'] ) ) {
			$media_run = MediaImporter::import( $media_index, $files, $reader, $remapper );
			foreach ( $media_run->warnings() as $warning ) {
				$warnings[] = $warning;
			}
			$data = $media_run->data();
			if ( is_array( $data ) ) {
				$media_result = array(
					'created' => absint( $data['created'] ?? 0 ),
					'skipped' => absint( $data['skipped'] ?? 0 ),
					'failed'  => absint( $data['failed'] ?? 0 ),
					'remap'   => isset( $data['remap'] ) && is_array( $data['remap'] ) ? $data['remap'] : array(),
				);
				$counts['media'] = array(
					'created' => $media_result['created'],
					'skipped' => $media_result['skipped'],
					'failed'  => $media_result['failed'],
				);
			}
		}

		self::maybe_cleanup( $workdir, $options );

		return PackageResult::success(
			array(
				'phase'            => '6',
				'schema_version'   => $reader->schema_version(),
				'duplicate_policy' => $policy,
				'builder'          => $builder_result,
				'media'            => $media_result,
				'counts'           => $counts,
				'id_map'           => $remapper->to_array(),
			),
			$warnings
		);
	}

	/**
	 * @param array<string, mixed> $options Raw options.
	 * @return array<string, mixed>
	 */
	private static function normalize_options( array $options ): array {
		$defaults = array(
			'zip_path'             => '',
			'entities'             => null,
			'media_index'          => null,
			'files'                => array(),
			'workdir'              => '',
			'duplicate_policy'     => DuplicateDetector::POLICY_SKIP,
			'builder_policy'       => BuilderImportPolicy::POLICY_KEEP,
			'include_terms'        => true,
			'include_agencies'     => true,
			'include_agents'       => true,
			'include_properties'   => true,
			'include_media'        => true,
			'apply_builder_policy' => true,
			'cleanup'              => true,
			'check_capability'     => true,
		);

		$merged = array_merge( $defaults, $options );
		$merged['duplicate_policy']     = DuplicateDetector::normalize_policy( (string) $merged['duplicate_policy'] );
		$merged['builder_policy']       = BuilderImportPolicy::normalize_policy( (string) $merged['builder_policy'] );
		$merged['include_terms']        = (bool) $merged['include_terms'];
		$merged['include_agencies']     = (bool) $merged['include_agencies'];
		$merged['include_agents']       = (bool) $merged['include_agents'];
		$merged['include_properties']   = (bool) $merged['include_properties'];
		$merged['include_media']        = (bool) $merged['include_media'];
		$merged['apply_builder_policy'] = (bool) $merged['apply_builder_policy'];
		$merged['cleanup']              = (bool) $merged['cleanup'];
		$merged['check_capability']     = (bool) $merged['check_capability'];
		$merged['zip_path']             = is_string( $merged['zip_path'] ) ? $merged['zip_path'] : '';
		$merged['workdir']              = is_string( $merged['workdir'] ) ? $merged['workdir'] : '';
		$merged['files']                = is_array( $merged['files'] ) ? $merged['files'] : array();

		return $merged;
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
	 * @param string               $workdir Workdir.
	 * @param array<string, mixed> $options Options.
	 * @return void
	 */
	private static function maybe_cleanup( string $workdir, array $options ): void {
		if ( '' === $workdir || empty( $options['cleanup'] ) ) {
			return;
		}
		TempStorage::delete_workdir( $workdir );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
