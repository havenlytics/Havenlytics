<?php
/**
 * Thumbnail Top Left DateTime Template
 * 
 * Displays property date and time information in the thumbnail top left section.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/datetime.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/datetime.php
 * 3. Modify the copied file to customize date and time display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * 
 * EXAMPLE OVERRIDE:
 * You can change date and time layout, colors, icons, or implement carousel display.
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id   = $property_id ?? get_the_ID();
$hvnly_property_data = $property_data ?? array();
$hvnly_mode          = $mode ?? 'default';
$hvnly_field         = $field ?? array(); // Contains saved sample data, ignored.

// Use our custom function to get smart time format.
$hvnly_time_text = hvnly_get_property_time( $hvnly_property_id, 'relative' );

// Alternative formats (commented examples):
// $hvnly_time_text = hvnly_get_property_time( $hvnly_property_id, 'relative' ); // "2 days ago".
// $hvnly_time_text = hvnly_get_property_time( $hvnly_property_id, 'time_ago' ); // "2 days ago".
// $hvnly_time_text = hvnly_get_property_time( $hvnly_property_id, 'short' ); // "Dec 5, 2024".
// $hvnly_time_text = hvnly_get_property_time( $hvnly_property_id, 'full' ); // "December 5, 2024 at 2:30 PM".
// $hvnly_time_text = hvnly_get_property_time( $hvnly_property_id, 'custom', 'M j, Y \a\t g:i A' ); // Custom format.

?>
<div class="hvnly-property-field-time">
	<div class="hvnly-property-date-posted hvnly-property-contact-info-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
            <div class="hvnly-property-content-feature-icon">
                <i class="fas fa-clock"></i>
            </div>
		<span class="hvnly-property-time-text"><?php echo esc_html( $hvnly_time_text ); ?></span>
	</div>
</div>