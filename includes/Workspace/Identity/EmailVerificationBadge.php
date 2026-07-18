<?php
/**
 * Email verification badge renderer (SPA + admin surfaces).
 *
 * Mirrors WorkspaceRegistrationStatus::badge_html() conventions. Escapes all output.
 *
 * @package HvnlyNab\Workspace\Identity
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\Identity;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.3.0
 */
final class EmailVerificationBadge {

	/**
	 * Badge HTML for a user.
	 *
	 * @param int  $user_id  User ID.
	 * @param bool $with_date Append the verification date when verified.
	 * @return string
	 */
	public static function html( int $user_id, bool $with_date = true ): string {
		$factor   = new EmailVerificationFactor();
		$verified = $factor->is_satisfied( $user_id );

		if ( $verified ) {
			$icon  = '✔';
			$state = 'verified';
			$label = __( 'Email Verified', 'havenlytics' );
			$at    = (string) get_user_meta( $user_id, EmailVerificationFactor::META_VERIFIED_AT, true );
			if ( $with_date && '' !== $at ) {
				$ts = strtotime( $at );
				if ( $ts ) {
					$label .= ' · ' . date_i18n( (string) get_option( 'date_format' ), $ts );
				}
			}
		} else {
			$icon  = '🟠';
			$state = 'unverified';
			$label = __( 'Email Not Verified', 'havenlytics' );
		}

		return sprintf(
			'<span class="hvnly-email-badge hvnly-email-badge--%1$s"><span class="hvnly-email-badge__icon" aria-hidden="true">%2$s</span> <span class="hvnly-email-badge__label">%3$s</span></span>',
			esc_attr( $state ),
			esc_html( $icon ),
			esc_html( $label )
		);
	}

	/**
	 * Plain state slug ('verified' | 'unverified').
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function state( int $user_id ): string {
		return ( new EmailVerificationFactor() )->is_satisfied( $user_id ) ? 'verified' : 'unverified';
	}
}
