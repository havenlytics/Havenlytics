<?php
/**
 * Workspace REST: Property media (featured + gallery images).
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
 * Media endpoints for draft property listings.
 *
 * @since 3.2.0
 */
final class PropertyMediaController {

	/**
	 * @var PropertyAccessGate
	 */
	private $access;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @param AgentIdentityService|null $identity Identity.
	 * @param PortalAuthorization|null  $auth     Auth.
	 */
	public function __construct( ?AgentIdentityService $identity = null, ?PortalAuthorization $auth = null ) {
		$identity     = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth   = $auth ? $auth : new PortalAuthorization( $identity );
		$this->access = new PropertyAccessGate( $this->auth, $identity );
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'hvnly_workspace_localize_data', array( $this, 'filter_localize' ) );
	}

	/**
	 * @param array<string, mixed> $data Boot data.
	 * @return array<string, mixed>
	 */
	public function filter_localize( array $data ): array {
		$rest                  = isset( $data['rest'] ) && is_array( $data['rest'] ) ? $data['rest'] : array();
		$rest['mediaMaxBytes'] = PropertyMediaService::MAX_BYTES;
		$rest['mediaMime']     = PropertyMediaService::ALLOWED_MIME;
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
			'/properties/(?P<id>\d+)/media',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_media' ),
					'permission_callback' => array( $this, 'permission_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload_media' ),
					'permission_callback' => array( $this, 'permission_upload' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/properties/(?P<id>\d+)/media/featured',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'set_featured' ),
				'permission_callback' => array( $this, 'permission_edit' ),
			)
		);

		register_rest_route(
			$ns,
			'/properties/(?P<id>\d+)/media/gallery',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'reorder_gallery' ),
				'permission_callback' => array( $this, 'permission_edit' ),
			)
		);

		register_rest_route(
			$ns,
			'/properties/(?P<id>\d+)/media/(?P<attachment_id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'detach_media' ),
				'permission_callback' => array( $this, 'permission_edit' ),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_edit( WP_REST_Request $request ) {
		$base = $this->require_user();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$id = absint( $request['id'] );
		if ( ! $this->access->can_edit( $id ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot manage media for this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_upload( WP_REST_Request $request ) {
		$edit = $this->permission_edit( $request );
		if ( is_wp_error( $edit ) ) {
			return $edit;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'hvnly_rest_forbidden',
				__( 'You cannot upload files.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /properties/{id}/media
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_media( WP_REST_Request $request ) {
		$post = $this->require_property( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => PropertyMediaService::get_media( (int) $post->ID ),
			)
		);
	}

	/**
	 * POST /properties/{id}/media — multipart upload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_media( WP_REST_Request $request ) {
		$post = $this->require_property( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : null;
		if ( ! $file ) {
			return new WP_Error(
				'hvnly_media_missing_file',
				__( 'No file uploaded.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$valid = PropertyMediaService::validate_upload( $file );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'file', (int) $post->ID );
		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error(
				'hvnly_media_upload_failed',
				$attachment_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$role = sanitize_key( (string) $request->get_param( 'role' ) );
		if ( 'featured' === $role ) {
			$set = PropertyMediaService::set_featured( (int) $post->ID, (int) $attachment_id );
			if ( is_wp_error( $set ) ) {
				return $set;
			}
		} else {
			PropertyMediaService::attach_to_gallery( (int) $post->ID, (int) $attachment_id );
		}

		$payload             = PropertyMediaService::get_media( (int) $post->ID );
		$payload['uploaded'] = PropertyMediaService::serialize_attachment( (int) $attachment_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $payload,
			)
		);
	}

	/**
	 * PUT /properties/{id}/media/featured
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_featured( WP_REST_Request $request ) {
		$post = $this->require_property( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$attachment_id = isset( $params['attachmentId'] ) ? absint( $params['attachmentId'] ) : 0;

		if ( $attachment_id <= 0 || ! PropertyMediaService::is_linked( (int) $post->ID, $attachment_id ) ) {
			// Allow setting any image owned by user / already in library if linked OR owned.
			if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
				return new WP_Error(
					'hvnly_media_invalid',
					__( 'Invalid attachment.', 'havenlytics' ),
					array( 'status' => 400 )
				);
			}
			if ( ! PropertyMediaService::user_owns_attachment( $attachment_id )
				&& ! PropertyMediaService::is_linked( (int) $post->ID, $attachment_id )
				&& ! current_user_can( 'manage_options' ) ) {
				return new WP_Error(
					'hvnly_rest_forbidden',
					__( 'You cannot use this attachment.', 'havenlytics' ),
					array( 'status' => 403 )
				);
			}
		}

		$set = PropertyMediaService::set_featured( (int) $post->ID, $attachment_id );
		if ( is_wp_error( $set ) ) {
			return $set;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => PropertyMediaService::get_media( (int) $post->ID ),
			)
		);
	}

	/**
	 * PUT /properties/{id}/media/gallery — reorder.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reorder_gallery( WP_REST_Request $request ) {
		$post = $this->require_property( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}
		$ids = isset( $params['ids'] ) && is_array( $params['ids'] ) ? $params['ids'] : array();

		$current = PropertyMediaService::get_gallery_ids( (int) $post->ID );
		$clean   = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && in_array( $id, $current, true ) ) {
				$clean[] = $id;
			}
		}

		// Keep any current IDs missing from payload at the end (safety).
		foreach ( $current as $id ) {
			if ( ! in_array( $id, $clean, true ) ) {
				$clean[] = $id;
			}
		}

		PropertyMediaService::set_gallery_ids( (int) $post->ID, $clean );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => PropertyMediaService::get_media( (int) $post->ID ),
			)
		);
	}

	/**
	 * DELETE /properties/{id}/media/{attachment_id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function detach_media( WP_REST_Request $request ) {
		$post = $this->require_property( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$attachment_id = absint( $request['attachment_id'] );
		if ( ! PropertyMediaService::is_linked( (int) $post->ID, $attachment_id ) ) {
			return new WP_Error(
				'hvnly_media_not_linked',
				__( 'Image is not attached to this property.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		$delete = (bool) $request->get_param( 'delete' );
		$data   = PropertyMediaService::detach( (int) $post->ID, $attachment_id, $delete );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	private function require_user() {
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
	 * @param int $id Property ID.
	 * @return \WP_Post|WP_Error
	 */
	private function require_property( int $id ) {
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
}
