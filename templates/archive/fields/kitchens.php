<?php
/**
 * Kitchens Field Template
 * 
 * This template displays the kitchens count for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/kitchens.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/kitchens.php
 * 3. Modify the copied file to customize the kitchens display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * 
 * EXAMPLE OVERRIDE:
 * You can change icons, add custom labels, or modify the layout.
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

// Get kitchens count
$hvnly_kitchens = absint(get_post_meta($hvnly_property_id, '_hvnly_property_kitchens', true));

if ($hvnly_kitchens > 0 || $hvnly_mode === 'preset') :
?>
<div class="hvnly-property--grid-list--feature">
    <i class="fas fa-utensils" aria-hidden="true"></i>
    <div class="hvnly-property--feature-lists">
        <?php if ($hvnly_kitchens > 0) : ?>
            <span class="hvnly-property-feature-number">
                <?php echo esc_html($hvnly_kitchens); ?>
            </span>
        <?php endif; ?>
        <span class="hvnly-property-feature-label">
            <?php 
            /* translators: %s: number of kitchens */
            echo esc_html( 
                _n( 
                    'Kitchen', 
                    'Kitchens', 
                    $hvnly_kitchens > 0 ? $hvnly_kitchens : 1, 
                    'havenlytics' 
                ) 
            ); 
            ?>
        </span>
    </div>
</div>
<?php 
endif;