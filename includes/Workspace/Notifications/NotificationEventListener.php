<?php
/**
 * Listens to existing Workspace / Contact Agent hooks and writes inbox rows.
 *
 * Does not modify frozen modules — only subscribes to their actions.
 *
 * @package HvnlyNab\Workspace\Notifications
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Notifications;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Workspace\Api\PropertyFormMapper;
use HvnlyNab\Workspace\Auth\AgentIdentityService;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
final class NotificationEventListener {

	/**
	 * @var NotificationRepository
	 */
	private $repo;

	/**
	 * @var AgentIdentityService
	 */
	private $identity;

	/**
	 * @param NotificationRepository|null $repo     Repository.
	 * @param AgentIdentityService|null   $identity Identity.
	 */
	public function __construct(
		?NotificationRepository $repo = null,
		?AgentIdentityService $identity = null
	) {
		$this->repo     = $repo ? $repo : new NotificationRepository();
		$this->identity = $identity ? $identity : AgentIdentityService::get_instance();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'hvnly_contact_agent_inquiry_created', array( $this, 'on_inquiry_created' ), 20, 2 );
		add_action( 'hvnly_contact_agent_inquiry_reply_sent', array( $this, 'on_inquiry_reply_sent' ), 20, 4 );
		add_action( 'hvnly_workspace_registration_status_changed', array( $this, 'on_registration_status' ), 20, 4 );
		add_action( 'hvnly_agent_profile_saved', array( $this, 'on_profile_saved' ), 20, 1 );
		add_action( 'updated_post_meta', array( $this, 'on_listing_status_meta' ), 20, 4 );
		add_action( 'added_post_meta', array( $this, 'on_listing_status_meta' ), 20, 4 );
		add_action( 'wp_set_password', array( $this, 'on_password_changed' ), 20, 2 );
	}

	/**
	 * @param int                  $inquiry_id Inquiry ID.
	 * @param array<string, mixed> $row        Inquiry row.
	 * @return void
	 */
	public function on_inquiry_created( int $inquiry_id, array $row ): void {
		$user_id = $this->resolve_user_from_agent_id( absint( $row['agent_id'] ?? 0 ) );
		if ( $user_id <= 0 || ! $this->user_allows_in_app( $user_id ) ) {
			return;
		}

		$sender   = (string) ( $row['sender_name'] ?? '' );
		$property = (string) ( $row['property_title'] ?? '' );
		$title    = __( 'New inquiry', 'havenlytics' );
		$body     = $property !== ''
			? sprintf(
				/* translators: 1: sender name, 2: property title */
				__( '%1$s asked about %2$s', 'havenlytics' ),
				$sender !== '' ? $sender : __( 'A visitor', 'havenlytics' ),
				$property
			)
			: sprintf(
				/* translators: %s: sender name */
				__( '%s sent you a message', 'havenlytics' ),
				$sender !== '' ? $sender : __( 'A visitor', 'havenlytics' )
			);

		$this->repo->create(
			array(
				'user_id'    => $user_id,
				'agent_id'   => absint( $row['agent_id'] ?? 0 ),
				'type'       => 'inquiry_created',
				'category'   => 'inquiry',
				'title'      => $title,
				'body'       => $body,
				'icon'       => 'inquiry',
				'action_url' => '#/inquiries/' . $inquiry_id,
				'payload'    => array(
					'inquiryId'  => $inquiry_id,
					'propertyId' => absint( $row['property_id'] ?? 0 ),
				),
			)
		);
	}

	/**
	 * Notify assigned agent when someone else replies (skip self-replies).
	 *
	 * @param int                  $reply_id   Reply ID.
	 * @param int                  $inquiry_id Inquiry ID.
	 * @param int                  $author_id  Reply author user ID.
	 * @param array<string, mixed> $inquiry    Inquiry row.
	 * @return void
	 */
	public function on_inquiry_reply_sent( int $reply_id, int $inquiry_id, int $author_id, array $inquiry ): void {
		$user_id = $this->resolve_user_from_agent_id( absint( $inquiry['agent_id'] ?? 0 ) );
		if ( $user_id <= 0 || $user_id === absint( $author_id ) || ! $this->user_allows_in_app( $user_id ) ) {
			return;
		}

		$property = (string) ( $inquiry['property_title'] ?? '' );
		$body     = $property !== ''
			? sprintf(
				/* translators: %s: property title */
				__( 'A reply was sent on the inquiry for %s.', 'havenlytics' ),
				$property
			)
			: __( 'A reply was sent on one of your inquiries.', 'havenlytics' );

		$this->repo->create(
			array(
				'user_id'    => $user_id,
				'agent_id'   => absint( $inquiry['agent_id'] ?? 0 ),
				'type'       => 'inquiry_replied',
				'category'   => 'inquiry',
				'title'      => __( 'Inquiry replied', 'havenlytics' ),
				'body'       => $body,
				'icon'       => 'inquiry',
				'action_url' => '#/inquiries/' . $inquiry_id,
				'payload'    => array(
					'inquiryId' => $inquiry_id,
					'replyId'   => $reply_id,
				),
			)
		);
	}

	/**
	 * @param int    $user_id  User ID.
	 * @param string $status   New status.
	 * @param int    $agent_id Agent ID.
	 * @param string $previous Previous status.
	 * @return void
	 */
	public function on_registration_status( int $user_id, string $status, int $agent_id, string $previous ): void {
		unset( $previous );

		// Sprint 30B: system events (demo / bulk import, migration, WP-CLI) never
		// create in-app inbox rows — demo agent provisioning stays silent.
		if ( function_exists( 'hvnly_is_system_notification_context' ) && hvnly_is_system_notification_context() ) {
			return;
		}

		if ( $user_id <= 0 || ! $this->user_allows_in_app( $user_id ) ) {
			return;
		}

		$map = array(
			'approved'  => array(
				'title' => __( 'Registration approved', 'havenlytics' ),
				'body'  => __( 'Your Workspace account has been approved.', 'havenlytics' ),
				'icon'  => 'account',
			),
			'active'    => array(
				'title' => __( 'Account activated', 'havenlytics' ),
				'body'  => __( 'Your Workspace account is active.', 'havenlytics' ),
				'icon'  => 'account',
			),
			'blocked'   => array(
				'title' => __( 'Account blocked', 'havenlytics' ),
				'body'  => __( 'Your Workspace account has been blocked.', 'havenlytics' ),
				'icon'  => 'account',
			),
			'suspended' => array(
				'title' => __( 'Account suspended', 'havenlytics' ),
				'body'  => __( 'Your Workspace account has been suspended.', 'havenlytics' ),
				'icon'  => 'account',
			),
			'archived'  => array(
				'title' => __( 'Account archived', 'havenlytics' ),
				'body'  => __( 'Your Agent identity has been archived.', 'havenlytics' ),
				'icon'  => 'account',
			),
		);

		$status = sanitize_key( $status );
		if ( ! isset( $map[ $status ] ) ) {
			return;
		}

		$this->repo->create(
			array(
				'user_id'    => $user_id,
				'agent_id'   => $agent_id,
				'type'       => 'registration_' . $status,
				'category'   => 'account',
				'title'      => $map[ $status ]['title'],
				'body'       => $map[ $status ]['body'],
				'icon'       => $map[ $status ]['icon'],
				'action_url' => '#/dashboard',
			)
		);
	}

	/**
	 * @param int $agent_id Agent CPT ID.
	 * @return void
	 */
	public function on_profile_saved( int $agent_id ): void {
		$user_id = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $user_id <= 0 ) {
			$user_id = (int) get_post_field( 'post_author', $agent_id );
		}
		if ( $user_id <= 0 || ! $this->user_allows_in_app( $user_id ) ) {
			return;
		}

		$this->repo->create(
			array(
				'user_id'    => $user_id,
				'agent_id'   => $agent_id,
				'type'       => 'profile_updated',
				'category'   => 'account',
				'title'      => __( 'Profile updated', 'havenlytics' ),
				'body'       => __( 'Your agent profile was saved successfully.', 'havenlytics' ),
				'icon'       => 'profile',
				'action_url' => '#/profile',
				'payload'    => array( 'agentId' => $agent_id ),
			)
		);
	}

	/**
	 * Property workflow via existing listing-status meta (no Property module edits).
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function on_listing_status_meta( $meta_id, $object_id, $meta_key, $meta_value ): void {
		unset( $meta_id );
		if ( PropertyFormMapper::META_LISTING_STATUS !== (string) $meta_key ) {
			return;
		}
		if ( PropertyFormMapper::POST_TYPE !== get_post_type( $object_id ) ) {
			return;
		}

		// Sprint 28E: demo / bulk import, migration, repair and WP-CLI passes are
		// system events — they must never create in-app inbox rows. Shares the one
		// gate used by the lifecycle email notifier + status bridge.
		if ( hvnly_is_system_notification_context() ) {
			return;
		}

		$user_id = (int) get_post_field( 'post_author', $object_id );
		if ( $user_id <= 0 || ! $this->user_allows_in_app( $user_id ) ) {
			return;
		}

		$label = sanitize_text_field( (string) $meta_value );
		$map   = array(
			'Pending'   => array(
				'type'  => 'property_submitted',
				'title' => __( 'Property submitted', 'havenlytics' ),
				'body'  => __( 'Your listing was submitted for review.', 'havenlytics' ),
			),
			'Published' => array(
				'type'  => 'property_published',
				'title' => __( 'Property published', 'havenlytics' ),
				'body'  => __( 'Your listing is now published.', 'havenlytics' ),
			),
			'Rejected'  => array(
				'type'  => 'property_rejected',
				'title' => __( 'Property rejected', 'havenlytics' ),
				'body'  => __( 'Your listing was returned with feedback.', 'havenlytics' ),
			),
		);

		if ( ! isset( $map[ $label ] ) ) {
			return;
		}

		$title_post = get_the_title( $object_id );
		$body       = $map[ $label ]['body'];
		if ( is_string( $title_post ) && $title_post !== '' ) {
			$body = $title_post . ' — ' . $body;
		}

		$this->repo->create(
			array(
				'user_id'    => $user_id,
				'agent_id'   => $this->identity->get_agent_id( $user_id ),
				'type'       => $map[ $label ]['type'],
				'category'   => 'property',
				'title'      => $map[ $label ]['title'],
				'body'       => $body,
				'icon'       => 'property',
				'action_url' => '#/property/' . absint( $object_id ) . '/edit',
				'payload'    => array(
					'propertyId' => absint( $object_id ),
					'status'     => $label,
				),
			)
		);
	}

	/**
	 * @param string $password New password (ignored).
	 * @param int    $user_id  User ID.
	 * @return void
	 */
	public function on_password_changed( $password, $user_id ): void {
		unset( $password );
		$user_id = absint( $user_id );
		if ( $user_id <= 0 || ! $this->user_allows_in_app( $user_id ) ) {
			return;
		}

		$this->repo->create(
			array(
				'user_id'    => $user_id,
				'agent_id'   => $this->identity->get_agent_id( $user_id ),
				'type'       => 'password_changed',
				'category'   => 'account',
				'title'      => __( 'Password changed', 'havenlytics' ),
				'body'       => __( 'Your account password was updated.', 'havenlytics' ),
				'icon'       => 'account',
				'action_url' => '#/settings',
				'payload'    => array(),
			)
		);
	}

	/**
	 * @param int $agent_id CPT or user ID stored on inquiry.
	 * @return int WP user ID.
	 */
	private function resolve_user_from_agent_id( int $agent_id ): int {
		if ( $agent_id <= 0 ) {
			return 0;
		}
		if ( AgentConstants::POST_TYPE === get_post_type( $agent_id ) ) {
			$linked = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
			return $linked > 0 ? $linked : (int) get_post_field( 'post_author', $agent_id );
		}
		return get_userdata( $agent_id ) ? $agent_id : 0;
	}

	/**
	 * Honor Settings in-app/email master preference when present.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function user_allows_in_app( int $user_id ): bool {
		$agent_id = $this->identity->get_agent_id( $user_id );
		if ( $agent_id <= 0 ) {
			return true;
		}
		$ext = get_post_meta( $agent_id, AgentConstants::META_EXTENSIONS, true );
		if ( ! is_array( $ext ) || empty( $ext['workspace_settings'] ) || ! is_array( $ext['workspace_settings'] ) ) {
			return true;
		}
		$prefs = $ext['workspace_settings'];
		if ( array_key_exists( 'notificationInApp', $prefs ) ) {
			return ! empty( $prefs['notificationInApp'] );
		}
		return true;
	}
}
