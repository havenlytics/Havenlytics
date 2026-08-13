<?php
/**
 * Maps Workspace Property Form values ↔ hvnly_property post/meta.
 *
 * Values use Builder field `name` keys inside `fields`. No Workspace-owned form meta.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Workspace\WorkspaceDateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Property form ↔ CPT field mapper (draft workflow).
 *
 * @since 3.2.0
 */
final class PropertyFormMapper {

	public const POST_TYPE = 'hvnly_property';
	public const TAX_TYPES = 'hvnly_prop_types';
	public const TAX_DEPTS = 'hvnly_prop_depts';

	/** Workflow display label (not a Builder form field). */
	public const META_LISTING_STATUS = '_hvnly_ws_listing_status';

	/**
	 * Empty form values (create).
	 *
	 * @return array<string, mixed>
	 */
	public static function empty_values(): array {
		$fields = array();
		foreach ( PropertyBuilderSchemaService::collect_storage_fields() as $row ) {
			$name = $row['name'];
			if ( self::is_json_list_storage_row( $row ) ) {
				$fields[ $name ] = array();
			} else {
				$fields[ $name ] = '';
			}
		}

		return array(
			'title'              => '',
			'excerpt'            => '',
			'description'        => '',
			'status'             => 'Draft',
			'currency'           => self::current_currency_code(),
			'propertyType'       => '',
			'propertyDepartment' => '',
			'propertyStatus'     => array(),
			'propertyFeaturesTax' => array(),
			'propertyLocations'  => array(),
			'propertyTags'       => array(),
			'propertyBadges'     => array(),
			'featuredImageId'    => 0,
			'fields'             => $fields,
		);
	}

	/**
	 * Read form values from a property post.
	 *
	 * @param \WP_Post $post Property post.
	 * @return array<string, mixed>
	 */
	public static function values_from_post( \WP_Post $post ): array {
		$values                = self::empty_values();
		$values['title']       = (string) $post->post_title;
		$values['excerpt']     = (string) $post->post_excerpt;
		$values['description'] = (string) $post->post_content;
		$values['status']      = self::resolve_status_label( $post );

		$values['propertyType']        = self::get_term_slugs( $post->ID, self::TAX_TYPES, false );
		$values['propertyDepartment']  = self::get_term_slugs( $post->ID, self::TAX_DEPTS, false );
		$values['propertyStatus']      = self::get_term_slugs( $post->ID, 'hvnly_prop_status', true );
		$values['propertyFeaturesTax'] = self::get_term_slugs( $post->ID, 'hvnly_prop_features', true );
		$values['propertyLocations']   = self::get_term_slugs( $post->ID, 'hvnly_prop_locations', true );
		$values['propertyTags']        = self::get_term_slugs( $post->ID, 'hvnly_prop_tags', true );
		$values['propertyBadges']      = self::get_term_slugs( $post->ID, 'hvnly_prop_badges', true );
		$values['currency']            = self::current_currency_code();
		$values['featuredImageId']     = (int) get_post_thumbnail_id( $post->ID );

		$schema = PropertyBuilderSchemaService::get_portal_schema();
		foreach ( PropertyBuilderSchemaService::collect_storage_fields( $schema ) as $row ) {
			$name = (string) ( $row['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			// Mirror wp-admin MapField / Havenlytics_Type: resolve via MetaResolver
			// (primary → field-map storage base → legacy aliases), not bare get_post_meta.
			$raw                       = self::resolve_builder_meta( (int) $post->ID, $row );
			$values['fields'][ $name ] = self::decode_field_value( $raw, $row );
		}

		// Agents: overlay property-level SSOT only when the schema has a sole Agents group.
		// Multiple independent Agents groups load/save per-instance meta only.
		self::hydrate_agents_from_ssot( (int) $post->ID, $values, $schema );

		// Gallery: per-field resolve_builder_meta above is authoritative for every instance.
		// Legacy singleton fallback applies only when the schema has a sole gallery field.
		self::maybe_hydrate_sole_legacy_gallery( (int) $post->ID, $values, $schema );

		return $values;
	}

	/**
	 * Force agents form fields to match PropertyAgentResolver SSOT.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $values  Form values (by ref).
	 * @param array<string, mixed> $schema  Portal schema.
	 * @return void
	 */
	private static function hydrate_agents_from_ssot( int $post_id, array &$values, array $schema ): void {
		if ( ! isset( $values['fields'] ) || ! is_array( $values['fields'] ) ) {
			$values['fields'] = array();
		}

		// Multiple Agents groups are independent — never copy one SSOT list into every field.
		if ( ! PropertyBuilderSchemaService::has_sole_group_instance( 'agents', $schema ) ) {
			return;
		}

		$ssot = array();
		if ( class_exists( '\HvnlyNab\Agent\PropertyAgentResolver' ) ) {
			$ssot = \HvnlyNab\Agent\PropertyAgentResolver::get_assigned_agent_ids( $post_id );
		} elseif ( class_exists( '\HvnlyNab\Agent\AgentConstants' ) ) {
			$raw = get_post_meta( $post_id, \HvnlyNab\Agent\AgentConstants::META_PROPERTY_AGENTS, true );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $id ) {
					$id = absint( $id );
					if ( $id > 0 ) {
						$ssot[] = $id;
					}
				}
				$ssot = array_values( array_unique( $ssot ) );
			}
		}

		foreach ( PropertyBuilderSchemaService::collect_storage_fields( $schema ) as $row ) {
			if ( ! self::is_agents_storage_row( $row ) ) {
				continue;
			}
			$name = (string) ( $row['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			if ( ! empty( $ssot ) ) {
				$values['fields'][ $name ] = $ssot;
				// Quiet repair: keep Builder JSON aligned with META SSOT.
				$encoded = wp_json_encode( array_values( $ssot ) );
				$current = get_post_meta( $post_id, $name, true );
				if ( (string) $current !== (string) $encoded ) {
					update_post_meta( $post_id, $name, $encoded );
				}
				continue;
			}
			// META empty: keep Builder value if present (legacy Builder-only data).
			$existing                  = $values['fields'][ $name ] ?? array();
			$values['fields'][ $name ] = is_array( $existing ) ? array_values( $existing ) : array();

			// Promote Builder-only agents into META SSOT so AccessGate/frontend stay consistent.
			if ( ! empty( $values['fields'][ $name ] ) ) {
				$promote = array();
				foreach ( $values['fields'][ $name ] as $id ) {
					$id = absint( $id );
					if ( $id > 0 ) {
						$promote[] = $id;
					}
				}
				$promote = array_values( array_unique( $promote ) );
				if ( ! empty( $promote ) ) {
					self::sync_agents_ssot_meta( $post_id, $promote );
					$values['fields'][ $name ] = $promote;
				}
			}
		}
	}

	/**
	 * Legacy singleton gallery fallback when the schema has exactly one gallery field.
	 *
	 * Each gallery instance loads from its own meta key via resolve_builder_meta().
	 * When a site still stores images only under `_hvnly_property_gallery_images`
	 * and the builder exposes a sole gallery group, hydrate that one field from
	 * the legacy key — never broadcast one CSV to every gallery instance.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $values  Form values (by ref).
	 * @param array<string, mixed> $schema  Portal schema.
	 * @return void
	 */
	private static function maybe_hydrate_sole_legacy_gallery( int $post_id, array &$values, array $schema ): void {
		if ( ! isset( $values['fields'] ) || ! is_array( $values['fields'] ) ) {
			$values['fields'] = array();
		}

		if ( ! PropertyBuilderSchemaService::has_sole_group_instance( 'gallery', $schema ) ) {
			return;
		}

		$gallery_names = self::gallery_images_storage_field_names();
		if ( 1 !== count( $gallery_names ) ) {
			return;
		}

		$name = $gallery_names[0];
		if ( '' !== trim( (string) ( $values['fields'][ $name ] ?? '' ) ) ) {
			return;
		}

		$legacy = get_post_meta( $post_id, self::META_GALLERY_LEGACY, true );
		if ( ! is_string( $legacy ) || '' === trim( $legacy ) ) {
			return;
		}

		$values['fields'][ $name ] = $legacy;
	}

	/**
	 * @param array<string, mixed> $row Storage row.
	 * @return bool
	 */
	private static function is_agents_storage_row( array $row ): bool {
		$comp = (string) ( $row['component'] ?? '' );
		$meta = (string) ( $row['metaKey'] ?? '' );
		$name = (string) ( $row['name'] ?? '' );
		// NEVER match solely on groupType=agents — title members share that groupType
		// and were incorrectly overwritten with agent ID JSON (SSOT hydrate).
		if ( 'agents' === $meta ) {
			return true;
		}
		if ( 'agents' === $comp && ( 'agents' === $meta || substr( $name, -7 ) === '_agents' ) ) {
			return true;
		}
		return $name !== '' && substr( $name, -7 ) === '_agents';
	}

	/**
	 * Builder agents storage keys from portal schema.
	 *
	 * @return string[]
	 */
	public static function agents_storage_field_names(): array {
		$names = array();
		foreach ( PropertyBuilderSchemaService::collect_storage_fields() as $row ) {
			if ( ! self::is_agents_storage_row( $row ) ) {
				continue;
			}
			$name = (string) ( $row['name'] ?? '' );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Load one Builder storage key the same way admin metabox does.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row     collect_storage_fields() row.
	 * @return mixed
	 */
	private static function resolve_builder_meta( int $post_id, array $row ) {
		$name = (string) ( $row['name'] ?? '' );
		if ( '' === $name ) {
			return '';
		}

		$meta_key = (string) ( $row['metaKey'] ?? '' );
		if ( '' === $meta_key && preg_match( '/_(address|latitude|longitude|preview|images|documents|url|title|thumbnail|faqs|items|features|agents|icon|label)$/', $name, $m ) ) {
			$meta_key = $m[1];
		}

		$field = array(
			'name'          => $name,
			'id'            => $name,
			'metaKey'       => $meta_key,
			'group_type'    => (string) ( $row['groupType'] ?? '' ),
			'group_id'      => (string) ( $row['groupId'] ?? '' ),
			'group_base_id' => (string) ( $row['groupBaseId'] ?? '' ),
		);

		if ( function_exists( 'hvnly_resolve_field_meta' ) ) {
			return hvnly_resolve_field_meta( $post_id, $field, $name );
		}

		if ( class_exists( '\HvnlyNab\Core\DataPreservation\MetaResolver' ) ) {
			return \HvnlyNab\Core\DataPreservation\MetaResolver::get_field_value( $post_id, $field, $name );
		}

		return get_post_meta( $post_id, $name, true );
	}

	/**
	 * Merge a PATCH payload onto existing form values (validation helper).
	 *
	 * @param array<string, mixed> $base  Existing values.
	 * @param array<string, mixed> $patch Incoming partial values.
	 * @return array<string, mixed>
	 */
	public static function merge_patch( array $base, array $patch ): array {
		$out = $base;
		foreach ( $patch as $key => $value ) {
			if ( 'fields' === $key && is_array( $value ) ) {
				$fields = isset( $out['fields'] ) && is_array( $out['fields'] ) ? $out['fields'] : array();
				foreach ( $value as $fk => $fv ) {
					$fields[ (string) $fk ] = $fv;
				}
				$out['fields'] = $fields;
				continue;
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Apply form values to a property (meta + content).
	 * Preserves workflow post_status (draft / pending / publish).
	 *
	 * When $patch is true (Workspace updates), only keys present in $values are written.
	 * Absent taxonomies / fields / post content are left untouched — never wiped with empties.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $values  Form values (partial when patching).
	 * @param bool                 $patch   PATCH-style update (default false = create/full write of provided keys).
	 * @return void
	 */
	public static function apply_values( int $post_id, array $values, bool $patch = false ): void {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}

		$existing = get_post( $post_id );
		$preserve = ( $existing && in_array( $existing->post_status, array( 'draft', 'pending', 'publish' ), true ) )
			? $existing->post_status
			: 'draft';

		$post_update = array(
			'ID'          => $post_id,
			'post_status' => $preserve,
			'post_type'   => self::POST_TYPE,
		);

		$touch_post = false;
		if ( array_key_exists( 'title', $values ) ) {
			$post_update['post_title'] = sanitize_text_field( (string) $values['title'] );
			$touch_post                = true;
		} elseif ( ! $patch ) {
			$post_update['post_title'] = '';
			$touch_post                = true;
		}

		if ( array_key_exists( 'excerpt', $values ) ) {
			$post_update['post_excerpt'] = sanitize_textarea_field( (string) $values['excerpt'] );
			$touch_post                  = true;
		} elseif ( ! $patch ) {
			$post_update['post_excerpt'] = '';
			$touch_post                  = true;
		}

		if ( array_key_exists( 'description', $values ) ) {
			$post_update['post_content'] = wp_kses_post( (string) $values['description'] );
			$touch_post                  = true;
		} elseif ( ! $patch ) {
			$post_update['post_content'] = '';
			$touch_post                  = true;
		}

		if ( $touch_post ) {
			wp_update_post( $post_update, true );
		}

		// WordPress featured image (post thumbnail) — not Builder meta.
		if ( array_key_exists( 'featuredImageId', $values ) ) {
			$thumb_id = absint( $values['featuredImageId'] );
			if ( $thumb_id <= 0 ) {
				delete_post_thumbnail( $post_id );
			} elseif ( self::user_may_use_attachment( $post_id, $thumb_id ) ) {
				set_post_thumbnail( $post_id, $thumb_id );
			}
			// Unauthorized ID: leave the existing thumbnail unchanged.
		}

		$current_listing = (string) get_post_meta( $post_id, self::META_LISTING_STATUS, true );
		if ( '' === $current_listing ) {
			self::sync_listing_status( $post_id, 'Draft' );
		}

		// Taxonomies: never sync unless the key was explicitly provided in this request.
		if ( array_key_exists( 'propertyType', $values ) ) {
			self::sync_taxonomy_value( $post_id, self::TAX_TYPES, $values['propertyType'], false );
		}
		if ( array_key_exists( 'propertyDepartment', $values ) ) {
			self::sync_taxonomy_value( $post_id, self::TAX_DEPTS, $values['propertyDepartment'], false );
		}
		if ( array_key_exists( 'propertyStatus', $values ) ) {
			self::sync_taxonomy_value( $post_id, 'hvnly_prop_status', $values['propertyStatus'], true );
		}
		if ( array_key_exists( 'propertyFeaturesTax', $values ) ) {
			self::sync_taxonomy_value( $post_id, 'hvnly_prop_features', $values['propertyFeaturesTax'], true );
		}
		if ( array_key_exists( 'propertyLocations', $values ) ) {
			self::sync_taxonomy_value( $post_id, 'hvnly_prop_locations', $values['propertyLocations'], true );
		}
		if ( array_key_exists( 'propertyTags', $values ) ) {
			self::sync_taxonomy_value( $post_id, 'hvnly_prop_tags', $values['propertyTags'], true );
		}
		if ( array_key_exists( 'propertyBadges', $values ) ) {
			self::sync_taxonomy_value( $post_id, 'hvnly_prop_badges', $values['propertyBadges'], true );
		}

		// Currency is global Settings → General only. Workspace must not mutate it per listing.

		$incoming = isset( $values['fields'] ) && is_array( $values['fields'] ) ? $values['fields'] : array();
		$schema   = PropertyBuilderSchemaService::get_portal_schema();
		$recorded = array();

		foreach ( PropertyBuilderSchemaService::collect_storage_fields( $schema ) as $row ) {
			$name      = $row['name'];
			$value_key = null;
			if ( array_key_exists( $name, $incoming ) ) {
				$value_key = $name;
			} elseif ( ! empty( $row['legacyName'] ) && array_key_exists( (string) $row['legacyName'], $incoming ) ) {
				// Accept fragmented Builder field names from older Workspace sessions.
				$value_key = (string) $row['legacyName'];
			} else {
				continue;
			}

			$encoded = self::encode_field_value( $incoming[ $value_key ], $row, $post_id );
			self::update_meta( $post_id, $name, $encoded );

			// Mirror Admin MapField / MetaResolver legacy aliases for map scalars.
			self::maybe_dual_write_map_legacy( $post_id, $row, $encoded );
			// Video + gallery title legacy mirrors (same keys Onboarding dual-writes).
			self::maybe_dual_write_video_legacy( $post_id, $row, $encoded );
			self::maybe_dual_write_gallery_title_legacy( $post_id, $row, $encoded );

			// Gallery: always write the Builder/legacy key above. Dual-write the singleton
			// legacy mirror ONLY from the canonical primary gallery key so multi-gallery
			// groups cannot wipe each other / the legacy mirror.
			if ( self::is_gallery_images_storage_row( $row ) ) {
				self::sync_legacy_gallery_mirror( $post_id, (string) $name, $encoded );
			}

			if ( self::is_agents_storage_row( $row ) ) {
				$ids        = array();
				$raw_agents = $incoming[ $value_key ];
				if ( is_array( $raw_agents ) ) {
					foreach ( $raw_agents as $id ) {
						$id = absint( $id );
						if ( $id > 0 ) {
							$ids[] = $id;
						}
					}
				}
				$ids = array_values( array_unique( $ids ) );
				// Property-level agents SSOT applies only when the schema has one Agents group.
				if ( PropertyBuilderSchemaService::has_sole_group_instance( 'agents', $schema ) ) {
					self::sync_agents_ssot_meta( $post_id, $ids );
				}
			}

			$group_id      = (string) ( $row['groupId'] ?? '' );
			$group_base_id = (string) ( $row['groupBaseId'] ?? '' );
			$group_type    = (string) ( $row['groupType'] ?? '' );
			if ( '' !== $group_id && '' !== $group_base_id && ! isset( $recorded[ $group_id ] ) ) {
				$recorded[ $group_id ] = true;
				if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
					\HvnlyNab\Core\GroupFieldIdentity::record_group_in_field_map(
						$post_id,
						$group_id,
						$group_base_id,
						$group_type
					);
				}
			}
		}
	}

	/**
	 * Dual-write Admin/importer map legacy keys when saving map address/lat/lng.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row     Storage row.
	 * @param mixed                $encoded Encoded value.
	 * @return void
	 */
	private static function maybe_dual_write_map_legacy( int $post_id, array $row, $encoded ): void {
		$group = (string) ( $row['groupType'] ?? '' );
		$meta  = (string) ( $row['metaKey'] ?? '' );
		if ( 'map' !== $group ) {
			return;
		}

		$legacy = array(
			'address'   => '_hvnly_property_map_location_address',
			'latitude'  => '_hvnly_property_location_Latitude',
			'longitude' => '_hvnly_property_location_Longitude',
		);
		if ( ! isset( $legacy[ $meta ] ) ) {
			return;
		}

		$key = $legacy[ $meta ];
		if ( '' === $encoded || null === $encoded ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $encoded );
	}

	/**
	 * Dual-write Onboarding/Admin video legacy keys when saving video title/url/thumbnail.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row     Storage row.
	 * @param mixed                $encoded Encoded value.
	 * @return void
	 */
	private static function maybe_dual_write_video_legacy( int $post_id, array $row, $encoded ): void {
		if ( 'video' !== (string) ( $row['groupType'] ?? '' ) ) {
			return;
		}

		$legacy = array(
			'title'     => '_hvnly_property_youtube_video_title',
			'url'       => '_hvnly_property_youtube_video_url',
			'thumbnail' => '_hvnly_property_youtube_video_thumbnail',
		);
		$meta   = (string) ( $row['metaKey'] ?? '' );
		if ( ! isset( $legacy[ $meta ] ) ) {
			return;
		}

		$key = $legacy[ $meta ];
		if ( '' === $encoded || null === $encoded ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $encoded );
	}

	/**
	 * Dual-write gallery title legacy key (images use sync_legacy_gallery_mirror).
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $row     Storage row.
	 * @param mixed                $encoded Encoded value.
	 * @return void
	 */
	private static function maybe_dual_write_gallery_title_legacy( int $post_id, array $row, $encoded ): void {
		if ( 'gallery' !== (string) ( $row['groupType'] ?? '' ) ) {
			return;
		}
		if ( 'title' !== (string) ( $row['metaKey'] ?? '' ) ) {
			return;
		}

		$key = '_hvnly_property_gallery_title';
		if ( '' === $encoded || null === $encoded ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $encoded );
	}

	/**
	 * @param array<string, mixed> $row Storage row.
	 * @return bool
	 */
	private static function is_gallery_images_storage_row( array $row ): bool {
		$comp  = (string) ( $row['component'] ?? '' );
		$group = (string) ( $row['groupType'] ?? '' );
		$name  = (string) ( $row['name'] ?? '' );
		$meta  = (string) ( $row['metaKey'] ?? '' );
		if ( 'images' === $meta ) {
			return true;
		}
		if ( ( 'gallery' === $comp || 'gallery' === $group ) && false !== strpos( $name, '_images' ) ) {
			return true;
		}
		return self::META_GALLERY_LEGACY === $name;
	}

	/**
	 * Mirror Builder gallery ↔ legacy singleton (+ instance key) without multi-gallery collisions.
	 *
	 * Sprint 28C: when the portal schema writes `{master}_images` but admin reads
	 * `{instance}_images` (UnifiedFieldGenerator), dual-write both + the legacy
	 * singleton for the sole/canonical gallery so reload always finds the CSV.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Field name written.
	 * @param mixed  $encoded Encoded CSV (or empty).
	 * @return void
	 */
	private static function sync_legacy_gallery_mirror( int $post_id, string $name, $encoded ): void {
		$primary = '';
		if ( class_exists( PropertyMediaService::class ) ) {
			$primary = (string) PropertyMediaService::builder_gallery_meta_key();
		}
		if ( '' === $primary ) {
			$primary = self::META_GALLERY_LEGACY;
		}

		$gallery_names = self::gallery_images_storage_field_names();
		$csv           = is_string( $encoded ) ? $encoded : '';
		$sole_gallery  = ( 1 === count( $gallery_names ) );

		// Form field IS the legacy key: also keep the Builder primary key in sync.
		if ( self::META_GALLERY_LEGACY === $name ) {
			if ( $primary !== self::META_GALLERY_LEGACY && '' !== $primary ) {
				if ( '' === $csv ) {
					delete_post_meta( $post_id, $primary );
				} else {
					update_post_meta( $post_id, $primary, $csv );
				}
			}
			if ( $sole_gallery ) {
				self::write_gallery_csv_keys( $post_id, $gallery_names, $csv, $name );
			}
			return;
		}

		// Dual-write from the canonical primary key, or the sole gallery in schema.
		$is_canonical = ( $name === $primary )
			|| ( $sole_gallery && $name === $gallery_names[0] );
		if ( ! $is_canonical ) {
			return;
		}

		if ( '' === $csv ) {
			delete_post_meta( $post_id, self::META_GALLERY_LEGACY );
		} else {
			update_post_meta( $post_id, self::META_GALLERY_LEGACY, $csv );
		}

		// Align admin instance key + every portal gallery images key (sole gallery only).
		$targets = $gallery_names;
		if ( '' !== $primary && self::META_GALLERY_LEGACY !== $primary ) {
			$targets[] = $primary;
		}
		self::write_gallery_csv_keys( $post_id, $targets, $csv, $name );
	}

	/**
	 * Write (or clear) a gallery CSV on sibling meta keys. Skips $skip_name (already written).
	 *
	 * @param int      $post_id   Post ID.
	 * @param string[] $keys      Meta keys.
	 * @param string   $csv       Encoded CSV (empty clears).
	 * @param string   $skip_name Key already persisted by apply_values.
	 * @return void
	 */
	private static function write_gallery_csv_keys( int $post_id, array $keys, string $csv, string $skip_name ): void {
		$seen = array(
			$skip_name => true,
			self::META_GALLERY_LEGACY => true,
		);
		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			if ( '' === $csv ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $csv );
			}
		}
	}

	/**
	 * @return string[]
	 */
	private static function gallery_images_storage_field_names(): array {
		$names = array();
		foreach ( PropertyBuilderSchemaService::collect_storage_fields() as $row ) {
			if ( ! self::is_gallery_images_storage_row( $row ) ) {
				continue;
			}
			$name = (string) ( $row['name'] ?? '' );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Write agents SSOT meta used by PropertyAccessGate / frontend.
	 *
	 * @param int   $post_id Post ID.
	 * @param int[] $ids     Agent CPT IDs.
	 * @return void
	 */
	private static function sync_agents_ssot_meta( int $post_id, array $ids ): void {
		if ( ! class_exists( '\HvnlyNab\Agent\AgentConstants' ) ) {
			return;
		}
		$agent_meta = \HvnlyNab\Agent\AgentConstants::META_PROPERTY_AGENTS;
		if ( ! empty( $ids ) ) {
			update_post_meta( $post_id, $agent_meta, array_values( $ids ) );
		} else {
			delete_post_meta( $post_id, $agent_meta );
		}
	}

	/** Legacy gallery meta key (singleton mirror). */
	private const META_GALLERY_LEGACY = '_hvnly_property_gallery_images';

	/**
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @param bool   $multiple Return array of slugs.
	 * @return string|array<int, string>
	 */
	private static function get_term_slugs( int $post_id, string $taxonomy, bool $multiple ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return $multiple ? array() : '';
		}
		$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return $multiple ? array() : '';
		}
		if ( $multiple ) {
			return array_values( array_map( 'strval', $terms ) );
		}
		return ! empty( $terms[0] ) ? (string) $terms[0] : '';
	}

	/**
	 * @param int                      $post_id  Post ID.
	 * @param string                   $taxonomy Taxonomy.
	 * @param string|array<int,string> $value    Slug or slugs.
	 * @param bool                     $multiple Multi-select.
	 * @return void
	 */
	private static function sync_taxonomy_value( int $post_id, string $taxonomy, $value, bool $multiple ): void {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}
		$slugs = array();
		if ( $multiple ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $slug ) {
					$slug = sanitize_title( (string) $slug );
					if ( '' !== $slug ) {
						$slugs[] = $slug;
					}
				}
			}
		} else {
			$slug = sanitize_title( (string) $value );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		if ( empty( $slugs ) ) {
			wp_set_object_terms( $post_id, array(), $taxonomy, false );
			return;
		}

		$term_ids = array();
		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term ) {
				$inserted = wp_insert_term( ucwords( str_replace( '-', ' ', $slug ) ), $taxonomy, array( 'slug' => $slug ) );
				if ( ! is_wp_error( $inserted ) && ! empty( $inserted['term_id'] ) ) {
					$term_ids[] = (int) $inserted['term_id'];
				}
				continue;
			}
			$term_ids[] = (int) $term->term_id;
		}
		wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function resolve_status_label( \WP_Post $post ): string {
		$listing = (string) get_post_meta( $post->ID, self::META_LISTING_STATUS, true );
		if ( 'trash' === $post->post_status ) {
			return 'Trash';
		}
		// Rejected is meta-only (post_status stays draft).
		if ( 'Rejected' === $listing ) {
			return 'Rejected';
		}
		/*
		 * Workflow SSOT is post_status for Pending / Published / Draft.
		 * Never surface stale listing meta (e.g. Pending while still draft) —
		 * that lied to Workspace UI while the admin badge (counts pending posts)
		 * and submit emails stayed quiet.
		 */
		if ( 'pending' === $post->post_status ) {
			return 'Pending';
		}
		if ( 'publish' === $post->post_status ) {
			return 'Published';
		}
		return 'Draft';
	}

	/**
	 * @param mixed                $raw Raw meta.
	 * @param array<string, string> $row Storage row.
	 * @return mixed
	 */
	private static function decode_field_value( $raw, array $row ) {
		$comp       = (string) ( $row['component'] ?? '' );
		$group_type = (string) ( $row['groupType'] ?? '' );

		if ( self::is_json_list_storage_row( $row ) ) {
			$rows = array();
			if ( is_array( $raw ) ) {
				$rows = $raw;
			} elseif ( is_string( $raw ) && '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( is_array( $decoded ) ) {
					$rows = $decoded;
				} else {
					$maybe = maybe_unserialize( $raw );
					if ( is_array( $maybe ) ) {
						$rows = $maybe;
					} elseif ( self::is_agents_storage_row( $row ) ) {
						$parts = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
						return array_values( $parts );
					}
				}
			}
			$meta    = (string) ( $row['metaKey'] ?? '' );
			$name    = (string) ( $row['name'] ?? '' );
			$is_docs = ( 'documents' === $comp )
				|| ( 'documents' === $meta )
				|| ( $name !== '' && substr( $name, -10 ) === '_documents' );
			if ( $is_docs ) {
				return self::normalize_document_rows( $rows );
			}
			return array_values( $rows );
		}

		if ( is_array( $raw ) || is_object( $raw ) ) {
			return wp_json_encode( $raw );
		}

		if ( is_string( $raw ) && self::is_empty_json_payload( $raw ) ) {
			return '';
		}

		return null === $raw || false === $raw ? '' : (string) $raw;
	}

	/**
	 * Detect empty JSON payloads wrongly stored on scalar fields ("[]", "{}", "null").
	 *
	 * @param string $raw Raw meta string.
	 * @return bool
	 */
	private static function is_empty_json_payload( string $raw ): bool {
		$trim = trim( $raw );
		if ( '' === $trim ) {
			return false;
		}
		if ( in_array( $trim, array( '[]', '{}', 'null', '""' ), true ) ) {
			return true;
		}
		$decoded = json_decode( $trim, true );
		return is_array( $decoded ) && array() === $decoded;
	}

	/**
	 * @param mixed                 $value   Incoming value.
	 * @param array<string, string> $row     Storage row.
	 * @param int                   $post_id Property post ID (attachment auth for gallery).
	 * @return mixed
	 */
	private static function encode_field_value( $value, array $row, int $post_id = 0 ) {
		$comp       = (string) ( $row['component'] ?? '' );
		$group_type = (string) ( $row['groupType'] ?? '' );
		$type       = (string) ( $row['type'] ?? '' );
		$meta_key   = (string) ( $row['metaKey'] ?? '' );

		if ( self::is_json_list_storage_row( $row ) ) {
			// Match is_agents_storage_row — never treat agents group title as an ID list.
			if ( self::is_agents_storage_row( $row ) ) {
				$ids = array();
				if ( is_array( $value ) ) {
					foreach ( $value as $id ) {
						$id = absint( $id );
						if ( $id > 0 ) {
							$ids[] = $id;
						}
					}
				} elseif ( is_string( $value ) && '' !== $value ) {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						foreach ( $decoded as $id ) {
							$id = absint( $id );
							if ( $id > 0 ) {
								$ids[] = $id;
							}
						}
					}
				}
				return wp_json_encode( array_values( array_unique( $ids ) ) );
			}

			if ( ! is_array( $value ) ) {
				if ( is_string( $value ) && '' !== $value ) {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						$value = $decoded;
					} else {
						$maybe = maybe_unserialize( $value );
						$value = is_array( $maybe ) ? $maybe : array();
					}
				} else {
					$value = array();
				}
			}
			// Documents JSON list only (metaKey/name/comp), not every property_docs member.
			$meta_key = (string) ( $row['metaKey'] ?? '' );
			$name     = (string) ( $row['name'] ?? '' );
			$comp     = (string) ( $row['component'] ?? '' );
			$is_docs  = ( 'documents' === $meta_key )
				|| ( 'documents' === $comp )
				|| ( $name !== '' && substr( $name, -10 ) === '_documents' );
			if ( $is_docs ) {
				$value = self::normalize_document_rows( $value );
			}
			return wp_json_encode( array_values( $value ) );
		}

		if ( 'price_label' === $comp || 'price_label' === $type ) {
			return self::encode_price_label_value( $value );
		}

		// Admin GalleryField stores comma-separated attachment IDs.
		if ( ( 'gallery' === $comp || 'gallery' === $group_type ) && false !== strpos( (string) ( $row['name'] ?? '' ), '_images' ) ) {
			return self::encode_gallery_images_value( $value, $post_id );
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			return wp_json_encode( $value );
		}

		// Preserve URLs / long text without stripping (sanitize_text_field truncates/strips).
		if ( 'textarea' === $comp || 'url' === $comp || 'video' === $comp || 'file' === $type || 'thumbnail' === $meta_key ) {
			if ( 'url' === $comp || 'file' === $type || 'thumbnail' === $meta_key || ( is_string( $value ) && preg_match( '#^https?://#i', (string) $value ) ) ) {
				return esc_url_raw( (string) $value );
			}
			return sanitize_textarea_field( (string) $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Whether this storage row is a JSON list widget (faqs/items/agents/…),
	 * not a scalar member like title/url/thumbnail.
	 *
	 * @param array<string, mixed> $row Storage row.
	 * @return bool
	 */
	private static function is_json_list_storage_row( array $row ): bool {
		$meta = (string) ( $row['metaKey'] ?? '' );
		$name = (string) ( $row['name'] ?? '' );
		$comp = (string) ( $row['component'] ?? '' );
		$type = (string) ( $row['type'] ?? '' );

		if ( 'checkbox' === $type || 'checkbox' === $comp ) {
			return true;
		}

		$list_keys = array( 'faqs', 'items', 'agents', 'features', 'documents' );
		if ( in_array( $meta, $list_keys, true ) ) {
			return true;
		}

		foreach ( $list_keys as $suffix ) {
			$needle = '_' . $suffix;
			if ( $name !== '' && substr( $name, -strlen( $needle ) ) === $needle ) {
				return true;
			}
		}

		// Collapsed documents widget without metaKey on the storage row.
		if ( 'documents' === $comp ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether the current user may bind an attachment to this property.
	 *
	 * Admins (imports / migration) pass. Agents may use attachments they own or
	 * that are already linked to this property (preserve admin-seeded media).
	 *
	 * @param int $post_id       Property post ID.
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private static function user_may_use_attachment( int $post_id, int $attachment_id ): bool {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( class_exists( PropertyMediaService::class ) ) {
			if ( PropertyMediaService::user_owns_attachment( $attachment_id ) ) {
				return true;
			}
			if ( $post_id > 0 && PropertyMediaService::is_linked( $post_id, $attachment_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Encode gallery images as admin GalleryField CSV of attachment IDs.
	 *
	 * @param mixed $value   Incoming.
	 * @param int   $post_id Property post ID for attachment authorization.
	 * @return string
	 */
	private static function encode_gallery_images_value( $value, int $post_id = 0 ): string {
		$ids = array();
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( is_array( $item ) && isset( $item['id'] ) ) {
					$id = absint( $item['id'] );
				} else {
					$id = absint( $item );
				}
				if ( $id > 0 && self::user_may_use_attachment( $post_id, $id ) ) {
					$ids[] = $id;
				}
			}
		} elseif ( is_string( $value ) && '' !== trim( $value ) ) {
			if ( '{' === $value[0] || '[' === $value[0] ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					return self::encode_gallery_images_value( $decoded, $post_id );
				}
			}
			foreach ( explode( ',', $value ) as $part ) {
				$id = absint( trim( $part ) );
				if ( $id > 0 && self::user_may_use_attachment( $post_id, $id ) ) {
					$ids[] = $id;
				}
			}
		}
		$ids = array_values( array_unique( $ids ) );
		return empty( $ids ) ? '' : implode( ',', $ids );
	}

	/**
	 * Encode price_label the same way admin PriceLabelField expects.
	 *
	 * @param mixed $value Incoming.
	 * @return string
	 */
	private static function encode_price_label_value( $value ): string {
		if ( is_array( $value ) ) {
			if ( isset( $value['__type'] ) ) {
				return wp_json_encode( $value );
			}
			if ( isset( $value['price'] ) || isset( $value['label'] ) ) {
				return wp_json_encode( $value );
			}
			$value = wp_json_encode( $value );
		}

		$value = is_string( $value ) ? $value : (string) $value;
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		if ( '{' === $value[0] ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return wp_json_encode( $decoded );
			}
		}

		if ( is_numeric( $value ) ) {
			return (string) $value;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Build list item shape for Workspace table.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	public static function list_item_from_post( \WP_Post $post ): array {
		$values = self::values_from_post( $post );
		$fields = isset( $values['fields'] ) && is_array( $values['fields'] ) ? $values['fields'] : array();

		$price_raw = isset( $fields['_hvnly_property_price'] ) ? (string) $fields['_hvnly_property_price'] : '';
		$price     = '';
		if ( $price_raw !== '' && is_numeric( $price_raw ) ) {
			$price = '$' . number_format( (float) $price_raw );
		} elseif ( $price_raw !== '' && '{' === $price_raw[0] ) {
			$decoded = json_decode( $price_raw, true );
			if ( is_array( $decoded ) && ! empty( $decoded['label'] ) ) {
				$price = (string) $decoded['label'];
			}
		}

		$city  = isset( $fields['_hvnly_property_town_city'] ) ? (string) $fields['_hvnly_property_town_city'] : '';
		$state = isset( $fields['_hvnly_property_country_state'] ) ? (string) $fields['_hvnly_property_country_state'] : '';
		$loc   = trim( $city . ( $state !== '' ? ', ' . $state : '' ) );

		$ts = WorkspaceDateTime::from_post_modified( $post );
		if ( $ts <= 0 ) {
			$ts = WorkspaceDateTime::now();
		}

		$thumb_id  = get_post_thumbnail_id( $post );
		$thumb_url = $thumb_id ? (string) wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';

		return array(
			'id'           => (int) $post->ID,
			'title'        => (string) $post->post_title,
			'status'       => $values['status'] !== '' ? $values['status'] : 'Draft',
			'postStatus'   => (string) $post->post_status,
			'price'        => $price,
			'location'     => $loc,
			'updatedAt'    => WorkspaceDateTime::relative_ago( $ts ),
			'modifiedGmt'  => (string) $post->post_modified_gmt,
			'thumbnailUrl' => $thumb_url,
			'publicUrl'    => (string) ( get_permalink( $post ) ?: '' ),
			'previewUrl'   => (string) ( get_preview_post_link( $post ) ?: '' ),
		);
	}

	/**
	 * Sync portal listing-status label with workflow (Draft / Pending / Published / Rejected).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $label   Display label.
	 * @return void
	 */
	public static function sync_listing_status( int $post_id, string $label ): void {
		$allowed = array( 'Draft', 'Pending', 'Published', 'Rejected' );
		if ( ! in_array( $label, $allowed, true ) ) {
			$label = 'Draft';
		}
		self::update_meta( $post_id, self::META_LISTING_STATUS, $label );
	}

	/**
	 * Current global currency code (settings key hvnly_currencyType).
	 *
	 * @return string
	 */
	private static function current_currency_code(): string {
		$helper = ( function_exists( 'HVNLY_NAB' ) && HVNLY_NAB() ) ? HVNLY_NAB()->Helper : null;
		if ( $helper && method_exists( $helper, 'get_current_currency_code' ) ) {
			return (string) $helper->get_current_currency_code();
		}
		return 'USD';
	}

	/**
	 * Persist currency to existing plugin settings key (not post meta).
	 *
	 * @deprecated 0.5.2 Workspace must not change global currency from property forms.
	 *
	 * @param string $code ISO currency code.
	 * @return void
	 */
	private static function persist_currency_setting( string $code ): void {
		unset( $code );
		// Intentionally no-op: currency lives in Settings → General → Currency only.
	}

	/**
	 * Normalize document rows to admin DocumentField shape.
	 *
	 * @param array<int, mixed> $rows Raw rows.
	 * @return array<int, array{icon:string,label:string,url:string,url_type:string}>
	 */
	private static function normalize_document_rows( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? (string) $row['label'] : '';
			if ( '' === $label && isset( $row['title'] ) ) {
				$label = (string) $row['title'];
			}
			if ( '' === $label && isset( $row['name'] ) ) {
				$label = (string) $row['name'];
			}
			$url = isset( $row['url'] ) ? (string) $row['url'] : '';
			if ( '' === $url && isset( $row['id'] ) ) {
				$url = (string) $row['id'];
			}
			$icon     = isset( $row['icon'] ) ? (string) $row['icon'] : '';
			$url_type = isset( $row['url_type'] ) ? (string) $row['url_type'] : 'custom';
			if ( '' === $label && '' === $url ) {
				continue;
			}
			$out[] = array(
				'icon'     => sanitize_text_field( $icon ),
				'label'    => sanitize_text_field( $label ),
				'url'      => esc_url_raw( $url ),
				'url_type' => sanitize_key( $url_type ),
			);
		}
		return $out;
	}

	/**
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return void
	 */
	private static function update_meta( int $post_id, string $key, $value ): void {
		if ( '' === $value || null === $value || ( is_array( $value ) && empty( $value ) ) ) {
			delete_post_meta( $post_id, $key );
			return;
		}
		update_post_meta( $post_id, $key, $value );
	}
}
