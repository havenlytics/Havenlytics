<?php
/**
 * Validates mapped CSV rows before import.
 *
 * @package HvnlyNab\CsvTransfer\Import
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Import;

use HvnlyNab\CsvTransfer\Mapping\FieldCatalog;
use HvnlyNab\CsvTransfer\Mapping\MappingResolver;

defined( 'ABSPATH' ) || exit;

/**
 * RowValidator — required-field + basic type checks per row.
 *
 * @since 3.7.0
 */
final class RowValidator {

	/**
	 * Validate one raw CSV row against a header => fieldId mapping.
	 *
	 * @param array<string, string>      $row     Raw CSV row.
	 * @param array<string, string|null> $mapping Header => field id.
	 * @param int                        $row_number 1-based data row number (for messages).
	 * @return array{row:int,valid:bool,errors:array<int,string>,warnings:array<int,string>,fields:array<string,string>}
	 */
	public static function validate( array $row, array $mapping, int $row_number ): array {
		$fields   = MappingResolver::map_row( $row, $mapping );
		$errors   = array();
		$warnings = array();

		foreach ( FieldCatalog::required_field_ids() as $required_id ) {
			$value = isset( $fields[ $required_id ] ) ? trim( (string) $fields[ $required_id ] ) : '';
			if ( '' === $value ) {
				$errors[] = sprintf(
					/* translators: %s: required field label */
					__( 'Missing required field "%s".', 'havenlytics' ),
					$required_id
				);
			}
		}

		if ( isset( $fields['price'] ) && '' !== trim( $fields['price'] ) ) {
			$numeric = preg_replace( '/[^0-9.\-]/', '', (string) $fields['price'] );
			if ( '' === $numeric || ! is_numeric( $numeric ) ) {
				$warnings[] = __( 'Price value is not numeric; it will be stored as text.', 'havenlytics' );
			}
		}

		foreach ( array( 'latitude', 'longitude' ) as $coord ) {
			if ( isset( $fields[ $coord ] ) && '' !== trim( $fields[ $coord ] ) && ! is_numeric( trim( (string) $fields[ $coord ] ) ) ) {
				$warnings[] = sprintf(
					/* translators: %s: field name */
					__( '"%s" is not a valid coordinate.', 'havenlytics' ),
					$coord
				);
			}
		}

		if ( isset( $fields['agent_email'] ) && '' !== trim( $fields['agent_email'] ) && ! is_email( trim( (string) $fields['agent_email'] ) ) ) {
			$warnings[] = __( 'Agent email is not a valid email address; the property will be created without an assigned agent.', 'havenlytics' );
		}

		return array(
			'row'      => $row_number,
			'valid'    => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
			'fields'   => $fields,
		);
	}

	/**
	 * Validate a batch of raw rows.
	 *
	 * @param array<int, array<string, string>> $rows Raw CSV rows.
	 * @param array<string, string|null>        $mapping Header => field id.
	 * @param int                                $start_row_number 1-based row number of the first row in $rows.
	 * @return array{results:array<int,array>,valid_count:int,error_count:int,warning_count:int}
	 */
	public static function validate_batch( array $rows, array $mapping, int $start_row_number = 1 ): array {
		$results       = array();
		$valid_count   = 0;
		$error_count   = 0;
		$warning_count = 0;

		foreach ( array_values( $rows ) as $index => $row ) {
			$result    = self::validate( $row, $mapping, $start_row_number + $index );
			$results[] = $result;
			if ( $result['valid'] ) {
				++$valid_count;
			} else {
				++$error_count;
			}
			if ( ! empty( $result['warnings'] ) ) {
				++$warning_count;
			}
		}

		return array(
			'results'       => $results,
			'valid_count'   => $valid_count,
			'error_count'   => $error_count,
			'warning_count' => $warning_count,
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
