<?php
/**
 * Property draft access checks using PortalAuthorization + ownership.
 *
 * Does not duplicate portal boolean logic — only adds resource-scoped ownership.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Agent\PropertyAgentResolver;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\Auth\PortalCapabilities;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;
use HvnlyNab\Workspace\WorkspaceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Who may create / edit / submit / publish properties in the Workspace.
 *
 * Allowed editors: Administrator, assigned Agent, or post author.
 * Does not duplicate PortalAuthorization booleans — only scopes them to a property.
 *
 * @since 3.2.0
 */
final class PropertyAccessGate {

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @param PortalAuthorization|null  $auth     Auth.
	 * @param AgentIdentityService|null $identity Identity.
	 */
	public function __construct( ?PortalAuthorization $auth = null, ?AgentIdentityService $identity = null ) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth     = $auth ? $auth : new PortalAuthorization( $this->identity );
	}

	/**
	 * @param int|null $user_id User ID.
	 * @return bool
	 */
	public function can_create( ?int $user_id = null ): bool {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		if ( ! WorkspaceRegistrationStatus::allows_property_mutations( WorkspaceRegistrationStatus::get_for_user( $user_id ) ) ) {
			return false;
		}
		return $this->auth->can_create_listings( $user_id );
	}

	/**
	 * @param int      $property_id Property post ID.
	 * @param int|null $user_id     User ID.
	 * @return bool
	 */
	public function can_edit( int $property_id, ?int $user_id = null ): bool {
		$user_id     = null === $user_id ? get_current_user_id() : absint( $user_id );
		$property_id = absint( $property_id );

		if ( $user_id <= 0 || $property_id <= 0 ) {
			return false;
		}

		$post = get_post( $property_id );
		if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		if ( ! WorkspaceRegistrationStatus::allows_property_mutations( WorkspaceRegistrationStatus::get_for_user( $user_id ) ) ) {
			return false;
		}

		if ( ! $this->auth->can_manage_own_properties( $user_id ) && ! $this->auth->can_create_listings( $user_id ) ) {
			return false;
		}

		if ( (int) $post->post_author === $user_id ) {
			return true;
		}

		return $this->is_assigned_agent( $property_id, $user_id );
	}

	/**
	 * Submit for review (draft/rejected → pending).
	 *
	 * @param int      $property_id Property ID.
	 * @param int|null $user_id     User ID.
	 * @return bool
	 */
	public function can_submit( int $property_id, ?int $user_id = null ): bool {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		if ( ! $this->can_edit( $property_id, $user_id ) ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, PortalCapabilities::SUBMIT_PROPERTIES ) ) {
			return true;
		}

		// Soft-grant: anyone who can edit their listing may submit for review.
		return $this->auth->can_create_listings( $user_id ) || $this->auth->can_manage_own_properties( $user_id );
	}

	/**
	 * Publish / approve / reject / unpublish.
	 *
	 * Administrators and users with can_publish_listings always.
	 * Assigned agents/authors only when direct-publish setting is on.
	 *
	 * @param int      $property_id Property ID.
	 * @param int|null $user_id     User ID.
	 * @return bool
	 */
	public function can_publish( int $property_id, ?int $user_id = null ): bool {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		if ( $user_id <= 0 || $property_id <= 0 ) {
			return false;
		}

		$post = get_post( $property_id );
		if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Approvers / agency owners / managers (PortalAuthorization::can_publish_listings).
		if ( $this->auth->can_publish_listings( $user_id ) ) {
			return true;
		}

		// Optional: assigned agents/authors publish directly when setting enabled.
		if ( WorkspaceSettings::agents_can_direct_publish() && $this->can_edit( $property_id, $user_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * May call changeStatus for at least one transition.
	 *
	 * @param int      $property_id Property ID.
	 * @param int|null $user_id     User ID.
	 * @return bool
	 */
	public function can_change_status( int $property_id, ?int $user_id = null ): bool {
		return $this->can_submit( $property_id, $user_id ) || $this->can_publish( $property_id, $user_id );
	}

	/**
	 * Soft-delete (move to trash).
	 *
	 * @param int      $property_id Property ID.
	 * @param int|null $user_id     User ID.
	 * @return bool
	 */
	public function can_trash( int $property_id, ?int $user_id = null ): bool {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		$post    = get_post( $property_id );
		if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return false;
		}
		if ( 'trash' === $post->post_status ) {
			return false;
		}
		return $this->can_edit( $property_id, $user_id );
	}

	/**
	 * Restore from trash.
	 *
	 * @param int      $property_id Property ID.
	 * @param int|null $user_id     User ID.
	 * @return bool
	 */
	public function can_restore( int $property_id, ?int $user_id = null ): bool {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		$post    = get_post( $property_id );
		if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return false;
		}
		if ( 'trash' !== $post->post_status ) {
			return false;
		}
		return $this->can_edit( $property_id, $user_id );
	}

	/**
	 * Permanently delete — administrators only.
	 *
	 * @param int      $property_id Property ID.
	 * @param int|null $user_id     User ID.
	 * @return bool
	 */
	public function can_delete_permanently( int $property_id, ?int $user_id = null ): bool {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		if ( $user_id <= 0 || ! user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		$post = get_post( $property_id );
		return $post && PropertyFormMapper::POST_TYPE === $post->post_type;
	}

	/**
	 * Duplicate listing (creates a new draft).
	 *
	 * @param int      $property_id Property ID.
	 * @param int|null $user_id     User ID.
	 * @return bool
	 */
	public function can_duplicate( int $property_id, ?int $user_id = null ): bool {
		$user_id = null === $user_id ? get_current_user_id() : absint( $user_id );
		$post    = get_post( $property_id );
		if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return false;
		}
		if ( 'trash' === $post->post_status ) {
			return false;
		}
		return $this->can_edit( $property_id, $user_id ) && $this->can_create( $user_id );
	}

	/**
	 * @param int $property_id Property ID.
	 * @param int $user_id     User ID.
	 * @return bool
	 */
	private function is_assigned_agent( int $property_id, int $user_id ): bool {
		$resolved = $this->identity->resolve( $user_id );
		$agent_id = isset( $resolved['agent']['id'] ) ? absint( $resolved['agent']['id'] ) : 0;

		if ( $agent_id > 0 ) {
			$assigned = PropertyAgentResolver::get_assigned_agent_ids( $property_id );
			if ( in_array( $agent_id, array_map( 'absint', $assigned ), true ) ) {
				return true;
			}
		}

		$legacy = PropertyAgentResolver::get_legacy_user_id( $property_id );
		return $legacy > 0 && $legacy === $user_id;
	}
}
