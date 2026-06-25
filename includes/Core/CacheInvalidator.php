<?php

/**
 * Cache Invalidator - Handles cache invalidation for property updates
 *
 * Responsible for clearing relevant caches when property data changes.
 * Ensures users always see up-to-date property information by invalidating
 * cached search results, filters, and sidebar widgets when properties are updated.
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
 * Import required classes for cache invalidation
 * These handle specific cache clearing for different components
 */

use HvnlyNab\Frontend\ViewModels\SearchFilters;
use HvnlyNab\Frontend\ViewModels\SidebarSearchFilters;
use HvnlyNab\Frontend\Query\PropertyQueryCache;
use Throwable;

/**
 * Cache Invalidator Class
 *
 * Handles coordinated cache invalidation across all plugin components
 * when property data changes. This ensures data consistency and prevents
 * stale cache from being served to users.
 *
 * Key responsibilities:
 * - Clear search result caches when properties are updated
 * - Invalidate sidebar widget caches
 * - Clear transients with specific patterns
 * - Coordinate with main engine for complete cache clearance
 *
 * @since 2.0.0
 */
class CacheInvalidator
{
    /**
     * Invalidate all relevant caches for a property update
     *
     * Main method called when a property is created, updated, or deleted.
     * Coordinates cache clearing across all plugin components to ensure
     * data consistency and fresh content delivery.
     *
     * @param int           $post_id Post ID of the property post being updated.
     * @param \WP_Post|null $post    Post object (passed on delete hooks).
     * @return void
     */
    public static function invalidate($post_id, $post = null)
    {
        if (!\hvnly_is_cache_enabled()) {
            return;
        }

        if (!self::is_hvnly_property($post_id, $post)) {
            return;
        }

        self::clear_all_listing_caches();
    }

    /**
     * @param int           $post_id Post ID.
     * @param \WP_Post|null $post    Optional post object.
     * @return bool
     */
    private static function is_hvnly_property($post_id, $post = null)
    {
        if ($post instanceof \WP_Post) {
            return $post->post_type === 'hvnly_property';
        }

        return get_post_type($post_id) === 'hvnly_property';
    }

    /**
     * Clear search, sidebar, and related listing caches.
     *
     * @return void
     */
    private static function clear_all_listing_caches()
    {
        if (class_exists(SearchFilters::class)) {
            (new SearchFilters())->clear_all_search_cache();
        }

        if (class_exists(SidebarSearchFilters::class)) {
            (new SidebarSearchFilters())->clear_sidebar_cache();
        }

        try {
            CacheManager::clear_transients_by_pattern('search_');
            CacheManager::clear_transients_by_pattern('sidebar_');
            PropertyQueryCache::invalidate_all();

            if (function_exists('HVN') && HVNLY_NAB()->engine()) {
                HVNLY_NAB()->engine()->clear_all_cache();
            }
        } catch (Throwable $e) {
            // Silent fail in production
        }
    }

    /**
     * Invalidate when listing-related meta is deleted outside save_post.
     *
     * @param array  $meta_ids   Deleted meta row IDs.
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Meta key.
     * @param mixed  $meta_value Meta value.
     */
    public static function invalidate_on_meta_delete($meta_ids, $post_id, $meta_key, $meta_value)
    {
        unset($meta_ids, $meta_value);

        if (!\hvnly_is_cache_enabled()) {
            return;
        }

        if (!self::is_listing_meta_key($meta_key) || !self::is_hvnly_property($post_id)) {
            return;
        }

        self::clear_all_listing_caches();
    }

    /**
     * Invalidate when property taxonomies are assigned without save_post.
     *
     * @param int    $object_id  Post ID.
     * @param array  $terms      Term IDs.
     * @param array  $tt_ids     Term taxonomy IDs.
     * @param string $taxonomy   Taxonomy slug.
     * @param bool   $append     Whether terms were appended.
     * @param array  $old_tt_ids Previous term taxonomy IDs.
     */
    public static function invalidate_on_term_assignment($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)
    {
        unset($terms, $tt_ids, $append, $old_tt_ids);

        if (!\hvnly_is_cache_enabled()) {
            return;
        }

        if (!self::is_property_listing_taxonomy($taxonomy) || !self::is_hvnly_property($object_id)) {
            return;
        }

        self::clear_all_listing_caches();
    }

    /**
     * @param string $meta_key Meta key.
     * @return bool
     */
    private static function is_listing_meta_key($meta_key)
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
    private static function is_property_listing_taxonomy($taxonomy)
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
}

add_action('before_delete_post', array(CacheInvalidator::class, 'invalidate'), 10, 2);
add_action('deleted_post_meta', array(CacheInvalidator::class, 'invalidate_on_meta_delete'), 10, 4);
add_action('set_object_terms', array(CacheInvalidator::class, 'invalidate_on_term_assignment'), 10, 6);
