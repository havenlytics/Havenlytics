<?php
/**
 * Title Section Template
 * 
 * This template displays the title section for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/title-section.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/title-section.php
 * 3. Modify the copied file to customize the title section
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = get_the_ID();
$hvnly_address = get_post_meta($hvnly_property_id, '_hvnly_property_address_line_1', true);
$hvnly_street = get_post_meta($hvnly_property_id, '_hvnly_property_street', true);
$hvnly_city = get_post_meta($hvnly_property_id, '_hvnly_property_town_city', true);
$hvnly_zip = get_post_meta($hvnly_property_id, '_hvnly_property_zip_code', true);

// Build full address with better formatting
$hvnly_address_parts = array();
if ($hvnly_address) {
    $hvnly_address_parts[] = $hvnly_address;
} elseif ($hvnly_street) {
    $hvnly_address_parts[] = $hvnly_street;
}
if ($hvnly_city) {
    $hvnly_address_parts[] = $hvnly_city;
}
if ($hvnly_zip) {
    $hvnly_address_parts[] = $hvnly_zip;
}
$hvnly_full_address = !empty($hvnly_address_parts) ? implode(', ', $hvnly_address_parts) : '';

// Get property status with dynamic class
$hvnly_status_terms = get_the_terms($hvnly_property_id, 'hvnly_prop_status');
$hvnly_status_badge = '';
$hvnly_status_class = '';

if ($hvnly_status_terms && !is_wp_error($hvnly_status_terms)) {
    $hvnly_status = $hvnly_status_terms[0];
    $hvnly_status_slug = sanitize_html_class($hvnly_status->slug);
    $hvnly_status_class = ' hvnly-property-single__status-badge--' . $hvnly_status_slug;
    $hvnly_status_badge = '<span class="hvnly-property-single__status-badge' . esc_attr( $hvnly_status_class ) . '">' . esc_html($hvnly_status->name) . '</span>';
}

$hvnly_dept_terms = get_the_terms($hvnly_property_id, 'hvnly_prop_depts');
$hvnly_department_badges = array();

if ($hvnly_dept_terms && !is_wp_error($hvnly_dept_terms)) {
    foreach ($hvnly_dept_terms as $hvnly_dept_term) {
        $hvnly_dept_slug = sanitize_html_class($hvnly_dept_term->slug);
        $hvnly_department_badges[] = sprintf(
            '<span class="hvnly-property-single__department-badge hvnly-property-single__department-badge--%1$s">%2$s</span>',
            esc_attr($hvnly_dept_slug),
            esc_html($hvnly_dept_term->name)
        );
    }
}

// Get property ID if available for additional metadata
$hvnly_property_unique_id = get_post_meta($hvnly_property_id, '_hvnly_unique_property_id', true);

// Get property features for quick meta
$hvnly_bedrooms = get_post_meta($hvnly_property_id, '_hvnly_property_bedrooms', true);
$hvnly_bathrooms = get_post_meta($hvnly_property_id, '_hvnly_property_bathrooms', true);
$hvnly_area = get_post_meta($hvnly_property_id, '_hvnly_property_sqft', true);

// Get price value and format using helper function
$hvnly_actual_price = get_post_meta($hvnly_property_id, '_hvnly_property_price', true);
$hvnly_formatted_price = hvnly_format_price($hvnly_actual_price);
?>
<div class="hvnly-property-single__title-section">
    <div class="hvnly-property-single__title-wrapper">
        <?php if ($hvnly_status_badge || !empty($hvnly_department_badges)) : ?>
            <div class="hvnly-property-single__badge-wrapper">
                <?php if ($hvnly_status_badge) : ?>
                    <?php echo wp_kses_post($hvnly_status_badge); ?>
                <?php endif; ?>
                <?php foreach ($hvnly_department_badges as $hvnly_department_badge) : ?>
                    <?php echo wp_kses_post($hvnly_department_badge); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <h1 class="hvnly-property-single__title"><?php the_title(); ?></h1>
        
        <?php if ($hvnly_full_address) : ?>
            <div class="hvnly-property-single__address">
                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                <span><?php echo esc_html($hvnly_full_address); ?></span>
                
            </div>
        <?php endif; ?>
       
    </div>
    
    <div class="hvnly-property-single__price">
        <?php echo wp_kses_post($hvnly_formatted_price); ?>
    </div>
        
   
</div>