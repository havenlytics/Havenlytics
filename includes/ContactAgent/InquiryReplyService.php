<?php
/**
 * Orchestrates secure admin replies to property inquiries.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
class InquiryReplyService {

	/**
	 * @var InquiryRepository
	 */
	private $inquiry_repository;

	/**
	 * @var InquiryReplyRepository
	 */
	private $reply_repository;

	/**
	 * @var InquiryReplyNotifier
	 */
	private $notifier;

	/**
	 * @param InquiryRepository|null      $inquiry_repository Inquiry repository.
	 * @param InquiryReplyRepository|null $reply_repository Reply repository.
	 * @param InquiryReplyNotifier|null   $notifier           Reply notifier.
	 */
	public function __construct(
		?InquiryRepository $inquiry_repository = null,
		?InquiryReplyRepository $reply_repository = null,
		?InquiryReplyNotifier $notifier = null
	) {
		ContactAgentFunctionsLoader::load();

		$this->inquiry_repository = $inquiry_repository ?? new InquiryRepository();
		$this->reply_repository   = $reply_repository ?? new InquiryReplyRepository();
		$this->notifier           = $notifier ?? new InquiryReplyNotifier();
	}

	/**
	 * Send a secure admin reply to an inquiry submitter.
	 *
	 * @param int    $inquiry_id Inquiry ID.
	 * @param string $message    Reply message.
	 * @param int    $user_id    Admin user ID.
	 * @return array{reply_id: int, email_sent: bool}|\WP_Error
	 */
	public function send_reply( int $inquiry_id, string $message, int $user_id ) {
		$capability = apply_filters( 'hvnly_inquiry_reply_capability', apply_filters( 'hvnly_admin_capability', 'manage_options' ) );
		$can_reply  = user_can( $user_id, $capability );

		/**
		 * Allow Workspace portal agents to reply to owned inquiries.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $can_reply   Whether the user may reply.
		 * @param int  $user_id     Acting user ID.
		 * @param int  $inquiry_id  Inquiry ID.
		 */
		$can_reply = (bool) apply_filters( 'hvnly_inquiry_user_can_reply', $can_reply, $user_id, $inquiry_id );

		if ( ! $can_reply ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_forbidden',
				__( 'You do not have permission to reply to inquiries.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$inquiry = $this->inquiry_repository->find( $inquiry_id );
		if ( ! $inquiry ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_not_found',
				__( 'Inquiry not found.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		$message = $this->sanitize_message( $message );
		$validation = $this->validate_message( $message );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$rate_check = $this->allow_reply( $user_id, $inquiry_id );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$admin_user = get_userdata( $user_id );
		if ( ! $admin_user instanceof \WP_User ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_invalid_user',
				__( 'Invalid admin user.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$agent = function_exists( 'hvnly_get_inquiry_agent_profile' )
			? hvnly_get_inquiry_agent_profile( $inquiry )
			: InquiryAgentResolver::profile_from_inquiry( $inquiry );

		if ( ! is_array( $agent ) ) {
			$agent = array();
		}

		$email_result = $this->notifier->send( $inquiry_id, $inquiry, $agent, $message, $admin_user );
		$email_sent   = ! is_wp_error( $email_result );

		$reply_id = $this->reply_repository->create(
			array(
				'inquiry_id'     => $inquiry_id,
				'author_user_id' => $user_id,
				'direction'      => ContactAgentConstants::REPLY_DIRECTION_OUTBOUND,
				'message'        => $message,
				'email_sent'     => $email_sent,
			)
		);

		if ( is_wp_error( $reply_id ) ) {
			return $reply_id;
		}

		$this->record_reply( $user_id, $inquiry_id );

		if ( ContactAgentConstants::STATUS_ARCHIVED !== ( $inquiry['status'] ?? '' ) ) {
			$this->inquiry_repository->update_status( $inquiry_id, ContactAgentConstants::STATUS_REPLIED );
		}

		if ( ! $email_sent && is_wp_error( $email_result ) ) {
			/**
			 * Reply stored but email failed — return partial success with stored ID.
			 */
			$email_result->add_data(
				array(
					'reply_id'   => (int) $reply_id,
					'email_sent' => false,
				)
			);

			return $email_result;
		}

		/**
		 * Fires after an admin reply is stored and emailed.
		 *
		 * @since 3.0.2
		 *
		 * @param int                  $reply_id   Reply row ID.
		 * @param int                  $inquiry_id Inquiry ID.
		 * @param int                  $user_id    Admin user ID.
		 * @param array<string, mixed> $inquiry    Inquiry row.
		 */
		do_action( 'hvnly_contact_agent_inquiry_reply_sent', (int) $reply_id, $inquiry_id, $user_id, $inquiry );

		return array(
			'reply_id'   => (int) $reply_id,
			'email_sent' => true,
		);
	}

	/**
	 * @param string $message Raw message.
	 * @return string
	 */
	private function sanitize_message( string $message ): string {
		$message = wp_unslash( $message );
		$message = sanitize_textarea_field( $message );

		return trim( preg_replace( "/\r\n?/", "\n", $message ) );
	}

	/**
	 * @param string $message Sanitized message.
	 * @return true|\WP_Error
	 */
	private function validate_message( string $message ) {
		$min = (int) apply_filters( 'hvnly_inquiry_reply_min_length', ContactAgentConstants::REPLY_MESSAGE_MIN_LENGTH );
		$max = (int) apply_filters( 'hvnly_inquiry_reply_max_length', ContactAgentConstants::REPLY_MESSAGE_MAX_LENGTH );

		if ( strlen( $message ) < max( 1, $min ) ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_too_short',
				sprintf(
					/* translators: %d: minimum characters */
					__( 'Please enter at least %d characters in your reply.', 'havenlytics' ),
					max( 1, $min )
				),
				array( 'status' => 400 )
			);
		}

		if ( strlen( $message ) > $max ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_too_long',
				sprintf(
					/* translators: %d: maximum characters */
					__( 'Your reply must be %d characters or fewer.', 'havenlytics' ),
					$max
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * @param int $user_id    Admin user ID.
	 * @param int $inquiry_id Inquiry ID.
	 * @return true|\WP_Error
	 */
	private function allow_reply( int $user_id, int $inquiry_id ) {
		$key      = $this->get_rate_limit_key( $user_id, $inquiry_id );
		$attempts = (int) get_transient( $key );
		$max      = max( 1, (int) apply_filters( 'hvnly_inquiry_reply_rate_limit_max', ContactAgentConstants::REPLY_RATE_LIMIT_MAX ) );

		if ( $attempts >= $max ) {
			return new \WP_Error(
				'hvnly_inquiry_reply_rate_limit',
				__( 'Too many replies sent for this inquiry. Please wait before sending another.', 'havenlytics' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * @param int $user_id    Admin user ID.
	 * @param int $inquiry_id Inquiry ID.
	 * @return void
	 */
	private function record_reply( int $user_id, int $inquiry_id ): void {
		$key      = $this->get_rate_limit_key( $user_id, $inquiry_id );
		$attempts = (int) get_transient( $key );
		$window   = max( 60, (int) apply_filters( 'hvnly_inquiry_reply_rate_limit_window', ContactAgentConstants::REPLY_RATE_LIMIT_WINDOW ) );

		set_transient( $key, $attempts + 1, $window );
	}

	/**
	 * @param int $user_id    Admin user ID.
	 * @param int $inquiry_id Inquiry ID.
	 * @return string
	 */
	private function get_rate_limit_key( int $user_id, int $inquiry_id ): string {
		return ContactAgentConstants::REPLY_RATE_LIMIT_TRANSIENT_PREFIX . $user_id . '_' . $inquiry_id;
	}
}
