<?php
/**
 * Contract for writing portable entities into WordPress.
 *
 * @package HvnlyNab\ImportExport\Contracts
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Writes remapped entities during staged import (later phases).
 *
 * @since 3.6.0
 */
interface EntityWriterInterface {

	/**
	 * Persist one entity of a given section type.
	 *
	 * @param string               $section Section name.
	 * @param array<string, mixed> $entity  Portable entity payload.
	 * @param array<string, mixed> $context Remap maps and import policies.
	 * @return array<string, mixed> Result including local IDs / status.
	 */
	public function write_entity( string $section, array $entity, array $context = array() ): array;
}
