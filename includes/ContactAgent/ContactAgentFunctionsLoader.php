<?php
/**
 * Loads Contact Agent global helper functions once per request.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.1.6
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures contact-agent-functions.php is available in admin, frontend, and AJAX contexts.
 *
 * @since 3.1.6
 */
final class ContactAgentFunctionsLoader {

	/** @var bool */
	private static $loaded = false;

	/**
	 * @return void
	 */
	public static function load(): void {
		if ( self::$loaded ) {
			return;
		}

		$file = HVNLYNAB_INCLUDES . '/Functions/contact-agent-functions.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}

		self::$loaded = true;
	}
}
