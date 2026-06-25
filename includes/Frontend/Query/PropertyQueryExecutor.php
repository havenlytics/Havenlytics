<?php
/**
 * Executes property listing queries with caching and safe optimizations.
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Query
 * @since       3.2.0
 */

namespace HvnlyNab\Frontend\Query;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyQueryExecutor {

    /**
     * Request-scoped memoization for executed queries.
     *
     * @var array<string, \WP_Query>
     */
    private static $hvnly_request_queries = [];

    /**
     * Run a property listing query through the unified builder + cache layer.
     *
     * @param array $hvnly_data     Filter payload.
     * @param int   $hvnly_page     Page number.
     * @param int   $hvnly_per_page Posts per page.
     * @param array $hvnly_options  widget_id, filter_context, bypass_cache.
     * @return \WP_Query
     */
    public static function query(array $hvnly_data, int $hvnly_page = 1, int $hvnly_per_page = 12, array $hvnly_options = []): \WP_Query {
        $hvnly_options = wp_parse_args($hvnly_options, [
            'widget_id'      => '',
            'filter_context' => 'default',
            'bypass_cache'   => false,
        ]);

        $hvnly_build_options = [];
        if ($hvnly_options['widget_id'] !== '') {
            $hvnly_build_options['widget_id'] = (string) $hvnly_options['widget_id'];
        }
        if (!empty($hvnly_options['filter_context'])) {
            $hvnly_build_options['filter_context'] = (string) $hvnly_options['filter_context'];
        }

        $hvnly_memo_key = self::hvnly_request_memo_key($hvnly_data, $hvnly_page, $hvnly_per_page, $hvnly_options);
        if (isset(self::$hvnly_request_queries[$hvnly_memo_key])) {
            return self::$hvnly_request_queries[$hvnly_memo_key];
        }

        $hvnly_args = PropertyQueryArgsBuilder::build($hvnly_data, $hvnly_page, $hvnly_per_page, $hvnly_build_options);
        $hvnly_args = self::hvnly_apply_query_optimizations($hvnly_args, $hvnly_options);

        if (!empty($hvnly_options['bypass_cache']) || !\hvnly_is_cache_enabled()) {
            $hvnly_query = new \WP_Query($hvnly_args);
            self::$hvnly_request_queries[$hvnly_memo_key] = $hvnly_query;

            return $hvnly_query;
        }

        if (($hvnly_args['orderby'] ?? '') === 'rand') {
            $hvnly_query = new \WP_Query($hvnly_args);
            self::$hvnly_request_queries[$hvnly_memo_key] = $hvnly_query;

            return $hvnly_query;
        }

        $hvnly_cache_key = PropertyQueryCache::generate_key($hvnly_data, $hvnly_page, $hvnly_per_page, $hvnly_options);
        $hvnly_cached    = PropertyQueryCache::get($hvnly_cache_key);

        if (false !== $hvnly_cached) {
            $hvnly_query = PropertyQueryCache::hydrate_query($hvnly_cached, $hvnly_args);
            self::$hvnly_request_queries[$hvnly_memo_key] = $hvnly_query;

            return $hvnly_query;
        }

        $hvnly_query = new \WP_Query($hvnly_args);
        PropertyQueryCache::set($hvnly_cache_key, $hvnly_query, $hvnly_options);
        self::$hvnly_request_queries[$hvnly_memo_key] = $hvnly_query;

        return $hvnly_query;
    }

    /**
     * Build query args only (no execution) — uses builder memoization.
     *
     * @param array $hvnly_data
     * @param int   $hvnly_page
     * @param int   $hvnly_per_page
     * @param array $hvnly_options
     * @return array
     */
    public static function build_args(array $hvnly_data, int $hvnly_page = 1, int $hvnly_per_page = 12, array $hvnly_options = []): array {
        $hvnly_build_options = [];
        if (!empty($hvnly_options['widget_id'])) {
            $hvnly_build_options['widget_id'] = (string) $hvnly_options['widget_id'];
        }
        if (!empty($hvnly_options['filter_context'])) {
            $hvnly_build_options['filter_context'] = (string) $hvnly_options['filter_context'];
        }

        $hvnly_args = PropertyQueryArgsBuilder::build($hvnly_data, $hvnly_page, $hvnly_per_page, $hvnly_build_options);

        return self::hvnly_apply_query_optimizations($hvnly_args, $hvnly_options);
    }

    /**
     * @param array $hvnly_args
     * @param array $hvnly_options
     * @return array
     */
    private static function hvnly_apply_query_optimizations(array $hvnly_args, array $hvnly_options): array {
        // Pagination and load-more depend on found_posts / max_num_pages.
        $hvnly_args['no_found_rows'] = false;

        if (!isset($hvnly_args['lazy_load_term_meta'])) {
            $hvnly_args['lazy_load_term_meta'] = true;
        }

        return apply_filters('hvnly_property_query_executor_args', $hvnly_args, $hvnly_options);
    }

    /**
     * @param array $hvnly_data
     * @param int   $hvnly_page
     * @param int   $hvnly_per_page
     * @param array $hvnly_options
     * @return string
     */
    private static function hvnly_request_memo_key(array $hvnly_data, int $hvnly_page, int $hvnly_per_page, array $hvnly_options): string {
        return md5(wp_json_encode([
            PropertyQueryCache::generate_key($hvnly_data, $hvnly_page, $hvnly_per_page, $hvnly_options),
            !empty($hvnly_options['bypass_cache']),
        ]));
    }
}
