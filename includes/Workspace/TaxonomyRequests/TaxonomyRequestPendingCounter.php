<?php
/**
 * Cached pending taxonomy request counter for the admin menu badge.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests
 * @since   3.4.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the per-submission administrator email (removed in 3.4.0): the
 * pending total now surfaces as a Comments-style bubble on the Taxonomy
 * Requests admin menu instead of one inbox message per request.
 *
 * Same shape as {@see \HvnlyNab\ContactAgent\InquiryUnreadCounter}: a
 * one-minute transient in front of the status-filtered COUNT, busted by the
 * existing status-changed action — no polling, no schema changes.
 *
 * @since 3.4.0
 */
final class TaxonomyRequestPendingCounter {

	/**
	 * Transient holding the cached pending count.
	 *
	 * @var string
	 */
	public const PENDING_COUNT_TRANSIENT = 'hvnly_taxonomy_request_pending_count';

	/**
	 * Register cache invalidation hooks.
	 *
	 * `hvnly_taxonomy_request_status_changed` fires on submission (→ pending)
	 * and on every approve/reject/merge/delete transition, so the badge
	 * increments and decrements without any extra bookkeeping.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'hvnly_taxonomy_request_status_changed', array( self::class, 'bust' ) );
	}

	/**
	 * Count of pending taxonomy requests.
	 *
	 * @return int
	 */
	public static function get_count(): int {
		$cached = get_transient( self::PENDING_COUNT_TRANSIENT );
		if ( false !== $cached ) {
			return max( 0, (int) $cached );
		}

		$repository = new TaxonomyRequestRepository();
		$count      = $repository->count( array( 'status' => TaxonomyRequestConstants::STATUS_PENDING ) );

		set_transient( self::PENDING_COUNT_TRANSIENT, $count, MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Clear the cached pending count.
	 *
	 * @return void
	 */
	public static function bust(): void {
		delete_transient( self::PENDING_COUNT_TRANSIENT );
	}

	/**
	 * Build WordPress admin menu badge HTML for a count.
	 *
	 * @param int $count Pending count.
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
