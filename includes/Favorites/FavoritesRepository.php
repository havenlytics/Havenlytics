<?php
/**
 * Favorites persistence layer.
 *
 * Every SQL statement for saved properties lives here. Callers deal in
 * user ids and property ids only.
 *
 * @package HvnlyNab\Favorites
 * @since   3.4.0
 */

namespace HvnlyNab\Favorites;

defined( 'ABSPATH' ) || exit;

/*
 * Table names are interpolated throughout this file. They are built from
 * $wpdb->prefix plus a class constant and never contain user input; SQL
 * identifiers cannot be bound as prepare() placeholders, so interpolation is
 * the only option. Every value that does originate from a caller is bound
 * through $wpdb->prepare().
 *
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 */

/**
 * Data access for {@see FavoritesSchema}.
 *
 * @since 3.4.0
 */
final class FavoritesRepository {

	/**
	 * Object-cache group for per-user favorite id sets.
	 *
	 * @var string
	 */
	public const CACHE_GROUP = 'hvnly_favorites';

	/**
	 * Hard ceiling on how many ids a single hydration call returns.
	 *
	 * The archive only needs ids to paint heart states for the cards actually
	 * on screen; shipping an unbounded set to the browser would be the one
	 * place "thousands of favorites" could still hurt.
	 *
	 * @var int
	 */
	public const MAX_HYDRATION_IDS = 2000;

	/**
	 * Property meta key holding the price (used by price sorting).
	 *
	 * @var string
	 */
	private const PRICE_META_KEY = '_hvnly_property_price';

	/**
	 * Add a favorite. Idempotent — re-favoriting is a no-op, not an error.
	 *
	 * @param int $user_id     User id.
	 * @param int $property_id Property id.
	 * @return bool True when the row exists after the call.
	 */
	public function add( int $user_id, int $property_id ): bool {
		global $wpdb;

		if ( $user_id <= 0 || $property_id <= 0 ) {
			return false;
		}

		$table = FavoritesSchema::table_name();

		// INSERT IGNORE leans on the UNIQUE(user_id, property_id) index so a
		// duplicate is absorbed by the database rather than a read-then-write
		// race in PHP.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (user_id, property_id, created_at) VALUES (%d, %d, %s)",
				$user_id,
				$property_id,
				current_time( 'mysql', true )
			)
		);

		$this->flush_user_cache( $user_id );

		return true;
	}

	/**
	 * Bulk-add favorites in a single statement (guest merge path).
	 *
	 * @param int   $user_id      User id.
	 * @param int[] $property_ids Property ids (already validated).
	 * @return int Number of rows actually inserted.
	 */
	public function add_many( int $user_id, array $property_ids ): int {
		global $wpdb;

		if ( $user_id <= 0 || empty( $property_ids ) ) {
			return 0;
		}

		$table = FavoritesSchema::table_name();
		$now   = current_time( 'mysql', true );

		$values       = array();
		$placeholders = array();
		foreach ( $property_ids as $property_id ) {
			$property_id = (int) $property_id;
			if ( $property_id <= 0 ) {
				continue;
			}
			$placeholders[] = '(%d, %d, %s)';
			$values[]       = $user_id;
			$values[]       = $property_id;
			$values[]       = $now;
		}

		if ( empty( $placeholders ) ) {
			return 0;
		}

		$sql = "INSERT IGNORE INTO {$table} (user_id, property_id, created_at) VALUES "
			. implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$inserted = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		$this->flush_user_cache( $user_id );

		return is_numeric( $inserted ) ? (int) $inserted : 0;
	}

	/**
	 * Remove a favorite.
	 *
	 * @param int $user_id     User id.
	 * @param int $property_id Property id.
	 * @return bool True when a row was deleted.
	 */
	public function remove( int $user_id, int $property_id ): bool {
		global $wpdb;

		if ( $user_id <= 0 || $property_id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			FavoritesSchema::table_name(),
			array(
				'user_id'     => $user_id,
				'property_id' => $property_id,
			),
			array( '%d', '%d' )
		);

		$this->flush_user_cache( $user_id );

		return ! empty( $deleted );
	}

	/**
	 * Total favorites for a user.
	 *
	 * @param int $user_id User id.
	 * @return int
	 */
	public function count( int $user_id ): int {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return 0;
		}

		$table = FavoritesSchema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id )
		);
	}

	/**
	 * All favorited property ids for a user, newest first.
	 *
	 * Cached per user in the object cache so a full archive page costs one
	 * query no matter how many cards it renders.
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	public function get_ids( int $user_id ): array {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return array();
		}

		$cache_key = 'ids_' . $user_id;
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = FavoritesSchema::table_name();
		$limit = (int) self::MAX_HYDRATION_IDS;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT property_id FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
				$user_id,
				$limit
			)
		);

		$ids = array_map( 'intval', (array) $rows );

		wp_cache_set( $cache_key, $ids, self::CACHE_GROUP, HOUR_IN_SECONDS );

		return $ids;
	}

	/**
	 * Whether a property is favorited by a user.
	 *
	 * Reads the cached id set rather than issuing a query per card — this is
	 * the call the archive template makes once per property.
	 *
	 * @param int $user_id     User id.
	 * @param int $property_id Property id.
	 * @return bool
	 */
	public function exists( int $user_id, int $property_id ): bool {
		if ( $user_id <= 0 || $property_id <= 0 ) {
			return false;
		}

		return in_array( $property_id, $this->get_ids( $user_id ), true );
	}

	/**
	 * One page of favorited property ids, sorted.
	 *
	 * Returns ids only — hydration into post objects is a separate, single
	 * WP_Query in {@see FavoritesService} so meta/term caches prime in bulk.
	 *
	 * @param int                  $user_id User id.
	 * @param array<string, mixed> $args    page, per_page, orderby, order.
	 * @return array{ids: int[], total: int, saved_at: array<int, string>}
	 */
	public function get_page( int $user_id, array $args ): array {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return array(
				'ids'      => array(),
				'total'    => 0,
				'saved_at' => array(),
			);
		}

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = (int) ( $args['per_page'] ?? 12 );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$orderby = (string) ( $args['orderby'] ?? 'date_added' );
		$order   = strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';

		$table = FavoritesSchema::table_name();
		$posts = $wpdb->posts;

		// Only published properties are listed. A favorite whose property was
		// trashed or unpublished stays in the table (so it reappears if the
		// listing returns) but is never shown or counted.
		/*
		 * Placeholders and values are kept apart until the single prepare()
		 * at the bottom. Pre-preparing a fragment and then feeding the result
		 * through prepare() a second time works only while no substituted
		 * value contains a '%' — the moment one does, the second pass
		 * misreads it as a placeholder. Bind once instead.
		 */
		$join   = " INNER JOIN {$posts} p ON p.ID = f.property_id ";
		$where  = ' WHERE f.user_id = %d AND p.post_status = \'publish\' AND p.post_type = %s ';
		$params = array( $user_id, FavoritesService::POST_TYPE );

		$meta_join   = '';
		$meta_params = array();

		switch ( $orderby ) {
			case 'title':
				$order_sql = "p.post_title {$order}";
				break;

			case 'price':
				// Price meta may legitimately hold a non-numeric "price on
				// call" payload; those CAST to 0 and group at one end rather
				// than breaking the sort.
				$meta_join     = " LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s ";
				$meta_params[] = self::PRICE_META_KEY;
				$order_sql     = "CAST(pm.meta_value AS DECIMAL(20,2)) {$order}, p.ID DESC";
				break;

			case 'date_published':
				$order_sql = "p.post_date {$order}";
				break;

			case 'date_added':
			default:
				$order_sql = "f.created_at {$order}, f.id {$order}";
				break;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} f {$join} {$where}", $params )
		);

		if ( 0 === $total ) {
			return array(
				'ids'      => array(),
				'total'    => 0,
				'saved_at' => array(),
			);
		}

		// created_at rides along so "Saved on …" costs zero extra queries.
		$sql = "SELECT f.property_id, f.created_at FROM {$table} f {$join}{$meta_join}{$where} ORDER BY {$order_sql} LIMIT %d OFFSET %d";

		// Order matters: the meta JOIN placeholder appears before the WHERE
		// placeholders in the statement, so its value binds first.
		$page_params = array_merge( $meta_params, $params, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $page_params ) );

		$ids      = array();
		$saved_at = array();
		foreach ( (array) $rows as $row ) {
			$property_id = (int) $row->property_id;
			$ids[]       = $property_id;

			$saved_at[ $property_id ] = (string) $row->created_at;
		}

		return array(
			'ids'      => $ids,
			'total'    => $total,
			'saved_at' => $saved_at,
		);
	}

	/**
	 * Delete every favorite row pointing at a property (property deleted).
	 *
	 * @param int $property_id Property id.
	 * @return int Rows removed.
	 */
	public function delete_for_property( int $property_id ): int {
		global $wpdb;

		if ( $property_id <= 0 ) {
			return 0;
		}

		$table = FavoritesSchema::table_name();

		// Collect affected users first so their cached id sets can be dropped;
		// after the DELETE the association is gone.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$user_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT user_id FROM {$table} WHERE property_id = %d", $property_id )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( $table, array( 'property_id' => $property_id ), array( '%d' ) );

		foreach ( (array) $user_ids as $user_id ) {
			$this->flush_user_cache( (int) $user_id );
		}

		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}

	/**
	 * Delete every favorite belonging to a user (user deleted).
	 *
	 * @param int $user_id User id.
	 * @return int Rows removed.
	 */
	public function delete_for_user( int $user_id ): int {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( FavoritesSchema::table_name(), array( 'user_id' => $user_id ), array( '%d' ) );

		$this->flush_user_cache( $user_id );

		return is_numeric( $deleted ) ? (int) $deleted : 0;
	}

	/**
	 * Drop the cached id set for a user.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function flush_user_cache( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		wp_cache_delete( 'ids_' . $user_id, self::CACHE_GROUP );
	}
}
