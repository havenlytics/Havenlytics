<?php
/**
 * Resolves property-to-agent assignments with legacy fallbacks.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Unified read path for property agent assignments.
 *
 * Priority:
 * 1. {@see AgentConstants::META_PROPERTY_AGENTS} (Agent CPT post IDs, ordered)
 * 2. {@see AgentConstants::META_PROPERTY_AGENT_LEGACY} (WordPress user ID)
 * 3. Property post author
 *
 * @since 3.0.2
 */
final class PropertyAgentResolver {

	/**
	 * Assigned Agent CPT post IDs for a property (may be empty).
	 *
	 * @param int $property_id Property post ID.
	 * @return int[]
	 */
	public static function get_assigned_agent_ids( int $property_id ): array {
		$property_id = absint( $property_id );
		if ( $property_id <= 0 ) {
			return array();
		}

		$stored = get_post_meta( $property_id, AgentConstants::META_PROPERTY_AGENTS, true );

		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return array();
		}

		$ids = array();
		foreach ( $stored as $agent_id ) {
			$agent_id = absint( $agent_id );
			if ( $agent_id > 0 && function_exists( 'hvnly_is_valid_agent' ) && hvnly_is_valid_agent( $agent_id ) ) {
				$ids[] = $agent_id;
			}
		}

		$ids = array_values( array_unique( $ids ) );

		/**
		 * Filter assigned Agent CPT IDs for a property.
		 *
		 * @since 3.0.2
		 *
		 * @param int[] $ids         Agent post IDs in display order.
		 * @param int   $property_id Property post ID.
		 */
		return apply_filters( 'hvnly_property_assigned_agent_ids', $ids, $property_id );
	}

	/**
	 * Whether the property has explicit CPT agent assignments saved.
	 *
	 * @param int $property_id Property post ID.
	 * @return bool
	 */
	public static function has_cpt_assignments( int $property_id ): bool {
		$raw = get_post_meta( absint( $property_id ), AgentConstants::META_PROPERTY_AGENTS, true );

		return is_array( $raw );
	}

	/**
	 * All resolved agent profiles for a property.
	 *
	 * @param int $property_id Property post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_agents( int $property_id ): array {
		$property_id = absint( $property_id );
		if ( $property_id <= 0 ) {
			return array();
		}

		$cpt_ids = self::get_assigned_agent_ids( $property_id );
		$agents  = array();

		if ( ! empty( $cpt_ids ) && function_exists( 'hvnly_get_agent' ) ) {
			foreach ( $cpt_ids as $agent_id ) {
				$profile = hvnly_get_agent( $agent_id );
				if ( ! empty( $profile ) ) {
					$agents[] = self::normalize_cpt_agent( $profile );
				}
			}
		}

		if ( ! empty( $agents ) ) {
			/**
			 * Filter resolved property agents (CPT assignments).
			 *
			 * @since 3.0.2
			 *
			 * @param array<int, array<string, mixed>> $agents      Agent profiles.
			 * @param int                               $property_id Property ID.
			 */
			return apply_filters( 'hvnly_property_agents', $agents, $property_id );
		}

		$cpt_from_user = self::get_cpt_agents_for_user( self::get_legacy_user_id( $property_id ) );
		if ( ! empty( $cpt_from_user ) ) {
			return apply_filters( 'hvnly_property_agents', $cpt_from_user, $property_id );
		}

		$legacy = self::get_legacy_user_agent( $property_id );
		if ( ! empty( $legacy ) ) {
			return array( $legacy );
		}

		return array();
	}

	/**
	 * Primary (first) agent for a property.
	 *
	 * @param int $property_id Property post ID.
	 * @return array<string, mixed>
	 */
	public static function get_primary( int $property_id ): array {
		$agents = self::get_agents( $property_id );

		if ( ! empty( $agents ) ) {
			return $agents[0];
		}

		return array();
	}

	/**
	 * Legacy WordPress user ID for a property.
	 *
	 * @param int $property_id Property post ID.
	 * @return int
	 */
	public static function get_legacy_user_id( int $property_id ): int {
		$property_id = absint( $property_id );
		if ( $property_id <= 0 ) {
			return 0;
		}

		$user_id = absint( get_post_meta( $property_id, AgentConstants::META_PROPERTY_AGENT_LEGACY, true ) );

		if ( ! $user_id ) {
			$user_id = absint( get_post_field( 'post_author', $property_id ) );
		}

		return $user_id;
	}

	/**
	 * Resolve Agent CPT profiles linked to a WordPress user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_cpt_agents_for_user( int $user_id ): array {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! function_exists( 'hvnly_get_agent' ) ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 10,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => AgentConstants::META_LINKED_USER_ID,
						'value' => $user_id,
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return array();
		}

		$agents = array();
		foreach ( $query->posts as $agent_id ) {
			$profile = hvnly_get_agent( (int) $agent_id );
			if ( ! empty( $profile ) ) {
				$agents[] = self::normalize_cpt_agent( $profile );
			}
		}

		return $agents;
	}

	/**
	 * Build normalized profile from legacy user assignment.
	 *
	 * @param int $property_id Property post ID.
	 * @return array<string, mixed>
	 */
	public static function get_legacy_user_agent( int $property_id ): array {
		$user_id = self::get_legacy_user_id( $property_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		$profile = array(
			'id'           => $user_id,
			'type'         => 'user',
			'source'       => 'user',
			'user_id'      => $user_id,
			'name'         => get_the_author_meta( 'display_name', $user_id ),
			'email'        => get_the_author_meta( 'user_email', $user_id ),
			'phone'        => (string) get_user_meta( $user_id, '_hvnly_agent_phone', true ),
			'whatsapp'     => (string) get_user_meta( $user_id, '_hvnly_agent_whatsapp', true ),
			'position'     => (string) get_user_meta( $user_id, '_hvnly_agent_position', true ),
			'website'      => (string) get_user_meta( $user_id, 'user_url', true ),
			'avatar'       => \HvnlyNab\Common\AvatarService::resolve_for_user( (int) $user_id, 200 ),
			'profile_url'  => '',
			'biography'    => '',
			'rating'       => function_exists( 'hvnly_get_author_property_ratings' )
				? (float) hvnly_get_author_property_ratings( $user_id )
				: 0.0,
			'review_count' => function_exists( 'hvnly_get_author_review_count' )
				? (int) hvnly_get_author_review_count( $user_id )
				: 0,
		);

		if ( '' === trim( $profile['position'] ) ) {
			$profile['position'] = __( 'Real Estate Agent', 'havenlytics' );
		}

		/**
		 * Filter legacy user agent profile for a property.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $profile     Agent profile.
		 * @param int                  $property_id Property ID.
		 * @param int                  $user_id     WordPress user ID.
		 */
		return apply_filters( 'hvnly_property_legacy_user_agent', $profile, $property_id, $user_id );
	}

	/**
	 * Sanitize and validate agent ID list for storage.
	 *
	 * @param mixed $raw Raw meta value or POST array.
	 * @return int[]
	 */
	public static function sanitize_agent_id_list( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();
		foreach ( $raw as $agent_id ) {
			$agent_id = absint( $agent_id );
			if ( $agent_id <= 0 ) {
				continue;
			}

			if ( function_exists( 'hvnly_is_valid_agent' ) && ! hvnly_is_valid_agent( $agent_id ) ) {
				continue;
			}

			if ( AgentConstants::POST_TYPE !== get_post_type( $agent_id ) ) {
				continue;
			}

			$ids[] = $agent_id;
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param array<string, mixed> $profile CPT agent profile.
	 * @return array<string, mixed>
	 */
	private static function normalize_cpt_agent( array $profile ): array {
		$linked_user = isset( $profile['linked_user'] ) ? absint( $profile['linked_user'] ) : 0;

		$profile['source']  = 'cpt';
		$profile['type']    = 'cpt';
		$profile['user_id'] = $linked_user;

		if ( '' === trim( (string) ( $profile['position'] ?? '' ) ) ) {
			$profile['position'] = __( 'Real Estate Agent', 'havenlytics' );
		}

		return $profile;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
