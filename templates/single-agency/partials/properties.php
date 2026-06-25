<?php

/**

 * Agency single properties carousel.

 *

 * @package     Havenlytics

 * @subpackage  Templates/single-agency/partials

 * @since       3.0.2

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



$hvnly_term_id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;

$hvnly_agency = isset( $args['agency'] ) && is_array( $args['agency'] ) ? $args['agency'] : array();



if ( $hvnly_term_id <= 0 ) {

	return;

}



$hvnly_property_count = absint( $hvnly_agency['property_count'] ?? 0 );

$hvnly_properties_query = function_exists( 'hvnly_get_agency_properties_query' )

	? hvnly_get_agency_properties_query(

		$hvnly_term_id,

		array(

			'posts_per_page' => -1,

			'nopaging'       => true,

		)

	)

	: null;



if ( $hvnly_property_count <= 0 && function_exists( 'hvnly_get_agency_archive_profile' ) ) {

	$hvnly_profile = hvnly_get_agency_archive_profile( $hvnly_term_id );

	$hvnly_property_count = absint( $hvnly_profile['property_count'] ?? 0 );

}

?>

<section class="hvnly-agency-single__properties" id="hvnly-agency-properties">

	<?php if ( $hvnly_properties_query instanceof WP_Query && $hvnly_properties_query->have_posts() ) : ?>

		<?php

		hvnly_get_template_part(

			'single-agency/partials/listings-carousel',

			null,

			array(

				'query'          => $hvnly_properties_query,

				'property_count' => $hvnly_property_count,

			)

		);

		?>

	<?php else : ?>

		<header class="hvnly-agency-single__section-header">

			<h2 class="hvnly-agency-single__section-title"><?php esc_html_e( 'Agency Listings', 'havenlytics' ); ?></h2>

			<p class="hvnly-agency-single__section-count">

				<?php

				printf(

					/* translators: %d: number of properties */

					esc_html( _n( '%d property listed', '%d properties listed', $hvnly_property_count, 'havenlytics' ) ),

					absint( $hvnly_property_count )

				);

				?>

			</p>

		</header>

		<?php

		hvnly_get_template_part(

			'property-archive/partials/empty-state',

			null,

			array(

				'message' => __( 'No properties are listed under this agency yet.', 'havenlytics' ),

				'icon'    => 'fas fa-home',

			)

		);

		?>

	<?php endif; ?>



	<?php wp_reset_postdata(); ?>

</section>


