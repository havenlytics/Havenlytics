<?php
/**
 * Breadcrumb Template
 * 
 * This template displays the breadcrumb navigation for single property pages.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property/breadcrumb.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property/breadcrumb.php
 * 3. Modify the copied file to customize the breadcrumb navigation
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
$hvnly_locations = get_the_terms($hvnly_property_id, 'hvnly_prop_locations');
$hvnly_types = get_the_terms($hvnly_property_id, 'hvnly_prop_types');

$hvnly_primary_type = $hvnly_types && !is_wp_error($hvnly_types) ? $hvnly_types[0] : null;
$hvnly_primary_location = $hvnly_locations && !is_wp_error($hvnly_locations) ? $hvnly_locations[0] : null;

// Sanitize and escape data for use in attributes
$hvnly_home_url = esc_url(home_url('/'));
$hvnly_archive_url = esc_url(get_post_type_archive_link('hvnly_property'));
$hvnly_type_url = $hvnly_primary_type ? esc_url(get_term_link($hvnly_primary_type)) : '';
$hvnly_location_url = $hvnly_primary_location ? esc_url(get_term_link($hvnly_primary_location)) : '';
$hvnly_type_name = $hvnly_primary_type ? esc_html($hvnly_primary_type->name) : '';
$hvnly_location_name = $hvnly_primary_location ? esc_html($hvnly_primary_location->name) : '';
$hvnly_current_title = esc_html(wp_trim_words(get_the_title(), 4, '...'));
?>
<div class="hvnly-property-breadcrumb">
    <div class="hvnly-property-breadcrumb__container">
        <a href="<?php echo esc_url( $hvnly_home_url ); ?>" class="hvnly-property-breadcrumb__item"
            aria-label="<?php esc_attr_e('Go to homepage', 'havenlytics'); ?>">
            <i class="fas fa-home" aria-hidden="true"></i>
            <span><?php esc_html_e('Home', 'havenlytics'); ?></span>
        </a>

        <i class="fas fa-chevron-right hvnly-property-breadcrumb__separator" aria-hidden="true"></i>

        <a href="<?php echo esc_url( $hvnly_archive_url ); ?>" class="hvnly-property-breadcrumb__item"
            aria-label="<?php esc_attr_e('View all properties', 'havenlytics'); ?>">
            <i class="fas fa-building" aria-hidden="true"></i>
            <span><?php esc_html_e('Properties', 'havenlytics'); ?></span>
        </a>

        <?php if ($hvnly_primary_type) : ?>
        <i class="fas fa-chevron-right hvnly-property-breadcrumb__separator" aria-hidden="true"></i>
        <a href="<?php echo esc_url( $hvnly_type_url ); ?>" class="hvnly-property-breadcrumb__item" aria-label="<?php echo esc_attr(sprintf(
                /* translators: %1$s: property type name */
                __('View all %s properties', 'havenlytics'), $hvnly_type_name)); ?>">
            <i class="fas fa-tag" aria-hidden="true"></i>
            <span><?php echo esc_html( $hvnly_type_name ); ?></span>
        </a>
        <?php endif; ?>

        <?php if ($hvnly_primary_location) : ?>
        <i class="fas fa-chevron-right hvnly-property-breadcrumb__separator" aria-hidden="true"></i>
        <a href="<?php echo esc_url( $hvnly_location_url ); ?>" class="hvnly-property-breadcrumb__item" aria-label="<?php echo esc_attr(sprintf(
                /* translators: %1$s: property location name */
                __('View all properties in %s', 'havenlytics'), $hvnly_location_name)); ?>">
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
            <span><?php echo esc_html( $hvnly_location_name ); ?></span>
        </a>
        <?php endif; ?>

        <i class="fas fa-chevron-right hvnly-property-breadcrumb__separator" aria-hidden="true"></i>

        <span class="hvnly-property-breadcrumb__current" aria-current="page">
            <?php echo esc_html( $hvnly_current_title ); ?>
        </span>
    </div>
</div>