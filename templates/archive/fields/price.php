<?php
/**
 * Price Field Template
 * 
 * This template displays the price for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/price.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/price.php
 * 3. Modify the copied file to customize the price display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * $property_data - Property data array (available in some contexts)
 * $field - Field configuration array (available in preset mode)
 * 
 * EXAMPLE OVERRIDE:
 * You can change icons, add CSS classes, or completely redesign the layout.
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

// Get actual property price from database
$hvnly_actual_price = $hvnly_property_data['price'] ?? get_post_meta($hvnly_property_id, '_hvnly_property_price', true);
$hvnly_formatted_price = hvnly_format_price($hvnly_actual_price);

// Only show if we have a price to display
if (!empty($hvnly_formatted_price)) :
?>
<div class="hvnly-property-field-price hvnly-property-field-mode-<?php echo esc_attr($hvnly_mode); ?>">
    <div class="hvnly-property-grid-list-price-header">
        <span class="hvnly-property-price-value"><?php echo esc_html($hvnly_formatted_price); ?></span>
    </div>
</div>
<?php
endif;