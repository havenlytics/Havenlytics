<?php
/**
 * Lightweight CSV reader: delimiter detection + assoc-array row iteration.
 *
 * @package HvnlyNab\CsvTransfer\Support
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer\Support;

defined( 'ABSPATH' ) || exit;

/**
 * CsvStream — opens a CSV file on disk and reads header/rows.
 *
 * @since 3.7.0
 */
final class CsvStream {

	/** @var array<int, string> */
	private const CANDIDATE_DELIMITERS = array( ',', ';', "\t", '|' );

	/** @var string */
	private $path;

	/** @var string */
	private $delimiter;

	/** @var array<int, string> */
	private $headers = array();

	/**
	 * @param string $path Absolute file path.
	 * @param string $delimiter Optional forced delimiter; auto-detected when empty.
	 */
	private function __construct( string $path, string $delimiter ) {
		$this->path      = $path;
		$this->delimiter = $delimiter;
	}

	/**
	 * @param string $path Absolute file path.
	 * @param string $delimiter Optional forced delimiter (','|';'|'\t'|'|').
	 * @return self|null Null when the file cannot be opened.
	 */
	public static function open( string $path, string $delimiter = '' ): ?self {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		if ( '' === $delimiter ) {
			$delimiter = self::detect_delimiter( $path );
		}

		$stream = new self( $path, $delimiter );
		$stream->read_headers();
		return $stream;
	}

	/**
	 * @return string Delimiter in use.
	 */
	public function delimiter(): string {
		return $this->delimiter;
	}

	/**
	 * @return array<int, string> Header row (BOM-stripped, trimmed).
	 */
	public function headers(): array {
		return $this->headers;
	}

	/**
	 * Read up to $limit data rows for a quick preview.
	 *
	 * @param int $limit Number of rows.
	 * @return array<int, array<string, string>>
	 */
	public function sample_rows( int $limit = 10 ): array {
		$rows   = array();
		$handle = $this->open_handle();
		if ( ! $handle ) {
			return $rows;
		}

		// Skip header row.
		fgetcsv( $handle, 0, $this->delimiter );

		$count = 0;
		while ( $count < $limit && false !== ( $row = fgetcsv( $handle, 0, $this->delimiter ) ) ) {
			if ( self::is_blank_row( $row ) ) {
				continue;
			}
			$rows[] = $this->assoc_row( $row );
			++$count;
		}
		fclose( $handle );
		return $rows;
	}

	/**
	 * Total number of data rows (excludes header, skips blank lines).
	 *
	 * @return int
	 */
	public function count_rows(): int {
		$handle = $this->open_handle();
		if ( ! $handle ) {
			return 0;
		}
		fgetcsv( $handle, 0, $this->delimiter );
		$count = 0;
		while ( false !== ( $row = fgetcsv( $handle, 0, $this->delimiter ) ) ) {
			if ( ! self::is_blank_row( $row ) ) {
				++$count;
			}
		}
		fclose( $handle );
		return $count;
	}

	/**
	 * Iterate a slice of data rows as associative arrays keyed by header.
	 *
	 * @param int $offset Zero-based data row offset.
	 * @param int $limit  Max rows to return (0 = all remaining).
	 * @return array<int, array<string, string>>
	 */
	public function read_rows( int $offset, int $limit = 0 ): array {
		$handle = $this->open_handle();
		if ( ! $handle ) {
			return array();
		}
		fgetcsv( $handle, 0, $this->delimiter );

		$index = 0;
		$out   = array();
		while ( false !== ( $row = fgetcsv( $handle, 0, $this->delimiter ) ) ) {
			if ( self::is_blank_row( $row ) ) {
				continue;
			}
			if ( $index >= $offset ) {
				$out[] = $this->assoc_row( $row );
				if ( $limit > 0 && count( $out ) >= $limit ) {
					break;
				}
			}
			++$index;
		}
		fclose( $handle );
		return $out;
	}

	/**
	 * @return resource|false
	 */
	private function open_handle() {
		return fopen( $this->path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	}

	/**
	 * @return void
	 */
	private function read_headers(): void {
		$handle = $this->open_handle();
		if ( ! $handle ) {
			return;
		}
		$row = fgetcsv( $handle, 0, $this->delimiter );
		fclose( $handle );
		if ( ! is_array( $row ) ) {
			return;
		}
		$row[0]        = self::strip_bom( (string) ( $row[0] ?? '' ) );
		$this->headers = array_map(
			static function ( $value ) {
				return self::to_utf8( trim( (string) $value ) );
			},
			$row
		);
	}

	/**
	 * @param array<int, string> $row Raw CSV row.
	 * @return array<string, string>
	 */
	private function assoc_row( array $row ): array {
		$assoc = array();
		foreach ( $this->headers as $index => $header ) {
			$value            = $row[ $index ] ?? '';
			$assoc[ $header ] = self::to_utf8( is_string( $value ) ? $value : (string) $value );
		}
		return $assoc;
	}

	/**
	 * @param array<int, string> $row Raw row.
	 * @return bool
	 */
	private static function is_blank_row( array $row ): bool {
		if ( 1 === count( $row ) && ( null === $row[0] || '' === trim( (string) $row[0] ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $path File path.
	 * @return string Best-guess delimiter.
	 */
	private static function detect_delimiter( string $path ): string {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return ',';
		}
		$line = fgets( $handle );
		fclose( $handle );
		if ( ! is_string( $line ) ) {
			return ',';
		}
		$line = self::strip_bom( $line );

		$best       = ',';
		$best_count = -1;
		foreach ( self::CANDIDATE_DELIMITERS as $candidate ) {
			$count = substr_count( $line, $candidate );
			if ( $count > $best_count ) {
				$best_count = $count;
				$best       = $candidate;
			}
		}
		return $best;
	}

	/**
	 * @param string $value Raw string.
	 * @return string BOM-stripped string.
	 */
	private static function strip_bom( string $value ): string {
		return preg_replace( '/^\xEF\xBB\xBF/', '', $value ) ?? $value;
	}

	/**
	 * Best-effort UTF-8 normalization for CSVs exported from Excel/legacy tools.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function to_utf8( string $value ): string {
		if ( '' === $value ) {
			return $value;
		}
		if ( function_exists( 'mb_check_encoding' ) && mb_check_encoding( $value, 'UTF-8' ) ) {
			return $value;
		}
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$converted = @mb_convert_encoding( $value, 'UTF-8', 'Windows-1252, ISO-8859-1, ASCII' );
			if ( is_string( $converted ) ) {
				return $converted;
			}
		}
		return $value;
	}

	/**
	 * Prevent direct instantiation outside of open().
	 */
	private function __clone() {}
}
