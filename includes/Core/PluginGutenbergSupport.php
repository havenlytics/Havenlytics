<?php
/**
 * Gutenberg editor toggle for Havenlytics custom post types.
 *
 * @package HvnlyNab\Core
 * @since   3.0.2
 */

namespace HvnlyNab\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Respects Misc Settings → Enable Gutenberg for all plugin CPTs (default: off).
 *
 * Note: CPT REST exposure (`show_in_rest`) is independent of this setting so
 * block Inspector pickers can use /wp/v2/hvnly_property and /wp/v2/hvnly_agent.
 * This class only gates the block *editor* for those post types.
 *
 * @since 3.0.2
 */
final class PluginGutenbergSupport {

	/** @var bool */
	private static $registered = false;

	/**
	 * Register the block editor filter once.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'filter_block_editor' ), 10, 2 );
		self::$registered = true;
	}

	/**
	 * Post types controlled by Havenlytics Misc Settings.
	 *
	 * @return string[]
	 */
	public static function managed_post_types(): array {
		$types = array(
			'hvnly_property',
			'hvnly_agent',
		);

		/**
		 * Filter Havenlytics post types that use the Gutenberg Misc setting.
		 *
		 * @since 3.0.2
		 *
		 * @param string[] $types Post type slugs.
		 */
		return apply_filters( 'hvnly_gutenberg_managed_post_types', $types );
	}

	/**
	 * Whether Gutenberg is enabled for plugin post types.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		if ( class_exists( SettingsManager::class ) ) {
			$general = SettingsManager::get_instance()->get_general_settings();
			if ( isset( $general['hvnly_EnabledGutenbergEditor'] ) ) {
				return ! empty( $general['hvnly_EnabledGutenbergEditor'] );
			}
		}

		$settings = get_option( 'hvnly_plugin_settings', array() );
		if ( isset( $settings['general']['hvnly_EnabledGutenbergEditor'] ) ) {
			return ! empty( $settings['general']['hvnly_EnabledGutenbergEditor'] );
		}

		return false;
	}

	/**
	 * @param bool   $use_block_editor Current value.
	 * @param string $post_type        Post type slug.
	 * @return bool
	 */
	public static function filter_block_editor( $use_block_editor, $post_type ): bool {
		if ( ! in_array( $post_type, self::managed_post_types(), true ) ) {
			return (bool) $use_block_editor;
		}

		return self::is_enabled();
	}

	/**
	 * Prevent instantiation.
	 */
	private function __construct() {}
}
