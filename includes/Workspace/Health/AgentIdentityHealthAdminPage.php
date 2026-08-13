<?php
/**
 * Admin UI: Tools → Havenlytics Health → Agent Identity.
 *
 * Scanner + deterministic Repair / Ignore / Details.
 *
 * @package HvnlyNab\Workspace\Health
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Health;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
final class AgentIdentityHealthAdminPage {

	public const PAGE_SLUG = 'hvnly_health_agent_identity';

	/**
	 * @var AgentIdentityRepairService
	 */
	private $repair;

	/**
	 * @param AgentIdentityRepairService|null $repair Repair service.
	 */
	public function __construct( ?AgentIdentityRepairService $repair = null ) {
		$this->repair = $repair ? $repair : new AgentIdentityRepairService();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_hvnly_agent_identity_repair', array( $this, 'handle_repair' ) );
		add_action( 'admin_post_hvnly_agent_identity_ignore', array( $this, 'handle_ignore' ) );
		add_action( 'admin_post_hvnly_agent_identity_caps', array( $this, 'handle_caps' ) );
	}

	/**
	 * @return void
	 */
	public function register_menu(): void {
		$cap = apply_filters( 'hvnly_admin_capability', 'manage_options' );

		add_management_page(
			__( 'Havenlytics Health', 'havenlytics' ),
			__( 'Havenlytics Health', 'havenlytics' ),
			$cap,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @return void
	 */
	public function handle_repair(): void {
		$this->assert_admin();
		check_admin_referer( 'hvnly_agent_identity_repair' );

		$issue_json = isset( $_POST['issue'] ) ? wp_unslash( (string) $_POST['issue'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$issue      = json_decode( $issue_json, true );
		if ( ! is_array( $issue ) ) {
			$this->redirect_notice( 'error', __( 'Invalid issue payload.', 'havenlytics' ) );
		}

		if ( ! AgentIdentityRepairService::is_auto_repairable( $issue ) ) {
			$this->redirect_notice( 'warning', __( 'MANUAL ACTION REQUIRED — this issue is not auto-repairable.', 'havenlytics' ) );
		}

		$result = $this->repair->repair_issue( $issue );
		$this->redirect_notice(
			$result->ok() ? 'success' : ( $result->is_manual() ? 'warning' : 'error' ),
			$result->reason()
		);
	}

	/**
	 * @return void
	 */
	public function handle_ignore(): void {
		$this->assert_admin();
		check_admin_referer( 'hvnly_agent_identity_ignore' );

		$key = isset( $_POST['issue_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['issue_key'] ) ) : '';
		if ( '' === $key ) {
			$this->redirect_notice( 'error', __( 'Missing issue key.', 'havenlytics' ) );
		}
		$this->repair->ignore_issue( $key );
		$this->redirect_notice( 'success', __( 'Issue ignored for this site (scan still detects it; UI hides it).', 'havenlytics' ) );
	}

	/**
	 * @return void
	 */
	public function handle_caps(): void {
		$this->assert_admin();
		check_admin_referer( 'hvnly_agent_identity_caps' );
		$result = $this->repair->repair_capability_assignment();
		AgentIdentityRepairLog::write(
			array(
				'repair_type' => $result->repair_type(),
				'before'      => $result->to_array()['before'],
				'after'       => $result->to_array()['after'],
				'result'      => $result->status(),
				'reason'      => $result->reason(),
			)
		);
		$this->redirect_notice( $result->ok() ? 'success' : 'error', $result->reason() );
	}

	/**
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( apply_filters( 'hvnly_admin_capability', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'havenlytics' ) );
		}

		$this->render_flash();

		$run    = isset( $_GET['hvnly_run_scan'] ) && '1' === (string) $_GET['hvnly_run_scan']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$report = null;

		if ( $run ) {
			if ( ! wp_verify_nonce( $nonce, 'hvnly_agent_identity_scan' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid security token. Reload and try again.', 'havenlytics' ) . '</p></div>';
			} else {
				$report = ( new AgentIdentityIntegrityScanner() )->scan();
			}
		}

		$scan_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'           => self::PAGE_SLUG,
					'hvnly_run_scan' => '1',
				),
				admin_url( 'tools.php' )
			),
			'hvnly_agent_identity_scan'
		);

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Havenlytics Health — Agent Identity', 'havenlytics' ) . '</h1>';
		echo '<p>' . esc_html__( 'Scan is read-only. Repair runs only through AgentIdentityRepairService and only when deterministic. Ambiguous issues require MANUAL ACTION.', 'havenlytics' ) . '</p>';

		echo '<p>';
		echo '<a class="button button-primary" href="' . esc_url( $scan_url ) . '">' . esc_html__( 'Run Agent Identity Scan', 'havenlytics' ) . '</a> ';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
		echo '<input type="hidden" name="action" value="hvnly_agent_identity_caps" />';
		wp_nonce_field( 'hvnly_agent_identity_caps' );
		echo '<button type="submit" class="button">' . esc_html__( 'Ensure Agent Capabilities', 'havenlytics' ) . '</button>';
		echo '</form>';
		echo '</p>';

		if ( ! $report instanceof AgentIdentityIntegrityReport ) {
			$this->render_rules_help();
			$this->render_recent_log();
			echo '</div>';
			return;
		}

		$this->render_report( $report );
		$this->render_recent_log();
		echo '</div>';
	}

	/**
	 * @param AgentIdentityIntegrityReport $report Report.
	 * @return void
	 */
	private function render_report( AgentIdentityIntegrityReport $report ): void {
		$data    = $report->to_array();
		$ignored = $this->repair->ignored_map();
		$sev     = $data['severity'];

		echo '<hr />';
		echo '<h2>' . esc_html__( 'Identity Health Score', 'havenlytics' ) . '</h2>';
		printf( '<p style="font-size:28px;font-weight:700;margin:8px 0;">%d / 100</p>', (int) $data['health_score'] );
		printf(
			'<p><strong>%s:</strong> %s</p>',
			esc_html__( 'GO / NO-GO', 'havenlytics' ),
			esc_html( (string) $data['verdict'] )
		);
		printf(
			'<p>%s — C:%d · H:%d · M:%d · L:%d · Total:%d</p>',
			esc_html__( 'Severity counts', 'havenlytics' ),
			(int) $sev['critical'],
			(int) $sev['high'],
			(int) $sev['medium'],
			(int) $sev['low'],
			(int) $data['issue_count']
		);

		echo '<h3>' . esc_html__( 'Objects scanned', 'havenlytics' ) . '</h3><ul>';
		foreach ( (array) $data['stats'] as $key => $value ) {
			printf( '<li><code>%s</code>: %s</li>', esc_html( (string) $key ), esc_html( (string) $value ) );
		}
		echo '</ul>';

		foreach ( array( 'critical', 'high', 'medium', 'low' ) as $level ) {
			$issues = $report->issues_by_severity( $level );
			if ( ! $issues ) {
				continue;
			}
			echo '<h2>' . esc_html( ucfirst( $level ) ) . ' (' . count( $issues ) . ')</h2>';
			echo '<table class="widefat striped"><thead><tr>';
			$headers = array( 'Rule', 'Object', 'User', 'Agent', 'Property', 'Inquiry', 'Why', 'Recommended repair', 'Actions' );
			foreach ( $headers as $h ) {
				echo '<th>' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';

			foreach ( $issues as $issue ) {
				$row = $issue->to_array();
				$key = AgentIdentityRepairService::issue_key( $row );
				if ( isset( $ignored[ $key ] ) ) {
					continue;
				}
				$auto = AgentIdentityRepairService::is_auto_repairable( $row );
				echo '<tr>';
				printf( '<td><code>%s</code><br /><strong>%s</strong></td>', esc_html( $row['rule_id'] ), esc_html( $row['title'] ) );
				printf( '<td>%s</td>', esc_html( $row['object'] ) );
				printf( '<td>%s</td>', $row['user_id'] ? (int) $row['user_id'] : '—' );
				printf( '<td>%s</td>', $row['agent_id'] ? (int) $row['agent_id'] : '—' );
				printf( '<td>%s</td>', $row['property_id'] ? (int) $row['property_id'] : '—' );
				printf( '<td>%s</td>', $row['inquiry_id'] ? (int) $row['inquiry_id'] : '—' );
				printf( '<td>%s</td>', esc_html( $row['why'] ) );
				printf( '<td>%s</td>', esc_html( $row['repair'] ) );
				echo '<td style="white-space:nowrap;">';
				if ( $auto ) {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin-right:4px;">';
					echo '<input type="hidden" name="action" value="hvnly_agent_identity_repair" />';
					echo '<input type="hidden" name="issue" value="' . esc_attr( wp_json_encode( $row ) ) . '" />';
					wp_nonce_field( 'hvnly_agent_identity_repair' );
					echo '<button type="submit" class="button button-primary">' . esc_html__( 'Repair', 'havenlytics' ) . '</button>';
					echo '</form>';
				} else {
					echo '<span class="button disabled" title="' . esc_attr__( 'Ambiguous — MANUAL ACTION REQUIRED', 'havenlytics' ) . '">' . esc_html__( 'Manual', 'havenlytics' ) . '</span> ';
				}
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin-right:4px;">';
				echo '<input type="hidden" name="action" value="hvnly_agent_identity_ignore" />';
				echo '<input type="hidden" name="issue_key" value="' . esc_attr( $key ) . '" />';
				wp_nonce_field( 'hvnly_agent_identity_ignore' );
				echo '<button type="submit" class="button">' . esc_html__( 'Ignore', 'havenlytics' ) . '</button>';
				echo '</form>';
				printf(
					'<details style="display:inline-block;vertical-align:middle;"><summary class="button">%s</summary><pre style="max-width:420px;white-space:pre-wrap;background:#fff;border:1px solid #ccd0d4;padding:8px;">%s</pre></details>',
					esc_html__( 'Details', 'havenlytics' ),
					esc_html( wp_json_encode( $row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) )
				);
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}
		echo '<p><em>' . esc_html__( 'Scanned at ', 'havenlytics' ) . esc_html( (string) $data['scanned_at'] ) . '</em></p>';
	}

	/**
	 * @return void
	 */
	private function render_rules_help(): void {
		echo '<h2>' . esc_html__( 'Validation rules', 'havenlytics' ) . '</h2><ol>';
		foreach ( AgentIdentityIntegrityScanner::rules_catalog() as $rule ) {
			$repairable = AgentIdentityRepairService::is_auto_repairable(
				array( 'rule_id' => $rule['id'] )
			);
			printf(
				'<li><code>%s</code> — %s <em>(%s)</em> %s</li>',
				esc_html( $rule['id'] ),
				esc_html( $rule['label'] ),
				esc_html( $rule['severity'] ),
				$repairable
					? '<strong style="color:#007017;">[' . esc_html__( 'auto-repairable', 'havenlytics' ) . ']</strong>'
					: '<span style="color:#996800;">[' . esc_html__( 'manual only', 'havenlytics' ) . ']</span>'
			);
		}
		echo '</ol>';
	}

	/**
	 * @return void
	 */
	private function render_recent_log(): void {
		$entries = AgentIdentityRepairLog::recent( 20 );
		if ( ! $entries ) {
			return;
		}
		echo '<h2>' . esc_html__( 'Recent repair log', 'havenlytics' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Timestamp', 'Type', 'Result', 'Reason', 'Admin' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $entries as $entry ) {
			echo '<tr>';
			printf( '<td>%s</td>', esc_html( (string) ( $entry['timestamp'] ?? '' ) ) );
			printf( '<td><code>%s</code></td>', esc_html( (string) ( $entry['repair_type'] ?? '' ) ) );
			printf( '<td>%s</td>', esc_html( (string) ( $entry['result'] ?? '' ) ) );
			printf( '<td>%s</td>', esc_html( (string) ( $entry['reason'] ?? '' ) ) );
			printf( '<td>%s</td>', esc_html( (string) ( $entry['admin_id'] ?? '' ) ) );
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @return void
	 */
	private function render_flash(): void {
		if ( empty( $_GET['hvnly_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$type  = isset( $_GET['hvnly_notice_type'] ) ? sanitize_key( (string) $_GET['hvnly_notice_type'] ) : 'info'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg   = isset( $_GET['hvnly_notice'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hvnly_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$class = 'notice-info';
		if ( 'success' === $type ) {
			$class = 'notice-success';
		} elseif ( 'warning' === $type ) {
			$class = 'notice-warning';
		} elseif ( 'error' === $type ) {
			$class = 'notice-error';
		}
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * @return void
	 */
	private function assert_admin(): void {
		if ( ! current_user_can( apply_filters( 'hvnly_admin_capability', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'Forbidden.', 'havenlytics' ) );
		}
	}

	/**
	 * @param string $type Notice type.
	 * @param string $message Message.
	 * @return void
	 */
	private function redirect_notice( string $type, string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => self::PAGE_SLUG,
					'hvnly_run_scan'    => '1',
					'_wpnonce'          => wp_create_nonce( 'hvnly_agent_identity_scan' ),
					'hvnly_notice_type' => $type,
					'hvnly_notice'      => $message,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}
}
