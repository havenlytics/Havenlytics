<?php
/**
 * Property management helpers — trash, restore, delete, duplicate.
 *
 * @package HvnlyNab\Workspace\Api
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Api;

use WP_Error;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Soft-delete / restore / force-delete / duplicate for Workspace listings.
 *
 * @since 3.2.0
 */
final class PropertyManagementService {

	/**
	 * @var PropertyAccessGate
	 */
	private $access;

	/**
	 * @param PropertyAccessGate|null $access Access gate.
	 */
	public function __construct( ?PropertyAccessGate $access = null ) {
		$this->access = $access ? $access : new PropertyAccessGate();
	}

	/**
	 * Move to trash.
	 *
	 * @param WP_Post $post Property.
	 * @return WP_Post|WP_Error
	 */
	public function trash( WP_Post $post ) {
		if ( ! $this->access->can_trash( (int) $post->ID ) ) {
			return new WP_Error(
				'hvnly_trash_forbidden',
				__( 'You cannot trash this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$result = wp_trash_post( (int) $post->ID );
		if ( ! $result ) {
			return new WP_Error(
				'hvnly_trash_failed',
				__( 'Could not move property to trash.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		$fresh = get_post( (int) $post->ID );
		return $fresh ? $fresh : $post;
	}

	/**
	 * Restore from trash (returns to draft).
	 *
	 * @param WP_Post $post Property.
	 * @return WP_Post|WP_Error
	 */
	public function restore( WP_Post $post ) {
		if ( ! $this->access->can_restore( (int) $post->ID ) ) {
			return new WP_Error(
				'hvnly_restore_forbidden',
				__( 'You cannot restore this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$result = wp_untrash_post( (int) $post->ID );
		if ( ! $result ) {
			return new WP_Error(
				'hvnly_restore_failed',
				__( 'Could not restore property.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		// WP may restore previous status; normalize rejected/draft listing label.
		$fresh = get_post( (int) $post->ID );
		if ( $fresh && 'publish' !== $fresh->post_status && 'pending' !== $fresh->post_status ) {
			wp_update_post(
				array(
					'ID'          => (int) $fresh->ID,
					'post_status' => 'draft',
				)
			);
			PropertyFormMapper::sync_listing_status( (int) $fresh->ID, 'Draft' );
			$fresh = get_post( (int) $fresh->ID );
		}

		return $fresh ? $fresh : $post;
	}

	/**
	 * Permanently delete (admin only).
	 *
	 * @param WP_Post $post Property.
	 * @return true|WP_Error
	 */
	public function delete_permanently( WP_Post $post ) {
		if ( ! $this->access->can_delete_permanently( (int) $post->ID ) ) {
			return new WP_Error(
				'hvnly_delete_forbidden',
				__( 'Only administrators can permanently delete properties.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$result = wp_delete_post( (int) $post->ID, true );
		if ( ! $result ) {
			return new WP_Error(
				'hvnly_delete_failed',
				__( 'Could not permanently delete property.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * Duplicate a property as a new draft owned by the current user.
	 *
	 * @param WP_Post $post Source property.
	 * @return WP_Post|WP_Error
	 */
	public function duplicate( WP_Post $post ) {
		if ( ! $this->access->can_duplicate( (int) $post->ID ) ) {
			return new WP_Error(
				'hvnly_duplicate_forbidden',
				__( 'You cannot duplicate this property.', 'havenlytics' ),
				array( 'status' => 403 )
			);
		}

		$user_id = get_current_user_id();
		$title   = (string) $post->post_title;
		if ( '' === $title ) {
			$title = __( 'Untitled property', 'havenlytics' );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => PropertyFormMapper::POST_TYPE,
				'post_status'  => 'draft',
				'post_title'   => $title . ' ' . __( '(Copy)', 'havenlytics' ),
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
				'post_author'  => $user_id,
			),
			true
		);

		if ( is_wp_error( $new_id ) || ! $new_id ) {
			return new WP_Error(
				'hvnly_duplicate_failed',
				__( 'Could not duplicate property.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		$new_id = (int) $new_id;
		$this->copy_meta( (int) $post->ID, $new_id );
		PropertyFormMapper::sync_listing_status( $new_id, 'Draft' );

		$thumb = get_post_thumbnail_id( $post );
		if ( $thumb ) {
			set_post_thumbnail( $new_id, $thumb );
		}

		$taxonomies = get_object_taxonomies( PropertyFormMapper::POST_TYPE );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( (int) $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_id, array_map( 'absint', $terms ), $taxonomy, false );
			}
		}

		$fresh = get_post( $new_id );
		return $fresh ? $fresh : new WP_Error(
			'hvnly_duplicate_failed',
			__( 'Could not duplicate property.', 'havenlytics' ),
			array( 'status' => 500 )
		);
	}

	/**
	 * Copy post meta except edit locks / workflow history.
	 *
	 * @param int $from From ID.
	 * @param int $to   To ID.
	 * @return void
	 */
	private function copy_meta( int $from, int $to ): void {
		$all = get_post_meta( $from );
		if ( ! is_array( $all ) ) {
			return;
		}

		$skip = array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			PropertyWorkflowService::META_HISTORY,
			PropertyWorkflowService::META_REJECT_REASON,
		);

		foreach ( $all as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			if ( ! is_array( $values ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $to, $key, maybe_unserialize( $value ) );
			}
		}
	}
}
