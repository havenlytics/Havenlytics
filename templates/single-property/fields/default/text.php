<?php
/**
 * Text Field Template
 * 
 * This template displays the text field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/default/text.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/default/text.php
 * 3. Modify the copied file to customize the text field display
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

if ( is_array( $hvnly_value ) || is_object( $hvnly_value ) ) {
    return;
}

if ( is_string( $hvnly_value ) ) {
    $hvnly_trim = trim( $hvnly_value );
    if ( '' === $hvnly_trim || in_array( $hvnly_trim, array( '[]', '{}', 'null', '""' ), true ) ) {
        return;
    }
    // Never echo raw JSON blobs.
    if ( ( '{' === $hvnly_trim[0] || '[' === $hvnly_trim[0] ) && null !== json_decode( $hvnly_trim, true ) ) {
        return;
    }
}

if ( empty( $hvnly_value ) && $hvnly_value !== '0' ) {
    return;
}
?>
<div class="hvnly-field hvnly-field--text hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">

    <?php if ( ! empty( $hvnly_label ) ) : ?>
    <div class="hvnly-property-single__default-title"><?php echo esc_html( $hvnly_label ); ?></div>
    <?php endif; ?>

    <span class="hvnly-field__value"><?php echo esc_html( $hvnly_value ); ?></span>
</div>