<?php
/**
 * WP-CLI: wp hvnly identity-scan (read-only).
 *
 * @package HvnlyNab\Workspace\Health
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Registers CLI command when WP_CLI is available.
 *
 * @since 3.2.0
 */
final class AgentIdentityIntegrityCli {

	/**
	 * @return void
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command(
			'hvnly identity-scan',
			static function ( $args, $assoc_args ) {
				$scanner = new AgentIdentityIntegrityScanner();
				$report  = $scanner->scan();
				$data    = $report->to_array();

				\WP_CLI::log( 'Identity Health Score: ' . $data['health_score'] . ' / 100' );
				\WP_CLI::log( 'Verdict: ' . $data['verdict'] );
				\WP_CLI::log(
					sprintf(
						'Issues — critical:%d high:%d medium:%d low:%d total:%d',
						$data['severity']['critical'],
						$data['severity']['high'],
						$data['severity']['medium'],
						$data['severity']['low'],
						$data['issue_count']
					)
				);

				$format = isset( $assoc_args['format'] ) ? (string) $assoc_args['format'] : 'table';
				if ( 'json' === $format ) {
					\WP_CLI::log( wp_json_encode( $data, JSON_PRETTY_PRINT ) );
					return;
				}

				$rows = array();
				foreach ( $data['issues'] as $issue ) {
					$rows[] = array(
						'severity'    => $issue['severity'],
						'rule'        => $issue['rule_id'],
						'object'      => $issue['object'],
						'user_id'     => $issue['user_id'],
						'agent_id'    => $issue['agent_id'],
						'property_id' => $issue['property_id'],
						'inquiry_id'  => $issue['inquiry_id'],
						'why'         => $issue['why'],
					);
				}
				if ( $rows ) {
					\WP_CLI\Utils\format_items( 'table', $rows, array( 'severity', 'rule', 'object', 'user_id', 'agent_id', 'property_id', 'inquiry_id', 'why' ) );
				} else {
					\WP_CLI::success( 'No identity issues detected.' );
				}
			}
		);
	}
}
