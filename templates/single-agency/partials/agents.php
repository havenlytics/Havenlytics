<?php

/**

 * Agency single agents grid.

 *

 * @package     Havenlytics

 * @subpackage  Templates/single-agency/partials

 * @since       3.0.2

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



use HvnlyNab\Agent\AgencyArchiveQuery;



$hvnly_term_id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;



if ( $hvnly_term_id <= 0 ) {

	return;

}



$hvnly_agent_ids = class_exists( AgencyArchiveQuery::class )

	? AgencyArchiveQuery::get_agent_ids( $hvnly_term_id )

	: array();

?>

<section class="hvnly-agency-single__agents" id="hvnly-agency-agents">

	<header class="hvnly-agency-single__section-header">

		<h2 class="hvnly-agency-single__section-title"><?php esc_html_e( 'Agency Agents', 'havenlytics' ); ?></h2>

		<p class="hvnly-agency-single__section-count">

			<?php

			printf(

				/* translators: %d: number of agents */

				esc_html( _n( '%d agent on this team', '%d agents on this team', count( $hvnly_agent_ids ), 'havenlytics' ) ),

				count( $hvnly_agent_ids )

			);

			?>

		</p>

	</header>



	<?php if ( ! empty( $hvnly_agent_ids ) ) : ?>

		<div class="hvnly-property--archive__grid hvnly-property--agents--grid hvnly-property--archive__grid--grid hvnly-agent-widget hvnly-agent-widget--stack hvnly-property-grid-view grid-view">

			<div class="hvnly-agent-widget__cards hvnly-property--archive__cards">

				<?php foreach ( $hvnly_agent_ids as $hvnly_agent_id ) : ?>

					<?php

					$hvnly_agent = function_exists( 'hvnly_get_agent' ) ? hvnly_get_agent( (int) $hvnly_agent_id ) : array();

					hvnly_get_template_part(

						'property-archive/partials/agent-card',

						null,

						array(

							'agent'   => $hvnly_agent,

							'context' => 'agency_team',

						)

					);

					?>

				<?php endforeach; ?>

			</div>

		</div>

	<?php else : ?>

		<?php

		hvnly_get_template_part(

			'property-archive/partials/empty-state',

			null,

			array(

				'message' => __( 'No agents are assigned to this agency yet.', 'havenlytics' ),

				'icon'    => 'fas fa-user-tie',

			)

		);

		?>

	<?php endif; ?>

</section>

