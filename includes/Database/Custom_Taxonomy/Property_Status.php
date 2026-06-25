<?php
/**
 * Property Status
 * 
 * @package HvnlyNab\Database\Custom_Taxonomy
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Taxonomy;

use HvnlyNab\Database\Base\Custom_Taxonomy;
use HvnlyNab\Database\Traits\Taxonomy_Permalink_Manager;
use HvnlyNab\Database\Traits\Taxonomy_Fields\Hvnly_Advanced_Icon_Manager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Property_Status class
 */
class Property_Status extends Custom_Taxonomy
{
    use Taxonomy_Permalink_Manager, Hvnly_Advanced_Icon_Manager;

    // Taxonomy slug
    private $hvnly_slug = 'hvnly_prop_status';

    /**
     * Extended constructor with icon management
     */
    public function __construct()
    {
        parent::__construct();
        
        // Initialize permalink manager
        $this->hvnly_initialize_permalink_manager($this->hvnly_slug);
        
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
        $this->init(
            $this->hvnly_slug,
            esc_html__('Property Status', 'havenlytics'),
            esc_html__('Status', 'havenlytics'),
            $this->post_types,
            array(
                'show_admin_column' => true,
                'public' => true,
                'publicly_queryable' => true,
                'query_var' => true,
                'rewrite' => [
                    'slug' => $this->hvnly_slug,
                    'with_front' => false,
                ],
            )
        );
    }
}