<?php

/**
 * Base Abstract Class for Registering Custom Post Types
 *
 * This abstract class provides a reusable foundation for creating 
 * custom post types in WordPress. It includes:
 * - Default label generation
 * - Default settings (archive, menu, supports, etc.)
 * - Registration of an additional custom post status ("expired")
 *
 * Extend this class and implement `register_custom_post_type()` 
 * in child classes for specific post types.
 *
 * @package HvnlyNab\Database\Base
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Base;

if (! defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Abstract Class Custom_Posts
 */
abstract class Custom_Posts
{

    /**
     * @var string $name Internal post type name/slug.
     */
    protected $name = '';

    /**
     * @var string $menu Menu label shown in the WordPress dashboard.
     */
    protected $menu = '';

    /**
     * @var bool $public_query Whether the post type should be publicly queryable.
     */
    protected $public_query = true;

    /**
     * Constructor
     *
     * Automatically hooks into WordPress to register the custom post type
     * when WordPress initializes.
     */
    public function __construct()
    {
        add_action('init', [$this, 'register_custom_post_type'], 0);
    }

    /**
     * Abstract method that child classes must implement.
     *
     * Each child class should define its own post type registration logic here.
     *
     * @return void
     */
    abstract public function register_custom_post_type();

    /**
     * Initialize and register a custom post type with sensible defaults.
     *
     * @param string $type           The post type slug (e.g., "havenlytics").
     * @param string $singular_label Singular display name (e.g., "Havenlytics").
     * @param string $plural_label   Plural display name (e.g., "Havenlytics").
     * @param array  $settings       Optional. Extra arguments to override defaults.
     * @param array  $labels         Optional. Extra labels to override defaults.
     *
     * @return void
     */
    public function init($type, $singular_label, $plural_label, $settings = [], $labels = [])
    {

        // Default labels for the post type
        $default_labels = [
            'name'          => esc_html($plural_label),
            'singular_name' => esc_html($singular_label),

            /* translators: %s: singular post type label */
            'add_new'       => sprintf(__('Add New %s', 'havenlytics'), esc_html($singular_label)),

            /* translators: %s: singular post type label */
            'add_new_item'  => sprintf(__('Add New %s', 'havenlytics'), esc_html($singular_label)),

            /* translators: %s: singular post type label */
            'edit_item'     => sprintf(__('Edit %s', 'havenlytics'), esc_html($singular_label)),

            /* translators: %s: singular post type label */
            'new_item'      => sprintf(__('New %s', 'havenlytics'), esc_html($singular_label)),

            /* translators: %s: singular post type label */
            'view_item'     => sprintf(__('View %s', 'havenlytics'), esc_html($singular_label)),

            /* translators: %s: plural post type label */
            'search_items'  => sprintf(__('Search %s', 'havenlytics'), esc_html($plural_label)),

            /* translators: %s: plural post type label */
            'not_found'     => sprintf(__('No %s found', 'havenlytics'), esc_html($plural_label)),

            /* translators: %s: plural post type label */
            'not_found_in_trash' => sprintf(__('No %s found in Trash', 'havenlytics'), esc_html($plural_label)),

            /* translators: %s: singular post type label */
            'parent_item_colon'  => sprintf(__('Parent %s:', 'havenlytics'), esc_html($singular_label)),

            'menu_name' => esc_html($plural_label),
        ];

        // Default settings for the post type
        $default_settings = [
            'labels'        => array_merge($default_labels, $labels),
            'public'        => true,
            'has_archive'   => true,
            'menu_icon'     => '', // Customize icon if needed
            'menu_position' => 2,
            'supports'      => ['title', 'editor', 'thumbnail'],
            'rewrite'       => [
                // Example: Generate slug from label → use text_to_slug helper if available
                // 'slug'       => HVNLY_NAB()->Helper->text_to_slug( sanitize_text_field( $plural_label ) ),
                'with_front' => false,
            ],
        ];

        // Register the custom post type
        register_post_type($type, array_merge($default_settings, $settings));

        // Register custom post status "Expired"
        register_post_status('expired', [
            'label'                     => _x('Expired', 'post status', 'havenlytics'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            /* translators: %s: number of expired posts */
            'label_count' => _n_noop('Expired (%s)', 'Expired (%s)', 'havenlytics'),
        ]);
    }

    /**
     * Check if Gutenberg editor should be enabled for a post type
     *
     * Retrieves the Gutenberg editor setting from the plugin settings.
     * The setting key is 'hvnly_EnabledGutenbergEditor' in the 'general' group.
     *
     * @return bool True if Gutenberg editor should be enabled, false otherwise.
     * @since 2.0.0
     */
    protected function is_gutenberg_enabled()
    {
        // Try to get settings from SettingsManager if available.
        if (class_exists('\HvnlyNab\Core\SettingsManager')) {
            $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
            $general_settings = $settings_manager->get_general_settings();
            
            // Check if Gutenberg editor is enabled in settings.
            if (isset($general_settings['hvnly_EnabledGutenbergEditor'])) {
                return ! empty( $general_settings['hvnly_EnabledGutenbergEditor'] );
            }
        }

        // Fallback: Check option directly.
        $settings = get_option('hvnly_plugin_settings', array());
        
        if (isset($settings['general']['hvnly_EnabledGutenbergEditor'])) {
            return ! empty( $settings['general']['hvnly_EnabledGutenbergEditor'] );
        }

        // Default to false (classic editor) for backward compatibility.
        return false;
    }

    /**
     * Disable or enable Gutenberg editor for a specific post type
     *
     * This method can be used as a filter callback for 'use_block_editor_for_post_type'.
     *
     * @param bool   $use_block_editor Whether to use the block editor.
     * @param string $post_type        The post type being checked.
     * @return bool Modified value based on plugin settings.
     * @since 2.0.0
     */
    public function filter_gutenberg_support( $use_block_editor, $post_type ) {
        // Only apply to our property post type.
        if ( 'hvnly_property' === $post_type ) {
            return $this->is_gutenberg_enabled();
        }
        return $use_block_editor;
    }
}