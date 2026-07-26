<?php
/**
 * Imports property CPT entities from an HPTP package.
 *
 * @package HvnlyNab\ImportExport\Import
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Import;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Core\GroupFieldIdentity;
use HvnlyNab\ImportExport\Export\TermsExporter;
use HvnlyNab\ImportExport\Package\PackageResult;
use HvnlyNab\ImportExport\Support\PortableFieldDecoder;
use HvnlyNab\Workspace\Api\PropertyBuilderSchemaService;
use HvnlyNab\Workspace\Api\PropertyFormMapper;

defined( 'ABSPATH' ) || exit;

/**
 * PropertiesImporter — reconstructs listings without media attachment creation.
 *
 * @since 3.6.0
 */
final class PropertiesImporter {

	public const META_PENDING_MEDIA = '_hvnly_ie_pending_media';
	public const META_QUARANTINE    = '_hvnly_ie_quarantine_fields';
	public const META_FEATURED      = '_hvnly_property_featured';

	/**
	 * @param EntityReader      $reader Reader.
	 * @param DuplicateDetector $detector Detector.
	 * @param IdRemapper        $remapper Remapper.
	 * @param string            $policy Duplicate policy.
	 * @param array<string,int> $batch Optional {offset,limit}. limit 0 = all.
	 * @return PackageResult
	 */
	public static function import(
		EntityReader $reader,
		DuplicateDetector $detector,
		IdRemapper $remapper,
		string $policy,
		array $batch = array()
	): PackageResult {
		$policy   = DuplicateDetector::normalize_policy( $policy );
		$rows     = $reader->read_section( 'properties' );
		$offset   = isset( $batch['offset'] ) ? max( 0, (int) $batch['offset'] ) : 0;
		$limit    = isset( $batch['limit'] ) ? max( 0, (int) $batch['limit'] ) : 0;
		$total    = count( $rows );

		if ( $limit > 0 ) {
			$rows = array_slice( $rows, $offset, $limit );
		} elseif ( $offset > 0 ) {
			$rows = array_slice( $rows, $offset );
		}

		$created  = 0;
		$updated  = 0;
		$skipped  = 0;
		$failed   = 0;
		$warnings = array();

		if ( ! post_type_exists( AgentConstants::PROPERTY_POST_TYPE ) ) {
			return PackageResult::failure(
				'hvnly_ie_property_post_type_missing',
				'Property post type is not registered.',
				array()
			);
		}

		foreach ( $rows as $row ) {
			$result = self::upsert( $row, $detector, $remapper, $policy );
			$created += $result['created'];
			$updated += $result['updated'];
			$skipped += $result['skipped'];
			$failed  += $result['failed'];
			foreach ( $result['warnings'] as $warning ) {
				$warnings[] = $warning;
			}
		}

		$next = $offset + count( $rows );

		return PackageResult::success(
			array(
				'created'  => $created,
				'updated'  => $updated,
				'skipped'  => $skipped,
				'failed'   => $failed,
				'offset'   => $offset,
				'processed'=> count( $rows ),
				'next'     => $next,
				'total'    => $total,
				'done'     => $next >= $total,
			),
			$warnings
		);
	}

	/**
	 * @param array<string, mixed> $row Row.
	 * @param DuplicateDetector    $detector Detector.
	 * @param IdRemapper           $remapper Remapper.
	 * @param string               $policy Policy.
	 * @return array{created:int,updated:int,skipped:int,failed:int,warnings:array}
	 */
	private static function upsert(
		array $row,
		DuplicateDetector $detector,
		IdRemapper $remapper,
		string $policy
	): array {
		$out = array(
			'created'  => 0,
			'updated'  => 0,
			'skipped'  => 0,
			'failed'   => 0,
			'warnings' => array(),
		);

		$slug  = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		$unique = trim( (string) ( $row['unique_property_id'] ?? '' ) );

		if ( '' === $title ) {
			$out['failed']     = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_property_invalid',
				'message' => 'Property row missing title.',
				'context' => array( 'slug' => $slug ),
			);
			return $out;
		}

		$existing_id = $detector->find_property( $row );

		if ( $existing_id > 0 && DuplicateDetector::POLICY_CREATE_NEW !== $policy ) {
			$remapper->set_property( $unique, $slug, $existing_id );
			if ( DuplicateDetector::POLICY_SKIP === $policy ) {
				// Keep pending media stubs so Phase 6 can restore even when entity data is skipped.
				$ok = self::persist_pending_media( $existing_id, $row, $remapper, $out['warnings'] );
				if ( ! $ok ) {
					$out['failed'] = 1;
					return $out;
				}
				$out['skipped'] = 1;
				return $out;
			}

			if ( ! self::write_property( $existing_id, $row, $remapper, $policy, $out['warnings'] ) ) {
				$out['failed'] = 1;
				return $out;
			}
			$out['updated'] = 1;
			return $out;
		}

		$insert_slug = $slug !== '' ? $slug : sanitize_title( $title );
		if ( $existing_id > 0 && DuplicateDetector::POLICY_CREATE_NEW === $policy ) {
			$insert_slug = self::unique_slug( $insert_slug );
		}

		$status = self::normalize_status( (string) ( $row['status'] ?? 'publish' ) );
		$author = self::resolve_author_id( (string) ( $row['author_email'] ?? '' ), $out['warnings'] );

		$postarr = array(
			'post_title'   => $title,
			'post_name'    => $insert_slug,
			'post_content' => wp_kses_post( (string) ( $row['content'] ?? '' ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $row['excerpt'] ?? '' ) ),
			'post_status'  => $status,
			'post_type'    => AgentConstants::PROPERTY_POST_TYPE,
			'post_author'  => $author,
		);

		if ( ! empty( $row['post_date_gmt'] ) ) {
			$gmt = (string) $row['post_date_gmt'];
			if ( '0000-00-00 00:00:00' !== $gmt ) {
				$postarr['post_date_gmt'] = $gmt;
				$postarr['post_date']     = get_date_from_gmt( $gmt );
			}
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			$out['failed']     = 1;
			$out['warnings'][] = array(
				'code'    => 'hvnly_ie_property_insert_failed',
				'message' => $post_id->get_error_message(),
				'context' => array( 'slug' => $slug, 'unique_property_id' => $unique ),
			);
			return $out;
		}

		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			$out['failed'] = 1;
			return $out;
		}

		if ( ! self::write_property( $post_id, $row, $remapper, DuplicateDetector::POLICY_UPDATE, $out['warnings'] ) ) {
			$out['failed'] = 1;
			return $out;
		}

		$remapper->set_property( $unique, $slug !== '' ? $slug : $insert_slug, $post_id );
		$out['created'] = 1;
		return $out;
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @param IdRemapper           $remapper Remapper.
	 * @param string               $policy Policy (update|overwrite).
	 * @param array                $warnings Warnings.
	 * @return bool
	 */
	private static function write_property(
		int $post_id,
		array $row,
		IdRemapper $remapper,
		string $policy,
		array &$warnings
	): bool {
		$status = self::normalize_status( (string) ( $row['status'] ?? 'publish' ) );
		$author = self::resolve_author_id( (string) ( $row['author_email'] ?? '' ), $warnings );

		$post_update = array(
			'ID'           => $post_id,
			'post_title'   => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
			'post_content' => wp_kses_post( (string) ( $row['content'] ?? '' ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $row['excerpt'] ?? '' ) ),
			'post_status'  => $status,
			'post_author'  => $author,
		);

		if ( ! empty( $row['slug'] ) ) {
			$post_update['post_name'] = sanitize_title( (string) $row['slug'] );
		}

		$result = wp_update_post( wp_slash( $post_update ), true );
		if ( is_wp_error( $result ) ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_property_update_failed',
				'message' => $result->get_error_message(),
				'context' => array( 'post_id' => $post_id ),
			);
			return false;
		}

		if ( DuplicateDetector::POLICY_OVERWRITE === $policy ) {
			self::clear_schema_meta( $post_id );
		}

		$fields_payload = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
		$decoded        = PortableFieldDecoder::decode_property_fields( $fields_payload, $remapper );
		foreach ( $decoded['warnings'] as $warning ) {
			$warnings[] = $warning;
		}
		foreach ( isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) ? $decoded['errors'] : array() as $error ) {
			$warnings[] = $error;
		}

		// BLOCKER: never silently discard quarantined property data.
		if ( ! empty( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
			$warnings[] = array(
				'code'    => 'hvnly_ie_property_data_loss_aborted',
				'message' => 'Property import aborted: Builder groups/fields could not be remapped without data loss.',
				'context' => array(
					'post_id'       => $post_id,
					'property_slug' => sanitize_title( (string) ( $row['slug'] ?? '' ) ),
					'error_count'   => count( $decoded['errors'] ),
				),
			);
			if ( ! empty( $decoded['quarantine'] ) && is_array( $decoded['quarantine'] ) ) {
				update_post_meta( $post_id, self::META_QUARANTINE, $decoded['quarantine'] );
			}
			return false;
		}

		$mapper_values = array(
			'title'       => (string) ( $row['title'] ?? '' ),
			'excerpt'     => (string) ( $row['excerpt'] ?? '' ),
			'description' => (string) ( $row['content'] ?? '' ),
			'fields'      => $decoded['fields'],
		);

		$terms = isset( $row['terms'] ) && is_array( $row['terms'] ) ? $row['terms'] : array();
		self::append_taxonomy_values( $mapper_values, $terms );

		// Resolve listing agents BEFORE mapper so Builder agents JSON is not written as [].
		$agent_ids = self::resolve_property_agent_ids( $row, $remapper, $warnings );
		if ( ! empty( $agent_ids ) ) {
			$mapper_values['fields'] = self::inject_agent_ids_into_fields( $mapper_values['fields'], $agent_ids );
			update_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS, $agent_ids );
		} else {
			delete_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS );
		}

		if ( class_exists( PropertyFormMapper::class ) ) {
			PropertyFormMapper::apply_values( $post_id, $mapper_values, true );
		} else {
			self::write_fields_direct( $post_id, $mapper_values['fields'] );
			self::write_terms_direct( $post_id, $terms );
		}

		// Re-assert package post status (apply_values may preserve workspace draft workflow).
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $status,
			)
		);

		self::write_identity_meta( $post_id, $row );
		// Re-assert SSOT after mapper (sole-group sync may have run with same IDs).
		if ( ! empty( $agent_ids ) ) {
			update_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS, $agent_ids );
		}
		self::write_field_map( $post_id, $row, $mapper_values['fields'] );
		self::persist_pending_media_from_decoded( $post_id, $row, $decoded, $warnings );

		if ( ! empty( $row['ws_listing_status'] ) && class_exists( PropertyFormMapper::class ) ) {
			update_post_meta(
				$post_id,
				PropertyFormMapper::META_LISTING_STATUS,
				sanitize_text_field( (string) $row['ws_listing_status'] )
			);
		}

		return true;
	}

	/**
	 * Decode and store media stubs for Phase 6 without rewriting property fields.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @param IdRemapper           $remapper Remapper.
	 * @param array                $warnings Warnings.
	 * @return bool False when remap would lose data.
	 */
	private static function persist_pending_media( int $post_id, array $row, IdRemapper $remapper, array &$warnings ): bool {
		$fields_payload = isset( $row['fields'] ) && is_array( $row['fields'] ) ? $row['fields'] : array();
		$decoded        = PortableFieldDecoder::decode_property_fields( $fields_payload, $remapper );
		return self::persist_pending_media_from_decoded( $post_id, $row, $decoded, $warnings );
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @param array<string, mixed> $decoded Decoder payload.
	 * @param array                $warnings Warnings.
	 * @return bool False when remap would lose data.
	 */
	private static function persist_pending_media_from_decoded( int $post_id, array $row, array $decoded, array &$warnings ): bool {
		foreach ( isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) ? $decoded['errors'] : array() as $error ) {
			$warnings[] = $error;
		}

		// Skip-path must also refuse silent loss when groups cannot be remapped.
		if ( ! empty( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
			if ( ! empty( $decoded['quarantine'] ) && is_array( $decoded['quarantine'] ) ) {
				update_post_meta( $post_id, self::META_QUARANTINE, $decoded['quarantine'] );
			}
			$warnings[] = array(
				'code'    => 'hvnly_ie_property_data_loss_aborted',
				'message' => 'Pending media was not stored because Builder groups could not be remapped without data loss.',
				'context' => array(
					'post_id'     => $post_id,
					'error_count' => count( $decoded['errors'] ),
				),
			);
			return false;
		}

		$pending = isset( $decoded['pending_media'] ) && is_array( $decoded['pending_media'] )
			? $decoded['pending_media']
			: array();

		if ( isset( $row['featured_image'] ) && PortableFieldDecoder::is_media_stub( $row['featured_image'] ) ) {
			$pending['__featured_image'] = $row['featured_image'];
		}

		if ( ! empty( $pending ) ) {
			update_post_meta( $post_id, self::META_PENDING_MEDIA, $pending );
			$warnings[] = array(
				'code'    => 'hvnly_ie_media_deferred',
				'message' => 'Property media stubs stored for Phase 6; no attachments created yet.',
				'context' => array(
					'post_id'     => $post_id,
					'stub_fields' => array_keys( $pending ),
				),
			);
		} else {
			delete_post_meta( $post_id, self::META_PENDING_MEDIA );
		}

		delete_post_meta( $post_id, self::META_QUARANTINE );
		return true;
	}

	/**
	 * @param array<string, mixed> $values Mapper values (by ref).
	 * @param array<string, mixed> $terms  Taxonomy slug map.
	 * @return void
	 */
	private static function append_taxonomy_values( array &$values, array $terms ): void {
		$map = array(
			'hvnly_prop_types'     => array( 'key' => 'propertyType', 'multiple' => false ),
			'hvnly_prop_depts'     => array( 'key' => 'propertyDepartment', 'multiple' => false ),
			'hvnly_prop_status'    => array( 'key' => 'propertyStatus', 'multiple' => true ),
			'hvnly_prop_features'  => array( 'key' => 'propertyFeaturesTax', 'multiple' => true ),
			'hvnly_prop_locations' => array( 'key' => 'propertyLocations', 'multiple' => true ),
			'hvnly_prop_tags'      => array( 'key' => 'propertyTags', 'multiple' => true ),
			'hvnly_prop_badges'    => array( 'key' => 'propertyBadges', 'multiple' => true ),
		);

		foreach ( $map as $taxonomy => $cfg ) {
			if ( ! isset( $terms[ $taxonomy ] ) || ! is_array( $terms[ $taxonomy ] ) ) {
				continue;
			}
			$slugs = array_values( array_filter( array_map( 'sanitize_title', array_map( 'strval', $terms[ $taxonomy ] ) ) ) );
			if ( $cfg['multiple'] ) {
				$values[ $cfg['key'] ] = $slugs;
			} else {
				$values[ $cfg['key'] ] = ! empty( $slugs[0] ) ? $slugs[0] : '';
			}
		}
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @return void
	 */
	private static function write_identity_meta( int $post_id, array $row ): void {
		$unique = trim( (string) ( $row['unique_property_id'] ?? '' ) );
		$mls    = trim( (string) ( $row['mls_number'] ?? '' ) );
		$ref    = trim( (string) ( $row['reference_number'] ?? '' ) );

		if ( '' !== $unique ) {
			update_post_meta( $post_id, DuplicateDetector::META_UNIQUE_ID, sanitize_text_field( $unique ) );
		}
		if ( '' !== $mls ) {
			update_post_meta( $post_id, DuplicateDetector::META_MLS, sanitize_text_field( $mls ) );
		}
		if ( '' !== $ref ) {
			update_post_meta( $post_id, DuplicateDetector::META_REFERENCE, sanitize_text_field( $ref ) );
		}

		$featured = ! empty( $row['featured'] ) ? '1' : '';
		if ( '' !== $featured ) {
			update_post_meta( $post_id, self::META_FEATURED, '1' );
		} else {
			delete_post_meta( $post_id, self::META_FEATURED );
		}
	}

	/**
	 * Resolve property → agent CPT IDs (remapper, then live duplicate lookup).
	 *
	 * @param array<string, mixed> $row Row.
	 * @param IdRemapper           $remapper Remapper.
	 * @param array                $warnings Warnings.
	 * @return int[]
	 */
	private static function resolve_property_agent_ids( array $row, IdRemapper $remapper, array &$warnings ): array {
		$refs = isset( $row['agents'] ) && is_array( $row['agents'] ) ? $row['agents'] : array();
		$ids  = array();
		foreach ( $refs as $ref ) {
			if ( ! is_array( $ref ) ) {
				continue;
			}
			$email = (string) ( $ref['email'] ?? '' );
			$slug  = (string) ( $ref['slug'] ?? '' );
			$id    = $remapper->get_agent( $email, $slug );
			if ( $id <= 0 ) {
				$detector = new DuplicateDetector();
				$id       = $detector->find_agent( $email, $slug, $ref );
			}
			if ( $id <= 0 ) {
				$warnings[] = array(
					'code'    => 'hvnly_ie_property_agent_missing',
					'message' => 'Property agent reference could not be resolved.',
					'context' => array(
						'property_slug' => sanitize_title( (string) ( $row['slug'] ?? '' ) ),
						'email'         => $email,
						'slug'          => $slug,
					),
				);
				continue;
			}
			$ids[] = $id;
		}

		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Inject resolved agent IDs into Builder agents storage fields (never leave []).
	 *
	 * @param array<string, mixed> $fields Decoded fields.
	 * @param int[]                $agent_ids Agent CPT IDs.
	 * @return array<string, mixed>
	 */
	private static function inject_agent_ids_into_fields( array $fields, array $agent_ids ): array {
		$agent_ids = array_values( array_unique( array_map( 'absint', $agent_ids ) ) );
		if ( empty( $agent_ids ) ) {
			return $fields;
		}

		$patched = false;
		if ( class_exists( PropertyBuilderSchemaService::class ) ) {
			foreach ( PropertyBuilderSchemaService::collect_storage_fields() as $row ) {
				$meta = (string) ( $row['metaKey'] ?? '' );
				$comp = (string) ( $row['component'] ?? '' );
				$name = (string) ( $row['name'] ?? '' );
				$is_agents = ( 'agents' === $meta )
					|| ( 'agents' === $comp && ( 'agents' === $meta || substr( $name, -7 ) === '_agents' ) );
				if ( ! $is_agents || '' === $name ) {
					continue;
				}
				$current = isset( $fields[ $name ] ) && is_array( $fields[ $name ] ) ? $fields[ $name ] : array();
				$current_ids = array();
				foreach ( $current as $id ) {
					$id = absint( $id );
					if ( $id > 0 ) {
						$current_ids[] = $id;
					}
				}
				if ( empty( $current_ids ) ) {
					$fields[ $name ] = $agent_ids;
					$patched         = true;
				}
			}
		}

		if ( ! $patched ) {
			// Fallback: patch any empty list already present under *_agents keys.
			foreach ( $fields as $name => $value ) {
				if ( ! is_string( $name ) || substr( $name, -7 ) !== '_agents' ) {
					continue;
				}
				$current_ids = array();
				if ( is_array( $value ) ) {
					foreach ( $value as $id ) {
						$id = absint( $id );
						if ( $id > 0 ) {
							$current_ids[] = $id;
						}
					}
				}
				if ( empty( $current_ids ) ) {
					$fields[ $name ] = $agent_ids;
				}
			}
		}

		return $fields;
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @param IdRemapper           $remapper Remapper.
	 * @param array                $warnings Warnings.
	 * @return void
	 */
	private static function write_agents_ssot( int $post_id, array $row, IdRemapper $remapper, array &$warnings ): void {
		$ids = self::resolve_property_agent_ids( $row, $remapper, $warnings );
		if ( ! empty( $ids ) ) {
			update_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS, $ids );
		} else {
			delete_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS );
		}
	}

	/**
	 * Restore / record Builder field map entries for written groups.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row Row.
	 * @param array<string, mixed> $written_fields Written mapper fields.
	 * @return void
	 */
	private static function write_field_map( int $post_id, array $row, array $written_fields ): void {
		if ( ! class_exists( GroupFieldIdentity::class ) || ! class_exists( PropertyBuilderSchemaService::class ) ) {
			return;
		}

		// Prefer package field_map groups when destination schema shares the same group_id.
		$package_map = isset( $row['field_map'] ) && is_array( $row['field_map'] ) ? $row['field_map'] : array();
		$pkg_groups  = isset( $package_map['groups'] ) && is_array( $package_map['groups'] ) ? $package_map['groups'] : array();

		foreach ( PropertyBuilderSchemaService::collect_storage_fields() as $storage_row ) {
			$group_id      = (string) ( $storage_row['groupId'] ?? '' );
			$group_base_id = (string) ( $storage_row['groupBaseId'] ?? '' );
			$group_type    = (string) ( $storage_row['groupType'] ?? '' );
			$name          = (string) ( $storage_row['name'] ?? '' );

			if ( '' === $group_id || '' === $group_base_id ) {
				continue;
			}
			if ( '' !== $name && ! array_key_exists( $name, $written_fields ) && ! isset( $pkg_groups[ $group_id ] ) ) {
				continue;
			}

			// Always map to destination base IDs (Keep policy). Package bases are not authoritative.
			GroupFieldIdentity::record_group_in_field_map( $post_id, $group_id, $group_base_id, $group_type );
		}
	}

	/**
	 * Clear destination schema storage keys before overwrite.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function clear_schema_meta( int $post_id ): void {
		if ( ! class_exists( PropertyBuilderSchemaService::class ) ) {
			return;
		}
		foreach ( PropertyBuilderSchemaService::collect_storage_fields() as $row ) {
			$name = (string) ( $row['name'] ?? '' );
			if ( '' !== $name ) {
				delete_post_meta( $post_id, $name );
			}
		}
		delete_post_meta( $post_id, self::META_PENDING_MEDIA );
		delete_post_meta( $post_id, self::META_QUARANTINE );
		if ( class_exists( GroupFieldIdentity::class ) ) {
			delete_post_meta( $post_id, GroupFieldIdentity::FIELD_MAP_META );
		}
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $fields Fields.
	 * @return void
	 */
	private static function write_fields_direct( int $post_id, array $fields ): void {
		foreach ( $fields as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key ) {
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				update_post_meta( $post_id, $key, wp_json_encode( $value ) );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $terms Terms.
	 * @return void
	 */
	private static function write_terms_direct( int $post_id, array $terms ): void {
		foreach ( TermsExporter::TAXONOMIES as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! isset( $terms[ $taxonomy ] ) || ! is_array( $terms[ $taxonomy ] ) ) {
				continue;
			}
			$slugs = array_values( array_filter( array_map( 'sanitize_title', array_map( 'strval', $terms[ $taxonomy ] ) ) ) );
			wp_set_object_terms( $post_id, $slugs, $taxonomy, false );
		}
	}

	/**
	 * @param string $email Author email.
	 * @param array  $warnings Warnings.
	 * @return int
	 */
	private static function resolve_author_id( string $email, array &$warnings ): int {
		$email = strtolower( sanitize_email( $email ) );
		if ( '' !== $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user instanceof \WP_User ) {
				return (int) $user->ID;
			}
			$warnings[] = array(
				'code'    => 'hvnly_ie_property_author_missing',
				'message' => 'Author email not found; falling back to current user.',
				'context' => array( 'author_email' => $email ),
			);
		}

		$current = get_current_user_id();
		return $current > 0 ? $current : 1;
	}

	/**
	 * @param string $status Status.
	 * @return string
	 */
	private static function normalize_status( string $status ): string {
		$status  = sanitize_key( $status );
		$allowed = array( 'publish', 'draft', 'pending', 'private', 'expired' );
		return in_array( $status, $allowed, true ) ? $status : 'draft';
	}

	/**
	 * @param string $slug Base slug.
	 * @return string
	 */
	private static function unique_slug( string $slug ): string {
		$candidate = $slug;
		$i         = 2;
		while ( $i < 1000 ) {
			$exists = get_page_by_path( $candidate, OBJECT, AgentConstants::PROPERTY_POST_TYPE );
			if ( ! ( $exists instanceof \WP_Post ) ) {
				return $candidate;
			}
			$candidate = $slug . '-' . $i;
			++$i;
		}
		return $slug . '-' . wp_generate_password( 6, false, false );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
