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
	 * Build a standalone, parameterized WP_Query for the agent archive.
	 *
	 * Shared builder for nested contexts (blocks, widgets, shortcodes) that
	 * cannot rely on the main-query adjustments in pre_get_posts(). Existing
	 * archive behaviour is unchanged — this only adds an explicit builder.
	 *
	 * @since 3.5.0
	 *
	 * @param array<string, mixed> $args {
	 *     Optional query overrides.
	 *
	 *     @type int    $per_page Posts per page.
	 *     @type int    $paged    Page number.
	 *     @type string $orderby  title|date.
	 *     @type string $order    ASC|DESC.
	 *     @type string $search   Free-text search (falls back to ?s).
	 * }
	 * @return \WP_Query
	 */
	public static function build_query( array $args = array() ): \WP_Query {
		$per_page = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : self::get_per_page();
		$paged    = isset( $args['paged'] )
			? max( 1, absint( $args['paged'] ) )
			: max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

		$orderby = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'title';
		$order   = isset( $args['order'] ) ? strtoupper( sanitize_key( (string) $args['order'] ) ) : 'ASC';

		if ( ! in_array( $orderby, array( 'title', 'date' ), true ) ) {
			$orderby = 'title';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'ASC';
		}

		$search = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';

		if ( '' === $search && isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search = sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$query_args = array(
			'post_type'              => AgentConstants::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $paged,
			'orderby'                => $orderby,
			'order'                  => $order,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		/**
		 * Filter the standalone agent archive query args.
		 *
		 * @since 3.5.0
		 *
		 * @param array<string, mixed> $query_args Query arguments.
		 * @param array<string, mixed> $args       Caller overrides.
		 */
		$query_args = apply_filters( 'hvnly_agent_archive_query_args', $query_args, $args );

		return new \WP_Query( $query_args );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
