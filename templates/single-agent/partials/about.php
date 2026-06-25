<?php

/**

 * Agent biography / about section.

 *

 * @package     Havenlytics

 * @subpackage  Templates/single-agent/partials

 * @since       3.0.2

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



$hvnly_agent = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();



if ( empty( $hvnly_agent ) && function_exists( 'hvnly_get_agent' ) ) {

	$hvnly_agent = hvnly_get_agent( (int) get_the_ID() );

}



$hvnly_name = (string) ( $hvnly_agent['name'] ?? get_the_title() );

$hvnly_bio  = (string) ( $hvnly_agent['biography'] ?? '' );

$hvnly_excerpt = (string) ( $hvnly_agent['excerpt'] ?? '' );



if ( '' === trim( wp_strip_all_tags( $hvnly_bio ) ) && '' !== trim( $hvnly_excerpt ) ) {

	$hvnly_bio = $hvnly_excerpt;

}



if ( '' === trim( wp_strip_all_tags( $hvnly_bio ) ) ) {

	return;

}

?>

<section class="hvnly-agent-single__about">

	<h2 class="hvnly-agent-single__section-title">

		<?php

		printf(

			/* translators: %s: agent name */

			esc_html__( 'About %s', 'havenlytics' ),

			esc_html( $hvnly_name )

		);

		?>

	</h2>

	<div class="hvnly-agent-single__about-content">

		<?php echo wp_kses_post( wpautop( $hvnly_bio ) ); ?>

	</div>

</section>

