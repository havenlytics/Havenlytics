<?php
/**
 * Property listing status workflow (draft → pending → publish).
 *
 * Server-validates every transition. History stored in post meta.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Workspace\WorkspaceSettings;
use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Submit / publish / changeStatus for Workspace properties.
 *
 * @since 3.2.0
 */
final class PropertyWorkflowService {

	public const META_HISTORY       = '_hvnly_ws_status_history';
	public const META_REJECT_REASON = '_hvnly_ws_reject_reason';

	/** Workflow keys (UI + REST). */
	public const STATUS_DRAFT     = 'draft';
	public const STATUS_PENDING   = 'pending';
	public const STATUS_PUBLISHED = 'publish';
	public const STATUS_REJECTED  = 'rejected';

	/**
	 * Allowed transitions: from => to[].
	 *
	 * @var array<string, string[]>
	 */
	private const TRANSITIONS = array(
		self::STATUS_DRAFT     => array( self::STATUS_PENDING, self::STATUS_PUBLISHED ),
		self::STATUS_PENDING   => array( self::STATUS_PUBLISHED, self::STATUS_DRAFT, self::STATUS_REJECTED ),
		self::STATUS_PUBLISHED => array( self::STATUS_DRAFT ),
		self::STATUS_REJECTED  => array( self::STATUS_DRAFT, self::STATUS_PENDING ),
	);

	/**
	 * @var PropertyAccessGate
	 */
	private $access;

	/**
	 * True while {@see self::transition()} is updating post_status + listing meta.
	 * Lets {@see PropertyListingStatusBridge} skip re-entrancy (Sprint 28A).
	 *
	 * @var bool
	 */
	private static $syncing = false;

	/**
	 * @param PropertyAccessGate|null $access Access gate.
	 */
	public function __construct( ?PropertyAccessGate $access = null ) {
		$this->access = $access ? $access : new PropertyAccessGate();
	}

	/**
	 * Whether a workflow status write is in progress on this request.
	 *
	 * @return bool
	 */
	public static function is_syncing(): bool {
		return self::$syncing;
	}

	/**
	 * Resolve workflow status from post + listing meta.
	 *
	 * @param WP_Post $post Property.
	 * @return string draft|pending|publish|rejected
	 */
	public function get_workflow_status( WP_Post $post ): string {
		$listing = (string) get_post_meta( $post->ID, PropertyFormMapper::META_LISTING_STATUS, true );
		$listing = strtolower( trim( $listing ) );

		if ( 'rejected' === $listing || self::STATUS_REJECTED === $listing ) {
			return self::STATUS_REJECTED;
		}

		switch ( $post->post_status ) {
			case 'pending':
				return self::STATUS_PENDING;
			case 'publish':
				return self::STATUS_PUBLISHED;
			case 'draft':
			case 'auto-draft':
			default:
				return self::STATUS_DRAFT;
		}
	}

	/**
	 * Human label for badges / form.
	 *
	 * @param string $workflow_status Workflow key.
	 * @return string
	 */
	public static function label_for( string $workflow_status ): string {
		switch ( $workflow_status ) {
			case self::STATUS_PENDING:
				return 'Pending';
			case self::STATUS_PUBLISHED:
				return 'Published';
			case self::STATUS_REJECTED:
				return 'Rejected';
			case self::STATUS_DRAFT:
			default:
				return 'Draft';
		}
	}

	/**
	 * Map workflow key → WP post_status.
	 *
	 * @param string $workflow_status Workflow key.
	 * @return string
	 */
	public static function post_status_for( string $workflow_status ): string {
		switch ( $workflow_status ) {
			case self::STATUS_PENDING:
				return 'pending';
			case self::STATUS_PUBLISHED:
				return 'publish';
			case self::STATUS_REJECTED:
			case self::STATUS_DRAFT:
			default:
				return 'draft';
		}
	}

	/**
	 * Status history (newest last).
	 *
	 * @param int $property_id Property ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_history( int $property_id ): array {
		$raw = get_post_meta( $property_id, self::META_HISTORY, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return array_values( $raw );
	}

	/**
	 * Workflow payload for REST form records.
	 *
	 * @param WP_Post $post Property.
	 * @return array<string, mixed>
	 */
	public function workflow_payload( WP_Post $post ): array {
		$status  = $this->get_workflow_status( $post );
		$user_id = get_current_user_id();

		return array(
			'status'              => $status,
			'label'               => self::label_for( $status ),
			'postStatus'          => (string) $post->post_status,
			'timeline'            => $this->timeline_steps( $status ),
			'history'             => $this->get_history( (int) $post->ID ),
			'allowedTransitions'  => $this->allowed_transitions_for_user( $post, $user_id ),
			'canSubmit'           => $this->access->can_submit( (int) $post->ID, $user_id ),
			'canPublish'          => $this->access->can_publish( (int) $post->ID, $user_id ),
			'canChangeStatus'     => $this->access->can_change_status( (int) $post->ID, $user_id ),
			'directPublish'       => WorkspaceSettings::agents_can_direct_publish() || $this->access->can_publish( (int) $post->ID, $user_id ),
			'rejectReason'        => (string) get_post_meta( $post->ID, self::META_REJECT_REASON, true ),
		);
	}

	/**
	 * Submit for review: draft|rejected → pending.
	 *
	 * @param WP_Post $post Property.
	 * @param string  $note Optional note.
	 * @return array<string, mixed>|WP_Error
	 */
	public function submit( WP_Post $post, string $note = '' ) {
		return $this->transition( $post, self::STATUS_PENDING, 'submit', $note );
	}

	/**
	 * Publish: draft → publish (if allowed) or pending → publish.
	 *
	 * @param WP_Post $post Property.
	 * @param string  $note Optional note.
	 * @return array<string, mixed>|WP_Error
	 */
	public function publish( WP_Post $post, string $note = '' ) {
		return $this->transition( $post, self::STATUS_PUBLISHED, 'publish', $note );
	}

	/**
	 * Generic status change.
	 *
	 * @param WP_Post $post   Property.
	 * @param string  $to     Target workflow status.
	 * @param string  $note   Optional note / reject reason.
	 * @param string  $action Action label for history.
	 * @return array<string, mixed>|WP_Error
	 */
	public function change_status( WP_Post $post, string $to, string $note = '', string $action = 'change_status' ) {
		$to = $this->normalize_status( $to );
		if ( '' === $to ) {
			return new WP_Error(
				'hvnly_workflow_invalid_status',
				__( 'Invalid target status.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( self::STATUS_REJECTED === $to && '' === trim( $note ) ) {
			$note = __( 'Rejected', 'havenlytics' );
		}

		return $this->transition( $post, $to, $action, $note );
	}

	/**
	 * @param WP_Post $post   Property.
	 * @param string  $to     Target.
	 * @param string  $action Action key.
	 * @param string  $note   Note.
	 * @return array<string, mixed>|WP_Error
	 */
	private function transition( WP_Post $post, string $to, string $action, string $note ) {
		$user_id = get_current_user_id();
		$from    = $this->get_workflow_status( $post );

		if ( ! $this->is_transition_allowed( $from, $to ) ) {
			return new WP_Error(
				'hvnly_workflow_invalid_transition',
				sprintf(
					/* translators: 1: from status, 2: to status */
					__( 'Cannot change status from %1$s to %2$s.', 'havenlytics' ),
					self::label_for( $from ),
					self::label_for( $to )
				),
				array( 'status' => 409 )
			);
		}

		$perm = $this->authorize_transition( (int) $post->ID, $from, $to, $action, $user_id );
		if ( is_wp_error( $perm ) ) {
			return $perm;
		}

		if ( in_array( $to, array( self::STATUS_PENDING, self::STATUS_PUBLISHED ), true ) ) {
			$values = PropertyFormMapper::values_from_post( $post );
			$valid  = PropertyDraftValidator::validate( $values );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		$post_status = self::post_status_for( $to );

		$updated       = null;
		self::$syncing = true;
		try {
			$updated = wp_update_post(
				array(
					'ID'          => (int) $post->ID,
					'post_status' => $post_status,
				),
				true
			);

			if ( ! is_wp_error( $updated ) ) {
				$fresh_status = get_post_status( (int) $post->ID );
				if ( (string) $fresh_status !== (string) $post_status ) {
					$updated = new WP_Error(
						'hvnly_workflow_status_mismatch',
						__( 'Could not update property status.', 'havenlytics' ),
						array(
							'status'   => 500,
							'expected' => $post_status,
							'actual'   => (string) $fresh_status,
						)
					);
				} else {
					/*
					 * Write reject reason BEFORE listing-status meta so PropertyWorkflowNotifier
					 * (and in-app listeners) can include the reason when the Rejected email fires.
					 */
					if ( self::STATUS_REJECTED === $to ) {
						update_post_meta( (int) $post->ID, self::META_REJECT_REASON, sanitize_textarea_field( $note ) );
					} elseif ( self::STATUS_PENDING === $to || self::STATUS_PUBLISHED === $to ) {
						delete_post_meta( (int) $post->ID, self::META_REJECT_REASON );
					}

					PropertyFormMapper::sync_listing_status( (int) $post->ID, self::label_for( $to ) );
				}
			}
		} finally {
			self::$syncing = false;
		}

		if ( is_wp_error( $updated ) ) {
			return new WP_Error(
				'hvnly_workflow_update_failed',
				__( 'Could not update property status.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		$this->append_history(
			(int) $post->ID,
			array(
				'from'     => $from,
				'to'       => $to,
				'action'   => $action,
				'note'     => sanitize_textarea_field( $note ),
				'userId'   => $user_id,
				'userName' => wp_get_current_user()->display_name,
				'at'       => gmdate( 'c' ),
			)
		);

		/*
		 * Reliability trigger (Sprint 31): announce every successful transition on a
		 * dedicated action, IN ADDITION to the listing-status meta write above.
		 * update_post_meta() does NOT fire updated_post_meta when the stored value is
		 * unchanged, so re-submitting a listing whose meta is already 'Pending' could
		 * otherwise slip past PropertyWorkflowNotifier and email no administrator.
		 * The notifier's own once-per-transition dedup keeps this idempotent — it
		 * never double-sends when the meta hook already delivered.
		 */
		do_action( 'hvnly_property_workflow_transitioned', (int) $post->ID, $to, $from, $user_id );

		$fresh = get_post( (int) $post->ID );
		if ( ! $fresh ) {
			return new WP_Error(
				'hvnly_workflow_update_failed',
				__( 'Could not update property status.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		return $this->workflow_payload( $fresh );
	}

	/**
	 * @param int    $property_id Property ID.
	 * @param string $from        From.
	 * @param string $to          To.
	 * @param string $action      Action.
	 * @param int    $user_id     User.
	 * @return true|WP_Error
	 */
	private function authorize_transition( int $property_id, string $from, string $to, string $action, int $user_id ) {
		if ( ! $this->access->can_edit( $property_id, $user_id ) && ! $this->access->can_publish( $property_id, $user_id ) ) {
			return new WP_Error(
				'hvnly_workflow_forbidden',
				__( 'You cannot change this property status.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		if ( self::STATUS_PENDING === $to ) {
			if ( ! $this->access->can_submit( $property_id, $user_id ) ) {
				return new WP_Error(
					'hvnly_workflow_forbidden',
					__( 'You cannot submit this listing for review.', 'havenlytics' ),
					array( 'status' => 403 )
				);
			}
			return true;
		}

		if ( self::STATUS_PUBLISHED === $to ) {
			if ( ! $this->access->can_publish( $property_id, $user_id ) ) {
				return new WP_Error(
					'hvnly_workflow_forbidden',
					__( 'You cannot publish this listing.', 'havenlytics' ),
					array( 'status' => 403 )
				);
			}
			return true;
		}

		if ( self::STATUS_REJECTED === $to ) {
			if ( ! $this->access->can_publish( $property_id, $user_id ) ) {
				return new WP_Error(
					'hvnly_workflow_forbidden',
					__( 'You cannot reject this listing.', 'havenlytics' ),
					array( 'status' => 403 )
				);
			}
			return true;
		}

		if ( self::STATUS_DRAFT === $to ) {
			// Withdraw pending, clear rejected, or unpublish.
			if ( self::STATUS_PUBLISHED === $from ) {
				if ( ! $this->access->can_publish( $property_id, $user_id ) ) {
					return new WP_Error(
						'hvnly_workflow_forbidden',
						__( 'You cannot unpublish this listing.', 'havenlytics' ),
						array( 'status' => 403 )
					);
				}
			} elseif ( ! $this->access->can_edit( $property_id, $user_id ) && ! $this->access->can_publish( $property_id, $user_id ) ) {
				return new WP_Error(
					'hvnly_workflow_forbidden',
					__( 'You cannot change this property status.', 'havenlytics' ),
					array( 'status' => 403 )
				);
			}
			return true;
		}

		return new WP_Error(
			'hvnly_workflow_forbidden',
			__( 'You cannot change this property status.', 'havenlytics' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * @param string $from From.
	 * @param string $to   To.
	 * @return bool
	 */
	private function is_transition_allowed( string $from, string $to ): bool {
		if ( $from === $to ) {
			return false;
		}
		$allowed = isset( self::TRANSITIONS[ $from ] ) ? self::TRANSITIONS[ $from ] : array();
		return in_array( $to, $allowed, true );
	}

	/**
	 * @param WP_Post $post    Post.
	 * @param int     $user_id User.
	 * @return string[]
	 */
	private function allowed_transitions_for_user( WP_Post $post, int $user_id ): array {
		$from    = $this->get_workflow_status( $post );
		$allowed = isset( self::TRANSITIONS[ $from ] ) ? self::TRANSITIONS[ $from ] : array();
		$out     = array();

		foreach ( $allowed as $to ) {
			$check = $this->authorize_transition( (int) $post->ID, $from, $to, 'change_status', $user_id );
			if ( ! is_wp_error( $check ) ) {
				$out[] = $to;
			}
		}

		return $out;
	}

	/**
	 * Timeline steps for UI.
	 *
	 * @param string $current Current workflow status.
	 * @return array<int, array<string, mixed>>
	 */
	private function timeline_steps( string $current ): array {
		$order = array( self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_PUBLISHED );
		$idx   = array_search( $current, $order, true );
		if ( self::STATUS_REJECTED === $current ) {
			$idx = 0;
		}

		$steps = array();
		foreach ( $order as $i => $key ) {
			$state = 'upcoming';
			if ( false !== $idx ) {
				if ( $i < $idx ) {
					$state = 'complete';
				} elseif ( $i === $idx ) {
					$state = 'current';
				}
			}
			if ( self::STATUS_REJECTED === $current && self::STATUS_DRAFT === $key ) {
				$state = 'current';
			}
			$steps[] = array(
				'id'    => $key,
				'label' => self::label_for( $key ),
				'state' => $state,
			);
		}

		if ( self::STATUS_REJECTED === $current ) {
			$steps[] = array(
				'id'    => self::STATUS_REJECTED,
				'label' => self::label_for( self::STATUS_REJECTED ),
				'state' => 'current',
			);
		}

		return $steps;
	}

	/**
	 * @param int                  $property_id Property ID.
	 * @param array<string, mixed> $entry       History entry.
	 * @return void
	 */
	private function append_history( int $property_id, array $entry ): void {
		$history   = $this->get_history( $property_id );
		$history[] = $entry;
		if ( count( $history ) > 50 ) {
			$history = array_slice( $history, -50 );
		}
		update_post_meta( $property_id, self::META_HISTORY, $history );
	}

	/**
	 * @param string $status Raw status.
	 * @return string
	 */
	private function normalize_status( string $status ): string {
		$status = strtolower( trim( $status ) );
		$map    = array(
			'draft'           => self::STATUS_DRAFT,
			'pending'         => self::STATUS_PENDING,
			'pending_review'  => self::STATUS_PENDING,
			'publish'         => self::STATUS_PUBLISHED,
			'published'       => self::STATUS_PUBLISHED,
			'rejected'        => self::STATUS_REJECTED,
			'reject'          => self::STATUS_REJECTED,
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : '';
	}
}
