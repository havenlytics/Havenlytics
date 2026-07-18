<?php
/**
 * Property preview — reuses the public single-property template via WP preview links.
 *
 * No duplicate preview template. Draft watermark + badge only when Workspace requests preview.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Builds nonce preview URLs and overlays for authorized Workspace editors.
 *
 * @since 3.2.0
 */
final class PropertyPreviewService {

	/**
	 * Query flag appended to preview URLs (badge / watermark / framing).
	 */
	public const QUERY_FLAG = 'hvnly_ws_preview';

	/**
	 * @var PropertyAccessGate
	 */
	private $access;

	/**
	 * @param PropertyAccessGate|null $access Access gate.
	 */
	public function __construct( ?PropertyAccessGate $access = null ) {
		$this->access = $access ? $access : new PropertyAccessGate();
	}

	/**
	 * Register frontend hooks (caps, badge, watermark, framing).
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'map_meta_cap', array( $this, 'filter_map_meta_cap' ), 10, 4 );
		add_filter( 'body_class', array( $this, 'filter_body_class' ) );
		add_action( 'wp_footer', array( $this, 'render_preview_chrome' ), 5 );
		add_filter( 'wp_headers', array( $this, 'filter_wp_headers' ) );
	}

	/**
	 * Allow assigned agents (and other gate-approved editors) to use WP preview caps.
	 *
	 * @param string[] $caps    Required caps.
	 * @param string   $cap     Capability.
	 * @param int      $user_id User ID.
	 * @param array    $args    Extra args (post ID).
	 * @return string[]
	 */
	public function filter_map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'read_post' ), true ) ) {
			return $caps;
		}

		$post_id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( $post_id <= 0 ) {
			return $caps;
		}

		$post = get_post( $post_id );
		if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return $caps;
		}

		if ( $this->access->can_edit( $post_id, (int) $user_id ) ) {
			return array( 'exist' );
		}

		return $caps;
	}

	/**
	 * Build a preview URL that renders through the normal frontend template.
	 *
	 * @param WP_Post $post Property post.
	 * @return string|WP_Error
	 */
	public function build_preview_url( WP_Post $post ) {
		if ( PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'hvnly_preview_invalid_type',
				__( 'Not a property listing.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->access->can_edit( (int) $post->ID ) ) {
			return new WP_Error(
				'hvnly_preview_forbidden',
				__( 'You cannot preview this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$url = get_preview_post_link( $post );
		if ( ! is_string( $url ) || '' === $url ) {
			$url = get_permalink( $post );
		}

		if ( ! is_string( $url ) || '' === $url ) {
			return new WP_Error(
				'hvnly_preview_url_failed',
				__( 'Could not build preview URL.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		$url = add_query_arg( self::QUERY_FLAG, '1', $url );

		/**
		 * Filter Workspace property preview URL.
		 *
		 * @since 3.2.0
		 *
		 * @param string  $url  Preview URL.
		 * @param WP_Post $post Property post.
		 */
		return (string) apply_filters( 'hvnly_workspace_property_preview_url', $url, $post );
	}

	/**
	 * Payload for REST GET /properties/{id}/preview.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, mixed>|WP_Error
	 */
	public function preview_payload( WP_Post $post ) {
		$url = $this->build_preview_url( $post );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$is_draft = in_array( $post->post_status, array( 'draft', 'auto-draft', 'pending' ), true );

		return array(
			'previewUrl' => $url,
			'status'     => (string) $post->post_status,
			'isDraft'    => $is_draft,
			'propertyId' => (int) $post->ID,
		);
	}

	/**
	 * @return bool
	 */
	public function is_workspace_preview_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag for chrome.
		return isset( $_GET[ self::QUERY_FLAG ] ) && '1' === (string) $_GET[ self::QUERY_FLAG ];
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function filter_body_class( $classes ) {
		if ( ! $this->should_render_chrome() ) {
			return $classes;
		}

		$classes[] = 'hvnly-ws-property-preview';
		$post      = get_queried_object();
		if ( $post instanceof WP_Post && in_array( $post->post_status, array( 'draft', 'auto-draft', 'pending' ), true ) ) {
			$classes[] = 'hvnly-ws-property-preview--draft';
		}

		return $classes;
	}

	/**
	 * Preview badge + draft watermark (overlay only — same template underneath).
	 *
	 * @return void
	 */
	public function render_preview_chrome(): void {
		if ( ! $this->should_render_chrome() ) {
			return;
		}

		$post     = get_queried_object();
		$is_draft = $post instanceof WP_Post
			&& in_array( $post->post_status, array( 'draft', 'auto-draft', 'pending' ), true );

		echo '<div class="hvnly-ws-preview-badge" role="status">' . esc_html__( 'Preview', 'havenlytics' );
		if ( $is_draft ) {
			echo ' · ' . esc_html__( 'Draft', 'havenlytics' );
		}
		echo '</div>';

		if ( $is_draft ) {
			echo '<div class="hvnly-ws-preview-watermark" aria-hidden="true">' . esc_html__( 'Draft', 'havenlytics' ) . '</div>';
		}

		echo '<style id="hvnly-ws-preview-chrome">';
		echo '.hvnly-ws-preview-badge{position:fixed;top:12px;left:12px;z-index:99999;padding:6px 12px;border-radius:6px;background:#111;color:#fff;font:600 12px/1.2 system-ui,sans-serif;letter-spacing:.02em;pointer-events:none;opacity:.92}';
		echo '.hvnly-ws-preview-watermark{position:fixed;inset:0;z-index:99998;pointer-events:none;display:flex;align-items:center;justify-content:center;font:700 72px/1 system-ui,sans-serif;letter-spacing:.2em;text-transform:uppercase;color:rgba(0,0,0,.06);transform:rotate(-24deg);user-select:none}';
		echo '</style>';
	}

	/**
	 * Allow same-origin iframe embedding for Workspace preview.
	 *
	 * @param array<string, string> $headers Headers.
	 * @return array<string, string>
	 */
	public function filter_wp_headers( $headers ) {
		if ( ! $this->is_workspace_preview_request() ) {
			return $headers;
		}

		if ( isset( $headers['X-Frame-Options'] ) ) {
			unset( $headers['X-Frame-Options'] );
		}

		return $headers;
	}

	/**
	 * Chrome only for authorized editors on Workspace preview requests.
	 *
	 * @return bool
	 */
	private function should_render_chrome(): bool {
		if ( is_admin() || ! $this->is_workspace_preview_request() || ! is_user_logged_in() ) {
			return false;
		}

		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return $this->access->can_edit( (int) $post->ID );
	}
}
