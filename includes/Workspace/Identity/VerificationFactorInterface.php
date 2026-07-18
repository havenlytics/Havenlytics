<?php
/**
 * Identity verification factor contract.
 *
 * A factor answers ONE question about a user's identity ("is the email verified?",
 * "is 2FA enrolled?", "is the phone confirmed?"). The authorization layer composes
 * factors generically via {@see IdentityVerificationService} and never names any
 * concrete factor — new factors (phone, 2FA, KYC, …) are added by implementing this
 * interface and registering through the `hvnly_identity_factors` filter, with zero
 * changes to the authorization flow.
 *
 * @package HvnlyNab\Workspace\Identity
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\Identity;

defined( 'ABSPATH' ) || exit;

/**
 * A single, composable identity-verification factor.
 *
 * @since 3.3.0
 */
interface VerificationFactorInterface {

	/**
	 * Stable machine slug (e.g. 'email', 'phone', '2fa').
	 *
	 * @return string
	 */
	public function slug(): string;

	/**
	 * Human, translation-ready label for UI (Security Center / admin).
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Whether this factor must pass before the user may access the Workspace.
	 *
	 * Return false to make the factor optional (informational only). Administrators
	 * and an emergency system toggle can relax this; the authorization layer only
	 * enforces factors for which this returns true.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_required_for_access( int $user_id ): bool;

	/**
	 * Whether the user currently satisfies this factor.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_satisfied( int $user_id ): bool;

	/**
	 * Serializable state for /me, the Security Center, and admin views.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public function state( int $user_id ): array;
}
