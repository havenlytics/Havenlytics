<?php
/**
 * Restrict AJAX/archive property queries to agent- or agency-assigned listings.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.4
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Applies post__in scoping when agent_id or agency_term_id is present in query data.
 *
 * @since 3.0.4
 */
final class AssignedListingQueryScope {

	/**
	 * Register query filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'hvnly_property_query_args', array( __CLASS__, 'filter_query_args' ), 20, 2 );
	}

	/**
	 * Limit property queries to listings assigned to an agent or agency.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @param array<string, mixed> $data Filter payload.
	 * @return array<string, mixed>
	 */
	public static function filter_query_args( array $args, array $data ): array {
		$agent_id       = ! empty( $data['agent_id'] ) ? absint( $data['agent_id'] ) : 0;
		$agency_term_id = ! empty( $data['agency_term_id'] ) ? absint( $data['agency_term_id'] ) : 0;

		if ( $agent_id <= 0 && $agency_term_id <= 0 ) {
			return $args;
		}

		if ( $agent_id > 0 ) {
			if ( function_exists( 'hvnly_is_valid_agent' ) && ! hvnly_is_valid_agent( $agent_id ) ) {
				$args['post__in'] = array( 0 );
				return $args;
			}

			$ids = AgentPropertiesQuery::get_assigned_property_ids( $agent_id );
		} else {
			$ids = AgencyArchiveQuery::get_property_ids( $agency_term_id );
		}

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			$args['post__in'] = array( 0 );
			return $args;
		}

		$args['post__in'] = $ids;

		if ( empty( $data['orderby'] ) ) {
			$args['orderby'] = 'post__in';
			unset( $args['order'], $args['meta_key'] );
		}

		return $args;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
