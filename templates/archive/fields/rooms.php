<?php
/**
 * Rooms Field Template
 * 
 * This template displays the total rooms count for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/rooms.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/rooms.php
 * 3. Modify the copied file to customize the rooms display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * 
 * EXAMPLE OVERRIDE:
 * You can change icons, add custom calculations, or modify the layout.
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = $property_id ?? get_the_ID();
$hvnly_mode = $mode ?? 'default';

// Get total rooms count
$hvnly_total_rooms = absint(get_post_meta($hvnly_property_id, '_hvnly_property_total_rooms', true));
$hvnly_field    = $field ?? array();

if ($hvnly_total_rooms > 0 || $hvnly_mode === 'preset') :
?>
<div class="hvnly-property--grid-list--feature">
    <i class="fas fa-door-closed" aria-hidden="true"></i>
    <div class="hvnly-property--feature-lists">
        <?php if ($hvnly_total_rooms > 0) : ?>
            <span class="hvnly-property-feature-number">
                <?php echo esc_html($hvnly_total_rooms); ?>
            </span>
        <?php endif; ?>
        <span class="hvnly-property-feature-label">
            <?php
            echo esc_html(
                function_exists( 'hvnly_archive_feature_label' )
                    ? hvnly_archive_feature_label( $hvnly_field, 'Room', 'Rooms', $hvnly_total_rooms )
                    : _n( 'Room', 'Rooms', $hvnly_total_rooms > 0 ? $hvnly_total_rooms : 1, 'havenlytics' )
            );
            ?>
        </span>
    </div>
</div>
<?php 
endif;