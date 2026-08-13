<?php
/**
 * Property Reviews
 *
 * @package HvnlyNab\Database\Custom_Taxonomy
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Taxonomy;

use HvnlyNab\Database\Base\Custom_Taxonomy;

if ( ! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Property_Reviews class
 */
class Property_Reviews extends Custom_Taxonomy {



    // Taxonomy slug
    private $slug = 'hvnly_prop_reviews';


    /**
     * Method register_custom_taxonomy
     *
     * @return void
     */
    public function register_custom_taxonomy() {

        // init taxonomy Settings
        $settings = array( 'hierarchical' => false );
        $this->init(
            $this->slug,
            esc_html__('Property Reviews', 'havenlytics'),
            esc_html__('Reviews', 'havenlytics'),
            $this->post_types,
            $settings
        );
    }
}
