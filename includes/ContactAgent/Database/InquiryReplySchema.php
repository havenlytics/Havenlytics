<?php
/**
 * Contact Agent inquiry replies table schema helpers.
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
final class InquiryReplySchema {

	/**
	 * Fully qualified table name including WordPress prefix.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . ContactAgentConstants::INQUIRY_REPLIES_TABLE;
	}

	/**
	 * Whether the replies table exists.
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
	 * Create the inquiry replies table when missing.
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
			inquiry_id bigint(20) unsigned NOT NULL,
			author_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			direction varchar(20) NOT NULL DEFAULT 'outbound',
			message longtext NOT NULL,
			email_sent tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_inquiry_id (inquiry_id),
			KEY idx_author_user_id (author_user_id),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		if ( self::table_exists() ) {
			/**
			 * Fires after the Contact Agent inquiry replies table is created.
			 *
			 * @since 3.0.2
			 */
			do_action( 'hvnly_contact_agent_inquiry_replies_table_created' );

			return true;
		}

		return false;
	}
}
