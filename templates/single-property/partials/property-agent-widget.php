<?php
/**
 * Property agent widget wrapper.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_property_id = isset( $args['property_id'] ) ? absint( $args['property_id'] ) : get_the_ID();
$hvnly_instance    = isset( $args['instance'] ) && is_array( $args['instance'] ) ? $args['instance'] : array();
$hvnly_agents      = isset( $args['agents'] ) && is_array( $args['agents'] ) ? $args['agents'] : array();
$hvnly_widget_id   = isset( $args['widget_id'] ) ? sanitize_html_class( (string) $args['widget_id'] ) : wp_unique_id( 'hvnly-agent-widget-' );

if ( ! $hvnly_property_id || empty( $hvnly_agents ) ) {
	return;
}

$hvnly_is_multi    = count( $hvnly_agents ) > 1;
$hvnly_layout      = isset( $hvnly_instance['multi_layout'] ) ? (string) $hvnly_instance['multi_layout'] : 'slider';
$hvnly_use_slider  = $hvnly_is_multi && 'slider' === $hvnly_layout;
$hvnly_stack_class = $hvnly_is_multi && ! $hvnly_use_slider ? ' hvnly-agent-widget--stack' : '';
?>
<div
	class="hvnly-agent-widget<?php echo esc_attr( $hvnly_stack_class ); ?>"
	id="<?php echo esc_attr( $hvnly_widget_id ); ?>"
	data-agent-count="<?php echo esc_attr( (string) count( $hvnly_agents ) ); ?>"
>
	<?php if ( $hvnly_use_slider ) : ?>
		<div class="hvnly-agent-widget__slider" data-hvnly-agent-slider>
			<div class="hvnly-agent-widget__track">
				<?php
				foreach ( $hvnly_agents as $hvnly_agent_index => $hvnly_agent ) :
					hvnly_get_template_part(
						'single-property/partials/property-agent-card',
						null,
						array(
							'property_id'        => $hvnly_property_id,
							'instance'           => $hvnly_instance,
							'agent'              => $hvnly_agent,
							'is_primary'         => 0 === (int) $hvnly_agent_index,
							'is_slide'           => true,
							'show_contact_agent' => false,
						)
					);
				endforeach;
				?>
			</div>
			<div class="hvnly-agent-widget__nav" aria-hidden="false">
				<button type="button" class="hvnly-agent-widget__nav-btn hvnly-agent-widget__nav-btn--prev" data-hvnly-agent-prev aria-label="<?php esc_attr_e( 'Previous agent', 'havenlytics' ); ?>">
					<span aria-hidden="true">&lsaquo;</span>
				</button>
				<div class="hvnly-agent-widget__dots" data-hvnly-agent-dots></div>
				<button type="button" class="hvnly-agent-widget__nav-btn hvnly-agent-widget__nav-btn--next" data-hvnly-agent-next aria-label="<?php esc_attr_e( 'Next agent', 'havenlytics' ); ?>">
					<span aria-hidden="true">&rsaquo;</span>
				</button>
			</div>
		</div>
	<?php else : ?>
		<div class="hvnly-agent-widget__cards">
			<?php
			foreach ( $hvnly_agents as $hvnly_agent_index => $hvnly_agent ) :
				hvnly_get_template_part(
					'single-property/partials/property-agent-card',
					null,
					array(
						'property_id'        => $hvnly_property_id,
						'instance'           => $hvnly_instance,
						'agent'              => $hvnly_agent,
						'is_primary'         => 0 === (int) $hvnly_agent_index,
						'is_slide'           => false,
						'show_contact_agent' => ! $hvnly_is_multi && 0 === (int) $hvnly_agent_index,
					)
				);
			endforeach;
			?>
		</div>
	<?php endif; ?>

	<?php if ( $hvnly_is_multi || $hvnly_use_slider ) : ?>
		<div class="hvnly-agent-widget__footer">
			<?php
			$hvnly_primary = $hvnly_agents[0];
			if ( function_exists( 'hvnly_is_contact_agent_enabled' ) && hvnly_is_contact_agent_enabled() && function_exists( 'hvnly_render_contact_agent_button' ) ) :
				?>
				<div class="hvnly-property-single__agent-actions hvnly-property-single__agent-actions--contact-agent">
					<?php
					hvnly_render_contact_agent_button(
						$hvnly_property_id,
						array(
							'agent_name'     => isset( $hvnly_primary['name'] ) ? (string) $hvnly_primary['name'] : '',
							'agent_id'       => isset( $hvnly_primary['id'] ) ? absint( $hvnly_primary['id'] ) : 0,
							'agent_type'     => isset( $hvnly_primary['type'] ) ? (string) $hvnly_primary['type'] : (string) ( $hvnly_primary['source'] ?? 'user' ),
							'agent_avatar'   => isset( $hvnly_primary['avatar'] ) ? (string) $hvnly_primary['avatar'] : '',
							'agent_position' => isset( $hvnly_primary['position'] ) ? (string) $hvnly_primary['position'] : '',
							'class'          => 'hvnly-property-single__agent-btn hvnly-property-single__agent-btn--contact hvnly-agent-widget__contact-btn',
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
