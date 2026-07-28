<?php
/**
 * Agents Section group field template.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/fields/group
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_property_id   = isset( $args['property_id'] ) ? absint( $args['property_id'] ) : get_the_ID();
$hvnly_agents        = isset( $args['agents'] ) && is_array( $args['agents'] ) ? $args['agents'] : array();
$hvnly_title         = isset( $args['section_title'] ) ? (string) $args['section_title'] : '';
$hvnly_group_id      = isset( $args['group_id'] ) ? sanitize_html_class( (string) $args['group_id'] ) : wp_unique_id( 'hvnly-agents-section-' );
$hvnly_group_base    = isset( $args['group_base_id'] ) ? (string) $args['group_base_id'] : '';
$hvnly_show_form     = ! empty( $args['show_contact_form'] );
$hvnly_property_title = isset( $args['property_title'] ) ? (string) $args['property_title'] : get_the_title( $hvnly_property_id );
$hvnly_agent_count   = count( $hvnly_agents );
$hvnly_is_single     = 1 === $hvnly_agent_count;

if ( in_array( trim( $hvnly_title ), array( '[]', '{}', 'null' ), true ) ) {
	$hvnly_title = '';
}

if ( ! $hvnly_property_id || empty( $hvnly_agents ) ) {
	return;
}

$hvnly_section_class = 'hvnly-agents-section';
if ( $hvnly_is_single ) {
	$hvnly_section_class .= ' hvnly-agents-section--single';
} else {
	$hvnly_section_class .= ' hvnly-agents-section--multi';
}
?>
<div
	class="hvnly-property-single__agents-field <?php echo esc_attr( $hvnly_section_class ); ?>"
	id="<?php echo esc_attr( $hvnly_group_id ); ?>"
	data-property-id="<?php echo esc_attr( (string) $hvnly_property_id ); ?>"
	data-group-base-id="<?php echo esc_attr( $hvnly_group_base ); ?>"
	data-agent-count="<?php echo esc_attr( (string) $hvnly_agent_count ); ?>"
>
	<?php if ( $hvnly_title ) : ?>
		<p class="hvnly-agents-section__lead"><?php echo esc_html( $hvnly_title ); ?></p>
	<?php endif; ?>

	<?php if ( $hvnly_is_single ) : ?>
		<?php
		hvnly_get_template_part(
			'single-property/partials/property-agent-section-card',
			null,
			array(
				'agent'      => $hvnly_agents[0],
				'is_primary' => true,
				'layout'     => 'featured',
			)
		);
		?>

		<?php if ( $hvnly_show_form ) : ?>
			<div class="hvnly-agents-section__form-row">
				<?php
				hvnly_get_template_part(
					'single-property/partials/property-agent-section-form',
					null,
					array(
						'property_id'    => $hvnly_property_id,
						'property_title' => $hvnly_property_title,
						'agents'         => $hvnly_agents,
						'agent'          => $hvnly_agents[0],
						'section_id'     => $hvnly_group_id,
						'form_id'        => $hvnly_group_id . '-contact-form',
					)
				);
				?>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="hvnly-agents-section__grid">
			<?php
			foreach ( $hvnly_agents as $hvnly_agent_index => $hvnly_agent ) :
				hvnly_get_template_part(
					'single-property/partials/property-agent-section-card',
					null,
					array(
						'agent'      => $hvnly_agent,
						'is_primary' => 0 === (int) $hvnly_agent_index,
						'layout'     => 'compact',
					)
				);
			endforeach;
			?>
		</div>

		<?php if ( $hvnly_show_form ) : ?>
			<div class="hvnly-agents-section__form-row">
				<?php
				hvnly_get_template_part(
					'single-property/partials/property-agent-section-form',
					null,
					array(
						'property_id'    => $hvnly_property_id,
						'property_title' => $hvnly_property_title,
						'agents'         => $hvnly_agents,
						'section_id'     => $hvnly_group_id,
						'form_id'        => $hvnly_group_id . '-contact-form',
					)
				);
				?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
