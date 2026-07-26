<?php
/**
 * Contract for applying an HPTP package to the site.
 *
 * @package HvnlyNab\ImportExport\Contracts
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Import engine boundary (implemented in later phases).
 *
 * @since 3.6.0
 */
interface ImporterInterface {

	/**
	 * Run or advance an import according to policies / job cursor.
	 *
	 * @param array<string, mixed> $options Import policies and job context.
	 * @return array<string, mixed> Progress or completion payload.
	 */
	public function import( array $options ): array;
}
