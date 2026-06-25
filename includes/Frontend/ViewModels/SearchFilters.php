<?php

/**
 * Search filters ViewModel with transient caching
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
 * SearchFilters ViewModel class
 *
 * @since 2.0.0
 */
class SearchFilters
{
    /**
     * Cache duration in seconds
     *
     * @var int
     */
    private $cache_duration = 12 * HOUR_IN_SECONDS;

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
        $cache_key = 'hvnly_terms_' . $taxonomy . '_' . md5(serialize($args));
        $terms = get_transient($cache_key);

        if (false === $terms) {
            $terms = get_terms($args);

            if (!is_wp_error($terms) && !empty($terms)) {
                set_transient($cache_key, $terms, $this->cache_duration);
            }
        }

        return apply_filters('hvnly_cached_terms', $terms, $taxonomy, $args);
    }

    /**
     * Get all filter data for templates
     *
     * @return array
     */
    public function get_filter_data()
    {
        $data = [];

        // Get dynamic taxonomies with caching
        $data['prop_types'] = $this->get_cached_terms('hvnly_prop_types');
        $data['departments'] = $this->get_cached_terms('hvnly_prop_depts');
        $data['locations'] = $this->get_cached_terms('hvnly_prop_locations');

        // Get current search values
        $data['current_filters'] = hvnly_get_current_filters();
        $data['current_search'] = $data['current_filters']['address_keyword'] ?? '';
        $data['current_types'] = $data['current_filters']['hvnly_prop_types'] ?? [];
        $data['current_locations'] = $data['current_filters']['hvnly_prop_locations'] ?? [];
        $data['current_department'] = $data['current_filters']['department'] ?? '';

        // Get current URL
        $data['current_url'] = get_post_type_archive_link('hvnly_property');

        // Sanitize GET parameters
        $data['url_params'] = [];
        foreach ($_GET as $key => $value) {
            $key   = sanitize_key($key);
            $value = is_array($value)
                ? array_map('sanitize_text_field', $value)
                : sanitize_text_field($value);

            $data['url_params'][$key] = $value;
        }

        return apply_filters('hvnly_search_filter_data', $data);
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
        $pattern = '_transient_hvnly_terms_' . $taxonomy . '_%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            )
        );

        do_action('hvnly_cleared_terms_cache', $taxonomy);
    }

    /**
     * Clear all search-related transients
     */
    public function clear_all_search_cache()
    {
        global $wpdb;

        // Clear term caches
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hvnly_terms_%'"
        );

        // Clear search result caches
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hvnly_search_%'"
        );

        do_action('hvnly_cleared_all_search_cache');
    }
}