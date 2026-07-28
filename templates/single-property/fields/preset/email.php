<?php
/**
 * Email Field Template
 * 
 * This template displays the email field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/preset/email.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/preset/email.php
 * 3. Modify the copied file to customize the email field display
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
$hvnly_label       = $args['field_label'] ?? $hvnly_field['label'] ?? __( 'Email', 'havenlytics' );
$hvnly_name        = $args['field_name'] ?? $hvnly_field['name'] ?? '';

if ( empty( $hvnly_value ) || ! is_email( $hvnly_value ) ) {
    return;
}
?>
<div class="hvnly-field hvnly-field--preset-email hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
    <strong class="hvnly-field__label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?>:</strong>
    <span class="hvnly-field__value">
        <a href="mailto:<?php echo esc_attr( $hvnly_value ); ?>" class="hvnly-email-link">
            <?php echo esc_html( $hvnly_value ); ?>
        </a>
    </span>
</div>