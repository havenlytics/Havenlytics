<?php
/**
 * Header Field Template
 * 
 * This template displays the header for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/sections/header.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/sections/header.php
 * 3. Modify the copied file to customize the header display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/sections
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id   = $property_id ?? get_the_ID();
$hvnly_property_data = $property_data ?? hvnly_get_property_data( $hvnly_property_id );
$hvnly_mode          = $mode ?? 'default';
$hvnly_all_sections  = $all_sections ?? [];
$hvnly_show_avatar   = $show_avatar ?? false;
$hvnly_section       = $section ?? [];
?>

<div class="hvnly-property-field-header hvnly-property-field-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
    <div class="hvnly-property--grid-list--header">
        <?php
        // Include avatar if enabled.
        if ( $hvnly_show_avatar || $hvnly_mode === 'default' ) {
            hvnly_render_field( 'avatar', $hvnly_property_id, $hvnly_property_data, $hvnly_mode );
        }
        ?>
        <div class="hvnly-property--grid-list--title-header">
            <?php
            // In preset mode, render fields in the order specified in the section.
            if ( $hvnly_mode === 'preset' && ! empty( $hvnly_section['fields'] ) ) {
                foreach ( $hvnly_section['fields'] as $hvnly_field ) {
                    $hvnly_field_type = $hvnly_field['type'] ?? '';
                    hvnly_render_field( $hvnly_field_type, $hvnly_property_id, $hvnly_property_data, $hvnly_mode, $hvnly_field );
                }
            } else {
                // Default mode: always show title, price, rating, location in fixed order.
                hvnly_render_field( 'title', $hvnly_property_id, $hvnly_property_data, $hvnly_mode );
                hvnly_render_field( 'price', $hvnly_property_id, $hvnly_property_data, $hvnly_mode );
                // hvnly_render_field( 'rating', $hvnly_property_id, $hvnly_property_data, $hvnly_mode );
                hvnly_render_field( 'location', $hvnly_property_id, $hvnly_property_data, $hvnly_mode );
            }
            ?>
        </div>
    </div>
</div>