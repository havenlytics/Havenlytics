<?php
/**
 * Keep `_hvnly_ws_listing_status` in sync when WP post_status changes outside
 * {@see PropertyWorkflowService} (classic editor Publish, Quick Edit, Bulk Edit,
 * REST core status writes, scheduled `future` → `publish`).
 *
 * Sprint 28A. Property lifecycle emails and in-app notifications already listen
 * to the listing-status meta — this bridge is the single entry that routes every
 * WP status write onto that same meta so one dispatcher covers every path.
 *
 * Skips when {@see PropertyWorkflowService} is already transitioning (it syncs
 * meta itself) to avoid duplicate meta writes / duplicate emails.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.2
 */

namespace HvnlyNab\Workspace\Api;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.2
 */
final class PropertyListingStatusBridge {

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 20, 3 );
	}

	/**
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public function on_transition_post_status( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return;
		}

		$new_status = (string) $new_status;
		$old_status = (string) $old_status;

		if ( $new_status === $old_status ) {
			return;
		}

		// Workflow service already writes listing meta after wp_update_post.
		if ( PropertyWorkflowService::is_syncing() ) {
			return;
		}

		// Sprint 28E: demo / bulk import, migration, repair and WP-CLI passes are
		// system events, not human workflow. Never mirror their post_status writes
		// onto the listing meta — that write is what fans out to the lifecycle
		// email + in-app listeners. A published demo listing still reads as
		// "Published" because status display derives from post_status, not this
		// meta (see PropertyFormMapper::resolve_status_label()).
		if ( hvnly_is_system_notification_context() ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( (int) $post->ID ) || wp_is_post_autosave( (int) $post->ID ) ) {
			return;
		}

		$label = self::label_from_post_status( $new_status );
		if ( '' === $label ) {
			return;
		}

		/*
		 * Rejected is a listing-meta state with post_status=draft. Never clobber an
		 * intentional Rejected label when something else briefly touches draft in the
		 * same request after PropertyWorkflowService already wrote Rejected — the
		 * is_syncing() guard covers the workflow path. Outside workflow, draft means
		 * withdraw / unpublish → Draft is correct.
		 */
		PropertyFormMapper::sync_listing_status( (int) $post->ID, $label );
	}

	/**
	 * Map a WordPress post_status onto a portal listing-status label.
	 *
	 * @param string $post_status Post status.
	 * @return string Label, or '' when this status should not rewrite listing meta.
	 */
	public static function label_from_post_status( string $post_status ): string {
		switch ( $post_status ) {
			case 'pending':
				return 'Pending';
			case 'publish':
				return 'Published';
			case 'draft':
			case 'private':
				return 'Draft';
			default:
				// trash, future, auto-draft, inherit — leave listing meta alone.
				return '';
		}
	}
}
