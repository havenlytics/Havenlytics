<?php
/**
 * Permanent admin unification: User ⇄ Agent CPT (Sprint 21B).
 *
 * FLOW A: Publish Agent CPT → ensure linked User (Pending Activation).
 * FLOW B: Create User role=hvnly_agent → ensure linked Agent CPT (Pending Activation).
 *
 * Frontend Workspace registration: unchanged (skip status override).
 * Demo Import: skipped via $GLOBALS['hvnly_bulk_importing'].
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
final class AgentIdentityAdminBridge {

	public const NONCE_CREATE_USER   = 'hvnly_create_workspace_account';
	public const NONCE_CREATE_AGENT  = 'hvnly_create_agent_for_user';
	public const NONCE_SEND_PASSWORD = 'hvnly_send_password_setup';

	/**
	 * Agent IDs that entered publish/private this request (provision after metabox email save).
	 *
	 * @var array<int, true>
	 */
	private $pending_agent_provision = array();

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'user_register', array( $this, 'on_user_register' ), 20 );
		add_action( 'set_user_role', array( $this, 'on_set_user_role' ), 20, 3 );
		add_action( 'add_user_role', array( $this, 'on_add_user_role' ), 20, 2 );
		// First enter publish/private only — never re-run on every Update of legacy/demo agents.
		add_action( 'transition_post_status', array( $this, 'on_agent_status_transition' ), 10, 3 );
		// After AgentMetabox::save (priority 10) so email meta is available.
		add_action( 'save_post_' . AgentConstants::POST_TYPE, array( $this, 'on_agent_saved' ), 20, 2 );
		add_action( 'admin_post_hvnly_create_workspace_account', array( $this, 'handle_create_workspace_account' ) );
		add_action( 'admin_post_hvnly_create_agent_for_user', array( $this, 'handle_create_agent_for_user' ) );
		add_action( 'admin_post_hvnly_send_password_setup', array( $this, 'handle_send_password_setup' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * @param int $user_id New user ID.
	 * @return void
	 */
	public function on_user_register( int $user_id ): void {
		$this->maybe_ensure_agent_for_user( $user_id );
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $role    New role.
	 * @param array  $old     Old roles.
	 * @return void
	 */
	public function on_set_user_role( $user_id, $role, $old = array() ): void {
		unset( $old );
		if ( PortalCapabilities::WP_ROLE_AGENT === (string) $role ) {
			$this->maybe_ensure_agent_for_user( (int) $user_id );
		}
	}

	/**
	 * @param int    $user_id User ID.
	 * @param string $role    Role added.
	 * @return void
	 */
	public function on_add_user_role( $user_id, $role ): void {
		if ( PortalCapabilities::WP_ROLE_AGENT === (string) $role ) {
			$this->maybe_ensure_agent_for_user( (int) $user_id );
		}
	}

	/**
	 * Mark first transition into publish/private (before metabox email save).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public function on_agent_status_transition( $new_status, $old_status, $post ): void {
		if ( AgentProvisioner::is_auto_user_provision_suppressed() ) {
			return;
		}
		if ( ! $post instanceof \WP_Post || AgentConstants::POST_TYPE !== $post->post_type ) {
			return;
		}
		$new_status = (string) $new_status;
		$old_status = (string) $old_status;
		if ( ! in_array( $new_status, array( 'publish', 'private' ), true ) ) {
			return;
		}
		// Already public — Update / bulk edit of published agents must not re-trigger.
		if ( in_array( $old_status, array( 'publish', 'private' ), true ) ) {
			return;
		}
		$this->pending_agent_provision[ (int) $post->ID ] = true;
	}

	/**
	 * FLOW A — after Agent CPT save, ensure linked User only on first publish/private enter.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public function on_agent_saved( $post_id, $post ): void {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! $post instanceof \WP_Post ) {
			return;
		}
		if ( AgentProvisioner::is_auto_user_provision_suppressed() ) {
			unset( $this->pending_agent_provision[ $post_id ] );
			return;
		}
		if ( empty( $this->pending_agent_provision[ $post_id ] ) ) {
			return;
		}
		unset( $this->pending_agent_provision[ $post_id ] );

		if ( AgentConstants::POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		// Demo Import / bulk property import — CPT-only agents.
		if ( ! empty( $GLOBALS['hvnly_bulk_importing'] ) ) {
			return;
		}

		$in_admin_context = is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		if ( ! $in_admin_context || ! current_user_can( 'create_users' ) ) {
			$this->store_notice(
				'error',
				__( 'Workspace account was not created: missing create_users capability or non-admin save context.', 'havenlytics' )
			);
			return;
		}
		if ( ! in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
			return;
		}

		$existing = absint( get_post_meta( $post_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $existing > 0 && get_userdata( $existing ) ) {
			return; // Idempotent — already linked.
		}

		$email = sanitize_email( (string) get_post_meta( $post_id, AgentConstants::META_EMAIL, true ) );
		if ( '' === $email || ! is_email( $email ) ) {
			$this->store_notice(
				'warning',
				__( 'Agent published without a Workspace account. Add a valid Agent email, then use “Create Workspace Account”.', 'havenlytics' )
			);
			return;
		}

		/**
		 * Filter whether admin Agent publish sends the WP password-setup email.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $send_reset Default true.
		 * @param int  $agent_id   Agent CPT ID.
		 */
		$send_reset = (bool) apply_filters( 'hvnly_agent_publish_send_password_setup', true, $post_id );

		$result = ( new AgentProvisioner() )->ensure_user_for_agent(
			$post_id,
			array(
				'send_reset'          => $send_reset,
				'registration_status' => WorkspaceRegistrationStatus::PENDING,
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->store_notice( 'error', $result->get_error_message() );
			return;
		}

		$this->store_notice(
			'success',
			sprintf(
				/* translators: %d: user ID */
				__( 'Workspace account linked (User #%d). Registration status: Pending Activation.', 'havenlytics' ),
				(int) $result
			)
		);
	}

	/**
	 * FLOW B — admin creates / assigns Agent role on a User.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function maybe_ensure_agent_for_user( int $user_id ): void {
		if ( AgentProvisioner::is_auto_agent_provision_suppressed() ) {
			return;
		}
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! $this->user_is_agent_role( $user_id ) ) {
			return;
		}

		$result = ( new AgentProvisioner() )->ensure_agent_for_user(
			$user_id,
			array(
				'status' => 'publish',
			)
		);
		if ( is_wp_error( $result ) ) {
			return;
		}

		// Front-end Workspace self-registration owns lifecycle — do not override.
		if ( $this->is_workspace_self_registration_request() ) {
			return;
		}

		if ( ! is_admin() || ! current_user_can( 'create_users' ) ) {
			return;
		}

		$raw = (string) get_user_meta( $user_id, WorkspaceRegistrationStatus::USER_META, true );
		if ( '' === $raw ) {
			WorkspaceRegistrationStatus::set_for_user(
				$user_id,
				WorkspaceRegistrationStatus::PENDING,
				(int) $result
			);
		}
	}

	/**
	 * @return bool
	 */
	private function is_workspace_self_registration_request(): bool {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['action'] ) ) : '';
		return SessionAuthController::ACTION_REGISTER === $action;
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function user_is_agent_role( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		return in_array( PortalCapabilities::WP_ROLE_AGENT, (array) $user->roles, true );
	}

	/**
	 * Manual / legacy: Create Workspace Account from Agent edit.
	 *
	 * @return void
	 */
	public function handle_create_workspace_account(): void {
		if ( ! current_user_can( 'create_users' ) || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to create Workspace accounts.', 'havenlytics' ), 403 );
		}
		check_admin_referer( self::NONCE_CREATE_USER );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$agent_id = isset( $_REQUEST['agent_id'] ) ? absint( $_REQUEST['agent_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$send_reset = ! isset( $_REQUEST['send_reset'] ) || '0' !== (string) wp_unslash( $_REQUEST['send_reset'] );

		$result = ( new AgentProvisioner() )->ensure_user_for_agent(
			$agent_id,
			array(
				'send_reset'          => $send_reset,
				'registration_status' => WorkspaceRegistrationStatus::PENDING,
			)
		);

		$redirect = get_edit_post_link( $agent_id, 'raw' );
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=' . AgentConstants::POST_TYPE );
		}

		if ( is_wp_error( $result ) ) {
			$this->store_notice( 'error', $result->get_error_message() );
			wp_safe_redirect( $redirect );
			exit;
		}

		$this->store_notice(
			'success',
			sprintf(
				/* translators: 1: user ID */
				__( 'Workspace account created and linked (User #%1$d). Status: Pending Activation.', 'havenlytics' ),
				(int) $result
			)
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Users list → Create Agent for role=agent user without CPT.
	 *
	 * @return void
	 */
	public function handle_create_agent_for_user(): void {
		if ( ! current_user_can( 'create_users' ) || ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to create Agent profiles.', 'havenlytics' ), 403 );
		}
		check_admin_referer( self::NONCE_CREATE_AGENT );

		$user_id = isset( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : 0;
		if ( $user_id <= 0 || ! $this->user_is_agent_role( $user_id ) ) {
			$this->store_notice( 'error', __( 'User must have the Agent role.', 'havenlytics' ) );
			wp_safe_redirect( admin_url( 'users.php' ) );
			exit;
		}

		$result = ( new AgentProvisioner() )->ensure_agent_for_user( $user_id, array( 'status' => 'publish' ) );
		if ( is_wp_error( $result ) ) {
			$this->store_notice( 'error', $result->get_error_message() );
			wp_safe_redirect( get_edit_user_link( $user_id ) ?: admin_url( 'users.php' ) );
			exit;
		}

		$raw = (string) get_user_meta( $user_id, WorkspaceRegistrationStatus::USER_META, true );
		if ( '' === $raw ) {
			WorkspaceRegistrationStatus::set_for_user(
				$user_id,
				WorkspaceRegistrationStatus::PENDING,
				(int) $result
			);
		}

		$this->store_notice(
			'success',
			sprintf(
				/* translators: %d: agent ID */
				__( 'Agent profile created and linked (Agent #%d). Status: Pending Activation.', 'havenlytics' ),
				(int) $result
			)
		);
		$edit = get_edit_post_link( (int) $result, 'raw' );
		wp_safe_redirect( is_string( $edit ) && $edit !== '' ? $edit : admin_url( 'users.php' ) );
		exit;
	}

	/**
	 * Send WP password-setup email for linked user (no plain-text password).
	 *
	 * @return void
	 */
	public function handle_send_password_setup(): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'You do not have permission to send password setup emails.', 'havenlytics' ), 403 );
		}
		check_admin_referer( self::NONCE_SEND_PASSWORD );

		$agent_id = isset( $_REQUEST['agent_id'] ) ? absint( $_REQUEST['agent_id'] ) : 0;
		$user_id  = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		$user     = $user_id > 0 ? get_userdata( $user_id ) : false;

		$redirect = get_edit_post_link( $agent_id, 'raw' );
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=' . AgentConstants::POST_TYPE );
		}

		if ( ! $user ) {
			$this->store_notice( 'error', __( 'No linked Workspace user found.', 'havenlytics' ) );
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( ! function_exists( 'retrieve_password' ) ) {
			require_once ABSPATH . 'wp-includes/user.php';
		}
		$result = retrieve_password( $user->user_login );
		if ( true === $result || ( ! is_wp_error( $result ) && false !== $result ) ) {
			$this->store_notice( 'success', __( 'Password setup email sent.', 'havenlytics' ) );
		} elseif ( is_wp_error( $result ) ) {
			$this->store_notice( 'error', $result->get_error_message() );
		} else {
			$this->store_notice( 'error', __( 'Could not send password setup email.', 'havenlytics' ) );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @return void
	 */
	public function admin_notices(): void {
		$key  = $this->notice_key();
		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['message'] ) ) {
			return;
		}
		delete_transient( $key );
		$type  = (string) ( $data['type'] ?? 'success' );
		$class = 'notice-success';
		if ( 'error' === $type ) {
			$class = 'notice-error';
		} elseif ( 'warning' === $type ) {
			$class = 'notice-warning';
		}
		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( (string) $data['message'] )
		);
	}

	/**
	 * @param string $type    success|error|warning.
	 * @param string $message Message.
	 * @return void
	 */
	private function store_notice( string $type, string $message ): void {
		set_transient(
			$this->notice_key(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			120
		);
	}

	/**
	 * @return string
	 */
	private function notice_key(): string {
		return 'hvnly_identity_bridge_notice_' . get_current_user_id();
	}

	/**
	 * Manual Create Workspace Account (no nested form — link only).
	 *
	 * @param int  $agent_id   Agent CPT ID.
	 * @param bool $send_reset Send password-setup email.
	 * @return string
	 */
	public static function create_workspace_account_url( int $agent_id, bool $send_reset = true ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'hvnly_create_workspace_account',
					'agent_id'   => absint( $agent_id ),
					'send_reset' => $send_reset ? '1' : '0',
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_CREATE_USER
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function create_agent_url( int $user_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'hvnly_create_agent_for_user',
					'user_id' => absint( $user_id ),
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_CREATE_AGENT
		);
	}

	/**
	 * @param int $agent_id Agent CPT ID.
	 * @return string
	 */
	public static function send_password_setup_url( int $agent_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'hvnly_send_password_setup',
					'agent_id' => absint( $agent_id ),
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_SEND_PASSWORD
		);
	}
}
