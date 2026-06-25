<?php
/**
 * Property List Template
 * 
 * This template displays the property list.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/shortcodes/property-list.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/shortcodes/property-list.php
 * 3. Modify the copied file to customize the property list display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/search
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Prefix all variables with hvnly_ to avoid global namespace conflicts
$hvnly_properties = $args['properties'];
$hvnly_atts       = $args['atts'];
$hvnly_paged      = $args['paged'];
$hvnly_shortcode  = $args['shortcode'];

// Calculate pagination info.
$hvnly_total_properties = (int) $hvnly_properties->found_posts;
$hvnly_per_page         = (int) $hvnly_atts['posts_per_page'];
$hvnly_start            = (($hvnly_paged - 1) * $hvnly_per_page) + 1;
$hvnly_end              = min($hvnly_paged * $hvnly_per_page, $hvnly_total_properties);
$hvnly_total_pages      = (int) $hvnly_properties->max_num_pages;

// Determine if we should show results bar at top and/or bottom.
$hvnly_show_top_bar    = in_array($hvnly_atts['results_bar_position'], ['top', 'both'], true);
$hvnly_show_bottom_bar = in_array($hvnly_atts['results_bar_position'], ['bottom', 'both'], true);

// Container classes.
$hvnly_container_classes = array_filter([
    'hvnly-property-display-shortcode',
    'hvnly-property-archive-wrap',
    'hvnly-list-view',
    esc_attr($hvnly_atts['class']),
]);

$hvnly_container_id = !empty($hvnly_atts['id']) ? ' id="' . esc_attr($hvnly_atts['id']) . '"' : '';

// Set global $wp_query for template compatibility.
global $wp_query, $post;
$hvnly_temp_query = $wp_query;
$hvnly_temp_post  = $post;
$wp_query   = $hvnly_properties;

?>
<div class="<?php echo esc_attr(implode(' ', $hvnly_container_classes)); ?>"<?php echo $hvnly_container_id; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-shortcode="<?php echo esc_attr($hvnly_shortcode); ?>">
    
    <?php
    /**
     * Top Results Bar
     * 
     * Shows results count and pagination above the list if enabled.
     */
    if ($hvnly_show_top_bar) :
        // Determine if we have anything to show
        $hvnly_has_results_to_show = ($hvnly_atts['show_results_count'] === 'yes' && $hvnly_total_properties > 0);
        $hvnly_has_pagination_to_show = ($hvnly_atts['show_pagination'] === 'yes' && $hvnly_total_pages > 1);
        
        if ($hvnly_has_results_to_show || $hvnly_has_pagination_to_show) :
            hvnly_get_template('global/results-bar-shortcode', [
                'properties'       => $hvnly_properties,
                'total'            => $hvnly_total_properties,
                'start'            => $hvnly_start,
                'end'              => $hvnly_end,
                'per_page'         => $hvnly_per_page,
                'current_page'     => $hvnly_paged,
                'total_pages'      => $hvnly_total_pages,
                'show_results'     => $hvnly_atts['show_results_count'] === 'yes',
                'show_pagination'  => $hvnly_atts['show_pagination'] === 'yes',
                'context'          => 'shortcode',
                'position'         => 'top',
                'classes'          => 'hvnly-property-list__results-bar-top',
            ]);
        endif;
    endif;
    ?>
    
    <div class="hvnly-property-archive-container">
        
        <?php
        /**
         * Action: hvnly_before_shortcode_loop
         * 
         * Allows adding content before the property loop.
         *
         * @param array $atts Shortcode attributes.
         */
        do_action('hvnly_before_shortcode_loop', $hvnly_atts);
        ?>
        
        <?php hvnly_get_template_part('search/loop-start'); ?>
        
        <div class="hvnly-property-list" style="display: flex; flex-direction: column; gap: 20px;">
            
            <?php if ($hvnly_properties->have_posts()) : ?>
                <?php while ($hvnly_properties->have_posts()) : $hvnly_properties->the_post(); ?>
                    <?php
                    /**
                     * Render property card with list-specific template if available.
                     * Falls back to grid card template if list template doesn't exist.
                     */
                    if ( function_exists( 'hvnly_locate_template' ) && hvnly_locate_template( 'content-property-list.php' ) ) {
                        hvnly_get_template_part( 'content', 'property-list' );
                    } elseif ( function_exists( 'hvnly_render_property_card' ) ) {
                        hvnly_render_property_card( get_the_ID() );
                    } else {
                        hvnly_get_template_part( 'content', 'property' );
                    }
                    ?>
                <?php endwhile; ?>
            <?php else : ?>
                <?php hvnly_get_template_part('content', 'none'); ?>
            <?php endif; ?>
            
        </div>
        
        <?php hvnly_get_template_part('search/loop-end'); ?>
        
        <?php
        /**
         * Action: hvnly_after_shortcode_loop
         * 
         * Allows adding content after the property loop.
         *
         * @param array $atts Shortcode attributes.
         */
        do_action('hvnly_after_shortcode_loop', $hvnly_atts);
        ?>
        
    </div>
    
    <?php
    /**
     * Bottom Results Bar
     * 
     * Shows results count and pagination below the list if enabled.
     */
    if ($hvnly_show_bottom_bar) :
        // Determine if we have anything to show
        $hvnly_has_results_to_show = ($hvnly_atts['show_results_count'] === 'yes' && $hvnly_total_properties > 0);
        $hvnly_has_pagination_to_show = ($hvnly_atts['show_pagination'] === 'yes' && $hvnly_total_pages > 1);
        
        if ($hvnly_has_results_to_show || $hvnly_has_pagination_to_show) :
            hvnly_get_template('global/results-bar-shortcode', [
                'properties'       => $hvnly_properties,
                'total'            => $hvnly_total_properties,
                'start'            => $hvnly_start,
                'end'              => $hvnly_end,
                'per_page'         => $hvnly_per_page,
                'current_page'     => $hvnly_paged,
                'total_pages'      => $hvnly_total_pages,
                'show_results'     => $hvnly_atts['show_results_count'] === 'yes',
                'show_pagination'  => $hvnly_atts['show_pagination'] === 'yes',
                'context'          => 'shortcode',
                'position'         => 'bottom',
                'classes'          => 'hvnly-property-list__results-bar',
            ]);
        endif;
    endif;
    ?>
    
</div>
<?php
// Reset global query variables.
$wp_query = $hvnly_temp_query;
$post     = $hvnly_temp_post;
wp_reset_postdata();