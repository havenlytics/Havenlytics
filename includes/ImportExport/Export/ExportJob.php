<?php
/**
 * Entity + media export orchestration (no UI / import).
 *
 * @package HvnlyNab\ImportExport\Export
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Export;

use HvnlyNab\ImportExport\Media\MediaIndexer;
use HvnlyNab\ImportExport\Media\MediaPacker;
use HvnlyNab\ImportExport\Package\ManifestSchema;
use HvnlyNab\ImportExport\Package\PackageResult;
use HvnlyNab\ImportExport\Package\PackageWriter;
use HvnlyNab\ImportExport\Support\PortableFieldEncoder;

defined( 'ABSPATH' ) || exit;

/**
 * Builds an HPTP package with entities and optional media binaries.
 *
 * @since 3.6.0
 */
final class ExportJob {

	/**
	 * Run a full entity (+ media) export.
	 *
	 * @param array<string, mixed> $options Export options.
	 * @return PackageResult data includes zip_path, entities, manifest, warnings.
	 */
	public static function run( array $options = array() ): PackageResult {
		$options  = self::normalize_options( $options );
		$warnings = array();
		$encoder  = new PortableFieldEncoder();
		$encoder->reset_media_catalog();

		$builders   = array();
		$terms      = array();
		$agencies   = array();
		$agents     = array();
		$properties = array();

		if ( ! empty( $options['include_builders'] ) ) {
			$builders = BuildersExporter::export();
		}

		if ( ! empty( $options['include_taxonomies'] ) ) {
			$terms = TermsExporter::export( $encoder );
		}

		if ( ! empty( $options['include_agencies'] ) ) {
			$agencies = AgenciesExporter::export( $encoder );
		}

		if ( ! empty( $options['include_agents'] ) ) {
			$agents = AgentsExporter::export( $encoder );
		}

		if ( ! empty( $options['include_properties'] ) ) {
			$properties = PropertiesExporter::export( $encoder, $options );
		}

		$media_catalog_raw = $encoder->media_catalog();
		$media_files       = array();
		$media_index       = array( 'files' => array() );
		$media_catalog     = array();
		$packaged_count    = 0;
		$unique_binaries   = 0;
		$include_media     = ! empty( $options['include_media'] );

		if ( $include_media && ! empty( $media_catalog_raw ) ) {
			$indexed = MediaIndexer::index( $media_catalog_raw );
			if ( ! $indexed->ok() ) {
				return $indexed;
			}
			foreach ( $indexed->warnings() as $warning ) {
				$warnings[] = $warning;
			}

			$index_data = $indexed->data();
			$packed     = MediaPacker::pack( isset( $index_data['entries'] ) ? $index_data['entries'] : array() );
			if ( ! $packed->ok() ) {
				return $packed;
			}
			foreach ( $packed->warnings() as $warning ) {
				$warnings[] = $warning;
			}

			$pack_data       = $packed->data();
			$media_files     = isset( $pack_data['binaries'] ) ? $pack_data['binaries'] : array();
			$media_index     = isset( $pack_data['index'] ) ? $pack_data['index'] : array( 'files' => array() );
			$media_catalog   = isset( $pack_data['catalog'] ) ? $pack_data['catalog'] : array();
			$packaged_count  = isset( $pack_data['packaged_count'] ) ? (int) $pack_data['packaged_count'] : 0;
			$unique_binaries = isset( $pack_data['unique_binary_count'] ) ? (int) $pack_data['unique_binary_count'] : 0;

			// Stubs that were not packaged remain as unbundled catalog entries.
			$bundled_keys = array();
			foreach ( $media_catalog as $row ) {
				if ( ! empty( $row['export_key'] ) ) {
					$bundled_keys[ (string) $row['export_key'] ] = true;
				}
			}
			foreach ( $media_catalog_raw as $stub ) {
				$key = isset( $stub['export_key'] ) ? (string) $stub['export_key'] : '';
				if ( '' === $key || isset( $bundled_keys[ $key ] ) ) {
					continue;
				}
				$media_catalog[] = array(
					'export_key' => $key,
					'filename'   => isset( $stub['filename'] ) ? (string) $stub['filename'] : '',
					'mime_type'  => isset( $stub['mime_type'] ) ? (string) $stub['mime_type'] : '',
					'bundled'    => false,
				);
			}
		} elseif ( ! empty( $media_catalog_raw ) && ! $include_media ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_export_media_disabled',
				'message' => 'Media stubs were discovered but include_media is disabled; binaries were not packaged.',
				'context' => array( 'media_stub_count' => count( $media_catalog_raw ) ),
			);
			foreach ( $media_catalog_raw as $stub ) {
				$media_catalog[] = array(
					'export_key' => (string) ( $stub['export_key'] ?? '' ),
					'filename'   => (string) ( $stub['filename'] ?? '' ),
					'mime_type'  => (string) ( $stub['mime_type'] ?? '' ),
					'bundled'    => false,
				);
			}
		}

		$has_media_binaries = $packaged_count > 0 && ! empty( $media_files );

		$entities = array(
			'schema_version' => ManifestSchema::SCHEMA_VERSION,
			'builders'       => $builders,
			'terms'          => $terms,
			'agencies'       => $agencies,
			'agents'         => $agents,
			'properties'     => $properties,
			'media_catalog'  => $media_catalog,
		);

		$counts = array(
			'terms'           => count( $terms ),
			'agencies'        => count( $agencies ),
			'agents'          => count( $agents ),
			'properties'      => count( $properties ),
			'media_stubs'     => count( $media_catalog_raw ),
			'media_files'     => $packaged_count,
			'media_binaries'  => $unique_binaries,
			'builders'        => ! empty( $builders ) ? 1 : 0,
		);

		$contents = array(
			'properties'   => ! empty( $options['include_properties'] ),
			'media'        => $has_media_binaries,
			'taxonomies'   => ! empty( $options['include_taxonomies'] ),
			'agents'       => ! empty( $options['include_agents'] ),
			'agencies'     => ! empty( $options['include_agencies'] ),
			'builders'     => ! empty( $options['include_builders'] ),
			'card'         => ! empty( $options['include_builders'] ),
			'search'       => ! empty( $options['include_builders'] ),
			'price_labels' => ! empty( $options['include_builders'] ),
		);

		$manifest = array(
			'format'                     => ManifestSchema::FORMAT,
			'schema_version'             => ManifestSchema::SCHEMA_VERSION,
			'plugin_version_exported'    => defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '',
			'exported_at'                => gmdate( 'c' ),
			'package_name'               => (string) $options['package_name'],
			'description'                => (string) $options['description'],
			'source_site_label'          => (string) $options['source_site_label'],
			'contents'                   => $contents,
			'counts'                     => $counts,
			'builder_schema_fingerprint' => isset( $builders['property']['schema_fingerprint'] )
				? (string) $builders['property']['schema_fingerprint']
				: '',
		);

		$write = PackageWriter::write(
			$manifest,
			$entities,
			$has_media_binaries ? $media_files : array(),
			$has_media_binaries ? $media_index : array()
		);
		if ( ! $write->ok() ) {
			return $write;
		}

		$data = $write->data();
		foreach ( $write->warnings() as $warning ) {
			$warnings[] = $warning;
		}

		$data['entities']     = $entities;
		$data['media_index']  = $has_media_binaries ? $media_index : array( 'files' => array() );
		$data['warnings']     = $warnings;
		$data['counts']       = $counts;

		return PackageResult::success( $data, $warnings );
	}

	/**
	 * @param array<string, mixed> $options Options.
	 * @return array<string, mixed>
	 */
	private static function normalize_options( array $options ): array {
		$site_label = '';
		if ( function_exists( 'get_bloginfo' ) ) {
			$site_label = (string) get_bloginfo( 'name' );
		}

		$defaults = array(
			'package_name'            => 'havenlytics-export-' . gmdate( 'Y-m-d-Hi' ),
			'description'             => '',
			'source_site_label'       => $site_label,
			'include_builders'        => true,
			'include_taxonomies'      => true,
			'include_agencies'        => true,
			'include_agents'          => true,
			'include_properties'      => true,
			'include_media'           => true,
			'include_workflow_status' => false,
			'statuses'                => array( 'publish', 'draft', 'pending', 'private', 'expired' ),
			'date_from'               => '',
			'date_to'                 => '',
		);

		$merged = array_merge( $defaults, $options );

		$name = preg_replace( '/[^a-z0-9-_]+/i', '-', (string) $merged['package_name'] );
		$name = trim( (string) $name, '-' );
		if ( '' === $name ) {
			$name = $defaults['package_name'];
		}
		if ( strlen( $name ) > 80 ) {
			$name = substr( $name, 0, 80 );
		}
		$merged['package_name'] = $name;

		if ( strlen( (string) $merged['description'] ) > 500 ) {
			$merged['description'] = substr( (string) $merged['description'], 0, 500 );
		}

		$merged['include_media'] = (bool) $merged['include_media'];

		return $merged;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
