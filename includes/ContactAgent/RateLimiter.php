<?php
/**
 * Submission rate limiter (Phase 3).
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
class RateLimiter {

	/**
	 * Determine whether the client may submit another inquiry.
	 *
	 * @param string $ip_address  Client IP.
	 * @param int    $property_id Property ID (reserved for per-property limits in Phase 6).
	 * @return true|\WP_Error
	 */
	public function allow( string $ip_address, int $property_id ) {
		unset( $property_id );

		$ip_address = trim( $ip_address );
		if ( '' === $ip_address ) {
			return new \WP_Error(
				'hvnly_contact_agent_rate_limit',
				__( 'Unable to process your request. Please try again later.', 'havenlytics' ),
				array( 'status' => 429 )
			);
		}

		$attempts = (int) get_transient( $this->get_transient_key( $ip_address ) );
		$max      = $this->get_max_attempts();

		if ( $attempts >= $max ) {
			return new \WP_Error(
				'hvnly_contact_agent_rate_limit',
				__( 'Too many messages sent. Please wait before trying again.', 'havenlytics' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Record a successful submission for rate limiting.
	 *
	 * @param string $ip_address  Client IP.
	 * @param int    $property_id Property ID.
	 * @return void
	 */
	public function record( string $ip_address, int $property_id ): void {
		unset( $property_id );

		$ip_address = trim( $ip_address );
		if ( '' === $ip_address ) {
			return;
		}

		$key      = $this->get_transient_key( $ip_address );
		$attempts = (int) get_transient( $key );
		$window   = $this->get_window_seconds();

		set_transient( $key, $attempts + 1, $window );
	}

	/**
	 * @param string $ip_address Client IP.
	 * @return string
	 */
	private function get_transient_key( string $ip_address ): string {
		return ContactAgentConstants::RATE_LIMIT_TRANSIENT_PREFIX . md5( $ip_address );
	}

	/**
	 * @return int
	 */
	private function get_max_attempts(): int {
		if ( function_exists( 'hvnly_contact_agent_rate_limit_max' ) ) {
			return hvnly_contact_agent_rate_limit_max();
		}

		return max( 1, (int) apply_filters( 'hvnly_contact_agent_rate_limit_max', ContactAgentConstants::RATE_LIMIT_MAX ) );
	}

	/**
	 * @return int
	 */
	private function get_window_seconds(): int {
		if ( function_exists( 'hvnly_contact_agent_rate_limit_window' ) ) {
			return hvnly_contact_agent_rate_limit_window();
		}

		return max( 60, (int) apply_filters( 'hvnly_contact_agent_rate_limit_window', ContactAgentConstants::RATE_LIMIT_WINDOW ) );
	}
}
