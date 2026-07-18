<?php
/**
 * Unified premium agent card (directory, agency team, property widget).
 *
 * @package     Havenlytics
 * @subpackage  Templates/partials/cards
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_agent = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();

if ( empty( $hvnly_agent['name'] ) ) {
	return;
}

$hvnly_context        = isset( $args['context'] ) ? sanitize_key( (string) $args['context'] ) : 'agents_archive';
$hvnly_property_id    = isset( $args['property_id'] ) ? absint( $args['property_id'] ) : 0;
$hvnly_instance       = isset( $args['instance'] ) && is_array( $args['instance'] ) ? $args['instance'] : array();
$hvnly_is_primary     = ! empty( $args['is_primary'] );
$hvnly_is_slide       = ! empty( $args['is_slide'] );
$hvnly_show_contact   = ! empty( $args['show_contact_agent'] );

$hvnly_id       = absint( $hvnly_agent['id'] ?? 0 );
$hvnly_name     = (string) $hvnly_agent['name'];
$hvnly_position = (string) ( $hvnly_agent['position'] ?? '' );
$hvnly_company  = (string) ( $hvnly_agent['company'] ?? '' );
$hvnly_email    = (string) ( $hvnly_agent['email'] ?? '' );
$hvnly_phone    = (string) ( $hvnly_agent['phone'] ?? '' );
$hvnly_whatsapp = (string) ( $hvnly_agent['whatsapp'] ?? '' );
$hvnly_website  = (string) ( $hvnly_agent['website'] ?? '' );
$hvnly_avatar   = (string) ( $hvnly_agent['avatar'] ?? '' );
$hvnly_profile  = (string) ( $hvnly_agent['profile_url'] ?? '' );
$hvnly_agency   = isset( $hvnly_agent['agency'] ) && is_array( $hvnly_agent['agency'] ) ? $hvnly_agent['agency'] : array();

if ( empty( $hvnly_profile ) && $hvnly_id ) {
	$hvnly_profile = (string) get_permalink( $hvnly_id );
}

if ( empty( $hvnly_agency['profile_url'] ) && ! empty( $hvnly_agency['id'] ) ) {
	$hvnly_term_link = get_term_link( (int) $hvnly_agency['id'], 'hvnly_agent_agency' );
	if ( ! is_wp_error( $hvnly_term_link ) ) {
		$hvnly_agency['profile_url'] = (string) $hvnly_term_link;
	}
}

$hvnly_property_count = function_exists( 'hvnly_get_agent_property_count' )
	? hvnly_get_agent_property_count( $hvnly_id )
	: 0;

$hvnly_status       = function_exists( 'hvnly_get_agent_availability_status' )
	? hvnly_get_agent_availability_status( $hvnly_id, $hvnly_agent )
	: 'available';
$hvnly_status_label = function_exists( 'hvnly_get_agent_availability_label' )
	? hvnly_get_agent_availability_label( $hvnly_status )
	: __( 'Available', 'havenlytics' );
$hvnly_badges       = function_exists( 'hvnly_get_agent_card_badges' )
	? hvnly_get_agent_card_badges( $hvnly_id, $hvnly_agent )
	: array();
$hvnly_experience   = function_exists( 'hvnly_get_agent_experience_label' )
	? hvnly_get_agent_experience_label( $hvnly_id, $hvnly_agent )
	: '';
$hvnly_location     = function_exists( 'hvnly_get_agent_location_label' )
	? hvnly_get_agent_location_label( $hvnly_id, $hvnly_agent )
	: '';

$hvnly_agency_name = (string) ( $hvnly_agency['name'] ?? $hvnly_company );

$hvnly_show_phone    = ( 'property' === $hvnly_context ) ? ( ! empty( $hvnly_instance['show_phone'] ) && $hvnly_phone ) : (bool) $hvnly_phone;
$hvnly_show_email    = ( 'property' === $hvnly_context ) ? ( ! empty( $hvnly_instance['show_email'] ) && $hvnly_email ) : false;
$hvnly_show_whatsapp = ( 'property' === $hvnly_context ) ? ( ! empty( $hvnly_instance['show_whatsapp'] ) && $hvnly_whatsapp ) : false;
$hvnly_show_website  = ( 'property' === $hvnly_context ) ? ( ! empty( $hvnly_instance['show_website'] ) && $hvnly_website ) : false;
$hvnly_show_profile  = ( 'property' === $hvnly_context ) ? ( ! empty( $hvnly_instance['show_profile_link'] ) && $hvnly_profile ) : (bool) $hvnly_profile;

$hvnly_show_rating = ( 'property' === $hvnly_context )
	&& ! empty( $hvnly_instance['show_rating'] )
	&& ( ! empty( $hvnly_instance['agent_rating_enabled'] ) || ( ! empty( $hvnly_agent['rating'] ) && (float) $hvnly_agent['rating'] > 0 ) );

$hvnly_rating  = ! empty( $hvnly_instance['agent_rating_enabled'] )
	? (float) ( $hvnly_instance['agent_rating'] ?? 0 )
	: (float) ( $hvnly_agent['rating'] ?? 0 );
$hvnly_reviews = ! empty( $hvnly_instance['agent_rating_enabled'] )
	? (int) ( $hvnly_instance['agent_reviews'] ?? 0 )
	: (int) ( $hvnly_agent['review_count'] ?? 0 );

$hvnly_card_classes = array(
	'hvnly-agent-card',
	'hvnly-agent-card--' . $hvnly_status,
	'hvnly-agent-card--' . $hvnly_context,
	'hvnly-agent-widget__card',
);

if ( $hvnly_is_slide ) {
	$hvnly_card_classes[] = 'hvnly-agent-widget__slide';
}
if ( $hvnly_is_primary ) {
	$hvnly_card_classes[] = 'hvnly-agent-widget__card--primary';
	$hvnly_card_classes[] = 'hvnly-agent-card--primary';
}

$hvnly_contact_url = function_exists( 'hvnly_get_agent_contact_url' )
	? hvnly_get_agent_contact_url( $hvnly_id, $hvnly_profile )
	: $hvnly_profile;
?>
<article
	class="<?php echo esc_attr( implode( ' ', $hvnly_card_classes ) ); ?>"
	data-agent-id="<?php echo esc_attr( (string) $hvnly_id ); ?>"
	data-agent-status="<?php echo esc_attr( $hvnly_status ); ?>"
	<?php echo ! empty( $hvnly_badges ) ? ' data-has-badges="true"' : ''; ?>
>
	<div class="hvnly-agent-card__media">
		<a class="hvnly-agent-card__photo-link" href="<?php echo esc_url( $hvnly_profile ); ?>" tabindex="-1" aria-hidden="true">
			<?php if ( $hvnly_avatar ) : ?>
				<img class="hvnly-agent-card__photo" src="<?php echo esc_url( $hvnly_avatar ); ?>" alt="" width="400" height="280" loading="lazy" decoding="async" />
			<?php else : ?>
				<div class="hvnly-agent-card__photo hvnly-agent-card__photo--placeholder" aria-hidden="true">
					<i class="fas fa-user-tie"></i>
				</div>
			<?php endif; ?>
			<span class="hvnly-agent-card__photo-overlay" aria-hidden="true"></span>
		</a>

		<?php if ( ! empty( $hvnly_badges ) || $hvnly_is_primary ) : ?>
			<div class="hvnly-agent-card__ribbon-group">
				<?php if ( $hvnly_is_primary ) : ?>
					<span class="hvnly-agent-card__ribbon hvnly-agent-card__ribbon--primary"><?php esc_html_e( 'Primary Agent', 'havenlytics' ); ?></span>
				<?php endif; ?>
				<?php foreach ( $hvnly_badges as $hvnly_badge ) : ?>
					<span class="hvnly-agent-card__ribbon hvnly-agent-card__ribbon--<?php echo esc_attr( (string) $hvnly_badge['type'] ); ?>">
						<?php echo esc_html( (string) $hvnly_badge['label'] ); ?>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<span class="hvnly-agent-card__overlay-badge hvnly-agent-card__overlay-badge--listings">
			<i class="fas fa-home" aria-hidden="true"></i>
			<?php
			printf(
				/* translators: %d: listing count */
				esc_html( _n( '%d Listing', '%d Listings', $hvnly_property_count, 'havenlytics' ) ),
				(int) $hvnly_property_count
			);
			?>
		</span>

		<span class="hvnly-agent-card__overlay-badge hvnly-agent-card__overlay-badge--status hvnly-agent-card__overlay-badge--<?php echo esc_attr( $hvnly_status ); ?>">
			<span class="hvnly-agent-card__status-dot" aria-hidden="true"></span>
			<?php echo esc_html( $hvnly_status_label ); ?>
		</span>
	</div>

	<div class="hvnly-agent-card__body">
		<div class="hvnly-agent-card__identity">
			<h2 class="hvnly-agent-card__name hvnly-agent-widget__name">
				<a href="<?php echo esc_url( $hvnly_profile ); ?>"><?php echo esc_html( $hvnly_name ); ?></a>
			</h2>

			<?php if ( $hvnly_position ) : ?>
				<p class="hvnly-agent-card__position hvnly-agent-widget__position"><?php echo esc_html( $hvnly_position ); ?></p>
			<?php endif; ?>

			<?php if ( $hvnly_agency_name ) : ?>
				<p class="hvnly-agent-card__agency hvnly-agent-widget__company">
					<?php if ( ! empty( $hvnly_agency['logo_url'] ) ) : ?>
						<img class="hvnly-agent-card__agency-logo" src="<?php echo esc_url( (string) $hvnly_agency['logo_url'] ); ?>" alt="" width="20" height="20" loading="lazy" decoding="async" />
					<?php endif; ?>
					<?php if ( ! empty( $hvnly_agency['profile_url'] ) ) : ?>
						<a href="<?php echo esc_url( (string) $hvnly_agency['profile_url'] ); ?>"><?php echo esc_html( $hvnly_agency_name ); ?></a>
					<?php else : ?>
						<span><?php echo esc_html( $hvnly_agency_name ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( $hvnly_location ) : ?>
				<p class="hvnly-agent-card__location">
					<i class="fas fa-map-marker-alt" aria-hidden="true"></i>
					<span><?php echo esc_html( $hvnly_location ); ?></span>
				</p>
			<?php endif; ?>
		</div>

		<?php if ( $hvnly_show_rating && $hvnly_rating > 0 ) : ?>
			<p class="hvnly-agent-card__rating hvnly-agent-widget__rating hvnly-property-single__agent-rating">
				<?php for ( $hvnly_star_index = 1; $hvnly_star_index <= 5; $hvnly_star_index++ ) : ?>
					<?php // Sprint 31D: `fa-star-o` is FA4 syntax — under FA5 the empty star rendered nothing. ?>
				<i class="<?php echo $hvnly_star_index <= floor( $hvnly_rating ) ? 'fas' : 'far'; ?> fa-star" aria-hidden="true"></i>
				<?php endfor; ?>
				<span>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: rating value, 2: review count */
							__( '%1$s (%2$s reviews)', 'havenlytics' ),
							number_format( $hvnly_rating, 1 ),
							number_format_i18n( $hvnly_reviews )
						)
					);
					?>
				</span>
			</p>
		<?php endif; ?>

		<?php if ( 'agents_archive' === $hvnly_context || 'agency_team' === $hvnly_context ) : ?>
			<div class="hvnly-agent-card__stats">
				<span class="hvnly-agent-card__stat">
					<i class="fas fa-building" aria-hidden="true"></i>
					<?php
					printf(
						/* translators: %d: property count */
						esc_html( _n( '%d Property', '%d Properties', $hvnly_property_count, 'havenlytics' ) ),
						(int) $hvnly_property_count
					);
					?>
				</span>
				<?php if ( $hvnly_experience ) : ?>
					<span class="hvnly-agent-card__stat">
						<i class="fas fa-award" aria-hidden="true"></i>
						<?php echo esc_html( $hvnly_experience ); ?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( 'property' === $hvnly_context && ( $hvnly_show_phone || $hvnly_show_email || $hvnly_show_whatsapp || $hvnly_show_website || $hvnly_show_profile ) ) : ?>
			<div class="hvnly-agent-card__actions hvnly-agent-card__actions--property hvnly-agent-widget__actions hvnly-property-single__agent-actions">
				<?php if ( $hvnly_show_phone ) : ?>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hvnly_phone ) ); ?>" class="hvnly-btn hvnly-btn-outline hvnly-agent-card__btn hvnly-agent-widget__btn hvnly-property-single__agent-btn">
						<i class="fas fa-phone-alt" aria-hidden="true"></i>
						<?php esc_html_e( 'Call', 'havenlytics' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $hvnly_show_email ) : ?>
					<a href="mailto:<?php echo esc_attr( $hvnly_email ); ?>" class="hvnly-btn hvnly-btn-outline hvnly-agent-card__btn hvnly-agent-widget__btn hvnly-property-single__agent-btn hvnly-property-single__agent-btn--secondary">
						<i class="fas fa-envelope" aria-hidden="true"></i>
						<?php esc_html_e( 'Email', 'havenlytics' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $hvnly_show_whatsapp ) : ?>
					<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $hvnly_whatsapp ) ); ?>" class="hvnly-btn hvnly-btn-outline hvnly-agent-card__btn hvnly-agent-widget__btn hvnly-property-single__agent-btn hvnly-property-single__agent-btn--secondary" target="_blank" rel="noopener noreferrer">
						<i class="fab fa-whatsapp" aria-hidden="true"></i>
						<?php esc_html_e( 'WhatsApp', 'havenlytics' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $hvnly_show_profile ) : ?>
					<a href="<?php echo esc_url( $hvnly_profile ); ?>" class="hvnly-btn hvnly-btn-outline hvnly-agent-card__btn hvnly-agent-widget__btn hvnly-property-single__agent-btn hvnly-property-single__agent-btn--secondary">
						<i class="fas fa-user" aria-hidden="true"></i>
						<?php esc_html_e( 'View Profile', 'havenlytics' ); ?>
					</a>
				<?php endif; ?>
				<?php if ( $hvnly_show_website ) : ?>
					<a href="<?php echo esc_url( $hvnly_website ); ?>" class="hvnly-btn hvnly-btn-outline hvnly-agent-card__btn hvnly-agent-widget__btn hvnly-property-single__agent-btn hvnly-property-single__agent-btn--secondary" target="_blank" rel="noopener noreferrer">
						<i class="fas fa-globe" aria-hidden="true"></i>
						<?php esc_html_e( 'Website', 'havenlytics' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php elseif ( in_array( $hvnly_context, array( 'agents_archive', 'agency_team' ), true ) ) : ?>
			<div class="hvnly-agent-card__actions">
				<?php if ( $hvnly_show_profile ) : ?>
					<a href="<?php echo esc_url( $hvnly_profile ); ?>" class="hvnly-btn hvnly-btn-primary hvnly-agent-card__btn hvnly-agent-card__btn--profile">
						<?php esc_html_e( 'View Profile', 'havenlytics' ); ?>
						<i class="fas fa-arrow-right" aria-hidden="true"></i>
					</a>
				<?php endif; ?>
				<?php if ( $hvnly_contact_url ) : ?>
					<a href="<?php echo esc_url( $hvnly_contact_url ); ?>" class="hvnly-btn hvnly-btn-outline hvnly-agent-card__btn hvnly-agent-card__btn--contact">
						<i class="fas fa-comment-dots" aria-hidden="true"></i>
						<?php esc_html_e( 'Contact Agent', 'havenlytics' ); ?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php
		if ( $hvnly_show_contact && function_exists( 'hvnly_is_contact_agent_enabled' ) && hvnly_is_contact_agent_enabled() && function_exists( 'hvnly_render_contact_agent_button' ) && $hvnly_property_id > 0 ) :
			?>
			<div class="hvnly-agent-card__contact-modal hvnly-property-single__agent-actions hvnly-property-single__agent-actions--contact-agent">
				<?php
				hvnly_render_contact_agent_button(
					$hvnly_property_id,
					array(
						'agent_name'     => $hvnly_name,
						'agent_id'       => $hvnly_id,
						'agent_type'     => isset( $hvnly_agent['type'] ) ? (string) $hvnly_agent['type'] : (string) ( $hvnly_agent['source'] ?? 'cpt' ),
						'agent_avatar'   => $hvnly_avatar,
						'agent_position' => $hvnly_position,
						'class'          => 'hvnly-btn hvnly-btn-primary hvnly-agent-card__btn hvnly-agent-card__btn--contact hvnly-property-single__agent-btn hvnly-property-single__agent-btn--contact hvnly-agent-widget__contact-btn',
					)
				);
				?>
			</div>
		<?php endif; ?>

		<?php
		if ( 'property' === $hvnly_context ) {
			$hvnly_social_links = array(
				'facebook'  => isset( $hvnly_agent['facebook'] ) ? (string) $hvnly_agent['facebook'] : (string) ( $hvnly_instance['social_facebook'] ?? '' ),
				'linkedin'  => isset( $hvnly_agent['linkedin'] ) ? (string) $hvnly_agent['linkedin'] : (string) ( $hvnly_instance['social_linkedin'] ?? '' ),
				'instagram' => isset( $hvnly_agent['instagram'] ) ? (string) $hvnly_agent['instagram'] : (string) ( $hvnly_instance['social_instagram'] ?? '' ),
			);
			$hvnly_social_icons = array(
				'facebook'  => 'fab fa-facebook-f',
				'linkedin'  => 'fab fa-linkedin-in',
				'instagram' => 'fab fa-instagram',
			);
			$hvnly_has_social = ! empty( $hvnly_instance['show_social'] ) && ! empty( array_filter( $hvnly_social_links ) );

			if ( $hvnly_has_social ) :
				?>
				<div class="hvnly-agent-card__social hvnly-agent-widget__social hvnly-property-single__agent-social">
					<div class="hvnly-property-single__social-links">
						<?php foreach ( $hvnly_social_links as $hvnly_platform => $hvnly_social_url ) : ?>
							<?php if ( $hvnly_social_url ) : ?>
								<a href="<?php echo esc_url( $hvnly_social_url ); ?>" class="hvnly-property-single__social-link hvnly-property-single__social-link--<?php echo esc_attr( $hvnly_platform ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( ucfirst( $hvnly_platform ) ); ?>">
									<i class="<?php echo esc_attr( $hvnly_social_icons[ $hvnly_platform ] ); ?>" aria-hidden="true"></i>
									<span class="screen-reader-text"><?php echo esc_html( ucfirst( $hvnly_platform ) ); ?></span>
								</a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			endif;
		}
		?>
	</div>
</article>
