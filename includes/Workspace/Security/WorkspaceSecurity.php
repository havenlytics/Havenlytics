<?php
/**
 * Workspace input sanitization / output escaping helpers.
 *
 * Single entry point for Workspace sanitization — avoid ad-hoc sanitize_* calls.
 *
 * @package HvnlyNab\Workspace\Security
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize and escape helpers for Workspace forms and API payloads.
 *
 * @since 3.2.0
 */
final class WorkspaceSecurity {

	/**
	 * Sanitize plain single-line text.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function text( $value ): string {
		return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Sanitize multi-line plain text (no HTML).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function textarea( $value ): string {
		return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Sanitize HTML using wp_kses_post.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function html( $value ): string {
		return wp_kses_post( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Sanitize URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function url( $value ): string {
		return esc_url_raw( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Sanitize email.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function email( $value ): string {
		return sanitize_email( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Sanitize phone-like string (digits, spaces, +, -, (), ext).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function phone( $value ): string {
		$raw = is_scalar( $value ) ? (string) $value : '';
		$raw = sanitize_text_field( $raw );
		return (string) preg_replace( '/[^0-9+\-\s().extEXT]/', '', $raw );
	}

	/**
	 * Sanitize numeric string / float.
	 *
	 * @param mixed $value Raw value.
	 * @return string Empty string when not numeric.
	 */
	public static function number( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$raw = trim( str_replace( ',', '', (string) $value ) );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return '';
		}
		return $raw;
	}

	/**
	 * Sanitize currency amount (non-negative number as string).
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function currency( $value ): string {
		$num = self::number( $value );
		if ( '' === $num || (float) $num < 0 ) {
			return '';
		}
		return $num;
	}

	/**
	 * Sanitize taxonomy slug or list of slugs.
	 *
	 * @param mixed $value Slug string or array of slugs.
	 * @return string|array<int, string>
	 */
	public static function taxonomy( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $slug ) {
				$clean = sanitize_title( is_scalar( $slug ) ? (string) $slug : '' );
				if ( '' !== $clean ) {
					$out[] = $clean;
				}
			}
			return array_values( array_unique( $out ) );
		}
		return sanitize_title( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Recursively sanitize a list of scalar/array values as text.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int|string, mixed>
	 */
	public static function array_values( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			$safe_key = is_int( $key ) ? $key : sanitize_key( (string) $key );
			if ( is_array( $item ) ) {
				$out[ $safe_key ] = self::array_values( $item );
			} elseif ( is_scalar( $item ) || null === $item ) {
				$out[ $safe_key ] = self::text( $item );
			}
		}
		return $out;
	}

	/**
	 * Escape for HTML body text.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function escape( $value ): string {
		return esc_html( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Escape for HTML attributes.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function escape_attr( $value ): string {
		return esc_attr( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Whether Workspace debug UX diagnostics are enabled.
	 *
	 * @return bool
	 */
	public static function is_debug(): bool {
		if ( defined( 'HVNLY_WORKSPACE_DEBUG' ) ) {
			return (bool) HVNLY_WORKSPACE_DEBUG;
		}
		/**
		 * Filter Workspace debug mode (diagnostics in validation UX).
		 *
		 * @since 3.2.0
		 *
		 * @param bool $debug Default false unless constant set.
		 */
		return (bool) apply_filters( 'hvnly_workspace_debug', false );
	}

	/**
	 * Future: CSRF / nonce helpers hook point.
	 *
	 * @param string $action Action name.
	 * @return string
	 */
	public static function create_nonce( string $action = 'hvnly_workspace' ): string {
		return wp_create_nonce( $action );
	}

	/**
	 * Future: audit log hook (no-op storage yet).
	 *
	 * @param string               $event   Event key.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	public static function audit( string $event, array $context = array() ): void {
		/**
		 * Fires when Workspace wants an audit log entry.
		 *
		 * @since 3.2.0
		 *
		 * @param string               $event   Event key.
		 * @param array<string, mixed> $context Context payload.
		 */
		do_action( 'hvnly_workspace_audit', $event, $context );
	}
}
