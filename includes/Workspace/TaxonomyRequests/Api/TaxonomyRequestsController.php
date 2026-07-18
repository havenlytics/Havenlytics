<?php
/**
 * Agent-facing taxonomy request REST endpoints.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests\Api
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests\Api;

use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestRegistry;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestRepository;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestService;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;

defined( 'ABSPATH' ) || exit;

final class TaxonomyRequestsController {

	/** @var AgentIdentityService */
	private $identity;

	/** @var PortalAuthorization */
	private $auth;

	/** @var TaxonomyRequestService */
	private $service;

	/** @var TaxonomyRequestRepository */
	private $repository;

	/** @var TaxonomyRequestRegistry */
	private $registry;

	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?TaxonomyRequestService $service = null
	) {
		$this->identity   = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth       = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->repository = new TaxonomyRequestRepository();
		$this->registry   = new TaxonomyRequestRegistry();
		$this->service    = $service ? $service : new TaxonomyRequestService( $this->repository, null, $this->registry, $this->identity );
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'hvnly_workspace_localize_data', array( $this, 'localize' ) );
	}

	/**
	 * @param array<string, mixed> $data Localized data.
	 * @return array<string, mixed>
	 */
	public function localize( array $data ): array {
		$rest                     = isset( $data['rest'] ) && is_array( $data['rest'] ) ? $data['rest'] : array();
		$rest['taxonomyRequests'] = esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/taxonomy-requests' ) );
		$data['rest']             = $rest;
		$data['taxonomyRequests'] = array(
			'taxonomies' => $this->registry->for_client(),
			'maxName'    => 191,
			'maxText'    => 2000,
		);
		return $data;
	}

	public function routes(): void {
		$namespace = WorkspaceConstants::REST_NAMESPACE;
		register_rest_route(
			$namespace,
			'/taxonomy-requests',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_requests' ),
					'permission_callback' => array( $this, 'permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_request' ),
					'permission_callback' => array( $this, 'permission' ),
				),
			)
		);
		register_rest_route(
			$namespace,
			'/taxonomy-requests/suggestions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'suggestions' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
		register_rest_route(
			$namespace,
			'/taxonomy-requests/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_request' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permission( \WP_REST_Request $request ) {
		if ( ! WorkspaceSettings::is_enabled() ) {
			return new \WP_Error( 'hvnly_workspace_disabled', __( 'Workspace is disabled.', 'havenlytics' ), array( 'status' => 403 ) );
		}
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'hvnly_workspace_unauthorized', __( 'Authentication required.', 'havenlytics' ), array( 'status' => 401 ) );
		}
		$nonce = sanitize_text_field( (string) $request->get_header( 'X-WP-Nonce' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'hvnly_workspace_invalid_nonce', __( 'Security check failed.', 'havenlytics' ), array( 'status' => 403 ) );
		}
		if ( ! $this->auth->can_access_workspace( get_current_user_id() ) ) {
			return new \WP_Error( 'hvnly_workspace_forbidden', __( 'You do not have Workspace access.', 'havenlytics' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_request( \WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$result  = $this->service->submit( get_current_user_id(), is_array( $payload ) ? $payload : array() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array(
			'success' => true,
			'data' => $result,
		) );
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function list_requests( \WP_REST_Request $request ): \WP_REST_Response {
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = min( 50, max( 1, absint( $request->get_param( 'per_page' ) ) ?: 20 ) );
		$args     = array(
			'user_id' => get_current_user_id(),
			'status'  => sanitize_key( (string) $request->get_param( 'status' ) ),
			'limit'   => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
		);
		$total    = $this->repository->count( $args );
		$data     = array(
			'items'      => $this->repository->list( $args ),
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $total,
			'totalPages' => (int) ceil( $total / $per_page ),
		);
		return rest_ensure_response( array(
			'success' => true,
			'data' => $data,
		) );
	}

	/**
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_request( \WP_REST_Request $request ) {
		$row = $this->repository->find_for_user( absint( $request['id'] ), get_current_user_id() );
		return $row
			? rest_ensure_response( array(
				'success' => true,
				'data' => $row,
			) )
			: new \WP_Error( 'hvnly_taxonomy_request_not_found', __( 'Taxonomy request not found.', 'havenlytics' ), array( 'status' => 404 ) );
	}

	/**
	 * @return \WP_REST_Response
	 */
	public function suggestions( \WP_REST_Request $request ): \WP_REST_Response {
		$items = $this->service->suggestions(
			sanitize_key( (string) $request->get_param( 'taxonomy' ) ),
			sanitize_text_field( (string) $request->get_param( 'q' ) )
		);
		return rest_ensure_response( array(
			'success' => true,
			'data' => array( 'items' => $items ),
		) );
	}
}
