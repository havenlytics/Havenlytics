<?php
/**
 * Archive card URL field.
 *
 * @package Havenlytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_property_id = $property_id ?? get_the_ID();
$hvnly_mode          = $mode ?? 'default';
$hvnly_field         = $field ?? array();
$hvnly_source        = sanitize_text_field( (string) ( $hvnly_field['value']['sourceField'] ?? '' ) );
$hvnly_url           = '';

if ( $hvnly_source ) {
	$hvnly_url = function_exists( 'hvnly_resolve_field_meta' )
		? hvnly_resolve_field_meta( (int) $hvnly_property_id, array( 'type' => 'url', 'name' => $hvnly_source ), $hvnly_source )
		: get_post_meta( $hvnly_property_id, $hvnly_source, true );
}

if ( empty( $hvnly_url ) ) {
	return;
}

if ( ! preg_match( '/^https?:\/\//i', (string) $hvnly_url ) ) {
	$hvnly_url = 'https://' . ltrim( (string) $hvnly_url, '/' );
}
?>
<div class="hvnly-property-field-url hvnly-property-contact-info-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
	<div class="hvnly-property-content-feature-icon">
		<i class="fas fa-link" aria-hidden="true"></i>
	</div>
	<a href="<?php echo esc_url( $hvnly_url ); ?>" target="_blank" rel="nofollow noopener"><?php echo esc_html( $hvnly_url ); ?></a>
</div>
