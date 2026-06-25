<?php
/**
 * Footer Field Template
 * 
 * This template displays the footer for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/sections/footer.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/sections/footer.php
 * 3. Modify the copied file to customize the footer display
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
$hvnly_all_sections  = $all_sections ?? [];
$hvnly_footer_sections = $footer_sections ?? []; // New parameter for combined footer sections
?>

<div class="hvnly-property-field-footer hvnly-property-field-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
    <div class="hvnly-property--grid-list--footer">
        <?php
        if ( $hvnly_mode === 'default' ) {
            // Default mode: include view button
            hvnly_load_field_template( 'view-btn', $hvnly_property_id, $hvnly_property_data );
        } elseif ( $hvnly_mode === 'preset' ) {
            // Preset mode: render all footer sections' fields
            if ( ! empty( $hvnly_footer_sections ) ) {
                foreach ( $hvnly_footer_sections as $hvnly_footer_section ) {
                    if ( ! empty( $hvnly_footer_section['fields'] ) ) {
                        foreach ( $hvnly_footer_section['fields'] as $hvnly_field ) {
                            $hvnly_field_type = $hvnly_field['type'] ?? '';
                            hvnly_render_field( $hvnly_field_type, $hvnly_property_id, $hvnly_property_data, $hvnly_mode, $hvnly_field );
                        }
                    }
                }
            } elseif ( ! empty( $hvnly_section['fields'] ) ) {
                // Fallback to single section for backward compatibility
                foreach ( $hvnly_section['fields'] as $hvnly_field ) {
                    $hvnly_field_type = $hvnly_field['type'] ?? '';
                    hvnly_render_field( $hvnly_field_type, $hvnly_property_id, $hvnly_property_data, $hvnly_mode, $hvnly_field );
                }
            }
        }
        ?>
        
      
        <?php
        // Include property types button in default mode.
        if ( $hvnly_mode === 'default' ) {
            hvnly_load_field_template( 'types', $hvnly_property_id, $hvnly_property_data );
        }
        ?>
        
    </div>
</div>