<?php
/**
 * Similar Properties Carousel Dots Template
 * 
 * This template displays the navigation dots for the similar properties carousel.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/similar-carousel-dots.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/similar-carousel-dots.php
 * 3. Modify the copied file to customize the navigation dots
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
// Get the number of slides from the carousel
$hvnly_current_property_id = get_the_ID();
$hvnly_similar_args = array(
    'post_type' => 'hvnly_property',
    'posts_per_page' => 6,
    'post__not_in' => array($hvnly_current_property_id),
    'fields' => 'ids', // Only get IDs for counting
);

$hvnly_similar_properties = new WP_Query($hvnly_similar_args);
$hvnly_total_slides = $hvnly_similar_properties->found_posts;

// If no properties, show at least one slide (the "no properties" message)
if ($hvnly_total_slides === 0) {
    $hvnly_total_slides = 1;
}

// Limit to reasonable number of dots
$hvnly_max_dots = 6;
$hvnly_dots_to_show = min($hvnly_total_slides, $hvnly_max_dots);
?>
<!-- Carousel Navigation Dots -->
<div class="hvnly-property-single__carousel-dots" id="hvnlyPropertySingleCarouselDots" role="tablist"
    aria-label="<?php esc_attr_e('Property carousel navigation', 'havenlytics'); ?>">
    <?php for ($hvnly_i = 0; $hvnly_i < $hvnly_dots_to_show; $hvnly_i++) : 
        $hvnly_dot_active = $hvnly_i === 0 ? ' hvnly-property-single__carousel-dot--active' : '';
        $hvnly_slide_number = $hvnly_i + 1;
    ?>
    <button class="hvnly-property-single__carousel-dot<?php echo esc_attr( $hvnly_dot_active ); ?>"
        data-slide-index="<?php echo esc_attr($hvnly_i); ?>" role="tab"
        aria-selected="<?php echo $hvnly_i === 0 ? 'true' : 'false'; ?>" aria-label="<?php 
                echo esc_attr( sprintf( 
                    /* translators: %1$d: current slide number, %2$d: total number of slides */
                    __( 'Go to slide %1$d of %2$d', 'havenlytics' ), $hvnly_slide_number, $hvnly_dots_to_show ) ); ?>">
        <span class="hvnly-property-single__carousel-dot-inner"></span>
    </button>
    <?php endfor; ?>

    <?php if ($hvnly_total_slides > $hvnly_max_dots) : ?>
    <div class="hvnly-property-single__carousel-dots-info">
        <span class="hvnly-property-single__carousel-dots-count">
            <?php 
                echo esc_html( sprintf(
                    /* translators: %d: total number of properties */
                     __( '%d properties', 'havenlytics' ), $hvnly_total_slides ) ); 
                ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<?php wp_reset_postdata(); ?>