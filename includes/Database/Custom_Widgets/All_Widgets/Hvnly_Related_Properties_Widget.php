<?php
/**
 * Related Properties Widget
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
 * Class Hvnly_Related_Properties_Widget
 */
class Hvnly_Related_Properties_Widget extends \WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'                   => 'hvnly-property-single__widget hvnly-property-single__widget--related',
			'description'                 => __( 'Display properties related to current property.', 'havenlytics' ),
			'customize_selective_refresh' => true,
		);

		parent::__construct(
			'hvnly_related_properties',
			__( 'Related Properties', 'havenlytics' ),
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

		$title         = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Related Properties', 'havenlytics' );
		$number        = ! empty( $instance['number'] ) ? absint( $instance['number'] ) : 3;
		$relation_type = ! empty( $instance['relation_type'] ) ? $instance['relation_type'] : 'location_type';

		$show_price     = isset( $instance['show_price'] ) && '1' === $instance['show_price'];
		$show_bedrooms  = isset( $instance['show_bedrooms'] ) && '1' === $instance['show_bedrooms'];
		$show_bathrooms = isset( $instance['show_bathrooms'] ) && '1' === $instance['show_bathrooms'];
		$show_sqft      = isset( $instance['show_sqft'] ) && '1' === $instance['show_sqft'];

		$current_id = get_the_ID();

		// Build tax query based on relation type.
		$tax_query = $this->build_tax_query( $current_id, $relation_type );

		if ( empty( $tax_query ) ) {
			return;
		}

		$query_args = array(
			'post_type'      => 'hvnly_property',
			'posts_per_page' => $number,
			'post_status'    => 'publish',
			'post__not_in'   => array( $current_id ),
			'tax_query'      => $tax_query,
			'orderby'        => 'rand',
			'no_found_rows'  => true,
		);

		$related_query = new \WP_Query( $query_args );

		if ( ! $related_query->have_posts() ) {
			return;
		}

		echo wp_kses_post( $args['before_widget'] );

		if ( ! empty( $title ) ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}
		?>

<div class="hvnly-property-single__featured-grid">
    <?php
			while ( $related_query->have_posts() ) :
				$related_query->the_post();
				$related_id    = get_the_ID();
				$related_title = get_the_title();
				$related_permalink = get_permalink();

				$related_price = '';
				$bedrooms      = '';
				$bathrooms     = '';
				$sqft          = '';

				if ( $show_price ) {
					$price_value = get_post_meta( $related_id, '_hvnly_property_price', true );
					if ( ! empty( $price_value ) ) {
						$related_price = HVNLY_NAB()->Helper->format_price( $price_value );
					}
				}

				if ( $show_bedrooms ) {
					$bedrooms = get_post_meta( $related_id, '_hvnly_property_bedrooms', true );
				}

				if ( $show_bathrooms ) {
					$bathrooms = get_post_meta( $related_id, '_hvnly_property_bathrooms', true );
				}

				if ( $show_sqft ) {
					$sqft = get_post_meta( $related_id, '_hvnly_property_sqft', true );
				}

				$related_image = '';
				if ( has_post_thumbnail( $related_id ) ) {
					$related_image = get_the_post_thumbnail_url( $related_id, 'medium' );
				}
				?>
    <div class="hvnly-property-single__featured-item">
        <a href="<?php echo esc_url( $related_permalink ); ?>">
            <div class="hvnly-property-single__featured-image">
                <?php if ( $related_image ) : ?>
                <img src="<?php echo esc_url( $related_image ); ?>" alt="<?php echo esc_attr( $related_title ); ?>">
                <?php else : ?>
                <i class="fas fa-home" style="font-size: 40px; color: <?php echo esc_attr( function_exists( 'hvnly_get_brand_color' ) ? hvnly_get_brand_color() : '#6C60FE' ); ?>;"></i>
                <?php endif; ?>
            </div>
            <div class="hvnly-property-single__featured-info">
                <h5><?php echo esc_html( $related_title ); ?></h5>

                <?php if ( $show_price && ! empty( $related_price ) ) : ?>
                <div class="hvnly-property-single__featured-price"><?php echo wp_kses_post( $related_price ); ?></div>
                <?php endif; ?>

                <?php if ( ( $show_bedrooms && ! empty( $bedrooms ) ) || ( $show_bathrooms && ! empty( $bathrooms ) ) || ( $show_sqft && ! empty( $sqft ) ) ) : ?>
                <div class="hvnly-property-single__featured-meta">
                    <?php if ( $show_bedrooms && ! empty( $bedrooms ) ) : ?>
                    <span class="hvnly-property-single__featured-meta-item">
                        <i class="fas fa-bed"></i>
                        <?php echo esc_html( $bedrooms ); ?> <?php esc_html_e( 'Beds', 'havenlytics' ); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ( $show_bathrooms && ! empty( $bathrooms ) ) : ?>
                    <span class="hvnly-property-single__featured-meta-item">
                        <i class="fas fa-bath"></i>
                        <?php echo esc_html( $bathrooms ); ?> <?php esc_html_e( 'Baths', 'havenlytics' ); ?>
                    </span>
                    <?php endif; ?>

                    <?php if ( $show_sqft && ! empty( $sqft ) ) : ?>
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
	 * Build tax query based on relation type
	 *
	 * @param int    $current_id    Current property ID.
	 * @param string $relation_type Relation type.
	 * @return array
	 */
	private function build_tax_query( $current_id, $relation_type ) {
		$tax_query = array( 'relation' => 'OR' );
		$has_terms = false;

		// Get location terms.
		if ( in_array( $relation_type, array( 'location', 'location_type' ), true ) ) {
			$location_terms = get_the_terms( $current_id, 'hvnly_prop_locations' );
			if ( ! empty( $location_terms ) && ! is_wp_error( $location_terms ) ) {
				$tax_query[] = array(
					'taxonomy' => 'hvnly_prop_locations',
					'field'    => 'term_id',
					'terms'    => wp_list_pluck( $location_terms, 'term_id' ),
				);
				$has_terms = true;
			}
		}

		// Get type terms.
		if ( in_array( $relation_type, array( 'type', 'location_type' ), true ) ) {
			$type_terms = get_the_terms( $current_id, 'hvnly_prop_types' );
			if ( ! empty( $type_terms ) && ! is_wp_error( $type_terms ) ) {
				$tax_query[] = array(
					'taxonomy' => 'hvnly_prop_types',
					'field'    => 'term_id',
					'terms'    => wp_list_pluck( $type_terms, 'term_id' ),
				);
				$has_terms = true;
			}
		}

		return $has_terms ? $tax_query : array();
	}

	/**
	 * Widget form (admin)
	 *
	 * @param array $instance Widget instance.
	 */
	public function form( $instance ) {
		$defaults = array(
			'title'         => __( 'Related Properties', 'havenlytics' ),
			'number'        => 3,
			'relation_type' => 'location_type',
			'show_price'    => '0',
			'show_bedrooms' => '0',
			'show_bathrooms'=> '0',
			'show_sqft'     => '0',
		);

		$instance = WidgetInstanceHelpers::normalize_instance( wp_parse_args( (array) $instance, $defaults ) );

		$title         = $instance['title'];
		$number        = absint( $instance['number'] );
		$relation_type = $instance['relation_type'];
		$show_price    = $instance['show_price'];
		$show_bedrooms = $instance['show_bedrooms'];
		$show_bathrooms= $instance['show_bathrooms'];
		$show_sqft     = $instance['show_sqft'];
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
    <label for="<?php echo esc_attr( $this->get_field_id( 'relation_type' ) ); ?>">
        <?php esc_html_e( 'Find related by:', 'havenlytics' ); ?>
    </label>
    <select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'relation_type' ) ); ?>"
        name="<?php echo esc_attr( $this->get_field_name( 'relation_type' ) ); ?>">
        <option value="location" <?php selected( $relation_type, 'location' ); ?>>
            <?php esc_html_e( 'Same Location', 'havenlytics' ); ?>
        </option>
        <option value="type" <?php selected( $relation_type, 'type' ); ?>>
            <?php esc_html_e( 'Same Property Type', 'havenlytics' ); ?>
        </option>
        <option value="location_type" <?php selected( $relation_type, 'location_type' ); ?>>
            <?php esc_html_e( 'Same Location & Type', 'havenlytics' ); ?>
        </option>
    </select>
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

		$relation_type = sanitize_text_field( (string) ( $new_instance['relation_type'] ?? $old_instance['relation_type'] ?? 'location_type' ) );
		if ( ! in_array( $relation_type, array( 'location', 'type', 'location_type' ), true ) ) {
			$relation_type = 'location_type';
		}

		$sanitized = array(
			'title'          => sanitize_text_field( (string) ( $new_instance['title'] ?? $old_instance['title'] ?? __( 'Related Properties', 'havenlytics' ) ) ),
			'number'         => absint( $new_instance['number'] ?? $old_instance['number'] ?? 3 ),
			'relation_type'  => $relation_type,
			'show_price'     => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_price'] ?? $old_instance['show_price'] ?? '0' ),
			'show_bedrooms'  => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_bedrooms'] ?? $old_instance['show_bedrooms'] ?? '0' ),
			'show_bathrooms' => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_bathrooms'] ?? $old_instance['show_bathrooms'] ?? '0' ),
			'show_sqft'      => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_sqft'] ?? $old_instance['show_sqft'] ?? '0' ),
		);

		if ( $sanitized['number'] < 1 ) {
			$sanitized['number'] = 1;
		}

		return WidgetInstanceHelpers::merge_instance( $old_instance, $sanitized );
	}
}