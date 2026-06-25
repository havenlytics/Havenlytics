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
<div class="hvnly-property-single__fancybox-popup" id="hvnlyPropertySingleFancyboxGallery">
    <span class="hvnly-property-single__fancybox-close">
        <svg class="hvnly-icon hvnly-icon-thin"><use xlink:href="#hvnly-times"></use></svg>
    </span>
    <span class="hvnly-property-single__fancybox-fullscreen">
        <svg class="hvnly-icon hvnly-expand"><use xlink:href="#hvnly-expand"></use></svg>
        <svg class="hvnly-icon hvnly-compress"><use xlink:href="#hvnly-compress"></use></svg>
    </span>
    <span class="hvnly-property-single__fancybox-nav hvnly-property-single__fancybox-nav--prev">
        <svg class="hvnly-icon hvnly-icon-thin"><use xlink:href="#hvnly-chevron-left"></use></svg>
    </span>
    <span class="hvnly-property-single__fancybox-nav hvnly-property-single__fancybox-nav--next">
        <svg class="hvnly-icon hvnly-icon-thin"><use xlink:href="#hvnly-chevron-right"></use></svg>
    </span>
    <div class="hvnly-property-single__fancybox-counter">1 / 5</div>

    <div class="hvnly-property-single__fancybox-content">
        <div class="hvnly-property-single__fancybox-main">
            <div class="hvnly-property-single__fancybox-img-wrap">
                <img class="hvnly-property-single__fancybox-img" src="" alt="">
            </div>
            <div class="hvnly-property-single__fancybox-caption"><?php the_title(); ?></div>
            <div class="hvnly-property-single__fancybox-property">
                <a href="<?php the_permalink(); ?>" class="hvnly-property-single__fancybox-property-button" target="_blank">
                    View Property Details 
                    <svg class="hvnly-icon hvnly-icon-thin"><use xlink:href="#hvnly-arrow-right"></use></svg>
                </a>
            </div>
        </div>
        <div class="hvnly-property-single__fancybox-sidebar">
            <div class="hvnly-property-single__fancybox-thumbnails" id="hvnlyPropertySingleFancyboxThumbnails">
                <!-- Thumbnails will be populated by JavaScript -->
            </div>
        </div>
    </div>
    <div class="hvnly-property-single__fullscreen-indicator">Press ESC to exit fullscreen</div>
</div>