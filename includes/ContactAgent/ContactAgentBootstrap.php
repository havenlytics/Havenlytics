<?php
/**
 * Contact Agent bootstrap — frontend service entry point.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Contact Agent module with Havenlytics frontend services.
 *
 * Phase 1: wiring only — no UI, database, email, or AJAX hooks.
 *
 * @since 3.0.2
 */
class ContactAgentBootstrap {

	/**
	 * @var ContactAgentModule|null
	 */
	private $module;

	/**
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Constructor — invoked by Frontend service manager.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize module wiring.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->load_functions();

		ContactAgentSettings::init();

		$this->module = ContactAgentModule::get_instance();
		$this->module->bootstrap();

		if ( class_exists( '\HvnlyNab\ContactAgent\Database\InquirySchema' ) ) {
			\HvnlyNab\ContactAgent\Database\InquirySchema::create_table();
		}

		$frontend = new ContactAgentFrontend();
		$frontend->register();

		$this->initialized = true;

		/**
		 * Fires when Contact Agent bootstrap completes.
		 *
		 * @since 3.0.2
		 *
		 * @param ContactAgentBootstrap $bootstrap Bootstrap instance.
		 * @param ContactAgentModule    $module    Module container.
		 */
		do_action( 'hvnly_contact_agent_init', $this, $this->module );
	}

	/**
	 * Load global helper functions.
	 *
	 * @return void
	 */
	private function load_functions(): void {
		$file = HVNLYNAB_INCLUDES . '/Functions/contact-agent-functions.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * @return ContactAgentModule|null
	 */
	public function get_module(): ?ContactAgentModule {
		return $this->module;
	}

	/**
	 * Whether bootstrap completed.
	 *
	 * @return bool
	 */
	public function is_initialized(): bool {
		return $this->initialized;
	}
}
