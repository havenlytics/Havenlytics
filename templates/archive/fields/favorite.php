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

/*
 * 3.4.0: render the saved state on the server for signed-in users so the
 * heart is correct in the first paint (no flash of "unsaved"). Guests are
 * hydrated client-side from localStorage instead — see hvnly-favorites.js.
 */
$hvnly_is_favorited = function_exists( 'hvnly_is_property_favorited' )
	? hvnly_is_property_favorited( $hvnly_property_id )
	: false;

$hvnly_favorite_label = $hvnly_is_favorited
	? __( 'Remove from favorites', 'havenlytics' )
	: __( 'Add to favorites', 'havenlytics' );

// 3.4.0: thumbnail + title for the Favorite toast, so it can identify the
// property without a round trip (and works for signed-out visitors).
$hvnly_toast_data = function_exists( 'hvnly_get_favorite_toast_data' )
	? hvnly_get_favorite_toast_data( $hvnly_property_id )
	: array( 'title' => '', 'thumb' => '' );
?>
<button type="button"
	class="hvnly-property--grid-list--favorite<?php echo $hvnly_is_favorited ? ' is-favorited' : ''; ?>"
	data-hvnly-favorite="1"
	data-property-id="<?php echo esc_attr( $hvnly_property_id ); ?>"
	data-property-title="<?php echo esc_attr( $hvnly_toast_data['title'] ); ?>"
	data-property-thumb="<?php echo esc_url( $hvnly_toast_data['thumb'] ); ?>"
	aria-pressed="<?php echo $hvnly_is_favorited ? 'true' : 'false'; ?>"
	aria-label="<?php echo esc_attr( $hvnly_favorite_label ); ?>">
	<i class="<?php echo $hvnly_is_favorited ? 'fas' : 'far'; ?> fa-heart" aria-hidden="true"></i>
</button>
