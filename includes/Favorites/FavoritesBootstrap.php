<?php
/**
 * Favorites module bootstrap.
 *
 * @package HvnlyNab\Favorites
 * @since   3.4.0
 */

namespace HvnlyNab\Favorites;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the saved-properties feature.
 *
 * Deliberately independent of {@see \HvnlyNab\Workspace\WorkspaceBootstrap}:
 * favorites are a public front-end feature that must keep working when an
 * administrator disables the Agent Workspace. Only the Saved Properties
 * *page* lives inside the Workspace.
 *
 * @since 3.4.0
 */
final class FavoritesBootstrap {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * @var FavoritesService|null
	 */
	private $service = null;

	/**
	 * @var FavoritesController|null
	 */
	private $controller = null;

	/**
	 * @var FavoritesAssets|null
	 */
	private $assets = null;

	/**
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return void
	 */
	public function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		$this->service    = new FavoritesService();
		$this->controller = new FavoritesController( $this->service );
		$this->assets     = new FavoritesAssets( $this->service );

		$this->controller->register();
		$this->assets->register_hooks();

		// Favorites routes share the Workspace namespace but are NOT a
		// Workspace surface — exempt them from the Workspace kill switch so
		// disabling the Agent Workspace does not break the public archive.
		add_filter( 'hvnly_workspace_rest_exempt_route', array( $this, 'exempt_favorites_routes' ), 10, 2 );

		// Referential integrity: favorites must not outlive their target.
		add_action( 'deleted_post', array( $this, 'on_post_deleted' ), 10, 2 );
		add_action( 'deleted_user', array( $this, 'on_user_deleted' ), 10 );

		/**
		 * Fires once the favorites module is wired.
		 *
		 * @since 3.4.0
		 *
		 * @param FavoritesBootstrap $bootstrap Module bootstrap.
		 */
		do_action( 'hvnly_favorites_init', $this );
	}

	/**
	 * Ensure the storage table exists (activation + migration safety net).
	 *
	 * @return bool
	 */
	public static function install(): bool {
		return FavoritesSchema::create_table();
	}

	/**
	 * @return FavoritesService
	 */
	public function service(): FavoritesService {
		if ( null === $this->service ) {
			$this->service = new FavoritesService();
		}
		return $this->service;
	}

	/**
	 * Mark `/favorites*` as exempt from the Workspace availability gate.
	 *
	 * @param bool   $exempt Current exemption.
	 * @param string $route  REST route being dispatched.
	 * @return bool
	 */
	public function exempt_favorites_routes( $exempt, $route ): bool {
		if ( $exempt ) {
			return true;
		}

		$route  = (string) $route;
		$prefix = '/' . trim( \HvnlyNab\Workspace\WorkspaceConstants::REST_NAMESPACE, '/' )
			. '/' . FavoritesController::ROUTE_BASE;

		return $route === $prefix || 0 === strpos( $route, $prefix . '/' );
	}

	/**
	 * Drop favorites pointing at a permanently deleted property.
	 *
	 * @param int           $post_id Post id.
	 * @param \WP_Post|null $post    Post object (WP 5.5+).
	 * @return void
	 */
	public function on_post_deleted( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		// `deleted_post` fires for every post type; only act on properties.
		if ( $post instanceof \WP_Post && FavoritesService::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( ! FavoritesSchema::table_exists() ) {
			return;
		}

		$this->service()->repository()->delete_for_property( $post_id );
	}

	/**
	 * Drop favorites belonging to a deleted user.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public function on_user_deleted( $user_id ): void {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! FavoritesSchema::table_exists() ) {
			return;
		}

		$this->service()->repository()->delete_for_user( $user_id );
	}
}
