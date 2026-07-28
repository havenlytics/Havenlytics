<?php
/**
 * Attach JavaScript / React script translations for Havenlytics bundles.
 *
 * @package HvnlyNab\I18n
 * @since   3.7.0
 */

namespace HvnlyNab\I18n;

defined( 'ABSPATH' ) || exit;

/**
 * Registers official WordPress script translations for a bundle handle.
 *
 * Uses only {@see wp_set_script_translations()} so JSON language packs from
 * WordPress.org, GlotPress, Loco Translate, and `wp i18n make-json` load into
 * `@wordpress/i18n` before the script runs.
 *
 * @since 3.7.0
 */
final class ScriptTranslations {

	private const DOMAIN = 'havenlytics';

	/**
	 * Register script translations for a registered/enqueued handle.
	 *
	 * @param string $handle Script handle matching wp_register_script / wp_enqueue_script.
	 * @return void
	 */
	public static function attach( string $handle ): void {
		if ( '' === $handle || ! function_exists( 'wp_set_script_translations' ) ) {
			return;
		}

		$path = self::languages_path();

		if ( '' !== $path ) {
			wp_set_script_translations( $handle, self::DOMAIN, $path );
			return;
		}

		wp_set_script_translations( $handle, self::DOMAIN );
	}

	/**
	 * @return string Absolute languages directory or empty string.
	 */
	private static function languages_path(): string {
		if ( defined( 'HVNLYNAB_LANG_DIR' ) && is_string( HVNLYNAB_LANG_DIR ) && '' !== HVNLYNAB_LANG_DIR ) {
			return HVNLYNAB_LANG_DIR;
		}

		return '';
	}
}
