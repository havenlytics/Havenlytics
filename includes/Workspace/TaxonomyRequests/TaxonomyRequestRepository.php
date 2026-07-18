<?php
/**
 * Taxonomy request repository.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests;

defined( 'ABSPATH' ) || exit;

final class TaxonomyRequestRepository {

	/**
	 * @param array<string, mixed> $data Row.
	 * @return int|\WP_Error
	 */
	public function create( array $data ) {
		if ( ! TaxonomyRequestSchema::tables_exist() ) {
			return new \WP_Error( 'hvnly_taxonomy_storage_unavailable', __( 'Taxonomy request storage is unavailable.', 'havenlytics' ), array( 'status' => 503 ) );
		}

		global $wpdb;
		$now = current_time( 'mysql', true );
		$row = array(
			'user_id'         => absint( $data['user_id'] ?? 0 ),
			'agent_id'        => absint( $data['agent_id'] ?? 0 ),
			'taxonomy'        => sanitize_key( (string) ( $data['taxonomy'] ?? '' ) ),
			'requested_name'  => (string) ( $data['requested_name'] ?? '' ),
			'normalized_name' => (string) ( $data['normalized_name'] ?? '' ),
			'normalized_slug' => (string) ( $data['normalized_slug'] ?? '' ),
			'description'     => (string) ( $data['description'] ?? '' ),
			'reason'          => (string) ( $data['reason'] ?? '' ),
			'status'          => TaxonomyRequestConstants::STATUS_PENDING,
			'dedupe_key'      => (string) ( $data['dedupe_key'] ?? '' ),
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			TaxonomyRequestSchema::requests_table(),
			$row,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			if ( false !== stripos( (string) $wpdb->last_error, 'duplicate' ) ) {
				$existing = $this->find_active_by_dedupe( $row['dedupe_key'] );
				return new \WP_Error(
					'hvnly_taxonomy_request_duplicate',
					__( 'An active request for this term already exists.', 'havenlytics' ),
					array(
						'status'   => 409,
						'existing' => $existing,
					)
				);
			}
			return new \WP_Error( 'hvnly_taxonomy_request_insert_failed', __( 'Unable to save the taxonomy request.', 'havenlytics' ), array( 'status' => 500 ) );
		}
		return (int) $wpdb->insert_id;
	}

	public function find( int $id ): ?array {
		if ( $id <= 0 || ! TaxonomyRequestSchema::requests_exist() ) {
			return null;
		}
		global $wpdb;
		$table = TaxonomyRequestSchema::requests_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id ), ARRAY_A );
		return is_array( $row ) ? $this->normalize( $row ) : null;
	}

	public function find_for_user( int $id, int $user_id ): ?array {
		$row = $this->find( $id );
		return $row && (int) $row['user_id'] === $user_id ? $row : null;
	}

	public function find_active_by_dedupe( string $dedupe_key ): ?array {
		if ( '' === $dedupe_key || ! TaxonomyRequestSchema::requests_exist() ) {
			return null;
		}
		global $wpdb;
		$table = TaxonomyRequestSchema::requests_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE dedupe_key = %s LIMIT 1", $dedupe_key ), ARRAY_A );
		return is_array( $row ) ? $this->normalize( $row ) : null;
	}

	/**
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( array $args = array() ): array {
		if ( ! TaxonomyRequestSchema::requests_exist() ) {
			return array();
		}
		$args   = $this->parse_args( $args );
		$where  = $this->where( $args );
		$order  = 'ASC' === $args['order'] ? 'ASC' : 'DESC';
		$cols   = array( 'id', 'user_id', 'agent_id', 'taxonomy', 'requested_name', 'status', 'created_at', 'updated_at' );
		$sort   = in_array( $args['orderby'], $cols, true ) ? $args['orderby'] : 'created_at';
		$values = array_merge( $where['values'], array( $args['limit'], $args['offset'] ) );
		global $wpdb;
		$table = TaxonomyRequestSchema::requests_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- WHERE fragments and values are built together; sort/order are allowlisted.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` {$where['sql']} ORDER BY {$sort} {$order}, id {$order} LIMIT %d OFFSET %d", $values ), ARRAY_A );
		return is_array( $rows ) ? array_map( array( $this, 'normalize' ), $rows ) : array();
	}

	/**
	 * @param array<string, mixed> $args Query args.
	 */
	public function count( array $args = array() ): int {
		if ( ! TaxonomyRequestSchema::requests_exist() ) {
			return 0;
		}
		$where = $this->where( $this->parse_args( $args ) );
		global $wpdb;
		$table = TaxonomyRequestSchema::requests_table();
		if ( empty( $where['values'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` {$where['sql']}" );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- WHERE fragments and values are built together.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` {$where['sql']}", $where['values'] ) );
	}

	/**
	 * Efficient, status-agnostic existence check (used to gate menu visibility).
	 *
	 * Runs a single primary-key `LIMIT 1` lookup — it never loads request rows
	 * and does not filter by status, so a request in ANY current or future
	 * status (pending, approved, rejected, merged, archived, …) keeps it true.
	 *
	 * @return bool True when at least one taxonomy request row exists.
	 */
	public function has_any(): bool {
		if ( ! TaxonomyRequestSchema::requests_exist() ) {
			return false;
		}
		global $wpdb;
		$table = TaxonomyRequestSchema::requests_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return null !== $wpdb->get_var( "SELECT id FROM `{$table}` LIMIT 1" );
	}

	public function count_recent_for_user( int $user_id, string $since ): int {
		return $this->count(
			array(
				'user_id'   => $user_id,
				'date_from' => $since,
			)
		);
	}

	/**
	 * Atomic pending -> processing claim.
	 *
	 * @return bool|\WP_Error
	 */
	public function claim( int $id, int $actor_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			TaxonomyRequestSchema::requests_table(),
			array(
				'status'      => TaxonomyRequestConstants::STATUS_PROCESSING,
				'reviewed_by' => $actor_id,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array(
				'id'     => $id,
				'status' => TaxonomyRequestConstants::STATUS_PENDING,
			),
			array( '%s', '%d', '%s' ),
			array( '%d', '%s' )
		);
		if ( false === $updated ) {
			return new \WP_Error( 'hvnly_taxonomy_claim_failed', __( 'Unable to claim this request.', 'havenlytics' ), array( 'status' => 500 ) );
		}
		return 1 === $updated;
	}

	/**
	 * @param array<string, mixed> $extra Additional allowlisted fields.
	 * @return bool|\WP_Error
	 */
	public function transition( int $id, string $from, string $to, int $actor_id, array $extra = array() ) {
		if ( ! in_array( $to, TaxonomyRequestConstants::statuses(), true ) ) {
			return new \WP_Error( 'hvnly_taxonomy_invalid_status', __( 'Invalid request status.', 'havenlytics' ), array( 'status' => 400 ) );
		}
		$data    = array(
			'status'      => $to,
			'reviewed_by' => $actor_id,
			'reviewed_at' => current_time( 'mysql', true ),
			'updated_at'  => current_time( 'mysql', true ),
			'dedupe_key'  => null,
		);
		$formats = array( '%s', '%d', '%s', '%s', '%s' );
		foreach ( array( 'created_term_id', 'merged_term_id' ) as $column ) {
			if ( isset( $extra[ $column ] ) ) {
				$data[ $column ] = absint( $extra[ $column ] );
				$formats[]       = '%d';
			}
		}
		if ( isset( $extra['rejection_reason'] ) ) {
			$data['rejection_reason'] = (string) $extra['rejection_reason'];
			$formats[]                = '%s';
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update( TaxonomyRequestSchema::requests_table(), $data, array(
			'id' => $id,
			'status' => $from,
		), $formats, array( '%d', '%s' ) );
		if ( false === $updated ) {
			return new \WP_Error( 'hvnly_taxonomy_transition_failed', __( 'Unable to update this request.', 'havenlytics' ), array( 'status' => 500 ) );
		}
		return 1 === $updated;
	}

	public function restore_pending( int $id ): void {
		global $wpdb;
		$row = $this->find( $id );
		if ( ! $row ) {
			return;
		}
		$dedupe = hash( 'sha256', $row['taxonomy'] . "\0" . $row['normalized_name'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			TaxonomyRequestSchema::requests_table(),
			array(
				'status' => TaxonomyRequestConstants::STATUS_PENDING,
				'dedupe_key' => $dedupe,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id' => $id,
				'status' => TaxonomyRequestConstants::STATUS_PROCESSING,
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	public function delete( int $id ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return 1 === $wpdb->delete( TaxonomyRequestSchema::requests_table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>
	 */
	private function parse_args( array $args ): array {
		$args              = wp_parse_args(
			$args,
			array(
				'user_id' => 0,
				'agent_id' => 0,
				'status' => '',
				'taxonomy' => '',
				'search' => '',
				'date_from' => '',
				'date_to' => '',
				'limit' => 20,
				'offset' => 0,
				'orderby' => 'created_at',
				'order' => 'DESC',
			)
		);
		$args['user_id']   = absint( $args['user_id'] );
		$args['agent_id']  = absint( $args['agent_id'] );
		$args['status']    = sanitize_key( (string) $args['status'] );
		$args['taxonomy']  = sanitize_key( (string) $args['taxonomy'] );
		$args['search']    = sanitize_text_field( (string) $args['search'] );
		$args['date_from'] = $this->date( (string) $args['date_from'], false );
		$args['date_to']   = $this->date( (string) $args['date_to'], true );
		$args['limit']     = min( 50, max( 1, absint( $args['limit'] ) ) );
		$args['offset']    = max( 0, absint( $args['offset'] ) );
		$args['orderby']   = sanitize_key( (string) $args['orderby'] );
		$args['order']     = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		return $args;
	}

	/**
	 * @return array{sql:string,values:array<int,mixed>}
	 */
	private function where( array $args ): array {
		$c = array();
		$v = array();
		foreach ( array(
			'user_id' => '%d',
			'agent_id' => '%d',
			'status' => '%s',
			'taxonomy' => '%s',
		) as $key => $placeholder ) {
			if ( ! empty( $args[ $key ] ) ) {
				$c[] = $key . ' = ' . $placeholder;
				$v[] = $args[ $key ];
			}
		}
		if ( '' !== $args['search'] ) {
			global $wpdb;
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$c[]  = '(requested_name LIKE %s OR description LIKE %s OR reason LIKE %s)';
			array_push( $v, $like, $like, $like );
		}
		if ( '' !== $args['date_from'] ) {
			$c[] = 'created_at >= %s';
			$v[] = $args['date_from'];
		}
		if ( '' !== $args['date_to'] ) {
			$c[] = 'created_at <= %s';
			$v[] = $args['date_to'];
		}
		return array(
			'sql' => $c ? 'WHERE ' . implode( ' AND ', $c ) : '',
			'values' => $v,
		);
	}

	private function date( string $value, bool $end ): string {
		$value = trim( $value );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value . ( $end ? ' 23:59:59' : ' 00:00:00' );
		}
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : '';
	}

	/**
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private function normalize( array $row ): array {
		foreach ( array( 'id', 'user_id', 'agent_id', 'created_term_id', 'merged_term_id', 'reviewed_by' ) as $key ) {
			$row[ $key ] = absint( $row[ $key ] ?? 0 );
		}
		return $row;
	}
}
