<?php
/**
 * Contact Agent trigger button.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_property_id  = isset( $args['property_id'] ) ? absint( $args['property_id'] ) : get_the_ID();
$hvnly_agent_name     = isset( $args['agent_name'] ) ? (string) $args['agent_name'] : '';
$hvnly_agent_id       = isset( $args['agent_id'] ) ? absint( $args['agent_id'] ) : 0;
$hvnly_agent_type     = isset( $args['agent_type'] ) ? sanitize_key( (string) $args['agent_type'] ) : '';
$hvnly_agent_avatar   = isset( $args['agent_avatar'] ) ? (string) $args['agent_avatar'] : '';
$hvnly_agent_position = isset( $args['agent_position'] ) ? (string) $args['agent_position'] : '';
$hvnly_button_label   = isset( $args['label'] ) ? (string) $args['label'] : __( 'Contact Agent', 'havenlytics' );
$hvnly_button_class   = isset( $args['class'] ) ? (string) $args['class'] : 'hvnly-property-single__agent-btn hvnly-property-single__agent-btn--contact';
$hvnly_availability   = isset( $args['agent_availability'] ) ? sanitize_key( (string) $args['agent_availability'] ) : '';
$hvnly_accepts        = ! isset( $args['agent_accepts_inquiries'] ) || ! empty( $args['agent_accepts_inquiries'] );

if ( '' === $hvnly_availability && $hvnly_agent_id && function_exists( 'hvnly_get_agent_availability_status' ) ) {
	$hvnly_availability = hvnly_get_agent_availability_status( $hvnly_agent_id );
}

if ( $hvnly_agent_id && function_exists( 'hvnly_agent_accepts_inquiries' ) ) {
	$hvnly_accepts = hvnly_agent_accepts_inquiries( $hvnly_agent_id );
}

if ( ! $hvnly_accepts ) {
	$hvnly_button_class .= ' hvnly-contact-agent__open-btn--disabled';
	$hvnly_button_label = __( 'Agent Offline', 'havenlytics' );
}

if ( ! $hvnly_property_id ) {
	return;
}

$hvnly_aria_label = $hvnly_agent_name
	? sprintf(
		/* translators: %s: agent display name */
		__( 'Contact agent %s about this property', 'havenlytics' ),
		$hvnly_agent_name
	)
	: __( 'Contact agent about this property', 'havenlytics' );
?>
<button
	type="button"
	class="<?php echo esc_attr( $hvnly_button_class ); ?> js-hvnly-contact-agent-open"
	data-property-id="<?php echo esc_attr( (string) $hvnly_property_id ); ?>"
	<?php if ( $hvnly_agent_id ) : ?>
		data-agent-id="<?php echo esc_attr( (string) $hvnly_agent_id ); ?>"
	<?php endif; ?>
	<?php if ( $hvnly_availability ) : ?>
		data-agent-availability="<?php echo esc_attr( $hvnly_availability ); ?>"
	<?php endif; ?>
	<?php if ( ! $hvnly_accepts ) : ?>
		disabled
		aria-disabled="true"
	<?php endif; ?>
	<?php if ( $hvnly_agent_type ) : ?>
		data-agent-type="<?php echo esc_attr( $hvnly_agent_type ); ?>"
	<?php endif; ?>
	<?php if ( $hvnly_agent_name ) : ?>
		data-agent-name="<?php echo esc_attr( $hvnly_agent_name ); ?>"
	<?php endif; ?>
	<?php if ( $hvnly_agent_avatar ) : ?>
		data-agent-avatar="<?php echo esc_attr( $hvnly_agent_avatar ); ?>"
	<?php endif; ?>
	<?php if ( $hvnly_agent_position ) : ?>
		data-agent-position="<?php echo esc_attr( $hvnly_agent_position ); ?>"
	<?php endif; ?>
	aria-haspopup="dialog"
	aria-controls="hvnlyContactAgentModal"
>
	<i class="fas fa-comment-dots" aria-hidden="true"></i>
	<span><?php echo esc_html( $hvnly_button_label ); ?></span>
	<span class="screen-reader-text"><?php echo esc_html( $hvnly_aria_label ); ?></span>
</button>
