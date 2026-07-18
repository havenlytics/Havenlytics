<?php
/**
 * Shared branding/chrome for every Havenlytics transactional email.
 *
 * Sprint 24C. Single source for the pieces that appear in every email —
 * logo, support link, documentation link, version footer — so the nine
 * templates stop each inventing their own.
 *
 * @package HvnlyNab\Email
 * @since   3.2.1
 */

namespace HvnlyNab\Email;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.1
 */
final class EmailBranding {

	/**
	 * Logo URL for email chrome.
	 *
	 * Returns '' when no logo is resolvable — templates then fall back to a text
	 * wordmark. This is deliberate: an <img> pointing at an unreachable URL (a
	 * local dev host, a private network) renders as a broken image in every
	 * recipient's inbox, which looks worse than no logo at all.
	 *
	 * @return string Absolute URL, or '' when none.
	 */
	public static function logo_url(): string {
		$url = '';

		$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id > 0 ) {
			$src = wp_get_attachment_image_src( $custom_logo_id, 'full' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$url = (string) $src[0];
			}
		}

		/**
		 * Filter the logo URL used in Havenlytics emails.
		 *
		 * Must be an absolute, publicly reachable URL. Return '' to render the
		 * text wordmark instead.
		 *
		 * @since 3.2.1
		 *
		 * @param string $url Logo URL.
		 */
		$url = (string) apply_filters( 'hvnly_email_logo_url', $url );

		return $url ? esc_url_raw( $url ) : '';
	}

	/**
	 * Support URL shown in the footer.
	 *
	 * @return string
	 */
	public static function support_url(): string {
		$url = (string) apply_filters( 'hvnly_email_support_url', 'https://havenlytics.com/support/' );

		return $url ? esc_url_raw( $url ) : '';
	}

	/**
	 * Documentation URL shown in the footer.
	 *
	 * @return string
	 */
	public static function docs_url(): string {
		$url = (string) apply_filters( 'hvnly_email_docs_url', 'https://havenlytics.com/docs/' );

		return $url ? esc_url_raw( $url ) : '';
	}

	/**
	 * Plugin version for the footer.
	 *
	 * @return string
	 */
	public static function version(): string {
		return defined( 'HVNLYNAB_VERSION' ) ? (string) HVNLYNAB_VERSION : '';
	}

	/**
	 * Chrome context merged into every email's template vars.
	 *
	 * @return array<string, string>
	 */
	public static function context(): array {
		$chrome = array(
			'brand_logo_url'   => self::logo_url(),
			'brand_support_url' => self::support_url(),
			'brand_docs_url'   => self::docs_url(),
			'brand_version'    => self::version(),
			'brand_site_name'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'brand_site_url'   => home_url( '/' ),
		);

		/**
		 * Filter the shared email chrome context.
		 *
		 * @since 3.2.1
		 *
		 * @param array<string, string> $chrome Chrome context.
		 */
		return apply_filters( 'hvnly_email_branding_context', $chrome );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
