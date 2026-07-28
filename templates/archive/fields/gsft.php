<?php
/**
 * Garage Square Feet Field Template
 * 
 * This template displays the garage square footage for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/gsft.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/gsft.php
 * 3. Modify the copied file to customize the garage square footage display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * 
 * EXAMPLE OVERRIDE:
 * You can change the icon, add unit conversion, or modify the layout.
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

// Get garage square feet
$hvnly_garage_sqft = absint(get_post_meta($hvnly_property_id, '_hvnly_property_garage_sqft', true));
$hvnly_field    = $field ?? array();

if ($hvnly_garage_sqft > 0 || $hvnly_mode === 'preset') :
?>
<div class="hvnly-property--grid-list--feature">
    <i class="fas fa-warehouse" aria-hidden="true"></i>
    <div class="hvnly-property--feature-lists">
        <?php if ($hvnly_garage_sqft > 0) : ?>
            <span class="hvnly-property-feature-number">
                <?php echo esc_html($hvnly_garage_sqft); ?>
            </span>
        <?php endif; ?>
        <span class="hvnly-property-feature-label">
            <?php
            echo esc_html(
                function_exists( 'hvnly_archive_feature_label' )
                    ? hvnly_archive_feature_label( $hvnly_field, 'Gsq ft', 'Gsq ft', $hvnly_garage_sqft )
                    : _n( 'Gsq ft', 'Gsq ft', $hvnly_garage_sqft > 0 ? $hvnly_garage_sqft : 1, 'havenlytics' )
            );
            ?>
        </span>
    </div>
</div>
<?php 
endif;