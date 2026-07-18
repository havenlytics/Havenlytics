<?php
/**
 * Cached pending-review counter for the Havenlytics Properties admin menu badge.
 *
 * Counts `post_status=pending` for {@see PropertyFormMapper::POST_TYPE} only
 * (awaiting review), not total listings. Badge markup matches WordPress Comments.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Properties menu pending-review badge.
 *
 * @since 3.2.0
 */
final class PropertyPendingReviewCounter {

	/**
	 * Transient key for the pending count cache.
	 *
	 * @var string
	 */
	private const TRANSIENT = 'hvnly_ws_property_pending_review_count';

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'apply_menu_badge' ), 999 );
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 30, 3 );
		add_action( 'updated_post_meta', array( $this, 'on_listing_status_meta' ), 40, 4 );
		add_action( 'added_post_meta', array( $this, 'on_listing_status_meta' ), 40, 4 );
		add_action( 'deleted_post', array( $this, 'bust' ) );
	}

	/**
	 * Append an awaiting-mod badge to the Havenlytics / Properties admin menus.
	 *
	 * @return void
	 */
	public function apply_menu_badge(): void {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$count = self::get_count();
		$badge = self::menu_badge_html( $count );
		if ( '' === $badge ) {
			return;
		}

		$slug = 'edit.php?post_type=' . PropertyFormMapper::POST_TYPE;

		global $menu, $submenu;

		if ( is_array( $menu ) ) {
			foreach ( $menu as $index => $item ) {
				if ( ! is_array( $item ) || ! isset( $item[2] ) || $slug !== (string) $item[2] ) {
					continue;
				}
				if ( isset( $menu[ $index ][0] ) && false === strpos( (string) $menu[ $index ][0], 'awaiting-mod' ) ) {
					$menu[ $index ][0] .= $badge;
				}
				break;
			}
		}

		if ( is_array( $submenu ) && isset( $submenu[ $slug ] ) && is_array( $submenu[ $slug ] ) ) {
			foreach ( $submenu[ $slug ] as $index => $item ) {
				if ( ! is_array( $item ) || ! isset( $item[2] ) ) {
					continue;
				}
				// Badge "Properties" (all_items) — same slug as parent, first matching row.
				if ( $slug !== (string) $item[2] ) {
					continue;
				}
				if ( isset( $submenu[ $slug ][ $index ][0] ) && false === strpos( (string) $submenu[ $slug ][ $index ][0], 'awaiting-mod' ) ) {
					$submenu[ $slug ][ $index ][0] .= $badge;
				}
				break;
			}
		}
	}

	/**
	 * Bust cache when a property post status changes.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public function on_transition_post_status( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || PropertyFormMapper::POST_TYPE !== $post->post_type ) {
			return;
		}
		if ( (string) $new_status === (string) $old_status ) {
			return;
		}
		if ( 'pending' !== (string) $new_status && 'pending' !== (string) $old_status ) {
			return;
		}
		self::bust();
	}

	/**
	 * Bust cache when listing-status meta moves to/from Pending (Rejected uses draft).
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 * @return void
	 */
	public function on_listing_status_meta( $meta_id, $object_id, $meta_key, $meta_value ): void {
		unset( $meta_id, $object_id );
		if ( PropertyFormMapper::META_LISTING_STATUS !== (string) $meta_key ) {
			return;
		}
		$label = sanitize_text_field( (string) $meta_value );
		if ( 'Pending' === $label || 'Published' === $label || 'Rejected' === $label || 'Draft' === $label ) {
			self::bust();
		}
	}

	/**
	 * Count properties awaiting review (`post_status=pending`).
	 *
	 * @return int
	 */
	public static function get_count(): int {
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return max( 0, (int) $cached );
		}

		$counts = wp_count_posts( PropertyFormMapper::POST_TYPE );
		$count  = ( is_object( $counts ) && isset( $counts->pending ) ) ? (int) $counts->pending : 0;
		$count  = max( 0, $count );

		set_transient( self::TRANSIENT, $count, MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Clear cached pending count.
	 *
	 * @return void
	 */
	public static function bust(): void {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * WordPress Comments-style awaiting-mod badge HTML.
	 *
	 * @param int $count Pending count.
	 * @return string
	 */
	public static function menu_badge_html( int $count ): string {
		if ( $count <= 0 ) {
			return '';
		}

		return sprintf(
			' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%1$d</span></span>',
			$count
		);
	}
}
