<?php

/**
 * View Controls Template
 * 
 * This template displays the view controls for the property archive.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/view-controls.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/view-controls.php
 * 3. Modify the copied file to customize the view controls display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get current filters
$hvnly_current_filters = hvnly_get_current_filters();
$hvnly_current_orderby = $hvnly_current_filters['orderby'] ?? '';
$hvnly_current_view_type = $hvnly_current_filters['view_type'] ?? '';

// If no orderby from URL, get the saved default from settings
if (empty($hvnly_current_orderby)) {
    if (function_exists('hvnly_get_default_sort_option')) {
        $hvnly_current_orderby = hvnly_get_default_sort_option();
    } else {
        $hvnly_current_orderby = 'date';
    }
}

// If no view_type from URL, get the saved default from settings
if (empty($hvnly_current_view_type)) {
    if (function_exists('hvnly_get_default_view_option')) {
        $hvnly_current_view_type = hvnly_get_default_view_option();
    } else {
        $hvnly_current_view_type = 'grid';
    }
}
?>

<div class="hvnly-property-view-options">
    <label for="hvnly-property-sort-select"
        class="hvnly-sortby-txt"><?php esc_html_e('Sort By:', 'havenlytics'); ?></label>
    <div class="hvnly-property-sort-dropdown">
        <select id="hvnly-property-sort-select" class="hvnly-property-sort-select" name="orderby" data-filter="orderby"
            aria-label="<?php esc_attr_e('Sort properties by', 'havenlytics'); ?>">
            <option value="a-to-z-title" <?php selected($hvnly_current_orderby, 'a-to-z-title'); ?>>
                <?php esc_html_e('A to Z (title)', 'havenlytics'); ?>
            </option>
            <option value="z-to-a-title" <?php selected($hvnly_current_orderby, 'z-to-a-title'); ?>>
                <?php esc_html_e('Z to A (title)', 'havenlytics'); ?>
            </option>
            <option value="date" <?php selected($hvnly_current_orderby, 'date'); ?>>
                <?php esc_html_e('Newest', 'havenlytics'); ?>
            </option>
            <option value="date_oldest" <?php selected($hvnly_current_orderby, 'date_oldest'); ?>>
                <?php esc_html_e('Oldest', 'havenlytics'); ?>
            </option>
            <option value="price_low" <?php selected($hvnly_current_orderby, 'price_low'); ?>>
                <?php esc_html_e('Price: Low to High', 'havenlytics'); ?>
            </option>
            <option value="price_high" <?php selected($hvnly_current_orderby, 'price_high'); ?>>
                <?php esc_html_e('Price: High to Low', 'havenlytics'); ?>
            </option>
            <option value="area" <?php selected($hvnly_current_orderby, 'area'); ?>>
                <?php esc_html_e('Square Footage', 'havenlytics'); ?>
            </option>
            <option value="bedrooms" <?php selected($hvnly_current_orderby, 'bedrooms'); ?>>
                <?php esc_html_e('Number of Bedrooms', 'havenlytics'); ?>
            </option>
            <option value="views" <?php selected($hvnly_current_orderby, 'views'); ?>>
                <?php esc_html_e('View Counts', 'havenlytics'); ?>
            </option>
            <option value="rating" <?php selected($hvnly_current_orderby, 'rating'); ?>>
                <?php esc_html_e('Rating', 'havenlytics'); ?>
            </option>
            <option value="rand_property" <?php selected($hvnly_current_orderby, 'rand_property'); ?>>
                <?php esc_html_e('Random Property', 'havenlytics'); ?>
            </option>
        </select>
    </div>

    <div class="hvnly-property-view-buttons" role="group"
        aria-label="<?php esc_attr_e('View layout options', 'havenlytics'); ?>">
        <button class="hvnly-property-view-btn <?php echo $hvnly_current_view_type === 'list' ? 'active' : ''; ?>"
            data-view="list" aria-label="<?php esc_attr_e('List view', 'havenlytics'); ?>">
            <i class="fas fa-list" aria-hidden="true"></i>
        </button>
        <button class="hvnly-property-view-btn <?php echo $hvnly_current_view_type === 'grid' ? 'active' : ''; ?>"
            data-view="grid" aria-label="<?php esc_attr_e('Grid view', 'havenlytics'); ?>">
            <i class="fas fa-th" aria-hidden="true"></i>
        </button>
        <button class="hvnly-property-view-btn <?php echo $hvnly_current_view_type === 'map' ? 'active' : ''; ?>"
            data-view="map" aria-label="<?php esc_attr_e('Map view', 'havenlytics'); ?>">
            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
        </button>
    </div>
</div>