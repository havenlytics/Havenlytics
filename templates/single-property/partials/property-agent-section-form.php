<?php
/**
 * Contact form for main-content agents section.
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
$hvnly_form_id        = isset( $args['form_id'] ) ? sanitize_html_class( (string) $args['form_id'] ) : wp_unique_id( 'hvnly-agents-section-form-' );
$hvnly_section_id     = isset( $args['section_id'] ) ? sanitize_html_class( (string) $args['section_id'] ) : '';

if ( empty( $hvnly_agents ) && ! empty( $hvnly_agent ) ) {
	$hvnly_agents = array( $hvnly_agent );
}

$hvnly_primary    = ! empty( $hvnly_agents[0] ) ? $hvnly_agents[0] : $hvnly_agent;
$hvnly_primary_id = isset( $hvnly_primary['id'] ) ? absint( $hvnly_primary['id'] ) : 0;
$hvnly_is_multi   = count( $hvnly_agents ) > 1;

if ( ! $hvnly_property_id ) {
	return;
}
?>
<div class="hvnly-agents-section__contact-panel">
    <div class="hvnly-agents-section__contact-header">
        <h4 class="hvnly-agents-section__contact-title"><?php esc_html_e( 'Send a Message', 'havenlytics' ); ?></h4>
        <p class="hvnly-agents-section__contact-subtitle">
            <?php esc_html_e( 'Contact the agent directly about this property.', 'havenlytics' ); ?>
        </p>
    </div>

    <?php if ( $hvnly_is_multi ) : ?>
    <div class="hvnly-agents-section__contact-agent-picker">
        <label
            for="<?php echo esc_attr( $hvnly_form_id ); ?>-agent-select"><?php esc_html_e( 'Contact agent', 'havenlytics' ); ?></label>
        <select id="<?php echo esc_attr( $hvnly_form_id ); ?>-agent-select"
            class="hvnly-agents-section__agent-select js-hvnly-agents-section-agent-select">
            <?php foreach ( $hvnly_agents as $hvnly_option ) : ?>
            <?php
					$hvnly_opt_id = isset( $hvnly_option['id'] ) ? absint( $hvnly_option['id'] ) : 0;
					if ( ! $hvnly_opt_id || empty( $hvnly_option['name'] ) ) {
						continue;
					}
					?>
            <option value="<?php echo esc_attr( (string) $hvnly_opt_id ); ?>"
                <?php selected( $hvnly_opt_id, $hvnly_primary_id ); ?>>
                <?php echo esc_html( (string) $hvnly_option['name'] ); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <form id="<?php echo esc_attr( $hvnly_form_id ); ?>"
        class="hvnly-agents-section__form js-hvnly-contact-agent-form js-hvnly-contact-agent-form-inline" method="post"
        action="#" novalidate
        <?php echo $hvnly_section_id ? ' data-section-id="' . esc_attr( $hvnly_section_id ) . '"' : ''; ?>>
        <input type="hidden" name="property_id" value="<?php echo esc_attr( (string) $hvnly_property_id ); ?>" />
        <input type="hidden" name="agent_id" class="js-hvnly-contact-agent-id"
            value="<?php echo esc_attr( (string) $hvnly_primary_id ); ?>" />

        <div class="hvnly-agents-section__field hvnly-contact-agent__field--honeypot" aria-hidden="true">
            <label
                for="<?php echo esc_attr( $hvnly_form_id ); ?>-website"><?php esc_html_e( 'Website', 'havenlytics' ); ?></label>
            <input type="text" id="<?php echo esc_attr( $hvnly_form_id ); ?>-website" name="hvnly_contact_website"
                class="js-hvnly-contact-agent-honeypot" tabindex="-1" autocomplete="off" />
        </div>

        <div class="hvnly-agents-section__field-row">
            <div class="hvnly-agents-section__field">
                <label
                    for="<?php echo esc_attr( $hvnly_form_id ); ?>-name"><?php esc_html_e( 'Your Name', 'havenlytics' ); ?>
                    <span class="hvnly-agents-section__required">*</span></label>
                <input type="text" id="<?php echo esc_attr( $hvnly_form_id ); ?>-name" name="sender_name" required
                    autocomplete="name" />
            </div>

            <div class="hvnly-agents-section__field">
                <label
                    for="<?php echo esc_attr( $hvnly_form_id ); ?>-phone"><?php esc_html_e( 'Phone', 'havenlytics' ); ?></label>
                <input type="tel" id="<?php echo esc_attr( $hvnly_form_id ); ?>-phone" name="sender_phone"
                    autocomplete="tel" inputmode="tel" />
            </div>
        </div>

        <div class="hvnly-agents-section__field">
            <label for="<?php echo esc_attr( $hvnly_form_id ); ?>-email"><?php esc_html_e( 'Email', 'havenlytics' ); ?>
                <span class="hvnly-agents-section__required">*</span></label>
            <input type="email" id="<?php echo esc_attr( $hvnly_form_id ); ?>-email" name="sender_email" required
                autocomplete="email" inputmode="email" />
        </div>

        <div class="hvnly-agents-section__field">
            <label
                for="<?php echo esc_attr( $hvnly_form_id ); ?>-message"><?php esc_html_e( 'Message', 'havenlytics' ); ?>
                <span class="hvnly-agents-section__required">*</span></label>
            <textarea id="<?php echo esc_attr( $hvnly_form_id ); ?>-message" name="message" rows="5" required><?php
				echo esc_textarea(
					$hvnly_property_title
						? sprintf(
							/* translators: %s: Property title. */
							__( "I'm interested in: %s\n\n", 'havenlytics' ),
							$hvnly_property_title
						)
						: ''
				);
			?></textarea>
        </div>

        <div class="hvnly-agents-section__feedback js-hvnly-contact-agent-feedback" role="status" aria-live="polite"
            hidden></div>

        <button type="submit" class="hvnly-btn hvnly-btn--primary hvnly-agents-section__submit js-hvnly-contact-agent-submit">
            <i class="fas fa-paper-plane" aria-hidden="true"></i>
            <?php esc_html_e( 'Send Message', 'havenlytics' ); ?>
        </button>
    </form>
</div>