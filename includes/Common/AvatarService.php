<?php
/**
 * Avatar resolution — the single source of truth for every avatar in the plugin.
 *
 * @package HvnlyNab\Common
 * @since   3.2.0
 */

namespace HvnlyNab\Common;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the correct avatar URL for an agent and/or WordPress user.
 *
 * ONE resolution chain, used everywhere — including WordPress core surfaces that
 * call {@see get_avatar()} / {@see get_avatar_url()} (Users list, Edit User,
 * admin bar, comments, author archives):
 *
 *   1. Agent Profile Avatar — the Agent CPT featured image (uploaded photo).
 *   2. Gravatar             — only when the linked WordPress user has a real Gravatar.
 *   3. Havenlytics default  — local shared placeholder illustration (never via Gravatar `d=`).
 *
 * WordPress integration is via {@see pre_get_avatar_data} ({@see register_hooks()}).
 * Gravatar URLs are built directly (never via get_avatar_url) to avoid filter recursion.
 *
 * @since 3.2.0
 */
final class AvatarService {

	/**
	 * Default pixel size when a caller does not specify one.
	 *
	 * @var int
	 */
	public const DEFAULT_SIZE = 96;

	/**
	 * Transient prefix for Gravatar existence probes.
	 *
	 * @var string
	 */
	private const GRAVATAR_CACHE_PREFIX = 'hvnly_has_gravatar_';

	/**
	 * Re-entrancy guard while resolving inside pre_get_avatar_data.
	 *
	 * @var bool
	 */
	private static $resolving_wp_avatar = false;

	/**
	 * Whether WordPress avatar filters are registered.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Wire AvatarService into WordPress core avatar APIs (Users list, admin bar, etc.).
	 *
	 * Safe to call multiple times.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( self::$hooks_registered ) {
			return;
		}
		self::$hooks_registered = true;

		// Canonical short-circuit for get_avatar() / get_avatar_url() / get_avatar_data().
		add_filter( 'pre_get_avatar_data', array( __CLASS__, 'filter_pre_get_avatar_data' ), 10, 2 );
	}

	/**
	 * Short-circuit WordPress avatar resolution with the Havenlytics priority chain.
	 *
	 * When `$args['url']` is set, core skips Gravatar construction and uses our URL
	 * for Users list, Edit User, admin bar, comments, and author archives.
	 *
	 * @param array<string, mixed> $args        Avatar args from get_avatar_data().
	 * @param mixed                $id_or_email User ID, email, WP_User, comment, or post.
	 * @return array<string, mixed>
	 */
	public static function filter_pre_get_avatar_data( $args, $id_or_email ) {
		if ( ! is_array( $args ) ) {
			$args = array();
		}

		// Another plugin already forced a URL — respect it (compatibility).
		if ( ! empty( $args['url'] ) && is_string( $args['url'] ) ) {
			return $args;
		}

		if ( self::$resolving_wp_avatar ) {
			return $args;
		}

		$user_id = self::parse_id_or_email( $id_or_email );
		$size    = isset( $args['size'] ) ? absint( $args['size'] ) : self::DEFAULT_SIZE;
		if ( $size <= 0 ) {
			$size = self::DEFAULT_SIZE;
		}

		self::$resolving_wp_avatar = true;
		try {
			if ( $user_id > 0 ) {
				$url = self::resolve_for_user( $user_id, $size, self::image_size_for_pixels( $size ) );
			} else {
				// Email / unknown identity with no resolvable user — local placeholder.
				$url = self::placeholder_url();
			}
		} finally {
			self::$resolving_wp_avatar = false;
		}

		if ( is_string( $url ) && '' !== $url ) {
			$args['url']          = $url;
			$args['found_avatar'] = true;
		}

		return $args;
	}

	/**
	 * Map WordPress get_avatar id_or_email to a user ID.
	 *
	 * @param mixed $id_or_email Identity.
	 * @return int User ID or 0.
	 */
	public static function parse_id_or_email( $id_or_email ): int {
		if ( is_numeric( $id_or_email ) ) {
			return absint( $id_or_email );
		}

		if ( $id_or_email instanceof \WP_User ) {
			return absint( $id_or_email->ID );
		}

		if ( $id_or_email instanceof \WP_Post ) {
			return absint( $id_or_email->post_author );
		}

		if ( $id_or_email instanceof \WP_Comment ) {
			if ( ! empty( $id_or_email->user_id ) ) {
				return absint( $id_or_email->user_id );
			}
			if ( ! empty( $id_or_email->comment_author_email ) && is_email( $id_or_email->comment_author_email ) ) {
				$user = get_user_by( 'email', $id_or_email->comment_author_email );
				return $user ? absint( $user->ID ) : 0;
			}
			return 0;
		}

		if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
			$user = get_user_by( 'email', $id_or_email );
			return $user ? absint( $user->ID ) : 0;
		}

		if ( is_object( $id_or_email ) && isset( $id_or_email->user_id ) ) {
			return absint( $id_or_email->user_id );
		}

		return 0;
	}

	/**
	 * Choose a registered image size for an Agent featured image given a pixel request.
	 *
	 * @param int $size Pixel size from get_avatar().
	 * @return string
	 */
	private static function image_size_for_pixels( int $size ): string {
		if ( $size <= 96 ) {
			return 'thumbnail';
		}
		if ( $size <= 300 ) {
			return 'medium';
		}
		return 'large';
	}

	/**
	 * Absolute URL to the shared Havenlytics placeholder avatar illustration.
	 *
	 * @return string
	 */
	public static function placeholder_url(): string {
		$url = defined( 'HVNLYNAB_ASSETS_URL' )
			? trailingslashit( HVNLYNAB_ASSETS_URL ) . 'frontend/img/agent-avatar-placeholder.svg'
			: '';

		/**
		 * Filter the shared default avatar placeholder URL.
		 *
		 * @since 3.2.0
		 *
		 * @param string $url Placeholder avatar URL.
		 */
		$filtered = (string) apply_filters( 'hvnly_avatar_placeholder_url', $url );

		return '' !== $filtered ? $filtered : $url;
	}

	/**
	 * Resolve an avatar URL for an agent and/or WordPress user.
	 *
	 * Never returns an empty string: falls through to the Havenlytics
	 * placeholder when nothing else resolves.
	 *
	 * @param int    $agent_id   Agent CPT post ID (0 when resolving a plain user).
	 * @param int    $user_id    Linked WordPress user ID (0 = auto-resolve from the agent).
	 * @param int    $size       Requested pixel size (used for Gravatar).
	 * @param string $image_size Registered image size for the featured image.
	 * @return string Non-empty avatar URL.
	 */
	public static function resolve_url( int $agent_id = 0, int $user_id = 0, int $size = self::DEFAULT_SIZE, string $image_size = 'medium' ): string {
		$size = $size > 0 ? $size : self::DEFAULT_SIZE;

		// 1. Agent Profile Avatar — the Agent CPT featured image (uploaded photo).
		if ( $agent_id > 0 ) {
			$thumb_id = (int) get_post_thumbnail_id( $agent_id );
			if ( $thumb_id > 0 ) {
				$featured = wp_get_attachment_image_url( $thumb_id, $image_size );
				if ( is_string( $featured ) && '' !== $featured ) {
					return self::filter_url( $featured, $agent_id, $user_id );
				}
			}

			// No featured image — fall back to the agent's linked user for Gravatar.
			if ( $user_id <= 0 ) {
				$user_id = self::linked_user_id( $agent_id );
			}
		}

		// 2. Real Gravatar (if any) → 3. Local Havenlytics placeholder.
		return self::filter_url( self::resolve_user_url( $user_id, $size ), $agent_id, $user_id );
	}

	/**
	 * Resolve an avatar for a WordPress user, preferring a linked Agent CPT photo.
	 *
	 * Use this whenever the caller only has a user ID but the person may have an
	 * Agent profile (inquiries, legacy property agents, archive author avatar, /me.user).
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param int    $size       Requested pixel size.
	 * @param string $image_size Featured image size.
	 * @return string Non-empty avatar URL.
	 */
	public static function resolve_for_user( int $user_id, int $size = self::DEFAULT_SIZE, string $image_size = 'medium' ): string {
		$user_id  = absint( $user_id );
		$agent_id = $user_id > 0 ? self::find_agent_id_for_user( $user_id ) : 0;

		return self::resolve_url( $agent_id, $user_id, $size, $image_size );
	}

	/**
	 * Resolve an avatar URL for a plain WordPress user (Gravatar → local placeholder).
	 *
	 * Does NOT look up an Agent CPT. Prefer {@see resolve_for_user()} when the person
	 * may have an Agent profile photo.
	 *
	 * Builds the Gravatar URL directly — never calls get_avatar_url() — so WordPress
	 * `pre_get_avatar_data` filters cannot recurse into this resolver.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $size    Requested pixel size.
	 * @return string Non-empty avatar URL.
	 */
	public static function resolve_user_url( int $user_id, int $size = self::DEFAULT_SIZE ): string {
		$size        = $size > 0 ? $size : self::DEFAULT_SIZE;
		$placeholder = self::placeholder_url();
		$user_id     = absint( $user_id );

		if ( $user_id > 0 && self::user_has_gravatar( $user_id ) ) {
			$gravatar = self::build_gravatar_url( $user_id, $size );
			if ( '' !== $gravatar ) {
				return $gravatar;
			}
		}

		return '' !== $placeholder ? $placeholder : '';
	}

	/**
	 * Build a Gravatar CDN URL without calling get_avatar_url() (avoids filter recursion).
	 *
	 * @param int $user_id User ID.
	 * @param int $size    Pixel size.
	 * @return string URL or empty string.
	 */
	private static function build_gravatar_url( int $user_id, int $size ): string {
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return '';
		}

		$email = strtolower( trim( (string) $user->user_email ) );
		$hash  = md5( $email ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_md5 -- Gravatar identity hash.
		$size  = max( 1, min( 2048, $size ) );

		// `d=mp` is a Gravatar CDN default (never a local SVG).
		return sprintf(
			'https://secure.gravatar.com/avatar/%1$s?s=%2$d&d=mp&r=g',
			rawurlencode( $hash ),
			$size
		);
	}

	/**
	 * Whether a WordPress user has a custom Gravatar image (cached).
	 *
	 * Uses Gravatar's `d=404` probe. On transport failure we treat as "no Gravatar"
	 * so callers fall back to the local placeholder instead of a broken image.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_has_gravatar( int $user_id ): bool {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		$email = strtolower( trim( (string) $user->user_email ) );
		$key   = self::GRAVATAR_CACHE_PREFIX . md5( $email );
		$cached = get_transient( $key );

		if ( '1' === $cached ) {
			return true;
		}
		if ( '0' === $cached ) {
			return false;
		}

		$hash     = md5( $email );
		$probe    = 'https://www.gravatar.com/avatar/' . $hash . '?d=404&s=64';
		$response = wp_remote_head(
			$probe,
			array(
				'timeout'     => 2,
				'redirection' => 0,
				/**
				 * Avoid SSL verification failures blocking Local / misconfigured hosts
				 * from falling back to the placeholder.
				 */
				'sslverify'   => true,
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$has  = ( 200 === $code );

		/**
		 * Filter whether a user is considered to have a Gravatar.
		 *
		 * @since 3.3.0
		 *
		 * @param bool $has     Whether Gravatar responded 200.
		 * @param int  $user_id User ID.
		 * @param int  $code    HTTP status from the probe (0 on error).
		 */
		$has = (bool) apply_filters( 'hvnly_user_has_gravatar', $has, $user_id, $code );

		set_transient( $key, $has ? '1' : '0', DAY_IN_SECONDS );

		return $has;
	}

	/**
	 * Drop the cached Gravatar probe for a user (profile/email changes).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function clear_gravatar_cache( int $user_id ): void {
		$user = get_userdata( absint( $user_id ) );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}
		delete_transient( self::GRAVATAR_CACHE_PREFIX . md5( strtolower( trim( (string) $user->user_email ) ) ) );
	}

	/**
	 * Resolve the WordPress user linked to an Agent CPT post.
	 *
	 * @param int $agent_id Agent CPT post ID.
	 * @return int User ID, or 0 when unlinked.
	 */
	public static function linked_user_id( int $agent_id ): int {
		if ( $agent_id <= 0 ) {
			return 0;
		}

		return absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
	}

	/**
	 * Find the primary Agent CPT linked to a WordPress user.
	 *
	 * @param int $user_id User ID.
	 * @return int Agent post ID, or 0.
	 */
	public static function find_agent_id_for_user( int $user_id ): int {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! post_type_exists( AgentConstants::POST_TYPE ) ) {
			return 0;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => AgentConstants::META_LINKED_USER_ID,
						'value' => $user_id,
					),
				),
			)
		);

		$id = ! empty( $query->posts[0] ) ? absint( $query->posts[0] ) : 0;
		wp_reset_postdata();

		return $id;
	}

	/**
	 * Render a resilient avatar `<img>` that falls back to the local placeholder on error.
	 *
	 * @param string               $url     Resolved avatar URL (may be Gravatar).
	 * @param array<string, mixed> $atts    Optional attributes (class, width, height, alt, loading).
	 * @return string Safe HTML.
	 */
	public static function img_html( string $url, array $atts = array() ): string {
		$placeholder = self::placeholder_url();
		$url         = is_string( $url ) && '' !== $url ? $url : $placeholder;
		if ( '' === $url ) {
			return '';
		}

		$class   = isset( $atts['class'] ) ? (string) $atts['class'] : 'hvnly-avatar';
		$width   = isset( $atts['width'] ) ? absint( $atts['width'] ) : 0;
		$height  = isset( $atts['height'] ) ? absint( $atts['height'] ) : 0;
		$alt     = isset( $atts['alt'] ) ? (string) $atts['alt'] : '';
		$loading = isset( $atts['loading'] ) ? sanitize_key( (string) $atts['loading'] ) : 'lazy';

		$onerror = '';
		if ( '' !== $placeholder && $url !== $placeholder ) {
			$onerror = sprintf(
				' onerror="this.onerror=null;this.src=%s;"',
				esc_attr( wp_json_encode( $placeholder ) )
			);
		}

		$html = sprintf(
			'<img class="%1$s" src="%2$s" alt="%3$s"%4$s%5$s%6$s%7$s />',
			esc_attr( $class ),
			esc_url( $url ),
			esc_attr( $alt ),
			$width > 0 ? ' width="' . $width . '"' : '',
			$height > 0 ? ' height="' . $height . '"' : '',
			$loading ? ' loading="' . esc_attr( $loading ) . '"' : '',
			$onerror
		);

		return $html;
	}

	/**
	 * Apply the shared resolution filter so integrations can override any avatar.
	 *
	 * @param string $url      Resolved URL.
	 * @param int    $agent_id Agent CPT post ID.
	 * @param int    $user_id  WordPress user ID.
	 * @return string
	 */
	private static function filter_url( string $url, int $agent_id, int $user_id ): string {
		if ( '' === $url ) {
			$url = self::placeholder_url();
		}

		/**
		 * Filter a resolved avatar URL.
		 *
		 * @since 3.2.0
		 *
		 * @param string $url      Resolved avatar URL.
		 * @param int    $agent_id Agent CPT post ID.
		 * @param int    $user_id  WordPress user ID.
		 */
		$filtered = (string) apply_filters( 'hvnly_resolve_avatar_url', $url, $agent_id, $user_id );

		return '' !== $filtered ? $filtered : self::placeholder_url();
	}
}
