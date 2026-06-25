<?php

/**

 * Sidebar contact form for agent profile pages.

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



$hvnly_agent_id = isset( $hvnly_agent['id'] ) ? absint( $hvnly_agent['id'] ) : 0;

$hvnly_name     = (string) ( $hvnly_agent['name'] ?? '' );

$hvnly_phone    = (string) ( $hvnly_agent['phone'] ?? '' );

$hvnly_avatar   = (string) ( $hvnly_agent['avatar'] ?? '' );

$hvnly_position = (string) ( $hvnly_agent['position'] ?? '' );

$hvnly_company  = (string) ( $hvnly_agent['company'] ?? '' );

$hvnly_form_id  = isset( $args['form_id'] ) ? sanitize_html_class( (string) $args['form_id'] ) : wp_unique_id( 'hvnly-agent-single-form-' );



if ( ! $hvnly_agent_id || ! $hvnly_name ) {

	return;

}



$hvnly_show_form = function_exists( 'hvnly_is_contact_agent_enabled' ) && hvnly_is_contact_agent_enabled();
$hvnly_availability = function_exists( 'hvnly_get_agent_availability_status' )
	? hvnly_get_agent_availability_status( $hvnly_agent_id, $hvnly_agent )
	: 'available';
$hvnly_accepts_inquiries = function_exists( 'hvnly_agent_accepts_inquiries' )
	? hvnly_agent_accepts_inquiries( $hvnly_agent_id, $hvnly_agent )
	: true;
$hvnly_availability_notice = function_exists( 'hvnly_get_agent_availability_notice' )
	? hvnly_get_agent_availability_notice( $hvnly_availability )
	: '';

?>

<aside class="hvnly-agent-single__sidebar-card" id="hvnly-agent-contact">

	<div class="hvnly-agent-single__sidebar-agent">

		<?php if ( $hvnly_avatar ) : ?>

			<img class="hvnly-agent-single__sidebar-avatar" src="<?php echo esc_url( $hvnly_avatar ); ?>" alt="" width="72" height="72" loading="lazy" decoding="async" />

		<?php endif; ?>

		<div class="hvnly-agent-single__sidebar-info">

			<div class="hvnly-agent-single__sidebar-name"><?php echo esc_html( $hvnly_name ); ?></div>

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

			<?php
			$hvnly_agency = isset( $hvnly_agent['agency'] ) && is_array( $hvnly_agent['agency'] ) ? $hvnly_agent['agency'] : array();
			if ( empty( $hvnly_agency['name'] ) && $hvnly_agent_id && function_exists( 'hvnly_get_agent_agency' ) ) {
				$hvnly_agency = hvnly_get_agent_agency( $hvnly_agent_id );
			}
			?>

			<?php if ( ! empty( $hvnly_agency['name'] ) ) : ?>
				<div class="hvnly-agent-single__sidebar-agency">
					<?php if ( ! empty( $hvnly_agency['logo_url'] ) ) : ?>
						<img class="hvnly-agent-single__sidebar-agency-logo" src="<?php echo esc_url( (string) $hvnly_agency['logo_url'] ); ?>" alt="" width="20" height="20" loading="lazy" decoding="async" />
					<?php endif; ?>
					<span><?php echo esc_html( (string) $hvnly_agency['name'] ); ?></span>
				</div>
			<?php elseif ( $hvnly_company ) : ?>

				<div class="hvnly-agent-single__sidebar-company"><?php echo esc_html( $hvnly_company ); ?></div>

			<?php elseif ( $hvnly_position ) : ?>

				<div class="hvnly-agent-single__sidebar-company"><?php echo esc_html( $hvnly_position ); ?></div>

			<?php endif; ?>

			<?php if ( $hvnly_phone ) : ?>

				<div class="hvnly-agent-single__sidebar-phone">

					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hvnly_phone ) ); ?>"><?php echo esc_html( $hvnly_phone ); ?></a>

				</div>

			<?php endif; ?>

		</div>

	</div>



	<?php if ( $hvnly_show_form ) : ?>

		<div class="hvnly-agent-single__contact-panel">

			<h3 class="hvnly-agent-single__contact-title"><?php esc_html_e( 'Send a Message', 'havenlytics' ); ?></h3>

			<p class="hvnly-agent-single__contact-subtitle">

				<?php esc_html_e( 'Have a question? Contact this agent directly.', 'havenlytics' ); ?>

			</p>

			<?php if ( $hvnly_availability_notice ) : ?>
				<div class="hvnly-contact-agent__availability-notice hvnly-contact-agent__availability-notice--<?php echo esc_attr( $hvnly_availability ); ?>" role="note">
					<?php echo esc_html( $hvnly_availability_notice ); ?>
				</div>
			<?php endif; ?>

			<form

				id="<?php echo esc_attr( $hvnly_form_id ); ?>"

				class="hvnly-agent-single__form js-hvnly-contact-agent-form js-hvnly-contact-agent-form-inline"

				method="post"

				action="#"

				novalidate

			>

				<input type="hidden" name="property_id" value="0" />

				<input type="hidden" name="agent_id" class="js-hvnly-contact-agent-id" value="<?php echo esc_attr( (string) $hvnly_agent_id ); ?>" />



				<div class="hvnly-agent-single__field hvnly-contact-agent__field--honeypot" aria-hidden="true">

					<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-website"><?php esc_html_e( 'Website', 'havenlytics' ); ?></label>

					<input type="text" id="<?php echo esc_attr( $hvnly_form_id ); ?>-website" name="hvnly_contact_website" class="js-hvnly-contact-agent-honeypot" tabindex="-1" autocomplete="off" />

				</div>



				<div class="hvnly-agent-single__field">

					<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-name"><?php esc_html_e( 'Your Name', 'havenlytics' ); ?> <span class="hvnly-agent-single__required">*</span></label>

					<input type="text" id="<?php echo esc_attr( $hvnly_form_id ); ?>-name" name="sender_name" required autocomplete="name" />

				</div>



				<div class="hvnly-agent-single__field">

					<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-phone"><?php esc_html_e( 'Phone', 'havenlytics' ); ?></label>

					<input type="tel" id="<?php echo esc_attr( $hvnly_form_id ); ?>-phone" name="sender_phone" autocomplete="tel" inputmode="tel" />

				</div>



				<div class="hvnly-agent-single__field">

					<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-email"><?php esc_html_e( 'Email', 'havenlytics' ); ?> <span class="hvnly-agent-single__required">*</span></label>

					<input type="email" id="<?php echo esc_attr( $hvnly_form_id ); ?>-email" name="sender_email" required autocomplete="email" inputmode="email" />

				</div>



				<div class="hvnly-agent-single__field">

					<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-message"><?php esc_html_e( 'Message', 'havenlytics' ); ?> <span class="hvnly-agent-single__required">*</span></label>

					<textarea id="<?php echo esc_attr( $hvnly_form_id ); ?>-message" name="message" rows="4" required><?php

						echo esc_textarea(

							sprintf(

								/* translators: %s: agent name */

								__( "Hello %s,\n\n", 'havenlytics' ),

								$hvnly_name

							)

						);

					?></textarea>

				</div>



				<div class="hvnly-agent-single__feedback js-hvnly-contact-agent-feedback" role="status" aria-live="polite" hidden></div>



				<div class="hvnly-agent-single__form-actions">

					<button type="submit" class="hvnly-agent-single__submit js-hvnly-contact-agent-submit"<?php echo ! $hvnly_accepts_inquiries ? ' disabled' : ''; ?>>

						<i class="fas fa-paper-plane" aria-hidden="true"></i>

						<?php esc_html_e( 'Send Message', 'havenlytics' ); ?>

					</button>



					<?php if ( $hvnly_phone ) : ?>

						<a class="hvnly-agent-single__call-btn" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $hvnly_phone ) ); ?>">

							<i class="fas fa-phone-alt" aria-hidden="true"></i>

							<?php esc_html_e( 'Call', 'havenlytics' ); ?>

						</a>

					<?php endif; ?>

				</div>

			</form>

		</div>

	<?php endif; ?>

</aside>

