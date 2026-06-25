<?php
/**
 * Checkbox Field Template
 * 
 * This template displays the checkbox field values.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/fields/default/checkbox.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/fields/default/checkbox.php
 * 3. Modify the copied file to customize the checkbox field display
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

// For checkboxes, we show Yes/No
$hvnly_display_value = $hvnly_value ? __( 'Yes', 'havenlytics' ) : __( 'No', 'havenlytics' );

// If there are options (multiple checkboxes)
if ( ! empty( $hvnly_field['options'] ) && is_array( $hvnly_field['options'] ) ) {
    $hvnly_selected_values = is_array( $hvnly_value ) ? $hvnly_value : [ $hvnly_value ];
    $hvnly_selected_options = [];
    
    foreach ( $hvnly_field['options'] as $hvnly_option_value => $hvnly_option_label ) {
        if ( in_array( $hvnly_option_value, $hvnly_selected_values ) ) {
            $hvnly_selected_options[] = $hvnly_option_label;
        }
    }
    
    if ( empty( $hvnly_selected_options ) ) {
        return;
    }
    ?>
    <div class="hvnly-field hvnly-field--checkbox-group hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
        <?php if ( ! empty( $hvnly_label ) ) : ?>
            <strong class="hvnly-field__label"><?php echo esc_html( $hvnly_label ); ?>:</strong>
        <?php endif; ?>
        <ul class="hvnly-field__checkbox-list">
            <?php foreach ( $hvnly_selected_options as $hvnly_option ) : ?>
                <li class="hvnly-field__checkbox-item"><?php echo esc_html( $hvnly_option ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return;
}

// Single checkbox
?>
<div class="hvnly-field hvnly-field--checkbox hvnly-field--<?php echo esc_attr( sanitize_title( $hvnly_name ) ); ?>">
    <?php if ( ! empty( $hvnly_label ) ) : ?>
        <strong class="hvnly-field__label"><?php echo esc_html( $hvnly_label ); ?>:</strong>
    <?php endif; ?>
    <span class="hvnly-field__value hvnly-field__value--checkbox">
        <?php echo esc_html( $hvnly_display_value ); ?>
    </span>
</div>