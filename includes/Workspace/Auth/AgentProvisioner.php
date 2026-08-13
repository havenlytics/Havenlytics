<?php
/**
 * Creates / links Agent CPT for a Workspace WordPress user.
 *
 * Sole authorized writer of {@see AgentConstants::META_LINKED_USER_ID}.
 * Enforces 1:1 User ⇄ Agent identity (Sprint 19D).
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\PropertyAgentResolver;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Provision an Agent profile for a newly registered Workspace user.
 *
 * @since 3.2.0
 */
final class AgentProvisioner {

	/**
	 * When true, metadata guard allows META_LINKED_USER_ID writes.
	 *
	 * @var bool
	 */
	private static $allow_link_write = false;

	/**
	 * Register metadata write guard (blocks unauthorized linked_user_id writes).
	 *
	 * @return void
	 */
	public static function register_write_guard(): void {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;
		add_filter( 'update_post_metadata', array( __CLASS__, 'guard_linked_user_metadata' ), 0, 5 );
		add_filter( 'add_post_metadata', array( __CLASS__, 'guard_linked_user_metadata' ), 0, 5 );
		add_filter( 'delete_post_metadata', array( __CLASS__, 'guard_linked_user_metadata' ), 0, 5 );
	}

	/**
	 * Block direct writes to linked_user_id unless {@see self::with_authorized_link_write()}.
	 *
	 * @param null|bool $check      Filter short-circuit.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value (unused).
	 * @param mixed     $prev_value Prev value (unused).
	 * @return null|bool
	 */
	public static function guard_linked_user_metadata( $check, $object_id, $meta_key, $meta_value = null, $prev_value = null ) {
		unset( $object_id, $meta_value, $prev_value );
		if ( AgentConstants::META_LINKED_USER_ID !== (string) $meta_key ) {
			return $check;
		}
		if ( self::$allow_link_write ) {
			return $check;
		}
		// Non-null short-circuits the DB write (update_post_meta returns false).
		return false;
	}

	/**
	 * Run a callback with authorized identity link writes.
	 *
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	public static function with_authorized_link_write( callable $callback ) {
		$prev                   = self::$allow_link_write;
		self::$allow_link_write = true;
		try {
			return $callback();
		} finally {
			self::$allow_link_write = $prev;
		}
	}

	/**
	 * Ensure the user has exactly one linked Agent CPT (create if missing).
	 * Idempotent: 100 calls return the same Agent ID.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args    Optional: display_name, user_email, status (publish|pending).
	 * @return int|WP_Error Agent post ID.
	 */
	public function ensure_agent_for_user( int $user_id, array $args = array() ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return new WP_Error(
				'hvnly_agent_provision_invalid_user',
				__( 'Invalid user.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'hvnly_agent_provision_invalid_user',
				__( 'Invalid user.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$linked_ids = $this->find_all_linked_agent_ids( $user_id );
		if ( count( $linked_ids ) > 1 ) {
			return new WP_Error(
				'hvnly_identity_duplicate_agents',
				sprintf(
					/* translators: %s: comma-separated agent IDs */
					__( 'Identity conflict: user is linked to multiple Agent CPTs (%s). Resolve manually before provisioning.', 'havenlytics' ),
					implode( ', ', $linked_ids )
				),
				array(
					'status'    => 409,
					'agent_ids' => $linked_ids,
				)
			);
		}

		if ( 1 === count( $linked_ids ) ) {
			$agent_id = (int) $linked_ids[0];
			$this->sync_defaults( $agent_id, $user_id, $user, $args );
			return $agent_id;
		}

		// Also check published resolver path (same meta).
		$existing = PropertyAgentResolver::get_cpt_agents_for_user( $user_id );
		if ( ! empty( $existing[0] ) && is_array( $existing[0] ) ) {
			$agent_id = isset( $existing[0]['id'] ) ? absint( $existing[0]['id'] ) : 0;
			if ( $agent_id <= 0 && isset( $existing[0]['ID'] ) ) {
				$agent_id = absint( $existing[0]['ID'] );
			}
			if ( $agent_id > 0 ) {
				$this->sync_defaults( $agent_id, $user_id, $user, $args );
				return $agent_id;
			}
		}

		$lock_key = 'hvnly_ea4u_' . $user_id;
		if ( get_transient( $lock_key ) ) {
			usleep( 250000 );
			$again = $this->find_all_linked_agent_ids( $user_id );
			if ( count( $again ) >= 1 ) {
				$agent_id = (int) $again[0];
				$this->sync_defaults( $agent_id, $user_id, $user, $args );
				return $agent_id;
			}
			return new WP_Error(
				'hvnly_identity_agent_provision_busy',
				__( 'Agent profile creation is already in progress for this user. Refresh and try again.', 'havenlytics' ),
				array( 'status' => 409 )
			);
		}
		set_transient( $lock_key, 1, 30 );

		try {
			return $this->ensure_agent_for_user_locked( $user_id, $user, $args );
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param \WP_User             $user    User.
	 * @param array<string, mixed> $args    Args.
	 * @return int|WP_Error
	 */
	private function ensure_agent_for_user_locked( int $user_id, $user, array $args ) {
		// Re-check under lock (double Add User / concurrent AJAX).
		$again = $this->find_all_linked_agent_ids( $user_id );
		if ( count( $again ) >= 1 ) {
			$agent_id = (int) $again[0];
			$this->sync_defaults( $agent_id, $user_id, $user, $args );
			return $agent_id;
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'publish';
		if ( ! in_array( $status, array( 'publish', 'pending', 'draft' ), true ) ) {
			$status = 'publish';
		}

		$display = isset( $args['display_name'] ) && is_string( $args['display_name'] ) && $args['display_name'] !== ''
			? sanitize_text_field( $args['display_name'] )
			: (string) $user->display_name;
		if ( $display === '' ) {
			$display = (string) $user->user_login;
		}

		$email = isset( $args['user_email'] ) ? sanitize_email( (string) $args['user_email'] ) : (string) $user->user_email;

		$agent_id = self::without_auto_user_provision(
			static function () use ( $status, $display, $user_id ) {
				return wp_insert_post(
					array(
						'post_type'   => AgentConstants::POST_TYPE,
						'post_status' => $status,
						'post_title'  => $display,
						'post_author' => $user_id,
					),
					true
				);
			}
		);

		if ( is_wp_error( $agent_id ) || ! $agent_id ) {
			return new WP_Error(
				'hvnly_agent_provision_failed',
				__( 'Could not create agent profile.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		$agent_id = (int) $agent_id;

		// Another request may have linked while we inserted — do not create a second link.
		$race = $this->find_all_linked_agent_ids( $user_id );
		if ( count( $race ) >= 1 && (int) $race[0] !== $agent_id ) {
			$this->hard_delete_agent_cpt( $agent_id );
			$winner = (int) $race[0];
			$this->sync_defaults( $winner, $user_id, $user, $args );
			return $winner;
		}

		$linked = $this->set_linked_user(
			$agent_id,
			$user_id,
			array(
				'confirm_relink' => true, // First link on a brand-new CPT.
				'source'         => 'provisioner',
			)
		);
		if ( is_wp_error( $linked ) ) {
			$this->hard_delete_agent_cpt( $agent_id );
			return $linked;
		}

		$this->sync_defaults( $agent_id, $user_id, $user, array_merge( $args, array(
			'force_registered_at' => true,
			'skip_link' => true,
		) ) );

		if ( $email !== '' ) {
			update_post_meta( $agent_id, AgentConstants::META_EMAIL, $email );
		}

		/**
		 * Fires after Workspace provisions an Agent CPT for a user.
		 *
		 * @since 3.2.0
		 *
		 * @param int $agent_id Agent post ID.
		 * @param int $user_id  User ID.
		 */
		do_action( 'hvnly_workspace_agent_provisioned', $agent_id, $user_id );

		AgentIdentityService::get_instance()->clear_cache( $user_id );

		return $agent_id;
	}

	/**
	 * Sole public API to set / clear Agent ↔ User link (1:1 enforced).
	 *
	 * @param int                  $agent_id Agent CPT ID.
	 * @param int                  $user_id  WP user ID (0 = unlink).
	 * @param array<string, mixed> $args     confirm_relink (bool) required when changing an existing link.
	 * @return true|WP_Error
	 */
	public function set_linked_user( int $agent_id, int $user_id, array $args = array() ) {
		$agent_id = absint( $agent_id );
		$user_id  = absint( $user_id );
		$confirm  = ! empty( $args['confirm_relink'] );

		if ( $agent_id <= 0 || AgentConstants::POST_TYPE !== get_post_type( $agent_id ) ) {
			return new WP_Error(
				'hvnly_identity_invalid_agent',
				__( 'Invalid Agent CPT.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$current = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );

		if ( $current === $user_id ) {
			return true;
		}

		// Identity lock: changing or clearing an existing link requires explicit confirmation.
		if ( $current > 0 && $current !== $user_id && ! $confirm ) {
			return new WP_Error(
				'hvnly_identity_relink_confirmation_required',
				__( 'Changing the linked WordPress user requires explicit administrator confirmation.', 'havenlytics' ),
				array(
					'status'           => 409,
					'current_user_id'  => $current,
					'requested_user_id' => $user_id,
				)
			);
		}

		if ( $user_id > 0 ) {
			if ( ! get_userdata( $user_id ) ) {
				return new WP_Error(
					'hvnly_identity_user_missing',
					__( 'Cannot link: WordPress user does not exist.', 'havenlytics' ),
					array( 'status' => 400 )
				);
			}

			$owners = $this->find_all_linked_agent_ids( $user_id );
			foreach ( $owners as $owner_id ) {
				if ( (int) $owner_id !== $agent_id ) {
					return new WP_Error(
						'hvnly_identity_user_already_linked',
						sprintf(
							/* translators: %d: agent post ID */
							__( 'Already linked: this WordPress user is already linked to Agent #%d. Duplicate identity is not allowed.', 'havenlytics' ),
							(int) $owner_id
						),
						array(
							'status'           => 409,
							'existing_agent_id' => (int) $owner_id,
						)
					);
				}
			}
		}

		$ok = self::with_authorized_link_write(
			static function () use ( $agent_id, $user_id ) {
				if ( $user_id <= 0 ) {
					delete_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID );
					return true;
				}
				return false !== update_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, $user_id );
			}
		);

		if ( ! $ok ) {
			return new WP_Error(
				'hvnly_identity_link_write_failed',
				__( 'Could not save Agent identity link.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		if ( $current > 0 ) {
			AgentIdentityService::get_instance()->clear_cache( $current );
		}
		if ( $user_id > 0 ) {
			AgentIdentityService::get_instance()->clear_cache( $user_id );
		}

		/**
		 * Fires after a 1:1 identity link is written.
		 *
		 * @since 3.2.0
		 *
		 * @param int $agent_id Agent ID.
		 * @param int $user_id  New user ID (0 = unlinked).
		 * @param int $previous Previous user ID.
		 */
		do_action( 'hvnly_workspace_identity_link_set', $agent_id, $user_id, $current );

		return true;
	}

	/**
	 * Map of user_id => agent_id for users already linked (for admin UI).
	 *
	 * @param int $exclude_agent_id Exclude this agent from the map.
	 * @return array<int, int>
	 */
	public function get_occupied_user_links( int $exclude_agent_id = 0 ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s
				AND p.post_type = %s
				AND p.post_status NOT IN ('auto-draft')
				AND pm.meta_value > 0",
				AgentConstants::META_LINKED_USER_ID,
				AgentConstants::POST_TYPE
			),
			ARRAY_A
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			$aid = absint( $row['post_id'] ?? 0 );
			$uid = absint( $row['meta_value'] ?? 0 );
			if ( $uid <= 0 || $aid === absint( $exclude_agent_id ) ) {
				continue;
			}
			$map[ $uid ] = $aid;
		}
		return $map;
	}

	/**
	 * Populate / refresh default Agent fields for a linked user.
	 *
	 * @param int                  $agent_id Agent ID.
	 * @param int                  $user_id  User ID.
	 * @param \WP_User             $user     User.
	 * @param array<string, mixed> $args     Args.
	 * @return void
	 */
	private function sync_defaults( int $agent_id, int $user_id, $user, array $args = array() ): void {
		if ( empty( $args['skip_link'] ) ) {
			// Re-affirm same link (idempotent; no confirmation needed when unchanged).
			$this->set_linked_user( $agent_id, $user_id, array( 'confirm_relink' => true ) );
		}

		$email = (string) $user->user_email;
		if ( $email !== '' && ! get_post_meta( $agent_id, AgentConstants::META_EMAIL, true ) ) {
			update_post_meta( $agent_id, AgentConstants::META_EMAIL, $email );
		}

		$position = (string) get_post_meta( $agent_id, AgentConstants::META_POSITION, true );
		if ( '' === $position ) {
			update_post_meta( $agent_id, AgentConstants::META_POSITION, __( 'Real Estate Agent', 'havenlytics' ) );
		}

		$force_registered = ! empty( $args['force_registered_at'] );
		$registered       = (string) get_post_meta( $agent_id, AgentConstants::META_REGISTERED_AT, true );
		if ( $force_registered || '' === $registered ) {
			update_post_meta( $agent_id, AgentConstants::META_REGISTERED_AT, gmdate( 'c' ) );
		}

		AgentIdentityService::get_instance()->clear_cache( $user_id );
	}

	/**
	 * When true, admin auto-provision hooks skip ensure_agent_for_user
	 * (prevents creating a second CPT while linking a new user to an existing Agent).
	 *
	 * @var bool
	 */
	private static $suppress_auto_agent_provision = false;

	/**
	 * When true, admin auto-provision hooks skip ensure_user_for_agent
	 * (prevents FLOW A while FLOW B inserts a published Agent CPT).
	 *
	 * @var bool
	 */
	private static $suppress_auto_user_provision = false;

	/**
	 * @return bool
	 */
	public static function is_auto_agent_provision_suppressed(): bool {
		return self::$suppress_auto_agent_provision;
	}

	/**
	 * @return bool
	 */
	public static function is_auto_user_provision_suppressed(): bool {
		return self::$suppress_auto_user_provision;
	}

	/**
	 * Run a callback without User→Agent auto-provision side effects.
	 *
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	public static function without_auto_agent_provision( callable $callback ) {
		$prev                                = self::$suppress_auto_agent_provision;
		self::$suppress_auto_agent_provision = true;
		try {
			return $callback();
		} finally {
			self::$suppress_auto_agent_provision = $prev;
		}
	}

	/**
	 * Run a callback without Agent→User auto-provision side effects.
	 *
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	public static function without_auto_user_provision( callable $callback ) {
		$prev                               = self::$suppress_auto_user_provision;
		self::$suppress_auto_user_provision = true;
		try {
			return $callback();
		} finally {
			self::$suppress_auto_user_provision = $prev;
		}
	}

	/**
	 * Ensure an Agent CPT has a linked Workspace WP User (create if missing).
	 * Idempotent when already linked. Never creates a second Agent CPT.
	 *
	 * @param int                  $agent_id Agent CPT ID.
	 * @param array<string, mixed> $args     Optional: user_login, user_email, send_reset (bool), display_name, registration_status.
	 * @return int|WP_Error User ID.
	 */
	public function ensure_user_for_agent( int $agent_id, array $args = array() ) {
		$agent_id = absint( $agent_id );
		$post     = get_post( $agent_id );
		if ( ! $post || AgentConstants::POST_TYPE !== $post->post_type ) {
			return new WP_Error(
				'hvnly_identity_invalid_agent',
				__( 'Invalid Agent CPT.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$existing_uid = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $existing_uid > 0 && get_userdata( $existing_uid ) ) {
			return $existing_uid;
		}

		$lock_key = 'hvnly_eu4a_' . $agent_id;
		if ( get_transient( $lock_key ) ) {
			// Concurrent request in progress — wait briefly then re-read link.
			usleep( 250000 );
			$existing_uid = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
			if ( $existing_uid > 0 && get_userdata( $existing_uid ) ) {
				return $existing_uid;
			}
			return new WP_Error(
				'hvnly_identity_user_provision_busy',
				__( 'Workspace account creation is already in progress for this Agent. Refresh and try again.', 'havenlytics' ),
				array( 'status' => 409 )
			);
		}
		set_transient( $lock_key, 1, 30 );

		try {
			return $this->ensure_user_for_agent_locked( $agent_id, $post, $args );
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * @param int                  $agent_id Agent ID.
	 * @param \WP_Post             $post     Agent post.
	 * @param array<string, mixed> $args     Args.
	 * @return int|WP_Error
	 */
	private function ensure_user_for_agent_locked( int $agent_id, $post, array $args ) {
		// Re-check under lock (double Publish / concurrent AJAX).
		$existing_uid = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $existing_uid > 0 ) {
			if ( get_userdata( $existing_uid ) ) {
				return $existing_uid;
			}
			$cleared = $this->set_linked_user( $agent_id, 0, array( 'confirm_relink' => true ) );
			if ( is_wp_error( $cleared ) ) {
				return $cleared;
			}
		}

		$email = isset( $args['user_email'] ) ? sanitize_email( (string) $args['user_email'] ) : '';
		if ( '' === $email ) {
			$email = sanitize_email( (string) get_post_meta( $agent_id, AgentConstants::META_EMAIL, true ) );
		}
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error(
				'hvnly_identity_agent_email_required',
				__( 'Agent must have a valid email before creating a Workspace account.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( email_exists( $email ) ) {
			return new WP_Error(
				'hvnly_identity_email_taken',
				sprintf(
					/* translators: %s: email */
					__( 'A WordPress user already exists with email %s. Link that user instead of creating a new account.', 'havenlytics' ),
					$email
				),
				array( 'status' => 409 )
			);
		}

		$login = isset( $args['user_login'] ) ? sanitize_user( (string) $args['user_login'], true ) : '';
		if ( '' === $login ) {
			$login = sanitize_user( (string) $post->post_name, true );
		}
		if ( '' === $login ) {
			$login = sanitize_user( sanitize_title( (string) $post->post_title ), true );
		}
		if ( '' === $login ) {
			$login = 'agent' . $agent_id;
		}
		$base_login = $login;
		$n          = 1;
		while ( username_exists( $login ) ) {
			$login = $base_login . $n;
			++$n;
			if ( $n > 50 ) {
				return new WP_Error(
					'hvnly_identity_login_unavailable',
					__( 'Could not allocate a unique username for this Agent.', 'havenlytics' ),
					array( 'status' => 409 )
				);
			}
		}

		$display = isset( $args['display_name'] ) ? sanitize_text_field( (string) $args['display_name'] ) : '';
		if ( '' === $display ) {
			$display = (string) $post->post_title;
		}
		if ( '' === $display ) {
			$display = $login;
		}

		$password = wp_generate_password( 24, true, true );
		( new CapabilityRegistrar() )->ensure_agent_role();

		$user_id = self::without_auto_agent_provision(
			static function () use ( $login, $email, $password, $display ) {
				return wp_insert_user(
					array(
						'user_login'   => $login,
						'user_email'   => $email,
						'user_pass'    => $password,
						'display_name' => $display,
						'role'         => PortalCapabilities::WP_ROLE_AGENT,
					)
				);
			}
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		$user_id = (int) $user_id;

		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();
			if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
				add_user_to_blog( $blog_id, $user_id, PortalCapabilities::WP_ROLE_AGENT );
			}
		}

		// Another request may have linked while we created the user — do not steal the link.
		$race_uid = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $race_uid > 0 && $race_uid !== $user_id && get_userdata( $race_uid ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			return $race_uid;
		}

		$linked = $this->set_linked_user(
			$agent_id,
			$user_id,
			array(
				'confirm_relink' => true,
				'source'         => 'ensure_user_for_agent',
			)
		);
		if ( is_wp_error( $linked ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			return $linked;
		}

		WorkspaceRegistrationStatus::set_for_user(
			$user_id,
			isset( $args['registration_status'] )
				? WorkspaceRegistrationStatus::normalize( (string) $args['registration_status'] )
				: WorkspaceRegistrationStatus::PENDING,
			$agent_id
		);

		$send_reset = array_key_exists( 'send_reset', $args ) ? (bool) $args['send_reset'] : true;
		/**
		 * Filter whether to send a password-reset email after admin creates a Workspace account.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $send_reset Default from args.
		 * @param int  $user_id    New user.
		 * @param int  $agent_id   Agent CPT.
		 */
		$send_reset = (bool) apply_filters( 'hvnly_send_workspace_account_reset_email', $send_reset, $user_id, $agent_id );
		if ( $send_reset ) {
			if ( ! function_exists( 'retrieve_password' ) ) {
				require_once ABSPATH . 'wp-includes/user.php';
			}
			retrieve_password( $login );
		}

		AgentIdentityService::get_instance()->clear_cache( $user_id );

		/**
		 * Fires after a Workspace user is created for an existing Agent CPT.
		 *
		 * @since 3.2.0
		 *
		 * @param int $user_id  User ID.
		 * @param int $agent_id Agent CPT ID.
		 */
		do_action( 'hvnly_workspace_user_provisioned_for_agent', $user_id, $agent_id );

		return $user_id;
	}

	/**
	 * Hard-delete a just-created Agent CPT during provisioner rollback (bypasses Delete Wizard guard).
	 *
	 * @param int $agent_id Agent CPT ID.
	 * @return void
	 */
	private function hard_delete_agent_cpt( int $agent_id ): void {
		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 ) {
			return;
		}
		$delete = static function () use ( $agent_id ) {
			wp_delete_post( $agent_id, true );
		};
		if ( class_exists( '\HvnlyNab\Agent\Admin\AgentDeleteExecutor' ) ) {
			\HvnlyNab\Agent\Admin\AgentDeleteExecutor::with_allowed_delete( $delete );
			return;
		}
		$delete();
	}

	/**
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	public function find_all_linked_agent_ids( int $user_id ): array {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => array( 'publish', 'pending', 'draft', 'private', 'trash' ),
				'posts_per_page'         => 50,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
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

		return array_map( 'intval', $query->posts );
	}
}
