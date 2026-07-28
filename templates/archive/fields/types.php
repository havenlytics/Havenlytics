<?php
/**
 * Default Property Types taxonomy Field Template
 * 
 * This template displays the types for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/types.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/types.php
 * 3. Modify the copied file to customize the types display
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

// Get property type terms from taxonomy
$hvnly_property_types = get_the_terms($hvnly_property_id, 'hvnly_prop_types');

if (empty($hvnly_property_types) || is_wp_error($hvnly_property_types)) {
    return;
}

// Check if any property type has display all option enabled
$hvnly_display_all = false;
foreach ($hvnly_property_types as $hvnly_property_type) {
    $hvnly_display_option = get_term_meta($hvnly_property_type->term_id, 'hvnly_prop_type_display_option', true);
    if (!empty($hvnly_display_option)) {
        $hvnly_display_all = true;
        break;
    }
}

// Separate property types based on display option
if ($hvnly_display_all) {
    // Display all property types
    $hvnly_display_property_types = $hvnly_property_types;
    $hvnly_remaining_property_types = array();
} else {
    // Use dropdown behavior
    $hvnly_first_property_type = $hvnly_property_types[0];
    $hvnly_remaining_property_types = array_slice($hvnly_property_types, 1);
    $hvnly_display_property_types = array($hvnly_first_property_type);
}
?>

<div class="hvnly-property-field-types hvnly-default-field <?php echo $hvnly_display_all ? 'hvnly-property-types-display-all' : ''; ?>">
    <?php foreach ($hvnly_display_property_types as $hvnly_property_type): 
        $hvnly_icon_data = get_term_meta($hvnly_property_type->term_id, '_hvnly_advanced_icon_data', true);
        $hvnly_icon_class = is_array($hvnly_icon_data) && isset($hvnly_icon_data['class']) ? $hvnly_icon_data['class'] : '';
        $hvnly_type_image_url = function_exists('hvnly_get_term_advanced_image_url')
            ? hvnly_get_term_advanced_image_url($hvnly_property_type->term_id)
            : '';
    ?>
        <div class="hvnly-property-type-label"><?php echo esc_html__('Type:', 'havenlytics'); ?></div>
        <a href="#" 
           class="hvnly-property-type-link" 
           data-action="filter-property-type" 
           data-taxonomy="hvnly_prop_types"
           data-term-slug="<?php echo esc_attr($hvnly_property_type->slug); ?>"
           data-term-id="<?php echo esc_attr($hvnly_property_type->term_id); ?>">
            <?php if (!empty($hvnly_type_image_url)): ?>
                <img class="hvnly-property-type-image" src="<?php echo esc_url($hvnly_type_image_url); ?>" alt="" />
            <?php elseif (!empty($hvnly_icon_class)): ?>
                <i class="<?php echo esc_attr($hvnly_icon_class); ?>"></i>
            <?php endif; ?>
            <span class="hvnly-property-type-text"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_property_type->name ) ) : esc_html( $hvnly_property_type->name ); ?></span>
        </a>
    <?php endforeach; ?>

    <?php if (!$hvnly_display_all && !empty($hvnly_remaining_property_types)): ?>
        <div class="hvnly-property-type-dropdown-container">
            <div class="hvnly-property-type-more-indicator" title="<?php echo esc_attr__('Show more property types', 'havenlytics'); ?>">
                +<?php echo esc_html(count($hvnly_remaining_property_types)); ?>
            </div>
            
            <div class="hvnly-property-types-dropdown">
                <?php foreach ($hvnly_remaining_property_types as $hvnly_property_type): 
                    $hvnly_icon_data = get_term_meta($hvnly_property_type->term_id, '_hvnly_advanced_icon_data', true);
                    $hvnly_icon_class = is_array($hvnly_icon_data) && isset($hvnly_icon_data['class']) ? $hvnly_icon_data['class'] : '';
                    $hvnly_type_image_url = function_exists('hvnly_get_term_advanced_image_url')
                        ? hvnly_get_term_advanced_image_url($hvnly_property_type->term_id)
                        : '';
                ?>
                    <a href="#" 
                       class="hvnly-property-type-link" 
                       data-action="filter-property-type" 
                       data-taxonomy="hvnly_prop_types"
                       data-term-slug="<?php echo esc_attr($hvnly_property_type->slug); ?>"
                       data-term-id="<?php echo esc_attr($hvnly_property_type->term_id); ?>">
                        <?php if (!empty($hvnly_type_image_url)): ?>
                            <img class="hvnly-property-type-image" src="<?php echo esc_url($hvnly_type_image_url); ?>" alt="" />
                        <?php elseif (!empty($hvnly_icon_class)): ?>
                            <i class="<?php echo esc_attr($hvnly_icon_class); ?>"></i>
                        <?php endif; ?>
                        <span class="hvnly-property-type-text"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_property_type->name ) ) : esc_html( $hvnly_property_type->name ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>