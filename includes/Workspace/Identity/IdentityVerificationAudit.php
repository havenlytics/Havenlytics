<?php
/**
 * Identity verification audit trail.
 *
 * Subscribes to the verification lifecycle actions and records a structured,
 * investigation-ready entry for each event. Entries NEVER contain tokens (raw or
 * hashed) — only non-secret metadata. Each entry is also broadcast on
 * `hvnly_identity_verification_logged` so a SIEM / external store can consume it.
 *
 * @package HvnlyNab\Workspace\Identity
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\Identity;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.3.0
 */
final class IdentityVerificationAudit {

	/**
	 * Register the audit listeners.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'hvnly_email_verification_verified', array( $this, 'on_verified' ), 10, 1 );
		add_action( 'hvnly_email_verification_failed', array( $this, 'on_failed' ), 10, 2 );
		add_action( 'hvnly_email_verification_expired', array( $this, 'on_expired' ), 10, 1 );
		add_action( 'hvnly_email_verification_resent', array( $this, 'on_resent' ), 10, 1 );
		add_action( 'hvnly_email_verification_sent', array( $this, 'on_sent' ), 10, 1 );
		add_action( 'hvnly_email_changed', array( $this, 'on_email_changed' ), 10, 3 );
		add_action( 'hvnly_email_verification_admin_verified', array( $this, 'on_admin_verified' ), 10, 2 );
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_verified( $user_id ): void {
		$this->record( 'verified', (int) $user_id, true );
	}

	/**
	 * @param int    $user_id User ID (0 when unresolved).
	 * @param string $reason  Failure reason.
	 * @return void
	 */
	public function on_failed( $user_id, $reason = '' ): void {
		$this->record( 'failed', (int) $user_id, false, array( 'reason' => sanitize_key( (string) $reason ) ) );
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_expired( $user_id ): void {
		$this->record( 'expired', (int) $user_id, false );
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_resent( $user_id ): void {
		$this->record( 'resent', (int) $user_id, true );
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_sent( $user_id ): void {
		$this->record( 'email_sent', (int) $user_id, true );
	}

	/**
	 * @param int    $user_id   User ID.
	 * @param string $old_email Old email.
	 * @param string $new_email New email.
	 * @return void
	 */
	public function on_email_changed( $user_id, $old_email = '', $new_email = '' ): void {
		// Emails are hashed in the log so the trail can correlate a change without
		// persisting personal data in plaintext debug output.
		$this->record(
			'email_changed',
			(int) $user_id,
			true,
			array(
				'old_email_hash' => $this->hash_pii( (string) $old_email ),
				'new_email_hash' => $this->hash_pii( (string) $new_email ),
			)
		);
	}

	/**
	 * @param int $user_id  User ID.
	 * @param int $admin_id Administrator who performed the action.
	 * @return void
	 */
	public function on_admin_verified( $user_id, $admin_id = 0 ): void {
		$this->record( 'admin_verified', (int) $user_id, true, array( 'admin_id' => (int) $admin_id ) );
	}

	/**
	 * Write one structured audit entry. Never includes tokens.
	 *
	 * @param string               $event   Event type.
	 * @param int                  $user_id Subject user ID.
	 * @param bool                 $success Whether the event was a success.
	 * @param array<string, mixed> $extra   Extra non-secret fields.
	 * @return void
	 */
	private function record( string $event, int $user_id, bool $success, array $extra = array() ): void {
		$entry = array_merge(
			array(
				'event'     => $event,
				'user_id'   => $user_id,
				'success'   => $success,
				'actor_id'  => get_current_user_id(),
				'timestamp' => gmdate( 'c' ),
				'ip'        => $this->client_ip(),
				'ua'        => $this->user_agent(),
				'context'   => $this->request_context(),
			),
			$extra
		);

		/**
		 * Filter an identity-verification audit entry before it is recorded.
		 *
		 * Use this to redact / anonymize sensitive fields for privacy compliance —
		 * e.g. drop the user agent, or anonymize the IP:
		 *
		 *     add_filter( 'hvnly_identity_audit_entry', function ( $e ) {
		 *         $e['ip'] = preg_replace( '/\.\d+$/', '.0', (string) $e['ip'] ); // mask last octet
		 *         unset( $e['ua'] );
		 *         return $e;
		 *     } );
		 *
		 * Tokens are never present in the entry, so this filter cannot expose them.
		 *
		 * @since 3.3.0
		 *
		 * @param array<string, mixed> $entry Audit entry (no tokens).
		 */
		$entry = (array) apply_filters( 'hvnly_identity_audit_entry', $entry );

		/**
		 * Fires with a structured identity-verification audit entry (no tokens).
		 *
		 * Consume this to forward the trail to an external SIEM / log store. The
		 * built-in sink is the gated debug log (file), so nothing accumulates in the
		 * database; retention is governed by the host's log rotation.
		 *
		 * @since 3.3.0
		 *
		 * @param array<string, mixed> $entry Audit entry.
		 */
		do_action( 'hvnly_identity_verification_logged', $entry );

		if ( function_exists( 'hvnly_debug_log' ) ) {
			hvnly_debug_log( (string) wp_json_encode( $entry ), 'Havenlytics Identity' );
		}
	}

	/**
	 * @return string
	 */
	private function client_ip(): string {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return '';
		}
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * @return string
	 */
	private function user_agent(): string {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 );
	}

	/**
	 * Transport context so the trail distinguishes web / REST / CLI / cron.
	 *
	 * @return string
	 */
	private function request_context(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return 'cron';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return 'ajax';
		}

		return 'web';
	}

	/**
	 * One-way hash of PII for correlation without storing plaintext.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function hash_pii( string $value ): string {
		$value = strtolower( trim( $value ) );

		return '' === $value ? '' : substr( hash_hmac( 'sha256', $value, wp_salt( 'auth' ) ), 0, 16 );
	}
}
