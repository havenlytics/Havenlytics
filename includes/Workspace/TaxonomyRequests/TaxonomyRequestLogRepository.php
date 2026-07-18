<?php
/**
 * Append-only taxonomy request audit repository.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests;

use HvnlyNab\Workspace\Security\WorkspaceSecurity;

defined( 'ABSPATH' ) || exit;

final class TaxonomyRequestLogRepository {

	/**
	 * @param array<string, mixed> $request Request snapshot.
	 * @param string               $action Action.
	 * @param string               $previous Previous status.
	 * @param string               $next New status.
	 * @param int                  $actor_id Actor.
	 * @param string               $reason Reason.
	 * @param array<string, mixed> $context Context.
	 * @return int|\WP_Error
	 */
	public function append( array $request, string $action, string $previous, string $next, int $actor_id, string $reason = '', array $context = array() ) {
		if ( ! TaxonomyRequestSchema::logs_exist() ) {
			return new \WP_Error( 'hvnly_taxonomy_log_storage_unavailable', __( 'Taxonomy request audit storage is unavailable.', 'havenlytics' ) );
		}

		$ip = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidate = sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
			$ip        = filter_var( $candidate, FILTER_VALIDATE_IP ) ? $candidate : '';
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 )
			: '';

		$row = array(
			'request_id'      => absint( $request['id'] ?? 0 ),
			'user_id'         => absint( $request['user_id'] ?? 0 ),
			'actor_id'        => absint( $actor_id ),
			'taxonomy'        => sanitize_key( (string) ( $request['taxonomy'] ?? '' ) ),
			'requested_name'  => sanitize_text_field( (string) ( $request['requested_name'] ?? '' ) ),
			'previous_status' => sanitize_key( $previous ),
			'new_status'      => sanitize_key( $next ),
			'action'          => sanitize_key( $action ),
			'reason'          => sanitize_textarea_field( $reason ),
			'context'         => wp_json_encode( WorkspaceSecurity::array_values( $context ) ),
			'ip_address'      => $ip,
			'user_agent'      => $ua,
			'created_at'      => current_time( 'mysql', true ),
		);

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			TaxonomyRequestSchema::logs_table(),
			$row,
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			return new \WP_Error( 'hvnly_taxonomy_log_failed', __( 'Unable to write the taxonomy request audit log.', 'havenlytics' ) );
		}
		$id = (int) $wpdb->insert_id;
		WorkspaceSecurity::audit( 'taxonomy_request_' . $row['action'], $row );
		do_action( 'hvnly_taxonomy_request_logged', $id, $row );
		return $id;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function for_request( int $request_id, int $limit = 100 ): array {
		if ( $request_id <= 0 || ! TaxonomyRequestSchema::logs_exist() ) {
			return array();
		}
		global $wpdb;
		$table = TaxonomyRequestSchema::logs_table();
		$limit = min( 200, max( 1, $limit ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE request_id = %d ORDER BY created_at DESC, id DESC LIMIT %d", $request_id, $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
