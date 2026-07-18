<?php
/**
 * Primes portal capabilities and registers the WordPress Agent role.
 *
 * @package HvnlyNab\Workspace\Auth
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace\Auth;

defined( 'ABSPATH' ) || exit;

/**
 * Capability + role registrar.
 *
 * @since 3.2.0
 */
final class CapabilityRegistrar {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'ensure_roles_and_caps' ), 5 );
	}

	/**
	 * Ensure administrator caps + Agent role exist.
	 *
	 * @return void
	 */
	public function ensure_roles_and_caps(): void {
		$this->ensure_administrator_caps();
		$this->ensure_agent_role();
	}

	/**
	 * Ensure administrator has all portal caps.
	 *
	 * @return void
	 */
	public function ensure_administrator_caps(): void {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		foreach ( PortalCapabilities::all() as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Create / sync the WordPress Agent role (slug hvnly_agent, label Agent).
	 *
	 * @return void
	 */
	public function ensure_agent_role(): void {
		$slug = PortalCapabilities::WP_ROLE_AGENT;
		$role = get_role( $slug );

		if ( ! $role ) {
			add_role(
				$slug,
				__( 'Agent', 'havenlytics' ),
				array( 'read' => true )
			);
			$role = get_role( $slug );
		}

		if ( ! $role ) {
			return;
		}

		foreach ( PortalCapabilities::agent_role_caps() as $cap ) {
			if ( ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}
}
