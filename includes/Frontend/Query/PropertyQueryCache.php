<?php
/**
 * Property query result cache — object cache with transient fallback.
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Query
 * @since       3.2.0
 */

namespace HvnlyNab\Frontend\Query;

use HvnlyNab\Core\CacheManager;

if ( ! defined('ABSPATH')) {
    exit;
}

class PropertyQueryCache {

    public const CACHE_GROUP = 'hvnly_property_queries';

    private const TRANSIENT_PREFIX = 'hvnly_pquery_';

    private const GENERATION_OPTION = 'hvnly_property_query_cache_gen';

    private const EMPTY_RESULT_TTL = 60;

    /**
     * Filter keys that affect query results.
     *
     * @return string[]
     */
    private static function hvnly_relevant_filter_keys(): array {
        $keys = array(
            'address_keyword',
            'department',
            'min_price',
            'max_price',
            'bedrooms',
            'bathrooms',
            'reception_rooms',
            'garages',
            'orderby',
            'featured_only',
            'view_type',
            'property_ids',
            'property_type',
            'location',
            'status',
            'widget_id',
            'hvnly_prop_depts',
            'hvnly_prop_types',
            'hvnly_prop_locations',
            'hvnly_prop_features',
            'hvnly_prop_reviews',
            'hvnly_prop_tags',
            'hvnly_prop_badges',
            'hvnly_prop_status',
            'amenities',
            'in_tag',
            'in_badge',
            'in_status',
            'in_type',
            'in_location',
            'in_feature',
            'in_review',
        );

        /**
         * Filter canonical property search filter keys (Saved Searches, cache, etc.).
         *
         * @param string[] $keys Filter keys understood by PropertyQueryExecutor.
         */
        $filtered = apply_filters( 'hvnly_property_search_filter_keys', $keys );

        return is_array( $filtered ) ? array_values( array_unique( array_map( 'strval', $filtered ) ) ) : $keys;
    }

    /**
     * Public list of filter keys that affect property search results.
     *
     * @return string[]
     */
    public static function relevant_filter_keys(): array {
        return self::hvnly_relevant_filter_keys();
    }

    /**
     * Build a deterministic cache key for a property listing query.
     *
     * @param array $hvnly_data     Filter payload.
     * @param int   $hvnly_page     Page number.
     * @param int   $hvnly_per_page Posts per page.
     * @param array $hvnly_options  Executor options (widget_id, filter_context, etc.).
     * @return string
     */
    public static function generate_key( array $hvnly_data, int $hvnly_page, int $hvnly_per_page, array $hvnly_options = array() ): string {
        $hvnly_payload = array(
            'gen'       => self::get_generation(),
            'page'      => max(1, $hvnly_page),
            'per_page'  => max(1, $hvnly_per_page),
            'widget_id' => (string) ( $hvnly_options['widget_id'] ?? ( $hvnly_data['widget_id'] ?? '' ) ),
            'context'   => (string) ( $hvnly_options['filter_context'] ?? 'default' ),
            'lang'      => function_exists('pll_current_language') ? pll_current_language() : get_locale(),
            'filters'   => self::hvnly_normalize_filters($hvnly_data),
        );

        $hvnly_hash = md5(wp_json_encode($hvnly_payload));

        return apply_filters('hvnly_property_query_cache_key', self::TRANSIENT_PREFIX . $hvnly_hash, $hvnly_payload);
    }

    /**
     * @param array $hvnly_data Raw filter payload.
     * @return array
     */
    private static function hvnly_normalize_filters( array $hvnly_data ): array {
        $hvnly_normalized = array();

        foreach (self::hvnly_relevant_filter_keys() as $hvnly_key) {
            if ( ! isset($hvnly_data[ $hvnly_key ]) || $hvnly_data[ $hvnly_key ] === '' || $hvnly_data[ $hvnly_key ] === array()) {
                continue;
            }

            $hvnly_value = $hvnly_data[ $hvnly_key ];
            if (is_array($hvnly_value)) {
                $hvnly_value = array_values(array_filter(array_map('strval', $hvnly_value)));
                sort($hvnly_value);
            } else {
                $hvnly_value = sanitize_text_field( (string) $hvnly_value);
            }

            $hvnly_normalized[ $hvnly_key ] = $hvnly_value;
        }

        ksort($hvnly_normalized);

        return $hvnly_normalized;
    }

    /**
     * @return int
     */
    public static function get_generation(): int {
        return max(1, (int) get_option(self::GENERATION_OPTION, 1));
    }

    /**
     * Retrieve cached query payload.
     *
     * @param string $hvnly_cache_key Cache key from generate_key().
     * @return array|false
     */
    public static function get( string $hvnly_cache_key ) {
        if ( ! \hvnly_is_cache_enabled()) {
            return false;
        }

        $hvnly_storage_key = self::hvnly_sanitize_storage_key($hvnly_cache_key);
        $engine            = function_exists('HVNLY_NAB') ? HVNLY_NAB()->engine() : null;

        if (wp_using_ext_object_cache()) {
            $hvnly_cached = wp_cache_get($hvnly_storage_key, self::CACHE_GROUP);
            if (false !== $hvnly_cached && is_array($hvnly_cached)) {
                if ($engine && method_exists($engine, 'track_cache_hit')) {
                    $engine->track_cache_hit();
                }
                return apply_filters('hvnly_property_query_cache_hit', $hvnly_cached, $hvnly_cache_key);
            }
        }

        $hvnly_transient_name = self::hvnly_transient_name($hvnly_storage_key);
        $hvnly_cached         = get_transient($hvnly_transient_name);

        if (false === $hvnly_cached || ! is_array($hvnly_cached)) {
            if ($engine && method_exists($engine, 'track_cache_miss')) {
                $engine->track_cache_miss();
            }
            return false;
        }

        if ($engine && method_exists($engine, 'track_cache_hit')) {
            $engine->track_cache_hit();
        }

        if (wp_using_ext_object_cache()) {
            wp_cache_set(
                $hvnly_storage_key,
                $hvnly_cached,
                self::CACHE_GROUP,
                self::hvnly_resolve_ttl(array(), $hvnly_cached)
            );
        }

        return apply_filters('hvnly_property_query_cache_hit', $hvnly_cached, $hvnly_cache_key);
    }

    /**
     * Store query payload.
     *
     * @param string    $hvnly_cache_key Cache key.
     * @param \WP_Query $hvnly_query     Executed query.
     * @param array     $hvnly_options   Executor options.
     * @return void
     */
    public static function set( string $hvnly_cache_key, \WP_Query $hvnly_query, array $hvnly_options = array() ): void {
        if ( ! \hvnly_is_cache_enabled()) {
            return;
        }

        $hvnly_payload     = self::extract_payload($hvnly_query);
        $hvnly_ttl         = self::hvnly_resolve_ttl($hvnly_options, $hvnly_payload);
        $hvnly_storage_key = self::hvnly_sanitize_storage_key($hvnly_cache_key);

        if (wp_using_ext_object_cache()) {
            wp_cache_set($hvnly_storage_key, $hvnly_payload, self::CACHE_GROUP, $hvnly_ttl);
        }

        set_transient(self::hvnly_transient_name($hvnly_storage_key), $hvnly_payload, $hvnly_ttl);

        do_action('hvnly_property_query_cache_set', $hvnly_cache_key, $hvnly_payload, $hvnly_ttl, $hvnly_options);
    }

    /**
     * @param \WP_Query $hvnly_query Query instance.
     * @return array{post_ids:int[],found_posts:int,max_num_pages:int,post_count:int}
     */
    public static function extract_payload( \WP_Query $hvnly_query ): array {
        return array(
            'post_ids'      => array_map('intval', wp_list_pluck($hvnly_query->posts, 'ID')),
            'found_posts'   => (int) $hvnly_query->found_posts,
            'max_num_pages' => (int) $hvnly_query->max_num_pages,
            'post_count'    => (int) $hvnly_query->post_count,
        );
    }

    /**
     * Rebuild a WP_Query object from cached post IDs while preserving counts.
     *
     * @param array $hvnly_cached        Cached payload.
     * @param array $hvnly_original_args Original query args (for parity / debugging).
     * @return \WP_Query
     */
    public static function hydrate_query( array $hvnly_cached, array $hvnly_original_args = array() ): \WP_Query {
        unset($hvnly_original_args);

        $hvnly_post_ids = array_filter(array_map('intval', $hvnly_cached['post_ids'] ?? array()));

        if (empty($hvnly_post_ids)) {
            $hvnly_query                = new \WP_Query(array(
                'post_type'      => 'hvnly_property',
                'post_status'    => 'publish',
                'post__in'       => array( 0 ),
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ));
            $hvnly_query->found_posts   = 0;
            $hvnly_query->max_num_pages = 0;
            $hvnly_query->post_count    = 0;

            return $hvnly_query;
        }

        $hvnly_query = new \WP_Query(array(
            'post_type'              => 'hvnly_property',
            'post_status'            => 'publish',
            'post__in'               => $hvnly_post_ids,
            'orderby'                => 'post__in',
            'posts_per_page'         => count($hvnly_post_ids),
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
            'lazy_load_term_meta'    => true,
        ));

        $hvnly_query->found_posts   = (int) ( $hvnly_cached['found_posts'] ?? count($hvnly_post_ids) );
        $hvnly_query->max_num_pages = (int) ( $hvnly_cached['max_num_pages'] ?? 1 );
        $hvnly_query->post_count    = (int) ( $hvnly_cached['post_count'] ?? count($hvnly_post_ids) );

        return $hvnly_query;
    }

    /**
     * Invalidate all property query caches.
     *
     * @return void
     */
    public static function invalidate_all(): void {
        if ( ! \hvnly_is_cache_enabled()) {
            return;
        }

        $hvnly_generation = self::get_generation();
        update_option(self::GENERATION_OPTION, $hvnly_generation + 1, false);

        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }

        CacheManager::clear_transients_by_pattern('pquery_');

        do_action('hvnly_property_query_cache_invalidated', $hvnly_generation + 1);
    }

    /**
     * @param array $hvnly_options Executor options.
     * @param array $hvnly_payload Cached payload.
     * @return int
     */
    private static function hvnly_resolve_ttl( array $hvnly_options, array $hvnly_payload ): int {
        $hvnly_default_ttl = (int) apply_filters('hvnly_property_query_cache_ttl', 10 * MINUTE_IN_SECONDS, $hvnly_options, $hvnly_payload);
        $hvnly_default_ttl = max(MINUTE_IN_SECONDS, min(15 * MINUTE_IN_SECONDS, $hvnly_default_ttl));

        $hvnly_context = (string) ( $hvnly_options['filter_context'] ?? 'default' );
        if (in_array($hvnly_context, array( 'ajax', 'elementor_load_more', 'map' ), true)) {
            $hvnly_default_ttl = min($hvnly_default_ttl, 5 * MINUTE_IN_SECONDS);
        }

        if (empty($hvnly_payload['post_ids']) || (int) ( $hvnly_payload['found_posts'] ?? 0 ) === 0) {
            return min($hvnly_default_ttl, self::EMPTY_RESULT_TTL);
        }

        return $hvnly_default_ttl;
    }

    /**
     * @param string $hvnly_cache_key Raw cache key.
     * @return string
     */
    private static function hvnly_sanitize_storage_key( string $hvnly_cache_key ): string {
        return preg_replace('/[^a-z0-9_]/', '', strtolower($hvnly_cache_key));
    }

    /**
     * @param string $hvnly_storage_key Sanitized storage key.
     * @return string
     */
    private static function hvnly_transient_name( string $hvnly_storage_key ): string {
        return self::TRANSIENT_PREFIX . md5($hvnly_storage_key);
    }
}
