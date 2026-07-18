<?php
/**
 * Notification repository — hvnly_portal_notifications CRUD.
 *
 * @package HvnlyNab\Workspace\Notifications
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Notifications;

use HvnlyNab\Workspace\WorkspaceDateTime;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
final class NotificationRepository {

	/**
	 * @param array<string, mixed> $data Notification row.
	 * @return int|\WP_Error
	 */
	public function create( array $data ) {
		if ( ! NotificationSchema::table_exists() ) {
			NotificationSchema::create_table();
		}
		if ( ! NotificationSchema::table_exists() ) {
			return new \WP_Error(
				'hvnly_notifications_table_missing',
				__( 'Notification storage is unavailable.', 'havenlytics' ),
				array( 'status' => 503 )
			);
		}

		$user_id = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0;
		if ( $user_id <= 0 ) {
			return new \WP_Error(
				'hvnly_notifications_invalid_user',
				__( 'Invalid user.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}

		$type = isset( $data['type'] ) ? sanitize_key( (string) $data['type'] ) : 'generic';
		$cat  = isset( $data['category'] ) ? sanitize_key( (string) $data['category'] ) : 'account';
		if ( ! in_array( $cat, array( 'property', 'inquiry', 'account' ), true ) ) {
			$cat = 'account';
		}

		$payload = isset( $data['payload'] ) ? $data['payload'] : array();
		if ( is_array( $payload ) ) {
			$payload = wp_json_encode( $payload );
		}

		$row = array(
			'user_id'    => $user_id,
			'agent_id'   => isset( $data['agent_id'] ) ? absint( $data['agent_id'] ) : 0,
			'type'       => $type,
			'category'   => $cat,
			'title'      => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'body'       => sanitize_textarea_field( (string) ( $data['body'] ?? '' ) ),
			'icon'       => sanitize_key( (string) ( $data['icon'] ?? 'bell' ) ),
			'action_url' => esc_url_raw( (string) ( $data['action_url'] ?? '' ) ),
			'payload'    => is_string( $payload ) ? $payload : '',
			'created_at' => current_time( 'mysql', true ),
		);

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			NotificationSchema::table_name(),
			$row,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new \WP_Error(
				'hvnly_notifications_insert_failed',
				__( 'Unable to create notification.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		$id = (int) $wpdb->insert_id;

		/**
		 * Fires after an in-app notification is stored.
		 *
		 * @since 3.2.0
		 *
		 * @param int                  $id   Notification ID.
		 * @param array<string, mixed> $row  Stored row.
		 */
		do_action( 'hvnly_portal_notification_created', $id, $row );

		return $id;
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args    Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_for_user( int $user_id, array $args = array() ): array {
		if ( $user_id <= 0 || ! NotificationSchema::table_exists() ) {
			return array();
		}

		$parsed = $this->parse_args( $args );
		$where  = $this->build_where( $user_id, $parsed );

		global $wpdb;
		$table  = NotificationSchema::table_name();
		$values = array_merge( $where['values'], array( $parsed['limit'], $parsed['offset'] ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$prepared = $wpdb->prepare(
			"SELECT * FROM `{$table}` {$where['sql']} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
			...$values
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $prepared, ARRAY_A );
		// phpcs:enable

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'normalize' ), $rows );
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args    Query args.
	 * @return int
	 */
	public function count_for_user( int $user_id, array $args = array() ): int {
		if ( $user_id <= 0 || ! NotificationSchema::table_exists() ) {
			return 0;
		}

		$parsed = $this->parse_args( $args );
		$where  = $this->build_where( $user_id, $parsed );

		global $wpdb;
		$table = NotificationSchema::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` {$where['sql']}",
				...$where['values']
			)
		);
		// phpcs:enable

		return max( 0, (int) $total );
	}

	/**
	 * @param int $id      Notification ID.
	 * @param int $user_id Owner user ID.
	 * @return array<string, mixed>|null
	 */
	public function find_for_user( int $id, int $user_id ): ?array {
		if ( $id <= 0 || $user_id <= 0 || ! NotificationSchema::table_exists() ) {
			return null;
		}

		global $wpdb;
		$table = NotificationSchema::table_name();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE id = %d AND user_id = %d LIMIT 1",
				$id,
				$user_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return is_array( $row ) ? $this->normalize( $row ) : null;
	}

	/**
	 * @param int $id      Notification ID.
	 * @param int $user_id Owner.
	 * @return true|\WP_Error
	 */
	public function mark_read( int $id, int $user_id ) {
		$row = $this->find_for_user( $id, $user_id );
		if ( ! $row ) {
			return new \WP_Error(
				'hvnly_notification_not_found',
				__( 'Notification not found.', 'havenlytics' ),
				array( 'status' => 404 )
			);
		}

		if ( ! empty( $row['readAt'] ) ) {
			return true;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->update(
			NotificationSchema::table_name(),
			array( 'read_at' => current_time( 'mysql', true ) ),
			array(
				'id'      => $id,
				'user_id' => $user_id,
			),
			array( '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $ok ) {
			return new \WP_Error(
				'hvnly_notification_update_failed',
				__( 'Unable to update notification.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * @param int $user_id User ID.
	 * @return int Rows updated.
	 */
	public function mark_all_read( int $user_id ): int {
		if ( $user_id <= 0 || ! NotificationSchema::table_exists() ) {
			return 0;
		}

		global $wpdb;
		$table = NotificationSchema::table_name();
		$now   = current_time( 'mysql', true );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE `{$table}` SET read_at = %s WHERE user_id = %d AND read_at IS NULL",
				$now,
				$user_id
			)
		);
		// phpcs:enable

		return (int) $wpdb->rows_affected;
	}

	/**
	 * Count notifications for a user and/or agent.
	 *
	 * @param int $user_id  User ID (0 = ignore).
	 * @param int $agent_id Agent CPT ID (0 = ignore).
	 * @return int
	 */
	public function count_for_identity( int $user_id = 0, int $agent_id = 0 ): int {
		if ( ! NotificationSchema::table_exists() ) {
			return 0;
		}
		$user_id  = absint( $user_id );
		$agent_id = absint( $agent_id );
		if ( $user_id <= 0 && $agent_id <= 0 ) {
			return 0;
		}

		global $wpdb;
		$table = NotificationSchema::table_name();
		if ( $user_id > 0 && $agent_id > 0 ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d OR agent_id = %d",
					$user_id,
					$agent_id
				)
			);
			// phpcs:enable
			return $count;
		}
		if ( $user_id > 0 ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d",
					$user_id
				)
			);
			// phpcs:enable
			return $count;
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE agent_id = %d",
				$agent_id
			)
		);
		// phpcs:enable
		return $count;
	}

	/**
	 * Reassign notifications from one user/agent identity to another.
	 *
	 * @param int $from_user  Source user.
	 * @param int $from_agent Source agent CPT.
	 * @param int $to_user    Target user.
	 * @param int $to_agent   Target agent CPT.
	 * @return int|\WP_Error
	 */
	public function reassign_identity( int $from_user, int $from_agent, int $to_user, int $to_agent ) {
		if ( ! NotificationSchema::table_exists() ) {
			return 0;
		}
		$from_user  = absint( $from_user );
		$from_agent = absint( $from_agent );
		$to_user    = absint( $to_user );
		$to_agent   = absint( $to_agent );
		if ( $to_user <= 0 ) {
			return new \WP_Error(
				'hvnly_notifications_reassign_invalid',
				__( 'Notification transfer requires a target user.', 'havenlytics' ),
				array( 'status' => 400 )
			);
		}
		if ( $from_user <= 0 && $from_agent <= 0 ) {
			return 0;
		}

		global $wpdb;
		$table = NotificationSchema::table_name();
		if ( $from_user > 0 && $from_agent > 0 ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$n = $wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET user_id = %d, agent_id = %d WHERE user_id = %d OR agent_id = %d",
					$to_user,
					$to_agent,
					$from_user,
					$from_agent
				)
			);
			// phpcs:enable
		} elseif ( $from_user > 0 ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$n = $wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET user_id = %d, agent_id = %d WHERE user_id = %d",
					$to_user,
					$to_agent,
					$from_user
				)
			);
			// phpcs:enable
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$n = $wpdb->query(
				$wpdb->prepare(
					"UPDATE `{$table}` SET user_id = %d, agent_id = %d WHERE agent_id = %d",
					$to_user,
					$to_agent,
					$from_agent
				)
			);
			// phpcs:enable
		}

		if ( false === $n ) {
			return new \WP_Error(
				'hvnly_notifications_reassign_failed',
				__( 'Could not transfer notifications.', 'havenlytics' ),
				array( 'status' => 500 )
			);
		}

		return (int) $n;
	}

	/**
	 * Delete all notifications for a user/agent identity (cleanup).
	 *
	 * @param int $user_id  User ID.
	 * @param int $agent_id Agent CPT ID.
	 * @return int
	 */
	public function delete_for_identity( int $user_id = 0, int $agent_id = 0 ): int {
		if ( ! NotificationSchema::table_exists() ) {
			return 0;
		}
		$user_id  = absint( $user_id );
		$agent_id = absint( $agent_id );
		if ( $user_id <= 0 && $agent_id <= 0 ) {
			return 0;
		}

		global $wpdb;
		$table = NotificationSchema::table_name();
		if ( $user_id > 0 && $agent_id > 0 ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$n = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table}` WHERE user_id = %d OR agent_id = %d",
					$user_id,
					$agent_id
				)
			);
			// phpcs:enable
		} elseif ( $user_id > 0 ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$n = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table}` WHERE user_id = %d",
					$user_id
				)
			);
			// phpcs:enable
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$n = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM `{$table}` WHERE agent_id = %d",
					$agent_id
				)
			);
			// phpcs:enable
		}

		return false === $n ? 0 : (int) $n;
	}

	/**
	 * @param array<string, mixed> $args Raw args.
	 * @return array<string, mixed>
	 */
	private function parse_args( array $args ): array {
		$parsed = wp_parse_args(
			$args,
			array(
				'status'   => '',
				'category' => '',
				'limit'    => 20,
				'offset'   => 0,
			)
		);
		$parsed['status']   = sanitize_key( (string) $parsed['status'] );
		$parsed['category'] = sanitize_key( (string) $parsed['category'] );
		$parsed['limit']    = min( 50, max( 1, absint( $parsed['limit'] ) ) );
		$parsed['offset']   = max( 0, absint( $parsed['offset'] ) );
		return $parsed;
	}

	/**
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args    Parsed.
	 * @return array{sql:string,values:array<int,mixed>}
	 */
	private function build_where( int $user_id, array $args ): array {
		$conditions = array( 'user_id = %d' );
		$values     = array( $user_id );

		if ( 'unread' === $args['status'] ) {
			$conditions[] = 'read_at IS NULL';
		} elseif ( 'read' === $args['status'] ) {
			$conditions[] = 'read_at IS NOT NULL';
		}

		if ( in_array( $args['category'], array( 'property', 'inquiry', 'account' ), true ) ) {
			$conditions[] = 'category = %s';
			$values[]     = $args['category'];
		}

		return array(
			'sql'    => 'WHERE ' . implode( ' AND ', $conditions ),
			'values' => $values,
		);
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private function normalize( array $row ): array {
		$payload = array();
		if ( ! empty( $row['payload'] ) ) {
			$decoded = json_decode( (string) $row['payload'], true );
			if ( is_array( $decoded ) ) {
				$payload = $decoded;
			}
		}

		$created = (string) ( $row['created_at'] ?? '' );
		$ts      = WorkspaceDateTime::parse_gmt_mysql( $created );

		return array(
			'id'        => (int) ( $row['id'] ?? 0 ),
			'userId'    => (int) ( $row['user_id'] ?? 0 ),
			'agentId'   => (int) ( $row['agent_id'] ?? 0 ),
			'type'      => (string) ( $row['type'] ?? '' ),
			'category'  => (string) ( $row['category'] ?? 'account' ),
			'title'     => (string) ( $row['title'] ?? '' ),
			'message'   => (string) ( $row['body'] ?? '' ),
			'icon'      => (string) ( $row['icon'] ?? 'bell' ),
			'actionUrl' => (string) ( $row['action_url'] ?? '' ),
			'payload'   => $payload,
			'readAt'    => ! empty( $row['read_at'] ) ? (string) $row['read_at'] : null,
			'isRead'    => ! empty( $row['read_at'] ),
			'createdAt' => $created,
			'time'      => $ts > 0 ? WorkspaceDateTime::relative_ago( $ts ) : '',
		);
	}
}
