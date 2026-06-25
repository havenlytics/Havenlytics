<?php
/**
 * Excerpt Field Template
 * 
 * This template displays the excerpt for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/sections/excerpt.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/sections/excerpt.php
 * 3. Modify the copied file to customize the excerpt display
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
$hvnly_section       = $section ?? [];
?>

<div class="hvnly-property-field-excerpt hvnly-property-field-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
    <div class="hvnly-property--grid-list--excerpt">
        <?php
        // In preset mode, check if excerpt field is configured.
        if ( $hvnly_mode === 'preset' ) {
            // If section has excerpt field, use it.
            if ( ! empty( $hvnly_section['fields'] ) ) {
                foreach ( $hvnly_section['fields'] as $hvnly_field ) {
                    if ( 'excerpt' === $hvnly_field['type'] ) {
                        hvnly_render_field( 'excerpt', $hvnly_property_id, $hvnly_property_data, $hvnly_mode );
                        break;
                    }
                }
            }
        } else {
            // Default mode always shows excerpt.
            hvnly_render_field( 'excerpt', $hvnly_property_id, $hvnly_property_data, $hvnly_mode );
        }
        ?>
    </div>
</div>