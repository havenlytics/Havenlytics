<?php
/**
 * Administrator tools for email verification.
 *
 * Adds an "Email" verified column to the Users list and three capability-checked,
 * nonce-protected, audit-logged admin actions: manually verify an account, resend the
 * verification email, and toggle the emergency system-wide disable. All actions require
 * `manage_options` and log via the identity audit trail.
 *
 * @package HvnlyNab\Workspace\Identity
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\Identity;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.3.0
 */
final class EmailVerificationAdminActions {

	private const ACTION_VERIFY  = 'hvnly_admin_verify_email';
	private const ACTION_RESEND  = 'hvnly_admin_resend_verification';
	private const ACTION_TOGGLE  = 'hvnly_admin_toggle_verification_enforcement';

	/**
	 * @return void
	 */
	public function register(): void {
		add_filter( 'manage_users_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_column' ), 10, 3 );

		add_action( 'admin_post_' . self::ACTION_VERIFY, array( $this, 'handle_verify' ) );
		add_action( 'admin_post_' . self::ACTION_RESEND, array( $this, 'handle_resend' ) );
		add_action( 'admin_post_' . self::ACTION_TOGGLE, array( $this, 'handle_toggle' ) );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_column( $columns ) {
		if ( is_array( $columns ) ) {
			$columns['hvnly_email_verified'] = __( 'Email', 'havenlytics' );
		}
		return $columns;
	}

	/**
	 * @param string $output      Column HTML.
	 * @param string $column_name Column key.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public function render_column( $output, $column_name, $user_id ) {
		if ( 'hvnly_email_verified' !== $column_name ) {
			return $output;
		}

		$user_id = (int) $user_id;
		$badge   = EmailVerificationBadge::html( $user_id );

		// Inline admin actions (only for non-admin accounts).
		$links = array();
		if ( current_user_can( 'manage_options' ) && ! user_can( $user_id, 'manage_options' ) ) {
			if ( 'verified' !== EmailVerificationBadge::state( $user_id ) ) {
				$links[] = '<a href="' . esc_url( $this->action_url( self::ACTION_VERIFY, $user_id ) ) . '">' . esc_html__( 'Mark verified', 'havenlytics' ) . '</a>';
			}
			$links[] = '<a href="' . esc_url( $this->action_url( self::ACTION_RESEND, $user_id ) ) . '">' . esc_html__( 'Resend', 'havenlytics' ) . '</a>';
		}

		$actions = $links ? '<div class="row-actions">' . implode( ' | ', $links ) . '</div>' : '';

		return $badge . $actions;
	}

	/**
	 * @return void
	 */
	public function handle_verify(): void {
		$user_id = $this->authorize( self::ACTION_VERIFY );
		( new EmailVerificationFactor() )->mark_verified( $user_id );

		/**
		 * Fires when an administrator manually verifies an account.
		 *
		 * @since 3.3.0
		 *
		 * @param int $user_id  Verified user ID.
		 * @param int $admin_id Acting administrator ID.
		 */
		do_action( 'hvnly_email_verification_admin_verified', $user_id, get_current_user_id() );

		$this->redirect_back( 'verified' );
	}

	/**
	 * @return void
	 */
	public function handle_resend(): void {
		$user_id = $this->authorize( self::ACTION_RESEND );
		( new EmailVerificationNotifier() )->send_verification( $user_id, '', true );
		do_action( 'hvnly_email_verification_resent', $user_id );
		$this->redirect_back( 'resent' );
	}

	/**
	 * @return void
	 */
	public function handle_toggle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'havenlytics' ), 403 );
		}
		check_admin_referer( self::ACTION_TOGGLE );

		$disabled = ! (bool) get_option( EmailVerificationFactor::OPTION_DISABLED, false );
		update_option( EmailVerificationFactor::OPTION_DISABLED, $disabled ? '1' : '', false );

		do_action(
			'hvnly_identity_verification_logged',
			array(
				'event'     => $disabled ? 'enforcement_disabled' : 'enforcement_enabled',
				'user_id'   => 0,
				'success'   => true,
				'actor_id'  => get_current_user_id(),
				'timestamp' => gmdate( 'c' ),
			)
		);

		$this->redirect_back( $disabled ? 'enforcement_off' : 'enforcement_on' );
	}

	/**
	 * Shared authorization for per-user admin actions.
	 *
	 * @param string $action Action slug.
	 * @return int Target user ID.
	 */
	private function authorize( string $action ): int {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'havenlytics' ), 403 );
		}
		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( $action . '_' . $user_id );

		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			wp_die( esc_html__( 'User not found.', 'havenlytics' ), 404 );
		}

		return $user_id;
	}

	/**
	 * @param string $action  Action slug.
	 * @param int    $user_id User ID.
	 * @return string
	 */
	private function action_url( string $action, int $user_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . $action . '&user_id=' . $user_id ),
			$action . '_' . $user_id
		);
	}

	/**
	 * @param string $result Result slug.
	 * @return void
	 */
	private function redirect_back( string $result ): void {
		$back = wp_get_referer();
		$back = $back ? $back : admin_url( 'users.php' );
		wp_safe_redirect( add_query_arg( 'hvnly_verify_result', rawurlencode( $result ), $back ) );
		exit;
	}
}
