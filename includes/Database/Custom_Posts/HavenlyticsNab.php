<?php

/**
 * Havenlytics Custom Post Type: Havenlytics
 *
 * This class registers a "Havenlytics" custom post type
 * that extends the reusable base class `Custom_Posts`.
 *
 * @package HvnlyNab\Database\Custom_Posts
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Posts;

use HvnlyNab\Database\Base\Custom_Posts;
use HvnlyNab\Frontend\ViewCountFormatter;

if ( ! defined('ABSPATH')) {
    exit; // Prevent direct access
}

/**
 * Class HavenlyticsNab
 *
 * Registers the "Property" custom post type for Havenlytics.
 */
class HavenlyticsNab extends Custom_Posts {


    /**
     * @var string $slug Post type slug.
     */
    private $slug = 'hvnly_property';

    /**
     * @var array $generated_ids Cache for generated IDs during current request.
     */
    private static $generated_ids = array();

    /**
     * Register the "Property" custom post type.
     *
     * @return void
     */
    public function register_custom_post_type() {
        // Add hooks for automatic Property ID generation
        add_action('wp_insert_post', array( $this, 'generate_auto_property_id' ), 10, 3);

        // Optional: Add hooks for post status transitions
        add_action('transition_post_status', array( $this, 'generate_property_id_on_publish' ), 10, 3);

        // Clean up property ID on delete
        add_action('before_delete_post', array( $this, 'cleanup_property_id_on_delete' ));

        // Customize admin table columns
        add_filter("manage_{$this->slug}_posts_columns", array( $this, 'custom_table_columns' ));
        add_action("manage_{$this->slug}_posts_custom_column", array( $this, 'custom_table_custom_column' ), 10, 2);
        add_filter("manage_taxonomies_{$this->slug}_columns", array( $this, 'manage_table_taxonomies' ));
        add_filter("manage_edit-{$this->slug}_sortable_columns", array( $this, 'sortable_columns' ));

        // Ensure meta boxes (Author, Comments) get reordered
        add_action('add_meta_boxes_' . $this->slug, array( $this, 'reorder_meta_boxes' ), 99);

        // Top filters
        add_action('restrict_manage_posts', array( $this, 'top_filters_dropdown' ));
        add_action('pre_get_posts', array( $this, 'filter_by_taxonomies' ));

        // Block editor for this CPT remains gated by PluginGutenbergSupport
        // (use_block_editor_for_post_type). REST must stay enabled so block
        // Inspector pickers can use /wp/v2/hvnly_property.

        // Labels for UI
        $this->name = esc_html__('Property', 'havenlytics');
        $this->menu = esc_html__('Add New Property', 'havenlytics');

        // Post type settings
        $settings = array(
            // Always expose in REST (picker / blocks). Not the same as Gutenberg editing.
            'show_in_rest'       => true,
            'menu_icon'          => HVNLYNAB_ASSETS_URL . '/admin/img/havenlytics-icon18x18.svg',
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
            'publicly_queryable' => $this->public_query,
            'show_in_menu'       => true,
            'menu_position'      => 3,
            'rewrite'            => array(
                'slug'       => class_exists( '\HvnlyNab\Core\PermalinkSettings' )
                    ? \HvnlyNab\Core\PermalinkSettings::get_property_single_slug()
                    : 'property',
                'with_front' => false,
                'pages'      => true,
                'feeds'      => true,
            ),
            'query_var'          => true,
            'has_archive'        => class_exists( '\HvnlyNab\Core\PermalinkSettings' )
                ? \HvnlyNab\Core\PermalinkSettings::get_property_archive_slug()
                : true,
        );

        // Post type labels
        $labels = array(
            'name'      => esc_html__('Property', 'havenlytics'),
            'all_items' => esc_html__('Properties', 'havenlytics'),
            'menu_name' => esc_html__('Havenlytics', 'havenlytics'),
        );

        // Initialize post type via base class
        $this->init(
            $this->slug,
            $this->name,
            $this->menu,
            $settings,
            $labels
        );
    }

    /**
     * Check if Gutenberg should be enabled for this post type
     * Retrieves setting from database: general -> hvnly_EnabledGutenbergEditor
     *
     * Note: This method overrides the parent class method with same visibility (protected)
     *
     * @return bool
     */
    protected function is_gutenberg_enabled() {
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
     * Filter Gutenberg editor support for this post type
     *
     * @param bool   $use_block_editor Whether to use the block editor.
     * @param string $post_type        The post type being checked.
     * @return bool
     */
    public function filter_gutenberg_support_for_post_type( $use_block_editor, $post_type ) {
        if ($post_type === $this->slug) {
            return $this->is_gutenberg_enabled();
        }
        return $use_block_editor;
    }

    /**
     * Automatically generate Property ID ONLY when post is first created
     * With caching to prevent multiple saves
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an update.
     * @return void
     */
    public function generate_auto_property_id( $post_id, $post, $update ) {
        // Only for our custom post type
        if ($post->post_type !== $this->slug) {
            return;
        }

        // Don't generate on revisions, autosaves, or imports
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || defined('WP_IMPORTING')) {
            return;
        }

        // Check cache first to avoid duplicate processing in same request
        if (isset(self::$generated_ids[ $post_id ])) {
            return;
        }

        // Check if Property ID already exists in database
        $existing_id = get_post_meta($post_id, '_hvnly_unique_property_id', true);

        // If ID already exists, cache it and return
        if ( ! empty($existing_id)) {
            self::$generated_ids[ $post_id ] = $existing_id;
            return;
        }

        // Generate new ID only for new posts
        if ( ! $update) {
            // Mark as processed in cache
            self::$generated_ids[ $post_id ] = true;

            // Use the Helper class to generate ID
            HVNLY_NAB()->Helper->generate_property_id($post_id);
        }
    }

    /**
     * Also generate on publish transition for safety
     * With cache check to prevent duplicates
     *
     * @param string  $new_status New post status.
     * @param string  $old_status Old post status.
     * @param WP_Post $post       Post object.
     * @return void
     */
    public function generate_property_id_on_publish( $new_status, $old_status, $post ) {
        if ($post->post_type !== $this->slug) {
            return;
        }

        // Check cache first
        if (isset(self::$generated_ids[ $post->ID ])) {
            return;
        }

        // Only generate when first published
        if ('publish' === $new_status && 'publish' !== $old_status) {
            $existing_id = get_post_meta($post->ID, '_hvnly_unique_property_id', true);
            if (empty($existing_id)) {
                // Mark as processed in cache
                self::$generated_ids[ $post->ID ] = true;
                HVNLY_NAB()->Helper->generate_property_id($post->ID);
            } else {
                // Cache existing ID
                self::$generated_ids[ $post->ID ] = $existing_id;
            }
        }
    }

    /**
     * Clean up Property ID when property is permanently deleted
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function cleanup_property_id_on_delete( $post_id ) {
        if (get_post_type($post_id) === $this->slug) {
            // When property is permanently deleted, remove its Property ID
            // This allows the number to be reused (gap filling)
            hvnly_safe_delete_post_meta( (int) $post_id, '_hvnly_unique_property_id', 'post_permanent_delete' );

            // Clear from cache
            unset(self::$generated_ids[ $post_id ]);
        }
    }

    /**
     * Reorder Author & Discussion meta boxes after custom tab/metabox.
     *
     * @return void
     */
    public function reorder_meta_boxes() {
        // Remove default placement
        remove_meta_box('authordiv', $this->slug, 'normal');
        remove_meta_box('commentstatusdiv', $this->slug, 'normal');
        remove_meta_box('commentsdiv', $this->slug, 'normal');

        // Re-add them with low priority (appear after custom tab/metabox)
        add_meta_box(
            'authordiv',
            __('Author', 'havenlytics'),
            'post_author_meta_box',
            $this->slug,
            'normal',
            'low'
        );

        add_meta_box(
            'commentstatusdiv',
            __('Discussion', 'havenlytics'),
            'post_comment_status_meta_box',
            $this->slug,
            'normal',
            'low'
        );

        add_meta_box(
            'commentsdiv',
            __('Comments', 'havenlytics'),
            'post_comment_meta_box',
            $this->slug,
            'normal',
            'low'
        );
    }

    /**
     * Custom table columns for admin listing
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function custom_table_columns( $columns ) {
        $columns = array(
            'cb'                         => $columns['cb'],
            'property_id'                => esc_html__('Property ID', 'havenlytics'),
            'preview_image'              => esc_html__('Property Image', 'havenlytics'),
            'title'                      => esc_html__('Property Address', 'havenlytics'),
            'taxonomy-hvnly_prop_depts'  => esc_html__('Department', 'havenlytics'),
            '_hvnly_property_price'      => esc_html__('Price', 'havenlytics'),
            'taxonomy-hvnly_prop_locations' => esc_html__('Property Location', 'havenlytics'),
            'taxonomy-hvnly_prop_status' => esc_html__('Property Status', 'havenlytics'),
            'view_count'                 => esc_html__('Views', 'havenlytics'),
            'author'                     => esc_html__('Owner / Landlord', 'havenlytics'),
            'date'                       => esc_html__('Create Date', 'havenlytics'),
        );
        return $columns;
    }

    /**
     * Sortable columns for admin listing
     *
     * @return array Sortable columns.
     */
    public function sortable_columns() {
        $columns = array(
            'property_id'                => 'property_id',
            'taxonomy-hvnly_prop_depts'  => esc_html__('Department', 'havenlytics'),
            'title'                      => esc_html__('Title', 'havenlytics'),
            '_hvnly_property_price'      => esc_html__('Price', 'havenlytics'),
            'taxonomy-hvnly_prop_location' => esc_html__('Location', 'havenlytics'),
            'taxonomy-hvnly_prop_status' => esc_html__('Status', 'havenlytics'),
            'view_count'                 => 'view_count',
            'author'                     => esc_html__('Owner / Landlord', 'havenlytics'),
        );
        return $columns;
    }

    /**
     * Custom column content for admin listing
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     * @return void
     */
    public function custom_table_custom_column( $column, $post_id ) {
        switch ($column) {
            case 'property_id':
                $property_id = HVNLY_NAB()->Helper->get_property_id($post_id);
                if ( ! empty($property_id)) {
                    echo '<strong>' . esc_html($property_id) . '</strong>';
                } else {
                    echo '<span style="color:var(--hvnly-brand-primary, #a7aaad);">' . esc_html__('Generating...', 'havenlytics') . '</span>';
                }
                break;
            case 'preview_image':
                echo get_the_post_thumbnail($post_id, array( 50, 50 ));
                break;
            case '_hvnly_property_price':
                $price = get_post_meta($post_id, '_hvnly_property_price', true);
                // Use the Helper class to format the price consistently with frontend
                if ( ! empty($price)) {
                    echo esc_html(HVNLY_NAB()->Helper->format_price($price));
                } else {
                    echo '<span style="color:#aaa;">—</span>';
                }
                break;
            case 'status':
                echo esc_html(ucfirst(get_post_status($post_id)));
                break;
            case 'view_count':
                $this->display_view_count($post_id);
                break;
        }
    }

    /**
     * Display view count with eye icons using ViewCountFormatter
     *
     * @param int $post_id Property ID.
     * @return void
     */
    private function display_view_count( $post_id ) {
        // Get view count from meta
        $view_data = get_post_meta($post_id, '_hvnly_property_views', true);

        // Initialize view count
        $total_views = 0;

        if ( ! empty($view_data) && is_array($view_data)) {
            // Get total views from the array structure
            $total_views = isset($view_data['total']) ? absint($view_data['total']) : 0;
        }

        // Use the ViewCountFormatter class to format the number
        $formatted_views = '';
        if (class_exists('HvnlyNab\Frontend\ViewCountFormatter')) {
            // Use compact format for admin table (1K, 1.5M, 2.3B)
            $formatted_views = ViewCountFormatter::format($total_views, 'compact', array(
                'precision'   => 1,
                'force_floor' => true,
            ));
        } else {
            // Fallback: format manually
            $formatted_views = $this->format_views_manually($total_views);
        }

        // Determine which eye icon to show based on view count
        if ($total_views > 0) {
            // Show open eye for properties with views
            $eye_icon = '<span class="dashicons dashicons-visibility" style="color:var(--hvnly-brand-primary, #46b450); vertical-align:middle; margin-right:5px;" title="' . esc_attr__('Has been viewed', 'havenlytics') . '"></span>';
        } else {
            // Show closed eye for properties with no views
            $eye_icon = '<span class="dashicons dashicons-hidden" style="color:var(--hvnly-brand-secondary, #a7aaad); vertical-align:middle; margin-right:5px;" title="' . esc_attr__('Not viewed yet', 'havenlytics') . '"></span>';
        }

        // Display the eye icon and formatted count
        echo '<div style="display:flex; align-items:center;">';
        echo wp_kses($eye_icon, array(
            'span' => array(
                'class' => array(),
                'style' => array(),
                'title' => array(),
            ),
            'div' => array(
                'style' => array(),
            ),
        ));
        echo '<span>' . esc_html($formatted_views) . '</span>';
        echo '</div>';

        // Add inline styles for better appearance
        echo '<style>
            .column-view_count {
                width: 120px;
            }
            .dashicons-visibility:before, .dashicons-hidden:before {
                font-size: 18px;
            }
            #adminmenumain
            .menu-icon-hvnly_property a.menu-icon-hvnly_property img{
                display: inline-block !important;
            }
        </style>';
    }

    /**
     * Fallback method to format views manually if ViewCountFormatter is not available
     *
     * @param int $view_count View count.
     * @return string Formatted view count.
     */
    private function format_views_manually( $view_count ) {
        $view_count = absint($view_count);

        if ($view_count < 1000) {
            return (string) $view_count;
        } elseif ($view_count < 1000000) {
            $thousands = floor($view_count / 1000);
            return $thousands . 'K';
        } elseif ($view_count < 1000000000) {
            $millions = floor($view_count / 1000000);
            return $millions . 'M';
        } else {
            $billions = floor($view_count / 1000000000);
            return $billions . 'B';
        }
    }

    /**
     * Manage table taxonomies
     *
     * @return array
     */
    public function manage_table_taxonomies() {
        $taxonomies   = array();
        $taxonomies[] = 'hvnly_prop_depts';
        $taxonomies[] = 'hvnly_prop_locations';
        return $taxonomies;
    }

    /**
     * Top filters dropdown for admin listing
     *
     * @return void
     */
    public function top_filters_dropdown() {
        global $typenow;

        if ($typenow !== $this->slug) {
            return;
        }

        // Array of all filters
        $filters = array(
            'hvnly_prop_depts'     => esc_html__('All Departments', 'havenlytics'),
            'hvnly_prop_locations' => esc_html__('All Locations', 'havenlytics'),
            'hvnly_prop_status'    => esc_html__('All Statuses', 'havenlytics'),
        );

        foreach ($filters as $taxonomy => $label) {
            $terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
            ));

            // Use filter_input for GET data
            $current_raw = filter_input(INPUT_GET, $taxonomy, FILTER_UNSAFE_RAW);
            $current     = $current_raw ? sanitize_text_field($current_raw) : '';
            ?>
<select name="<?php echo esc_attr($taxonomy); ?>" id="filter-<?php echo esc_attr($taxonomy); ?>" class="postform">
    <option value=""><?php echo esc_html($label); ?></option>
			<?php
			if ( ! is_wp_error($terms) && ! empty($terms)) {
				foreach ($terms as $term) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr($term->slug),
						selected($current, $term->slug, false),
						esc_html($term->name)
					);
				}
			}
			?>
</select>
			<?php
        }
    }

    /**
     * Filter properties by taxonomy based on top filter dropdowns
     *
     * @param WP_Query $query The WP_Query instance.
     * @return void
     */
    public function filter_by_taxonomies( $query ) {
        global $pagenow;

        if ($pagenow !== 'edit.php' || ! is_admin()) {
            return;
        }

        $tax_queries = array();

        $taxonomies = array(
            'hvnly_prop_depts',
            'hvnly_prop_status',
            'hvnly_prop_locations',
        );

        foreach ($taxonomies as $taxonomy) {
            // Use filter_input for GET data
            $tax_value_raw = filter_input(INPUT_GET, $taxonomy, FILTER_UNSAFE_RAW);
            $tax_value     = $tax_value_raw ? sanitize_text_field($tax_value_raw) : '';

            if ( ! empty($tax_value)) {
                $tax_queries[] = array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $tax_value,
                );
            }
        }

        if ( ! empty($tax_queries)) {
            $query->set('tax_query', $tax_queries);
        }
    }
}
