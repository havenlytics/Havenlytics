<?php

/**

 * Inquiry email notifier (Phase 5).

 *

 * @package HvnlyNab\ContactAgent

 * @since   3.0.2

 */



namespace HvnlyNab\ContactAgent;



use HvnlyNab\ContactAgent\Contracts\InquiryNotifierInterface;



defined( 'ABSPATH' ) || exit;



/**

 * Sends agent, admin, and submitter auto-reply emails via wp_mail().

 *

 * @since 3.0.2

 */

class InquiryNotifier implements InquiryNotifierInterface {



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

	 * {@inheritdoc}

	 */

	public function send( int $inquiry_id, array $inquiry, array $agent ) {

		if ( $inquiry_id <= 0 || empty( $inquiry ) ) {

			return new \WP_Error(

				'hvnly_contact_agent_invalid_inquiry',

				__( 'Unable to send inquiry notifications.', 'havenlytics' ),

				array( 'status' => 400 )

			);

		}



		$property_id = isset( $inquiry['property_id'] ) ? absint( $inquiry['property_id'] ) : 0;



		$agent = InquiryAgentResolver::profile_from_inquiry( $inquiry );



		if ( empty( $agent ) && $property_id > 0 && function_exists( 'hvnly_get_property_agent' ) ) {

			$agent = hvnly_get_property_agent( $property_id );

		}



		if ( ! is_array( $agent ) ) {

			$agent = array();

		}



		$context = InquiryEmailContextBuilder::build( $inquiry_id, $inquiry, $agent );



		$results = array(

			'agent'  => null,

			'admin'  => null,

			'sender' => null,

		);



		if ( function_exists( 'hvnly_contact_agent_notify_agent_enabled' ) && hvnly_contact_agent_notify_agent_enabled() ) {

			$agent_email = isset( $agent['email'] ) ? sanitize_email( (string) $agent['email'] ) : '';

			if ( is_email( $agent_email ) ) {

				$results['agent'] = $this->send_typed_email(

					ContactAgentConstants::EMAIL_TYPE_AGENT,

					$agent_email,

					$context,

					$this->build_agent_headers( $context )

				);

			}

		}



		if ( function_exists( 'hvnly_contact_agent_notify_admin_enabled' ) && hvnly_contact_agent_notify_admin_enabled() ) {

			$admin_email = function_exists( 'hvnly_contact_agent_admin_notification_email' )

				? hvnly_contact_agent_admin_notification_email()

				: sanitize_email( (string) get_option( 'admin_email' ) );



			if ( is_email( $admin_email ) ) {

				$agent_email = isset( $agent['email'] ) ? strtolower( sanitize_email( (string) $agent['email'] ) ) : '';

				if ( strtolower( $admin_email ) !== $agent_email ) {

					$results['admin'] = $this->send_typed_email(

						ContactAgentConstants::EMAIL_TYPE_ADMIN,

						$admin_email,

						$context,

						$this->build_agent_headers( $context )

					);

				}

			}

		}



		if ( function_exists( 'hvnly_contact_agent_notify_sender_enabled' ) && hvnly_contact_agent_notify_sender_enabled() ) {

			$sender_email = isset( $inquiry['sender_email'] ) ? sanitize_email( (string) $inquiry['sender_email'] ) : '';

			if ( is_email( $sender_email ) ) {

				$results['sender'] = $this->send_typed_email(

					ContactAgentConstants::EMAIL_TYPE_SENDER,

					$sender_email,

					$context,

					$this->build_sender_headers( $context )

				);

			}

		}



		/**

		 * Fires after inquiry notification attempts complete.

		 *

		 * @since 3.0.2

		 *

		 * @param int                  $inquiry_id Inquiry ID.

		 * @param array<string, mixed> $results    Per-recipient wp_mail results.

		 * @param array<string, mixed> $context    Email context.

		 */

		do_action( 'hvnly_contact_agent_inquiry_notified', $inquiry_id, $results, $context );



		return true;

	}



	/**

	 * @param string               $email_type Email type slug.

	 * @param string               $recipient  Recipient email address.

	 * @param array<string, mixed> $context    Email context.

	 * @param string[]             $headers    Email headers.

	 * @return bool

	 */

	private function send_typed_email( string $email_type, string $recipient, array $context, array $headers ): bool {

		$subject = $this->resolve_subject( $email_type, $context );

		$body    = $this->renderer->render( $email_type, $context );



		if ( '' === trim( $body ) ) {

			return false;

		}



		$sent = wp_mail( $recipient, $subject, $body, $headers );



		if ( ! $sent && function_exists( 'hvnly_debug_log' ) ) {

			hvnly_debug_log(

				sprintf(

					'Contact Agent %s email failed for inquiry #%d',

					$email_type,

					(int) ( $context['inquiry_id'] ?? 0 )

				),

				'ContactAgent'

			);

		}



		return (bool) $sent;

	}



	/**

	 * @param string               $email_type Email type slug.

	 * @param array<string, mixed> $context    Email context.

	 * @return string

	 */

	private function resolve_subject( string $email_type, array $context ): string {

		switch ( $email_type ) {

			case ContactAgentConstants::EMAIL_TYPE_SENDER:

				$subject = ContactAgentSettings::get_sender_subject();

				if ( '' === trim( $subject ) ) {

					$subject = sprintf(

						/* translators: %s: site name */

						__( 'We received your inquiry — {{site_name}}', 'havenlytics' ),

						(string) ( $context['site_name'] ?? '' )

					);

				}

				$subject = InquiryEmailContextBuilder::replace_merge_tags( $subject, $context );

				break;



			case ContactAgentConstants::EMAIL_TYPE_ADMIN:

				$subject = sprintf(

					/* translators: %d: inquiry ID */

					__( '[Havenlytics] New property inquiry #%d', 'havenlytics' ),

					(int) ( $context['inquiry_id'] ?? 0 )

				);

				break;



			case ContactAgentConstants::EMAIL_TYPE_AGENT:

			default:

				$subject = sprintf(

					/* translators: %s: property title */

					__( 'New inquiry for %s', 'havenlytics' ),

					(string) ( $context['property_title'] ?? '' )

				);

				break;

		}



		/**

		 * Filter a Contact Agent email subject by type.

		 *

		 * @since 3.0.2

		 *

		 * @param string               $subject    Subject line.

		 * @param string               $email_type Email type slug.

		 * @param array<string, mixed> $context    Email context.

		 */

		return (string) apply_filters( 'hvnly_contact_agent_email_subject', $subject, $email_type, $context );

	}



	/**

	 * @param array<string, mixed> $context Email context.

	 * @return string[]

	 */

	private function build_agent_headers( array $context ): array {

		$inquiry = isset( $context['inquiry'] ) && is_array( $context['inquiry'] ) ? $context['inquiry'] : array();



		$sender_name  = isset( $inquiry['sender_name'] ) ? sanitize_text_field( (string) $inquiry['sender_name'] ) : '';

		$sender_email = isset( $inquiry['sender_email'] ) ? sanitize_email( (string) $inquiry['sender_email'] ) : '';

		$sender_name  = preg_replace( '/[\r\n\t]+/', ' ', $sender_name );



		$headers = array( 'Content-Type: text/html; charset=UTF-8' );



		if ( is_email( $sender_email ) ) {

			if ( $sender_name ) {

				$headers[] = sprintf( 'Reply-To: %s <%s>', $sender_name, $sender_email );

			} else {

				$headers[] = 'Reply-To: ' . $sender_email;

			}

		}



		/**

		 * Filter Contact Agent notification email headers.

		 *

		 * @since 3.0.2

		 *

		 * @param string[]             $headers Email headers.

		 * @param array<string, mixed> $context Email context.

		 */

		return apply_filters( 'hvnly_contact_agent_email_headers', $headers, $context );

	}



	/**

	 * @param array<string, mixed> $context Email context.

	 * @return string[]

	 */

	private function build_sender_headers( array $context ): array {

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );



		$agent_email = isset( $context['agent_email'] ) ? sanitize_email( (string) $context['agent_email'] ) : '';

		$agent_name  = isset( $context['agent_name'] ) ? sanitize_text_field( (string) $context['agent_name'] ) : '';

		$agent_name  = preg_replace( '/[\r\n\t]+/', ' ', $agent_name );



		if ( is_email( $agent_email ) ) {

			if ( $agent_name ) {

				$headers[] = sprintf( 'Reply-To: %s <%s>', $agent_name, $agent_email );

			} else {

				$headers[] = 'Reply-To: ' . $agent_email;

			}

		}



		/**

		 * Filter Contact Agent submitter auto-reply email headers.

		 *

		 * @since 3.0.2

		 *

		 * @param string[]             $headers Email headers.

		 * @param array<string, mixed> $context Email context.

		 */

		return apply_filters( 'hvnly_contact_agent_sender_email_headers', $headers, $context );

	}

}


