<?php
/**
 * Lightweight property price resolver for display vs calculation eligibility.
 *
 * Reads existing _hvnly_property_price storage formats without migration.
 *
 * @package HvnlyNab\Services
 * @since   3.1.3
 */

namespace HvnlyNab\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hvnly_Price_Resolver
 */
class Hvnly_Price_Resolver {

	/**
	 * Meta key for property price (Property Builder / PriceLabelField standard).
	 */
	public const PRICE_META_KEY = '_hvnly_property_price';

	/**
	 * Slug meaning “no placeholder label” in PriceLabelField.
	 */
	public const PLACEHOLDER_NONE = 'priceOnCallNone';

	/**
	 * Built-in placeholder slugs (non-calculable).
	 *
	 * @var string[]
	 */
	private static $builtin_placeholder_slugs = array(
		'priceOnCall',
		'fixedPrice',
		'guidePrice',
		'offersOver',
		'offersExcess',
		'fromPrice',
		'askingPrice',
		'contactForPrice',
		'priceOnApplication',
		'priceOnRequest',
		'auction',
		'tender',
		'negotiable',
	);

	/**
	 * Resolve price for a property.
	 *
	 * @param int $property_id Property post ID.
	 * @return array{
	 *     is_calculable: bool,
	 *     numeric_price: float|null,
	 *     display_price: string,
	 *     placeholder_label: string|null
	 * }
	 */
	public static function resolve( $property_id ) {
		$property_id = absint( $property_id );

		$result = array(
			'is_calculable'     => false,
			'numeric_price'     => null,
			'display_price'     => '',
			'placeholder_label' => null,
		);

		if ( ! $property_id ) {
			$result['display_price'] = self::empty_price_text();
			return apply_filters( 'hvnly_price_resolver', $result, $property_id );
		}

		$raw = get_post_meta( $property_id, self::PRICE_META_KEY, true );

		if ( self::is_empty_raw( $raw ) ) {
			$result['display_price'] = self::empty_price_text();
			return apply_filters( 'hvnly_price_resolver', $result, $property_id );
		}

		$helper = HVNLY_NAB()->Helper;

		// JSON custom label from PriceLabelField.
		if ( is_string( $raw ) && strlen( $raw ) > 0 && '{' === $raw[0] ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && isset( $decoded['__type'] ) && 'custom_label' === $decoded['__type'] ) {
				$slug  = isset( $decoded['value'] ) ? (string) $decoded['value'] : '';
				$label = isset( $decoded['label'] ) ? trim( preg_replace( '/\s+/', ' ', (string) $decoded['label'] ) ) : '';

				if ( self::PLACEHOLDER_NONE === $slug || '' === $slug ) {
					$result['display_price'] = self::empty_price_text();
					return apply_filters( 'hvnly_price_resolver', $result, $property_id );
				}

				$result['placeholder_label'] = self::label_for_slug( $slug, $label, $helper );
				$result['display_price']     = $result['placeholder_label'];
				return apply_filters( 'hvnly_price_resolver', $result, $property_id );
			}
		}

		// Pure numeric storage.
		if ( is_numeric( $raw ) ) {
			$numeric = (float) $raw;
			if ( $numeric > 0 ) {
				$result['is_calculable'] = true;
				$result['numeric_price'] = $numeric;
				$result['display_price'] = $helper->format_numeric_price_for_filter( $numeric );
				return apply_filters( 'hvnly_price_resolver', $result, $property_id );
			}

			$result['display_price'] = self::empty_price_text();
			return apply_filters( 'hvnly_price_resolver', $result, $property_id );
		}

		if ( is_string( $raw ) ) {
			$raw = trim( $raw );

			// Known placeholder slug stored as plain string.
			if ( self::is_placeholder_slug( $raw ) ) {
				$result['placeholder_label'] = self::label_for_slug( $raw, '', $helper );
				$result['display_price']     = $result['placeholder_label'];
				return apply_filters( 'hvnly_price_resolver', $result, $property_id );
			}

			// Numeric string (may include formatting characters).
			$numeric = $helper->safe_price_to_float( $raw );
			if ( $numeric > 0 ) {
				$result['is_calculable'] = true;
				$result['numeric_price'] = $numeric;
				$result['display_price'] = $helper->format_numeric_price_for_filter( $numeric );
				return apply_filters( 'hvnly_price_resolver', $result, $property_id );
			}

			// Non-numeric free text — treat as placeholder label.
			if ( '' !== $raw ) {
				$result['placeholder_label'] = $raw;
				$result['display_price']     = $raw;
				return apply_filters( 'hvnly_price_resolver', $result, $property_id );
			}
		}

		$result['display_price'] = $helper->format_price( $raw, $property_id );
		return apply_filters( 'hvnly_price_resolver', $result, $property_id );
	}

	/**
	 * Whether raw meta is empty.
	 *
	 * @param mixed $raw Raw meta value.
	 * @return bool
	 */
	private static function is_empty_raw( $raw ) {
		return ( empty( $raw ) && '0' !== $raw && 0 !== $raw );
	}

	/**
	 * Default empty price text (matches Helpers::format_price).
	 *
	 * @return string
	 */
	private static function empty_price_text() {
		return apply_filters( 'hvnly_empty_price_text', __( 'Price on Request', 'havenlytics' ) );
	}

	/**
	 * Resolve human-readable label for a placeholder slug.
	 *
	 * @param string $slug   Placeholder slug.
	 * @param string $fallback Stored label fallback.
	 * @param object $helper   HVNLY_NAB()->Helper instance.
	 * @return string
	 */
	private static function label_for_slug( $slug, $fallback, $helper ) {
		if ( self::PLACEHOLDER_NONE === $slug ) {
			return self::empty_price_text();
		}

		$db_label = $helper->get_price_on_call_label_by_value( $slug );
		if ( ! empty( $db_label ) ) {
			return apply_filters( 'hvnly_price_on_call_text', $db_label );
		}

		if ( ! empty( $fallback ) ) {
			return apply_filters( 'hvnly_price_on_call_text', $fallback );
		}

		return apply_filters( 'hvnly_price_on_call_text', ucwords( str_replace( array( '_', '-' ), ' ', $slug ) ) );
	}

	/**
	 * Whether a string value is a known placeholder slug.
	 *
	 * @param string $value Raw slug.
	 * @return bool
	 */
	private static function is_placeholder_slug( $value ) {
		if ( self::PLACEHOLDER_NONE === $value ) {
			return false;
		}

		if ( in_array( $value, self::$builtin_placeholder_slugs, true ) ) {
			return true;
		}

		$custom_options = get_option( 'hvnly_price_on_call_custom_options', array() );
		if ( is_array( $custom_options ) ) {
			foreach ( $custom_options as $option ) {
				if ( isset( $option['value'] ) && $option['value'] === $value ) {
					return true;
				}
			}
		}

		$helper   = HVNLY_NAB()->Helper;
		$db_label = $helper->get_price_on_call_label_by_value( $value );
		if ( ! empty( $db_label ) && ! is_numeric( $value ) ) {
			return true;
		}

		return (bool) apply_filters( 'hvnly_price_is_placeholder_slug', false, $value );
	}

	/**
	 * Default mortgage calculation mode (extensible for regional engines).
	 *
	 * @return string
	 */
	public static function get_mortgage_mode() {
		/**
		 * Filter the active mortgage calculation mode.
		 *
		 * @param string $mode Default `international`. Future: `usa`, `uk`, etc.
		 */
		return apply_filters(
			'hvnly_mortgage_mode',
			get_option( 'hvnly_mortgage_mode', 'international' )
		);
	}
}
