<?php

/**

 * Agent Listings Carousel sidebar widget.

 *

 * @package HvnlyNab\Database\Custom_Widgets\All_Widgets

 * @since   3.0.5

 */



namespace HvnlyNab\Database\Custom_Widgets\All_Widgets;



use HvnlyNab\Agent\AgentListingsCarouselWidgetRenderer;

use HvnlyNab\Database\Custom_Widgets\WidgetInstanceHelpers;



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



/**

 * Displays a carousel of properties assigned to a selected agent.

 */

class Hvnly_Agent_Listings_Carousel_Widget extends \WP_Widget {



	/**

	 * Constructor.

	 */

	public function __construct() {

		parent::__construct(

			'hvnly_agent_listings_carousel',

			__( 'Agent Listings Carousel', 'havenlytics' ),

			array(

				'classname'                   => 'hvnly-property-single__widget hvnly-property-single__widget--agent-listings',

				'description'                 => __( 'Carousel of properties assigned to a selected agent on single property pages.', 'havenlytics' ),

				'customize_selective_refresh' => true,

			)

		);
	}



	/**

	 * Frontend output.

	 *

	 * @param array $args     Widget arguments.

	 * @param array $instance Widget instance.

	 * @return void

	 */

	public function widget( $args, $instance ) {

		if ( ! is_singular( 'hvnly_property' ) ) {

			return;

		}

		$property_id = (int) get_the_ID();

		if ( $property_id <= 0 ) {

			return;

		}

		$instance = wp_parse_args( (array) $instance, AgentListingsCarouselWidgetRenderer::get_defaults() );

		ob_start();

		$rendered = AgentListingsCarouselWidgetRenderer::render( $property_id, $instance, (string) $args['widget_id'] );

		$output = ob_get_clean();

		if ( ! $rendered || '' === trim( $output ) ) {

			return;

		}

		echo wp_kses_post( $args['before_widget'] );

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template uses escaped output helpers.

		echo wp_kses_post( $args['after_widget'] );
	}



	/**

	 * Admin form.

	 *

	 * @param array $instance Widget instance.

	 * @return void

	 */

	public function form( $instance ) {

		$instance = WidgetInstanceHelpers::normalize_instance(

			wp_parse_args( (array) $instance, AgentListingsCarouselWidgetRenderer::get_defaults() )

		);

		$agent_id = absint( $instance['agent_id'] );

		$title = (string) $instance['title'];

		$number = absint( $instance['number'] );

		$orderby = (string) $instance['orderby'];

		$show_price = (string) $instance['show_price'];

		$show_location = (string) $instance['show_location'];

		$show_status = (string) $instance['show_status'];

		$autoplay = (string) $instance['autoplay'];

		$show_nav = (string) $instance['show_nav'];

		$agent_choices = AgentListingsCarouselWidgetRenderer::get_agent_choices();

		?>

		<p>

			<label for="<?php echo esc_attr( $this->get_field_id( 'agent_id' ) ); ?>">

				<?php esc_html_e( 'Agent:', 'havenlytics' ); ?>

			</label>

			<select

				class="widefat"

				id="<?php echo esc_attr( $this->get_field_id( 'agent_id' ) ); ?>"

				name="<?php echo esc_attr( $this->get_field_name( 'agent_id' ) ); ?>"

			>

				<?php foreach ( $agent_choices as $choice_id => $choice_label ) : ?>

					<option value="<?php echo esc_attr( (string) $choice_id ); ?>" <?php selected( $agent_id, $choice_id ); ?>>

						<?php echo esc_html( $choice_label ); ?>

					</option>

				<?php endforeach; ?>

			</select>

			<small><?php esc_html_e( 'Choose a specific agent, or leave on Auto to use the current property agent.', 'havenlytics' ); ?></small>

		</p>



		<p>

			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">

				<?php esc_html_e( 'Title override:', 'havenlytics' ); ?>

			</label>

			<input

				type="text"

				class="widefat"

				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"

				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"

				value="<?php echo esc_attr( $title ); ?>"

				placeholder="<?php esc_attr_e( 'More Listings From {Agent Name}', 'havenlytics' ); ?>"

			>

			<small><?php esc_html_e( 'Leave empty to use the selected agent name automatically.', 'havenlytics' ); ?></small>

		</p>



		<p>

			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>">

				<?php esc_html_e( 'Number of properties:', 'havenlytics' ); ?>

			</label>

			<input

				type="number"

				class="widefat"

				id="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"

				name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>"

				value="<?php echo esc_attr( (string) $number ); ?>"

				min="1"

				max="20"

			>

		</p>



		<p>

			<label for="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>">

				<?php esc_html_e( 'Order by:', 'havenlytics' ); ?>

			</label>

			<select

				class="widefat"

				id="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>"

				name="<?php echo esc_attr( $this->get_field_name( 'orderby' ) ); ?>"

			>

				<option value="assigned" <?php selected( $orderby, 'assigned' ); ?>><?php esc_html_e( 'Assignment order', 'havenlytics' ); ?></option>

				<option value="date" <?php selected( $orderby, 'date' ); ?>><?php esc_html_e( 'Date', 'havenlytics' ); ?></option>

				<option value="title" <?php selected( $orderby, 'title' ); ?>><?php esc_html_e( 'Title', 'havenlytics' ); ?></option>

				<option value="price" <?php selected( $orderby, 'price' ); ?>><?php esc_html_e( 'Price', 'havenlytics' ); ?></option>

				<option value="rand" <?php selected( $orderby, 'rand' ); ?>><?php esc_html_e( 'Random', 'havenlytics' ); ?></option>

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

			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_location' ) ); ?>"

				name="<?php echo esc_attr( $this->get_field_name( 'show_location' ) ); ?>" value="1"

				<?php checked( $show_location, '1' ); ?>>

			<label for="<?php echo esc_attr( $this->get_field_id( 'show_location' ) ); ?>">

				<?php esc_html_e( 'Show location', 'havenlytics' ); ?>

			</label>

		</p>



		<p>

			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_status' ) ); ?>"

				name="<?php echo esc_attr( $this->get_field_name( 'show_status' ) ); ?>" value="1"

				<?php checked( $show_status, '1' ); ?>>

			<label for="<?php echo esc_attr( $this->get_field_id( 'show_status' ) ); ?>">

				<?php esc_html_e( 'Show status', 'havenlytics' ); ?>

			</label>

		</p>



		<p>

			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'autoplay' ) ); ?>"

				name="<?php echo esc_attr( $this->get_field_name( 'autoplay' ) ); ?>" value="1"

				<?php checked( $autoplay, '1' ); ?>>

			<label for="<?php echo esc_attr( $this->get_field_id( 'autoplay' ) ); ?>">

				<?php esc_html_e( 'Autoplay carousel', 'havenlytics' ); ?>

			</label>

		</p>



		<p>

			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_nav' ) ); ?>"

				name="<?php echo esc_attr( $this->get_field_name( 'show_nav' ) ); ?>" value="1"

				<?php checked( $show_nav, '1' ); ?>>

			<label for="<?php echo esc_attr( $this->get_field_id( 'show_nav' ) ); ?>">

				<?php esc_html_e( 'Show navigation arrows', 'havenlytics' ); ?>

			</label>

		</p>

		<?php
	}



	/**

	 * Save widget settings.

	 *

	 * @param array $new_instance New settings.

	 * @param array $old_instance Old settings.

	 * @return array<string, mixed>

	 */

	public function update( $new_instance, $old_instance ) {

		$old_instance = WidgetInstanceHelpers::normalize_instance( $old_instance );

		$new_instance = is_array( $new_instance ) ? $new_instance : array();

		$orderby = sanitize_key( (string) ( $new_instance['orderby'] ?? $old_instance['orderby'] ?? 'assigned' ) );

		if ( ! in_array( $orderby, array( 'assigned', 'date', 'title', 'price', 'rand' ), true ) ) {

			$orderby = 'assigned';

		}

		$agent_id = absint( $new_instance['agent_id'] ?? $old_instance['agent_id'] ?? 0 );

		if ( $agent_id > 0 && function_exists( 'hvnly_is_valid_agent' ) && ! hvnly_is_valid_agent( $agent_id ) ) {

			$agent_id = 0;

		}

		$sanitized = array(

			'agent_id'      => $agent_id,

			'title'         => sanitize_text_field( (string) ( $new_instance['title'] ?? $old_instance['title'] ?? '' ) ),

			'number'        => absint( $new_instance['number'] ?? $old_instance['number'] ?? 6 ),

			'orderby'       => $orderby,

			'show_price'    => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_price'] ?? $old_instance['show_price'] ?? '1' ),

			'show_location' => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_location'] ?? $old_instance['show_location'] ?? '1' ),

			'show_status'   => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_status'] ?? $old_instance['show_status'] ?? '1' ),

			'autoplay'      => WidgetInstanceHelpers::checkbox_flag( $new_instance['autoplay'] ?? $old_instance['autoplay'] ?? '0' ),

			'show_nav'      => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_nav'] ?? $old_instance['show_nav'] ?? '1' ),

		);

		if ( $sanitized['number'] < 1 ) {

			$sanitized['number'] = 1;

		}

		return WidgetInstanceHelpers::merge_instance( $old_instance, $sanitized );
	}
}


