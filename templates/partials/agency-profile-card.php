<?php
/**
 * Reusable agency profile card (logo, contact, address).
 *
 * @package     Havenlytics
 * @subpackage  Templates/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_agency  = isset( $args['agency'] ) && is_array( $args['agency'] ) ? $args['agency'] : array();
$hvnly_context = isset( $args['context'] ) ? sanitize_key( (string) $args['context'] ) : 'card';

if ( empty( $hvnly_agency['name'] ) ) {
	return;
}

$hvnly_name    = (string) $hvnly_agency['name'];
$hvnly_logo    = (string) ( $hvnly_agency['logo_url'] ?? '' );
$hvnly_address = (string) ( $hvnly_agency['address'] ?? '' );
$hvnly_email   = (string) ( $hvnly_agency['email'] ?? '' );
$hvnly_phone   = (string) ( $hvnly_agency['phone'] ?? '' );
$hvnly_mobile  = (string) ( $hvnly_agency['mobile'] ?? '' );
$hvnly_office  = (string) ( $hvnly_agency['office'] ?? '' );
$hvnly_fax     = (string) ( $hvnly_agency['fax'] ?? '' );
$hvnly_website     = (string) ( $hvnly_agency['website'] ?? '' );
$hvnly_profile_url = (string) ( $hvnly_agency['profile_url'] ?? '' );
$hvnly_license     = (string) ( $hvnly_agency['license'] ?? '' );

if ( '' === $hvnly_profile_url && ! empty( $hvnly_agency['id'] ) ) {
	$hvnly_term_link = get_term_link( (int) $hvnly_agency['id'], 'hvnly_agent_agency' );
	if ( ! is_wp_error( $hvnly_term_link ) ) {
		$hvnly_profile_url = (string) $hvnly_term_link;
	}
}

$hvnly_details = array();

if ( $hvnly_email ) {
	$hvnly_details[] = array(
		'icon'  => 'fas fa-envelope',
		'label' => __( 'Email', 'havenlytics' ),
		'value' => $hvnly_email,
		'href'  => 'mailto:' . $hvnly_email,
	);
}
if ( $hvnly_phone ) {
	$hvnly_details[] = array(
		'icon'  => 'fas fa-phone-alt',
		'label' => __( 'Phone', 'havenlytics' ),
		'value' => $hvnly_phone,
		'href'  => 'tel:' . preg_replace( '/[^0-9+]/', '', $hvnly_phone ),
	);
}
if ( $hvnly_mobile && $hvnly_mobile !== $hvnly_phone ) {
	$hvnly_details[] = array(
		'icon'  => 'fas fa-mobile-alt',
		'label' => __( 'Mobile', 'havenlytics' ),
		'value' => $hvnly_mobile,
		'href'  => 'tel:' . preg_replace( '/[^0-9+]/', '', $hvnly_mobile ),
	);
}
if ( $hvnly_office && $hvnly_office !== $hvnly_phone && $hvnly_office !== $hvnly_mobile ) {
	$hvnly_details[] = array(
		'icon'  => 'fas fa-building',
		'label' => __( 'Office', 'havenlytics' ),
		'value' => $hvnly_office,
		'href'  => 'tel:' . preg_replace( '/[^0-9+]/', '', $hvnly_office ),
	);
}
if ( $hvnly_fax ) {
	$hvnly_details[] = array(
		'icon'  => 'fas fa-fax',
		'label' => __( 'Fax', 'havenlytics' ),
		'value' => $hvnly_fax,
		'href'  => '',
	);
}
if ( $hvnly_address ) {
	$hvnly_details[] = array(
		'icon'  => 'fas fa-map-marker-alt',
		'label' => __( 'Address', 'havenlytics' ),
		'value' => $hvnly_address,
		'href'  => '',
	);
}
if ( $hvnly_license ) {
	$hvnly_details[] = array(
		'icon'  => 'fas fa-id-card',
		'label' => __( 'License', 'havenlytics' ),
		'value' => $hvnly_license,
		'href'  => '',
	);
}
if ( $hvnly_website ) {
	$hvnly_details[] = array(
		'icon'  => 'fas fa-globe',
		'label' => __( 'Website', 'havenlytics' ),
		'value' => $hvnly_website,
		'href'  => $hvnly_website,
	);
}

$hvnly_card_class = 'hvnly-agency-profile';
if ( 'inline' === $hvnly_context ) {
	$hvnly_card_class .= ' hvnly-agency-profile--inline';
}
?>
<section class="<?php echo esc_attr( $hvnly_card_class ); ?>">
	<div class="hvnly-agency-profile__head">
		<?php if ( $hvnly_logo ) : ?>
			<div class="hvnly-agency-profile__logo-wrap">
				<img class="hvnly-agency-profile__logo" src="<?php echo esc_url( $hvnly_logo ); ?>" alt="" width="72" height="72" loading="lazy" decoding="async" />
			</div>
		<?php endif; ?>

		<div class="hvnly-agency-profile__identity">
			<span class="hvnly-agency-profile__label"><?php esc_html_e( 'Agency', 'havenlytics' ); ?></span>
			<h3 class="hvnly-agency-profile__name">
				<?php if ( $hvnly_profile_url ) : ?>
					<a href="<?php echo esc_url( $hvnly_profile_url ); ?>"><?php echo esc_html( $hvnly_name ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $hvnly_name ); ?>
				<?php endif; ?>
			</h3>
		</div>
	</div>

	<?php if ( ! empty( $hvnly_details ) ) : ?>
		<div class="hvnly-agency-profile__details">
			<?php foreach ( $hvnly_details as $hvnly_detail ) : ?>
				<div class="hvnly-agency-profile__detail">
					<span class="hvnly-agency-profile__detail-icon" aria-hidden="true"><i class="<?php echo esc_attr( (string) $hvnly_detail['icon'] ); ?>"></i></span>
					<div class="hvnly-agency-profile__detail-body">
						<span class="hvnly-agency-profile__detail-label"><?php echo esc_html( (string) $hvnly_detail['label'] ); ?></span>
						<?php if ( ! empty( $hvnly_detail['href'] ) ) : ?>
							<a class="hvnly-agency-profile__detail-value" href="<?php echo esc_url( (string) $hvnly_detail['href'] ); ?>"<?php echo 0 === strpos( (string) $hvnly_detail['href'], 'http' ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
								<?php echo esc_html( (string) $hvnly_detail['value'] ); ?>
							</a>
						<?php else : ?>
							<span class="hvnly-agency-profile__detail-value"><?php echo esc_html( (string) $hvnly_detail['value'] ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>
