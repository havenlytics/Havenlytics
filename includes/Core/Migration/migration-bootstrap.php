<?php
/**
 * Load migration trait/interface before Composer autoloads handlers.
 *
 * Flat files live beside this bootstrap so Linux deployments always include them,
 * even when Traits/Interfaces subfolders are missing from the release package.
 *
 * @package Havenlytics
 * @since   3.0.2
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hvnly_load_migration_dependencies' ) ) {
	/**
	 * @return void
	 */
	function hvnly_load_migration_dependencies(): void {
		static $loaded = false;

		if ( $loaded ) {
			return;
		}

		$loaded = true;
		$base   = __DIR__;

		if ( ! trait_exists( 'HvnlyNab\Core\Migration\Traits\MigrationTrait', false ) ) {
			foreach ( array(
				$base . '/MigrationTrait.php',
				$base . '/Traits/MigrationTrait.php',
				$base . '/traits/MigrationTrait.php',
			) as $path ) {
				if ( is_readable( $path ) ) {
					require_once $path;
					break;
				}
			}
		}

		if ( ! interface_exists( 'HvnlyNab\Core\Migration\Interfaces\MigrationInterface', false ) ) {
			foreach ( array(
				$base . '/MigrationInterface.php',
				$base . '/Interfaces/MigrationInterface.php',
				$base . '/interfaces/MigrationInterface.php',
			) as $path ) {
				if ( is_readable( $path ) ) {
					require_once $path;
					break;
				}
			}
		}
	}
}

hvnly_load_migration_dependencies();
