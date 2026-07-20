<?php
/**
 * Favorites (saved properties) table schema.
 *
 * @package HvnlyNab\Favorites
 * @since   3.4.0
 */

namespace HvnlyNab\Favorites;

defined( 'ABSPATH' ) || exit;

/**
 * Per-user saved properties.
 *
 * A dedicated table (rather than serialized user meta) so that pagination,
 * sorting and counting stay indexed operations — a user with thousands of
 * favorites costs the same per page as a user with three.
 *
 * @since 3.4.0
 */
final class FavoritesSchema {

	public const TABLE = 'hvnly_favorites';

	/**
	 * Fully-prefixed table name.
	 *
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
	 * Create the table when missing (idempotent).
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

		/*
		 * user_property is UNIQUE so "favorite" is idempotent at the storage
		 * layer — a double-click, a retried request, or a guest merge that
		 * overlaps existing rows can never create duplicates.
		 *
		 * user_created backs the default "recently saved" listing + pagination.
		 * property_id backs reverse lookups (favorite counts, cleanup on delete).
		 */
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			property_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY user_property (user_id, property_id),
			KEY user_created (user_id, created_at),
			KEY property_id (property_id)
		) {$charset_collate};";

		dbDelta( $sql );

		return self::table_exists();
	}
}
