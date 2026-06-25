<?php
/**
 * Fallback Field Template
 * 
 * This template is used when no specific template exists for a field type.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/fallback-field.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/fallback-field.php
 * 3. Modify the copied file to customize the fallback field display
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
$hvnly_field_type   = $args['field_type'] ?? $hvnly_field['type'] ?? 'text';

if ( empty( $hvnly_value ) ) {
    return;
}

// Try to format the value nicely
if ( is_array( $hvnly_value ) ) {
    $hvnly_display_value = implode( ', ', array_map( 'esc_html', $hvnly_value ) );
} elseif ( is_serialized( $hvnly_value ) ) {
    $hvnly_unserialized = maybe_unserialize( $hvnly_value );
    if ( is_array( $hvnly_unserialized ) ) {
        $hvnly_display_value = implode( ', ', array_map( 'esc_html', $hvnly_unserialized ) );
    } else {
        $hvnly_display_value = esc_html( $hvnly_value );
    }
} else {
    $hvnly_display_value = esc_html( $hvnly_value );
}
?>
<div class="hvnly-field hvnly-field--fallback hvnly-field--<?php echo esc_attr( $hvnly_field_type ); ?> hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_field_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
        <strong class="hvnly-field__label"><?php echo esc_html( $hvnly_label ); ?>:</strong>
    <?php endif; ?>
    <span class="hvnly-field__value"><?php echo wp_kses_post( $hvnly_display_value ); ?></span>
</div>