<?php
/**
 * Agency single hero section.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-agency/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_agency  = isset( $args['agency'] ) && is_array( $args['agency'] ) ? $args['agency'] : array();
$hvnly_term    = isset( $args['term'] ) && $args['term'] instanceof WP_Term ? $args['term'] : null;
$hvnly_name    = (string) ( $hvnly_agency['name'] ?? ( $hvnly_term ? $hvnly_term->name : '' ) );
$hvnly_logo    = (string) ( $hvnly_agency['logo_url'] ?? '' );
$hvnly_desc    = (string) ( $hvnly_agency['description'] ?? ( $hvnly_term ? $hvnly_term->description : '' ) );
$hvnly_website = (string) ( $hvnly_agency['website'] ?? '' );
$hvnly_email   = (string) ( $hvnly_agency['email'] ?? '' );
$hvnly_phone   = (string) ( $hvnly_agency['phone'] ?? '' );
$hvnly_mobile  = (string) ( $hvnly_agency['mobile'] ?? '' );
$hvnly_address = (string) ( $hvnly_agency['address'] ?? '' );
$hvnly_location = (string) ( $hvnly_agency['location'] ?? '' );

if ( empty( $hvnly_name ) ) {
	return;
}
?>
<section class="hvnly-agency-single__hero">
	<div class="hvnly-agency-single__hero-inner">
		<div class="hvnly-agency-single__brand">
			<?php if ( $hvnly_logo ) : ?>
				<img class="hvnly-agency-single__logo" src="<?php echo esc_url( $hvnly_logo ); ?>" alt="" width="120" height="120" loading="eager" decoding="async" />
			<?php else : ?>
				<div class="hvnly-agency-single__logo hvnly-agency-single__logo--placeholder" aria-hidden="true">
					<i class="fas fa-building"></i>
				</div>
			<?php endif; ?>

			<div class="hvnly-agency-single__identity">
				<span class="hvnly-agency-profile__label"><?php esc_html_e( 'Agency', 'havenlytics' ); ?></span>
				<h1 class="hvnly-agency-single__name"><?php echo esc_html( $hvnly_name ); ?></h1>
				<?php if ( $hvnly_location ) : ?>
					<p class="hvnly-agency-single__location">
						<i class="fas fa-map-marker-alt" aria-hidden="true"></i>
						<span><?php echo esc_html( $hvnly_location ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( $hvnly_desc ) : ?>
			<div class="hvnly-agency-single__description">
				<?php echo wp_kses_post( wpautop( $hvnly_desc ) ); ?>
			</div>
		<?php endif; ?>

		<div class="hvnly-agency-single__contact">
			<?php if ( $hvnly_email ) : ?>
				<a class="hvnly-agency-single__contact-link" href="mailto:<?php echo esc_attr( $hvnly_email ); ?>">
					<i class="fas fa-envelope" aria-hidden="true"></i>
					<span><?php echo esc_html( $hvnly_email ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $hvnly_phone ) : ?>
				<a class="hvnly-agency-single__contact-link" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hvnly_phone ) ); ?>">
					<i class="fas fa-phone-alt" aria-hidden="true"></i>
					<span><?php echo esc_html( $hvnly_phone ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $hvnly_mobile && $hvnly_mobile !== $hvnly_phone ) : ?>
				<a class="hvnly-agency-single__contact-link" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hvnly_mobile ) ); ?>">
					<i class="fas fa-mobile-alt" aria-hidden="true"></i>
					<span><?php echo esc_html( $hvnly_mobile ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $hvnly_address ) : ?>
				<span class="hvnly-agency-single__contact-link hvnly-agency-single__contact-link--text">
					<i class="fas fa-map-marker-alt" aria-hidden="true"></i>
					<span><?php echo esc_html( $hvnly_address ); ?></span>
				</span>
			<?php endif; ?>
			<?php if ( $hvnly_website ) : ?>
				<a class="hvnly-agency-single__contact-link hvnly-agency-single__website" href="<?php echo esc_url( $hvnly_website ); ?>" target="_blank" rel="noopener noreferrer">
					<i class="fas fa-globe" aria-hidden="true"></i>
					<span><?php esc_html_e( 'Visit Website', 'havenlytics' ); ?></span>
				</a>
			<?php endif; ?>
		</div>
	</div>
</section>
