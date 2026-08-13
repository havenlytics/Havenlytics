<?php
/**
 * Workspace REST: GET /hvnly/v1/analytics
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
 * Analytics overview for the current logged-in Workspace user.
 *
 * @since 3.2.0
 */
final class AnalyticsController {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var AnalyticsService
	 */
	private $service;

	/**
	 * @param AgentIdentityService|null $identity Identity.
	 * @param PortalAuthorization|null  $auth     Authz.
	 * @param AnalyticsService|null     $service  Analytics builder.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?AnalyticsService $service = null
	) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth     = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->service  = $service ? $service : new AnalyticsService( $this->identity, $this->auth );
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
		$rest              = isset( $data['rest'] ) && is_array( $data['rest'] ) ? $data['rest'] : array();
		$rest['analytics'] = esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/analytics' ) );
		$data['rest']      = $rest;
		return $data;
	}

	/**
	 * @return void
	 */
	public function routes(): void {
		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/analytics',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_analytics' ),
				'permission_callback' => array( $this, 'permission_analytics' ),
				'args'                => array(
					'range' => array(
						'description'       => 'today|7d|30d|month|all',
						'type'              => 'string',
						'required'          => false,
						'default'           => '30d',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public function permission_analytics() {
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
	 * GET /analytics — current user only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_analytics( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'hvnly_workspace_unauthorized',
				__( 'Authentication required.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		$this->identity->resolve( $user_id );

		$range = sanitize_key( (string) $request->get_param( 'range' ) );
		if ( '' === $range ) {
			$range = '30d';
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->service->get_overview( $user_id, $range ),
			)
		);
	}
}
