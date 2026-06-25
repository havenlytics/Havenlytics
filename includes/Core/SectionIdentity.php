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
		'sec_basic_info'       => self::SEC_PROPERTY_OVERVIEW,
		'sec_additional_info'  => self::SEC_PROPERTY_DETAILS,
		'sec_faq'              => self::SEC_PROPERTY_FAQ,
		'sec_repeater'         => self::SEC_PROPERTY_REPEATER,
		'sec_agents'           => self::SEC_PROPERTY_AGENTS,
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
	 * Resolve a stored section ID to its canonical semantic ID (read-time only).
	 */
	public static function resolve_canonical_section_id( string $section_id ): string {
		$section_id = sanitize_key( $section_id );
		if ( '' === $section_id ) {
			return '';
		}

		return self::LEGACY_SECTION_ALIASES[ $section_id ] ?? $section_id;
	}

	/**
	 * Whether two section IDs refer to the same logical section.
	 */
	public static function section_ids_match( string $a, string $b ): bool {
		if ( $a === $b ) {
			return true;
		}

		return self::resolve_canonical_section_id( $a ) === self::resolve_canonical_section_id( $b );
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
		$canonical   = self::resolve_canonical_section_id( $section_id );
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
