<?php
/**
 * Workspace Notification Service.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\Notifications\NotificationRepository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
final class NotificationService {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var NotificationRepository
	 */
	private $repo;

	/**
	 * @param AgentIdentityService|null   $identity Identity.
	 * @param PortalAuthorization|null    $auth     Authz.
	 * @param NotificationRepository|null $repo     Repo.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?NotificationRepository $repo = null
	) {
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth     = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->repo     = $repo ? $repo : new NotificationRepository();
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $query   Query.
	 * @return array<string, mixed>|WP_Error
	 */
	public function list_notifications( int $user_id, array $query = array() ) {
		$user_id = absint( $user_id );
		if ( ! $this->auth->can_access_workspace( $user_id ) ) {
			return new WP_Error(
				'hvnly_notifications_forbidden',
				__( 'You cannot view notifications.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$page     = max( 1, absint( $query['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, absint( $query['perPage'] ?? $query['per_page'] ?? 20 ) ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? '' ) );
		$category = sanitize_key( (string) ( $query['category'] ?? '' ) );

		$args = array(
			'status'   => $status,
			'category' => $category,
			'limit'    => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
		);

		$total  = $this->repo->count_for_user( $user_id, $args );
		$items  = $this->repo->list_for_user( $user_id, $args );
		$unread = $this->repo->count_for_user( $user_id, array( 'status' => 'unread' ) );

		return array(
			'items'      => $items,
			'page'       => $page,
			'perPage'    => $per_page,
			'total'      => $total,
			'totalPages' => max( 1, (int) ceil( $total / $per_page ) ),
			'unread'     => $unread,
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @param int $id      Notification ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function mark_read( int $user_id, int $id ) {
		$user_id = absint( $user_id );
		if ( ! $this->auth->can_access_workspace( $user_id ) ) {
			return new WP_Error(
				'hvnly_notifications_forbidden',
				__( 'You cannot update notifications.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$result = $this->repo->mark_read( $id, $user_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$row = $this->repo->find_for_user( $id, $user_id );
		return $row ? $row : new WP_Error(
			'hvnly_notification_not_found',
			__( 'Notification not found.', 'havenlytics' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * @param int $user_id User ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function mark_all_read( int $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $this->auth->can_access_workspace( $user_id ) ) {
			return new WP_Error(
				'hvnly_notifications_forbidden',
				__( 'You cannot update notifications.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$updated = $this->repo->mark_all_read( $user_id );
		$unread  = $this->repo->count_for_user( $user_id, array( 'status' => 'unread' ) );

		return array(
			'updated' => $updated,
			'unread'  => $unread,
		);
	}
}
