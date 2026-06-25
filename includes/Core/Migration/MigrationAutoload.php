<?php
/**
 * Case-safe loader for migration shared types.
 *
 * PSR-4 expects Traits/ and Interfaces/ (capitalized). Some deployments
 * (especially from Windows) ship lowercase traits/ and interfaces/ folders,
 * which breaks Composer autoloading on Linux.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration
 * @since       3.0.2
 */

namespace HvnlyNab\Core\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures MigrationTrait and MigrationInterface are loaded before handlers run.
 *
 * @since 3.0.2
 */
final class MigrationAutoload {

	/**
	 * Load migration trait/interface when autoload paths differ by directory case.
	 *
	 * @return void
	 */
	public static function load_dependencies(): void {
		$base = __DIR__;

		if ( ! trait_exists( 'HvnlyNab\Core\Migration\Traits\MigrationTrait' ) ) {
			self::require_first_readable(
				array(
					$base . '/MigrationTrait.php',
					$base . '/Traits/MigrationTrait.php',
					$base . '/traits/MigrationTrait.php',
				)
			);
		}

		if ( ! interface_exists( 'HvnlyNab\Core\Migration\Interfaces\MigrationInterface' ) ) {
			self::require_first_readable(
				array(
					$base . '/MigrationInterface.php',
					$base . '/Interfaces/MigrationInterface.php',
					$base . '/interfaces/MigrationInterface.php',
				)
			);
		}
	}

	/**
	 * @param string[] $paths Candidate file paths.
	 * @return void
	 */
	private static function require_first_readable( array $paths ): void {
		foreach ( $paths as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
