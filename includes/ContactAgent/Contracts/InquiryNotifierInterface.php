<?php
/**
 * Inquiry notification contract (implementation in Phase 5).
 *
 * @package HvnlyNab\ContactAgent\Contracts
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
interface InquiryNotifierInterface {

	/**
	 * Send notifications for a stored inquiry.
	 *
	 * @param int                  $inquiry_id Inquiry ID.
	 * @param array<string, mixed> $inquiry    Inquiry row data.
	 * @param array<string, mixed> $agent      Agent data from hvnly_get_property_agent().
	 * @return bool|\WP_Error
	 */
	public function send( int $inquiry_id, array $inquiry, array $agent );
}
