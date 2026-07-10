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
	 * Keep the Agents menu highlighted on agency taxonomy screens.
	 *
	 * @param string $parent_file Parent menu file.
	 * @return string
	 */
	public function highlight_parent_menu( string $parent_file ): string {
		if ( $this->is_agency_taxonomy_screen() ) {
			return self::MENU_SLUG;
		}

		return $parent_file;
	}

	/**
	 * Highlight the Agencies submenu on taxonomy screens.
	 *
	 * @param string|null $submenu_file Submenu file.
	 * @param string      $parent_file  Parent menu file.
	 * @return string|null
	 */
	public function highlight_submenu( $submenu_file, string $parent_file ) {
		if ( ! $this->is_agency_taxonomy_screen() ) {
			return $submenu_file;
		}

		return self::AGENCIES_SUBMENU_SLUG;
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
