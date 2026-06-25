<?php
/**
 * Full agent profile card for main-content agents section.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use HvnlyNab\Agent\PropertyAgentWidgetRenderer;

$hvnly_agent      = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();
$hvnly_is_primary = ! empty( $args['is_primary'] );
$hvnly_layout     = isset( $args['layout'] ) ? sanitize_key( (string) $args['layout'] ) : 'compact';

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
$hvnly_profile    = (string) ( $hvnly_agent['profile_url'] ?? '' );
$hvnly_bio        = (string) ( $hvnly_agent['biography'] ?? '' );
$hvnly_excerpt    = (string) ( $hvnly_agent['excerpt'] ?? '' );
$hvnly_agency     = isset( $hvnly_agent['agency'] ) && is_array( $hvnly_agent['agency'] ) ? $hvnly_agent['agency'] : array();

if ( empty( $hvnly_agency['name'] ) && ! empty( $hvnly_agent['id'] ) && function_exists( 'hvnly_get_agent_agency' ) ) {
	$hvnly_agency = hvnly_get_agent_agency( (int) $hvnly_agent['id'] );
}

if ( '' === trim( $hvnly_bio ) && '' !== trim( $hvnly_excerpt ) ) {
	$hvnly_bio = $hvnly_excerpt;
}

$hvnly_instance = array(
	'show_website' => true,
	'show_social'  => true,
);

$hvnly_social_links = PropertyAgentWidgetRenderer::resolve_social_links( $hvnly_agent, $hvnly_instance );

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
if ( $hvnly_mobile ) {
	$hvnly_details[] = array(
		'icon'  => 'fas fa-mobile-alt',
		'label' => __( 'Mobile', 'havenlytics' ),
		'value' => $hvnly_mobile,
		'href'  => 'tel:' . preg_replace( '/[^0-9+]/', '', $hvnly_mobile ),
	);
}
if ( $hvnly_office ) {
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
if ( $hvnly_whatsapp ) {
	$hvnly_details[] = array(
		'icon'  => 'fab fa-whatsapp',
		'label' => __( 'WhatsApp', 'havenlytics' ),
		'value' => $hvnly_whatsapp,
		'href'  => 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $hvnly_whatsapp ),
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

$hvnly_card_class = 'hvnly-agents-section__card';
if ( $hvnly_is_primary ) {
	$hvnly_card_class .= ' hvnly-agents-section__card--primary';
}
if ( 'featured' === $hvnly_layout ) {
	$hvnly_card_class .= ' hvnly-agents-section__card--featured';
}
?>
<article class="<?php echo esc_attr( $hvnly_card_class ); ?>">
	<div class="hvnly-agents-section__card-inner">
		<div class="hvnly-agents-section__profile">
			<div class="hvnly-agents-section__avatar-wrap">
				<?php if ( $hvnly_avatar ) : ?>
					<img class="hvnly-agents-section__avatar" src="<?php echo esc_url( $hvnly_avatar ); ?>" alt="" width="120" height="120" loading="lazy" decoding="async" />
				<?php else : ?>
					<div class="hvnly-agents-section__avatar hvnly-agents-section__avatar--placeholder" aria-hidden="true">
						<i class="fas fa-user-circle"></i>
					</div>
				<?php endif; ?>
			</div>

			<div class="hvnly-agents-section__identity">
				<?php if ( $hvnly_is_primary ) : ?>
					<span class="hvnly-agents-section__badge"><?php esc_html_e( 'Primary Agent', 'havenlytics' ); ?></span>
				<?php endif; ?>

				<h3 class="hvnly-agents-section__name"><?php echo esc_html( $hvnly_name ); ?></h3>

				<?php if ( $hvnly_position ) : ?>
					<p class="hvnly-agents-section__position"><?php echo esc_html( $hvnly_position ); ?></p>
				<?php endif; ?>

				<?php if ( $hvnly_company ) : ?>
					<p class="hvnly-agents-section__company"><?php echo esc_html( $hvnly_company ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $hvnly_agency['name'] ) ) : ?>
					<p class="hvnly-agents-section__agency">
						<?php if ( ! empty( $hvnly_agency['logo_url'] ) ) : ?>
							<img class="hvnly-agents-section__agency-logo" src="<?php echo esc_url( (string) $hvnly_agency['logo_url'] ); ?>" alt="" width="24" height="24" loading="lazy" decoding="async" />
						<?php endif; ?>
						<span><?php echo esc_html( (string) $hvnly_agency['name'] ); ?></span>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( ! empty( $hvnly_agency['name'] ) ) : ?>
			<?php
			hvnly_get_template(
				'partials/agency-profile-card.php',
				array(
					'agency'  => $hvnly_agency,
					'context' => 'inline',
				)
			);
			?>
		<?php endif; ?>

		<?php if ( ! empty( $hvnly_details ) ) : ?>
			<div class="hvnly-agents-section__details">
				<?php foreach ( $hvnly_details as $hvnly_detail ) : ?>
					<div class="hvnly-agents-section__detail">
						<span class="hvnly-agents-section__detail-icon" aria-hidden="true"><i class="<?php echo esc_attr( (string) $hvnly_detail['icon'] ); ?>"></i></span>
						<div class="hvnly-agents-section__detail-body">
							<span class="hvnly-agents-section__detail-label"><?php echo esc_html( (string) $hvnly_detail['label'] ); ?></span>
							<?php if ( ! empty( $hvnly_detail['href'] ) ) : ?>
								<a class="hvnly-agents-section__detail-value" href="<?php echo esc_url( (string) $hvnly_detail['href'] ); ?>"<?php echo 0 === strpos( (string) $hvnly_detail['href'], 'http' ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
									<?php echo esc_html( (string) $hvnly_detail['value'] ); ?>
								</a>
							<?php else : ?>
								<span class="hvnly-agents-section__detail-value"><?php echo esc_html( (string) $hvnly_detail['value'] ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== trim( wp_strip_all_tags( $hvnly_bio ) ) ) : ?>
			<div class="hvnly-agents-section__bio">
				<?php echo wp_kses_post( wpautop( $hvnly_bio ) ); ?>
			</div>
		<?php endif; ?>

		<div class="hvnly-agents-section__actions">
			<?php if ( $hvnly_phone ) : ?>
				<a class="hvnly-agents-section__btn hvnly-agents-section__btn--primary" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hvnly_phone ) ); ?>">
					<i class="fas fa-phone-alt" aria-hidden="true"></i>
					<?php esc_html_e( 'Call', 'havenlytics' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $hvnly_whatsapp ) : ?>
				<a class="hvnly-agents-section__btn" href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $hvnly_whatsapp ) ); ?>" target="_blank" rel="noopener noreferrer">
					<i class="fab fa-whatsapp" aria-hidden="true"></i>
					<?php esc_html_e( 'WhatsApp', 'havenlytics' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $hvnly_email ) : ?>
				<a class="hvnly-agents-section__btn" href="mailto:<?php echo esc_attr( $hvnly_email ); ?>">
					<i class="fas fa-envelope" aria-hidden="true"></i>
					<?php esc_html_e( 'Email', 'havenlytics' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $hvnly_profile ) : ?>
				<a class="hvnly-agents-section__btn" href="<?php echo esc_url( $hvnly_profile ); ?>">
					<i class="fas fa-user" aria-hidden="true"></i>
					<?php esc_html_e( 'View Profile', 'havenlytics' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( $hvnly_website ) : ?>
				<a class="hvnly-agents-section__btn" href="<?php echo esc_url( $hvnly_website ); ?>" target="_blank" rel="noopener noreferrer">
					<i class="fas fa-globe" aria-hidden="true"></i>
					<?php esc_html_e( 'Website', 'havenlytics' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $hvnly_social_links ) ) : ?>
			<div class="hvnly-agents-section__social">
				<?php foreach ( $hvnly_social_links as $hvnly_platform => $hvnly_link ) : ?>
					<a
						class="hvnly-agents-section__social-link hvnly-agents-section__social-link--<?php echo esc_attr( $hvnly_platform ); ?>"
						href="<?php echo esc_url( (string) $hvnly_link['url'] ); ?>"
						target="_blank"
						rel="noopener noreferrer"
						title="<?php echo esc_attr( (string) $hvnly_link['label'] ); ?>"
					>
						<i class="<?php echo esc_attr( (string) $hvnly_link['icon'] ); ?>" aria-hidden="true"></i>
						<span class="screen-reader-text"><?php echo esc_html( (string) $hvnly_link['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</article>
