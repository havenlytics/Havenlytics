<?php
/**
 * URL Field Template
 * 
 * This template displays the URL field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/url-field.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/url-field.php
 * 3. Modify the copied file to customize the URL field display
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

if ( empty( $hvnly_value ) ) {
    return;
}

// Ensure URL has protocol
if ( ! preg_match( '/^https?:\/\//', $hvnly_value ) ) {
    $hvnly_value = 'https://' . $hvnly_value;
}
?>
<div class="hvnly-field hvnly-field--url hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_field_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
        <strong class="hvnly-field__label"><?php echo esc_html( $hvnly_label ); ?>:</strong>
    <?php endif; ?>
    <span class="hvnly-field__value">
        <a href="<?php echo esc_url( $hvnly_value ); ?>" 
           target="_blank" 
           rel="nofollow noopener">
            <?php echo esc_html( $hvnly_value ); ?>
        </a>
    </span>
</div>