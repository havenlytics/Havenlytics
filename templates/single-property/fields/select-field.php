<?php
/**
 * Select Field Template
 * 
 * This template displays the select field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/select-field.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/select-field.php
 * 3. Modify the copied file to customize the select field display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/fields/
 * @since       2.0.0
 * @deprecated  2.3.0 Legacy fallback template. Prefer single-property/fields/{category}/{type}.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_field       = $args['field'] ?? [];
$hvnly_value       = $args['value'] ?? '';
$hvnly_label       = $args['field_label'] ?? $hvnly_field['label'] ?? '';
$hvnly_name        = $args['field_name'] ?? $hvnly_field['name'] ?? '';
$hvnly_field_options = $args['field_options'] ?? $hvnly_field['options'] ?? [];

if ( empty( $hvnly_value ) ) {
    return;
}

// Initialize display value
$hvnly_display_value = $hvnly_value;

// FIRST PRIORITY: Check if field has options from field configuration
if ( ! empty( $hvnly_field_options ) && is_array( $hvnly_field_options ) ) {
    
    // Direct match with value as key
    if ( isset( $hvnly_field_options[ $hvnly_value ] ) ) {
        $hvnly_display_value = $hvnly_field_options[ $hvnly_value ];
    } 
    // Search through options to find matching value
    else {
        foreach ( $hvnly_field_options as $hvnly_option_value => $hvnly_option_label ) {
            if ( strcasecmp( $hvnly_option_value, $hvnly_value ) === 0 ) {
                $hvnly_display_value = $hvnly_option_label;
                break;
            }
        }
    }
}

// SECOND PRIORITY: Known field option maps (localized helpers)
if ( $hvnly_display_value === $hvnly_value ) {
    $hvnly_fallback_options = array();

    if ( $hvnly_name === '_hvnly_property_cooling' || strpos( $hvnly_name, 'cooling' ) !== false ) {
        $hvnly_fallback_options = function_exists( 'hvnly_get_cooling_field_options' ) ? hvnly_get_cooling_field_options() : array();
    } elseif ( $hvnly_name === '_hvnly_property_heating' || strpos( $hvnly_name, 'heating' ) !== false ) {
        $hvnly_fallback_options = function_exists( 'hvnly_get_heating_field_options' ) ? hvnly_get_heating_field_options() : array();
    } elseif ( $hvnly_name === '_hvnly_property_water' || strpos( $hvnly_name, 'water' ) !== false ) {
        $hvnly_fallback_options = function_exists( 'hvnly_get_water_field_options' ) ? hvnly_get_water_field_options() : array();
    } elseif ( $hvnly_name === '_hvnly_property_location' || strpos( $hvnly_name, 'location' ) !== false ) {
        $hvnly_fallback_options = function_exists( 'hvnly_get_location_field_options' ) ? hvnly_get_location_field_options() : array();
    } elseif ( $hvnly_name === '_hvnly_property_country_location' || strpos( $hvnly_name, 'country' ) !== false ) {
        $hvnly_fallback_options = function_exists( 'hvnly_get_country_field_options' ) ? hvnly_get_country_field_options() : array();
    }

    if ( ! empty( $hvnly_fallback_options ) ) {
        if ( isset( $hvnly_fallback_options[ $hvnly_value ] ) ) {
            $hvnly_display_value = $hvnly_fallback_options[ $hvnly_value ];
        } elseif ( isset( $hvnly_fallback_options[ strtoupper( (string) $hvnly_value ) ] ) ) {
            $hvnly_display_value = $hvnly_fallback_options[ strtoupper( (string) $hvnly_value ) ];
        } elseif ( isset( $hvnly_fallback_options[ strtolower( (string) $hvnly_value ) ] ) ) {
            $hvnly_display_value = $hvnly_fallback_options[ strtolower( (string) $hvnly_value ) ];
        }
    }
}

// THIRD PRIORITY: Make the value look presentable
if ( $hvnly_display_value === $hvnly_value ) {
    // Convert underscores and hyphens to spaces, capitalize words
    $hvnly_display_value = ucwords( str_replace( array( '_', '-' ), ' ', $hvnly_value ) );
}

// Debug for admins (optional)
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'administrator' ) ) {
    echo '<!-- Select Field Debug: ' . esc_html( $hvnly_name ) . ' = ' . esc_html( $hvnly_value ) . ' → ' . esc_html( $hvnly_display_value ) . ' -->';
}

/**
 * Filter the display value for select fields
 *
 * @since 2.2.0
 *
 * @param string $display_value The value to display
 * @param mixed  $value         The stored value
 * @param array  $field         The field configuration
 * @param string $name          The field name
 */
$hvnly_display_value = apply_filters( 'hvnly_select_field_display_value', $hvnly_display_value, $hvnly_value, $hvnly_field, $hvnly_name );
?>
<!-- Single Select Field -->
<div class="hvnly-field hvnly-field--select hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
        <strong class="hvnly-field__label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?>:</strong>
    <?php endif; ?>
    <span class="hvnly-field__value"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_display_value ) ) : esc_html( $hvnly_display_value ); ?></span>
</div>