<?php
/**
 * Portal notifications table schema.
 *
 * @package HvnlyNab\Workspace\Notifications
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight in-app notification inbox (roadmap: hvnly_portal_notifications).
 *
 * @since 3.2.0
 */
final class NotificationSchema {

	public const TABLE = 'hvnly_portal_notifications';

	/**
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * @return bool
	 */
	public static function create_table(): bool {
		if ( self::table_exists() ) {
			return true;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(50) NOT NULL,
			category varchar(30) NOT NULL DEFAULT 'account',
			title varchar(255) NOT NULL,
			body text,
			icon varchar(50) NOT NULL DEFAULT 'bell',
			action_url varchar(255) NOT NULL DEFAULT '',
			payload longtext,
			read_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_read (user_id, read_at),
			KEY user_category (user_id, category),
			KEY type (type),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		return self::table_exists();
	}
}
