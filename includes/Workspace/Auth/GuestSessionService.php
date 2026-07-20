<?php
/**
 * Read-only guest sessions for the Agent Workspace.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.4.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Workspace\Identity\VerificationRateLimiter;
use HvnlyNab\Workspace\WorkspaceAvailability;
use HvnlyNab\Workspace\WorkspaceConstants;

defined( 'ABSPATH' ) || exit;

/**
 * Ephemeral, read-only Workspace access for visitors.
 *
 * ## Security model
 *
 * A guest session is **not** a WordPress login. No user is created, no role is
 * assigned, and {@see wp_set_current_user()} is never called. `is_user_logged_in()`
 * stays false and `get_current_user_id()` stays `0` for the entire request.
 *
 * That is the whole design: every existing capability check, nonce check and
 * ownership check in the plugin already denies user `0`, so guest mode cannot
 * accidentally inherit a write path. Access is *additive* and limited to the
 * explicit read-only endpoints on {@see \HvnlyNab\Workspace\Api\GuestController}.
 *
 * The session itself is a random token stored server-side in a transient, so it
 * expires on its own and can be revoked. The cookie carries only an opaque
 * selector plus an HMAC — it grants nothing if the transient is gone.
 *
 * @since 3.4.0
 */
final class GuestSessionService {

	/**
	 * Cookie carrying the guest selector + signature.
	 *
	 * @var string
	 */
	public const COOKIE = 'hvnly_ws_guest';

	/**
	 * Transient key prefix for the server-side record.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'hvnly_guest_';

	/**
	 * Default session lifetime in seconds.
	 *
	 * @var int
	 */
	private const DEFAULT_TTL = 1800;

	/**
	 * Per-IP guest sessions allowed per window.
	 *
	 * @var int
	 */
	private const RATE_MAX = 10;

	/**
	 * Rate-limit window in seconds.
	 *
	 * @var int
	 */
	private const RATE_WINDOW = 600;

	/**
	 * Resolved session for this request (false = not yet resolved).
	 *
	 * @var array<string, mixed>|null|false
	 */
	private $resolved = false;

	/**
	 * @var VerificationRateLimiter
	 */
	private $limiter;

	/**
	 * @param VerificationRateLimiter|null $limiter Rate limiter.
	 */
	public function __construct( ?VerificationRateLimiter $limiter = null ) {
		$this->limiter = $limiter ? $limiter : new VerificationRateLimiter();
	}

	/**
	 * Whether guest login is offered on this site.
	 *
	 * Off by default — an administrator opts in. Guest mode is also hard-tied
	 * to Workspace availability: if the Workspace is disabled there is nothing
	 * to tour.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		if ( ! WorkspaceAvailability::is_available() ) {
			return false;
		}

		$stored  = get_option( WorkspaceConstants::OPTION_KEY, array() );
		$enabled = is_array( $stored ) && ! empty( $stored['guest_login_enabled'] );

		// Prefer the Settings UI value when present, exactly as
		// WorkspaceSettings::is_enabled() does. The mirrored option above is
		// written by the settings-sync hook; reading the group directly means
		// the admin toggle still takes effect if that sync has not run yet,
		// rather than appearing on while doing nothing.
		$plugin_settings = get_option( 'hvnly_plugin_settings', array() );
		if ( is_array( $plugin_settings )
			&& ! empty( $plugin_settings['contact-agent'] )
			&& is_array( $plugin_settings['contact-agent'] )
			&& array_key_exists( 'hvnly_workspace_guest_login', $plugin_settings['contact-agent'] )
		) {
			$enabled = ! empty( $plugin_settings['contact-agent']['hvnly_workspace_guest_login'] );
		}

		// Emergency kill only — a constant can force OFF but never force ON.
		if ( defined( 'HVNLY_WORKSPACE_GUEST_ENABLED' ) && ! HVNLY_WORKSPACE_GUEST_ENABLED ) {
			$enabled = false;
		}

		/**
		 * Filter whether read-only guest login is available.
		 *
		 * @since 3.4.0
		 *
		 * @param bool $enabled Whether guest login is enabled.
		 */
		return (bool) apply_filters( 'hvnly_workspace_guest_login_enabled', $enabled );
	}

	/**
	 * Session lifetime in seconds.
	 *
	 * @return int
	 */
	public static function ttl(): int {
		/**
		 * Filter the guest session lifetime.
		 *
		 * @since 3.4.0
		 *
		 * @param int $ttl Lifetime in seconds.
		 */
		$ttl = (int) apply_filters( 'hvnly_workspace_guest_session_ttl', self::DEFAULT_TTL );

		// Clamp: never eternal, never so short the tour is unusable.
		return max( 300, min( DAY_IN_SECONDS, $ttl ) );
	}

	/**
	 * Start a guest session and set the cookie.
	 *
	 * @return array<string, mixed>|\WP_Error Session payload or error.
	 */
	public function create() {
		if ( ! self::is_enabled() ) {
			return new \WP_Error(
				'hvnly_guest_disabled',
				__( 'Guest access is not available on this site.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		// A signed-in user has no business holding a guest session — it would
		// only create an ambiguous "who am I" state in the SPA.
		if ( is_user_logged_in() ) {
			return new \WP_Error(
				'hvnly_guest_already_signed_in',
				__( 'You are already signed in.', 'havenlytics' ),
				array( 'status' => 409 )
			);
		}

		if ( $this->limiter->exceeded( 'guest_session', $this->limiter->ip(), self::RATE_MAX, self::RATE_WINDOW ) ) {
			return new \WP_Error(
				'hvnly_guest_rate_limited',
				__( 'Too many guest sessions from this address. Please try again later.', 'havenlytics' ),
				array( 'status' => 429 )
			);
		}

		$selector = wp_generate_password( 24, false, false );
		$ttl      = self::ttl();
		$expires  = time() + $ttl;

		$record = array(
			'selector'   => $selector,
			'created_at' => time(),
			'expires_at' => $expires,
		);

		set_transient( self::TRANSIENT_PREFIX . $this->hash( $selector ), $record, $ttl );

		$this->set_cookie( $selector, $expires );

		$this->resolved = $record;

		/**
		 * Fires when a read-only guest session begins.
		 *
		 * @since 3.4.0
		 *
		 * @param string $selector Opaque session selector.
		 */
		do_action( 'hvnly_workspace_guest_session_started', $selector );

		return array(
			'active'    => true,
			'expiresAt' => $expires,
			'expiresIn' => $ttl,
		);
	}

	/**
	 * End the current guest session.
	 *
	 * @return void
	 */
	public function destroy(): void {
		$selector = $this->selector_from_cookie();

		if ( '' !== $selector ) {
			delete_transient( self::TRANSIENT_PREFIX . $this->hash( $selector ) );

			/**
			 * Fires when a guest session ends.
			 *
			 * @since 3.4.0
			 *
			 * @param string $selector Opaque session selector.
			 */
			do_action( 'hvnly_workspace_guest_session_ended', $selector );
		}

		$this->clear_cookie();

		$this->resolved = null;
	}

	/**
	 * The active guest session for this request, if any.
	 *
	 * @return array<string, mixed>|null
	 */
	public function current(): ?array {
		if ( false !== $this->resolved ) {
			return $this->resolved;
		}

		$this->resolved = null;

		if ( ! self::is_enabled() || is_user_logged_in() ) {
			return null;
		}

		$selector = $this->selector_from_cookie();
		if ( '' === $selector ) {
			return null;
		}

		$record = get_transient( self::TRANSIENT_PREFIX . $this->hash( $selector ) );
		if ( ! is_array( $record ) || empty( $record['expires_at'] ) ) {
			return null;
		}

		// Transients can outlive their TTL when the object cache is external
		// or cron is starved — never trust expiry to the storage layer alone.
		if ( (int) $record['expires_at'] <= time() ) {
			delete_transient( self::TRANSIENT_PREFIX . $this->hash( $selector ) );
			return null;
		}

		$this->resolved = $record;

		return $record;
	}

	/**
	 * Whether this request is an active guest session.
	 *
	 * @return bool
	 */
	public function is_guest(): bool {
		return null !== $this->current();
	}

	/**
	 * Payload describing guest state for the SPA boot data.
	 *
	 * @return array<string, mixed>
	 */
	public function boot_payload(): array {
		$session = $this->current();

		return array(
			'enabled'   => self::is_enabled(),
			'active'    => null !== $session,
			'expiresAt' => $session ? (int) $session['expires_at'] : 0,
			'ttl'       => self::ttl(),
		);
	}

	/**
	 * Validate and extract the selector from the request cookie.
	 *
	 * @return string Selector, or '' when absent/tampered.
	 */
	private function selector_from_cookie(): string {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return '';
		}

		$raw = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) );

		$parts = explode( '.', $raw );
		if ( count( $parts ) !== 2 ) {
			return '';
		}

		list( $selector, $signature ) = $parts;

		if ( '' === $selector || '' === $signature ) {
			return '';
		}

		// Constant-time compare — a fast-exit strcmp here would leak the
		// signature one byte at a time.
		if ( ! hash_equals( $this->sign( $selector ), $signature ) ) {
			return '';
		}

		return $selector;
	}

	/**
	 * @param string $selector Selector.
	 * @param int    $expires  Expiry timestamp.
	 * @return void
	 */
	private function set_cookie( string $selector, int $expires ): void {
		$value = $selector . '.' . $this->sign( $selector );

		// Headers are already sent during some template paths; the SPA reloads
		// after starting a session, so a missed cookie surfaces immediately
		// rather than silently half-working.
		if ( headers_sent() ) {
			return;
		}

		$secure = is_ssl();

		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie(
				self::COOKIE,
				$value,
				array(
					'expires'  => $expires,
					'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
					'secure'   => $secure,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
			return;
		}

		setcookie(
			self::COOKIE,
			$value,
			$expires,
			( defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/' ),
			( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '' ),
			$secure,
			true
		);
	}

	/**
	 * @return void
	 */
	private function clear_cookie(): void {
		unset( $_COOKIE[ self::COOKIE ] );

		if ( headers_sent() ) {
			return;
		}

		setcookie(
			self::COOKIE,
			'',
			time() - YEAR_IN_SECONDS,
			( defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/' ),
			( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '' ),
			is_ssl(),
			true
		);
	}

	/**
	 * HMAC of a selector, for the cookie signature.
	 *
	 * @param string $selector Selector.
	 * @return string
	 */
	private function sign( string $selector ): string {
		return hash_hmac( 'sha256', 'hvnly_guest|' . $selector, wp_salt( 'auth' ) );
	}

	/**
	 * Storage hash of a selector — the raw selector is never a key name.
	 *
	 * @param string $selector Selector.
	 * @return string
	 */
	private function hash( string $selector ): string {
		return substr( hash_hmac( 'sha256', $selector, wp_salt( 'secure_auth' ) ), 0, 32 );
	}
}
