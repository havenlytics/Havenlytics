<?php
/**
 * Single Property Template
 * 
 * This template displays the single property page.
 * 
 * HOW TO OVERRIDE THIS TEMPLATE:
 * 1. Copy this file from: wp-content/plugins/havenlytics/templates/single-property.php
 * 2. Paste it to your theme at: wp-content/themes/your-theme/havenlytics/single-property.php
 * 3. Modify the copied file to customize the single property page
 * 
 * @package     Havenlytics
 * @subpackage  Templates/
 * @since       2.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if ( function_exists( 'hvnly_ensure_template_functions' ) ) {
    hvnly_ensure_template_functions();
}

get_header(); ?>

<?php
/**
 * hvnly_before_main_content hook.
 *
 * @hooked hvnly_output_content_wrapper_start - 10 (outputs opening divs for the content)
 */
do_action('hvnly_before_main_content');
?>

<?php
if (have_posts()) :
    while (have_posts()) : ?>
        <?php the_post(); ?>

        <?php hvnly_get_template_part('content', 'single-property'); ?>

    <?php endwhile; // end of the loop. 
endif;
wp_reset_postdata();  // avoid errors further down the page

/**
 * hvnly_after_main_content hook.
 *
 * @hooked hvnly_output_content_wrapper_end - 10 (outputs closing divs for the content)
 */
do_action('hvnly_after_main_content');

get_footer();
