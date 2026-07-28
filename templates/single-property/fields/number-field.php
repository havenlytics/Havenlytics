<?php
/**
 * Number Field Template
 * 
 * This template displays the number field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/number-field.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/number-field.php
 * 3. Modify the copied file to customize the number field display
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
$hvnly_field        = $args['field'] ?? array();
$hvnly_value        = $args['field_value'] ?? $args['value'] ?? '';
$hvnly_label        = $args['field_label'] ?? $hvnly_field['label'] ?? '';
$hvnly_property_id  = $args['property_id'] ?? get_the_ID();
$hvnly_field_name   = $args['field_name'] ?? $hvnly_field['name'] ?? '';

if ( empty( $hvnly_value ) && $hvnly_value !== 0 && $hvnly_value !== '0' ) {
    return;
}

// Format number based on field context
$hvnly_formatted_value = number_format_i18n( floatval( $hvnly_value ) );

// Check if this is a price field (by name or context)
if ( strpos( $hvnly_field_name, 'price' ) !== false || strpos( $hvnly_label, 'Price' ) !== false ) {
    if ( function_exists( 'hvnly_format_numeric_price' ) ) {
        $hvnly_formatted_value = hvnly_format_numeric_price( $hvnly_value );
    } elseif ( function_exists( 'hvnly_format_price' ) ) {
        $hvnly_formatted_value = hvnly_format_price( $hvnly_value );
    } else {
        $hvnly_currency_symbol = apply_filters( 'hvnly_currency_symbol', '$' );
        $hvnly_formatted_value = $hvnly_currency_symbol . $hvnly_formatted_value;
    }
}

// Check if this is an area field (sq ft)
if ( strpos( $hvnly_field_name, 'sqft' ) !== false || strpos( $hvnly_field_name, 'area' ) !== false || strpos( $hvnly_label, 'sq ft' ) !== false ) {
    $hvnly_formatted_value .= ' ' . __( 'sq ft', 'havenlytics' );
}
?>
<div class="hvnly-field hvnly-field--number hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_field_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
        <strong class="hvnly-field__label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?>:</strong>
    <?php endif; ?>
    <span class="hvnly-field__value"><?php echo esc_html( $hvnly_formatted_value ); ?></span>
</div>