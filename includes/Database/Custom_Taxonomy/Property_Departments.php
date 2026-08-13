<?php
/**
 * Property Departments
 *
 * @package HvnlyNab\Database\Custom_Taxonomy
 * @since 2.0.0
 */
namespace HvnlyNab\Database\Custom_Taxonomy;

use HvnlyNab\Database\Base\Custom_Taxonomy;
use HvnlyNab\Database\Traits\Taxonomy_Permalink_Manager;


if ( ! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Property_Departments class
 */
class Property_Departments extends Custom_Taxonomy {


    use Taxonomy_Permalink_Manager;

    // Taxonomy slug
    private $slug = 'hvnly_prop_depts';

    /**
     * Method __construct
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();

        // Initialize permalink manager
        $this->hvnly_initialize_permalink_manager($this->slug);
    }

    /**
     * Method register_custom_taxonomy
     *
     * @return void
     */
    public function register_custom_taxonomy() {

        // init taxonomy Settings
        $this->init(
            $this->slug,
            esc_html__('Property Department', 'havenlytics'),
            esc_html__('Departments', 'havenlytics'),
            $this->post_types,
            array(
                'show_admin_column' => true,
                'public' => true,
                'publicly_queryable' => true, // KEEP THIS TRUE
                'query_var' => true,
                'rewrite' => array(
                    'slug' => $this->slug,
                    'with_front' => false,
                ),
            )
        );
    }
}
