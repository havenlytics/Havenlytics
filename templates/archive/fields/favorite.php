<?php
/**
 * Grid Card footer top Template
 *
 * Displays property favorite button in the grid card footer top section.
 *
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/favorite.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/favorite.php
 * 3. Modify the copied file to customize favorite button display
 *
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 *
 * EXAMPLE OVERRIDE:
 * You can change favorite button layout, colors, icons, or implement carousel display.
 *
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Sprint 31D: guard against contexts that do not pass $property_id.
$hvnly_property_id = isset( $property_id ) ? $property_id : get_the_ID();
?>
<button type="button" class="hvnly-property--grid-list--favorite" data-property-id="<?php echo esc_attr( $hvnly_property_id ); ?>"
	aria-pressed="false"
	aria-label="<?php esc_attr_e( 'Add to favorites', 'havenlytics' ); ?>">
	<i class="far fa-heart" aria-hidden="true"></i>
</button>
