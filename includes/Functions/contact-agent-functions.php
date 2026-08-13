<?php
/**
 * Contact Agent helper functions.
 *
 * @package Havenlytics
 * @since   3.0.2
 */

defined( 'ABSPATH' ) || exit;

use HvnlyNab\ContactAgent\ContactAgentModule;

/**
 * Retrieve the Contact Agent module container.
 *
 * @return ContactAgentModule|null
 */
function hvnly_contact_agent_module(): ?ContactAgentModule {
	if ( ! class_exists( ContactAgentModule::class ) ) {
		return null;
	}

	$module = ContactAgentModule::get_instance();

	return $module->is_bootstrapped() ? $module : null;
}

/**
 * Whether Contact Agent is enabled for the current site.
 *
 * Reads from plugin settings (Contact Agent tab); filterable.
 *
 * @return bool
 */
function hvnly_is_contact_agent_enabled(): bool {
	$enabled = false;

	if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentSettings::class ) ) {
		$enabled = \HvnlyNab\ContactAgent\ContactAgentSettings::is_enabled();
	}

	/**
	 * Filter whether Contact Agent features are enabled.
	 *
	 * @since 3.0.2
	 *
	 * @param bool $enabled Value from plugin settings.
	 */
	return (bool) apply_filters( 'hvnly_contact_agent_enabled', $enabled );
}

/**
 * Render the Contact Agent trigger button.
 *
 * @since 3.0.2
 *
 * @param int                  $property_id Property post ID.
 * @param array<string, mixed> $args        Optional button args (label, class, agent_name).
 * @return void
 */
function hvnly_render_contact_agent_button( int $property_id = 0, array $args = array() ): void {
	if ( ! hvnly_is_contact_agent_enabled() ) {
		return;
	}

	if ( $property_id <= 0 ) {
		$property_id = (int) get_the_ID();
	}

	if ( $property_id <= 0 || 'hvnly_property' !== get_post_type( $property_id ) ) {
		return;
	}

	$args['property_id'] = $property_id;

	if ( empty( $args['agent_id'] ) || empty( $args['agent_name'] ) ) {
		$agents = function_exists( 'hvnly_get_property_agents' )
			? hvnly_get_property_agents( $property_id )
			: array();

		if ( empty( $agents ) && function_exists( 'hvnly_get_property_agent' ) ) {
			$primary = hvnly_get_property_agent( $property_id );
			if ( is_array( $primary ) && ! empty( $primary ) ) {
				$agents = array( $primary );
			}
		}

		$agent = ! empty( $agents ) ? $agents[0] : array();

		if ( empty( $args['agent_id'] ) && ! empty( $agent['id'] ) ) {
			$args['agent_id'] = absint( $agent['id'] );
		}

		if ( empty( $args['agent_type'] ) && ! empty( $agent ) ) {
			$args['agent_type'] = isset( $agent['type'] )
				? (string) $agent['type']
				: (string) ( $agent['source'] ?? 'user' );
		}

		if ( empty( $args['agent_name'] ) && ! empty( $agent['name'] ) ) {
			$args['agent_name'] = (string) $agent['name'];
		}

		if ( empty( $args['agent_avatar'] ) && ! empty( $agent['avatar'] ) ) {
			$args['agent_avatar'] = (string) $agent['avatar'];
		}

		if ( empty( $args['agent_position'] ) && ! empty( $agent['position'] ) ) {
			$args['agent_position'] = (string) $agent['position'];
		}
	}

	$resolved_agent_id = ! empty( $args['agent_id'] ) ? absint( $args['agent_id'] ) : 0;
	if ( $resolved_agent_id > 0 && function_exists( 'hvnly_get_agent_availability_status' ) ) {
		$args['agent_availability'] = hvnly_get_agent_availability_status( $resolved_agent_id );
		if ( function_exists( 'hvnly_agent_accepts_inquiries' ) ) {
			$args['agent_accepts_inquiries'] = hvnly_agent_accepts_inquiries( $resolved_agent_id );
		}
		if ( function_exists( 'hvnly_get_agent_availability_notice' ) ) {
			$args['agent_availability_notice'] = hvnly_get_agent_availability_notice( (string) $args['agent_availability'] );
		}
	}

	/**
	 * Filter Contact Agent button arguments before render.
	 *
	 * @since 3.0.2
	 *
	 * @param array<string, mixed> $args        Button arguments.
	 * @param int                  $property_id Property ID.
	 */
	$args = apply_filters( 'hvnly_contact_agent_button_args', $args, $property_id );

	if ( function_exists( 'hvnly_get_template_part' ) ) {
		hvnly_get_template_part( 'single-property/partials/contact-agent-button', null, $args );
	}
}

/**
 * Resolve client IP for rate limiting (best-effort).
 *
 * @return string
 */
function hvnly_contact_agent_get_client_ip(): string {
	$ip = '';

	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}

	/**
	 * Filter the client IP used for Contact Agent rate limiting.
	 *
	 * @since 3.0.2
	 *
	 * @param string $ip Detected IP address.
	 */
	return (string) apply_filters( 'hvnly_contact_agent_client_ip', $ip );
}

/**
 * Whether the Contact Agent inquiries table exists.
 *
 * @since 3.0.2
 *
 * @return bool
 */
function hvnly_contact_agent_inquiries_table_exists(): bool {
	if ( ! class_exists( \HvnlyNab\ContactAgent\Database\InquirySchema::class ) ) {
		return false;
	}

	return \HvnlyNab\ContactAgent\Database\InquirySchema::table_exists();
}

/**
 * Retrieve the Contact Agent inquiries table name (with prefix).
 *
 * @since 3.0.2
 *
 * @return string
 */
function hvnly_contact_agent_inquiries_table_name(): string {
	if ( ! class_exists( \HvnlyNab\ContactAgent\Database\InquirySchema::class ) ) {
		global $wpdb;

		return $wpdb->prefix . 'hvnly_inquiries';
	}

	return \HvnlyNab\ContactAgent\Database\InquirySchema::table_name();
}

/**
 * Whether agent email notifications are enabled.
 *
 * Reads from plugin settings (ContactAgentSettings); defaults to true via filter.
 *
 * @since 3.0.2
 *
 * @return bool
 */
function hvnly_contact_agent_notify_agent_enabled(): bool {
	$enabled = true;

	if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentSettings::class ) ) {
		$enabled = \HvnlyNab\ContactAgent\ContactAgentSettings::notify_agent_enabled();
	}

	/**
	 * Filter whether Contact Agent sends email to the property agent.
	 *
	 * @since 3.0.2
	 *
	 * @param bool $enabled Value from plugin settings.
	 */
	return (bool) apply_filters( 'hvnly_contact_agent_notify_agent', $enabled );
}

/**
 * Whether admin copy notifications are enabled.
 *
 * @since 3.0.2
 *
 * @return bool
 */
function hvnly_contact_agent_notify_admin_enabled(): bool {
	$enabled = true;

	if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentSettings::class ) ) {
		$enabled = \HvnlyNab\ContactAgent\ContactAgentSettings::notify_admin_enabled();
	}

	/**
	 * Filter whether Contact Agent sends an admin copy email.
	 *
	 * @since 3.0.2
	 *
	 * @param bool $enabled Value from plugin settings.
	 */
	return (bool) apply_filters( 'hvnly_contact_agent_notify_admin', $enabled );
}

/**
 * Whether submitter auto-reply emails are enabled.
 *
 * @since 3.0.2
 *
 * @return bool
 */
function hvnly_contact_agent_notify_sender_enabled(): bool {
	$enabled = true;

	if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentSettings::class ) ) {
		$enabled = \HvnlyNab\ContactAgent\ContactAgentSettings::notify_sender_enabled();
	}

	/**
	 * Filter whether Contact Agent sends an instant auto-reply to the submitter.
	 *
	 * @since 3.0.2
	 *
	 * @param bool $enabled Value from plugin settings.
	 */
	return (bool) apply_filters( 'hvnly_contact_agent_notify_sender', $enabled );
}

/**
 * Build the frontend/AJAX success message for a stored inquiry.
 *
 * Supports {{merge_tags}} when settings provide a custom message.
 *
 * @since 3.0.2
 *
 * @param array<string, mixed> $inquiry Inquiry row.
 * @param array<string, mixed> $agent   Agent profile.
 * @return string
 */
function hvnly_contact_agent_get_success_message( array $inquiry, array $agent = array() ): string {
	$default = __( 'Thank you! Your message has been received. Our agent will contact you as soon as possible.', 'havenlytics' );

	$template = $default;
	if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentSettings::class ) ) {
		$stored = \HvnlyNab\ContactAgent\ContactAgentSettings::get_success_message();
		if ( '' !== trim( $stored ) ) {
			$template = $stored;
		}
	}

	$inquiry_id = isset( $inquiry['id'] ) ? absint( $inquiry['id'] ) : 0;
	$context    = class_exists( \HvnlyNab\ContactAgent\InquiryEmailContextBuilder::class )
		? \HvnlyNab\ContactAgent\InquiryEmailContextBuilder::build( $inquiry_id, $inquiry, $agent )
		: array();

	$message = class_exists( \HvnlyNab\ContactAgent\InquiryEmailContextBuilder::class )
		? \HvnlyNab\ContactAgent\InquiryEmailContextBuilder::replace_merge_tags( $template, $context )
		: $default;

	if ( '' === trim( $message ) ) {
		$message = $default;
	}

	/**
	 * Filter the Contact Agent success message shown after form submission.
	 *
	 * @since 3.0.2
	 *
	 * @param string               $message Resolved message.
	 * @param array<string, mixed> $inquiry Inquiry row.
	 * @param array<string, mixed> $agent   Agent profile.
	 * @param array<string, mixed> $context Email context used for merge tags.
	 */
	return (string) apply_filters( 'hvnly_contact_agent_success_message', $message, $inquiry, $agent, $context );
}

/**
 * Admin notification email address.
 *
 * @since 3.0.2
 *
 * @return string
 */
function hvnly_contact_agent_admin_notification_email(): string {
	$default = sanitize_email( (string) get_option( 'admin_email' ) );

	if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentSettings::class ) ) {
		$default = \HvnlyNab\ContactAgent\ContactAgentSettings::get_admin_email();
	}

	/**
	 * Filter the admin notification email for Contact Agent inquiries.
	 *
	 * @since 3.0.2
	 *
	 * @param string $email Resolved admin notification email.
	 */
	return sanitize_email( (string) apply_filters( 'hvnly_contact_agent_admin_notification_email', $default ) );
}

/**
 * Whether honeypot spam protection is enabled.
 *
 * @since 3.0.2
 *
 * @return bool
 */
function hvnly_contact_agent_honeypot_enabled(): bool {
	$enabled = true;

	if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentSettings::class ) ) {
		$enabled = \HvnlyNab\ContactAgent\ContactAgentSettings::is_honeypot_enabled();
	}

	/**
	 * Filter whether Contact Agent honeypot protection is active.
	 *
	 * @since 3.0.2
	 *
	 * @param bool $enabled Value from plugin settings.
	 */
	return (bool) apply_filters( 'hvnly_contact_agent_honeypot_enabled', $enabled );
}

/**
 * Maximum inquiry submissions per IP within the rate window.
 *
 * @since 3.0.2
 *
 * @return int
 */
function hvnly_contact_agent_rate_limit_max(): int {
	$max = \HvnlyNab\ContactAgent\ContactAgentConstants::RATE_LIMIT_MAX;

	if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentSettings::class ) ) {
		$max = \HvnlyNab\ContactAgent\ContactAgentSettings::get_rate_limit_max();
	}

	/**
	 * Filter maximum Contact Agent submissions per IP within the rate window.
	 *
	 * @since 3.0.2
	 *
	 * @param int $max Value from plugin settings.
	 */
	return max( 1, (int) apply_filters( 'hvnly_contact_agent_rate_limit_max', $max ) );
}

/**
 * Rate limit window in seconds.
 *
 * @since 3.0.2
 *
 * @return int
 */
function hvnly_contact_agent_rate_limit_window(): int {
	$window = \HvnlyNab\ContactAgent\ContactAgentConstants::RATE_LIMIT_WINDOW;

	if ( class_exists( \HvnlyNab\ContactAgent\ContactAgentSettings::class ) ) {
		$window = \HvnlyNab\ContactAgent\ContactAgentSettings::get_rate_limit_window();
	}

	/**
	 * Filter Contact Agent rate limit window in seconds.
	 *
	 * @since 3.0.2
	 *
	 * @param int $seconds Value from plugin settings.
	 */
	return max( 60, (int) apply_filters( 'hvnly_contact_agent_rate_limit_window', $window ) );
}

/**
 * Resolve the agent profile for a stored inquiry row.
 *
 * @since 3.0.2
 *
 * @param array<string, mixed> $inquiry Inquiry row (must include property_id and agent_id).
 * @return array<string, mixed>
 */
function hvnly_get_inquiry_agent_profile( array $inquiry ): array {
	if ( ! class_exists( \HvnlyNab\ContactAgent\InquiryAgentResolver::class ) ) {
		return array();
	}

	$profile = \HvnlyNab\ContactAgent\InquiryAgentResolver::profile_from_inquiry( $inquiry );

	return is_array( $profile ) ? $profile : array();
}

/**
 * Localize Contact Agent frontend script configuration.
 *
 * Ensures AJAX URL, nonce, and action are available even when the script
 * is enqueued late during template rendering (e.g. agents section).
 *
 * @since 3.0.2
 *
 * @param int $property_id Property post ID (0 for agent profile pages).
 * @return void
 */
function hvnly_localize_contact_agent_script( int $property_id = 0 ): void {
	if ( ! wp_script_is( 'hvnly-frontend-contact-agent', 'registered' ) ) {
		wp_register_script(
			'hvnly-frontend-contact-agent',
			HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-contact-agent.js',
			array( 'jquery' ),
			defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2',
			true
		);
	}

	wp_localize_script(
		'hvnly-frontend-contact-agent',
		'hvnlyContactAgent',
		array(
			'propertyId' => absint( $property_id ),
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'hvnly_ajax_request' ),
			'action'     => class_exists( \HvnlyNab\ContactAgent\ContactAgentConstants::class )
				? \HvnlyNab\ContactAgent\ContactAgentConstants::AJAX_SUBMIT_ACTION
				: 'hvnly_submit_contact_agent',
			'validation' => array(
				'nameMinLength'    => 2,
				'nameMaxLength'    => 100,
				'emailMaxLength'   => 100,
				'phoneMaxLength'   => 30,
				'messageMinLength' => 10,
				'messageMaxLength' => 5000,
			),
			'i18n'       => array(
				'submitting'             => __( 'Sending...', 'havenlytics' ),
				'success'                => __( 'A member of our team will respond as soon as possible.', 'havenlytics' ),
				'successTitle'           => __( 'Inquiry Sent Successfully', 'havenlytics' ),
				'successSubtitle'        => __( 'Thank you for contacting us. Your inquiry has been received successfully.', 'havenlytics' ),
				'successReferenceLabel'  => __( 'Reference ID', 'havenlytics' ),
				'successResponseLabel'   => __( 'Expected response time', 'havenlytics' ),
				'successResponseTime'    => __( 'Within 1 business day.', 'havenlytics' ),
				'error'                  => __( 'Something went wrong. Please try again.', 'havenlytics' ),
				'errorTitle'             => __( 'Unable to send your inquiry', 'havenlytics' ),
				'errorHint'              => __( 'Please try again in a few minutes or contact us directly.', 'havenlytics' ),
				'networkError'           => __( 'Unable to send your message. Please refresh the page and try again.', 'havenlytics' ),
				'sessionExpired'         => __( 'Security token expired. Please refresh the page and try again.', 'havenlytics' ),
				'handlerMissing'         => __( 'Contact form endpoint is unavailable. Please refresh the page or contact the site administrator.', 'havenlytics' ),
				'contactTitle'           =>
					/* translators: %s: Agent display name. */
					__( 'Contact %s', 'havenlytics' ),
				'sendTo'                 =>
					/* translators: %s: Agent display name. */
					__( 'Send to %s', 'havenlytics' ),
				'agentOffline'           => __( 'This agent is offline and not accepting inquiries at this time.', 'havenlytics' ),
				'validationNameRequired' => __( 'Please enter your full name.', 'havenlytics' ),
				'validationNameMin'        => __( 'Please enter your full name.', 'havenlytics' ),
				'validationNameMax'        => __( 'Your name is too long.', 'havenlytics' ),
				'validationEmailRequired'  => __( 'Email address is required.', 'havenlytics' ),
				'validationEmailInvalid' => __( 'Please enter a valid email address.', 'havenlytics' ),
				'validationPhoneInvalid'   => __( 'Please enter a valid phone number.', 'havenlytics' ),
				'validationPhoneMax'       => __( 'Your phone number is too long.', 'havenlytics' ),
				'validationMessageRequired' => __( 'Message is required.', 'havenlytics' ),
				'validationMessageMin'     => __( 'Message must contain at least 10 characters.', 'havenlytics' ),
				'validationMessageMax'     => __( 'Your message is too long.', 'havenlytics' ),
				'validationPrivacyRequired' => __( 'Please accept the Privacy Policy.', 'havenlytics' ),
			),
		)
	);
}
