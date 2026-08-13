<?php
/**
 * Email verification factor — the first concrete identity factor.
 *
 * Owns the secure token lifecycle and the dedicated user-meta store. Enforced
 * generically by {@see IdentityVerificationService}; the authorization layer never
 * references this class directly.
 *
 * Backward-compatibility (SAFE-BY-DESIGN): only accounts that were EXPLICITLY marked
 * unverified (new registrations after this feature ships, or an email change) carry
 * `_hvnly_email_verified = '0'`. Any account with NO meta is a pre-existing/legacy
 * account and is treated as satisfied ("grandfathered"), so enabling enforcement can
 * never lock out an existing agent — even before the migration back-fill runs.
 *
 * Token scheme: selector + verifier. The selector is stored in plaintext for lookup;
 * only an HMAC-SHA256 of the verifier is stored. The raw verifier is never persisted
 * or logged. Verification is single-use (token deleted on success), time-limited, and
 * compared in constant time.
 *
 * @package HvnlyNab\Workspace\Identity
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\Identity;

use HvnlyNab\Workspace\Auth\AgentIdentityService;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.3.0
 */
final class EmailVerificationFactor implements VerificationFactorInterface {

	public const SLUG = 'email';

	/** '1' verified, '0' explicitly unverified, absent = legacy (grandfathered). */
	public const META_VERIFIED    = '_hvnly_email_verified';
	public const META_VERIFIED_AT = '_hvnly_email_verified_at';
	public const META_SELECTOR    = '_hvnly_email_verification_selector';
	public const META_TOKEN       = '_hvnly_email_verification_token';
	public const META_EXPIRES     = '_hvnly_email_verification_expires';
	public const META_SENT_AT     = '_hvnly_email_verification_sent_at';

	/** Marks the one-time migration back-fill as complete. */
	public const OPTION_MIGRATED = 'hvnly_email_verification_migrated';

	/** Admin emergency kill switch — when truthy, verification is not enforced. */
	public const OPTION_DISABLED = 'hvnly_email_verification_disabled';

	/**
	 * @inheritDoc
	 */
	public function slug(): string {
		return self::SLUG;
	}

	/**
	 * @inheritDoc
	 */
	public function label(): string {
		return __( 'Email Verification', 'havenlytics' );
	}

	/**
	 * @inheritDoc
	 */
	public function is_required_for_access( int $user_id ): bool {
		if ( $user_id > 0 && user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		// Admin emergency kill switch (option), overridable by the filter below.
		$enforced = ! (bool) get_option( self::OPTION_DISABLED, false );

		/**
		 * Emergency / system-wide toggle for email-verification enforcement.
		 *
		 * Return false to disable the requirement without a code change.
		 *
		 * @since 3.3.0
		 *
		 * @param bool $enforced Whether verification is required. Default true.
		 * @param int  $user_id  User ID.
		 */
		return (bool) apply_filters( 'hvnly_email_verification_enforced', $enforced, $user_id );
	}

	/**
	 * @inheritDoc
	 */
	public function is_satisfied( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$raw = (string) get_user_meta( $user_id, self::META_VERIFIED, true );
		if ( '1' === $raw ) {
			return true;
		}
		if ( '0' === $raw ) {
			return false;
		}

		// No explicit state. Grandfather is a TEMPORARY migration bridge ONLY — it applies
		// while the one-time back-fill has not yet completed, so no existing agent is locked
		// out during the upgrade window. Once migration is done, authorization is fully
		// deterministic and requires an explicit verified flag; there is no permanent
		// implicit behavior based on missing metadata.
		return ! $this->migration_complete();
	}

	/**
	 * Whether the one-time verification back-fill has completed (explicit-state cut-over).
	 *
	 * @return bool
	 */
	public function migration_complete(): bool {
		return (bool) get_option( self::OPTION_MIGRATED );
	}

	/**
	 * @inheritDoc
	 */
	public function state( int $user_id ): array {
		$user = $user_id > 0 ? get_userdata( $user_id ) : null;
		$raw  = (string) get_user_meta( $user_id, self::META_VERIFIED, true );
		$at   = (string) get_user_meta( $user_id, self::META_VERIFIED_AT, true );
		$sent = (int) get_user_meta( $user_id, self::META_SENT_AT, true );

		return array(
			'slug'       => self::SLUG,
			'label'      => $this->label(),
			'required'   => $this->is_required_for_access( $user_id ),
			'satisfied'  => $this->is_satisfied( $user_id ),
			'legacy'     => ( '' === $raw ),
			'email'      => $user instanceof \WP_User ? $user->user_email : '',
			'verifiedAt' => '' !== $at ? $at : null,
			'lastSentAt' => $sent > 0 ? gmdate( 'c', $sent ) : null,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Token lifecycle                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Verification token lifetime in seconds (clamped 1h–48h).
	 *
	 * @return int
	 */
	public function ttl(): int {
		/**
		 * Filter the verification-token lifetime.
		 *
		 * @since 3.3.0
		 *
		 * @param int $seconds Default DAY_IN_SECONDS (24h).
		 */
		$ttl = (int) apply_filters( 'hvnly_email_verification_ttl', DAY_IN_SECONDS );

		return max( HOUR_IN_SECONDS, min( 2 * DAY_IN_SECONDS, $ttl ) );
	}

	/**
	 * Mark a user explicitly unverified (new registration / email change) and mint a
	 * fresh single-use token. Returns the RAW token to embed in the verification link.
	 *
	 * @param int $user_id User ID.
	 * @return string Raw token ("selector.verifier"), or '' on failure.
	 */
	public function start_verification( int $user_id ): string {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return '';
		}

		update_user_meta( $user_id, self::META_VERIFIED, '0' );
		delete_user_meta( $user_id, self::META_VERIFIED_AT );

		try {
			$selector = bin2hex( random_bytes( 8 ) );
			$verifier = bin2hex( random_bytes( 32 ) );
		} catch ( \Throwable $e ) {
			return '';
		}

		update_user_meta( $user_id, self::META_SELECTOR, $selector );
		update_user_meta( $user_id, self::META_TOKEN, hash_hmac( 'sha256', $verifier, wp_salt( 'auth' ) ) );
		update_user_meta( $user_id, self::META_EXPIRES, time() + $this->ttl() );
		update_user_meta( $user_id, self::META_SENT_AT, time() );

		$this->clear_caches( $user_id );

		return $selector . '.' . $verifier;
	}

	/**
	 * Re-issue a token for a resend. Never reveals whether the account exists/needs it.
	 *
	 * @param int $user_id User ID.
	 * @return string Raw token, or '' when already verified / invalid (caller sends generic response).
	 */
	public function reissue( int $user_id ): string {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || $this->is_verified_explicit( $user_id ) ) {
			return '';
		}

		return $this->start_verification( $user_id );
	}

	/**
	 * Verify a raw token. Single-use, constant-time, expiry-checked.
	 *
	 * @param string $token Raw token from the link ("selector.verifier").
	 * @return array{ok:bool,user_id:int,reason:string}
	 */
	public function verify( string $token ): array {
		$parts = explode( '.', (string) $token, 2 );
		if ( 2 !== count( $parts ) ) {
			return $this->fail( 0, 'invalid' );
		}

		$selector = preg_replace( '/[^a-f0-9]/', '', $parts[0] );
		$verifier = preg_replace( '/[^a-f0-9]/', '', $parts[1] );
		if ( '' === $selector || '' === $verifier ) {
			return $this->fail( 0, 'invalid' );
		}

		$user_id = $this->find_user_by_selector( $selector );
		if ( $user_id <= 0 ) {
			return $this->fail( 0, 'invalid' );
		}

		$stored  = (string) get_user_meta( $user_id, self::META_TOKEN, true );
		$expires = (int) get_user_meta( $user_id, self::META_EXPIRES, true );
		$calc    = hash_hmac( 'sha256', $verifier, wp_salt( 'auth' ) );

		if ( '' === $stored || ! hash_equals( $stored, $calc ) ) {
			return $this->fail( $user_id, 'invalid' );
		}

		if ( $expires > 0 && time() > $expires ) {
			// Expired tokens are invalidated immediately (auto-cleanup on use).
			$this->clear_token( $user_id );

			/**
			 * Fires when a verification attempt is rejected because the token expired.
			 *
			 * @since 3.3.0
			 *
			 * @param int $user_id User ID.
			 */
			do_action( 'hvnly_email_verification_expired', $user_id );
			return $this->fail( $user_id, 'expired' );
		}

		$this->mark_verified( $user_id );

		return array(
			'ok'      => true,
			'user_id' => $user_id,
			'reason'  => 'verified',
		);
	}

	/**
	 * Force an account to verified (admin manual verify, or post-token success).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function mark_verified( int $user_id ): void {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}

		update_user_meta( $user_id, self::META_VERIFIED, '1' );
		update_user_meta( $user_id, self::META_VERIFIED_AT, gmdate( 'c' ) );
		$this->clear_token( $user_id );
		$this->clear_caches( $user_id );

		/**
		 * Fires immediately after an email address is successfully verified.
		 *
		 * @since 3.3.0
		 *
		 * @param int $user_id User ID.
		 */
		do_action( 'hvnly_email_verification_verified', $user_id );
	}

	/**
	 * Reset verification after an email change: mark unverified + mint a fresh token.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $old_email Previous email.
	 * @param string $new_email New email.
	 * @return string Raw token for the new verification link.
	 */
	public function reset_for_email_change( int $user_id, string $old_email = '', string $new_email = '' ): string {
		$token = $this->start_verification( $user_id );

		/**
		 * Fires when a verified agent changes their email and must re-verify.
		 *
		 * @since 3.3.0
		 *
		 * @param int    $user_id   User ID.
		 * @param string $old_email Previous email.
		 * @param string $new_email New email.
		 */
		do_action( 'hvnly_email_changed', $user_id, $old_email, $new_email );

		return $token;
	}

	/* ------------------------------------------------------------------ */
	/* Migration (idempotent)                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * One-time back-fill: stamp pre-existing workspace-accessible accounts as verified
	 * so admin views read cleanly. Not required for safety (legacy accounts are already
	 * grandfathered by {@see self::is_satisfied()}), but makes state explicit.
	 *
	 * Idempotent: skips accounts that already carry the meta and no-ops after completion.
	 *
	 * @param bool $force Re-run even when the completion flag is set.
	 * @return int Number of accounts stamped.
	 */
	public function backfill_existing( bool $force = false ): int {
		if ( ! $force && get_option( self::OPTION_MIGRATED ) ) {
			return 0;
		}

		$stamped = 0;
		$users   = get_users(
			array(
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => self::META_VERIFIED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( (array) $users as $uid ) {
			$uid = (int) $uid;
			if ( user_can( $uid, 'manage_options' ) ) {
				continue; // Admins never carry a verification requirement.
			}
			// Explicitly stamp every pre-existing account as verified so post-migration
			// authorization needs no implicit fallback. New registrations set '0'.
			update_user_meta( $uid, self::META_VERIFIED, '1' );
			update_user_meta( $uid, self::META_VERIFIED_AT, gmdate( 'c' ) );
			++$stamped;
		}

		update_option( self::OPTION_MIGRATED, gmdate( 'c' ), false );

		return $stamped;
	}

	/**
	 * Purge all expired verification tokens (scheduled cleanup / cron target).
	 *
	 * @return int Number of accounts whose expired token was cleared.
	 */
	public function cleanup_expired(): int {
		$users = get_users(
			array(
				'fields'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => self::META_EXPIRES,
						'value'   => time(),
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		foreach ( (array) $users as $uid ) {
			$this->clear_token( (int) $uid );
		}

		return count( (array) $users );
	}

	/* ------------------------------------------------------------------ */
	/* Internals                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Whether the account is explicitly verified ('1'). Legacy (no meta) is NOT explicit.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function is_verified_explicit( int $user_id ): bool {
		return '1' === (string) get_user_meta( $user_id, self::META_VERIFIED, true );
	}

	/**
	 * @param int    $user_id User ID (0 when unknown — no enumeration leak).
	 * @param string $reason  Failure reason.
	 * @return array{ok:bool,user_id:int,reason:string}
	 */
	private function fail( int $user_id, string $reason ): array {
		/**
		 * Fires when a verification attempt fails (invalid token).
		 *
		 * @since 3.3.0
		 *
		 * @param int    $user_id User ID (0 when not resolvable).
		 * @param string $reason  Failure reason slug.
		 */
		do_action( 'hvnly_email_verification_failed', $user_id, $reason );

		return array(
			'ok'      => false,
			'user_id' => $user_id,
			'reason'  => $reason,
		);
	}

	/**
	 * @param string $selector Selector.
	 * @return int User ID or 0.
	 */
	private function find_user_by_selector( string $selector ): int {
		if ( '' === $selector ) {
			return 0;
		}
		$ids = get_users(
			array(
				'fields'     => 'ids',
				'number'     => 1,
				'meta_key'   => self::META_SELECTOR, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $selector,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return empty( $ids ) ? 0 : (int) $ids[0];
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function clear_token( int $user_id ): void {
		delete_user_meta( $user_id, self::META_SELECTOR );
		delete_user_meta( $user_id, self::META_TOKEN );
		delete_user_meta( $user_id, self::META_EXPIRES );
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function clear_caches( int $user_id ): void {
		if ( class_exists( AgentIdentityService::class ) ) {
			AgentIdentityService::get_instance()->clear_cache( $user_id );
		}
	}
}
