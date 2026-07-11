<?php
/**
 * Property builder section identity — stable canonical IDs with read-time legacy aliases.
 *
 * @package Havenlytics
 * @since   3.1.1
 */

namespace HvnlyNab\Core;

defined( 'ABSPATH' ) || exit;

class SectionIdentity {

	public const SEC_PROPERTY_OVERVIEW      = 'sec_property_overview';
	public const SEC_PROPERTY_DETAILS       = 'sec_property_details';
	public const SEC_ADDRESS_NEIGHBORHOOD   = 'sec_address_neighborhood';
	public const SEC_PROPERTY_VIDEO         = 'sec_property_video';
	public const SEC_PROPERTY_GALLERY       = 'sec_property_gallery';
	public const SEC_PROPERTY_LOCATION      = 'sec_property_location';
	public const SEC_PROPERTY_DOCUMENTS     = 'sec_property_documents';
	public const SEC_PROPERTY_FAQ           = 'sec_property_faq';
	public const SEC_PROPERTY_REPEATER      = 'sec_property_repeater';
	public const SEC_PROPERTY_FEATURES      = 'sec_property_features';
	public const SEC_PROPERTY_AGENTS        = 'sec_property_agents';

	/**
	 * Read-time aliases: legacy stored ID => canonical semantic ID (no option rewrite).
	 *
	 * @var array<string, string>
	 */
	private const LEGACY_SECTION_ALIASES = array(
		'sec_basic_info'              => self::SEC_PROPERTY_OVERVIEW,
		'sec_additional_info'         => self::SEC_PROPERTY_DETAILS,
		'sec_additional_info_legacy'  => self::SEC_PROPERTY_DETAILS,
		'sec_faq'                     => self::SEC_PROPERTY_FAQ,
		'sec_repeater'                => self::SEC_PROPERTY_REPEATER,
		'sec_agents'                  => self::SEC_PROPERTY_AGENTS,
	);

	/**
	 * Canonical section IDs preferred for import/demo targeting, per group type.
	 *
	 * @var array<string, string[]>
	 */
	private const GROUP_TYPE_CANONICAL_SECTIONS = array(
		'video'         => array( self::SEC_PROPERTY_VIDEO ),
		'gallery'       => array( self::SEC_PROPERTY_GALLERY ),
		'map'           => array( self::SEC_PROPERTY_LOCATION ),
		'property_docs' => array( self::SEC_PROPERTY_DOCUMENTS ),
		'faq'           => array( self::SEC_PROPERTY_FAQ, 'sec_faq' ),
		'repeater'      => array( self::SEC_PROPERTY_REPEATER, 'sec_repeater' ),
		'agents'        => array( self::SEC_PROPERTY_AGENTS, 'sec_agents' ),
		'features'      => array( self::SEC_PROPERTY_FEATURES ),
	);

	/**
	 * Resolve a stored section ID to its canonical semantic ID.
	 *
	 * Preferred public name: {@see get_canonical_section_id()}.
	 */
	public static function resolve_canonical_section_id( string $section_id ): string {
		$section_id = sanitize_key( $section_id );
		if ( '' === $section_id ) {
			return '';
		}

		return self::LEGACY_SECTION_ALIASES[ $section_id ] ?? $section_id;
	}

	/**
	 * Canonical section ID for a stored/legacy/custom section ID.
	 *
	 * Sole source of truth for alias resolution — call sites must not
	 * hardcode legacy ↔ canonical maps.
	 */
	public static function get_canonical_section_id( string $section_id ): string {
		return self::resolve_canonical_section_id( $section_id );
	}

	/**
	 * Whether two section IDs refer to the same logical section.
	 */
	public static function section_ids_match( string $a, string $b ): bool {
		if ( $a === $b ) {
			return true;
		}

		return self::get_canonical_section_id( $a ) === self::get_canonical_section_id( $b );
	}

	/**
	 * Legacy dynamic custom section pattern (still valid, never rewritten).
	 */
	public static function is_legacy_custom_section_id( string $section_id ): bool {
		return 0 === strpos( $section_id, 'hvnly__dyamic_metabox_tab__' );
	}

	/**
	 * New custom section pattern for user-added tabs.
	 */
	public static function is_modern_custom_section_id( string $section_id ): bool {
		return 0 === strpos( $section_id, 'sec_custom_' );
	}

	/**
	 * Any user-created (non-default) section ID.
	 */
	public static function is_custom_section_id( string $section_id ): bool {
		return self::is_legacy_custom_section_id( $section_id ) || self::is_modern_custom_section_id( $section_id );
	}

	/**
	 * Generate a new custom section ID.
	 */
	public static function generate_custom_section_id(): string {
		return 'sec_custom_' . substr( uniqid(), -13 );
	}

	/**
	 * Preferred section IDs for locating the canonical import target of a group type.
	 *
	 * @return string[]
	 */
	public static function canonical_section_ids_for_group_type( string $group_type ): array {
		$group_type = sanitize_key( $group_type );

		return self::GROUP_TYPE_CANONICAL_SECTIONS[ $group_type ] ?? array();
	}

	/**
	 * Sort score for canonical section selection (lower = higher priority).
	 */
	public static function canonical_section_priority( string $section_id, string $group_type ): int {
		$section_id  = sanitize_key( $section_id );
		$group_type  = sanitize_key( $group_type );
		$canonical   = self::get_canonical_section_id( $section_id );
		$preferences = self::canonical_section_ids_for_group_type( $group_type );

		foreach ( $preferences as $index => $preferred_id ) {
			if ( self::section_ids_match( $canonical, $preferred_id ) ) {
				return $index;
			}
		}

		if ( self::is_custom_section_id( $section_id ) ) {
			return 100;
		}

		return 50;
	}

	/**
	 * Find a section in builder storage by canonical/legacy ID equivalence.
	 *
	 * @param array<string, array<string, mixed>> $sections Builder sections.
	 * @param string                              $target_id Target section ID.
	 * @return array{key: string, section: array<string, mixed>}|null
	 */
	public static function find_equivalent_section( array $sections, string $target_id ): ?array {
		foreach ( $sections as $key => $section ) {
			$stored_id = (string) ( $section['id'] ?? $key );
			if ( self::section_ids_match( $stored_id, $target_id ) || self::section_ids_match( (string) $key, $target_id ) ) {
				return array(
					'key'     => (string) $key,
					'section' => $section,
				);
			}
		}

		return null;
	}

	/**
	 * Whether builder storage contains an equivalent section for the target ID.
	 */
	public static function has_equivalent_section( array $sections, string $target_id ): bool {
		return null !== self::find_equivalent_section( $sections, $target_id );
	}

	/**
	 * Merge two equivalent section definitions into one.
	 *
	 * Keeps $primary section metadata. Appends fields from $secondary that are
	 * not identical duplicates (by field id/name/group identity). Does not
	 * rename field IDs or meta keys. Preserves $primary field order, then
	 * appends unique $secondary fields in their original order.
	 *
	 * @param array<string, mixed> $primary   Section to keep.
	 * @param array<string, mixed> $secondary Section whose unique fields are merged in.
	 * @return array<string, mixed>
	 */
	public static function merge_equivalent_sections( array $primary, array $secondary ): array {
		$merged_fields = isset( $primary['fields'] ) && is_array( $primary['fields'] )
			? array_values( $primary['fields'] )
			: array();
		$seen_fields   = self::field_identity_map( $merged_fields );

		foreach ( $secondary['fields'] ?? array() as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$fid = self::field_identity( $field );
			if ( '' !== $fid && isset( $seen_fields[ $fid ] ) ) {
				continue;
			}

			if ( '' !== $fid ) {
				$seen_fields[ $fid ] = true;
			}

			$merged_fields[] = $field;
		}

		$primary['fields'] = $merged_fields;

		return $primary;
	}

	/**
	 * Collapse builder sections that share a canonical ID (legacy alias pairs).
	 *
	 * Prefer the canonical storage key when present. Merge unique fields from
	 * discarded aliases into the kept section via {@see merge_equivalent_sections()}.
	 * Lone legacy sections are left unchanged (backward compatible).
	 * Field / meta keys are never renamed.
	 *
	 * @param array<string, array<string, mixed>> $sections Builder sections option.
	 * @return array{sections: array<string, array<string, mixed>>, removed: string[]}
	 */
	public static function deduplicate_equivalent_sections( array $sections ): array {
		$groups = array();

		foreach ( $sections as $key => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			$key_str   = (string) $key;
			$stored_id = sanitize_key( (string) ( $section['id'] ?? $key_str ) );
			$key_id    = sanitize_key( $key_str );
			$id_canon  = self::get_canonical_section_id( $stored_id );
			$key_canon = self::get_canonical_section_id( $key_id );

			// Prefer a resolved alias from either the option key or section id.
			$canonical = $id_canon;
			if ( $key_canon !== $key_id ) {
				$canonical = $key_canon;
			} elseif ( $id_canon !== $stored_id ) {
				$canonical = $id_canon;
			}

			if ( ! isset( $groups[ $canonical ] ) ) {
				$groups[ $canonical ] = array();
			}

			$groups[ $canonical ][] = array(
				'key'     => $key_str,
				'section' => $section,
			);
		}

		$result  = array();
		$removed = array();

		foreach ( $groups as $canonical => $entries ) {
			if ( count( $entries ) < 2 ) {
				$result[ $entries[0]['key'] ] = $entries[0]['section'];
				continue;
			}

			$winner_idx = 0;
			foreach ( $entries as $i => $entry ) {
				$entry_id = sanitize_key( (string) ( $entry['section']['id'] ?? $entry['key'] ) );
				if ( $entry['key'] === $canonical || $entry_id === $canonical ) {
					$winner_idx = $i;
					break;
				}
			}

			$winner = $entries[ $winner_idx ];

			foreach ( $entries as $i => $entry ) {
				if ( $i === $winner_idx ) {
					continue;
				}

				$removed[]         = $entry['key'];
				$winner['section'] = self::merge_equivalent_sections( $winner['section'], $entry['section'] );
			}

			// When collapsing onto the canonical section, store under the canonical key.
			$storage_key = $winner['key'];
			if ( $storage_key !== $canonical ) {
				$winner['section']['id'] = $canonical;
				if ( $winner['key'] !== $canonical ) {
					$removed[] = $winner['key'];
				}
				$storage_key = $canonical;
			} else {
				$winner['section']['id'] = $canonical;
			}

			$result[ $storage_key ] = $winner['section'];
		}

		return array(
			'sections' => $result,
			'removed'  => array_values( array_unique( $removed ) ),
		);
	}

	/**
	 * Stable identity for a builder field (for merge-without-duplicate).
	 *
	 * @param array<string, mixed> $field Field definition.
	 */
	private static function field_identity( array $field ): string {
		$id = sanitize_key( (string) ( $field['id'] ?? '' ) );
		if ( '' !== $id ) {
			return 'id:' . $id;
		}

		$name = sanitize_key( (string) ( $field['name'] ?? '' ) );
		if ( '' !== $name ) {
			return 'name:' . $name;
		}

		$base = sanitize_key( (string) ( $field['group_base_id'] ?? '' ) );
		$meta = sanitize_key( (string) ( $field['metaKey'] ?? '' ) );
		if ( '' !== $base && '' !== $meta ) {
			return 'group:' . $base . ':' . $meta;
		}

		return '';
	}

	/**
	 * @param array<int, mixed> $fields Field list.
	 * @return array<string, true>
	 */
	private static function field_identity_map( array $fields ): array {
		$map = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$fid = self::field_identity( $field );
			if ( '' !== $fid ) {
				$map[ $fid ] = true;
			}
		}

		return $map;
	}

	/**
	 * Extract group_base_id for a group type from a builder section.
	 */
	public static function extract_group_base_from_section( array $section, string $group_type ): string {
		$group_type = sanitize_key( $group_type );

		foreach ( $section['fields'] ?? array() as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$field_group_type = sanitize_key( (string) ( $field['group_type'] ?? '' ) );
			if ( '' === $field_group_type ) {
				$field_group_type = sanitize_key( (string) ( $field['type'] ?? '' ) );
			}

			if ( $field_group_type !== $group_type ) {
				continue;
			}

			$base = sanitize_key( (string) ( $field['group_base_id'] ?? '' ) );
			if ( '' !== $base ) {
				return $base;
			}
		}

		return '';
	}
}
