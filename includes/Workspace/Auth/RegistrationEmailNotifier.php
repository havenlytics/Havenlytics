<?php
/**
 * Sends Workspace registration lifecycle emails via Havenlytics EmailRenderer.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Email\EmailConstants;
use HvnlyNab\Email\EmailHeaders;
use HvnlyNab\Email\EmailRenderer;
use HvnlyNab\Email\EmailSettings;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Listens to registration status changes and emails the agent when enabled.
 *
 * @since 3.2.0
 */
final class RegistrationEmailNotifier {

	/**
	 * @var EmailRenderer
	 */
	private $renderer;

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
		add_action( 'hvnly_workspace_registration_status_changed', array( $this, 'on_status_changed' ), 20, 5 );
		add_filter( 'hvnly_email_templates', array( $this, 'register_templates' ) );
		add_filter( 'hvnly_email_template_labels', array( $this, 'register_labels' ) );
	}

	/**
	 * @param array<string, string> $templates Templates.
	 * @return array<string, string>
	 */
	public function register_templates( array $templates ): array {
		$templates[ EmailConstants::TYPE_WS_REG_APPROVED ]  = 'emails/workspace-registration-approved.php';
		$templates[ EmailConstants::TYPE_WS_REG_REJECTED ]  = 'emails/workspace-registration-rejected.php';
		$templates[ EmailConstants::TYPE_WS_REG_SUSPENDED ] = 'emails/workspace-registration-suspended.php';
		$templates[ EmailConstants::TYPE_WS_REG_ACTIVATED ] = 'emails/workspace-registration-activated.php';
		return $templates;
	}

	/**
	 * @param array<string, string> $labels Labels.
	 * @return array<string, string>
	 */
	public function register_labels( array $labels ): array {
		$labels[ EmailConstants::TYPE_WS_REG_APPROVED ]  = __( 'Registration Approved', 'havenlytics' );
		$labels[ EmailConstants::TYPE_WS_REG_REJECTED ]  = __( 'Registration Rejected', 'havenlytics' );
		$labels[ EmailConstants::TYPE_WS_REG_SUSPENDED ] = __( 'Account Suspended', 'havenlytics' );
		$labels[ EmailConstants::TYPE_WS_REG_ACTIVATED ] = __( 'Account Activated', 'havenlytics' );
		return $labels;
	}

	/**
	 * @param int         $user_id       User ID.
	 * @param string      $status        New status.
	 * @param int         $agent_id      Agent ID.
	 * @param string      $previous      Previous normalized status.
	 * @param string|null $raw_previous  Raw previous meta before normalization.
	 * @return void
	 */
	public function on_status_changed( $user_id, $status, $agent_id = 0, $previous = '', $raw_previous = null ): void {
		$user_id = absint( $user_id );

		// Sprint 30B: never email during a system event — demo / bulk import,
		// migration, repair, or a WP-CLI pass. Demo agent provisioning must stay
		// completely silent. Shares the one gate used by the property notifiers.
		if ( function_exists( 'hvnly_is_system_notification_context' ) && hvnly_is_system_notification_context() ) {
			return;
		}

		// Brand-new registration arrives with empty raw meta. set_for_user() still
		// normalizes that empty value to ACTIVE for comparisons, so detect first
		// write from the optional raw argument (or legacy empty $previous).
		$is_new_registration = ( null !== $raw_previous )
			? ( '' === trim( (string) $raw_previous ) )
			: ( '' === trim( (string) $previous ) );

		$status   = WorkspaceRegistrationStatus::normalize( (string) $status );
		$previous = WorkspaceRegistrationStatus::normalize( (string) $previous );
		$agent_id = absint( $agent_id );

		if ( $user_id <= 0 ) {
			return;
		}

		if ( ! $is_new_registration && $previous === $status ) {
			return;
		}

		// An admin creating an account for someone else sends an invitation
		// (WorkspaceAccountNotifier), not a "your registration was received" notice.
		// Suppress the self-registration mail on that path so nobody gets two emails.
		if ( $is_new_registration && $this->is_admin_provisioned( $user_id ) ) {
			return;
		}

		$email_type = $this->resolve_email_type( $status, $previous, $is_new_registration );
		if ( ! $email_type || ! $this->is_template_enabled( $email_type ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}

		$context = $this->build_context( $user, $status, $agent_id, $email_type );
		$subject = $this->resolve_subject( $email_type, $context );
		$body    = $this->renderer->render( $email_type, $context );

		if ( '' === trim( $body ) ) {
			return;
		}

		// Sprint 24C: shared, DMARC-safe headers. See EmailHeaders for why the old
		// admin_email fallback for From: was removed.
		$headers = EmailHeaders::base();

		$sent = wp_mail( $user->user_email, $subject, $body, $headers );

		// A registration awaiting approval must also tell the administrator —
		// previously nobody was ever notified that someone was waiting.
		if ( EmailConstants::TYPE_WS_REG_PENDING === $email_type ) {
			$this->notify_admin_of_pending( $user, $context );
		}

		/**
		 * Fires after a Workspace registration email attempt.
		 *
		 * @since 3.2.0
		 *
		 * @param bool                 $sent       Whether wp_mail succeeded.
		 * @param string               $email_type Email type.
		 * @param int                  $user_id    User ID.
		 * @param array<string, mixed> $context    Context.
		 */
		do_action( 'hvnly_workspace_registration_email_sent', (bool) $sent, $email_type, $user_id, $context );
	}

	/**
	 * @param string $status   New.
	 * @param string $previous Old.
	 * @return string|null
	 */
	private function resolve_email_type( string $status, string $previous, bool $is_new_registration = false ): ?string {
		// Sprint 24C — the two states that previously produced nothing at all.
		if ( $is_new_registration ) {
			if ( WorkspaceRegistrationStatus::PENDING === $status ) {
				return EmailConstants::TYPE_WS_REG_PENDING;
			}
			if ( WorkspaceRegistrationStatus::ACTIVE === $status ) {
				return EmailConstants::TYPE_AGENT_REGISTER;
			}
		}

		if ( WorkspaceRegistrationStatus::PENDING === $status ) {
			return EmailConstants::TYPE_WS_REG_PENDING;
		}

		if ( WorkspaceRegistrationStatus::ARCHIVED === $status ) {
			return EmailConstants::TYPE_WS_REG_ARCHIVED;
		}

		$deny_prev = in_array(
			$previous,
			array(
				WorkspaceRegistrationStatus::SUSPENDED,
				WorkspaceRegistrationStatus::BLOCKED,
				WorkspaceRegistrationStatus::ARCHIVED,
			),
			true
		);

		if ( WorkspaceRegistrationStatus::ACTIVE === $status && $deny_prev ) {
			return EmailConstants::TYPE_WS_REG_ACTIVATED;
		}
		if ( WorkspaceRegistrationStatus::APPROVED === $status && WorkspaceRegistrationStatus::PENDING === $previous ) {
			return EmailConstants::TYPE_WS_REG_APPROVED;
		}
		if ( WorkspaceRegistrationStatus::ACTIVE === $status && WorkspaceRegistrationStatus::APPROVED === $previous ) {
			return EmailConstants::TYPE_WS_REG_ACTIVATED;
		}
		if ( WorkspaceRegistrationStatus::APPROVED === $status ) {
			return EmailConstants::TYPE_WS_REG_APPROVED;
		}
		if ( WorkspaceRegistrationStatus::BLOCKED === $status ) {
			return EmailConstants::TYPE_WS_REG_REJECTED;
		}
		if ( WorkspaceRegistrationStatus::SUSPENDED === $status ) {
			return EmailConstants::TYPE_WS_REG_SUSPENDED;
		}
		return null;
	}

	/**
	 * @param string $email_type Type.
	 * @return bool
	 */
	private function is_template_enabled( string $email_type ): bool {
		$map = array(
			EmailConstants::TYPE_WS_REG_APPROVED  => 'hvnly_workspace_email_approval',
			EmailConstants::TYPE_WS_REG_REJECTED  => 'hvnly_workspace_email_rejection',
			EmailConstants::TYPE_WS_REG_SUSPENDED => 'hvnly_workspace_email_suspended',
			EmailConstants::TYPE_WS_REG_ACTIVATED => 'hvnly_workspace_email_activated',
			// Sprint 24C: hvnly_workspace_email_registration existed in the schema and
			// the settings UI but had no PHP consumer. It now gates the registration
			// received / pending emails, as its label always claimed.
			EmailConstants::TYPE_AGENT_REGISTER   => 'hvnly_workspace_email_registration',
			EmailConstants::TYPE_WS_REG_PENDING   => 'hvnly_workspace_email_registration',
			EmailConstants::TYPE_WS_REG_ARCHIVED  => 'hvnly_workspace_email_activated',
		);

		$key = isset( $map[ $email_type ] ) ? $map[ $email_type ] : '';
		if ( '' === $key ) {
			return true;
		}

		$stored = get_option( 'hvnly_plugin_settings', array() );
		$group  = ( is_array( $stored ) && isset( $stored['contact-agent'] ) && is_array( $stored['contact-agent'] ) )
			? $stored['contact-agent']
			: array();

		if ( ! array_key_exists( $key, $group ) ) {
			return true; // Default on when unset.
		}

		return ! empty( $group[ $key ] );
	}

	/**
	 * @param \WP_User $user       User.
	 * @param string   $status     Status.
	 * @param int      $agent_id   Agent ID.
	 * @param string   $email_type Type.
	 * @return array<string, mixed>
	 */
	private function build_context( $user, string $status, int $agent_id, string $email_type ): array {
		$workspace_url = WorkspaceSettings::route_url( 'login' );

		$context = array(
			'email_type'     => $email_type,
			'user_name'      => sanitize_text_field( $user->display_name ),
			'user_email'     => sanitize_email( $user->user_email ),
			'agent_id'       => (string) $agent_id,
			'status'         => $status,
			'status_label'   => WorkspaceRegistrationStatus::label( $status ),
			'workspace_url'  => esc_url_raw( $workspace_url ),
			'site_name'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'       => home_url( '/' ),
		);

		// Sprint 24C: written to sound like a person, not a state machine.
		$intros           = array(
			EmailConstants::TYPE_WS_REG_APPROVED  => __( 'Good news — your workspace has been approved. You can sign in now and start adding listings.', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_REJECTED  => __( 'We were not able to approve your workspace registration. If you think this is a mistake, reply to this email or get in touch with the team.', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_SUSPENDED => __( 'Your workspace access has been paused, so you will not be able to sign in for now. Get in touch and we will sort it out with you.', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_ACTIVATED => __( 'You are back in — your workspace has been reactivated and everything is where you left it.', 'havenlytics' ),
			EmailConstants::TYPE_AGENT_REGISTER   => __( 'Welcome aboard. Your workspace is ready — sign in to add your first listing and start taking inquiries.', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_PENDING   => __( 'Thanks for registering. An administrator reviews new agent accounts, so you will hear from us shortly — we will email you the moment your workspace is ready.', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_ARCHIVED  => __( 'Your workspace has been archived and you will no longer be able to sign in. Your listings are safe. Get in touch if you would like it reopened.', 'havenlytics' ),
		);
		$context['intro'] = isset( $intros[ $email_type ] ) ? $intros[ $email_type ] : '';

		// Templates that share the notice layout read these.
		$context['title']     = $this->resolve_title( $email_type );
		$context['preheader'] = (string) $context['intro'];
		$context['cta_url']   = (string) $context['workspace_url'];
		$context['cta_label'] = __( 'Sign in to your workspace', 'havenlytics' );

		// A blocked, suspended or archived agent cannot sign in — offering them a
		// sign-in button would be a dead end, so drop the CTA for those.
		$no_cta = array(
			EmailConstants::TYPE_WS_REG_REJECTED,
			EmailConstants::TYPE_WS_REG_SUSPENDED,
			EmailConstants::TYPE_WS_REG_ARCHIVED,
			EmailConstants::TYPE_WS_REG_PENDING,
		);
		if ( in_array( $email_type, $no_cta, true ) ) {
			$context['cta_url']   = '';
			$context['cta_label'] = '';
		}

		/**
		 * Filter Workspace registration email context.
		 *
		 * @since 3.2.0
		 *
		 * @param array<string, mixed> $context    Context.
		 * @param string               $email_type Type.
		 * @param \WP_User             $user       User.
		 */
		return (array) apply_filters( 'hvnly_workspace_registration_email_context', $context, $email_type, $user );
	}

	/**
	 * @param string               $email_type Type.
	 * @param array<string, mixed> $context    Context.
	 * @return string
	 */
	private function resolve_subject( string $email_type, array $context ): string {
		$site    = (string) ( $context['site_name'] ?? get_bloginfo( 'name' ) );
		$map     = array(
			EmailConstants::TYPE_WS_REG_APPROVED     => sprintf( /* translators: %s: site name. */ __( 'Your workspace is ready — %s', 'havenlytics' ), $site ),
			EmailConstants::TYPE_WS_REG_REJECTED     => sprintf( /* translators: %s: site name. */ __( 'About your registration — %s', 'havenlytics' ), $site ),
			EmailConstants::TYPE_WS_REG_SUSPENDED    => sprintf( /* translators: %s: site name. */ __( 'Your account has been paused — %s', 'havenlytics' ), $site ),
			EmailConstants::TYPE_WS_REG_ACTIVATED    => sprintf( /* translators: %s: site name. */ __( 'You are back in — %s', 'havenlytics' ), $site ),
			EmailConstants::TYPE_AGENT_REGISTER      => sprintf( /* translators: %s: site name. */ __( 'Welcome to %s', 'havenlytics' ), $site ),
			EmailConstants::TYPE_WS_REG_PENDING      => sprintf( /* translators: %s: site name. */ __( 'We received your registration — %s', 'havenlytics' ), $site ),
			EmailConstants::TYPE_WS_REG_ARCHIVED     => sprintf( /* translators: %s: site name. */ __( 'Your workspace has been archived — %s', 'havenlytics' ), $site ),
			// The admin alert blanks user_name (no "Hi <agent>," greeting for the
			// admin), so the agent's name is carried separately in agent_name.
			EmailConstants::TYPE_WS_REG_ADMIN_ALERT  => sprintf( /* translators: %s: agent name. */ __( 'New agent awaiting approval: %s', 'havenlytics' ), (string) ( $context['agent_name'] ?? $context['user_name'] ?? '' ) ),
		);
		$subject = isset( $map[ $email_type ] ) ? $map[ $email_type ] : $site;

		/**
		 * Filter Workspace registration email subject.
		 *
		 * @since 3.2.0
		 *
		 * @param string               $subject    Subject.
		 * @param string               $email_type Type.
		 * @param array<string, mixed> $context    Context.
		 */
		return (string) apply_filters( 'hvnly_workspace_registration_email_subject', $subject, $email_type, $context );
	}

	/**
	 * Heading shown inside the email body.
	 *
	 * @param string $email_type Type.
	 * @return string
	 */
	private function resolve_title( string $email_type ): string {
		$map = array(
			EmailConstants::TYPE_WS_REG_APPROVED    => __( 'Your workspace is ready', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_REJECTED    => __( 'About your registration', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_SUSPENDED   => __( 'Your account has been paused', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_ACTIVATED   => __( 'You are back in', 'havenlytics' ),
			EmailConstants::TYPE_AGENT_REGISTER     => __( 'Welcome to your workspace', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_PENDING     => __( 'We received your registration', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_ARCHIVED    => __( 'Your workspace has been archived', 'havenlytics' ),
			EmailConstants::TYPE_WS_REG_ADMIN_ALERT => __( 'A new agent is waiting for approval', 'havenlytics' ),
		);

		return isset( $map[ $email_type ] ) ? $map[ $email_type ] : __( 'Account update', 'havenlytics' );
	}

	/**
	 * Tell the administrator that a registration is waiting for them.
	 *
	 * Before Sprint 24C nothing in the plugin ever notified an admin about any
	 * lifecycle event — a pending registration could sit unseen indefinitely.
	 *
	 * @param \WP_User             $user    Registering user.
	 * @param array<string, mixed> $context Agent-side context.
	 * @return void
	 */
	private function notify_admin_of_pending( $user, array $context ): void {
		$admin_email = sanitize_email( (string) get_option( 'admin_email' ) );

		/**
		 * Filter the recipient of the "registration awaiting approval" alert.
		 *
		 * Return '' to disable the admin alert.
		 *
		 * @since 3.2.1
		 *
		 * @param string   $admin_email Recipient.
		 * @param \WP_User $user        Registering user.
		 */
		$admin_email = (string) apply_filters( 'hvnly_workspace_registration_admin_alert_email', $admin_email, $user );

		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$review_url = admin_url( 'edit.php?post_type=hvnly_agent' );

		$agent_name = (string) ( $context['user_name'] ?? '' );

		$admin_context              = array_merge(
			$context,
			array(
				'email_type'  => EmailConstants::TYPE_WS_REG_ADMIN_ALERT,
				'title'       => $this->resolve_title( EmailConstants::TYPE_WS_REG_ADMIN_ALERT ),
				// No "Hi <name>," greeting for the admin — but keep the agent's name
				// available for the subject line and the detail rows.
				'user_name'   => '',
				'agent_name'  => $agent_name,
				'intro'       => sprintf(
					/* translators: %s: agent display name. */
					__( '%s has registered for a workspace and is waiting for approval. Nothing happens until you approve or decline the request.', 'havenlytics' ),
					(string) ( $context['user_name'] ?? '' )
				),
				'detail_rows' => array(
					__( 'Name', 'havenlytics' )       => (string) ( $context['user_name'] ?? '' ),
					__( 'Email', 'havenlytics' )      => (string) ( $context['user_email'] ?? '' ),
					__( 'Registered', 'havenlytics' ) => date_i18n( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
				),
				'cta_url'     => esc_url_raw( $review_url ),
				'cta_label'   => __( 'Review the request', 'havenlytics' ),
				'footnote'    => '',
			)
		);
		$admin_context['preheader'] = (string) $admin_context['intro'];

		$body = $this->renderer->render( EmailConstants::TYPE_WS_REG_ADMIN_ALERT, $admin_context );
		if ( '' === trim( $body ) ) {
			return;
		}

		$subject = $this->resolve_subject( EmailConstants::TYPE_WS_REG_ADMIN_ALERT, $admin_context );

		$headers = EmailHeaders::base();
		$reply   = EmailHeaders::reply_to( (string) ( $context['user_email'] ?? '' ), (string) ( $context['user_name'] ?? '' ) );
		if ( '' !== $reply ) {
			$headers[] = $reply;
		}

		$sent = wp_mail( $admin_email, $subject, $body, $headers );

		/**
		 * Fires after the admin "registration awaiting approval" alert is attempted.
		 *
		 * @since 3.2.1
		 *
		 * @param bool                 $sent    Whether wp_mail succeeded.
		 * @param \WP_User             $user    Registering user.
		 * @param array<string, mixed> $context Admin email context.
		 */
		do_action( 'hvnly_workspace_registration_admin_alert_sent', (bool) $sent, $user, $admin_context );
	}

	/**
	 * Was this account created by an administrator on someone else's behalf?
	 *
	 * Self-registration happens while logged out, so current_user_id() is 0.
	 * Admin provisioning happens while an administrator is signed in and acting
	 * on a different user — that path sends an invitation instead.
	 *
	 * @param int $user_id The user whose status just changed.
	 * @return bool
	 */
	private function is_admin_provisioned( int $user_id ): bool {
		$actor = get_current_user_id();

		$provisioned = ( $actor > 0 && $actor !== $user_id && current_user_can( 'edit_users' ) );

		/**
		 * Filter whether a new account is treated as admin-provisioned.
		 *
		 * @since 3.2.1
		 *
		 * @param bool $provisioned Whether an admin created this account.
		 * @param int  $user_id     New user ID.
		 * @param int  $actor       Acting user ID.
		 */
		return (bool) apply_filters( 'hvnly_workspace_registration_is_admin_provisioned', $provisioned, $user_id, $actor );
	}
}
