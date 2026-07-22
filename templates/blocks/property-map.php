<?php
/**
 * Property Map block — live frontend map container + control toolbar.
 *
 * Markers come from the existing hvnly_get_properties_for_map AJAX endpoint
 * (reused). Rendering is handled by the block map controller (hvnly-block-map.js)
 * on the bundled Leaflet.
 *
 * Expects $args: config (array).
 *
 * @package     Havenlytics
 * @subpackage  Templates/blocks
 * @since       3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_a = ( isset( $args ) && is_array( $args ) ) ? $args : array();
$hvnly_config  = isset( $hvnly_a['config'] ) && is_array( $hvnly_a['config'] ) ? $hvnly_a['config'] : array();

$hvnly_uid    = isset( $hvnly_config['canvasId'] ) ? (string) $hvnly_config['canvasId'] : 'hvnly-block-map';
$hvnly_height = isset( $hvnly_config['height'] ) ? max( 240, absint( $hvnly_config['height'] ) ) : 520;
?>
<div
	class="hvnly-block-map"
	data-hvnly-block-map
	data-config="<?php echo esc_attr( (string) wp_json_encode( $hvnly_config ) ); ?>"
	style="--hvnly-block-map-height: <?php echo esc_attr( (string) $hvnly_height ); ?>px;"
>
	<div class="hvnly-block-map__canvas" id="<?php echo esc_attr( $hvnly_uid ); ?>"></div>

	<div class="hvnly-block-map__toolbar">
		<button type="button" class="hvnly-block-map__btn" data-hvnly-block-map-action="zoom-in" aria-label="<?php esc_attr_e( 'Zoom in', 'havenlytics' ); ?>">
			<i class="fas fa-plus" aria-hidden="true"></i>
		</button>
		<button type="button" class="hvnly-block-map__btn" data-hvnly-block-map-action="zoom-out" aria-label="<?php esc_attr_e( 'Zoom out', 'havenlytics' ); ?>">
			<i class="fas fa-minus" aria-hidden="true"></i>
		</button>
		<button type="button" class="hvnly-block-map__btn" data-hvnly-block-map-action="fit" aria-label="<?php esc_attr_e( 'Fit all properties', 'havenlytics' ); ?>">
			<i class="fas fa-expand" aria-hidden="true"></i>
		</button>
		<?php if ( ! empty( $hvnly_config['geolocate'] ) ) : ?>
			<button type="button" class="hvnly-block-map__btn" data-hvnly-block-map-action="locate" aria-label="<?php esc_attr_e( 'My location', 'havenlytics' ); ?>">
				<i class="fas fa-location-arrow" aria-hidden="true"></i>
			</button>
		<?php endif; ?>
	</div>

	<div class="hvnly-block-map__loading"><?php esc_html_e( 'Loading map…', 'havenlytics' ); ?></div>
</div>
