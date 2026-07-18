<?php
/**
 * Property CPT list workflow actions (Approve / Reject pending listings).
 *
 * Reuses {@see PropertyWorkflowService} — no parallel approval system.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use HvnlyNab\Workspace\Auth\PortalCapabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Admin row / bulk actions for hvnly_property editorial workflow.
 *
 * @since 3.2.0
 */
final class PropertyWorkflowAdminActions {

	public const NONCE = 'hvnly_ws_property_workflow';

	/**
	 * @return void
	 */
	public function register(): void {
		$slug = PropertyFormMapper::POST_TYPE;

		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 20, 2 );
		add_filter( 'bulk_actions-edit-' . $slug, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . $slug, array( $this, 'handle_bulk' ), 10, 3 );
		add_action( 'admin_post_hvnly_ws_prop_approve', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_hvnly_ws_prop_reject', array( $this, 'handle_reject' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * @return bool
	 */
	private function can_moderate(): bool {
		return current_user_can( 'manage_options' )
			|| current_user_can( PortalCapabilities::APPROVE_LISTINGS )
			|| current_user_can( 'publish_posts' );
	}

	/**
	 * @param array<string, string> $actions Actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function row_actions( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		if ( ! $this->can_moderate() || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$workflow = new PropertyWorkflowService();
		$status   = $workflow->get_workflow_status( $post );

		if ( PropertyWorkflowService::STATUS_PENDING === $status ) {
			$actions['hvnly_ws_approve'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( 'hvnly_ws_prop_approve', (int) $post->ID ) ),
				esc_html__( 'Approve Listing', 'havenlytics' )
			);
			$actions['hvnly_ws_reject'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( 'hvnly_ws_prop_reject', (int) $post->ID ) ),
				esc_html__( 'Reject Listing', 'havenlytics' )
			);
		}

		return $actions;
	}

	/**
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( $actions ) {
		if ( ! $this->can_moderate() ) {
			return $actions;
		}
		$actions['hvnly_ws_prop_approve'] = __( 'Approve Listings', 'havenlytics' );
		$actions['hvnly_ws_prop_reject']  = __( 'Reject Listings', 'havenlytics' );
		return $actions;
	}

	/**
	 * @param string $redirect Redirect.
	 * @param string $doaction Action.
	 * @param int[]  $post_ids IDs.
	 * @return string
	 */
	public function handle_bulk( $redirect, $doaction, $post_ids ) {
		if ( ! $this->can_moderate() ) {
			return $redirect;
		}

		$workflow = new PropertyWorkflowService();
		$updated  = 0;

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			$post    = get_post( $post_id );
			if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
				continue;
			}
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}

			if ( 'hvnly_ws_prop_approve' === $doaction ) {
				$result = $workflow->publish( $post );
				if ( ! is_wp_error( $result ) ) {
					++$updated;
				}
			} elseif ( 'hvnly_ws_prop_reject' === $doaction ) {
				$result = $workflow->change_status( $post, PropertyWorkflowService::STATUS_REJECTED, '', 'reject' );
				if ( ! is_wp_error( $result ) ) {
					++$updated;
				}
			}
		}

		return add_query_arg(
			array(
				'hvnly_ws_prop_bulk' => sanitize_key( (string) $doaction ),
				'hvnly_ws_prop_n'    => $updated,
			),
			$redirect
		);
	}

	/**
	 * @return void
	 */
	public function handle_approve(): void {
		$this->handle_single( 'approve' );
	}

	/**
	 * @return void
	 */
	public function handle_reject(): void {
		$this->handle_single( 'reject' );
	}

	/**
	 * @param string $action approve|reject.
	 * @return void
	 */
	private function handle_single( string $action ): void {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( $post_id <= 0 || ! wp_verify_nonce( $nonce, self::NONCE . '_' . $post_id ) ) {
			wp_die( esc_html__( 'Invalid property workflow action.', 'havenlytics' ) );
		}
		if ( ! $this->can_moderate() || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to moderate this listing.', 'havenlytics' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Property not found.', 'havenlytics' ) );
		}

		$workflow = new PropertyWorkflowService();
		if ( 'approve' === $action ) {
			$workflow->publish( $post );
			$status = 'approved';
		} else {
			$workflow->change_status( $post, PropertyWorkflowService::STATUS_REJECTED, '', 'reject' );
			$status = 'rejected';
		}

		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=' . PropertyFormMapper::POST_TYPE );
		}

		wp_safe_redirect( add_query_arg( 'hvnly_ws_prop', $status, $redirect ) );
		exit;
	}

	/**
	 * @param string $action Action name.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	private function action_url( string $action, int $post_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => $action,
					'post_id' => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE . '_' . $post_id
		);
	}

	/**
	 * @return void
	 */
	public function admin_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PropertyFormMapper::POST_TYPE !== $screen->post_type ) {
			return;
		}

		if ( ! empty( $_GET['hvnly_ws_prop_bulk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = isset( $_GET['hvnly_ws_prop_n'] ) ? absint( $_GET['hvnly_ws_prop_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: count */
						_n( '%d listing updated.', '%d listings updated.', $count, 'havenlytics' ),
						$count
					)
				)
			);
			return;
		}

		if ( empty( $_GET['hvnly_ws_prop'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$status = sanitize_key( wp_unslash( $_GET['hvnly_ws_prop'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg    = 'approved' === $status
			? __( 'Listing approved and published.', 'havenlytics' )
			: __( 'Listing rejected.', 'havenlytics' );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $msg )
		);
	}
}
