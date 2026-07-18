<?php
/**
 * Inventory of everything owned by an Agent identity (Delete Wizard Step 1).
 *
 * @package HvnlyNab\Agent\Admin
 * @since   3.2.0
 */

namespace HvnlyNab\Agent\Admin;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ContactAgent\InquiryRepository;
use HvnlyNab\Workspace\Api\PropertyFormMapper;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;
use HvnlyNab\Workspace\Notifications\NotificationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only ownership audit for Delete Wizard.
 *
 * @since 3.2.0
 */
final class AgentDeleteInventoryService {

	/**
	 * @param int $agent_id Agent CPT ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function inventory( int $agent_id ) {
		$agent_id = absint( $agent_id );
		$post     = get_post( $agent_id );
		if ( ! $post || AgentConstants::POST_TYPE !== $post->post_type ) {
			return new \WP_Error(
				'hvnly_delete_invalid_agent',
				__( 'Invalid Agent profile.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$user_id = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

		$props = $this->property_inventory( $agent_id, $user_id );
		$inq   = new InquiryRepository();
		$notif = new NotificationRepository();

		$inquiry_total = $inq->count( array( 'agent_id' => $agent_id ) );
		$notif_total   = $notif->count_for_identity( $user_id, $agent_id );

		$thumb_id = (int) get_post_thumbnail_id( $agent_id );
		$media    = array(
			'featured_image_id' => $thumb_id,
			'attachments'       => $this->count_attachments_for_user( $user_id ),
		);

		return array(
			'agent' => array(
				'id'     => $agent_id,
				'title'  => (string) get_the_title( $agent_id ),
				'status' => (string) $post->post_status,
			),
			'user'  => array(
				'id'       => $user_id,
				'login'    => $user ? (string) $user->user_login : '',
				'email'    => $user ? (string) $user->user_email : '',
				'exists'   => (bool) $user,
				'lifecycle'=> $user_id > 0 ? WorkspaceRegistrationStatus::get_for_user( $user_id ) : '',
			),
			'properties'    => $props,
			'inquiries'     => array(
				'total' => $inquiry_total,
			),
			'notifications' => array(
				'total' => $notif_total,
			),
			'media'         => $media,
			'crm_future'    => array(
				'note' => __( 'No CRM records in this release. Reserved for future modules.', 'havenlytics' ),
			),
		);
	}

	/**
	 * @param int $agent_id Agent CPT ID.
	 * @param int $user_id  Linked user ID.
	 * @return array<string, mixed>
	 */
	private function property_inventory( int $agent_id, int $user_id ): array {
		global $wpdb;

		$buckets = array(
			'publish'  => array(),
			'pending'  => array(),
			'draft'    => array(),
			'rejected' => array(),
			'trash'    => array(),
			'other'    => array(),
		);

		$assigned_ids = $this->find_assigned_property_ids( $agent_id );
		$author_ids   = array();
		if ( $user_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$author_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = %s AND post_author = %d
					AND post_status IN ('publish','pending','draft','private','trash')",
					AgentConstants::PROPERTY_POST_TYPE,
					$user_id
				)
			);
			$author_ids = array_map( 'absint', (array) $author_ids );
		}

		$all_ids = array_values( array_unique( array_merge( $assigned_ids, $author_ids ) ) );
		foreach ( $all_ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post ) {
				continue;
			}
			$item = array(
				'id'       => $pid,
				'title'    => (string) $post->post_title,
				'status'   => (string) $post->post_status,
				'author'   => (int) $post->post_author,
				'assigned' => in_array( $pid, $assigned_ids, true ),
			);
			$label = strtolower( (string) get_post_meta( $pid, PropertyFormMapper::META_LISTING_STATUS, true ) );
			if ( false !== strpos( $label, 'reject' ) ) {
				$buckets['rejected'][] = $item;
			} elseif ( isset( $buckets[ $post->post_status ] ) ) {
				$buckets[ $post->post_status ][] = $item;
			} elseif ( 'private' === $post->post_status ) {
				$buckets['draft'][] = $item;
			} else {
				$buckets['other'][] = $item;
			}
		}

		$counts = array();
		$total  = 0;
		foreach ( $buckets as $key => $items ) {
			$counts[ $key ] = count( $items );
			$total         += count( $items );
		}
		$counts['total'] = $total;

		return array(
			'counts' => $counts,
			'items'  => $buckets,
			'ids'    => $all_ids,
		);
	}

	/**
	 * @param int $agent_id Agent CPT ID.
	 * @return int[]
	 */
	public function find_assigned_property_ids( int $agent_id ): array {
		global $wpdb;
		$agent_id = absint( $agent_id );
		if ( $agent_id <= 0 ) {
			return array();
		}

		$sql = $wpdb->prepare(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type = %s
			AND p.post_status IN ('publish','pending','draft','private','trash')
			AND pm.meta_key = %s
			AND (
				pm.meta_value LIKE %s
				OR pm.meta_value LIKE %s
				OR pm.meta_value LIKE %s
				OR pm.meta_value LIKE %s
				OR pm.meta_value LIKE %s
			)",
			AgentConstants::PROPERTY_POST_TYPE,
			AgentConstants::META_PROPERTY_AGENTS,
			'%i:' . $agent_id . ';%',
			'%[' . $agent_id . ']%',
			'%[' . $agent_id . ',%',
			'%,' . $agent_id . ']%',
			'%,' . $agent_id . ',%'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( $sql );
		return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	}

	/**
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function count_attachments_for_user( int $user_id ): int {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}
		$q = new \WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'author'                 => $user_id,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		return (int) $q->found_posts;
	}
}
