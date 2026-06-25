<?php
/**
 * Section Title Template
 * 
 * This template displays the title for a section.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/archive/section-title.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/archive/section-title.php
 * 3. Modify the copied file to customize the section title display
 * 
 * @package     Havenlytics
 * @subpackage  Templates/archive
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * CUSTOMIZATION EXAMPLES:
 * 
 * 1. Change title text for property archive:
 * add_filter('hvnly_page_title', function ($title) {
 *     if (is_post_type_archive('hvnly_property')) {
 *         return 'Available Properties'; // Custom archive title
 *     }
 *     return $title;
 * });
 * 
 * 2. Hide title on specific pages:
 * add_filter('hvnly_show_page_title', function($show) {
 *     if (is_page('contact')) {
 *         return false;
 *     }
 *     return $show;
 * });
 */

?>

<div class="hvnly-property-section-title">
    <?php if (apply_filters('hvnly_show_page_title', true)) : ?>

        <h1 class="property-archive-title page-title"><?php hvnly_page_title(); ?> </h1>

    <?php endif; ?>

</div>