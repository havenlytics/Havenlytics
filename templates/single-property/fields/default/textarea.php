<?php
/**
 * Textarea Field Template
 * 
 * This template displays the textarea field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/default/textarea.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/default/textarea.php
 * 3. Modify the copied file to customize the textarea field display
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

if ( empty( $hvnly_value ) ) {
    return;
}
?>
<div class="hvnly-field hvnly-field--textarea hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
    <div class="hvnly-property-single__default-title"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?></div>
    <?php endif; ?>
    <div class="hvnly-field__value hvnly-field__value--textarea">
        <?php echo wp_kses_post( wpautop( $hvnly_value ) ); ?>
    </div>
</div>