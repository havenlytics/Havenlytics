<?php
/**
 * Query properties assigned to agents within an agency.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Builds property listings for agency single templates.
 *
 * @since 3.0.2
 */
final class AgencyPropertiesQuery {

	/**
	 * @param int                  $term_id Agency term ID.
	 * @param array<string, mixed> $args    Optional query overrides.
	 * @return \WP_Query
	 */
	public static function query( int $term_id, array $args = array() ): \WP_Query {
		$term_id = absint( $term_id );
		$ids     = AgencyArchiveQuery::get_property_ids( $term_id );

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
		 * Filter WP_Query args for agency property listings.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $query_args Query arguments.
		 * @param int                  $term_id    Agency term ID.
		 * @param int[]                $ids        Property IDs.
		 */
		$query_args = apply_filters( 'hvnly_agency_properties_query_args', $query_args, $term_id, $ids );

		return new \WP_Query( $query_args );
	}

	/**
	 * @param int $term_id Agency term ID.
	 * @return int
	 */
	public static function count( int $term_id ): int {
		return AgencyArchiveQuery::count_properties( $term_id );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
