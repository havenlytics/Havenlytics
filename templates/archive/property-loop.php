<?php
/**
 * Property Loop Template
 * 
 * This template displays the loop for properties in grid, list, or map view.
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive
 * @since       2.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current filters and view type
$hvnly_current_filters = hvnly_get_current_filters();
$hvnly_view_type = isset( $hvnly_current_filters['view_type'] ) ? $hvnly_current_filters['view_type'] : '';

if ( empty( $hvnly_view_type ) ) {
    $hvnly_view_type = function_exists( 'hvnly_get_default_view_option' ) ? hvnly_get_default_view_option() : 'grid';
}

$hvnly_per_page = function_exists( 'hvnly_get_properties_per_page' ) ? hvnly_get_properties_per_page() : 12;

// Set CSS classes
$hvnly_view_class = '';
$hvnly_display_style = '';

if ( $hvnly_view_type === 'grid' ) {
    $hvnly_view_class = 'hvnly-grid-view grid-view';
    $hvnly_display_style = 'display: grid;';
} elseif ( $hvnly_view_type === 'list' ) {
    $hvnly_view_class = 'hvnly-list-view list-view';
    $hvnly_display_style = 'display: flex; flex-direction: column; gap: 20px;';
} elseif ( $hvnly_view_type === 'map' ) {
    $hvnly_view_class = 'map-view';
    $hvnly_display_style = 'display: none;';
}

?>

<!-- Property Grid -->
<?php
// Unique per instance so Archive / Search / Featured can coexist on one page.
// JS targets .hvnly-property-grid-view; the id is for legacy / Elementor hooks only.
static $hvnly_property_grid_seq = 0;
$hvnly_property_grid_seq++;
$hvnly_property_grid_dom_id = 'hvnly-property-grid-' . $hvnly_property_grid_seq;
?>
<div class="hvnly-property-grid-view <?php echo esc_attr( $hvnly_view_class ); ?>"
    id="<?php echo esc_attr( $hvnly_property_grid_dom_id ); ?>" data-view-type="<?php echo esc_attr( $hvnly_view_type ); ?>"
    style="<?php echo esc_attr( $hvnly_display_style ); ?>">
    <?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
    <?php hvnly_get_template_part( 'content', 'property' ); ?>
    <?php endwhile; ?>
    <?php else : ?>
    <?php hvnly_get_template_part( 'content', 'none' ); ?>
    <?php endif; ?>
</div>

<!-- Map Placeholder - Simple container, JS will load map here -->
<div id="hvnly-map-placeholder" class="hvnly-map-placeholder"
    data-provider="<?php echo esc_attr( function_exists('hvnly_get_map_provider') ? hvnly_get_map_provider() : 'leaflet' ); ?>"
    style="<?php echo $hvnly_view_type === 'map' ? 'display: block;' : 'display: none; min-height: 500px;'; ?>"></div>