<?php
/**
 * Default single-property sidebar widget setup.
 *
 * @package HvnlyNab\Setup
 * @since   3.0.2
 */

namespace HvnlyNab\Setup;

use HvnlyNab\Agent\PropertyAgentWidgetRenderer;

defined( 'ABSPATH' ) || exit;

/**
 * Seeds the Property Agent widget into the Havenlytics property sidebar.
 *
 * @since 3.0.2
 */
final class SidebarWidgetInstaller {

	public const OPTION_FLAG = 'hvnly_default_property_agent_widget_installed';
	public const SIDEBAR_ID  = 'hvnly_single_property_sidebar_widgets_area';
	public const WIDGET_ID   = 'hvnly_property_agent';

	/**
	 * Register default widget on first install if the sidebar area is empty.
	 *
	 * @return void
	 */
	public static function maybe_install(): void {
		if ( get_option( self::OPTION_FLAG ) ) {
			return;
		}

		if ( ! self::is_sidebar_empty() ) {
			update_option( self::OPTION_FLAG, 1 );
			return;
		}

		self::install_default_widget();
		update_option( self::OPTION_FLAG, 1 );
	}

	/**
	 * @return bool
	 */
	private static function is_sidebar_empty(): bool {
		$sidebars = get_option( 'sidebars_widgets', array() );

		if ( ! is_array( $sidebars ) || empty( $sidebars[ self::SIDEBAR_ID ] ) ) {
			return true;
		}

		$widgets = array_filter(
			(array) $sidebars[ self::SIDEBAR_ID ],
			static function ( $widget_id ) {
				return is_string( $widget_id ) && 'wp_inactive_widgets' !== $widget_id;
			}
		);

		return empty( $widgets );
	}

	/**
	 * @return void
	 */
	private static function install_default_widget(): void {
		$option_key = 'widget_' . self::WIDGET_ID;
		$instances  = get_option( $option_key, array() );

		if ( ! is_array( $instances ) ) {
			$instances = array();
		}

		$instance_id = 1;
		while ( isset( $instances[ $instance_id ] ) ) {
			++$instance_id;
		}

		$defaults          = PropertyAgentWidgetRenderer::get_defaults();
		$defaults['title'] = __( 'Contact Agent', 'havenlytics' );

		$instances[ $instance_id ] = $defaults;
		$instances['_multiwidget'] = 1;

		update_option( $option_key, $instances );

		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( ! is_array( $sidebars ) ) {
			$sidebars = array();
		}

		if ( empty( $sidebars[ self::SIDEBAR_ID ] ) || ! is_array( $sidebars[ self::SIDEBAR_ID ] ) ) {
			$sidebars[ self::SIDEBAR_ID ] = array();
		}

		$sidebars[ self::SIDEBAR_ID ][] = self::WIDGET_ID . '-' . $instance_id;

		if ( ! isset( $sidebars['array_version'] ) ) {
			$sidebars['array_version'] = 3;
		}

		update_option( 'sidebars_widgets', $sidebars );
	}
}

add_action( 'init', array( SidebarWidgetInstaller::class, 'maybe_install' ), 99 );
