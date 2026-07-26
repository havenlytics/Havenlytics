<?php
/**
 * CSV Transfer module bootstrap.
 *
 * Spreadsheet-based property import/export: header mapping, validation,
 * batched AJAX jobs, mapping profiles/presets, and Settings UI transport.
 * Independent of the ZIP-based Import / Export (HPTP) module.
 *
 * @package HvnlyNab\CsvTransfer
 * @since   3.7.0
 */

namespace HvnlyNab\CsvTransfer;

use HvnlyNab\CsvTransfer\Admin\CsvAjaxController;
use HvnlyNab\CsvTransfer\Jobs\CsvTempCleanup;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the CSV Transfer feature boundary.
 *
 * @since 3.7.0
 */
final class CsvTransferModule {

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

		CsvAjaxController::register();
		CsvTempCleanup::register_cron();

		/**
		 * Fires once the CSV Transfer module is loaded.
		 *
		 * @since 3.7.0
		 *
		 * @param CsvTransferModule $module Module instance.
		 */
		do_action( 'hvnly_csv_transfer_init', $this );
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
		return wp_create_nonce( CsvAjaxController::NONCE_ACTION );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
