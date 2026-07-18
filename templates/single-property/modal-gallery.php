<?php
/**
 * Modal Gallery Template
 * 
 * This template displays the modal gallery for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/modal-gallery.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/modal-gallery.php
 * 3. Modify the copied file to customize the modal gallery
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- New Fancybox Gallery Popup -->
<?php // Sprint 31D: controls were <span>s — no keyboard access, no accessible name. ?>
<div class="hvnly-property-single__fancybox-popup" id="hvnlyPropertySingleFancyboxGallery">
    <button type="button" class="hvnly-property-single__fancybox-close" aria-label="<?php esc_attr_e('Close gallery', 'havenlytics'); ?>">
        <svg class="hvnly-icon hvnly-icon-thin" aria-hidden="true"><use xlink:href="#hvnly-times"></use></svg>
    </button>
    <button type="button" class="hvnly-property-single__fancybox-fullscreen" aria-label="<?php esc_attr_e('Toggle fullscreen', 'havenlytics'); ?>">
        <svg class="hvnly-icon hvnly-expand" aria-hidden="true"><use xlink:href="#hvnly-expand"></use></svg>
        <svg class="hvnly-icon hvnly-compress" aria-hidden="true"><use xlink:href="#hvnly-compress"></use></svg>
    </button>
    <button type="button" class="hvnly-property-single__fancybox-nav hvnly-property-single__fancybox-nav--prev" aria-label="<?php esc_attr_e('Previous image', 'havenlytics'); ?>">
        <svg class="hvnly-icon hvnly-icon-thin" aria-hidden="true"><use xlink:href="#hvnly-chevron-left"></use></svg>
    </button>
    <button type="button" class="hvnly-property-single__fancybox-nav hvnly-property-single__fancybox-nav--next" aria-label="<?php esc_attr_e('Next image', 'havenlytics'); ?>">
        <svg class="hvnly-icon hvnly-icon-thin" aria-hidden="true"><use xlink:href="#hvnly-chevron-right"></use></svg>
    </button>
    <div class="hvnly-property-single__fancybox-counter">1 / 5</div>

    <div class="hvnly-property-single__fancybox-content">
        <div class="hvnly-property-single__fancybox-main">
            <div class="hvnly-property-single__fancybox-img-wrap">
                <img class="hvnly-property-single__fancybox-img" src="" alt="">
            </div>
            <div class="hvnly-property-single__fancybox-caption"><?php the_title(); ?></div>
            <div class="hvnly-property-single__fancybox-property">
                <a href="<?php the_permalink(); ?>" class="hvnly-property-single__fancybox-property-button" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'View Property Details', 'havenlytics' ); ?>
                    <svg class="hvnly-icon hvnly-icon-thin" aria-hidden="true"><use xlink:href="#hvnly-arrow-right"></use></svg>
                </a>
            </div>
        </div>
        <div class="hvnly-property-single__fancybox-sidebar">
            <div class="hvnly-property-single__fancybox-thumbnails" id="hvnlyPropertySingleFancyboxThumbnails">
                <!-- Thumbnails will be populated by JavaScript -->
            </div>
        </div>
    </div>
    <div class="hvnly-property-single__fullscreen-indicator"><?php esc_html_e( 'Press ESC to exit fullscreen', 'havenlytics' ); ?></div>
</div>