<?php
/**
 * Shared header builder for Havenlytics transactional email.
 *
 * Sprint 24C. Extracts the From/Reply-To logic that was copy-pasted across
 * four notifiers, and fixes two defects in the process:
 *
 * 1. DMARC. The previous From: builder fell back to get_option('admin_email')
 *    whenever hvnly_email_from_address was unset — which is the default state.
 *    On most WordPress sites admin_email is a @gmail.com / @outlook.com address.
 *    Emitting "From: someone@gmail.com" from the site's own server fails DMARC
 *    alignment against Gmail's p=reject policy and gets the mail rejected or
 *    spam-foldered. We now set From: ONLY when an address has been explicitly
 *    configured; otherwise we omit the header entirely and let WordPress use
 *    wordpress@site-domain, which is always aligned with the sending host.
 *
 * 2. Header injection. Display names were interpolated unquoted:
 *        sprintf( 'Reply-To: %s <%s>', $name, $email )
 *    sanitize_text_field() strips CRLF but NOT commas, and wp_mail() splits
 *    Reply-To on commas — so a visitor named `Bob, attacker@evil.com` could
 *    add a second Reply-To to the agent's notification. Phrases are now quoted
 *    and the quote/comma/angle characters stripped.
 *
 * @package HvnlyNab\Email
 * @since   3.2.1
 */

namespace HvnlyNab\Email;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.1
 */
final class EmailHeaders {

	/**
	 * HTML content type header. Set per-message, never via the global
	 * wp_mail_content_type filter (which would break other plugins' mail).
	 *
	 * @var string
	 */
	public const CONTENT_TYPE_HTML = 'Content-Type: text/html; charset=UTF-8';

	/**
	 * Base headers for an HTML email: content type + (optional) From.
	 *
	 * @return string[]
	 */
	public static function base(): array {
		$headers = array( self::CONTENT_TYPE_HTML );

		$from = self::from_header();
		if ( '' !== $from ) {
			$headers[] = $from;
		}

		return $headers;
	}

	/**
	 * Build the From: header — or return '' to omit it.
	 *
	 * Only returns a header when the administrator has EXPLICITLY configured
	 * hvnly_email_from_address. We deliberately do NOT fall back to admin_email:
	 * see the DMARC note in the class docblock.
	 *
	 * @return string Header line, or '' when From: should be omitted.
	 */
	public static function from_header(): string {
		$configured = sanitize_email( (string) EmailSettings::get( 'hvnly_email_from_address', '' ) );

		if ( ! is_email( $configured ) ) {
			// Not configured. Omit From: — WordPress will use wordpress@site-domain,
			// which is always DMARC-aligned with the sending host.
			return '';
		}

		$name = self::sanitize_phrase( (string) EmailSettings::get( 'hvnly_email_from_name', '' ) );
		if ( '' === $name ) {
			$name = self::sanitize_phrase( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		}

		$header = '' !== $name
			? sprintf( 'From: "%s" <%s>', $name, $configured )
			: 'From: ' . $configured;

		/**
		 * Filter the Havenlytics From: header.
		 *
		 * Returning '' omits the header (recommended when no verified sending
		 * domain is configured).
		 *
		 * @since 3.2.1
		 *
		 * @param string $header     The From: header line, or ''.
		 * @param string $configured Configured from-address.
		 */
		return (string) apply_filters( 'hvnly_email_from_header', $header, $configured );
	}

	/**
	 * Build a Reply-To: header from an address + optional display name.
	 *
	 * @param string $email Reply-to address.
	 * @param string $name  Optional display name.
	 * @return string Header line, or '' when the address is not valid.
	 */
	public static function reply_to( string $email, string $name = '' ): string {
		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return '';
		}

		$name = self::sanitize_phrase( $name );

		return '' !== $name
			? sprintf( 'Reply-To: "%s" <%s>', $name, $email )
			: 'Reply-To: ' . $email;
	}

	/**
	 * Make a string safe to use as an RFC 5322 display phrase.
	 *
	 * Strips CRLF/tabs (header injection) AND the characters that would break
	 * out of a quoted phrase or be read as an address-list separator:
	 * comma, quote, angle brackets, semicolon, colon, backslash.
	 *
	 * @param string $value Raw display name.
	 * @return string
	 */
	public static function sanitize_phrase( string $value ): string {
		$value = sanitize_text_field( $value );
		$value = preg_replace( '/[\r\n\t]+/', ' ', $value );
		$value = str_replace( array( ',', '"', '<', '>', ';', ':', '\\' ), '', (string) $value );

		return trim( (string) $value );
	}

	/**
	 * Attach a plain-text AltBody for the next {@see wp_mail()} call via phpmailer_init.
	 *
	 * Does not bypass wp_mail(); SMTP plugins still receive the message normally.
	 * One-shot: the listener removes itself after firing.
	 *
	 * @param string $html_body Rendered HTML body.
	 * @return void
	 */
	public static function attach_plain_text_alt( string $html_body ): void {
		$plain = wp_strip_all_tags( $html_body );
		$plain = html_entity_decode( $plain, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$plain = trim( preg_replace( "/[ \t]+/", ' ', $plain ) ?? $plain );
		$plain = trim( preg_replace( "/\n{3,}/", "\n\n", $plain ) ?? $plain );

		if ( '' === $plain ) {
			return;
		}

		$callback = null;
		$callback = static function ( $phpmailer ) use ( $plain, &$callback ) {
			if ( is_object( $phpmailer ) && empty( $phpmailer->AltBody ) ) {
				$phpmailer->AltBody = $plain;
			}
			if ( null !== $callback ) {
				remove_action( 'phpmailer_init', $callback, 20 );
			}
		};

		add_action( 'phpmailer_init', $callback, 20 );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
