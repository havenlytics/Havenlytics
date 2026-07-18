<?php
/**
 * Workspace REST: GET /hvnly/v1/dashboard
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
 * Dashboard home for the current logged-in Workspace user.
 *
 * Never accepts client agent_id — resolves via AgentIdentityService.
 *
 * @since 3.2.0
 */
final class DashboardController {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var DashboardService
	 */
	private $service;

	/**
	 * @param AgentIdentityService|null $identity Identity.
	 * @param PortalAuthorization|null  $auth     Authz.
	 * @param DashboardService|null     $service  Dashboard builder.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?DashboardService $service = null
	) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth     = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->service  = $service ? $service : new DashboardService( $this->identity, $this->auth );
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
		$rest['dashboard'] = esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/dashboard' ) );
		$data['rest']      = $rest;
		return $data;
	}

	/**
	 * @return void
	 */
	public function routes(): void {
		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'permission_dashboard' ),
			)
		);
	}

	/**
	 * Logged-in Workspace user only. Never trusts client IDs.
	 *
	 * @return true|WP_Error
	 */
	public function permission_dashboard() {
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

		return true;
	}

	/**
	 * GET /dashboard — current user only.
	 *
	 * @param WP_REST_Request $request Request (ignored for identity).
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_dashboard( WP_REST_Request $request ) {
		unset( $request );

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'hvnly_workspace_unauthorized',
				__( 'Authentication required.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		// Force identity resolution for current user only — never from query/body.
		$this->identity->resolve( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->service->get_home( $user_id ),
			)
		);
	}
}
