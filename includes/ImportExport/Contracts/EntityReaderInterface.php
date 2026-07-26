<?php
/**
 * Contract for reading portable entities from a package context.
 *
 * @package HvnlyNab\ImportExport\Contracts
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Reads logical entity collections (properties, terms, agents, builders, …).
 *
 * @since 3.6.0
 */
interface EntityReaderInterface {

	/**
	 * Return entities for a logical section key (e.g. properties, agents).
	 *
	 * @param string               $section Section name.
	 * @param array<string, mixed> $args    Optional filters / pagination.
	 * @return array<int, array<string, mixed>>
	 */
	public function read_section( string $section, array $args = array() ): array;
}
