<?php
/**
 * Number Field Template
 * 
 * This template displays the number field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/default/number.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/default/number.php
 * 3. Modify the copied file to customize the number field display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/fields/default
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_field       = $args['field'] ?? [];
$hvnly_value       = $args['value'] ?? '';
$hvnly_label       = $args['field_label'] ?? $hvnly_field['label'] ?? '';
$hvnly_name        = $args['field_name'] ?? $hvnly_field['name'] ?? '';

if ( empty( $hvnly_value ) && $hvnly_value !== 0 && $hvnly_value !== '0' ) {
    return;
}

// Format number
$hvnly_formatted_value = number_format_i18n( floatval( $hvnly_value ) );

// Check if this is a price field
if ( strpos( $hvnly_name, 'price' ) !== false || strpos( $hvnly_label, 'Price' ) !== false ) {
    $hvnly_currency_symbol = apply_filters( 'hvnly_currency_symbol', '$' );
    $hvnly_formatted_value = $hvnly_currency_symbol . $hvnly_formatted_value;
}

// Check if this is an area field
if ( strpos( $hvnly_name, 'sqft' ) !== false || strpos( $hvnly_name, 'area' ) !== false || strpos( $hvnly_label, 'sq ft' ) !== false ) {
    $hvnly_formatted_value .= ' ' . __( 'sq ft', 'havenlytics' );
}
?>
<div class="hvnly-field hvnly-field--number hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
    <div class="hvnly-property-single__default-title"><?php echo esc_html( $hvnly_label ); ?></div>
    <?php endif; ?>
    <span class="hvnly-field__value"><?php echo esc_html( $hvnly_formatted_value ); ?></span>
</div>