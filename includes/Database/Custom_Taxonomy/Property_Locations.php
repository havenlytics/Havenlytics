<?php
/**
 * Property Locations
 *
 * @package HvnlyNab\Database\Custom_Taxonomy
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Taxonomy;

use HvnlyNab\Database\Base\Custom_Taxonomy;
use HvnlyNab\Database\Traits\Taxonomy_Permalink_Manager;
use HvnlyNab\Database\Traits\Taxonomy_Fields\Hvnly_Advanced_Img_Manager;

if ( ! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Property_Locations class
 *
 * Adds image upload option to Property Location taxonomy
 */
class Property_Locations extends Custom_Taxonomy {

    use Taxonomy_Permalink_Manager;
    use Hvnly_Advanced_Img_Manager;

    // Taxonomy slug
    private $hvnly_slug = 'hvnly_prop_locations';

    /**
     * Extended constructor with img management
     */
    public function __construct() {
        parent::__construct();

        // Initialize permalink manager
        $this->hvnly_initialize_permalink_manager($this->hvnly_slug);

        // Initialize advanced img management
        $this->hvnly_initialize_img_manager($this->hvnly_slug);
    }

    /**
     * Register custom taxonomy
     *
     * @return void
     */
    public function register_custom_taxonomy() {
        // Initialize taxonomy
        $this->init(
            $this->hvnly_slug,
            esc_html__('Property Location', 'havenlytics'),
            esc_html__('Locations', 'havenlytics'),
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
