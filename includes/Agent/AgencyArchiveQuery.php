<?php
/**
 * Agency archive query helpers.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Lists agencies with aggregate stats for archive and single templates.
 *
 * @since 3.0.2
 */
final class AgencyArchiveQuery {

	public const DEFAULT_PER_PAGE = 12;

	/** @var array<int, int[]> */
	private static $agency_property_ids_cache = array();

	/** @var array<int, int> */
	private static $agency_agent_count_cache = array();

	/**
	 * Paginated agency archive result.
	 *
	 * @param array<string, mixed> $args Query overrides.
	 * @return array{items: array<int, array<string, mixed>>, total: int, max_pages: int, current_page: int, per_page: int}
	 */
	public static function query_agencies( array $args = array() ): array {
		$per_page = isset( $args['per_page'] ) ? max( 1, absint( $args['per_page'] ) ) : self::get_per_page();
		$paged    = isset( $args['paged'] ) ? max( 1, absint( $args['paged'] ) ) : max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$search   = isset( $args['search'] ) ? sanitize_text_field( (string) $args['search'] ) : '';

		if ( '' === $search && isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$search = sanitize_text_field( wp_unslash( (string) $_GET['s'] ) );
		}

		$orderby = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'name';
		$order   = isset( $args['order'] ) ? strtoupper( sanitize_key( (string) $args['order'] ) ) : 'ASC';

		if ( 'date' === $orderby ) {
			$orderby = 'term_id';
		}

		$allowed_orderby = array( 'name', 'term_id', 'count', 'slug' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'name';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'ASC';
		}

		$term_args = array(
			'taxonomy'   => AgentConstants::TAXONOMY_AGENCY,
			'hide_empty' => false,
			'orderby'    => $orderby,
			'order'      => $order,
		);

		if ( '' !== $search ) {
			$term_args['search'] = $search;
		}

		$all_terms = get_terms( $term_args );
		if ( is_wp_error( $all_terms ) || ! is_array( $all_terms ) ) {
			$all_terms = array();
		}

		$total     = count( $all_terms );
		$max_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
		$offset    = ( $paged - 1 ) * $per_page;
		$slice     = array_slice( $all_terms, $offset, $per_page );

		$items = array();
		foreach ( $slice as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$items[] = self::get_archive_profile( (int) $term->term_id );
		}

		return array(
			'items'        => $items,
			'total'        => $total,
			'max_pages'    => max( 1, $max_pages ),
			'current_page' => $paged,
			'per_page'     => $per_page,
		);
	}

	/**
	 * @return int
	 */
	public static function get_per_page(): int {
		/**
		 * Filter agencies shown per archive page.
		 *
		 * @since 3.0.2
		 *
		 * @param int $per_page Terms per page.
		 */
		return max( 1, (int) apply_filters( 'hvnly_agency_archive_per_page', self::DEFAULT_PER_PAGE ) );
	}

	/**
	 * @param int $term_id Agency term ID.
	 * @return array<string, mixed>
	 */
	public static function get_archive_profile( int $term_id ): array {
		$term_id = absint( $term_id );
		if ( $term_id <= 0 ) {
			return array();
		}

		$profile = AgencyFields::get_profile( $term_id );
		if ( empty( $profile ) ) {
			return array();
		}

		$link = get_term_link( $term_id, AgentConstants::TAXONOMY_AGENCY );
		$profile['profile_url']    = is_wp_error( $link ) ? '' : (string) $link;
		$profile['agent_count']    = self::count_agents( $term_id );
		$profile['property_count'] = self::count_properties( $term_id );
		$profile['location']       = self::format_location( $profile );

		/**
		 * Filter enriched agency archive profile data.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $profile Agency profile.
		 * @param int                  $term_id Term ID.
		 */
		return apply_filters( 'hvnly_agency_archive_profile', $profile, $term_id );
	}

	/**
	 * @param int $term_id Agency term ID.
	 * @return int[]
	 */
	public static function get_agent_ids( int $term_id ): array {
		$term_id = absint( $term_id );
		if ( $term_id <= 0 ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => AgentConstants::TAXONOMY_AGENCY,
						'field'    => 'term_id',
						'terms'    => array( $term_id ),
					),
				),
			)
		);

		$ids = is_array( $query->posts ) ? array_map( 'absint', $query->posts ) : array();

		wp_reset_postdata();

		return array_values( array_filter( $ids ) );
	}

	/**
	 * @param int $term_id Agency term ID.
	 * @return int
	 */
	public static function count_agents( int $term_id ): int {
		$term_id = absint( $term_id );
		if ( $term_id <= 0 ) {
			return 0;
		}

		if ( isset( self::$agency_agent_count_cache[ $term_id ] ) ) {
			return self::$agency_agent_count_cache[ $term_id ];
		}

		$query = new \WP_Query(
			array(
				'post_type'      => AgentConstants::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => AgentConstants::TAXONOMY_AGENCY,
						'field'    => 'term_id',
						'terms'    => array( $term_id ),
					),
				),
			)
		);

		$count = (int) $query->found_posts;
		wp_reset_postdata();

		self::$agency_agent_count_cache[ $term_id ] = $count;

		return $count;
	}

	/**
	 * @param int $term_id Agency term ID.
	 * @return int[]
	 */
	public static function get_property_ids( int $term_id ): array {
		$term_id = absint( $term_id );
		if ( $term_id <= 0 ) {
			return array();
		}

		if ( isset( self::$agency_property_ids_cache[ $term_id ] ) ) {
			return self::$agency_property_ids_cache[ $term_id ];
		}

		$property_ids = array();
		foreach ( self::get_agent_ids( $term_id ) as $agent_id ) {
			$property_ids = array_merge( $property_ids, AgentPropertiesQuery::get_assigned_property_ids( $agent_id ) );
		}

		$property_ids = array_values( array_unique( array_filter( array_map( 'absint', $property_ids ) ) ) );

		self::$agency_property_ids_cache[ $term_id ] = $property_ids;

		return $property_ids;
	}

	/**
	 * @param int $term_id Agency term ID.
	 * @return int
	 */
	public static function count_properties( int $term_id ): int {
		return count( self::get_property_ids( $term_id ) );
	}

	/**
	 * @param array<string, mixed> $profile Agency profile.
	 * @return string
	 */
	private static function format_location( array $profile ): string {
		$address = isset( $profile['address'] ) ? trim( (string) $profile['address'] ) : '';
		if ( '' === $address ) {
			return '';
		}

		$parts = preg_split( '/[\r\n,]+/', $address );
		if ( ! is_array( $parts ) || empty( $parts ) ) {
			return $address;
		}

		$last = trim( (string) end( $parts ) );

		return '' !== $last ? $last : $address;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
