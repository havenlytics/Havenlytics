<?php
/**
 * Workspace REST: read-only guest mode.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.4.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Favorites\FavoritesService;
use HvnlyNab\Workspace\Auth\GuestSessionService;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Guest tour endpoints.
 *
 * Every data route here is **GET only** and returns publicly visible
 * information. There is deliberately no guest write surface of any kind: the
 * only non-GET routes start and end the session itself.
 *
 * A guest is never a WordPress user, so the ordinary Workspace controllers
 * continue to reject these requests with 401 — guests simply cannot reach
 * property mutation, media upload, profile editing or settings.
 *
 * @since 3.4.0
 */
final class GuestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	public const ROUTE_BASE = 'guest';

	/**
	 * Hard ceiling on guest property paging — a tour, not a scraping surface.
	 *
	 * @var int
	 */
	private const MAX_PAGES = 5;

	/**
	 * @var GuestSessionService
	 */
	private $sessions;

	/**
	 * @param GuestSessionService|null $sessions Session service.
	 */
	public function __construct( ?GuestSessionService $sessions = null ) {
		$this->sessions = $sessions ? $sessions : new GuestSessionService();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'hvnly_workspace_localize_data', array( $this, 'filter_localize' ) );
	}

	/**
	 * @return GuestSessionService
	 */
	public function sessions(): GuestSessionService {
		return $this->sessions;
	}

	/**
	 * Advertise guest state + endpoints to the SPA.
	 *
	 * @param array<string, mixed> $data Localized data.
	 * @return array<string, mixed>
	 */
	public function filter_localize( array $data ): array {
		$rest = isset( $data['rest'] ) && is_array( $data['rest'] ) ? $data['rest'] : array();

		$base = rest_url( WorkspaceConstants::REST_NAMESPACE . '/' . self::ROUTE_BASE );

		$rest['guest']           = esc_url_raw( $base );
		$rest['guestSession']    = esc_url_raw( $base . '/session' );
		$rest['guestDashboard']  = esc_url_raw( $base . '/dashboard' );
		$rest['guestProperties'] = esc_url_raw( $base . '/properties' );

		$data['rest']  = $rest;
		$data['guest'] = $this->sessions->boot_payload();

		$data['i18n']['guestLoginLabel']    = __( 'Continue as guest', 'havenlytics' );
		$data['i18n']['guestBannerTitle']   = __( 'You are exploring in guest mode', 'havenlytics' );
		$data['i18n']['guestBannerBody']    = __( 'This is a read-only preview. Creating, editing and deleting are disabled, and your session ends automatically.', 'havenlytics' );
		$data['i18n']['guestExitLabel']     = __( 'Exit guest mode', 'havenlytics' );
		$data['i18n']['guestReadOnlyLabel'] = __( 'Read only', 'havenlytics' );

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
			'/' . $base . '/session',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'start_session' ),
					'permission_callback' => array( $this, 'permission_start' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'end_session' ),
					'permission_callback' => array( $this, 'permission_nonce_only' ),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'session_status' ),
					'permission_callback' => array( $this, 'permission_nonce_only' ),
				),
			)
		);

		register_rest_route(
			$ns,
			'/' . $base . '/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'dashboard' ),
				'permission_callback' => array( $this, 'permission_guest' ),
			)
		);

		register_rest_route(
			$ns,
			'/' . $base . '/properties',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'properties' ),
				'permission_callback' => array( $this, 'permission_guest' ),
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
					// Comma-separated ids — lets a guest hydrate the favorites
					// they hold in localStorage. Still filtered to published
					// listings, so it exposes nothing a visitor cannot browse.
					'include'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$ns,
			'/' . $base . '/profile',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'profile' ),
				'permission_callback' => array( $this, 'permission_guest' ),
			)
		);
	}

	/**
	 * CSRF check shared by every guest route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private function verify_nonce( WP_REST_Request $request ) {
		$nonce = sanitize_text_field( (string) $request->get_header( 'X-WP-Nonce' ) );

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'hvnly_guest_invalid_nonce',
				__( 'Security check failed. Please refresh the page and try again.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Guest feature must be on and the Workspace available.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_start( WP_REST_Request $request ) {
		if ( ! WorkspaceSettings::is_enabled() ) {
			return new WP_Error(
				'hvnly_workspace_disabled',
				__( 'Workspace is disabled.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		if ( ! GuestSessionService::is_enabled() ) {
			return new WP_Error(
				'hvnly_guest_disabled',
				__( 'Guest access is not available on this site.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return $this->verify_nonce( $request );
	}

	/**
	 * Nonce only — used for ending/inspecting a session, which must work even
	 * after guest login has been switched off so an existing cookie can be
	 * cleaned up.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_nonce_only( WP_REST_Request $request ) {
		return $this->verify_nonce( $request );
	}

	/**
	 * Requires a valid, unexpired guest session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function permission_guest( WP_REST_Request $request ) {
		$nonce = $this->verify_nonce( $request );
		if ( is_wp_error( $nonce ) ) {
			return $nonce;
		}

		if ( ! $this->sessions->is_guest() ) {
			return new WP_Error(
				'hvnly_guest_session_expired',
				__( 'Your guest session has ended. Start a new one to continue exploring.', 'havenlytics' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * POST /guest/session
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_session( WP_REST_Request $request ) {
		unset( $request );

		$result = $this->sessions->create();

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
	 * DELETE /guest/session
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function end_session( WP_REST_Request $request ) {
		unset( $request );

		$this->sessions->destroy();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array( 'active' => false ),
			)
		);
	}

	/**
	 * GET /guest/session
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function session_status( WP_REST_Request $request ) {
		unset( $request );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $this->sessions->boot_payload(),
			)
		);
	}

	/**
	 * GET /guest/dashboard — public aggregate figures only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function dashboard( WP_REST_Request $request ) {
		unset( $request );

		$counts    = wp_count_posts( FavoritesService::POST_TYPE );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;

		// wp_count_posts() returns an object whose `publish` key is absent for
		// an unregistered post type — read it defensively, not chained.
		$agent_counts = wp_count_posts( 'hvnly_agent' );
		$agents       = ( is_object( $agent_counts ) && isset( $agent_counts->publish ) )
			? (int) $agent_counts->publish
			: 0;

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'readOnly' => true,
					'metrics'  => array(
						array(
							'key'   => 'published',
							'label' => __( 'Published listings', 'havenlytics' ),
							'value' => $published,
						),
						array(
							'key'   => 'agents',
							'label' => __( 'Active agents', 'havenlytics' ),
							'value' => $agents,
						),
					),
					'notice'   => __( 'Guest mode shows public information only. Sign in to manage your own listings.', 'havenlytics' ),
				),
			)
		);
	}

	/**
	 * GET /guest/properties — published listings, read-only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function properties( WP_REST_Request $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = max( 1, min( 24, $per_page ? $per_page : 12 ) );

		$page = min( $page, self::MAX_PAGES );

		$args = array(
			'post_type'              => FavoritesService::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		$include = (string) $request->get_param( 'include' );
		if ( '' !== $include ) {
			$ids = array_slice(
				array_values(
					array_filter(
						array_map( 'absint', explode( ',', $include ) )
					)
				),
				0,
				100
			);

			if ( empty( $ids ) ) {
				// An explicit but unusable include must return nothing, not
				// silently fall back to "every listing".
				return rest_ensure_response(
					array(
						'success' => true,
						'data'    => array(
							'items'      => array(),
							'page'       => 1,
							'perPage'    => $per_page,
							'total'      => 0,
							'totalPages' => 1,
							'readOnly'   => true,
						),
					)
				);
			}

			$args['post__in']       = $ids;
			$args['orderby']        = 'post__in';
			$args['posts_per_page'] = count( $ids );
			$args['paged']          = 1;
		}

		$query = new WP_Query( $args );

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$thumbnail_id = (int) get_post_thumbnail_id( $post->ID );
			$thumbnail    = '';
			if ( $thumbnail_id > 0 ) {
				$src = wp_get_attachment_image_src( $thumbnail_id, 'medium' );
				if ( is_array( $src ) && ! empty( $src[0] ) ) {
					$thumbnail = esc_url_raw( (string) $src[0] );
				}
			}

			$items[] = array(
				'id'        => (int) $post->ID,
				'title'     => get_the_title( $post ),
				'permalink' => esc_url_raw( (string) get_permalink( $post ) ),
				'thumbnail' => $thumbnail,
				'price'     => function_exists( 'hvnly_get_property_price' )
					? (string) hvnly_get_property_price( $post->ID )
					: '',
				'status'    => 'publish',
				// Explicit, so the SPA renders the read-only row shape rather
				// than inferring capability from a missing key.
				'actions'   => array(
					'canEdit'   => false,
					'canView'   => true,
					'canTrash'  => false,
					'canSubmit' => false,
				),
			);
		}

		wp_reset_postdata();

		$total       = (int) $query->found_posts;
		$total_pages = min( self::MAX_PAGES, (int) $query->max_num_pages );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'items'      => $items,
					'page'       => $page,
					'perPage'    => $per_page,
					'total'      => $total,
					'totalPages' => max( 1, $total_pages ),
					'readOnly'   => true,
				),
			)
		);
	}

	/**
	 * GET /guest/profile — a placeholder identity, never a real user record.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function profile( WP_REST_Request $request ) {
		unset( $request );

		$session = $this->sessions->current();

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => array(
					'readOnly'    => true,
					'displayName' => __( 'Guest', 'havenlytics' ),
					'role'        => __( 'Read-only visitor', 'havenlytics' ),
					'avatar'      => esc_url_raw( \HvnlyNab\Common\AvatarService::placeholder_url() ),
					'expiresAt'   => $session ? (int) $session['expires_at'] : 0,
					'notice'      => __( 'Guest sessions are temporary and cannot be edited. Create an account to build your agent profile.', 'havenlytics' ),
				),
			)
		);
	}
}
