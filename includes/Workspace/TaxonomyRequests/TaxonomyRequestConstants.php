<?php
/**
 * Taxonomy request workflow constants.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests;

defined( 'ABSPATH' ) || exit;

final class TaxonomyRequestConstants {

	public const REQUESTS_TABLE = 'hvnly_taxonomy_requests';
	public const LOGS_TABLE     = 'hvnly_taxonomy_request_logs';

	public const STATUS_PENDING    = 'pending';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_APPROVED   = 'approved';
	public const STATUS_REJECTED   = 'rejected';
	public const STATUS_MERGED     = 'merged';
	public const STATUS_DELETED    = 'deleted';

	public const POLICY_APPROVAL   = 'approval';
	public const POLICY_AUTO       = 'auto';
	public const POLICY_ADMIN_ONLY = 'admin_only';

	public const MAX_NAME_LENGTH = 191;
	public const MAX_TEXT_LENGTH = 2000;

	/**
	 * @return string[]
	 */
	public static function statuses(): array {
		return array(
			self::STATUS_PENDING,
			self::STATUS_PROCESSING,
			self::STATUS_APPROVED,
			self::STATUS_REJECTED,
			self::STATUS_MERGED,
			self::STATUS_DELETED,
		);
	}

	/**
	 * @param string $status Status.
	 * @return bool
	 */
	public static function is_terminal( string $status ): bool {
		return in_array(
			$status,
			array( self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_MERGED, self::STATUS_DELETED ),
			true
		);
	}

	private function __construct() {}
}
