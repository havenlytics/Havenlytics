<?php
/**
 * Agents top-level admin menu.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a standalone Agents menu directly below Havenlytics.
 *
 * @since 3.0.2
 */
final class AgentAdminMenu {

	/** @var string Top-level menu slug. */
	public const MENU_SLUG = 'edit.php?post_type=hvnly_agent';

    /** @var int Menu position — grouped before WordPress Posts (5). */
    private const MENU_POSITION = 4;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ), 9 );
		add_filter( 'parent_file', array( $this, 'highlight_parent_menu' ), 99 );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ), 99, 2 );
	}

	/** @var string Agencies submenu slug (matches WordPress core submenu_file on edit-tags.php). */
	private const AGENCIES_SUBMENU_SLUG = 'edit-tags.php?taxonomy=hvnly_agent_agency';

	/**
	 * @return void
	 */
	public function register_menus(): void {
		$list_cap   = 'edit_posts';
		$agency_cap = 'manage_categories';
		$menu_slug  = self::MENU_SLUG;

		add_menu_page(
			esc_html__( 'Agents', 'havenlytics' ),
			esc_html__( 'Agents', 'havenlytics' ),
			$list_cap,
			$menu_slug,
			'',
			'dashicons-businessman',
			self::MENU_POSITION
		);

		add_submenu_page(
			$menu_slug,
			esc_html__( 'All Agents', 'havenlytics' ),
			esc_html__( 'All Agents', 'havenlytics' ),
			$list_cap,
			$menu_slug
		);

		add_submenu_page(
			$menu_slug,
			esc_html__( 'Add New Agent', 'havenlytics' ),
			esc_html__( 'Add New Agent', 'havenlytics' ),
			$list_cap,
			'post-new.php?post_type=' . AgentConstants::POST_TYPE
		);

		add_submenu_page(
			$menu_slug,
			esc_html__( 'Agencies', 'havenlytics' ),
			esc_html__( 'Agencies', 'havenlytics' ),
			$agency_cap,
			self::AGENCIES_SUBMENU_SLUG
		);
	}

	/**
	 * Keep the Agents menu highlighted on all agent CPT and agency taxonomy screens.
	 *
	 * @param string $parent_file Parent menu file.
	 * @return string
	 */
	public function highlight_parent_menu( string $parent_file ): string {
		if ( $this->is_agency_taxonomy_screen() || $this->is_agent_post_screen() ) {
			return self::MENU_SLUG;
		}

		return $parent_file;
	}

	/**
	 * Highlight the correct Agents submenu (All Agents / Add New / Agencies).
	 *
	 * @param string|null $submenu_file Submenu file.
	 * @param string      $parent_file  Parent menu file.
	 * @return string|null
	 */
	public function highlight_submenu( $submenu_file, string $parent_file ) {
		unset( $parent_file );

		if ( $this->is_agency_taxonomy_screen() ) {
			return self::AGENCIES_SUBMENU_SLUG;
		}

		if ( ! $this->is_agent_post_screen() ) {
			return $submenu_file;
		}

		global $pagenow;

		if ( 'post-new.php' === $pagenow ) {
			return 'post-new.php?post_type=' . AgentConstants::POST_TYPE;
		}

		// List + edit existing agent → All Agents.
		return self::MENU_SLUG;
	}

	/**
	 * Detect Agents list / edit / add-new admin screens.
	 *
	 * @return bool
	 */
	private function is_agent_post_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && AgentConstants::POST_TYPE === $screen->post_type ) {
			return true;
		}

		global $pagenow;
		if ( ! in_array( $pagenow, array( 'edit.php', 'post.php', 'post-new.php' ), true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only menu routing.
		if ( isset( $_GET['post_type'] ) && AgentConstants::POST_TYPE === sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) ) {
			return true;
		}

		// post.php?post={id}&action=edit has no post_type query arg.
		if ( 'post.php' === $pagenow ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only menu routing.
			$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
			if ( $post_id > 0 && AgentConstants::POST_TYPE === get_post_type( $post_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect the Agencies taxonomy admin screen reliably for menu highlighting.
	 *
	 * @return bool
	 */
	private function is_agency_taxonomy_screen(): bool {
		global $pagenow;

		if ( ! in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only menu routing.
		if ( ! isset( $_GET['taxonomy'] ) ) {
			return false;
		}

		return AgentConstants::TAXONOMY_AGENCY === sanitize_key( wp_unslash( (string) $_GET['taxonomy'] ) );
	}
}
