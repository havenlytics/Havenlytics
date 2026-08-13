<?php
/**
 * Havenlytics Custom Metabox
 *
 * @package HvnlyNab\Database\Base
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Base;


// Security check to prevent direct access
if ( ! defined('ABSPATH')) {
    exit;
}
/**
 * Custom_Metabox class
 */
abstract class Custom_Metabox {






    /**
     * Method __construct
     *
     * @return void
     */
    public function __construct() {

        // Init Custom Post Type
        // Note: Child classes should register their own metaboxes
    }


    /**
     * Method hvnly_register_dynamic_tabs_metabox
     *
     * @return void
     */
    abstract public function hvnly_register_dynamic_tabs_metabox();


    /**
     * Method hvnly_save_dynamic_tabs_data
     *
     * @param $post_id $post_id
     *
     * @return void
     */
    abstract public function hvnly_save_dynamic_tabs_data( $post_id );
}
