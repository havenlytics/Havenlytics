<?php
/**
 * Contact Agent AJAX handler (Phase 3).
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * Handles public inquiry submission via admin-ajax.php.
 *
 * Phase 5: persists inquiries and sends email notifications (non-blocking).
 *
 * @since 3.0.2
 */
class AjaxHandler {

	/**
	 * @var ContactAgentModule
	 */
	private $module;

	/**
	 * @var bool
	 */
	private $hooks_registered = false;

	/**
	 * @param ContactAgentModule $module Module container.
	 */
	public function __construct( ContactAgentModule $module ) {
		$this->module = $module;
	}

	/**
	 * Register WordPress AJAX hooks (idempotent).
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( $this->hooks_registered ) {
			return;
		}

		$action = ContactAgentConstants::AJAX_SUBMIT_ACTION;

		// Central registration lives in ContactAgentAjaxRegistrar (HookManager).
		if ( has_action( 'wp_ajax_' . $action ) || has_action( 'wp_ajax_nopriv_' . $action ) ) {
			$this->hooks_registered = true;
			return;
		}

		add_action( 'wp_ajax_' . $action, array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'handle_submit' ) );

		$this->hooks_registered = true;

		/**
		 * Fires after Contact Agent AJAX hooks are registered.
		 *
		 * @since 3.0.2
		 *
		 * @param AjaxHandler $handler Handler instance.
		 */
		do_action( 'hvnly_contact_agent_ajax_registered', $this );
	}

	/**
	 * Handle inquiry submission.
	 *
	 * @return void
	 */
	public function handle_submit(): void {
		if ( ! function_exists( 'hvnly_is_contact_agent_enabled' ) || ! hvnly_is_contact_agent_enabled() ) {
			$this->send_error(
				__( 'Contact Agent is not available.', 'havenlytics' ),
				'hvnly_contact_agent_disabled',
				403
			);
		}

		if ( class_exists( '\HvnlyNab\ContactAgent\Database\InquirySchema' ) ) {
			\HvnlyNab\ContactAgent\Database\InquirySchema::create_table();
		}

		$nonce = isset( $_POST['nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['nonce'] ) )
			: '';

		if ( ! wp_verify_nonce( $nonce, ContactAgentConstants::AJAX_NONCE_ACTION ) ) {
			$this->send_error(
				__( 'Security check failed. Please refresh the page and try again.', 'havenlytics' ),
				'hvnly_contact_agent_invalid_nonce',
				403
			);
		}

		$payload = $this->collect_payload();
		$spam    = $this->module->get_spam_guard()->verify( $payload );

		if ( is_wp_error( $spam ) ) {
			if ( $this->module->get_spam_guard()->is_silent_rejection( $spam ) ) {
				$this->send_success(
					array(
						'message' => __( 'Thank you. Your message has been sent.', 'havenlytics' ),
					)
				);
			}

			$this->send_error( $spam->get_error_message(), $spam->get_error_code(), 400 );
		}

		$property_id = isset( $payload['property_id'] ) ? absint( $payload['property_id'] ) : 0;
		$client_ip   = function_exists( 'hvnly_contact_agent_get_client_ip' )
			? hvnly_contact_agent_get_client_ip()
			: '';

		$rate = $this->module->get_rate_limiter()->allow( $client_ip, $property_id );
		if ( is_wp_error( $rate ) ) {
			$this->send_error( $rate->get_error_message(), $rate->get_error_code(), 429 );
		}

		$validated = $this->module->get_validator()->validate( $payload );
		if ( is_wp_error( $validated ) ) {
			$this->send_error( $validated->get_error_message(), $validated->get_error_code(), 400 );
		}

		$persist_payload = array_merge(
			$validated,
			array(
				'ip_address' => $client_ip,
				'user_agent' => $this->get_user_agent(),
			)
		);

		/**
		 * Fires after an inquiry passes validation and before persistence.
		 *
		 * @since 3.0.2
		 *
		 * @param array<string, mixed> $persist_payload Sanitized inquiry payload.
		 */
		do_action( 'hvnly_contact_agent_inquiry_validated', $persist_payload );

		$inquiry_id = $this->module->get_repository()->create( $persist_payload );
		if ( is_wp_error( $inquiry_id ) ) {
			$this->send_error( $inquiry_id->get_error_message(), $inquiry_id->get_error_code(), 500 );
		}

		$this->module->get_rate_limiter()->record( $client_ip, (int) $validated['property_id'] );

		$inquiry = array_merge(
			$persist_payload,
			array(
				'id'         => (int) $inquiry_id,
				'status'     => ContactAgentConstants::STATUS_NEW,
				'created_at' => current_time( 'mysql', true ),
			)
		);

		$agent = InquiryAgentResolver::profile_from_inquiry( $inquiry );

		if ( empty( $agent ) && function_exists( 'hvnly_get_property_agent' ) ) {
			$agent = hvnly_get_property_agent( (int) $validated['property_id'] );
		}

		$this->module->get_notifier()->send(
			(int) $inquiry_id,
			$inquiry,
			is_array( $agent ) ? $agent : array()
		);

		$success_message = function_exists( 'hvnly_contact_agent_get_success_message' )
			? hvnly_contact_agent_get_success_message( $inquiry, is_array( $agent ) ? $agent : array() )
			: __( 'Thank you. Your message has been received.', 'havenlytics' );

		$this->send_success(
			array(
				'message'    => $success_message,
				'inquiryId'  => (int) $inquiry_id,
			)
		);
	}

	/**
	 * Collect POST payload for validation.
	 *
	 * @return array<string, mixed>
	 */
	private function collect_payload(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in handle_submit().
		return array(
			'property_id'             => isset( $_POST['property_id'] ) ? absint( wp_unslash( $_POST['property_id'] ) ) : 0,
			'agent_id'                => isset( $_POST['agent_id'] ) ? absint( wp_unslash( $_POST['agent_id'] ) ) : 0,
			'sender_name'             => isset( $_POST['sender_name'] ) ? wp_unslash( $_POST['sender_name'] ) : '',
			'sender_email'            => isset( $_POST['sender_email'] ) ? wp_unslash( $_POST['sender_email'] ) : '',
			'sender_phone'            => isset( $_POST['sender_phone'] ) ? wp_unslash( $_POST['sender_phone'] ) : '',
			'message'                 => isset( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '',
			SpamGuard::HONEYPOT_FIELD => isset( $_POST[ SpamGuard::HONEYPOT_FIELD ] )
				? wp_unslash( $_POST[ SpamGuard::HONEYPOT_FIELD ] )
				: '',
		);
	}

	/**
	 * @return string
	 */
	private function get_user_agent(): string {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}

	/**
	 * @param array<string, mixed> $data Response data.
	 * @return void
	 */
	private function send_success( array $data ): void {
		wp_send_json_success( $data );
	}

	/**
	 * @param string $message Error message.
	 * @param string $code    Error code.
	 * @param int    $status  HTTP-like status for clients.
	 * @return void
	 */
	private function send_error( string $message, string $code, int $status ): void {
		wp_send_json_error(
			array(
				'message' => $message,
				'code'    => $code,
				'status'  => $status,
			),
			$status
		);
	}
}
