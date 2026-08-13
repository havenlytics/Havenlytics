<?php
/**
 * Property Card Compare field (Pro-activated).
 *
 * HOW TO OVERRIDE:
 * Copy to: your-theme/havenlytics/archive/fields/compare.php
 *
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! apply_filters( 'hvnly_compare_card_field_enabled', false ) ) {
	return;
}

$hvnly_property_id = isset( $property_id ) ? (int) $property_id : (int) get_the_ID();
if ( $hvnly_property_id <= 0 ) {
	return;
}

$hvnly_toast_data = function_exists( 'hvnly_get_favorite_toast_data' )
	? hvnly_get_favorite_toast_data( $hvnly_property_id )
	: array( 'title' => '', 'thumb' => '' );

$hvnly_compare_label = __( 'Add to compare', 'havenlytics' );
?>
<button type="button"
	class="hvnly-action-toggle hvnly-compare-toggle hvnly-compare-toggle--card hvnly-property--grid-list--compare"
	data-hvnly-compare="1"
	data-hvnly-compare-native="1"
	data-property-id="<?php echo esc_attr( (string) $hvnly_property_id ); ?>"
	data-property-title="<?php echo esc_attr( (string) ( $hvnly_toast_data['title'] ?? '' ) ); ?>"
	data-property-thumb="<?php echo esc_url( (string) ( $hvnly_toast_data['thumb'] ?? '' ) ); ?>"
	aria-pressed="false"
	aria-label="<?php echo esc_attr( $hvnly_compare_label ); ?>">
	<span class="hvnly-compare-toggle__icon" aria-hidden="true"></span>
</button>
