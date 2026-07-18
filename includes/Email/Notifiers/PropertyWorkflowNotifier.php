<?php
/**
 * Listing lifecycle emails: submitted, approved/published, rejected.
 *
 * Sprint 24C. These events already produced an in-app notification but never an
 * email — and the three settings toggles that claimed to control them
 * (hvnly_workspace_email_property_submitted / _approved / _rejected) had no PHP
 * consumer at all. This notifier wires them up.
 *
 * It deliberately listens to the SAME trigger the in-app listener already uses
 * (the listing-status meta), so the two stay in step and no Property module code
 * has to change.
 *
 * Sprint 28A. Hardened reliability:
 * - One mail dispatcher for agent + admin alerts (no duplicated wp_mail stacks).
 * - Dedup records Draft/non-emailable moves so Draft → Published can fire again.
 * - Ownership resolves assigned agent → workspace linked user → post_author.
 * - Admin pending alert still fires when agent mail cannot (missing author email).
 * - Classic editor / Quick Edit / Bulk Edit reach this notifier via
 *   {@see \HvnlyNab\Workspace\Api\PropertyListingStatusBridge}.
 *
 * @package HvnlyNab\Email\Notifiers
 * @since   3.2.1
 */

namespace HvnlyNab\Email\Notifiers;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\Agent\PropertyAgentResolver;
use HvnlyNab\Email\EmailBranding;
use HvnlyNab\Email\EmailConstants;
use HvnlyNab\Email\EmailHeaders;
use HvnlyNab\Email\EmailRenderer;
use HvnlyNab\Workspace\Api\PropertyFormMapper;
use HvnlyNab\Workspace\Api\PropertyWorkflowService;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.1
 */
class PropertyWorkflowNotifier {

	/**
	 * Meta key recording the last status we emailed about, so a save that writes
	 * the same meta twice does not send two emails.
	 *
	 * @var string
	 */
	private const META_LAST_EMAILED = '_hvnly_last_emailed_listing_status';

	/**
	 * @var EmailRenderer
	 */
	private $renderer;

	/**
	 * @param EmailRenderer|null $renderer Renderer.
	 */
	public function __construct( ?EmailRenderer $renderer = null ) {
		$this->renderer = $renderer ? $renderer : new EmailRenderer();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'updated_post_meta', array( $this, 'on_listing_status_meta' ), 30, 4 );
		add_action( 'added_post_meta', array( $this, 'on_listing_status_meta' ), 30, 4 );
		// Reliable companion trigger — fires even when the meta write above is a
		// no-op (unchanged value). Idempotent via the same once-per-transition dedup.
		add_action( 'hvnly_property_workflow_transitioned', array( $this, 'on_workflow_transitioned' ), 30, 4 );
	}

	/**
	 * Reliable transition trigger — belt-and-suspenders companion to the listing
	 * status meta hook. WordPress skips updated_post_meta when the meta value is
	 * unchanged, so a re-submit of a listing already stamped 'Pending' could
	 * otherwise notify nobody. {@see PropertyWorkflowService::transition()} fires
	 * this on every successful transition; the shared once-per-transition dedup in
	 * {@see self::on_listing_status_meta()} stops it double-sending when the meta
	 * hook already handled the same move.
	 *
	 * @param int    $object_id Listing ID.
	 * @param string $to        Target workflow status (draft|pending|publish|rejected).
	 * @param string $from      Previous workflow status (unused).
	 * @param int    $user_id   Acting user ID (unused).
	 * @return void
	 */
	public function on_workflow_transitioned( $object_id, $to, $from = '', $user_id = 0 ): void {
		unset( $from, $user_id );

		if ( ! class_exists( PropertyWorkflowService::class ) ) {
			return;
		}

		$label = PropertyWorkflowService::label_for( (string) $to );
		$this->on_listing_status_meta( 0, (int) $object_id, PropertyFormMapper::META_LISTING_STATUS, $label );
	}

	/**
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function on_listing_status_meta( $meta_id, $object_id, $meta_key, $meta_value ): void {
		unset( $meta_id );

		if ( ! class_exists( PropertyFormMapper::class ) ) {
			return;
		}

		if ( PropertyFormMapper::META_LISTING_STATUS !== (string) $meta_key ) {
			return;
		}

		$object_id = absint( $object_id );

		if ( PropertyFormMapper::POST_TYPE !== get_post_type( $object_id ) ) {
			return;
		}

		// Never email during a system event — demo / bulk import, migration,
		// repair or a WP-CLI/cron bulk pass. A bulk status write must not turn
		// into a thousand emails. Shared with the status bridge + in-app listener
		// so one gate decides "human workflow vs system" everywhere (Sprint 28E).
		if ( hvnly_is_system_notification_context() ) {
			return;
		}

		/**
		 * Filter whether listing lifecycle emails may send in the current request.
		 *
		 * Return false from a bulk/import routine to suppress the whole batch.
		 *
		 * @since 3.2.1
		 *
		 * @param bool $enabled   Whether to send.
		 * @param int  $object_id Listing ID.
		 */
		if ( ! apply_filters( 'hvnly_email_property_workflow_enabled', true, $object_id ) ) {
			return;
		}

		$label = sanitize_text_field( (string) $meta_value );

		// updated_post_meta can fire more than once for a single save. Only email
		// when the status actually moved to somewhere new.
		$last = (string) get_post_meta( $object_id, self::META_LAST_EMAILED, true );
		if ( $last === $label ) {
			return;
		}

		$email_type = $this->resolve_type( $label );

		/*
		 * Sprint 28A: always record non-emailable moves (Draft) and disabled toggles
		 * so a later Published/Pending/Rejected transition is not stuck behind a
		 * stale "last emailed" value from a previous cycle (republish / re-submit).
		 */
		if ( '' === $email_type || ! $this->is_enabled( $email_type ) ) {
			update_post_meta( $object_id, self::META_LAST_EMAILED, $label );
			return;
		}

		$owner   = $this->resolve_owner_user( $object_id );
		$context = $this->build_context( $object_id, $owner, $email_type, $label );

		// Sprint 28E: a lifecycle email confirms an event to the OWNING AGENT.
		// When the owner IS the person performing the action — an administrator
		// publishing their own listing, or an agent submitting / self-publishing
		// their own — no "your property has been…" email should go back to them
		// about their own click. The administrator submission alert below is
		// independent and still fires so admins learn of agent submissions.
		$attempted = false;
		$delivered = true;
		$sent      = false;
		if ( $owner instanceof \WP_User && ! $this->owner_is_current_actor( $owner ) && is_email( $owner->user_email ) ) {
			$attempted = true;
			$subject   = $this->resolve_subject( $email_type, $context );
			$sent      = $this->dispatch( $owner->user_email, $subject, $email_type, $context );
			$delivered = $delivered && $sent;
		}

		// Sprint 27D / 28A: submitted listings also alert the administrator —
		// even when the agent mail could not be delivered (missing owner email).
		if ( EmailConstants::TYPE_PROPERTY_SUBMITTED === $email_type ) {
			$admin_sent = $this->notify_admin_of_submission( $object_id, $context );
			if ( null !== $admin_sent ) {
				$attempted = true;
				$delivered = $delivered && $admin_sent;
			}
		}

		/*
		 * Sprint 31 (reliability fix): record the once-per-transition dedup marker
		 * only when nothing was left UNDELIVERED. Previously this write was
		 * unconditional, so a transient wp_mail() failure marked the transition
		 * "already emailed" forever and it never retried — a silently lost email.
		 * A clean send, or an intentional skip with no send attempt (owner is the
		 * actor / no valid recipient), still advances the marker as before.
		 */
		if ( ! $attempted || $delivered ) {
			update_post_meta( $object_id, self::META_LAST_EMAILED, $label );
		}

		/**
		 * Fires after a listing lifecycle email attempt.
		 *
		 * @since 3.2.1
		 *
		 * @param bool                 $sent       Whether wp_mail succeeded.
		 * @param string               $email_type Email type.
		 * @param int                  $object_id  Listing ID.
		 * @param array<string, mixed> $context    Email context.
		 */
		do_action( 'hvnly_email_property_workflow_sent', (bool) $sent, $email_type, $object_id, $context );
	}

	/**
	 * Map the listing-status label onto an email type.
	 *
	 * @param string $label Listing status label.
	 * @return string Email type, or '' when the status is not emailable.
	 */
	private function resolve_type( string $label ): string {
		$map = array(
			'Pending'   => EmailConstants::TYPE_PROPERTY_SUBMITTED,
			'Approved'  => EmailConstants::TYPE_PROPERTY_APPROVED,
			'Published' => EmailConstants::TYPE_PROPERTY_PUBLISHED,
			'Rejected'  => EmailConstants::TYPE_PROPERTY_REJECTED,
		);

		return isset( $map[ $label ] ) ? $map[ $label ] : '';
	}

	/**
	 * Settings gate. Storage is NOT migrated — these keys keep living in
	 * hvnly_plugin_settings['contact-agent'], where they always have.
	 *
	 * @param string $email_type Email type.
	 * @return bool
	 */
	private function is_enabled( string $email_type ): bool {
		$map = array(
			EmailConstants::TYPE_PROPERTY_SUBMITTED => 'hvnly_workspace_email_property_submitted',
			EmailConstants::TYPE_PROPERTY_APPROVED  => 'hvnly_workspace_email_property_approved',
			EmailConstants::TYPE_PROPERTY_PUBLISHED => 'hvnly_workspace_email_property_approved',
			EmailConstants::TYPE_PROPERTY_REJECTED  => 'hvnly_workspace_email_property_rejected',
		);

		$key = isset( $map[ $email_type ] ) ? $map[ $email_type ] : '';
		if ( '' === $key ) {
			return false;
		}

		$stored = get_option( 'hvnly_plugin_settings', array() );
		$group  = ( is_array( $stored ) && isset( $stored['contact-agent'] ) && is_array( $stored['contact-agent'] ) )
			? $stored['contact-agent']
			: array();

		if ( ! array_key_exists( $key, $group ) ) {
			return true; // Default on when unset.
		}

		return ! empty( $group[ $key ] );
	}

	/**
	 * Resolve the workspace account that owns this listing for notifications.
	 *
	 * property → assigned agent CPT → linked WP user → post_author fallback.
	 *
	 * @param int $property_id Property ID.
	 * @return \WP_User|null
	 */
	private function resolve_owner_user( int $property_id ): ?\WP_User {
		$user_id = 0;

		if ( class_exists( PropertyAgentResolver::class ) ) {
			$primary = PropertyAgentResolver::get_primary( $property_id );
			if ( ! empty( $primary['id'] ) ) {
				$user_id = absint( get_post_meta( (int) $primary['id'], AgentConstants::META_LINKED_USER_ID, true ) );
			}
			if ( $user_id <= 0 && ! empty( $primary['user_id'] ) ) {
				$user_id = absint( $primary['user_id'] );
			}
		}

		if ( $user_id <= 0 ) {
			$user_id = (int) get_post_field( 'post_author', $property_id );
		}

		if ( $user_id <= 0 ) {
			return null;
		}

		$user = get_userdata( $user_id );
		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * Whether the listing owner is the same human performing the current request.
	 *
	 * Guards against a self-notification: an administrator publishing their own
	 * listing, or an agent submitting / self-publishing their own, should not be
	 * emailed about the action they just performed. Returns false for background
	 * transitions with no logged-in user (e.g. a scheduled future → publish),
	 * where the owning agent should still be told their listing went live.
	 *
	 * @param \WP_User $owner Resolved listing owner.
	 * @return bool
	 */
	private function owner_is_current_actor( \WP_User $owner ): bool {
		$current = get_current_user_id();

		return $current > 0 && (int) $owner->ID === $current;
	}

	/**
	 * @param int            $object_id  Listing ID.
	 * @param \WP_User|null  $author     Listing owner (workspace account), when known.
	 * @param string         $email_type Email type.
	 * @param string         $label      Raw status label.
	 * @return array<string, mixed>
	 */
	private function build_context( int $object_id, ?\WP_User $author, string $email_type, string $label ): array {
		/*
		 * Titles come back HTML-encoded from get_the_title() — including NUMERIC
		 * entities such as &#8211; for an en dash. wp_specialchars_decode() only
		 * handles the named set (&amp; &quot; &lt; &gt; &#039;), so it leaves those
		 * untouched; html_entity_decode() is required. Decode once here so the value
		 * is clean text — templates still escape on output.
		 */
		$title = html_entity_decode( (string) get_the_title( $object_id ), ENT_QUOTES, 'UTF-8' );

		$intros = array(
			EmailConstants::TYPE_PROPERTY_SUBMITTED => __( 'Thanks — your listing is in the queue for review. We will email you as soon as it has been looked at.', 'havenlytics' ),
			EmailConstants::TYPE_PROPERTY_APPROVED  => __( 'Your listing has been approved and is ready to go live.', 'havenlytics' ),
			EmailConstants::TYPE_PROPERTY_PUBLISHED => __( 'Congratulations — your listing is live. Buyers can find it and send you inquiries from now on.', 'havenlytics' ),
			EmailConstants::TYPE_PROPERTY_REJECTED  => __( 'Your listing was sent back for changes. Open it in your workspace to see what needs adjusting, then submit it again.', 'havenlytics' ),
		);

		$titles = array(
			EmailConstants::TYPE_PROPERTY_SUBMITTED => __( 'Listing submitted for review', 'havenlytics' ),
			EmailConstants::TYPE_PROPERTY_APPROVED  => __( 'Your listing was approved', 'havenlytics' ),
			EmailConstants::TYPE_PROPERTY_PUBLISHED => __( 'Your property has been published', 'havenlytics' ),
			EmailConstants::TYPE_PROPERTY_REJECTED  => __( 'Your listing needs changes', 'havenlytics' ),
		);

		$is_live       = ( EmailConstants::TYPE_PROPERTY_PUBLISHED === $email_type );
		$permalink     = (string) get_permalink( $object_id );
		$workspace_url = $this->workspace_edit_url( $object_id );
		$cta_url       = $is_live && $permalink ? $permalink : $workspace_url;
		$cta_label     = $is_live ? __( 'View your listing', 'havenlytics' ) : __( 'Open in your workspace', 'havenlytics' );

		$user_name  = $author instanceof \WP_User ? sanitize_text_field( $author->display_name ) : '';
		$user_email = $author instanceof \WP_User ? sanitize_email( $author->user_email ) : '';

		$detail_rows = array(
			__( 'Listing', 'havenlytics' ) => $title,
			__( 'Status', 'havenlytics' )  => $label,
		);

		if ( EmailConstants::TYPE_PROPERTY_REJECTED === $email_type ) {
			$reason = trim( (string) get_post_meta( $object_id, PropertyWorkflowService::META_REJECT_REASON, true ) );
			if ( '' !== $reason ) {
				$detail_rows[ __( 'Reason', 'havenlytics' ) ] = $reason;
			}
		}

		if ( $is_live ) {
			$published_gmt = (string) get_post_field( 'post_date', $object_id );
			$detail_rows   = array(
				__( 'Listing', 'havenlytics' )     => $title,
				__( 'Published', 'havenlytics' )   => $published_gmt
					? date_i18n( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), strtotime( $published_gmt ) )
					: date_i18n( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
				__( 'Listing URL', 'havenlytics' ) => $permalink,
				__( 'Dashboard', 'havenlytics' )   => $workspace_url,
			);
		}

		$context = array_merge(
			EmailBranding::context(),
			array(
				'email_type'     => $email_type,
				'user_name'      => $user_name,
				'user_email'     => $user_email,
				'property_id'    => (string) $object_id,
				'property_title' => $title,
				'property_url'   => esc_url_raw( $permalink ),
				'edit_url'       => esc_url_raw( $workspace_url ),
				'status_label'   => $label,
				'site_name'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'site_url'       => home_url( '/' ),
				'title'          => isset( $titles[ $email_type ] ) ? $titles[ $email_type ] : __( 'Listing update', 'havenlytics' ),
				'intro'          => isset( $intros[ $email_type ] ) ? $intros[ $email_type ] : '',
				'detail_rows'    => $detail_rows,
				'cta_url'        => esc_url_raw( $cta_url ),
				'cta_label'      => $cta_label,
				'footnote'       => '',
			)
		);

		$context['preheader'] = (string) $context['intro'];

		/**
		 * Filter listing lifecycle email context.
		 *
		 * @since 3.2.1
		 *
		 * @param array<string, mixed> $context    Context.
		 * @param string               $email_type Email type.
		 * @param int                  $object_id  Listing ID.
		 */
		return (array) apply_filters( 'hvnly_email_property_workflow_context', $context, $email_type, $object_id );
	}

	/**
	 * @param string               $email_type Email type.
	 * @param array<string, mixed> $context    Context.
	 * @return string
	 */
	private function resolve_subject( string $email_type, array $context ): string {
		$title = (string) ( $context['property_title'] ?? '' );

		$map = array(
			EmailConstants::TYPE_PROPERTY_SUBMITTED => sprintf( /* translators: %s: listing title. */ __( 'Submitted for review: %s', 'havenlytics' ), $title ),
			EmailConstants::TYPE_PROPERTY_APPROVED  => sprintf( /* translators: %s: listing title. */ __( 'Approved: %s', 'havenlytics' ), $title ),
			EmailConstants::TYPE_PROPERTY_PUBLISHED => __( 'Your property has been published', 'havenlytics' ),
			EmailConstants::TYPE_PROPERTY_REJECTED  => sprintf( /* translators: %s: listing title. */ __( 'Changes needed: %s', 'havenlytics' ), $title ),
		);

		$subject = isset( $map[ $email_type ] ) ? $map[ $email_type ] : $title;

		/**
		 * Filter a listing lifecycle email subject.
		 *
		 * @since 3.2.1
		 *
		 * @param string               $subject    Subject.
		 * @param string               $email_type Email type.
		 * @param array<string, mixed> $context    Context.
		 */
		return (string) apply_filters( 'hvnly_email_property_workflow_subject', $subject, $email_type, $context );
	}

	/**
	 * Tell the administrator a new listing is awaiting review.
	 *
	 * Sprint 27D / 28A. Reuses the property-notice template + EmailHeaders via
	 * {@see self::dispatch()}. Gated by the same property_submitted toggle and the
	 * same once-per-transition dedup on the agent path.
	 *
	 * @param int                  $object_id Listing ID.
	 * @param array<string, mixed> $context   Agent-side email context.
	 * @return bool|null True/false when an alert was attempted, null when skipped
	 *                   (no valid recipient / admin self-notify) so the caller does
	 *                   not treat a deliberate skip as an undelivered failure.
	 */
	private function notify_admin_of_submission( int $object_id, array $context ): ?bool {
		$admin_email = sanitize_email( (string) get_option( 'admin_email' ) );

		/**
		 * Filter the recipient of the "new listing awaiting review" admin alert.
		 *
		 * Return '' to disable the alert.
		 *
		 * @since 3.2.2
		 *
		 * @param string $admin_email Recipient address.
		 * @param int    $object_id   Listing ID.
		 */
		$admin_email = (string) apply_filters( 'hvnly_email_property_admin_alert_email', $admin_email, $object_id );

		if ( ! is_email( $admin_email ) ) {
			return null;
		}

		/*
		 * Skip only when an administrator is the current actor and the alert would
		 * go to their own mailbox (self-notify about their own click).
		 *
		 * Do NOT skip merely because the listing owner's email equals admin_email —
		 * demo/agent accounts often reuse that address, and suppressing the alert
		 * then hides agent submissions from the site owner entirely.
		 */
		$actor = wp_get_current_user();
		if ( $actor instanceof \WP_User && $actor->ID > 0 && user_can( $actor, 'manage_options' )
			&& strtolower( $admin_email ) === strtolower( (string) $actor->user_email ) ) {
			return null;
		}

		$title       = (string) ( $context['property_title'] ?? '' );
		$author_name = (string) ( $context['user_name'] ?? '' );
		$author_mail = (string) ( $context['user_email'] ?? '' );
		$review_url  = admin_url( 'post.php?post=' . $object_id . '&action=edit' );
		$queue_url   = admin_url( 'edit.php?post_type=' . PropertyFormMapper::POST_TYPE . '&post_status=pending' );

		$admin_context = array_merge(
			EmailBranding::context(),
			array(
				'email_type'  => EmailConstants::TYPE_PROPERTY_ADMIN_ALERT,
				// No "Hi <admin>," greeting for the administrator.
				'user_name'   => '',
				'title'       => __( 'A new listing is awaiting review', 'havenlytics' ),
				'intro'       => sprintf(
					/* translators: 1: agent name, 2: listing title. */
					__( '%1$s submitted "%2$s" for review. Nothing goes live until you approve it. Open the listing below, then use Approve Listing or Reject Listing.', 'havenlytics' ),
					$author_name !== '' ? $author_name : __( 'An agent', 'havenlytics' ),
					$title
				),
				'detail_rows' => array(
					__( 'Listing', 'havenlytics' )      => $title,
					__( 'Property ID', 'havenlytics' )  => (string) $object_id,
					__( 'Agent', 'havenlytics' )        => $author_name,
					__( 'Agent email', 'havenlytics' )  => $author_mail,
					__( 'Submitted', 'havenlytics' )    => date_i18n( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) ),
					__( 'Review / Edit', 'havenlytics' ) => $review_url,
					__( 'Pending queue', 'havenlytics' ) => $queue_url,
				),
				'cta_url'     => esc_url_raw( $review_url ),
				'cta_label'   => __( 'Review / Approve listing', 'havenlytics' ),
				'footnote'    => __( 'Tip: You can also open Havenlytics → Properties → Pending, then use Approve Listing or Reject Listing on the row.', 'havenlytics' ),
				'site_name'   => (string) ( $context['site_name'] ?? wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
				'site_url'    => home_url( '/' ),
			)
		);
		$admin_context['preheader'] = (string) $admin_context['intro'];

		$subject = sprintf(
			/* translators: %s: listing title. */
			__( 'New listing awaiting review: %s', 'havenlytics' ),
			$title
		);

		$headers = EmailHeaders::base();
		$reply   = EmailHeaders::reply_to( $author_mail, $author_name );
		if ( '' !== $reply ) {
			$headers[] = $reply;
		}

		$sent = $this->dispatch(
			$admin_email,
			$subject,
			EmailConstants::TYPE_PROPERTY_ADMIN_ALERT,
			$admin_context,
			$headers
		);

		/**
		 * Fires after the admin "new listing awaiting review" alert is attempted.
		 *
		 * @since 3.2.2
		 *
		 * @param bool                 $sent          Whether wp_mail succeeded.
		 * @param int                  $object_id     Listing ID.
		 * @param array<string, mixed> $admin_context Admin email context.
		 */
		do_action( 'hvnly_email_property_admin_alert_sent', (bool) $sent, $object_id, $admin_context );

		return (bool) $sent;
	}

	/**
	 * Single mail path for every property-workflow message.
	 *
	 * Always uses {@see wp_mail()} so SMTP plugins / Mailhog / host mail work.
	 * Failures never throw — workflow already completed before dispatch runs.
	 *
	 * @param string               $to         Recipient.
	 * @param string               $subject    Subject.
	 * @param string               $email_type Email type.
	 * @param array<string, mixed> $context    Context.
	 * @param string[]|null        $headers    Optional headers (defaults to EmailHeaders::base()).
	 * @return bool Whether wp_mail reported success.
	 */
	private function dispatch( string $to, string $subject, string $email_type, array $context, ?array $headers = null ): bool {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return false;
		}

		// Prevent header injection via subject (CRLF).
		$subject = str_replace( array( "\r", "\n" ), '', $subject );

		$body = $this->renderer->render( $email_type, $context );
		if ( '' === trim( $body ) ) {
			if ( function_exists( 'hvnly_debug_log' ) ) {
				hvnly_debug_log(
					sprintf( 'Property workflow email skipped: empty body for type=%s', $email_type ),
					'Havenlytics Email'
				);
			}
			return false;
		}

		if ( null === $headers ) {
			$headers = EmailHeaders::base();
		}

		EmailHeaders::attach_plain_text_alt( $body );

		$mail_error = '';
		$on_fail    = static function ( $error ) use ( &$mail_error ) {
			if ( is_wp_error( $error ) ) {
				$mail_error = $error->get_error_message();
			}
		};
		add_action( 'wp_mail_failed', $on_fail, 10, 1 );

		$sent = false;
		try {
			$sent = (bool) wp_mail( $to, $subject, $body, $headers );
		} catch ( \Throwable $e ) {
			// Never let a mail transport exception break the listing workflow.
			$sent       = false;
			$mail_error = $e->getMessage();
			if ( function_exists( 'hvnly_debug_log' ) ) {
				hvnly_debug_log(
					sprintf(
						'Property workflow email exception type=%s to=%s error=%s',
						$email_type,
						$to,
						$e->getMessage()
					),
					'Havenlytics Email'
				);
			}
		} finally {
			remove_action( 'wp_mail_failed', $on_fail, 10 );
		}

		if ( ! $sent && function_exists( 'hvnly_debug_log' ) ) {
			hvnly_debug_log(
				sprintf(
					'Property workflow email failed type=%s to=%s%s',
					$email_type,
					$to,
					'' !== $mail_error ? ( ' error=' . $mail_error ) : ''
				),
				'Havenlytics Email'
			);
		}

		return $sent;
	}

	/**
	 * Workspace deep-link to edit this listing (falls back to workspace home).
	 *
	 * @param int $property_id Property ID.
	 * @return string
	 */
	private function workspace_edit_url( int $property_id ): string {
		$route = $property_id > 0 ? 'property/' . $property_id . '/edit' : '';

		if ( class_exists( \HvnlyNab\Workspace\WorkspaceSettings::class ) ) {
			return \HvnlyNab\Workspace\WorkspaceSettings::route_url( $route );
		}

		return $this->workspace_url();
	}

	/**
	 * @return string
	 */
	private function workspace_url(): string {
		if ( class_exists( \HvnlyNab\Workspace\WorkspaceSettings::class ) ) {
			return \HvnlyNab\Workspace\WorkspaceSettings::route_url( '' );
		}

		$page_id = absint( get_option( \HvnlyNab\Workspace\WorkspaceConstants::PAGE_ID_OPTION, 0 ) );

		$url = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/' );

		return is_string( $url ) && '' !== $url ? esc_url_raw( $url ) : home_url( '/' );
	}
}
