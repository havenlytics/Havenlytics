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

	/** @var int Menu position — immediately after Havenlytics (position 2). */
	private const MENU_POSITION = 3;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ), 9 );
		add_filter( 'parent_file', array( $this, 'highlight_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ), 10, 2 );
	}

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
			'edit-tags.php?taxonomy=' . AgentConstants::TAXONOMY_AGENCY . '&post_type=' . AgentConstants::POST_TYPE
		);
	}

	/**
	 * Keep the Agents menu highlighted on agency taxonomy screens.
	 *
	 * @param string $parent_file Parent menu file.
	 * @return string
	 */
	public function highlight_parent_menu( string $parent_file ): string {
		global $current_screen;

		if (
			isset( $current_screen->taxonomy )
			&& AgentConstants::TAXONOMY_AGENCY === $current_screen->taxonomy
		) {
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
		global $current_screen;

		if (
			self::MENU_SLUG === $parent_file
			&& isset( $current_screen->taxonomy )
			&& AgentConstants::TAXONOMY_AGENCY === $current_screen->taxonomy
		) {
			return 'edit-tags.php?taxonomy=' . AgentConstants::TAXONOMY_AGENCY . '&post_type=' . AgentConstants::POST_TYPE;
		}

		return $submenu_file;
	}
}
