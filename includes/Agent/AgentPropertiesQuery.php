<?php
/**
 * Query properties assigned to an Agent CPT profile.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Finds published properties linked to an agent via metabox or builder assignments.
 *
 * @since 3.0.2
 */
final class AgentPropertiesQuery {

	/**
	 * Property post IDs assigned to an agent (unique, newest first).
	 *
	 * @param int      $agent_id      Agent post ID.
	 * @param string[] $post_statuses Post statuses to include. Defaults to published only.
	 * @return int[]
	 */
	public static function get_assigned_property_ids( int $agent_id, array $post_statuses = array( 'publish' ) ): array {
		global $wpdb;

		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 ) {
			return array();
		}

		$post_statuses = array_values( array_filter( array_map( 'sanitize_key', $post_statuses ) ) );
		if ( empty( $post_statuses ) ) {
			$post_statuses = array( 'publish' );
		}

		$serialized_needle = '%i:' . $agent_id . ';%';
		$json_needles      = array(
			'%[' . $agent_id . ']%',
			'%[' . $agent_id . ',%',
			'%,' . $agent_id . ']%',
			'%,' . $agent_id . ',%',
		);

		$meta_clauses = array(
			$wpdb->prepare(
				'(pm.meta_key = %s AND pm.meta_value LIKE %s)',
				AgentConstants::META_PROPERTY_AGENTS,
				$serialized_needle
			),
		);

		foreach ( $json_needles as $json_needle ) {
			$meta_clauses[] = $wpdb->prepare(
				'(pm.meta_key LIKE %s AND pm.meta_value LIKE %s)',
				'%\_agents',
				$json_needle
			);
		}

		if ( 1 === count( $post_statuses ) ) {
			$status_sql = $wpdb->prepare( 'p.post_status = %s', $post_statuses[0] );
		} else {
			$status_placeholders = implode( ',', array_fill( 0, count( $post_statuses ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders built from count of sanitized statuses.
			$status_sql = $wpdb->prepare( "p.post_status IN ({$status_placeholders})", ...$post_statuses );
		}

		$prepared_query = $wpdb->prepare(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
				AND {$status_sql}
				AND (" . implode( ' OR ', $meta_clauses ) . ')
			ORDER BY p.post_date DESC',
			AgentConstants::PROPERTY_POST_TYPE
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Core tables; OR clauses are individually prepared above.
		$ids = $wpdb->get_col( $prepared_query );

		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return array();
		}

		$ids = array_map( 'absint', $ids );
		$ids = array_values( array_filter( array_unique( $ids ) ) );

		/**
		 * Filter property IDs assigned to an agent profile.
		 *
		 * @since 3.0.2
		 *
		 * @param int[] $ids      Property post IDs.
		 * @param int   $agent_id Agent post ID.
		 */
		return apply_filters( 'hvnly_agent_assigned_property_ids', $ids, $agent_id );
	}

	/**
	 * WP_Query for properties assigned to an agent.
	 *
	 * @param int                  $agent_id Agent post ID.
	 * @param array<string, mixed> $args     Optional query overrides.
	 * @return \WP_Query
	 */
	public static function query( int $agent_id, array $args = array() ): \WP_Query {
		$agent_id = absint( $agent_id );
		$ids      = self::get_assigned_property_ids( $agent_id );

		$defaults = array(
			'post_type'      => AgentConstants::PROPERTY_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => function_exists( 'hvnly_get_properties_per_page' ) ? hvnly_get_properties_per_page() : 12,
			'paged'          => max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ),
			'post__in'       => ! empty( $ids ) ? $ids : array( 0 ),
			'orderby'        => 'post__in',
		);

		$query_args = wp_parse_args( $args, $defaults );

		/**
		 * Filter WP_Query args for agent property listings.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $query_args Query arguments.
		 * @param int                  $agent_id   Agent post ID.
		 * @param int[]                $ids        Assigned property IDs.
		 */
		$query_args = apply_filters( 'hvnly_agent_properties_query_args', $query_args, $agent_id, $ids );

		return new \WP_Query( $query_args );
	}

	/**
	 * Count published properties assigned to an agent.
	 *
	 * @param int $agent_id Agent post ID.
	 * @return int
	 */
	public static function count( int $agent_id ): int {
		return count( self::get_assigned_property_ids( $agent_id ) );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
