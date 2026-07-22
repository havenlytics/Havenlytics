<?php

/**
 * AJAX Handler for Havenlytics
 *
 * @package     Havenlytics
 * @subpackage  Frontend
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend;

use HvnlyNab\Frontend\Query\PropertyQueryArgsBuilder;
use HvnlyNab\Frontend\Query\PropertyQueryCache;
use HvnlyNab\Frontend\Query\PropertyQueryExecutor;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Handler class
 *
 * @since 2.0.0
 */
class AjaxHandler
{
    /**
     * Cache duration for search results
     *
     * @var int
     */
    private $search_cache_duration = 1 * HOUR_IN_SECONDS;

    /**
     * Property Builder sections cache
     *
     * @var array|null
     */
    private $property_builder_sections = null;

    /**
     * Map field definitions cache (field_name => field_type)
     *
     * @var array
     */
    private $map_field_definitions = [];

    /**
     * Cached map group configs from the property builder (group_id => field row).
     *
     * @var array<string, array>|null
     */
    private $builder_map_field_sets = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_ajax_hvnly_search_properties', array($this, 'search_properties'));
        add_action('wp_ajax_nopriv_hvnly_search_properties', array($this, 'search_properties'));

        add_action('wp_ajax_hvnly_load_more', array($this, 'load_more_properties'));
        add_action('wp_ajax_nopriv_hvnly_load_more', array($this, 'load_more_properties'));

        add_action('wp_ajax_hvnly_get_filters', array($this, 'get_filter_options'));
        add_action('wp_ajax_nopriv_hvnly_get_filters', array($this, 'get_filter_options'));

        add_action('wp_ajax_hvnly_get_properties_for_map', array($this, 'hvnly_get_properties_for_map'));
        add_action('wp_ajax_nopriv_hvnly_get_properties_for_map', array($this, 'hvnly_get_properties_for_map'));

        add_action('save_post_hvnly_property', array($this, 'clear_property_cache'));
        add_action('delete_post', array($this, 'clear_property_cache'), 10, 2);
        add_action('before_delete_post', array($this, 'clear_property_cache'), 10, 2);
        add_action('updated_post_meta', array($this, 'clear_property_cache_on_meta_update'), 10, 4);
        add_action('added_post_meta', array($this, 'clear_property_cache_on_meta_update'), 10, 4);
        add_action('deleted_post_meta', array($this, 'clear_property_cache_on_meta_delete'), 10, 4);
        add_action('set_object_terms', array($this, 'clear_property_cache_on_term_assignment'), 10, 6);
        add_action('created_term', array($this, 'clear_terms_cache'), 10, 2);
        add_action('edited_term', array($this, 'clear_terms_cache'), 10, 2);
        add_action('delete_term', array($this, 'clear_terms_cache'), 10, 3);

        add_action('created_term', array($this, 'clear_sidebar_terms_cache'), 10, 2);
        add_action('edited_term', array($this, 'clear_sidebar_terms_cache'), 10, 2);
        add_action('delete_term', array($this, 'clear_sidebar_terms_cache'), 10, 3);

        add_action('wp_ajax_hvnly_render_map_container', array($this, 'hvnly_render_map_container'));
        add_action('wp_ajax_nopriv_hvnly_render_map_container', array($this, 'hvnly_render_map_container'));
    }

    /**
     * Get Property Builder sections from database
     *
     * @return array
     */
    private function get_property_builder_sections()
    {
        if ($this->property_builder_sections === null) {
            $this->property_builder_sections = get_option('hvnly_property_builder.sections', array());
            
            if (empty($this->property_builder_sections) || !is_array($this->property_builder_sections)) {
                $this->property_builder_sections = array();
            }
        }
        return $this->property_builder_sections;
    }

    /**
     * Get all map field definitions from Property Builder
     *
     * @return array
     */
    private function get_map_field_definitions()
    {
        if (!empty($this->map_field_definitions)) {
            return $this->map_field_definitions;
        }

        $sections = $this->get_property_builder_sections();
        $map_fields = array();

        foreach ($sections as $section) {
            if (empty($section['fields']) || !is_array($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as $field) {
                $is_map_field = false;
                if (isset($field['type']) && $field['type'] === 'map') {
                    $is_map_field = true;
                }
                if (isset($field['group_type']) && $field['group_type'] === 'map') {
                    $is_map_field = true;
                }

                if ($is_map_field && isset($field['name'])) {
                    $field_name = $field['name'];
                    $base_name = preg_replace('/_(address|latitude|longitude|preview)$/', '', $field_name);
                    
                    if (!empty($base_name)) {
                        $map_fields[$base_name] = array(
                            'base_id' => $base_name,
                            'address_key' => $base_name . '_address',
                            'latitude_key' => $base_name . '_latitude',
                            'longitude_key' => $base_name . '_longitude',
                            'preview_key' => $base_name . '_preview',
                        );
                    }
                }
            }
        }

        $this->map_field_definitions = $map_fields;
        return $map_fields;
    }

    /**
     * Map group field rows keyed by group_id (cached per request).
     *
     * @return array<string, array>
     */
    private function get_builder_map_field_sets() {
        if ( null !== $this->builder_map_field_sets ) {
            return $this->builder_map_field_sets;
        }

        $map_field_sets = array();
        $sections       = $this->get_property_builder_sections();

        if ( is_array( $sections ) ) {
            foreach ( $sections as $section_key => $section ) {
                $section_id = $section['id'] ?? ( is_string( $section_key ) ? $section_key : '' );
                foreach ( $section['fields'] ?? array() as $field ) {
                    if ( ! is_array( $field ) || ( $field['group_type'] ?? '' ) !== 'map' ) {
                        continue;
                    }
                    if ( $section_id !== '' && empty( $field['section_id'] ) ) {
                        $field['section_id'] = $section_id;
                    }
                    $gid = $field['group_id'] ?? '';
                    if ( '' === $gid ) {
                        continue;
                    }
                    if ( ! isset( $map_field_sets[ $gid ] ) ) {
                        $map_field_sets[ $gid ] = $field;
                        continue;
                    }
                    if ( empty( $map_field_sets[ $gid ]['group_base_id'] ) && ! empty( $field['group_base_id'] ) ) {
                        $map_field_sets[ $gid ] = $field;
                    }
                }
            }
        }

        if ( empty( $map_field_sets ) ) {
            $map_field_sets['default'] = array(
                'group_type'    => 'map',
                'group_base_id' => '',
                'group_id'      => '',
            );
        }

        $this->builder_map_field_sets = $map_field_sets;

        return $map_field_sets;
    }

    /**
     * Validate map coordinates before exposing them to the frontend.
     *
     * @param mixed $lat Latitude value.
     * @param mixed $lng Longitude value.
     * @return array|null Normalized ['lat' => float, 'lng' => float] or null when invalid.
     */
    private function normalize_map_coordinates($lat, $lng)
    {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        if (abs($lat) < 0.0001 && abs($lng) < 0.0001) {
            return null;
        }

        return array(
            'lat' => $lat,
            'lng' => $lng,
        );
    }

    /**
     * Get property coordinates from Property Builder map fields
     * Uses stored references to find the correct dynamic field names
     *
     * @param int $property_id Property ID
     * @return array ['lat' => float, 'lng' => float, 'address' => string, 'has_coordinates' => bool]
     */
    private function get_property_map_data($property_id)
    {
        $result = array(
            'lat' => null,
            'lng' => null,
            'address' => '',
            'has_coordinates' => false
        );

        $property_id = (int) $property_id;
        if ($property_id <= 0) {
            return $result;
        }

        // Same resolver path used by single-property map assets — iterate scoped map field sets only.
        $map_field_sets = $this->get_builder_map_field_sets();

        if ( function_exists( 'hvnly_resolve_field_meta' ) ) {
            foreach ( $map_field_sets as $map_field ) {
                if ( empty( $map_field['group_id'] ) && empty( $map_field['group_base_id'] ) ) {
                    continue;
                }
                $lat     = hvnly_resolve_field_meta( $property_id, array_merge( $map_field, array( 'metaKey' => 'latitude' ) ) );
                $lng     = hvnly_resolve_field_meta( $property_id, array_merge( $map_field, array( 'metaKey' => 'longitude' ) ) );
                $address = hvnly_resolve_field_meta( $property_id, array_merge( $map_field, array( 'metaKey' => 'address' ) ) );

                if ( ! empty( $lat ) && is_numeric( $lat ) && ! empty( $lng ) && is_numeric( $lng ) ) {
                    $coords = $this->normalize_map_coordinates( $lat, $lng );
                    if ( $coords ) {
                        $result['lat']             = $coords['lat'];
                        $result['lng']             = $coords['lng'];
                        $result['address']         = is_string( $address ) ? $address : '';
                        $result['has_coordinates'] = true;
                        return $result;
                    }
                }
            }
        }

        $active_lat_key = get_post_meta( $property_id, '_hvnly_active_map_latitude_key', true );
        $active_lng_key = get_post_meta( $property_id, '_hvnly_active_map_longitude_key', true );
        if ( is_string( $active_lat_key ) && $active_lat_key !== '' && is_string( $active_lng_key ) && $active_lng_key !== '' ) {
            $lat = get_post_meta( $property_id, $active_lat_key, true );
            $lng = get_post_meta( $property_id, $active_lng_key, true );
            if ( ! empty( $lat ) && is_numeric( $lat ) && ! empty( $lng ) && is_numeric( $lng ) ) {
                $coords = $this->normalize_map_coordinates( $lat, $lng );
                if ( $coords ) {
                    $active_addr_key = get_post_meta( $property_id, '_hvnly_active_map_address_key', true );
                    $address         = is_string( $active_addr_key ) && $active_addr_key !== ''
                        ? get_post_meta( $property_id, $active_addr_key, true )
                        : '';
                    $result['lat']             = $coords['lat'];
                    $result['lng']             = $coords['lng'];
                    $result['address']         = is_string( $address ) ? $address : '';
                    $result['has_coordinates'] = true;
                    return $result;
                }
            }
        }

        foreach ( $this->get_map_field_definitions() as $definition ) {
            $lat = get_post_meta( $property_id, $definition['latitude_key'], true );
            $lng = get_post_meta( $property_id, $definition['longitude_key'], true );

            if ( ! empty( $lat ) && is_numeric( $lat ) && ! empty( $lng ) && is_numeric( $lng ) ) {
                $coords = $this->normalize_map_coordinates( $lat, $lng );
                if ( $coords ) {
                    $result['lat']             = $coords['lat'];
                    $result['lng']             = $coords['lng'];
                    $address = get_post_meta( $property_id, $definition['address_key'], true );
                    $result['address']         = is_string( $address ) ? $address : '';
                    $result['has_coordinates'] = true;
                    return $result;
                }
            }
        }

        $legacy_pairs = array(
            array( '_hvnly_property_location_Latitude', '_hvnly_property_location_Longitude', '_hvnly_property_map_location_address' ),
            array( '_hvnly_property_latitude', '_hvnly_property_longitude', '_hvnly_property_address' ),
        );

        foreach ( $legacy_pairs as $pair ) {
            list( $lat_key, $lng_key, $address_key ) = $pair;
            $lat = get_post_meta( $property_id, $lat_key, true );
            $lng = get_post_meta( $property_id, $lng_key, true );

            if ( ! empty( $lat ) && is_numeric( $lat ) && ! empty( $lng ) && is_numeric( $lng ) ) {
                $coords = $this->normalize_map_coordinates( $lat, $lng );
                if ( $coords ) {
                    $result['lat']             = $coords['lat'];
                    $result['lng']             = $coords['lng'];
                    $address = get_post_meta( $property_id, $address_key, true );
                    $result['address']         = is_string( $address ) ? $address : '';
                    $result['has_coordinates'] = true;
                    return $result;
                }
            }
        }

        $scanned = $this->scan_post_meta_for_coordinates( $property_id );
        if ( is_array( $scanned ) ) {
            $result['lat']             = $scanned['lat'];
            $result['lng']             = $scanned['lng'];
            $result['address']         = $scanned['address'];
            $result['has_coordinates'] = true;
        }

        return $result;
    }

    /**
     * Scan post meta for paired latitude/longitude keys.
     *
     * @param int $property_id Property ID.
     * @return array|null Normalized map data or null when not found.
     */
    private function scan_post_meta_for_coordinates( $property_id ) {
        $property_id = (int) $property_id;
        if ( $property_id <= 0 ) {
            return null;
        }

        $meta_keys = get_post_meta( $property_id );
        if ( ! is_array( $meta_keys ) || empty( $meta_keys ) ) {
            return null;
        }

        $lat_by_base     = array();
        $lng_by_base     = array();
        $address_by_base = array();

        foreach ( array_keys( $meta_keys ) as $key ) {
            if ( preg_match( '/_(latitude|lat)$/i', $key, $lat_match ) ) {
                $base = substr( $key, 0, -strlen( $lat_match[0] ) );
                $val  = get_post_meta( $property_id, $key, true );
                if ( $val !== '' && $val !== false && $val !== null && is_numeric( $val ) ) {
                    $lat_by_base[ $base ] = $val;
                }
                continue;
            }

            if ( preg_match( '/_(longitude|lng)$/i', $key, $lng_match ) ) {
                $base = substr( $key, 0, -strlen( $lng_match[0] ) );
                $val  = get_post_meta( $property_id, $key, true );
                if ( $val !== '' && $val !== false && $val !== null && is_numeric( $val ) ) {
                    $lng_by_base[ $base ] = $val;
                }
                continue;
            }

            if ( preg_match( '/_address$/i', $key ) ) {
                $base = substr( $key, 0, -strlen( '_address' ) );
                $val  = get_post_meta( $property_id, $key, true );
                if ( $val !== '' && $val !== false && $val !== null ) {
                    $address_by_base[ $base ] = (string) $val;
                }
            }
        }

        foreach ( $lat_by_base as $base => $lat ) {
            if ( ! isset( $lng_by_base[ $base ] ) ) {
                continue;
            }

            $coords = $this->normalize_map_coordinates( $lat, $lng_by_base[ $base ] );
            if ( $coords ) {
                return array(
                    'lat'     => $coords['lat'],
                    'lng'     => $coords['lng'],
                    'address' => $address_by_base[ $base ] ?? '',
                );
            }
        }

        if ( count( $lat_by_base ) === 1 && count( $lng_by_base ) === 1 ) {
            $lat  = reset( $lat_by_base );
            $lng  = reset( $lng_by_base );
            $coords = $this->normalize_map_coordinates( $lat, $lng );
            if ( $coords ) {
                $address = ! empty( $address_by_base ) ? (string) reset( $address_by_base ) : '';
                return array(
                    'lat'     => $coords['lat'],
                    'lng'     => $coords['lng'],
                    'address' => $address,
                );
            }
        }

        return null;
    }

    /**
     * Render map container via AJAX
     *
     * @return void
     */
    public function hvnly_render_map_container()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!wp_verify_nonce($nonce, 'hvnly_ajax_request')) {
            wp_send_json_error('Security check failed');
        }
        
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : 'leaflet';
        
        ob_start();
        hvnly_get_template_part('map/map-container', null, array('provider' => $provider));
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'provider' => $provider
        ));
    }

    /**
     * Search properties via AJAX with caching
     *
     * @return void
     */
    public function search_properties()
    {
        // Fixed nonce handling with proper unslashing
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!wp_verify_nonce($nonce, 'hvnly_ajax_request')) {  
            wp_send_json_error('Security check failed');
        }

        try {
            $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
            
            // Use custom properties per page setting instead of WordPress default
            $custom_per_page = function_exists('hvnly_get_properties_per_page') 
    ? hvnly_get_properties_per_page() 
    : get_option('posts_per_page', 12);

$per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : $custom_per_page;

            $is_top_search = isset($_POST['is_top_search']) && sanitize_text_field(wp_unslash($_POST['is_top_search'])) === 'true';

            // Sanitize POST data
            $post_data = $this->sanitize_post_data($_POST);

            // Build query args and check cache
            $args = $this->build_query_args($post_data, $page, $per_page);
            $cache_key = $this->generate_search_cache_key($args, $page, $per_page);

            // Try to get cached results
            $cached_results = $this->get_cached_search_results($cache_key);

            if (false !== $cached_results && !$is_top_search) {
                // Use cached results
                wp_send_json_success($cached_results);
            }

            // No cache found or top search - perform fresh query
            $properties_query = PropertyQueryExecutor::query($post_data, $page, $per_page, [
                'filter_context' => 'ajax',
                'bypass_cache'   => $is_top_search,
            ]);

            if (is_wp_error($properties_query->posts)) {
                wp_send_json_error('Query error occurred');
            }

            // Generate response data using templates
            $response_data = $this->generate_search_response($properties_query, $page, $per_page, $post_data, $is_top_search);

            // Cache the results (except for top search)
            if (!$is_top_search) {
                $this->set_cached_search_results($cache_key, $response_data);
            }

            wp_send_json_success($response_data);
        } catch (\Exception $e) {
            wp_send_json_error('An error occurred while loading properties. Please try again.');
        } catch (\Error $e) {
            wp_send_json_error('An error occurred while loading properties. Please try again.');
        }
    }

    /**
     * Sanitize POST data
     *
     * @param array $data Raw POST data
     * @return array
     */
    private function sanitize_post_data($data)
    {
        $sanitized = array();
        
        // Define allowed keys and their sanitization callbacks
        $allowed_keys = array(
            'nonce' => 'sanitize_text_field',
            'page' => 'absint',
            'per_page' => 'absint',
            'is_top_search' => 'sanitize_text_field',
            'address_keyword' => 'sanitize_text_field',
            'department' => 'sanitize_text_field',
            'min_price' => 'absint',
            'max_price' => 'absint',
            'bedrooms' => 'absint',
            'bathrooms' => 'absint',
            'reception_rooms' => 'absint',
            'garages' => 'absint',
            'orderby' => 'sanitize_text_field',
            'property_ids' => array($this, 'sanitize_array_field'),
            'property_type' => array($this, 'sanitize_array_field'),
            'hvnly_prop_depts' => array($this, 'sanitize_array_field'),
            'hvnly_prop_types' => array($this, 'sanitize_array_field'),
            'hvnly_prop_locations' => array($this, 'sanitize_array_field'),
            'in_tag' => array($this, 'sanitize_taxonomy_ids'),
            'in_badge' => array($this, 'sanitize_taxonomy_ids'),
            'in_status' => array($this, 'sanitize_taxonomy_ids'),
            'in_type' => array($this, 'sanitize_taxonomy_ids'),
            'in_location' => array($this, 'sanitize_taxonomy_ids'),
            'in_feature' => array($this, 'sanitize_taxonomy_ids'),
            'in_review' => array($this, 'sanitize_taxonomy_ids'),
            'amenities' => array($this, 'sanitize_array_field'),
            'hvnly_prop_features' => array($this, 'sanitize_array_field'),
            'hvnly_prop_reviews' => array($this, 'sanitize_array_field'),
            'hvnly_prop_tags' => array($this, 'sanitize_array_field'),
            'hvnly_prop_badges' => array($this, 'sanitize_array_field'),
            'hvnly_prop_status' => array($this, 'sanitize_array_field'),
            'featured_only' => 'sanitize_text_field',
            'view_type' => 'sanitize_text_field',
            'paged' => 'absint',
            'widget_id' => 'sanitize_text_field',
            'instance_id' => 'sanitize_text_field',
            'agent_id' => 'absint',
            'agency_term_id' => 'absint',
        );
        
        foreach ($allowed_keys as $key => $callback) {
            if (isset($data[$key])) {
                if (is_array($callback)) {
                    $sanitized[$key] = call_user_func($callback, $data[$key]);
                } elseif (is_callable($callback)) {
                    $sanitized[$key] = $callback(wp_unslash($data[$key]));
                } else {
                    $sanitized[$key] = $callback;
                }
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize array field
     *
     * @param mixed $data
     * @return array
     */
    private function sanitize_array_field($data)
    {
        if (empty($data)) {
            return array();
        }
        
        if (!is_array($data)) {
            $data = explode(',', $data);
        }
        
        return array_map('sanitize_text_field', array_filter($data));
    }

    /**
     * Sanitize taxonomy IDs
     *
     * @param mixed $data
     * @return array
     */
    private function sanitize_taxonomy_ids($data)
    {
        if (empty($data)) {
            return array();
        }
        
        // Replace %2C with comma if present
        $data = str_replace('%2C', ',', $data);
        
        if (!is_array($data)) {
            $data = explode(',', $data);
        }
        
        return array_map('absint', array_filter($data));
    }

    /**
     * Generate cache key for search results
     *
     * @param array $args
     * @param int   $page
     * @param int   $per_page
     * @return string
     */
    private function generate_search_cache_key($args, $page, $per_page)
    {
        $key_data = array(
            'args' => $args,
            'page' => $page,
            'per_page' => $per_page,
            'lang' => function_exists('pll_current_language') ? pll_current_language() : get_locale()
        );

        $cache_key = 'hvnly_search_' . md5(serialize($key_data));

        return apply_filters('hvnly_search_cache_key', $cache_key, $key_data);
    }

    /**
     * Get cached search results
     *
     * @param string $cache_key
     * @return mixed
     */
    private function get_cached_search_results($cache_key)
    {
        if (!\hvnly_is_cache_enabled()) {
            return false;
        }

        $cached = get_transient($cache_key);

        return apply_filters('hvnly_get_cached_search_results', $cached, $cache_key);
    }

    /**
     * Set cached search results
     *
     * @param string $cache_key Cache key
     * @param mixed  $data      Data to cache
     */
    private function set_cached_search_results($cache_key, $data)
    {
        if (!\hvnly_is_cache_enabled()) {
            return;
        }

        $duration = apply_filters('hvnly_search_cache_duration', $this->search_cache_duration);
        set_transient($cache_key, $data, $duration);

        do_action('hvnly_set_cached_search_results', $cache_key, $data, $duration);
    }

    /**
     * Generate search response data using templates
     *
     * @param \WP_Query $query       Properties query
     * @param int      $page        Current page
     * @param int      $per_page    Posts per page
     * @param array    $filters     Current filters
     * @param bool     $is_top_search Whether it's a top search
     * @return array
     */
    private function generate_search_response($query, $page, $per_page, $filters, $is_top_search = false)
    {
        global $wp_query;

        // Set up global query for template compatibility
        $original_query = $wp_query;
        $wp_query = $query;

        // Use template for property grid
        ob_start();
        $this->load_property_grid_template($query, $is_top_search, $filters);
        $html = ob_get_clean();

        // Restore original query
        $wp_query = $original_query;

        if ($is_top_search) {
            return array(
                'html' => $html,
                'found_posts' => $query->found_posts,
            );
        }

        // Generate pagination using template
        ob_start();
        $this->generate_pagination_template($query, $page);
        $pagination_html = ob_get_clean();

        // Generate results count using template
        ob_start();
        $this->generate_results_count_template($query, $page, $per_page, $filters);
        $results_count_html = ob_get_clean();

        // Calculate start and end for display
        $start = (($page - 1) * $per_page) + 1;
        $end = min($page * $per_page, $query->found_posts);
        $loaded_count = min($page * $per_page, $query->found_posts);

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
            'loaded_count' => $loaded_count,
        );
    }

    /**
     * Load property grid using template
     *
     * @param \WP_Query $query Properties query
     * @param bool      $is_top_search Whether it's a top search
     * @param array     $filters       Current search filters (agent/agency context)
     * @return void
     */
    private function load_property_grid_template($query, $is_top_search = false, $filters = array())
    {
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $this->render_property_listing_card($filters);
            }
            wp_reset_postdata();
        } else {
            // Use no results template
            hvnly_get_template_part('content', 'none');
        }
    }

    /**
     * Render a single property card, wrapped for assigned listing contexts.
     *
     * Agency/agent single pages scope card styles under item wrappers; AJAX must match SSR markup.
     *
     * @param array $filters Current search filters
     * @return void
     */
    private function render_property_listing_card($filters = array())
    {
        $instance_id    = isset($filters['instance_id']) ? sanitize_text_field($filters['instance_id']) : '';
        $agent_id       = isset($filters['agent_id']) ? absint($filters['agent_id']) : 0;
        $agency_term_id = isset($filters['agency_term_id']) ? absint($filters['agency_term_id']) : 0;

        if ($instance_id === 'agency-properties' || $agency_term_id > 0) {
            echo '<div class="hvnly-agency-single__property-item">';
            hvnly_get_template_part('content', 'property');
            echo '</div>';
            return;
        }

        if ($instance_id === 'agent-properties' || $agent_id > 0) {
            $dept_ids = wp_get_post_terms(get_the_ID(), 'hvnly_prop_depts', array('fields' => 'ids'));
            if (is_wp_error($dept_ids)) {
                $dept_ids = array();
            }
            $dept_attr = !empty($dept_ids) ? implode(' ', array_map('absint', $dept_ids)) : 'none';
            printf(
                '<div class="hvnly-agent-single__property-item" data-dept-ids="%s">',
                esc_attr($dept_attr)
            );
            hvnly_get_template_part('content', 'property');
            echo '</div>';
            return;
        }

        hvnly_get_template_part('content', 'property');
    }

    /**
     * Generate pagination using existing template
     *
     * @param \WP_Query $query Properties query
     * @param int $current_page Current page number
     * @return void
     */
    private function generate_pagination_template($query, $current_page)
    {
        if (function_exists('hvnly_render_ajax_pagination_fragment')) {
            hvnly_render_ajax_pagination_fragment($query, $current_page);
            return;
        }

        global $wp_query;

        $original_query = $wp_query;
        $wp_query       = $query;

        if (function_exists('hvnly_get_template')) {
            hvnly_get_template('search/pagination.php', array());
        }

        $wp_query = $original_query;
    }

    /**
     * Generate results count using existing template
     *
     * @param \WP_Query $query Properties query
     * @param int $current_page Current page number
     * @param int $per_page Posts per page
     * @param array $filters Current filters
     * @return void
     */
    private function generate_results_count_template($query, $current_page, $per_page, $filters)
    {
        if (function_exists('hvnly_render_ajax_results_count_fragment')) {
            hvnly_render_ajax_results_count_fragment($query, $current_page, $per_page, $filters);
            return;
        }

        global $wp_query;

        $original_query   = $wp_query;
        $wp_query         = $query;
        $total_properties = (int) $query->found_posts;
        $start            = (( (int) $current_page - 1 ) * (int) $per_page) + 1;
        $end              = min((int) $current_page * (int) $per_page, $total_properties);
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
     * Clear sidebar terms cache when terms are modified
     *
     * @param int    $term_id Term ID
     * @param int    $tt_id   Term taxonomy ID
     * @param string $taxonomy Taxonomy name
     */
    public function clear_sidebar_terms_cache($term_id, $tt_id = 0, $taxonomy = '')
    {
        // Handle different hook signatures
        if (func_num_args() === 1) {
            // For delete_term hook (3 params)
            $term_id = func_get_arg(0);
            $taxonomy = func_get_arg(2);
        } elseif (func_num_args() === 2) {
            // For created_term and edited_term hooks
            $term_id = func_get_arg(0);
            $taxonomy = func_get_arg(1);
        }
        
        // Early validation to prevent unnecessary processing
        if (empty($term_id) || !is_numeric($term_id)) {
            return;
        }

        $term = get_term($term_id);
        
        // Comprehensive validation for term existence and validity
        if (!$term || is_wp_error($term) || !isset($term->taxonomy)) {
            return;
        }

        // Only proceed if we have a valid term with taxonomy
        try {
            if (class_exists('\HvnlyNab\Frontend\ViewModels\SidebarSearchFilters')) {
                $sidebar_filters = new \HvnlyNab\Frontend\ViewModels\SidebarSearchFilters();
                $sidebar_filters->clear_terms_cache($term->taxonomy);
            }
            
            do_action('hvnly_cleared_sidebar_terms_cache', $term_id, $term->taxonomy);
        } catch (\Exception $e) {
            // Silent fail in production
        }
    }

    
    /**
     * Clear cache when property is saved or deleted
     *
     * @param int           $post_id Post ID.
     * @param \WP_Post|null $post    Post object (required for delete_post / before_delete_post).
     */
    public function clear_property_cache($post_id, $post = null)
    {
        if (!\hvnly_is_cache_enabled()) {
            return;
        }

        if (!$this->is_hvnly_property($post_id, $post)) {
            return;
        }

        $this->clear_all_search_transients();
        do_action('hvnly_cleared_property_cache', $post_id);
    }

    /**
     * Clear listing cache when relevant property meta is updated outside save_post.
     *
     * @param int    $meta_id    Meta ID.
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     */
    public function clear_property_cache_on_meta_update($meta_id, $post_id, $meta_key, $meta_value)
    {
        unset($meta_id, $meta_value);
        $this->maybe_clear_property_cache_for_meta($post_id, $meta_key);
    }

    /**
     * Clear listing cache when relevant property meta is deleted.
     *
     * @param array  $meta_ids   Deleted meta row IDs.
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     */
    public function clear_property_cache_on_meta_delete($meta_ids, $post_id, $meta_key, $meta_value)
    {
        unset($meta_ids, $meta_value);
        $this->maybe_clear_property_cache_for_meta($post_id, $meta_key);
    }

    /**
     * Clear listing cache when property taxonomies are assigned without save_post.
     *
     * @param int    $object_id  Post ID.
     * @param array  $terms      Term IDs.
     * @param array  $tt_ids     Term taxonomy IDs.
     * @param string $taxonomy   Taxonomy slug.
     * @param bool   $append     Whether terms were appended.
     * @param array  $old_tt_ids Previous term taxonomy IDs.
     */
    public function clear_property_cache_on_term_assignment($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)
    {
        unset($terms, $tt_ids, $append, $old_tt_ids);

        if (!$this->is_property_listing_taxonomy($taxonomy) || !$this->is_hvnly_property($object_id)) {
            return;
        }

        $this->clear_property_cache($object_id);
    }

    /**
     * @param int    $post_id  Post ID.
     * @param string $meta_key Meta key.
     */
    private function maybe_clear_property_cache_for_meta($post_id, $meta_key)
    {
        if (!\hvnly_is_cache_enabled()) {
            return;
        }

        if (!$this->is_listing_meta_key($meta_key) || !$this->is_hvnly_property($post_id)) {
            return;
        }

        $this->clear_all_search_transients();
        do_action('hvnly_cleared_property_cache', $post_id);
    }

    /**
     * @param int           $post_id Post ID.
     * @param \WP_Post|null $post    Optional post object.
     * @return bool
     */
    private function is_hvnly_property($post_id, $post = null)
    {
        if ($post instanceof \WP_Post) {
            return $post->post_type === 'hvnly_property';
        }

        return get_post_type($post_id) === 'hvnly_property';
    }

    /**
     * @param string $meta_key Meta key.
     * @return bool
     */
    private function is_listing_meta_key($meta_key)
    {
        $meta_key = (string) $meta_key;

        $exact_keys = array(
            '_hvnly_property_price',
            '_hvnly_property_bedrooms',
            '_hvnly_property_bathrooms',
            '_hvnly_property_featured',
            '_hvnly_property_action_tool_is_featured',
            '_hvnly_property_latitude',
            '_hvnly_property_longitude',
            '_hvnly_property_location_Latitude',
            '_hvnly_property_location_Longitude',
            '_hvnly_active_map_latitude_key',
            '_hvnly_active_map_longitude_key',
            '_hvnly_active_map_address_key',
            '_hvnly_property_map_location_address',
            '_hvnly_unique_property_id',
            '_thumbnail_id',
        );

        if (in_array($meta_key, $exact_keys, true)) {
            return true;
        }

        if (preg_match('/_(latitude|longitude|lat|lng)$/i', $meta_key)) {
            return true;
        }

        if (preg_match('/^map_.+_(latitude|longitude)$/i', $meta_key)) {
            return true;
        }

        if (preg_match('/_address$/i', $meta_key)) {
            return true;
        }

        if (preg_match('/^map_.+_address$/i', $meta_key)) {
            return true;
        }

        return (bool) preg_match('/status/i', $meta_key);
    }

    /**
     * @param string $taxonomy Taxonomy slug.
     * @return bool
     */
    private function is_property_listing_taxonomy($taxonomy)
    {
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

        return in_array((string) $taxonomy, $taxonomies, true);
    }

    /**
     * Clear cache when terms are modified
     *
     * @param int    $term_id Term ID
     * @param int    $tt_id   Term taxonomy ID
     * @param string $taxonomy Taxonomy name
     */
    public function clear_terms_cache($term_id, $tt_id = 0, $taxonomy = '')
    {
        if (!\hvnly_is_cache_enabled()) {
            return;
        }

        // Handle different hook signatures
        if (func_num_args() === 1) {
            // For delete_term hook (3 params)
            $term_id = func_get_arg(0);
            $taxonomy = func_get_arg(2);
        } elseif (func_num_args() === 2) {
            // For created_term and edited_term hooks
            $term_id = func_get_arg(0);
            $taxonomy = func_get_arg(1);
        }
        
        // Early validation to prevent unnecessary processing
        if (empty($term_id) || !is_numeric($term_id)) {
            $this->clear_all_search_transients();
            do_action('hvnly_cleared_terms_cache', $term_id);
            return;
        }

        $term = get_term($term_id);
        
        // Comprehensive validation for term existence and validity
        if (!$term || is_wp_error($term) || !isset($term->taxonomy)) {
            $this->clear_all_search_transients();
            do_action('hvnly_cleared_terms_cache', $term_id);
            return;
        }

        // Only proceed if we have a valid term with taxonomy
        try {
            if (class_exists('\HvnlyNab\Frontend\ViewModels\SearchFilters')) {
                $search_filters = new \HvnlyNab\Frontend\ViewModels\SearchFilters();
                $search_filters->clear_terms_cache($term->taxonomy);
            }
        } catch (\Exception $e) {
            // Silent fail in production
        }

        $this->clear_all_search_transients();
        do_action('hvnly_cleared_terms_cache', $term_id);
    }

    /**
     * Clear all search-related transients
     *
     * Uses direct database query with proper suppression comments since
     * pattern-based transient deletion cannot be accomplished with standard
     * WordPress API. This is the most efficient method for clearing
     * multiple transients matching a pattern.
     */
    private function clear_all_search_transients()
    {
        if (!\hvnly_is_cache_enabled()) {
            return;
        }

        global $wpdb;
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        // Direct query required for pattern-based transient deletion
        $transient_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name 
                FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                '_transient_hvnly_search_%',
                '_transient_timeout_hvnly_search_%'
            )
        );
        // phpcs:enable
        
        if (empty($transient_keys)) {
            if (class_exists(PropertyQueryCache::class)) {
                PropertyQueryCache::invalidate_all();
            }
            do_action('hvnly_cleared_all_search_transients');
            return;
        }
        
        // Batch delete transients for better performance
        $deleted_count = 0;
        foreach ($transient_keys as $key) {
            // Extract transient name using regex for reliability
            if (preg_match('/^_transient_(?:timeout_)?(.+)$/', $key, $matches)) {
                $transient_name = $matches[1];
                
                // Use WordPress API to ensure proper cache invalidation
                if (delete_transient($transient_name)) {
                    $deleted_count++;
                }
            }
        }
        
        // Log deletion for debugging if needed (only in debug mode)

        if (class_exists(PropertyQueryCache::class)) {
            PropertyQueryCache::invalidate_all();
        }

        do_action('hvnly_cleared_all_search_transients');
    }

    /**
     * Build query args from request data
     *
     * @param array $data Request data
     * @param int   $page Current page
     * @param int   $per_page Posts per page
     * @return array Query args
     */
    private function build_query_args($data, $page = 1, $per_page = 12)
    {
        return PropertyQueryArgsBuilder::build($data, (int) $page, (int) $per_page);
    }

    /**
     * Get properties for map view with Property Builder support
     *
     * @return void
     */
    public function hvnly_get_properties_for_map()
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!wp_verify_nonce($nonce, 'hvnly_ajax_request')) {  
            wp_send_json_error('Security check failed');
        }

        try {
            $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;

            $custom_per_page = function_exists('hvnly_get_properties_per_page')
                ? absint(hvnly_get_properties_per_page())
                : absint(get_option('posts_per_page', 12));

            $per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : $custom_per_page;
            if ($per_page < 1) {
                $per_page = max(1, $custom_per_page);
            }

            $post_data = $this->sanitize_post_data($_POST);
            $args = $this->build_query_args($post_data, $page, $per_page);
            $cache_key = $this->generate_search_cache_key($args, $page, $per_page) . '_map';

            $cached_results = $this->get_cached_search_results($cache_key);

            if (false !== $cached_results) {
                $cached_has_coords = ! empty($cached_results['properties']);
                $cached_found      = isset($cached_results['found_posts']) ? (int) $cached_results['found_posts'] : 0;

                // Do not reuse cached empty coordinate sets when listings still exist.
                if ($cached_has_coords || $cached_found === 0) {
                    wp_send_json_success($cached_results);
                }
            }

            $properties_query = PropertyQueryExecutor::query($post_data, $page, $per_page, [
                'filter_context' => 'map',
            ]);
            $properties = array();
            $posts_scanned = 0;
            $posts_with_coordinates = 0;

            if ($properties_query->have_posts()) {
                while ($properties_query->have_posts()) {
                    $properties_query->the_post();
                    $post_id = get_the_ID();
                    ++$posts_scanned;

                    $map_data = $this->get_property_map_data($post_id);
                    
                    $price = get_post_meta($post_id, '_hvnly_property_price', true);
                    $bedrooms = get_post_meta($post_id, '_hvnly_property_bedrooms', true);
                    $bathrooms = get_post_meta($post_id, '_hvnly_property_bathrooms', true);

                    $formatted_price = '';
                    if (!empty($price)) {
                        if (is_numeric($price)) {
                            $formatted_price = '$' . number_format((float)$price);
                        } elseif (is_string($price)) {
                            $decoded = json_decode($price, true);
                            if (is_array($decoded) && isset($decoded['__type']) && $decoded['__type'] === 'custom_label') {
                                $formatted_price = isset($decoded['label']) ? $decoded['label'] : $price;
                            } else {
                                $clean_price = preg_replace('/[^0-9.]/', '', $price);
                                if (is_numeric($clean_price)) {
                                    $formatted_price = '$' . number_format((float)$clean_price);
                                } else {
                                    $formatted_price = $price;
                                }
                            }
                        }
                    }

                    $sqft = get_post_meta($post_id, '_hvnly_property_sqft', true);
                    $area = '';
                    if (!empty($sqft)) {
                        $area = is_numeric($sqft)
                            ? number_format((float) $sqft) . ' ' . __('sqft', 'havenlytics')
                            : (string) $sqft;
                    }

                    // Primary listing status (e.g. For Sale / For Rent / Sold) for the popup badge.
                    // Additive field: existing consumers ignore it; reads the real hvnly_prop_status taxonomy.
                    $status_label = '';
                    $status_terms = get_the_terms($post_id, 'hvnly_prop_status');
                    if (is_array($status_terms) && !empty($status_terms) && !is_wp_error($status_terms)) {
                        $status_label = $status_terms[0]->name;
                    }

                    $property_data = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'lat' => $map_data['lat'],
                        'lng' => $map_data['lng'],
                        'address' => $map_data['address'],
                        'price' => $formatted_price,
                        'status' => $status_label,
                        'bedrooms' => $bedrooms,
                        'bathrooms' => $bathrooms,
                        'area' => $area,
                        'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
                        'link' => get_permalink(),
                        'has_coordinates' => $map_data['has_coordinates'],
                    );

                    $properties[] = $property_data;

                    if ( ! empty( $property_data['has_coordinates'] ) ) {
                        ++$posts_with_coordinates;
                    }
                }
                wp_reset_postdata();
            }

            $valid_properties = array_filter($properties, function ($property) {
                return $property['has_coordinates'];
            });

            $start = (($page - 1) * $per_page) + 1;
            $end = min($page * $per_page, $properties_query->found_posts);

            ob_start();
            $this->generate_pagination_template($properties_query, $page);
            $pagination_html = ob_get_clean();

            ob_start();
            $this->generate_results_count_template($properties_query, $page, $per_page, $post_data);
            $results_count_html = ob_get_clean();

            $response_data = array(
                'properties' => array_values($valid_properties),
                'count' => count($valid_properties),
                'found_posts' => $properties_query->found_posts,
                'max_pages' => $properties_query->max_num_pages,
                'current_page' => $page,
                'posts_per_page' => $per_page,
                'start' => $start,
                'end' => $end,
                'pagination_html' => $pagination_html,
                'results_count_html' => $results_count_html,
                'loaded_count' => min($page * $per_page, $properties_query->found_posts),
                'posts_scanned' => $posts_scanned,
                'posts_with_coordinates' => $posts_with_coordinates,
            );

            if ( function_exists( 'hvnly_is_debug_logging_enabled' ) && hvnly_is_debug_logging_enabled() ) {
                $sample_coords = array();
                if ( $posts_scanned > 0 && $posts_with_coordinates === 0 && ! empty( $properties ) ) {
                    foreach ( array_slice( $properties, 0, 3 ) as $sample ) {
                        $sample_coords[] = array(
                            'id' => $sample['id'],
                            'has_coordinates' => ! empty( $sample['has_coordinates'] ),
                            'lat' => $sample['lat'],
                            'lng' => $sample['lng'],
                        );
                    }
                }

                $response_data['debug'] = array(
                    'cache_key' => $cache_key,
                    'query_args' => $args,
                    'filter_keys' => array_keys($post_data),
                    'map_provider' => function_exists('hvnly_get_map_provider') ? hvnly_get_map_provider() : '',
                    'map_field_definitions' => count($this->get_map_field_definitions()),
                    'sample_properties' => $sample_coords,
                );
            }

            $this->set_cached_search_results($cache_key, $response_data);
            wp_send_json_success($response_data);
            
        } catch (\Exception $e) {
            wp_send_json_error('An error occurred while loading map properties.');
        } catch (\Error $e) {
            wp_send_json_error('An error occurred while loading map properties.');
        }
    }

    /**
     * Load more properties
     *
     * @return void
     */
    public function load_more_properties()
    {
        $this->search_properties();
    }

    /**
     * Get filter options via AJAX
     *
     * @return void
     */
    public function get_filter_options()
    {
        // Fixed nonce handling with proper unslashing
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        
        if (!wp_verify_nonce($nonce, 'hvnly_ajax_request')) {
            wp_send_json_error('Security check failed');
        }

        // Fixed taxonomy parameter handling
        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field(wp_unslash($_POST['taxonomy'])) : '';

        if (empty($taxonomy)) {
            wp_send_json_error(__('Taxonomy not specified', 'havenlytics'));
        }

        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
        ));

        if (is_wp_error($terms)) {
            wp_send_json_error(__('Error fetching terms', 'havenlytics'));
        }

        $options = array();
        foreach ($terms as $term) {
            $options[] = array(
                'value' => $term->slug,
                'label' => $term->name,
                'count' => $term->count,
            );
        }

        wp_send_json_success($options);
    }
}