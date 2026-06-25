<?php
/**
 * Grid Card footer top Template
 * 
 * Displays property phone number in the grid card footer top section.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/phone.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/phone.php
 * 3. Modify the copied file to customize phone number display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * 
 * EXAMPLE OVERRIDE:
 * You can change phone number layout, colors, icons, or implement carousel display.
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

$hvnly_phone_number = get_post_meta($hvnly_property_id, 'preset_hvnly_property_field_phone', true);
?>

    <?php if ($hvnly_phone_number): ?>
        <div class="hvnly-property-field-phone hvnly-property-contact-info-mode-<?php echo esc_attr($hvnly_mode); ?>">
            <div class="hvnly-property-content-feature-icon">
                <i class="fas fa-phone"></i>
            </div>
            <a href="tel:<?php echo esc_attr($hvnly_phone_number); ?>"><?php echo esc_html($hvnly_phone_number); ?></a>
        </div>
    <?php endif; ?>