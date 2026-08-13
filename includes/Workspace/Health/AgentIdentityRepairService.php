<?php
/**
 * Agent Identity Repair Engine — single service for all identity repairs.
 *
 * Deterministic only. Ambiguous cases return MANUAL ACTION REQUIRED.
 * Never deletes Users/CPTs, never invents links, never overwrites emails,
 * never changes property/inquiry ownership automatically.
 *
 * @package HvnlyNab\Workspace\Health
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Health;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\AgentProvisioner;
use HvnlyNab\Workspace\Auth\CapabilityRegistrar;
use HvnlyNab\Workspace\Auth\PortalCapabilities;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
final class AgentIdentityRepairService {

	public const TYPE_BROKEN_LINK            = 'repair_broken_link';
	public const TYPE_MISSING_AGENT          = 'repair_missing_agent';
	public const TYPE_MISSING_USER           = 'repair_missing_user';
	public const TYPE_REGISTRATION_STATUS    = 'repair_registration_status';
	public const TYPE_CAPABILITY_ASSIGNMENT  = 'repair_capability_assignment';
	public const TYPE_ROLE                   = 'repair_role';
	public const TYPE_PROPERTY_ASSIGNMENT    = 'repair_property_assignment';
	public const TYPE_NOTIFICATION_OWNERSHIP = 'repair_notification_ownership';
	public const TYPE_INQUIRY_OWNERSHIP      = 'repair_inquiry_ownership';
	public const TYPE_DUPLICATE_EMAIL        = 'repair_duplicate_email';
	public const TYPE_DUPLICATE_IDENTITY     = 'repair_duplicate_identity';
	public const TYPE_LEGACY_REFERENCES      = 'repair_legacy_references';

	public const IGNORE_OPTION = 'hvnly_agent_identity_ignored';

	/**
	 * @var AgentProvisioner
	 */
	private $provisioner;

	/**
	 * @var CapabilityRegistrar
	 */
	private $capabilities;

	/**
	 * @param AgentProvisioner|null     $provisioner Provisioner.
	 * @param CapabilityRegistrar|null  $capabilities Caps registrar.
	 */
	public function __construct(
		?AgentProvisioner $provisioner = null,
		?CapabilityRegistrar $capabilities = null
	) {
		$this->provisioner  = $provisioner ? $provisioner : new AgentProvisioner();
		$this->capabilities = $capabilities ? $capabilities : new CapabilityRegistrar();
	}

	/**
	 * Map scanner rule → repair type (for UI).
	 *
	 * @param string $rule_id Scanner rule id.
	 * @return string|null
	 */
	public static function repair_type_for_rule( string $rule_id ): ?string {
		$map = array(
			AgentIdentityIntegrityScanner::RULE_BROKEN_LINKED_ID          => self::TYPE_BROKEN_LINK,
			AgentIdentityIntegrityScanner::RULE_USER_MISSING_AGENT        => self::TYPE_MISSING_AGENT,
			AgentIdentityIntegrityScanner::RULE_AGENT_MISSING_USER        => self::TYPE_MISSING_USER,
			AgentIdentityIntegrityScanner::RULE_REG_STATUS_MISMATCH       => self::TYPE_REGISTRATION_STATUS,
			AgentIdentityIntegrityScanner::RULE_ROLE_MISMATCH             => self::TYPE_ROLE,
			AgentIdentityIntegrityScanner::RULE_PROPERTY_ASSIGNED_GONE    => self::TYPE_PROPERTY_ASSIGNMENT,
			AgentIdentityIntegrityScanner::RULE_PROPERTY_AUTHOR_GONE      => self::TYPE_PROPERTY_ASSIGNMENT,
			AgentIdentityIntegrityScanner::RULE_DASHBOARD_OWNERSHIP_SKEW  => self::TYPE_PROPERTY_ASSIGNMENT,
			AgentIdentityIntegrityScanner::RULE_NOTIFICATION_INVALID_USER => self::TYPE_NOTIFICATION_OWNERSHIP,
			AgentIdentityIntegrityScanner::RULE_INQUIRY_INVALID_AGENT     => self::TYPE_INQUIRY_OWNERSHIP,
			AgentIdentityIntegrityScanner::RULE_DUPLICATE_EMAIL           => self::TYPE_DUPLICATE_EMAIL,
			AgentIdentityIntegrityScanner::RULE_MULTI_AGENT_ONE_USER      => self::TYPE_DUPLICATE_IDENTITY,
			AgentIdentityIntegrityScanner::RULE_AGENT_MULTI_USER_META     => self::TYPE_DUPLICATE_IDENTITY,
			AgentIdentityIntegrityScanner::RULE_LEGACY_PROPERTY_AGENT     => self::TYPE_LEGACY_REFERENCES,
			AgentIdentityIntegrityScanner::RULE_ORPHAN_DATA               => self::TYPE_LEGACY_REFERENCES,
			AgentIdentityIntegrityScanner::RULE_TAXONOMY_CONFLICT         => self::TYPE_LEGACY_REFERENCES,
		);
		return isset( $map[ $rule_id ] ) ? $map[ $rule_id ] : null;
	}

	/**
	 * Whether the UI may show an automated Repair button for this issue.
	 *
	 * @param AgentIdentityIssue|array<string, mixed> $issue Issue.
	 * @return bool
	 */
	public static function is_auto_repairable( $issue ): bool {
		$row  = $issue instanceof AgentIdentityIssue ? $issue->to_array() : (array) $issue;
		$type = self::repair_type_for_rule( (string) ( $row['rule_id'] ?? '' ) );
		if ( null === $type ) {
			return false;
		}
		// Only these types have deterministic automated paths today.
		return in_array(
			$type,
			array(
				self::TYPE_MISSING_AGENT,
				self::TYPE_REGISTRATION_STATUS,
				self::TYPE_ROLE,
			),
			true
		);
	}

	/**
	 * Stable ignore key for an issue.
	 *
	 * @param array<string, mixed> $issue Issue row.
	 * @return string
	 */
	public static function issue_key( array $issue ): string {
		return implode(
			'|',
			array(
				(string) ( $issue['rule_id'] ?? '' ),
				(string) ( $issue['object'] ?? '' ),
				(string) absint( $issue['user_id'] ?? 0 ),
				(string) absint( $issue['agent_id'] ?? 0 ),
				(string) absint( $issue['property_id'] ?? 0 ),
				(string) absint( $issue['inquiry_id'] ?? 0 ),
				(string) absint( $issue['notification_id'] ?? 0 ),
			)
		);
	}

	/**
	 * @param string $key Issue key.
	 * @return void
	 */
	public function ignore_issue( string $key ): void {
		$key = sanitize_text_field( $key );
		if ( '' === $key ) {
			return;
		}
		$ignored = get_option( self::IGNORE_OPTION, array() );
		if ( ! is_array( $ignored ) ) {
			$ignored = array();
		}
		$ignored[ $key ] = array(
			'at'       => gmdate( 'c' ),
			'admin_id' => get_current_user_id(),
		);
		update_option( self::IGNORE_OPTION, $ignored, false );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function ignored_map(): array {
		$ignored = get_option( self::IGNORE_OPTION, array() );
		return is_array( $ignored ) ? $ignored : array();
	}

	/**
	 * Dispatch a repair from a scanner issue row.
	 *
	 * @param array<string, mixed> $issue Issue from scanner.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_issue( array $issue ): AgentIdentityRepairResult {
		$rule = (string) ( $issue['rule_id'] ?? '' );
		$type = self::repair_type_for_rule( $rule );
		if ( null === $type ) {
			return $this->finish(
				new AgentIdentityRepairResult(
					AgentIdentityRepairResult::STATUS_SKIPPED,
					'unknown',
					'No repair type mapped for rule: ' . $rule
				),
				$issue
			);
		}

		switch ( $type ) {
			case self::TYPE_MISSING_AGENT:
				return $this->finish( $this->repair_missing_agent( absint( $issue['user_id'] ?? 0 ) ), $issue );
			case self::TYPE_REGISTRATION_STATUS:
				return $this->finish( $this->repair_registration_status( absint( $issue['agent_id'] ?? 0 ) ), $issue );
			case self::TYPE_ROLE:
				return $this->finish( $this->repair_role( absint( $issue['user_id'] ?? 0 ) ), $issue );
			case self::TYPE_CAPABILITY_ASSIGNMENT:
				return $this->finish( $this->repair_capability_assignment(), $issue );
			case self::TYPE_BROKEN_LINK:
				return $this->finish( $this->repair_broken_link( absint( $issue['agent_id'] ?? 0 ) ), $issue );
			case self::TYPE_MISSING_USER:
				return $this->finish( $this->repair_missing_user( absint( $issue['agent_id'] ?? 0 ) ), $issue );
			case self::TYPE_PROPERTY_ASSIGNMENT:
				return $this->finish( $this->repair_property_assignment( absint( $issue['property_id'] ?? 0 ) ), $issue );
			case self::TYPE_NOTIFICATION_OWNERSHIP:
				return $this->finish( $this->repair_notification_ownership( absint( $issue['notification_id'] ?? 0 ) ), $issue );
			case self::TYPE_INQUIRY_OWNERSHIP:
				return $this->finish( $this->repair_inquiry_ownership( absint( $issue['inquiry_id'] ?? 0 ) ), $issue );
			case self::TYPE_DUPLICATE_EMAIL:
				return $this->finish( $this->repair_duplicate_email( $issue ), $issue );
			case self::TYPE_DUPLICATE_IDENTITY:
				return $this->finish( $this->repair_duplicate_identity( $issue ), $issue );
			case self::TYPE_LEGACY_REFERENCES:
				return $this->finish( $this->repair_legacy_references( $issue ), $issue );
			default:
				return $this->finish(
					new AgentIdentityRepairResult(
						AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
						$type,
						'MANUAL ACTION REQUIRED — repair type not automated.'
					),
					$issue
				);
		}
	}

	/**
	 * Broken linked_user_id → missing WP user.
	 * Never guess a replacement user.
	 *
	 * @param int $agent_id Agent CPT ID.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_broken_link( int $agent_id ): AgentIdentityRepairResult {
		$agent_id = absint( $agent_id );
		$before   = $this->snapshot_agent( $agent_id );
		$linked   = absint( $before['linked_user_id'] ?? 0 );

		if ( $agent_id <= 0 || empty( $before['exists'] ) ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_FAILED,
				self::TYPE_BROKEN_LINK,
				'Agent CPT not found.',
				$before
			);
		}

		if ( $linked > 0 && get_userdata( $linked ) ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_SKIPPED,
				self::TYPE_BROKEN_LINK,
				'Linked user exists — nothing to repair.',
				$before,
				$before
			);
		}

		// No deterministic rematch: never invent a user link.
		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
			self::TYPE_BROKEN_LINK,
			'MANUAL ACTION REQUIRED — linked_user_id points to a missing user and no unique replacement is guaranteed. Do not auto-relink.',
			$before
		);
	}

	/**
	 * WP user with hvnly_agent role and no Agent CPT → provision via AgentProvisioner.
	 *
	 * @param int $user_id User ID.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_missing_agent( int $user_id ): AgentIdentityRepairResult {
		$user_id = absint( $user_id );
		$before  = $this->snapshot_user( $user_id );

		if ( $user_id <= 0 || empty( $before['exists'] ) ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_FAILED,
				self::TYPE_MISSING_AGENT,
				'User not found.',
				$before
			);
		}

		$roles = (array) ( $before['roles'] ?? array() );
		if ( ! in_array( PortalCapabilities::WP_ROLE_AGENT, $roles, true ) && empty( $before['is_admin'] ) ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
				self::TYPE_MISSING_AGENT,
				'MANUAL ACTION REQUIRED — user does not have hvnly_agent role; refusing to provision.',
				$before
			);
		}

		$existing = absint( $before['linked_agent_id'] ?? 0 );
		if ( $existing > 0 ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_SKIPPED,
				self::TYPE_MISSING_AGENT,
				'Agent CPT already linked — refusing to create a duplicate.',
				$before,
				$before
			);
		}

		$created = $this->provisioner->ensure_agent_for_user( $user_id );
		if ( is_wp_error( $created ) ) {
			$after = $this->snapshot_user( $user_id );
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_FAILED,
				self::TYPE_MISSING_AGENT,
				$created->get_error_message(),
				$before,
				$after
			);
		}

		$after = $this->snapshot_user( $user_id );
		// Rollback if somehow duplicate links appeared.
		$link_count = $this->count_agents_for_user( $user_id );
		if ( $link_count > 1 ) {
			$this->rollback_user_agent_link( $user_id, $before, (int) $created );
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_ROLLED_BACK,
				self::TYPE_MISSING_AGENT,
				'Rollback — multiple Agent CPTs linked after provision; duplicate risk.',
				$before,
				$this->snapshot_user( $user_id )
			);
		}

		AgentIdentityService::get_instance()->clear_cache( $user_id );

		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_SUCCESS,
			self::TYPE_MISSING_AGENT,
			sprintf( 'Provisioned Agent CPT #%d via AgentProvisioner.', (int) $created ),
			$before,
			$after
		);
	}

	/**
	 * Never auto-create users.
	 *
	 * @param int $agent_id Agent CPT ID.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_missing_user( int $agent_id ): AgentIdentityRepairResult {
		$before = $this->snapshot_agent( $agent_id );
		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
			self::TYPE_MISSING_USER,
			'MANUAL ACTION REQUIRED — never automatically create WordPress users. Approve/create and link in a future sprint.',
			$before
		);
	}

	/**
	 * Sync Agent CPT registration mirror from user SSOT (does not change user meta).
	 *
	 * @param int $agent_id Agent CPT ID.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_registration_status( int $agent_id ): AgentIdentityRepairResult {
		$agent_id = absint( $agent_id );
		$before   = $this->snapshot_agent( $agent_id );

		if ( empty( $before['exists'] ) ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_FAILED,
				self::TYPE_REGISTRATION_STATUS,
				'Agent CPT not found.',
				$before
			);
		}

		$user_id = absint( $before['linked_user_id'] ?? 0 );
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
				self::TYPE_REGISTRATION_STATUS,
				'MANUAL ACTION REQUIRED — no valid linked user; cannot sync registration mirror.',
				$before
			);
		}

		$user_status = WorkspaceRegistrationStatus::get_for_user( $user_id );
		$mirror      = (string) ( $before['reg_mirror'] ?? '' );
		$mirror_norm = '' === $mirror ? '' : WorkspaceRegistrationStatus::normalize( $mirror );

		if ( '' !== $mirror && $mirror_norm === $user_status ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_SKIPPED,
				self::TYPE_REGISTRATION_STATUS,
				'Mirror already matches user SSOT.',
				$before,
				$before
			);
		}

		$prev_mirror = get_post_meta( $agent_id, WorkspaceRegistrationStatus::AGENT_META, true );
		$updated     = update_post_meta( $agent_id, WorkspaceRegistrationStatus::AGENT_META, $user_status );
		$after       = $this->snapshot_agent( $agent_id );

		if ( WorkspaceRegistrationStatus::normalize( (string) ( $after['reg_mirror'] ?? '' ) ) !== $user_status ) {
			if ( false === $prev_mirror || '' === $prev_mirror ) {
				delete_post_meta( $agent_id, WorkspaceRegistrationStatus::AGENT_META );
			} else {
				update_post_meta( $agent_id, WorkspaceRegistrationStatus::AGENT_META, $prev_mirror );
			}
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_ROLLED_BACK,
				self::TYPE_REGISTRATION_STATUS,
				'Rollback — mirror write did not stick.',
				$before,
				$this->snapshot_agent( $agent_id )
			);
		}

		// update_post_meta returns false when value unchanged; treat matching after as success.
		unset( $updated );

		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_SUCCESS,
			self::TYPE_REGISTRATION_STATUS,
			sprintf( 'Synced agent mirror to user SSOT status "%s". User meta unchanged.', $user_status ),
			$before,
			$after
		);
	}

	/**
	 * Ensure WP Agent role + portal caps exist (global, deterministic).
	 *
	 * @return AgentIdentityRepairResult
	 */
	public function repair_capability_assignment(): AgentIdentityRepairResult {
		$before = array(
			'agent_role_exists' => (bool) get_role( PortalCapabilities::WP_ROLE_AGENT ),
			'agent_caps'        => $this->role_caps_snapshot( PortalCapabilities::WP_ROLE_AGENT ),
			'admin_caps'        => $this->role_caps_snapshot( 'administrator' ),
		);

		$this->capabilities->ensure_roles_and_caps();

		$after = array(
			'agent_role_exists' => (bool) get_role( PortalCapabilities::WP_ROLE_AGENT ),
			'agent_caps'        => $this->role_caps_snapshot( PortalCapabilities::WP_ROLE_AGENT ),
			'admin_caps'        => $this->role_caps_snapshot( 'administrator' ),
		);

		if ( ! $after['agent_role_exists'] ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_FAILED,
				self::TYPE_CAPABILITY_ASSIGNMENT,
				'Agent role still missing after ensure_roles_and_caps().',
				$before,
				$after
			);
		}

		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_SUCCESS,
			self::TYPE_CAPABILITY_ASSIGNMENT,
			'Ensured hvnly_agent role and portal capabilities.',
			$before,
			$after
		);
	}

	/**
	 * Add hvnly_agent role to a linked user who is missing it (non-destructive).
	 *
	 * @param int $user_id User ID.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_role( int $user_id ): AgentIdentityRepairResult {
		$user_id = absint( $user_id );
		$before  = $this->snapshot_user( $user_id );

		if ( empty( $before['exists'] ) ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_FAILED,
				self::TYPE_ROLE,
				'User not found.',
				$before
			);
		}

		$roles = (array) ( $before['roles'] ?? array() );
		if ( in_array( PortalCapabilities::WP_ROLE_AGENT, $roles, true ) ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_SKIPPED,
				self::TYPE_ROLE,
				'User already has hvnly_agent role.',
				$before,
				$before
			);
		}

		$agent_id = absint( $before['linked_agent_id'] ?? 0 );
		if ( $agent_id <= 0 ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
				self::TYPE_ROLE,
				'MANUAL ACTION REQUIRED — no linked Agent CPT; role repair requires a deterministic agent link.',
				$before
			);
		}

		$this->capabilities->ensure_agent_role();
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_FAILED,
				self::TYPE_ROLE,
				'User disappeared during repair.',
				$before
			);
		}

		$user->add_role( PortalCapabilities::WP_ROLE_AGENT );
		$after = $this->snapshot_user( $user_id );

		if ( ! in_array( PortalCapabilities::WP_ROLE_AGENT, (array) $after['roles'], true ) ) {
			return new AgentIdentityRepairResult(
				AgentIdentityRepairResult::STATUS_ROLLED_BACK,
				self::TYPE_ROLE,
				'Rollback — role was not present after add_role().',
				$before,
				$after
			);
		}

		AgentIdentityService::get_instance()->clear_cache( $user_id );

		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_SUCCESS,
			self::TYPE_ROLE,
			'Added hvnly_agent role (existing roles preserved).',
			$before,
			$after
		);
	}

	/**
	 * Property ownership — detect only (Delete Wizard later).
	 *
	 * @param int $property_id Property ID.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_property_assignment( int $property_id ): AgentIdentityRepairResult {
		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
			self::TYPE_PROPERTY_ASSIGNMENT,
			'MANUAL ACTION REQUIRED — never change property ownership automatically. Use the Agent Delete Wizard for reassignment.',
			array( 'property_id' => absint( $property_id ) )
		);
	}

	/**
	 * Notifications — only touch when user_id exists; invalid owners stay untouched.
	 *
	 * @param int $notification_id Notification row ID.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_notification_ownership( int $notification_id ): AgentIdentityRepairResult {
		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
			self::TYPE_NOTIFICATION_OWNERSHIP,
			'MANUAL ACTION REQUIRED — notification user_id is missing or invalid; leave untouched (no silent delete).',
			array( 'notification_id' => absint( $notification_id ) )
		);
	}

	/**
	 * Inquiries — never auto-reassign unless future deterministic rule approved.
	 *
	 * @param int $inquiry_id Inquiry ID.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_inquiry_ownership( int $inquiry_id ): AgentIdentityRepairResult {
		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
			self::TYPE_INQUIRY_OWNERSHIP,
			'MANUAL ACTION REQUIRED — inquiry ownership is not auto-repaired (ambiguous agent_id).',
			array( 'inquiry_id' => absint( $inquiry_id ) )
		);
	}

	/**
	 * Duplicate emails — admin decides.
	 *
	 * @param array<string, mixed> $issue Issue.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_duplicate_email( array $issue ): AgentIdentityRepairResult {
		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
			self::TYPE_DUPLICATE_EMAIL,
			'MANUAL ACTION REQUIRED — never silently overwrite duplicate emails. Administrator must choose the canonical address.',
			$issue
		);
	}

	/**
	 * Duplicate identity links — never pick a winner automatically.
	 *
	 * @param array<string, mixed> $issue Issue.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_duplicate_identity( array $issue ): AgentIdentityRepairResult {
		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
			self::TYPE_DUPLICATE_IDENTITY,
			'MANUAL ACTION REQUIRED — multiple possible targets; never relink or merge automatically.',
			$issue
		);
	}

	/**
	 * Legacy references — detect/report only in this sprint.
	 *
	 * @param array<string, mixed> $issue Issue.
	 * @return AgentIdentityRepairResult
	 */
	public function repair_legacy_references( array $issue ): AgentIdentityRepairResult {
		return new AgentIdentityRepairResult(
			AgentIdentityRepairResult::STATUS_MANUAL_REQUIRED,
			self::TYPE_LEGACY_REFERENCES,
			'MANUAL ACTION REQUIRED — legacy meta/taxonomy cleanup is deferred (no silent deletes/migrations).',
			$issue
		);
	}

	/**
	 * @param AgentIdentityRepairResult $result Result.
	 * @param array<string, mixed>      $issue  Issue context.
	 * @return AgentIdentityRepairResult
	 */
	private function finish( AgentIdentityRepairResult $result, array $issue = array() ): AgentIdentityRepairResult {
		$data = $result->to_array();
		AgentIdentityRepairLog::write(
			array(
				'repair_type' => $data['repair_type'],
				'before'      => $data['before'],
				'after'       => $data['after'],
				'result'      => $data['status'],
				'reason'      => $data['reason'],
				'issue_key'   => self::issue_key( $issue ),
				'rule_id'     => (string) ( $issue['rule_id'] ?? '' ),
				'object'      => (string) ( $issue['object'] ?? '' ),
			)
		);
		return $result;
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	private function snapshot_user( int $user_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array(
				'exists'          => false,
				'user_id'         => $user_id,
				'linked_agent_id' => 0,
			);
		}
		return array(
			'exists'          => true,
			'user_id'         => $user_id,
			'login'           => (string) $user->user_login,
			'email'           => (string) $user->user_email,
			'roles'           => array_values( (array) $user->roles ),
			'is_admin'        => user_can( $user, 'manage_options' ),
			'reg_status'      => WorkspaceRegistrationStatus::get_for_user( $user_id ),
			'linked_agent_id' => WorkspaceRegistrationStatus::find_linked_agent_id( $user_id ),
		);
	}

	/**
	 * @param int $agent_id Agent ID.
	 * @return array<string, mixed>
	 */
	private function snapshot_agent( int $agent_id ): array {
		$post = get_post( $agent_id );
		if ( ! $post || AgentConstants::POST_TYPE !== $post->post_type ) {
			return array(
				'exists'         => false,
				'agent_id'       => $agent_id,
				'linked_user_id' => 0,
			);
		}
		return array(
			'exists'         => true,
			'agent_id'       => $agent_id,
			'title'          => (string) $post->post_title,
			'status'         => (string) $post->post_status,
			'linked_user_id' => absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) ),
			'reg_mirror'     => (string) get_post_meta( $agent_id, WorkspaceRegistrationStatus::AGENT_META, true ),
			'email'          => (string) get_post_meta( $agent_id, AgentConstants::META_EMAIL, true ),
		);
	}

	/**
	 * @param string $role_name Role.
	 * @return string[]
	 */
	private function role_caps_snapshot( string $role_name ): array {
		$role = get_role( $role_name );
		if ( ! $role ) {
			return array();
		}
		$caps = array();
		foreach ( $role->capabilities as $cap => $grant ) {
			if ( $grant && 0 === strpos( (string) $cap, 'hvnly_' ) ) {
				$caps[] = (string) $cap;
			}
		}
		sort( $caps );
		return $caps;
	}

	/**
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function count_agents_for_user( int $user_id ): int {
		$q = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page'         => 20,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => AgentConstants::META_LINKED_USER_ID,
						'value' => $user_id,
					),
				),
			)
		);
		return count( $q->posts );
	}

	/**
	 * Best-effort rollback after failed provision (trash only the newly created CPT if empty of history).
	 * Never deletes users. Never deletes pre-existing agents.
	 *
	 * @param int                  $user_id   User ID.
	 * @param array<string, mixed> $before    Before snapshot.
	 * @param int                  $created_id Newly created agent ID.
	 * @return void
	 */
	private function rollback_user_agent_link( int $user_id, array $before, int $created_id ): void {
		$prior = absint( $before['linked_agent_id'] ?? 0 );
		if ( $created_id > 0 && $created_id !== $prior ) {
			// Soft-trash only the CPT we just created in this request — not hard delete.
			wp_trash_post( $created_id );
		}
		AgentIdentityService::get_instance()->clear_cache( $user_id );
	}
}
