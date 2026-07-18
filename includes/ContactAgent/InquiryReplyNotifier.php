<?php
/**
 * Sends admin reply emails to inquiry submitters.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
class InquiryReplyNotifier {

	/**
	 * @var InquiryEmailRenderer
	 */
	private $renderer;

	/**
	 * @param InquiryEmailRenderer|null $renderer Optional renderer for tests.
	 */
	public function __construct( ?InquiryEmailRenderer $renderer = null ) {
		$this->renderer = $renderer ?? new InquiryEmailRenderer();
	}

	/**
	 * Email the inquiry submitter with an admin reply.
	 *
	 * @param int                  $inquiry_id   Inquiry ID.
	 * @param array<string, mixed> $inquiry      Inquiry row.
	 * @param array<string, mixed> $agent        Agent profile.
	 * @param string               $reply_message Reply body.
	 * @param \WP_User             $admin_user   Admin who sent the reply.
	 * @return bool|\WP_Error
	 */
	public function send( int $inquiry_id, array $inquiry, array $agent, string $reply_message, \WP_User $admin_user ) {
		$recipient = isset( $inquiry['sender_email'] ) ? sanitize_email( (string) $inquiry['sender_email'] ) : '';
		if ( ! is_email( $recipient ) ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_invalid_recipient',
				__( 'The inquiry does not have a valid sender email address.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$context = InquiryEmailContextBuilder::build_for_reply( $inquiry_id, $inquiry, $agent, $reply_message, $admin_user );
		$subject = $this->resolve_subject( $context );
		$body    = $this->renderer->render( ContactAgentConstants::EMAIL_TYPE_ADMIN_REPLY, $context );

		if ( '' === trim( $body ) ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_empty_body',
				__( 'Unable to render the reply email.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		$sent = wp_mail( $recipient, $subject, $body, $this->build_headers( $context, $admin_user ) );

		if ( ! $sent ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_email_failed',
				__( 'The reply was saved but the email could not be sent. Check your site mail settings.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $context Email context.
	 * @return string
	 */
	private function resolve_subject( array $context ): string {
		$subject = sprintf(
			/* translators: 1: property title, 2: site name */
			__( 'Re: Your inquiry about %1$s — %2$s', 'havenlytics' ),
			(string) ( $context['property_title'] ?? '' ),
			(string) ( $context['site_name'] ?? '' )
		);

		/**
		 * Filter admin reply email subject.
		 *
		 * @since 3.0.2
		 *
		 * @param string               $subject    Subject line.
		 * @param array<string, mixed> $context    Email context.
		 */
		$subject = (string) apply_filters( 'hvnly_contact_agent_admin_reply_subject', $subject, $context );

		/**
		 * Filter a Contact Agent email subject by type.
		 *
		 * @since 3.0.2
		 *
		 * @param string               $subject    Subject line.
		 * @param string               $email_type Email type slug.
		 * @param array<string, mixed> $context    Email context.
		 */
		return (string) apply_filters(
			'hvnly_contact_agent_email_subject',
			$subject,
			ContactAgentConstants::EMAIL_TYPE_ADMIN_REPLY,
			$context
		);
	}

	/**
	 * @param array<string, mixed> $context    Email context.
	 * @param \WP_User             $admin_user Admin user.
	 * @return string[]
	 */
	private function build_headers( array $context, \WP_User $admin_user ): array {
		// Sprint 24C: shared, DMARC-safe headers + quoted Reply-To phrase.
		$headers = \HvnlyNab\Email\EmailHeaders::base();

		$reply_to = \HvnlyNab\Email\EmailHeaders::reply_to(
			(string) $admin_user->user_email,
			(string) $admin_user->display_name
		);

		if ( '' !== $reply_to ) {
			$headers[] = $reply_to;
		}

		/**
		 * Filter admin reply email headers.
		 *
		 * @since 3.0.2
		 *
		 * @param string[]             $headers Email headers.
		 * @param array<string, mixed> $context Email context.
		 * @param \WP_User             $admin_user Admin user.
		 */
		return apply_filters( 'hvnly_contact_agent_admin_reply_headers', $headers, $context, $admin_user );
	}
}
