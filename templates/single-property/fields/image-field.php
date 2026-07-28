<?php
/**
 * Image Field Template
 * 
 * This template displays the image field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/image-field.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/image-field.php
 * 3. Modify the copied file to customize the image field display
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

$hvnly_image_id = 0;
$hvnly_image_url = '';
$hvnly_image_alt = '';

// Handle different value formats
if ( is_numeric( $hvnly_value ) ) {
    // Attachment ID
    $hvnly_image_id = absint( $hvnly_value );
    $hvnly_image_url = wp_get_attachment_image_url( $hvnly_image_id, 'large' );
    $hvnly_full_image_url = wp_get_attachment_image_url( $hvnly_image_id, 'full' );
    $hvnly_image_alt = get_post_meta( $hvnly_image_id, '_wp_attachment_image_alt', true );
    $hvnly_image_title = get_the_title( $hvnly_image_id );
} elseif ( is_string( $hvnly_value ) ) {
    // URL
    $hvnly_image_url = $hvnly_value;
    $hvnly_full_image_url = $hvnly_value;
    $hvnly_image_alt = $hvnly_label;
    $hvnly_image_title = $hvnly_label;
} elseif ( is_array( $hvnly_value ) && isset( $hvnly_value['url'] ) ) {
    // Array with URL
    $hvnly_image_url = $hvnly_value['url'];
    $hvnly_full_image_url = $hvnly_value['url'] ?? $hvnly_value['url'];
    $hvnly_image_alt = $hvnly_value['alt'] ?? $hvnly_label;
    $hvnly_image_title = $hvnly_value['title'] ?? $hvnly_label;
    $hvnly_image_id = $hvnly_value['id'] ?? 0;
}

if ( empty( $hvnly_image_url ) ) {
    return;
}
?>
<div class="hvnly-field hvnly-field--image hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_field_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
    <div class="hvnly-property-single__default-title"><?php echo function_exists( 'hvnly_translate_ui' ) ? esc_html( hvnly_translate_ui( $hvnly_label ) ) : esc_html( $hvnly_label ); ?></div>
    <?php endif; ?>

    <div class="hvnly-field__image-wrapper">

        <img src="<?php echo esc_url( $hvnly_image_url ); ?>"
            alt="<?php echo esc_attr( $hvnly_image_alt ?: $hvnly_image_title ); ?>" class="hvnly-field__image"
            loading="lazy">

    </div>
</div>