<?php
/**
 * Time Field Template
 * 
 * This template displays the time for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/time.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/time.php
 * 3. Modify the copied file to customize the time display
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
<div class="hvnly-property-field-time hvnly-property-field-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
	<div class="hvnly-property-date-posted">
		<i class="fas fa-clock" aria-hidden="true"></i>
		<span class="hvnly-property-time-text"><?php echo esc_html( $hvnly_time_text ); ?></span>
	</div>
</div>