<?php
/**
 * Grid Card middle center Template
 * 
 * Displays property features in the grid card middle center section.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/features.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/features.php
 * 3. Modify the copied file to customize features display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * 
 * EXAMPLE OVERRIDE:
 * You can change features layout, colors, icons, or implement carousel display.
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = $property_id ?? get_the_ID();
$hvnly_property_data = $property_data ?? hvnly_get_property_data($hvnly_property_id);
$hvnly_mode = $mode ?? 'default';
$hvnly_all_sections = $all_sections ?? [];
?>

<div class="hvnly-property-field-features hvnly-property-field-mode-<?php echo esc_attr($hvnly_mode); ?>">
    <div class="hvnly-property--grid-list--features">
        <?php
        // Include beds
        // In default mode OR preset mode with beds field configured
        if ($hvnly_mode === 'default' || ($hvnly_mode === 'preset' && hvnly_is_field_configured($hvnly_all_sections, 'beds'))) {
            hvnly_render_field('beds', $hvnly_property_id, $hvnly_property_data, $hvnly_mode);
        }
        ?>
        <?php
        // Include baths
        // In default mode OR preset mode with baths field configured
        if ($hvnly_mode === 'default' || ($hvnly_mode === 'preset' && hvnly_is_field_configured($hvnly_all_sections, 'baths'))) {
            hvnly_render_field('baths', $hvnly_property_id, $hvnly_property_data, $hvnly_mode);
        }
        ?>
        <?php
        // Include receptions
        // In default mode OR preset mode with receptions field configured
        if ($hvnly_mode === 'default' || ($hvnly_mode === 'preset' && hvnly_is_field_configured($hvnly_all_sections, 'receptions'))) {
            hvnly_render_field('receptions', $hvnly_property_id, $hvnly_property_data, $hvnly_mode);
        }
        ?>
        <?php
        // Include sqft
        // In default mode OR preset mode with sqft field configured
        if ($hvnly_mode === 'default' || ($hvnly_mode === 'preset' && hvnly_is_field_configured($hvnly_all_sections, 'sqft'))) {
            hvnly_render_field('sqft', $hvnly_property_id, $hvnly_property_data, $hvnly_mode);
        }
        ?>
        <?php
        // Include generic feature
        // Only in preset mode with feature field configured (not in default mode)
        if ($hvnly_mode === 'preset' && hvnly_is_field_configured($hvnly_all_sections, 'feature')) {
            hvnly_render_field('feature', $hvnly_property_id, $hvnly_property_data, $hvnly_mode);
        }
        ?>
    </div>
</div>