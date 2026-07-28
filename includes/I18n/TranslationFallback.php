<?php
/**
 * Fallback to English when a havenlytics translation is missing or empty.
 *
 * WordPress returns an empty string when the MO catalog contains a msgid with
 * an empty msgstr. That removes UI labels (admin menus, CPT names, etc.).
 * These filters restore the source English string in that case.
 *
 * @package HvnlyNab\I18n
 * @since   3.7.0
 */

namespace HvnlyNab\I18n;

defined( 'ABSPATH' ) || exit;

final class TranslationFallback {

	private const DOMAIN = 'havenlytics';

	/**
	 * Register gettext fallbacks for the plugin text domain.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'gettext', array( self::class, 'fallback' ), 10, 3 );
		add_filter( 'gettext_with_context', array( self::class, 'fallback_with_context' ), 10, 4 );
		add_filter( 'ngettext', array( self::class, 'fallback_plural' ), 10, 5 );
		add_filter( 'ngettext_with_context', array( self::class, 'fallback_plural_with_context' ), 10, 6 );
	}

	/**
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function fallback( string $translation, string $text, string $domain ): string {
		if ( self::DOMAIN !== $domain || '' !== $translation || '' === $text ) {
			return $translation;
		}

		return $text;
	}

	/**
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $context     Context.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function fallback_with_context( string $translation, string $text, string $context, string $domain ): string {
		if ( self::DOMAIN !== $domain || '' !== $translation || '' === $text ) {
			return $translation;
		}

		return $text;
	}

	/**
	 * @param string $translation Translated text.
	 * @param string $single      Singular form.
	 * @param string $plural      Plural form.
	 * @param int    $number      Count.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function fallback_plural( string $translation, string $single, string $plural, int $number, string $domain ): string {
		if ( self::DOMAIN !== $domain || '' !== $translation ) {
			return $translation;
		}

		if ( '' === $single && '' === $plural ) {
			return $translation;
		}

		return ( 1 === $number ) ? $single : $plural;
	}

	/**
	 * @param string $translation Translated text.
	 * @param string $single      Singular form.
	 * @param string $plural      Plural form.
	 * @param int    $number      Count.
	 * @param string $context     Context.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function fallback_plural_with_context( string $translation, string $single, string $plural, int $number, string $context, string $domain ): string {
		return self::fallback_plural( $translation, $single, $plural, $number, $domain );
	}
}
