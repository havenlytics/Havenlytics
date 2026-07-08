<?php
/**
 * Inquiry repository — custom table CRUD.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

use HvnlyNab\ContactAgent\Contracts\InquiryRepositoryInterface;
use HvnlyNab\ContactAgent\Database\InquirySchema;
use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
class InquiryRepository implements InquiryRepositoryInterface {

	/**
	 * @var string[]|null
	 */
	private static $cached_columns = null;

	/**
	 * @return string[]
	 */
	private function table_columns(): array {
		if ( is_array( self::$cached_columns ) ) {
			return self::$cached_columns;
		}

		global $wpdb;

		$table = InquirySchema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_col( "DESCRIBE {$table}" );

		self::$cached_columns = is_array( $columns ) ? array_values( array_filter( array_map( 'strval', $columns ) ) ) : array();

		return self::$cached_columns;
	}

	/**
	 * @param string $column Column name.
	 * @return bool
	 */
	private function has_column( string $column ): bool {
		return in_array( $column, $this->table_columns(), true );
	}

	/**
	 * @param int    $property_id Property ID.
	 * @return string
	 */
	private function resolve_property_title( int $property_id ): string {
		if ( $property_id <= 0 ) {
			return '';
		}

		$title = get_the_title( $property_id );

		return is_string( $title ) ? $title : '';
	}

	/**
	 * @param int    $agent_id Agent identifier.
	 * @param string $agent_type Agent type (cpt/user).
	 * @return string
	 */
	private function resolve_agent_name( int $agent_id, string $agent_type ): string {
		$agent_id   = absint( $agent_id );
		$agent_type = sanitize_key( $agent_type );

		if ( $agent_id <= 0 ) {
			return '';
		}

		if ( InquiryAgentResolver::TYPE_CPT === $agent_type ) {
			$title = get_the_title( $agent_id );
			return is_string( $title ) ? $title : '';
		}

		$user = get_user_by( 'id', $agent_id );
		if ( $user instanceof \WP_User ) {
			return (string) $user->display_name;
		}

		return '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function create( array $data ) {
		if ( ! InquirySchema::table_exists() ) {
			return new \WP_Error(
				'hvnly_contact_agent_table_missing',
				__( 'Inquiry storage is not available yet. Please contact the site administrator.', 'havenlytics' ),
				array( 'status' => 503 )
			);
		}

		global $wpdb;

		$property_id = isset( $data['property_id'] ) ? absint( $data['property_id'] ) : 0;
		$agent_id    = isset( $data['agent_id'] ) ? absint( $data['agent_id'] ) : 0;
		$agent_type  = isset( $data['agent_type'] ) ? sanitize_key( (string) $data['agent_type'] ) : InquiryAgentResolver::TYPE_USER;

		if ( $agent_id <= 0 && $property_id > 0 ) {
			$resolution = InquiryAgentResolver::resolve_for_submission( $property_id, 0 );
			if ( ! is_wp_error( $resolution ) ) {
				$agent_id = isset( $resolution['agent_id'] ) ? absint( $resolution['agent_id'] ) : 0;
				$agent_type = isset( $resolution['agent_type'] ) ? sanitize_key( (string) $resolution['agent_type'] ) : $agent_type;
			} elseif ( function_exists( 'hvnly_get_property_agent' ) ) {
				$agent = hvnly_get_property_agent( $property_id );
				if ( is_array( $agent ) && ! empty( $agent['id'] ) ) {
					$agent_id = absint( $agent['id'] );
				}
			}
		}

		$row = array(
			'property_id'  => $property_id,
			'agent_id'       => $agent_id,
			'sender_name'    => isset( $data['sender_name'] ) ? (string) $data['sender_name'] : '',
			'sender_email'   => isset( $data['sender_email'] ) ? (string) $data['sender_email'] : '',
			'sender_phone'   => isset( $data['sender_phone'] ) ? (string) $data['sender_phone'] : '',
			'message'        => isset( $data['message'] ) ? (string) $data['message'] : '',
			'source'         => isset( $data['source'] ) ? (string) $data['source'] : ContactAgentConstants::DEFAULT_SOURCE,
			'status'         => ContactAgentConstants::STATUS_NEW,
			'ip_address'     => isset( $data['ip_address'] ) ? (string) $data['ip_address'] : '',
			'user_agent'     => isset( $data['user_agent'] ) ? (string) $data['user_agent'] : '',
			'created_at'     => current_time( 'mysql', true ),
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		// Backward-compatible: only persist extra columns if they exist on this install.
		if ( $this->has_column( 'property_title' ) ) {
			$row['property_title'] = $this->resolve_property_title( $property_id );
			array_splice( $formats, 1, 0, '%s' );
		}

		if ( $this->has_column( 'agent_name' ) ) {
			$row['agent_name'] = $this->resolve_agent_name( $agent_id, $agent_type );
			$agent_name_pos    = $this->has_column( 'property_title' ) ? 3 : 2;
			array_splice( $formats, $agent_name_pos, 0, '%s' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert( InquirySchema::table_name(), $row, $formats );

		if ( false === $inserted ) {
			return new \WP_Error(
				'hvnly_contact_agent_insert_failed',
				__( 'Unable to save your inquiry. Please try again later.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		$inquiry_id = (int) $wpdb->insert_id;

		/**
		 * Fires after an inquiry is stored.
		 *
		 * @since 3.0.2
		 *
		 * @param int                  $inquiry_id New row ID.
		 * @param array<string, mixed> $row        Stored row data.
		 */
		do_action( 'hvnly_contact_agent_inquiry_created', $inquiry_id, $row );

		return $inquiry_id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function find( int $inquiry_id ): ?array {
		if ( $inquiry_id <= 0 || ! InquirySchema::table_exists() ) {
			return null;
		}

		global $wpdb;

		$table = function_exists( 'hvnly_get_validated_custom_table' )
			? hvnly_get_validated_custom_table( InquirySchema::table_name() )
			: InquirySchema::table_name();

		if ( '' === $table ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Validated hvnly_ custom table.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d LIMIT 1",
				$inquiry_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->normalize_row( $row );
	}

	/**
	 * {@inheritdoc}
	 */
	public function list( array $args = array() ): array {
		if ( ! InquirySchema::table_exists() ) {
			return array();
		}

		global $wpdb;

		$table = function_exists( 'hvnly_get_validated_custom_table' )
			? hvnly_get_validated_custom_table( InquirySchema::table_name() )
			: InquirySchema::table_name();

		if ( '' === $table ) {
			return array();
		}

		$args     = $this->parse_list_args( $args );
		$where    = $this->build_where_clause( $args );
		$order_by = $this->sanitize_orderby( $args['orderby'] );
		$order    = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$values   = array_merge( $where['values'], array( $args['limit'], $args['offset'] ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Validated hvnly_ custom table; WHERE uses prepared fragments.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` {$where['sql']} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
				$values
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'normalize_row' ), $rows );
	}

	/**
	 * {@inheritdoc}
	 */
	public function count( array $args = array() ): int {
		if ( ! InquirySchema::table_exists() ) {
			return 0;
		}

		global $wpdb;

		$table = function_exists( 'hvnly_get_validated_custom_table' )
			? hvnly_get_validated_custom_table( InquirySchema::table_name() )
			: InquirySchema::table_name();

		if ( '' === $table ) {
			return 0;
		}

		$args  = $this->parse_list_args( $args );
		$where = $this->build_where_clause( $args );

		if ( empty( $where['values'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Validated hvnly_ custom table.
			$total = $wpdb->get_var(
				"SELECT COUNT(*) FROM `{$table}` {$where['sql']}"
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Validated hvnly_ custom table; WHERE uses prepared fragments.
			$total = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` {$where['sql']}",
					$where['values']
				)
			);
		}

		return max( 0, (int) $total );
	}

	/**
	 * {@inheritdoc}
	 */
	public function update_status( int $inquiry_id, string $status ) {
		if ( $inquiry_id <= 0 ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_inquiry',
				__( 'Invalid inquiry ID.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( ! $this->is_valid_status( $status ) ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_status',
				__( 'Invalid inquiry status.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( ! InquirySchema::table_exists() ) {
			return new \WP_Error(
				'hvnly_contact_agent_table_missing',
				__( 'Inquiry storage is not available.', 'havenlytics' ),
				array( 'status' => 503 )
			);
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			InquirySchema::table_name(),
			array( 'status' => $status ),
			array( 'id' => $inquiry_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new \WP_Error(
				'hvnly_contact_agent_update_failed',
				__( 'Unable to update inquiry status.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		/**
		 * Fires after an inquiry status changes.
		 *
		 * @since 3.0.2
		 *
		 * @param int    $inquiry_id Inquiry ID.
		 * @param string $status     New status.
		 */
		do_action( 'hvnly_contact_agent_inquiry_status_updated', $inquiry_id, $status );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( int $inquiry_id ) {
		if ( $inquiry_id <= 0 ) {
			return new \WP_Error(
				'hvnly_contact_agent_invalid_inquiry',
				__( 'Invalid inquiry ID.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		if ( ! InquirySchema::table_exists() ) {
			return new \WP_Error(
				'hvnly_contact_agent_table_missing',
				__( 'Inquiry storage is not available.', 'havenlytics' ),
				array( 'status' => 503 )
			);
		}

		$reply_repository = new InquiryReplyRepository();
		$reply_repository->delete_by_inquiry( $inquiry_id );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			InquirySchema::table_name(),
			array( 'id' => $inquiry_id ),
			array( '%d' )
		);

		if ( false === $deleted || 0 === $deleted ) {
			return new \WP_Error(
				'hvnly_contact_agent_delete_failed',
				__( 'Unable to delete inquiry.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		/**
		 * Fires after an inquiry is deleted.
		 *
		 * @since 3.0.2
		 *
		 * @param int $inquiry_id Deleted inquiry ID.
		 */
		do_action( 'hvnly_contact_agent_inquiry_deleted', $inquiry_id );

		return true;
	}

	/**
	 * @param array<string, mixed> $args Raw list args.
	 * @return array<string, mixed>
	 */
	private function parse_list_args( array $args ): array {
		$defaults = array(
			'property_id' => 0,
			'agent_id'    => 0,
			'status'      => '',
			'limit'       => 20,
			'offset'      => 0,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
		);

		$parsed = wp_parse_args( $args, $defaults );

		$parsed['property_id'] = absint( $parsed['property_id'] );
		$parsed['agent_id']    = absint( $parsed['agent_id'] );
		$parsed['status']      = sanitize_key( (string) $parsed['status'] );
		$parsed['limit']       = min( 100, max( 1, absint( $parsed['limit'] ) ) );
		$parsed['offset']      = max( 0, absint( $parsed['offset'] ) );

		return $parsed;
	}

	/**
	 * @param array<string, mixed> $args Parsed list args.
	 * @return array{sql: string, values: array<int, mixed>}
	 */
	private function build_where_clause( array $args ): array {
		$conditions = array();
		$values     = array();

		if ( ! empty( $args['property_id'] ) ) {
			$conditions[] = 'property_id = %d';
			$values[]     = (int) $args['property_id'];
		}

		if ( ! empty( $args['agent_id'] ) ) {
			$conditions[] = 'agent_id = %d';
			$values[]     = (int) $args['agent_id'];
		}

		if ( ! empty( $args['status'] ) && $this->is_valid_status( (string) $args['status'] ) ) {
			$conditions[] = 'status = %s';
			$values[]     = (string) $args['status'];
		}

		if ( empty( $conditions ) ) {
			return array(
				'sql'    => '',
				'values' => array(),
			);
		}

		return array(
			'sql'    => 'WHERE ' . implode( ' AND ', $conditions ),
			'values' => $values,
		);
	}

	/**
	 * @param string $orderby Requested orderby column.
	 * @return string
	 */
	private function sanitize_orderby( string $orderby ): string {
		$allowed = array(
			'id'          => 'id',
			'created_at'  => 'created_at',
			'property_id' => 'property_id',
			'agent_id'    => 'agent_id',
			'status'      => 'status',
		);

		return $allowed[ $orderby ] ?? 'created_at';
	}

	/**
	 * @param string $status Status slug.
	 * @return bool
	 */
	private function is_valid_status( string $status ): bool {
		return in_array( $status, ContactAgentConstants::INQUIRY_STATUSES, true );
	}

	/**
	 * @param array<string, mixed> $row Raw DB row.
	 * @return array<string, mixed>
	 */
	private function normalize_row( array $row ): array {
		return array(
			'id'           => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'property_id'  => isset( $row['property_id'] ) ? (int) $row['property_id'] : 0,
			'agent_id'     => isset( $row['agent_id'] ) ? (int) $row['agent_id'] : 0,
			'agent_type'   => self::infer_agent_type_from_row( $row ),
			'sender_name'  => isset( $row['sender_name'] ) ? (string) $row['sender_name'] : '',
			'sender_email' => isset( $row['sender_email'] ) ? (string) $row['sender_email'] : '',
			'sender_phone' => isset( $row['sender_phone'] ) ? (string) $row['sender_phone'] : '',
			'message'      => isset( $row['message'] ) ? (string) $row['message'] : '',
			'source'       => isset( $row['source'] ) ? (string) $row['source'] : '',
			'status'       => isset( $row['status'] ) ? (string) $row['status'] : '',
			'ip_address'   => isset( $row['ip_address'] ) ? (string) $row['ip_address'] : '',
			'user_agent'   => isset( $row['user_agent'] ) ? (string) $row['user_agent'] : '',
			'created_at'   => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
		);
	}

	/**
	 * Infer stored agent reference type for backward-compatible reads.
	 *
	 * @param array<string, mixed> $row Raw DB row.
	 * @return string
	 */
	private static function infer_agent_type_from_row( array $row ): string {
		if ( isset( $row['agent_type'] ) && $row['agent_type'] ) {
			return sanitize_key( (string) $row['agent_type'] );
		}

		$agent_id = isset( $row['agent_id'] ) ? absint( $row['agent_id'] ) : 0;
		if ( $agent_id <= 0 ) {
			return InquiryAgentResolver::TYPE_USER;
		}

		if ( AgentConstants::POST_TYPE === get_post_type( $agent_id ) ) {
			return InquiryAgentResolver::TYPE_CPT;
		}

		return InquiryAgentResolver::TYPE_USER;
	}
}
