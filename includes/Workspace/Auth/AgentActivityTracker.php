<?php
/**
 * Tracks Agent Workspace last login / activity (user meta SSOT).
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Workspace\WorkspaceDateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Does not invent a login system — stamps meta from existing auth hooks.
 *
 * @since 3.2.0
 */
final class AgentActivityTracker {

	public const META_LAST_LOGIN             = '_hvnly_ws_last_login';
	public const META_LAST_ACTIVITY          = '_hvnly_ws_last_activity';
	public const META_LAST_WORKSPACE_ACCESS  = '_hvnly_ws_last_workspace_access';

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_login', array( $this, 'on_wp_login' ), 20, 2 );
		add_action( 'hvnly_workspace_agent_activity', array( $this, 'on_workspace_activity' ), 10, 2 );
	}

	/**
	 * Core WordPress login (admin + other entry points).
	 *
	 * @param string   $user_login Login.
	 * @param \WP_User $user       User.
	 * @return void
	 */
	public function on_wp_login( $user_login, $user ): void {
		unset( $user_login );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$this->stamp_login( (int) $user->ID );
	}

	/**
	 * Workspace SPA / REST activity (optional explicit stamp).
	 *
	 * @param int    $user_id User ID.
	 * @param string $kind    login|activity|workspace.
	 * @return void
	 */
	public function on_workspace_activity( int $user_id, string $kind = 'activity' ): void {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}
		switch ( $kind ) {
			case 'login':
				$this->stamp_login( $user_id );
				break;
			case 'workspace':
				$this->stamp_workspace_access( $user_id );
				break;
			default:
				$this->stamp_activity( $user_id );
		}
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function stamp_login( int $user_id ): void {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		update_user_meta( $user_id, self::META_LAST_LOGIN, $now );
		update_user_meta( $user_id, self::META_LAST_ACTIVITY, $now );
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function stamp_activity( int $user_id ): void {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! $this->should_refresh( $user_id, self::META_LAST_ACTIVITY ) ) {
			return;
		}
		update_user_meta( $user_id, self::META_LAST_ACTIVITY, gmdate( 'Y-m-d H:i:s' ) );
	}

	/**
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function stamp_workspace_access( int $user_id ): void {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}
		// Avoid writing usermeta on every GET /me (15-minute throttle).
		if ( ! $this->should_refresh( $user_id, self::META_LAST_WORKSPACE_ACCESS ) ) {
			return;
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		update_user_meta( $user_id, self::META_LAST_WORKSPACE_ACCESS, $now );
		update_user_meta( $user_id, self::META_LAST_ACTIVITY, $now );
	}

	/**
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Meta key to throttle.
	 * @return bool
	 */
	private function should_refresh( int $user_id, string $meta_key ): bool {
		$prev = (string) get_user_meta( $user_id, $meta_key, true );
		if ( '' === $prev ) {
			return true;
		}
		$ts = WorkspaceDateTime::parse_gmt_mysql( $prev );
		if ( $ts <= 0 ) {
			return true;
		}
		return ( WorkspaceDateTime::now() - $ts ) >= ( 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * @param int $user_id User ID.
	 * @return array{login:string,activity:string,workspace:string}
	 */
	public static function get_for_user( int $user_id ): array {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array(
				'login'     => '',
				'activity'  => '',
				'workspace' => '',
			);
		}
		return array(
			'login'     => (string) get_user_meta( $user_id, self::META_LAST_LOGIN, true ),
			'activity'  => (string) get_user_meta( $user_id, self::META_LAST_ACTIVITY, true ),
			'workspace' => (string) get_user_meta( $user_id, self::META_LAST_WORKSPACE_ACCESS, true ),
		);
	}

	/**
	 * Human-readable relative/admin date.
	 *
	 * @param string $gmt GMT datetime Y-m-d H:i:s.
	 * @return string
	 */
	public static function format_admin( string $gmt ): string {
		if ( '' === $gmt ) {
			return '—';
		}
		$rel = WorkspaceDateTime::relative_ago( WorkspaceDateTime::parse_gmt_mysql( $gmt ) );
		return '' !== $rel ? $rel : '—';
	}
}
