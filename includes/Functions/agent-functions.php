<?php
/**
 * Agent helper functions.
 *
 * @package Havenlytics
 * @since   3.0.2
 */

defined( 'ABSPATH' ) || exit;

use HvnlyNab\Agent\AgentBootstrap;
use HvnlyNab\Agent\AgencyFields;
use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\AgentFields;
use HvnlyNab\Agent\Contracts\AgentRepositoryInterface;
use HvnlyNab\Agent\AgentPropertiesQuery;
use HvnlyNab\Agent\AgencyArchiveQuery;
use HvnlyNab\Agent\AgencyPropertiesQuery;
use HvnlyNab\Agent\PropertyAgentResolver;

/**
 * Agent CPT slug.
 *
 * @since 3.0.2
 *
 * @return string
 */
function hvnly_get_agent_post_type(): string {
	return AgentConstants::POST_TYPE;
}

/**
 * Whether a post is a Havenlytics agent profile.
 *
 * @since 3.0.2
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function hvnly_is_agent_post( int $post_id = 0 ): bool {
	$post_id = $post_id > 0 ? $post_id : (int) get_the_ID();

	return $post_id > 0 && AgentConstants::POST_TYPE === get_post_type( $post_id );
}

/**
 * Retrieve the Agent repository.
 *
 * @since 3.0.2
 *
 * @return AgentRepositoryInterface|null
 */
function hvnly_agent_repository(): ?AgentRepositoryInterface {
	if ( ! class_exists( AgentBootstrap::class ) ) {
		return null;
	}

	return AgentBootstrap::get_instance()->get_repository();
}

/**
 * Get a normalized agent profile from the Agent CPT.
 *
 * @since 3.0.2
 *
 * @param int $agent_id Agent post ID.
 * @return array<string, mixed> Empty array when not found.
 */
function hvnly_get_agent( int $agent_id = 0 ): array {
	$agent_id = absint( $agent_id );
	if ( $agent_id <= 0 ) {
		return array();
	}

	$repository = hvnly_agent_repository();
	if ( ! $repository ) {
		return array();
	}

	$profile = $repository->get( $agent_id );

	return is_array( $profile ) ? $profile : array();
}

/**
 * Whether an agent post exists and is published.
 *
 * @since 3.0.2
 *
 * @param int $agent_id Agent post ID.
 * @return bool
 */
function hvnly_is_valid_agent( int $agent_id ): bool {
	$repository = hvnly_agent_repository();

	if ( ! $repository ) {
		return false;
	}

	return $repository->is_valid_agent( absint( $agent_id ) );
}

/**
 * List published agent profiles.
 *
 * @since 3.0.2
 *
 * @param array<string, mixed> $args Optional query args.
 * @return array<int, array<string, mixed>>
 */
function hvnly_get_agents( array $args = array() ): array {
	$repository = hvnly_agent_repository();

	if ( ! $repository ) {
		return array();
	}

	return $repository->list_agents( $args );
}

/**
 * Assigned Agent CPT post IDs for a property (ordered).
 *
 * @since 3.0.2
 *
 * @param int $property_id Property post ID.
 * @return int[]
 */
function hvnly_get_property_agent_ids( int $property_id = 0 ): array {
	$property_id = $property_id > 0 ? $property_id : (int) get_the_ID();
	if ( $property_id <= 0 || ! class_exists( PropertyAgentResolver::class ) ) {
		return array();
	}

	return PropertyAgentResolver::get_assigned_agent_ids( $property_id );
}

/**
 * All resolved agents for a property (CPT assignments or legacy user fallback).
 *
 * @since 3.0.2
 *
 * @param int $property_id Property post ID.
 * @return array<int, array<string, mixed>>
 */
function hvnly_get_property_agents( int $property_id = 0 ): array {
	$property_id = $property_id > 0 ? $property_id : (int) get_the_ID();
	if ( $property_id <= 0 || ! class_exists( PropertyAgentResolver::class ) ) {
		return array();
	}

	return PropertyAgentResolver::get_agents( $property_id );
}

/**
 * Primary agent for a property.
 *
 * @since 3.0.2
 *
 * @param int $property_id Property post ID.
 * @return array<string, mixed>
 */
function hvnly_get_primary_property_agent( int $property_id = 0 ): array {
	$property_id = $property_id > 0 ? $property_id : (int) get_the_ID();
	if ( $property_id <= 0 || ! class_exists( PropertyAgentResolver::class ) ) {
		return array();
	}

	return PropertyAgentResolver::get_primary( $property_id );
}

/**
 * Agent CPT profiles explicitly assigned to a property (sidebar widget source).
 *
 * @since 3.0.2
 *
 * @param int $property_id Property post ID.
 * @return array<int, array<string, mixed>>
 */
function hvnly_get_sidebar_property_agents( int $property_id = 0 ): array {
	$property_id = $property_id > 0 ? $property_id : (int) get_the_ID();
	if ( $property_id <= 0 || ! class_exists( PropertyAgentResolver::class ) ) {
		return array();
	}

	$agent_ids = hvnly_get_property_agent_ids( $property_id );
	if ( empty( $agent_ids ) ) {
		return array();
	}

	$agents = array();
	foreach ( $agent_ids as $agent_id ) {
		$profile = hvnly_get_agent( (int) $agent_id );
		if ( ! empty( $profile ) && ! empty( $profile['name'] ) ) {
			$agents[] = $profile;
		}
	}

	/**
	 * Filter sidebar widget agents resolved from property assignments.
	 *
	 * @since 3.0.2
	 *
	 * @param array<int, array<string, mixed>> $agents      Agent profiles.
	 * @param int                               $property_id Property ID.
	 */
	return apply_filters( 'hvnly_sidebar_property_agents', $agents, $property_id );
}

/**
 * Default sidebar contact when a property has no assigned agents.
 *
 * @since 3.0.2
 *
 * @return array<string, mixed>
 */
function hvnly_get_default_sidebar_contact(): array {
	$user = get_user_by( 'email', (string) get_option( 'admin_email' ) );

	if ( ! $user instanceof \WP_User ) {
		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID', 'display_name', 'user_email' ),
			)
		);

		if ( ! empty( $admins ) && $admins[0] instanceof \WP_User ) {
			$user = $admins[0];
		}
	}

	if ( $user instanceof \WP_User ) {
		$contact = array(
			'id'           => (int) $user->ID,
			'type'         => 'site_admin',
			'source'       => 'site_admin',
			'user_id'      => (int) $user->ID,
			'name'         => $user->display_name ? $user->display_name : __( 'Site Administrator', 'havenlytics' ),
			'email'        => (string) $user->user_email,
			'phone'        => '',
			'whatsapp'     => '',
			'position'     => __( 'Site Administrator', 'havenlytics' ),
			'avatar'       => get_avatar_url( $user->ID, array( 'size' => 200 ) ),
			'is_fallback'  => true,
		);
	} else {
		$contact = array(
			'id'           => 0,
			'type'         => 'site_admin',
			'source'       => 'site_admin',
			'user_id'      => 0,
			'name'         => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'email'        => (string) get_option( 'admin_email' ),
			'phone'        => '',
			'whatsapp'     => '',
			'position'     => __( 'Site Administrator', 'havenlytics' ),
			'avatar'       => '',
			'is_fallback'  => true,
		);
	}

	/**
	 * Filter default sidebar contact details when no property agent is assigned.
	 *
	 * @since 3.0.2
	 *
	 * @param array<string, mixed> $contact Default contact profile.
	 */
	return apply_filters( 'hvnly_default_sidebar_contact', $contact );
}

/**
 * Primary agency term assigned to an agent.
 *
 * @since 3.0.2
 *
 * @param int $agent_id Agent post ID.
 * @return \WP_Term|null
 */
function hvnly_get_agent_agency_term( int $agent_id = 0 ) {
	$agent_id = absint( $agent_id );
	if ( $agent_id <= 0 ) {
		return null;
	}

	$terms = get_the_terms( $agent_id, AgentConstants::TAXONOMY_AGENCY );
	if ( ! is_array( $terms ) || empty( $terms ) ) {
		return null;
	}

	$term = $terms[0];
	return $term instanceof \WP_Term ? $term : null;
}

/**
 * Normalized agency data for an agent profile.
 *
 * @since 3.0.2
 *
 * @param int $agent_id Agent post ID.
 * @return array<string, mixed>
 */
function hvnly_get_agent_agency( int $agent_id = 0 ): array {
	$agent_id = absint( $agent_id );
	if ( $agent_id <= 0 ) {
		$agent_id = (int) get_the_ID();
	}

	if ( $agent_id <= 0 || ! class_exists( AgencyFields::class ) ) {
		return array();
	}

	return AgencyFields::resolve_for_agent( $agent_id );
}

/**
 * Normalized agency profile by term ID.
 *
 * @since 3.0.2
 *
 * @param int $term_id Agency term ID.
 * @return array<string, mixed>
 */
function hvnly_get_agency_profile( int $term_id = 0 ): array {
	if ( $term_id <= 0 || ! class_exists( AgencyFields::class ) ) {
		return array();
	}

	return AgencyFields::get_profile( $term_id );
}

/**
 * Enriched agency profile for directory and single templates.
 *
 * @since 3.0.2
 *
 * @param int $term_id Agency term ID.
 * @return array<string, mixed>
 */
function hvnly_get_agency_archive_profile( int $term_id = 0 ): array {
	if ( $term_id <= 0 || ! class_exists( AgencyArchiveQuery::class ) ) {
		return array();
	}

	return AgencyArchiveQuery::get_archive_profile( $term_id );
}

/**
 * WP_Query for properties assigned to agents within an agency.
 *
 * @since 3.0.2
 *
 * @param int                  $term_id Agency term ID.
 * @param array<string, mixed> $args    Optional query overrides.
 * @return \WP_Query|null
 */
function hvnly_get_agency_properties_query( int $term_id = 0, array $args = array() ): ?\WP_Query {
	$term_id = absint( $term_id );
	if ( $term_id <= 0 || ! class_exists( AgencyPropertiesQuery::class ) ) {
		return null;
	}

	return AgencyPropertiesQuery::query( $term_id, $args );
}

/**
 * Published property IDs assigned to an agent profile.
 *
 * @since 3.0.2
 *
 * @param int $agent_id Agent post ID.
 * @return int[]
 */
function hvnly_get_agent_property_ids( int $agent_id = 0 ): array {
	$agent_id = $agent_id > 0 ? $agent_id : (int) get_the_ID();
	if ( $agent_id <= 0 || ! class_exists( AgentPropertiesQuery::class ) ) {
		return array();
	}

	return AgentPropertiesQuery::get_assigned_property_ids( $agent_id );
}

/**
 * Count of published properties assigned to an agent.
 *
 * @since 3.0.2
 *
 * @param int $agent_id Agent post ID.
 * @return int
 */
function hvnly_get_agent_property_count( int $agent_id = 0 ): int {
	$agent_id = $agent_id > 0 ? $agent_id : (int) get_the_ID();
	if ( $agent_id <= 0 || ! class_exists( AgentPropertiesQuery::class ) ) {
		return 0;
	}

	return AgentPropertiesQuery::count( $agent_id );
}

/**
 * Count of active (non-sold) listings assigned to an agent.
 *
 * @since 3.0.2
 *
 * @param int $agent_id Agent post ID.
 * @return int
 */
function hvnly_get_agent_active_listing_count( int $agent_id = 0 ): int {
	$agent_id = $agent_id > 0 ? $agent_id : (int) get_the_ID();
	if ( $agent_id <= 0 || ! class_exists( AgentPropertiesQuery::class ) ) {
		return 0;
	}

	$ids = AgentPropertiesQuery::get_assigned_property_ids( $agent_id );
	if ( empty( $ids ) ) {
		return 0;
	}

	$query = new \WP_Query(
		array(
			'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'post__in'               => $ids,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy' => 'hvnly_prop_status',
					'field'    => 'slug',
					'terms'    => array( 'sold' ),
					'operator' => 'NOT IN',
				),
			),
		)
	);

	$count = (int) $query->found_posts;
	wp_reset_postdata();

	return $count;
}

/**
 * WP_Query for an agent's assigned properties.
 *
 * @since 3.0.2
 *
 * @param int                  $agent_id Agent post ID.
 * @param array<string, mixed> $args     Optional query overrides.
 * @return \WP_Query|null
 */
function hvnly_get_agent_properties_query( int $agent_id = 0, array $args = array() ): ?\WP_Query {
	$agent_id = $agent_id > 0 ? $agent_id : (int) get_the_ID();
	if ( $agent_id <= 0 || ! class_exists( AgentPropertiesQuery::class ) ) {
		return null;
	}

	return AgentPropertiesQuery::query( $agent_id, $args );
}

/**
 * Whether the current request is the agency taxonomy archive index (/agencies/).
 *
 * @since 3.0.2
 *
 * @return bool
 */
function hvnly_is_agency_archive_index(): bool {
	if ( ! is_tax( AgentConstants::TAXONOMY_AGENCY ) ) {
		return false;
	}

	$obj = get_queried_object();

	return ! ( $obj instanceof \WP_Term && ! empty( $obj->term_id ) );
}

/**
 * Current directory view mode (grid or list).
 *
 * @since 3.0.2
 *
 * @return string
 */
function hvnly_get_property_archive_view_type(): string {
	$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( (string) $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $view, array( 'grid', 'list' ), true ) ) {
		$view = 'grid';
	}

	/**
	 * Filter directory archive view type.
	 *
	 * @since 3.0.2
	 *
	 * @param string $view View mode.
	 */
	return apply_filters( 'hvnly_property_archive_view_type', $view );
}

/**
 * Paginated agency archive data.
 *
 * @since 3.0.2
 *
 * @param array<string, mixed> $args Optional overrides.
 * @return array<string, mixed>
 */
function hvnly_get_agency_archive_query( array $args = array() ): array {
	if ( ! class_exists( AgencyArchiveQuery::class ) ) {
		return array(
			'items'        => array(),
			'total'        => 0,
			'max_pages'    => 1,
			'current_page' => 1,
			'per_page'     => 12,
		);
	}

	return AgencyArchiveQuery::query_agencies( $args );
}

/**
 * Enqueue agency listing assets (shared by shortcode and Elementor widget).
 *
 * @since 3.0.3
 * @return void
 */
function hvnly_enqueue_property_agencies_listing_assets(): void {
	if ( class_exists( '\HvnlyNab\Integrations\Elementor\Bootstrap' ) ) {
		\HvnlyNab\Integrations\Elementor\Bootstrap::get_instance()->enqueue_agency_widget_assets_for_render();
		return;
	}

	wp_enqueue_style( 'hvnly-frontend-cards' );
	wp_enqueue_style( 'hvnly-frontend-property-agents-archive' );
	wp_enqueue_style( 'hvnly-frontend-property-ajax-filter' );
	wp_enqueue_style( 'hvnly-frontend-property-archive' );

	$agencies_css_path = defined( 'HVNLYNAB_ASSETS_PATH' )
		? HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-property-agencies.css'
		: '';

	if ( $agencies_css_path && file_exists( $agencies_css_path ) ) {
		if ( ! wp_style_is( 'hvnly-elementor-property-agencies', 'registered' ) ) {
			wp_register_style(
				'hvnly-elementor-property-agencies',
				HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-property-agencies.css',
				array( 'hvnly-frontend-property-agents-archive', 'hvnly-frontend-cards' ),
				defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.5'
			);
		}
		wp_enqueue_style( 'hvnly-elementor-property-agencies' );
	}

	if ( wp_script_is( 'hvnly-frontend-property-agents-archive', 'registered' ) ) {
		wp_enqueue_script( 'hvnly-frontend-property-agents-archive' );
	}
}

/**
 * Render Havenlytics pagination markup for directory archives.
 *
 * @since 3.0.2
 *
 * @param array<string, mixed> $args Pagination args.
 * @return void
 */
function hvnly_render_property_archive_pagination( array $args = array() ): void {
	if ( ! function_exists( 'hvnly_get_template_part' ) ) {
		return;
	}

	$defaults = array(
		'current_page'  => max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ),
		'max_pages'     => 1,
		'per_page'      => 12,
		'found_posts'   => 0,
		'instance_id'   => 'property-archive',
		'base_url'      => '',
		'entity_label'  => __( 'results', 'havenlytics' ),
	);

	$args = wp_parse_args( $args, $defaults );

	if ( (int) $args['max_pages'] <= 1 ) {
		return;
	}

	hvnly_get_template_part(
		'property-archive/partials/archive-pagination',
		null,
		array(
			'current_page' => (int) $args['current_page'],
			'max_pages'    => (int) $args['max_pages'],
			'per_page'     => (int) $args['per_page'],
			'found_posts'  => (int) $args['found_posts'],
			'base_url'     => (string) $args['base_url'],
			'entity_label' => (string) $args['entity_label'],
		)
	);
}

/**
 * Agent availability status definitions (available, busy, away, offline).
 *
 * @since 3.0.2
 *
 * @return array<string, array{label: string, description: string, accepts_inquiries: bool}>
 */
function hvnly_get_agent_availability_definitions(): array {
	$definitions = array(
		'available' => array(
			'label'              => __( 'Available', 'havenlytics' ),
			'description'        => __( 'Actively accepting new inquiries.', 'havenlytics' ),
			'accepts_inquiries'  => true,
		),
		'busy'      => array(
			'label'              => __( 'Busy', 'havenlytics' ),
			'description'        => __( 'Currently with clients — responses may take longer.', 'havenlytics' ),
			'accepts_inquiries'  => true,
		),
		'away'      => array(
			'label'              => __( 'Away', 'havenlytics' ),
			'description'        => __( 'Temporarily unavailable — inquiries will be answered when back.', 'havenlytics' ),
			'accepts_inquiries'  => true,
		),
		'offline'   => array(
			'label'              => __( 'Offline', 'havenlytics' ),
			'description'        => __( 'Not accepting inquiries at this time.', 'havenlytics' ),
			'accepts_inquiries'  => false,
		),
	);

	/**
	 * Filter agent availability status definitions.
	 *
	 * @since 3.0.2
	 *
	 * @param array<string, array{label: string, description: string, accepts_inquiries: bool}> $definitions Status definitions.
	 */
	return (array) apply_filters( 'hvnly_agent_availability_definitions', $definitions );
}

/**
 * Agent availability status for card UI (available, busy, away, offline).
 *
 * Reads optional `availability` from extensions meta; defaults to available.
 *
 * @since 3.0.2
 *
 * @param int                  $agent_id Agent post ID.
 * @param array<string, mixed> $agent    Optional agent profile.
 * @return string
 */
function hvnly_get_agent_availability_status( int $agent_id = 0, array $agent = array() ): string {
	$agent_id = $agent_id > 0 ? $agent_id : absint( $agent['id'] ?? 0 );
	$status   = AgentConstants::AVAILABILITY_AVAILABLE;

	if ( ! empty( $agent['availability_status'] ) ) {
		$status = sanitize_key( (string) $agent['availability_status'] );
	} elseif ( $agent_id > 0 && class_exists( AgentFields::class ) ) {
		$status = AgentFields::get_availability( $agent_id );
	} elseif ( $agent_id > 0 ) {
		$extensions = isset( $agent['extensions'] ) && is_array( $agent['extensions'] )
			? $agent['extensions']
			: (array) get_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, true );

		if ( ! empty( $extensions['availability'] ) ) {
			$status = sanitize_key( (string) $extensions['availability'] );
		}
	}

	$allowed = array_keys( hvnly_get_agent_availability_definitions() );
	if ( ! in_array( $status, $allowed, true ) ) {
		$status = AgentConstants::AVAILABILITY_AVAILABLE;
	}

	/**
	 * Filter agent availability status for directory cards.
	 *
	 * @since 3.0.2
	 *
	 * @param string               $status   Status slug.
	 * @param int                  $agent_id Agent post ID.
	 * @param array<string, mixed> $agent    Agent profile.
	 */
	return (string) apply_filters( 'hvnly_agent_availability_status', $status, $agent_id, $agent );
}

/**
 * Human-readable availability label.
 *
 * @since 3.0.2
 *
 * @param string $status Status slug.
 * @return string
 */
function hvnly_get_agent_availability_label( string $status ): string {
	$definitions = hvnly_get_agent_availability_definitions();
	$status      = sanitize_key( $status );

	return $definitions[ $status ]['label'] ?? $definitions['available']['label'];
}

/**
 * Visitor-facing availability notice for contact surfaces.
 *
 * @since 3.0.2
 *
 * @param string $status Status slug.
 * @return string
 */
function hvnly_get_agent_availability_notice( string $status ): string {
	$status = sanitize_key( $status );

	$notices = array(
		'available' => '',
		'busy'      => __( 'This agent is busy right now. Your message will be answered as soon as possible.', 'havenlytics' ),
		'away'      => __( 'This agent is currently away. Inquiries are queued and will be answered when they return.', 'havenlytics' ),
		'offline'   => __( 'This agent is offline and not accepting inquiries at this time.', 'havenlytics' ),
	);

	/**
	 * Filter visitor-facing availability notice text.
	 *
	 * @since 3.0.2
	 *
	 * @param string $notice Notice text.
	 * @param string $status Status slug.
	 */
	return (string) apply_filters( 'hvnly_agent_availability_notice', $notices[ $status ] ?? '', $status );
}

/**
 * Whether an agent accepts Contact Agent inquiries for the given status.
 *
 * @since 3.0.2
 *
 * @param int                  $agent_id Agent post ID.
 * @param array<string, mixed> $agent    Optional agent profile.
 * @return bool
 */
function hvnly_agent_accepts_inquiries( int $agent_id = 0, array $agent = array() ): bool {
	$status      = hvnly_get_agent_availability_status( $agent_id, $agent );
	$definitions = hvnly_get_agent_availability_definitions();
	$accepts     = ! empty( $definitions[ $status ]['accepts_inquiries'] );

	/**
	 * Filter whether an agent accepts inquiries for their current availability status.
	 *
	 * @since 3.0.2
	 *
	 * @param bool                 $accepts  Whether inquiries are accepted.
	 * @param string               $status   Availability slug.
	 * @param int                  $agent_id Agent post ID.
	 * @param array<string, mixed> $agent    Agent profile.
	 */
	return (bool) apply_filters( 'hvnly_agent_accepts_inquiries', $accepts, $status, $agent_id, $agent );
}

/**
 * Render a reusable availability badge.
 *
 * @since 3.0.2
 *
 * @param array<string, mixed> $args {
 *     @type int    $agent_id Agent post ID.
 *     @type array  $agent    Agent profile array.
 *     @type string $context  Badge variant: card, inline, profile.
 *     @type bool   $echo     Echo or return.
 * }
 * @return string
 */
function hvnly_render_agent_availability_badge( array $args = array() ): string {
	$agent_id = isset( $args['agent_id'] ) ? absint( $args['agent_id'] ) : 0;
	$agent    = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();
	$context  = isset( $args['context'] ) ? sanitize_key( (string) $args['context'] ) : 'inline';
	$echo     = ! empty( $args['echo'] );

	if ( $agent_id <= 0 && ! empty( $agent['id'] ) ) {
		$agent_id = absint( $agent['id'] );
	}

	$status = hvnly_get_agent_availability_status( $agent_id, $agent );
	$label  = hvnly_get_agent_availability_label( $status );

	$classes = array(
		'hvnly-agent-availability',
		'hvnly-agent-availability--' . $status,
		'hvnly-agent-availability--' . $context,
	);

	$html = sprintf(
		'<span class="%1$s" data-agent-status="%2$s"><span class="hvnly-agent-availability__dot" aria-hidden="true"></span><span class="hvnly-agent-availability__label">%3$s</span></span>',
		esc_attr( implode( ' ', $classes ) ),
		esc_attr( $status ),
		esc_html( $label )
	);

	if ( $echo ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return '';
	}

	return $html;
}

/**
 * Optional premium badges for agent cards (featured, verified).
 *
 * @since 3.0.2
 *
 * @param int                  $agent_id Agent post ID.
 * @param array<string, mixed> $agent    Agent profile.
 * @return array<int, array{type: string, label: string}>
 */
function hvnly_get_agent_card_badges( int $agent_id = 0, array $agent = array() ): array {
	$agent_id = $agent_id > 0 ? $agent_id : absint( $agent['id'] ?? 0 );
	$badges   = array();

	if ( $agent_id > 0 ) {
		$extensions = isset( $agent['extensions'] ) && is_array( $agent['extensions'] )
			? $agent['extensions']
			: (array) get_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, true );

		if ( ! empty( $extensions['featured'] ) ) {
			$badges[] = array(
				'type'  => 'featured',
				'label' => __( 'Featured Agent', 'havenlytics' ),
			);
		}

		if ( ! empty( $extensions['verified'] ) ) {
			$badges[] = array(
				'type'  => 'verified',
				'label' => __( 'Verified Agent', 'havenlytics' ),
			);
		}
	}

	/**
	 * Filter premium badges shown on agent cards.
	 *
	 * @since 3.0.2
	 *
	 * @param array<int, array{type: string, label: string}> $badges   Badge list.
	 * @param int                                            $agent_id Agent post ID.
	 * @param array<string, mixed>                           $agent    Agent profile.
	 */
	return apply_filters( 'hvnly_agent_card_badges', $badges, $agent_id, $agent );
}

/**
 * Experience label for agent cards (extensions or filter).
 *
 * @since 3.0.2
 *
 * @param int                  $agent_id Agent post ID.
 * @param array<string, mixed> $agent    Agent profile.
 * @return string
 */
function hvnly_get_agent_experience_label( int $agent_id = 0, array $agent = array() ): string {
	$agent_id = $agent_id > 0 ? $agent_id : absint( $agent['id'] ?? 0 );
	$label    = '';

	if ( $agent_id > 0 ) {
		$extensions = isset( $agent['extensions'] ) && is_array( $agent['extensions'] )
			? $agent['extensions']
			: (array) get_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, true );

		$years = isset( $extensions['experience_years'] ) ? absint( $extensions['experience_years'] ) : 0;
		if ( $years > 0 ) {
			$label = sprintf(
				/* translators: %d: years of experience */
				_n( '%d Year Experience', '%d Years Experience', $years, 'havenlytics' ),
				$years
			);
		} elseif ( ! empty( $extensions['experience'] ) ) {
			$label = sanitize_text_field( (string) $extensions['experience'] );
		}
	}

	/**
	 * Filter experience label on agent cards.
	 *
	 * @since 3.0.2
	 *
	 * @param string               $label    Experience label.
	 * @param int                  $agent_id Agent post ID.
	 * @param array<string, mixed> $agent    Agent profile.
	 */
	return (string) apply_filters( 'hvnly_agent_experience_label', $label, $agent_id, $agent );
}

/**
 * Short location label for agent cards.
 *
 * @since 3.0.2
 *
 * @param int                  $agent_id Agent post ID.
 * @param array<string, mixed> $agent    Agent profile.
 * @return string
 */
function hvnly_get_agent_location_label( int $agent_id = 0, array $agent = array() ): string {
	$address = isset( $agent['address'] ) ? trim( (string) $agent['address'] ) : '';

	if ( '' === $address && $agent_id > 0 ) {
		$address = trim( (string) get_post_meta( $agent_id, AgentConstants::META_ADDRESS, true ) );
	}

	if ( '' === $address ) {
		return '';
	}

	$parts = preg_split( '/[\r\n,]+/', $address );
	if ( ! is_array( $parts ) || empty( $parts ) ) {
		return $address;
	}

	$last = trim( (string) end( $parts ) );

	/**
	 * Filter location label on agent cards.
	 *
	 * @since 3.0.2
	 *
	 * @param string               $location Location label.
	 * @param int                  $agent_id Agent post ID.
	 * @param array<string, mixed> $agent    Agent profile.
	 */
	return (string) apply_filters( 'hvnly_agent_location_label', '' !== $last ? $last : $address, $agent_id, $agent );
}

/**
 * Contact URL for an agent from directory cards.
 *
 * @since 3.0.2
 *
 * @param int    $agent_id    Agent post ID.
 * @param string $profile_url Agent profile URL.
 * @return string
 */
function hvnly_get_agent_contact_url( int $agent_id, string $profile_url ): string {
	$agent_id    = absint( $agent_id );
	$profile_url = (string) $profile_url;

	if ( $profile_url && function_exists( 'hvnly_is_contact_agent_enabled' ) && hvnly_is_contact_agent_enabled() ) {
		return trailingslashit( $profile_url ) . '#hvnly-agent-contact';
	}

	$agent = $agent_id > 0 && function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( $agent_id ) : array();
	$email = isset( $agent['email'] ) ? (string) $agent['email'] : '';

	if ( $email ) {
		return 'mailto:' . $email;
	}

	return $profile_url;
}
