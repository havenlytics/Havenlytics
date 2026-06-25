<?php
/**
 * Shared agents property archive markup (native archive + Elementor widget).
 *
 * @package     Havenlytics
 * @subpackage  Templates/property-archive/partials
 * @since       3.0.2
 *
 * @var array<string, mixed> $args Template args.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use HvnlyNab\Agent\AgentArchiveQuery;
use HvnlyNab\Agent\AgentConstants;

$hvnly_args = isset( $args ) && is_array( $args ) ? $args : array();

$hvnly_query = isset( $hvnly_args['query'] ) && $hvnly_args['query'] instanceof WP_Query
	? $hvnly_args['query']
	: null;

$hvnly_use_global_query = ! $hvnly_query;
if ( $hvnly_use_global_query ) {
	global $wp_query;
	$hvnly_query = $wp_query;
}

$hvnly_show_header   = ! isset( $hvnly_args['show_header'] ) || ! empty( $hvnly_args['show_header'] );
$hvnly_title         = isset( $hvnly_args['title'] ) ? (string) $hvnly_args['title'] : __( 'Real Estate Agents', 'havenlytics' );
$hvnly_subtitle      = isset( $hvnly_args['subtitle'] ) ? (string) $hvnly_args['subtitle'] : __( 'Browse professional agents and connect with the right expert for your property journey.', 'havenlytics' );
$hvnly_instance_id   = isset( $hvnly_args['instance_id'] ) ? sanitize_key( (string) $hvnly_args['instance_id'] ) : 'agent-archive';
$hvnly_wrapper_class = isset( $hvnly_args['wrapper_class'] ) ? (string) $hvnly_args['wrapper_class'] : '';
$hvnly_columns       = isset( $hvnly_args['columns'] ) ? max( 1, min( 4, absint( $hvnly_args['columns'] ) ) ) : 4;
$hvnly_show_search   = ! isset( $hvnly_args['show_search'] ) || ! empty( $hvnly_args['show_search'] );
$hvnly_show_controls = ! isset( $hvnly_args['show_view_controls'] ) || ! empty( $hvnly_args['show_view_controls'] );

$hvnly_view_type = function_exists( 'hvnly_get_property_archive_view_type' ) ? hvnly_get_property_archive_view_type() : 'grid';
$hvnly_total     = $hvnly_query instanceof WP_Query ? (int) $hvnly_query->found_posts : 0;
$hvnly_per_page  = isset( $hvnly_args['per_page'] ) ? absint( $hvnly_args['per_page'] ) : ( class_exists( AgentArchiveQuery::class ) ? AgentArchiveQuery::get_per_page() : 12 );

if ( $hvnly_query instanceof WP_Query && $hvnly_query->get( 'posts_per_page' ) > 0 ) {
	$hvnly_per_page = absint( $hvnly_query->get( 'posts_per_page' ) );
}

$hvnly_current_page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
if ( $hvnly_query instanceof WP_Query && $hvnly_query->get( 'paged' ) > 0 ) {
	$hvnly_current_page = max( 1, (int) $hvnly_query->get( 'paged' ) );
}

$hvnly_start     = $hvnly_total > 0 ? ( ( $hvnly_current_page - 1 ) * $hvnly_per_page ) + 1 : 0;
$hvnly_end       = min( $hvnly_current_page * $hvnly_per_page, $hvnly_total );
$hvnly_max_pages = $hvnly_query instanceof WP_Query ? (int) $hvnly_query->max_num_pages : 1;

$hvnly_search_url = isset( $hvnly_args['search_action'] ) ? (string) $hvnly_args['search_action'] : '';
if ( '' === $hvnly_search_url ) {
	$hvnly_search_url = get_post_type_archive_link( AgentConstants::POST_TYPE );
	if ( is_wp_error( $hvnly_search_url ) || ! $hvnly_search_url ) {
		$hvnly_search_url = home_url( '/' );
	}
}

$hvnly_wrapper_classes = array(
	'hvnly-property--archive',
	'hvnly-property--agents--archive',
	'hvnly-agent-archive',
);

if ( '' !== trim( $hvnly_wrapper_class ) ) {
	$hvnly_wrapper_classes[] = $hvnly_wrapper_class;
}

$hvnly_grid_classes = array(
	'hvnly-property--archive__grid',
	'hvnly-property--agents--grid',
);

if ( 'list' === $hvnly_view_type ) {
	$hvnly_grid_classes[] = 'hvnly-property--archive__grid--list';
} else {
	$hvnly_grid_classes[] = 'hvnly-property--archive__grid--grid';
}

$hvnly_card_context = isset( $hvnly_args['card_context'] ) ? sanitize_key( (string) $hvnly_args['card_context'] ) : 'agents_archive';
?>
<div
	class="<?php echo esc_attr( implode( ' ', $hvnly_wrapper_classes ) ); ?>"
	data-columns="<?php echo esc_attr( (string) $hvnly_columns ); ?>"
	style="--hvnly-property-agents-columns: <?php echo esc_attr( (string) $hvnly_columns ); ?>;"
>
	<?php if ( $hvnly_show_header && ( $hvnly_title || $hvnly_subtitle ) ) : ?>
		<header class="hvnly-property--archive__header">
			<?php if ( $hvnly_title ) : ?>
				<h1 class="hvnly-property--archive__title"><?php echo esc_html( $hvnly_title ); ?></h1>
			<?php endif; ?>
			<?php if ( $hvnly_subtitle ) : ?>
				<p class="hvnly-property--archive__subtitle"><?php echo esc_html( $hvnly_subtitle ); ?></p>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<?php if ( $hvnly_show_controls ) : ?>
		<?php
		hvnly_get_template_part(
			'property-archive/partials/view-controls',
			null,
			array(
				'total'         => $hvnly_total,
				'start'         => $hvnly_start,
				'end'           => $hvnly_end,
				'entity_label'  => __( 'agents', 'havenlytics' ),
				'search_action' => $hvnly_show_search ? $hvnly_search_url : '',
				'show_search'   => $hvnly_show_search,
			)
		);
		?>
	<?php endif; ?>

	<?php if ( $hvnly_query instanceof WP_Query && $hvnly_query->have_posts() ) : ?>
		<div
			class="<?php echo esc_attr( implode( ' ', $hvnly_grid_classes ) ); ?>"
			data-view-type="<?php echo esc_attr( $hvnly_view_type ); ?>"
		>
			<div class="hvnly-property--archive__cards">
				<?php
				while ( $hvnly_query->have_posts() ) :
					$hvnly_query->the_post();
					$hvnly_agent = function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( (int) get_the_ID() ) : array();
					hvnly_get_template_part(
						'property-archive/partials/agent-card',
						null,
						array(
							'agent'   => $hvnly_agent,
							'context' => $hvnly_card_context,
						)
					);
				endwhile;
				?>
			</div>
		</div>

		<?php
		if ( function_exists( 'hvnly_render_property_archive_pagination' ) ) {
			hvnly_render_property_archive_pagination(
				array(
					'current_page' => $hvnly_current_page,
					'max_pages'    => $hvnly_max_pages,
					'per_page'     => $hvnly_per_page,
					'found_posts'  => $hvnly_total,
					'instance_id'  => $hvnly_instance_id,
					'base_url'     => $hvnly_search_url,
					'entity_label' => __( 'agents', 'havenlytics' ),
				)
			);
		}
		?>
	<?php else : ?>
		<?php
		hvnly_get_template_part(
			'property-archive/partials/empty-state',
			null,
			array(
				'message' => __( 'No agents match your search. Try a different keyword or check back soon.', 'havenlytics' ),
				'icon'    => 'fas fa-user-tie',
			)
		);
		?>
	<?php endif; ?>
</div>
<?php
if ( $hvnly_query instanceof WP_Query ) {
	wp_reset_postdata();
}
