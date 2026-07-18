<?php
/**
 * Single Agent Identity integrity finding (read-only).
 *
 * @package HvnlyNab\Workspace\Health
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Health;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable issue DTO for Agent Identity Health reports.
 *
 * @since 3.2.0
 */
final class AgentIdentityIssue {

	public const SEVERITY_CRITICAL = 'critical';
	public const SEVERITY_HIGH     = 'high';
	public const SEVERITY_MEDIUM   = 'medium';
	public const SEVERITY_LOW      = 'low';

	/**
	 * @var string
	 */
	private $rule_id;

	/**
	 * @var string
	 */
	private $severity;

	/**
	 * @var string
	 */
	private $title;

	/**
	 * @var string
	 */
	private $why;

	/**
	 * @var string
	 */
	private $repair;

	/**
	 * @var string
	 */
	private $file;

	/**
	 * @var string
	 */
	private $object;

	/**
	 * @var int
	 */
	private $user_id;

	/**
	 * @var int
	 */
	private $agent_id;

	/**
	 * @var int
	 */
	private $property_id;

	/**
	 * @var int
	 */
	private $inquiry_id;

	/**
	 * @var int
	 */
	private $notification_id;

	/**
	 * @param array<string, mixed> $args Issue fields.
	 */
	public function __construct( array $args ) {
		$this->rule_id         = (string) ( $args['rule_id'] ?? '' );
		$this->severity        = (string) ( $args['severity'] ?? self::SEVERITY_MEDIUM );
		$this->title           = (string) ( $args['title'] ?? '' );
		$this->why             = (string) ( $args['why'] ?? '' );
		$this->repair          = (string) ( $args['repair'] ?? '' );
		$this->file            = (string) ( $args['file'] ?? '' );
		$this->object          = (string) ( $args['object'] ?? '' );
		$this->user_id         = absint( $args['user_id'] ?? 0 );
		$this->agent_id        = absint( $args['agent_id'] ?? 0 );
		$this->property_id     = absint( $args['property_id'] ?? 0 );
		$this->inquiry_id      = absint( $args['inquiry_id'] ?? 0 );
		$this->notification_id = absint( $args['notification_id'] ?? 0 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'rule_id'         => $this->rule_id,
			'severity'        => $this->severity,
			'title'           => $this->title,
			'why'             => $this->why,
			'repair'          => $this->repair,
			'file'            => $this->file,
			'object'          => $this->object,
			'user_id'         => $this->user_id,
			'agent_id'        => $this->agent_id,
			'property_id'     => $this->property_id,
			'inquiry_id'      => $this->inquiry_id,
			'notification_id' => $this->notification_id,
		);
	}

	/**
	 * @return string
	 */
	public function severity(): string {
		return $this->severity;
	}

	/**
	 * @return string
	 */
	public function rule_id(): string {
		return $this->rule_id;
	}
}
