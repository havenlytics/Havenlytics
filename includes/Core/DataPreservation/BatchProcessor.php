<?php
/**
 * Batched property iteration for migrations (no posts_per_page => -1).
 *
 * @package HvnlyNab\Core\DataPreservation
 * @since   3.0.2
 */

namespace HvnlyNab\Core\DataPreservation;

defined( 'ABSPATH' ) || exit;

class BatchProcessor {

	/**
	 * Process properties in batches.
	 *
	 * @param callable $callback function( int $post_id ): void
	 * @param string   $state_key Option key for resume offset.
	 * @param int|null $batch_size Batch size (default HVNLY_MIGRATION_BATCH_SIZE).
	 * @return array{processed:int,complete:bool,offset:int}
	 */
	public static function each_property( callable $callback, string $state_key = '', ?int $batch_size = null ): array {
		$batch_size = $batch_size ?? (int) ( defined( 'HVNLY_MIGRATION_BATCH_SIZE' ) ? HVNLY_MIGRATION_BATCH_SIZE : 100 );
		$offset     = $state_key !== '' ? (int) get_option( $state_key, 0 ) : 0;

		$query = new \WP_Query(
			array(
				'post_type'              => 'hvnly_property',
				'post_status'            => 'any',
				'posts_per_page'         => $batch_size,
				'offset'                 => $offset,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$processed = 0;
		foreach ( $query->posts as $post_id ) {
			$callback( (int) $post_id );
			++$processed;
		}

		$total      = (int) $query->found_posts;
		$new_offset = $offset + $processed;
		$complete   = $new_offset >= $total || $processed === 0;

		if ( $state_key !== '' ) {
			if ( $complete ) {
				delete_option( $state_key );
			} else {
				update_option( $state_key, $new_offset, false );
			}
		}

		return array(
			'processed' => $processed,
			'complete'  => $complete,
			'offset'    => $new_offset,
			'total'     => $total,
		);
	}

	/**
	 * Count properties without loading all IDs.
	 *
	 * @return int
	 */
	public static function count_properties(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Efficient property count for batch migrations.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash'",
				'hvnly_property'
			)
		);
	}
}
