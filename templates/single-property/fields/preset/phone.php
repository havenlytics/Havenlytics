<?php
/**
 * Phone Field Template
 * 
 * This template displays the phone field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/preset/phone.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/preset/phone.php
 * 3. Modify the copied file to customize the phone field display
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
$hvnly_label       = $args['field_label'] ?? $hvnly_field['label'] ?? __( 'Phone', 'havenlytics' );
$hvnly_name        = $args['field_name'] ?? $hvnly_field['name'] ?? '';

if ( empty( $hvnly_value ) ) {
    return;
}

// Clean phone number for tel link
$hvnly_tel_link = preg_replace( '/[^0-9+]/', '', $hvnly_value );
?>
<div class="hvnly-field hvnly-field--preset-phone hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
    <strong class="hvnly-field__label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?>:</strong>
    <span class="hvnly-field__value">
        <a href="tel:<?php echo esc_attr( $hvnly_tel_link ); ?>" class="hvnly-phone-link">
            <?php echo esc_html( $hvnly_value ); ?>
        </a>
    </span>
</div>