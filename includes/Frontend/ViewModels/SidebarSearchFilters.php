<?php

/**
 * Sidebar Search Filters ViewModel with transient caching
 *
 * @package     Havenlytics
 * @subpackage  Frontend
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend\ViewModels;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SidebarSearchFilters ViewModel class
 *
 * @since 2.0.0
 */
class SidebarSearchFilters
{
    /**
     * Cache duration in seconds
     *
     * @var int
     */
    private $cache_duration = 12 * HOUR_IN_SECONDS;

    /**
     * Get all sidebar filter data with caching
     *
     * @return array
     */
    public function get_sidebar_filter_data()
    {
        $cache_key = 'hvnly_sidebar_filters_data';
        $cached_data = get_transient($cache_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        $data = $this->generate_sidebar_filter_data();

        // Cache the data
        set_transient($cache_key, $data, $this->cache_duration);

        return apply_filters('hvnly_sidebar_filter_data', $data);
    }

    /**
     * Generate sidebar filter data from database
     *
     * @return array
     */
    private function generate_sidebar_filter_data()
    {
        $data = [];

        // Get current filter values
        $data['current_filters'] = hvnly_get_current_filters();

        // Extract commonly used current values
        $data['current_min_price'] = $data['current_filters']['min_price'] ?? '';
        $data['current_max_price'] = $data['current_filters']['max_price'] ?? '';
        $data['current_bedrooms'] = $data['current_filters']['bedrooms'] ?? '';
        $data['current_bathrooms'] = $data['current_filters']['bathrooms'] ?? '';
        $data['current_garages'] = $data['current_filters']['garages'] ?? '';
        $data['current_reception_rooms'] = $data['current_filters']['reception_rooms'] ?? '';
        $data['current_amenities'] = $data['current_filters']['amenities'] ?? [];

        // Get taxonomy terms with caching
        $data['hvnly_property_departments'] = $this->get_cached_terms('hvnly_prop_depts');
        $data['hvnly_locations'] = $this->get_cached_terms('hvnly_prop_locations');
        $data['hvnly_features'] = $this->get_cached_terms('hvnly_prop_features');
        $data['hvnly_reviews'] = $this->get_cached_terms('hvnly_prop_reviews');
        $data['hvnly_property_status'] = $this->get_cached_terms('hvnly_prop_status');
        $data['hvnly_prop_types'] = $this->get_cached_terms('hvnly_prop_types');
        $data['hvnly_tags'] = $this->get_cached_terms('hvnly_prop_tags');
        $data['hvnly_badges'] = $this->get_cached_terms('hvnly_prop_badges');

        // Get current values for all checkbox groups with proper array handling
        $data['hvnly_current_property_types'] = $this->get_current_taxonomy_values('hvnly_prop_types', $data['current_filters']);
        $data['hvnly_current_locations'] = $this->get_current_taxonomy_values('hvnly_prop_locations', $data['current_filters']);
        $data['hvnly_current_features'] = $this->get_current_taxonomy_values('hvnly_prop_features', $data['current_filters']);
        $data['hvnly_current_reviews'] = $this->get_current_taxonomy_values('hvnly_prop_reviews', $data['current_filters']);
        $data['hvnly_current_property_status'] = $this->get_current_taxonomy_values('hvnly_prop_status', $data['current_filters']);
        $data['hvnly_current_tags'] = $this->get_current_taxonomy_values('hvnly_prop_tags', $data['current_filters']);
        $data['hvnly_current_badges'] = $this->get_current_taxonomy_values('hvnly_prop_badges', $data['current_filters']);

        $data['hvnly_property_ids'] = $this->get_unique_property_ids();
        $data['hvnly_current_property_ids'] = $this->get_current_property_id_values($data['current_filters']);

        return $data;
    }

    /**
     * Get unique property IDs for filter with improved performance
     *
     * @return array
     */
    private function get_unique_property_ids()
    {
        global $wpdb;
        
        $cache_key = 'hvnly_unique_property_ids';
        $property_ids = get_transient($cache_key);
        
        if (false === $property_ids) {
            // Query to get all unique property IDs
            $property_ids = $wpdb->get_col(
                "SELECT DISTINCT pm.meta_value 
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_hvnly_unique_property_id'
                 AND p.post_type = 'hvnly_property'
                 AND p.post_status = 'publish'
                 AND pm.meta_value IS NOT NULL
                 AND pm.meta_value != ''
                 ORDER BY CAST(pm.meta_value AS UNSIGNED)"
            );
            
            set_transient($cache_key, $property_ids, $this->cache_duration);
        }
        
        return $property_ids;
    }

    /**
     * Get current property ID values from filters
     *
     * @param array $current_filters Current filters array
     * @return array
     */
    private function get_current_property_id_values($current_filters)
    {
        $current_values = $current_filters['property_ids'] ?? [];

        // Ensure it's always an array
        if (!is_array($current_values)) {
            $current_values = array($current_values);
        }

        return array_filter($current_values);
    }

    /**
     * Get cached taxonomy terms
     *
     * @param string $taxonomy Taxonomy name
     * @param array  $args     Get terms arguments
     * @return array|WP_Error
     */
    public function get_cached_terms($taxonomy, $args = [])
    {
        $default_args = [
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ];

        $args = wp_parse_args($args, $default_args);

        // Generate cache key based on taxonomy and args
        $cache_key = 'hvnly_sidebar_terms_' . $taxonomy . '_' . md5(serialize($args));
        $terms = get_transient($cache_key);

        if (false === $terms) {
            $terms = get_terms($args);

            if (!is_wp_error($terms) && !empty($terms)) {
                set_transient($cache_key, $terms, $this->cache_duration);
            }
        }

        return apply_filters('hvnly_sidebar_cached_terms', $terms, $taxonomy, $args);
    }

    /**
     * Get current taxonomy values from filters with proper array handling
     *
     * @param string $taxonomy Taxonomy name
     * @param array  $current_filters Current filters array
     * @return array
     */
    private function get_current_taxonomy_values($taxonomy, $current_filters)
    {
        $current_values = $current_filters[$taxonomy] ?? [];

        // Ensure it's always an array
        if (!is_array($current_values)) {
            $current_values = array($current_values);
        }

        return array_filter($current_values);
    }

    /**
     * Build query args for sidebar filters
     *
     * @param array $data Request data
     * @param int   $page Current page
     * @param int   $per_page Posts per page
     * @return array
     */
    public function build_sidebar_query_args($data, $page = 1, $per_page = 12)
    {
        $args = [
            'post_type' => 'hvnly_property',
            'post_status' => 'publish',
            'paged' => $page,
            'posts_per_page' => $per_page,
        ];
        
        // Add search keyword
        if (!empty($data['address_keyword'])) {
            $args['s'] = sanitize_text_field($data['address_keyword']);
        }
        
        // Build tax query
        $tax_query = [];
        
        // Department filter
        if (!empty($data['department'])) {
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_depts',
                'field' => 'slug',
                'terms' => sanitize_text_field($data['department']),
            ];
        }
        
        // Property types
        if (!empty($data['hvnly_prop_types']) && is_array($data['hvnly_prop_types'])) {
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_types',
                'field' => 'slug',
                'terms' => array_map('sanitize_text_field', $data['hvnly_prop_types']),
            ];
        }
        
        // Locations
        if (!empty($data['hvnly_prop_locations']) && is_array($data['hvnly_prop_locations'])) {
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_locations',
                'field' => 'slug',
                'terms' => array_map('sanitize_text_field', $data['hvnly_prop_locations']),
            ];
        }
        
        // Features
        if (!empty($data['hvnly_prop_features']) && is_array($data['hvnly_prop_features'])) {
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_features',
                'field' => 'slug',
                'terms' => array_map('sanitize_text_field', $data['hvnly_prop_features']),
            ];
        }
        
        // Tags
        if (!empty($data['hvnly_prop_tags']) && is_array($data['hvnly_prop_tags'])) {
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_tags',
                'field' => 'slug',
                'terms' => array_map('sanitize_text_field', $data['hvnly_prop_tags']),
            ];
        }
        
        // Badges
        if (!empty($data['hvnly_prop_badges']) && is_array($data['hvnly_prop_badges'])) {
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_badges',
                'field' => 'slug',
                'terms' => array_map('sanitize_text_field', $data['hvnly_prop_badges']),
            ];
        }
        
        // Status
        if (!empty($data['hvnly_prop_status']) && is_array($data['hvnly_prop_status'])) {
            $tax_query[] = [
                'taxonomy' => 'hvnly_prop_status',
                'field' => 'slug',
                'terms' => array_map('sanitize_text_field', $data['hvnly_prop_status']),
            ];
        }
        
        if (!empty($tax_query)) {
            $args['tax_query'] = count($tax_query) > 1 ? array_merge($tax_query, ['relation' => 'AND']) : $tax_query;
        }
        
        // Meta queries for numeric fields
        $meta_query = [];
        
        if (!empty($data['min_price'])) {
            $meta_query[] = [
                'key' => '_hvnly_property_price',
                'value' => absint($data['min_price']),
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }
        
        if (!empty($data['max_price'])) {
            $meta_query[] = [
                'key' => '_hvnly_property_price',
                'value' => absint($data['max_price']),
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }
        
        if (!empty($data['bedrooms'])) {
            $meta_query[] = [
                'key' => '_hvnly_property_bedrooms',
                'value' => absint($data['bedrooms']),
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }
        
        if (!empty($data['bathrooms'])) {
            $meta_query[] = [
                'key' => '_hvnly_property_bathrooms',
                'value' => absint($data['bathrooms']),
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }
        
        if (!empty($data['reception_rooms'])) {
            $meta_query[] = [
                'key' => '_hvnly_property_reception_rooms',
                'value' => absint($data['reception_rooms']),
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }
        
        if (!empty($data['garages'])) {
            $meta_query[] = [
                'key' => '_hvnly_property_garage_sqft',
                'value' => absint($data['garages']),
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }
        
        if (!empty($data['property_ids'])) {
            $property_ids = is_array($data['property_ids']) ? $data['property_ids'] : [$data['property_ids']];
            if (!empty($property_ids)) {
                $meta_query[] = [
                    'key' => '_hvnly_unique_property_id',
                    'value' => $property_ids,
                    'compare' => 'IN',
                ];
            }
        }
        
        if (!empty($meta_query)) {
            $args['meta_query'] = count($meta_query) > 1 ? array_merge($meta_query, ['relation' => 'AND']) : $meta_query;
        }
        
        // Order by
        if (!empty($data['orderby'])) {
            $orderby = sanitize_text_field($data['orderby']);
            
            switch ($orderby) {
                case 'price_low':
                    $args['meta_key'] = '_hvnly_property_price';
                    $args['orderby'] = 'meta_value_num';
                    $args['order'] = 'ASC';
                    break;
                case 'price_high':
                    $args['meta_key'] = '_hvnly_property_price';
                    $args['orderby'] = 'meta_value_num';
                    $args['order'] = 'DESC';
                    break;
                case 'date':
                default:
                    $args['orderby'] = 'date';
                    $args['order'] = 'DESC';
                    break;
            }
        }
        
        return apply_filters('hvnly_sidebar_query_args', $args, $data);
    }

    /**
     * Generate cache key for sidebar results
     *
     * @param array $args Query arguments
     * @param int   $page Current page
     * @param int   $per_page Posts per page
     * @return string
     */
    public function generate_sidebar_cache_key($args, $page, $per_page)
    {
        $key_data = [
            'args' => $args,
            'page' => $page,
            'per_page' => $per_page,
            'lang' => function_exists('pll_current_language') ? pll_current_language() : get_locale(),
        ];
        
        return 'hvnly_sidebar_search_' . md5(serialize($key_data));
    }

    /**
     * Get cached sidebar results
     *
     * @param array $args Query arguments
     * @param int   $page Current page
     * @param int   $per_page Posts per page
     * @return mixed
     */
    public function get_cached_sidebar_results($args, $page, $per_page)
    {
        $cache_key = $this->generate_sidebar_cache_key($args, $page, $per_page);
        return get_transient($cache_key);
    }

    /**
     * Set cached sidebar results
     *
     * @param string $cache_key Cache key
     * @param mixed  $data Data to cache
     * @return bool
     */
    public function set_cached_sidebar_results($cache_key, $data)
    {
        $duration = apply_filters('hvnly_sidebar_cache_duration', $this->cache_duration);
        return set_transient($cache_key, $data, $duration);
    }

    /**
     * Clear sidebar filters cache
     */
    public function clear_sidebar_cache()
    {
        global $wpdb;

        // Delete all sidebar transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hvnly_sidebar_%'"
        );

        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hvnly_sidebar_%'"
        );
        delete_transient('hvnly_unique_property_ids');
        do_action('hvnly_cleared_sidebar_cache');
    }

    /**
     * Clear terms cache for a taxonomy
     *
     * @param string $taxonomy Taxonomy name
     */
    public function clear_terms_cache($taxonomy)
    {
        global $wpdb;

        // Delete all term transients for this taxonomy
        $pattern = '_transient_hvnly_sidebar_terms_' . $taxonomy . '_%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );

        do_action('hvnly_cleared_sidebar_terms_cache', $taxonomy);
    }

    /**
     * Clear all sidebar-related transients
     */
    public function clear_all_sidebar_cache()
    {
        global $wpdb;

        // Clear sidebar data cache
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hvnly_sidebar_%'"
        );

        // Clear timeout caches too
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hvnly_sidebar_%'"
        );

        delete_transient('hvnly_unique_property_ids');
        do_action('hvnly_cleared_all_sidebar_cache');
    }
}