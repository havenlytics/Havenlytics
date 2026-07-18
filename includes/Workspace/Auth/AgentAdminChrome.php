<?php
/**
 * Hide WordPress admin chrome for Workspace Agent users.
 *
 * Centralized redirect policy: Agents never use the standard wp-admin UI.
 * Administrators and other staff roles keep the normal WP experience.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Workspace\WorkspaceSettings;

defined( 'ABSPATH' ) || exit;

/**
 * Agent-facing UX: no admin bar / wp-admin distraction.
 *
 * Single choke-point for Agent → Workspace redirects (`admin_init` + `login_redirect`).
 *
 * @since 3.2.0
 */
final class AgentAdminChrome {

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_setup_theme', array( $this, 'maybe_hide_admin_bar' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_wp_admin' ), 1 );
		add_filter( 'show_admin_bar', array( $this, 'filter_show_admin_bar' ) );
		add_filter( 'login_redirect', array( $this, 'filter_login_redirect' ), 20, 3 );
	}

	/**
	 * Whether the current user is a Workspace Agent who must stay out of wp-admin UI.
	 *
	 * @return bool
	 */
	private function is_workspace_agent(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Staff / administrators always keep wp-admin.
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user || ! ( $user instanceof \WP_User ) || $user->ID <= 0 ) {
			return false;
		}

		$roles = is_array( $user->roles ) ? $user->roles : array();
		if ( in_array( PortalCapabilities::WP_ROLE_AGENT, $roles, true ) ) {
			return true;
		}

		// Capability-based fallback: portal agents without a clean role list still redirect.
		if ( user_can( $user, PortalCapabilities::PORTAL_ACCESS ) && ! user_can( $user, 'edit_posts' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether this request must be allowed through wp-admin without redirect.
	 *
	 * @return bool
	 */
	private function is_exempt_admin_request(): bool {
		if ( wp_doing_ajax() ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		$script = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		if ( in_array( $script, array( 'admin-ajax.php', 'async-upload.php', 'admin-post.php' ), true ) ) {
			return true;
		}

		// Heartbeat / media / customizer endpoints that must remain on admin-ajax are covered above.
		// Allow direct file upload screens used by media modal.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$uri = (string) wp_unslash( $_SERVER['REQUEST_URI'] );
			if ( false !== strpos( $uri, 'async-upload.php' ) || false !== strpos( $uri, 'admin-ajax.php' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Absolute Workspace landing URL for Agents.
	 *
	 * @return string
	 */
	private function workspace_home_url(): string {
		$url = WorkspaceSettings::get_base_url();
		if ( ! is_string( $url ) || '' === $url ) {
			$url = WorkspaceSettings::route_url( 'dashboard' );
		}

		/**
		 * Filter where Agents are sent when blocked from wp-admin.
		 *
		 * @since 3.3.0
		 *
		 * @param string $url Destination URL.
		 */
		return (string) apply_filters( 'hvnly_workspace_agent_admin_redirect', $url );
	}

	/**
	 * @return void
	 */
	public function maybe_hide_admin_bar(): void {
		if ( $this->is_workspace_agent() ) {
			show_admin_bar( false );
		}
	}

	/**
	 * @param bool $show Show.
	 * @return bool
	 */
	public function filter_show_admin_bar( $show ) {
		if ( $this->is_workspace_agent() ) {
			return false;
		}
		return $show;
	}

	/**
	 * After wp-login, send Agents to the Workspace instead of /wp-admin/.
	 *
	 * @param string             $redirect_to           Requested redirect.
	 * @param string             $requested_redirect_to Raw requested redirect.
	 * @param \WP_User|\WP_Error $user                  Authenticated user or error.
	 * @return string
	 */
	public function filter_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
		unset( $requested_redirect_to );

		if ( ! ( $user instanceof \WP_User ) || $user->ID <= 0 ) {
			return $redirect_to;
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return $redirect_to;
		}
		if ( ! WorkspaceSettings::is_enabled() ) {
			return $redirect_to;
		}

		$roles = is_array( $user->roles ) ? $user->roles : array();
		$is_agent = in_array( PortalCapabilities::WP_ROLE_AGENT, $roles, true )
			|| ( user_can( $user, PortalCapabilities::PORTAL_ACCESS ) && ! user_can( $user, 'edit_posts' ) );

		if ( ! $is_agent ) {
			return $redirect_to;
		}

		return $this->workspace_home_url();
	}

	/**
	 * Redirect Agents away from wp-admin into Workspace (except AJAX / REST / cron / uploads).
	 *
	 * @return void
	 */
	public function maybe_redirect_wp_admin(): void {
		if ( ! $this->is_workspace_agent() ) {
			return;
		}
		if ( $this->is_exempt_admin_request() ) {
			return;
		}
		if ( ! WorkspaceSettings::is_enabled() ) {
			return;
		}

		$destination = $this->workspace_home_url();
		if ( '' === $destination ) {
			return;
		}

		wp_safe_redirect( $destination );
		exit;
	}
}
