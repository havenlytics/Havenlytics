<?php
/**
 * Shortcode Assets Handler
 *
 * Conditionally enqueues CSS/JS only when shortcodes are present on the page.
 * Prevents loading assets on pages that don't use Havenlytics shortcodes.
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
 * Shortcode Assets class
 *
 * @since 2.0.0
 */
class Assets {

    /**
     * Shortcode tags to check for
     *
     * @var array
     */
    private $shortcode_tags = [
        'hvnly_property_search',
        'hvnly_property_grid',
        'hvnly_property_list',
        'hvnly_property_agents',
        'hvnly_property_agencies',
        'hvnly_featured_properties',
        'hvnly_properties', // Legacy
        'hvnly_property_lists', // Legacy
        'hvnly_property_search_form', // Legacy
    ];

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp', [$this, 'check_shortcode_presence']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_shortcode_assets'], 20);
        
        // Body class filter - now uses AbstractShortcode's registry
        add_filter('body_class', [$this, 'add_shortcode_body_classes'], 20);
    }

    /**
     * Check if current page has any Havenlytics shortcodes
     * Stores result in a global flag for later use
     */
    public function check_shortcode_presence() {
        global $post, $hvnly_has_shortcode;
        
        $hvnly_has_shortcode = false;
        
        if (is_a($post, 'WP_Post') && !empty($post->post_content)) {
            foreach ($this->shortcode_tags as $tag) {
                if (has_shortcode($post->post_content, $tag)) {
                    $hvnly_has_shortcode = true;
                    break;
                }
            }
        }
    }

    /**
     * Add shortcode-specific classes to body
     * Uses AbstractShortcode's registry for already-rendered shortcodes
     *
     * @param array $classes Body classes
     * @return array
     */
    public function add_shortcode_body_classes($classes) {
        // Add classes for shortcodes that have been rendered
        $active_shortcodes = AbstractShortcode::get_active_shortcodes();
        foreach ($active_shortcodes as $tag) {
            $class_name = str_replace('_', '-', $tag);
            $classes[] = 'hvnly-' . $class_name . '-shortcode';
        }
        
        // Also add a general class if any shortcode is present
        global $hvnly_has_shortcode;
        if ($hvnly_has_shortcode) {
            $classes[] = 'hvnly-shortcode-page';
        }
        
        return $classes;
    }

    /**
     * Enqueue shortcode-specific assets only when needed
     */
    public function enqueue_shortcode_assets() {
        global $hvnly_has_shortcode;
        
        // Only enqueue if shortcodes are present on the page
        if (!$hvnly_has_shortcode) {
            return;
        }

        $version = defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '2.2.0';
        
        // Enqueue main shortcode CSS
        wp_enqueue_style(
            'hvnly-shortcodes',
            HVNLYNAB_ASSETS_URL . '/frontend/css/shortcodes/hvnly-shortcodes.css',
            [], // No dependencies
            $version
        );

        /**
         * Action: hvnly_after_shortcode_assets_enqueued
         * 
         * Allows additional assets to be enqueued after shortcode CSS.
         */
        do_action('hvnly_after_shortcode_assets_enqueued');
    }

    /**
     * Check if a specific shortcode exists on the page
     *
     * @param string $tag Shortcode tag to check
     * @return bool
     */
    public static function has_shortcode($tag) {
        global $post;
        
        if (!is_a($post, 'WP_Post') || empty($post->post_content)) {
            return false;
        }
        
        return has_shortcode($post->post_content, $tag);
    }

    /**
     * Get all shortcode tags registered by the plugin
     *
     * @return array
     */
    public static function get_shortcode_tags() {
        $instance = new self();
        return $instance->shortcode_tags;
    }
}