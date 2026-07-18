<?php
/**
 * Email-change re-verification guard.
 *
 * When a non-admin account's email address changes (self-service profile edit or an
 * admin edit), the previous verification is revoked, ALL prior tokens are invalidated,
 * and a fresh verification email is sent to the NEW address. Access is withheld until
 * the new address is verified. Old links can never reactivate the account (single-token
 * store — the selector is overwritten).
 *
 * @package HvnlyNab\Workspace\Identity
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\Identity;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.3.0
 */
final class EmailChangeReverifier {

	/**
	 * @var EmailVerificationFactor
	 */
	private $factor;

	/**
	 * @var EmailVerificationNotifier
	 */
	private $notifier;

	/**
	 * @param EmailVerificationFactor|null   $factor   Factor.
	 * @param EmailVerificationNotifier|null $notifier Notifier.
	 */
	public function __construct( ?EmailVerificationFactor $factor = null, ?EmailVerificationNotifier $notifier = null ) {
		$this->factor   = $factor ? $factor : new EmailVerificationFactor();
		$this->notifier = $notifier ? $notifier : new EmailVerificationNotifier();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		// Fires for both self-service and admin edits after the user row is updated.
		add_action( 'profile_update', array( $this, 'on_profile_update' ), 10, 2 );
	}

	/**
	 * @param int      $user_id       User ID.
	 * @param \WP_User $old_user_data Pre-update user object.
	 * @return void
	 */
	public function on_profile_update( $user_id, $old_user_data ): void {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! $old_user_data instanceof \WP_User ) {
			return;
		}

		// Admins are never gated by verification — no reason to churn their token.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return;
		}

		// Skip demo / bulk import / migration contexts.
		if ( function_exists( 'hvnly_is_system_notification_context' ) && hvnly_is_system_notification_context() ) {
			return;
		}

		$fresh = get_userdata( $user_id );
		if ( ! $fresh instanceof \WP_User ) {
			return;
		}

		$old_email = (string) $old_user_data->user_email;
		$new_email = (string) $fresh->user_email;

		if ( '' === $new_email || strtolower( trim( $old_email ) ) === strtolower( trim( $new_email ) ) ) {
			return; // no email change.
		}

		// Revoke verification, mint a brand-new token (invalidating every prior one),
		// and email the NEW address.
		$token = $this->factor->reset_for_email_change( $user_id, $old_email, $new_email );
		if ( '' !== $token ) {
			$this->notifier->send_verification( $user_id, $token, false );
		}
	}
}
