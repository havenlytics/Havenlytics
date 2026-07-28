<?php
/**
 * Property view button Field Template
 * 
 * This template displays the view button for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/view-btn.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/view-btn.php
 * 3. Modify the copied file to customize the view button display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */


if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_field_data = $field ?? [];
$hvnly_field_value = $hvnly_field_data['value'] ?? [];
$hvnly_button_text = isset( $hvnly_field_value['text'] ) && '' !== (string) $hvnly_field_value['text']
	? (string) $hvnly_field_value['text']
	: __( 'View Property', 'havenlytics' );
$hvnly_button_url = $hvnly_field_value['url'] ?? get_permalink($property_id);
$hvnly_button_display = function_exists( 'hvnly_translate_ui' )
	? hvnly_translate_ui( $hvnly_button_text )
	: $hvnly_button_text;
?>

<div class="hvnly-field-view-btn hvnly-default-field">
    <a href="<?php echo esc_url($hvnly_button_url); ?>" class="hvnly-btn-primary" aria-label="<?php 
        echo esc_attr(sprintf(
            /* translators: %s: Property title */
            __('View details for %s', 'havenlytics'), get_the_title($property_id))); ?>">
        <i class="fas fa-external-link-alt" aria-hidden="true"></i>
        <span class="hvnly-view-btn-text"><?php echo esc_html( $hvnly_button_display ); ?></span>
    </a>
</div>