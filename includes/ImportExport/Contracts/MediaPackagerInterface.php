<?php
/**
 * Contract for packaging and unpacking HPTP media binaries.
 *
 * @package HvnlyNab\ImportExport\Contracts
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Media package boundary (implemented in later phases — no ZIP I/O here).
 *
 * @since 3.6.0
 */
interface MediaPackagerInterface {

	/**
	 * Collect media referenced by entities into a package media index.
	 *
	 * @param array<string, mixed> $entities Entity payload / refs.
	 * @param array<string, mixed> $options  Pack options.
	 * @return array<string, mixed> Media index + warnings.
	 */
	public function pack( array $entities, array $options = array() ): array;

	/**
	 * Restore media from a package index into the Media Library.
	 *
	 * @param array<string, mixed> $media_index Package media index.
	 * @param array<string, mixed> $options     Unpack options.
	 * @return array<string, mixed> UUID → attachment ID map + soft-failures.
	 */
	public function unpack( array $media_index, array $options = array() ): array;
}
