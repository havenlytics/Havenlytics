<?php
/**
 * Favorites REST controller.
 *
 * @package HvnlyNab\Favorites
 * @since   3.4.0
 */

namespace HvnlyNab\Favorites;

use HvnlyNab\Workspace\WorkspaceConstants;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Saved-property endpoints under the `hvnly/v1` namespace.
 *
 * Unlike the Workspace controllers these routes require only a signed-in
 * user — favorites are personal data, not an agent privilege, and the public
 * archive must keep working for subscribers and buyers. The Saved Properties
 * *page* is still gated by the Workspace authorization layer; this API is not.
 *
 * The current user is always taken from the session. No endpoint accepts a
 * user id, so one user can never read or mutate another's favorites.
 *
 * @since 3.4.0
 */
final class FavoritesController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	public const ROUTE_BASE = 'favorites';

	/**
	 * @var FavoritesService
	 */
	private $service;

	/**
	 * @param FavoritesService|null $service Service.
	 */
	public function __construct( ?FavoritesService $service = null ) {
		$this->service = $service ? $service : new FavoritesService();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'hvnly_workspace_localize_data', array( $this, 'filter_localize' ) );
	}

	/**
	 * Expose the endpoint to the Workspace SPA boot payload.
	 *
	 * @param array<string, mixed> $data Localized data.
	 * @return array<string, mixed>
	 */
	public function filter_localize( array $data ): array {
		$rest = isset( $data['rest'] ) && is_array( $data['rest'] ) ? $data['rest'] : array();

		$rest['favorites'] = esc_url_raw(
			rest_url( WorkspaceConstants::REST_NAMESPACE . '/' . self::ROUTE_BASE )
		);

		$data['rest'] = $rest;

		// Seed the sidebar badge (3.4.0): one indexed COUNT at page load, so
		// the SPA never issues a REST request just to learn the total. Live
		// updates ride on the add/remove responses, which already carry it.
		$user_id           = get_current_user_id();
		$data['favorites'] = array(
			'count' => $user_id > 0 ? $this->service->repository()->count( $user_id ) : 0,
		);

		return $data;
	}

	/**
	 * @return void
	 */
	public function routes(): void {
		$ns   = WorkspaceConstants::REST_NAMESPACE;
		$base = self::ROUTE_BASE;

		register_rest_route(
			$ns,
			'/' . $base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_favorites' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 12,
							'sanitize_callback' => 'absint',
						),
						'orderby'  => array(
							'type'              => 'string',
							'default'           => 'date_added',
							'enum'              => array( 'date_added', 'title', 'price', 'date_published' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'order'    => array(
							'type'              => 'string',
							'default'           => 'DESC',
							'enum'              => array( 'ASC', 'DESC', 'asc', 'desc' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add_favorite' ),
					'permission_callback' => array( $this, 'permission' ),
					'args'                => array(
						'property_id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/' . $base . '/ids',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_ids' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/' . $base . '/merge',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'merge_favorites' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);

		register_rest_route(
			$ns,
			'/' . $base . '/(?P<id>\d+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'remove_favorite' ),
				'permission_callback' => array( $this, 'permission' ),
				'args'                => array(
					'id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Signed-in user + valid REST nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'hvnly_favorites_unauthorized',
				__( 'You must be signed in to manage saved properties.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		$nonce = sanitize_text_field( (string) $request->get_header( 'X-WP-Nonce' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'hvnly_favorites_invalid_nonce',
				__( 'Security check failed. Please refresh the page and try again.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /favorites
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_favorites( WP_REST_Request $request ) {
		$result = $this->service->list_favorites(
			get_current_user_id(),
			array(
				'page'     => (int) $request->get_param( 'page' ),
				'per_page' => (int) $request->get_param( 'per_page' ),
				'orderby'  => (string) $request->get_param( 'orderby' ),
				'order'    => (string) $request->get_param( 'order' ),
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
	 * GET /favorites/ids
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_ids( WP_REST_Request $request ) {
		unset( $request );

		$ids = $this->service->get_ids( get_current_user_id() );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'ids'   => $ids,
					'total' => count( $ids ),
				),
			)
		);
	}

	/**
	 * POST /favorites
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_favorite( WP_REST_Request $request ) {
		$property_id = absint( $request->get_param( 'property_id' ) );

		$result = $this->service->add( get_current_user_id(), $property_id );

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
	 * DELETE /favorites/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_favorite( WP_REST_Request $request ) {
		$property_id = absint( $request['id'] );

		$result = $this->service->remove( get_current_user_id(), $property_id );

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
	 * POST /favorites/merge
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function merge_favorites( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$raw_ids = array();
		if ( isset( $payload['property_ids'] ) && is_array( $payload['property_ids'] ) ) {
			$raw_ids = $payload['property_ids'];
		} elseif ( is_array( $request->get_param( 'property_ids' ) ) ) {
			$raw_ids = (array) $request->get_param( 'property_ids' );
		}

		$result = $this->service->merge( get_current_user_id(), $raw_ids );

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
