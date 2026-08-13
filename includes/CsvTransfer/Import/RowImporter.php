<?php
/**
 * Creates/updates hvnly_property posts from a mapped CSV row.
 *
 * @package HvnlyNab\CsvTransfer\Import
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Import;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Core\GroupFieldIdentity;
use HvnlyNab\CsvTransfer\Mapping\FieldCatalog;
use HvnlyNab\CsvTransfer\Mapping\SchemaTargets;
use HvnlyNab\Workspace\Api\PropertyFormMapper;

defined( 'ABSPATH' ) || exit;

/**
 * RowImporter — writes one mapped CSV row into a property post.
 *
 * Storage keys mirror the Property Editor (builder group keys + legacy dual-write).
 *
 * @since 3.7.0
 */
final class RowImporter {

	public const META_PENDING_MEDIA = '_hvnly_csv_pending_media';
	public const META_SOURCE        = '_hvnly_csv_import_source';
	public const META_AMENITIES     = '_hvnly_property_amenities';

	/** @deprecated Kept for export/compat with older CSV rows that wrote this key. */
	public const META_VIDEOS = '_hvnly_csv_videos';

	public const STATUS_CREATED = 'created';
	public const STATUS_UPDATED = 'updated';
	public const STATUS_SKIPPED = 'skipped';
	public const STATUS_FAILED  = 'failed';

	/** @var array<string, true> Field kinds handled outside generic meta write. */
	private const SKIP_GENERIC = array(
		'post'           => true,
		'taxonomy'       => true,
		'featured_image' => true,
		'gallery'        => true,
		'documents'      => true,
		'agent_email'    => true,
		'agent_username' => true,
		'co_agents'      => true,
	);

	/**
	 * @param array<string, string> $fields Mapped fields (field id => raw string value).
	 * @param string                $duplicate_policy skip|update|replace.
	 * @param string                $job_id Optional import job id (scopes pending media).
	 * @param array<string, mixed>  $options Import options (gallery_as_featured, …).
	 * @return array{status:string,post_id:int,warnings:array<int,string>}
	 */
	public static function import_row( array $fields, string $duplicate_policy, string $job_id = '', array $options = array() ): array {
		$warnings = array();
		$title    = isset( $fields['title'] ) ? sanitize_text_field( (string) $fields['title'] ) : '';

		if ( '' === $title ) {
			return array(
				'status' => self::STATUS_FAILED,
				'post_id' => 0,
				'warnings' => array( __( 'Row missing required Title value.', 'havenlytics' ) ),
			);
		}

		if ( ! post_type_exists( AgentConstants::PROPERTY_POST_TYPE ) ) {
			return array(
				'status' => self::STATUS_FAILED,
				'post_id' => 0,
				'warnings' => array( __( 'Property post type is not registered.', 'havenlytics' ) ),
			);
		}

		$policy   = DuplicateMatcher::normalize_policy( $duplicate_policy );
		$existing = DuplicateMatcher::find( $fields );

		if ( $existing > 0 && DuplicateMatcher::POLICY_SKIP === $policy ) {
			return array(
				'status' => self::STATUS_SKIPPED,
				'post_id' => $existing,
				'warnings' => $warnings,
			);
		}

		$status = self::normalize_status( isset( $fields['status'] ) ? (string) $fields['status'] : 'publish' );

		$postarr = array(
			'post_title'   => $title,
			'post_content' => isset( $fields['content'] ) ? wp_kses_post( (string) $fields['content'] ) : '',
			'post_excerpt' => isset( $fields['excerpt'] ) ? sanitize_textarea_field( (string) $fields['excerpt'] ) : '',
			'post_status'  => $status,
			'post_type'    => AgentConstants::PROPERTY_POST_TYPE,
		);

		if ( isset( $fields['slug'] ) && '' !== trim( (string) $fields['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $fields['slug'] );
		}
		if ( isset( $fields['menu_order'] ) && is_numeric( trim( (string) $fields['menu_order'] ) ) ) {
			$postarr['menu_order'] = (int) $fields['menu_order'];
		}
		if ( isset( $fields['post_date'] ) && '' !== trim( (string) $fields['post_date'] ) ) {
			$ts = strtotime( (string) $fields['post_date'] );
			if ( false !== $ts ) {
				$postarr['post_date']     = gmdate( 'Y-m-d H:i:s', $ts );
				$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}
		if ( isset( $fields['author'] ) && '' !== trim( (string) $fields['author'] ) ) {
			$author_id = self::resolve_author_id( (string) $fields['author'] );
			if ( $author_id > 0 ) {
				$postarr['post_author'] = $author_id;
			} else {
				$warnings[] = __( 'Author could not be resolved; using the importing user.', 'havenlytics' );
			}
		}

		if ( $existing > 0 ) {
			$postarr['ID'] = $existing;
			if ( DuplicateMatcher::POLICY_REPLACE === $policy ) {
				self::clear_meta( $existing );
			}
			$result = wp_update_post( wp_slash( $postarr ), true );
			$is_new = false;
		} else {
			$result = wp_insert_post( wp_slash( $postarr ), true );
			$is_new = true;
		}

		if ( is_wp_error( $result ) ) {
			$warnings[] = $result->get_error_message();
			return array(
				'status' => self::STATUS_FAILED,
				'post_id' => 0,
				'warnings' => $warnings,
			);
		}

		$post_id = absint( $result );
		if ( $post_id <= 0 ) {
			return array(
				'status' => self::STATUS_FAILED,
				'post_id' => 0,
				'warnings' => array( __( 'Could not save property post.', 'havenlytics' ) ),
			);
		}

		self::write_flags( $post_id, $fields );
		self::write_mapped_fields( $post_id, $fields );
		self::write_taxonomies( $post_id, $fields, $warnings );
		self::write_agents( $post_id, $fields, $warnings );
		self::queue_media( $post_id, $fields, $job_id, $options );

		update_post_meta( $post_id, self::META_SOURCE, 'csv' );

		return array(
			'status'   => $is_new ? self::STATUS_CREATED : self::STATUS_UPDATED,
			'post_id'  => $post_id,
			'warnings' => $warnings,
		);
	}

	/**
	 * @param int                   $post_id Post ID.
	 * @param array<string, string> $fields Mapped fields.
	 * @return void
	 */
	private static function write_flags( int $post_id, array $fields ): void {
		if ( isset( $fields['featured'] ) && '' !== trim( (string) $fields['featured'] ) ) {
			$featured = self::truthy( (string) $fields['featured'] ) ? '1' : '0';
			update_post_meta( $post_id, '_hvnly_property_featured', $featured );
		}
		if ( isset( $fields['sticky'] ) && '' !== trim( (string) $fields['sticky'] ) ) {
			if ( self::truthy( (string) $fields['sticky'] ) ) {
				stick_post( $post_id );
			} else {
				unstick_post( $post_id );
			}
		}
	}

	/**
	 * Write meta / group / JSON widget fields.
	 *
	 * Video / Gallery / Map go through {@see PropertyFormMapper::apply_values()}
	 * (same portal dual-write + `_hvnly_field_map` path as Workspace / Onboarding).
	 *
	 * @param int                   $post_id Post ID.
	 * @param array<string, string> $fields Mapped fields.
	 * @return void
	 */
	private static function write_mapped_fields( int $post_id, array $fields ): void {
		$builder_values = self::extract_builder_group_values( $fields );
		if ( ! empty( $builder_values ) && class_exists( PropertyFormMapper::class ) ) {
			PropertyFormMapper::apply_values(
				$post_id,
				array( 'fields' => $builder_values ),
				true
			);
		} elseif ( ! empty( $builder_values ) ) {
			foreach ( $builder_values as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
			if ( class_exists( GroupFieldIdentity::class ) ) {
				GroupFieldIdentity::record_schema_groups( $post_id, array( 'video', 'gallery', 'map' ) );
			}
		}

		foreach ( $fields as $field_id => $raw ) {
			$field_id = (string) $field_id;
			$value    = trim( (string) $raw );
			if ( '' === $value ) {
				continue;
			}

			// Skip fields consumed by post create / taxonomies / agents / media queue / builder groups.
			if ( in_array( $field_id, array( 'title', 'slug', 'content', 'excerpt', 'status', 'author', 'post_date', 'menu_order', 'featured', 'sticky', 'featured_image', 'gallery', 'documents', 'agent_email', 'agent_username', 'co_agents', 'department', 'property_type', 'location', 'property_status', 'features', 'tags', 'badges', 'categories' ), true ) ) {
				continue;
			}
			if ( self::is_builder_group_field( $field_id ) ) {
				continue;
			}

			$resolved = SchemaTargets::resolve( $field_id );
			$kind     = (string) ( $resolved['kind'] ?? 'meta' );

			if ( isset( self::SKIP_GENERIC[ $kind ] ) ) {
				continue;
			}

			switch ( $kind ) {
				case 'amenities':
					update_post_meta( $post_id, self::META_AMENITIES, self::split_list( $value ) );
					break;

				case 'features_meta':
					$items = self::split_list( $value );
					$json  = wp_json_encode( array_values( $items ) );
					foreach ( (array) ( $resolved['keys'] ?? array() ) as $key ) {
						update_post_meta( $post_id, $key, $json ? $json : '[]' );
					}
					break;

				case 'faq':
					$payload = self::parse_faq_payload( $value );
					if ( null === $payload ) {
						break;
					}
					foreach ( (array) ( $resolved['keys'] ?? array() ) as $key ) {
						update_post_meta( $post_id, $key, $payload );
					}
					break;

				case 'repeater':
					$payload = self::parse_highlights_payload( $value );
					if ( null === $payload ) {
						break;
					}
					foreach ( (array) ( $resolved['keys'] ?? array() ) as $key ) {
						update_post_meta( $post_id, $key, $payload );
					}
					break;

				case 'group':
				case 'meta':
				case 'video':
				default:
					$sanitized = self::sanitize_scalar( $field_id, $value );
					if ( '' === $sanitized ) {
						break;
					}
					$keys = (array) ( $resolved['keys'] ?? array() );
					if ( empty( $keys ) && ! FieldCatalog::is_core_field_id( $field_id ) ) {
						if ( preg_match( '/^[a-z0-9_\-]+$/i', $field_id ) ) {
							$keys = array( $field_id );
						}
					}
					foreach ( $keys as $key ) {
						update_post_meta( $post_id, $key, $sanitized );
					}
					break;
			}
		}
	}

	/**
	 * Whether a logical CSV field belongs to Property Video / Gallery / Location groups.
	 *
	 * @param string $field_id Logical field id.
	 * @return bool
	 */
	private static function is_builder_group_field( string $field_id ): bool {
		$resolved = SchemaTargets::resolve( $field_id );
		$def      = (array) ( $resolved['def'] ?? array() );
		$gt       = (string) ( $def['group_type'] ?? '' );
		return in_array( $gt, array( 'video', 'gallery', 'map' ), true ) && 'gallery' !== $field_id;
	}

	/**
	 * Map CSV logical group fields → Builder storage names for PropertyFormMapper.
	 *
	 * @param array<string, string> $fields Mapped CSV fields.
	 * @return array<string, string> Storage name => value.
	 */
	private static function extract_builder_group_values( array $fields ): array {
		$out = array();

		foreach ( $fields as $field_id => $raw ) {
			$field_id = (string) $field_id;
			$value    = trim( (string) $raw );
			if ( '' === $value || 'gallery' === $field_id ) {
				continue;
			}

			$resolved = SchemaTargets::resolve( $field_id );
			$def      = (array) ( $resolved['def'] ?? array() );
			$gt       = (string) ( $def['group_type'] ?? '' );
			$suffix   = (string) ( $def['suffix'] ?? '' );
			$kind     = (string) ( $resolved['kind'] ?? '' );

			if ( ! in_array( $gt, array( 'video', 'gallery', 'map' ), true ) || '' === $suffix ) {
				continue;
			}

			$storage = SchemaTargets::builder_key( $gt, $suffix );
			if ( '' === $storage ) {
				continue;
			}

			if ( 'video' === $kind || 'video_url' === $field_id || 'video_thumbnail' === $field_id ) {
				$list  = self::split_list( $value );
				$first = ! empty( $list ) ? $list[0] : $value;
				$value = esc_url_raw( trim( (string) $first ) );
			} else {
				$value = self::sanitize_scalar( $field_id, $value );
			}

			if ( '' === $value ) {
				continue;
			}

			$out[ $storage ] = $value;
		}

		return $out;
	}

	/**
	 * @param string $field_id Field id.
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_scalar( string $field_id, string $value ): string {
		$numeric_ids = array(
			'latitude',
			'longitude',
			'views',
			'bedrooms',
			'bathrooms',
			'half_bathrooms',
			'reception_rooms',
			'kitchens',
			'floors',
			'rooms',
			'year_built',
			'area',
			'garage_sqft',
			'lot_size',
			'price',
			'hoa_fee',
			'annual_tax',
		);
		if ( in_array( $field_id, $numeric_ids, true ) ) {
			return is_numeric( trim( $value ) ) ? (string) trim( $value ) : sanitize_text_field( $value );
		}
		if ( 'video_url' === $field_id || false !== strpos( $field_id, '_url' ) ) {
			$list  = self::split_list( $value );
			$first = ! empty( $list ) ? $list[0] : $value;
			return esc_url_raw( trim( $first ) );
		}
		return sanitize_text_field( $value );
	}

	/**
	 * FAQ CSV: JSON array, or Question::Answer|Question::Answer.
	 *
	 * @param string $value Raw cell.
	 * @return string|null JSON string.
	 */
	private static function parse_faq_payload( string $value ): ?string {
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return null;
		}
		if ( '{' === $trimmed[0] || '[' === $trimmed[0] ) {
			$decoded = json_decode( $trimmed, true );
			if ( is_array( $decoded ) ) {
				$items = array();
				foreach ( $decoded as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$q = sanitize_text_field( (string) ( $row['question'] ?? '' ) );
					$a = sanitize_textarea_field( (string) ( $row['answer'] ?? '' ) );
					if ( '' === $q && '' === $a ) {
						continue;
					}
					$items[] = array(
						'question' => $q,
						'answer' => $a,
					);
				}
				$json = wp_json_encode( $items );
				return $json ? $json : null;
			}
		}

		$items = array();
		foreach ( self::split_list( $trimmed ) as $pair ) {
			$parts = preg_split( '/\s*::\s*/', $pair, 2 );
			if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
				continue;
			}
			$q = sanitize_text_field( $parts[0] );
			$a = sanitize_textarea_field( $parts[1] );
			if ( '' === $q && '' === $a ) {
				continue;
			}
			$items[] = array(
				'question' => $q,
				'answer' => $a,
			);
		}
		if ( empty( $items ) ) {
			return null;
		}
		$json = wp_json_encode( $items );
		return $json ? $json : null;
	}

	/**
	 * Highlights CSV: JSON array, or Title::Value|Title::Value.
	 *
	 * @param string $value Raw cell.
	 * @return string|null JSON string.
	 */
	private static function parse_highlights_payload( string $value ): ?string {
		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return null;
		}
		if ( '{' === $trimmed[0] || '[' === $trimmed[0] ) {
			$decoded = json_decode( $trimmed, true );
			if ( is_array( $decoded ) ) {
				$items = array();
				foreach ( $decoded as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$items[] = array(
						'title' => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
						'value' => sanitize_text_field( (string) ( $row['value'] ?? '' ) ),
						'icon'  => sanitize_text_field( (string) ( $row['icon'] ?? '' ) ),
					);
				}
				$json = wp_json_encode( $items );
				return $json ? $json : null;
			}
		}

		$items = array();
		foreach ( self::split_list( $trimmed ) as $pair ) {
			$parts = preg_split( '/\s*::\s*/', $pair, 2 );
			if ( ! is_array( $parts ) ) {
				continue;
			}
			$title = sanitize_text_field( $parts[0] ?? '' );
			$val   = sanitize_text_field( $parts[1] ?? '' );
			if ( '' === $title && '' === $val ) {
				continue;
			}
			$items[] = array(
				'title' => $title,
				'value' => $val,
				'icon' => '',
			);
		}
		if ( empty( $items ) ) {
			return null;
		}
		$json = wp_json_encode( $items );
		return $json ? $json : null;
	}

	/**
	 * @param int                    $post_id Post ID.
	 * @param array<string, string>  $fields Mapped fields.
	 * @param array<int, string>     $warnings Warnings (by ref).
	 * @return void
	 */
	private static function write_taxonomies( int $post_id, array $fields, array &$warnings ): void {
		$map = array(
			'department'      => 'hvnly_prop_depts',
			'property_type'   => 'hvnly_prop_types',
			'property_status' => 'hvnly_prop_status',
			'features'        => 'hvnly_prop_features',
			'location'        => 'hvnly_prop_locations',
			'tags'            => 'hvnly_prop_tags',
			'badges'          => 'hvnly_prop_badges',
			'categories'      => 'hvnly_prop_categories',
		);

		foreach ( $map as $field_id => $taxonomy ) {
			if ( ! isset( $fields[ $field_id ] ) || '' === trim( (string) $fields[ $field_id ] ) ) {
				continue;
			}
			if ( ! taxonomy_exists( $taxonomy ) ) {
				$warnings[] = sprintf(
					/* translators: %s: taxonomy slug */
					__( 'Taxonomy "%s" is not registered; values were skipped.', 'havenlytics' ),
					$taxonomy
				);
				continue;
			}
			$names = self::split_list( (string) $fields[ $field_id ] );
			if ( empty( $names ) ) {
				continue;
			}
			$term_ids = self::ensure_terms( $names, $taxonomy, $warnings );
			if ( empty( $term_ids ) ) {
				continue;
			}
			$result = wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
			if ( is_wp_error( $result ) ) {
				$warnings[] = $result->get_error_message();
			}
		}
	}

	/**
	 * Reuse existing terms by name; create missing ones automatically.
	 *
	 * @param array<int, string> $names Term names.
	 * @param string             $taxonomy Taxonomy slug.
	 * @param array<int, string> $warnings Warnings (by ref).
	 * @return array<int, int> Term IDs.
	 */
	private static function ensure_terms( array $names, string $taxonomy, array &$warnings ): array {
		$term_ids = array();
		foreach ( $names as $name ) {
			$name = sanitize_text_field( (string) $name );
			if ( '' === $name ) {
				continue;
			}

			$existing = term_exists( $name, $taxonomy );
			if ( is_array( $existing ) && ! empty( $existing['term_id'] ) ) {
				$term_ids[] = (int) $existing['term_id'];
				continue;
			}
			if ( is_int( $existing ) && $existing > 0 ) {
				$term_ids[] = $existing;
				continue;
			}

			$created = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $created ) ) {
				$retry = term_exists( $name, $taxonomy );
				if ( is_array( $retry ) && ! empty( $retry['term_id'] ) ) {
					$term_ids[] = (int) $retry['term_id'];
					continue;
				}
				$warnings[] = $created->get_error_message();
				continue;
			}
			$term_ids[] = (int) ( $created['term_id'] ?? 0 );
		}

		return array_values( array_filter( $term_ids ) );
	}

	/**
	 * @param int                    $post_id Post ID.
	 * @param array<string, string>  $fields Mapped fields.
	 * @param array<int, string>     $warnings Warnings (by ref).
	 * @return void
	 */
	private static function write_agents( int $post_id, array $fields, array &$warnings ): void {
		$agent_ids = array();

		$email = isset( $fields['agent_email'] ) ? trim( (string) $fields['agent_email'] ) : '';
		if ( '' !== $email ) {
			$id = self::find_agent_by_email( $email );
			if ( $id > 0 ) {
				$agent_ids[] = $id;
			} else {
				$warnings[] = sprintf(
					/* translators: %s: agent email */
					__( 'No agent found for email "%s"; property saved without that agent.', 'havenlytics' ),
					$email
				);
			}
		}

		$username = isset( $fields['agent_username'] ) ? trim( (string) $fields['agent_username'] ) : '';
		if ( '' !== $username ) {
			$id = self::find_agent_by_username( $username );
			if ( $id > 0 ) {
				$agent_ids[] = $id;
			} else {
				$warnings[] = sprintf(
					/* translators: %s: agent username */
					__( 'No agent found for username "%s".', 'havenlytics' ),
					$username
				);
			}
		}

		if ( isset( $fields['co_agents'] ) && '' !== trim( (string) $fields['co_agents'] ) ) {
			foreach ( self::split_list( (string) $fields['co_agents'] ) as $co ) {
				$co = trim( $co );
				if ( '' === $co ) {
					continue;
				}
				$id = is_email( $co ) ? self::find_agent_by_email( $co ) : self::find_agent_by_username( $co );
				if ( $id > 0 ) {
					$agent_ids[] = $id;
				}
			}
		}

		$agent_ids = array_values( array_unique( array_filter( array_map( 'absint', $agent_ids ) ) ) );
		if ( empty( $agent_ids ) ) {
			return;
		}

		// Agent system SSOT (array of IDs).
		update_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS, $agent_ids );

		// Builder agents widget key (JSON string) when present in schema.
		$builder_key = SchemaTargets::builder_key( 'agents', 'agents' );
		if ( '' !== $builder_key ) {
			update_post_meta( $post_id, $builder_key, wp_json_encode( $agent_ids ) );
		}
	}

	/**
	 * Resolve an existing Agent CPT by email.
	 *
	 * Order mirrors Havenlytics agent identity:
	 * 1) Linked WP user email → Agent via META_LINKED_USER_ID
	 * 2) Agent profile email meta (META_EMAIL)
	 *
	 * Never creates agents.
	 *
	 * @param string $email Agent email.
	 * @return int Agent post ID.
	 */
	private static function find_agent_by_email( string $email ): int {
		$sanitized = sanitize_email( $email );
		if ( ! is_email( $sanitized ) ) {
			return 0;
		}
		$email_lc = strtolower( $sanitized );

		// Prefer linked WordPress user → Agent CPT relationship.
		$user = get_user_by( 'email', $sanitized );
		if ( $user instanceof \WP_User ) {
			$id = self::find_agent_by_linked_user_id( (int) $user->ID );
			if ( $id > 0 ) {
				return $id;
			}
		}

		// Fall back to the Agent profile email field (try original + lowercase).
		foreach ( array_unique( array( $sanitized, $email_lc ) ) as $candidate ) {
			$id = self::find_agent_by_meta( AgentConstants::META_EMAIL, $candidate );
			if ( $id > 0 ) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * @param int $user_id Linked WP user ID.
	 * @return int Agent post ID.
	 */
	private static function find_agent_by_linked_user_id( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		// Match AgentProvisioner storage (integer user id in meta).
		return self::find_agent_by_meta( AgentConstants::META_LINKED_USER_ID, $user_id );
	}

	/**
	 * @param string     $meta_key Meta key.
	 * @param string|int $value Exact meta value.
	 * @return int Agent post ID.
	 */
	private static function find_agent_by_meta( string $meta_key, $value ): int {
		if ( '' === $meta_key || '' === (string) $value || ! post_type_exists( AgentConstants::POST_TYPE ) ) {
			return 0;
		}
		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => $meta_key,
						'value' => $value,
					),
				),
			)
		);
		return ! empty( $query->posts ) ? absint( $query->posts[0] ) : 0;
	}

	/**
	 * @param string $username WP username or agent title.
	 * @return int Agent post ID.
	 */
	private static function find_agent_by_username( string $username ): int {
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			return 0;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => AgentConstants::META_LINKED_USER_ID,
						'value' => (string) $user->ID,
					),
				),
			)
		);
		return ! empty( $query->posts ) ? absint( $query->posts[0] ) : 0;
	}

	/**
	 * @param int                   $post_id Post ID.
	 * @param array<string, string> $fields Mapped fields.
	 * @param string                $job_id Import job id that owns this media queue.
	 * @param array<string, mixed>  $options Import options.
	 * @return void
	 */
	private static function queue_media( int $post_id, array $fields, string $job_id = '', array $options = array() ): void {
		$pending = array();

		$featured = '';
		if ( isset( $fields['featured_image'] ) && '' !== trim( (string) $fields['featured_image'] ) ) {
			$featured = esc_url_raw( trim( (string) $fields['featured_image'] ) );
		}

		$gallery_urls = array();
		if ( isset( $fields['gallery'] ) && '' !== trim( (string) $fields['gallery'] ) ) {
			$gallery_urls = array_values( array_filter( array_map( 'esc_url_raw', self::split_list( (string) $fields['gallery'] ) ) ) );
		}

		// Explicit Featured Image always wins; otherwise optionally promote first gallery image.
		if ( '' === $featured && ! empty( $options['gallery_as_featured'] ) && ! empty( $gallery_urls ) ) {
			$featured = $gallery_urls[0];
		}

		if ( '' !== $featured ) {
			$pending['featured_image'] = $featured;
		}
		if ( ! empty( $gallery_urls ) ) {
			$pending['gallery'] = $gallery_urls;
		}

		if ( isset( $fields['documents'] ) && '' !== trim( (string) $fields['documents'] ) ) {
			$doc_urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', self::split_list( (string) $fields['documents'] ) ) ) ) );
			if ( ! empty( $doc_urls ) ) {
				$pending['documents'] = $doc_urls;
			}
		}

		if ( empty( $pending ) ) {
			return;
		}

		if ( '' !== $job_id ) {
			$pending['job_id'] = $job_id;
		}

		$existing = get_post_meta( $post_id, self::META_PENDING_MEDIA, true );
		$existing = is_array( $existing ) ? $existing : array();

		$existing_job = isset( $existing['job_id'] ) ? (string) $existing['job_id'] : '';
		if ( '' !== $job_id && '' !== $existing_job && $existing_job !== $job_id ) {
			$existing = array();
		}

		$merged = array_merge( $existing, $pending );
		if ( '' !== $job_id ) {
			$merged['job_id'] = $job_id;
		}

		update_post_meta( $post_id, self::META_PENDING_MEDIA, $merged );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function clear_meta( int $post_id ): void {
		delete_post_meta( $post_id, self::META_PENDING_MEDIA );
		delete_post_meta( $post_id, self::META_AMENITIES );
	}

	/**
	 * @param string $status Raw status text.
	 * @return string WordPress post status.
	 */
	private static function normalize_status( string $status ): string {
		$status = strtolower( trim( $status ) );
		$map    = array(
			'publish'   => 'publish',
			'published' => 'publish',
			'active'    => 'publish',
			'for sale'  => 'publish',
			'for rent'  => 'publish',
			'live'      => 'publish',
			'draft'     => 'draft',
			'pending'   => 'pending',
			'private'   => 'private',
			'expired'   => 'expired',
			'sold'      => 'publish',
			'inactive'  => 'draft',
		);
		if ( isset( $map[ $status ] ) ) {
			return $map[ $status ];
		}
		$slug = sanitize_key( $status );
		return in_array( $slug, array( 'publish', 'draft', 'pending', 'private', 'expired' ), true ) ? $slug : 'publish';
	}

	/**
	 * @param string $value Raw author login/email/id.
	 * @return int
	 */
	private static function resolve_author_id( string $value ): int {
		$value = trim( $value );
		if ( is_numeric( $value ) ) {
			$user = get_user_by( 'id', (int) $value );
			return $user ? (int) $user->ID : 0;
		}
		if ( is_email( $value ) ) {
			$user = get_user_by( 'email', $value );
			return $user ? (int) $user->ID : 0;
		}
		$user = get_user_by( 'login', $value );
		return $user ? (int) $user->ID : 0;
	}

	/**
	 * @param string $value Yes/no/1/0/true/false.
	 * @return bool
	 */
	private static function truthy( string $value ): bool {
		$value = strtolower( trim( $value ) );
		return in_array( $value, array( '1', 'true', 'yes', 'y', 'on', 'featured' ), true );
	}

	/**
	 * Split a CSV cell that may contain a delimited list of values.
	 *
	 * @param string $value Raw cell value.
	 * @return array<int, string>
	 */
	private static function split_list( string $value ): array {
		if ( false !== strpos( $value, '|' ) ) {
			$parts = explode( '|', $value );
		} elseif ( false !== strpos( $value, ';' ) ) {
			$parts = explode( ';', $value );
		} elseif ( preg_match( '/\r\n|\r|\n/', $value ) ) {
			$parts = preg_split( '/\r\n|\r|\n/', $value );
		} else {
			$parts = explode( ',', $value );
		}
		$parts = is_array( $parts ) ? $parts : array( $value );
		return array_values( array_filter( array_map( 'trim', $parts ), 'strlen' ) );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
