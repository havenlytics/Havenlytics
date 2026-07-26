<?php
/**
 * Import / Export module bootstrap.
 *
 * Property Transfer Package (HPTP): package I/O, entity/media transfer,
 * admin-ajax job transport, Settings UI, and production cleanup.
 *
 * @package HvnlyNab\ImportExport
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport;

use HvnlyNab\ImportExport\Admin\AjaxController;
use HvnlyNab\ImportExport\Jobs\JobCleanup;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the Property Transfer Package (HPTP) feature boundary.
 *
 * Independent of {@see \HvnlyNab\Admin\PropertyImportWizard} (demo content only).
 *
 * @since 3.6.0
 */
final class ImportExportModule {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Bootstrap the module.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		AjaxController::register();
		JobCleanup::register_cron();

		/**
		 * Fires once the Import / Export module is loaded.
		 *
		 * @since 3.6.0
		 *
		 * @param ImportExportModule $module Module instance.
		 */
		do_action( 'hvnly_import_export_init', $this );
	}

	/**
	 * Whether init() has completed for this request lifecycle.
	 *
	 * @return bool
	 */
	public static function is_initialized(): bool {
		return self::$initialized;
	}

	/**
	 * Nonce for admin-ajax actions (Settings UI / manual clients).
	 *
	 * @return string
	 */
	public static function create_nonce(): string {
		return wp_create_nonce( AjaxController::NONCE_ACTION );
	}
}
