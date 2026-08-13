<?php
/**
 * Modal Video Template
 * 
 * This template displays the modal video for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/modal-video.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/modal-video.php
 * 3. Modify the copied file to customize the modal video
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Video Popup - Single unified popup for all videos -->
<div class="hvnly-property-single__fancybox-popup" id="hvnlyPropertySingleFancyboxVideo">
    <?php // Sprint 31D: controls were <span>s — no keyboard access, no accessible name. ?>
    <button type="button" class="hvnly-ui-control hvnly-property-single__fancybox-close" aria-label="<?php esc_attr_e('Close video', 'havenlytics'); ?>">
        <svg class="hvnly-icon hvnly-icon-thin" aria-hidden="true"><use xlink:href="#hvnly-times"></use></svg>
    </button>
    <button type="button" class="hvnly-ui-control hvnly-property-single__fancybox-fullscreen" aria-label="<?php esc_attr_e('Toggle fullscreen', 'havenlytics'); ?>">
        <svg class="hvnly-icon hvnly-expand" aria-hidden="true"><use xlink:href="#hvnly-expand"></use></svg>
        <svg class="hvnly-icon hvnly-compress" aria-hidden="true"><use xlink:href="#hvnly-compress"></use></svg>
    </button>
    <div class="hvnly-property-single__fancybox-counter" id="hvnlyPropertySingleVideoCounter">
        <?php esc_html_e('Virtual Tour', 'havenlytics'); ?>
    </div>

    <div class="hvnly-property-single__fancybox-content">
        <div class="hvnly-property-single__fancybox-main" style="border-radius: 20px;">
            <div class="hvnly-property-single__fancybox-img-wrap">
                <div id="hvnlyPropertySingleVideoPlayer" class="hvnly-video-player-container">
                    <!-- Video will be loaded dynamically by JavaScript -->
                    <div class="hvnly-video-loading" style="color: white; text-align: center; padding: 50px;">
                        <svg class="hvnly-icon hvnly-icon-xl" style="margin-bottom: 20px;"><use xlink:href="#hvnly-video"></use></svg>
                        <h3><?php esc_html_e('Loading Video...', 'havenlytics'); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="hvnly-property-single__fullscreen-indicator"><?php esc_html_e('Press ESC to exit fullscreen', 'havenlytics'); ?></div>
</div>