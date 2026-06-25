<?php
/**
 * Shortcode Cache Invalidation
 *
 * Handles automatic cache clearing when properties or terms are updated.
 * Ensures fresh content is always displayed.
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
 * Cache Invalidation class
 *
 * @since 2.0.0
 */
class CacheInvalidation {

    /**
     * Constructor
     */
    public function __construct() {
        // Clear caches when properties are saved
        add_action('save_post_hvnly_property', [$this, 'on_property_save'], 10, 3);
        add_action('delete_post', [$this, 'on_property_delete']);
        
        // Clear caches when terms are updated
        add_action('created_term', [$this, 'on_term_update'], 10, 3);
        add_action('edited_term', [$this, 'on_term_update'], 10, 3);
        add_action('delete_term', [$this, 'on_term_update'], 10, 3);
        
        // Clear caches when plugin settings change
        add_action('update_option', [$this, 'on_option_update'], 10, 3);
        
        // Add manual cache clearing action
        add_action('hvnly_clear_shortcode_caches', [$this, 'clear_all_shortcode_caches']);
    }

    /**
     * Handle property save
     *
     * @param int     $post_id Post ID
     * @param \WP_Post $post    Post object
     * @param bool    $update  Whether this is an update
     */
    public function on_property_save($post_id, $post, $update) {
        // Prevent infinite loops
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Only clear caches for published properties
        if ($post->post_status !== 'publish') {
            return;
        }

        // Clear all shortcode caches
        $this->clear_all_shortcode_caches();
    }

    /**
     * Handle property delete
     *
     * @param int $post_id Post ID
     */
    public function on_property_delete($post_id) {
        if (get_post_type($post_id) !== 'hvnly_property') {
            return;
        }

        $this->clear_all_shortcode_caches();
    }

    /**
     * Handle term updates
     *
     * @param int    $term_id  Term ID
     * @param int    $tt_id    Term taxonomy ID
     * @param string $taxonomy Taxonomy name
     */
    public function on_term_update($term_id, $tt_id, $taxonomy) {
        // Only care about property taxonomies
        $property_taxonomies = [
            'hvnly_prop_types',
            'hvnly_prop_locations',
            'hvnly_prop_depts',
            'hvnly_prop_status',
            'hvnly_prop_features',
            'hvnly_prop_badges',
            'hvnly_prop_tags',
        ];

        if (in_array($taxonomy, $property_taxonomies, true)) {
            $this->clear_all_shortcode_caches();
        }
    }

    /**
     * Handle option updates
     *
     * @param string $option    Option name
     * @param mixed  $old_value Old value
     * @param mixed  $new_value New value
     */
    public function on_option_update($option, $old_value, $new_value) {
        // Options that should trigger cache clearing
        $relevant_options = [
            'posts_per_page',
            'hvnly_properties_per_page',
            'hvnly_default_orderby',
            'hvnly_currency_symbol',
            'hvnly_currency_position',
        ];

        if (in_array($option, $relevant_options, true)) {
            $this->clear_all_shortcode_caches();
        }
    }

    /**
     * Clear all shortcode caches
     */
    public function clear_all_shortcode_caches() {
        // Use the registry to clear all shortcode caches
        if (class_exists('HvnlyNab\Frontend\Shortcodes\Registry')) {
            Registry::clear_all_caches();
        } else {
            // Fallback: manual pattern clearing
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hvnly_hvnly_property_grid_%'"
            );
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hvnly_hvnly_property_list_%'"
            );
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hvnly_hvnly_featured_properties_%'"
            );
        }

        do_action('hvnly_shortcode_caches_cleared');
    }
}