<?php
/**
 * Property Category
 * 
 * @package HvnlyNab\Database\Custom_Taxonomy
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Taxonomy;

use HvnlyNab\Database\Base\Custom_Taxonomy;
use HvnlyNab\Database\Traits\Taxonomy_Fields\Hvnly_Advanced_Icon_Manager;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Property_Category class
 */
class Property_Category extends Custom_Taxonomy
{

    use Hvnly_Advanced_Icon_Manager;

    // Taxonomy slug
    private $hvnly_slug = 'hvnly_prop_categories';


    /**
     * Extended constructor with icon management
     */
    public function __construct()
    {
        parent::__construct();
        
        // Initialize advanced icon management
        $this->hvnly_initialize_icon_manager($this->hvnly_slug);
    }

    /**
     * Method register_custom_taxonomy
     *
     * @return void
     */
    public function register_custom_taxonomy()
    {

        // init taxonomy Settings
        $settings = array('hierarchical' => false);
        $this->init(
            $this->hvnly_slug,
            esc_html__('Property Category', 'havenlytics'),
            esc_html__('Categories', 'havenlytics'),
            $this->post_types,
            $settings
        );
    }
}
