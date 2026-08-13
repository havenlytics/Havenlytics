<?php
/**
 * Assigned properties grid for an agent profile.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-agent/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_agent_id = isset( $args['agent_id'] ) ? absint( $args['agent_id'] ) : (int) get_the_ID();
$hvnly_agent    = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();

if ( empty( $hvnly_agent ) && function_exists( 'hvnly_get_agent' ) ) {
	$hvnly_agent = hvnly_get_agent( $hvnly_agent_id );
}

$hvnly_property_count = function_exists( 'hvnly_get_agent_property_count' )
	? hvnly_get_agent_property_count( $hvnly_agent_id )
	: 0;

$hvnly_properties_query = function_exists( 'hvnly_get_agent_properties_query' )
	? hvnly_get_agent_properties_query(
		$hvnly_agent_id,
		array(
			'posts_per_page' => -1,
			'nopaging'       => true,
		)
	)
	: null;

$hvnly_department_terms = get_terms(
	array(
		'taxonomy'   => 'hvnly_prop_depts',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	)
);

if ( is_wp_error( $hvnly_department_terms ) ) {
	$hvnly_department_terms = array();
}
?>
<section class="hvnly-agent-single__properties" id="hvnly-agent-properties">
	<div class="hvnly-agent-single__properties-header">
		<div>
			<h2 class="hvnly-agent-single__section-title"><?php esc_html_e( 'Agent Listings', 'havenlytics' ); ?></h2>
			<p class="hvnly-agent-single__properties-count">
				<?php
				printf(
					/* translators: %d: number of properties */
					esc_html( _n( '%d property assigned', '%d properties assigned', $hvnly_property_count, 'havenlytics' ) ),
					(int) $hvnly_property_count
				);
				?>
			</p>
		</div>

		<?php if ( ! empty( $hvnly_department_terms ) ) : ?>
			<ul class="hvnly-agent-single__status-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter listings by department', 'havenlytics' ); ?>">
				<li>
					<button type="button" class="hvnly-ui-control hvnly-agent-single__status-tab is-active" data-dept="all" role="tab" aria-selected="true">
						<?php esc_html_e( 'All', 'havenlytics' ); ?>
					</button>
				</li>
				<?php foreach ( $hvnly_department_terms as $hvnly_dept_term ) : ?>
					<li>
						<button
							type="button"
							class="hvnly-ui-control hvnly-agent-single__status-tab"
							data-dept="<?php echo esc_attr( (string) $hvnly_dept_term->term_id ); ?>"
							role="tab"
							aria-selected="false"
						>
							<?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_dept_term->name ) ) : esc_html( $hvnly_dept_term->name ); ?>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<?php if ( $hvnly_properties_query instanceof WP_Query && $hvnly_properties_query->have_posts() ) : ?>
		<div
			class="hvnly-agent-single__listings-grid hvnly-agent-single__property-grid hvnly-property-grid-view hvnly-grid-view grid-view"
			data-agent-id="<?php echo esc_attr( (string) $hvnly_agent_id ); ?>"
			data-view-type="grid"
		>
			<?php
			while ( $hvnly_properties_query->have_posts() ) :
				$hvnly_properties_query->the_post();

				$hvnly_property_dept_ids = wp_get_post_terms( get_the_ID(), 'hvnly_prop_depts', array( 'fields' => 'ids' ) );
				if ( is_wp_error( $hvnly_property_dept_ids ) ) {
					$hvnly_property_dept_ids = array();
				}

				$hvnly_dept_attr = ! empty( $hvnly_property_dept_ids ) ? implode( ' ', array_map( 'absint', $hvnly_property_dept_ids ) ) : 'none';
				?>
				<div class="hvnly-agent-single__property-item" data-dept-ids="<?php echo esc_attr( $hvnly_dept_attr ); ?>">
					<?php hvnly_get_template_part( 'content', 'property' ); ?>
				</div>
			<?php endwhile; ?>
		</div>

		<p class="hvnly-agent-single__properties-footnote" hidden aria-live="polite"></p>
	<?php else : ?>
		<div class="hvnly-agent-single__empty">
			<i class="fas fa-home" aria-hidden="true"></i>
			<p><?php esc_html_e( 'No properties are assigned to this agent yet.', 'havenlytics' ); ?></p>
		</div>
	<?php endif; ?>

	<?php wp_reset_postdata(); ?>
</section>
