<?php
/**
 * Shared agencies archive markup (native archive + Elementor widget).
 *
 * @package     Havenlytics
 * @subpackage  Templates/property-archive/partials
 * @since       3.0.3
 *
 * @var array<string, mixed> $args Template args.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_args = isset( $args ) && is_array( $args ) ? $args : array();

$hvnly_query_result = isset( $hvnly_args['query'] ) && is_array( $hvnly_args['query'] )
	? $hvnly_args['query']
	: ( function_exists( 'hvnly_get_agency_archive_query' ) ? hvnly_get_agency_archive_query() : array() );

$hvnly_items        = isset( $hvnly_query_result['items'] ) && is_array( $hvnly_query_result['items'] ) ? $hvnly_query_result['items'] : array();
$hvnly_total        = absint( $hvnly_query_result['total'] ?? 0 );
$hvnly_current_page = absint( $hvnly_query_result['current_page'] ?? 1 );
$hvnly_per_page     = absint( $hvnly_query_result['per_page'] ?? 12 );
$hvnly_max_pages    = absint( $hvnly_query_result['max_pages'] ?? 1 );

$hvnly_show_header   = ! isset( $hvnly_args['show_header'] ) || ! empty( $hvnly_args['show_header'] );
$hvnly_title         = isset( $hvnly_args['title'] ) ? (string) $hvnly_args['title'] : __( 'Real Estate Agencies', 'havenlytics' );
$hvnly_subtitle      = isset( $hvnly_args['subtitle'] ) ? (string) $hvnly_args['subtitle'] : __( 'Explore trusted agencies and their teams of property professionals.', 'havenlytics' );
$hvnly_instance_id   = isset( $hvnly_args['instance_id'] ) ? sanitize_key( (string) $hvnly_args['instance_id'] ) : 'agency-archive';
$hvnly_wrapper_class = isset( $hvnly_args['wrapper_class'] ) ? (string) $hvnly_args['wrapper_class'] : '';
$hvnly_columns       = isset( $hvnly_args['columns'] ) ? max( 1, min( 4, absint( $hvnly_args['columns'] ) ) ) : 4;
$hvnly_show_search   = ! isset( $hvnly_args['show_search'] ) || ! empty( $hvnly_args['show_search'] );
$hvnly_show_controls = ! isset( $hvnly_args['show_view_controls'] ) || ! empty( $hvnly_args['show_view_controls'] );
$hvnly_card_context  = isset( $hvnly_args['card_context'] ) ? sanitize_key( (string) $hvnly_args['card_context'] ) : 'agencies_archive';

$hvnly_view_type = function_exists( 'hvnly_get_property_archive_view_type' ) ? hvnly_get_property_archive_view_type() : 'grid';
$hvnly_start     = $hvnly_total > 0 ? ( ( $hvnly_current_page - 1 ) * $hvnly_per_page ) + 1 : 0;
$hvnly_end       = min( $hvnly_current_page * $hvnly_per_page, $hvnly_total );

$hvnly_search_url = isset( $hvnly_args['search_action'] ) ? (string) $hvnly_args['search_action'] : '';
if ( '' === $hvnly_search_url ) {
	if ( class_exists( '\HvnlyNab\Core\PermalinkSettings' ) ) {
		$hvnly_search_url = home_url( '/' . trailingslashit( \HvnlyNab\Core\PermalinkSettings::get_agency_archive_slug() ) );
	} else {
		$hvnly_search_url = home_url( '/' );
	}
}

$hvnly_wrapper_classes = array(
	'hvnly-property--archive',
	'hvnly-property--agencies--archive',
	'hvnly-agency-archive',
);

if ( '' !== trim( $hvnly_wrapper_class ) ) {
	$hvnly_wrapper_classes[] = $hvnly_wrapper_class;
}

$hvnly_grid_classes = array(
	'hvnly-property--archive__grid',
	'hvnly-property--agencies--grid',
);

if ( 'list' === $hvnly_view_type ) {
	$hvnly_grid_classes[] = 'hvnly-property--archive__grid--list';
	$hvnly_grid_classes[] = 'hvnly-property-list-view';
	$hvnly_grid_classes[] = 'list-view';
} else {
	$hvnly_grid_classes[] = 'hvnly-property--archive__grid--grid';
	$hvnly_grid_classes[] = 'hvnly-property-grid-view';
	$hvnly_grid_classes[] = 'grid-view';
}
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
				'entity_label'  => __( 'agencies', 'havenlytics' ),
				'search_action' => $hvnly_show_search ? $hvnly_search_url : '',
				'show_search'   => $hvnly_show_search,
			)
		);
		?>
	<?php endif; ?>

	<?php if ( ! empty( $hvnly_items ) ) : ?>
		<div
			class="<?php echo esc_attr( implode( ' ', $hvnly_grid_classes ) ); ?>"
			data-view-type="<?php echo esc_attr( $hvnly_view_type ); ?>"
		>
			<div class="hvnly-property--archive__cards">
				<?php foreach ( $hvnly_items as $hvnly_agency ) : ?>
					<?php
					hvnly_get_template_part(
						'property-archive/partials/agency-card',
						null,
						array(
							'agency'  => $hvnly_agency,
							'context' => $hvnly_card_context,
						)
					);
					?>
				<?php endforeach; ?>
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
					'entity_label' => __( 'agencies', 'havenlytics' ),
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
				'message' => __( 'No agencies match your search. Try a different keyword or check back soon.', 'havenlytics' ),
				'icon'    => 'fas fa-building',
			)
		);
		?>
	<?php endif; ?>
</div>
