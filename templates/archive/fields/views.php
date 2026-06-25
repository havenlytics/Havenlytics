<?php
/**
 * Views Field Template
 * 
 * This template displays the view count for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/views.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/views.php
 * 3. Modify the copied file to customize the view count display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_field_data  = $field ?? array();
$hvnly_field_value = $hvnly_field_data['value'] ?? array();
$hvnly_view_count  = $hvnly_field_value['count'] ?? 0;

// Use advanced smart formatting
$hvnly_formatted_views = hvnly_get_smart_property_views();

// Get actual count for icon logic
$hvnly_actual_views = hvnly_get_property_views();

// Determine eye icon class based on view count
$hvnly_eye_icon_class = $hvnly_actual_views > 0 ? 'fas fa-eye' : 'fas fa-eye-slash';

// Add tooltip with full count if formatted
$hvnly_tooltip = $hvnly_actual_views >= 1000 ? sprintf( 
    /* translators: %s: full view count */
    __( '%s views', 'havenlytics' ), 
    number_format_i18n( $hvnly_actual_views ) 
) : '';
?>

<div class="hvnly-property-view-count-field hvnly-default-field">
    <div class="hvnly-property-view-count" 
         data-view-count="<?php echo esc_attr( $hvnly_actual_views ); ?>"
         <?php echo $hvnly_tooltip ? 'title="' . esc_attr( $hvnly_tooltip ) . '"' : ''; ?>>
        <i class="<?php echo esc_attr( $hvnly_eye_icon_class ); ?>"></i>
        <span><?php echo esc_html( $hvnly_formatted_views ); ?></span>
    </div>
</div>