<?php
/**
 * CSV cell sanitization helpers (formula-injection hardening).
 *
 * @package HvnlyNab\CsvTransfer\Support
 * @since   3.7.1
 */

namespace HvnlyNab\CsvTransfer\Support;

defined( 'ABSPATH' ) || exit;

/**
 * CsvSafeValue — neutralize spreadsheet formula injection in CSV cells.
 *
 * Spreadsheet apps may execute cells whose first character is =, +, -, or @.
 * Values that are purely numeric (including negatives) are left unchanged.
 *
 * @since 3.7.1
 */
final class CsvSafeValue {

	/**
	 * Characters that can trigger formula evaluation when leading a cell.
	 *
	 * @var string
	 */
	private const DANGEROUS_PREFIXES = '=+-@';

	/**
	 * Return a CSV-safe string representation of a cell value.
	 *
	 * Preserves UTF-8, empty strings, commas, quotes, and line breaks.
	 * Purely numeric values (including negatives/decimals) are not altered.
	 *
	 * @param mixed $value Raw cell value.
	 * @return string
	 */
	public static function sanitize( $value ): string {
		if ( null === $value ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		$string = (string) $value;
		if ( '' === $string ) {
			return '';
		}

		// Preserve legitimate numeric cells (e.g. -42.5).
		if ( is_numeric( $string ) ) {
			return $string;
		}

		$first = $string[0];
		if ( false !== strpos( self::DANGEROUS_PREFIXES, $first ) ) {
			return "'" . $string;
		}

		return $string;
	}

	/**
	 * Sanitize every cell in a CSV row.
	 *
	 * @param array<int|string, mixed> $row Row values.
	 * @return array<int|string, string>
	 */
	public static function sanitize_row( array $row ): array {
		$out = array();
		foreach ( $row as $key => $value ) {
			$out[ $key ] = self::sanitize( $value );
		}
		return $out;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
