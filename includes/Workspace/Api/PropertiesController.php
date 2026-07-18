<?php
/**
 * Workspace REST: Properties draft CRUD (hvnly/v1).
 *
 * GET/POST/PUT — listing CRUD + workflow (submit / publish / status).
 * Preview + media on sibling routes.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Properties draft endpoints for the Workspace Property Form Engine.
 *
 * @since 3.2.0
 */
final class PropertiesController {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var PropertyAccessGate
	 */
	private $access;

	/**
	 * @var PropertyPreviewService
	 */
	private $preview;

	/**
	 * @var PropertyWorkflowService
	 */
	private $workflow;

	/**
	 * @var PropertyManagementService
	 */
	private $management;

	/**
	 * @param AgentIdentityService|null $identity Identity.
	 * @param PortalAuthorization|null  $auth     Auth.
	 */
	public function __construct( ?AgentIdentityService $identity = null, ?PortalAuthorization $auth = null ) {
		$this->identity   = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth       = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->access     = new PropertyAccessGate( $this->auth, $this->identity );
		$this->preview    = new PropertyPreviewService( $this->access );
		$this->workflow   = new PropertyWorkflowService( $this->access );
		$this->management = new PropertyManagementService( $this->access );
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'hvnly_workspace_localize_data', array( $this, 'filter_localize' ) );
		$this->preview->register();
	}

	/**
	 * @param array<string, mixed> $data Boot data.
	 * @return array<string, mixed>
	 */
	public function filter_localize( array $data ): array {
		$rest = isset( $data['rest'] ) && is_array( $data['rest'] ) ? $data['rest'] : array();
		$rest['properties'] = esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/properties' ) );
		$rest['propertySchema'] = esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/properties/schema' ) );
		$data['rest']       = $rest;
		return $data;
	}

	/**
	 * @return void
	 */
	public function routes(): void {
		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/schema',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_schema' ),
				'permission_callback' => array( $this, 'permission_list' ),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_properties' ),
					'permission_callback' => array( $this, 'permission_list' ),
					'args'                => array(
						'page'      => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page'  => array(
							'type'    => 'integer',
							'default' => 5,
						),
						'search'    => array(
							'type'    => 'string',
							'default' => '',
						),
						'status'    => array(
							'type'    => 'string',
							'default' => 'all',
						),
						'orderby'   => array(
							'type'    => 'string',
							'default' => 'modified',
						),
						'order'     => array(
							'type'    => 'string',
							'default' => 'DESC',
						),
						'date_from' => array(
							'type'    => 'string',
							'default' => '',
						),
						'date_to'   => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_draft' ),
					'permission_callback' => array( $this, 'permission_create' ),
				),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/bulk',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bulk_action' ),
				'permission_callback' => array( $this, 'permission_list' ),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_property' ),
					'permission_callback' => array( $this, 'permission_edit_item' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_draft' ),
					'permission_callback' => array( $this, 'permission_edit_item' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'trash_property' ),
					'permission_callback' => array( $this, 'permission_trash' ),
				),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/(?P<id>\d+)/restore',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'restore_property' ),
				'permission_callback' => array( $this, 'permission_restore' ),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/(?P<id>\d+)/force',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'force_delete_property' ),
				'permission_callback' => array( $this, 'permission_force_delete' ),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/(?P<id>\d+)/duplicate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'duplicate_property' ),
				'permission_callback' => array( $this, 'permission_duplicate' ),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/(?P<id>\d+)/preview',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_preview' ),
				'permission_callback' => array( $this, 'permission_edit_item' ),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/(?P<id>\d+)/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit_property' ),
				'permission_callback' => array( $this, 'permission_submit' ),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/(?P<id>\d+)/publish',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'publish_property' ),
				'permission_callback' => array( $this, 'permission_publish' ),
			)
		);

		register_rest_route(
			WorkspaceConstants::REST_NAMESPACE,
			'/properties/(?P<id>\d+)/status',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_status' ),
				'permission_callback' => array( $this, 'permission_change_status' ),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public function permission_list() {
		return $this->require_workspace_user();
	}

	/**
	 * @return true|WP_Error
	 */
	public function permission_create() {
		$base = $this->require_workspace_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		if ( ! $this->access->can_create() ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot create property drafts.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_edit_item( WP_REST_Request $request ) {
		$base = $this->require_workspace_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$id = absint( $request['id'] );
		if ( ! $this->access->can_edit( $id ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot edit this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_submit( WP_REST_Request $request ) {
		$base = $this->require_workspace_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$id = absint( $request['id'] );
		if ( ! $this->access->can_submit( $id ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot submit this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_publish( WP_REST_Request $request ) {
		$base = $this->require_workspace_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$id = absint( $request['id'] );
		if ( ! $this->access->can_publish( $id ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot publish this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_change_status( WP_REST_Request $request ) {
		$base = $this->require_workspace_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$id = absint( $request['id'] );
		if ( ! $this->access->can_change_status( $id ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot change this property status.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_trash( WP_REST_Request $request ) {
		$base = $this->require_workspace_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$id = absint( $request['id'] );
		if ( ! $this->access->can_trash( $id ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot trash this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_restore( WP_REST_Request $request ) {
		$base = $this->require_workspace_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$id = absint( $request['id'] );
		if ( ! $this->access->can_restore( $id ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot restore this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_force_delete( WP_REST_Request $request ) {
		$base = $this->require_workspace_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$id = absint( $request['id'] );
		if ( ! $this->access->can_delete_permanently( $id ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'Only administrators can permanently delete properties.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_duplicate( WP_REST_Request $request ) {
		$base = $this->require_workspace_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}
		$id = absint( $request['id'] );
		if ( ! $this->access->can_duplicate( $id ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot duplicate this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * GET /properties — listings with status/search/sort/date filters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_properties( WP_REST_Request $request ) {
		$page      = max( 1, (int) $request->get_param( 'page' ) );
		$per_page  = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$search    = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$status    = sanitize_key( (string) $request->get_param( 'status' ) );
		$orderby   = sanitize_key( (string) $request->get_param( 'orderby' ) );
		$order     = strtoupper( sanitize_text_field( (string) $request->get_param( 'order' ) ) );
		$date_from = sanitize_text_field( (string) $request->get_param( 'date_from' ) );
		$date_to   = sanitize_text_field( (string) $request->get_param( 'date_to' ) );
		$user_id   = get_current_user_id();

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$orderby_map = array(
			'modified' => 'modified',
			'date'     => 'date',
			'title'    => 'title',
		);
		$orderby = isset( $orderby_map[ $orderby ] ) ? $orderby_map[ $orderby ] : 'modified';

		$args = array(
			'post_type'              => PropertyFormMapper::POST_TYPE,
			'post_status'            => $this->resolve_list_post_statuses( $status ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => $orderby,
			'order'                  => $order,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		if ( ! user_can( $user_id, 'manage_options' ) ) {
			$args['author'] = $user_id;
		}

		if ( $search !== '' ) {
			$args['s'] = $search;
		}

		$date_query = $this->build_date_query( $date_from, $date_to );
		if ( ! empty( $date_query ) ) {
			$args['date_query'] = $date_query;
		}

		if ( 'rejected' === $status ) {
			$args['meta_query'] = array(
				array(
					'key'   => PropertyFormMapper::META_LISTING_STATUS,
					'value' => 'Rejected',
				),
			);
		} elseif ( 'draft' === $status ) {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => PropertyFormMapper::META_LISTING_STATUS,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => PropertyFormMapper::META_LISTING_STATUS,
					'value'   => 'Rejected',
					'compare' => '!=',
				),
			);
		}

		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$items[] = $this->enrich_list_item( $post );
			}
		}

		$total       = (int) $query->found_posts;
		$total_pages = max( 1, (int) $query->max_num_pages );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'items'      => $items,
					'page'       => $page,
					'perPage'    => $per_page,
					'total'      => $total,
					'totalPages' => $total_pages,
					'filters'    => array(
						'status'  => $status ? $status : 'all',
						'orderby' => $orderby,
						'order'   => $order,
					),
				),
			)
		);
	}

	/**
	 * GET /properties/schema — Property Builder portal schema.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_schema( WP_REST_Request $request ) {
		unset( $request );
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => PropertyBuilderSchemaService::get_portal_schema(),
			)
		);
	}

	/**
	 * GET /properties/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_property( WP_REST_Request $request ) {
		$post = $this->get_managed_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->form_record_from_post( $post ),
			)
		);
	}

	/**
	 * POST /properties — create draft.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_draft( WP_REST_Request $request ) {
		$incoming = $this->extract_values( $request );
		$values   = PropertyFormMapper::merge_patch( PropertyFormMapper::empty_values(), $incoming );
		$valid    = PropertyDraftValidator::validate( $values );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$user_id = get_current_user_id();
		$post_id = wp_insert_post(
			array(
				'post_type'    => PropertyFormMapper::POST_TYPE,
				'post_status'  => 'draft',
				'post_title'   => sanitize_text_field( (string) ( $values['title'] ?? '' ) ),
				'post_content' => wp_kses_post( (string) ( $values['description'] ?? '' ) ),
				'post_author'  => $user_id,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new WP_Error(
				'hvnly_property_create_failed',
				__( 'Could not create property draft.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		// Create: write provided keys only (no empty-default wipe of absent media/taxonomies).
		PropertyFormMapper::apply_values( (int) $post_id, $incoming, false );
		$this->assign_current_agent( (int) $post_id, $user_id );

		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'hvnly_property_create_failed',
				__( 'Could not create property draft.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->form_record_from_post( $post ),
			)
		);
	}

	/**
	 * GET /properties/{id}/preview — nonce URL for the live single-property template.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_preview( WP_REST_Request $request ) {
		$post = $this->get_previewable_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$payload = $this->preview->preview_payload( $post );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $payload,
			)
		);
	}

	/**
	 * PUT /properties/{id} — update draft (never publishes).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_draft( WP_REST_Request $request ) {
		$post = $this->get_managed_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$incoming = $this->extract_values( $request );
		$existing = PropertyFormMapper::values_from_post( $post );
		$merged   = PropertyFormMapper::merge_patch( $existing, $incoming );
		$valid    = PropertyDraftValidator::validate( $merged );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		// PATCH: only write keys present in the request — never wipe absent meta/taxonomies/media.
		PropertyFormMapper::apply_values( (int) $post->ID, $incoming, true );

		$fresh = get_post( (int) $post->ID );
		if ( ! $fresh ) {
			return new WP_Error(
				'hvnly_property_update_failed',
				__( 'Could not update property.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->form_record_from_post( $fresh ),
			)
		);
	}

	/**
	 * POST /properties/{id}/submit — draft/rejected → pending.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_property( WP_REST_Request $request ) {
		$post = $this->get_managed_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$note   = $this->extract_note( $request );
		$result = $this->workflow->submit( $post, $note );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fresh = get_post( (int) $post->ID );
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->form_record_from_post( $fresh ? $fresh : $post ),
				'message' => __( 'Submitted for review.', 'havenlytics' ),
			)
		);
	}

	/**
	 * POST /properties/{id}/publish — draft|pending → publish.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function publish_property( WP_REST_Request $request ) {
		$post = $this->get_managed_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$note   = $this->extract_note( $request );
		$result = $this->workflow->publish( $post, $note );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fresh = get_post( (int) $post->ID );
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->form_record_from_post( $fresh ? $fresh : $post ),
				'message' => __( 'Property published.', 'havenlytics' ),
			)
		);
	}

	/**
	 * POST /properties/{id}/status — generic changeStatus.
	 *
	 * Body: { "status": "draft|pending|publish|rejected", "note": "..." }
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function change_status( WP_REST_Request $request ) {
		$post = $this->get_managed_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$to   = isset( $params['status'] ) ? sanitize_text_field( (string) $params['status'] ) : '';
		$note = $this->extract_note( $request );

		$result = $this->workflow->change_status( $post, $to, $note );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fresh = get_post( (int) $post->ID );
		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->form_record_from_post( $fresh ? $fresh : $post ),
				'message' => __( 'Status updated.', 'havenlytics' ),
			)
		);
	}

	/**
	 * DELETE /properties/{id} — move to trash.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function trash_property( WP_REST_Request $request ) {
		$post = $this->get_managed_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$result = $this->management->trash( $post );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->enrich_list_item( $result ),
				'message' => __( 'Property moved to trash.', 'havenlytics' ),
			)
		);
	}

	/**
	 * POST /properties/{id}/restore.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_property( WP_REST_Request $request ) {
		$post = $this->get_managed_post( absint( $request['id'] ), true );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$result = $this->management->restore( $post );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->enrich_list_item( $result ),
				'message' => __( 'Property restored.', 'havenlytics' ),
			)
		);
	}

	/**
	 * DELETE /properties/{id}/force — permanent delete (admin).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function force_delete_property( WP_REST_Request $request ) {
		$post = $this->get_managed_post( absint( $request['id'] ), true );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$id     = (int) $post->ID;
		$result = $this->management->delete_permanently( $post );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'id' => $id, 'deleted' => true ),
				'message' => __( 'Property permanently deleted.', 'havenlytics' ),
			)
		);
	}

	/**
	 * POST /properties/{id}/duplicate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate_property( WP_REST_Request $request ) {
		$post = $this->get_managed_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$result = $this->management->duplicate( $post );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->form_record_from_post( $result ),
				'message' => __( 'Property duplicated as draft.', 'havenlytics' ),
			)
		);
	}

	/**
	 * POST /properties/bulk — { action, ids[] }.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk_action( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$action = isset( $params['action'] ) ? sanitize_key( (string) $params['action'] ) : '';
		$ids    = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : array();
		$ids    = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( empty( $ids ) ) {
			return new WP_Error(
				'hvnly_bulk_empty',
				__( 'Select at least one property.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$allowed = array( 'trash', 'restore', 'submit', 'publish' );
		if ( ! in_array( $action, $allowed, true ) ) {
			return new WP_Error(
				'hvnly_bulk_invalid_action',
				__( 'Invalid bulk action.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$succeeded = array();
		$failed    = array();

		foreach ( $ids as $id ) {
			$include_trash = ( 'restore' === $action );
			$post          = $this->get_managed_post( $id, $include_trash );
			if ( is_wp_error( $post ) ) {
				$failed[] = array(
					'id'      => $id,
					'message' => $post->get_error_message(),
				);
				continue;
			}

			$result = $this->run_bulk_item( $action, $post );
			if ( is_wp_error( $result ) ) {
				$failed[] = array(
					'id'      => $id,
					'message' => $result->get_error_message(),
				);
				continue;
			}

			$succeeded[] = $id;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'action'    => $action,
					'succeeded' => $succeeded,
					'failed'    => $failed,
				),
				'message' => sprintf(
					/* translators: 1: succeeded count, 2: failed count */
					__( 'Bulk action finished: %1$d succeeded, %2$d failed.', 'havenlytics' ),
					count( $succeeded ),
					count( $failed )
				),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	private function require_workspace_user() {
		if ( ! WorkspaceSettings::is_enabled() ) {
			return new WP_Error(
				'hvnly_workspace_disabled',
				__( 'Workspace is disabled.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'hvnly_rest_unauthorized',
				__( 'Authentication required.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		if ( ! $this->auth->can_access_workspace() ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'Workspace access unavailable.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * @param int $id Post ID.
	 * @return \WP_Post|WP_Error
	 */
	private function get_draft_post( int $id ) {
		return $this->get_managed_post( $id );
	}

	/**
	 * Managed property (draft, pending, publish; optionally trash).
	 *
	 * @param int  $id          Post ID.
	 * @param bool $allow_trash Include trash.
	 * @return \WP_Post|WP_Error
	 */
	private function get_managed_post( int $id, bool $allow_trash = false ) {
		$post = get_post( $id );
		if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'hvnly_property_not_found',
				__( 'Property not found.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		$allowed = array( 'draft', 'auto-draft', 'pending', 'publish' );
		if ( $allow_trash ) {
			$allowed[] = 'trash';
		}

		if ( ! in_array( $post->post_status, $allowed, true ) ) {
			return new WP_Error(
				'hvnly_property_unavailable',
				__( 'This property is not available in Workspace.', 'havenlytics' ),
				array( 'status' => 409 )
			);
		}

		return $post;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function extract_note( WP_REST_Request $request ): string {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		if ( isset( $params['note'] ) ) {
			return sanitize_textarea_field( (string) $params['note'] );
		}
		if ( isset( $params['reason'] ) ) {
			return sanitize_textarea_field( (string) $params['reason'] );
		}
		return '';
	}

	/**
	 * @param string   $action Action key.
	 * @param \WP_Post $post   Post.
	 * @return true|WP_Error
	 */
	private function run_bulk_item( string $action, \WP_Post $post ) {
		switch ( $action ) {
			case 'trash':
				$result = $this->management->trash( $post );
				return is_wp_error( $result ) ? $result : true;
			case 'restore':
				$result = $this->management->restore( $post );
				return is_wp_error( $result ) ? $result : true;
			case 'submit':
				$result = $this->workflow->submit( $post );
				return is_wp_error( $result ) ? $result : true;
			case 'publish':
				$result = $this->workflow->publish( $post );
				return is_wp_error( $result ) ? $result : true;
			default:
				return new WP_Error(
					'hvnly_bulk_invalid_action',
					__( 'Invalid bulk action.', 'havenlytics' ),
					array( 'status' => 400 )
				);
		}
	}

	/**
	 * @param string $status Filter status.
	 * @return string[]
	 */
	private function resolve_list_post_statuses( string $status ): array {
		switch ( $status ) {
			case 'draft':
			case 'rejected':
				return array( 'draft' );
			case 'pending':
				return array( 'pending' );
			case 'publish':
			case 'published':
				return array( 'publish' );
			case 'trash':
				return array( 'trash' );
			case 'all':
			default:
				return array( 'draft', 'pending', 'publish' );
		}
	}

	/**
	 * @param string $date_from Y-m-d.
	 * @param string $date_to   Y-m-d.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_date_query( string $date_from, string $date_to ): array {
		$query = array();
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$query['after'] = $date_from;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$query['before'] = $date_to . ' 23:59:59';
		}
		if ( empty( $query ) ) {
			return array();
		}
		$query['inclusive'] = true;
		$query['column']    = 'post_modified';
		return array( $query );
	}

	/**
	 * List item + server-computed action flags.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private function enrich_list_item( \WP_Post $post ): array {
		$item = PropertyFormMapper::list_item_from_post( $post );
		$id   = (int) $post->ID;

		$workflow = null;
		if ( 'trash' !== $post->post_status ) {
			$workflow = $this->workflow->workflow_payload( $post );
		}

		$allowed = is_array( $workflow ) && isset( $workflow['allowedTransitions'] )
			? $workflow['allowedTransitions']
			: array();

		$item['actions'] = array(
			'canView'      => 'publish' === $post->post_status,
			'canEdit'      => 'trash' !== $post->post_status && $this->access->can_edit( $id ),
			'canPreview'   => 'trash' !== $post->post_status && $this->access->can_edit( $id ),
			'canDuplicate' => $this->access->can_duplicate( $id ),
			'canSubmit'    => ! empty( $workflow['canSubmit'] ) && in_array( 'pending', $allowed, true ),
			'canPublish'   => ! empty( $workflow['canPublish'] ) && in_array( 'publish', $allowed, true ),
			'canTrash'     => $this->access->can_trash( $id ),
			'canRestore'   => $this->access->can_restore( $id ),
			'canDelete'    => $this->access->can_delete_permanently( $id ),
		);

		return $item;
	}

	/**
	 * Any property status may be previewed when the access gate allows.
	 *
	 * @param int $id Post ID.
	 * @return \WP_Post|WP_Error
	 */
	private function get_previewable_post( int $id ) {
		$post = get_post( $id );
		if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'hvnly_property_not_found',
				__( 'Property not found.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		return $post;
	}

	/**
	 * Extract form values from the REST request.
	 *
	 * IMPORTANT: Does NOT seed missing Builder fields / taxonomies with empty defaults.
	 * Empty defaults previously caused apply_values() to wipe existing meta on update.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function extract_values( WP_REST_Request $request ): array {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$values = isset( $params['values'] ) && is_array( $params['values'] )
			? $params['values']
			: $params;

		$out = array(
			'fields' => array(),
		);

		$scalar_keys = array(
			'title',
			'excerpt',
			'description',
			'status',
			'propertyType',
			'propertyDepartment',
			'currency',
			'featuredImageId',
		);

		foreach ( $scalar_keys as $key ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}
			if ( is_bool( $values[ $key ] ) ) {
				$out[ $key ] = $values[ $key ] ? '1' : '';
			} elseif ( is_scalar( $values[ $key ] ) ) {
				$out[ $key ] = (string) $values[ $key ];
			}
		}

		$taxonomy_array_keys = array(
			'propertyStatus',
			'propertyFeaturesTax',
			'propertyLocations',
			'propertyTags',
			'propertyBadges',
		);

		foreach ( $taxonomy_array_keys as $key ) {
			if ( ! array_key_exists( $key, $values ) || ! is_array( $values[ $key ] ) ) {
				continue;
			}
			$out[ $key ] = array_values(
				array_filter(
					array_map(
						static function ( $slug ) {
							return sanitize_title( (string) $slug );
						},
						$values[ $key ]
					)
				)
			);
		}

		if ( isset( $values['fields'] ) && is_array( $values['fields'] ) ) {
			foreach ( $values['fields'] as $fk => $fv ) {
				$fk = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $fk );
				if ( '' === $fk ) {
					continue;
				}
				if ( is_array( $fv ) ) {
					$out['fields'][ $fk ] = $fv;
				} elseif ( is_bool( $fv ) ) {
					$out['fields'][ $fk ] = $fv;
				} elseif ( is_scalar( $fv ) ) {
					$out['fields'][ $fk ] = (string) $fv;
				}
			}
		}

		return $out;
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private function form_record_from_post( \WP_Post $post ): array {
		return array(
			'id'       => (int) $post->ID,
			'mode'     => 'edit',
			'values'   => PropertyFormMapper::values_from_post( $post ),
			'schema'   => PropertyBuilderSchemaService::get_portal_schema(),
			'item'     => PropertyFormMapper::list_item_from_post( $post ),
			'workflow' => $this->workflow->workflow_payload( $post ),
		);
	}

	/**
	 * Auto-assign linked agent CPT to the property (META SSOT + Builder agents fields).
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function assign_current_agent( int $post_id, int $user_id ): void {
		$resolved = $this->identity->resolve( $user_id );
		$agent_id = isset( $resolved['agent']['id'] ) ? absint( $resolved['agent']['id'] ) : 0;
		if ( $agent_id <= 0 ) {
			return;
		}

		$ids = array( $agent_id );
		update_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS, $ids );

		// Keep Builder `{base}_agents` in sync so hydrate/PATCH baselines match META SSOT.
		foreach ( PropertyFormMapper::agents_storage_field_names() as $field_name ) {
			update_post_meta( $post_id, $field_name, wp_json_encode( $ids ) );
		}
	}
}
