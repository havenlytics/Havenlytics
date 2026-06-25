<?php

/**

 * Unified premium agency card.

 *

 * @package     Havenlytics

 * @subpackage  Templates/partials/cards

 * @since       3.0.2

 */



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



$hvnly_agency = isset( $args['agency'] ) && is_array( $args['agency'] ) ? $args['agency'] : array();



if ( empty( $hvnly_agency['name'] ) ) {

	return;

}



$hvnly_context = isset( $args['context'] ) ? sanitize_key( (string) $args['context'] ) : 'agencies_archive';



$hvnly_name = (string) $hvnly_agency['name'];

$hvnly_logo = (string) ( $hvnly_agency['logo_url'] ?? '' );

$hvnly_location = (string) ( $hvnly_agency['location'] ?? '' );

$hvnly_profile = (string) ( $hvnly_agency['profile_url'] ?? '' );

$hvnly_agent_count = absint( $hvnly_agency['agent_count'] ?? 0 );

$hvnly_property_count = absint( $hvnly_agency['property_count'] ?? 0 );

$hvnly_term_id = absint( $hvnly_agency['id'] ?? 0 );



$hvnly_description = (string) ( $hvnly_agency['description'] ?? '' );

if ( '' === trim( $hvnly_description ) && $hvnly_term_id > 0 ) {

	$hvnly_term = get_term( $hvnly_term_id, 'hvnly_agent_agency' );

	if ( $hvnly_term instanceof WP_Term && ! empty( $hvnly_term->description ) ) {

		$hvnly_description = (string) $hvnly_term->description;

	}

}



$hvnly_excerpt = '' !== trim( $hvnly_description ) ? wp_trim_words( wp_strip_all_tags( $hvnly_description ), 18, '…' ) : '';



if ( '' === $hvnly_location && ! empty( $hvnly_agency['address'] ) ) {

	$hvnly_address = trim( (string) $hvnly_agency['address'] );

	$hvnly_parts = preg_split( '/[\r\n,]+/', $hvnly_address );

	if ( is_array( $hvnly_parts ) && ! empty( $hvnly_parts ) ) {

		$hvnly_location = trim( (string) end( $hvnly_parts ) );

	}

}



/**

 * Filter agency card excerpt text.

 *

 * @since 3.0.2

 *

 * @param string               $excerpt Excerpt.

 * @param array<string, mixed> $agency  Agency profile.

 */

$hvnly_excerpt = (string) apply_filters( 'hvnly_agency_card_excerpt', $hvnly_excerpt, $hvnly_agency );

?>

<article

	class="hvnly-agency-card hvnly-agency-card--<?php echo esc_attr( $hvnly_context ); ?> hvnly-agency-profile"

	data-agency-id="<?php echo esc_attr( (string) $hvnly_term_id ); ?>"

>

	<div class="hvnly-agency-card__media">

		<span class="hvnly-agency-card__media-bg" aria-hidden="true"></span>



		<a

			class="hvnly-agency-card__logo-link hvnly-agency-profile__head"

			href="<?php echo esc_url( $hvnly_profile ); ?>"

			tabindex="-1"

			aria-hidden="true"

		>

			<?php if ( $hvnly_logo ) : ?>

				<img

					class="hvnly-agency-card__logo hvnly-agency-profile__logo"

					src="<?php echo esc_url( $hvnly_logo ); ?>"

					alt=""

					width="88"

					height="88"

					loading="lazy"

					decoding="async"

				/>

			<?php else : ?>

				<div class="hvnly-agency-card__logo hvnly-agency-card__logo--placeholder hvnly-agency-profile__logo" aria-hidden="true">

					<i class="fas fa-building"></i>

				</div>

			<?php endif; ?>

		</a>



		<span class="hvnly-agency-card__overlay-badge hvnly-agency-card__overlay-badge--listings">

			<i class="fas fa-home" aria-hidden="true"></i>

			<?php

			printf(

				/* translators: %d: property count */

				esc_html( _n( '%d Listing', '%d Listings', $hvnly_property_count, 'havenlytics' ) ),

				absint( $hvnly_property_count )

			);

			?>

		</span>



		<span class="hvnly-agency-card__overlay-badge hvnly-agency-card__overlay-badge--team">

			<i class="fas fa-users" aria-hidden="true"></i>

			<?php

			printf(

				/* translators: %d: agent count */

				esc_html( _n( '%d Agent', '%d Agents', $hvnly_agent_count, 'havenlytics' ) ),

				absint( $hvnly_agent_count )

			);

			?>

		</span>

	</div>



	<div class="hvnly-agency-card__body">

		<div class="hvnly-agency-card__identity">

			<span class="hvnly-agency-card__label hvnly-agency-profile__label"><?php esc_html_e( 'Real Estate Agency', 'havenlytics' ); ?></span>



			<h2 class="hvnly-agency-card__name hvnly-agency-profile__name">

				<a href="<?php echo esc_url( $hvnly_profile ); ?>"><?php echo esc_html( $hvnly_name ); ?></a>

			</h2>



			<?php if ( $hvnly_location ) : ?>

				<p class="hvnly-agency-card__location">

					<i class="fas fa-map-marker-alt" aria-hidden="true"></i>

					<span><?php echo esc_html( $hvnly_location ); ?></span>

				</p>

			<?php endif; ?>

		</div>



		<?php if ( $hvnly_excerpt ) : ?>

			<p class="hvnly-agency-card__excerpt"><?php echo esc_html( $hvnly_excerpt ); ?></p>

		<?php endif; ?>



		<div class="hvnly-agency-card__stats">

			<span class="hvnly-agency-card__stat">

				<i class="fas fa-users" aria-hidden="true"></i>

				<?php

				printf(

					/* translators: %d: agent count */

					esc_html( _n( '%d Team Member', '%d Team Members', $hvnly_agent_count, 'havenlytics' ) ),

					absint( $hvnly_agent_count )

				);

				?>

			</span>

			<span class="hvnly-agency-card__stat">

				<i class="fas fa-building" aria-hidden="true"></i>

				<?php

				printf(

					/* translators: %d: property count */

					esc_html( _n( '%d Property', '%d Properties', $hvnly_property_count, 'havenlytics' ) ),

					absint( $hvnly_property_count )

				);

				?>

			</span>

		</div>



		<div class="hvnly-agency-card__actions">

			<a href="<?php echo esc_url( $hvnly_profile ); ?>" class="hvnly-btn hvnly-btn-primary hvnly-agency-card__btn">

				<?php esc_html_e( 'View Agency', 'havenlytics' ); ?>

				<i class="fas fa-arrow-right" aria-hidden="true"></i>

			</a>

		</div>

	</div>

</article>

