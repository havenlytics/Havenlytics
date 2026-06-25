<?php
/**
 * Bedrooms Field Template
 * 
 * This template displays the bedrooms field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/preset/bedrooms.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/preset/bedrooms.php
 * 3. Modify the copied file to customize the bedrooms field display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/fields/preset
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_field       = $args['field'] ?? [];
$hvnly_value       = $args['value'] ?? '';
$hvnly_label       = $args['field_label'] ?? $hvnly_field['label'] ?? __( 'Bedrooms', 'havenlytics' );
$hvnly_name        = $args['field_name'] ?? $hvnly_field['name'] ?? '';

if ( empty( $hvnly_value ) && $hvnly_value !== 0 && $hvnly_value !== '0' ) {
    return;
}
?>
<div class="hvnly-field hvnly-field--preset-bedrooms hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
    <strong class="hvnly-field__label"><?php echo esc_html( $hvnly_label ); ?>:</strong>
    <span class="hvnly-field__value"><?php echo esc_html( $hvnly_value ); ?></span>
</div>