<?php
/**
 * Advanced View Count Formatter for Havenlytics
 *
 * @package     Havenlytics
 * @subpackage  Frontend
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced View Count Formatter class
 *
 * @since 2.0.0
 */
class ViewCountFormatter {

	/**
	 * Format types with their thresholds
	 *
	 * @var array
	 */
	private static $format_types = array(
		'compact'  => array(
			'threshold' => 1000,
			'suffixes'  => array(
				1000 => 'K',
				1000000 => 'M',
				1000000000 => 'B',
			),
		),
		'readable' => array(
			'threshold' => 1000,
			'suffixes'  => array(
				1000 => ' thousand',
				1000000 => ' million',
				1000000000 => ' billion',
			),
		),
		'full'     => array(
			'threshold' => 1000,
			'suffixes'  => array(
				1000 => ',000',
				1000000 => ',000,000',
				1000000000 => ',000,000,000',
			),
		),
	);

	/**
	 * Format view count with advanced options
	 *
	 * @param int    $count View count.
	 * @param string $format_type Format type (compact, readable, full).
	 * @param array  $args Additional arguments.
	 * @return string
	 * @since 2.0.0
	 */
	public static function format( $count, $format_type = 'compact', $args = array() ) {
		$count = absint( $count );

		// Validate format type
		if ( ! array_key_exists( $format_type, self::$format_types ) ) {
			$format_type = 'compact';
		}

		$defaults = array(
			'precision'   => 1,
			'force_floor' => false,
			'min_value'   => 0,
			'max_value'   => null,
			'locale'      => get_locale(),
		);

		$args = wp_parse_args( $args, $defaults );

		// Apply min/max filters
		$count = self::apply_min_max( $count, $args['min_value'], $args['max_value'] );

		// Get the appropriate formatter method
		$method = "format_{$format_type}";
		if ( method_exists( __CLASS__, $method ) ) {
			return self::$method( $count, $args );
		}

		// Fallback to compact format
		return self::format_compact( $count, $args );
	}

	/**
	 * Format as compact (1K, 1.5M, 2.3B)
	 *
	 * @param int   $count View count.
	 * @param array $args Format arguments.
	 * @return string
	 * @since 2.0.0
	 */
	private static function format_compact( $count, $args ) {
		$format_config = self::$format_types['compact'];
		
		if ( $count < $format_config['threshold'] ) {
			return self::format_number( $count, $args );
		}

		$suffixes = $format_config['suffixes'];
		krsort( $suffixes );

		foreach ( $suffixes as $threshold => $suffix ) {
			if ( $count >= $threshold ) {
				$formatted = $count / $threshold;
				
				// Check if we should floor the value (e.g., 1.0K → 1K)
				if ( $args['force_floor'] && floor( $formatted ) == $formatted ) {
					$formatted = floor( $formatted );
				} else {
					$formatted = round( $formatted, $args['precision'] );
				}

				return self::format_number( $formatted, $args ) . $suffix;
			}
		}

		return self::format_number( $count, $args );
	}

	/**
	 * Format as readable (1 thousand, 1.5 million, 2.3 billion)
	 *
	 * @param int   $count View count.
	 * @param array $args Format arguments.
	 * @return string
	 * @since 2.0.0
	 */
	private static function format_readable( $count, $args ) {
		$format_config = self::$format_types['readable'];
		
		if ( $count < $format_config['threshold'] ) {
			return self::format_number( $count, $args );
		}

		$suffixes = $format_config['suffixes'];
		krsort( $suffixes );

		foreach ( $suffixes as $threshold => $suffix ) {
			if ( $count >= $threshold ) {
				$formatted = $count / $threshold;
				
				if ( $args['force_floor'] && floor( $formatted ) == $formatted ) {
					$formatted = floor( $formatted );
				} else {
					$formatted = round( $formatted, $args['precision'] );
				}

				$number = self::format_number( $formatted, $args );
				$suffix = self::get_localized_suffix( $suffix, $args['locale'] );
				
				return $number . $suffix;
			}
		}

		return self::format_number( $count, $args );
	}

	/**
	 * Format as full number with commas (1,000, 1,500,000)
	 *
	 * @param int   $count View count.
	 * @param array $args Format arguments.
	 * @return string
	 * @since 2.0.0
	 */
	private static function format_full( $count, $args ) {
		return number_format_i18n( $count );
	}

	/**
	 * Format number with locale support
	 *
	 * @param mixed $number Number to format.
	 * @param array $args Format arguments.
	 * @return string
	 * @since 2.0.0
	 */
	private static function format_number( $number, $args ) {
		if ( is_float( $number ) ) {
			// For floats, we need custom formatting to avoid locale issues
			$number = round( $number, $args['precision'] );
			
			// Check if it's a whole number after rounding
			if ( floor( $number ) == $number ) {
				return number_format_i18n( $number, 0 );
			}
			
			return number_format_i18n( $number, $args['precision'] );
		}
		
		return number_format_i18n( $number );
	}

	/**
	 * Get localized suffix for readable format
	 *
	 * @param string $suffix English suffix.
	 * @param string $locale Locale code.
	 * @return string
	 * @since 2.0.0
	 */
	private static function get_localized_suffix( $suffix, $locale ) {
		$localizations = array(
			'en_US' => array(
				' thousand' => ' thousand',
				' million'  => ' million',
				' billion'  => ' billion',
			),
			'es_ES' => array(
				' thousand' => ' mil',
				' million'  => ' millón',
				' billion'  => ' mil millones',
			),
			'fr_FR' => array(
				' thousand' => ' mille',
				' million'  => ' million',
				' billion'  => ' milliard',
			),
			'de_DE' => array(
				' thousand' => ' tausend',
				' million'  => ' million',
				' billion'  => ' milliarde',
			),
		);

		return isset( $localizations[ $locale ][ $suffix ] ) 
			? $localizations[ $locale ][ $suffix ] 
			: $suffix;
	}

	/**
	 * Apply min/max value constraints
	 *
	 * @param int $count View count.
	 * @param int $min Minimum value.
	 * @param int $max Maximum value.
	 * @return int
	 * @since 2.0.0
	 */
	private static function apply_min_max( $count, $min, $max ) {
		if ( null !== $min && $count < $min ) {
			return $min;
		}

		if ( null !== $max && $count > $max ) {
			return $max;
		}

		return $count;
	}

	/**
	 * Get available format types
	 *
	 * @return array
	 * @since 2.0.0
	 */
	public static function get_format_types() {
		return array_keys( self::$format_types );
	}

	/**
	 * Get format configuration
	 *
	 * @param string $format_type Format type.
	 * @return array|null
	 * @since 2.0.0
	 */
	public static function get_format_config( $format_type ) {
		return isset( self::$format_types[ $format_type ] ) 
			? self::$format_types[ $format_type ] 
			: null;
	}
}