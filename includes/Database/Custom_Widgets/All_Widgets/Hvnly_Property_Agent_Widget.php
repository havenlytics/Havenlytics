<?php
/**
 * Property Agent sidebar widget — dynamic agents from property assignment.
 *
 * @package HvnlyNab\Database\Custom_Widgets\All_Widgets
 * @since   3.0.2
 */

namespace HvnlyNab\Database\Custom_Widgets\All_Widgets;

use HvnlyNab\Agent\PropertyAgentWidgetRenderer;
use HvnlyNab\Database\Custom_Widgets\WidgetInstanceHelpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays property-assigned agent details and contact form in the single-property sidebar.
 */
class Hvnly_Property_Agent_Widget extends \WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'hvnly_property_agent',
			__( 'Property Agent', 'havenlytics' ),
			array(
				'classname'                   => 'hvnly-property-single__widget hvnly-property-single__widget--agent',
				'description'                 => __( 'Shows assigned property agents with contact details and inquiry form. Falls back to site admin when no agent is assigned.', 'havenlytics' ),
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

		if ( PropertyAgentWidgetRenderer::should_hide_sidebar_widget( $property_id ) ) {
			return;
		}

		$instance = wp_parse_args( (array) $instance, PropertyAgentWidgetRenderer::get_sidebar_defaults() );
		$agents   = PropertyAgentWidgetRenderer::resolve_sidebar_agents( $property_id );

		if ( empty( $agents ) ) {
			return;
		}

		$title = ! empty( $instance['title'] ) ? (string) $instance['title'] : __( 'Contact Agent', 'havenlytics' );

		echo wp_kses_post( $args['before_widget'] );

		if ( '' !== trim( $title ) ) {
			echo wp_kses_post( $args['before_title'] ) . esc_html( $title ) . wp_kses_post( $args['after_title'] );
		}

		PropertyAgentWidgetRenderer::render_sidebar(
			$property_id,
			$instance,
			sanitize_html_class( $this->id ),
			$agents
		);

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Widget settings form.
	 *
	 * @param array $instance Widget instance.
	 * @return void
	 */
	public function form( $instance ) {
		$instance = WidgetInstanceHelpers::normalize_instance(
			wp_parse_args( (array) $instance, PropertyAgentWidgetRenderer::get_sidebar_defaults() )
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Widget Title:', 'havenlytics' ); ?>
			</label>
			<input
				type="text"
				class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( (string) $instance['title'] ); ?>"
			/>
		</p>
		<p class="description">
			<?php esc_html_e( 'Agent details are loaded automatically from the property’s assigned agents. If none are assigned, the site administrator is shown with the inquiry form.', 'havenlytics' ); ?>
		</p>
		<p>
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_phone' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_phone' ) ); ?>"
				value="1"
				<?php checked( $instance['show_phone'], '1' ); ?>
			/>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_phone' ) ); ?>">
				<?php esc_html_e( 'Show phone', 'havenlytics' ); ?>
			</label>
		</p>
		<p>
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_email' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_email' ) ); ?>"
				value="1"
				<?php checked( $instance['show_email'], '1' ); ?>
			/>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_email' ) ); ?>">
				<?php esc_html_e( 'Show email', 'havenlytics' ); ?>
			</label>
		</p>
		<p>
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_whatsapp' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_whatsapp' ) ); ?>"
				value="1"
				<?php checked( $instance['show_whatsapp'], '1' ); ?>
			/>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_whatsapp' ) ); ?>">
				<?php esc_html_e( 'Show WhatsApp', 'havenlytics' ); ?>
			</label>
		</p>
		<p>
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $this->get_field_id( 'show_social' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'show_social' ) ); ?>"
				value="1"
				<?php checked( $instance['show_social'], '1' ); ?>
			/>
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_social' ) ); ?>">
				<?php esc_html_e( 'Show social links', 'havenlytics' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Save widget settings.
	 *
	 * @param array $new_instance New settings.
	 * @param array $old_instance Old settings.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$old_instance = WidgetInstanceHelpers::normalize_instance( $old_instance );
		$new_instance = is_array( $new_instance ) ? $new_instance : array();

		$sanitized = array(
			'title'         => sanitize_text_field( (string) ( $new_instance['title'] ?? $old_instance['title'] ?? '' ) ),
			'show_phone'    => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_phone'] ?? $old_instance['show_phone'] ?? '0' ),
			'show_email'    => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_email'] ?? $old_instance['show_email'] ?? '0' ),
			'show_whatsapp' => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_whatsapp'] ?? $old_instance['show_whatsapp'] ?? '0' ),
			'show_social'   => WidgetInstanceHelpers::checkbox_flag( $new_instance['show_social'] ?? $old_instance['show_social'] ?? '0' ),
		);

		return WidgetInstanceHelpers::merge_instance( $old_instance, $sanitized );
	}
}
