<?php
/**
 * Date range resolver for Analytics dashboard filters.
 *
 * @package HvnlyNab\Analytics
 * @since   3.1.6
 */

namespace HvnlyNab\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves preset and custom date ranges for analytics queries.
 *
 * @since 3.1.6
 */
final class AnalyticsDateRange {

	/** @var string */
	private $range_key;

	/** @var string */
	private $start_date;

	/** @var string */
	private $end_date;

	/**
	 * @param string $range_key Preset key or "custom".
	 * @param string $date_from Optional Y-m-d for custom range start.
	 * @param string $date_to   Optional Y-m-d for custom range end.
	 */
	public function __construct( string $range_key = '30d', string $date_from = '', string $date_to = '' ) {
		$this->range_key = sanitize_key( $range_key ?: '30d' );
		$this->resolve( $date_from, $date_to );
	}

	/**
	 * @return string
	 */
	public function get_key(): string {
		return $this->range_key;
	}

	/**
	 * @return string Y-m-d
	 */
	public function get_start(): string {
		return $this->start_date;
	}

	/**
	 * @return string Y-m-d
	 */
	public function get_end(): string {
		return $this->end_date;
	}

	/**
	 * @return string[]
	 */
	public static function allowed_presets(): array {
		return array( 'today', 'yesterday', '7d', '30d', '90d', '1y' );
	}

	/**
	 * @param string $date_from Custom start.
	 * @param string $date_to   Custom end.
	 */
	private function resolve( string $date_from, string $date_to ): void {
		$today = current_time( 'Y-m-d' );

		if ( 'custom' === $this->range_key ) {
			$start = $this->sanitize_date( $date_from );
			$end   = $this->sanitize_date( $date_to );

			if ( '' === $start || '' === $end ) {
				$start = gmdate( 'Y-m-d', strtotime( '-30 days', strtotime( $today ) ) );
				$end   = $today;
			}

			if ( $start > $end ) {
				$tmp   = $start;
				$start = $end;
				$end   = $tmp;
			}

			$this->start_date = $start;
			$this->end_date   = $end;
			return;
		}

		switch ( $this->range_key ) {
			case 'today':
				$this->start_date = $today;
				$this->end_date   = $today;
				break;
			case 'yesterday':
				$yesterday        = gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );
				$this->start_date = $yesterday;
				$this->end_date   = $yesterday;
				break;
			case '7d':
				$this->start_date = gmdate( 'Y-m-d', strtotime( '-6 days', strtotime( $today ) ) );
				$this->end_date   = $today;
				break;
			case '90d':
				$this->start_date = gmdate( 'Y-m-d', strtotime( '-89 days', strtotime( $today ) ) );
				$this->end_date   = $today;
				break;
			case '1y':
				$this->start_date = gmdate( 'Y-m-d', strtotime( '-364 days', strtotime( $today ) ) );
				$this->end_date   = $today;
				break;
			case '30d':
			default:
				$this->range_key  = '30d';
				$this->start_date = gmdate( 'Y-m-d', strtotime( '-29 days', strtotime( $today ) ) );
				$this->end_date   = $today;
				break;
		}
	}

	/**
	 * @param string $date Raw date string.
	 * @return string
	 */
	private function sanitize_date( string $date ): string {
		$date = sanitize_text_field( $date );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return '';
		}
		return $date;
	}

	/**
	 * Cache key fragment for transients.
	 *
	 * @return string
	 */
	public function cache_suffix(): string {
		return $this->range_key . '_' . $this->start_date . '_' . $this->end_date;
	}
}
