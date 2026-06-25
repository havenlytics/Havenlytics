<?php
/**
 * Agent Listings Carousel sidebar widget renderer.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.5
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves agent listings and renders the sidebar carousel widget.
 *
 * @since 3.0.5
 */
final class AgentListingsCarouselWidgetRenderer {

	/**
	 * Default widget instance values.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'agent_id'      => 0,
			'title'         => '',
			'number'        => 6,
			'orderby'       => 'assigned',
			'show_price'    => '1',
			'show_location' => '1',
			'show_status'   => '1',
			'autoplay'      => '0',
			'show_nav'      => '1',
		);
	}

	/**
	 * Published agents for the widget settings dropdown.
	 *
	 * @return array<int, string> Agent post ID => display name.
	 */
	public static function get_agent_choices(): array {
		$choices = array(
			0 => __( 'Auto: Current Property Agent', 'havenlytics' ),
		);

		$agents = function_exists( 'hvnly_get_agents' )
			? hvnly_get_agents(
				array(
					'posts_per_page' => -1,
					'nopaging'       => true,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			)
			: array();

		foreach ( $agents as $agent ) {
			$agent_id = absint( $agent['id'] ?? 0 );
			$name     = trim( (string) ( $agent['name'] ?? '' ) );

			if ( $agent_id <= 0 || '' === $name ) {
				continue;
			}

			$choices[ $agent_id ] = $name;
		}

		return $choices;
	}

	/**
	 * Resolve Agent CPT post ID from a profile array.
	 *
	 * @param array<string, mixed> $agent Agent profile.
	 * @return int
	 */
	public static function resolve_agent_cpt_id( array $agent ): int {
		if ( empty( $agent ) ) {
			return 0;
		}

		$type     = (string) ( $agent['type'] ?? $agent['source'] ?? '' );
		$agent_id = absint( $agent['id'] ?? 0 );

		if ( 'cpt' === $type && $agent_id > 0 && function_exists( 'hvnly_is_valid_agent' ) && hvnly_is_valid_agent( $agent_id ) ) {
			return $agent_id;
		}

		$user_id = absint( $agent['user_id'] ?? 0 );
		if ( $user_id <= 0 && 'user' === $type ) {
			$user_id = $agent_id;
		}

		if ( $user_id > 0 ) {
			$cpt_agents = PropertyAgentResolver::get_cpt_agents_for_user( $user_id );
			if ( ! empty( $cpt_agents[0]['id'] ) ) {
				return absint( $cpt_agents[0]['id'] );
			}
		}

		return 0;
	}

	/**
	 * Resolve which agent CPT ID to use for this widget instance.
	 *
	 * @param int                  $property_id Property ID.
	 * @param array<string, mixed> $instance    Widget instance.
	 * @return int
	 */
	public static function resolve_agent_id( int $property_id, array $instance ): int {
		$selected = absint( $instance['agent_id'] ?? 0 );

		if ( $selected > 0 ) {
			return $selected;
		}

		$agent = function_exists( 'hvnly_get_primary_property_agent' )
			? hvnly_get_primary_property_agent( $property_id )
			: PropertyAgentResolver::get_primary( $property_id );

		return self::resolve_agent_cpt_id( is_array( $agent ) ? $agent : array() );
	}

	/**
	 * Build WP_Query args for agent listings.
	 *
	 * @param int                  $agent_id    Agent CPT post ID.
	 * @param int                  $property_id Current property ID to optionally exclude.
	 * @param array<string, mixed> $instance    Widget instance.
	 * @return array<string, mixed>
	 */
	public static function build_query_args( int $agent_id, int $property_id, array $instance ): array {
		$number  = max( 1, absint( $instance['number'] ?? 6 ) );
		$orderby = sanitize_key( (string) ( $instance['orderby'] ?? 'assigned' ) );

		$args = array(
			'posts_per_page' => $number,
			'no_found_rows'  => true,
		);

		if ( $property_id > 0 ) {
			$args['post__not_in'] = array( $property_id );
		}

		switch ( $orderby ) {
			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'title':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'rand':
				$args['orderby'] = 'rand';
				break;
			case 'price':
				$args['meta_key'] = '_hvnly_property_price';
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				break;
			default:
				$args['orderby'] = 'post__in';
				break;
		}

		return $args;
	}

	/**
	 * Count listings for the selected agent.
	 *
	 * @param int $agent_id            Agent CPT post ID.
	 * @param int $exclude_property_id Optional property ID to exclude.
	 * @return int
	 */
	public static function count_listings( int $agent_id, int $exclude_property_id = 0 ): int {
		$assigned_ids = AgentPropertiesQuery::get_assigned_property_ids( $agent_id );
		if ( empty( $assigned_ids ) ) {
			return 0;
		}

		if ( $exclude_property_id > 0 ) {
			$assigned_ids = array_diff( $assigned_ids, array( $exclude_property_id ) );
		}

		return count( $assigned_ids );
	}

	/**
	 * Resolve display title for the widget.
	 *
	 * @param array<string, mixed> $instance Widget instance.
	 * @param array<string, mixed> $agent    Selected agent profile.
	 * @return string
	 */
	public static function resolve_title( array $instance, array $agent ): string {
		$override = trim( (string) ( $instance['title'] ?? '' ) );
		if ( '' !== $override ) {
			return $override;
		}

		$agent_name = trim( (string) ( $agent['name'] ?? '' ) );
		if ( '' === $agent_name ) {
			return __( 'More Listings From Agent', 'havenlytics' );
		}

		/* translators: %s: agent display name */
		return sprintf( __( 'More Listings From %s', 'havenlytics' ), $agent_name );
	}

	/**
	 * Render sidebar agent listings carousel.
	 *
	 * @param int                  $property_id Property ID.
	 * @param array<string, mixed> $instance    Widget instance.
	 * @param string               $widget_id   Unique widget DOM id.
	 * @return bool True when output was rendered.
	 */
	public static function render( int $property_id, array $instance, string $widget_id ): bool {
		$agent_id = self::resolve_agent_id( $property_id, $instance );
		if ( $agent_id <= 0 ) {
			return false;
		}

		if ( function_exists( 'hvnly_is_valid_agent' ) && ! hvnly_is_valid_agent( $agent_id ) ) {
			return false;
		}

		$agent = function_exists( 'hvnly_get_agent' )
			? hvnly_get_agent( $agent_id )
			: null;

		if ( ! is_array( $agent ) || empty( $agent ) ) {
			return false;
		}

		$query_args = self::build_query_args( $agent_id, $property_id, $instance );

		$properties_query = function_exists( 'hvnly_get_agent_properties_query' )
			? hvnly_get_agent_properties_query( $agent_id, $query_args )
			: AgentPropertiesQuery::query( $agent_id, $query_args );

		if ( ! $properties_query instanceof \WP_Query || ! $properties_query->have_posts() ) {
			return false;
		}

		$available_count = self::count_listings( $agent_id, $property_id );
		$carousel_uid    = sanitize_html_class( $widget_id );
		$title           = self::resolve_title( $instance, $agent );

		$show_price    = isset( $instance['show_price'] ) && '1' === $instance['show_price'];
		$show_location = isset( $instance['show_location'] ) && '1' === $instance['show_location'];
		$show_status   = isset( $instance['show_status'] ) && '1' === $instance['show_status'];
		$autoplay      = isset( $instance['autoplay'] ) && '1' === $instance['autoplay'];
		$show_nav      = ! isset( $instance['show_nav'] ) || '1' === $instance['show_nav'];

		if ( function_exists( 'hvnly_get_template_part' ) ) {
			hvnly_get_template_part(
				'widgets/agent-listings-carousel',
				null,
				array(
					'query'           => $properties_query,
					'title'           => $title,
					'available_count' => max( $available_count, (int) $properties_query->post_count ),
					'carousel_uid'    => $carousel_uid,
					'show_price'      => $show_price,
					'show_location'   => $show_location,
					'show_status'     => $show_status,
					'autoplay'        => $autoplay,
					'show_nav'        => $show_nav,
				)
			);
		}

		wp_reset_postdata();

		return true;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
