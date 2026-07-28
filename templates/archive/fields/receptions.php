<?php
/**
 * Receptions Field Template
 * 
 * This template displays the reception rooms count for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/fields/receptions.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/fields/receptions.php
 * 3. Modify the copied file to customize the receptions display
 * 
 * AVAILABLE VARIABLES:
 * $property_id - Current property ID
 * $mode - Render mode: 'preset' (from builder) or 'default'
 * 
 * EXAMPLE OVERRIDE:
 * You can change icons, add custom labels, or modify the formatting.
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

// Get reception rooms count
$hvnly_reception = absint(get_post_meta($hvnly_property_id, '_hvnly_property_reception_rooms', true));
$hvnly_field    = $field ?? array();

if ($hvnly_reception > 0 || $hvnly_mode === 'preset') :
?>
<div class="hvnly-property--grid-list--feature">
    <i class="fas fa-couch" aria-hidden="true"></i>
    <div class="hvnly-property--feature-lists">
        <?php if ($hvnly_reception > 0) : ?>
            <span class="hvnly-property-feature-number">
                <?php echo esc_html(number_format_i18n($hvnly_reception)); ?>
            </span>
        <?php endif; ?>
        <span class="hvnly-property-feature-label">
            <?php
            echo esc_html(
                function_exists( 'hvnly_archive_feature_label' )
                    ? hvnly_archive_feature_label( $hvnly_field, 'Recep', 'Recep', $hvnly_reception )
                    : _n( 'Recep', 'Recep', $hvnly_reception > 0 ? $hvnly_reception : 1, 'havenlytics' )
            );
            ?>
        </span>
    </div>
</div>
<?php 
endif;