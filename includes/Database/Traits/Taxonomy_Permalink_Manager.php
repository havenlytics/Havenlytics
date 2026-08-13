<?php

namespace HvnlyNab\Database\Traits;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Taxonomy Permalink Manager Trait
 *
 * Modifies taxonomy term links to use property archive with query parameters
 *
 * @package Havenlytics
 * @since 2.0.0
 */
trait Taxonomy_Permalink_Manager {

    /**
     * Taxonomy slug for image management
     *
     * @var string
     */
    protected $hvnly_taxonomy_slug;

    /**
     * Taxonomy to query parameter mapping
     *
     * @var array
     */
    private $hvnly_taxonomy_mapping = array(
        'hvnly_prop_depts' => array( 'department', 'slug', 'hvnly_property' ),
        'hvnly_prop_types' => array( 'property_type', 'slug', 'hvnly_property' ),
        'hvnly_prop_features' => array( 'in_feature', 'id', 'hvnly_property' ),
        'hvnly_prop_locations' => array( 'location', 'slug', 'hvnly_property' ),
        'hvnly_prop_status' => array( 'in_status', 'id', 'hvnly_property' ),
        'hvnly_prop_tags' => array( 'in_tag', 'id', 'hvnly_property' ),
        'hvnly_prop_badges' => array( 'badge', 'slug', 'hvnly_property' ),
    );

    /**
     * Default query parameters
     *
     * @var array
     */
    private $hvnly_default_params = array(
        'view_type' => 'grid',
        'orderby' => 'date',
    );

    /**
     * Initialize permalink management
     *
     * @param string $taxonomy_slug The taxonomy slug
     * @return void
     */
    public function hvnly_initialize_permalink_manager( $taxonomy_slug ) {
        // Set the taxonomy slug
        $this->hvnly_taxonomy_slug = $taxonomy_slug;
        // Add filters to modify links
        add_filter('term_link', array( $this, 'hvnly_modify_taxonomy_link' ), 10, 3);

        // Ensure view link appears in admin
        add_filter('tag_row_actions', array( $this, 'hvnly_add_view_link' ), 10, 2);
        add_filter('term_row_actions', array( $this, 'hvnly_add_view_link' ), 10, 2);

        // Add pre_get_posts filter to handle direct URL access
        add_action('pre_get_posts', array( $this, 'hvnly_handle_taxonomy_url_filters' ));
    }

    /**
     * Modify taxonomy term links
     *
     * @param string $url Original term URL
     * @param object $term Term object
     * @param string $taxonomy Taxonomy slug
     * @return string Modified URL
     */
    public function hvnly_modify_taxonomy_link( $url, $term, $taxonomy ) {
        // Check if this is one of our mapped taxonomies
        if (isset($this->hvnly_taxonomy_mapping[ $taxonomy ])) {
            list($param, $type, $post_type) = $this->hvnly_taxonomy_mapping[ $taxonomy ];

            // Get post type archive URL dynamically
            $post_type_url = $this->hvnly_get_post_type_archive_url($post_type);

            // Get term value (slug or ID)
            $value = $type === 'id' ? $term->term_id : $term->slug;

            // Build new URL with query parameters
            $query_args = array_merge(
                $this->hvnly_default_params,
                array( $param => $value )
            );

            // For checkboxes/array parameters, format as comma-separated
            $url = add_query_arg($query_args, $post_type_url);

            return $url;
        }

        return $url;
    }

    /**
     * Handle taxonomy URL filters on property archive
     * This ensures direct URL access works properly
     *
     * @param WP_Query $query The WP_Query instance.
     */
    public function hvnly_handle_taxonomy_url_filters( $query ) {
        // Only apply to property archive and main query
        if (is_admin() || ! $query->is_main_query() || ! is_post_type_archive('hvnly_property')) {
            return;
        }

        // Get all taxonomy parameters from URL
        $tax_query = array();

        // Handle each taxonomy mapping
        foreach ($this->hvnly_taxonomy_mapping as $taxonomy => $mapping) {
            list($param, $type) = $mapping;

            //  Use filter_input for GET parameters
            $param_value_raw = filter_input(INPUT_GET, $param, FILTER_UNSAFE_RAW);
            $param_value     = $param_value_raw ? sanitize_text_field($param_value_raw) : '';

            if ( ! empty($param_value)) {
                // For ID-based parameters, convert to term ID
                if ($type === 'id') {
                    $term_id = absint($param_value);
                    $term    = get_term($term_id, $taxonomy);

                    if ($term && ! is_wp_error($term)) {
                        $tax_query[] = array(
                            'taxonomy' => $taxonomy,
                            'field' => 'term_id',
                            'terms' => $term_id,
                        );
                    }
                } else {
                    // For slug-based parameters
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'slug',
                        'terms' => $param_value,
                    );
                }
            }
        }

        // Also handle array parameters (for backward compatibility)
        $array_taxonomies = array(
            'hvnly_prop_depts',
            'hvnly_prop_types',
            'hvnly_prop_locations',
            'hvnly_prop_features',
            'hvnly_prop_status',
            'hvnly_prop_tags',
            'hvnly_prop_badges',
        );

        foreach ($array_taxonomies as $taxonomy) {
            // Use filter_input for array parameters
            $terms_raw = filter_input(INPUT_GET, $taxonomy, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?:
                        filter_input(INPUT_GET, $taxonomy, FILTER_UNSAFE_RAW);

            if ( ! empty($terms_raw)) {
                if (is_array($terms_raw)) {
                    $terms = array_map('sanitize_text_field', $terms_raw);
                } else {
                    $terms = explode(',', sanitize_text_field($terms_raw));
                }

                $terms = array_filter($terms);

                if ( ! empty($terms)) {
                    $tax_query[] = array(
                        'taxonomy' => $taxonomy,
                        'field' => 'slug',
                        'terms' => $terms,
                    );
                }
            }
        }

        // Apply tax_query if we have any taxonomy filters
        if ( ! empty($tax_query)) {
            if (count($tax_query) > 1) {
                $tax_query['relation'] = 'AND';
            }
            $query->set('tax_query', $tax_query);
        }
    }

    /**
     * Get post type archive URL dynamically
     *
     * @param string $post_type Post type slug
     * @return string Archive URL
     */
    private function hvnly_get_post_type_archive_url( $post_type ) {
        // First try to get archive link
        $archive_url = get_post_type_archive_link($post_type);

        if ($archive_url) {
            return $archive_url;
        }

        // If no archive, get post type object to find rewrite slug
        $post_type_obj = get_post_type_object($post_type);

        if ($post_type_obj) {
            // Check if it has a custom rewrite slug
            if (isset($post_type_obj->rewrite['slug'])) {
                $slug = $post_type_obj->rewrite['slug'];
                // Make sure we have proper URL structure
                if (strpos($slug, '/') !== false) {
                    // If slug has slashes, it's a path
                    return home_url('/' . $slug . '/');
                } else {
                    // Simple slug
                    return home_url('/' . $slug . '/');
                }
            }

            // Fallback to post type name
            return home_url('/' . $post_type . '/');
        }

        // Ultimate fallback
        return home_url('/property/');
    }

    /**
     * Add view link to taxonomy admin list
     *
     * @param array $actions Existing row actions
     * @param object $term Term object
     * @return array Modified row actions
     */
    public function hvnly_add_view_link( $actions, $term ) {
        // Check if this is one of our taxonomies
        if (isset($this->hvnly_taxonomy_mapping[ $term->taxonomy ])) {

            // Get the modified term link
            $term_link = get_term_link($term);

            if ( ! is_wp_error($term_link)) {
                $actions['view'] = sprintf(
                    '<a href="%s" target="_blank" aria-label="%s">%s</a>',
                    esc_url($term_link),
                    esc_attr(
                        sprintf(
                            /* translators: %s: term name */
                            __('View "%s"', 'havenlytics'),
                            esc_html($term->name)
                        )
                    ),
                    esc_html__('View', 'havenlytics')
                );
            }
        }

        return $actions;
    }

    /**
     * Get the custom URL for a term
     *
     * @param int|object $term Term ID or object
     * @param string $taxonomy Taxonomy slug (optional if $term is object)
     * @return string|false URL or false on error
     */
    public function hvnly_get_term_custom_url( $term, $taxonomy = '' ) {
        if (is_numeric($term)) {
            $term = get_term($term, $taxonomy);
        }

        if ( ! $term || is_wp_error($term)) {
            return false;
        }

        return $this->hvnly_modify_taxonomy_link('', $term, $term->taxonomy);
    }

    /**
     * Get taxonomy mapping
     *
     * @return array Taxonomy to parameter mapping
     */
    public function hvnly_get_taxonomy_mapping() {
        return $this->hvnly_taxonomy_mapping;
    }

    /**
     * Add a new taxonomy to the mapping
     *
     * @param string $taxonomy Taxonomy slug
     * @param string $param Query parameter name
     * @param string $type Value type (slug or id)
     * @param string $post_type Associated post type
     * @return void
     */
    public function hvnly_add_taxonomy_mapping( $taxonomy, $param, $type = 'slug', $post_type = 'hvnly_property' ) {
        $this->hvnly_taxonomy_mapping[ $taxonomy ] = array( $param, $type, $post_type );
    }
}
