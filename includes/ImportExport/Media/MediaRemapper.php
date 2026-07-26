<?php
/**
 * Remaps packaged media onto imported Havenlytics entities.
 *
 * @package HvnlyNab\ImportExport\Media
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Media;

use HvnlyNab\Agent\AgencyFields;
use HvnlyNab\ImportExport\Import\EntityReader;
use HvnlyNab\ImportExport\Import\IdRemapper;
use HvnlyNab\ImportExport\Import\PropertiesImporter;
use HvnlyNab\ImportExport\Package\PackageResult;
use HvnlyNab\ImportExport\Support\PortableFieldDecoder;
use HvnlyNab\Workspace\Api\PropertyMediaService;

defined( 'ABSPATH' ) || exit;

/**
 * MediaRemapper — featured, gallery, docs, video, agent photos, agency logos, term images.
 *
 * @since 3.6.0
 */
final class MediaRemapper {

	/**
	 * Apply media map to imported entities.
	 *
	 * @param UrlRewriter  $rewriter Stub resolver.
	 * @param EntityReader $reader   Entity reader.
	 * @param IdRemapper   $remapper Entity ID remapper.
	 * @return PackageResult
	 */
	public static function apply( UrlRewriter $rewriter, EntityReader $reader, IdRemapper $remapper ): PackageResult {
		$warnings = array();
		$stats    = array(
			'properties' => 0,
			'agents'     => 0,
			'agencies'   => 0,
			'terms'      => 0,
			'fields'     => 0,
			'missing'    => 0,
		);

		self::remap_properties( $rewriter, $remapper, $stats, $warnings );
		self::remap_agents( $rewriter, $reader, $remapper, $stats, $warnings );
		self::remap_agencies( $rewriter, $reader, $remapper, $stats, $warnings );
		self::remap_terms( $rewriter, $reader, $remapper, $stats, $warnings );

		return PackageResult::success(
			array(
				'stats' => $stats,
				'map'   => $rewriter->map(),
			),
			$warnings
		);
	}

	/**
	 * @param UrlRewriter $rewriter Rewriter.
	 * @param IdRemapper  $remapper Remapper.
	 * @param array       $stats    Stats.
	 * @param array       $warnings Warnings.
	 * @return void
	 */
	private static function remap_properties( UrlRewriter $rewriter, IdRemapper $remapper, array &$stats, array &$warnings ): void {
		$map = $remapper->to_array();
		$ids = array();
		foreach ( array( 'properties_by_unique', 'properties_by_slug' ) as $bucket ) {
			if ( empty( $map[ $bucket ] ) || ! is_array( $map[ $bucket ] ) ) {
				continue;
			}
			foreach ( $map[ $bucket ] as $post_id ) {
				$post_id = absint( $post_id );
				if ( $post_id > 0 ) {
					$ids[ $post_id ] = true;
				}
			}
		}

		foreach ( array_keys( $ids ) as $post_id ) {
			$pending = get_post_meta( $post_id, PropertiesImporter::META_PENDING_MEDIA, true );
			if ( ! is_array( $pending ) || empty( $pending ) ) {
				continue;
			}

			$remaining = array();
			$touched   = false;

			foreach ( $pending as $field => $value ) {
				$field = (string) $field;

				if ( '__featured_image' === $field ) {
					$id = $rewriter->resolve_id( $value );
					if ( $id <= 0 ) {
						$remaining[ $field ] = $value;
						++$stats['missing'];
						$warnings[] = self::missing_warning( 'property_featured', $post_id, $value );
						continue;
					}
					if ( class_exists( PropertyMediaService::class ) ) {
						$result = PropertyMediaService::set_featured( $post_id, $id );
						if ( is_wp_error( $result ) ) {
							set_post_thumbnail( $post_id, $id );
						}
					} else {
						set_post_thumbnail( $post_id, $id );
					}
					$touched = true;
					++$stats['fields'];
					continue;
				}

				if ( substr( $field, -strlen( '::__documents' ) ) === '::__documents' ) {
					$meta_key = substr( $field, 0, -strlen( '::__documents' ) );
					self::restore_documents_field( $post_id, $meta_key, $value, $rewriter, $remaining, $field, $stats, $warnings, $touched );
					continue;
				}

				if ( PortableFieldDecoder::is_media_stub_list( $value ) && ! PortableFieldDecoder::is_media_stub( $value ) ) {
					// Gallery images list.
					$ids_list = $rewriter->resolve_id_list( $value );
					$expected = is_array( $value ) ? count( $value ) : 0;
					if ( count( $ids_list ) < $expected ) {
						++$stats['missing'];
						$warnings[] = array(
							'code'    => 'hvnly_ie_media_gallery_partial',
							'message' => 'Some gallery images could not be resolved; partial gallery restored.',
							'context' => array(
								'post_id'   => $post_id,
								'field'     => $field,
								'resolved'  => count( $ids_list ),
								'expected'  => $expected,
							),
						);
					}
					if ( empty( $ids_list ) ) {
						$remaining[ $field ] = $value;
						continue;
					}
					self::write_gallery_field( $post_id, $field, $ids_list );
					$touched = true;
					++$stats['fields'];
					continue;
				}

				if ( PortableFieldDecoder::is_media_stub( $value ) ) {
					$id = $rewriter->resolve_id( $value );
					if ( $id <= 0 ) {
						$remaining[ $field ] = $value;
						++$stats['missing'];
						$warnings[] = self::missing_warning( 'property_field', $post_id, $value, $field );
						continue;
					}

					if ( '_thumbnail_id' === $field ) {
						update_post_meta( $post_id, $field, (string) $id );
					} else {
						$url = wp_get_attachment_url( $id );
						if ( ! is_string( $url ) || '' === $url ) {
							$remaining[ $field ] = $value;
							continue;
						}
						update_post_meta( $post_id, $field, esc_url_raw( $url ) );
					}
					$touched = true;
					++$stats['fields'];
					continue;
				}

				$remaining[ $field ] = $value;
			}

			if ( empty( $remaining ) ) {
				delete_post_meta( $post_id, PropertiesImporter::META_PENDING_MEDIA );
			} else {
				update_post_meta( $post_id, PropertiesImporter::META_PENDING_MEDIA, $remaining );
			}

			if ( $touched ) {
				++$stats['properties'];
			}
		}
	}

	/**
	 * @param int         $post_id Post ID.
	 * @param string      $meta_key Meta key.
	 * @param mixed       $pending_docs Pending docs stubs.
	 * @param UrlRewriter $rewriter Rewriter.
	 * @param array       $remaining Remaining pending.
	 * @param string      $pending_key Pending key.
	 * @param array       $stats Stats.
	 * @param array       $warnings Warnings.
	 * @param bool        $touched Touched flag.
	 * @return void
	 */
	private static function restore_documents_field(
		int $post_id,
		string $meta_key,
		$pending_docs,
		UrlRewriter $rewriter,
		array &$remaining,
		string $pending_key,
		array &$stats,
		array &$warnings,
		bool &$touched
	): void {
		$existing = get_post_meta( $post_id, $meta_key, true );
		$rows     = array();
		if ( is_string( $existing ) && '' !== $existing ) {
			$decoded = json_decode( $existing, true );
			$rows    = is_array( $decoded ) ? $decoded : array();
		} elseif ( is_array( $existing ) ) {
			$rows = $existing;
		}

		$pending_map = is_array( $pending_docs ) ? $pending_docs : array();

		// If property-phase normalize dropped empty rows, rebuild skeletons from pending stubs.
		if ( empty( $rows ) && ! empty( $pending_map ) ) {
			ksort( $pending_map, SORT_NUMERIC );
			foreach ( $pending_map as $index => $stub ) {
				$label = '';
				if ( is_array( $stub ) && ! empty( $stub['filename'] ) ) {
					$label = (string) $stub['filename'];
				}
				$rows[] = array(
					'icon'     => '',
					'label'    => '' !== $label ? $label : 'Document',
					'url'      => '',
					'url_type' => 'custom',
				);
			}
		}

		$rewritten = $rewriter->rewrite_documents( $rows, $pending_map );
		foreach ( $rewritten['missing'] as $export_key ) {
			++$stats['missing'];
			$warnings[] = array(
				'code'    => 'hvnly_ie_media_document_missing',
				'message' => 'Document media stub could not be resolved.',
				'context' => array(
					'post_id'    => $post_id,
					'meta_key'   => $meta_key,
					'export_key' => $export_key,
				),
			);
		}

		update_post_meta( $post_id, $meta_key, wp_json_encode( array_values( $rewritten['rows'] ) ) );
		$touched = true;
		++$stats['fields'];

		if ( ! empty( $rewritten['missing'] ) ) {
			$remaining[ $pending_key ] = $pending_docs;
		}
	}

	/**
	 * @param int   $post_id Post ID.
	 * @param string $field Field / meta key.
	 * @param int[] $ids Attachment IDs.
	 * @return void
	 */
	private static function write_gallery_field( int $post_id, string $field, array $ids ): void {
		$csv = implode( ',', array_map( 'absint', $ids ) );
		update_post_meta( $post_id, $field, $csv );

		if ( ! class_exists( PropertyMediaService::class ) ) {
			return;
		}

		$builder_key = PropertyMediaService::builder_gallery_meta_key();
		if ( $field === $builder_key || $field === PropertyMediaService::META_GALLERY ) {
			PropertyMediaService::set_gallery_ids( $post_id, $ids );
		}
	}

	/**
	 * @param UrlRewriter  $rewriter Rewriter.
	 * @param EntityReader $reader Reader.
	 * @param IdRemapper   $remapper Remapper.
	 * @param array        $stats Stats.
	 * @param array        $warnings Warnings.
	 * @return void
	 */
	private static function remap_agents(
		UrlRewriter $rewriter,
		EntityReader $reader,
		IdRemapper $remapper,
		array &$stats,
		array &$warnings
	): void {
		foreach ( $reader->read_section( 'agents' ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['photo'] ) ) {
				continue;
			}
			$post_id = $remapper->get_agent(
				(string) ( $row['email'] ?? '' ),
				(string) ( $row['slug'] ?? '' )
			);
			if ( $post_id <= 0 ) {
				continue;
			}
			$id = $rewriter->resolve_id( $row['photo'] );
			if ( $id <= 0 ) {
				++$stats['missing'];
				$warnings[] = self::missing_warning( 'agent_photo', $post_id, $row['photo'] );
				continue;
			}
			set_post_thumbnail( $post_id, $id );
			++$stats['agents'];
			++$stats['fields'];
		}
	}

	/**
	 * @param UrlRewriter  $rewriter Rewriter.
	 * @param EntityReader $reader Reader.
	 * @param IdRemapper   $remapper Remapper.
	 * @param array        $stats Stats.
	 * @param array        $warnings Warnings.
	 * @return void
	 */
	private static function remap_agencies(
		UrlRewriter $rewriter,
		EntityReader $reader,
		IdRemapper $remapper,
		array &$stats,
		array &$warnings
	): void {
		foreach ( $reader->read_section( 'agencies' ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['logo'] ) ) {
				continue;
			}
			$term_id = $remapper->get_agency( (string) ( $row['slug'] ?? '' ) );
			if ( $term_id <= 0 ) {
				continue;
			}
			$id = $rewriter->resolve_id( $row['logo'] );
			if ( $id <= 0 ) {
				++$stats['missing'];
				$warnings[] = self::missing_warning( 'agency_logo', $term_id, $row['logo'] );
				continue;
			}
			if ( class_exists( AgencyFields::class ) ) {
				update_term_meta( $term_id, AgencyFields::META_LOGO_ID, $id );
			} else {
				update_term_meta( $term_id, 'hvnly_agency_logo_id', $id );
			}
			++$stats['agencies'];
			++$stats['fields'];
		}
	}

	/**
	 * @param UrlRewriter  $rewriter Rewriter.
	 * @param EntityReader $reader Reader.
	 * @param IdRemapper   $remapper Remapper.
	 * @param array        $stats Stats.
	 * @param array        $warnings Warnings.
	 * @return void
	 */
	private static function remap_terms(
		UrlRewriter $rewriter,
		EntityReader $reader,
		IdRemapper $remapper,
		array &$stats,
		array &$warnings
	): void {
		foreach ( $reader->read_section( 'terms' ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$meta = isset( $row['meta'] ) && is_array( $row['meta'] ) ? $row['meta'] : array();
			if ( empty( $meta['image'] ) || ! PortableFieldDecoder::is_media_stub( $meta['image'] ) ) {
				continue;
			}
			$term_id = $remapper->get_term(
				(string) ( $row['taxonomy'] ?? '' ),
				(string) ( $row['slug'] ?? '' )
			);
			if ( $term_id <= 0 ) {
				continue;
			}
			$id = $rewriter->resolve_id( $meta['image'] );
			if ( $id <= 0 ) {
				++$stats['missing'];
				$warnings[] = self::missing_warning( 'term_image', $term_id, $meta['image'] );
				continue;
			}
			$url  = wp_get_attachment_url( $id );
			$data = array(
				'id'  => $id,
				'url' => is_string( $url ) ? $url : '',
			);
			// Preserve stub alt/title when present.
			if ( is_array( $meta['image'] ) ) {
				if ( ! empty( $meta['image']['alt'] ) ) {
					$data['alt'] = sanitize_text_field( (string) $meta['image']['alt'] );
				}
				if ( ! empty( $meta['image']['title'] ) ) {
					$data['title'] = sanitize_text_field( (string) $meta['image']['title'] );
				}
			}
			update_term_meta( $term_id, '_hvnly_term_advanced_image_data', $data );
			++$stats['terms'];
			++$stats['fields'];
		}
	}

	/**
	 * @param string $kind Kind.
	 * @param int    $entity_id Entity ID.
	 * @param mixed  $stub Stub.
	 * @param string $field Field.
	 * @return array{code:string,message:string,context:array}
	 */
	private static function missing_warning( string $kind, int $entity_id, $stub, string $field = '' ): array {
		$key = '';
		if ( is_array( $stub ) ) {
			$key = (string) ( $stub['export_key'] ?? '' );
		}
		return array(
			'code'    => 'hvnly_ie_media_stub_unresolved',
			'message' => 'Media stub could not be mapped to a local attachment; import continues.',
			'context' => array(
				'kind'       => $kind,
				'entity_id'  => $entity_id,
				'export_key' => $key,
				'field'      => $field,
			),
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
