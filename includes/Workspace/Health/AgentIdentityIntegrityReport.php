<?php
/**
 * Agent Identity integrity scan report (read-only).
 *
 * @package HvnlyNab\Workspace\Health
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Aggregated Health report + score.
 *
 * @since 3.2.0
 */
final class AgentIdentityIntegrityReport {

	/**
	 * @var AgentIdentityIssue[]
	 */
	private $issues = array();

	/**
	 * @var array<string, int>
	 */
	private $stats = array();

	/**
	 * @var string
	 */
	private $scanned_at = '';

	/**
	 * @param AgentIdentityIssue[]  $issues Findings.
	 * @param array<string, int>    $stats  Scan counters.
	 * @param string                $scanned_at GMT datetime.
	 */
	public function __construct( array $issues, array $stats = array(), string $scanned_at = '' ) {
		$this->issues     = array_values( $issues );
		$this->stats      = $stats;
		$this->scanned_at = $scanned_at !== '' ? $scanned_at : gmdate( 'c' );
	}

	/**
	 * @param AgentIdentityIssue $issue Finding.
	 * @return void
	 */
	public function add( AgentIdentityIssue $issue ): void {
		$this->issues[] = $issue;
	}

	/**
	 * @return AgentIdentityIssue[]
	 */
	public function issues(): array {
		return $this->issues;
	}

	/**
	 * @param string $severity Severity slug.
	 * @return AgentIdentityIssue[]
	 */
	public function issues_by_severity( string $severity ): array {
		$out = array();
		foreach ( $this->issues as $issue ) {
			if ( $issue->severity() === $severity ) {
				$out[] = $issue;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, int>
	 */
	public function counts_by_severity(): array {
		$counts = array(
			AgentIdentityIssue::SEVERITY_CRITICAL => 0,
			AgentIdentityIssue::SEVERITY_HIGH     => 0,
			AgentIdentityIssue::SEVERITY_MEDIUM   => 0,
			AgentIdentityIssue::SEVERITY_LOW      => 0,
		);
		foreach ( $this->issues as $issue ) {
			$sev = $issue->severity();
			if ( isset( $counts[ $sev ] ) ) {
				++$counts[ $sev ];
			}
		}
		return $counts;
	}

	/**
	 * Identity Health Score 0–100 (penalties capped).
	 *
	 * @return int
	 */
	public function health_score(): int {
		$counts = $this->counts_by_severity();
		$score  = 100;
		$score -= min( 60, $counts[ AgentIdentityIssue::SEVERITY_CRITICAL ] * 12 );
		$score -= min( 30, $counts[ AgentIdentityIssue::SEVERITY_HIGH ] * 5 );
		$score -= min( 15, $counts[ AgentIdentityIssue::SEVERITY_MEDIUM ] * 2 );
		$score -= min( 8, $counts[ AgentIdentityIssue::SEVERITY_LOW ] * 1 );
		return max( 0, (int) $score );
	}

	/**
	 * @return string go|nogo
	 */
	public function verdict(): string {
		$counts = $this->counts_by_severity();
		if ( $counts[ AgentIdentityIssue::SEVERITY_CRITICAL ] > 0 ) {
			return 'nogo';
		}
		if ( $counts[ AgentIdentityIssue::SEVERITY_HIGH ] >= 5 ) {
			return 'nogo';
		}
		return 'go';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$issues = array();
		foreach ( $this->issues as $issue ) {
			$issues[] = $issue->to_array();
		}

		return array(
			'health_score' => $this->health_score(),
			'verdict'      => strtoupper( $this->verdict() ),
			'scanned_at'   => $this->scanned_at,
			'stats'        => $this->stats,
			'severity'     => $this->counts_by_severity(),
			'issue_count'  => count( $this->issues ),
			'issues'       => $issues,
		);
	}
}
