<?php
/**
 * Helpers for normalizing Havenlytics widget instance data for the block widget editor.
 *
 * @package HvnlyNab\Database\Custom_Widgets
 * @since   3.0.6
 */

namespace HvnlyNab\Database\Custom_Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Shared normalization and merge utilities for WP_Widget instances.
 */
final class WidgetInstanceHelpers {

	/**
	 * Instance keys that must always be arrays when present (legacy agent widget).
	 *
	 * @var array<int, string>
	 */
	private const ARRAY_INSTANCE_KEYS = array(
		'selected_properties',
		'property_ids',
		'properties',
	);

	/**
	 * Normalize a widget instance for admin REST / block editor consumption.
	 *
	 * @param mixed $instance Raw instance.
	 * @return array<string, mixed>
	 */
	public static function normalize_instance( $instance ): array {
		if ( ! is_array( $instance ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $instance as $key => $value ) {
			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				continue;
			}

			$key = (string) $key;

			if ( null === $value ) {
				continue;
			}

			if ( in_array( $key, self::ARRAY_INSTANCE_KEYS, true ) ) {
				$normalized[ $key ] = self::normalize_id_list( $value );
				continue;
			}

			$normalized[ $key ] = $value;
		}

		return $normalized;
	}

	/**
	 * Merge sanitized keys into a previous instance without dropping legacy keys.
	 *
	 * @param array<string, mixed> $old_instance Previous instance.
	 * @param array<string, mixed> $sanitized    Newly sanitized keys.
	 * @return array<string, mixed>
	 */
	public static function merge_instance( array $old_instance, array $sanitized ): array {
		$old_instance = self::normalize_instance( $old_instance );
		$sanitized    = self::normalize_instance( $sanitized );

		return array_merge( $old_instance, $sanitized );
	}

	/**
	 * Sanitize a full widget option row (all instances + _multiwidget).
	 *
	 * @param mixed $option Raw option value.
	 * @return array<int|string, mixed>
	 */
	public static function sanitize_widget_option( $option ): array {
		if ( ! is_array( $option ) ) {
			return array( '_multiwidget' => 1 );
		}

		$sanitized = array();

		foreach ( $option as $key => $value ) {
			if ( '_multiwidget' === $key ) {
				$sanitized['_multiwidget'] = 1;
				continue;
			}

			if ( is_numeric( $key ) ) {
				$sanitized[ (int) $key ] = self::normalize_instance( $value );
			}
		}

		if ( ! isset( $sanitized['_multiwidget'] ) ) {
			$sanitized['_multiwidget'] = 1;
		}

		return $sanitized;
	}

	/**
	 * Sanitize the sidebars_widgets option.
	 *
	 * @param mixed $sidebars Raw option value.
	 * @return array<string, array<int, string>>
	 */
	public static function sanitize_sidebars_widgets( $sidebars ): array {
		if ( ! is_array( $sidebars ) ) {
			return array(
				'array_version' => 3,
				'wp_inactive_widgets' => array(),
			);
		}

		$normalized = array();

		foreach ( $sidebars as $sidebar_id => $widgets ) {
			if ( 'array_version' === $sidebar_id ) {
				$normalized['array_version'] = 3;
				continue;
			}

			if ( ! is_string( $sidebar_id ) || '' === $sidebar_id ) {
				continue;
			}

			if ( ! is_array( $widgets ) ) {
				$widgets = array();
			}

			$list = array();

			foreach ( $widgets as $widget_id ) {
				if ( ! is_string( $widget_id ) || '' === $widget_id ) {
					continue;
				}

				$list[] = $widget_id;
			}

			$normalized[ $sidebar_id ] = array_values( array_unique( $list ) );
		}

		if ( ! isset( $normalized['array_version'] ) ) {
			$normalized['array_version'] = 3;
		}

		if ( ! isset( $normalized['wp_inactive_widgets'] ) || ! is_array( $normalized['wp_inactive_widgets'] ) ) {
			$normalized['wp_inactive_widgets'] = array();
		}

		return $normalized;
	}

	/**
	 * @param mixed $value Raw list value.
	 * @return array<int, int>
	 */
	public static function normalize_id_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$ids = array();

		foreach ( $value as $item ) {
			$id = absint( $item );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Coerce checkbox-like values to Havenlytics string flags.
	 *
	 * @param mixed $value Raw value.
	 * @return string '1' or '0'
	 */
	public static function checkbox_flag( $value ): string {
		return ! empty( $value ) && '0' !== (string) $value ? '1' : '0';
	}
}
