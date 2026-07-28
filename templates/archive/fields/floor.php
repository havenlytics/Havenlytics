<?php
/**
 * Grid Card middle center Template
 * 
 * Displays property features in the grid card middle center section.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/floor.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/floor.php
 * 3. Modify the copied file to customize floor display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * 
 * EXAMPLE OVERRIDE:
 * You can change features layout, colors, icons, or implement carousel display.
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/Fields
 * @since       2.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
// Get property ID (fallback to current post)
$hvnly_property_id = $property_id ?? get_the_ID();

// Get render mode (preset or default)
$hvnly_mode = $mode ?? 'default';

// Get floors count from property meta
$hvnly_floors = absint(get_post_meta($hvnly_property_id, '_hvnly_property_floors', true));
$hvnly_field    = $field ?? array();

// Show in preset mode OR if there are floors in default mode
if ($hvnly_floors > 0 || $hvnly_mode === 'preset') :
?>
<div class="hvnly-property--grid-list--feature">
    <!-- Building/floor icon - Customize with any FontAwesome icon -->
    <i class="fas fa-building" aria-hidden="true"></i>
    
    <div class="hvnly-property--feature-lists">
        <!-- Display floor count if available -->
        <?php if ($hvnly_floors > 0) : ?>
            <span class="hvnly-property-feature-number">
                <?php echo esc_html($hvnly_floors); ?>
            </span>
        <?php endif; ?>
        
        <!-- Floor/Floors label with proper pluralization -->
        <span class="hvnly-property-feature-label">
            <?php
            echo esc_html(
                function_exists( 'hvnly_archive_feature_label' )
                    ? hvnly_archive_feature_label( $hvnly_field, 'Floor', 'Floors', $hvnly_floors )
                    : _n( 'Floor', 'Floors', $hvnly_floors > 0 ? $hvnly_floors : 1, 'havenlytics' )
            );
            ?>
        </span>
    </div>
</div>
<?php 
endif; // End if floors or preset mode