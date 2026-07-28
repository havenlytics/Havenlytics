<?php
/**
 * Square Feet Field Template
 * 
 * This template displays the square footage for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/sqft.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/sqft.php
 * 3. Modify the copied file to customize the square footage display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * 
 * EXAMPLE OVERRIDE:
 * You can add metric conversion, change formatting, or add tooltips.
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

// Get square footage
$hvnly_area = absint(get_post_meta($hvnly_property_id, '_hvnly_property_sqft', true));
$hvnly_field = $field ?? array();

if ($hvnly_area > 0 || $hvnly_mode === 'preset') :
?>
<div class="hvnly-property--grid-list--feature">
    <i class="fas fa-ruler-combined" aria-hidden="true"></i>
    <div class="hvnly-property--feature-lists">
        <?php if ($hvnly_area > 0) : ?>
            <span class="hvnly-property-feature-number">
                <?php echo esc_html(number_format_i18n($hvnly_area)); ?>
            </span>
        <?php endif; ?>
        <span class="hvnly-property-feature-label">
            <?php
            $hvnly_sqft_label = isset( $hvnly_field['label'] ) ? trim( (string) $hvnly_field['label'] ) : '';
            echo esc_html(
                '' !== $hvnly_sqft_label
                    ? ( function_exists( 'hvnly_translate_ui' ) ? hvnly_translate_ui( $hvnly_sqft_label ) : $hvnly_sqft_label )
                    : __( 'sq ft', 'havenlytics' )
            );
            ?>
        </span>
    </div>
</div>
<?php 
endif;