<?php
/**
 * Agent admin registration approval UI (row / bulk / metabox / AJAX).
 *
 * Updates ONLY `_hvnly_ws_registration_status` — never post_status.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce-style registration lifecycle controls on hvnly_agent.
 *
 * @since 3.2.0
 */
final class RegistrationAdminActions {

	public const AJAX_ACTION  = 'hvnly_ws_reg_set_status';
	public const NONCE_ACTION = 'hvnly_ws_reg_status';
	public const META_FIELD    = 'hvnly_ws_registration_status_field';
	public const META_PREVIOUS = 'hvnly_ws_registration_status_previous';
	public const META_NONCE    = 'hvnly_ws_reg_metabox';

	/**
	 * @return void
	 */
	public function register(): void {
		$slug = AgentConstants::POST_TYPE;

		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 20, 2 );
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'save_post_' . $slug, array( $this, 'save_metabox' ), 20, 2 );

		add_filter( 'bulk_actions-edit-' . $slug, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . $slug, array( $this, 'handle_bulk_actions' ), 10, 3 );

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_set_status' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		// Fallback non-AJAX admin-post handlers.
		add_action( 'admin_post_hvnly_ws_reg_approve', array( $this, 'handle_admin_post_approve' ) );
		add_action( 'admin_post_hvnly_ws_reg_activate', array( $this, 'handle_admin_post_activate' ) );
		add_action( 'admin_post_hvnly_ws_reg_suspend', array( $this, 'handle_admin_post_suspend' ) );
		add_action( 'admin_post_hvnly_ws_reg_block', array( $this, 'handle_admin_post_block' ) );
		add_action( 'admin_post_hvnly_ws_reg_archive', array( $this, 'handle_admin_post_archive' ) );
		add_action( 'admin_post_hvnly_ws_reg_restore', array( $this, 'handle_admin_post_restore' ) );
		// Legacy reject → block.
		add_action( 'admin_post_hvnly_ws_reg_reject', array( $this, 'handle_admin_post_block' ) );
	}

	/**
	 * @param string $hook_suffix Hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || AgentConstants::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$ver = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.2.0';

		wp_register_style(
			'hvnly-ws-registration-admin',
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-ws-registration-admin.css',
			array(),
			$ver
		);
		wp_enqueue_style( 'hvnly-ws-registration-admin' );

		if ( ! in_array( $hook_suffix, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_register_script(
			'hvnly-ws-registration-admin',
			HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-ws-registration-admin.js',
			array( 'jquery' ),
			$ver,
			true
		);
		wp_enqueue_script( 'hvnly-ws-registration-admin' );

		$confirms = array();
		foreach ( WorkspaceRegistrationStatus::all() as $slug ) {
			$msg = WorkspaceRegistrationStatus::confirm_message( $slug );
			if ( '' !== $msg ) {
				$confirms[ $slug ] = $msg;
			}
		}

		wp_localize_script(
			'hvnly-ws-registration-admin',
			'HvnlyWsRegistrationAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'action'   => self::AJAX_ACTION,
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'confirms' => $confirms,
				'i18n'     => array(
					'working' => __( 'Updating…', 'havenlytics' ),
					'error'   => __( 'Could not update agent lifecycle status.', 'havenlytics' ),
					'success' => __( 'Agent lifecycle status updated.', 'havenlytics' ),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	private function can_manage_registration(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Administrator (or equivalent) + Workspace portal cap primed on admins.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return current_user_can( PortalCapabilities::APPROVE_LISTINGS )
			&& current_user_can( PortalCapabilities::PORTAL_ACCESS );
	}

	/**
	 * @param int $agent_id Agent ID.
	 * @return bool
	 */
	private function can_manage_agent( int $agent_id ): bool {
		return $this->can_manage_registration() && current_user_can( 'edit_post', $agent_id );
	}

	/**
	 * @param array<string, string> $actions Actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function row_actions( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || AgentConstants::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		if ( ! $this->can_manage_agent( (int) $post->ID ) ) {
			return $actions;
		}

		$user_id = absint( get_post_meta( $post->ID, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $user_id <= 0 ) {
			return $actions;
		}

		$status  = WorkspaceRegistrationStatus::get_for_user( $user_id );
		$agent_id = (int) $post->ID;

		$links = $this->available_actions_for_status( $status );
		foreach ( $links as $key => $item ) {
			$actions[ 'hvnly_ws_' . $key ] = sprintf(
				'<a href="%1$s" class="hvnly-ws-reg-action" data-agent-id="%2$d" data-status="%3$s">%4$s</a>',
				esc_url( $this->fallback_url( $key, $agent_id ) ),
				$agent_id,
				esc_attr( $item['status'] ),
				esc_html( $item['label'] )
			);
		}

		return $actions;
	}

	/**
	 * @param string $status Current status.
	 * @return array<string, array{status:string,label:string}>
	 */
	private function available_actions_for_status( string $status ): array {
		$status = WorkspaceRegistrationStatus::normalize( $status );
		$out    = array();
		$is_admin = current_user_can( 'manage_options' );

		switch ( $status ) {
			case WorkspaceRegistrationStatus::PENDING:
				$out['approve'] = array(
					'status' => WorkspaceRegistrationStatus::APPROVED,
					'label'  => __( 'Approve', 'havenlytics' ),
				);
				$out['block']   = array(
					'status' => WorkspaceRegistrationStatus::BLOCKED,
					'label'  => __( 'Block', 'havenlytics' ),
				);
				$out['suspend'] = array(
					'status' => WorkspaceRegistrationStatus::SUSPENDED,
					'label'  => __( 'Suspend', 'havenlytics' ),
				);
				break;
			case WorkspaceRegistrationStatus::APPROVED:
				$out['activate'] = array(
					'status' => WorkspaceRegistrationStatus::ACTIVE,
					'label'  => __( 'Activate', 'havenlytics' ),
				);
				$out['suspend']  = array(
					'status' => WorkspaceRegistrationStatus::SUSPENDED,
					'label'  => __( 'Suspend', 'havenlytics' ),
				);
				$out['block']    = array(
					'status' => WorkspaceRegistrationStatus::BLOCKED,
					'label'  => __( 'Block', 'havenlytics' ),
				);
				$out['archive']  = array(
					'status' => WorkspaceRegistrationStatus::ARCHIVED,
					'label'  => __( 'Archive', 'havenlytics' ),
				);
				break;
			case WorkspaceRegistrationStatus::ACTIVE:
				$out['suspend'] = array(
					'status' => WorkspaceRegistrationStatus::SUSPENDED,
					'label'  => __( 'Suspend', 'havenlytics' ),
				);
				$out['block']   = array(
					'status' => WorkspaceRegistrationStatus::BLOCKED,
					'label'  => __( 'Block', 'havenlytics' ),
				);
				$out['archive'] = array(
					'status' => WorkspaceRegistrationStatus::ARCHIVED,
					'label'  => __( 'Archive', 'havenlytics' ),
				);
				break;
			case WorkspaceRegistrationStatus::SUSPENDED:
				$out['activate'] = array(
					'status' => WorkspaceRegistrationStatus::ACTIVE,
					'label'  => __( 'Restore', 'havenlytics' ),
				);
				$out['block']    = array(
					'status' => WorkspaceRegistrationStatus::BLOCKED,
					'label'  => __( 'Block', 'havenlytics' ),
				);
				$out['archive']  = array(
					'status' => WorkspaceRegistrationStatus::ARCHIVED,
					'label'  => __( 'Archive', 'havenlytics' ),
				);
				break;
			case WorkspaceRegistrationStatus::BLOCKED:
				$out['activate'] = array(
					'status' => WorkspaceRegistrationStatus::ACTIVE,
					'label'  => __( 'Restore', 'havenlytics' ),
				);
				$out['archive']  = array(
					'status' => WorkspaceRegistrationStatus::ARCHIVED,
					'label'  => __( 'Archive', 'havenlytics' ),
				);
				break;
			case WorkspaceRegistrationStatus::ARCHIVED:
				if ( $is_admin ) {
					$out['restore'] = array(
						'status' => WorkspaceRegistrationStatus::ACTIVE,
						'label'  => __( 'Restore', 'havenlytics' ),
					);
				}
				break;
		}

		return $out;
	}

	/**
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( $actions ) {
		if ( ! $this->can_manage_registration() ) {
			return $actions;
		}

		$actions['hvnly_ws_approve']  = __( 'Approve Agent', 'havenlytics' );
		$actions['hvnly_ws_activate'] = __( 'Activate Agent', 'havenlytics' );
		$actions['hvnly_ws_suspend']  = __( 'Suspend Agent', 'havenlytics' );
		$actions['hvnly_ws_block']    = __( 'Block Agent', 'havenlytics' );
		$actions['hvnly_ws_archive']  = __( 'Archive Agent', 'havenlytics' );
		if ( current_user_can( 'manage_options' ) ) {
			$actions['hvnly_ws_restore'] = __( 'Restore Agent', 'havenlytics' );
		}

		return $actions;
	}

	/**
	 * @param string $redirect Redirect URL.
	 * @param string $doaction Action.
	 * @param int[]  $post_ids IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect, $doaction, $post_ids ) {
		$map = array(
			'hvnly_ws_approve'  => WorkspaceRegistrationStatus::APPROVED,
			'hvnly_ws_activate' => WorkspaceRegistrationStatus::ACTIVE,
			'hvnly_ws_suspend'  => WorkspaceRegistrationStatus::SUSPENDED,
			'hvnly_ws_block'    => WorkspaceRegistrationStatus::BLOCKED,
			'hvnly_ws_archive'  => WorkspaceRegistrationStatus::ARCHIVED,
			'hvnly_ws_restore'  => WorkspaceRegistrationStatus::ACTIVE,
			'hvnly_ws_reject'   => WorkspaceRegistrationStatus::BLOCKED, // legacy
		);

		if ( ! isset( $map[ $doaction ] ) || ! $this->can_manage_registration() ) {
			return $redirect;
		}

		$updated = 0;
		foreach ( (array) $post_ids as $agent_id ) {
			$agent_id = absint( $agent_id );
			if ( $agent_id <= 0 || ! $this->can_manage_agent( $agent_id ) ) {
				continue;
			}
			if ( $this->apply_status( $agent_id, $map[ $doaction ] ) ) {
				++$updated;
			}
		}

		return add_query_arg(
			array(
				'hvnly_ws_reg_bulk' => $map[ $doaction ],
				'hvnly_ws_reg_n'    => $updated,
			),
			$redirect
		);
	}

	/**
	 * @return void
	 */
	public function register_metabox(): void {
		add_meta_box(
			'hvnly_ws_registration_status',
			__( 'Agent Lifecycle', 'havenlytics' ),
			array( $this, 'render_metabox' ),
			AgentConstants::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function render_metabox( $post ): void {
		$user_id = absint( get_post_meta( $post->ID, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $user_id <= 0 ) {
			echo '<p>' . esc_html__( 'No linked WordPress user. Link a user to manage Workspace registration.', 'havenlytics' ) . '</p>';
			return;
		}

		$status = WorkspaceRegistrationStatus::get_for_user( $user_id );
		$user   = get_userdata( $user_id );

		wp_nonce_field( self::META_NONCE, self::META_NONCE );

		echo '<p class="description">' . esc_html__( 'Lifecycle controls Workspace access. Publishing the Agent profile does not change lifecycle — use this panel or row actions (Approve / Suspend / Block / Archive / Restore).', 'havenlytics' ) . '</p>';

		if ( $user ) {
			echo '<p><strong>' . esc_html( $user->user_login ) . '</strong><br><span class="description">' . esc_html( $user->user_email ) . '</span></p>';
		}

		printf(
			'<input type="hidden" name="%1$s" value="%2$s" />',
			esc_attr( self::META_PREVIOUS ),
			esc_attr( $status )
		);

		echo '<p><label for="' . esc_attr( self::META_FIELD ) . '"><strong>' . esc_html__( 'Lifecycle Status', 'havenlytics' ) . '</strong></label></p>';
		echo '<select name="' . esc_attr( self::META_FIELD ) . '" id="' . esc_attr( self::META_FIELD ) . '" class="widefat">';
		foreach ( WorkspaceRegistrationStatus::all() as $value ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $status, $value, false ),
				esc_html( WorkspaceRegistrationStatus::label( $value ) )
			);
		}
		echo '</select>';

		echo '<p style="margin-top:12px;">' . wp_kses_post( WorkspaceRegistrationStatus::badge_html( $status ) ) . '</p>';
		echo '<p class="description">' . esc_html( WorkspaceRegistrationStatus::workspace_state_label( $user_id ) ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Archived can only be restored by an Administrator.', 'havenlytics' ) . '</p>';
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public function save_metabox( $post_id, $post ): void {
		unset( $post );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::META_NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::META_NONCE ] ) ), self::META_NONCE ) ) {
			return;
		}
		if ( ! $this->can_manage_agent( (int) $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::META_FIELD ] ) ) {
			return;
		}

		$posted = WorkspaceRegistrationStatus::normalize( sanitize_key( wp_unslash( $_POST[ self::META_FIELD ] ) ) );
		$loaded = isset( $_POST[ self::META_PREVIOUS ] )
			? WorkspaceRegistrationStatus::normalize( sanitize_key( wp_unslash( $_POST[ self::META_PREVIOUS ] ) ) )
			: '';

		/*
		 * If the dropdown was not changed, do not write.
		 * Prevents Publish/Update from clobbering a row/AJAX action
		 * with a stale value still sitting in the form.
		 */
		if ( '' !== $loaded && $posted === $loaded ) {
			return;
		}

		$this->apply_status( (int) $post_id, $posted );
	}

	/**
	 * @return void
	 */
	public function ajax_set_status(): void {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'havenlytics' ) ), 403 );
		}

		$agent_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? WorkspaceRegistrationStatus::normalize( sanitize_key( wp_unslash( $_POST['status'] ) ) ) : '';

		if ( $agent_id <= 0 || ! in_array( $status, WorkspaceRegistrationStatus::all(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'havenlytics' ) ), 400 );
		}

		if ( ! $this->can_manage_agent( $agent_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to manage Workspace registration.', 'havenlytics' ) ), 403 );
		}

		if ( ! $this->apply_status( $agent_id, $status ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Could not update lifecycle status. Check the agent is linked and the transition is allowed.', 'havenlytics' ),
				),
				400
			);
		}

		$user_id = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		$status  = WorkspaceRegistrationStatus::get_for_user( $user_id );

		wp_send_json_success(
			array(
				'agentId'        => $agent_id,
				'status'         => $status,
				'statusLabel'    => WorkspaceRegistrationStatus::label( $status ),
				'badgeHtml'      => WorkspaceRegistrationStatus::badge_html( $status ),
				'workspaceLabel' => WorkspaceRegistrationStatus::workspace_state_label( $user_id ),
				'rowActionsHtml' => $this->render_row_actions_fragment( $agent_id, $status ),
				'message'        => sprintf(
					/* translators: %s: status label */
					__( 'Lifecycle status updated to: %s', 'havenlytics' ),
					WorkspaceRegistrationStatus::label( $status )
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function handle_admin_post_approve(): void {
		$this->handle_admin_post( WorkspaceRegistrationStatus::APPROVED );
	}

	/**
	 * @return void
	 */
	public function handle_admin_post_activate(): void {
		$this->handle_admin_post( WorkspaceRegistrationStatus::ACTIVE );
	}

	/**
	 * @return void
	 */
	public function handle_admin_post_suspend(): void {
		$this->handle_admin_post( WorkspaceRegistrationStatus::SUSPENDED );
	}

	/**
	 * @return void
	 */
	public function handle_admin_post_block(): void {
		$this->handle_admin_post( WorkspaceRegistrationStatus::BLOCKED );
	}

	/**
	 * @return void
	 */
	public function handle_admin_post_archive(): void {
		$this->handle_admin_post( WorkspaceRegistrationStatus::ARCHIVED );
	}

	/**
	 * @return void
	 */
	public function handle_admin_post_restore(): void {
		$this->handle_admin_post( WorkspaceRegistrationStatus::ACTIVE );
	}

	/**
	 * @param string $status Target status.
	 * @return void
	 */
	private function handle_admin_post( string $status ): void {
		$agent_id = isset( $_GET['agent_id'] ) ? absint( $_GET['agent_id'] ) : 0;
		$nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( $agent_id <= 0 || ! wp_verify_nonce( $nonce, self::NONCE_ACTION . '_' . $agent_id ) ) {
			wp_die( esc_html__( 'Invalid registration action.', 'havenlytics' ) );
		}
		if ( ! $this->can_manage_agent( $agent_id ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Workspace registration.', 'havenlytics' ) );
		}

		$this->apply_status( $agent_id, $status );

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=' . AgentConstants::POST_TYPE );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'hvnly_ws_reg' => $status,
				),
				$redirect
			)
		);
		exit;
	}

	/**
	 * Apply lifecycle status to linked user. Never touches post_status.
	 *
	 * @param int    $agent_id Agent ID.
	 * @param string $status   Status.
	 * @return bool
	 */
	private function apply_status( int $agent_id, string $status ): bool {
		$user_id = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $user_id <= 0 ) {
			return false;
		}

		$status   = WorkspaceRegistrationStatus::normalize( $status );
		$current  = WorkspaceRegistrationStatus::get_for_user( $user_id );
		$is_admin = current_user_can( 'manage_options' );

		if ( ! WorkspaceRegistrationStatus::can_transition( $current, $status, $is_admin ) ) {
			return false;
		}

		WorkspaceRegistrationStatus::set_for_user( $user_id, $status, $agent_id );
		return true;
	}

	/**
	 * @param string $action_key approve|activate|suspend|block|archive|restore.
	 * @param int    $agent_id   Agent ID.
	 * @return string
	 */
	private function fallback_url( string $action_key, int $agent_id ): string {
		$map = array(
			'approve'  => 'hvnly_ws_reg_approve',
			'activate' => 'hvnly_ws_reg_activate',
			'suspend'  => 'hvnly_ws_reg_suspend',
			'block'    => 'hvnly_ws_reg_block',
			'archive'  => 'hvnly_ws_reg_archive',
			'restore'  => 'hvnly_ws_reg_restore',
			'reject'   => 'hvnly_ws_reg_block',
		);
		$action = isset( $map[ $action_key ] ) ? $map[ $action_key ] : 'hvnly_ws_reg_approve';

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => $action,
					'agent_id' => $agent_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION . '_' . $agent_id
		);
	}

	/**
	 * @param int    $agent_id Agent ID.
	 * @param string $status   Status.
	 * @return string
	 */
	private function render_row_actions_fragment( int $agent_id, string $status ): string {
		$html = '';
		foreach ( $this->available_actions_for_status( $status ) as $key => $item ) {
			$html .= sprintf(
				'<span class="hvnly_ws_%1$s"><a href="%2$s" class="hvnly-ws-reg-action" data-agent-id="%3$d" data-status="%4$s">%5$s</a> | </span>',
				esc_attr( $key ),
				esc_url( $this->fallback_url( $key, $agent_id ) ),
				$agent_id,
				esc_attr( $item['status'] ),
				esc_html( $item['label'] )
			);
		}
		return $html;
	}

	/**
	 * @return void
	 */
	public function admin_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || AgentConstants::POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( ! empty( $_GET['hvnly_ws_reg_bulk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$status = WorkspaceRegistrationStatus::normalize( sanitize_key( wp_unslash( $_GET['hvnly_ws_reg_bulk'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count  = isset( $_GET['hvnly_ws_reg_n'] ) ? absint( $_GET['hvnly_ws_reg_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: count, 2: status label */
						_n(
							'%1$d agent updated to %2$s.',
							'%1$d agents updated to %2$s.',
							$count,
							'havenlytics'
						),
						$count,
						WorkspaceRegistrationStatus::label( $status )
					)
				)
			);
			return;
		}

		if ( empty( $_GET['hvnly_ws_reg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$status = WorkspaceRegistrationStatus::normalize( sanitize_key( wp_unslash( $_GET['hvnly_ws_reg'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: status label */
					__( 'Workspace lifecycle status updated to: %s', 'havenlytics' ),
					WorkspaceRegistrationStatus::label( $status )
				)
			)
		);
	}
}
