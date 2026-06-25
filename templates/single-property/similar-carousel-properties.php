<?php
/**
 * Similar Properties Carousel Properties Template
 * 
 * This template displays the properties within the similar properties carousel.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/similar-carousel-properties.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/similar-carousel-properties.php
 * 3. Modify the copied file to customize the similar properties carousel
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_current_property_id = get_the_ID();

// Get similar properties based on criteria
$hvnly_similar_args = array(
    'post_type' => 'hvnly_property',
    'posts_per_page' => 6,
    'post__not_in' => array($hvnly_current_property_id),
    'orderby' => 'rand',
);

// Try to get similar properties by location
$hvnly_locations = get_the_terms($hvnly_current_property_id, 'hvnly_prop_locations');
if ($hvnly_locations && !is_wp_error($hvnly_locations)) {
    $hvnly_location_ids = wp_list_pluck($hvnly_locations, 'term_id');
    $hvnly_similar_args['tax_query'][] = array(
        'taxonomy' => 'hvnly_prop_locations',
        'field' => 'term_id',
        'terms' => $hvnly_location_ids,
    );
}

// Try to get similar properties by type
$hvnly_types = get_the_terms($hvnly_current_property_id, 'hvnly_prop_types');
if ($hvnly_types && !is_wp_error($hvnly_types)) {
    $hvnly_type_ids = wp_list_pluck($hvnly_types, 'term_id');
    if (empty($hvnly_similar_args['tax_query'])) {
        $hvnly_similar_args['tax_query'] = array();
    }
    $hvnly_similar_args['tax_query'][] = array(
        'taxonomy' => 'hvnly_prop_types',
        'field' => 'term_id',
        'terms' => $hvnly_type_ids,
    );
}

// If no location or type, get featured properties
if (empty($hvnly_similar_args['tax_query'])) {
    $hvnly_similar_args['meta_key'] = '_hvnly_property_featured';
    $hvnly_similar_args['meta_value'] = '1';
}

$hvnly_similar_properties = new WP_Query($hvnly_similar_args);

// If still no properties, get latest properties
if (!$hvnly_similar_properties->have_posts()) {
    $hvnly_similar_args = array(
        'post_type' => 'hvnly_property',
        'posts_per_page' => 6,
        'post__not_in' => array($hvnly_current_property_id),
        'orderby' => 'date',
        'order' => 'DESC',
    );
   
    $hvnly_similar_properties = new WP_Query($hvnly_similar_args);
}
?>

<!-- Similar Properties Carousel -->
<div class="hvnly-property-single__carousel-container">
    <div class="hvnly-property-single__carousel-track" id="hvnlyPropertySingleCarouselTrack">
        <?php if ($hvnly_similar_properties->have_posts()) : 
            $hvnly_slide_index = 0;
            while ($hvnly_similar_properties->have_posts()) : $hvnly_similar_properties->the_post(); 
                $hvnly_property_id = get_the_ID();
                
                // Get price value and format using helper function
                $hvnly_actual_price = get_post_meta($hvnly_property_id, '_hvnly_property_price', true);
                $hvnly_price = hvnly_format_price($hvnly_actual_price);
                
                $hvnly_image_url = get_the_post_thumbnail_url($hvnly_property_id, 'large');
                $hvnly_bedrooms = get_post_meta($hvnly_property_id, '_hvnly_property_bedrooms', true);
                $hvnly_bathrooms = get_post_meta($hvnly_property_id, '_hvnly_property_bathrooms', true);
                $hvnly_sqft = get_post_meta($hvnly_property_id, '_hvnly_property_sqft', true);
                $hvnly_is_featured = get_post_meta($hvnly_property_id, '_hvnly_property_featured', true);
                
                // Get taxonomies for hover overlay
                $hvnly_property_types = wp_get_post_terms($hvnly_property_id, 'hvnly_prop_types', array('fields' => 'names'));
                $hvnly_property_status = wp_get_post_terms($hvnly_property_id, 'hvnly_prop_status', array('fields' => 'names'));
                $hvnly_property_badges = wp_get_post_terms($hvnly_property_id, 'hvnly_prop_badges', array('fields' => 'names'));
                $hvnly_property_features = wp_get_post_terms($hvnly_property_id, 'hvnly_prop_features', array('fields' => 'names'));
                
                // Check if property is featured
                $hvnly_is_featured_property = ($hvnly_is_featured === '1' || $hvnly_is_featured === 1);
        ?>
            <!-- Slide <?php echo esc_html( $hvnly_slide_index + 1 ); ?> -->
            <div class="hvnly-property-single__carousel-slide">
                <div class="hvnly-property-single__similar-card">
                    <div class="hvnly-property-single__similar-image" data-index="<?php echo esc_attr( $hvnly_slide_index ); ?>">
                        <a href="<?php the_permalink(); ?>">
                            <?php if ($hvnly_image_url) : ?>
                                <img src="<?php echo esc_url($hvnly_image_url); ?>" 
                                     alt="<?php the_title_attribute(); ?>"
                                     data-src="<?php echo esc_url(get_the_post_thumbnail_url($hvnly_property_id, 'full')); ?>"
                                     data-alt="<?php the_title_attribute(); ?>">
                            <?php else : ?>
                                <div class="hvnly-property-single__similar-image-placeholder">
                                    <i class="fas fa-home" style="font-size: 40px; color: var(--hvnly-primary-color);"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Hover Overlay with Taxonomies -->
                            <div class="hvnly-property-single__image-overlay">
                                <?php if (!empty($hvnly_property_types)) : ?>
                                    <div class="hvnly-property-single__overlay-item">
                                        <i class="fas fa-tag"></i>
                                        <span><?php echo esc_html(implode(', ', array_slice($hvnly_property_types, 0, 2))); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($hvnly_property_status)) : ?>
                                    <div class="hvnly-property-single__overlay-item">
                                        <i class="fas fa-info-circle"></i>
                                        <span><?php echo esc_html(implode(', ', array_slice($hvnly_property_status, 0, 2))); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($hvnly_property_badges)) : ?>
                                    <div class="hvnly-property-single__overlay-item">
                                        <i class="fas fa-star"></i>
                                        <span><?php echo esc_html(implode(', ', array_slice($hvnly_property_badges, 0, 2))); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($hvnly_property_features)) : ?>
                                    <div class="hvnly-property-single__overlay-item">
                                        <i class="fas fa-check-circle"></i>
                                        <span><?php echo esc_html(implode(', ', array_slice($hvnly_property_features, 0, 2))); ?></span>
                                        <?php if (count($hvnly_property_features) > 2) : ?>
                                            <span class="hvnly-property-single__overlay-more">+<?php echo count($hvnly_property_features) - 2; ?> <?php esc_html_e('more', 'havenlytics'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php 
                            // Only show badge if the property is featured
                            if ($hvnly_is_featured_property) : 
                            ?>
                                <span class="hvnly-property-single__similar-badge hvnly-property-single__similar-badge--featured">
                                    <?php esc_html_e('Featured', 'havenlytics'); ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </div>
                    
                    <div class="hvnly-property-single__similar-content">
                        <div class="hvnly-property-single__similar-price"><?php echo wp_kses_post( $hvnly_price ); ?></div>
                        
                        <h3 class="hvnly-property-single__similar-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h3>
                        
                        <?php if ($hvnly_bedrooms || $hvnly_bathrooms || $hvnly_sqft) : ?>
                            <div class="hvnly-property-single__similar-meta">
                                <?php if ($hvnly_bedrooms) : ?>
                                    <div class="hvnly-property-single__similar-feature">
                                        <i class="fas fa-bed"></i>
                                        <span><?php echo esc_html($hvnly_bedrooms); ?> <?php esc_html_e('Beds', 'havenlytics'); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($hvnly_bathrooms) : ?>
                                    <div class="hvnly-property-single__similar-feature">
                                        <i class="fas fa-bath"></i>
                                        <span><?php echo esc_html($hvnly_bathrooms); ?> <?php esc_html_e('Baths', 'havenlytics'); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($hvnly_sqft) : ?>
                                    <div class="hvnly-property-single__similar-feature">
                                        <i class="fas fa-vector-square"></i>
                                        <span><?php echo esc_html($hvnly_sqft); ?> <?php esc_html_e('SqFt', 'havenlytics'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <a href="<?php the_permalink(); ?>" class="hvnly-property-single__btn hvnly-property-single__btn--full" style="margin-top: var(--hvnly-space-md); color: var(--hvnly-color-white);">
                            <i class="fas fa-eye"></i> 
                            <?php esc_html_e('View Details', 'havenlytics'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php 
                $hvnly_slide_index++;
            endwhile; 
        else : ?>
            <!-- No similar properties message -->
            <div class="hvnly-property-single__carousel-slide">
                <div class="hvnly-property-single__similar-card hvnly-property-single__similar-card--empty">
                    <div class="hvnly-property-single__similar-content" style="text-align: center; padding: var(--hvnly-space-xl);">
                        <i class="fas fa-home" style="font-size: 48px; margin-bottom: var(--hvnly-space-md); color: var(--hvnly-text-secondary, #666);"></i>
                        <h3><?php esc_html_e('No Similar Properties', 'havenlytics'); ?></h3>
                        <p><?php esc_html_e('No similar properties found at the moment.', 'havenlytics'); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php wp_reset_postdata(); ?>