<?php
/**
 * Admin list integrations: Users screen identity columns.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight admin column enhancements (no screen redesign).
 *
 * @since 3.2.0
 */
final class IdentityAdminColumns {

	/**
	 * @return void
	 */
	public function register(): void {
		// Agent CPT columns owned by AgentManagementAdminList (Sprint 20A).
		add_filter( 'manage_users_columns', array( $this, 'users_columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'users_column_content' ), 10, 3 );
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function agent_columns( array $columns ): array {
		$columns['hvnly_ws_linked_user'] = __( 'Linked WordPress User', 'havenlytics' );
		$columns['hvnly_ws_wp_role']     = __( 'WordPress Role', 'havenlytics' );
		$columns['hvnly_ws_reg_status']  = __( 'Registration Status', 'havenlytics' );
		$columns['hvnly_ws_workspace']   = __( 'Workspace Status', 'havenlytics' );
		return $columns;
	}

	/**
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function agent_column_content( string $column, int $post_id ): void {
		$user_id = absint( get_post_meta( $post_id, AgentConstants::META_LINKED_USER_ID, true ) );

		switch ( $column ) {
			case 'hvnly_ws_linked_user':
				if ( $user_id <= 0 ) {
					echo esc_html__( 'Not Linked', 'havenlytics' );
					return;
				}
				$user = get_userdata( $user_id );
				if ( ! $user ) {
					echo esc_html__( 'Not Linked', 'havenlytics' );
					return;
				}
				$url = get_edit_user_link( $user_id );
				printf(
					'<a href="%s">%s</a> <span class="description">(ID %d)</span>',
					esc_url( $url ? $url : '#' ),
					esc_html( $user->user_login ),
					(int) $user_id
				);
				break;

			case 'hvnly_ws_wp_role':
				if ( $user_id <= 0 ) {
					echo '&mdash;';
					return;
				}
				$user = get_userdata( $user_id );
				if ( ! $user || empty( $user->roles ) ) {
					echo '&mdash;';
					return;
				}
				$names = array_map(
					static function ( $role_slug ) {
						$wp_roles = wp_roles();
						return isset( $wp_roles->role_names[ $role_slug ] )
							? translate_user_role( $wp_roles->role_names[ $role_slug ] )
							: $role_slug;
					},
					(array) $user->roles
				);
				echo esc_html( implode( ', ', $names ) );
				break;

			case 'hvnly_ws_reg_status':
				$status = WorkspaceRegistrationStatus::get_for_agent( $post_id );
				if ( null === $status ) {
					echo '&mdash;';
					return;
				}
				echo wp_kses_post( WorkspaceRegistrationStatus::badge_html( $status ) );
				break;

			case 'hvnly_ws_workspace':
				echo esc_html( WorkspaceRegistrationStatus::workspace_state_label( $user_id > 0 ? $user_id : null ) );
				break;
		}
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function users_columns( array $columns ): array {
		$columns['hvnly_ws_linked_agent'] = __( 'Linked Agent', 'havenlytics' );
		$columns['hvnly_ws_reg_status']   = __( 'Registration Status', 'havenlytics' );
		$columns['hvnly_ws_last_login']   = __( 'Last Login', 'havenlytics' );
		return $columns;
	}

	/**
	 * @param string $output      Output.
	 * @param string $column_name Column.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public function users_column_content( $output, $column_name, $user_id ) {
		$user_id = absint( $user_id );

		if ( 'hvnly_ws_linked_agent' === $column_name ) {
			return $this->render_linked_agent_cell( $user_id );
		}

		if ( 'hvnly_ws_reg_status' === $column_name || 'hvnly_ws_workspace' === $column_name ) {
			if ( ! $this->user_has_agent_role( $user_id ) && WorkspaceRegistrationStatus::find_linked_agent_id( $user_id ) <= 0 ) {
				return '&mdash;';
			}
			$status = WorkspaceRegistrationStatus::get_for_user( $user_id );
			return wp_kses_post( WorkspaceRegistrationStatus::badge_html( (string) $status ) );
		}

		if ( 'hvnly_ws_last_login' === $column_name ) {
			if ( ! $this->user_has_agent_role( $user_id ) && WorkspaceRegistrationStatus::find_linked_agent_id( $user_id ) <= 0 ) {
				return '&mdash;';
			}
			$gmt = (string) get_user_meta( $user_id, AgentActivityTracker::META_LAST_LOGIN, true );
			$rel = AgentActivityTracker::format_admin( $gmt );
			if ( '—' === $rel || '' === $gmt ) {
				return '<span class="description">' . esc_html__( 'Never', 'havenlytics' ) . '</span>';
			}
			return esc_html( $rel );
		}

		return $output;
	}

	/**
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function render_linked_agent_cell( int $user_id ): string {
		$agent_id = WorkspaceRegistrationStatus::find_linked_agent_id( $user_id );
		$is_agent = $this->user_has_agent_role( $user_id );

		if ( $agent_id > 0 ) {
			$edit  = get_edit_post_link( $agent_id, 'raw' );
			$title = get_the_title( $agent_id );
			$label = $title ? $title : sprintf(
				/* translators: %d agent id */
				__( 'Agent #%d', 'havenlytics' ),
				$agent_id
			);
			if ( ! $edit ) {
				return esc_html( $label );
			}
			return sprintf(
				'<a href="%s">%s</a> <span class="description">%s</span>',
				esc_url( $edit ),
				esc_html( $label ),
				esc_html__( 'Open Agent', 'havenlytics' )
			);
		}

		if ( ! $is_agent ) {
			return esc_html__( 'Not Linked', 'havenlytics' );
		}

		if ( ! current_user_can( 'create_users' ) || ! current_user_can( 'edit_posts' ) ) {
			return esc_html__( 'Missing Agent', 'havenlytics' );
		}

		return sprintf(
			'<span class="hvnly-ws-missing-agent">%s</span> &mdash; <a class="button button-small" href="%s">%s</a>',
			esc_html__( 'Missing Agent', 'havenlytics' ),
			esc_url( AgentIdentityAdminBridge::create_agent_url( $user_id ) ),
			esc_html__( 'Create Agent', 'havenlytics' )
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function user_has_agent_role( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		return in_array( PortalCapabilities::WP_ROLE_AGENT, (array) $user->roles, true );
	}
}
