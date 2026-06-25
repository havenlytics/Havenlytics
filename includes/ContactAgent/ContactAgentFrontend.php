<?php
/**
 * Contact Agent frontend hooks (Phase 2 — UI only).
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * Registers frontend presentation hooks when the feature is enabled.
 *
 * @since 3.0.2
 */
class ContactAgentFrontend {

	/**
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Register WordPress hooks (idempotent).
	 *
	 * @return void
	 */
	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		if ( ! function_exists( 'hvnly_is_contact_agent_enabled' ) || ! hvnly_is_contact_agent_enabled() ) {
			return;
		}

		/**
		 * Fires before Contact Agent frontend hooks are registered.
		 *
		 * @since 3.0.2
		 */
		do_action( 'hvnly_contact_agent_before_frontend_register' );

		$this->registered = true;

		/**
		 * Fires after Contact Agent frontend hooks are registered.
		 *
		 * Template output is wired via template-hooks.php; assets via Frontend\Assets.
		 *
		 * @since 3.0.2
		 */
		do_action( 'hvnly_contact_agent_frontend_registered' );
	}

	/**
	 * Whether frontend hooks were registered.
	 *
	 * @return bool
	 */
	public function is_registered(): bool {
		return $this->registered;
	}
}
