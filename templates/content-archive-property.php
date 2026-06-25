<?php
/**
 * Content Archive Property Template
 * 
 * This template displays the content for the archive page for properties.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/content-archive-property.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/content-archive-property.php
 * 3. Modify the copied file to customize the archive page
 * 
 * @package     Havenlytics
 * @subpackage  Templates/
 * @since       2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


global $wp_query;

// Check if filter sidebar should be shown
$hvnly_show_sidebar = function_exists( 'hvnly_should_show_filter_sidebar' ) ? hvnly_should_show_filter_sidebar() : true;

// Calculate property range for display
$hvnly_total_properties = $wp_query->found_posts;
$hvnly_current_page     = max( 1, get_query_var( 'paged' ) );
$hvnly_per_page         = function_exists( 'hvnly_get_properties_per_page' ) ? hvnly_get_properties_per_page() : 4;
$hvnly_start            = ( ( $hvnly_current_page - 1 ) * $hvnly_per_page ) + 1;
$hvnly_end              = min( $hvnly_current_page * $hvnly_per_page, $hvnly_total_properties );
$hvnly_current_filters  = hvnly_get_current_filters();
$hvnly_sidebar_layout_class = function_exists( 'hvnly_get_search_sidebar_layout_class' )
    ? hvnly_get_search_sidebar_layout_class()
    : '';

// Calculate max pages properly
$hvnly_max_pages = $wp_query->max_num_pages;
if ( $hvnly_max_pages <= 1 && $hvnly_total_properties > $hvnly_per_page ) {
    $hvnly_max_pages = ceil( $hvnly_total_properties / $hvnly_per_page );
}

do_action( 'hvnly_before_archive_property' );
?>

<!-- Property Section Title -->
<?php hvnly_get_template_part( 'archive/section', 'title' ); ?>

<!-- Dynamic Search Form -->
<?php hvnly_get_template_part( 'search/search', 'filters' ); ?>

<!-- Dynamic Main Grid Property list -->
<div class="hvnly-with-sidebar__contents__grids<?php echo $hvnly_sidebar_layout_class ? ' ' . esc_attr( $hvnly_sidebar_layout_class ) : ''; ?>">

    <!-- Filter Sidebar -->
    <?php if ( $hvnly_show_sidebar ) : ?>
    <?php hvnly_get_template_part( 'search/filter', 'sidebar' ); ?>
    <?php endif; ?>

    <!-- Property Listings -->
    <section class="hvnly-property--grid--listings">

        <!-- View Controls -->
        <div class="hvnly-property--view--controls">
            <?php
            hvnly_get_template_part( 'search/result', 'count', array(
                'total_properties' => $hvnly_total_properties,
                'start'            => $hvnly_start,
                'end'              => $hvnly_end,
                'current_filters'  => $hvnly_current_filters,
            ) );
            ?>
            <?php hvnly_get_template_part( 'archive/view', 'controls' ); ?>
        </div>

        <!-- Dynamic Property Grid -->
        <?php hvnly_get_template_part( 'archive/property', 'loop' ); ?>

        <!-- Unified Pagination -->
        <?php
        if ( function_exists( 'hvnly_render_property_listing_pagination' ) ) {
            hvnly_render_property_listing_pagination(
                array(
                    'current_page' => $hvnly_current_page,
                    'max_pages'    => $hvnly_max_pages,
                    'per_page'     => $hvnly_per_page,
                    'found_posts'  => $hvnly_total_properties,
                    'instance_id'  => 'main-archive',
                )
            );
        }
        ?>

    </section>
</div>

<?php
do_action( 'hvnly_after_archive_property' );