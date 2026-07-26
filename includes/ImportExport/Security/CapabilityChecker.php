<?php
/**
 * Import / Export capability gate (no transport wiring).
 *
 * @package HvnlyNab\ImportExport\Security
 * @since   3.6.0
 */

namespace HvnlyNab\ImportExport\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Central capability name for HPTP admin operations.
 *
 * @since 3.6.0
 */
final class CapabilityChecker {

	/**
	 * WordPress capability required for Import / Export (3.6.0).
	 *
	 * Matches the approved functional specification (administrators).
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Whether the current user may manage Import / Export.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage(): bool {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * Whether a specific user may manage Import / Export.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_can_manage( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		return user_can( $user_id, self::CAPABILITY );
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
