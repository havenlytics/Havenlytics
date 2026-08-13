<?php
/**
 * Read-only Agent Identity Integrity Scanner.
 *
 * Detects orphan / broken / duplicate identity links. Never writes.
 * Designed to power future Admin → Tools → Havenlytics Health → Agent Identity.
 *
 * @package HvnlyNab\Workspace\Health
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Health;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ContactAgent\Database\InquirySchema;
use HvnlyNab\Workspace\Auth\PortalCapabilities;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;
use HvnlyNab\Workspace\Notifications\NotificationSchema;

defined( 'ABSPATH' ) || exit;

/**
 * Site-Health style scanner for WP User ⇄ hvnly_agent CPT integrity.
 *
 * @since 3.2.0
 */
final class AgentIdentityIntegrityScanner {

	public const RULE_AGENT_MISSING_USER        = 'agent_cpt_linked_user_missing';
	public const RULE_USER_MISSING_AGENT        = 'agent_role_user_missing_cpt';
	public const RULE_MULTI_AGENT_ONE_USER      = 'multiple_agents_linked_to_user';
	public const RULE_AGENT_MULTI_USER_META     = 'agent_multiple_linked_user_meta_rows';
	public const RULE_BROKEN_LINKED_ID          = 'broken_linked_user_id';
	public const RULE_DUPLICATE_EMAIL           = 'duplicate_email';
	public const RULE_DUPLICATE_USERNAME        = 'duplicate_username';
	public const RULE_REG_STATUS_MISMATCH       = 'registration_status_mismatch';
	public const RULE_ROLE_MISMATCH             = 'role_mismatch';
	public const RULE_PROFILE_INCOMPLETE        = 'profile_incomplete';
	public const RULE_FEATURED_IMAGE_MISSING    = 'featured_image_missing';
	public const RULE_PROPERTY_ASSIGNED_GONE    = 'property_assigned_agent_missing';
	public const RULE_PROPERTY_AUTHOR_GONE      = 'property_author_missing';
	public const RULE_INQUIRY_INVALID_AGENT     = 'inquiry_invalid_agent_id';
	public const RULE_NOTIFICATION_INVALID_USER = 'notification_invalid_user';
	public const RULE_DASHBOARD_OWNERSHIP_SKEW  = 'dashboard_ownership_skew';
	public const RULE_TAXONOMY_CONFLICT         = 'agent_taxonomy_conflict';
	public const RULE_LEGACY_PROPERTY_AGENT     = 'legacy_property_agent_meta';
	public const RULE_ORPHAN_DATA               = 'orphan_data';

	/**
	 * @var AgentIdentityIssue[]
	 */
	private $issues = array();

	/**
	 * @var array<string, int>
	 */
	private $stats = array();

	/**
	 * Run a full read-only scan.
	 *
	 * @return AgentIdentityIntegrityReport
	 */
	public function scan(): AgentIdentityIntegrityReport {
		$this->issues = array();
		$this->stats  = array(
			'agents_scanned'         => 0,
			'agent_users_scanned'    => 0,
			'properties_scanned'     => 0,
			'inquiries_scanned'      => 0,
			'notifications_scanned'  => 0,
			'legacy_agent_meta_rows' => 0,
			'tax_term_assignments'   => 0,
		);

		$agents = $this->load_agents();
		$users  = $this->load_agent_role_users();
		$links  = $this->build_link_index( $agents );

		$this->stats['agents_scanned']      = count( $agents );
		$this->stats['agent_users_scanned'] = count( $users );

		$this->check_agents( $agents );
		$this->check_users_missing_agents( $users, $links );
		$this->check_multi_agent_links( $links );
		$this->check_duplicate_emails( $agents, $users );
		$this->check_duplicate_usernames();
		$this->check_registration_status( $agents );
		$this->check_role_mismatch( $agents );
		$this->check_profile_and_avatar( $agents );
		$this->check_properties( $agents );
		$this->check_inquiries( $agents );
		$this->check_notifications();
		$this->check_dashboard_skew( $users, $links, $agents );
		$this->check_taxonomy_conflicts();
		$this->check_legacy_property_agent_meta();
		$this->check_orphan_link_meta();

		return new AgentIdentityIntegrityReport( $this->issues, $this->stats, gmdate( 'c' ) );
	}

	/**
	 * Validation rule catalog (for UI / docs).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function rules_catalog(): array {
		return array(
			array(
				'id'       => self::RULE_AGENT_MISSING_USER,
				'severity' => AgentIdentityIssue::SEVERITY_HIGH,
				'label'    => 'Agent CPT linked user missing / empty',
			),
			array(
				'id'       => self::RULE_USER_MISSING_AGENT,
				'severity' => AgentIdentityIssue::SEVERITY_HIGH,
				'label'    => 'hvnly_agent role user without Agent CPT',
			),
			array(
				'id'       => self::RULE_MULTI_AGENT_ONE_USER,
				'severity' => AgentIdentityIssue::SEVERITY_CRITICAL,
				'label'    => 'Multiple Agent CPTs linked to same User',
			),
			array(
				'id'       => self::RULE_AGENT_MULTI_USER_META,
				'severity' => AgentIdentityIssue::SEVERITY_CRITICAL,
				'label'    => 'Agent CPT has multiple linked_user_id meta rows',
			),
			array(
				'id'       => self::RULE_BROKEN_LINKED_ID,
				'severity' => AgentIdentityIssue::SEVERITY_CRITICAL,
				'label'    => 'Broken linked_user_id (user deleted)',
			),
			array(
				'id'       => self::RULE_DUPLICATE_EMAIL,
				'severity' => AgentIdentityIssue::SEVERITY_HIGH,
				'label'    => 'Duplicate email across users / agent public emails',
			),
			array(
				'id'       => self::RULE_DUPLICATE_USERNAME,
				'severity' => AgentIdentityIssue::SEVERITY_CRITICAL,
				'label'    => 'Duplicate username (should be impossible in WP)',
			),
			array(
				'id'       => self::RULE_REG_STATUS_MISMATCH,
				'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
				'label'    => 'Registration status user SSOT ≠ agent mirror',
			),
			array(
				'id'       => self::RULE_ROLE_MISMATCH,
				'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
				'label'    => 'Linked user missing hvnly_agent role (non-admin)',
			),
			array(
				'id'       => self::RULE_PROFILE_INCOMPLETE,
				'severity' => AgentIdentityIssue::SEVERITY_LOW,
				'label'    => 'Linked agent profile incomplete',
			),
			array(
				'id'       => self::RULE_FEATURED_IMAGE_MISSING,
				'severity' => AgentIdentityIssue::SEVERITY_LOW,
				'label'    => 'Featured image / avatar missing',
			),
			array(
				'id'       => self::RULE_PROPERTY_ASSIGNED_GONE,
				'severity' => AgentIdentityIssue::SEVERITY_HIGH,
				'label'    => 'Property assigned to missing Agent CPT',
			),
			array(
				'id'       => self::RULE_PROPERTY_AUTHOR_GONE,
				'severity' => AgentIdentityIssue::SEVERITY_HIGH,
				'label'    => 'Property authored by missing user',
			),
			array(
				'id'       => self::RULE_INQUIRY_INVALID_AGENT,
				'severity' => AgentIdentityIssue::SEVERITY_HIGH,
				'label'    => 'Inquiry agent_id invalid',
			),
			array(
				'id'       => self::RULE_NOTIFICATION_INVALID_USER,
				'severity' => AgentIdentityIssue::SEVERITY_HIGH,
				'label'    => 'Notification user_id invalid',
			),
			array(
				'id'       => self::RULE_DASHBOARD_OWNERSHIP_SKEW,
				'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
				'label'    => 'Dashboard ownership skew risk',
			),
			array(
				'id'       => self::RULE_TAXONOMY_CONFLICT,
				'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
				'label'    => 'Legacy hvnly_prop_agents tax vs Workspace SSOT',
			),
			array(
				'id'       => self::RULE_LEGACY_PROPERTY_AGENT,
				'severity' => AgentIdentityIssue::SEVERITY_LOW,
				'label'    => 'Legacy _hvnly_property_agent meta still present',
			),
			array(
				'id'       => self::RULE_ORPHAN_DATA,
				'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
				'label'    => 'Orphan linked meta / other orphans',
			),
		);
	}

	/**
	 * @param array<string, mixed> $args Issue args.
	 * @return void
	 */
	private function add_issue( array $args ): void {
		$this->issues[] = new AgentIdentityIssue( $args );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function load_agents(): array {
		$posts = get_posts(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft', 'private', 'trash' ),
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$id         = (int) $post->ID;
			$out[ $id ] = array(
				'id'           => $id,
				'title'        => (string) $post->post_title,
				'status'       => (string) $post->post_status,
				'author'       => (int) $post->post_author,
				'linked_user'  => absint( get_post_meta( $id, AgentConstants::META_LINKED_USER_ID, true ) ),
				'email'        => (string) get_post_meta( $id, AgentConstants::META_EMAIL, true ),
				'reg_mirror'   => (string) get_post_meta( $id, WorkspaceRegistrationStatus::AGENT_META, true ),
				'thumbnail_id' => (int) get_post_thumbnail_id( $id ),
			);
		}
		return $out;
	}

	/**
	 * Users with role hvnly_agent.
	 *
	 * @return array<int, \WP_User>
	 */
	private function load_agent_role_users(): array {
		$q   = new \WP_User_Query(
			array(
				'role'   => PortalCapabilities::WP_ROLE_AGENT,
				'number' => -1,
				'fields' => 'all',
			)
		);
		$out = array();
		foreach ( (array) $q->get_results() as $user ) {
			if ( $user instanceof \WP_User ) {
				$out[ (int) $user->ID ] = $user;
			}
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $agents Agents.
	 * @return array<int, int[]> user_id => agent_ids
	 */
	private function build_link_index( array $agents ): array {
		$links = array();
		foreach ( $agents as $agent ) {
			$uid = (int) $agent['linked_user'];
			if ( $uid <= 0 ) {
				continue;
			}
			if ( ! isset( $links[ $uid ] ) ) {
				$links[ $uid ] = array();
			}
			$links[ $uid ][] = (int) $agent['id'];
		}
		return $links;
	}

	/**
	 * Rules 1, 4, 5 (agent side).
	 *
	 * @param array<int, array<string, mixed>> $agents Agents.
	 * @return void
	 */
	private function check_agents( array $agents ): void {
		global $wpdb;

		foreach ( $agents as $agent ) {
			$agent_id = (int) $agent['id'];
			$linked   = (int) $agent['linked_user'];

			// Multiple meta rows for linked user (rule 4 / orphan multi-value).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$meta_rows = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
					$agent_id,
					AgentConstants::META_LINKED_USER_ID
				)
			);
			if ( is_array( $meta_rows ) && count( $meta_rows ) > 1 ) {
				$this->add_issue(
					array(
						'rule_id'  => self::RULE_AGENT_MULTI_USER_META,
						'severity' => AgentIdentityIssue::SEVERITY_CRITICAL,
						'title'    => 'Agent has multiple linked_user_id meta rows',
						'object'   => 'hvnly_agent#' . $agent_id,
						'agent_id' => $agent_id,
						'file'     => 'includes/Agent/AgentConstants.php',
						'why'      => sprintf(
							'Found %d meta rows for %s; identity resolution is nondeterministic.',
							count( $meta_rows ),
							AgentConstants::META_LINKED_USER_ID
						),
						'repair'   => 'Keep a single linked_user_id value; remove duplicate meta rows (repair sprint).',
					)
				);
			}

			if ( $linked <= 0 ) {
				// Demo / public-only agents are expected; still report as orphan identity for Workspace.
				$sev = ( 'publish' === $agent['status'] ) ? AgentIdentityIssue::SEVERITY_MEDIUM : AgentIdentityIssue::SEVERITY_LOW;
				$this->add_issue(
					array(
						'rule_id'  => self::RULE_AGENT_MISSING_USER,
						'severity' => $sev,
						'title'    => 'Agent CPT has no linked WordPress user',
						'object'   => 'hvnly_agent#' . $agent_id,
						'agent_id' => $agent_id,
						'file'     => 'includes/Workspace/Auth/AgentProvisioner.php',
						'why'      => 'No _hvnly_agent_linked_user_id. Cannot login to Workspace as this agent. Common for demo import.',
						'repair'   => 'If Workspace login is required: create/link a hvnly_agent user. If public-only demo: document as intentional and exclude from Workspace metrics.',
					)
				);
				continue;
			}

			$user = get_userdata( $linked );
			if ( ! $user ) {
				$this->add_issue(
					array(
						'rule_id'  => self::RULE_BROKEN_LINKED_ID,
						'severity' => AgentIdentityIssue::SEVERITY_CRITICAL,
						'title'    => 'Broken linked_user_id (user missing)',
						'object'   => 'hvnly_agent#' . $agent_id,
						'agent_id' => $agent_id,
						'user_id'  => $linked,
						'file'     => 'includes/Agent/AgentConstants.php',
						'why'      => sprintf( 'Meta points to user ID %d which does not exist.', $linked ),
						'repair'   => 'Clear broken link or recreate/reassign user (repair sprint). Never silent-delete CPT without reassignment plan.',
					)
				);
			}
		}
	}

	/**
	 * Rule 2.
	 *
	 * @param array<int, \WP_User> $users Agent-role users.
	 * @param array<int, int[]>    $links user→agents.
	 * @return void
	 */
	private function check_users_missing_agents( array $users, array $links ): void {
		foreach ( $users as $user_id => $user ) {
			if ( ! empty( $links[ $user_id ] ) ) {
				continue;
			}
			$this->add_issue(
				array(
					'rule_id'  => self::RULE_USER_MISSING_AGENT,
					'severity' => AgentIdentityIssue::SEVERITY_HIGH,
					'title'    => 'Agent-role user has no linked Agent CPT',
					'object'   => 'user#' . $user_id,
					'user_id'  => (int) $user_id,
					'file'     => 'includes/Workspace/Auth/AgentProvisioner.php',
					'why'      => sprintf(
						'User %s has role %s but no hvnly_agent CPT with linked_user_id=%d.',
						$user->user_login,
						PortalCapabilities::WP_ROLE_AGENT,
						$user_id
					),
					'repair'   => 'Call ensure_agent_for_user (provision) or remove role if account is unused (repair sprint).',
				)
			);
		}
	}

	/**
	 * Rule 3.
	 *
	 * @param array<int, int[]> $links user→agents.
	 * @return void
	 */
	private function check_multi_agent_links( array $links ): void {
		foreach ( $links as $user_id => $agent_ids ) {
			if ( count( $agent_ids ) < 2 ) {
				continue;
			}
			$this->add_issue(
				array(
					'rule_id'  => self::RULE_MULTI_AGENT_ONE_USER,
					'severity' => AgentIdentityIssue::SEVERITY_CRITICAL,
					'title'    => 'Multiple Agent CPTs linked to the same user',
					'object'   => 'user#' . $user_id,
					'user_id'  => (int) $user_id,
					'agent_id' => (int) $agent_ids[0],
					'file'     => 'includes/Workspace/Auth/AgentIdentityService.php',
					'why'      => sprintf(
						'User %d is linked from agent IDs: %s. Identity takes first match only.',
						$user_id,
						implode( ', ', $agent_ids )
					),
					'repair'   => 'Enforce 1:1 — keep one CPT, unlink or merge others (repair sprint).',
				)
			);
		}
	}

	/**
	 * Rules 6–7.
	 *
	 * @param array<int, array<string, mixed>> $agents Agents.
	 * @param array<int, \WP_User>             $users  Agent users.
	 * @return void
	 */
	private function check_duplicate_emails( array $agents, array $users ): void {
		global $wpdb;

		// Duplicate WP user emails (should be rare).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT user_email, COUNT(*) AS c, GROUP_CONCAT(ID) AS ids
			FROM {$wpdb->users}
			WHERE user_email <> ''
			GROUP BY user_email
			HAVING c > 1",
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			$this->add_issue(
				array(
					'rule_id'  => self::RULE_DUPLICATE_EMAIL,
					'severity' => AgentIdentityIssue::SEVERITY_CRITICAL,
					'title'    => 'Duplicate WordPress user email',
					'object'   => (string) $row['user_email'],
					'file'     => 'wp_users',
					'why'      => sprintf( 'Email %s shared by user IDs: %s', $row['user_email'], $row['ids'] ),
					'repair'   => 'Merge or rename emails so each login email is unique.',
				)
			);
		}

		// Public agent emails colliding with another agent (or with a different user's login email).
		$email_map = array();
		foreach ( $agents as $agent ) {
			$email = strtolower( trim( (string) $agent['email'] ) );
			if ( '' === $email || ! is_email( $email ) ) {
				continue;
			}
			if ( ! isset( $email_map[ $email ] ) ) {
				$email_map[ $email ] = array();
			}
			$email_map[ $email ][] = (int) $agent['id'];
		}
		foreach ( $email_map as $email => $agent_ids ) {
			if ( count( $agent_ids ) < 2 ) {
				continue;
			}
			$this->add_issue(
				array(
					'rule_id'  => self::RULE_DUPLICATE_EMAIL,
					'severity' => AgentIdentityIssue::SEVERITY_HIGH,
					'title'    => 'Duplicate public agent email meta',
					'object'   => $email,
					'agent_id' => $agent_ids[0],
					'file'     => 'includes/Agent/AgentConstants.php',
					'why'      => sprintf( 'Public email %s on agent IDs: %s', $email, implode( ', ', $agent_ids ) ),
					'repair'   => 'Deduplicate _hvnly_agent_email values or confirm intentional shared inbox.',
				)
			);
		}

		foreach ( $users as $user_id => $user ) {
			$login_email = strtolower( (string) $user->user_email );
			if ( '' === $login_email ) {
				continue;
			}
			foreach ( $agents as $agent ) {
				$public = strtolower( trim( (string) $agent['email'] ) );
				$linked = (int) $agent['linked_user'];
				if ( $public === $login_email && $linked > 0 && $linked !== (int) $user_id ) {
					$this->add_issue(
						array(
							'rule_id'  => self::RULE_DUPLICATE_EMAIL,
							'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
							'title'    => 'Agent public email matches a different user login email',
							'object'   => $login_email,
							'user_id'  => (int) $user_id,
							'agent_id' => (int) $agent['id'],
							'file'     => 'includes/Workspace/Api/ProfileService.php',
							'why'      => 'Login email and public CPT email collide across identities.',
							'repair'   => 'Align contact.email with the correct account or differentiate public vs login email consciously.',
						)
					);
				}
			}
		}
	}

	/**
	 * Rule 7 — duplicate usernames (WP normally prevents).
	 *
	 * @return void
	 */
	private function check_duplicate_usernames(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT user_login, COUNT(*) AS c, GROUP_CONCAT(ID) AS ids
			FROM {$wpdb->users}
			GROUP BY user_login
			HAVING c > 1",
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			$this->add_issue(
				array(
					'rule_id'  => self::RULE_DUPLICATE_USERNAME,
					'severity' => AgentIdentityIssue::SEVERITY_CRITICAL,
					'title'    => 'Duplicate username',
					'object'   => (string) $row['user_login'],
					'file'     => 'wp_users',
					'why'      => sprintf( 'Username %s on IDs: %s', $row['user_login'], $row['ids'] ),
					'repair'   => 'Fix at database level; WordPress requires unique user_login.',
				)
			);
		}
	}

	/**
	 * Rule 8.
	 *
	 * @param array<int, array<string, mixed>> $agents Agents.
	 * @return void
	 */
	private function check_registration_status( array $agents ): void {
		foreach ( $agents as $agent ) {
			$linked = (int) $agent['linked_user'];
			if ( $linked <= 0 || ! get_userdata( $linked ) ) {
				continue;
			}
			$user_status = WorkspaceRegistrationStatus::get_for_user( $linked );
			$raw_user    = (string) get_user_meta( $linked, WorkspaceRegistrationStatus::USER_META, true );
			$mirror      = (string) $agent['reg_mirror'];

			// Compare raw mirror to effective user status when mirror is present.
			if ( '' === $mirror ) {
				if ( '' !== $raw_user ) {
					$this->add_issue(
						array(
							'rule_id'  => self::RULE_REG_STATUS_MISMATCH,
							'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
							'title'    => 'Registration status mirror missing on Agent CPT',
							'object'   => 'hvnly_agent#' . $agent['id'],
							'user_id'  => $linked,
							'agent_id' => (int) $agent['id'],
							'file'     => 'includes/Workspace/Auth/WorkspaceRegistrationStatus.php',
							'why'      => sprintf(
								'User SSOT=%s (%s) but agent mirror meta is empty.',
								$user_status,
								'' === $raw_user ? 'legacy-empty→active' : $raw_user
							),
							'repair'   => 'Re-save status via RegistrationAdminActions to rewrite mirror (repair sprint).',
						)
					);
				}
				continue;
			}

			$mirror_norm = WorkspaceRegistrationStatus::normalize( $mirror );
			if ( $mirror_norm !== $user_status ) {
				$this->add_issue(
					array(
						'rule_id'  => self::RULE_REG_STATUS_MISMATCH,
						'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
						'title'    => 'Registration status user ≠ agent mirror',
						'object'   => 'hvnly_agent#' . $agent['id'],
						'user_id'  => $linked,
						'agent_id' => (int) $agent['id'],
						'file'     => 'includes/Workspace/Auth/WorkspaceRegistrationStatus.php',
						'why'      => sprintf( 'User effective=%s, agent mirror=%s (raw=%s).', $user_status, $mirror_norm, $mirror ),
						'repair'   => 'Trust user meta SSOT; rewrite agent mirror to match.',
					)
				);
			}
		}
	}

	/**
	 * Rule 9.
	 *
	 * @param array<int, array<string, mixed>> $agents Agents.
	 * @return void
	 */
	private function check_role_mismatch( array $agents ): void {
		foreach ( $agents as $agent ) {
			$linked = (int) $agent['linked_user'];
			if ( $linked <= 0 ) {
				continue;
			}
			$user = get_userdata( $linked );
			if ( ! $user ) {
				continue;
			}
			if ( user_can( $user, 'manage_options' ) ) {
				continue;
			}
			$roles = (array) $user->roles;
			if ( in_array( PortalCapabilities::WP_ROLE_AGENT, $roles, true ) ) {
				continue;
			}
			$this->add_issue(
				array(
					'rule_id'  => self::RULE_ROLE_MISMATCH,
					'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
					'title'    => 'Linked user missing hvnly_agent role',
					'object'   => 'user#' . $linked,
					'user_id'  => $linked,
					'agent_id' => (int) $agent['id'],
					'file'     => 'includes/Workspace/Auth/CapabilityRegistrar.php',
					'why'      => sprintf(
						'Linked user roles=[%s]; expected %s for Workspace agents.',
						implode( ',', $roles ),
						PortalCapabilities::WP_ROLE_AGENT
					),
					'repair'   => 'Assign hvnly_agent role or unlink CPT if this is not a Workspace agent.',
				)
			);
		}
	}

	/**
	 * Rules 10–11 (linked agents only for incomplete; all published for avatar optional).
	 *
	 * @param array<int, array<string, mixed>> $agents Agents.
	 * @return void
	 */
	private function check_profile_and_avatar( array $agents ): void {
		foreach ( $agents as $agent ) {
			$linked = (int) $agent['linked_user'];
			if ( $linked <= 0 ) {
				continue;
			}
			$missing = array();
			if ( '' === trim( (string) $agent['title'] ) ) {
				$missing[] = 'display name / post_title';
			}
			if ( '' === trim( (string) $agent['email'] ) ) {
				$missing[] = 'public email';
			}
			if ( $missing ) {
				$this->add_issue(
					array(
						'rule_id'  => self::RULE_PROFILE_INCOMPLETE,
						'severity' => AgentIdentityIssue::SEVERITY_LOW,
						'title'    => 'Linked agent profile incomplete',
						'object'   => 'hvnly_agent#' . $agent['id'],
						'user_id'  => $linked,
						'agent_id' => (int) $agent['id'],
						'file'     => 'includes/Workspace/Api/ProfileService.php',
						'why'      => 'Missing: ' . implode( ', ', $missing ),
						'repair'   => 'Complete profile via Workspace Profile or admin Agent screen.',
					)
				);
			}
			if ( (int) $agent['thumbnail_id'] <= 0 && 'publish' === $agent['status'] ) {
				$this->add_issue(
					array(
						'rule_id'  => self::RULE_FEATURED_IMAGE_MISSING,
						'severity' => AgentIdentityIssue::SEVERITY_LOW,
						'title'    => 'Agent featured image missing',
						'object'   => 'hvnly_agent#' . $agent['id'],
						'agent_id' => (int) $agent['id'],
						'user_id'  => $linked,
						'file'     => 'includes/Workspace/Pages/Profile/ProfilePhotoField.js',
						'why'      => 'No post thumbnail (_thumbnail_id) on published linked agent.',
						'repair'   => 'Optional: upload avatar via Profile media picker.',
					)
				);
			}
		}
	}

	/**
	 * Rules 12–13.
	 *
	 * @param array<int, array<string, mixed>> $agents Agents keyed by ID.
	 * @return void
	 */
	private function check_properties( array $agents ): void {
		$posts                             = get_posts(
			array(
				'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft', 'private', 'trash' ),
				'posts_per_page'         => -1,
				'fields'                 => 'all',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);
		$this->stats['properties_scanned'] = count( $posts );

		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$pid    = (int) $post->ID;
			$author = (int) $post->post_author;
			if ( $author > 0 && ! get_userdata( $author ) ) {
				$this->add_issue(
					array(
						'rule_id'     => self::RULE_PROPERTY_AUTHOR_GONE,
						'severity'    => AgentIdentityIssue::SEVERITY_HIGH,
						'title'       => 'Property authored by missing user',
						'object'      => 'hvnly_property#' . $pid,
						'property_id' => $pid,
						'user_id'     => $author,
						'file'        => 'includes/Workspace/Api/DashboardService.php',
						'why'         => sprintf( 'post_author=%d does not exist; dashboard author-scoped counts skew.', $author ),
						'repair'      => 'Reassign author like WP user-delete flow.',
					)
				);
			}

			$assigned = get_post_meta( $pid, AgentConstants::META_PROPERTY_AGENTS, true );
			if ( ! is_array( $assigned ) ) {
				continue;
			}
			foreach ( $assigned as $agent_id ) {
				$agent_id = absint( $agent_id );
				if ( $agent_id <= 0 ) {
					continue;
				}
				if ( isset( $agents[ $agent_id ] ) ) {
					continue;
				}
				// Also tolerate agent existing but not in our status list — re-check get_post.
				$exists = get_post( $agent_id );
				if ( $exists && AgentConstants::POST_TYPE === $exists->post_type ) {
					continue;
				}
				$this->add_issue(
					array(
						'rule_id'     => self::RULE_PROPERTY_ASSIGNED_GONE,
						'severity'    => AgentIdentityIssue::SEVERITY_HIGH,
						'title'       => 'Property assigned to missing Agent CPT',
						'object'      => 'hvnly_property#' . $pid,
						'property_id' => $pid,
						'agent_id'    => $agent_id,
						'file'        => 'includes/Agent/PropertyAgentAssignment.php',
						'why'         => sprintf( '_hvnly_property_agents contains agent ID %d which is not a valid hvnly_agent.', $agent_id ),
						'repair'      => 'Remove stale ID from assignment meta or restore the Agent CPT.',
					)
				);
			}
		}
	}

	/**
	 * Rule 14.
	 *
	 * @param array<int, array<string, mixed>> $agents Agents.
	 * @return void
	 */
	private function check_inquiries( array $agents ): void {
		if ( ! class_exists( InquirySchema::class ) || ! InquirySchema::table_exists() ) {
			return;
		}
		global $wpdb;
		$table = InquirySchema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows                             = $wpdb->get_results( "SELECT id, agent_id, property_id FROM {$table} WHERE agent_id > 0", ARRAY_A );
		$this->stats['inquiries_scanned'] = is_array( $rows ) ? count( $rows ) : 0;

		foreach ( (array) $rows as $row ) {
			$agent_id = absint( $row['agent_id'] ?? 0 );
			$inq_id   = absint( $row['id'] ?? 0 );
			if ( $agent_id <= 0 ) {
				continue;
			}
			if ( isset( $agents[ $agent_id ] ) ) {
				continue;
			}
			// Legacy: agent_id may be a WP user ID.
			if ( get_userdata( $agent_id ) ) {
				continue;
			}
			$post = get_post( $agent_id );
			if ( $post && AgentConstants::POST_TYPE === $post->post_type ) {
				continue;
			}
			$this->add_issue(
				array(
					'rule_id'     => self::RULE_INQUIRY_INVALID_AGENT,
					'severity'    => AgentIdentityIssue::SEVERITY_HIGH,
					'title'       => 'Inquiry points to invalid agent_id',
					'object'      => 'inquiry#' . $inq_id,
					'inquiry_id'  => $inq_id,
					'agent_id'    => $agent_id,
					'property_id' => absint( $row['property_id'] ?? 0 ),
					'file'        => 'includes/ContactAgent/InquiryRepository.php',
					'why'         => sprintf( 'agent_id=%d is neither a live hvnly_agent nor a WP user.', $agent_id ),
					'repair'      => 'Reassign inquiry agent_id or soft-close orphaned inquiry (repair sprint).',
				)
			);
		}
	}

	/**
	 * Rule 15.
	 *
	 * @return void
	 */
	private function check_notifications(): void {
		if ( ! class_exists( NotificationSchema::class ) || ! NotificationSchema::table_exists() ) {
			return;
		}
		global $wpdb;
		$table = NotificationSchema::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows                                 = $wpdb->get_results( "SELECT id, user_id, agent_id FROM {$table}", ARRAY_A );
		$this->stats['notifications_scanned'] = is_array( $rows ) ? count( $rows ) : 0;

		foreach ( (array) $rows as $row ) {
			$nid     = absint( $row['id'] ?? 0 );
			$user_id = absint( $row['user_id'] ?? 0 );
			if ( $user_id <= 0 || get_userdata( $user_id ) ) {
				continue;
			}
			$this->add_issue(
				array(
					'rule_id'         => self::RULE_NOTIFICATION_INVALID_USER,
					'severity'        => AgentIdentityIssue::SEVERITY_HIGH,
					'title'           => 'Notification owned by missing user',
					'object'          => 'notification#' . $nid,
					'notification_id' => $nid,
					'user_id'         => $user_id,
					'agent_id'        => absint( $row['agent_id'] ?? 0 ),
					'file'            => 'includes/Workspace/Notifications/NotificationSchema.php',
					'why'             => sprintf( 'portal notification user_id=%d does not exist.', $user_id ),
					'repair'          => 'Delete orphan notification rows after user deletion cascade (repair sprint).',
				)
			);
		}
	}

	/**
	 * Rule 16 — author vs assignment skew for linked agents.
	 *
	 * @param array<int, \WP_User>             $users  Agent users.
	 * @param array<int, int[]>                $links  Links.
	 * @param array<int, array<string, mixed>> $agents Agents.
	 * @return void
	 */
	private function check_dashboard_skew( array $users, array $links, array $agents ): void {
		foreach ( $users as $user_id => $user ) {
			if ( empty( $links[ $user_id ] ) ) {
				// Already reported as missing agent — also dashboard skew.
				$this->add_issue(
					array(
						'rule_id'  => self::RULE_DASHBOARD_OWNERSHIP_SKEW,
						'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
						'title'    => 'Dashboard identity incomplete for agent-role user',
						'object'   => 'user#' . $user_id,
						'user_id'  => (int) $user_id,
						'file'     => 'includes/Workspace/Api/DashboardService.php',
						'why'      => 'Dashboard resolves profile via AgentIdentityService; missing CPT yields incomplete profile/stats.',
						'repair'   => 'Provision linked Agent CPT before relying on dashboard metrics.',
					)
				);
			}
		}

		// Properties assigned to an agent but authored by someone else → agent may edit but not see in author-scoped lists.
		$posts    = get_posts(
			array(
				'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft' ),
				'posts_per_page'         => 200,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
			)
		);
		$reported = 0;
		foreach ( $posts as $post ) {
			if ( $reported >= 25 || ! $post instanceof \WP_Post ) {
				break;
			}
			$assigned = get_post_meta( (int) $post->ID, AgentConstants::META_PROPERTY_AGENTS, true );
			if ( ! is_array( $assigned ) || empty( $assigned ) ) {
				continue;
			}
			$author = (int) $post->post_author;
			foreach ( $assigned as $agent_id ) {
				$agent_id = absint( $agent_id );
				if ( $agent_id <= 0 || empty( $agents[ $agent_id ] ) ) {
					continue;
				}
				$linked = (int) $agents[ $agent_id ]['linked_user'];
				if ( $linked > 0 && $author > 0 && $linked !== $author ) {
					$this->add_issue(
						array(
							'rule_id'     => self::RULE_DASHBOARD_OWNERSHIP_SKEW,
							'severity'    => AgentIdentityIssue::SEVERITY_MEDIUM,
							'title'       => 'Assigned agent is not property author',
							'object'      => 'hvnly_property#' . $post->ID,
							'property_id' => (int) $post->ID,
							'agent_id'    => $agent_id,
							'user_id'     => $linked,
							'file'        => 'includes/Workspace/Api/PropertyAccessGate.php',
							'why'         => sprintf(
								'Property author=%d but assigned agent CPT %d links to user %d. Edit may work; list/dashboard (author-scoped) may hide it.',
								$author,
								$agent_id,
								$linked
							),
							'repair'      => 'Align post_author with assigned agent user, or expand list queries to include assignments (product decision).',
						)
					);
					++$reported;
					break;
				}
			}
		}
	}

	/**
	 * Rule 17.
	 *
	 * @return void
	 */
	private function check_taxonomy_conflicts(): void {
		$taxonomy = 'hvnly_prop_agents';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$posts                               = get_posts(
			array(
				'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft' ),
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'tax_query'              => array(
					array(
						'taxonomy' => $taxonomy,
						'operator' => 'EXISTS',
					),
				),
				'update_post_meta_cache' => true,
			)
		);
		$this->stats['tax_term_assignments'] = count( $posts );

		foreach ( $posts as $pid ) {
			$pid      = (int) $pid;
			$assigned = get_post_meta( $pid, AgentConstants::META_PROPERTY_AGENTS, true );
			$has_ssot = is_array( $assigned ) && ! empty( $assigned );
			if ( $has_ssot ) {
				continue;
			}
			$this->add_issue(
				array(
					'rule_id'     => self::RULE_TAXONOMY_CONFLICT,
					'severity'    => AgentIdentityIssue::SEVERITY_MEDIUM,
					'title'       => 'Legacy agent taxonomy without Workspace SSOT meta',
					'object'      => 'hvnly_property#' . $pid,
					'property_id' => $pid,
					'file'        => 'includes/Database/Custom_Taxonomy/Property_Agents.php',
					'why'         => 'Property has hvnly_prop_agents terms but empty _hvnly_property_agents. Dual agent models.',
					'repair'      => 'Migrate tax terms to CPT assignment SSOT or clear legacy terms after confirming UI.',
				)
			);
		}
	}

	/**
	 * Rule 18.
	 *
	 * @return void
	 */
	private function check_legacy_property_agent_meta(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows                                  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' LIMIT 200",
				AgentConstants::META_PROPERTY_AGENT_LEGACY
			),
			ARRAY_A
		);
		$this->stats['legacy_agent_meta_rows'] = is_array( $rows ) ? count( $rows ) : 0;

		foreach ( (array) $rows as $row ) {
			$pid = absint( $row['post_id'] ?? 0 );
			$uid = absint( $row['meta_value'] ?? 0 );
			$this->add_issue(
				array(
					'rule_id'     => self::RULE_LEGACY_PROPERTY_AGENT,
					'severity'    => AgentIdentityIssue::SEVERITY_LOW,
					'title'       => 'Legacy _hvnly_property_agent meta present',
					'object'      => 'hvnly_property#' . $pid,
					'property_id' => $pid,
					'user_id'     => $uid,
					'file'        => 'includes/Agent/PropertyAgentResolver.php',
					'why'         => 'Legacy single-user agent meta still stored; resolver falls back when CPT list empty.',
					'repair'      => 'Migrate to _hvnly_property_agents CPT IDs then remove legacy meta (repair sprint).',
				)
			);
		}
	}

	/**
	 * Rule 19 — linked meta pointing at non-agent posts, etc.
	 *
	 * @return void
	 */
	private function check_orphan_link_meta(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				FROM {$wpdb->postmeta} pm
				LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND (p.ID IS NULL OR p.post_type <> %s)",
				AgentConstants::META_LINKED_USER_ID,
				AgentConstants::POST_TYPE
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$this->add_issue(
				array(
					'rule_id'  => self::RULE_ORPHAN_DATA,
					'severity' => AgentIdentityIssue::SEVERITY_MEDIUM,
					'title'    => 'linked_user_id meta on non-agent post',
					'object'   => 'post#' . absint( $row['post_id'] ?? 0 ),
					'user_id'  => absint( $row['meta_value'] ?? 0 ),
					'file'     => 'includes/Agent/AgentConstants.php',
					'why'      => 'Orphan postmeta: _hvnly_agent_linked_user_id exists outside hvnly_agent CPT.',
					'repair'   => 'Delete stray meta row (repair sprint).',
				)
			);
		}
	}
}
