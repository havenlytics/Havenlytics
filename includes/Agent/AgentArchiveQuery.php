<?php
/**
 * Agent archive listing query helpers.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Configures and augments the main agent archive query.
 *
 * @since 3.0.2
 */
final class AgentArchiveQuery {

	public const DEFAULT_PER_PAGE = 12;

	/**
	 * Register main-query adjustments.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
	}

	/**
	 * @param \WP_Query $query Query instance.
	 * @return void
	 */
	public static function pre_get_posts( $query ): void {
		if ( is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $query->is_post_type_archive( AgentConstants::POST_TYPE ) ) {
			return;
		}

		$query->set( 'posts_per_page', self::get_per_page() );
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $search ) {
			$query->set( 's', $search );
		}
	}

	/**
	 * @return int
	 */
	public static function get_per_page(): int {
		/**
		 * Filter agents shown per archive page.
		 *
		 * @since 3.0.2
		 *
		 * @param int $per_page Posts per page.
		 */
		return max( 1, (int) apply_filters( 'hvnly_agent_archive_per_page', self::DEFAULT_PER_PAGE ) );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
