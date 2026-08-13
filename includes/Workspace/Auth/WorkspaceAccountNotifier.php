<?php
/**
 * Password reset, password changed and workspace invitation emails.
 *
 * Sprint 24C. Fills three gaps the audit found:
 *
 *  - PASSWORD RESET was WordPress core's plain-text email. We do NOT replace the
 *    reset flow (key generation, expiry and validation stay entirely in core) —
 *    we only brand the message, via core's own `retrieve_password_notification_email`
 *    filter. That filter is the supported way to restyle the reset mail without
 *    touching a single line of the security-critical path.
 *
 *  - PASSWORD CHANGED sent nothing. A password change with no confirmation email
 *    is how account takeovers go unnoticed, so this one matters beyond polish.
 *
 *  - INVITATION did not exist: an admin-created account triggered core's password
 *    reset mail, so a brand-new agent received an email that said someone had
 *    requested a password reset for an account they had never heard of. We send a
 *    real invitation instead, and suppress the core reset on that path so nobody
 *    gets two emails.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.1
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Email\EmailBranding;
use HvnlyNab\Email\EmailConstants;
use HvnlyNab\Email\EmailHeaders;
use HvnlyNab\Email\EmailRenderer;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.1
 */
final class WorkspaceAccountNotifier {

	/**
	 * @var EmailRenderer
	 */
	private $renderer;

	/**
	 * Password-setup decision per provisioned user, recorded by the filter and
	 * consumed by the action.
	 *
	 * Sprint 24C.1. Provisioning asks TWO hooks about ONE decision:
	 *
	 *   1. filter hvnly_send_workspace_account_reset_email — receives the admin's
	 *      $send_reset choice, and decides whether CORE sends its reset email.
	 *   2. action hvnly_workspace_user_provisioned_for_agent — fires
	 *      UNCONDITIONALLY afterwards (AgentProvisioner.php:750), and is where our
	 *      invitation hangs.
	 *
	 * The action cannot see $send_reset, so without shared state it cannot know
	 * whether the admin wanted any email at all. Recording the resolved decision
	 * here is what keeps exactly one password-setup path alive.
	 *
	 * @var array<int, string> user_id => 'invite' | 'core' | 'none'
	 */
	private $provision_decision = array();

	/**
	 * Agents already emailed about a profile change in this request. Guards against
	 * a duplicate when more than one save hook fires for the same profile in one
	 * request. Sprint 27D.
	 *
	 * @var array<int, bool>
	 */
	private $profile_emailed = array();

	/**
	 * @param EmailRenderer|null $renderer Renderer.
	 */
	public function __construct( ?EmailRenderer $renderer = null ) {
		$this->renderer = $renderer ? $renderer : new EmailRenderer();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		// Brand core's reset email. Priority 20 so we run after SessionAuthController's
		// retrieve_password_message filter has built the Workspace URL.
		add_filter( 'retrieve_password_notification_email', array( $this, 'filter_reset_email' ), 20, 4 );

		// Confirm a completed password reset.
		add_action( 'after_password_reset', array( $this, 'on_password_reset' ), 20, 2 );

		// Provisioning: the filter decides, the action obeys. Note the filter takes
		// all three args — it needs $send_reset (the admin's choice) and $user_id.
		add_filter( 'hvnly_send_workspace_account_reset_email', array( $this, 'resolve_provision_email' ), 20, 3 );
		add_action( 'hvnly_workspace_user_provisioned_for_agent', array( $this, 'on_user_provisioned' ), 20, 2 );

		// Sprint 27D: confirm a profile change to the account owner. Fires on genuine
		// edits only (admin metabox save + Workspace self-service save), never on
		// registration/provisioning — so new agents are not emailed at signup.
		add_action( 'hvnly_agent_profile_saved', array( $this, 'on_profile_saved' ), 20, 1 );
	}

	/* ---------------------------------------------------------------------- */
	/* Password reset — brand core's email, never replace core's flow.          */
	/* ---------------------------------------------------------------------- */

	/**
	 * Replace the body of core's password-reset email with the Havenlytics template.
	 *
	 * The reset key, its expiry and its validation are untouched — this only
	 * changes what the message looks like.
	 *
	 * @param array<string, mixed> $defaults   wp_mail() arguments from core.
	 * @param string               $key        Reset key.
	 * @param string               $user_login User login.
	 * @param \WP_User|null        $user_data  User object.
	 * @return array<string, mixed>
	 */
	public function filter_reset_email( $defaults, $key, $user_login, $user_data = null ) {
		if ( ! is_array( $defaults ) ) {
			return $defaults;
		}

		if ( ! WorkspaceSettings::is_enabled() || ! $this->is_enabled( 'hvnly_workspace_email_password_reset' ) ) {
			return $defaults;
		}

		if ( ! $user_data instanceof \WP_User ) {
			$user_data = get_user_by( 'login', (string) $user_login );
		}
		if ( ! $user_data instanceof \WP_User ) {
			return $defaults;
		}

		$reset_url = $this->reset_url( (string) $user_login, (string) $key );
		if ( '' === $reset_url ) {
			return $defaults; // No Workspace page — leave core's email alone.
		}

		$context              = $this->base_context( $user_data );
		$context              = array_merge(
			$context,
			array(
				'email_type' => EmailConstants::TYPE_PASSWORD_RESET,
				'title'      => __( 'Reset your password', 'havenlytics' ),
				'intro'      => __( 'Someone asked to reset the password for your account. Choose a new one using the button below — the link is single-use and expires in 24 hours.', 'havenlytics' ),
				'cta_url'    => $reset_url,
				'cta_label'  => __( 'Choose a new password', 'havenlytics' ),
				'footnote'   => __( 'If you did not ask for this, you can safely ignore this email — your password will not change.', 'havenlytics' ),
			)
		);
		$context['preheader'] = (string) $context['intro'];

		$body = $this->renderer->render( EmailConstants::TYPE_PASSWORD_RESET, $context );
		if ( '' === trim( $body ) ) {
			return $defaults; // Render failed — fall back to core's plain text.
		}

		$defaults['subject'] = sprintf(
			/* translators: %s: site name. */
			__( 'Reset your password — %s', 'havenlytics' ),
			(string) $context['site_name']
		);
		$defaults['message'] = $body;
		$defaults['headers'] = EmailHeaders::base();

		return $defaults;
	}

	/* ---------------------------------------------------------------------- */
	/* Password changed                                                         */
	/* ---------------------------------------------------------------------- */

	/**
	 * Confirm a completed password reset to the account owner.
	 *
	 * @param \WP_User $user     User whose password changed.
	 * @param string   $new_pass New password (unused — never emailed).
	 * @return void
	 */
	public function on_password_reset( $user, $new_pass = '' ): void {
		unset( $new_pass );

		if ( ! $user instanceof \WP_User || ! is_email( $user->user_email ) ) {
			return;
		}

		if ( ! $this->is_enabled( 'hvnly_workspace_email_password_reset' ) ) {
			return;
		}

		$context              = $this->base_context( $user );
		$context              = array_merge(
			$context,
			array(
				'email_type'  => EmailConstants::TYPE_PASSWORD_CHANGED,
				'title'       => __( 'Your password was changed', 'havenlytics' ),
				'intro'       => __( 'Your password has just been changed. If that was you, there is nothing else to do.', 'havenlytics' ),
				'detail_rows' => array(
					__( 'Account', 'havenlytics' ) => (string) $user->user_email,
					__( 'When', 'havenlytics' )    => date_i18n( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
				),
				'cta_url'     => (string) $context['workspace_url'],
				'cta_label'   => __( 'Sign in to your workspace', 'havenlytics' ),
				'footnote'    => __( 'If this was not you, contact the site administrator immediately — someone else may have access to your account.', 'havenlytics' ),
			)
		);
		$context['preheader'] = (string) $context['intro'];

		$this->dispatch(
			$user->user_email,
			sprintf( /* translators: %s: site name. */ __( 'Your password was changed — %s', 'havenlytics' ), (string) $context['site_name'] ),
			EmailConstants::TYPE_PASSWORD_CHANGED,
			$context
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Profile updated                                                          */
	/* ---------------------------------------------------------------------- */

	/**
	 * Confirm an agent-profile change to the account owner.
	 *
	 * Sprint 27D. Reuses the shared notice template + EmailRenderer + EmailHeaders.
	 * Recipient is resolved from the Agent CPT the same way the in-app listener does
	 * (linked user meta, then post author). No new template, renderer or header path.
	 *
	 * @param int $agent_id Agent CPT ID (payload of hvnly_agent_profile_saved).
	 * @return void
	 */
	public function on_profile_saved( $agent_id ): void {
		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 ) {
			return;
		}

		// Never email during a bulk import or WP-CLI/cron pass.
		if ( ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		if ( ! $this->is_enabled( 'hvnly_workspace_email_profile_updated' ) ) {
			return;
		}

		// Resolve the account behind the profile — mirrors NotificationEventListener.
		$user_id = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $user_id <= 0 ) {
			$user_id = (int) get_post_field( 'post_author', $agent_id );
		}
		if ( $user_id <= 0 ) {
			return;
		}

		// One email per agent per request.
		if ( isset( $this->profile_emailed[ $agent_id ] ) ) {
			return;
		}
		$this->profile_emailed[ $agent_id ] = true;

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || ! is_email( $user->user_email ) ) {
			return;
		}

		$context              = $this->base_context( $user );
		$context              = array_merge(
			$context,
			array(
				'email_type'  => EmailConstants::TYPE_AGENT_PROFILE_UPDATE,
				'title'       => __( 'Your profile was updated', 'havenlytics' ),
				'intro'       => __( 'Your agent profile was just updated. If that was you, there is nothing else to do.', 'havenlytics' ),
				'detail_rows' => array(
					__( 'Account', 'havenlytics' ) => (string) $user->user_email,
					__( 'When', 'havenlytics' )    => date_i18n( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
				),
				'cta_url'     => (string) $context['workspace_url'],
				'cta_label'   => __( 'Open your workspace', 'havenlytics' ),
				'footnote'    => __( 'If this was not you, contact the site administrator immediately — someone else may have access to your account.', 'havenlytics' ),
			)
		);
		$context['preheader'] = (string) $context['intro'];

		$this->dispatch(
			$user->user_email,
			sprintf( /* translators: %s: site name. */ __( 'Your profile was updated — %s', 'havenlytics' ), (string) $context['site_name'] ),
			EmailConstants::TYPE_AGENT_PROFILE_UPDATE,
			$context
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Invitation                                                              */
	/* ---------------------------------------------------------------------- */

	/**
	 * Decide — once — how a newly provisioned agent will be able to set a password.
	 *
	 * Exactly one path must always exist, or the agent is locked out of an account
	 * they never asked for and cannot recover.
	 *
	 *   admin said "no email"       → 'none'   → core suppressed, no invitation.
	 *   invitation enabled          → 'invite' → core suppressed, we invite.
	 *   invitation disabled         → 'core'   → CORE SENDS ITS RESET. Not suppressed.
	 *
	 * The third case is the release blocker this method exists to close: previously
	 * core was suppressed unconditionally while the invitation was gated behind the
	 * hvnly_workspace_email_registration setting, so turning that setting off left
	 * the agent with no email from anyone and no way to set a password.
	 *
	 * @param bool $send_reset Whether core should send its reset email (admin's choice).
	 * @param int  $user_id    Provisioned user.
	 * @param int  $agent_id   Linked agent post.
	 * @return bool Whether core should send its reset email.
	 */
	public function resolve_provision_email( $send_reset, $user_id = 0, $agent_id = 0 ): bool {
		unset( $agent_id );

		$user_id = absint( $user_id );

		// The administrator explicitly asked for no email. Respect that: no core
		// reset, and no invitation either.
		if ( ! $send_reset ) {
			if ( $user_id > 0 ) {
				$this->provision_decision[ $user_id ] = 'none';
			}

			return false;
		}

		// A password-setup email is wanted. Send ours if invitations are enabled,
		// otherwise stand aside and let WordPress send its standard reset email.
		$invite = $this->is_enabled( 'hvnly_workspace_email_registration' );

		if ( $user_id > 0 ) {
			$this->provision_decision[ $user_id ] = $invite ? 'invite' : 'core';
		}

		return ! $invite;
	}

	/**
	 * Invite an admin-provisioned agent to set their password and sign in.
	 *
	 * Only acts when resolve_provision_email() decided on the invitation path. The
	 * action this hangs on fires unconditionally, so without that check an admin who
	 * asked for no email would get one anyway.
	 *
	 * @param int $user_id  Provisioned user.
	 * @param int $agent_id Linked agent post.
	 * @return void
	 */
	public function on_user_provisioned( $user_id, $agent_id = 0 ): void {
		unset( $agent_id );

		$user_id = absint( $user_id );

		$decision = isset( $this->provision_decision[ $user_id ] )
			? $this->provision_decision[ $user_id ]
			: '';

		// Clear immediately — one decision per provisioning run.
		unset( $this->provision_decision[ $user_id ] );

		/*
		 * No recorded decision means our filter never ran for this user, so we did
		 * not suppress anything and core may already have sent its reset. Staying
		 * silent is the safe default: a missing invitation is recoverable via
		 * "Forgot password"; a duplicate one is not recallable.
		 */
		if ( 'invite' !== $decision ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || ! is_email( $user->user_email ) ) {
			return;
		}

		// Same key mechanism core uses for a reset — we are not inventing a token.
		$key = get_password_reset_key( $user );

		$setup_url = is_wp_error( $key )
			? ''
			: $this->reset_url( $user->user_login, (string) $key );

		/*
		 * We told core not to send its reset email because we were going to invite.
		 * If we cannot actually build the invitation (no reset key, or no Workspace
		 * page to point at), returning here would leave the agent with no email at
		 * all — the very lockout this sprint exists to remove. Hand the job back to
		 * WordPress instead.
		 */
		if ( '' === $setup_url ) {
			if ( ! function_exists( 'retrieve_password' ) ) {
				require_once ABSPATH . 'wp-includes/user.php';
			}

			retrieve_password( $user->user_login );

			return;
		}

		$context              = $this->base_context( $user );
		$context              = array_merge(
			$context,
			array(
				'email_type' => EmailConstants::TYPE_WS_INVITATION,
				'title'      => __( 'You have been invited', 'havenlytics' ),
				'intro'      => sprintf(
					/* translators: %s: site name. */
					__( 'An account has been created for you on %s. Set a password and your workspace is ready — you can add listings and take inquiries straight away.', 'havenlytics' ),
					(string) $context['site_name']
				),
				'detail_rows' => array(
					__( 'Username', 'havenlytics' ) => (string) $user->user_login,
				),
				'cta_url'    => $setup_url,
				'cta_label'  => __( 'Set your password', 'havenlytics' ),
				'footnote'   => __( 'This link is single-use and expires in 24 hours. If it has expired, use "Forgot password" on the sign-in page.', 'havenlytics' ),
			)
		);
		$context['preheader'] = (string) $context['intro'];

		$this->dispatch(
			$user->user_email,
			sprintf( /* translators: %s: site name. */ __( 'You have been invited to %s', 'havenlytics' ), (string) $context['site_name'] ),
			EmailConstants::TYPE_WS_INVITATION,
			$context
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Internals                                                               */
	/* ---------------------------------------------------------------------- */

	/**
	 * Render + send.
	 *
	 * @param string               $to         Recipient.
	 * @param string               $subject    Subject.
	 * @param string               $email_type Email type.
	 * @param array<string, mixed> $context    Context.
	 * @return void
	 */
	private function dispatch( string $to, string $subject, string $email_type, array $context ): void {
		$body = $this->renderer->render( $email_type, $context );
		if ( '' === trim( $body ) ) {
			return;
		}

		/**
		 * Filter a Workspace account email subject.
		 *
		 * @since 3.2.1
		 *
		 * @param string               $subject    Subject.
		 * @param string               $email_type Email type.
		 * @param array<string, mixed> $context    Context.
		 */
		$subject = (string) apply_filters( 'hvnly_workspace_account_email_subject', $subject, $email_type, $context );

		$sent = wp_mail( $to, $subject, $body, EmailHeaders::base() );

		/**
		 * Fires after a Workspace account email attempt.
		 *
		 * @since 3.2.1
		 *
		 * @param bool                 $sent       Whether wp_mail succeeded.
		 * @param string               $email_type Email type.
		 * @param string               $to         Recipient.
		 * @param array<string, mixed> $context    Context.
		 */
		do_action( 'hvnly_workspace_account_email_sent', (bool) $sent, $email_type, $to, $context );
	}

	/**
	 * Shared context for account emails.
	 *
	 * @param \WP_User $user User.
	 * @return array<string, mixed>
	 */
	private function base_context( \WP_User $user ): array {
		return array_merge(
			EmailBranding::context(),
			array(
				'user_name'     => sanitize_text_field( $user->display_name ),
				'user_email'    => sanitize_email( $user->user_email ),
				'site_name'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'site_url'      => home_url( '/' ),
				'workspace_url' => $this->workspace_url(),
				'detail_rows'   => array(),
				'footnote'      => '',
			)
		);
	}

	/**
	 * Workspace sign-in URL.
	 *
	 * @return string
	 */
	private function workspace_url(): string {
		return WorkspaceSettings::route_url( 'login' );
	}

	/**
	 * Workspace password-reset URL carrying core's key.
	 *
	 * @param string $login User login.
	 * @param string $key   Reset key.
	 * @return string
	 */
	private function reset_url( string $login, string $key ): string {
		return WorkspaceSettings::route_url(
			'reset-password',
			array(
				'login' => $login,
				'key'   => $key,
			)
		);
	}

	/**
	 * Read an existing settings toggle. Storage is NOT migrated — these keys live
	 * where they always have, in hvnly_plugin_settings['contact-agent'].
	 *
	 * @param string $key Option key.
	 * @return bool
	 */
	private function is_enabled( string $key ): bool {
		$stored = get_option( 'hvnly_plugin_settings', array() );
		$group  = ( is_array( $stored ) && isset( $stored['contact-agent'] ) && is_array( $stored['contact-agent'] ) )
			? $stored['contact-agent']
			: array();

		if ( ! array_key_exists( $key, $group ) ) {
			return true; // Default on when unset, matching RegistrationEmailNotifier.
		}

		return ! empty( $group[ $key ] );
	}
}
