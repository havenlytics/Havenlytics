<?php
/**
 * Resolves inquiry-to-agent relationships for Contact Agent submissions.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * Links property inquiries to Agent CPT profiles or legacy user agents.
 *
 * @since 3.0.2
 */
final class InquiryAgentResolver {

	/** @var string Agent CPT reference type. */
	public const TYPE_CPT = 'cpt';

	/** @var string Legacy WordPress user reference type. */
	public const TYPE_USER = 'user';

	/**
	 * Resolve the target agent for an inquiry submission.
	 *
	 * @param int $property_id        Property post ID.
	 * @param int $requested_agent_id Optional agent ID from the form.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function resolve_for_submission( int $property_id, int $requested_agent_id = 0 ) {
		$property_id = absint( $property_id );
		if ( $property_id <= 0 ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_property',
				__( 'A valid property is required.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$agents             = self::collect_property_agents( $property_id );
		$requested_agent_id = absint( $requested_agent_id );
		$selected           = null;

		if ( $requested_agent_id > 0 ) {
			$selected = self::find_agent_in_list( $agents, $requested_agent_id );

			if ( null === $selected ) {
				$fallback = self::resolve_valid_agent_profile( $requested_agent_id );
				if ( ! empty( $fallback ) ) {
					$agents[] = $fallback;
					$selected = $fallback;
				}
			}

			if ( null === $selected ) {
				$sidebar_fallback = self::resolve_sidebar_fallback_agent( $property_id, $requested_agent_id );
				if ( ! empty( $sidebar_fallback ) ) {
					$selected = $sidebar_fallback;
				}
			}

			if ( null === $selected ) {
				return new \WP_Error(
					'hvnly_contact_agent_invalid_agent',
					__( 'The selected agent is not assigned to this property.', 'havenlytics' ),
					array( 'status' => 400 )
				);
			}
		} elseif ( ! empty( $agents ) ) {
			$selected = $agents[0];
		} else {
			$sidebar_fallback = self::resolve_sidebar_fallback_agent( $property_id, 0 );
			if ( ! empty( $sidebar_fallback ) ) {
				$selected = $sidebar_fallback;
			} else {
				return new \WP_Error(
					'hvnly_contact_agent_no_agent',
					__( 'No agent is available for this property.', 'havenlytics' ),
					array( 'status' => 400 )
				);
			}
		}

		$resolved = self::normalize_storage_fields( $selected );

		/**
		 * Filter resolved inquiry agent before persistence.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $resolved    Resolved agent payload.
		 * @param int                  $property_id Property ID.
		 * @param array<int, array>    $agents      All property agents.
		 */
		return apply_filters( 'hvnly_contact_agent_resolved_agent', $resolved, $property_id, $agents );
	}

	/**
	 * Resolve agent for a direct agent-profile inquiry (no property context).
	 *
	 * @param int $agent_id Agent CPT post ID from the form.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function resolve_for_agent_profile( int $agent_id ) {
		$agent_id = absint( $agent_id );

		if ( $agent_id <= 0 || ! function_exists( 'hvnly_get_agent' ) ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_agent',
				__( 'The selected agent is not available.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$profile = hvnly_get_agent( $agent_id );
		if ( empty( $profile ) ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_agent',
				__( 'The selected agent is not available.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$resolved = self::normalize_storage_fields( $profile );

		/**
		 * Filter resolved inquiry agent for agent-profile submissions.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $resolved Resolved agent payload.
		 * @param int                  $agent_id Agent post ID.
		 */
		return apply_filters( 'hvnly_contact_agent_resolved_agent_profile', $resolved, $agent_id );
	}

	/**
	 * Build an agent profile array from a stored inquiry row.
	 *
	 * @param array<string, mixed> $inquiry Inquiry row.
	 * @return array<string, mixed>
	 */
	public static function profile_from_inquiry( array $inquiry ): array {
		$agent_id   = isset( $inquiry['agent_id'] ) ? absint( $inquiry['agent_id'] ) : 0;
		$agent_type = isset( $inquiry['agent_type'] ) ? sanitize_key( (string) $inquiry['agent_type'] ) : '';

		if ( $agent_id > 0 ) {
			$profile = self::resolve_stored_agent_profile( $agent_id, $agent_type );
			if ( ! empty( $profile['name'] ) ) {
				return $profile;
			}
		}

		$property_id = isset( $inquiry['property_id'] ) ? absint( $inquiry['property_id'] ) : 0;
		if ( $property_id <= 0 ) {
			return array();
		}

		$property_agents = self::collect_property_agents( $property_id );
		if ( ! empty( $property_agents ) ) {
			return $property_agents[0];
		}

		$sidebar_fallback = self::resolve_sidebar_fallback_agent( $property_id, 0 );
		if ( ! empty( $sidebar_fallback ) ) {
			return $sidebar_fallback;
		}

		if ( function_exists( 'hvnly_get_property_agent' ) ) {
			$agent = hvnly_get_property_agent( $property_id );
			if ( is_array( $agent ) && ! empty( $agent['name'] ) ) {
				return $agent;
			}
		}

		return array();
	}

	/**
	 * Resolve a stored inquiry agent reference to a display profile.
	 *
	 * @param int    $agent_id   Stored agent or user ID.
	 * @param string $agent_type Stored reference type when available.
	 * @return array<string, mixed>
	 */
	private static function resolve_stored_agent_profile( int $agent_id, string $agent_type = '' ): array {
		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 ) {
			return array();
		}

		if ( self::TYPE_CPT === $agent_type && function_exists( 'hvnly_get_agent' ) ) {
			$profile = hvnly_get_agent( $agent_id );
			if ( ! empty( $profile ) ) {
				return $profile;
			}
		}

		if ( self::TYPE_USER === $agent_type || AgentConstants::POST_TYPE !== get_post_type( $agent_id ) ) {
			return self::profile_from_user_id( $agent_id );
		}

		if ( function_exists( 'hvnly_get_agent' ) ) {
			$profile = hvnly_get_agent( $agent_id );
			if ( ! empty( $profile ) ) {
				return $profile;
			}
		}

		return self::profile_from_user_id( $agent_id );
	}

	/**
	 * @param array<string, mixed> $agent Agent profile.
	 * @return array<string, mixed>
	 */
	private static function normalize_storage_fields( array $agent ): array {
		$type = isset( $agent['type'] ) ? sanitize_key( (string) $agent['type'] ) : '';
		if ( 'site_admin' === $type || 'site_admin' === sanitize_key( (string) ( $agent['source'] ?? '' ) ) ) {
			return array(
				'agent_id'   => absint( $agent['user_id'] ?? ( $agent['id'] ?? 0 ) ),
				'agent_type' => self::TYPE_USER,
				'agent'      => $agent,
			);
		}

		if ( ! in_array( $type, array( self::TYPE_CPT, self::TYPE_USER, 'custom' ), true ) ) {
			$type = isset( $agent['source'] ) ? sanitize_key( (string) $agent['source'] ) : self::TYPE_USER;
		}

		if ( self::TYPE_CPT === $type || 'cpt' === $type ) {
			$storage_type = self::TYPE_CPT;
			$storage_id   = absint( $agent['id'] ?? 0 );
		} else {
			$storage_type = self::TYPE_USER;
			$storage_id   = absint( $agent['user_id'] ?? ( $agent['id'] ?? 0 ) );
		}

		return array(
			'agent_id'   => $storage_id,
			'agent_type' => $storage_type,
			'agent'      => $agent,
		);
	}

	/**
	 * @param int $user_id WordPress user ID.
	 * @return array<string, mixed>
	 */
	private static function profile_from_user_id( int $user_id ): array {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		return array(
			'id'      => $user_id,
			'type'    => self::TYPE_USER,
			'source'  => self::TYPE_USER,
			'user_id' => $user_id,
			'name'    => get_the_author_meta( 'display_name', $user_id ),
			'email'   => get_the_author_meta( 'user_email', $user_id ),
			'phone'   => (string) get_user_meta( $user_id, '_hvnly_agent_phone', true ),
			'avatar'  => \HvnlyNab\Common\AvatarService::resolve_for_user( (int) $user_id, 200 ),
		);
	}

	/**
	 * Collect all agents eligible for inquiries on a property.
	 *
	 * Includes CPT assignments, legacy user fallback, and builder group field agents.
	 *
	 * @param int $property_id Property post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_property_agents( int $property_id ): array {
		$agents = function_exists( 'hvnly_get_property_agents' )
			? hvnly_get_property_agents( $property_id )
			: array();

		if ( empty( $agents ) && function_exists( 'hvnly_get_property_agent' ) ) {
			$primary = hvnly_get_property_agent( $property_id );
			if ( is_array( $primary ) && ! empty( $primary ) ) {
				$agents = array( $primary );
			}
		}

		return self::merge_builder_meta_agents( $property_id, $agents );
	}

	/**
	 * Merge Agent CPT profiles stored on builder group fields (e.g. `{group}_agents` meta).
	 *
	 * @param int                             $property_id Property post ID.
	 * @param array<int, array<string, mixed>> $agents      Existing agent profiles.
	 * @return array<int, array<string, mixed>>
	 */
	private static function merge_builder_meta_agents( int $property_id, array $agents ): array {
		$known_ids = array();

		foreach ( $agents as $agent ) {
			if ( ! is_array( $agent ) ) {
				continue;
			}

			if ( ! empty( $agent['id'] ) ) {
				$known_ids[ absint( $agent['id'] ) ] = true;
			}

			if ( ! empty( $agent['user_id'] ) ) {
				$known_ids[ absint( $agent['user_id'] ) ] = true;
			}
		}

		$raw_meta = get_post_meta( $property_id );
		if ( ! is_array( $raw_meta ) ) {
			return $agents;
		}

		foreach ( $raw_meta as $meta_key => $meta_values ) {
			if ( ! is_string( $meta_key ) || substr( $meta_key, -8 ) !== '_agents' ) {
				continue;
			}

			$value = is_array( $meta_values ) && isset( $meta_values[0] ) ? $meta_values[0] : '';
			$ids   = self::parse_agent_id_list( $value );

			foreach ( $ids as $agent_id ) {
				if ( isset( $known_ids[ $agent_id ] ) ) {
					continue;
				}

				$profile = self::resolve_valid_agent_profile( $agent_id );
				if ( empty( $profile ) ) {
					continue;
				}

				$agents[]               = $profile;
				$known_ids[ $agent_id ] = true;
			}
		}

		return $agents;
	}

	/**
	 * @param mixed $raw Raw meta value.
	 * @return int[]
	 */
	private static function parse_agent_id_list( $raw ): array {
		if ( is_array( $raw ) ) {
			return array_values( array_filter( array_map( 'absint', $raw ) ) );
		}

		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return array_values( array_filter( array_map( 'absint', $decoded ) ) );
			}
		}

		return array();
	}

	/**
	 * Find an agent profile by CPT post ID or linked WordPress user ID.
	 *
	 * @param array<int, array<string, mixed>> $agents             Agent profiles.
	 * @param int                              $requested_agent_id Requested ID from the form.
	 * @return array<string, mixed>|null
	 */
	private static function find_agent_in_list( array $agents, int $requested_agent_id ): ?array {
		foreach ( $agents as $agent ) {
			if ( ! is_array( $agent ) ) {
				continue;
			}

			$agent_id = ! empty( $agent['id'] ) ? absint( $agent['id'] ) : 0;
			$user_id  = ! empty( $agent['user_id'] ) ? absint( $agent['user_id'] ) : 0;

			if ( $agent_id === $requested_agent_id || $user_id === $requested_agent_id ) {
				return $agent;
			}
		}

		return null;
	}

	/**
	 * Resolve a published Agent CPT profile when the submitted ID is valid.
	 *
	 * @param int $agent_id Agent CPT post ID.
	 * @return array<string, mixed>
	 */
	private static function resolve_valid_agent_profile( int $agent_id ): array {
		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 || ! function_exists( 'hvnly_get_agent' ) ) {
			return array();
		}

		if ( function_exists( 'hvnly_is_valid_agent' ) && ! hvnly_is_valid_agent( $agent_id ) ) {
			return array();
		}

		$profile = hvnly_get_agent( $agent_id );
		if ( empty( $profile ) || empty( $profile['name'] ) ) {
			return array();
		}

		$profile['source'] = isset( $profile['source'] ) ? (string) $profile['source'] : self::TYPE_CPT;
		$profile['type']   = isset( $profile['type'] ) ? (string) $profile['type'] : self::TYPE_CPT;

		return $profile;
	}

	/**
	 * Sidebar widget fallback contact when a property has no assigned Agent CPT profiles.
	 *
	 * @param int $property_id        Property post ID.
	 * @param int $requested_agent_id Optional submitted agent/user ID.
	 * @return array<string, mixed>
	 */
	private static function resolve_sidebar_fallback_agent( int $property_id, int $requested_agent_id = 0 ): array {
		$property_id = absint( $property_id );
		if ( $property_id <= 0 ) {
			return array();
		}

		$sidebar_agents = function_exists( 'hvnly_get_sidebar_property_agents' )
			? hvnly_get_sidebar_property_agents( $property_id )
			: array();

		if ( ! empty( $sidebar_agents ) ) {
			return array();
		}

		$fallback = function_exists( 'hvnly_get_default_sidebar_contact' )
			? hvnly_get_default_sidebar_contact()
			: array();

		if ( empty( $fallback ) ) {
			return array();
		}

		$requested_agent_id = absint( $requested_agent_id );
		if ( $requested_agent_id <= 0 ) {
			return $fallback;
		}

		$candidate_ids = array(
			absint( $fallback['id'] ?? 0 ),
			absint( $fallback['user_id'] ?? 0 ),
		);

		if ( in_array( $requested_agent_id, $candidate_ids, true ) ) {
			return $fallback;
		}

		return array();
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
