<?php
/**
 * Cached unread inquiry counter for admin menu badges.
 *
 * @package HvnlyNab\ContactAgent
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
final class InquiryUnreadCounter {

	/**
	 * Register cache invalidation hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'hvnly_contact_agent_inquiry_created', array( self::class, 'bust' ) );
		add_action( 'hvnly_contact_agent_inquiry_status_updated', array( self::class, 'bust' ) );
		add_action( 'hvnly_contact_agent_inquiry_deleted', array( self::class, 'bust' ) );
	}

	/**
	 * Get count of unread (new) inquiries.
	 *
	 * @return int
	 */
	public static function get_count(): int {
		$cached = get_transient( ContactAgentConstants::UNREAD_COUNT_TRANSIENT );
		if ( false !== $cached ) {
			return max( 0, (int) $cached );
		}

		$repository = new InquiryRepository();
		$count      = $repository->count( array( 'status' => ContactAgentConstants::STATUS_NEW ) );

		set_transient( ContactAgentConstants::UNREAD_COUNT_TRANSIENT, $count, MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Clear cached unread count.
	 *
	 * @return void
	 */
	public static function bust(): void {
		delete_transient( ContactAgentConstants::UNREAD_COUNT_TRANSIENT );
	}

	/**
	 * Build WordPress admin menu badge HTML for a count.
	 *
	 * @param int $count Unread count.
	 * @return string
	 */
	public static function menu_badge_html( int $count ): string {
		if ( $count <= 0 ) {
			return '';
		}

		return sprintf(
			' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%1$d</span></span>',
			$count
		);
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
