<?php
/**
 * Schedule Tour Field Template
 * 
 * This template displays the schedule tour button for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/schedule-tour.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/schedule-tour.php
 * 3. Modify the copied file to customize the schedule tour button display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */


if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id   = $property_id ?? get_the_ID();
$hvnly_property_data = $property_data ?? hvnly_get_property_data( $hvnly_property_id );
$hvnly_mode          = $mode ?? 'default';
$hvnly_field         = $field ?? [];

$hvnly_field_data = $hvnly_field['value'] ?? [];
$hvnly_button_text = $hvnly_field_data['text'] ?? __('Schedule Tour', 'havenlytics');
$hvnly_button_icon = $hvnly_field_data['icon'] ?? 'calendar-alt';
$hvnly_button_url = $hvnly_field_data['url'] ?? get_permalink($hvnly_property_id) . '#schedule-tour';
?>

<div class="hvnly-field-schedule-tour hvnly-property-field-mode-<?php echo esc_attr( $hvnly_mode ); ?>">
    <a href="<?php echo esc_url( $hvnly_button_url ); ?>" class="hvnly-btn hvnly-btn--primary hvnly-schedule-tour-button"
        aria-label="<?php echo esc_attr( sprintf( __('Schedule a tour for this property', 'havenlytics' ) ) ); ?>">
        <?php if ( ! empty( $hvnly_button_icon ) ) : ?>
        <i class="fas fa-<?php echo esc_attr( $hvnly_button_icon ); ?>" aria-hidden="true"></i>
        <?php endif; ?>
        <?php echo esc_html( $hvnly_button_text ); ?>
    </a>
</div>