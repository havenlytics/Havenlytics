<?php
/**
 * Workspace Inquiry Inbox — wraps existing Contact Agent repositories.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Agent\PropertyAgentResolver;
use HvnlyNab\ContactAgent\ContactAgentConstants;
use HvnlyNab\ContactAgent\InquiryReplyRepository;
use HvnlyNab\ContactAgent\InquiryReplyService;
use HvnlyNab\ContactAgent\InquiryRepository;
use HvnlyNab\Workspace\Auth\AgentIdentityService;
use HvnlyNab\Workspace\Auth\PortalAuthorization;
use HvnlyNab\Workspace\Auth\PortalCapabilities;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Agent-scoped inquiry inbox on existing hvnly_inquiries tables.
 *
 * @since 3.2.0
 */
final class InquiryService {

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @var PortalAuthorization
	 */
	private $auth;

	/**
	 * @var InquiryRepository
	 */
	private $inquiries;

	/**
	 * @var InquiryReplyRepository
	 */
	private $replies;

	/**
	 * @var InquiryReplyService
	 */
	private $reply_service;

	/**
	 * @param AgentIdentityService|null    $identity      Identity.
	 * @param PortalAuthorization|null     $auth          Authz.
	 * @param InquiryRepository|null       $inquiries     Inquiry repo.
	 * @param InquiryReplyRepository|null  $replies       Reply repo.
	 * @param InquiryReplyService|null     $reply_service Reply orchestrator.
	 */
	public function __construct(
		?AgentIdentityService $identity = null,
		?PortalAuthorization $auth = null,
		?InquiryRepository $inquiries = null,
		?InquiryReplyRepository $replies = null,
		?InquiryReplyService $reply_service = null
	) {
		$this->identity      = $identity ? $identity : AgentIdentityService::get_instance();
		$this->auth          = $auth ? $auth : new PortalAuthorization( $this->identity );
		$this->inquiries     = $inquiries ? $inquiries : new InquiryRepository();
		$this->replies       = $replies ? $replies : new InquiryReplyRepository();
		$this->reply_service = $reply_service ? $reply_service : new InquiryReplyService( $this->inquiries, $this->replies );
	}

	/**
	 * Register portal reply permission filter (once).
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'hvnly_inquiry_user_can_reply', array( $this, 'filter_user_can_reply' ), 10, 3 );
	}

	/**
	 * Allow portal agents with inquiry caps to pass InquiryReplyService gate.
	 * Ownership is enforced separately before send_reply().
	 *
	 * @param bool $allowed    Current allow flag.
	 * @param int  $user_id    User ID.
	 * @param int  $inquiry_id Inquiry ID.
	 * @return bool
	 */
	public function filter_user_can_reply( bool $allowed, int $user_id, int $inquiry_id ): bool {
		unset( $inquiry_id );
		if ( $allowed ) {
			return true;
		}
		return $this->auth->can_manage_inquiries( $user_id )
			|| user_can( $user_id, PortalCapabilities::REPLY_INQUIRIES );
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $query   List query.
	 * @return array<string, mixed>|WP_Error
	 */
	public function list_inquiries( int $user_id, array $query = array() ) {
		$user_id = absint( $user_id );
		if ( ! $this->auth->can_manage_inquiries( $user_id ) ) {
			return new WP_Error(
				'hvnly_inquiries_forbidden',
				__( 'You cannot view inquiries.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$agent_ids = $this->ownership_agent_ids( $user_id );
		if ( empty( $agent_ids ) ) {
			return array(
				'items'      => array(),
				'page'       => 1,
				'perPage'    => 10,
				'total'      => 0,
				'totalPages' => 1,
				'unread'     => 0,
			);
		}

		$page     = max( 1, absint( $query['page'] ?? 1 ) );
		$per_page = min( 50, max( 1, absint( $query['perPage'] ?? $query['per_page'] ?? 10 ) ) );
		$status   = sanitize_key( (string) ( $query['status'] ?? '' ) );
		if ( 'all' === $status || 'unread' === $status ) {
			// unread handled below.
		} elseif ( $status && ! in_array( $status, ContactAgentConstants::INQUIRY_STATUSES, true ) ) {
			$status = '';
		}

		$search = sanitize_text_field( (string) ( $query['search'] ?? '' ) );
		$date_from = sanitize_text_field( (string) ( $query['dateFrom'] ?? $query['date_from'] ?? '' ) );
		$date_to   = sanitize_text_field( (string) ( $query['dateTo'] ?? $query['date_to'] ?? '' ) );

		$list_args = array(
			'agent_ids' => $agent_ids,
			'limit'     => $per_page,
			'offset'    => ( $page - 1 ) * $per_page,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'search'    => $search,
			'date_from' => $date_from,
			'date_to'   => $date_to,
		);

		if ( 'unread' === (string) ( $query['status'] ?? '' ) ) {
			$list_args['status'] = ContactAgentConstants::STATUS_NEW;
		} elseif ( $status && 'all' !== $status ) {
			$list_args['status'] = $status;
		}

		$total = $this->inquiries->count( $list_args );
		$rows  = $this->inquiries->list( $list_args );
		$items = array_map( array( $this, 'serialize_list_item' ), $rows );

		$unread = $this->inquiries->count(
			array(
				'agent_ids' => $agent_ids,
				'status'    => ContactAgentConstants::STATUS_NEW,
			)
		);

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
	 * @param int $user_id    User ID.
	 * @param int $inquiry_id Inquiry ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_inquiry( int $user_id, int $inquiry_id ) {
		$user_id = absint( $user_id );
		$row     = $this->require_owned_inquiry( $user_id, $inquiry_id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		// Auto mark-read when opening (same as admin detail).
		if ( ContactAgentConstants::STATUS_NEW === ( $row['status'] ?? '' ) ) {
			$this->inquiries->update_status( $inquiry_id, ContactAgentConstants::STATUS_READ );
			$row['status'] = ContactAgentConstants::STATUS_READ;
		}

		return $this->serialize_detail( $row );
	}

	/**
	 * @param int                  $user_id    User ID.
	 * @param int                  $inquiry_id Inquiry ID.
	 * @param array<string, mixed> $payload    Patch body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function patch_inquiry( int $user_id, int $inquiry_id, array $payload ) {
		$row = $this->require_owned_inquiry( $user_id, $inquiry_id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		$status = isset( $payload['status'] ) ? sanitize_key( (string) $payload['status'] ) : '';
		if ( '' === $status ) {
			return new WP_Error(
				'hvnly_inquiry_invalid_status',
				__( 'Status is required.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$allowed = array(
			ContactAgentConstants::STATUS_READ,
			ContactAgentConstants::STATUS_ARCHIVED,
			ContactAgentConstants::STATUS_NEW,
		);
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error(
				'hvnly_inquiry_invalid_status',
				__( 'Invalid status transition.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->inquiries->update_status( $inquiry_id, $status );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fresh = $this->inquiries->find( $inquiry_id );
		return $fresh ? $this->serialize_detail( $fresh ) : new WP_Error(
			'hvnly_inquiry_not_found',
			__( 'Inquiry not found.', 'havenlytics' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Reply via InquiryReplyService (email + replies table + status).
	 *
	 * @param int    $user_id    User ID.
	 * @param int    $inquiry_id Inquiry ID.
	 * @param string $message    Reply body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function reply( int $user_id, int $inquiry_id, string $message ) {
		$row = $this->require_owned_inquiry( $user_id, $inquiry_id );
		if ( is_wp_error( $row ) ) {
			return $row;
		}

		if ( ContactAgentConstants::STATUS_ARCHIVED === ( $row['status'] ?? '' ) ) {
			return new WP_Error(
				'hvnly_inquiry_archived',
				__( 'Archived inquiries cannot be replied to.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( ! user_can( $user_id, PortalCapabilities::REPLY_INQUIRIES )
			&& ! $this->auth->can_manage_inquiries( $user_id )
			&& ! user_can( $user_id, 'manage_options' ) ) {
			return new WP_Error(
				'hvnly_inquiry_reply_forbidden',
				__( 'You do not have permission to reply to inquiries.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$result = $this->reply_service->send_reply( $inquiry_id, $message, $user_id );
		if ( is_wp_error( $result ) ) {
			// Partial success: reply stored, email failed.
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['reply_id'] ) ) {
				$detail = $this->inquiries->find( $inquiry_id );
				return array(
					'success'   => true,
					'emailSent' => false,
					'replyId'   => (int) $data['reply_id'],
					'warning'   => $result->get_error_message(),
					'inquiry'   => $detail ? $this->serialize_detail( $detail ) : null,
				);
			}
			return $result;
		}

		$detail = $this->inquiries->find( $inquiry_id );
		return array(
			'success'   => true,
			'emailSent' => ! empty( $result['email_sent'] ),
			'replyId'   => isset( $result['reply_id'] ) ? (int) $result['reply_id'] : 0,
			'inquiry'   => $detail ? $this->serialize_detail( $detail ) : null,
		);
	}

	/**
	 * @param int $user_id    User ID.
	 * @param int $inquiry_id Inquiry ID.
	 * @return array<string, mixed>|WP_Error
	 */
	private function require_owned_inquiry( int $user_id, int $inquiry_id ) {
		$user_id = absint( $user_id );
		if ( ! $this->auth->can_manage_inquiries( $user_id ) ) {
			return new WP_Error(
				'hvnly_inquiries_forbidden',
				__( 'You cannot view inquiries.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$inquiry_id = absint( $inquiry_id );
		$row        = $this->inquiries->find( $inquiry_id );
		if ( ! $row ) {
			return new WP_Error(
				'hvnly_inquiry_not_found',
				__( 'Inquiry not found.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->user_owns_inquiry( $user_id, $row ) ) {
			return new WP_Error(
				'hvnly_inquiry_forbidden',
				__( 'You do not have access to this inquiry.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		return $row;
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $row     Inquiry row.
	 * @return bool
	 */
	private function user_owns_inquiry( int $user_id, array $row ): bool {
		$agent_id = isset( $row['agent_id'] ) ? absint( $row['agent_id'] ) : 0;
		if ( $agent_id <= 0 ) {
			return false;
		}
		return in_array( $agent_id, $this->ownership_agent_ids( $user_id ), true );
	}

	/**
	 * Agent CPT IDs linked to user + user ID (legacy user-type rows).
	 *
	 * @param int $user_id User ID.
	 * @return int[]
	 */
	private function ownership_agent_ids( int $user_id ): array {
		$ids = array( $user_id );

		$linked = $this->identity->get_agent_id( $user_id );
		if ( $linked > 0 ) {
			$ids[] = $linked;
		}

		if ( class_exists( PropertyAgentResolver::class ) ) {
			foreach ( PropertyAgentResolver::get_cpt_agents_for_user( $user_id ) as $agent ) {
				if ( is_array( $agent ) && ! empty( $agent['id'] ) ) {
					$ids[] = absint( $agent['id'] );
				}
			}
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		/**
		 * Filter ownership agent_id set for Workspace inbox scoping.
		 *
		 * @since 3.2.0
		 *
		 * @param int[] $ids     Agent IDs (CPT and/or user).
		 * @param int   $user_id User ID.
		 */
		return array_values( array_unique( array_map( 'absint', (array) apply_filters( 'hvnly_workspace_inquiry_agent_ids', $ids, $user_id ) ) ) );
	}

	/**
	 * @param array<string, mixed> $row Inquiry row.
	 * @return array<string, mixed>
	 */
	private function serialize_list_item( array $row ): array {
		$preview = isset( $row['message'] ) ? wp_html_excerpt( (string) $row['message'], 120, '…' ) : '';
		return array(
			'id'            => (int) ( $row['id'] ?? 0 ),
			'senderName'    => (string) ( $row['sender_name'] ?? '' ),
			'senderEmail'   => (string) ( $row['sender_email'] ?? '' ),
			'senderPhone'   => (string) ( $row['sender_phone'] ?? '' ),
			'propertyId'    => (int) ( $row['property_id'] ?? 0 ),
			'propertyTitle' => (string) ( $row['property_title'] ?? '' ),
			'agentId'       => (int) ( $row['agent_id'] ?? 0 ),
			'agentName'     => (string) ( $row['agent_name'] ?? '' ),
			'status'        => (string) ( $row['status'] ?? '' ),
			'source'        => (string) ( $row['source'] ?? '' ),
			'preview'       => $preview,
			'createdAt'     => (string) ( $row['created_at'] ?? '' ),
			'isUnread'      => ContactAgentConstants::STATUS_NEW === ( $row['status'] ?? '' ),
		);
	}

	/**
	 * @param array<string, mixed> $row Inquiry row.
	 * @return array<string, mixed>
	 */
	private function serialize_detail( array $row ): array {
		$item    = $this->serialize_list_item( $row );
		$replies = $this->replies->list_for_inquiry( (int) ( $row['id'] ?? 0 ) );
		$timeline = array();

		$timeline[] = array(
			'id'        => 'inquiry-' . (int) ( $row['id'] ?? 0 ),
			'type'      => 'inquiry',
			'direction' => 'inbound',
			'message'   => (string) ( $row['message'] ?? '' ),
			'author'    => (string) ( $row['sender_name'] ?? '' ),
			'email'     => (string) ( $row['sender_email'] ?? '' ),
			'createdAt' => (string) ( $row['created_at'] ?? '' ),
			'emailSent' => null,
		);

		foreach ( $replies as $reply ) {
			if ( ! is_array( $reply ) ) {
				continue;
			}
			$timeline[] = array(
				'id'        => 'reply-' . (int) ( $reply['id'] ?? 0 ),
				'type'      => 'reply',
				'direction' => (string) ( $reply['direction'] ?? 'outbound' ),
				'message'   => (string) ( $reply['message'] ?? '' ),
				'author'    => (string) ( $reply['author_name'] ?? '' ),
				'email'     => (string) ( $reply['author_email'] ?? '' ),
				'createdAt' => (string) ( $reply['created_at'] ?? '' ),
				'emailSent' => ! empty( $reply['email_sent'] ),
			);
		}

		$item['message']  = (string) ( $row['message'] ?? '' );
		$item['replies']  = array_map(
			static function ( $reply ) {
				if ( ! is_array( $reply ) ) {
					return array();
				}
				return array(
					'id'           => (int) ( $reply['id'] ?? 0 ),
					'direction'    => (string) ( $reply['direction'] ?? '' ),
					'message'      => (string) ( $reply['message'] ?? '' ),
					'authorUserId' => (int) ( $reply['author_user_id'] ?? 0 ),
					'authorName'   => (string) ( $reply['author_name'] ?? '' ),
					'emailSent'    => ! empty( $reply['email_sent'] ),
					'createdAt'    => (string) ( $reply['created_at'] ?? '' ),
				);
			},
			$replies
		);
		$item['timeline'] = $timeline;
		$item['canReply'] = ContactAgentConstants::STATUS_ARCHIVED !== ( $row['status'] ?? '' );

		return $item;
	}
}
