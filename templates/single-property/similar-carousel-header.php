<?php
/**
 * Similar Properties Carousel Header Template
 * 
 * This template displays the header for the similar properties carousel.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/similar-carousel-header.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/similar-carousel-header.php
 * 3. Modify the copied file to customize the similar properties carousel header
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Similar Properties Header -->
<div class="hvnly-property-single__similar-header">
    <h3 class="hvnly-property-single__section-title"><?php esc_html_e('Similar Luxury Properties', 'havenlytics'); ?>
    </h3>
    <div class="hvnly-property-single__carousel-controls">
        <button class="hvnly-property-single__carousel-btn" id="hvnlyPropertySingleCarouselPrev"
            aria-label="<?php esc_attr_e('Previous properties', 'havenlytics'); ?>" disabled>
            <i class="fas fa-chevron-left" aria-hidden="true"></i>
        </button>
        <button class="hvnly-property-single__carousel-btn" id="hvnlyPropertySingleCarouselNext"
            aria-label="<?php esc_attr_e('Next properties', 'havenlytics'); ?>">
            <i class="fas fa-chevron-right" aria-hidden="true"></i>
        </button>
    </div>
</div>