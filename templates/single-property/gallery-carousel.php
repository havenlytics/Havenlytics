<?php
/**
 * Gallery Carousel Template
 * 
 * This template displays the gallery carousel for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/gallery-carousel.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/gallery-carousel.php
 * 3. Modify the copied file to customize the gallery carousel
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = isset( $args['property_id'] ) ? absint( $args['property_id'] ) : get_the_ID();

// Merged gallery IDs from all builder gallery fields (same path as archive thumbnails).
$hvnly_gallery_image_ids = function_exists( 'hvnly_get_property_gallery_ids' )
    ? hvnly_get_property_gallery_ids( $hvnly_property_id )
    : array();

$hvnly_featured_image = get_the_post_thumbnail_url($hvnly_property_id, 'full');
$hvnly_gallery_title = '';

// Prepare display images
$hvnly_display_images = array();
$hvnly_has_images = false;

// First, try to use gallery images
if (!empty($hvnly_gallery_image_ids)) {
    $hvnly_has_images = true;
    foreach ($hvnly_gallery_image_ids as $hvnly_image_id) {
        $hvnly_image_url = wp_get_attachment_image_url($hvnly_image_id, 'full');
        $hvnly_image_thumb = wp_get_attachment_image_url($hvnly_image_id, 'medium');
        if ($hvnly_image_url) {
            $hvnly_display_images[] = array(
                'type' => 'gallery',
                'url' => esc_url($hvnly_image_url),
                'thumb' => $hvnly_image_thumb,
                'id' => absint($hvnly_image_id),
                'title' => get_the_title($hvnly_image_id),
                'alt' => get_post_meta($hvnly_image_id, '_wp_attachment_image_alt', true)
            );
        }
    }
}

// If no gallery images, use featured image
if (!$hvnly_has_images && $hvnly_featured_image) {
    $hvnly_has_images = true;
    $hvnly_display_images[] = array(
        'type' => 'featured',
        'url' => esc_url($hvnly_featured_image),
        'thumb' => $hvnly_featured_image,
        'id' => 'featured',
        'title' => get_the_title($hvnly_property_id),
        'alt' => get_post_meta($hvnly_property_id, '_wp_attachment_image_alt', true)
    );
}

// If still no images, use bundled visual placeholder (never attaches media).
if (!$hvnly_has_images) {
    $hvnly_placeholder = function_exists('hvnly_get_property_thumbnail_url')
        ? hvnly_get_property_thumbnail_url($hvnly_property_id, 'full')
        : '';
    $hvnly_display_images[] = array(
        'type' => 'placeholder',
        'url' => $hvnly_placeholder,
        'thumb' => $hvnly_placeholder,
        'id' => 'placeholder',
        'title' => get_the_title($hvnly_property_id),
        'alt' => get_the_title($hvnly_property_id)
    );
}

$hvnly_total_images = count($hvnly_display_images);
?>
<!-- Property Gallery Carousel with Smooth Zoom Effects -->
<div class="hvnly-property-single__gallery">

    <div class="hvnly-property-single__gallery-main" id="hvnlyPropertySingleGalleryMain">
        <!-- Gallery Slides -->
        <?php foreach ($hvnly_display_images as $hvnly_index => $hvnly_image_data): 
            $hvnly_is_active = $hvnly_index === 0 ? ' hvnly-property-single__gallery-slide--active' : '';
            $hvnly_slide_index = absint($hvnly_index);
        ?>
        <div class="hvnly-property-single__gallery-slide<?php echo esc_attr( $hvnly_is_active ); ?>"
            style="background-image: url('<?php echo esc_url($hvnly_image_data['url']); ?>');"
            data-slide-index="<?php echo esc_attr($hvnly_slide_index); ?>"
            data-image-id="<?php echo esc_attr($hvnly_image_data['id']); ?>"
            data-image-title="<?php echo esc_attr($hvnly_image_data['title']); ?>"
            data-image-alt="<?php echo esc_attr($hvnly_image_data['alt']); ?>">
        </div>
        <?php endforeach; ?>

        <div class="hvnly-property-single__gallery-counter" id="hvnlyPropertySingleGalleryCounter">
            <?php if ($hvnly_total_images > 0): ?>
            1/<?php echo esc_html($hvnly_total_images); ?>
            <?php else: ?>
            0/0
            <?php endif; ?>
        </div>

        <?php if ($hvnly_total_images > 1): ?>
        <div class="hvnly-property-single__gallery-indicators" id="hvnlyPropertySingleGalleryIndicators">
            <?php for ($hvnly_i = 0; $hvnly_i < $hvnly_total_images; $hvnly_i++): 
                    $hvnly_indicator_active = $hvnly_i === 0 ? ' hvnly-property-single__gallery-indicator--active' : '';
                ?>
            <div class="hvnly-property-single__gallery-indicator<?php echo esc_attr( $hvnly_indicator_active ); ?>"
                data-index="<?php echo esc_attr($hvnly_i); ?>"></div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php if ($hvnly_total_images > 1): ?>
        <div class="hvnly-property-single__gallery-controls">
            <button class="hvnly-property-single__gallery-btn hvnly-property-single__gallery-btn--prev"
                aria-label="<?php esc_attr_e('Previous image', 'havenlytics'); ?>">
                <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-chevron-left"></use></svg>
            </button>
            <button class="hvnly-property-single__gallery-btn hvnly-property-single__gallery-btn--next"
                aria-label="<?php esc_attr_e('Next image', 'havenlytics'); ?>">
                <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-chevron-right"></use></svg>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($hvnly_total_images > 1): ?>
    <div class="hvnly-property-single__thumbnail-container" id="hvnlyPropertySingleGalleryThumbnails">
        <!-- Thumbnails will be populated by JavaScript -->
    </div>
    <?php endif; ?>
</div>