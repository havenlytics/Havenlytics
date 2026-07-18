<?php
/**
 * Portal authorization — boolean permission checks only.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Workspace\Identity\IdentityVerificationService;

defined( 'ABSPATH' ) || exit;

/**
 * Workspace permission gate.
 *
 * Returns booleans only. No redirects. No HTML.
 * Uses {@see AgentIdentityService} as the sole identity source.
 *
 * @since 3.2.0
 */
final class PortalAuthorization {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * Per-user permission cache for the current request.
	 *
	 * @var array<int, array<string, bool>>
	 */
	private $cache = array();

	/**
	 * @param AgentIdentityService|null $identity Identity service.
	 */
	public function __construct( ?AgentIdentityService $identity = null ) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
	}

	/**
	 * @return AgentIdentityService
	 */
	public function get_identity(): AgentIdentityService {
		return $this->identity;
	}

	/**
	 * Can enter the Workspace SPA?
	 *
	 * @param int|null $user_id Optional user ID.
	 * @return bool
	 */
	public function can_access_workspace( ?int $user_id = null ): bool {
		return $this->permission( 'can_access_workspace', $user_id );
	}

	/**
	 * Can manage own assigned properties?
	 *
	 * @param int|null $user_id Optional user ID.
	 * @return bool
	 */
	public function can_manage_own_properties( ?int $user_id = null ): bool {
		return $this->permission( 'can_manage_own_properties', $user_id );
	}

	/**
	 * Can edit own agent profile?
	 *
	 * @param int|null $user_id Optional user ID.
	 * @return bool
	 */
	public function can_edit_own_profile( ?int $user_id = null ): bool {
		return $this->permission( 'can_edit_own_profile', $user_id );
	}

	/**
	 * Can view Workspace analytics?
	 *
	 * @param int|null $user_id Optional user ID.
	 * @return bool
	 */
	public function can_view_analytics( ?int $user_id = null ): bool {
		return $this->permission( 'can_view_analytics', $user_id );
	}

	/**
	 * Can create property listings?
	 *
	 * @param int|null $user_id Optional user ID.
	 * @return bool
	 */
	public function can_create_listings( ?int $user_id = null ): bool {
		return $this->permission( 'can_create_listings', $user_id );
	}

	/**
	 * Can publish listings without approval?
	 *
	 * @param int|null $user_id Optional user ID.
	 * @return bool
	 */
	public function can_publish_listings( ?int $user_id = null ): bool {
		return $this->permission( 'can_publish_listings', $user_id );
	}

	/**
	 * Can manage inquiries (view/reply own or agency)?
	 *
	 * @param int|null $user_id Optional user ID.
	 * @return bool
	 */
	public function can_manage_inquiries( ?int $user_id = null ): bool {
		return $this->permission( 'can_manage_inquiries', $user_id );
	}

	/**
	 * All Sprint 2 permission booleans for REST /me.
	 *
	 * @param int|null $user_id Optional user ID.
	 * @return array<string, bool>
	 */
	public function all( ?int $user_id = null ): array {
		$user_id = null === $user_id ? $this->identity->get_user_id() : absint( $user_id );

		if ( isset( $this->cache[ $user_id ] ) ) {
			return $this->cache[ $user_id ];
		}

		$permissions = array(
			'can_access_workspace'      => $this->compute_can_access_workspace( $user_id ),
			'can_manage_own_properties' => $this->compute_can_manage_own_properties( $user_id ),
			'can_edit_own_profile'      => $this->compute_can_edit_own_profile( $user_id ),
			'can_view_analytics'        => $this->compute_can_view_analytics( $user_id ),
			'can_create_listings'       => $this->compute_can_create_listings( $user_id ),
			'can_publish_listings'      => $this->compute_can_publish_listings( $user_id ),
			'can_manage_inquiries'      => $this->compute_can_manage_inquiries( $user_id ),
		);

		/**
		 * Filter Workspace permission map.
		 *
		 * @since 3.2.0
		 *
		 * @param array<string, bool> $permissions Permission map.
		 * @param int                 $user_id     User ID.
		 */
		$permissions = (array) apply_filters( 'hvnly_workspace_permissions', $permissions, $user_id );

		$this->cache[ $user_id ] = $permissions;

		return $this->cache[ $user_id ];
	}

	/**
	 * Clear permission cache.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cache = array();
	}

	/**
	 * @param string   $key     Permission key.
	 * @param int|null $user_id User ID.
	 * @return bool
	 */
	private function permission( string $key, ?int $user_id ): bool {
		$all = $this->all( $user_id );
		return ! empty( $all[ $key ] );
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function compute_can_access_workspace( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Condition 1 — dedicated Workspace registration status ("what may they do?").
		// Independent of Agent CPT publish and of identity verification below.
		if ( ! WorkspaceRegistrationStatus::allows_workspace_access( WorkspaceRegistrationStatus::get_for_user( $user_id ) ) ) {
			return false;
		}

		// Condition 2 — identity verification ("who are they, and are they verified?").
		// Generic factor gate; this layer never names "email". Administrators are already
		// short-circuited above, so this never blocks them. Legacy accounts are
		// grandfathered inside the factor, so existing agents are never locked out.
		if ( ! IdentityVerificationService::instance()->required_factors_satisfied( $user_id ) ) {
			return false;
		}

		if ( user_can( $user_id, PortalCapabilities::PORTAL_ACCESS ) ) {
			return true;
		}

		$role = $this->identity->get_role( $user_id );
		if ( in_array( $role, array( PortalCapabilities::ROLE_ADMINISTRATOR, PortalCapabilities::ROLE_AGENCY_OWNER, PortalCapabilities::ROLE_AGENCY_MANAGER, PortalCapabilities::ROLE_AGENT ), true ) ) {
			/**
			 * Soft portal access for linked agents until custom roles are registered.
			 *
			 * @since 3.2.0
			 *
			 * @param bool $allowed Soft allow.
			 * @param int  $user_id User ID.
			 */
			return (bool) apply_filters( 'hvnly_workspace_soft_portal_access', true, $user_id );
		}

		return false;
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function compute_can_manage_own_properties( int $user_id ): bool {
		if ( ! $this->compute_can_access_workspace( $user_id ) ) {
			return false;
		}

		return $this->has_cap_or_soft(
			$user_id,
			PortalCapabilities::VIEW_ASSIGNED_PROPERTIES,
			array( PortalCapabilities::ROLE_ADMINISTRATOR, PortalCapabilities::ROLE_AGENCY_OWNER, PortalCapabilities::ROLE_AGENCY_MANAGER, PortalCapabilities::ROLE_AGENT )
		) || $this->has_cap_or_soft(
			$user_id,
			PortalCapabilities::EDIT_ASSIGNED_PROPERTIES,
			array( PortalCapabilities::ROLE_ADMINISTRATOR, PortalCapabilities::ROLE_AGENCY_OWNER, PortalCapabilities::ROLE_AGENCY_MANAGER, PortalCapabilities::ROLE_AGENT )
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function compute_can_edit_own_profile( int $user_id ): bool {
		if ( ! $this->compute_can_access_workspace( $user_id ) ) {
			return false;
		}

		return $this->has_cap_or_soft(
			$user_id,
			PortalCapabilities::EDIT_OWN_PROFILE,
			array( PortalCapabilities::ROLE_ADMINISTRATOR, PortalCapabilities::ROLE_AGENCY_OWNER, PortalCapabilities::ROLE_AGENCY_MANAGER, PortalCapabilities::ROLE_AGENT )
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function compute_can_view_analytics( int $user_id ): bool {
		if ( ! $this->compute_can_access_workspace( $user_id ) ) {
			return false;
		}

		return $this->has_cap_or_soft(
			$user_id,
			PortalCapabilities::VIEW_ANALYTICS,
			array( PortalCapabilities::ROLE_ADMINISTRATOR, PortalCapabilities::ROLE_AGENCY_OWNER, PortalCapabilities::ROLE_AGENCY_MANAGER, PortalCapabilities::ROLE_AGENT )
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function compute_can_create_listings( int $user_id ): bool {
		if ( ! $this->compute_can_access_workspace( $user_id ) ) {
			return false;
		}

		return $this->has_cap_or_soft(
			$user_id,
			PortalCapabilities::CREATE_PROPERTIES,
			array( PortalCapabilities::ROLE_ADMINISTRATOR, PortalCapabilities::ROLE_AGENCY_OWNER, PortalCapabilities::ROLE_AGENCY_MANAGER, PortalCapabilities::ROLE_AGENT )
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function compute_can_publish_listings( int $user_id ): bool {
		if ( ! $this->compute_can_access_workspace( $user_id ) ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, PortalCapabilities::APPROVE_LISTINGS ) ) {
			return true;
		}

		return $this->has_cap_or_soft(
			$user_id,
			PortalCapabilities::EDIT_PUBLISHED_PROPERTIES,
			array( PortalCapabilities::ROLE_ADMINISTRATOR, PortalCapabilities::ROLE_AGENCY_OWNER, PortalCapabilities::ROLE_AGENCY_MANAGER )
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function compute_can_manage_inquiries( int $user_id ): bool {
		if ( ! $this->compute_can_access_workspace( $user_id ) ) {
			return false;
		}

		return $this->has_cap_or_soft(
			$user_id,
			PortalCapabilities::VIEW_OWN_INQUIRIES,
			array( PortalCapabilities::ROLE_ADMINISTRATOR, PortalCapabilities::ROLE_AGENCY_OWNER, PortalCapabilities::ROLE_AGENCY_MANAGER, PortalCapabilities::ROLE_AGENT )
		) || $this->has_cap_or_soft(
			$user_id,
			PortalCapabilities::REPLY_INQUIRIES,
			array( PortalCapabilities::ROLE_ADMINISTRATOR, PortalCapabilities::ROLE_AGENCY_OWNER, PortalCapabilities::ROLE_AGENCY_MANAGER, PortalCapabilities::ROLE_AGENT )
		) || user_can( $user_id, PortalCapabilities::VIEW_AGENCY_INQUIRIES );
	}

	/**
	 * Explicit WP cap OR soft-grant by role when caps are not yet assigned.
	 *
	 * @param int      $user_id      User ID.
	 * @param string   $cap          Capability.
	 * @param string[] $soft_roles   Roles that soft-receive the permission.
	 * @return bool
	 */
	private function has_cap_or_soft( int $user_id, string $cap, array $soft_roles ): bool {
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		if ( user_can( $user_id, $cap ) ) {
			return true;
		}

		$role = $this->identity->get_role( $user_id );
		if ( ! in_array( $role, $soft_roles, true ) ) {
			return false;
		}

		/**
		 * Filter soft capability grant.
		 *
		 * @since 3.2.0
		 *
		 * @param bool   $allowed Soft allow.
		 * @param string $cap     Capability.
		 * @param int    $user_id User ID.
		 * @param string $role    Workspace role.
		 */
		return (bool) apply_filters( 'hvnly_workspace_soft_capability', true, $cap, $user_id, $role );
	}
}
