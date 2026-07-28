<?php
/**
 * Website Field Template
 * 
 * This template displays the website field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/preset/website.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/preset/website.php
 * 3. Modify the copied file to customize the website field display
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
$hvnly_label       = $args['field_label'] ?? $hvnly_field['label'] ?? __( 'Website', 'havenlytics' );
$hvnly_name        = $args['field_name'] ?? $hvnly_field['name'] ?? '';

if ( empty( $hvnly_value ) ) {
    return;
}

// Ensure URL has protocol
if ( ! preg_match( '/^https?:\/\//', $hvnly_value ) ) {
    $hvnly_url = 'https://' . $hvnly_value;
} else {
    $hvnly_url = $hvnly_value;
}
?>
<div class="hvnly-field hvnly-field--preset-website hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
    <strong class="hvnly-field__label"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?>:</strong>
    <span class="hvnly-field__value">
        <a href="<?php echo esc_url( $hvnly_url ); ?>" target="_blank" rel="noopener">
            <?php echo esc_html( $hvnly_value ); ?>
        </a>
    </span>
</div>