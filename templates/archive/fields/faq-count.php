<?php
/**
 * Archive card FAQ count field.
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
$hvnly_count         = 0;

if ( $hvnly_source ) {
	$hvnly_raw = function_exists( 'hvnly_resolve_field_meta' )
		? hvnly_resolve_field_meta(
			(int) $hvnly_property_id,
			array(
				'type'       => 'faq',
				'group_type' => 'faq',
				'metaKey'    => 'faqs',
				'name'       => $hvnly_source,
			),
			$hvnly_source
		)
		: get_post_meta( $hvnly_property_id, $hvnly_source, true );

	if ( is_string( $hvnly_raw ) && '' !== $hvnly_raw ) {
		$hvnly_decoded = json_decode( $hvnly_raw, true );
		if ( is_array( $hvnly_decoded ) ) {
			$hvnly_count = count( array_filter( $hvnly_decoded, 'is_array' ) );
		}
	} elseif ( is_array( $hvnly_raw ) ) {
		$hvnly_count = count( array_filter( $hvnly_raw, 'is_array' ) );
	}
}

if ( $hvnly_count < 1 ) {
	return;
}

$hvnly_label = sprintf(
	/* translators: %d: number of FAQ items */
	_n( '%d FAQ', '%d FAQs', $hvnly_count, 'havenlytics' ),
	$hvnly_count
);
?>
<div class="hvnly-property-field-faq-count hvnly-property-contact-info-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
	<div class="hvnly-property-content-feature-icon">
		<i class="fas fa-question-circle" aria-hidden="true"></i>
	</div>
	<span class="hvnly-property-faq-count-text"><?php echo esc_html( $hvnly_label ); ?></span>
</div>
