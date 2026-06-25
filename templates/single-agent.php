<?php

/**

 * Single Agent Template

 *

 * @package     Havenlytics

 * @subpackage  Templates/

 * @since       3.0.2

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



if ( function_exists( 'hvnly_ensure_template_functions' ) ) {

	hvnly_ensure_template_functions();

}



get_header();



do_action( 'hvnly_before_main_content' );



if ( have_posts() ) :

	while ( have_posts() ) :

		the_post();

		hvnly_get_template_part( 'content', 'single-agent' );

	endwhile;

endif;



wp_reset_postdata();



do_action( 'hvnly_after_main_content' );



get_footer();

