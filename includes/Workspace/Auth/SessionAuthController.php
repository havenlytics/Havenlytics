<?php
/**
 * Workspace session authentication (WordPress users — no custom auth store).
 *
 * Owns: login, register, forgot/reset password, logout, auth nonce refresh.
 * Does not own registration status SSOT or portal permission rules.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Workspace\Identity\EmailVerificationFactor;
use HvnlyNab\Workspace\Identity\EmailVerificationNotifier;
use HvnlyNab\Workspace\Identity\VerificationRateLimiter;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress session auth for the Workspace Authentication Experience.
 *
 * @since 3.2.0
 */
final class SessionAuthController {

	public const ACTION_LOGIN          = 'hvnly_ws_login';
	public const ACTION_REGISTER       = 'hvnly_ws_register';
	public const ACTION_LOST_PASSWORD  = 'hvnly_ws_lostpassword';
	public const ACTION_RESET_PASSWORD = 'hvnly_ws_resetpassword';
	public const ACTION_LOGOUT         = 'hvnly_ws_logout';
	public const ACTION_REFRESH_NONCES = 'hvnly_ws_auth_nonces';
	public const ACTION_VERIFY_EMAIL   = 'hvnly_ws_verify_email';
	public const ACTION_RESEND_VERIFY  = 'hvnly_ws_resend_verification';

	public const NONCE_LOGIN          = 'hvnly_ws_auth_login';
	public const NONCE_REGISTER       = 'hvnly_ws_auth_register';
	public const NONCE_LOST_PASSWORD  = 'hvnly_ws_auth_lostpassword';
	public const NONCE_RESET_PASSWORD = 'hvnly_ws_auth_resetpassword';
	public const NONCE_LOGOUT         = 'hvnly_ws_auth_logout';
	public const NONCE_VERIFY_EMAIL   = 'hvnly_ws_auth_verify_email';
	public const NONCE_RESEND_VERIFY  = 'hvnly_ws_auth_resend_verification';

	/**
	 * Register AJAX hooks + localize filter + reset-email URL.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION_LOGIN, array( $this, 'login' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_LOGIN, array( $this, 'login' ) );

		add_action( 'wp_ajax_' . self::ACTION_REGISTER, array( $this, 'register_user' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_REGISTER, array( $this, 'register_user' ) );

		add_action( 'wp_ajax_' . self::ACTION_LOST_PASSWORD, array( $this, 'lost_password' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_LOST_PASSWORD, array( $this, 'lost_password' ) );

		add_action( 'wp_ajax_' . self::ACTION_RESET_PASSWORD, array( $this, 'reset_password' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_RESET_PASSWORD, array( $this, 'reset_password' ) );

		add_action( 'wp_ajax_' . self::ACTION_LOGOUT, array( $this, 'logout' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_LOGOUT, array( $this, 'logout' ) );

		add_action( 'wp_ajax_' . self::ACTION_REFRESH_NONCES, array( $this, 'refresh_nonces' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_REFRESH_NONCES, array( $this, 'refresh_nonces' ) );

		// Identity verification (3.3.0) — public verify + resend.
		add_action( 'wp_ajax_' . self::ACTION_VERIFY_EMAIL, array( $this, 'verify_email' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_VERIFY_EMAIL, array( $this, 'verify_email' ) );
		add_action( 'wp_ajax_' . self::ACTION_RESEND_VERIFY, array( $this, 'resend_verification' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION_RESEND_VERIFY, array( $this, 'resend_verification' ) );

		add_filter( 'hvnly_workspace_localize_data', array( $this, 'filter_localize' ) );
		add_filter( 'retrieve_password_message', array( $this, 'filter_reset_message' ), 10, 4 );
	}

	/**
	 * Fresh per-action auth nonces for SPA boot / responses.
	 *
	 * @return array{login:string,register:string,lostPassword:string,resetPassword:string,logout:string}
	 */
	public function create_nonces(): array {
		return array(
			'login'         => wp_create_nonce( self::NONCE_LOGIN ),
			'register'      => wp_create_nonce( self::NONCE_REGISTER ),
			'lostPassword'  => wp_create_nonce( self::NONCE_LOST_PASSWORD ),
			'resetPassword' => wp_create_nonce( self::NONCE_RESET_PASSWORD ),
			'logout'        => wp_create_nonce( self::NONCE_LOGOUT ),
			'verifyEmail'   => wp_create_nonce( self::NONCE_VERIFY_EMAIL ),
			'resendVerification' => wp_create_nonce( self::NONCE_RESEND_VERIFY ),
		);
	}

	/**
	 * Add auth endpoints + copy to SPA boot config.
	 *
	 * @param array<string, mixed> $data Localized data.
	 * @return array<string, mixed>
	 */
	public function filter_localize( array $data ): array {
		$ajax    = admin_url( 'admin-ajax.php' );
		$mode    = WorkspaceSettings::get_registration_mode();
		$nonces  = $this->create_nonces();
		$user_id = get_current_user_id();

		$data['auth'] = array(
			'ajaxUrl'             => esc_url_raw( $ajax ),
			'nonces'              => $nonces,
			// Prefer auth.nonces.<action>; kept for older clients during deploy.
			'nonce'               => $nonces['login'],
			'registrationMode'    => $mode,
			'registrationEnabled' => WorkspaceSettings::is_registration_enabled(),
			'usersCanRegister'    => WorkspaceSettings::is_registration_enabled(),
			'supportEmail'        => sanitize_email( (string) get_option( 'admin_email', '' ) ),
			'logoutRedirect'      => esc_url_raw( WorkspaceSettings::get_logout_redirect_url() ),
			'actions'             => array(
				'login'         => self::ACTION_LOGIN,
				'register'      => self::ACTION_REGISTER,
				'lostPassword'  => self::ACTION_LOST_PASSWORD,
				'resetPassword' => self::ACTION_RESET_PASSWORD,
				'logout'        => self::ACTION_LOGOUT,
				'refreshNonces' => self::ACTION_REFRESH_NONCES,
				'verifyEmail'   => self::ACTION_VERIFY_EMAIL,
				'resendVerification' => self::ACTION_RESEND_VERIFY,
			),
		);

		if ( ! isset( $data['user'] ) || ! is_array( $data['user'] ) ) {
			$data['user'] = array();
		}
		$data['user']['isLoggedIn'] = is_user_logged_in();
		if ( $user_id > 0 ) {
			$data['user']['registrationStatus'] = WorkspaceRegistrationStatus::get_for_user( $user_id );
		} else {
			$data['user']['registrationStatus'] = null;
		}

		$i18n = isset( $data['i18n'] ) && is_array( $data['i18n'] ) ? $data['i18n'] : array();

		$data['i18n'] = array_merge(
			$i18n,
			array(
				'authLoginTitle'           => __( 'Sign in', 'havenlytics' ),
				'authLoginSubtitle'        => __( 'Access your Havenlytics Workspace.', 'havenlytics' ),
				'authRegisterTitle'        => __( 'Create account', 'havenlytics' ),
				'authRegisterSubtitle'     => __( 'Create your agent account to access the Workspace.', 'havenlytics' ),
				'authForgotTitle'          => __( 'Forgot password', 'havenlytics' ),
				'authForgotSubtitle'       => __( 'We will email you a link to reset your password.', 'havenlytics' ),
				'authResetTitle'           => __( 'Reset password', 'havenlytics' ),
				'authResetSubtitle'        => __( 'Choose a new password for your account.', 'havenlytics' ),
				'authUsername'             => __( 'Username or email', 'havenlytics' ),
				'authUsernameOnly'         => __( 'Username', 'havenlytics' ),
				'authEmail'                => __( 'Email', 'havenlytics' ),
				'authPassword'             => __( 'Password', 'havenlytics' ),
				'authPasswordConfirm'      => __( 'Confirm password', 'havenlytics' ),
				'authRemember'             => __( 'Remember me', 'havenlytics' ),
				'authSubmitLogin'          => __( 'Sign in', 'havenlytics' ),
				'authSubmitRegister'       => __( 'Create account', 'havenlytics' ),
				'authSubmitForgot'         => __( 'Send reset link', 'havenlytics' ),
				'authSubmitReset'          => __( 'Update password', 'havenlytics' ),
				'authSubmitLogout'         => __( 'Sign out', 'havenlytics' ),
				'authLinkRegister'         => __( 'Create an account', 'havenlytics' ),
				'authLinkLogin'            => __( 'Back to sign in', 'havenlytics' ),
				'authLinkForgot'           => __( 'Forgot password?', 'havenlytics' ),
				'authRegisterDisabled'     => __( 'Registration has been disabled by the site administrator. Please contact the site owner if you need a Workspace account.', 'havenlytics' ),
				'authRegistrationDisabledTitle' => __( 'Registration is currently disabled', 'havenlytics' ),
				'authRegistrationDisabledSubtitle' => __( 'New agent accounts cannot be created right now. Please contact the site administrator if you need access, or sign in if you already have an account.', 'havenlytics' ),
				'authWorkspaceUnavailableTitle' => __( 'Agent Workspace is currently unavailable', 'havenlytics' ),
				'authWorkspaceUnavailableSubtitle' => __( 'The site administrator has temporarily disabled the Agent Workspace. If you believe this is an error, please contact the site administrator.', 'havenlytics' ),
				'authContactAdmin'             => __( 'Contact Administrator', 'havenlytics' ),
				'authLinkHome'             => __( 'Return to Homepage', 'havenlytics' ),
				'authSupportContactLabel'  => __( 'Need help?', 'havenlytics' ),
				'authForgotSuccess'        => __( 'Check your email for the reset link.', 'havenlytics' ),
				'authResetSuccess'         => __( 'Password updated. You can sign in now.', 'havenlytics' ),
				'authRegisterSuccess'      => __( 'Account created. Redirecting…', 'havenlytics' ),
				'authRegisterPending'      => __( 'Account created and awaiting administrator approval. You will be able to sign in once approved.', 'havenlytics' ),
				'authRegisterPendingHint'  => __( 'New accounts require administrator approval before Workspace access.', 'havenlytics' ),
				'authInvalidNonce'         => __( 'Security check failed. Please refresh and try again.', 'havenlytics' ),
				'authGenericError'         => __( 'Something went wrong. Please try again.', 'havenlytics' ),
				'authPasswordMismatch'     => __( 'Passwords do not match.', 'havenlytics' ),
				'authPasswordTooShort'     => __( 'Use a password with at least 8 characters.', 'havenlytics' ),
				'authResetKey'             => __( 'Reset key', 'havenlytics' ),
				'authResetKeyHint'         => __( 'From your reset email link', 'havenlytics' ),
				'authExtensionsHint'       => __( 'Additional sign-in options', 'havenlytics' ),
				'authPendingTitle'         => __( 'Awaiting approval', 'havenlytics' ),
				'authPendingSubtitle'      => __( 'Your account was created successfully. An administrator must approve your Workspace access before you can sign in.', 'havenlytics' ),
				'authRejectedTitle'        => __( 'Account blocked', 'havenlytics' ),
				'authRejectedSubtitle'     => __( 'Your Workspace account has been blocked. Contact the site administrator if you believe this is an error.', 'havenlytics' ),
				'authSuspendedTitle'       => __( 'Account suspended', 'havenlytics' ),
				'authSuspendedSubtitle'    => __( 'Your Workspace account has been suspended. You can stay signed in, but Workspace is unavailable until an administrator restores access.', 'havenlytics' ),
				'authBlockedTitle'         => __( 'Account blocked', 'havenlytics' ),
				'authBlockedSubtitle'      => __( 'Your Workspace account has been blocked. Contact the site administrator if you believe this is an error.', 'havenlytics' ),
				'authArchivedTitle'        => __( 'Account archived', 'havenlytics' ),
				'authArchivedSubtitle'     => __( 'This Agent identity has been archived and cannot access Workspace.', 'havenlytics' ),
				'authSessionExpiredTitle'  => __( 'Session expired', 'havenlytics' ),
				'authSessionExpiredSubtitle' => __( 'Your session ended. Sign in again to continue.', 'havenlytics' ),
				'authForbiddenTitle'       => __( 'Access denied', 'havenlytics' ),
				'authForbiddenSubtitle'    => __( 'You do not have permission to access this Workspace page.', 'havenlytics' ),
				'authNotFoundTitle'        => __( 'Page not found', 'havenlytics' ),
				'authNotFoundSubtitle'     => __( 'That Workspace page does not exist.', 'havenlytics' ),
				'unauthorizedTitle'        => __( 'Sign in required', 'havenlytics' ),
				'unauthorizedSubtitle'     => __( 'Sign in to access your Havenlytics Workspace.', 'havenlytics' ),
			)
		);

		return $data;
	}

	/**
	 * Point password-reset emails at the Workspace reset screen.
	 *
	 * @param string   $message    Email body.
	 * @param string   $key        Reset key.
	 * @param string   $user_login User login.
	 * @param \WP_User $user_data  User object.
	 * @return string
	 */
	public function filter_reset_message( $message, $key, $user_login, $user_data = null ): string {
		unset( $user_data );

		if ( ! WorkspaceSettings::is_enabled() ) {
			return $message;
		}

		$url = WorkspaceSettings::route_url(
			'reset-password',
			array(
				'login' => $user_login,
				'key'   => $key,
			)
		);
		if ( $url === '' ) {
			return $message;
		}

		$lines = array(
			__( 'Someone requested a password reset for the following account:', 'havenlytics' ),
			'',
			site_url(),
			'',
			sprintf(
				/* translators: %s: user login */
				__( 'Username: %s', 'havenlytics' ),
				$user_login
			),
			'',
			__( 'If this was a mistake, ignore this email and nothing will happen.', 'havenlytics' ),
			'',
			__( 'To reset your password, visit the following address:', 'havenlytics' ),
			'',
			$url,
			'',
		);

		return implode( "\r\n", $lines );
	}

	/**
	 * POST login via wp_signon().
	 *
	 * @return void
	 */
	public function login(): void {
		$this->guard_enabled();
		$this->verify_nonce( self::NONCE_LOGIN );

		$login    = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';
		$remember = ! empty( $_POST['rememberme'] );

		if ( $login === '' ) {
			wp_send_json_error(
				array(
					'code'    => 'empty_username',
					'message' => __( 'Username or email is required.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		if ( $password === '' ) {
			wp_send_json_error(
				array(
					'code'    => 'empty_password',
					'message' => __( 'Password is required.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => $remember,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			$mapped = $this->map_wp_error( $user );
			wp_send_json_error(
				array(
					'code'    => $mapped['code'],
					'message' => $mapped['message'],
					'nonces'  => $this->create_nonces(),
				),
				401
			);
		}

		$user_id = (int) $user->ID;
		$status  = WorkspaceRegistrationStatus::get_for_user( $user_id );

		/*
		 * Administrators belong in wp-admin, not the Agent Workspace SPA.
		 * Capability-based (manage_options) on purpose — never a bare role-name
		 * check — so admin-equivalent custom roles are covered too. The wp_signon()
		 * session above is already established, so hand them straight to the native
		 * dashboard. `canAccessWorkspace => false` stops the SPA login handler from
		 * consuming a stored "intended" Workspace route and overriding this redirect.
		 */
		if ( user_can( $user_id, 'manage_options' ) ) {
			do_action( 'hvnly_workspace_agent_activity', $user_id, 'login' );
			AgentIdentityService::get_instance()->clear_cache( $user_id );

			/**
			 * Filter the post-login destination for administrators / manage_options
			 * users signing in through the Workspace login screen.
			 *
			 * @since 3.2.4
			 *
			 * @param string $url     Default WordPress admin dashboard URL.
			 * @param int    $user_id Authenticated user ID.
			 */
			$admin_redirect = (string) apply_filters( 'hvnly_workspace_admin_login_redirect', admin_url(), $user_id );

			wp_send_json_success(
				array(
					'userId'             => $user_id,
					'registrationStatus' => $status,
					'canAccessWorkspace' => false,
					'isAdminRedirect'    => true,
					'redirect'           => esc_url_raw( $admin_redirect ),
					'message'            => __( 'Redirecting to the WordPress dashboard…', 'havenlytics' ),
					'nonces'             => $this->create_nonces(),
				)
			);
		}

		// Blocked / Pending / Archived — no session for Workspace auth.
		if ( ! WorkspaceRegistrationStatus::allows_login( $status ) ) {
			wp_logout();
			AgentIdentityService::get_instance()->clear_cache( $user_id );
			$view = $this->auth_view_for_status( $status );
			wp_send_json_error(
				array(
					'code'               => 'registration_' . $status,
					'registrationStatus' => $status,
					'message'            => WorkspaceRegistrationStatus::login_message( $status ),
					'redirect'           => $this->workspace_url( '#/' . $view ),
					'nonces'             => $this->create_nonces(),
				),
				403
			);
		}

		// Suspended — session kept; Workspace SPA shows reason (no REST access).
		if ( ! WorkspaceRegistrationStatus::allows_workspace_access( $status ) ) {
			do_action( 'hvnly_workspace_agent_activity', $user_id, 'login' );
			AgentIdentityService::get_instance()->clear_cache( $user_id );
			$view = $this->auth_view_for_status( $status );
			wp_send_json_success(
				array(
					'userId'              => $user_id,
					'registrationStatus'  => $status,
					'canAccessWorkspace'  => false,
					'redirect'            => $this->workspace_url( '#/' . $view ),
					'message'             => WorkspaceRegistrationStatus::login_message( $status ),
					'nonces'              => $this->create_nonces(),
				)
			);
		}

		$authorization = new PortalAuthorization();
		if ( ! $authorization->can_access_workspace( $user_id ) ) {
			wp_logout();
			AgentIdentityService::get_instance()->clear_cache( $user_id );
			wp_send_json_error(
				array(
					'code'               => 'workspace_forbidden',
					'registrationStatus' => $status,
					'message'            => __( 'You do not have permission to access the Workspace.', 'havenlytics' ),
					'redirect'           => $this->workspace_url( '#/forbidden' ),
					'nonces'             => $this->create_nonces(),
				),
				403
			);
		}

		do_action( 'hvnly_workspace_agent_activity', $user_id, 'workspace' );
		AgentIdentityService::get_instance()->clear_cache( $user_id );

		wp_send_json_success(
			array(
				'userId'             => $user_id,
				'registrationStatus' => $status,
				'canAccessWorkspace' => true,
				'redirect'           => $this->workspace_url( '#/dashboard' ),
				'nonces'             => $this->create_nonces(),
			)
		);
	}

	/**
	 * Register a Workspace user + Agent profile (Workspace registration policy).
	 *
	 * Independent of WordPress Membership “Anyone can register”.
	 *
	 * @return void
	 */
	public function register_user(): void {
		$this->guard_enabled();
		$this->verify_nonce( self::NONCE_REGISTER );

		$mode = WorkspaceSettings::get_registration_mode();
		if ( 'disabled' === $mode ) {
			wp_send_json_error(
				array(
					'message' => __( 'Registration has been disabled by the site administrator. Please contact the site owner if you need a Workspace account.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				403
			);
		}

		$user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$password   = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		$pass2      = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

		if ( $user_login === '' || $user_email === '' || $password === '' ) {
			wp_send_json_error(
				array(
					'message' => __( 'Username, email, and password are required.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		if ( $password !== $pass2 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Passwords do not match.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Use a password with at least 8 characters.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		if ( ! is_email( $user_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Enter a valid email address.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		if ( username_exists( $user_login ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'That username is already registered.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		if ( email_exists( $user_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'That email address is already registered.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		( new CapabilityRegistrar() )->ensure_agent_role();

		$user_id = wp_insert_user(
			array(
				'user_login'   => $user_login,
				'user_email'   => $user_email,
				'user_pass'    => $password,
				'role'         => WorkspaceSettings::get_default_registration_role(),
				'display_name' => $user_login,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$mapped = $this->map_wp_error( $user_id );
			wp_send_json_error(
				array(
					'code'    => $mapped['code'],
					'message' => $mapped['message'],
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		$user_id = (int) $user_id;
		$pending = ( 'approval' === $mode );

		$provisioner = new AgentProvisioner();
		$agent_id    = $provisioner->ensure_agent_for_user(
			$user_id,
			array(
				'display_name' => $user_login,
				'user_email'   => $user_email,
				// Profile visibility only — never used as Workspace approval.
				'status'       => 'publish',
			)
		);

		if ( is_wp_error( $agent_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			$mapped = $this->map_wp_error( $agent_id );
			wp_send_json_error(
				array(
					'code'    => $mapped['code'],
					'message' => $mapped['message'],
					'nonces'  => $this->create_nonces(),
				),
				500
			);
		}

		$agent_id = (int) $agent_id;

		if ( $pending ) {
			WorkspaceRegistrationStatus::set_for_user( $user_id, WorkspaceRegistrationStatus::PENDING, $agent_id );
			wp_send_json_success(
				array(
					'userId'             => $user_id,
					'agentId'            => $agent_id,
					'status'             => WorkspaceRegistrationStatus::PENDING,
					'registrationStatus' => WorkspaceRegistrationStatus::PENDING,
					'redirect'           => $this->workspace_url( '#/pending' ),
					'message'            => __( 'Account created and awaiting administrator approval. You will be able to sign in once approved.', 'havenlytics' ),
					'nonces'             => $this->create_nonces(),
				)
			);
		}

		WorkspaceRegistrationStatus::set_for_user( $user_id, WorkspaceRegistrationStatus::ACTIVE, $agent_id );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		do_action( 'hvnly_workspace_agent_activity', $user_id, 'workspace' );

		AgentIdentityService::get_instance()->clear_cache( $user_id );

		wp_send_json_success(
			array(
				'userId'             => $user_id,
				'agentId'            => $agent_id,
				'status'             => WorkspaceRegistrationStatus::ACTIVE,
				'registrationStatus' => WorkspaceRegistrationStatus::ACTIVE,
				'redirect'           => $this->workspace_url( '#/dashboard' ),
				'message'            => __( 'Account created. Redirecting…', 'havenlytics' ),
				'nonces'             => $this->create_nonces(),
			)
		);
	}

	/**
	 * Trigger WordPress lost-password email.
	 *
	 * @return void
	 */
	public function lost_password(): void {
		$this->guard_enabled();
		$this->verify_nonce( self::NONCE_LOST_PASSWORD );

		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';

		if ( $user_login === '' ) {
			wp_send_json_error(
				array(
					'message' => __( 'Enter your username or email.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		$result = retrieve_password( $user_login );

		if ( true !== $result && is_wp_error( $result ) ) {
			$mapped = $this->map_wp_error( $result );
			wp_send_json_error(
				array(
					'code'    => $mapped['code'],
					'message' => $mapped['message'],
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Check your email for the reset link.', 'havenlytics' ),
				'nonces'  => $this->create_nonces(),
			)
		);
	}

	/**
	 * Complete password reset with key from email.
	 *
	 * @return void
	 */
	public function reset_password(): void {
		$this->guard_enabled();
		$this->verify_nonce( self::NONCE_RESET_PASSWORD );

		$login            = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
		$key              = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		$password         = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
		$password_confirm = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

		if ( $login === '' || $key === '' || $password === '' ) {
			wp_send_json_error(
				array(
					'message' => __( 'Reset key, username, and password are required.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		if ( $password !== $password_confirm ) {
			wp_send_json_error(
				array(
					'message' => __( 'Passwords do not match.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Use a password with at least 8 characters.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		$user = check_password_reset_key( $key, $login );

		if ( is_wp_error( $user ) ) {
			$mapped = $this->map_wp_error( $user );
			wp_send_json_error(
				array(
					'code'    => $mapped['code'],
					'message' => $mapped['message'],
					'nonces'  => $this->create_nonces(),
				),
				400
			);
		}

		reset_password( $user, $password );

		wp_send_json_success(
			array(
				'message'  => __( 'Password updated. You can sign in now.', 'havenlytics' ),
				'redirect' => $this->workspace_url( '#/login' ),
				'nonces'   => $this->create_nonces(),
			)
		);
	}

	/**
	 * Destroy Workspace session and return fresh guest nonces.
	 *
	 * Allowed even when Workspace is disabled so agents stuck on the
	 * unavailable page can still sign out.
	 *
	 * @return void
	 */
	public function logout(): void {
		$this->verify_nonce( self::NONCE_LOGOUT );

		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			AgentIdentityService::get_instance()->clear_cache( $user_id );
		}

		wp_logout();

		wp_send_json_success(
			array(
				'redirect' => WorkspaceSettings::get_logout_redirect_url(),
				'nonces'   => $this->create_nonces(),
			)
		);
	}

	/**
	 * Issue fresh auth nonces (no CSRF required — public read of new tokens only).
	 *
	 * Allowed when Workspace is disabled so the unavailable page can refresh
	 * logout nonces for signed-in visitors.
	 *
	 * @return void
	 */
	public function refresh_nonces(): void {
		wp_send_json_success(
			array(
				'nonces' => $this->create_nonces(),
			)
		);
	}

	/**
	 * PUBLIC: verify an email address from a token (SPA posts the token off the link).
	 *
	 * Security posture:
	 * - Nonce (CSRF) required; Workspace must be enabled.
	 * - Per-IP and per-selector rate limits (brute-force / fuzzing containment).
	 * - Strict token shape validation before any lookup (malformed → generic reject).
	 * - EVERY failure mode (malformed / invalid / expired / already verified / replay /
	 *   rate-limited) returns ONE identical generic message. No enumeration, no state leak.
	 * - Real reasons are recorded only in the audit log (never the token).
	 *
	 * @return void
	 */
	public function verify_email(): void {
		$this->guard_enabled();
		$this->verify_nonce( self::NONCE_VERIFY_EMAIL );

		$limiter = new VerificationRateLimiter();

		// Per-IP ceiling across all verify attempts.
		if ( $limiter->exceeded( 'verify_ip', $limiter->ip(), 20, 10 * MINUTE_IN_SECONDS ) ) {
			$this->generic_verify_fail();
		}

		$token = $this->normalize_token( isset( $_POST['token'] ) ? wp_unslash( $_POST['token'] ) : '' );
		if ( '' === $token ) {
			$this->generic_verify_fail(); // missing / duplicated / malformed / truncated / encoded
		}

		// Per-selector ceiling — caps verifier brute-force for a given token.
		$selector = explode( '.', $token )[0];
		if ( $limiter->exceeded( 'verify_token', $selector, 10, HOUR_IN_SECONDS ) ) {
			$this->generic_verify_fail();
		}

		$result = ( new EmailVerificationFactor() )->verify( $token );
		if ( empty( $result['ok'] ) ) {
			$this->generic_verify_fail();
		}

		// The token is the capability for its OWN account, independent of any session —
		// so this is safe whether the visitor is logged out, logged in as that user, as
		// another user, or as an administrator. The SPA re-fetches /me to reflect it.
		wp_send_json_success(
			array(
				'verified' => true,
				'loggedIn' => is_user_logged_in(),
				'message'  => __( 'Your email address has been verified. You can now access the Agent Workspace.', 'havenlytics' ),
				'nonces'   => $this->create_nonces(),
			)
		);
	}

	/**
	 * PUBLIC: resend the verification email for the CURRENT session user.
	 *
	 * Session-bound (no email parameter) so enumeration is impossible. The response is
	 * always the same generic success regardless of whether the visitor is logged in,
	 * whether an account exists, or whether it is already verified. Rate limited per IP,
	 * per account, per day, with a cooldown.
	 *
	 * @return void
	 */
	public function resend_verification(): void {
		$this->guard_enabled();
		$this->verify_nonce( self::NONCE_RESEND_VERIFY );

		$limiter = new VerificationRateLimiter();
		$ip      = $limiter->ip();
		$user_id = get_current_user_id();

		// Per-IP abuse ceiling (applies even to logged-out spam).
		if ( ! $limiter->exceeded( 'resend_ip', $ip, 10, HOUR_IN_SECONDS ) && $user_id > 0 ) {
			$cooling = $limiter->in_cooldown( 'resend_cooldown', (string) $user_id, MINUTE_IN_SECONDS );
			$hourly  = $limiter->exceeded( 'resend_acct', (string) $user_id, 5, HOUR_IN_SECONDS );
			$daily   = $limiter->exceeded( 'resend_daily', (string) $user_id, 10, DAY_IN_SECONDS );

			if ( ! $cooling && ! $hourly && ! $daily ) {
				// send_verification() reissues; it is a no-op (returns false) if already verified.
				( new EmailVerificationNotifier() )->send_verification( $user_id, '', true );
			}
		}

		wp_send_json_success(
			array(
				'message' => __( 'If your email still needs verification, a new verification link is on its way. Please check your inbox.', 'havenlytics' ),
				'nonces'  => $this->create_nonces(),
			)
		);
	}

	/**
	 * Single generic verification-failure response (no state disclosure).
	 *
	 * @return void
	 */
	private function generic_verify_fail(): void {
		wp_send_json_error(
			array(
				'code'    => 'verification_failed',
				'message' => __( 'This verification link is invalid or has expired. Please sign in and request a new verification email.', 'havenlytics' ),
				'nonces'  => $this->create_nonces(),
			),
			400
		);
	}

	/**
	 * Normalize + strictly validate a token's shape BEFORE any lookup.
	 * Accepts only "selector.verifier" (16 hex + 64 hex). Everything else → ''.
	 *
	 * @param mixed $raw Raw token (may be array on duplicated params).
	 * @return string Valid token, or '' when malformed.
	 */
	private function normalize_token( $raw ): string {
		if ( is_array( $raw ) ) {
			return ''; // duplicated ?token=&token= → reject.
		}
		$token = trim( sanitize_text_field( (string) $raw ) );
		// Tolerate a URL-encoded dot; reject anything with other stray characters.
		$token = str_ireplace( '%2e', '.', $token );

		return preg_match( '/^[a-f0-9]{16}\.[a-f0-9]{64}$/', $token ) ? $token : '';
	}

	/**
	 * @return void
	 */
	private function guard_enabled(): void {
		if ( ! WorkspaceSettings::is_enabled() ) {
			wp_send_json_error(
				array( 'message' => __( 'Workspace is disabled.', 'havenlytics' ) ),
				403
			);
		}
	}

	/**
	 * Verify action-specific auth nonce. On failure, return fresh nonces for retry.
	 *
	 * @param string $nonce_action Nonce action string.
	 * @return void
	 */
	private function verify_nonce( string $nonce_action ): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! $nonce || ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_nonce',
					'message' => __( 'Security check failed. Please refresh and try again.', 'havenlytics' ),
					'nonces'  => $this->create_nonces(),
				),
				403
			);
		}
	}

	/**
	 * Map WordPress WP_Error codes to plain-text Workspace auth messages.
	 *
	 * Never forwards get_error_message() HTML (strong tags, wp-login.php links).
	 *
	 * @param \WP_Error $error WordPress error.
	 * @return array{code:string,message:string}
	 */
	private function map_wp_error( \WP_Error $error ): array {
		$code = (string) $error->get_error_code();

		switch ( $code ) {
			case 'incorrect_password':
			case 'invalid_username':
			case 'invalidcombo':
				return array(
					'code'    => 'invalid_credentials',
					'message' => __( 'Incorrect email or password.', 'havenlytics' ),
				);
			case 'invalid_email':
				return array(
					'code'    => 'invalid_email',
					'message' => __( 'Please enter a valid email address.', 'havenlytics' ),
				);
			case 'empty_username':
				return array(
					'code'    => 'empty_username',
					'message' => __( 'Username or email is required.', 'havenlytics' ),
				);
			case 'empty_password':
				return array(
					'code'    => 'empty_password',
					'message' => __( 'Password is required.', 'havenlytics' ),
				);
			case 'existing_user_login':
				return array(
					'code'    => 'existing_user_login',
					'message' => __( 'That username is already registered.', 'havenlytics' ),
				);
			case 'existing_user_email':
				return array(
					'code'    => 'existing_user_email',
					'message' => __( 'That email address is already registered.', 'havenlytics' ),
				);
			case 'invalid_key':
			case 'expired_key':
				return array(
					'code'    => $code,
					'message' => __( 'This password reset link is invalid or has expired. Request a new one.', 'havenlytics' ),
				);
			default:
				return array(
					'code'    => $code !== '' ? $code : 'auth_error',
					'message' => __( 'Unable to complete that request. Please try again.', 'havenlytics' ),
				);
		}
	}

	/**
	 * @param string $status Registration status.
	 * @return string Auth hash view id.
	 */
	private function auth_view_for_status( string $status ): string {
		switch ( WorkspaceRegistrationStatus::normalize( $status ) ) {
			case WorkspaceRegistrationStatus::PENDING:
				return 'pending';
			case WorkspaceRegistrationStatus::BLOCKED:
				return 'blocked';
			case WorkspaceRegistrationStatus::SUSPENDED:
				return 'suspended';
			case WorkspaceRegistrationStatus::ARCHIVED:
				return 'archived';
			default:
				return 'forbidden';
		}
	}

	/**
	 * @param string $hash Optional hash including leading #.
	 * @return string
	 */
	private function workspace_url( string $hash = '' ): string {
		return WorkspaceSettings::route_url( $hash );
	}
}
