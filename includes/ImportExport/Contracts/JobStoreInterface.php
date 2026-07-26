<?php
/**
 * Contract for durable Import / Export job state (options-backed in 3.6.0).
 *
 * @package HvnlyNab\ImportExport\Contracts
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Job cursor / lock storage boundary (implemented in later phases).
 *
 * @since 3.6.0
 */
interface JobStoreInterface {

	/**
	 * Load the active job state, if any.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_job(): ?array;

	/**
	 * Persist job state.
	 *
	 * @param array<string, mixed> $job Job payload.
	 * @return void
	 */
	public function save_job( array $job ): void;

	/**
	 * Clear job state after completion or cancel cleanup.
	 *
	 * @return void
	 */
	public function clear_job(): void;
}
