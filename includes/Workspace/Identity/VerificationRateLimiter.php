<?php
/**
 * Rate limiter for the public verification endpoints.
 *
 * Transient-backed counters + cooldowns (no custom table). Keys are hashed so raw
 * IPs / identifiers are never stored as option names. All limits are filterable.
 *
 * @package HvnlyNab\Workspace\Identity
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\Identity;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.3.0
 */
final class VerificationRateLimiter {

	/**
	 * Whether a fixed-window counter has reached its ceiling. Increments on each call
	 * that is still under the ceiling.
	 *
	 * @param string $bucket Logical bucket (e.g. 'verify_ip').
	 * @param string $id     Identifier (IP, user id, selector).
	 * @param int    $max    Max events per window.
	 * @param int    $window Window length in seconds.
	 * @return bool True when the ceiling is already reached (block the request).
	 */
	public function exceeded( string $bucket, string $id, int $max, int $window ): bool {
		$key   = $this->key( $bucket, $id );
		$count = (int) get_transient( $key );

		if ( $count >= $max ) {
			return true;
		}

		set_transient( $key, $count + 1, $window );

		return false;
	}

	/**
	 * Whether a cooldown is active for this identifier. Starts a fresh cooldown when
	 * none is active.
	 *
	 * @param string $bucket   Bucket (e.g. 'resend_cooldown').
	 * @param string $id       Identifier.
	 * @param int    $cooldown Cooldown length in seconds.
	 * @return bool True when still cooling down (block the request).
	 */
	public function in_cooldown( string $bucket, string $id, int $cooldown ): bool {
		$key = $this->key( $bucket, $id );

		if ( get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, time(), $cooldown );

		return false;
	}

	/**
	 * Best-effort client IP (proxy headers are intentionally NOT trusted by default).
	 *
	 * @return string
	 */
	public function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filter the client IP used for verification rate limiting.
		 *
		 * Only override behind a trusted proxy you control.
		 *
		 * @since 3.3.0
		 *
		 * @param string $ip Remote address.
		 */
		$ip = (string) apply_filters( 'hvnly_verification_client_ip', $ip );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * @param string $bucket Bucket.
	 * @param string $id     Identifier.
	 * @return string Transient key (hashed, length-safe).
	 */
	private function key( string $bucket, string $id ): string {
		return 'hvnly_vrl_' . $bucket . '_' . substr( hash_hmac( 'sha256', $id, wp_salt( 'auth' ) ), 0, 20 );
	}
}
