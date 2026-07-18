<?php
/**
 * Workspace date/time — single timezone + relative-time contract.
 *
 * Default: WordPress site timezone ({@see wp_timezone()}).
 * Relative diffs use Unix timestamps (timezone-agnostic).
 * Absolute display uses {@see wp_date()} so Settings → General → Timezone is respected.
 *
 * Future agent preferred timezone: filter `hvnly_workspace_timezone`
 * (pass a DateTimeZone or timezone string) — callers should not hardcode offsets.
 *
 * @package HvnlyNab\Workspace
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace;

defined( 'ABSPATH' ) || exit;

/**
 * Shared date/time helpers for Agent Workspace APIs.
 *
 * @since 3.2.0
 */
final class WorkspaceDateTime {

	/**
	 * Earliest plausible content timestamp (rejects zero-date / year-0 parses).
	 *
	 * @var int
	 */
	private const MIN_PLAUSIBLE_TS = 946684800; // 2000-01-01 00:00:00 UTC

	/**
	 * Active display timezone (site default; agent override via filter later).
	 *
	 * @return \DateTimeZone
	 */
	public static function timezone(): \DateTimeZone {
		$tz = apply_filters( 'hvnly_workspace_timezone', wp_timezone() );

		if ( $tz instanceof \DateTimeZone ) {
			return $tz;
		}

		if ( is_string( $tz ) && '' !== $tz ) {
			try {
				return new \DateTimeZone( $tz );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Fall through to site timezone.
			}
		}

		return wp_timezone();
	}

	/**
	 * Current Unix timestamp for relative diffs.
	 *
	 * @return int
	 */
	public static function now(): int {
		return time();
	}

	/**
	 * Absolute local display via WordPress (site / filtered timezone).
	 *
	 * @param int         $unix   Unix timestamp (UTC-based).
	 * @param string|null $format PHP date format; null = site date + time formats.
	 * @return string
	 */
	public static function format( int $unix, ?string $format = null ): string {
		if ( ! self::is_plausible( $unix ) ) {
			return '';
		}

		if ( null === $format || '' === $format ) {
			$format = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' );
		}

		return (string) wp_date( $format, $unix, self::timezone() );
	}

	/**
	 * Relative “X ago” label, or empty when timestamp is missing/invalid.
	 *
	 * @param int $unix Unix timestamp.
	 * @return string
	 */
	public static function relative_ago( int $unix ): string {
		if ( ! self::is_plausible( $unix ) ) {
			return '';
		}

		$diff = human_time_diff( $unix, self::now() );

		return sprintf(
			/* translators: %s: human time difference */
			__( '%s ago', 'havenlytics' ),
			$diff
		);
	}

	/**
	 * ISO-8601 GMT string for sorting / API payloads.
	 *
	 * @param int $unix Unix timestamp.
	 * @return string
	 */
	public static function to_iso_gmt( int $unix ): string {
		if ( ! self::is_plausible( $unix ) ) {
			return '';
		}

		return gmdate( 'c', $unix );
	}

	/**
	 * Parse a MySQL GMT/UTC datetime (`Y-m-d H:i:s`) into Unix time.
	 *
	 * Rejects empty values and WordPress zero-dates (`0000-00-00 00:00:00`).
	 *
	 * @param string $gmt MySQL GMT datetime.
	 * @return int Unix timestamp or 0.
	 */
	public static function parse_gmt_mysql( string $gmt ): int {
		$gmt = trim( $gmt );
		if ( '' === $gmt || self::is_zero_mysql_datetime( $gmt ) ) {
			return 0;
		}

		$ts = strtotime( $gmt . ' UTC' );
		if ( false === $ts ) {
			return 0;
		}

		return self::is_plausible( (int) $ts ) ? (int) $ts : 0;
	}

	/**
	 * Parse an ISO-8601 / RFC3339 / strtotime-friendly string (e.g. gmdate( 'c' )).
	 *
	 * @param string $value Datetime string.
	 * @return int Unix timestamp or 0.
	 */
	public static function parse_datetime( string $value ): int {
		$value = trim( $value );
		if ( '' === $value || self::is_zero_mysql_datetime( $value ) ) {
			return 0;
		}

		$ts = strtotime( $value );
		if ( false === $ts ) {
			return 0;
		}

		return self::is_plausible( (int) $ts ) ? (int) $ts : 0;
	}

	/**
	 * Post created time (GMT preferred; local fallback). Never uses zero-date.
	 *
	 * @param \WP_Post|int $post Post object or ID.
	 * @return int Unix timestamp or 0.
	 */
	public static function from_post_created( $post ): int {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post ) {
			return 0;
		}

		$ts = (int) get_post_time( 'U', true, $post, true );
		if ( self::is_plausible( $ts ) ) {
			return $ts;
		}

		$ts = (int) get_post_time( 'U', false, $post, true );
		return self::is_plausible( $ts ) ? $ts : 0;
	}

	/**
	 * Post modified time (GMT preferred; local fallback).
	 *
	 * @param \WP_Post|int $post Post object or ID.
	 * @return int Unix timestamp or 0.
	 */
	public static function from_post_modified( $post ): int {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post ) {
			return 0;
		}

		$ts = (int) get_post_modified_time( 'U', true, $post, true );
		if ( self::is_plausible( $ts ) ) {
			return $ts;
		}

		$ts = (int) get_post_modified_time( 'U', false, $post, true );
		return self::is_plausible( $ts ) ? $ts : 0;
	}

	/**
	 * @param string $value MySQL or ISO-ish datetime.
	 * @return bool
	 */
	public static function is_zero_mysql_datetime( string $value ): bool {
		return 0 === strpos( $value, '0000-00-00' );
	}

	/**
	 * @param int $unix Candidate Unix timestamp.
	 * @return bool
	 */
	public static function is_plausible( int $unix ): bool {
		if ( $unix < self::MIN_PLAUSIBLE_TS ) {
			return false;
		}
		// Reject absurd future clocks (clock skew of a day is fine).
		if ( $unix > ( self::now() + DAY_IN_SECONDS ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
