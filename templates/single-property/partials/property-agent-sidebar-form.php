<?php
/**
 * Inline sidebar contact form for property agent widget.
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
$hvnly_form_id        = isset( $args['form_id'] ) ? sanitize_html_class( (string) $args['form_id'] ) : wp_unique_id( 'hvnly-agent-sidebar-form-' );

if ( empty( $hvnly_agents ) && ! empty( $hvnly_agent ) ) {
	$hvnly_agents = array( $hvnly_agent );
}

$hvnly_primary      = ! empty( $hvnly_agents[0] ) ? $hvnly_agents[0] : $hvnly_agent;
$hvnly_primary_id   = isset( $hvnly_primary['id'] ) ? absint( $hvnly_primary['id'] ) : 0;
if ( ! $hvnly_primary_id && ! empty( $hvnly_primary['user_id'] ) ) {
	$hvnly_primary_id = absint( $hvnly_primary['user_id'] );
}

if ( ! $hvnly_property_id ) {
	return;
}
?>
<form id="<?php echo esc_attr( $hvnly_form_id ); ?>"
    class="hvnly-agent-sidebar__form js-hvnly-contact-agent-form js-hvnly-contact-agent-form-inline" method="post"
    action="#" novalidate>
    <input type="hidden" name="property_id" value="<?php echo esc_attr( (string) $hvnly_property_id ); ?>" />
    <input type="hidden" name="agent_id" class="js-hvnly-contact-agent-id"
        value="<?php echo esc_attr( (string) $hvnly_primary_id ); ?>" />

    <div class="hvnly-agent-sidebar__field hvnly-contact-agent__field--honeypot" aria-hidden="true">
        <label
            for="<?php echo esc_attr( $hvnly_form_id ); ?>-website"><?php esc_html_e( 'Website', 'havenlytics' ); ?></label>
        <input type="text" id="<?php echo esc_attr( $hvnly_form_id ); ?>-website" name="hvnly_contact_website"
            class="js-hvnly-contact-agent-honeypot" tabindex="-1" autocomplete="off" />
    </div>

    <div class="hvnly-agent-sidebar__field">
        <label for="<?php echo esc_attr( $hvnly_form_id ); ?>-name"><?php esc_html_e( 'Your Name', 'havenlytics' ); ?>
            <span class="hvnly-agent-sidebar__required">*</span></label>
        <input type="text" id="<?php echo esc_attr( $hvnly_form_id ); ?>-name" name="sender_name" required
            autocomplete="name" />
    </div>

    <div class="hvnly-agent-sidebar__field">
        <label
            for="<?php echo esc_attr( $hvnly_form_id ); ?>-phone"><?php esc_html_e( 'Phone', 'havenlytics' ); ?></label>
        <input type="tel" id="<?php echo esc_attr( $hvnly_form_id ); ?>-phone" name="sender_phone" autocomplete="tel"
            inputmode="tel" />
    </div>

    <div class="hvnly-agent-sidebar__field">
        <label for="<?php echo esc_attr( $hvnly_form_id ); ?>-email"><?php esc_html_e( 'Email', 'havenlytics' ); ?>
            <span class="hvnly-agent-sidebar__required">*</span></label>
        <input type="email" id="<?php echo esc_attr( $hvnly_form_id ); ?>-email" name="sender_email" required
            autocomplete="email" inputmode="email" />
    </div>

    <div class="hvnly-agent-sidebar__field">
        <label for="<?php echo esc_attr( $hvnly_form_id ); ?>-message"><?php esc_html_e( 'Message', 'havenlytics' ); ?>
            <span class="hvnly-agent-sidebar__required">*</span></label>
        <textarea id="<?php echo esc_attr( $hvnly_form_id ); ?>-message" name="message" rows="4" required><?php
			echo esc_textarea(
				$hvnly_property_title
					? sprintf( 
						/* translators: %s: property title */	
					__( "I'm interested in: %s\n\n", 'havenlytics' ), $hvnly_property_title )
					: ''
			);
		?></textarea>
    </div>

    <div class="hvnly-agent-sidebar__feedback js-hvnly-contact-agent-feedback" role="status" aria-live="polite" hidden>
    </div>

    <button type="submit" class="hvnly-agent-sidebar__submit js-hvnly-contact-agent-submit">
        <?php esc_html_e( 'Send Message', 'havenlytics' ); ?>
    </button>
</form>