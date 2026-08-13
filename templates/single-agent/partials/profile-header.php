<?php

/**

 * Agent profile header (avatar, identity, meta, social, actions).

 *

 * @package     Havenlytics

 * @subpackage  Templates/single-agent/partials

 * @since       3.0.2

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



use HvnlyNab\Agent\PropertyAgentWidgetRenderer;



$hvnly_agent = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();



if ( empty( $hvnly_agent ) && function_exists( 'hvnly_get_agent' ) ) {

	$hvnly_agent = hvnly_get_agent( (int) get_the_ID() );

}



if ( empty( $hvnly_agent['name'] ) && empty( $hvnly_agent['avatar'] ) ) {

	return;

}



$hvnly_name       = (string) ( $hvnly_agent['name'] ?? '' );

$hvnly_position   = (string) ( $hvnly_agent['position'] ?? '' );

$hvnly_company    = (string) ( $hvnly_agent['company'] ?? '' );

$hvnly_email      = (string) ( $hvnly_agent['email'] ?? '' );

$hvnly_phone      = (string) ( $hvnly_agent['phone'] ?? '' );

$hvnly_mobile     = (string) ( $hvnly_agent['mobile'] ?? '' );

$hvnly_office     = (string) ( $hvnly_agent['office'] ?? '' );

$hvnly_fax        = (string) ( $hvnly_agent['fax'] ?? '' );

$hvnly_whatsapp   = (string) ( $hvnly_agent['whatsapp'] ?? '' );

$hvnly_address    = (string) ( $hvnly_agent['address'] ?? '' );

$hvnly_license    = (string) ( $hvnly_agent['license'] ?? '' );

$hvnly_website    = (string) ( $hvnly_agent['website'] ?? '' );

$hvnly_avatar     = (string) ( $hvnly_agent['avatar'] ?? '' );

$hvnly_agency     = isset( $hvnly_agent['agency'] ) && is_array( $hvnly_agent['agency'] ) ? $hvnly_agent['agency'] : array();

if ( empty( $hvnly_agency['name'] ) && ! empty( $hvnly_agent['id'] ) && function_exists( 'hvnly_get_agent_agency' ) ) {
	$hvnly_agency = hvnly_get_agent_agency( (int) $hvnly_agent['id'] );
}

if ( empty( $hvnly_agency['profile_url'] ) && ! empty( $hvnly_agency['id'] ) ) {
	$hvnly_term_link = get_term_link( (int) $hvnly_agency['id'], 'hvnly_agent_agency' );
	if ( ! is_wp_error( $hvnly_term_link ) ) {
		$hvnly_agency['profile_url'] = (string) $hvnly_term_link;
	}
}

$hvnly_agent_id = (int) ( $hvnly_agent['id'] ?? 0 );

$hvnly_property_count = function_exists( 'hvnly_get_agent_property_count' )
	? hvnly_get_agent_property_count( $hvnly_agent_id )
	: 0;

$hvnly_active_count = function_exists( 'hvnly_get_agent_active_listing_count' )
	? hvnly_get_agent_active_listing_count( $hvnly_agent_id )
	: $hvnly_property_count;



$hvnly_social_links = PropertyAgentWidgetRenderer::resolve_social_links(

	$hvnly_agent,

	array(

		'show_website' => true,

		'show_social'  => true,

	)

);



$hvnly_meta_items = array();



if ( $hvnly_license ) {

	$hvnly_meta_items[] = array(

		'label' => __( 'Agent License', 'havenlytics' ),

		'value' => $hvnly_license,

	);

}

if ( $hvnly_fax ) {

	$hvnly_meta_items[] = array(

		'label' => __( 'Tax / Fax Number', 'havenlytics' ),

		'value' => $hvnly_fax,

	);

}

if ( $hvnly_address ) {

	$hvnly_meta_items[] = array(

		'label' => __( 'Address', 'havenlytics' ),

		'value' => $hvnly_address,

	);

}

if ( $hvnly_position ) {

	$hvnly_meta_items[] = array(

		'label' => __( 'Position', 'havenlytics' ),

		'value' => $hvnly_position,

	);

}

if ( $hvnly_mobile && $hvnly_mobile !== $hvnly_phone ) {

	$hvnly_meta_items[] = array(

		'label' => __( 'Mobile', 'havenlytics' ),

		'value' => $hvnly_mobile,

		'href'  => 'tel:' . preg_replace( '/[^0-9+]/', '', $hvnly_mobile ),

	);

}

if ( $hvnly_office ) {

	$hvnly_meta_items[] = array(

		'label' => __( 'Office', 'havenlytics' ),

		'value' => $hvnly_office,

		'href'  => 'tel:' . preg_replace( '/[^0-9+]/', '', $hvnly_office ),

	);

}

?>

<section class="hvnly-agent-single__profile-card">

	<div class="hvnly-agent-single__profile-top">

		<div class="hvnly-agent-single__avatar-wrap">

			<?php if ( $hvnly_avatar ) : ?>

				<img class="hvnly-agent-single__avatar" src="<?php echo esc_url( $hvnly_avatar ); ?>" alt="" width="160" height="160" loading="eager" decoding="async" />

			<?php else : ?>

				<div class="hvnly-agent-single__avatar hvnly-agent-single__avatar--placeholder" aria-hidden="true">

					<i class="fas fa-user-circle"></i>

				</div>

			<?php endif; ?>

		</div>



		<div class="hvnly-agent-single__identity">

			<div class="hvnly-agent-single__identity-head">

				<div>

					<h1 class="hvnly-agent-single__name"><?php echo esc_html( $hvnly_name ); ?></h1>

					<?php if ( function_exists( 'hvnly_render_agent_availability_badge' ) ) : ?>
						<?php
						hvnly_render_agent_availability_badge(
							array(
								'agent_id' => $hvnly_agent_id,
								'agent'    => $hvnly_agent,
								'context'  => 'profile',
								'echo'     => true,
							)
						);
						?>
					<?php endif; ?>

					<?php if ( $hvnly_company || ! empty( $hvnly_agency['name'] ) ) : ?>

						<p class="hvnly-agent-single__company">

							<?php if ( ! empty( $hvnly_agency['logo_url'] ) ) : ?>
								<img class="hvnly-agent-single__agency-logo" src="<?php echo esc_url( (string) $hvnly_agency['logo_url'] ); ?>" alt="" width="28" height="28" loading="lazy" decoding="async" />
							<?php endif; ?>

							<?php esc_html_e( 'Company Agent at', 'havenlytics' ); ?>

							<?php if ( ! empty( $hvnly_agency['website'] ) ) : ?>

								<a href="<?php echo esc_url( (string) $hvnly_agency['website'] ); ?>" target="_blank" rel="noopener noreferrer">

									<?php echo esc_html( $hvnly_company ?: (string) $hvnly_agency['name'] ); ?>

								</a>

							<?php else : ?>

								<strong><?php echo esc_html( $hvnly_company ?: (string) $hvnly_agency['name'] ); ?></strong>

							<?php endif; ?>

						</p>

					<?php elseif ( $hvnly_position ) : ?>

						<p class="hvnly-agent-single__position"><?php echo esc_html( $hvnly_position ); ?></p>

					<?php endif; ?>

				</div>



				<div class="hvnly-agent-single__quick-actions">

					<?php if ( $hvnly_email ) : ?>

						<a class="hvnly-btn hvnly-btn--primary hvnly-agent-single__quick-btn hvnly-agent-single__quick-btn--primary" href="mailto:<?php echo esc_attr( $hvnly_email ); ?>">

							<i class="fas fa-envelope" aria-hidden="true"></i>

							<span><?php esc_html_e( 'Send Email', 'havenlytics' ); ?></span>

						</a>

					<?php endif; ?>



					<?php if ( $hvnly_phone ) : ?>

						<a class="hvnly-btn hvnly-btn--outline hvnly-agent-single__quick-btn" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hvnly_phone ) ); ?>">

							<i class="fas fa-phone-alt" aria-hidden="true"></i>

							<span><?php echo esc_html( $hvnly_phone ); ?></span>

						</a>

					<?php endif; ?>

				</div>

			</div>



			<div class="hvnly-agent-single__stats">
				<div class="hvnly-agent-single__stat">
					<span class="hvnly-agent-single__stat-value"><?php echo esc_html( (string) $hvnly_property_count ); ?></span>
					<span class="hvnly-agent-single__stat-label">
						<?php echo esc_html( _n( 'Total Property', 'Total Properties', $hvnly_property_count, 'havenlytics' ) ); ?>
					</span>
				</div>

				<div class="hvnly-agent-single__stat">
					<span class="hvnly-agent-single__stat-value"><?php echo esc_html( (string) $hvnly_active_count ); ?></span>
					<span class="hvnly-agent-single__stat-label">
						<?php echo esc_html( _n( 'Active Listing', 'Active Listings', $hvnly_active_count, 'havenlytics' ) ); ?>
					</span>
				</div>

				<?php if ( ! empty( $hvnly_agency['name'] ) ) : ?>
					<div class="hvnly-agent-single__stat hvnly-agent-single__stat--agency">
						<span class="hvnly-agent-single__stat-value hvnly-agent-single__stat-value--text">
							<?php if ( ! empty( $hvnly_agency['profile_url'] ) ) : ?>
								<a href="<?php echo esc_url( (string) $hvnly_agency['profile_url'] ); ?>"><?php echo esc_html( (string) $hvnly_agency['name'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( (string) $hvnly_agency['name'] ); ?>
							<?php endif; ?>
						</span>
						<span class="hvnly-agent-single__stat-label"><?php esc_html_e( 'Agency', 'havenlytics' ); ?></span>
					</div>
				<?php endif; ?>
			</div>



			<?php if ( ! empty( $hvnly_meta_items ) ) : ?>

				<ul class="hvnly-agent-single__meta-list">

					<?php foreach ( $hvnly_meta_items as $hvnly_meta ) : ?>

						<li class="hvnly-agent-single__meta-item">

							<strong><?php echo esc_html( (string) $hvnly_meta['label'] ); ?>:</strong>

							<?php if ( ! empty( $hvnly_meta['href'] ) ) : ?>

								<a href="<?php echo esc_url( (string) $hvnly_meta['href'] ); ?>"><?php echo esc_html( (string) $hvnly_meta['value'] ); ?></a>

							<?php else : ?>

								<span><?php echo esc_html( (string) $hvnly_meta['value'] ); ?></span>

							<?php endif; ?>

						</li>

					<?php endforeach; ?>

				</ul>

			<?php endif; ?>



			<?php if ( ! empty( $hvnly_social_links ) ) : ?>

				<ul class="hvnly-agent-single__social-list">

					<?php foreach ( $hvnly_social_links as $hvnly_platform => $hvnly_link ) : ?>

						<li>

							<a

								class="hvnly-agent-single__social-link hvnly-agent-single__social-link--<?php echo esc_attr( $hvnly_platform ); ?>"

								href="<?php echo esc_url( (string) $hvnly_link['url'] ); ?>"

								target="_blank"

								rel="noopener noreferrer"

								title="<?php echo esc_attr( (string) $hvnly_link['label'] ); ?>"

							>

								<i class="<?php echo esc_attr( (string) $hvnly_link['icon'] ); ?>" aria-hidden="true"></i>

								<span class="screen-reader-text"><?php echo esc_html( (string) $hvnly_link['label'] ); ?></span>

							</a>

						</li>

					<?php endforeach; ?>

				</ul>

			<?php endif; ?>

		</div>

	</div>

</section>

