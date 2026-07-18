<?php
/**
 * Email verification notifier.
 *
 * Sends the branded "verify your email" request (on registration + resend) and the
 * "email verified" confirmation. Renders through the shared EmailRenderer / EmailBranding
 * / EmailHeaders stack, mirrors the RegistrationEmailNotifier conventions, and exposes the
 * verification-email hooks. Guarded against system/bulk contexts; logs on failure.
 *
 * @package HvnlyNab\Workspace\Identity
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\Identity;

use HvnlyNab\Email\EmailBranding;
use HvnlyNab\Email\EmailConstants;
use HvnlyNab\Email\EmailHeaders;
use HvnlyNab\Email\EmailRenderer;
use HvnlyNab\Workspace\WorkspaceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.3.0
 */
final class EmailVerificationNotifier {

	/**
	 * @var EmailRenderer
	 */
	private $renderer;

	/**
	 * @var EmailVerificationFactor
	 */
	private $factor;

	/**
	 * @param EmailRenderer|null           $renderer Renderer.
	 * @param EmailVerificationFactor|null $factor   Email factor.
	 */
	public function __construct( ?EmailRenderer $renderer = null, ?EmailVerificationFactor $factor = null ) {
		$this->renderer = $renderer ? $renderer : new EmailRenderer();
		$this->factor   = $factor ? $factor : new EmailVerificationFactor();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		// New self-registration → send the verification request.
		add_action( 'hvnly_workspace_agent_provisioned', array( $this, 'on_agent_provisioned' ), 20, 2 );
		// Successful verification → send the confirmation.
		add_action( 'hvnly_email_verification_verified', array( $this, 'on_verified' ), 20, 1 );
	}

	/**
	 * @param int $agent_id Agent post ID (unused).
	 * @param int $user_id  User ID.
	 * @return void
	 */
	public function on_agent_provisioned( $agent_id, $user_id ): void {
		unset( $agent_id );
		$user_id = absint( $user_id );

		// Never during demo / bulk import / migration / CLI.
		if ( function_exists( 'hvnly_is_system_notification_context' ) && hvnly_is_system_notification_context() ) {
			return;
		}
		if ( $user_id <= 0 || user_can( $user_id, 'manage_options' ) ) {
			return;
		}

		// Mint a fresh token (marks the new account explicitly unverified) and send.
		$token = $this->factor->start_verification( $user_id );
		if ( '' === $token ) {
			return;
		}

		$this->send_verification( $user_id, $token, false );
	}

	/**
	 * Send (or re-send) the verification email. Reusable by registration, the resend
	 * endpoint, and admin actions.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Raw token. When empty a fresh token is minted.
	 * @param bool   $is_resend Whether this is a resend.
	 * @return bool Whether wp_mail reported success.
	 */
	public function send_verification( int $user_id, string $token = '', bool $is_resend = false ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		if ( '' === $token ) {
			$token = $this->factor->reissue( $user_id );
			if ( '' === $token ) {
				return false; // already verified / cannot reissue — caller sends generic response.
			}
		}

		$context = $this->build_verify_context( $user, $token );
		$subject = (string) apply_filters(
			'hvnly_email_verification_subject',
			sprintf( /* translators: %s: site name. */ __( 'Verify your email for %s', 'havenlytics' ), $context['site_name'] ),
			EmailConstants::TYPE_WS_EMAIL_VERIFY,
			$context
		);

		/**
		 * Fires immediately before a verification email is sent.
		 *
		 * @since 3.3.0
		 *
		 * @param int                  $user_id User ID.
		 * @param array<string, mixed> $context Email context (contains the verify URL, not the raw token separately).
		 * @param bool                 $is_resend Whether this is a resend.
		 */
		do_action( 'hvnly_email_verification_email_before_send', $user_id, $context, $is_resend );

		$sent = $this->dispatch( $user->user_email, $subject, EmailConstants::TYPE_WS_EMAIL_VERIFY, $context );

		if ( $sent ) {
			/**
			 * Fires after a verification email was handed to wp_mail successfully.
			 *
			 * @since 3.3.0
			 *
			 * @param int  $user_id   User ID.
			 * @param bool $is_resend Whether this is a resend.
			 */
			do_action( 'hvnly_email_verification_email_sent', $user_id, $is_resend );
			do_action( 'hvnly_email_verification_sent', $user_id );
			if ( $is_resend ) {
				do_action( 'hvnly_email_verification_resent', $user_id );
			}
		}

		return $sent;
	}

	/**
	 * Send the "email verified" confirmation.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_verified( $user_id ): void {
		$user = get_userdata( (int) $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		$workspace_url = WorkspaceSettings::route_url( '' );
		$context       = array_merge(
			EmailBranding::context(),
			array(
				'email_type' => EmailConstants::TYPE_WS_EMAIL_VERIFIED,
				'user_name'  => $user->display_name,
				'title'      => __( 'Your email is verified', 'havenlytics' ),
				'intro'      => __( 'Thanks — your email address has been verified and your Agent Workspace is now fully unlocked.', 'havenlytics' ),
				'detail_rows'=> array(
					__( 'Email', 'havenlytics' )    => $user->user_email,
					__( 'Verified', 'havenlytics' ) => date_i18n( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
				),
				'cta_url'    => esc_url_raw( $workspace_url ),
				'cta_label'  => __( 'Open your workspace', 'havenlytics' ),
				'footnote'   => '',
				'site_name'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'site_url'   => home_url( '/' ),
			)
		);
		$context['preheader'] = (string) $context['intro'];

		$this->dispatch(
			$user->user_email,
			(string) apply_filters(
				'hvnly_email_verification_subject',
				__( 'Your email has been verified', 'havenlytics' ),
				EmailConstants::TYPE_WS_EMAIL_VERIFIED,
				$context
			),
			EmailConstants::TYPE_WS_EMAIL_VERIFIED,
			$context
		);
	}

	/**
	 * Build the "verify your email" email context (branded).
	 *
	 * @param \WP_User $user  User.
	 * @param string   $token Raw token.
	 * @return array<string, mixed>
	 */
	private function build_verify_context( \WP_User $user, string $token ): array {
		$verify_url = WorkspaceSettings::route_url(
			'verify-email',
			array(
				'token' => $token,
				'login' => $user->user_login,
			)
		);

		$ttl_hours = (int) round( $this->factor->ttl() / HOUR_IN_SECONDS );
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$context = array_merge(
			EmailBranding::context(),
			array(
				'email_type'  => EmailConstants::TYPE_WS_EMAIL_VERIFY,
				'user_name'   => $user->display_name,
				'title'       => __( 'Verify your email address', 'havenlytics' ),
				'intro'       => __( 'Your Havenlytics agent account has been created. Please confirm your email address to activate your Agent Workspace.', 'havenlytics' ),
				'detail_rows' => array(
					__( 'Email', 'havenlytics' )        => $user->user_email,
					__( 'Link expires in', 'havenlytics' ) => sprintf(
						/* translators: %d: hours. */
						_n( '%d hour', '%d hours', $ttl_hours, 'havenlytics' ),
						$ttl_hours
					),
				),
				'cta_url'     => esc_url_raw( $verify_url ),
				'cta_label'   => __( 'Verify my email', 'havenlytics' ),
				// Security notice — ignore if you did not register.
				'footnote'    => __( 'If you did not create a Havenlytics account, you can safely ignore this email — no account will be activated without verification.', 'havenlytics' ),
				'site_name'   => $site_name,
				'site_url'    => home_url( '/' ),
			)
		);
		$context['preheader'] = (string) $context['intro'];

		/**
		 * Filter the verification email context (content override).
		 *
		 * @since 3.3.0
		 *
		 * @param array<string, mixed> $context  Email context (branded; contains cta_url = verify link).
		 * @param \WP_User             $user     Recipient.
		 */
		return (array) apply_filters( 'hvnly_email_verification_content', $context, $user );
	}

	/**
	 * Render + send, retry-safe, logs on failure. Never throws.
	 *
	 * @param string               $to      Recipient.
	 * @param string               $subject Subject.
	 * @param string               $type    Email type.
	 * @param array<string, mixed> $context Context.
	 * @return bool
	 */
	private function dispatch( string $to, string $subject, string $type, array $context ): bool {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return false;
		}

		$subject = str_replace( array( "\r", "\n" ), '', $subject );
		$body    = $this->renderer->render( $type, $context );
		if ( '' === trim( $body ) ) {
			return false;
		}

		$headers = EmailHeaders::base();
		EmailHeaders::attach_plain_text_alt( $body );

		$sent = false;
		try {
			$sent = (bool) wp_mail( $to, $subject, $body, $headers );
		} catch ( \Throwable $e ) {
			$sent = false;
		}

		if ( ! $sent && function_exists( 'hvnly_debug_log' ) ) {
			hvnly_debug_log(
				sprintf( 'Email verification message failed type=%s to=%s', $type, $to ),
				'Havenlytics Identity'
			);
		}

		return $sent;
	}
}
