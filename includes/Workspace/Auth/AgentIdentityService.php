<?php
/**
 * Agent identity resolver — single source of truth for Workspace.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\PropertyAgentResolver;
use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the current WordPress user → linked Agent CPT identity.
 *
 * Request-scoped cache. Never duplicate this resolution elsewhere —
 * inject or call {@see self::get_instance()}.
 *
 * @since 3.2.0
 */
final class AgentIdentityService {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Per-user identity cache for the current request.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $cache = array();

	/**
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Resolve identity for the current user (or a specific user ID).
	 *
	 * @param int|null $user_id Optional user ID; defaults to current.
	 * @return array<string, mixed>
	 */
	public function resolve( ?int $user_id = null ): array {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );

		if ( isset( $this->cache[ $user_id ] ) ) {
			return $this->cache[ $user_id ];
		}

		$identity                = $this->build_identity( $user_id );
		$this->cache[ $user_id ] = $identity;

		/**
		 * Filter resolved Workspace identity.
		 *
		 * @since 3.2.0
		 *
		 * @param array<string, mixed> $identity Identity payload.
		 * @param int                  $user_id  User ID.
		 */
		$this->cache[ $user_id ] = (array) apply_filters( 'hvnly_workspace_identity', $identity, $user_id );

		return $this->cache[ $user_id ];
	}

	/**
	 * Clear request cache (tests / user switch).
	 *
	 * @param int|null $user_id Optional specific user; null clears all.
	 * @return void
	 */
	public function clear_cache( ?int $user_id = null ): void {
		if ( null === $user_id ) {
			$this->cache = array();
			return;
		}

		unset( $this->cache[ absint( $user_id ) ] );
	}

	/**
	 * Ensure one Agent CPT for a user (idempotent). Delegates to {@see AgentProvisioner}.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args    Optional provisioner args.
	 * @return int|WP_Error Agent post ID.
	 */
	public function ensure_agent_for_user( int $user_id, array $args = array() ) {
		return ( new AgentProvisioner() )->ensure_agent_for_user( $user_id, $args );
	}

	/**
	 * Set / clear 1:1 Agent ↔ User link. Sole public write API with Provisioner.
	 *
	 * @param int                  $agent_id Agent CPT ID.
	 * @param int                  $user_id  WP user ID (0 = unlink).
	 * @param array<string, mixed> $args     confirm_relink required when changing an existing link.
	 * @return true|WP_Error
	 */
	public function set_linked_user( int $agent_id, int $user_id, array $args = array() ) {
		return ( new AgentProvisioner() )->set_linked_user( $agent_id, $user_id, $args );
	}

	/**
	 * Create / return linked WP User for an Agent CPT (admin Workspace account).
	 *
	 * @param int                  $agent_id Agent CPT ID.
	 * @param array<string, mixed> $args     Optional provisioner args.
	 * @return int|WP_Error User ID.
	 */
	public function ensure_user_for_agent( int $agent_id, array $args = array() ) {
		return ( new AgentProvisioner() )->ensure_user_for_agent( $agent_id, $args );
	}

	/**
	 * @param int|null $user_id User ID.
	 * @return int
	 */
	public function get_user_id( ?int $user_id = null ): int {
		return (int) $this->resolve( $user_id )['user']['id'];
	}

	/**
	 * @param int|null $user_id User ID.
	 * @return WP_User|null
	 */
	public function get_user( ?int $user_id = null ): ?WP_User {
		$id = $this->get_user_id( $user_id );
		if ( $id <= 0 ) {
			return null;
		}

		$user = get_userdata( $id );
		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * Linked Agent CPT post ID (0 when none).
	 *
	 * @param int|null $user_id User ID.
	 * @return int
	 */
	public function get_agent_id( ?int $user_id = null ): int {
		$agent = $this->resolve( $user_id )['agent'];
		return is_array( $agent ) ? absint( $agent['id'] ?? 0 ) : 0;
	}

	/**
	 * Minimal agent payload or null.
	 *
	 * @param int|null $user_id User ID.
	 * @return array<string, mixed>|null
	 */
	public function get_agent( ?int $user_id = null ): ?array {
		$agent = $this->resolve( $user_id )['agent'];
		return is_array( $agent ) ? $agent : null;
	}

	/**
	 * Agent post status or empty string.
	 *
	 * @param int|null $user_id User ID.
	 * @return string
	 */
	public function get_agent_status( ?int $user_id = null ): string {
		$agent = $this->get_agent( $user_id );
		return is_array( $agent ) ? (string) ( $agent['status'] ?? '' ) : '';
	}

	/**
	 * Workspace role key (future-compatible personas).
	 *
	 * @param int|null $user_id User ID.
	 * @return string
	 */
	public function get_role( ?int $user_id = null ): string {
		return (string) $this->resolve( $user_id )['role'];
	}

	/**
	 * Relevant WP capability map for the user.
	 *
	 * @param int|null $user_id User ID.
	 * @return array<string, bool>
	 */
	public function get_capabilities( ?int $user_id = null ): array {
		$caps = $this->resolve( $user_id )['capabilities'];
		return is_array( $caps ) ? $caps : array();
	}

	/**
	 * Whether a WordPress user is logged in for this identity.
	 *
	 * @param int|null $user_id User ID.
	 * @return bool
	 */
	public function is_logged_in( ?int $user_id = null ): bool {
		return $this->get_user_id( $user_id ) > 0;
	}

	/**
	 * Build identity array (uncached).
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	private function build_identity( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array(
				'user'         => array(
					'id'           => 0,
					'email'        => '',
					'display_name' => '',
					'roles'        => array(),
				),
				'agent'        => null,
				'role'         => PortalCapabilities::ROLE_GUEST,
				'capabilities' => array(),
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			return array(
				'user'         => array(
					'id'           => 0,
					'email'        => '',
					'display_name' => '',
					'roles'        => array(),
				),
				'agent'        => null,
				'role'         => PortalCapabilities::ROLE_GUEST,
				'capabilities' => array(),
			);
		}

		$agent   = $this->resolve_linked_agent( $user_id );
		$role    = $this->resolve_role( $user, $agent );
		$cap_map = $this->resolve_capability_map( $user );

		return array(
			'user'         => array(
				'id'           => (int) $user->ID,
				'email'        => (string) $user->user_email,
				'display_name' => (string) $user->display_name,
				'avatar_url'   => \HvnlyNab\Common\AvatarService::resolve_for_user( (int) $user->ID, 96 ),
				'roles'        => array_values( array_map( 'strval', (array) $user->roles ) ),
			),
			'agent'        => $agent,
			'role'         => $role,
			'capabilities' => $cap_map,
		);
	}

	/**
	 * Resolve linked published Agent CPT (minimal fields only).
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|null
	 */
	private function resolve_linked_agent( int $user_id ): ?array {
		$profiles = PropertyAgentResolver::get_cpt_agents_for_user( $user_id );
		if ( empty( $profiles ) ) {
			// Also allow non-publish draft/pending agents for identity diagnostics.
			$agent_id = $this->find_linked_agent_id( $user_id, array( 'publish', 'pending', 'draft', 'private' ) );
			if ( $agent_id <= 0 ) {
				return null;
			}

			return $this->normalize_agent( $agent_id, null, $user_id );
		}

		$first = $profiles[0];
		$id    = isset( $first['id'] ) ? absint( $first['id'] ) : 0;
		if ( $id <= 0 ) {
			return null;
		}

		return $this->normalize_agent( $id, $first, $user_id );
	}

	/**
	 * Find linked agent post ID with explicit statuses.
	 *
	 * @param int      $user_id  User ID.
	 * @param string[] $statuses Post statuses.
	 * @return int
	 */
	private function find_linked_agent_id( int $user_id, array $statuses ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => $statuses,
				'posts_per_page'         => 1,
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

		if ( empty( $query->posts[0] ) ) {
			return 0;
		}

		return absint( $query->posts[0] );
	}

	/**
	 * Minimal agent DTO for REST /me.
	 *
	 * @param int                       $agent_id Agent post ID.
	 * @param array<string, mixed>|null $profile  Optional preloaded profile.
	 * @param int                       $user_id  Linked WP user ID (for Gravatar fallback).
	 * @return array<string, mixed>
	 */
	private function normalize_agent( int $agent_id, ?array $profile = null, int $user_id = 0 ): array {
		$post = get_post( $agent_id );
		if ( ! $post || AgentConstants::POST_TYPE !== $post->post_type ) {
			return array(
				'id'         => 0,
				'status'     => '',
				'slug'       => '',
				'title'      => '',
				'avatar_url' => '',
				'public_url' => '',
				'agency'     => null,
			);
		}

		$agency = null;
		if ( is_array( $profile ) && isset( $profile['agency'] ) && is_array( $profile['agency'] ) ) {
			$agency = array(
				'id'   => isset( $profile['agency']['id'] ) ? absint( $profile['agency']['id'] ) : 0,
				'name' => isset( $profile['agency']['name'] ) ? (string) $profile['agency']['name'] : '',
			);
		} elseif ( function_exists( 'hvnly_get_agent' ) ) {
			$full = hvnly_get_agent( $agent_id );
			if ( is_array( $full ) && isset( $full['agency'] ) && is_array( $full['agency'] ) ) {
				$agency = array(
					'id'   => isset( $full['agency']['id'] ) ? absint( $full['agency']['id'] ) : 0,
					'name' => isset( $full['agency']['name'] ) ? (string) $full['agency']['name'] : '',
				);
			}
		}

		// Single avatar resolution chain (shared everywhere): Agent uploaded
		// photo → linked user's Gravatar → Havenlytics placeholder. Never blank.
		$avatar = \HvnlyNab\Common\AvatarService::resolve_url( $agent_id, $user_id, 96, 'medium' );

		return array(
			'id'         => $agent_id,
			'status'     => (string) $post->post_status,
			'slug'       => (string) $post->post_name,
			'title'      => (string) get_the_title( $agent_id ),
			'avatar_url' => $avatar,
			'public_url' => (string) get_permalink( $agent_id ),
			'agency'     => $agency,
		);
	}

	/**
	 * Resolve workspace persona role.
	 *
	 * Stable public API keys support Agent / Agency / Buyer / Administrator
	 * and future Laravel sync without breaking consumers.
	 *
	 * @param WP_User                  $user  User.
	 * @param array<string, mixed>|null $agent Agent DTO.
	 * @return string
	 */
	private function resolve_role( WP_User $user, ?array $agent ): string {
		if ( user_can( $user, 'manage_options' ) ) {
			return PortalCapabilities::ROLE_ADMINISTRATOR;
		}

		$wp_roles = array_map( 'strval', (array) $user->roles );

		if ( in_array( 'hvnly_agency_owner', $wp_roles, true ) || user_can( $user, 'hvnly_manage_agency' ) ) {
			return PortalCapabilities::ROLE_AGENCY_OWNER;
		}

		if ( in_array( 'hvnly_agency_manager', $wp_roles, true ) || absint( get_user_meta( $user->ID, 'hvnly_managed_agency_term_id', true ) ) > 0 ) {
			return PortalCapabilities::ROLE_AGENCY_MANAGER;
		}

		if ( in_array( 'hvnly_buyer', $wp_roles, true ) ) {
			return PortalCapabilities::ROLE_BUYER;
		}

		if ( in_array( 'hvnly_agent', $wp_roles, true ) || in_array( 'hvnly_assistant', $wp_roles, true ) || in_array( 'hvnly_editor', $wp_roles, true ) || in_array( 'hvnly_contributor', $wp_roles, true ) ) {
			return PortalCapabilities::ROLE_AGENT;
		}

		if ( is_array( $agent ) && ! empty( $agent['id'] ) ) {
			return PortalCapabilities::ROLE_AGENT;
		}

		/**
		 * Filter unresolved Workspace role before defaulting to unlinked.
		 *
		 * @since 3.2.0
		 *
		 * @param string  $role Default role.
		 * @param WP_User $user User.
		 */
		$filtered = apply_filters( 'hvnly_workspace_identity_role', PortalCapabilities::ROLE_UNLINKED, $user );

		return is_string( $filtered ) && $filtered !== '' ? $filtered : PortalCapabilities::ROLE_UNLINKED;
	}

	/**
	 * Map portal caps via user_can (true when explicitly granted).
	 *
	 * @param WP_User $user User.
	 * @return array<string, bool>
	 */
	private function resolve_capability_map( WP_User $user ): array {
		$map = array();
		foreach ( PortalCapabilities::all() as $cap ) {
			$map[ $cap ] = user_can( $user, $cap );
		}
		$map['manage_options'] = user_can( $user, 'manage_options' );
		$map['upload_files']   = user_can( $user, 'upload_files' );

		return $map;
	}
}
