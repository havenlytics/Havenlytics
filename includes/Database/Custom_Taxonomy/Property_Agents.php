<?php
/**
 * Property Agents
 * 
 * @package HvnlyNab\Database\Custom_Taxonomy
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Taxonomy;

use HvnlyNab\Database\Base\Custom_Taxonomy;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Property_Agents class
 */
class Property_Agents extends Custom_Taxonomy
{

    // Taxonomy slug
    private $slug = 'hvnly_prop_agents';



    /**
     * Method register_custom_taxonomy
     *
     * @return void
     */
    public function register_custom_taxonomy()
    {

        // init taxonomy Settings
        $this->init(
            $this->slug,
            esc_html__('Property Agent', 'havenlytics'),
            esc_html__('Agents', 'havenlytics'),
            $this->post_types,
            array(
                'show_admin_column' => true,
            )
        );
    }
}
