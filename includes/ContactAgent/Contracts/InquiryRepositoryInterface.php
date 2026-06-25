<?php
/**
 * Inquiry persistence contract.
 *
 * @package HvnlyNab\ContactAgent\Contracts
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
interface InquiryRepositoryInterface {

	/**
	 * Persist an inquiry row.
	 *
	 * @param array<string, mixed> $data Sanitized inquiry payload.
	 * @return int|\WP_Error Insert ID or error.
	 */
	public function create( array $data );

	/**
	 * Fetch a single inquiry by ID.
	 *
	 * @param int $inquiry_id Inquiry ID.
	 * @return array<string, mixed>|null
	 */
	public function find( int $inquiry_id ): ?array;

	/**
	 * List inquiries with optional filters.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function list( array $args = array() ): array;

	/**
	 * Count inquiries matching filters.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return int
	 */
	public function count( array $args = array() ): int;

	/**
	 * Update inquiry status.
	 *
	 * @param int    $inquiry_id Inquiry ID.
	 * @param string $status     New status slug.
	 * @return true|\WP_Error
	 */
	public function update_status( int $inquiry_id, string $status );

	/**
	 * Delete an inquiry row.
	 *
	 * @param int $inquiry_id Inquiry ID.
	 * @return true|\WP_Error
	 */
	public function delete( int $inquiry_id );
}
