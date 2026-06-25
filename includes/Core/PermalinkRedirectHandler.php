<?php
/**
 * 301 redirects for legacy Havenlytics archive permalink bases.
 *
 * @package HvnlyNab\Core
 * @since   3.0.2
 */

namespace HvnlyNab\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Redirects old archive index URLs after slug migrations.
 *
 * @since 3.0.2
 */
final class PermalinkRedirectHandler {

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_legacy_archives' ), 1 );
	}

	/**
	 * Redirect legacy archive roots without touching single-item URLs.
	 *
	 * @return void
	 */
	public static function maybe_redirect_legacy_archives(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$legacy = get_option( PermalinkSettings::LEGACY_REDIRECTS_KEY, array() );
		if ( ! is_array( $legacy ) || empty( $legacy ) ) {
			return;
		}

		$path = self::get_request_path();
		if ( '' === $path ) {
			return;
		}

		$current = PermalinkSettings::get_all();

		$redirects = array(
			array(
				'legacy_key'  => 'property_archive',
				'legacy_slug' => isset( $legacy['property_archive'] ) ? (string) $legacy['property_archive'] : '',
				'new_slug'    => PermalinkSettings::get_property_archive_slug(),
				'single_slug' => PermalinkSettings::get_property_single_slug(),
			),
			array(
				'legacy_key'  => 'agent_archive',
				'legacy_slug' => isset( $legacy['agent_archive'] ) ? (string) $legacy['agent_archive'] : '',
				'new_slug'    => PermalinkSettings::get_agent_archive_slug(),
				'single_slug' => PermalinkSettings::get_agent_single_slug(),
			),
			array(
				'legacy_key'  => 'agency_archive',
				'legacy_slug' => isset( $legacy['agency_archive'] ) ? (string) $legacy['agency_archive'] : '',
				'new_slug'    => PermalinkSettings::get_agency_archive_slug(),
				'single_slug' => PermalinkSettings::get_agency_single_slug(),
			),
		);

		foreach ( $redirects as $rule ) {
			$target = self::match_legacy_archive_redirect( $path, $rule );
			if ( is_string( $target ) && '' !== $target ) {
				wp_safe_redirect( $target, 301 );
				exit;
			}
		}

		unset( $legacy, $current );
	}

	/**
	 * @param string               $path Request path without leading/trailing slashes.
	 * @param array<string, string> $rule Redirect rule config.
	 * @return string|null
	 */
	private static function match_legacy_archive_redirect( string $path, array $rule ): ?string {
		$legacy_slug = isset( $rule['legacy_slug'] ) ? sanitize_title( (string) $rule['legacy_slug'] ) : '';
		$new_slug    = isset( $rule['new_slug'] ) ? sanitize_title( (string) $rule['new_slug'] ) : '';
		$single_slug = isset( $rule['single_slug'] ) ? sanitize_title( (string) $rule['single_slug'] ) : '';

		if ( '' === $legacy_slug || '' === $new_slug || $legacy_slug === $new_slug ) {
			return null;
		}

		$pattern = '#^' . preg_quote( $legacy_slug, '#' ) . '(?:/page/([0-9]+))?/?$#';
		if ( ! preg_match( $pattern, $path, $matches ) ) {
			return null;
		}

		// Avoid redirect loops when legacy archive equaled the single base and singles share that prefix.
		if ( $legacy_slug === $single_slug && ! empty( $matches[0] ) ) {
			// Only archive index and paginated archive index are matched by the regex above.
		}

		$target_path = $new_slug;
		if ( ! empty( $matches[1] ) ) {
			$target_path .= '/page/' . absint( $matches[1] );
		}

		return user_trailingslashit( home_url( '/' . $target_path ) );
	}

	/**
	 * @return string
	 */
	private static function get_request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		$path = is_string( $path ) ? trim( $path, '/' ) : '';

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = is_string( $home_path ) ? trim( $home_path, '/' ) : '';

		if ( '' !== $home_path && str_starts_with( $path, $home_path . '/' ) ) {
			$path = trim( substr( $path, strlen( $home_path ) ), '/' );
		} elseif ( $path === $home_path ) {
			$path = '';
		}

		return $path;
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
