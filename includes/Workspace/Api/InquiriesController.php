<?php
/**
 * Workspace REST: inquiries inbox on Contact Agent tables.
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
 * Agent-scoped inquiry inbox.
 *
 * Never accepts client agent_id for ownership.
 *
 * @since 3.2.0
 */
final class InquiriesController {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var InquiryService
	 */
	private $service;

	/**
	 * @param AgentIdentityService|null $identity Identity.
	 * @param PortalAuthorization|null  $auth     Authz.
	 * @param InquiryService|null       $service  Inquiry service.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?InquiryService $service = null
	) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth     = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->service  = $service ? $service : new InquiryService( $this->identity, $this->auth );
	}

	/**
	 * @return void
	 */
	public function register(): void {
		$this->service->register_hooks();
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'hvnly_workspace_localize_data', array( $this, 'filter_localize' ) );
	}

	/**
	 * @param array<string, mixed> $data Localize payload.
	 * @return array<string, mixed>
	 */
	public function filter_localize( array $data ): array {
		$rest = isset( $data['rest'] ) && is_array( $data['rest'] ) ? $data['rest'] : array();
		$rest['inquiries'] = esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/inquiries' ) );
		$data['rest']      = $rest;
		return $data;
	}

	/**
	 * @return void
	 */
	public function routes(): void {
		$ns = WorkspaceConstants::REST_NAMESPACE;

		register_rest_route(
			$ns,
			'/inquiries',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_inquiries' ),
				'permission_callback' => array( $this, 'permission_manage' ),
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'status'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'search'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/inquiries/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_inquiry' ),
					'permission_callback' => array( $this, 'permission_manage' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'patch_inquiry' ),
					'permission_callback' => array( $this, 'permission_manage' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/inquiries/(?P<id>\d+)/reply',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reply_inquiry' ),
				'permission_callback' => array( $this, 'permission_manage' ),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public function permission_manage() {
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

		if ( ! $this->auth->can_manage_inquiries( $user_id ) ) {
			return new WP_Error(
				'hvnly_inquiries_forbidden',
				__( 'You cannot manage inquiries.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /inquiries
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_inquiries( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$this->identity->resolve( $user_id );

		$result = $this->service->list_inquiries(
			$user_id,
			array(
				'page'    => (int) $request->get_param( 'page' ),
				'perPage' => (int) $request->get_param( 'per_page' ),
				'status'  => (string) $request->get_param( 'status' ),
				'search'  => (string) $request->get_param( 'search' ),
			)
		);

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
	 * GET /inquiries/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_inquiry( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$this->identity->resolve( $user_id );

		$result = $this->service->get_inquiry( $user_id, (int) $request['id'] );
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
	 * PATCH /inquiries/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function patch_inquiry( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$this->identity->resolve( $user_id );

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}
		unset( $payload['agent_id'], $payload['agentId'], $payload['user_id'], $payload['userId'] );

		$result = $this->service->patch_inquiry( $user_id, (int) $request['id'], $payload );
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
	 * POST /inquiries/{id}/reply
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reply_inquiry( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$this->identity->resolve( $user_id );

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$message = isset( $payload['message'] ) ? (string) $payload['message'] : '';
		$result  = $this->service->reply( $user_id, (int) $request['id'], $message );
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
