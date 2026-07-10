<?php
/**
 * Registers Contact Agent AJAX handlers on every request (including admin-ajax.php).
 *
 * Frontend bootstrap can fail before ContactAgentBootstrap runs; this registrar
 * ensures inquiry submissions always have a registered handler.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
final class ContactAgentAjaxRegistrar {

	/** @var bool */
	private static $registered = false;

	/**
	 * Register wp_ajax hooks (idempotent).
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		$action = ContactAgentConstants::AJAX_SUBMIT_ACTION;

		add_action( 'wp_ajax_' . $action, array( __CLASS__, 'dispatch' ) );
		add_action( 'wp_ajax_nopriv_' . $action, array( __CLASS__, 'dispatch' ) );

		self::$registered = true;
	}

	/**
	 * Bootstrap module services and delegate to AjaxHandler.
	 *
	 * @return void
	 */
	public static function dispatch(): void {
		self::bootstrap_dependencies();

		if ( ! class_exists( ContactAgentModule::class ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Contact Agent module is not available.', 'havenlytics' ),
					'code'    => 'hvnly_contact_agent_module_missing',
					'status'  => 503,
				),
				503
			);
		}

		$module = ContactAgentModule::get_instance();
		$module->bootstrap();

		if ( class_exists( '\HvnlyNab\ContactAgent\Database\InquirySchema' ) ) {
			\HvnlyNab\ContactAgent\Database\InquirySchema::create_table();
		}

		$module->get_ajax_handler()->handle_submit();
	}

	/**
	 * Load helpers required for AJAX handling.
	 *
	 * @return void
	 */
	private static function bootstrap_dependencies(): void {
		ContactAgentFunctionsLoader::load();

		if ( class_exists( ContactAgentSettings::class ) ) {
			ContactAgentSettings::init();
		}
	}
}
