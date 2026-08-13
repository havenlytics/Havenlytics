<?php
/**
 * Property Types
 *
 * @package HvnlyNab\Database\Custom_Taxonomy
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Taxonomy;

use HvnlyNab\Database\Base\Custom_Taxonomy;
use HvnlyNab\Database\Traits\Taxonomy_Permalink_Manager;
use HvnlyNab\Database\Traits\Taxonomy_Fields\Hvnly_Advanced_Icon_Manager;
use HvnlyNab\Database\Traits\Taxonomy_Fields\Hvnly_Advanced_Img_Manager;

if ( ! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Property_Types class
 */
class Property_Types extends Custom_Taxonomy {

    use Taxonomy_Permalink_Manager, Hvnly_Advanced_Icon_Manager, Hvnly_Advanced_Img_Manager {
        Hvnly_Advanced_Icon_Manager::hvnly_is_taxonomy_admin_page insteadof Hvnly_Advanced_Img_Manager;
    }

    // Taxonomy slug
    private $hvnly_slug = 'hvnly_prop_types';

    /**
     * Extended constructor with icon management
     */
    public function __construct() {
        parent::__construct();

        // Initialize permalink manager
        $this->hvnly_initialize_permalink_manager($this->hvnly_slug);

        // Initialize advanced icon management (keeps icon meta + persistence intact for
        // backward compatibility and the frontend image → icon → text fallback).
        $this->hvnly_initialize_icon_manager($this->hvnly_slug);

        // Initialize optional term image upload (same workflow as Property Locations).
        $this->hvnly_initialize_img_manager($this->hvnly_slug);

        // 3.1.2: Property Types use image upload only in the admin UI.
        // Hide the icon picker field and its admin column on this taxonomy while
        // leaving existing icon term meta untouched (no migration, no data loss).
        $this->hvnly_hide_property_type_icon_admin_ui();
    }

    /**
     * Remove the icon picker UI from the Property Types admin screens.
     *
     * Only the icon selection field and its admin column are unhooked. Stored
     * icon meta (`_hvnly_advanced_icon_data`) is preserved for the frontend
     * fallback, and icon persistence safely no-ops because its nonce field is
     * no longer rendered.
     *
     * @since 3.1.2
     * @return void
     */
    private function hvnly_hide_property_type_icon_admin_ui() {
        $slug = $this->hvnly_slug;

        remove_action("{$slug}_add_form_fields", array( $this, 'hvnly_render_icon_selection_field' ));
        remove_action("{$slug}_edit_form_fields", array( $this, 'hvnly_render_icon_editing_field' ));
        remove_filter("manage_edit-{$slug}_columns", array( $this, 'hvnly_add_icon_admin_column' ));
        remove_filter("manage_{$slug}_custom_column", array( $this, 'hvnly_display_icon_admin_column' ), 10);
    }

    /**
     * Method register_custom_taxonomy
     *
     * @return void
     */
    public function register_custom_taxonomy() {

        // init taxonomy Settings
        $this->init(
            $this->hvnly_slug,
            esc_html__('Property Type', 'havenlytics'),
            esc_html__('Types', 'havenlytics'),
            $this->post_types,
            array(
                'show_admin_column' => true,
                'public' => true,
                'publicly_queryable' => true,
                'query_var' => true,
                'rewrite' => array(
                    'slug' => $this->hvnly_slug,
                    'with_front' => false,
                ),
            )
        );
    }
}
