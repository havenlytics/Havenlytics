<?php
/**
 * Archive card date field.
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
$hvnly_raw           = '';

if ( $hvnly_source ) {
	$hvnly_raw = function_exists( 'hvnly_resolve_field_meta' )
		? hvnly_resolve_field_meta( (int) $hvnly_property_id, array( 'type' => 'date', 'name' => $hvnly_source ), $hvnly_source )
		: get_post_meta( $hvnly_property_id, $hvnly_source, true );
}

if ( empty( $hvnly_raw ) ) {
	return;
}

$hvnly_timestamp = strtotime( (string) $hvnly_raw );
$hvnly_display   = ( false !== $hvnly_timestamp )
	? date_i18n( get_option( 'date_format' ), $hvnly_timestamp )
	: (string) $hvnly_raw;
?>
<div class="hvnly-property-field-date hvnly-property-contact-info-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
	<div class="hvnly-property-content-feature-icon">
		<i class="fas fa-calendar-alt" aria-hidden="true"></i>
	</div>
	<span class="hvnly-property-date-text"><?php echo esc_html( $hvnly_display ); ?></span>
</div>
