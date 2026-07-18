<?php
/**
 * Taxonomy request storage schema.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests;

defined( 'ABSPATH' ) || exit;

final class TaxonomyRequestSchema {

	public static function requests_table(): string {
		global $wpdb;
		return $wpdb->prefix . TaxonomyRequestConstants::REQUESTS_TABLE;
	}

	public static function logs_table(): string {
		global $wpdb;
		return $wpdb->prefix . TaxonomyRequestConstants::LOGS_TABLE;
	}

	/**
	 * @param string $table Table name.
	 * @return bool
	 */
	private static function exists( string $table ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	public static function requests_exist(): bool {
		return self::exists( self::requests_table() );
	}

	public static function logs_exist(): bool {
		return self::exists( self::logs_table() );
	}

	public static function tables_exist(): bool {
		return self::requests_exist() && self::logs_exist();
	}

	/**
	 * Create or reconcile both tables independently.
	 *
	 * @return bool
	 */
	public static function create_tables(): bool {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$requests = self::requests_table();
		$logs     = self::logs_table();
		$collate  = $wpdb->get_charset_collate();

		$request_sql = "CREATE TABLE {$requests} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			agent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			taxonomy varchar(64) NOT NULL,
			requested_name varchar(191) NOT NULL,
			normalized_name varchar(191) NOT NULL,
			normalized_slug varchar(191) NOT NULL,
			description text NULL,
			reason text NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			dedupe_key char(64) NULL,
			created_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
			merged_term_id bigint(20) unsigned NOT NULL DEFAULT 0,
			rejection_reason text NULL,
			reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			reviewed_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			archived_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY active_dedupe (dedupe_key),
			KEY requester_created (user_id, created_at, id),
			KEY status_taxonomy_created (status, taxonomy, created_at, id),
			KEY taxonomy_slug (taxonomy, normalized_slug),
			KEY created_term_id (created_term_id),
			KEY merged_term_id (merged_term_id)
		) ENGINE=InnoDB {$collate};";

		$log_sql = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			request_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
			taxonomy varchar(64) NOT NULL,
			requested_name varchar(191) NOT NULL,
			previous_status varchar(20) NULL,
			new_status varchar(20) NULL,
			action varchar(50) NOT NULL,
			reason text NULL,
			context longtext NULL,
			ip_address varchar(45) NULL,
			user_agent varchar(500) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY request_created (request_id, created_at, id),
			KEY action_created (action, created_at, id),
			KEY actor_created (actor_id, created_at, id)
		) ENGINE=InnoDB {$collate};";

		dbDelta( $request_sql );
		dbDelta( $log_sql );

		return self::tables_exist();
	}
}
