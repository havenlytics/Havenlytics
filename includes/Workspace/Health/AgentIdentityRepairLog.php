<?php
/**
 * Append-only Agent Identity repair audit log (no identity mutations).
 *
 * @package HvnlyNab\Workspace\Health
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Stores repair attempts for admin review.
 *
 * @since 3.2.0
 */
final class AgentIdentityRepairLog {

	public const OPTION_KEY = 'hvnly_agent_identity_repair_log';

	public const MAX_ENTRIES = 200;

	/**
	 * @param array<string, mixed> $entry Log entry.
	 * @return void
	 */
	public static function write( array $entry ): void {
		$entry = array_merge(
			array(
				'timestamp'   => gmdate( 'c' ),
				'repair_type' => '',
				'before'      => array(),
				'after'       => array(),
				'result'      => '',
				'reason'      => '',
				'admin_id'    => get_current_user_id(),
				'issue_key'   => '',
			),
			$entry
		);

		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, self::MAX_ENTRIES );
		update_option( self::OPTION_KEY, $log, false );
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function recent( int $limit = 50 ): array {
		$log = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $log ) ) {
			return array();
		}
		return array_slice( $log, 0, max( 1, $limit ) );
	}
}
