<?php
/**
 * Date field template (default category).
 *
 * @package Havenlytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_field      = $args['field'] ?? array();
$hvnly_value      = $args['field_value'] ?? $args['value'] ?? '';
$hvnly_label      = $args['field_label'] ?? $hvnly_field['label'] ?? '';
$hvnly_field_name = $args['field_name'] ?? $hvnly_field['name'] ?? '';

if ( empty( $hvnly_value ) ) {
	return;
}

$hvnly_timestamp = strtotime( (string) $hvnly_value );
$hvnly_display   = ( false !== $hvnly_timestamp )
	? date_i18n( get_option( 'date_format' ), $hvnly_timestamp )
	: (string) $hvnly_value;
?>
<div class="hvnly-field hvnly-field--date hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_field_name ) ); ?>">
	<?php if ( ! empty( $hvnly_label ) ) : ?>
		<strong class="hvnly-field__label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?>:</strong>
	<?php endif; ?>
	<span class="hvnly-field__value"><?php echo esc_html( $hvnly_display ); ?></span>
</div>
