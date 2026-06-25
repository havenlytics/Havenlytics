<?php

/**

 * Agencies archive content template.

 *

 * @package     Havenlytics

 * @subpackage  Templates/

 * @since       3.0.2

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



use HvnlyNab\Core\PermalinkSettings;



$hvnly_view_type = function_exists( 'hvnly_get_property_archive_view_type' ) ? hvnly_get_property_archive_view_type() : 'grid';

$hvnly_query     = function_exists( 'hvnly_get_agency_archive_query' ) ? hvnly_get_agency_archive_query() : array(

	'items'        => array(),

	'total'        => 0,

	'max_pages'    => 1,

	'current_page' => 1,

	'per_page'     => 12,

);



$hvnly_items        = isset( $hvnly_query['items'] ) && is_array( $hvnly_query['items'] ) ? $hvnly_query['items'] : array();

$hvnly_total        = absint( $hvnly_query['total'] ?? 0 );

$hvnly_current_page = absint( $hvnly_query['current_page'] ?? 1 );

$hvnly_per_page     = absint( $hvnly_query['per_page'] ?? 12 );

$hvnly_max_pages    = absint( $hvnly_query['max_pages'] ?? 1 );

$hvnly_start        = $hvnly_total > 0 ? ( ( $hvnly_current_page - 1 ) * $hvnly_per_page ) + 1 : 0;

$hvnly_end          = min( $hvnly_current_page * $hvnly_per_page, $hvnly_total );

$hvnly_archive_slug = class_exists( PermalinkSettings::class ) ? PermalinkSettings::get_agency_archive_slug() : 'agencies';

$hvnly_search_url   = home_url( '/' . trailingslashit( $hvnly_archive_slug ) );



do_action( 'hvnly_before_archive_agency' );

?>

<div class="hvnly-property--archive hvnly-property--agencies--archive hvnly-agency-archive">

	<header class="hvnly-property--archive__header">

		<h1 class="hvnly-property--archive__title"><?php esc_html_e( 'Real Estate Agencies', 'havenlytics' ); ?></h1>

		<p class="hvnly-property--archive__subtitle"><?php esc_html_e( 'Explore trusted agencies and their teams of property professionals.', 'havenlytics' ); ?></p>

	</header>



	<?php

	hvnly_get_template_part(

		'property-archive/partials/view-controls',

		null,

		array(

			'total'         => $hvnly_total,

			'start'         => $hvnly_start,

			'end'           => $hvnly_end,

			'entity_label'  => __( 'agencies', 'havenlytics' ),

			'search_action' => $hvnly_search_url,

		)

	);

	?>



	<?php if ( ! empty( $hvnly_items ) ) : ?>

		<div

			class="hvnly-property--archive__grid hvnly-property--agencies--grid <?php echo 'list' === $hvnly_view_type ? 'hvnly-property--archive__grid--list hvnly-property-list-view list-view' : 'hvnly-property--archive__grid--grid hvnly-property-grid-view grid-view'; ?>"

			data-view-type="<?php echo esc_attr( $hvnly_view_type ); ?>"

		>

			<div class="hvnly-property--archive__cards">

				<?php foreach ( $hvnly_items as $hvnly_agency ) : ?>

					<?php

					hvnly_get_template_part(

						'property-archive/partials/agency-card',

						null,

						array( 'agency' => $hvnly_agency )

					);

					?>

				<?php endforeach; ?>

			</div>

		</div>



		<?php

		if ( function_exists( 'hvnly_render_property_archive_pagination' ) ) {

			hvnly_render_property_archive_pagination(

				array(

					'current_page' => $hvnly_current_page,

					'max_pages'    => $hvnly_max_pages,

					'per_page'     => $hvnly_per_page,

					'found_posts'  => $hvnly_total,

					'instance_id'  => 'agency-archive',

					'base_url'     => $hvnly_search_url,

					'entity_label' => __( 'agencies', 'havenlytics' ),

				)

			);

		}

		?>

	<?php else : ?>

		<?php

		hvnly_get_template_part(

			'property-archive/partials/empty-state',

			null,

			array(

				'message' => __( 'No agencies match your search. Try a different keyword or check back soon.', 'havenlytics' ),

				'icon'    => 'fas fa-building',

			)

		);

		?>

	<?php endif; ?>

</div>

<?php

do_action( 'hvnly_after_archive_agency' );

