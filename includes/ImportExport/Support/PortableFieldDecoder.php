<?php
/**
 * Decodes portable HPTP property fields onto the destination Builder schema.
 *
 * @package HvnlyNab\ImportExport\Support
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Support;

use HvnlyNab\ImportExport\Import\IdRemapper;
use HvnlyNab\Workspace\Api\PropertyBuilderSchemaService;

defined( 'ABSPATH' ) || exit;

/**
 * PortableFieldDecoder — maps package fields → destination storage names.
 *
 * Media stubs are collected for Phase 6 and not converted into attachment IDs.
 *
 * Group resolution priority (Site A → Site B safe):
 * 1. portable_key / portable_id (when present on both sides)
 * 2. group_type + group_base_id
 * 3. group_type ordinal among unclaimed destination groups (compatible fallback)
 * 4. exact group_id (same-site / after Builder replace)
 *
 * Unmapped groups with data are returned in quarantine and as fatal errors —
 * callers must not silently discard them.
 *
 * @since 3.6.0
 */
final class PortableFieldDecoder {

	/**
	 * Decode portable property fields for PropertyFormMapper / direct writes.
	 *
	 * @param array<string, mixed> $fields_payload fields.standalone + fields.groups.
	 * @param IdRemapper           $remapper       Agent remapper.
	 * @return array{
	 *   fields: array<string, mixed>,
	 *   pending_media: array<string, mixed>,
	 *   quarantine: array<string, mixed>,
	 *   errors: array<int, array{code:string,message:string,context:array}>,
	 *   warnings: array<int, array{code:string,message:string,context:array}>
	 * }
	 */
	public static function decode_property_fields( array $fields_payload, IdRemapper $remapper ): array {
		$standalone = isset( $fields_payload['standalone'] ) && is_array( $fields_payload['standalone'] )
			? $fields_payload['standalone']
			: array();
		$groups     = isset( $fields_payload['groups'] ) && is_array( $fields_payload['groups'] )
			? $fields_payload['groups']
			: array();

		$schema_index = self::build_schema_index();
		$fields       = array();
		$pending      = array();
		$quarantine   = array();
		$errors       = array();
		$warnings     = array();
		$claimed      = array(); // dest group_id => true

		foreach ( $standalone as $name => $row ) {
			$name  = (string) ( is_array( $row ) ? ( $row['name'] ?? $name ) : $name );
			$value = is_array( $row ) && array_key_exists( 'value', $row ) ? $row['value'] : $row;

			if ( '' === $name ) {
				continue;
			}

			if ( self::is_media_stub( $value ) || self::is_media_stub_list( $value ) ) {
				$pending[ $name ] = $value;
				continue;
			}

			if ( ! isset( $schema_index['by_name'][ $name ] ) ) {
				$quarantine[ $name ] = $value;
				$errors[]            = array(
					'code'    => 'hvnly_ie_field_unmapped',
					'message' => 'Standalone field has no matching destination Builder field; data would be lost.',
					'context' => array( 'name' => $name ),
				);
				continue;
			}

			$fields[ $name ] = self::normalize_scalar_for_mapper( $value, $schema_index['by_name'][ $name ], $remapper, $pending, $name );
		}

		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$group_id      = (string) ( $group['group_id'] ?? '' );
			$group_type    = (string) ( $group['group_type'] ?? '' );
			$group_base_id = (string) ( $group['group_base_id'] ?? '' );
			$portable_key  = (string) ( $group['portable_key'] ?? $group['portable_id'] ?? '' );
			$values        = isset( $group['values'] ) && is_array( $group['values'] ) ? $group['values'] : array();

			if ( empty( $values ) ) {
				continue;
			}

			$resolved = self::resolve_destination_group(
				$schema_index,
				$claimed,
				$portable_key,
				$group_type,
				$group_base_id,
				$group_id
			);

			if ( null === $resolved ) {
				$q_key                = '' !== $group_id ? 'group:' . $group_id : 'group:' . $group_type;
				$quarantine[ $q_key ] = $group;
				$errors[]             = array(
					'code'    => 'hvnly_ie_group_unmapped',
					'message' => 'Grouped fields could not be remapped onto the destination Builder; import cannot preserve this data.',
					'context' => array(
						'group_id'      => $group_id,
						'group_type'    => $group_type,
						'group_base_id' => $group_base_id,
						'portable_key'  => $portable_key,
						'suffixes'      => array_keys( $values ),
					),
				);
				continue;
			}

			$dest_group_id = (string) $resolved['group_id'];
			$dest_members  = $resolved['members'];
			$claimed[ $dest_group_id ] = true;

			foreach ( $values as $suffix => $value ) {
				$suffix = (string) $suffix;
				if ( ! isset( $dest_members[ $suffix ] ) ) {
					$quarantine[ 'group:' . $group_id . ':' . $suffix ] = $value;
					$errors[] = array(
						'code'    => 'hvnly_ie_group_suffix_unmapped',
						'message' => 'A grouped field suffix has no matching destination Builder member; data would be lost.',
						'context' => array(
							'group_id'       => $group_id,
							'dest_group_id'  => $dest_group_id,
							'group_type'     => $group_type,
							'suffix'         => $suffix,
						),
					);
					continue;
				}

				$dest_name = (string) $dest_members[ $suffix ]['name'];
				$row       = $dest_members[ $suffix ];

				if ( self::is_media_stub( $value ) || self::is_media_stub_list( $value ) ) {
					$pending[ $dest_name ] = $value;
					continue;
				}

				// Documents JSON list only — never run on icon/label/url title members
				// that share groupType=property_docs (those are plain scalars).
				if ( 'documents' === $suffix ) {
					$decoded              = self::decode_documents( $value, $pending, $dest_name );
					$fields[ $dest_name ] = $decoded;
					continue;
				}

				// Agents ID list only — never resolve the section title suffix through
				// resolve_agent_ids (that overwrites "Listing Agents" with []).
				if ( 'agents' === $suffix ) {
					$fields[ $dest_name ] = self::resolve_agent_ids( $value, $remapper );
					continue;
				}

				if ( 'images' === $suffix ) {
					// Gallery images are media — keep stubs only (pending survives for Phase 6).
					$pending[ $dest_name ] = $value;
					continue;
				}

				if ( 'thumbnail' === $suffix && self::is_media_stub( $value ) ) {
					$pending[ $dest_name ] = $value;
					continue;
				}

				$fields[ $dest_name ] = self::normalize_scalar_for_mapper( $value, $row, $remapper, $pending, $dest_name );
			}
		}

		return array(
			'fields'        => $fields,
			'pending_media' => $pending,
			'quarantine'    => $quarantine,
			'errors'        => $errors,
			'warnings'      => $warnings,
		);
	}

	/**
	 * Resolve a package group onto a destination schema group.
	 *
	 * @param array{by_name:array,by_group:array,by_portable:array,by_type_base:array,by_type:array} $schema_index Index.
	 * @param array<string, bool> $claimed Already claimed dest group ids.
	 * @param string              $portable_key Package portable key.
	 * @param string              $group_type Group type.
	 * @param string              $group_base_id Package base id.
	 * @param string              $group_id Package group id.
	 * @return array{group_id:string,members:array<string,array>}|null
	 */
	private static function resolve_destination_group(
		array $schema_index,
		array $claimed,
		string $portable_key,
		string $group_type,
		string $group_base_id,
		string $group_id
	): ?array {
		// 1. Portable identifier.
		if ( '' !== $portable_key && isset( $schema_index['by_portable'][ $portable_key ] ) ) {
			$dest_id = (string) $schema_index['by_portable'][ $portable_key ];
			if ( empty( $claimed[ $dest_id ] ) && isset( $schema_index['by_group'][ $dest_id ] ) ) {
				return array(
					'group_id' => $dest_id,
					'members'  => $schema_index['by_group'][ $dest_id ],
				);
			}
		}

		// 2. group_type + group_base_id.
		if ( '' !== $group_type && '' !== $group_base_id ) {
			$type_base_key = $group_type . "\0" . $group_base_id;
			if ( isset( $schema_index['by_type_base'][ $type_base_key ] ) ) {
				$dest_id = (string) $schema_index['by_type_base'][ $type_base_key ];
				if ( empty( $claimed[ $dest_id ] ) && isset( $schema_index['by_group'][ $dest_id ] ) ) {
					return array(
						'group_id' => $dest_id,
						'members'  => $schema_index['by_group'][ $dest_id ],
					);
				}
			}
		}

		// 3. Compatible fallback: first unclaimed destination group of the same type.
		if ( '' !== $group_type && isset( $schema_index['by_type'][ $group_type ] ) && is_array( $schema_index['by_type'][ $group_type ] ) ) {
			foreach ( $schema_index['by_type'][ $group_type ] as $dest_id ) {
				$dest_id = (string) $dest_id;
				if ( ! empty( $claimed[ $dest_id ] ) ) {
					continue;
				}
				if ( isset( $schema_index['by_group'][ $dest_id ] ) ) {
					return array(
						'group_id' => $dest_id,
						'members'  => $schema_index['by_group'][ $dest_id ],
					);
				}
			}
		}

		// 4. Exact group_id (same site / after Builder replace).
		if ( '' !== $group_id && isset( $schema_index['by_group'][ $group_id ] ) && empty( $claimed[ $group_id ] ) ) {
			return array(
				'group_id' => $group_id,
				'members'  => $schema_index['by_group'][ $group_id ],
			);
		}

		return null;
	}

	/**
	 * Index destination schema for portable group remapping.
	 *
	 * @return array{
	 *   by_name:array<string,array>,
	 *   by_group:array<string,array<string,array>>,
	 *   by_portable:array<string,string>,
	 *   by_type_base:array<string,string>,
	 *   by_type:array<string,array<int,string>>
	 * }
	 */
	private static function build_schema_index(): array {
		$by_name     = array();
		$by_group    = array();
		$by_portable = array();
		$by_type_base = array();
		$by_type     = array();
		$group_meta  = array(); // group_id => {type, base, portable}

		if ( ! class_exists( PropertyBuilderSchemaService::class ) ) {
			return array(
				'by_name'      => $by_name,
				'by_group'     => $by_group,
				'by_portable'  => $by_portable,
				'by_type_base' => $by_type_base,
				'by_type'      => $by_type,
			);
		}

		foreach ( PropertyBuilderSchemaService::collect_storage_fields() as $row ) {
			$name = (string) ( $row['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$by_name[ $name ] = $row;

			$group_id = (string) ( $row['groupId'] ?? '' );
			$meta_key = (string) ( $row['metaKey'] ?? '' );
			if ( '' === $group_id || '' === $meta_key ) {
				continue;
			}
			if ( ! isset( $by_group[ $group_id ] ) ) {
				$by_group[ $group_id ] = array();
			}
			$by_group[ $group_id ][ $meta_key ] = $row;

			if ( ! isset( $group_meta[ $group_id ] ) ) {
				$g_type = (string) ( $row['groupType'] ?? '' );
				$g_base = (string) ( $row['groupBaseId'] ?? '' );
				$group_meta[ $group_id ] = array(
					'type'         => $g_type,
					'base'         => $g_base,
					'portable_key' => self::make_portable_key( $g_type, $g_base, $group_id ),
				);
			}
		}

		foreach ( $group_meta as $gid => $meta ) {
			$type = (string) $meta['type'];
			$base = (string) $meta['base'];
			$pkey = (string) $meta['portable_key'];

			if ( '' !== $pkey && ! isset( $by_portable[ $pkey ] ) ) {
				$by_portable[ $pkey ] = $gid;
			}
			if ( '' !== $type && '' !== $base ) {
				$by_type_base[ $type . "\0" . $base ] = $gid;
			}
			if ( '' !== $type ) {
				if ( ! isset( $by_type[ $type ] ) ) {
					$by_type[ $type ] = array();
				}
				$by_type[ $type ][] = $gid;
			}
		}

		return array(
			'by_name'      => $by_name,
			'by_group'     => $by_group,
			'by_portable'  => $by_portable,
			'by_type_base' => $by_type_base,
			'by_type'      => $by_type,
		);
	}

	/**
	 * Stable portable key for a Builder group instance.
	 *
	 * @param string $group_type Type.
	 * @param string $group_base_id Base id.
	 * @param string $group_id Group id.
	 * @return string
	 */
	public static function make_portable_key( string $group_type, string $group_base_id, string $group_id ): string {
		if ( '' !== $group_type && '' !== $group_base_id ) {
			return $group_type . ':' . $group_base_id;
		}
		if ( '' !== $group_id ) {
			return 'id:' . $group_id;
		}
		return '';
	}

	/**
	 * @param mixed      $value    Value.
	 * @param array      $row      Storage row.
	 * @param IdRemapper $remapper Remapper.
	 * @param array      $pending  Pending media (by ref).
	 * @param string     $name     Field name.
	 * @return mixed
	 */
	private static function normalize_scalar_for_mapper( $value, array $row, IdRemapper $remapper, array &$pending, string $name ) {
		$meta  = (string) ( $row['metaKey'] ?? '' );
		$group = (string) ( $row['groupType'] ?? '' );

		if ( 'thumbnail' === $meta && self::is_media_stub( $value ) ) {
			$pending[ $name ] = $value;
			return '';
		}

		if ( ( 'agents' === $meta || 'agents' === $group ) && is_array( $value ) ) {
			return self::resolve_agent_ids( $value, $remapper );
		}

		return $value;
	}

	/**
	 * @param mixed      $value    Portable agents list.
	 * @param IdRemapper $remapper Remapper.
	 * @return int[]
	 */
	private static function resolve_agent_ids( $value, IdRemapper $remapper ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$detector = class_exists( \HvnlyNab\ImportExport\Import\DuplicateDetector::class )
			? new \HvnlyNab\ImportExport\Import\DuplicateDetector()
			: null;

		$ids = array();
		foreach ( $value as $ref ) {
			if ( is_numeric( $ref ) ) {
				// Never trust source DB IDs.
				continue;
			}
			if ( ! is_array( $ref ) ) {
				continue;
			}
			$email = (string) ( $ref['email'] ?? '' );
			$slug  = (string) ( $ref['slug'] ?? '' );
			$id    = $remapper->get_agent( $email, $slug );
			if ( $id <= 0 && $detector instanceof \HvnlyNab\ImportExport\Import\DuplicateDetector ) {
				$id = $detector->find_agent( $email, $slug, $ref );
			}
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	/**
	 * Strip embedded media stubs from document rows; queue stubs for Phase 6.
	 *
	 * @param mixed  $value   Documents payload.
	 * @param array  $pending Pending media.
	 * @param string $name    Field name.
	 * @return array<int, array<string, mixed>>
	 */
	private static function decode_documents( $value, array &$pending, string $name ): array {
		$items = array();
		if ( is_array( $value ) ) {
			$items = $value;
		}

		$out        = array();
		$media_rows = array();
		foreach ( $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = isset( $item['label'] ) ? (string) $item['label'] : '';
			if ( '' === $label && isset( $item['title'] ) ) {
				$label = (string) $item['title'];
			}
			if ( '' === $label && isset( $item['name'] ) ) {
				$label = (string) $item['name'];
			}

			$row = array(
				'icon'     => isset( $item['icon'] ) ? (string) $item['icon'] : '',
				'label'    => $label,
				'url_type' => isset( $item['url_type'] ) ? (string) $item['url_type'] : 'custom',
				'url'      => isset( $item['url'] ) ? (string) $item['url'] : '',
			);

			if ( isset( $item['media'] ) && self::is_media_stub( $item['media'] ) ) {
				$media_rows[ (string) $index ] = $item['media'];
				// Keep a non-empty label so PropertyFormMapper::normalize_document_rows
				// does not drop the row before Phase 6 restores the file URL.
				if ( '' === $row['label'] ) {
					$fname = isset( $item['media']['filename'] ) ? (string) $item['media']['filename'] : '';
					$row['label'] = '' !== $fname ? $fname : 'Document';
				}
			}

			if ( '' === $row['label'] && '' === $row['url'] ) {
				continue;
			}

			$out[] = $row;
		}

		if ( ! empty( $media_rows ) ) {
			$pending[ $name . '::__documents' ] = $media_rows;
		}

		return $out;
	}

	/**
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function is_media_stub( $value ): bool {
		return is_array( $value ) && ! empty( $value['export_key'] );
	}

	/**
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function is_media_stub_list( $value ): bool {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return false;
		}
		// Associative media stub.
		if ( isset( $value['export_key'] ) ) {
			return true;
		}
		$first = reset( $value );
		return self::is_media_stub( $first );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
