<?php

/**
 * Cache Manager - Handles all cache operations for Havenlytics
 *
 * Centralized cache management system that handles transient operations,
 * cache statistics, and cache maintenance. Provides both database and
 * object cache support with compression capabilities.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Core;

// Prevent direct access to WordPress files
defined('ABSPATH') || exit;

/**
 * Cache Manager Class
 *
 * Comprehensive cache management system that handles:
 * - Transient storage and retrieval with compression
 * - Cache statistics and performance monitoring
 * - Bulk cache clearing operations
 * - Pattern-based cache invalidation
 * - Automatic cache maintenance
 *
 * Features:
 * - Supports both database transients and object caching
 * - Optional GZIP compression for large datasets
 * - Cache hit rate tracking and statistics
 * - Safe cache operations with error handling
 *
 * @since 2.0.0
 */
class CacheManager
{
    /**
     * Initialize default cache options
     *
     * Sets up default cache statistics and configuration options
     * when the plugin is activated. Provides realistic baseline
     * metrics for cache performance reporting.
     *
     * @return void
     */
    public static function initialize_cache_options()
    {
        /**
         * Set default counters to zero — never seed fake hit rates.
         */
        if (get_option('hvnly_cache_requests') === false) {
            update_option('hvnly_cache_requests', 0, false);
        }

        if (get_option('hvnly_cache_hits') === false) {
            update_option('hvnly_cache_hits', 0, false);
        }

        if (get_option('hvnly_cache_misses') === false) {
            update_option('hvnly_cache_misses', 0, false);
        }
    }

    /**
     * Clear all plugin cache entries
     *
     * Removes all Havenlytics-related cache entries from both database
     * transients and object cache. This is a comprehensive cache clearance
     * used during major updates or when cache corruption is suspected.
     *
     * @return int|false Number of database rows affected, or false on error
     */
    public static function clear_all_cache()
    {
        global $wpdb;

        /**
         * Delete all Havenlytics transients and their timeouts from database
         * Uses prepared statements to prevent SQL injection
         */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to purge Havenlytics transients by prefix.
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_hvnly_') . '%',
                $wpdb->esc_like('_transient_timeout_hvnly_') . '%'
            )
        );

        /**
         * Clear Havenlytics object-cache group when available.
         * Do NOT flush the entire object cache — that would wipe unrelated
         * plugins/themes when Redis or Memcached is active.
         */
        if (wp_using_ext_object_cache()) {
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('hvnly_property_queries');
            }
            if (class_exists('\HvnlyNab\Frontend\Query\PropertyQueryCache')) {
                \HvnlyNab\Frontend\Query\PropertyQueryCache::invalidate_all();
            }
        }

        /**
         * Fire action hook to allow other components to clear their caches
         * Third-party plugins can hook into this for coordinated cache clearing
         */
        do_action('hvnly_cache_cleared');

        return $result;
    }

    /**
     * Whether the global Havenlytics cache layer is enabled.
     *
     * @return bool
     */
    public static function is_enabled()
    {
        return \hvnly_is_cache_enabled();
    }

    /**
     * Clear cache entries matching a specific pattern
     *
     * Selective cache clearing for specific types of data. Useful when
     * only certain cache categories need invalidation (e.g., search results).
     *
     * @param string $pattern The pattern to match in cache keys
     * @return int|false Number of database rows affected, or false on error
     */
    public static function clear_transients_by_pattern($pattern)
    {
        global $wpdb;

        $pattern = (string) $pattern;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to locate Havenlytics transients by pattern.
        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_hvnly_' . $pattern) . '%'
            )
        );

        $deleted = 0;
        if (!empty($option_names)) {
            foreach ($option_names as $option_name) {
                $transient_name = str_replace('_transient_', '', $option_name);
                if (delete_transient($transient_name)) {
                    $deleted++;
                }
            }
        }

        /**
         * Fire action hook with the pattern for extended functionality
         */
        do_action('hvnly_cache_pattern_cleared', $pattern);

        return $deleted;
    }

    /**
     * Clear expired cache entries
     *
     * Maintenance function that removes cache entries whose TTL has expired.
     * This helps keep the database clean and prevents accumulation of stale data.
     *
     * @return void
     */
    public static function clear_expired_cache()
    {
        global $wpdb;
        $time = time();

        /**
         * Delete only expired cache timeouts
         * WordPress automatically cleans up the corresponding transient values
         */
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to remove expired Havenlytics transient timeouts.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                $wpdb->esc_like('_transient_timeout_hvnly_') . '%',
                $time
            )
        );

        /**
         * Fire action hook for expired cache cleanup
         */
        do_action('hvnly_cache_expired_cleared');
    }

    /**
     * Normalize a Havenlytics transient key for the Transients API.
     *
     * WordPress adds the `_transient_` option prefix itself — callers must
     * never pass `_transient_*` names into set_transient/get_transient.
     *
     * @param string $key Raw cache key.
     * @return string Key starting with hvnly_
     */
    private static function normalize_transient_key($key)
    {
        $key = (string) $key;

        if (strpos($key, '_transient_timeout_') === 0) {
            $key = substr($key, strlen('_transient_timeout_'));
        } elseif (strpos($key, '_transient_') === 0) {
            $key = substr($key, strlen('_transient_'));
        }

        $key = sanitize_key($key);

        if (strpos($key, 'hvnly_') !== 0) {
            $key = 'hvnly_' . $key;
        }

        return $key;
    }

    /**
     * Store value in cache with optional compression
     *
     * Wrapper for WordPress set_transient with Havenlytics-specific features:
     * - Automatic key prefixing
     * - Optional GZIP compression for large datasets
     * - Consistent TTL handling
     *
     * @param string $key The cache key (will be prefixed with 'hvnly_')
     * @param mixed $value The value to cache
     * @param int $ttl Time to live in seconds (default: 1 hour)
     * @return bool True if value was set, false otherwise
     */
    public static function set_transient($key, $value, $ttl = HOUR_IN_SECONDS)
    {
        if (!\hvnly_is_cache_enabled()) {
            return false;
        }

        $key = self::normalize_transient_key($key);

        /**
         * Apply compression if enabled in settings
         * Reduces database storage for large datasets
         */
        if (get_option('hvnly_cache_compression', false)) {
            $value = gzcompress(serialize($value));
        }

        /**
         * Use WordPress native transient function for actual storage
         * This ensures compatibility with object caching plugins
         */
        return set_transient($key, $value, $ttl);
    }

    /**
     * Retrieve value from cache with decompression support
     *
     * Retrieves cached values with proper handling for compressed data.
     * Includes error handling for corrupted or invalid cache entries.
     *
     * @param string $key The cache key to retrieve
     * @return mixed The cached value, or false if not found
     */
    public static function get_transient($key)
    {
        if (!\hvnly_is_cache_enabled()) {
            return false;
        }

        $key = self::normalize_transient_key($key);
        $value = get_transient($key);

        /**
         * Handle decompression if the value was stored with compression
         */
        if ($value && get_option('hvnly_cache_compression', false)) {
            $uncompressed = @gzuncompress($value);
            if ($uncompressed === false) {
                /**
                 * Log decompression failures in debug mode - REMOVED FOR PRODUCTION
                 * This helps identify corrupted cache entries
                 */
                // Debug code removed for production
                return false;
            }
            $value = @unserialize($uncompressed);
        }

        return $value;
    }

    /**
     * Delete a specific cache entry
     *
     * Removes a single cache entry by key. Useful for targeted cache
     * invalidation when specific data changes.
     *
     * @param string $key The cache key to delete
     * @return bool True if the key was deleted, false otherwise
     */
    public static function delete_transient($key)
    {
        $key = self::normalize_transient_key($key);
        return delete_transient($key);
    }

    /**
     * Get comprehensive cache statistics
     *
     * Analyzes cache usage and provides detailed statistics including:
     * - Total cache size and item count
     * - Breakdown by cache type (search, sidebar, terms)
     * - Cache hit rate performance
     * - Human-readable size formatting
     *
     * @return array Comprehensive cache statistics
     */
    public static function get_cache_stats(): array
    {
        global $wpdb;

        try {
            /**
             * Query database for all Havenlytics cache entries and their sizes
             */
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to report Havenlytics transient cache statistics.
            $cache_data = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, LENGTH(option_value) as size 
                     FROM {$wpdb->options} 
                     WHERE option_name LIKE %s
                       AND option_name NOT LIKE %s",
                    $wpdb->esc_like('_transient_hvnly_') . '%',
                    $wpdb->esc_like('_transient_timeout_') . '%'
                )
            );

            /**
             * Process the raw data into meaningful statistics
             */
            return self::calculate_cache_stats($cache_data);
        } catch (\Exception $e) {
            /**
             * Handle database errors gracefully
             * Return default statistics to prevent frontend errors
             */
            // Debug code removed for production
            return self::get_default_cache_stats();
        }
    }

    /**
     * Calculate statistics from raw cache data
     *
     * Processes database results into structured statistics with
     * categorization and performance metrics.
     *
     * @param array $cache_data Raw cache data from database
     * @return array Processed cache statistics
     */
    private static function calculate_cache_stats(array $cache_data): array
    {
        /**
         * Initialize statistics array with default values
         */
        $stats = [
            'total_cache_size'    => 0,
            'search_cache_count'  => 0,
            'sidebar_cache_count' => 0,
            'term_cache_count'    => 0,
            'total_cached_items'  => count($cache_data),
        ];

        /**
         * Process each cache entry to calculate totals and categories
         */
        foreach ($cache_data as $item) {
            $stats['total_cache_size'] += (int) $item->size;

            /**
             * Categorize cache entries by their purpose
             */
            if (strpos($item->option_name, 'search') !== false) {
                $stats['search_cache_count']++;
            } elseif (strpos($item->option_name, 'sidebar') !== false) {
                $stats['sidebar_cache_count']++;
            } elseif (strpos($item->option_name, 'terms') !== false) {
                $stats['term_cache_count']++;
            }
        }

        /**
         * Calculate derived statistics
         */
        $stats['cache_hit_rate']   = self::calculate_hit_rate();
        $stats['cache_size_human'] = self::format_bytes($stats['total_cache_size']);

        return $stats;
    }

    /**
     * Calculate cache hit rate percentage
     *
     * Determines the effectiveness of the cache system by comparing
     * cache hits to total requests. Uses stored option values for
     * persistent tracking across page loads.
     *
     * @return float Cache hit rate as a percentage (0.0 - 100.0)
     */
    private static function calculate_hit_rate(): float
    {
        $hits   = (int) get_option('hvnly_cache_hits', 0);
        $misses = (int) get_option('hvnly_cache_misses', 0);
        $total  = $hits + $misses;

        if ($total <= 0) {
            return 0.0;
        }

        return round(($hits / $total) * 100, 1);
    }

    /**
     * Format bytes into human readable string
     *
     * Converts byte counts into appropriate units (KB, MB, GB)
     * for display in admin interfaces and reports.
     *
     * @param int $bytes Number of bytes to format
     * @param int $decimals Number of decimal places to show
     * @return string Formatted byte string with unit
     */
    private static function format_bytes($bytes, $decimals = 2): string
    {
        if ($bytes === 0) return '0 Bytes';

        $k = 1024;
        $dm = $decimals < 0 ? 0 : $decimals;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));

        return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
    }

    /**
     * Get default cache statistics
     *
     * Provides fallback statistics when cache data cannot be retrieved
     * or when errors occur during statistics calculation.
     *
     * @return array Default cache statistics
     */
    private static function get_default_cache_stats(): array
    {
        return [
            'total_cache_size'    => 0,
            'search_cache_count'  => 0,
            'sidebar_cache_count' => 0,
            'term_cache_count'    => 0,
            'cache_hit_rate'      => 0.0,
            'total_cached_items'  => 0,
            'cache_size_human'    => '0 Bytes'
        ];
    }


    
}

/**
 * Initialize cache options when plugin is activated
 * This ensures cache statistics have proper baseline values
 */
register_activation_hook(__FILE__, [CacheManager::class, 'initialize_cache_options']);