<?php
/**
 * Workspace REST: notifications inbox.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\Notifications\NotificationEventListener;
use HvnlyNab\Workspace\Notifications\NotificationSchema;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
final class NotificationsController {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var NotificationService
	 */
	private $service;

	/**
	 * @var NotificationEventListener|null
	 */
	private $listener;

	/**
	 * @param AgentIdentityService|null $identity Identity.
	 * @param PortalAuthorization|null  $auth     Authz.
	 * @param NotificationService|null  $service  Service.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?NotificationService $service = null
	) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth     = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->service  = $service ? $service : new NotificationService( $this->identity, $this->auth );
	}

	/**
	 * @return void
	 */
	public function register(): void {
		NotificationSchema::create_table();
		$this->listener = new NotificationEventListener( null, $this->identity );
		$this->listener->register();

		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'hvnly_workspace_localize_data', array( $this, 'filter_localize' ) );
	}

	/**
	 * @param array<string, mixed> $data Localize.
	 * @return array<string, mixed>
	 */
	public function filter_localize( array $data ): array {
		$rest = isset( $data['rest'] ) && is_array( $data['rest'] ) ? $data['rest'] : array();
		$rest['notifications'] = esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/notifications' ) );
		$data['rest']          = $rest;
		return $data;
	}

	/**
	 * @return void
	 */
	public function routes(): void {
		$ns = WorkspaceConstants::REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/notifications',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_notifications' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/notifications/read-all',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'read_all' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/notifications/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'patch_notification' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public function permission() {
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
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_notifications( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$this->identity->resolve( $user_id );

		$result = $this->service->list_notifications(
			$user_id,
			array(
				'page'     => (int) $request->get_param( 'page' ),
				'perPage'  => (int) $request->get_param( 'per_page' ),
				'status'   => (string) $request->get_param( 'status' ),
				'category' => (string) $request->get_param( 'category' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'data' => $result ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function patch_notification( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$this->identity->resolve( $user_id );

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		// Only mark-read supported.
		if ( empty( $payload['isRead'] ) && empty( $payload['read'] ) ) {
			$payload['isRead'] = true;
		}

		$result = $this->service->mark_read( $user_id, (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'data' => $result ) );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function read_all( WP_REST_Request $request ) {
		unset( $request );
		$user_id = get_current_user_id();
		$this->identity->resolve( $user_id );

		$result = $this->service->mark_all_read( $user_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'data' => $result ) );
	}
}
