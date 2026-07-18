<?php
/**
 * Workspace REST: GET|PUT /hvnly/v1/profile
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Current-user Agent Profile editor endpoint.
 *
 * Never accepts client agent_id — resolves via AgentIdentityService.
 *
 * @since 3.2.0
 */
final class ProfileController {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var ProfileService
	 */
	private $service;

	/**
	 * @param AgentIdentityService|null $identity Identity.
	 * @param PortalAuthorization|null  $auth     Authz.
	 * @param ProfileService|null       $service  Profile service.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?ProfileService $service = null
	) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth     = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->service  = $service ? $service : new ProfileService( $this->identity, $this->auth );
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'hvnly_workspace_localize_data', array( $this, 'filter_localize' ) );
	}

	/**
	 * @param array<string, mixed> $data Localize payload.
	 * @return array<string, mixed>
	 */
	public function filter_localize( array $data ): array {
		$rest = isset( $data['rest'] ) && is_array( $data['rest'] ) ? $data['rest'] : array();
		$rest['profile'] = esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/profile' ) );
		$data['rest']    = $rest;
		return $data;
	}

	/**
	 * @return void
	 */
	public function routes(): void {
		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/profile',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_profile' ),
					'permission_callback' => array( $this, 'permission_view' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'put_profile' ),
					'permission_callback' => array( $this, 'permission_edit' ),
				),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public function permission_view() {
		return $this->permission_base( false );
	}

	/**
	 * @return true|WP_Error
	 */
	public function permission_edit() {
		return $this->permission_base( true );
	}

	/**
	 * @param bool $require_edit Require edit_own_profile.
	 * @return true|WP_Error
	 */
	private function permission_base( bool $require_edit ) {
		if ( ! WorkspaceSettings::is_enabled() ) {
			return new WP_Error(
				'hvnly_workspace_disabled',
				__( 'Workspace is disabled.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'hvnly_workspace_unauthorized',
				__( 'Authentication required.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		$user_id = get_current_user_id();
		if ( ! $this->auth->can_access_workspace( $user_id ) ) {
			return new WP_Error(
				'hvnly_workspace_forbidden',
				__( 'You do not have Workspace access.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		if ( $require_edit && ! $this->auth->can_edit_own_profile( $user_id ) ) {
			return new WP_Error(
				'hvnly_profile_forbidden',
				__( 'You cannot edit this profile.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /profile — current user only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_profile( WP_REST_Request $request ) {
		unset( $request );

		$user_id = get_current_user_id();
		$this->identity->resolve( $user_id );

		$result = $this->service->get_profile( $user_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}

	/**
	 * PUT /profile — current user only. Ignores client agent_id.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function put_profile( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$this->identity->resolve( $user_id );

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		// Never trust client identity overrides.
		unset( $payload['agentId'], $payload['agent_id'], $payload['userId'], $payload['user_id'] );

		$result = $this->service->update_profile( $user_id, $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $result,
			)
		);
	}
}
