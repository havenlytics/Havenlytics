<?php
/**
 * Contact Agent inquiries table schema helpers.
 *
 * @package HvnlyNab\ContactAgent\Database
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent\Database;

use HvnlyNab\ContactAgent\ContactAgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
final class InquirySchema {

	/**
	 * Fully qualified table name including WordPress prefix.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . ContactAgentConstants::INQUIRIES_TABLE;
	}

	/**
	 * Whether the inquiries table exists.
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;

		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Create the inquiries table when missing.
	 *
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
			property_id bigint(20) unsigned NOT NULL,
			agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sender_name varchar(100) NOT NULL,
			sender_email varchar(100) NOT NULL,
			sender_phone varchar(30) NOT NULL DEFAULT '',
			message longtext NOT NULL,
			source varchar(50) NOT NULL DEFAULT 'contact_agent_modal',
			status varchar(20) NOT NULL DEFAULT 'new',
			ip_address varchar(45) DEFAULT NULL,
			user_agent text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_property_id (property_id),
			KEY idx_agent_id (agent_id),
			KEY idx_status (status),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		if ( self::table_exists() ) {
			/**
			 * Fires after the Contact Agent inquiries table is created.
			 *
			 * @since 3.0.2
			 */
			do_action( 'hvnly_contact_agent_inquiries_table_created' );

			return true;
		}

		return false;
	}
}
