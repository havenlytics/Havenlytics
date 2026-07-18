<?php
/**
 * Taxonomy request in-app and email notifications.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests;

use HvnlyNab\Email\EmailConstants;
use HvnlyNab\Email\EmailHeaders;
use HvnlyNab\Email\EmailRenderer;
use HvnlyNab\Workspace\Notifications\NotificationRepository;
use HvnlyNab\Workspace\TaxonomyRequests\Admin\TaxonomyRequestsAdminPage;
use HvnlyNab\Workspace\WorkspaceSettings;

defined( 'ABSPATH' ) || exit;

final class TaxonomyRequestNotifier {

	/** @var NotificationRepository */
	private $notifications;

	/** @var EmailRenderer */
	private $renderer;

	public function __construct( ?NotificationRepository $notifications = null, ?EmailRenderer $renderer = null ) {
		$this->notifications = $notifications ? $notifications : new NotificationRepository();
		$this->renderer      = $renderer ? $renderer : new EmailRenderer();
	}

	public function register(): void {
		add_action( 'hvnly_taxonomy_request_status_changed', array( $this, 'notify' ), 10, 5 );
	}

	/**
	 * @param int                  $id Request ID.
	 * @param string               $to New status.
	 * @param string               $from Old status.
	 * @param int                  $actor_id Actor.
	 * @param array<string, mixed> $request Request.
	 */
	public function notify( int $id, string $to, string $from, int $actor_id, array $request ): void {
		unset( $from, $actor_id );
		if ( function_exists( 'hvnly_is_system_notification_context' ) && hvnly_is_system_notification_context() ) {
			return;
		}
		$map = $this->messages();
		if ( ! isset( $map[ $to ] ) ) {
			return;
		}
		$user_id = absint( $request['user_id'] ?? 0 );
		$user    = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}
		$name    = (string) ( $request['requested_name'] ?? '' );
		$message = $map[ $to ];
		$url     = WorkspaceSettings::route_url( '' );

		if ( (bool) apply_filters( 'hvnly_taxonomy_request_in_app_enabled', true, $user_id, $to, $request ) ) {
			$this->notifications->create(
				array(
					'user_id'    => $user_id,
					'agent_id'   => absint( $request['agent_id'] ?? 0 ),
					'type'       => 'taxonomy_request_' . $to,
					'category'   => 'account',
					'title'      => $message['title'],
					'body'       => sprintf( $message['body'], $name ),
					'icon'       => 'tag',
					// In-app deep link is an SPA fragment route (the email CTA
					// below keeps the absolute $url). Storing an absolute URL
					// here made the SPA router unable to resolve a target.
					'action_url' => '#/dashboard',
					'payload'    => array(
						'requestId' => $id,
						'status' => $to,
						'taxonomy' => (string) $request['taxonomy'],
					),
				)
			);
		}

		if ( (bool) apply_filters( 'hvnly_taxonomy_request_email_enabled', true, $user_id, $to, $request ) && is_email( $user->user_email ) ) {
			$this->send( $user->user_email, $user->display_name, $message, $name, $to, $request, $url );
		}

		if ( TaxonomyRequestConstants::STATUS_PENDING === $to ) {
			$this->notify_admin( $user, $request );
		}
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private function messages(): array {
		return array(
			TaxonomyRequestConstants::STATUS_PENDING => array(
				'type' => EmailConstants::TYPE_TAXONOMY_REQUEST_SUBMITTED,
				'title' => __( 'Taxonomy request submitted', 'havenlytics' ),
				/* translators: %s: requested taxonomy term name. */
				'body' => __( 'Your request for “%s” is awaiting review.', 'havenlytics' ),
			),
			TaxonomyRequestConstants::STATUS_APPROVED => array(
				'type' => EmailConstants::TYPE_TAXONOMY_REQUEST_APPROVED,
				'title' => __( 'Taxonomy request approved', 'havenlytics' ),
				/* translators: %s: requested taxonomy term name. */
				'body' => __( 'Your request for “%s” was approved.', 'havenlytics' ),
			),
			TaxonomyRequestConstants::STATUS_REJECTED => array(
				'type' => EmailConstants::TYPE_TAXONOMY_REQUEST_REJECTED,
				'title' => __( 'Taxonomy request rejected', 'havenlytics' ),
				/* translators: %s: requested taxonomy term name. */
				'body' => __( 'Your request for “%s” was rejected.', 'havenlytics' ),
			),
			TaxonomyRequestConstants::STATUS_MERGED => array(
				'type' => EmailConstants::TYPE_TAXONOMY_REQUEST_MERGED,
				'title' => __( 'Taxonomy request matched', 'havenlytics' ),
				/* translators: %s: requested taxonomy term name. */
				'body' => __( 'Your request for “%s” was matched to an existing term.', 'havenlytics' ),
			),
		);
	}

	/**
	 * @param array<string, string> $message Message.
	 * @param array<string, mixed>  $request Request.
	 */
	private function send( string $email, string $user_name, array $message, string $name, string $status, array $request, string $url ): bool {
		$details = array(
			__( 'Requested term', 'havenlytics' ) => $name,
			__( 'Status', 'havenlytics' )         => ucfirst( $status ),
		);
		if ( TaxonomyRequestConstants::STATUS_REJECTED === $status && ! empty( $request['rejection_reason'] ) ) {
			$details[ __( 'Reason', 'havenlytics' ) ] = (string) $request['rejection_reason'];
		}
		$intro   = sprintf( $message['body'], $name );
		$context = array(
			'title'       => $message['title'],
			'preheader'   => $intro,
			'user_name'   => sanitize_text_field( $user_name ),
			'intro'       => $intro,
			'detail_rows' => $details,
			'cta_url'     => esc_url_raw( $url ),
			'cta_label'   => __( 'Open Workspace', 'havenlytics' ),
			'footnote'    => '',
		);
		$body    = $this->renderer->render( $message['type'], $context );
		if ( '' === trim( $body ) ) {
			return false;
		}
		EmailHeaders::attach_plain_text_alt( $body );
		try {
			return (bool) wp_mail( sanitize_email( $email ), str_replace( array( "\r", "\n" ), '', $message['title'] ), $body, EmailHeaders::base() );
		} catch ( \Throwable $exception ) {
			if ( function_exists( 'hvnly_debug_log' ) ) {
				hvnly_debug_log( $exception->getMessage(), 'Taxonomy Request Email' );
			}
			return false;
		}
	}

	/**
	 * @param array<string, mixed> $request Request.
	 */
	private function notify_admin( \WP_User $requester, array $request ): void {
		$email = sanitize_email( (string) apply_filters( 'hvnly_taxonomy_request_admin_email', get_option( 'admin_email' ), $request ) );
		if ( ! is_email( $email ) || strtolower( $email ) === strtolower( (string) $requester->user_email ) ) {
			return;
		}
		$url     = TaxonomyRequestsAdminPage::url( absint( $request['id'] ?? 0 ) );
		$message = array(
			'type'  => EmailConstants::TYPE_TAXONOMY_REQUEST_SUBMITTED,
			'title' => __( 'New taxonomy request awaiting review', 'havenlytics' ),
			/* translators: %s: requested taxonomy term name. */
			'body'  => __( 'A Workspace agent requested the term “%s”.', 'havenlytics' ),
		);
		$this->send( $email, '', $message, (string) $request['requested_name'], TaxonomyRequestConstants::STATUS_PENDING, $request, $url );
	}
}
