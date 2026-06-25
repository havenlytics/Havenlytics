<?php
/**
 * Property Tags
 * 
 * @package HvnlyNab\Database\Custom_Taxonomy
 * @since 2.0.0
 */
namespace HvnlyNab\Database\Custom_Taxonomy;

use HvnlyNab\Database\Base\Custom_Taxonomy;
use HvnlyNab\Database\Traits\Taxonomy_Permalink_Manager;

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Property_Tags class
 */
class Property_Tags extends Custom_Taxonomy
{
    use Taxonomy_Permalink_Manager;

    // Taxonomy slug
    private $slug = 'hvnly_prop_tags';

    /**
     * Method __construct
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        // Initialize permalink manager
        $this->hvnly_initialize_permalink_manager($this->slug);
    }

    /**
     * Method register_custom_taxonomy
     *
     * @return void
     */
    public function register_custom_taxonomy()
    {
        // init taxonomy Settings
        $settings = array(
            'hierarchical' => false,
            'public' => true,
            'publicly_queryable' => true,
            'query_var' => true,
            'rewrite' => [
                'slug' => $this->slug,
                'with_front' => false,
            ],
        );
        
        $this->init(
            $this->slug,
            esc_html__('Property Tag', 'havenlytics'),
            esc_html__('Tags', 'havenlytics'),
            $this->post_types,
            $settings
        );
    }
}