<?php
/**
 * Sidebar AJAX Handler for Havenlytics
 *
 * @package     Havenlytics
 * @subpackage  Frontend
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * SidebarAjaxHandler class
 *
 * @since 2.0.0
 */
class SidebarAjaxHandler {

    /**
     * Sidebar search filters ViewModel
     *
     * @var ViewModels\SidebarSearchFilters
     */
    private $sidebar_filters;

    /**
     * Constructor
     */
    public function __construct() {
        $this->sidebar_filters = new ViewModels\SidebarSearchFilters();

        add_action('wp_ajax_hvnly_sidebar_filter_search', array( $this, 'sidebar_filter_search' ));
        add_action('wp_ajax_nopriv_hvnly_sidebar_filter_search', array( $this, 'sidebar_filter_search' ));

        // Cache invalidation hooks for sidebar
        add_action('save_post_hvnly_property', array( $this, 'clear_sidebar_cache' ));
        add_action('delete_post', array( $this, 'clear_sidebar_cache' ), 10, 2);
        add_action('before_delete_post', array( $this, 'clear_sidebar_cache' ), 10, 2);
        add_action('hvnly_cleared_property_cache', array( $this, 'clear_sidebar_cache_on_event' ));
        add_action('set_object_terms', array( $this, 'clear_sidebar_cache_on_term_assignment' ), 10, 6);
        add_action('created_term', array( $this, 'clear_sidebar_terms_cache' ));
        add_action('edited_term', array( $this, 'clear_sidebar_terms_cache' ));
        add_action('delete_term', array( $this, 'clear_sidebar_terms_cache' ));
    }

    /**
     * Sidebar filter search with transient caching
     *
     * @return void
     */
    public function sidebar_filter_search() {
        // Unslash and sanitize nonce
        $nonce_raw = filter_input(INPUT_POST, 'nonce', FILTER_UNSAFE_RAW);
        $nonce     = $nonce_raw ? sanitize_text_field($nonce_raw) : '';

        if ( ! wp_verify_nonce($nonce, 'hvnly_ajax_request')) {
            wp_send_json_error(__( 'Security check failed', 'havenlytics' ));
        }

        try {
            // Sanitize page and per_page inputs
            $page     = absint(filter_input(INPUT_POST, 'page', FILTER_VALIDATE_INT) ?: 1);
            $per_page = absint(filter_input(INPUT_POST, 'per_page', FILTER_VALIDATE_INT) ?: get_option('posts_per_page', 12));

            // Build query args specifically for sidebar filters
            $post_data_raw = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW) ?: array();
            $post_data     = array_map('sanitize_text_field', $post_data_raw); // sanitize all POST fields

            $args = $this->sidebar_filters->build_sidebar_query_args($post_data, $page, $per_page);

            // Generate cache key for sidebar filters
            $cache_key = $this->sidebar_filters->generate_sidebar_cache_key($args, $page, $per_page);

            // Try to get cached sidebar results
            $cached_results = false;
            if (\hvnly_is_cache_enabled()) {
                $cached_results = $this->sidebar_filters->get_cached_sidebar_results($args, $page, $per_page);
            }

            if (false !== $cached_results) {
                wp_send_json_success($cached_results);
            }

            // No cache found - perform fresh query
            $query_started    = microtime(true);
            $properties_query = new \WP_Query($args);
            if (function_exists('HVNLY_NAB') && HVNLY_NAB()->engine() && method_exists(HVNLY_NAB()->engine(), 'track_query_executed')) {
                HVNLY_NAB()->engine()->track_query_executed(microtime(true) - $query_started);
            }

            if (is_wp_error($properties_query->posts)) {
                wp_send_json_error(__( 'Query error occurred', 'havenlytics' ));
            }

            // Generate response data using existing template system
            $response_data = $this->generate_sidebar_search_response($properties_query, $page, $per_page, $post_data);

            // Cache the sidebar results
            if (\hvnly_is_cache_enabled()) {
                $this->sidebar_filters->set_cached_sidebar_results($cache_key, $response_data);
            }

            wp_send_json_success($response_data);

        } catch (\Exception $e) {
            wp_send_json_error(__( 'An error occurred while filtering properties. Please try again.', 'havenlytics' ));
        } catch (\Error $e) {
            wp_send_json_error(__( 'An error occurred while filtering properties. Please try again.', 'havenlytics' ));
        }
    }

    /**
     * Generate sidebar search response data
     *
     * @param \WP_Query $query Properties query
     * @param int       $page Current page
     * @param int       $per_page Posts per page
     * @param array     $filters Current filters
     * @return array
     */
    private function generate_sidebar_search_response( $query, $page, $per_page, $filters ) {
        global $wp_query;

        // Set up global query for template compatibility
        $original_query = $wp_query;
        $wp_query       = $query;

        ob_start();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                hvnly_get_template_part('content', 'property');
            }
            wp_reset_postdata();
        } else {
            hvnly_get_template_part('content', 'none');
        }

        $html = ob_get_clean();

        // Restore original query
        $wp_query = $original_query;

        // Generate pagination
        ob_start();
        $this->generate_pagination_template($query, $page);
        $pagination_html = ob_get_clean();

        // Generate results count
        ob_start();
        $this->generate_results_count_template($query, $page, $per_page, $filters);
        $results_count_html = ob_get_clean();

        // Calculate start and end for display
        $start = ( ( $page - 1 ) * $per_page ) + 1;
        $end   = min($page * $per_page, $query->found_posts);

        return array(
            'html' => $html,
            'pagination_html' => $pagination_html,
            'results_count_html' => $results_count_html,
            'found_posts' => $query->found_posts,
            'max_pages' => $query->max_num_pages,
            'current_page' => $page,
            'post_count' => $query->post_count,
            'start' => $start,
            'end' => $end,
            'loaded_count' => min($page * $per_page, $query->found_posts),
        );
    }

    /**
     * Generate pagination using existing template
     *
     * @param \WP_Query $query Properties query
     * @param int       $current_page Current page number
     * @return void
     */
    private function generate_pagination_template( $query, $current_page ) {
        if (function_exists('hvnly_render_ajax_pagination_fragment')) {
            hvnly_render_ajax_pagination_fragment($query, $current_page, array(
                'pass_pagination_type' => true,
            ));
            return;
        }

        global $wp_query;

        $original_query = $wp_query;
        $wp_query       = $query;

        if (function_exists('hvnly_get_pagination_type')) {
            $hvnly_passed_pagination_type = hvnly_get_pagination_type();
        }

        if (function_exists('hvnly_get_template')) {
            hvnly_get_template('search/pagination.php', array());
        }

        $wp_query = $original_query;
    }

    /**
     * Generate results count using existing template
     *
     * @param \WP_Query $query Properties query
     * @param int       $current_page Current page number
     * @param int       $per_page Posts per page
     * @param array     $filters Current filters
     * @return void
     */
    private function generate_results_count_template( $query, $current_page, $per_page, $filters ) {
        if (function_exists('hvnly_render_ajax_results_count_fragment')) {
            hvnly_render_ajax_results_count_fragment($query, $current_page, $per_page, $filters);
            return;
        }

        global $wp_query;

        $original_query   = $wp_query;
        $wp_query         = $query;
        $total_properties = (int) $query->found_posts;
        $start            = ( ( (int) $current_page - 1 ) * (int) $per_page ) + 1;
        $end              = min( (int) $current_page * (int) $per_page, $total_properties);
        $current_filters  = function_exists('hvnly_build_ajax_result_count_filters')
            ? hvnly_build_ajax_result_count_filters($filters)
            : array();

        if (function_exists('hvnly_get_template')) {
            hvnly_get_template(
                'search/result-count.php',
                array(
                    'total_properties'        => $total_properties,
                    'start'                   => $start,
                    'end'                     => $end,
                    'current_filters'         => $current_filters,
                    'hvnly_current_filters'   => $current_filters,
                )
            );
        }

        $wp_query = $original_query;
    }

    /**
     * Clear sidebar cache when property is saved or deleted
     *
     * @param int           $post_id Post ID.
     * @param \WP_Post|null $post    Post object (required for delete hooks).
     * @return void
     */
    public function clear_sidebar_cache( $post_id, $post = null ) {
        if ( ! \hvnly_is_cache_enabled()) {
            return;
        }

        if ( ! $this->is_hvnly_property($post_id, $post)) {
            return;
        }

        $this->sidebar_filters->clear_all_sidebar_cache();
        do_action('hvnly_cleared_sidebar_property_cache', $post_id);
    }

    /**
     * Clear sidebar cache when search cache is cleared via meta-only updates.
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function clear_sidebar_cache_on_event( $post_id ) {
        if ( ! \hvnly_is_cache_enabled()) {
            return;
        }

        if ( ! $this->is_hvnly_property($post_id)) {
            return;
        }

        $this->sidebar_filters->clear_all_sidebar_cache();
        do_action('hvnly_cleared_sidebar_property_cache', $post_id);
    }

    /**
     * Clear sidebar cache when property taxonomies are assigned without save_post.
     *
     * @param int    $object_id  Post ID.
     * @param array  $terms      Term IDs.
     * @param array  $tt_ids     Term taxonomy IDs.
     * @param string $taxonomy   Taxonomy slug.
     * @param bool   $append     Whether terms were appended.
     * @param array  $old_tt_ids Previous term taxonomy IDs.
     */
    public function clear_sidebar_cache_on_term_assignment( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
        unset($terms, $tt_ids, $append, $old_tt_ids);

        if ( ! $this->is_property_listing_taxonomy($taxonomy) || ! $this->is_hvnly_property($object_id)) {
            return;
        }

        $this->clear_sidebar_cache($object_id);
    }

    /**
     * @param string $taxonomy Taxonomy slug.
     * @return bool
     */
    private function is_property_listing_taxonomy( $taxonomy ) {
        static $taxonomies = array(
            'hvnly_prop_types',
            'hvnly_prop_depts',
            'hvnly_prop_locations',
            'hvnly_prop_status',
            'hvnly_prop_features',
            'hvnly_prop_badges',
            'hvnly_prop_tags',
            'hvnly_prop_reviews',
            'amenities',
        );

        return in_array( (string) $taxonomy, $taxonomies, true);
    }

    /**
     * @param int           $post_id Post ID.
     * @param \WP_Post|null $post    Optional post object.
     * @return bool
     */
    private function is_hvnly_property( $post_id, $post = null ) {
        if ($post instanceof \WP_Post) {
            return $post->post_type === 'hvnly_property';
        }

        return get_post_type($post_id) === 'hvnly_property';
    }

    /**
     * Clear sidebar cache when terms are modified
     *
     * @param int $term_id Term ID
     * @return void
     */
    public function clear_sidebar_terms_cache( $term_id ) {
        if ( ! \hvnly_is_cache_enabled()) {
            return;
        }

        $term = get_term($term_id);
        if ( ! $term || is_wp_error($term)) {
            return;
        }

        $this->sidebar_filters->clear_all_sidebar_cache();
        do_action('hvnly_cleared_sidebar_terms_cache', $term_id, $term->taxonomy);
    }
}
