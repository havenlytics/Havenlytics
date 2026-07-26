<?php
/**
 * Contract for building an HPTP package from site data.
 *
 * @package HvnlyNab\ImportExport\Contracts
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Export engine boundary (implemented in later phases).
 *
 * @since 3.6.0
 */
interface ExporterInterface {

	/**
	 * Run or advance an export according to options / job cursor.
	 *
	 * @param array<string, mixed> $options Export selections and job context.
	 * @return array<string, mixed> Progress or completion payload.
	 */
	public function export( array $options ): array;
}
