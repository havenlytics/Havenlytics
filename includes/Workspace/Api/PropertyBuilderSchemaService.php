<?php
/**
 * Portal schema from Havenlytics Property Builder (SSOT).
 *
 * Option: `hvnly_property_builder.sections`
 * Mirrors admin `Havenlytics_Type::process_group_fields()` — groups stay intact.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Api\Type\Builders\DefaultTabSectionsData;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only structured Property Builder schema for Workspace renderer.
 *
 * @since 3.2.0
 */
final class PropertyBuilderSchemaService {

	public const TAX_TYPES = 'hvnly_prop_types';
	public const TAX_DEPTS = 'hvnly_prop_depts';

	/** Schema version for Workspace clients. */
	public const SCHEMA_VERSION = 2;

	/**
	 * Group types that collapse to a single storage widget in admin.
	 *
	 * @var array<int, string>
	 */
	private const COLLAPSED_GROUP_TYPES = array(
		'property_docs',
		'faq',
		'repeater',
		'gallery',
		'video',
		'map',
		'agents',
		'features',
	);

	/**
	 * Groups whose Workspace UI writes only storageName (JSON widget),
	 * never DnD member meta keys (_label / _url / etc.).
	 * Member-level required flags must not block draft save.
	 *
	 * @var array<int, string>
	 */
	private const STORAGE_WIDGET_GROUP_TYPES = array(
		'property_docs',
		'faq',
		'repeater',
		'gallery',
		'agents',
		'features',
	);

	/**
	 * Raw builder sections (option or defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function raw_sections(): array {
		$stored = get_option( 'hvnly_property_builder.sections', array() );
		if ( is_array( $stored ) && ! empty( $stored ) ) {
			return $stored;
		}

		if ( class_exists( DefaultTabSectionsData::class ) ) {
			$defaults = DefaultTabSectionsData::get_default_sections_for_dnd();
			return is_array( $defaults ) ? $defaults : array();
		}

		return array();
	}

	/**
	 * Structured portal schema for Workspace Property Builder renderer.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_portal_schema(): array {
		$sections = self::raw_sections();
		$list     = array_values( is_array( $sections ) ? $sections : array() );
		usort(
			$list,
			static function ( $a, $b ) {
				return ( (int) ( $a['order'] ?? 0 ) ) <=> ( (int) ( $b['order'] ?? 0 ) );
			}
		);

		$tabs = array();
		foreach ( $list as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			if ( array_key_exists( 'enabled', $section ) && ! $section['enabled'] ) {
				continue;
			}
			if ( ! empty( $section['adminOnly'] ) || ! empty( $section['admin_only'] ) ) {
				continue;
			}

			$tab_id = isset( $section['id'] ) ? sanitize_key( (string) $section['id'] ) : '';
			if ( '' === $tab_id ) {
				$tab_id = 'sec_' . substr( md5( (string) ( $section['title'] ?? uniqid( '', true ) ) ), 0, 10 );
			}

			$raw_fields = isset( $section['fields'] ) && is_array( $section['fields'] ) ? $section['fields'] : array();
			$items      = self::build_section_items( $raw_fields, $tab_id );

			$tabs[] = array(
				'id'       => $tab_id,
				'label'    => isset( $section['title'] ) ? sanitize_text_field( (string) $section['title'] ) : __( 'Untitled', 'havenlytics' ),
				'icon'     => isset( $section['icon'] ) ? sanitize_text_field( (string) $section['icon'] ) : '',
				'required' => ! empty( $section['required'] ),
				'items'    => $items,
				// BC: flat fields list for older clients (prefer items).
				'fields'   => self::flatten_items_for_bc( $items ),
			);
		}

		return array(
			'version'            => self::SCHEMA_VERSION,
			'tabs'               => $tabs,
			'core'               => array(
				'postTitle'   => true,
				'postExcerpt' => true,
				'postContent' => true,
				'status'      => true,
				'taxonomies'  => self::core_taxonomy_defs(),
			),
			'taxonomies'         => self::taxonomy_options_map(),
			'currency'           => self::currency_info(),
			'currencyOptions'    => self::currency_options(),
			'documentIcons'      => self::document_icon_options(),
			'priceLabelOptions'  => self::price_label_options(),
			'agentOptions'       => self::agent_options(),
			'supportedTypes'     => self::supported_component_types(),
			'gaps'               => self::known_gaps(),
		);
	}

	/**
	 * Collapse flat builder fields into ordered render items (standalone | group).
	 *
	 * @param array<int, mixed> $fields     Raw fields.
	 * @param string            $section_id Section id.
	 * @return array<int, array<string, mixed>>
	 */
	private static function build_section_items( array $fields, string $section_id ): array {
		usort(
			$fields,
			static function ( $a, $b ) {
				return ( (int) ( $a['order'] ?? 0 ) ) <=> ( (int) ( $b['order'] ?? 0 ) );
			}
		);

		$items            = array();
		$processed_groups = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( array_key_exists( 'enabled', $field ) && ! $field['enabled'] ) {
				continue;
			}
			if ( ! empty( $field['hidden'] ) ) {
				continue;
			}
			if ( ! empty( $field['adminOnly'] ) || ! empty( $field['admin_only'] ) ) {
				continue;
			}

			$group_id   = isset( $field['group_id'] ) ? (string) $field['group_id'] : '';
			$group_type = isset( $field['group_type'] ) ? sanitize_key( (string) $field['group_type'] ) : '';

			if ( '' !== $group_id && '' !== $group_type ) {
				if ( isset( $processed_groups[ $group_id ] ) ) {
					continue;
				}
				$processed_groups[ $group_id ] = true;

				$group_item = self::build_group_item( $fields, $group_id, $group_type, $section_id );
				if ( null !== $group_item ) {
					$items[] = $group_item;
				}
				continue;
			}

			$normalized = self::normalize_field( $field, $section_id );
			if ( null === $normalized ) {
				continue;
			}

			$items[] = array(
				'kind'  => 'field',
				'field' => $normalized,
			);
		}

		return $items;
	}

	/**
	 * Build one group render unit (all members preserved).
	 *
	 * @param array<int, mixed> $all_fields  Section fields.
	 * @param string            $group_id    Group id.
	 * @param string            $group_type  Group type.
	 * @param string            $section_id  Section id.
	 * @return array<string, mixed>|null
	 */
	private static function build_group_item( array $all_fields, string $group_id, string $group_type, string $section_id ): ?array {
		$members = array();
		foreach ( $all_fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( (string) ( $field['group_id'] ?? '' ) !== $group_id ) {
				continue;
			}
			if ( array_key_exists( 'enabled', $field ) && ! $field['enabled'] ) {
				continue;
			}
			// Keep Builder-hidden members (e.g. map lat/lng) — admin MapField uses them.
			$normalized = self::normalize_field( $field, $section_id );
			if ( null !== $normalized ) {
				$normalized['hidden'] = ! empty( $field['hidden'] );
				$members[]            = $normalized;
			}
		}

		if ( empty( $members ) ) {
			return null;
		}

		usort(
			$members,
			static function ( $a, $b ) {
				$ap = (int) ( $a['groupPosition'] ?? $a['order'] ?? 0 );
				$bp = (int) ( $b['groupPosition'] ?? $b['order'] ?? 0 );
				return $ap <=> $bp;
			}
		);

		$first         = $members[0];
		$group_base_id = self::resolve_shared_group_base_id( $group_type, $members );
		$group_name    = (string) ( $first['groupName'] ?? $first['label'] ?? ucfirst( $group_type ) );
		$required      = false;
		foreach ( $members as $m ) {
			if ( ! empty( $m['required'] ) ) {
				$required = true;
				break;
			}
		}

		// Builder config can fragment per-member `group_base_id` / `name` across members.
		// Normalize member names to `{group_base_id}_{metaKey}` so Workspace reads/writes
		// the exact same storage keys as wp-admin for THIS group instance.
		if ( '' !== $group_base_id ) {
			foreach ( $members as $idx => $member ) {
				$meta = (string) ( $member['metaKey'] ?? '' );
				if ( '' === $meta ) {
					continue;
				}
				$original_name                    = (string) ( $member['name'] ?? '' );
				$canonical_name                   = $group_base_id . '_' . $meta;
				$members[ $idx ]['groupBaseId'] = $group_base_id;
				$members[ $idx ]['name']          = $canonical_name;
				if ( '' !== $original_name && $original_name !== $canonical_name ) {
					$members[ $idx ]['legacyName'] = $original_name;
				}
			}
		}

		$storage_name = self::resolve_group_storage_name( $group_type, $group_base_id, $members );
		$component    = self::component_for_group_type( $group_type );

		return array(
			'kind'         => 'group',
			'groupId'      => $group_id,
			'groupType'    => $group_type,
			'groupName'    => $group_name,
			'groupBaseId'  => $group_base_id,
			'storageName'  => $storage_name,
			'component'    => $component,
			'required'     => $required,
			'members'      => $members,
			'sectionId'    => $section_id,
			'order'        => (int) ( $first['order'] ?? 0 ),
		);
	}

	/**
	 * Shared storage base for a group instance (Admin / importer SSOT).
	 *
	 * The base ID must be per-instance (unique per group_id). Never collapse to a
	 * type-level "master" base, otherwise two independent group instances (e.g. two
	 * galleries) would write to the same meta keys and share state.
	 *
	 * @param string            $group_type Group type.
	 * @param array<int, array> $members    Normalized members.
	 * @return string
	 */
	private static function resolve_shared_group_base_id( string $group_type, array $members ): string {
		$preferred = array(
			'map'           => array( 'address', 'preview', 'latitude', 'longitude' ),
			'video'         => array( 'title', 'url', 'thumbnail' ),
			'gallery'       => array( 'title', 'images' ),
			// Prefer documents JSON, then icon — Admin DocumentField / master base
			// historically live on the icon member's group_base_id. Preferring label
			// first (legacy) picks a fragmented DnD base and import writes docs to a
			// meta key the UI never reads.
			'property_docs' => array( 'documents', 'icon', 'label', 'url' ),
			'faq'           => array( 'title', 'faqs' ),
			'repeater'      => array( 'title', 'items' ),
			'agents'        => array( 'title', 'agents' ),
			'features'      => array( 'features' ),
		);

		$order = $preferred[ $group_type ] ?? array();
		foreach ( $order as $meta_key ) {
			foreach ( $members as $member ) {
				if ( (string) ( $member['metaKey'] ?? '' ) !== $meta_key ) {
					continue;
				}
				$base = (string) ( $member['groupBaseId'] ?? '' );
				if ( '' !== $base ) {
					return $base;
				}
			}
		}

		return (string) ( $members[0]['groupBaseId'] ?? '' );
	}

	/**
	 * Primary meta key for collapsed group widgets.
	 *
	 * @param string               $group_type   Group type.
	 * @param string               $group_base_id Base id.
	 * @param array<int, array>    $members      Members.
	 * @return string
	 */
	private static function resolve_group_storage_name( string $group_type, string $group_base_id, array $members ): string {
		$suffix_map = array(
			'property_docs' => 'documents',
			'faq'           => 'faqs',
			'repeater'      => 'items',
			'gallery'       => 'images',
			'video'         => 'url',
			'map'           => 'preview',
			'agents'        => 'agents',
			'features'      => 'features',
		);

		if ( isset( $suffix_map[ $group_type ] ) && '' !== $group_base_id ) {
			return $group_base_id . '_' . $suffix_map[ $group_type ];
		}

		foreach ( $members as $m ) {
			$meta = (string) ( $m['metaKey'] ?? '' );
			if ( isset( $suffix_map[ $group_type ] ) && $meta === $suffix_map[ $group_type ] ) {
				return (string) ( $m['name'] ?? '' );
			}
		}

		return (string) ( $members[0]['name'] ?? '' );
	}

	/**
	 * @param string $group_type Group type.
	 * @return string
	 */
	private static function component_for_group_type( string $group_type ): string {
		$map = array(
			'map'           => 'map',
			'gallery'       => 'gallery',
			'video'         => 'video',
			'property_docs' => 'documents',
			'faq'           => 'faq',
			'repeater'      => 'repeater',
			'agents'        => 'agents',
			'features'      => 'features',
		);
		return $map[ $group_type ] ?? 'generic_group';
	}

	/**
	 * Normalize one builder field for the portal renderer.
	 *
	 * @param array<string, mixed> $field      Raw field.
	 * @param string               $section_id Section id.
	 * @return array<string, mixed>|null
	 */
	private static function normalize_field( array $field, string $section_id = '' ): ?array {
		$name = isset( $field['name'] ) ? (string) $field['name'] : ( isset( $field['id'] ) ? (string) $field['id'] : '' );
		$type = isset( $field['type'] ) ? sanitize_key( (string) $field['type'] ) : 'text';
		if ( '' === $name ) {
			return null;
		}

		$options = self::normalize_options( isset( $field['options'] ) ? $field['options'] : array() );

		// Prefer live registry options (countries, heating, etc.) — same as admin SelectField.
		if ( function_exists( 'hvnly_get_field_options' ) ) {
			$registry = hvnly_get_field_options( $name );
			if ( ! empty( $registry ) && is_array( $registry ) ) {
				$options = self::normalize_options( $registry );
			}
		}

		// Live taxonomy options when field is bound to a taxonomy.
		$taxonomy = isset( $field['taxonomy'] ) ? sanitize_key( (string) $field['taxonomy'] ) : '';
		if ( '' === $taxonomy && false !== strpos( $name, 'dept' ) ) {
			$taxonomy = self::TAX_DEPTS;
		}
		if ( in_array( $type, array( 'taxonomy', 'taxonomy_select' ), true ) && '' === $taxonomy ) {
			$taxonomy = self::TAX_TYPES;
		}
		if ( '' !== $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$options = self::taxonomy_options( $taxonomy );
			$type    = 'taxonomy';
		}

		// Workspace portal overrides — match product requirements vs raw Builder types.
		$searchable = false;
		$component  = self::component_for_field_type( $type );
		if ( '_hvnly_property_location' === $name ) {
			$type       = 'text';
			$component  = 'text';
			$options    = array();
			$searchable = false;
		} elseif ( '_hvnly_property_country_location' === $name ) {
			$type       = 'select';
			$component  = 'select';
			$searchable = true;
			if ( empty( $options ) && function_exists( 'hvnly_get_country_field_options' ) ) {
				$options = self::normalize_options( hvnly_get_country_field_options() );
			}
		}

		$condition = null;
		if ( ! empty( $field['condition'] ) && is_array( $field['condition'] ) ) {
			$condition = array(
				'field' => isset( $field['condition']['field'] ) ? (string) $field['condition']['field'] : '',
				'value' => isset( $field['condition']['value'] ) ? $field['condition']['value'] : '',
			);
		}

		$width = isset( $field['width'] ) ? sanitize_key( (string) $field['width'] ) : 'full';
		if ( ! in_array( $width, array( 'full', 'half', 'third', 'quarter' ), true ) ) {
			$width = 'full';
		}

		$hidden = ! empty( $field['hidden'] );

		return array(
			'id'            => isset( $field['id'] ) ? (string) $field['id'] : $name,
			'name'          => $name,
			'type'          => $type,
			'label'         => isset( $field['label'] ) ? sanitize_text_field( (string) $field['label'] ) : $name,
			'placeholder'   => isset( $field['placeholder'] ) ? sanitize_text_field( (string) $field['placeholder'] ) : '',
			'required'      => ! empty( $field['required'] ) || ! empty( $field['is_required'] ),
			'options'       => $options,
			'width'         => $width,
			'order'         => (int) ( $field['order'] ?? 0 ),
			'sectionId'     => $section_id,
			'metaKey'       => isset( $field['metaKey'] ) ? (string) $field['metaKey'] : '',
			'groupId'       => isset( $field['group_id'] ) ? (string) $field['group_id'] : '',
			'groupType'     => isset( $field['group_type'] ) ? sanitize_key( (string) $field['group_type'] ) : '',
			'groupName'     => isset( $field['group_name'] ) ? sanitize_text_field( (string) $field['group_name'] ) : '',
			'groupBaseId'   => isset( $field['group_base_id'] ) ? (string) $field['group_base_id'] : '',
			'groupPosition' => (int) ( $field['group_position'] ?? 0 ),
			'taxonomy'      => $taxonomy,
			'condition'     => $condition,
			'component'     => $component,
			'searchable'    => $searchable,
			'hidden'        => $hidden,
		);
	}

	/**
	 * @param string $type Field type.
	 * @return string
	 */
	private static function component_for_field_type( string $type ): string {
		$map = array(
			'text'            => 'text',
			'textarea'        => 'textarea',
			'wysiwyg'         => 'textarea',
			'editor'          => 'textarea',
			'number'          => 'number',
			'select'          => 'select',
			'radio'           => 'radio',
			'checkbox'        => 'checkbox',
			'toggle'          => 'toggle',
			'switch'          => 'toggle',
			'date'            => 'date',
			'time'            => 'time',
			'url'             => 'url',
			'file'            => 'file',
			'gallery'         => 'gallery',
			'map'             => 'map',
			'video'           => 'video',
			'property_docs'   => 'documents',
			'agents'          => 'agents',
			'price_label'     => 'price_label',
			'faq'             => 'faq',
			'repeater'        => 'repeater',
			'taxonomy'         => 'taxonomy',
			'taxonomy_select'  => 'taxonomy',
		);
		return $map[ $type ] ?? 'text';
	}

	/**
	 * @param mixed $options Raw options.
	 * @return array<int, array{value:string,label:string}>
	 */
	private static function normalize_options( $options ): array {
		if ( ! is_array( $options ) ) {
			return array();
		}
		$out = array();
		foreach ( $options as $key => $opt ) {
			if ( is_array( $opt ) ) {
				$value = isset( $opt['value'] ) ? (string) $opt['value'] : (string) $key;
				$label = isset( $opt['label'] ) ? (string) $opt['label'] : $value;
				$out[] = array(
					'value' => $value,
					'label' => $label,
				);
			} elseif ( is_scalar( $opt ) ) {
				// Assoc map: key => label.
				if ( is_string( $key ) && ! is_numeric( $key ) ) {
					$out[] = array(
						'value' => (string) $key,
						'label' => (string) $opt,
					);
				} else {
					$out[] = array(
						'value' => (string) $opt,
						'label' => (string) $opt,
					);
				}
			}
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $items Items.
	 * @return array<int, array<string, mixed>>
	 */
	private static function flatten_items_for_bc( array $items ): array {
		$flat = array();
		foreach ( $items as $item ) {
			if ( ( $item['kind'] ?? '' ) === 'field' && isset( $item['field'] ) ) {
				$flat[] = $item['field'];
			} elseif ( ( $item['kind'] ?? '' ) === 'group' ) {
				$flat[] = array(
					'id'         => (string) ( $item['storageName'] ?? $item['groupId'] ?? '' ),
					'name'       => (string) ( $item['storageName'] ?? '' ),
					'type'       => (string) ( $item['groupType'] ?? 'group' ),
					'label'      => (string) ( $item['groupName'] ?? '' ),
					'required'   => ! empty( $item['required'] ),
					'groupType'  => (string) ( $item['groupType'] ?? '' ),
					'component'  => (string) ( $item['component'] ?? '' ),
					'kind'       => 'group',
					'members'    => $item['members'] ?? array(),
				);
			}
		}
		return $flat;
	}

	/**
	 * @deprecated 0.5.3 isMedia short-circuit removed — Workspace renders all Builder items.
	 *
	 * @param string            $tab_id Tab id.
	 * @param string            $label  Label.
	 * @param array<int, mixed> $items  Items.
	 * @return bool
	 */
	private static function section_is_media( string $tab_id, string $label, array $items ): bool {
		unset( $tab_id, $label, $items );
		return false;
	}

	/**
	 * Collect every builder field name the mapper should read/write.
	 *
	 * @param array<string, mixed>|null $schema Schema (optional).
	 * @return array<int, array{name:string,type:string,component:string,groupType:string}>
	 */
	public static function collect_storage_fields( ?array $schema = null ): array {
		$schema = is_array( $schema ) ? $schema : self::get_portal_schema();
		$out    = array();
		$seen   = array();

		foreach ( $schema['tabs'] as $tab ) {
			$items = isset( $tab['items'] ) && is_array( $tab['items'] ) ? $tab['items'] : array();
			foreach ( $items as $item ) {
				if ( ( $item['kind'] ?? '' ) === 'field' && isset( $item['field']['name'] ) ) {
					$name = (string) $item['field']['name'];
					if ( isset( $seen[ $name ] ) ) {
						continue;
					}
					$seen[ $name ] = true;
					$field         = $item['field'];
					$out[]         = array(
						'name'        => $name,
						'label'       => (string) ( $field['label'] ?? $name ),
						'type'        => (string) ( $field['type'] ?? 'text' ),
						'component'   => (string) ( $field['component'] ?? 'text' ),
						'groupType'   => (string) ( $field['groupType'] ?? '' ),
						'metaKey'     => (string) ( $field['metaKey'] ?? '' ),
						'groupId'     => (string) ( $field['groupId'] ?? '' ),
						'groupBaseId' => (string) ( $field['groupBaseId'] ?? '' ),
					);
					continue;
				}

				if ( ( $item['kind'] ?? '' ) !== 'group' ) {
					continue;
				}

				$group_type    = (string) ( $item['groupType'] ?? '' );
				$group_id      = (string) ( $item['groupId'] ?? '' );
				$group_base_id = (string) ( $item['groupBaseId'] ?? '' );
				// Persist every member name (map address/lat/lng, video title/url, …).
				foreach ( (array) ( $item['members'] ?? array() ) as $member ) {
					$name = (string) ( $member['name'] ?? '' );
					if ( '' === $name || isset( $seen[ $name ] ) ) {
						continue;
					}
					$seen[ $name ] = true;
					$out[]         = array(
						'name'        => $name,
						'label'       => (string) ( $member['label'] ?? $name ),
						'type'        => (string) ( $member['type'] ?? 'text' ),
						'component'   => (string) ( $member['component'] ?? 'text' ),
						'groupType'   => $group_type,
						'metaKey'     => (string) ( $member['metaKey'] ?? '' ),
						'groupId'     => (string) ( $member['groupId'] ?? $group_id ),
						'groupBaseId' => (string) ( $member['groupBaseId'] ?? $group_base_id ),
						'legacyName'  => (string) ( $member['legacyName'] ?? '' ),
					);
				}

				// Collapsed storage keys (faq/repeater/docs/agents) may not appear as member names.
				$storage = (string) ( $item['storageName'] ?? '' );
				if ( '' !== $storage && ! isset( $seen[ $storage ] ) ) {
					$seen[ $storage ] = true;
					$meta_key         = '';
					if ( '' !== $group_base_id && 0 === strpos( $storage, $group_base_id . '_' ) ) {
						$meta_key = substr( $storage, strlen( $group_base_id ) + 1 );
					}
					$out[] = array(
						'name'        => $storage,
						'label'       => (string) ( $item['label'] ?? $item['groupName'] ?? $storage ),
						'type'        => $group_type,
						'component'   => (string) ( $item['component'] ?? $group_type ),
						'groupType'   => $group_type,
						'metaKey'     => $meta_key,
						'groupId'     => $group_id,
						'groupBaseId' => $group_base_id,
					);
				}
			}
		}

		return $out;
	}

	/**
	 * Count distinct Builder group instances of a type (by groupId).
	 *
	 * Used to decide whether type-level SSOT / legacy singleton fallbacks apply.
	 * Multiple instances of the same group type must remain isolated.
	 *
	 * @param string                   $group_type Group type slug (gallery, agents, …).
	 * @param array<string, mixed>|null $schema    Portal schema (optional).
	 * @return int
	 */
	public static function count_group_instances( string $group_type, ?array $schema = null ): int {
		$group_type = sanitize_key( $group_type );
		if ( '' === $group_type ) {
			return 0;
		}

		$schema = is_array( $schema ) ? $schema : self::get_portal_schema();
		$seen   = array();

		foreach ( (array) ( $schema['tabs'] ?? array() ) as $tab ) {
			foreach ( (array) ( $tab['items'] ?? array() ) as $item ) {
				if ( ( $item['kind'] ?? '' ) !== 'group' ) {
					continue;
				}
				if ( (string) ( $item['groupType'] ?? '' ) !== $group_type ) {
					continue;
				}
				$group_id = (string) ( $item['groupId'] ?? '' );
				if ( '' === $group_id ) {
					continue;
				}
				$seen[ $group_id ] = true;
			}
		}

		return count( $seen );
	}

	/**
	 * Whether the portal schema exposes exactly one instance of a group type.
	 *
	 * @param string                   $group_type Group type slug.
	 * @param array<string, mixed>|null $schema    Portal schema (optional).
	 * @return bool
	 */
	public static function has_sole_group_instance( string $group_type, ?array $schema = null ): bool {
		return 1 === self::count_group_instances( $group_type, $schema );
	}

	/**
	 * Required builder field names (for validation).
	 *
	 * @param array<string, mixed>|null $schema Schema.
	 * @return array<string, string> name => label
	 */
	public static function required_field_map( ?array $schema = null ): array {
		$schema = is_array( $schema ) ? $schema : self::get_portal_schema();
		$map    = array();

		foreach ( $schema['tabs'] as $tab ) {
			foreach ( (array) ( $tab['items'] ?? array() ) as $item ) {
				if ( ( $item['kind'] ?? '' ) === 'field' && ! empty( $item['field']['required'] ) ) {
					$field = $item['field'];
					if ( ! empty( $field['hidden'] ) ) {
						continue;
					}
					$name         = (string) $field['name'];
					$map[ $name ] = (string) ( $field['label'] ?? $name );
				}
				if ( ( $item['kind'] ?? '' ) !== 'group' ) {
					continue;
				}

				$group_type = (string) ( $item['groupType'] ?? '' );
				// Docs/FAQ/repeater/etc. render storage widgets — never validate member keys.
				if ( in_array( $group_type, self::STORAGE_WIDGET_GROUP_TYPES, true ) ) {
					continue;
				}

				foreach ( (array) ( $item['members'] ?? array() ) as $member ) {
					if ( empty( $member['required'] ) || ! empty( $member['hidden'] ) ) {
						continue;
					}
					$name         = (string) $member['name'];
					$map[ $name ] = (string) ( $member['label'] ?? $name );
				}
			}
		}

		return $map;
	}

	/**
	 * Whether a group type writes only storageName in Workspace (not member keys).
	 *
	 * @param string $group_type Group type.
	 * @return bool
	 */
	public static function is_storage_widget_group( string $group_type ): bool {
		return in_array( $group_type, self::STORAGE_WIDGET_GROUP_TYPES, true );
	}

	/**
	 * WP sidebar taxonomies exposed in Workspace core (not Builder DnD fields).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function core_taxonomy_defs(): array {
		$defs = array(
			array(
				'key'      => 'propertyType',
				'taxonomy' => self::TAX_TYPES,
				'label'    => __( 'Property type', 'havenlytics' ),
				'required' => true,
				'multiple' => false,
			),
			array(
				'key'      => 'propertyDepartment',
				'taxonomy' => self::TAX_DEPTS,
				'label'    => __( 'Property department', 'havenlytics' ),
				'required' => false,
				'multiple' => false,
			),
			array(
				'key'      => 'propertyStatus',
				'taxonomy' => 'hvnly_prop_status',
				'label'    => __( 'Property status', 'havenlytics' ),
				'required' => false,
				'multiple' => true,
			),
			array(
				'key'      => 'propertyFeaturesTax',
				'taxonomy' => 'hvnly_prop_features',
				'label'    => __( 'Property features (taxonomy)', 'havenlytics' ),
				'required' => false,
				'multiple' => true,
			),
			array(
				'key'      => 'propertyLocations',
				'taxonomy' => 'hvnly_prop_locations',
				'label'    => __( 'Property locations', 'havenlytics' ),
				'required' => false,
				'multiple' => true,
			),
			array(
				'key'      => 'propertyTags',
				'taxonomy' => 'hvnly_prop_tags',
				'label'    => __( 'Property tags', 'havenlytics' ),
				'required' => false,
				'multiple' => true,
			),
			array(
				'key'      => 'propertyBadges',
				'taxonomy' => 'hvnly_prop_badges',
				'label'    => __( 'Property badges', 'havenlytics' ),
				'required' => false,
				'multiple' => true,
			),
		);

		$out = array();
		foreach ( $defs as $def ) {
			if ( ! taxonomy_exists( $def['taxonomy'] ) ) {
				continue;
			}
			$def['options'] = self::taxonomy_options( $def['taxonomy'] );
			$out[]          = $def;
		}
		return $out;
	}

	/**
	 * @return array<string, array<int, array{value:string,label:string}>>
	 */
	private static function taxonomy_options_map(): array {
		$map = array();
		foreach ( self::core_taxonomy_defs() as $def ) {
			$map[ $def['key'] ] = $def['options'];
		}
		return $map;
	}

	/**
	 * Global currency from Havenlytics settings (not a Builder field).
	 *
	 * @return array{code:string,symbol:string}
	 */
	private static function currency_info(): array {
		$code   = 'USD';
		$symbol = '$';
		$helper = ( function_exists( 'HVNLY_NAB' ) && HVNLY_NAB() ) ? HVNLY_NAB()->Helper : null;
		if ( $helper && method_exists( $helper, 'get_currency_settings' ) ) {
			$settings = $helper->get_currency_settings();
			$code     = isset( $settings['hvnly_currencyType'] ) ? (string) $settings['hvnly_currencyType'] : 'USD';
			$symbol   = (string) $helper->get_currency_symbol( $code );
		} else {
			$code = (string) get_option( 'hvnly_currencyType', 'USD' );
		}
		return array(
			'code'   => $code,
			'symbol' => $symbol,
		);
	}

	/**
	 * Currency select options from Helper symbols map (same backend as Settings).
	 *
	 * @return array<int, array{value:string,label:string}>
	 */
	private static function currency_options(): array {
		$map = array();
		$helper = ( function_exists( 'HVNLY_NAB' ) && HVNLY_NAB() ) ? HVNLY_NAB()->Helper : null;
		if ( $helper && method_exists( $helper, 'get_currency_symbols_map' ) ) {
			$map = $helper->get_currency_symbols_map();
		}
		if ( ! is_array( $map ) || empty( $map ) ) {
			return array(
				array(
					'value' => 'USD',
					'label' => 'USD ($)',
				),
			);
		}

		$out = array();
		foreach ( $map as $code => $symbol ) {
			$code  = (string) $code;
			$out[] = array(
				'value' => $code,
				'label' => sprintf( '%s (%s)', $code, (string) $symbol ),
			);
		}
		return $out;
	}

	/**
	 * Document icon choices (Font Awesome names used by admin DocumentField).
	 *
	 * @return array<int, array{value:string,label:string}>
	 */
	private static function document_icon_options(): array {
		$icons = array(
			'file-pdf'        => 'PDF',
			'file-word'       => 'Word',
			'file-excel'      => 'Excel',
			'file-powerpoint' => 'PowerPoint',
			'file-image'      => 'Image',
			'file-video'      => 'Video',
			'file-audio'      => 'Audio',
			'file-archive'    => 'Archive',
			'file-lines'      => 'Document',
			'file'            => 'File',
			'folder'          => 'Folder',
			'paperclip'       => 'Attachment',
			'link'            => 'Link',
			'download'        => 'Download',
			'youtube'         => 'YouTube',
			'vimeo'           => 'Vimeo',
			'map-marker-alt'  => 'Map',
			'globe'           => 'Website',
			'vr-cardboard'    => 'Virtual Tour',
			'draw-polygon'    => 'Floor Plan',
			'home'            => 'Home',
			'key'             => 'Key',
			'camera'          => 'Photos',
			'clipboard-check' => 'Inspection',
			'file-signature'  => 'Contract',
		);
		$out = array();
		foreach ( $icons as $value => $label ) {
			$out[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $out;
	}

private static function known_gaps(): array {
		return array(
			array(
				'id'     => 'seo',
				'severity' => 'missing',
				'note'   => 'SEO is not in Property Builder defaults; no Workspace SEO fields.',
			),
			array(
				'id'     => 'wysiwyg',
				'severity' => 'partial',
				'note'   => 'Description/excerpt render as textarea (not TinyMCE).',
			),
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function supported_component_types(): array {
		return array(
			'text',
			'textarea',
			'number',
			'select',
			'radio',
			'checkbox',
			'toggle',
			'date',
			'time',
			'url',
			'taxonomy',
			'price_label',
			'map',
			'gallery',
			'video',
			'documents',
			'repeater',
			'faq',
			'agents',
			'features',
			'file',
			'generic_group',
		);
	}

	/**
	 * @param string $taxonomy Taxonomy slug.
	 * @return array<int, array{value:string,label:string}>
	 */
	public static function taxonomy_options( string $taxonomy ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array(
				'value' => (string) $term->slug,
				'label' => (string) $term->name,
			);
		}
		return $out;
	}

	/**
	 * @return array<int, array{value:string,label:string}>
	 */
	private static function price_label_options(): array {
		$defaults = array(
			array( 'value' => 'priceOnCallNone', 'label' => __( 'None', 'havenlytics' ) ),
			array( 'value' => 'priceOnCall', 'label' => __( 'Price On Call', 'havenlytics' ) ),
			array( 'value' => 'fixedPrice', 'label' => __( 'Fixed Price', 'havenlytics' ) ),
			array( 'value' => 'guidePrice', 'label' => __( 'Guide Price', 'havenlytics' ) ),
			array( 'value' => 'offersOver', 'label' => __( 'Offers Over', 'havenlytics' ) ),
		);

		$custom = get_option( 'hvnly_price_on_call_custom_options', array() );
		if ( is_array( $custom ) ) {
			foreach ( $custom as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$value = isset( $row['value'] ) ? (string) $row['value'] : '';
				$label = isset( $row['label'] ) ? (string) $row['label'] : $value;
				if ( '' !== $value ) {
					$defaults[] = array(
						'value' => $value,
						'label' => $label,
					);
				}
			}
		}

		return $defaults;
	}

	/**
	 * Published agent CPT options for Listing Agents group.
	 *
	 * @return array<int, array{value:string,label:string}>
	 */
	private static function agent_options(): array {
		if ( ! post_type_exists( 'hvnly_agent' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'hvnly_agent',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = array(
				'value' => (string) $post->ID,
				'label' => (string) $post->post_title,
			);
		}
		return $out;
	}
}
