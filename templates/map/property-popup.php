<?php
/**
 * Property Popup Template
 * 
 * This template displays the popup content for a property on the map.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/map/property-popup.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/map/property-popup.php
 * 3. Modify the copied file to customize the popup content
 * 
 * @package     Havenlytics
 * @subpackage  Templates/map
 * @since       2.0.0
 */

if (!defined('ABSPATH')) exit;

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property = $args['property'] ?? [];
?>
<div class="hvnly-property-popup-content ">
    <div class="hvnly-popup-image">
        <?php if (!empty($hvnly_property['thumbnail'])): ?>
        <img src="<?php echo esc_url($hvnly_property['thumbnail']); ?>"
            alt="<?php echo esc_attr($hvnly_property['title'] ?? ''); ?>" loading="lazy">
        <?php else: ?>
        <div class="hvnly-popup-no-image">
            <i class="fas fa-home"></i>
        </div>
        <?php endif; ?>
    </div>
    <div class="hvnly-popup-details">
        <h4 class="hvnly-popup-title">
            <?php echo esc_html($hvnly_property['title'] ?? __('Untitled Property', 'havenlytics')); ?></h4>

        <?php if (!empty($hvnly_property['price'])): ?>
        <p class="hvnly-popup-price"><?php echo esc_html($hvnly_property['price']); ?></p>
        <?php endif; ?>

        <?php if (!empty($hvnly_property['address'])): ?>
        <p class="hvnly-popup-address">
            <i class="fas fa-map-marker-alt"></i>
            <span><?php echo esc_html($hvnly_property['address']); ?></span>
        </p>
        <?php endif; ?>

        <div class="hvnly-popup-features">
            <?php if (!empty($hvnly_property['bedrooms'])): ?>
            <div class="hvnly-popup-feature">
                <i class="fas fa-bed"></i>
                <?php echo esc_html($hvnly_property['bedrooms']); ?>
                <?php esc_html_e('Bed', 'havenlytics'); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($hvnly_property['bathrooms'])): ?>
            <div class="hvnly-popup-feature">
                <i class="fas fa-bath"></i>
                <?php echo esc_html($hvnly_property['bathrooms']); ?>
                <?php esc_html_e('Bath', 'havenlytics'); ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="hvnly-popup-actions">
            <a href="<?php echo esc_url($hvnly_property['link'] ?? '#'); ?>" class="hvnly-popup-view-btn">
                <?php esc_html_e('View Details', 'havenlytics'); ?>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>