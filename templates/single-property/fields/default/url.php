<?php
/**
 * URL field template (default category).
 *
 * @package Havenlytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_field       = $args['field'] ?? array();
$hvnly_value       = $args['field_value'] ?? $args['value'] ?? '';
$hvnly_label       = $args['field_label'] ?? $hvnly_field['label'] ?? '';
$hvnly_field_name  = $args['field_name'] ?? $hvnly_field['name'] ?? '';

if ( empty( $hvnly_value ) ) {
	return;
}

$hvnly_url = (string) $hvnly_value;
if ( ! preg_match( '/^https?:\/\//i', $hvnly_url ) ) {
	$hvnly_url = 'https://' . ltrim( $hvnly_url, '/' );
}
?>
<div class="hvnly-field hvnly-field--url hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_field_name ) ); ?>">
	<?php if ( ! empty( $hvnly_label ) ) : ?>
		<strong class="hvnly-field__label"><?php echo esc_html( $hvnly_label ); ?>:</strong>
	<?php endif; ?>
	<span class="hvnly-field__value">
		<a href="<?php echo esc_url( $hvnly_url ); ?>" target="_blank" rel="nofollow noopener">
			<?php echo esc_html( $hvnly_url ); ?>
		</a>
	</span>
</div>
