<?php
/**
 * Spam protection for Contact Agent submissions (Phase 3).
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
class SpamGuard {

	/** @var string Honeypot field name — must match frontend form markup. */
	public const HONEYPOT_FIELD = 'hvnly_contact_website';

	/**
	 * Run spam checks on raw payload.
	 *
	 * @param array<string, mixed> $payload Raw input.
	 * @return true|\WP_Error True when clean; WP_Error when spam detected.
	 */
	public function verify( array $payload ) {
		if ( function_exists( 'hvnly_contact_agent_honeypot_enabled' ) && ! hvnly_contact_agent_honeypot_enabled() ) {
			return true;
		}

		$honeypot = isset( $payload[ self::HONEYPOT_FIELD ] )
			? trim( (string) $payload[ self::HONEYPOT_FIELD ] )
			: '';

		if ( '' !== $honeypot ) {
			return new \WP_Error(
				'hvnly_contact_agent_spam_detected',
				__( 'Spam detected.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		/**
		 * Additional spam checks (Phase 6 may add Akismet/time-trap settings).
		 *
		 * @since 3.0.2
		 *
		 * @param true|\WP_Error $result  Current result.
		 * @param array          $payload Raw payload.
		 */
		$result = apply_filters( 'hvnly_contact_agent_spam_verify', true, $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Whether the error indicates a honeypot/spam hit (respond silently).
	 *
	 * @param \WP_Error $error Error object.
	 * @return bool
	 */
	public function is_silent_rejection( \WP_Error $error ): bool {
		return 'hvnly_contact_agent_spam_detected' === $error->get_error_code();
	}
}
