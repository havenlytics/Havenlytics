<?php
/**
 * Thumbnail Field Template
 * 
 * This template displays the thumbnail for a property.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/sections/thumbnail.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/sections/thumbnail.php
 * 3. Modify the copied file to customize the thumbnail display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive/sections
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = $property_id ?? get_the_ID();
$hvnly_section = $section ?? [];
$hvnly_overlay_sections = $overlay_sections ?? [];
$hvnly_mode = $mode ?? 'default';
$hvnly_property_data = $property_data ?? [];
$hvnly_all_sections = $all_sections ?? [];

// Get property data
$hvnly_is_featured = get_post_meta($hvnly_property_id, '_hvnly_property_action_tool_is_featured', true);
$hvnly_price = get_post_meta($hvnly_property_id, '_hvnly_property_price', true);

// Get gallery images using the improved function
$hvnly_gallery_image_ids = hvnly_get_property_gallery_ids($hvnly_property_id);

// Get featured image
$hvnly_featured_image = get_the_post_thumbnail_url($hvnly_property_id, 'large');

// Apply gallery order
$hvnly_property_id_int = absint($hvnly_property_id);
$hvnly_gallery_order = ($hvnly_property_id_int % 2 == 0) ? 'ASC' : 'DESC';
if (!empty($hvnly_gallery_image_ids) && $hvnly_gallery_order === 'DESC') {
    $hvnly_gallery_image_ids = array_reverse($hvnly_gallery_image_ids);
}
?>

<div class="hvnly-property-grid-list-thumb">
    <!-- Gallery area with property permalink -->
    <a href="<?php echo esc_url(get_permalink($hvnly_property_id)); ?>" class="hvnly-property-grid-list-thumb-link">
        <div class="hvnly-property-gallery-main">
            <div class="hvnly-property-carousel-gallery"
                id="hvnly-property-carousel-gallery-<?php echo esc_attr($hvnly_property_id); ?>">
                <div class="hvnly-property-carousel-track">
                    <?php
                    $hvnly_has_images = false;
                    $hvnly_display_images = array();

                    // First, try to use gallery images
                    if (!empty($hvnly_gallery_image_ids)) {
                        $hvnly_has_images = true;
                        $hvnly_image_count = 0;
                        foreach ($hvnly_gallery_image_ids as $hvnly_image_id) {
                            if ($hvnly_image_count >= 12) break;
                            $hvnly_image_url = wp_get_attachment_image_url($hvnly_image_id, 'large');
                            if ($hvnly_image_url) {
                                $hvnly_display_images[] = array(
                                    'type' => 'gallery',
                                    'url' => esc_url($hvnly_image_url),
                                    'id' => absint($hvnly_image_id)
                                );
                                $hvnly_image_count++;
                            }
                        }
                    }

                    // If no gallery images, use featured image
                    if (!$hvnly_has_images && $hvnly_featured_image) {
                        $hvnly_has_images = true;
                        $hvnly_display_images[] = array(
                            'type' => 'featured',
                            'url' => esc_url($hvnly_featured_image),
                            'id' => 'featured'
                        );
                    }

                    // If still no images, use placeholder
                    if (!$hvnly_has_images) {
                        $hvnly_display_images[] = array(
                            'type' => 'placeholder',
                            'url' => HVNLYNAB_ASSETS_URL . 'images/placeholder.jpg',
                            'id' => 'placeholder'
                        );
                    }

                    // Display the slides
                    foreach ($hvnly_display_images as $hvnly_index => $hvnly_image_data):
                        $hvnly_slide_index = absint($hvnly_index);
                    ?>
                    <div class="hvnly-property-carousel-slide"
                        style="background-image: url('<?php echo esc_url($hvnly_image_data['url']); ?>');"
                        data-image-id="<?php echo esc_attr($hvnly_image_data['id']); ?>"
                        data-slide-index="<?php echo esc_attr($hvnly_slide_index); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($hvnly_display_images) > 1): ?>
                <div class="hvnly-property-carousel-nav">
                    <button class="hvnly-property-carousel-nav-btn hvnly-property-carousel-prev-btn"
                        onclick="event.preventDefault();"
                        aria-label="<?php esc_attr_e('Previous image', 'havenlytics'); ?>">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="hvnly-property-carousel-nav-btn hvnly-property-carousel-next-btn"
                        onclick="event.preventDefault();"
                        aria-label="<?php esc_attr_e('Next image', 'havenlytics'); ?>">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                <div class="hvnly-property-carousel-indicators">
                    <?php for ($hvnly_i = 0; $hvnly_i < count($hvnly_display_images); $hvnly_i++): ?>
                    <div class="hvnly-property-carousel-indicator <?php echo $hvnly_i === 0 ? 'hvnly-property-carousel-active' : ''; ?>"
                        data-indicator-index="<?php echo esc_attr(absint($hvnly_i)); ?>"></div>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </a>

    <!-- Overlay elements (outside the gallery link) -->
    <div class="hvnly-property--grid-list--overlay">
        <div class="hvnly-property--grid-list-top--overlay">
            <div class="hvnly-property-grid-list-top-badge-left">
                <?php
                if ($hvnly_mode === 'preset' && isset($hvnly_overlay_sections['image-overlay-top-left']) && 
                    !empty($hvnly_overlay_sections['image-overlay-top-left']['fields'])) {
                    
                    // Preset mode: Render configured fields from this specific overlay section
                    foreach ($hvnly_overlay_sections['image-overlay-top-left']['fields'] as $hvnly_field) {
                        $hvnly_field_type = $hvnly_field['type'] ?? '';
                        
                        // Use template function to render field WITHOUT field data
                        hvnly_render_field(
                            $hvnly_field_type,
                            $hvnly_property_id,
                            $hvnly_property_data,
                            'preset'
                        );
                    }
                } else {
                    // Default mode OR preset mode without overlay configuration
                    if ($hvnly_mode === 'preset') {
                        // In preset mode, check if fields are configured anywhere
                        if (hvnly_is_field_configured($hvnly_all_sections, 'badge')) {
                            hvnly_render_field('badge', $hvnly_property_id, $hvnly_property_data, 'preset');
                        }
                        
                        if (hvnly_is_field_configured($hvnly_all_sections, 'status')) {
                            hvnly_render_field('status', $hvnly_property_id, $hvnly_property_data, 'preset');
                        }
                        
                        if (hvnly_is_field_configured($hvnly_all_sections, 'time')) {
                            hvnly_render_field('time', $hvnly_property_id, $hvnly_property_data, 'preset');
                        }
                    } else {
                        // In default mode, show all default fields
                        hvnly_render_field('badge', $hvnly_property_id, $hvnly_property_data, 'default');
                        hvnly_render_field('status', $hvnly_property_id, $hvnly_property_data, 'default');
                    }
                }
                ?>
            </div>
            <div class="hvnly-property-grid-list-top-badge-right">
                <?php
                if ($hvnly_mode === 'preset' && isset($hvnly_overlay_sections['image-overlay-top-right']) && 
                    !empty($hvnly_overlay_sections['image-overlay-top-right']['fields'])) {
                    
                    // Preset mode: Render configured fields WITH field data
                    foreach ($hvnly_overlay_sections['image-overlay-top-right']['fields'] as $hvnly_field) {
                        $hvnly_field_type = $hvnly_field['type'] ?? '';
                        hvnly_render_field($hvnly_field_type, $hvnly_property_id, $hvnly_property_data, 'preset', $hvnly_field);
                    }
                } else {
                    // Default mode
                    if ($hvnly_mode === 'preset') {
                        // In preset mode, only show if configured
                        if (hvnly_is_field_configured($hvnly_all_sections, 'favorite')) {
                            hvnly_render_field('favorite', $hvnly_property_id, $hvnly_property_data, 'preset');
                        }
                        
                        if (hvnly_is_field_configured($hvnly_all_sections, 'share')) {
                            hvnly_render_field('share', $hvnly_property_id, $hvnly_property_data, 'preset');
                        }
                    } else {
                        // In default mode, show all WITHOUT field data
                        hvnly_render_field('favorite', $hvnly_property_id, $hvnly_property_data, 'default');
                        hvnly_render_field('share', $hvnly_property_id, $hvnly_property_data, 'default');
                    }
                }
                ?>
            </div>
        </div>
        <div class="hvnly-property--grid-list--bottom-overlay">
            <div class="hvnly-property-grid-list-bottom-badge-left">
                <?php
                if ($hvnly_mode === 'preset' && isset($hvnly_overlay_sections['image-overlay-bottom-left']) && 
                    !empty($hvnly_overlay_sections['image-overlay-bottom-left']['fields'])) {
                    
                    // Preset mode: Render configured fields
                    foreach ($hvnly_overlay_sections['image-overlay-bottom-left']['fields'] as $hvnly_field) {
                        $hvnly_field_type = $hvnly_field['type'] ?? '';
                        hvnly_render_field($hvnly_field_type, $hvnly_property_id, $hvnly_property_data, 'preset');
                    }
                } else {
                    // Default mode
                    if ($hvnly_mode === 'preset') {
                        // In preset mode, only show if configured
                        if (hvnly_is_field_configured($hvnly_all_sections, 'price')) {
                            hvnly_render_field('price', $hvnly_property_id, $hvnly_property_data, 'preset');
                        }
                    } else {
                        // In default mode, show price
                        hvnly_render_field('price', $hvnly_property_id, $hvnly_property_data, 'default');
                    }
                }
                ?>
            </div>
            <div class="hvnly-property-grid-list-bottom-badge-right">
                <?php
                if ($hvnly_mode === 'preset' && isset($hvnly_overlay_sections['image-overlay-bottom-right']) && 
                    !empty($hvnly_overlay_sections['image-overlay-bottom-right']['fields'])) {
                    
                    // Preset mode: Render configured fields
                    foreach ($hvnly_overlay_sections['image-overlay-bottom-right']['fields'] as $hvnly_field) {
                        $hvnly_field_type = $hvnly_field['type'] ?? '';
                        hvnly_render_field($hvnly_field_type, $hvnly_property_id, $hvnly_property_data, 'preset');
                    }
                } else {
                    // Default mode
                    if ($hvnly_mode === 'preset') {
                        // In preset mode, only show if configured
                        if (hvnly_is_field_configured($hvnly_all_sections, 'views')) {
                            hvnly_render_field('views', $hvnly_property_id, $hvnly_property_data, 'preset');
                        }
                    } else {
                        // In default mode, show views
                        hvnly_render_field('views', $hvnly_property_id, $hvnly_property_data, 'default');
                    }
                }
                ?>
            </div>
        </div>
    </div>
</div>