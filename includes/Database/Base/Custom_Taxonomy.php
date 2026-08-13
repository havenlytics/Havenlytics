<?php

/**
 * Base Abstract Class for Registering Custom Taxonomies
 *
 * This abstract class provides a reusable structure to register custom taxonomies
 * in WordPress. It defines sensible defaults for taxonomy labels and settings
 * which can be overridden in child classes.
 *
 * @package HvnlyNab\Database\Base
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Base;

// Security check to prevent direct access
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Abstract Class Custom_Taxonomy
 *
 * Extend this class to create and register new custom taxonomies.
 */
abstract class Custom_Taxonomy {


    /**
     * @var string $post_types The default post type(s) this taxonomy applies to.
     *                         Can be a string (single post type) or array of post types.
     */
    protected $post_types = 'hvnly_property';

    /**
     * Constructor
     *
     * Hooks into WordPress `init` to register the custom taxonomy.
     */
    public function __construct() {
        // Register taxonomy during WordPress initialization
        add_action('init', array( $this, 'register_custom_taxonomy' ), 1);
    }

    /**
     * Abstract method that must be implemented in child classes.
     *
     * Each child class should define its own taxonomy registration logic here.
     *
     * @return void
     */
    abstract public function register_custom_taxonomy();

    /**
     * Initialize and register a custom taxonomy with default labels and settings.
     *
     * @param string       $type           Taxonomy slug (e.g., "property_type").
     * @param string       $singular_label Singular display name (e.g., "Property Type").
     * @param string       $plural_label   Plural display name (e.g., "Property Types").
     * @param string|array $post_types     Post type(s) the taxonomy should be applied to.
     * @param array        $settings       Optional. Custom settings to override defaults.
     * @param array        $labels         Optional. Custom labels to override defaults.
     *
     * @return void
     */
    public function init( $type, $singular_label, $plural_label, $post_types, $settings = array(), $labels = array() ) {

        // Default taxonomy labels
        $default_labels = array(
            'name'                  => esc_html($plural_label),
            'singular_name'         => esc_html($singular_label),
            /* translators: %s: Add New post type label */
            'add_new_item'          => sprintf(__('Add New %s', 'havenlytics'), esc_html($singular_label)),
            /* translators: %s: New post type label */
            'new_item_name'         => sprintf(__('New %s Name', 'havenlytics'), esc_html($singular_label)),
            /* translators: %s: Edit post type label */
            'edit_item'             => sprintf(__('Edit %s', 'havenlytics'), esc_html($singular_label)),
            /* translators: %s: Update post type label */
            'update_item'           => sprintf(__('Update %s', 'havenlytics'), esc_html($singular_label)),
            /* translators: %s: Add post type label */
            'add_or_remove_items'   => sprintf(__('Add or remove %s', 'havenlytics'), esc_html(strtolower($plural_label))),
            /* translators: %s: Search post type label */
            'search_items'          => sprintf(__('Search %s', 'havenlytics'), esc_html($plural_label)),
            /* translators: %s: No post type label */
            'not_found'             => sprintf(__('No %s found', 'havenlytics'), esc_html($singular_label)),
            /* translators: %s: Popular post type label */
            'popular_items'         => sprintf(__('Popular %s', 'havenlytics'), esc_html($plural_label)),
            /* translators: %s: All post type label */
            'all_items'             => sprintf(__('All %s', 'havenlytics'), esc_html($plural_label)),
            /* translators: %s: Parent post type label */
            'parent_item'           => sprintf(__('Parent %s', 'havenlytics'), esc_html($singular_label)),
            /* translators: %s: Choose post type label */
            'choose_from_most_used' => sprintf(__('Choose from the most used %s', 'havenlytics'), esc_html(strtolower($plural_label))),
            /* translators: %s: Parent post type label */
            'parent_item_colon'     => sprintf(__('Parent %s:', 'havenlytics'), esc_html($singular_label)),
            'menu_name'             => esc_html($plural_label),
        );

        // Default taxonomy settings
        $default_settings = array(
            'labels'            => array_merge($default_labels, $labels),
            'public'            => true,
            'show_in_nav_menus' => true,
            'show_admin_column' => false,
            'hierarchical'      => true,
            'show_tagcloud'     => false,
            'show_ui'           => true,
            'rewrite'           => array(
                'slug'       => sanitize_title($plural_label),
                'with_front' => false,
            ),
            'capabilities'      => array(
                'manage_terms' => 'manage_categories',
                'edit_terms'   => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'read',
            ),
        );

        // Register taxonomy with WordPress
        register_taxonomy($type, $post_types, array_merge($default_settings, $settings));
    }
}
