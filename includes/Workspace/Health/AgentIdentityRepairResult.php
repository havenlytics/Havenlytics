<?php
/**
 * Result of a single Agent Identity repair attempt.
 *
 * @package HvnlyNab\Workspace\Health
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Health;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.2.0
 */
final class AgentIdentityRepairResult {

	public const STATUS_SUCCESS         = 'success';
	public const STATUS_FAILED          = 'failed';
	public const STATUS_MANUAL_REQUIRED = 'manual_required';
	public const STATUS_SKIPPED         = 'skipped';
	public const STATUS_ROLLED_BACK     = 'rolled_back';

	/**
	 * @var string
	 */
	private $status;

	/**
	 * @var string
	 */
	private $repair_type;

	/**
	 * @var string
	 */
	private $reason;

	/**
	 * @var array<string, mixed>
	 */
	private $before;

	/**
	 * @var array<string, mixed>
	 */
	private $after;

	/**
	 * @param string               $status      Status slug.
	 * @param string               $repair_type Repair type.
	 * @param string               $reason      Human reason.
	 * @param array<string, mixed> $before      Snapshot before.
	 * @param array<string, mixed> $after       Snapshot after.
	 */
	public function __construct(
		string $status,
		string $repair_type,
		string $reason,
		array $before = array(),
		array $after = array()
	) {
		$this->status      = $status;
		$this->repair_type = $repair_type;
		$this->reason      = $reason;
		$this->before      = $before;
		$this->after       = $after;
	}

	/**
	 * @return bool
	 */
	public function ok(): bool {
		return self::STATUS_SUCCESS === $this->status;
	}

	/**
	 * @return bool
	 */
	public function is_manual(): bool {
		return self::STATUS_MANUAL_REQUIRED === $this->status;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'status'      => $this->status,
			'repair_type' => $this->repair_type,
			'reason'      => $this->reason,
			'before'      => $this->before,
			'after'       => $this->after,
		);
	}

	/**
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * @return string
	 */
	public function repair_type(): string {
		return $this->repair_type;
	}
}
