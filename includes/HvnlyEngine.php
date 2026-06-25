<?php

/**
 * HvnlyEngine - Core caching and performance engine
 *
 * Main engine class that handles all caching operations, performance tracking,
 * and optimization for the Havenlytics plugin. Implements singleton pattern
 * for global access and provides comprehensive cache management with
 * real-time statistics and performance monitoring.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}


class HvnlyEngine
{
    /**
     * Singleton instance for global access
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Engine configuration settings
     *
     * @var array
     */
    private $config = [];

    /**
     * Cached statistics to avoid repeated calculations
     *
     * @var array
     */
    private $cache_stats = [];

    /**
     * Minimum size to apply compression (1KB)
     */
    const COMPRESSION_THRESHOLD = 1024;

    /**
     * Get singleton instance of the engine
     *
     * Implements singleton pattern to ensure only one instance exists
     * throughout the application. Provides global access to engine
     * functionality while maintaining proper encapsulation.
     *
     * @return self Singleton instance of Havenlytics_Engine
     * @since 2.0.0
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton pattern
     *
     * Initializes the engine by loading configuration, setting up hooks,
     * and preparing performance tracking. Prevents direct instantiation.
     *
     * @since 2.0.0
     */
    private function __construct()
    {
        $this->load_config();
        $this->setup_hooks();
        $this->initialize_stats();
    }

    /**
     * Load engine configuration from WordPress options
     *
     * Retrieves all configuration settings from WordPress options with
     * sensible defaults. Handles cache TTL, compression, and performance
     * tracking settings.
     *
     * @return void
     * @since 2.0.0
     */
    private function load_config()
    {
        $this->config = [
            'cache_ttl'          => (int) get_option('hvnly_cache_ttl', HOUR_IN_SECONDS * 6),
            'cache_compression'  => (bool) get_option('hvnly_cache_compression', true), // Default to true now
            'enable_performance' => (bool) get_option('hvnly_cache_debug', false),
            'cache_enabled'      => (bool) get_option('hvnly_cache_enabled', true),
            'cache_prefix'       => 'hvnly_',
            'auto_compress'      => true, // Always auto-compress large items
        ];
    }

    /**
     * Get current engine configuration
     *
     * Provides read-only access to the engine configuration array.
     * Used by other components to check current settings without
     * direct access to private properties.
     *
     * @return array Current engine configuration
     * @since 2.0.0
     */
    public function get_config()
    {
        return $this->config;
    }

    /**
     * Set up WordPress hook integration
     *
     * Registers engine methods with WordPress hooks for scheduled
     * events and automated maintenance tasks.
     *
     * @return void
     * @since 2.0.0
     */
    private function setup_hooks()
    {
        add_action('hvnly_daily_cache_cleanup', [$this, 'cleanup_expired_cache']);
        add_action('hvnly_weekly_optimization', [$this, 'run_optimization']);
        
        // Add action for when settings are updated
        add_action('update_option_hvnly_cache_ttl', [$this, 'on_settings_update'], 10, 2);
        add_action('update_option_hvnly_cache_compression', [$this, 'on_settings_update'], 10, 2);
    }

    /**
     * Handle settings updates
     *
     * @param mixed $old_value Old setting value
     * @param mixed $new_value New setting value
     * @return void
     */
    public function on_settings_update($old_value, $new_value)
    {
        $this->load_config();
    }

    /**
     * Initialize performance tracking statistics
     *
     * Sets up default performance tracking options if they don't exist.
     * Ensures consistent baseline metrics for cache hit rate calculations
     * and performance monitoring.
     *
     * @return void
     * @since 2.0.0
     */
    private function initialize_stats()
    {
        if (false === get_option('hvnly_cache_hits', false)) {
            add_option('hvnly_cache_hits', 0, '', 'no');
            add_option('hvnly_cache_misses', 0, '', 'no');
            add_option('hvnly_queries_executed', 0, '', 'no');
            add_option('hvnly_cache_requests', 0, '', 'no');
            add_option('hvnly_compression_savings', 0, '', 'no'); // Track compression savings
        }
    }

    /**
     * Store value in cache with automatic compression for large items
     *
     * @param string $key   The cache key
     * @param mixed  $value The value to cache
     * @param int    $ttl   Time to live in seconds (null = use setting)
     * @return bool True if value was set, false otherwise
     */
    public function set_cache($key, $value, $ttl = null)
    {
        // Check if caching is enabled
        if (!$this->config['cache_enabled']) {
            return false;
        }

        // Sanitize the cache key
        $cache_key = $this->config['cache_prefix'] . sanitize_key($key);

        // Use default TTL from config if not specified
        if (null === $ttl) {
            $ttl = $this->config['cache_ttl'];
        }

        // Ensure TTL is positive
        $ttl = max(1, absint($ttl));

        // Always check size and compress if beneficial
        $value = $this->maybe_auto_compress($value, $cache_key);

        // Use WordPress transient API
        $result = set_transient($cache_key, $value, $ttl);

        // Track cache operation
        if ($result) {
            $this->increment_cache_requests();
        }

        return $result;
    }

    /**
     * Retrieve value from cache with auto-decompression
     *
     * @param string $key The cache key
     * @return mixed The cached value, or false if not found
     */
    public function get_cache($key)
    {
        $cache_key = $this->config['cache_prefix'] . sanitize_key($key);
        $value = get_transient($cache_key);

        // Track hit/miss
        if (false !== $value) {
            $this->track_cache_hit();
            
            // Auto-decompress if needed
            $value = $this->maybe_auto_decompress($value);
        } else {
            $this->track_cache_miss();
        }

        return $value;
    }

    /**
     * Get comprehensive cache statistics with compression savings
     *
     * Analyzes the current cache state and returns detailed statistics
     * including cache size, item counts by type, hit rates, compression savings,
     * and human-readable size formatting.
     *
     * @return array
     * @since 2.0.0
     */
    public function get_cache_stats()
    {
        global $wpdb;

        // Return cached stats if already calculated in this request
        if (!empty($this->cache_stats)) {
            return $this->cache_stats;
        }

        try {
            // Query database for all Havenlytics cache entries and their sizes
            $cache_data = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, LENGTH(option_value) as size, option_value
                     FROM {$wpdb->options}
                     WHERE option_name LIKE %s",
                    $wpdb->esc_like('_transient_' . $this->config['cache_prefix']) . '%'
                )
            );

            $total_size      = 0;
            $compressed_size = 0;
            $search_count    = 0;
            $sidebar_count   = 0;
            $term_count      = 0;
            $compressed_count = 0;
            $total_items     = 0;

            foreach ($cache_data as $item) {
                $item_size = (int) $item->size;
                $total_size += $item_size;
                $total_items++;

                // Check if this is compressed data
                if ($this->is_compressed_data($item->option_value)) {
                    $compressed_count++;
                    $uncompressed = @gzuncompress($item->option_value);
                    if (false !== $uncompressed) {
                        $compressed_size += strlen($uncompressed);
                    }
                }

                if (strpos($item->option_name, 'search') !== false) {
                    $search_count++;
                } elseif (strpos($item->option_name, 'sidebar') !== false) {
                    $sidebar_count++;
                } elseif (strpos($item->option_name, 'terms') !== false) {
                    $term_count++;
                }
            }

            $hit_rate = $this->calculate_hit_rate();
            
            // Calculate compression savings
            $savings = $compressed_size > 0 ? $compressed_size - $total_size : 0;
            $savings_percent = $compressed_size > 0 ? round(($savings / $compressed_size) * 100, 1) : 0;

            $this->cache_stats = [
                'total_cache_size'     => $total_size,
                'search_cache_count'   => $search_count,
                'sidebar_cache_count'  => $sidebar_count,
                'term_cache_count'     => $term_count,
                'cache_hit_rate'       => $hit_rate,
                'total_cached_items'   => $total_items,
                'cache_size_human'     => $this->format_bytes($total_size),
                'compressed_count'     => $compressed_count,
                'compression_ratio'    => $savings_percent . '%',
                'compression_savings'  => $this->format_bytes($savings),
                'avg_item_size'        => $total_items > 0 ? $this->format_bytes($total_size / $total_items) : '0 Bytes',
            ];
            
            // Update compression savings statistic
            update_option('hvnly_compression_savings', $savings);
            
        } catch (Exception $e) {
            // Error logging removed for production
            $this->cache_stats = $this->get_default_stats();
        }

        return $this->cache_stats;
    }

    /**
     * Check if data is compressed
     *
     * @param string $data Data to check
     * @return bool True if data appears compressed
     */
    private function is_compressed_data($data)
    {
        if (!is_string($data) || strlen($data) < 10) {
            return false;
        }
        
        // Check for gzip magic numbers
        $first_two = substr($data, 0, 2);
        return in_array($first_two, ["\x1f\x8b", "\x78\x9c"]);
    }

    /**
     * Auto-compress value if it's large enough to benefit
     *
     * @param mixed  $value     Value to potentially compress
     * @param string $cache_key Cache key for logging
     * @return mixed Compressed or original value
     */
    private function maybe_auto_compress($value, $cache_key)
    {
        // Check if compression functions exist
        if (!function_exists('gzcompress')) {
            return $value;
        }

        // Serialize first to check size
        $serialized = maybe_serialize($value);
        $size = strlen($serialized);

        // Always compress if item is large (over 1KB) regardless of setting
        if ($size > self::COMPRESSION_THRESHOLD) {
            $compressed = gzcompress($serialized, 9);
            if (false !== $compressed) {
                // Debug logging removed for production
                return $compressed;
            }
        }
        // If compression setting is enabled but item is small, still compress if user wants
        elseif ($this->config['cache_compression']) {
            $compressed = gzcompress($serialized, 6); // Lower compression level for small items
            if (false !== $compressed) {
                return $compressed;
            }
        }

        return $value;
    }

    /**
     * Auto-decompress value if it was compressed
     *
     * @param mixed $value Value to potentially decompress
     * @return mixed Decompressed or original value
     */
    private function maybe_auto_decompress($value)
    {
        if (!function_exists('gzuncompress') || !is_string($value)) {
            return $value;
        }

        // Check if this looks like compressed data
        if ($this->is_compressed_data($value)) {
            $uncompressed = @gzuncompress($value);
            if (false !== $uncompressed) {
                $unserialized = maybe_unserialize($uncompressed);
                if (false !== $unserialized) {
                    return $unserialized;
                }
            }
            
            // Decompression failure logging removed for production
        }

        return $value;
    }

    /**
     * Calculate current cache hit rate percentage
     *
     * @return float Cache hit rate as percentage (0.0 - 100.0)
     * @since 2.0.0
     */
    private function calculate_hit_rate()
    {
        $hits   = (int) get_option('hvnly_cache_hits', 0);
        $misses = (int) get_option('hvnly_cache_misses', 0);

        $total_requests = $hits + $misses;

        if ($total_requests > 0) {
            $hit_rate = ($hits / $total_requests) * 100;
            return round($hit_rate, 1);
        }

        return 0.0;
    }

    /**
     * Track a successful cache hit
     *
     * @return void
     * @since 2.0.0
     */
    public function track_cache_hit()
    {
        $hits = (int) get_option('hvnly_cache_hits', 0);
        update_option('hvnly_cache_hits', $hits + 1);
        $this->increment_cache_requests();
    }

    /**
     * Track a cache miss
     *
     * @return void
     * @since 2.0.0
     */
    public function track_cache_miss()
    {
        $misses = (int) get_option('hvnly_cache_misses', 0);
        update_option('hvnly_cache_misses', $misses + 1);
        $this->increment_cache_requests();
    }

    /**
     * Increment total cache requests
     *
     * @return void
     */
    private function increment_cache_requests()
    {
        $requests = (int) get_option('hvnly_cache_requests', 0);
        update_option('hvnly_cache_requests', $requests + 1);
    }

    /**
     * Track a database query execution
     *
     * @return void
     * @since 2.0.0
     */
    public function track_query_executed()
    {
        $queries = (int) get_option('hvnly_queries_executed', 0);
        update_option('hvnly_queries_executed', $queries + 1);
    }

    /**
     * Format bytes into human readable string
     *
     * @param int $bytes
     * @param int $decimals
     * @return string
     * @since 2.0.0
     */
    private function format_bytes($bytes, $decimals = 2)
    {
        if ($bytes === 0) {
            return '0 Bytes';
        }

        $k      = 1024;
        $dm     = $decimals < 0 ? 0 : $decimals;
        $sizes  = ['Bytes', 'KB', 'MB', 'GB'];
        $i      = floor(log($bytes) / log($k));

        return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
    }

    /**
     * Get comprehensive performance metrics
     *
     * @return array
     * @since 2.0.0
     */
    public function get_performance_metrics()
    {
        $hits             = (int) get_option('hvnly_cache_hits', 0);
        $misses           = (int) get_option('hvnly_cache_misses', 0);
        $queries_executed = (int) get_option('hvnly_queries_executed', 0);
        $requests         = (int) get_option('hvnly_cache_requests', 0);
        $compression_savings = (int) get_option('hvnly_compression_savings', 0);

        return [
            'cache_hits'          => $hits,
            'cache_misses'        => $misses,
            'queries_executed'    => $queries_executed,
            'average_query_time'  => 0.15, // Placeholder
            'cache_efficiency'    => $this->calculate_hit_rate(),
            'memory_usage'        => memory_get_usage(true),
            'total_queries_saved' => $requests > 0 ? $requests - $queries_executed : 0,
            'cache_hit_rate'      => $this->calculate_hit_rate(),
            'compression_savings' => $this->format_bytes($compression_savings),
        ];
    }

    /**
     * Clear all plugin cache entries
     *
     * Removes all Havenlytics-related cache entries using the Transients API
     * so both database and any persistent object cache stay in sync.
     *
     * @return int Number of cache entries cleared
     * @since 2.0.0
     */
    public function clear_all_cache()
    {
        global $wpdb;

        // Find all transient value rows for our prefix
        $like = $wpdb->esc_like('_transient_' . $this->config['cache_prefix']) . '%';

        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name
                 FROM {$wpdb->options}
                 WHERE option_name LIKE %s",
                $like
            )
        );

        $deleted_count = 0;

        if (!empty($option_names)) {
            foreach ($option_names as $option_name) {
                // "_transient_hvnly_something" → "hvnly_something"
                $transient_name = str_replace('_transient_', '', $option_name);

                if (delete_transient($transient_name)) {
                    $deleted_count++;
                }
            }
        }

        // Clear cached statistics to force recalculation
        $this->cache_stats = [];

        // Debug logging removed for production

        return $deleted_count;
    }

    /**
     * Clear cache entries matching a specific pattern
     *
     * @param string $pattern The pattern to match in cache keys
     * @return int Number of cache entries cleared
     * @since 2.0.0
     */
    public function clear_transients_by_pattern($pattern)
    {
        global $wpdb;

        // Sanitize pattern
        $pattern = sanitize_key($pattern);

        // e.g. "_transient_hvnly_search_%" or "_transient_hvnly_sidebar_%"
        $like = $wpdb->esc_like('_transient_' . $this->config['cache_prefix'] . $pattern) . '%';

        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name
                 FROM {$wpdb->options}
                 WHERE option_name LIKE %s",
                $like
            )
        );

        $deleted_count = 0;

        if (!empty($option_names)) {
            foreach ($option_names as $option_name) {
                $transient_name = str_replace('_transient_', '', $option_name);

                if (delete_transient($transient_name)) {
                    $deleted_count++;
                }
            }
        }

        // Force stats recalculation
        $this->cache_stats = [];

        // Debug logging removed for production

        return $deleted_count;
    }

    /**
     * Update engine configuration
     *
     * @param array $new_config
     * @return void
     * @since 2.0.0
     */
    public function update_config($new_config)
    {
        foreach ($new_config as $key => $value) {
            $this->config[$key] = $value;
            update_option('hvnly_' . $key, $value);
        }
    }

    /**
     * Cleanup expired cache entries
     *
     * Scheduled maintenance task that removes cache entries whose TTL has expired.
     * Uses direct database deletion for efficiency with large numbers of expired entries.
     *
     * @return int Number of expired entries cleared
     * @since 2.0.0
     */
    public function cleanup_expired_cache()
    {
        global $wpdb;

        $time = time();

        // Delete expired timeouts and their corresponding transients in one query
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE a, b 
                FROM {$wpdb->options} a, {$wpdb->options} b
                WHERE a.option_name LIKE %s
                AND a.option_name = REPLACE(b.option_name, '_transient_timeout_', '_transient_')
                AND a.option_value < %d",
                $wpdb->esc_like('_transient_timeout_' . $this->config['cache_prefix']) . '%',
                $time
            )
        );

        $deleted_count = (int) $result;

        // Debug logging removed for production

        return $deleted_count;
    }

    /**
     * Run weekly optimization tasks
     *
     * @return void
     * @since 2.0.0
     */
    public function run_optimization()
    {
        global $wpdb;

        $wpdb->query("OPTIMIZE TABLE {$wpdb->options}");

        // Debug logging removed for production
    }

    /**
     * Get default statistics for error conditions
     *
     * @return array Default cache statistics
     * @since 2.0.0
     */
    private function get_default_stats()
    {
        return [
            'total_cache_size'     => 0,
            'search_cache_count'   => 0,
            'sidebar_cache_count'  => 0,
            'term_cache_count'     => 0,
            'cache_hit_rate'       => 0,
            'total_cached_items'   => 0,
            'cache_size_human'     => '0 Bytes',
            'compressed_count'     => 0,
            'compression_ratio'    => '0%',
            'compression_savings'  => '0 Bytes',
            'avg_item_size'        => '0 Bytes',
        ];
    }

    /**
     * Prevent cloning of singleton instance
     *
     * @return void
     * @since 2.0.0
     */
    private function __clone() {}

    /**
     * Prevent unserializing of singleton instance
     *
     * @return void
     * @since 2.0.0
     */
    public function __wakeup() {}
}