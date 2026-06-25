<?php
/**
 * Contact Agent inquiry form partial.
 *
 * @package     Havenlytics
 * @subpackage  Templates/single-property/partials
 * @since       3.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hvnly_property_id    = isset( $args['property_id'] ) ? absint( $args['property_id'] ) : 0;
$hvnly_property_title = isset( $args['property_title'] ) ? (string) $args['property_title'] : '';
$hvnly_agent          = isset( $args['agent'] ) && is_array( $args['agent'] ) ? $args['agent'] : array();
$hvnly_agents         = isset( $args['agents'] ) && is_array( $args['agents'] ) ? $args['agents'] : array();

if ( empty( $hvnly_agents ) && ! empty( $hvnly_agent ) ) {
	$hvnly_agents = array( $hvnly_agent );
}

$hvnly_primary      = ! empty( $hvnly_agents[0] ) ? $hvnly_agents[0] : $hvnly_agent;
$hvnly_primary_id   = isset( $hvnly_primary['id'] ) ? absint( $hvnly_primary['id'] ) : 0;
$hvnly_primary_type = isset( $hvnly_primary['type'] ) ? sanitize_key( (string) $hvnly_primary['type'] ) : 'user';

if ( 'cpt' !== $hvnly_primary_type && ! empty( $hvnly_primary['source'] ) && 'cpt' === $hvnly_primary['source'] ) {
	$hvnly_primary_type = 'cpt';
}

$hvnly_agent_name = isset( $hvnly_primary['name'] ) ? (string) $hvnly_primary['name'] : '';
$hvnly_has_multi  = count( $hvnly_agents ) > 1;

$hvnly_availability = function_exists( 'hvnly_get_agent_availability_status' )
	? hvnly_get_agent_availability_status( $hvnly_primary_id, $hvnly_primary )
	: 'available';
$hvnly_accepts_inquiries = function_exists( 'hvnly_agent_accepts_inquiries' )
	? hvnly_agent_accepts_inquiries( $hvnly_primary_id, $hvnly_primary )
	: true;
$hvnly_availability_notice = function_exists( 'hvnly_get_agent_availability_notice' )
	? hvnly_get_agent_availability_notice( $hvnly_availability )
	: '';

if ( ! $hvnly_property_id ) {
	return;
}

$hvnly_form_id = 'hvnly-contact-agent-form-' . $hvnly_property_id;
$hvnly_form_classes = 'hvnly-contact-agent__form js-hvnly-contact-agent-form';
if ( ! $hvnly_accepts_inquiries ) {
	$hvnly_form_classes .= ' hvnly-contact-agent__form--offline';
}
?>
<form
	id="<?php echo esc_attr( $hvnly_form_id ); ?>"
	class="<?php echo esc_attr( $hvnly_form_classes ); ?>"
	method="post"
	action="#"
	novalidate
	data-agent-availability="<?php echo esc_attr( $hvnly_availability ); ?>"
	<?php echo ! $hvnly_accepts_inquiries ? ' data-agent-offline="1"' : ''; ?>
>
	<input type="hidden" name="property_id" value="<?php echo esc_attr( (string) $hvnly_property_id ); ?>" />
	<input
		type="hidden"
		name="agent_id"
		class="js-hvnly-contact-agent-id"
		value="<?php echo esc_attr( (string) $hvnly_primary_id ); ?>"
	/>

	<?php if ( $hvnly_availability_notice ) : ?>
		<div class="hvnly-contact-agent__availability-notice hvnly-contact-agent__availability-notice--<?php echo esc_attr( $hvnly_availability ); ?>" role="note">
			<?php echo esc_html( $hvnly_availability_notice ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $hvnly_has_multi ) : ?>
		<div class="hvnly-contact-agent__field hvnly-contact-agent__field--agent-select">
			<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-agent">
				<?php esc_html_e( 'Contact', 'havenlytics' ); ?>
				<span class="hvnly-contact-agent__required" aria-hidden="true">*</span>
			</label>
			<select
				id="<?php echo esc_attr( $hvnly_form_id ); ?>-agent"
				class="hvnly-contact-agent__select js-hvnly-contact-agent-select"
				required
			>
				<?php foreach ( $hvnly_agents as $hvnly_option ) : ?>
					<?php
					$hvnly_option_id   = isset( $hvnly_option['id'] ) ? absint( $hvnly_option['id'] ) : 0;
					$hvnly_option_name = isset( $hvnly_option['name'] ) ? (string) $hvnly_option['name'] : '';
					$hvnly_option_type = isset( $hvnly_option['type'] ) ? sanitize_key( (string) $hvnly_option['type'] ) : 'user';
					$hvnly_option_pos  = isset( $hvnly_option['position'] ) ? (string) $hvnly_option['position'] : '';
					$hvnly_option_av   = isset( $hvnly_option['avatar'] ) ? (string) $hvnly_option['avatar'] : '';

					if ( 'cpt' !== $hvnly_option_type && ! empty( $hvnly_option['source'] ) && 'cpt' === $hvnly_option['source'] ) {
						$hvnly_option_type = 'cpt';
					}

					if ( ! $hvnly_option_id || ! $hvnly_option_name ) {
						continue;
					}
					?>
					<option
						value="<?php echo esc_attr( (string) $hvnly_option_id ); ?>"
						data-agent-type="<?php echo esc_attr( $hvnly_option_type ); ?>"
						data-agent-name="<?php echo esc_attr( $hvnly_option_name ); ?>"
						data-agent-position="<?php echo esc_attr( $hvnly_option_pos ); ?>"
						data-agent-avatar="<?php echo esc_attr( $hvnly_option_av ); ?>"
						<?php selected( $hvnly_option_id, $hvnly_primary_id ); ?>
					>
						<?php echo esc_html( $hvnly_option_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>

	<div class="hvnly-contact-agent__field hvnly-contact-agent__field--honeypot" aria-hidden="true">
		<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-website"><?php esc_html_e( 'Website', 'havenlytics' ); ?></label>
		<input
			type="text"
			id="<?php echo esc_attr( $hvnly_form_id ); ?>-website"
			name="hvnly_contact_website"
			class="js-hvnly-contact-agent-honeypot"
			tabindex="-1"
			autocomplete="off"
		/>
	</div>

	<div class="hvnly-contact-agent__field">
		<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-name">
			<?php esc_html_e( 'Your Name', 'havenlytics' ); ?>
			<span class="hvnly-contact-agent__required" aria-hidden="true">*</span>
		</label>
		<input
			type="text"
			id="<?php echo esc_attr( $hvnly_form_id ); ?>-name"
			name="sender_name"
			class="hvnly-contact-agent__input"
			required
			autocomplete="name"
		/>
	</div>

	<div class="hvnly-contact-agent__field">
		<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-email">
			<?php esc_html_e( 'Email Address', 'havenlytics' ); ?>
			<span class="hvnly-contact-agent__required" aria-hidden="true">*</span>
		</label>
		<input
			type="email"
			id="<?php echo esc_attr( $hvnly_form_id ); ?>-email"
			name="sender_email"
			class="hvnly-contact-agent__input"
			required
			autocomplete="email"
			inputmode="email"
		/>
	</div>

	<div class="hvnly-contact-agent__field">
		<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-phone">
			<?php esc_html_e( 'Phone (optional)', 'havenlytics' ); ?>
		</label>
		<input
			type="tel"
			id="<?php echo esc_attr( $hvnly_form_id ); ?>-phone"
			name="sender_phone"
			class="hvnly-contact-agent__input"
			autocomplete="tel"
			inputmode="tel"
		/>
	</div>

	<div class="hvnly-contact-agent__field">
		<label for="<?php echo esc_attr( $hvnly_form_id ); ?>-message">
			<?php esc_html_e( 'Message', 'havenlytics' ); ?>
			<span class="hvnly-contact-agent__required" aria-hidden="true">*</span>
		</label>
		<textarea
			id="<?php echo esc_attr( $hvnly_form_id ); ?>-message"
			name="message"
			class="hvnly-contact-agent__textarea"
			rows="5"
			required
		><?php
			echo esc_textarea(
				$hvnly_property_title
					? sprintf(
						/* translators: %s: property title */
						__( "I'm interested in: %s\n\n", 'havenlytics' ),
						$hvnly_property_title
					)
					: ''
			);
		?></textarea>
	</div>

	<div class="hvnly-contact-agent__feedback js-hvnly-contact-agent-feedback" role="status" aria-live="polite" hidden></div>

	<div class="hvnly-contact-agent__actions">
		<button type="button" class="hvnly-contact-agent__btn hvnly-contact-agent__btn--secondary js-hvnly-contact-agent-close">
			<?php esc_html_e( 'Cancel', 'havenlytics' ); ?>
		</button>
		<button type="submit" class="hvnly-contact-agent__btn hvnly-contact-agent__btn--primary js-hvnly-contact-agent-submit"<?php echo ! $hvnly_accepts_inquiries ? ' disabled' : ''; ?>>
			<?php
			echo esc_html(
				$hvnly_agent_name
					? sprintf(
						/* translators: %s: agent name */
						__( 'Send to %s', 'havenlytics' ),
						$hvnly_agent_name
					)
					: __( 'Send Message', 'havenlytics' )
			);
			?>
		</button>
	</div>
</form>
