<?php
/**
 * Identity verification layer (generic factor composition).
 *
 * Answers "who is this user, and has their identity been verified?" — independent of
 * WorkspaceRegistrationStatus, which answers "what is this user allowed to do?".
 * The authorization layer ({@see \HvnlyNab\Workspace\Auth\PortalAuthorization}) calls
 * only {@see self::required_factors_satisfied()} and never references a concrete factor,
 * so future factors (phone, 2FA, KYC, …) plug in via the `hvnly_identity_factors`
 * filter with no authorization changes.
 *
 * @package HvnlyNab\Workspace\Identity
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\Identity;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.3.0
 */
final class IdentityVerificationService {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Per-request factor registry cache.
	 *
	 * @var array<string, VerificationFactorInterface>|null
	 */
	private $factors = null;

	/**
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registered factors, keyed by slug. Email ships by default.
	 *
	 * @return array<string, VerificationFactorInterface>
	 */
	public function factors(): array {
		if ( null !== $this->factors ) {
			return $this->factors;
		}

		$factors = array(
			EmailVerificationFactor::SLUG => new EmailVerificationFactor(),
		);

		/**
		 * Filter the registered identity-verification factors.
		 *
		 * Add a factor by appending a {@see VerificationFactorInterface} keyed by its slug.
		 * This is the ONLY extension point required to add phone / 2FA / KYC / company /
		 * government-ID verification — the authorization flow never changes.
		 *
		 * @since 3.3.0
		 *
		 * @param array<string, VerificationFactorInterface> $factors Factor registry.
		 */
		$factors = (array) apply_filters( 'hvnly_identity_factors', $factors );

		// Keep only valid factors, keyed by their own slug.
		$clean = array();
		foreach ( $factors as $factor ) {
			if ( $factor instanceof VerificationFactorInterface ) {
				$clean[ $factor->slug() ] = $factor;
			}
		}

		$this->factors = $clean;

		return $this->factors;
	}

	/**
	 * Condition 2 of the two-condition access model: every REQUIRED factor is satisfied.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function required_factors_satisfied( int $user_id ): bool {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		foreach ( $this->factors() as $factor ) {
			if ( $factor->is_required_for_access( $user_id ) && ! $factor->is_satisfied( $user_id ) ) {
				return false;
			}
		}

		/**
		 * Filter the final identity-satisfaction decision (behavior override).
		 *
		 * @since 3.3.0
		 *
		 * @param bool $satisfied Whether all required factors passed.
		 * @param int  $user_id   User ID.
		 */
		return (bool) apply_filters( 'hvnly_identity_verification_satisfied', true, $user_id );
	}

	/**
	 * Full factor state for /me, the Security Center, and admin views.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public function state_for_user( int $user_id ): array {
		$factors = array();
		foreach ( $this->factors() as $slug => $factor ) {
			$factors[ $slug ] = $factor->state( $user_id );
		}

		return array(
			'satisfied' => $this->required_factors_satisfied( $user_id ),
			'factors'   => $factors,
		);
	}

	/**
	 * @param string $slug Factor slug.
	 * @return VerificationFactorInterface|null
	 */
	public function get_factor( string $slug ): ?VerificationFactorInterface {
		$factors = $this->factors();

		return isset( $factors[ $slug ] ) ? $factors[ $slug ] : null;
	}

	/**
	 * Convenience accessor for the email factor.
	 *
	 * @return EmailVerificationFactor
	 */
	public function email(): EmailVerificationFactor {
		$factor = $this->get_factor( EmailVerificationFactor::SLUG );

		return $factor instanceof EmailVerificationFactor ? $factor : new EmailVerificationFactor();
	}

	/**
	 * Reset the per-request factor cache (tests / factor re-registration).
	 *
	 * @return void
	 */
	public function reset_cache(): void {
		$this->factors = null;
	}
}
