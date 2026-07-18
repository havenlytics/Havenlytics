<?php
/**
 * Workspace REST: GET /hvnly/v1/me
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;
use HvnlyNab\Workspace\WorkspaceAvailability;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Current-user identity endpoint.
 *
 * @since 3.2.0
 */
final class MeController {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @param AgentIdentityService|null $identity Identity service.
	 * @param PortalAuthorization|null  $auth     Authorization service.
	 */
	public function __construct( ?AgentIdentityService $identity = null, ?PortalAuthorization $auth = null ) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth     = $auth ? $auth : new PortalAuthorization( $this->identity );
	}

	/**
	 * Register routes (idempotent via rest_api_init).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * @return void
	 */
	public function routes(): void {
		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_me' ),
				'permission_callback' => array( $this, 'permission_logged_in' ),
			)
		);
	}

	/**
	 * Require an authenticated WordPress user (cookie / application password).
	 *
	 * Returns WP_Error 401 when anonymous — no redirects.
	 *
	 * @return true|WP_Error
	 */
	public function permission_logged_in() {
		if ( ! WorkspaceAvailability::is_available() ) {
			return WorkspaceAvailability::unavailable_error();
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'hvnly_rest_unauthorized',
				__( 'Authentication required.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * GET /me — minimal identity payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_me( WP_REST_Request $request ) {
		unset( $request );

		$payload     = $this->build_identity_payload();
		$permissions = isset( $payload['permissions'] ) && is_array( $payload['permissions'] ) ? $payload['permissions'] : array();
		$user_id     = isset( $payload['user']['id'] ) ? (int) $payload['user']['id'] : 0;

		if ( ! empty( $permissions['can_access_workspace'] ) && $user_id > 0 ) {
			do_action( 'hvnly_workspace_agent_activity', $user_id, 'workspace' );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $payload,
			)
		);
	}

	/**
	 * Build the identity payload for the current (or given) user.
	 *
	 * Shared by GET /me and the SPA boot localization so both surfaces resolve
	 * an identical shape — letting the Workspace initialize synchronously on
	 * page load with no blocking /me round-trip (and no first-login loader flash).
	 *
	 * @since 3.2.0
	 *
	 * @return array<string, mixed>
	 */
	public function build_identity_payload(): array {
		$resolved = $this->identity->resolve();
		$user     = isset( $resolved['user'] ) && is_array( $resolved['user'] ) ? $resolved['user'] : array();
		$agent    = isset( $resolved['agent'] ) && is_array( $resolved['agent'] ) ? $resolved['agent'] : null;
		$role     = isset( $resolved['role'] ) ? (string) $resolved['role'] : '';

		$permissions = $this->auth->all();
		$user_id     = isset( $user['id'] ) ? (int) $user['id'] : 0;
		$reg_status  = $user_id > 0 ? WorkspaceRegistrationStatus::get_for_user( $user_id ) : WorkspaceRegistrationStatus::PENDING;

		// Identity verification state (Condition 2) — the SPA renders the Verify page from
		// this. `can_access` below already folds this in via PortalAuthorization.
		$verification  = $user_id > 0
			? \HvnlyNab\Workspace\Identity\IdentityVerificationService::instance()->state_for_user( $user_id )
			: array( 'satisfied' => false, 'factors' => array() );
		$email_verified = ! empty( $verification['factors']['email']['satisfied'] );

		$payload = array(
			'user'        => array(
				'id'                   => $user_id,
				'email'                => isset( $user['email'] ) ? (string) $user['email'] : '',
				'display_name'         => isset( $user['display_name'] ) ? (string) $user['display_name'] : '',
				'avatar_url'           => isset( $user['avatar_url'] ) ? (string) $user['avatar_url'] : '',
				'roles'                => isset( $user['roles'] ) && is_array( $user['roles'] ) ? array_values( $user['roles'] ) : array(),
				'registration_status'  => $reg_status,
				'email_verified'       => $email_verified,
			),
			'agent'        => $agent,
			'permissions'  => $permissions,
			'verification' => $verification,
			'workspace'   => array(
				'enabled'              => WorkspaceSettings::is_enabled(),
				'version'              => WorkspaceConstants::MODULE_VERSION,
				'namespace'            => WorkspaceConstants::REST_NAMESPACE,
				'role'                 => $role,
				'can_access'           => ! empty( $permissions['can_access_workspace'] ),
				'registration_status'  => $reg_status,
			),
		);

		/**
		 * Filter GET /me payload (before success envelope).
		 *
		 * @since 3.2.0
		 *
		 * @param array<string, mixed> $payload Identity payload.
		 */
		return (array) apply_filters( 'hvnly_workspace_me_response', $payload );
	}
}
