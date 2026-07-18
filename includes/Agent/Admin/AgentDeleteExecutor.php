<?php
/**
 * Final Agent + User deletion after successful ownership transfer.
 *
 * @package HvnlyNab\Agent\Admin
 * @since   3.2.0
 */

namespace HvnlyNab\Agent\Admin;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\AgentProvisioner;
use HvnlyNab\Workspace\Notifications\NotificationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Step 5 executor. Sets an allow-flag so CPT delete is not blocked.
 *
 * @since 3.2.0
 */
final class AgentDeleteExecutor {

	public const LOG_OPTION = 'hvnly_agent_delete_log';

	/**
	 * When true, trash/delete of hvnly_agent is permitted.
	 *
	 * @var bool
	 */
	private static $allow = false;

	/**
	 * @return bool
	 */
	public static function is_delete_allowed(): bool {
		return self::$allow;
	}

	/**
	 * Run a callback with trash/delete guards temporarily lifted (provisioner rollback only).
	 *
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	public static function with_allowed_delete( callable $callback ) {
		$prev        = self::$allow;
		self::$allow = true;
		try {
			return $callback();
		} finally {
			self::$allow = $prev;
		}
	}

	/**
	 * Block accidental trash/delete outside the wizard.
	 *
	 * @return void
	 */
	public static function register_guards(): void {
		add_action( 'wp_trash_post', array( __CLASS__, 'guard_trash' ), 1 );
		add_action( 'before_delete_post', array( __CLASS__, 'guard_delete' ), 1 );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function guard_trash( $post_id ): void {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || self::$allow ) {
			return;
		}
		if ( AgentConstants::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		wp_die(
			esc_html__( 'Agents cannot be trashed directly. Use the Delete Wizard to transfer ownership first.', 'havenlytics' ),
			esc_html__( 'Delete Wizard required', 'havenlytics' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function guard_delete( $post_id ): void {
		self::guard_trash( $post_id );
	}

	/**
	 * @param int                  $agent_id Source agent.
	 * @param array<string, mixed> $target   Transfer target.
	 * @param array<string, mixed> $context  Inventory + transfer summary.
	 * @return true|\WP_Error
	 */
	public function execute( int $agent_id, array $target, array $context = array() ) {
		$agent_id = absint( $agent_id );
		$post     = get_post( $agent_id );
		if ( ! $post || AgentConstants::POST_TYPE !== $post->post_type ) {
			return new \WP_Error(
				'hvnly_delete_invalid_agent',
				__( 'Invalid Agent profile.', 'havenlytics' )
			);
		}

		$to_user = absint( $target['user_id'] ?? 0 );
		if ( $to_user <= 0 || ! get_userdata( $to_user ) ) {
			return new \WP_Error(
				'hvnly_delete_target_missing',
				__( 'Cannot delete: transfer target user is missing.', 'havenlytics' )
			);
		}

		$user_id = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );

		if ( $user_id > 0 && (int) $user_id === (int) $to_user ) {
			return new \WP_Error(
				'hvnly_delete_user_same_target',
				__( 'Cannot delete: linked user is the same as the transfer target.', 'havenlytics' )
			);
		}

		$remaining = ( new AgentDeleteInventoryService() )->find_assigned_property_ids( $agent_id );
		if ( ! empty( $remaining ) ) {
			return new \WP_Error(
				'hvnly_delete_incomplete_transfer',
				__( 'Cannot delete: properties are still assigned to this Agent. Transfer failed or incomplete.', 'havenlytics' )
			);
		}

		$provisioner = new AgentProvisioner();
		$unlinked    = $provisioner->set_linked_user(
			$agent_id,
			0,
			array( 'confirm_relink' => true )
		);
		if ( is_wp_error( $unlinked ) ) {
			return $unlinked;
		}

		// Clear any leftover notifications for this identity.
		( new NotificationRepository() )->delete_for_identity( $user_id, $agent_id );

		AgentIdentityService::get_instance()->clear_cache( $user_id > 0 ? $user_id : null );

		// Drop any in-progress wizard transient for this agent.
		delete_transient( 'hvnly_dw_' . get_current_user_id() . '_' . $agent_id );

		self::$allow = true;
		$deleted_cpt = wp_delete_post( $agent_id, true );
		self::$allow = false;

		if ( ! $deleted_cpt ) {
			return new \WP_Error(
				'hvnly_delete_cpt_failed',
				__( 'Could not delete the Agent CPT. Operation aborted before user deletion.', 'havenlytics' )
			);
		}

		if ( $user_id > 0 && get_userdata( $user_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			$ok = wp_delete_user( $user_id, $to_user );
			if ( ! $ok ) {
				$this->log(
					array(
						'ok'          => false,
						'agent_id'    => $agent_id,
						'user_id'     => $user_id,
						'error'       => 'user_delete_failed',
						'cpt_deleted' => true,
						'target'      => $target,
						'context'     => $context,
					)
				);
				return new \WP_Error(
					'hvnly_delete_user_failed',
					__( 'Agent CPT was deleted but the WordPress user could not be removed. Reassign remaining authored content manually.', 'havenlytics' )
				);
			}
		}

		AgentIdentityService::get_instance()->clear_cache( null );

		$this->log(
			array(
				'ok'        => true,
				'agent_id'  => $agent_id,
				'user_id'   => $user_id,
				'target'    => $target,
				'context'   => $context,
				'actor'     => get_current_user_id(),
				'at'        => gmdate( 'c' ),
			)
		);

		/**
		 * Fires after a successful Agent Delete Wizard completion.
		 *
		 * @since 3.2.0
		 *
		 * @param int                  $agent_id Deleted agent CPT ID.
		 * @param int                  $user_id  Deleted user ID (0 if none).
		 * @param array<string, mixed> $target   Transfer target.
		 */
		do_action( 'hvnly_agent_delete_wizard_completed', $agent_id, $user_id, $target );

		return true;
	}

	/**
	 * @param array<string, mixed> $entry Log entry.
	 * @return void
	 */
	private function log( array $entry ): void {
		$log = get_option( self::LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, 50 );
		update_option( self::LOG_OPTION, $log, false );
	}
}
