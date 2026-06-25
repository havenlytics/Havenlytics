<?php
/**
 * Location Field Template
 * 
 * This template displays the property location/address.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/location.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/location.php
 * 3. Modify the copied file to customize the location display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $property_data - Property data array
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * 
 * EXAMPLE OVERRIDE:
 * You can add Google Maps link, change formatting, or display city/state separately.
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = $property_id ?? get_the_ID();
$hvnly_property_data = $property_data ?? hvnly_get_property_data($hvnly_property_id);
$hvnly_mode = $mode ?? 'default';

// Get actual property location from database
$hvnly_actual_location = $hvnly_property_data['address'] ?? get_post_meta($hvnly_property_id, '_hvnly_property_street', true);

// Only show if we have a location to display
if (!empty($hvnly_actual_location)) :
?>
<div class="hvnly-field-location hvnly-property-field-mode-<?php echo esc_attr($hvnly_mode); ?>">
    <div class="hvnly-property--grid-list--location">
        <i class="fas fa-map-marker-alt"></i>
        <span><?php echo esc_html($hvnly_actual_location); ?></span>
    </div>
</div>
<?php
endif;