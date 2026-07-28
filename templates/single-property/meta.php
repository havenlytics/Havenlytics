<?php
/**
 * Meta Template
 * 
 * This template displays the meta information for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/meta.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/meta.php
 * 3. Modify the copied file to customize the meta information
 * 
 * @package     Havenlytics
 * @subpackage  Templates/single-property/
 * @since       2.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_property_id = get_the_ID();

// Get all meta fields
$hvnly_actual_price = get_post_meta($hvnly_property_id, '_hvnly_property_price', true);
$hvnly_price = hvnly_format_price($hvnly_actual_price);
$hvnly_bedrooms = get_post_meta($hvnly_property_id, '_hvnly_property_bedrooms', true);
$hvnly_bathrooms = get_post_meta($hvnly_property_id, '_hvnly_property_bathrooms', true);
$hvnly_half_bathrooms = get_post_meta($hvnly_property_id, '_hvnly_property_half_bathrooms', true);
$hvnly_reception_rooms = get_post_meta($hvnly_property_id, '_hvnly_property_reception_rooms', true);
$hvnly_kitchens = get_post_meta($hvnly_property_id, '_hvnly_property_kitchens', true);
$hvnly_total_rooms = get_post_meta($hvnly_property_id, '_hvnly_property_total_rooms', true);
$hvnly_floors = get_post_meta($hvnly_property_id, '_hvnly_property_floors', true);
$hvnly_year_built = get_post_meta($hvnly_property_id, '_hvnly_property_year_built', true);
$hvnly_sqft = get_post_meta($hvnly_property_id, '_hvnly_property_sqft', true);
$hvnly_lot_size = get_post_meta($hvnly_property_id, '_hvnly_property_lot_size', true);
$hvnly_garage_sqft = get_post_meta($hvnly_property_id, '_hvnly_property_garage_sqft', true);
$hvnly_mls_number = get_post_meta($hvnly_property_id, '_hvnly_property_mls_number', true);
$hvnly_property_id_number = get_post_meta($hvnly_property_id, '_hvnly_unique_property_id', true);

// Calculate total bathrooms
$hvnly_total_bathrooms = $hvnly_bathrooms;
if ($hvnly_half_bathrooms) {
    /* translators: %1$s: full bathrooms count, %2$s: half bathrooms count */
    $hvnly_total_bathrooms = sprintf(
        /* translators: %1$s: full bathrooms, %2$s: half bathrooms */
        __( '%1$s + %2$s half', 'havenlytics' ),
        $hvnly_bathrooms,
        $hvnly_half_bathrooms
    );
}
?>
<div class="hvnly-property-single__meta">
    <?php if (!empty($hvnly_bedrooms)) : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-bed"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_bedrooms); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Bedrooms', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($hvnly_bathrooms)) : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-bath"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_total_bathrooms); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Bathrooms', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($hvnly_reception_rooms)) : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-sofa"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_reception_rooms); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Reception', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($hvnly_kitchens)) : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-utensils"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_kitchens); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Kitchens', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($hvnly_sqft)) : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-ruler"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_sqft); ?> <?php esc_html_e( 'sqft', 'havenlytics' ); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Area', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($hvnly_garage_sqft)) : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-car"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_garage_sqft); ?> <?php esc_html_e( 'sqft', 'havenlytics' ); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Garage', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($hvnly_total_rooms)) : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-grid"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_total_rooms); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Total Rooms', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($hvnly_floors)) : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-layers"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_floors); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Floors', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($hvnly_lot_size)) : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-lot-area"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_lot_size); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Lot Size', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($hvnly_year_built) && $hvnly_year_built != '0') : ?>
        <div class="hvnly-property-single__meta-item">
            <svg class="hvnly-icon" aria-hidden="true"><use xlink:href="#hvnly-calendar"></use></svg>
            <span class="hvnly-property-single__meta-value"><?php echo esc_html($hvnly_year_built); ?></span>
            <span class="hvnly-property-single__meta-label"><?php esc_html_e('Built', 'havenlytics'); ?></span>
        </div>
    <?php endif; ?>

</div>

<?php if (!empty($hvnly_property_id_number) || !empty($hvnly_mls_number)) : ?>
<div class="hvnly-property-single__property-ids">
    <?php if (!empty($hvnly_property_id_number)) : ?>
        <div class="hvnly-property-single__property-id">
            <strong><?php esc_html_e('Property ID:', 'havenlytics'); ?></strong> <?php echo esc_html($hvnly_property_id_number); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($hvnly_mls_number)) : ?>
        <div class="hvnly-property-single__property-id">
            <strong><?php esc_html_e('MLS #:', 'havenlytics'); ?></strong> <?php echo esc_html($hvnly_mls_number); ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>