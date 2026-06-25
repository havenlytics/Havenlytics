<?php
/**
 * Title Field Template
 * 
 * This template displays the title for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/title.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/title.php
 * 3. Modify the copied file to customize the title display
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

// Get the actual property title from database
$hvnly_property_title = $hvnly_property_data['title'] ?? get_the_title($hvnly_property_id);
$hvnly_property_permalink = $hvnly_property_data['permalink'] ?? get_permalink($hvnly_property_id);
?>

<div class="hvnly-field-title hvnly-property-field-mode-<?php echo esc_attr($hvnly_mode); ?>">
    <h3 class="hvnly-property--grid-list--title">
        <a href="<?php echo esc_url($hvnly_property_permalink); ?>">
            <?php echo esc_html($hvnly_property_title); ?>
        </a>
    </h3>
</div>