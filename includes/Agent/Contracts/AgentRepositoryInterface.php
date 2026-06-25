<?php
/**
 * Agent repository contract.
 *
 * @package HvnlyNab\Agent\Contracts
 * @since   3.0.2
 */

namespace HvnlyNab\Agent\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * @since 3.0.2
 */
interface AgentRepositoryInterface {

	/**
	 * Get a normalized agent profile.
	 *
	 * @param int $agent_id Agent post ID.
	 * @return array<string, mixed>|null
	 */
	public function get( int $agent_id ): ?array;

	/**
	 * Whether the post ID is a published agent.
	 *
	 * @param int $agent_id Agent post ID.
	 * @return bool
	 */
	public function is_valid_agent( int $agent_id ): bool;

	/**
	 * List agent profiles.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_agents( array $args = array() ): array;
}
