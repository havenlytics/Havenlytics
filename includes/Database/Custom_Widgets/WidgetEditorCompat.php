<?php
/**
 * Block Widget Editor compatibility layer for Havenlytics widgets.
 *
 * @package HvnlyNab\Database\Custom_Widgets
 * @since   3.0.6
 */

namespace HvnlyNab\Database\Custom_Widgets;

use HvnlyNab\Database\Custom_Widgets\All_Widgets\Hvnly_Legacy_Preserved_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes widget storage and registers legacy widget types for WP 6.x editor.
 */
final class WidgetEditorCompat {

	/**
	 * Legacy widget bases with specialized preservation shims.
	 *
	 * @var array<string, string>
	 */
	private const LEGACY_WIDGET_TITLES = array(
		'hvnly_agent' => 'Legacy: Property Agent',
	);

	/**
	 * Boot compatibility hooks.
	 *
	 * @return void
	 */
	public static function boot(): void {
		add_filter( 'option_sidebars_widgets', array( __CLASS__, 'filter_sidebars_widgets' ), 1 );
		add_filter( 'pre_update_option_sidebars_widgets', array( __CLASS__, 'filter_sidebars_widgets' ), 1 );

		add_filter( 'widget_update_callback', array( __CLASS__, 'filter_widget_update_callback' ), 10, 4 );
		add_filter( 'block_categories_all', array( __CLASS__, 'register_block_category' ), 10, 2 );
		add_filter( 'get_block_type_variations', array( __CLASS__, 'filter_legacy_widget_variations' ), 10, 2 );
		add_filter( 'widget_types_to_hide_from_legacy_widget_block', array( __CLASS__, 'hide_hvnly_from_generic_widgets_category' ) );

		add_action( 'widgets_init', array( __CLASS__, 'register_option_filters' ), 0 );
		add_action( 'widgets_init', array( __CLASS__, 'register_legacy_widgets' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ), 100 );
	}

	/**
	 * Register the HAVENLYTICS category at the top of the widget block inserter sidebar.
	 *
	 * @param array<int, array<string, mixed>> $categories Block categories.
	 * @param \WP_Block_Editor_Context         $context    Editor context.
	 * @return array<int, array<string, mixed>>
	 */
	public static function register_block_category( array $categories, $context ): array {
		if ( ! is_object( $context ) || ! isset( $context->name ) ) {
			return $categories;
		}

		if ( ! in_array( (string) $context->name, array( 'core/edit-widgets', 'core/customize-widgets' ), true ) ) {
			return $categories;
		}

		$filtered = array_values(
			array_filter(
				$categories,
				static function ( $category ) {
					return is_array( $category )
						&& ( ! isset( $category['slug'] ) || Register_Widgets::WIDGET_CATEGORY_SLUG !== $category['slug'] );
				}
			)
		);

		array_unshift(
			$filtered,
			array(
				'slug'  => Register_Widgets::WIDGET_CATEGORY_SLUG,
				'title' => _x( 'HAVENLYTICS', 'Widget block inserter category', 'havenlytics' ),
				'icon'  => null,
			)
		);

		return $filtered;
	}

	/**
	 * Hide Havenlytics widgets from the generic "Widgets" inserter category.
	 *
	 * They are re-registered under HAVENLYTICS via the block editor script.
	 * Existing widget instances and sidebar assignments are unaffected.
	 *
	 * @param array<int, string> $widget_types Widget id bases to hide.
	 * @return array<int, string>
	 */
	public static function hide_hvnly_from_generic_widgets_category( array $widget_types ): array {
		$hvnly_bases = array_values(
			array_unique(
				array_merge(
					self::get_hvnly_widget_bases(),
					self::collect_referenced_widget_bases(),
					array_keys( self::LEGACY_WIDGET_TITLES )
				)
			)
		);

		foreach ( $hvnly_bases as $id_base ) {
			if ( is_string( $id_base ) && str_starts_with( $id_base, 'hvnly_' ) && ! in_array( $id_base, $widget_types, true ) ) {
				$widget_types[] = $id_base;
			}
		}

		return $widget_types;
	}

	/**
	 * Assign Havenlytics legacy widget variations to the HAVENLYTICS category (PHP fallback).
	 *
	 * @param array<int, array<string, mixed>> $variations Block variations.
	 * @param \WP_Block_Type                    $block_type Block type object.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_legacy_widget_variations( array $variations, $block_type ): array {
		if ( ! is_object( $block_type ) || empty( $block_type->name ) || 'core/legacy-widget' !== $block_type->name ) {
			return $variations;
		}

		if ( empty( $variations ) ) {
			return $variations;
		}

		$hvnly_bases = array_values(
			array_unique(
				array_merge(
					self::get_hvnly_widget_bases(),
					self::collect_referenced_widget_bases()
				)
			)
		);

		foreach ( $variations as $index => $variation ) {
			if ( ! is_array( $variation ) || empty( $variation['attributes']['idBase'] ) ) {
				continue;
			}

			$id_base = (string) $variation['attributes']['idBase'];
			if ( ! str_starts_with( $id_base, 'hvnly_' ) && ! in_array( $id_base, $hvnly_bases, true ) ) {
				continue;
			}

			$variations[ $index ]['category'] = Register_Widgets::WIDGET_CATEGORY_SLUG;
			$variations[ $index ]['scope']    = array( 'inserter' );

			if ( ! empty( $variation['title'] ) && is_string( $variation['title'] ) ) {
				$variations[ $index ]['title'] = preg_replace( '/^Havenlytics:\s*/i', '', $variation['title'] );
			}
		}

		return $variations;
	}

	/**
	 * @return void
	 */
	public static function register_option_filters(): void {
		$bases = array_merge(
			self::get_hvnly_widget_bases(),
			array_keys( self::LEGACY_WIDGET_TITLES ),
			self::collect_referenced_widget_bases()
		);

		foreach ( array_unique( $bases ) as $id_base ) {
			if ( ! is_string( $id_base ) || ! str_starts_with( $id_base, 'hvnly_' ) ) {
				continue;
			}

			add_filter( 'option_widget_' . $id_base, array( __CLASS__, 'filter_widget_option' ), 1 );
			add_filter( 'pre_update_option_widget_' . $id_base, array( __CLASS__, 'filter_widget_option' ), 1 );
		}
	}

	/**
	 * Register legacy widget classes referenced in sidebars but removed from active code.
	 *
	 * @return void
	 */
	public static function register_legacy_widgets(): void {
		$active_bases = Register_Widgets::get_widget_id_bases();
		$needed_bases = self::collect_referenced_widget_bases();

		foreach ( $needed_bases as $id_base ) {
			if ( in_array( $id_base, $active_bases, true ) ) {
				continue;
			}

			if ( ! str_starts_with( $id_base, 'hvnly_' ) ) {
				continue;
			}

			$title = self::LEGACY_WIDGET_TITLES[ $id_base ] ?? '';
			Hvnly_Legacy_Preserved_Widget::register_base( $id_base, $title );
		}
	}

	/**
	 * @param mixed $sidebars Sidebars option.
	 * @return array<string, mixed>
	 */
	public static function filter_sidebars_widgets( $sidebars ): array {
		return WidgetInstanceHelpers::sanitize_sidebars_widgets( $sidebars );
	}

	/**
	 * @param mixed $option Widget option row.
	 * @return array<int|string, mixed>
	 */
	public static function filter_widget_option( $option ): array {
		return WidgetInstanceHelpers::sanitize_widget_option( $option );
	}

	/**
	 * Ensure update() always returns a valid array for Havenlytics widgets.
	 *
	 * @param mixed                $instance     Updated instance.
	 * @param array<string, mixed> $new_instance New settings.
	 * @param array<string, mixed> $old_instance Old settings.
	 * @param \WP_Widget           $widget       Widget object.
	 * @return array<string, mixed>
	 */
	public static function filter_widget_update_callback( $instance, array $new_instance, array $old_instance, $widget ) {
		unset( $new_instance );

		if ( ! is_object( $widget ) || ! isset( $widget->id_base ) || ! is_string( $widget->id_base ) ) {
			return is_array( $instance ) ? WidgetInstanceHelpers::normalize_instance( $instance ) : array();
		}

		if ( ! str_starts_with( $widget->id_base, 'hvnly_' ) ) {
			return $instance;
		}

		if ( ! is_array( $instance ) ) {
			return WidgetInstanceHelpers::normalize_instance( $old_instance );
		}

		return WidgetInstanceHelpers::normalize_instance( $instance );
	}

	/**
	 * Enqueue block editor assets for the widgets screen.
	 *
	 * @return void
	 */
	public static function enqueue_block_editor_assets(): void {
		if ( ! self::is_widget_editor_screen() ) {
			return;
		}

		self::register_block_editor_script();
	}

	/**
	 * Enqueue legacy widget admin helpers on the widgets screens only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'widgets.php', 'customize.php' ), true ) ) {
			return;
		}

		$version = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.6';

		wp_enqueue_style(
			'hvnly-admin-widgets',
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-widgets.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'hvnly-admin-widgets',
			HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-admin-widgets.js',
			array( 'jquery' ),
			$version,
			true
		);

		self::register_block_editor_script();

		wp_enqueue_script(
			'hvnly-agent-widget',
			HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-agent-widget.js',
			array( 'jquery', 'jquery-ui-autocomplete', 'media-upload', 'media-views' ),
			$version,
			true
		);

		wp_localize_script(
			'hvnly-agent-widget',
			'hvnlyAgentWidget',
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'selectAvatar' => __( 'Select Avatar', 'havenlytics' ),
				'useAsAvatar'  => __( 'Use as avatar', 'havenlytics' ),
			)
		);
	}

	/**
	 * Register and localize the Havenlytics widget block editor script.
	 *
	 * @return void
	 */
	private static function register_block_editor_script(): void {
		$version = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.6';

		wp_enqueue_style(
			'hvnly-admin-widgets',
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-widgets.css',
			array(),
			$version
		);

		$deps = array( 'wp-hooks', 'wp-blocks', 'wp-element', 'wp-dom' );
		if ( wp_script_is( 'wp-edit-widgets', 'registered' ) ) {
			$deps[] = 'wp-edit-widgets';
		}

		wp_register_script(
			'hvnly-widget-block-editor',
			HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-widget-block-editor.js',
			$deps,
			$version,
			true
		);

		wp_localize_script(
			'hvnly-widget-block-editor',
			'hvnlyWidgetEditor',
			array(
				'categorySlug'  => Register_Widgets::WIDGET_CATEGORY_SLUG,
				'categoryTitle' => _x( 'HAVENLYTICS', 'Widget block inserter category', 'havenlytics' ),
				'iconUrl'       => HVNLYNAB_ASSETS_URL . '/admin/img/havenlytics-icon20x20.svg',
				'widgetBases'   => array_values(
					array_unique(
						array_merge(
							self::get_hvnly_widget_bases(),
							self::collect_referenced_widget_bases()
						)
					)
				),
				'widgetCatalog' => Register_Widgets::get_widget_catalog(),
			)
		);

		wp_enqueue_script( 'hvnly-widget-block-editor' );
	}

	/**
	 * Whether the current request is the widgets block editor.
	 *
	 * @return bool
	 */
	private static function is_widget_editor_screen(): bool {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && in_array( $screen->id, array( 'widgets', 'customize' ), true ) ) {
				return true;
			}
		}

		global $pagenow;

		return isset( $pagenow ) && in_array( $pagenow, array( 'widgets.php', 'customize.php' ), true );
	}

	/**
	 * @return array<int, string>
	 */
	private static function get_hvnly_widget_bases(): array {
		return Register_Widgets::get_widget_id_bases();
	}

	/**
	 * Collect widget id bases referenced in sidebars_widgets.
	 *
	 * @return array<int, string>
	 */
	private static function collect_referenced_widget_bases(): array {
		$sidebars = WidgetInstanceHelpers::sanitize_sidebars_widgets( get_option( 'sidebars_widgets', array() ) );
		$bases    = array();

		foreach ( $sidebars as $sidebar_id => $widgets ) {
			if ( in_array( $sidebar_id, array( 'array_version', 'wp_inactive_widgets' ), true ) ) {
				continue;
			}

			foreach ( $widgets as $widget_id ) {
				if ( ! is_string( $widget_id ) || ! str_contains( $widget_id, '-' ) ) {
					continue;
				}

				$base = substr( $widget_id, 0, strrpos( $widget_id, '-' ) );
				if ( '' !== $base ) {
					$bases[] = $base;
				}
			}
		}

		return array_values( array_unique( $bases ) );
	}
}
