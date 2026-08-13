<?php
/**
 * Deterministic duplicate detection for HPTP import.
 *
 * @package HvnlyNab\ImportExport\Import
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Import;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * DuplicateDetector — logical keys only (never source DB IDs).
 *
 * Match order (approved):
 * - Terms: taxonomy + slug
 * - Agency: slug
 * - Agent: email → slug
 * - Property: unique ID → MLS → reference → slug → title
 *
 * @since 3.6.0
 */
final class DuplicateDetector {

	public const POLICY_SKIP       = 'skip';
	public const POLICY_UPDATE     = 'update';
	public const POLICY_OVERWRITE  = 'overwrite';
	public const POLICY_CREATE_NEW = 'create_new';

	public const META_UNIQUE_ID = '_hvnly_unique_property_id';
	public const META_MLS       = '_hvnly_property_mls_number';
	public const META_REFERENCE = '_hvnly_property_reference_number';

	/**
	 * @param string $policy Policy string.
	 * @return string Normalized policy.
	 */
	public static function normalize_policy( string $policy ): string {
		$policy  = strtolower( trim( $policy ) );
		$allowed = array(
			self::POLICY_SKIP,
			self::POLICY_UPDATE,
			self::POLICY_OVERWRITE,
			self::POLICY_CREATE_NEW,
		);
		return in_array( $policy, $allowed, true ) ? $policy : self::POLICY_SKIP;
	}

	/**
	 * Find existing property taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $slug     Slug.
	 * @return int Term ID or 0.
	 */
	public function find_term( string $taxonomy, string $slug ): int {
		$taxonomy = sanitize_key( $taxonomy );
		$slug     = sanitize_title( $slug );
		if ( '' === $taxonomy || '' === $slug || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );
		return ( $term instanceof \WP_Term ) ? (int) $term->term_id : 0;
	}

	/**
	 * Find existing agency term by slug.
	 *
	 * @param string $slug Agency slug.
	 * @return int
	 */
	public function find_agency( string $slug ): int {
		$slug = sanitize_title( $slug );
		if ( '' === $slug || ! taxonomy_exists( AgentConstants::TAXONOMY_AGENCY ) ) {
			return 0;
		}

		$term = get_term_by( 'slug', $slug, AgentConstants::TAXONOMY_AGENCY );
		return ( $term instanceof \WP_Term ) ? (int) $term->term_id : 0;
	}

	/**
	 * Find existing agent by portable identity order:
	 * portable_id → linked user id → email → slug → reference.
	 *
	 * @param string               $email Email (agent profile or linked user).
	 * @param string               $slug  Slug.
	 * @param array<string, mixed> $row   Optional full agent row for extra keys.
	 * @return int Post ID or 0.
	 */
	public function find_agent( string $email = '', string $slug = '', array $row = array() ): int {
		$email = strtolower( sanitize_email( $email ) );
		$slug  = sanitize_title( $slug );

		if ( '' === $email && ! empty( $row['email'] ) ) {
			$email = strtolower( sanitize_email( (string) $row['email'] ) );
		}
		if ( '' === $email && ! empty( $row['linked_user_email'] ) ) {
			$email = strtolower( sanitize_email( (string) $row['linked_user_email'] ) );
		}
		if ( '' === $slug && ! empty( $row['slug'] ) ) {
			$slug = sanitize_title( (string) $row['slug'] );
		}

		$portable_id = trim( (string) ( $row['portable_id'] ?? $row['portable_key'] ?? '' ) );
		if ( '' !== $portable_id ) {
			$id = $this->find_agent_by_meta( '_hvnly_agent_portable_id', $portable_id );
			if ( $id > 0 ) {
				return $id;
			}
		}

		$linked_user_id = absint( $row['linked_user_id'] ?? 0 );
		if ( $linked_user_id <= 0 && '' !== $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user instanceof \WP_User ) {
				$linked_user_id = (int) $user->ID;
			}
		}
		if ( $linked_user_id > 0 ) {
			$id = $this->find_agent_by_meta( AgentConstants::META_LINKED_USER_ID, (string) $linked_user_id );
			if ( $id > 0 ) {
				return $id;
			}
		}

		if ( '' !== $email ) {
			$id = $this->find_agent_by_meta( AgentConstants::META_EMAIL, $email );
			if ( $id > 0 ) {
				return $id;
			}
		}

		if ( '' !== $slug ) {
			$existing = get_page_by_path( $slug, OBJECT, AgentConstants::POST_TYPE );
			if ( $existing instanceof \WP_Post ) {
				return (int) $existing->ID;
			}
		}

		$reference = trim( (string) ( $row['reference'] ?? $row['reference_number'] ?? '' ) );
		if ( '' !== $reference ) {
			$id = $this->find_agent_by_meta( '_hvnly_agent_reference', $reference );
			if ( $id > 0 ) {
				return $id;
			}
		}

		return 0;
	}

	/**
	 * @param string $meta_key Meta key.
	 * @param string $value Exact value.
	 * @return int
	 */
	private function find_agent_by_meta( string $meta_key, string $value ): int {
		if ( '' === $meta_key || '' === $value || ! post_type_exists( AgentConstants::POST_TYPE ) ) {
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
		return ! empty( $query->posts[0] ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Find existing property by approved logical match order.
	 *
	 * @param array<string, mixed> $row Portable property row (or identity subset).
	 * @return int Post ID or 0.
	 */
	public function find_property( array $row ): int {
		$unique = trim( (string) ( $row['unique_property_id'] ?? '' ) );
		$mls    = trim( (string) ( $row['mls_number'] ?? '' ) );
		$ref    = trim( (string) ( $row['reference_number'] ?? '' ) );
		$slug   = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		$title  = trim( (string) ( $row['title'] ?? '' ) );

		if ( '' !== $unique ) {
			$id = $this->find_property_by_meta( self::META_UNIQUE_ID, $unique );
			if ( $id > 0 ) {
				return $id;
			}
		}

		if ( '' !== $mls ) {
			$id = $this->find_property_by_meta( self::META_MLS, $mls );
			if ( $id > 0 ) {
				return $id;
			}
		}

		if ( '' !== $ref ) {
			$id = $this->find_property_by_meta( self::META_REFERENCE, $ref );
			if ( $id > 0 ) {
				return $id;
			}
		}

		if ( '' !== $slug && post_type_exists( AgentConstants::PROPERTY_POST_TYPE ) ) {
			$existing = get_page_by_path( $slug, OBJECT, AgentConstants::PROPERTY_POST_TYPE );
			if ( $existing instanceof \WP_Post ) {
				return (int) $existing->ID;
			}
		}

		if ( '' !== $title && post_type_exists( AgentConstants::PROPERTY_POST_TYPE ) ) {
			return $this->find_property_by_title( $title );
		}

		return 0;
	}

	/**
	 * @param string $meta_key Meta key.
	 * @param string $value    Exact value.
	 * @return int
	 */
	private function find_property_by_meta( string $meta_key, string $value ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'expired' ),
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

		return ! empty( $query->posts[0] ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Exact title match; lowest ID wins when duplicates exist.
	 *
	 * @param string $title Title.
	 * @return int
	 */
	private function find_property_by_title( string $title ): int {
		global $wpdb;

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
					AND post_title = %s
					AND post_status NOT IN ('trash', 'auto-draft', 'inherit')
				ORDER BY ID ASC
				LIMIT 1",
				AgentConstants::PROPERTY_POST_TYPE,
				$title
			)
		);

		return absint( $id );
	}
}
