<?php
/**
 * Featured Properties Widget
 *
 * @package HvnlyNab\Database\Custom_Widgets\All_Widgets
 * @since 2.0.0
 */

namespace HvnlyNab\Database\Custom_Widgets\All_Widgets;

use HvnlyNab\Database\Custom_Widgets\WidgetInstanceHelpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hvnly_Featured_Properties_Widget
 */
class Hvnly_Featured_Properties_Widget extends \WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'                   => 'hvnly-property-single__widget hvnly-property-single__widget--featured',
			'description'                 => __( 'Display featured properties.', 'havenlytics' ),
			'customize_selective_refresh' => true,
		);

		parent::__construct(
			'hvnly_featured_properties',
			__( 'Featured Properties', 'havenlytics' ),
			$widget_ops
		);
	}

	/**
	 * Widget frontend display
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		// Only show on single property pages.
		if ( ! is_singular( 'hvnly_property' ) ) {
			return;
		}

		// Get instance values with defaults.
		$title  = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Featured Properties', 'havenlytics' );
		$number = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : 4;

		// Checkbox values.
		$show_price     = isset( $instance['show_price'] ) && '1' === $instance['show_price'];
		$show_bedrooms  = isset( $instance['show_bedrooms'] ) && '1' === $instance['show_bedrooms'];
		$show_bathrooms = isset( $instance['show_bathrooms'] ) && '1' === $instance['show_bathrooms'];
		$show_sqft      = isset( $instance['show_sqft'] ) && '1' === $instance['show_sqft'];

		// Query featured properties.
		$query_args = array(
			'post_type'      => 'hvnly_property',
			'posts_per_page' => $number,
			'meta_key'       => '_hvnly_property_featured',
			'meta_value'     => '1',
			'post_status'    => 'publish',
			'no_found_rows'  => true,
		);

		$featured_query = new \WP_Query( $query_args );

		if ( ! $featured_query->have_posts() ) {
			return;
		}

		// Widget output.
		echo wp_kses_post( $args['before_widget'] );

		if ( ! empty( $title ) ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}
		?>

<div class="hvnly-property-single__featured-grid">
		<?php
		while ( $featured_query->have_posts() ) :
			$featured_query->the_post();
			$featured_id        = get_the_ID();
			$featured_title     = get_the_title();
			$featured_permalink = get_permalink();

			// Initialize variables.
			$featured_price = '';
			$bedrooms       = '';
			$bathrooms      = '';
			$sqft           = '';

			if ( $show_price ) {
				$price_value = get_post_meta( $featured_id, '_hvnly_property_price', true );
				if ( ! empty( $price_value ) ) {
					$featured_price = HVNLY_NAB()->Helper->format_price( $price_value );
				}
			}

			if ( $show_bedrooms ) {
				$bedrooms = get_post_meta( $featured_id, '_hvnly_property_bedrooms', true );
			}

			if ( $show_bathrooms ) {
				$bathrooms = get_post_meta( $featured_id, '_hvnly_property_bathrooms', true );
			}

			if ( $show_sqft ) {
				$sqft = get_post_meta( $featured_id, '_hvnly_property_sqft', true );
			}

			// Get featured image.
			$featured_image = '';
			if ( has_post_thumbnail( $featured_id ) ) {
				$featured_image = get_the_post_thumbnail_url( $featured_id, 'medium' );
			}
			?>
    <div class="hvnly-property-single__featured-item">
        <a href="<?php echo esc_url( $featured_permalink ); ?>">
            <div class="hvnly-property-single__featured-image">
                <?php if ( $featured_image ) : ?>
                <img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( $featured_title ); ?>">
                <?php else : ?>
                <i class="fas fa-home" style="font-size: 40px; color: <?php echo esc_attr( function_exists( 'hvnly_get_brand_color' ) ? hvnly_get_brand_color() : '#6C60FE' ); ?>;"></i>
                <?php endif; ?>
            </div>
            <div class="hvnly-property-single__featured-info">
                <h5><?php echo esc_html( $featured_title ); ?></h5>

                <?php if ( $featured_price ) : ?>
                <div class="hvnly-property-single__featured-price"><?php echo wp_kses_post( $featured_price ); ?></div>
                <?php endif; ?>

                <?php if ( $bedrooms || $bathrooms || $sqft ) : ?>
                <div class="hvnly-property-single__featured-meta">
                    <?php if ( $bedrooms ) : ?>
                    <span class="hvnly-property-single__featured-meta-item">
                        <i class="fas fa-bed"></i>
                        <?php echo esc_html( $bedrooms ); ?> <?php esc_html_e( 'Beds', 'havenlytics' ); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ( $bathrooms ) : ?>
                    <span class="hvnly-property-single__featured-meta-item">
                        <i class="fas fa-bath"></i>
                        <?php echo esc_html( $bathrooms ); ?> <?php esc_html_e( 'Baths', 'havenlytics' ); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ( $sqft ) : ?>
                    <span class="hvnly-property-single__featured-meta-item">
                        <i class="fas fa-vector-square"></i>
                        <?php echo esc_html( $sqft ); ?> <?php esc_html_e( 'sqft', 'havenlytics' ); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <?php endwhile; ?>
</div>

		<?php
		wp_reset_postdata();
		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Widget form (admin)
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$defaults = array(
			'title'         => __( 'Featured Properties', 'havenlytics' ),
			'number'        => 4,
			'show_price'    => '1',
			'show_bedrooms' => '1',
			'show_bathrooms' => '1',
			'show_sqft'     => '1',
		);

		$instance = WidgetInstanceHelpers::normalize_instance( wp_parse_args( (array) $instance, $defaults ) );

		$title          = $instance['title'];
		$number         = absint( $instance['number'] );
		$show_price     = $instance['show_price'];
		$show_bedrooms  = $instance['show_bedrooms'];
		$show_bathrooms = $instance['show_bathrooms'];
		$show_sqft      = $instance['show_sqft'];
		?>

<p>
    <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
        <?php esc_html_e( 'Title:', 'havenlytics' ); ?>
    </label>
    <input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
        name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" value="<?php echo esc_attr( $title ); ?>">
</p>

<p>
    <label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>">
        <?php esc_html_e( 'Number of properties:', 'havenlytics' ); ?>
    </label>
    <input type="number" class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"
        name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" value="<?php echo esc_attr( $number ); ?>"
        min="1" max="10">
</p>

<p>
    <input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_price' ) ); ?>"
        name="<?php echo esc_attr( $this->get_field_name( 'show_price' ) ); ?>" value="1"
        <?php checked( $show_price, '1' ); ?>>
    <label for="<?php echo esc_attr( $this->get_field_id( 'show_price' ) ); ?>">
        <?php esc_html_e( 'Show price', 'havenlytics' ); ?>
    </label>
</p>

<p>
    <input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_bedrooms' ) ); ?>"
        name="<?php echo esc_attr( $this->get_field_name( 'show_bedrooms' ) ); ?>" value="1"
        <?php checked( $show_bedrooms, '1' ); ?>>
    <label for="<?php echo esc_attr( $this->get_field_id( 'show_bedrooms' ) ); ?>">
        <?php esc_html_e( 'Show bedrooms', 'havenlytics' ); ?>
    </label>
</p>

<p>
    <input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_bathrooms' ) ); ?>"
        name="<?php echo esc_attr( $this->get_field_name( 'show_bathrooms' ) ); ?>" value="1"
        <?php checked( $show_bathrooms, '1' ); ?>>
    <label for="<?php echo esc_attr( $this->get_field_id( 'show_bathrooms' ) ); ?>">
        <?php esc_html_e( 'Show bathrooms', 'havenlytics' ); ?>
    </label>
</p>

<p>
    <input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_sqft' ) ); ?>"
        name="<?php echo esc_attr( $this->get_field_name( 'show_sqft' ) ); ?>" value="1"
        <?php checked( $show_sqft, '1' ); ?>>
    <label for="<?php echo esc_attr( $this->get_field_id( 'show_sqft' ) ); ?>">
        <?php esc_html_e( 'Show square feet', 'havenlytics' ); ?>
    </label>
</p>
		<?php
	}

	/**
	 * Update widget settings
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$old_instance = WidgetInstanceHelpers::normalize_instance( $old_instance );
		$new_instance = is_array( $new_instance ) ? $new_instance : array();

		$sanitized = array(
			'title'          => sanitize_text_field( (string) ( $new_instance['title'] ?? $old_instance['title'] ?? __( 'Featured Properties', 'havenlytics' ) ) ),
			'number'         => absint( $new_instance['number'] ?? $old_instance['number'] ?? 4 ),
			'show_price'     => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_price'] ?? $old_instance['show_price'] ?? '1' ),
			'show_bedrooms'  => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_bedrooms'] ?? $old_instance['show_bedrooms'] ?? '1' ),
			'show_bathrooms' => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_bathrooms'] ?? $old_instance['show_bathrooms'] ?? '1' ),
			'show_sqft'      => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_sqft'] ?? $old_instance['show_sqft'] ?? '1' ),
		);

		if ( $sanitized['number'] < 1 ) {
			$sanitized['number'] = 1;
		}

		return WidgetInstanceHelpers::merge_instance( $old_instance, $sanitized );
	}
}