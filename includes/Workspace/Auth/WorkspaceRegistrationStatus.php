<?php
/**
 * Agent lifecycle / Workspace registration status (SSOT).
 *
 * Single source of truth: user meta {@see self::USER_META}
 * (mirrored to Agent post meta for list/metabox convenience).
 * Never driven by WordPress post_status.
 *
 * Sprint 19E lifecycle:
 * Pending → Approved → Active ⇄ Suspended|Blocked → Active
 * Archived → Active (administrator restore only)
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * Agent lifecycle status — independent of Agent CPT publish.
 *
 * @since 3.2.0
 */
final class WorkspaceRegistrationStatus {

	public const USER_META = '_hvnly_ws_registration_status';

	public const AGENT_META = '_hvnly_ws_registration_status';

	public const PENDING   = 'pending';
	public const APPROVED  = 'approved';
	public const ACTIVE    = 'active';
	public const SUSPENDED = 'suspended';
	public const BLOCKED   = 'blocked';
	public const ARCHIVED  = 'archived';

	/**
	 * Legacy deny status — normalized to {@see self::BLOCKED}.
	 *
	 * @var string
	 */
	public const REJECTED = 'rejected';

	/**
	 * Canonical lifecycle values (persisted).
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::PENDING,
			self::APPROVED,
			self::ACTIVE,
			self::SUSPENDED,
			self::BLOCKED,
			self::ARCHIVED,
		);
	}

	/**
	 * Normalize raw meta (including legacy aliases).
	 *
	 * @param string $raw Raw status.
	 * @return string
	 */
	public static function normalize( string $raw ): string {
		$raw = strtolower( trim( $raw ) );
		$map = array(
			'pending'          => self::PENDING,
			'pending_approval' => self::PENDING,
			'approved'         => self::APPROVED,
			'active'           => self::ACTIVE,
			''                 => self::ACTIVE,
			'rejected'         => self::BLOCKED,
			'blocked'          => self::BLOCKED,
			'suspended'        => self::SUSPENDED,
			'archived'         => self::ARCHIVED,
			'disabled'         => self::BLOCKED,
		);

		return isset( $map[ $raw ] ) ? $map[ $raw ] : self::PENDING;
	}

	/**
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_for_user( int $user_id ): string {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return self::PENDING;
		}

		$raw = (string) get_user_meta( $user_id, self::USER_META, true );
		// Legacy: missing meta meant open/active after registration.
		if ( '' === $raw ) {
			return self::ACTIVE;
		}

		return self::normalize( $raw );
	}

	/**
	 * Resolve status from linked user (SSOT). Agent meta is a write mirror only.
	 *
	 * @param int $agent_id Agent post ID.
	 * @return string|null Null when no linked user (Not Linked).
	 */
	public static function get_for_agent( int $agent_id ): ?string {
		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 ) {
			return null;
		}

		$user_id = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $user_id <= 0 ) {
			return null;
		}

		return self::get_for_user( $user_id );
	}

	/**
	 * @param int    $user_id  User ID.
	 * @param string $status   Status slug.
	 * @param int    $agent_id Optional Agent post ID to mirror.
	 * @return void
	 */
	public static function set_for_user( int $user_id, string $status, int $agent_id = 0 ): void {
		$user_id  = absint( $user_id );
		$status   = self::normalize( $status );
		$previous = self::PENDING;

		if ( $user_id <= 0 ) {
			return;
		}

		$raw_previous = (string) get_user_meta( $user_id, self::USER_META, true );
		// Empty meta still normalizes to Active for comparisons, but listeners that
		// need to detect first-time registration also receive the raw value.
		$previous = ( '' === $raw_previous ) ? self::ACTIVE : self::normalize( $raw_previous );

		// Persist even when normalizing empty → active so agent meta stays in sync.
		$unchanged = ( $previous === $status && '' !== $raw_previous );

		if ( ! $unchanged ) {
			update_user_meta( $user_id, self::USER_META, $status );
		}

		if ( $agent_id <= 0 ) {
			$agent_id = self::find_linked_agent_id( $user_id );
		}
		if ( $agent_id > 0 ) {
			update_post_meta( $agent_id, self::AGENT_META, $status );
		}

		if ( $unchanged ) {
			return;
		}

		AgentIdentityService::get_instance()->clear_cache( $user_id );

		/**
		 * Fires when Workspace registration / lifecycle status changes.
		 *
		 * @since 3.2.0
		 *
		 * @param int    $user_id       User ID.
		 * @param string $status        New normalized status.
		 * @param int    $agent_id      Agent post ID (0 if unknown).
		 * @param string $previous      Previous normalized status.
		 * @param string $raw_previous  Raw previous meta before normalization ('' for first write).
		 */
		do_action( 'hvnly_workspace_registration_status_changed', $user_id, $status, $agent_id, $previous, $raw_previous );
	}

	/**
	 * Allowed transitions: from => to[].
	 *
	 * @return array<string, string[]>
	 */
	public static function allowed_transitions(): array {
		return array(
			self::PENDING   => array( self::APPROVED, self::BLOCKED, self::SUSPENDED ),
			self::APPROVED  => array( self::ACTIVE, self::SUSPENDED, self::BLOCKED, self::ARCHIVED ),
			self::ACTIVE    => array( self::SUSPENDED, self::BLOCKED, self::ARCHIVED ),
			self::SUSPENDED => array( self::ACTIVE, self::BLOCKED, self::ARCHIVED ),
			self::BLOCKED   => array( self::ACTIVE, self::ARCHIVED ),
			self::ARCHIVED  => array( self::ACTIVE ),
		);
	}

	/**
	 * @param string $from            Current status.
	 * @param string $to              Target status.
	 * @param bool   $is_administrator Whether actor is WP administrator (manage_options).
	 * @return bool
	 */
	public static function can_transition( string $from, string $to, bool $is_administrator = false ): bool {
		$from = self::normalize( $from );
		$to   = self::normalize( $to );

		if ( $from === $to ) {
			return true;
		}

		if ( ! in_array( $to, self::all(), true ) ) {
			return false;
		}

		$map = self::allowed_transitions();
		$ok  = isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );

		// Archived restore is administrator-only.
		if ( self::ARCHIVED === $from && self::ACTIVE === $to ) {
			return $is_administrator;
		}

		if ( $ok ) {
			return true;
		}

		// Administrators may force any lifecycle value from the metabox.
		return $is_administrator;
	}

	/**
	 * Workspace SPA access (Approved + Active).
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function allows_workspace_access( string $status ): bool {
		$status = self::normalize( $status );
		return in_array( $status, array( self::APPROVED, self::ACTIVE ), true );
	}

	/**
	 * Whether login may establish a WP session for Workspace auth UX.
	 * Suspended keeps the session so the SPA can explain denial.
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function allows_login( string $status ): bool {
		$status = self::normalize( $status );
		return in_array(
			$status,
			array( self::APPROVED, self::ACTIVE, self::SUSPENDED ),
			true
		);
	}

	/**
	 * Create / edit / media mutations in Workspace.
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function allows_property_mutations( string $status ): bool {
		return self::allows_workspace_access( $status );
	}

	/**
	 * Human label for admin UI.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function label( string $status ): string {
		switch ( self::normalize( $status ) ) {
			case self::PENDING:
				return __( 'Pending Activation', 'havenlytics' );
			case self::APPROVED:
				return __( 'Approved', 'havenlytics' );
			case self::ACTIVE:
				return __( 'Active', 'havenlytics' );
			case self::SUSPENDED:
				return __( 'Suspended', 'havenlytics' );
			case self::BLOCKED:
				return __( 'Blocked', 'havenlytics' );
			case self::ARCHIVED:
				return __( 'Archived', 'havenlytics' );
			default:
				return __( 'Pending Activation', 'havenlytics' );
		}
	}

	/**
	 * Login / status-page message for Workspace auth.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function login_message( string $status ): string {
		switch ( self::normalize( $status ) ) {
			case self::PENDING:
				return __( 'Your account is awaiting approval.', 'havenlytics' );
			case self::SUSPENDED:
				return __( 'Your account has been suspended.', 'havenlytics' );
			case self::BLOCKED:
				return __( 'Your account has been blocked.', 'havenlytics' );
			case self::ARCHIVED:
				return __( 'This account has been archived and cannot access Workspace.', 'havenlytics' );
			default:
				return __( 'Your Workspace access is not active. Please contact the site administrator.', 'havenlytics' );
		}
	}

	/**
	 * Workspace Status column value (not registration status).
	 *
	 * @param int|null $user_id Linked user ID (0/null = Not Linked).
	 * @return string
	 */
	public static function workspace_state_label( ?int $user_id ): string {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return __( 'Not Linked', 'havenlytics' );
		}

		$status = self::get_for_user( $user_id );
		switch ( $status ) {
			case self::APPROVED:
			case self::ACTIVE:
				return __( 'Ready', 'havenlytics' );
			case self::PENDING:
				return __( 'Waiting Approval', 'havenlytics' );
			case self::SUSPENDED:
				return __( 'Suspended', 'havenlytics' );
			case self::BLOCKED:
				return __( 'Blocked', 'havenlytics' );
			case self::ARCHIVED:
				return __( 'Archived', 'havenlytics' );
			default:
				return __( 'Disabled', 'havenlytics' );
		}
	}

	/**
	 * HTML badge for Registration Status column.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function badge_html( string $status ): string {
		$status = self::normalize( $status );
		$class  = 'hvnly-ws-reg-badge hvnly-ws-reg-badge--' . $status;
		$icons  = array(
			self::PENDING   => '🟡',
			self::APPROVED  => '🟢',
			self::ACTIVE    => '🟢',
			self::SUSPENDED => '🟠',
			self::BLOCKED   => '🔴',
			self::ARCHIVED  => '⚪',
		);
		$icon = isset( $icons[ $status ] ) ? $icons[ $status ] : '';

		return sprintf(
			'<span class="%s"><span class="hvnly-ws-reg-badge__icon" aria-hidden="true">%s</span><span class="hvnly-ws-reg-badge__label">%s</span></span>',
			esc_attr( $class ),
			esc_html( $icon ),
			esc_html( self::label( $status ) )
		);
	}

	/**
	 * Confirmation copy for admin lifecycle actions.
	 *
	 * @param string $status Target status.
	 * @return string Empty when no confirmation needed.
	 */
	public static function confirm_message( string $status ): string {
		switch ( self::normalize( $status ) ) {
			case self::SUSPENDED:
				return __( 'Suspend this agent? They can sign in but cannot use Workspace until restored.', 'havenlytics' );
			case self::BLOCKED:
				return __( 'Block this agent? Login and Workspace access will be denied.', 'havenlytics' );
			case self::ARCHIVED:
				return __( 'Archive this agent? Workspace access ends. Data is kept; only an administrator can restore.', 'havenlytics' );
			case self::ACTIVE:
				return __( 'Restore this agent to Active and re-enable Workspace access?', 'havenlytics' );
			default:
				return '';
		}
	}

	/**
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function find_linked_agent_id( int $user_id ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => AgentConstants::META_LINKED_USER_ID,
						'value' => $user_id,
					),
				),
			)
		);

		return empty( $query->posts[0] ) ? 0 : absint( $query->posts[0] );
	}
}
