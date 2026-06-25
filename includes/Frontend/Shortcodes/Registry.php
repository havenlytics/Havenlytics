<?php
/**
 * Shortcode Registry
 *
 * Manages registration and retrieval of all Havenlytics shortcodes.
 * Provides centralized shortcode management and cache clearing.
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Shortcodes
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend\Shortcodes;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode Registry class
 *
 * @since 2.0.0
 */
class Registry {

    /**
     * Registered shortcode instances
     *
     * @var array
     */
    private static $instances = [];

    /**
     * Registered shortcode tags
     *
     * @var array
     */
    private static $tags = [];

    /**
     * Initialize the registry and register all shortcodes
     */
    public static function init() {
        $shortcodes = self::get_shortcode_classes();

        foreach ($shortcodes as $tag => $class) {
            self::register($tag, $class);
        }
    }

    /**
     * Get all shortcode classes
     *
     * @return array
     */
    private static function get_shortcode_classes() {
        return [
            'hvnly_property_search'     => PropertySearch::class,
            'hvnly_property_grid'      => PropertyGrid::class,
            'hvnly_property_list'       => PropertyList::class,
            'hvnly_property_agents'     => PropertyAgents::class,
            'hvnly_property_agencies'   => PropertyAgencies::class,
            'hvnly_featured_properties' => FeaturedProperties::class,
 
            // Legacy aliases are handled by LegacyCompatibility class
        ];
    }

    /**
     * Register a shortcode
     *
     * @param string $tag   Shortcode tag
     * @param string $class Shortcode class name
     * @return bool
     */
    public static function register($tag, $class) {
        if (!class_exists($class)) {
            // if (defined('WP_DEBUG') && WP_DEBUG) {
            //     error_log(sprintf('[Havenlytics] Shortcode class not found: %s', $class));
            // }
            return false;
        }

        try {
            $instance = new $class();
            
            if (!$instance instanceof AbstractShortcode) {
                // if (defined('WP_DEBUG') && WP_DEBUG) {
                //     error_log(sprintf('[Havenlytics] Shortcode class must extend AbstractShortcode: %s', $class));
                // }
                return false;
            }

            self::$instances[$tag] = $instance;
            self::$tags[] = $tag;
            
            return true;
        } catch (\Throwable $e) {
            // if (defined('WP_DEBUG') && WP_DEBUG) {
            //     error_log(sprintf('[Havenlytics] Failed to register shortcode %s: %s', $tag, $e->getMessage()));
            // }
            return false;
        }
    }

    /**
     * Get a registered shortcode instance
     *
     * @param string $tag Shortcode tag
     * @return AbstractShortcode|null
     */
    public static function get($tag) {
        return isset(self::$instances[$tag]) ? self::$instances[$tag] : null;
    }

    /**
     * Get all registered shortcode tags
     *
     * @return array
     */
    public static function get_tags() {
        return self::$tags;
    }

    /**
     * Check if a shortcode is registered
     *
     * @param string $tag Shortcode tag
     * @return bool
     */
    public static function exists($tag) {
        return isset(self::$instances[$tag]);
    }

    /**
     * Clear all shortcode caches
     */
    public static function clear_all_caches() {
        global $wpdb;
        
        // Clear all shortcode transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hvnly_hvnly_property_%'"
        );
        
        // Also clear timeout transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_hvnly_hvnly_property_%'"
        );

        do_action('hvnly_shortcode_caches_cleared');
    }

    /**
     * Clear cache for a specific shortcode
     *
     * @param string $tag Shortcode tag
     */
    public static function clear_cache($tag) {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_hvnly_' . $tag . '_%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_hvnly_' . $tag . '_%'
            )
        );
    }
}