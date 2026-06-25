<?php
/**
 * Content Single Property Template
 * 
 * This template displays the content for individual property posts.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/content-single-property.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/content-single-property.php
 * 3. Modify the copied file to customize the single property page
 * 
 * @package     Havenlytics
 * @subpackage  Templates/
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * hvnly_single_property_elements hook.
 *
 * @hooked hvnly_single_property_header - 10
 * @hooked hvnly_single_property_main_container_start - 20
 * @hooked hvnly_single_property_layout_grid - 30
 * @hooked hvnly_single_property_main_container_end - 40
 * @hooked hvnly_single_property_similar_properties - 50
 */
do_action('hvnly_single_property_elements');
?>