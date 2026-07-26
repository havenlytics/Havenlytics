<?php
/**
 * Finds existing hvnly_property posts for duplicate-safe CSV import.
 *
 * @package HvnlyNab\CsvTransfer\Import
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Import;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * DuplicateMatcher — mls/reference/title lookups + policy normalization.
 *
 * @since 3.7.0
 */
final class DuplicateMatcher {

	public const META_MLS       = '_hvnly_csv_mls_number';
	public const META_REFERENCE = '_hvnly_csv_reference_number';

	public const POLICY_SKIP    = 'skip';
	public const POLICY_UPDATE  = 'update';
	public const POLICY_REPLACE = 'replace';

	/**
	 * @param string $policy Requested policy.
	 * @return string One of skip|update|replace.
	 */
	public static function normalize_policy( string $policy ): string {
		$policy = sanitize_key( $policy );
		return in_array( $policy, array( self::POLICY_SKIP, self::POLICY_UPDATE, self::POLICY_REPLACE ), true )
			? $policy
			: self::POLICY_SKIP;
	}

	/**
	 * Find an existing property post matching mapped row identity fields.
	 *
	 * @param array<string, string> $fields Mapped fields (field id => value).
	 * @return int Post ID or 0 when no match found.
	 */
	public static function find( array $fields ): int {
		$mls = isset( $fields['mls'] ) ? trim( (string) $fields['mls'] ) : '';
		if ( '' !== $mls ) {
			$found = self::find_by_meta( self::META_MLS, $mls );
			if ( $found > 0 ) {
				return $found;
			}
		}

		$reference = isset( $fields['reference'] ) ? trim( (string) $fields['reference'] ) : '';
		if ( '' !== $reference ) {
			$found = self::find_by_meta( self::META_REFERENCE, $reference );
			if ( $found > 0 ) {
				return $found;
			}
		}

		$title = isset( $fields['title'] ) ? trim( (string) $fields['title'] ) : '';
		if ( '' !== $title ) {
			$found = self::find_by_title( $title );
			if ( $found > 0 ) {
				return $found;
			}
		}

		return 0;
	}

	/**
	 * @param string $meta_key Meta key.
	 * @param string $value Value.
	 * @return int
	 */
	private static function find_by_meta( string $meta_key, string $value ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'expired' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => $meta_key,
						'value' => $value,
					),
				),
			)
		);

		$ids = $query->posts;
		return ! empty( $ids ) ? absint( $ids[0] ) : 0;
	}

	/**
	 * @param string $title Exact post title.
	 * @return int
	 */
	private static function find_by_title( string $title ): int {
		$query = new \WP_Query(
			array(
				'post_type'              => AgentConstants::PROPERTY_POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'expired' ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'title'                  => $title,
			)
		);

		$ids = $query->posts;
		return ! empty( $ids ) ? absint( $ids[0] ) : 0;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
