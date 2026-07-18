<?php
/**
 * Transfer Agent ownership before delete (Delete Wizard Steps 2–4).
 *
 * @package HvnlyNab\Agent\Admin
 * @since   3.2.0
 */

namespace HvnlyNab\Agent\Admin;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\PropertyAgentResolver;
use HvnlyNab\ContactAgent\InquiryRepository;
use HvnlyNab\Workspace\Api\PropertyFormMapper;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Notifications\NotificationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Transfers properties, inquiries, and notifications. Supports rollback.
 *
 * @since 3.2.0
 */
final class AgentOwnershipTransferService {

	/**
	 * Undo log for the current request.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $undo = array();

	/**
	 * Resolve transfer target.
	 *
	 * @param string $mode     agent|administrator.
	 * @param int    $agent_id Target agent CPT (when mode=agent).
	 * @return array{mode:string,agent_id:int,user_id:int,label:string}|\WP_Error
	 */
	public function resolve_target( string $mode, int $agent_id = 0 ) {
		$mode = sanitize_key( $mode );
		if ( 'administrator' === $mode ) {
			$uid = get_current_user_id();
			if ( $uid <= 0 || ! current_user_can( 'manage_options' ) ) {
				return new \WP_Error(
					'hvnly_delete_target_admin_required',
					__( 'Administrator transfer requires an administrator session.', 'havenlytics' )
				);
			}
			$linked = AgentIdentityService::get_instance()->get_agent_id( $uid );
			return array(
				'mode'     => 'administrator',
				'agent_id' => absint( $linked ),
				'user_id'  => $uid,
				'label'    => __( 'Administrator', 'havenlytics' ),
			);
		}

		if ( 'agent' !== $mode ) {
			return new \WP_Error(
				'hvnly_delete_target_invalid',
				__( 'Choose a transfer target.', 'havenlytics' )
			);
		}

		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 || AgentConstants::POST_TYPE !== get_post_type( $agent_id ) ) {
			return new \WP_Error(
				'hvnly_delete_target_missing',
				__( 'Transfer target Agent is missing.', 'havenlytics' )
			);
		}

		$user_id = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			return new \WP_Error(
				'hvnly_delete_target_no_user',
				__( 'Transfer target Agent must have a linked WordPress user.', 'havenlytics' )
			);
		}

		return array(
			'mode'     => 'agent',
			'agent_id' => $agent_id,
			'user_id'  => $user_id,
			'label'    => (string) get_the_title( $agent_id ),
		);
	}

	/**
	 * Execute transfer. Rolls back on failure.
	 *
	 * @param int                  $from_agent Source agent CPT.
	 * @param array<string, mixed> $target     From resolve_target().
	 * @param array<string, mixed> $inventory  From inventory service.
	 * @return array<string, mixed>|\WP_Error Result summary.
	 */
	public function transfer( int $from_agent, array $target, array $inventory ) {
		$from_agent = absint( $from_agent );
		$to_agent   = absint( $target['agent_id'] ?? 0 );
		$to_user    = absint( $target['user_id'] ?? 0 );
		$from_user  = absint( $inventory['user']['id'] ?? 0 );

		if ( $from_agent <= 0 || $to_user <= 0 ) {
			return new \WP_Error(
				'hvnly_delete_transfer_invalid',
				__( 'Transfer target missing.', 'havenlytics' )
			);
		}
		if ( $from_agent === $to_agent ) {
			return new \WP_Error(
				'hvnly_delete_transfer_same',
				__( 'Cannot transfer an Agent to itself.', 'havenlytics' )
			);
		}

		$this->undo = array();

		try {
			$prop_result = $this->transfer_properties( $from_agent, $from_user, $to_agent, $to_user, $inventory );
			if ( is_wp_error( $prop_result ) ) {
				throw new \RuntimeException( $prop_result->get_error_message(), 1 );
			}

			$inq = new InquiryRepository();
			$inq_n = $inq->reassign_agent(
				$from_agent,
				$to_agent,
				(string) ( $target['label'] ?? '' )
			);
			if ( is_wp_error( $inq_n ) ) {
				throw new \RuntimeException( $inq_n->get_error_message(), 2 );
			}
			$this->undo[] = array(
				'type' => 'inquiries',
				'from' => $from_agent,
				'to'   => $to_agent,
				'name' => (string) get_the_title( $from_agent ),
				'n'    => (int) $inq_n,
			);

			$reply_n = $inq->reassign_reply_authors( $from_user, $to_user );
			if ( is_wp_error( $reply_n ) ) {
				throw new \RuntimeException( $reply_n->get_error_message(), 2 );
			}
			if ( $from_user > 0 && (int) $reply_n > 0 ) {
				$this->undo[] = array(
					'type'      => 'inquiry_replies',
					'from_user' => $from_user,
					'to_user'   => $to_user,
					'n'         => (int) $reply_n,
				);
			}

			$notif = new NotificationRepository();
			$notif_n = $notif->reassign_identity( $from_user, $from_agent, $to_user, $to_agent );
			if ( is_wp_error( $notif_n ) ) {
				throw new \RuntimeException( $notif_n->get_error_message(), 3 );
			}
			$this->undo[] = array(
				'type'       => 'notifications',
				'from_user'  => $from_user,
				'from_agent' => $from_agent,
				'to_user'    => $to_user,
				'to_agent'   => $to_agent,
				'n'          => (int) $notif_n,
			);

			return array(
				'ok'              => true,
				'properties'      => $prop_result,
				'inquiries'       => (int) $inq_n,
				'inquiry_replies' => (int) $reply_n,
				'notifications'   => (int) $notif_n,
				'target'          => $target,
			);
		} catch ( \Throwable $e ) {
			$this->rollback();
			return new \WP_Error(
				'hvnly_delete_transfer_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Transfer failed and was rolled back: %s', 'havenlytics' ),
					$e->getMessage()
				),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * @param int                  $from_agent From agent.
	 * @param int                  $from_user  From user.
	 * @param int                  $to_agent   To agent (0 = admin without CPT).
	 * @param int                  $to_user    To user.
	 * @param array<string, mixed> $inventory  Inventory.
	 * @return array<string, int>|\WP_Error
	 */
	private function transfer_properties( int $from_agent, int $from_user, int $to_agent, int $to_user, array $inventory ) {
		$ids = isset( $inventory['properties']['ids'] ) && is_array( $inventory['properties']['ids'] )
			? array_map( 'absint', $inventory['properties']['ids'] )
			: array();

		$moved = 0;
		foreach ( $ids as $property_id ) {
			$property_id = absint( $property_id );
			if ( $property_id <= 0 ) {
				continue;
			}

			$post = get_post( $property_id );
			if ( ! $post || AgentConstants::PROPERTY_POST_TYPE !== $post->post_type ) {
				continue;
			}

			$before_agents = get_post_meta( $property_id, AgentConstants::META_PROPERTY_AGENTS, true );
			$before_agents = is_array( $before_agents ) ? array_map( 'absint', $before_agents ) : array();
			$before_author = (int) $post->post_author;
			$before_legacy = absint( get_post_meta( $property_id, AgentConstants::META_PROPERTY_AGENT_LEGACY, true ) );
			$builder_snap  = array();
			foreach ( PropertyFormMapper::agents_storage_field_names() as $field ) {
				$builder_snap[ $field ] = get_post_meta( $property_id, $field, true );
			}

			$new_ids = array();
			foreach ( $before_agents as $aid ) {
				if ( (int) $aid === $from_agent ) {
					if ( $to_agent > 0 && ! in_array( $to_agent, $new_ids, true ) ) {
						$new_ids[] = $to_agent;
					}
					continue;
				}
				if ( $aid > 0 && ! in_array( $aid, $new_ids, true ) ) {
					$new_ids[] = $aid;
				}
			}
			// Author-only properties: ensure target is assigned when possible.
			if ( empty( $new_ids ) && $to_agent > 0 && (int) $post->post_author === $from_user ) {
				$new_ids[] = $to_agent;
			}

			$new_ids = PropertyAgentResolver::sanitize_agent_id_list( $new_ids );

			update_post_meta( $property_id, AgentConstants::META_PROPERTY_AGENTS, $new_ids );
			foreach ( PropertyFormMapper::agents_storage_field_names() as $field ) {
				update_post_meta( $property_id, $field, wp_json_encode( $new_ids ) );
			}

			if ( $from_user > 0 && $before_author === $from_user ) {
				wp_update_post(
					array(
						'ID'          => $property_id,
						'post_author' => $to_user,
					)
				);
			}

			if ( $from_user > 0 && $before_legacy === $from_user ) {
				update_post_meta( $property_id, AgentConstants::META_PROPERTY_AGENT_LEGACY, $to_user );
			}

			// Record undo BEFORE side-effect hooks so a throwing listener can still roll back.
			$this->undo[] = array(
				'type'          => 'property',
				'property_id'   => $property_id,
				'agents'        => $before_agents,
				'author'        => $before_author,
				'legacy'        => $before_legacy,
				'builder'       => $builder_snap,
			);

			do_action( 'hvnly_property_agents_saved', $property_id, $new_ids );

			++$moved;
		}

		return array( 'moved' => $moved );
	}

	/**
	 * Roll back transfers recorded in $this->undo (LIFO).
	 *
	 * @return void
	 */
	public function rollback(): void {
		$steps = array_reverse( $this->undo );
		$this->undo = array();

		$inq   = new InquiryRepository();
		$notif = new NotificationRepository();

		foreach ( $steps as $step ) {
			$type = (string) ( $step['type'] ?? '' );
			if ( 'property' === $type ) {
				$pid = absint( $step['property_id'] ?? 0 );
				if ( $pid <= 0 ) {
					continue;
				}
				$agents = isset( $step['agents'] ) && is_array( $step['agents'] ) ? $step['agents'] : array();
				update_post_meta( $pid, AgentConstants::META_PROPERTY_AGENTS, $agents );
				$builder = isset( $step['builder'] ) && is_array( $step['builder'] ) ? $step['builder'] : array();
				foreach ( $builder as $field => $value ) {
					update_post_meta( $pid, (string) $field, $value );
				}
				wp_update_post(
					array(
						'ID'          => $pid,
						'post_author' => absint( $step['author'] ?? 0 ),
					)
				);
				$legacy = absint( $step['legacy'] ?? 0 );
				if ( $legacy > 0 ) {
					update_post_meta( $pid, AgentConstants::META_PROPERTY_AGENT_LEGACY, $legacy );
				} else {
					delete_post_meta( $pid, AgentConstants::META_PROPERTY_AGENT_LEGACY );
				}
				continue;
			}

			if ( 'inquiries' === $type ) {
				$inq->reassign_agent(
					absint( $step['to'] ?? 0 ),
					absint( $step['from'] ?? 0 ),
					(string) ( $step['name'] ?? '' )
				);
				continue;
			}

			if ( 'inquiry_replies' === $type ) {
				$inq->reassign_reply_authors(
					absint( $step['to_user'] ?? 0 ),
					absint( $step['from_user'] ?? 0 )
				);
				continue;
			}

			if ( 'notifications' === $type ) {
				$notif->reassign_identity(
					absint( $step['to_user'] ?? 0 ),
					absint( $step['to_agent'] ?? 0 ),
					absint( $step['from_user'] ?? 0 ),
					absint( $step['from_agent'] ?? 0 )
				);
			}
		}
	}
}
