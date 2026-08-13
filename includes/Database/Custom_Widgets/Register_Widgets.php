<?php
/**
 * Register Widgets
 *
 * @package HvnlyNab\Database\Custom_Widgets
 */

namespace HvnlyNab\Database\Custom_Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Register_Widgets
 */
class Register_Widgets {

	/**
	 * Block inserter category slug for Havenlytics widgets.
	 */
	public const WIDGET_CATEGORY_SLUG = 'havenlytics';

	/**
	 * Block inserter category label for Havenlytics widgets.
	 */
	public const WIDGET_CATEGORY_TITLE = 'HAVENLYTICS';

	/**
	 * Constructor.
	 */
	public function __construct() {
		WidgetEditorCompat::boot();
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
	}

	/**
	 * Registered Havenlytics widget id bases.
	 *
	 * @return array<int, string>
	 */
	public static function get_widget_id_bases(): array {
		$bases = array();

		foreach ( self::get_widgets() as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$widget = new $class();
			if ( $widget instanceof \WP_Widget && ! empty( $widget->id_base ) ) {
				$bases[] = (string) $widget->id_base;
			}
		}

		return array_values( array_unique( $bases ) );
	}

	/**
	 * Havenlytics widget catalog for the block inserter (id_base => title).
	 *
	 * @return array<string, string>
	 */
	public static function get_widget_catalog(): array {
		$catalog = array();

		foreach ( self::get_widgets() as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$widget = new $class();
			if ( $widget instanceof \WP_Widget && ! empty( $widget->id_base ) ) {
				$catalog[ (string) $widget->id_base ] = (string) $widget->name;
			}
		}

		return $catalog;
	}

	/**
	 * Get sidebar definitions.
	 *
	 * @return array
	 */
	public static function register_sidebar() {
		return array(
			array(
				'name'          => esc_html__( 'Havenlytics - Single Property Sidebar', 'havenlytics' ),
				'id'            => 'hvnly_single_property_sidebar_widgets_area',
				'description'   => esc_html__( 'Add widgets for the sidebar on single property page.', 'havenlytics' ),
				'before_widget' => '<div id="%1$s" class="widget hvnly-widget hvnly-property-single__widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h4 class="hvnly-property-single__widget-title">',
				'after_title'   => '</h4>',
			),

		);
	}

	/**
	 * Get widget classes.
	 *
	 * @return array
	 */
	public static function get_widgets() {
		return array(
			All_Widgets\Hvnly_Featured_Properties_Widget::class,
			All_Widgets\Hvnly_Property_Agent_Widget::class,
			All_Widgets\Hvnly_Mortgage_Calculator_Widget::class,
			All_Widgets\Hvnly_Related_Properties_Widget::class,
			All_Widgets\Hvnly_Agent_Listings_Carousel_Widget::class,
		);
	}

	/**
	 * Register widgets and sidebars.
	 */
	public function register_widgets() {
		foreach ( self::get_widgets() as $class ) {
			if ( class_exists( $class ) ) {
				register_widget( $class );
			}
		}

		foreach ( self::register_sidebar() as $sidebar ) {
			register_sidebar( $sidebar );
		}
	}
}
