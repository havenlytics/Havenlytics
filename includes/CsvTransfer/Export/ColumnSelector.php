<?php
/**
 * Resolves allowed / selected CSV export columns.
 *
 * @package HvnlyNab\CsvTransfer\Export
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Export;

use HvnlyNab\CsvTransfer\Mapping\CsvSupportedSchema;
use HvnlyNab\CsvTransfer\Mapping\FieldCatalog;

defined( 'ABSPATH' ) || exit;

/**
 * ColumnSelector — validated column list for export.
 *
 * Defaults to the full supported CSV schema (defaults + Gallery / Video / Location).
 *
 * @since 3.7.0
 */
final class ColumnSelector {

	/**
	 * Default export columns — full supported CSV contract.
	 *
	 * @return array<int, string>
	 */
	public static function default_columns(): array {
		return CsvSupportedSchema::field_ids();
	}

	/**
	 * @return array<int, string> All selectable field ids.
	 */
	public static function allowed_columns(): array {
		return FieldCatalog::field_ids();
	}

	/**
	 * Sanitize a requested column list against the catalog, preserving order.
	 *
	 * @param array<int, string> $requested Requested field ids.
	 * @return array<int, string>
	 */
	public static function sanitize( array $requested ): array {
		$allowed = array_flip( self::allowed_columns() );
		$out     = array();
		foreach ( $requested as $column ) {
			$key = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $column );
			if ( '' === $key ) {
				continue;
			}
			if ( isset( $allowed[ $key ] ) && ! in_array( $key, $out, true ) ) {
				$out[] = $key;
			}
		}
		return empty( $out ) ? self::default_columns() : $out;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
