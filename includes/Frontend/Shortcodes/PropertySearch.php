<?php
/**
 * Property Search
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Shortcodes
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */


namespace HvnlyNab\Frontend\Shortcodes;

use HvnlyNab\Frontend\Query\PropertyQueryExecutor;

if ( ! defined('ABSPATH')) {
    exit;
}

class PropertySearch extends AbstractShortcode {

    protected $tag = 'hvnly_property_search';

    protected $default_atts = array(
        'posts_per_page' => 4,
        'class'          => '',
        'id'             => '',
    );

    public function __construct() {
        parent::__construct();
    }

    public function render( $atts, $content = '' ) {

        $this->before_render();

        // ⭐ Flag to detect shortcode rendering (used by hvnly_page_title)
        global $hvnly_has_shortcode;
        $temp_shortcode_flag = $hvnly_has_shortcode ?? false;
        $hvnly_has_shortcode = true;

        $atts = shortcode_atts($this->default_atts, $atts, $this->tag);

        $current_filters = function_exists('hvnly_get_current_filters') ? hvnly_get_current_filters() : array();

        $paged = max(
            1,
            (int) get_query_var('paged'),
            (int) get_query_var('page'),
            isset($current_filters['paged']) ? (int) $current_filters['paged'] : 1
        );

        // Keep initial SSR query and AJAX query architecture aligned.
        $properties_query = PropertyQueryExecutor::query(
            is_array($current_filters) ? $current_filters : array(),
            (int) $paged,
            (int) $atts['posts_per_page'],
            array(
                'filter_context' => 'shortcode_initial',
            )
        );

        global $wp_query, $post;

        $temp_query = $wp_query;
        $temp_post  = $post;

        $wp_query = $properties_query;
        $post     = $properties_query->posts[0] ?? null;

        ob_start();

        // ==============================================
        // ADD INLINE CSS WHEN SIDEBAR IS HIDDEN
        // ==============================================
        $show_sidebar = function_exists('hvnly_should_show_filter_sidebar') ? hvnly_should_show_filter_sidebar() : true;

        if ( ! $show_sidebar) {
            ?>
<style>
/* Hide sidebar in shortcode when setting is OFF */
.hvnly-property-filter-sidebar {
    display: none !important;
}

/* Make main container full width */
.hvnly-with-sidebar__contents__grids {
    grid-template-columns: 1fr !important;
}

/* Adjust grid columns to 3 columns */
.hvnly-property-grid-view.hvnly-grid-view {
    grid-template-columns: repeat(3, 1fr) !important;
}

/* Responsive - Tablet */
@media (max-width: 1200px) {
    .hvnly-property-grid-view.hvnly-grid-view {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}

/* Responsive - Mobile */
@media (max-width: 768px) {
    .hvnly-property-grid-view.hvnly-grid-view {
        grid-template-columns: 1fr !important;
    }
}
</style>
			<?php
        }

        $container_classes = array();

        if ( ! empty($atts['class'])) {
            $container_classes[] = esc_attr($atts['class']);
        }

        $container_id = ! empty($atts['id']) ? ' id="' . esc_attr($atts['id']) . '"' : '';

        // Only output class attribute if there are classes
        if ( ! empty($container_classes)) {
            echo '<div class="' . esc_attr(implode(' ', $container_classes)) . '"' . $container_id . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo '<div' . $container_id . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        do_action('hvnly_before_main_content');

        if (function_exists('hvnly_get_template_part')) {
            hvnly_get_template_part('content', 'archive-property');
        }

        do_action('hvnly_after_main_content');

        echo '</div>';

        $output = ob_get_clean();

        // Restore globals
        $wp_query = $temp_query;
        $post     = $temp_post;
        wp_reset_postdata();

        // ⭐ Restore shortcode flag
        $hvnly_has_shortcode = $temp_shortcode_flag;

        $this->after_render();

        return $output;
    }
}