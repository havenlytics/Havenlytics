<?php
/**
 * Generic legacy Havenlytics widget preservation shim.
 *
 * Registers unregistered hvnly_* widget bases found in sidebars_widgets so the
 * block widget editor can load/save without JS errors. Preserves all instance data.
 *
 * @package HvnlyNab\Database\Custom_Widgets\All_Widgets
 * @since   3.0.6
 */

namespace HvnlyNab\Database\Custom_Widgets\All_Widgets;

use HvnlyNab\Agent\PropertyAgentWidgetRenderer;
use HvnlyNab\Database\Custom_Widgets\WidgetInstanceHelpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preserves legacy widget type registration and stored settings.
 */
class Hvnly_Legacy_Preserved_Widget extends \WP_Widget {

	/**
	 * @var array<string, bool>
	 */
	private static $registered_bases = array();

	/**
	 * @param string $id_base Widget id base.
	 * @param string $title   Admin title.
	 */
	public function __construct( string $id_base, string $title = '' ) {
		if ( '' === $title ) {
			$title = sprintf(
				/* translators: %s: legacy widget id */
				__( 'Havenlytics (Legacy): %s', 'havenlytics' ),
				$id_base
			);
		}

		parent::__construct(
			$id_base,
			$title,
			array(
				'classname'                   => 'hvnly-widget hvnly-widget--legacy hvnly-widget--' . sanitize_html_class( $id_base ),
				'description'                 => __( 'Legacy Havenlytics widget preserved for compatibility. Settings are retained; edit with care.', 'havenlytics' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Register a legacy base once.
	 *
	 * @param string $id_base Widget id base.
	 * @param string $title   Optional admin title.
	 * @return void
	 */
	public static function register_base( string $id_base, string $title = '' ): void {
		if ( isset( self::$registered_bases[ $id_base ] ) ) {
			return;
		}

		self::$registered_bases[ $id_base ] = true;
		register_widget( new self( $id_base, $title ) );
	}

	/**
	 * @param array<string, mixed> $args     Widget args.
	 * @param array<string, mixed> $instance Instance settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		if ( ! is_singular( 'hvnly_property' ) ) {
			return;
		}

		$instance = WidgetInstanceHelpers::normalize_instance( $instance );

		// Legacy agent card layout (pre-3.0.2) — keep original frontend output path.
		if ( 'hvnly_agent' === $this->id_base && function_exists( 'hvnly_get_template_part' ) ) {
			$property_id = (int) get_the_ID();
			if ( $property_id <= 0 ) {
				return;
			}

			$agents = PropertyAgentWidgetRenderer::resolve_sidebar_agents( $property_id );
			if ( empty( $agents ) ) {
				return;
			}

			PropertyAgentWidgetRenderer::enqueue_assets(
				'classic',
				count( $agents ) > 1 && ( ! isset( $instance['multi_layout'] ) || 'slider' === (string) $instance['multi_layout'] )
			);

			echo wp_kses_post( $args['before_widget'] ?? '' );

			if ( ! empty( $instance['title'] ) ) {
				echo wp_kses_post( $args['before_title'] ?? '' );
				echo esc_html( (string) $instance['title'] );
				echo wp_kses_post( $args['after_title'] ?? '' );
			}

			hvnly_get_template_part(
				'single-property/partials/property-agent-widget',
				null,
				array(
					'property_id' => $property_id,
					'instance'    => $instance,
					'agents'      => $agents,
					'widget_id'   => sanitize_html_class( $this->id ),
				)
			);

			echo wp_kses_post( $args['after_widget'] ?? '' );
		}
	}

	/**
	 * @param array<string, mixed> $instance Instance settings.
	 * @return void
	 */
	public function form( $instance ) {
		$instance = WidgetInstanceHelpers::normalize_instance( $instance );
		?>
		<div class="hvnly-agent-widget-form hvnly-legacy-widget-form">
			<p class="description">
				<?php esc_html_e( 'This is a legacy Havenlytics widget. All saved settings are preserved. Use the fields below to update safe display options.', 'havenlytics' ); ?>
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
					<?php esc_html_e( 'Widget Title:', 'havenlytics' ); ?>
				</label>
				<input
					type="text"
					class="widefat"
					id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
					value="<?php echo esc_attr( (string) ( $instance['title'] ?? '' ) ); ?>"
				/>
			</p>
			<?php if ( 'hvnly_agent' === $this->id_base ) : ?>
				<p>
					<label for="<?php echo esc_attr( $this->get_field_id( 'agent_source' ) ); ?>">
						<?php esc_html_e( 'Agent Source:', 'havenlytics' ); ?>
					</label>
					<select
						class="widefat hvnly-agent-source"
						id="<?php echo esc_attr( $this->get_field_id( 'agent_source' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'agent_source' ) ); ?>"
					>
						<?php
						$source = isset( $instance['agent_source'] ) ? (string) $instance['agent_source'] : 'property';
						foreach ( array( 'property' => __( 'Property assigned agents', 'havenlytics' ), 'custom' => __( 'Custom selection', 'havenlytics' ) ) as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $source, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="<?php echo esc_attr( $this->get_field_id( 'widget_layout' ) ); ?>">
						<?php esc_html_e( 'Widget Layout:', 'havenlytics' ); ?>
					</label>
					<select
						class="widefat hvnly-agent-widget-layout"
						id="<?php echo esc_attr( $this->get_field_id( 'widget_layout' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'widget_layout' ) ); ?>"
					>
						<?php
						$layout = isset( $instance['widget_layout'] ) ? (string) $instance['widget_layout'] : 'classic';
						foreach ( array( 'classic' => __( 'Classic', 'havenlytics' ), 'compact' => __( 'Compact', 'havenlytics' ) ) as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $layout, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>
				<div class="hvnly-selected-properties">
					<?php
					$selected = WidgetInstanceHelpers::normalize_id_list( $instance['selected_properties'] ?? array() );
					$target   = $this->get_field_name( 'selected_properties' );
					foreach ( $selected as $property_id ) :
						?>
						<div class="hvnly-selected-property" data-id="<?php echo esc_attr( (string) $property_id ); ?>">
							<span><?php
							echo esc_html(
								get_the_title( $property_id ) ?: sprintf(
									/* translators: %d: Property post ID. */
									__( 'Property #%d', 'havenlytics' ),
									$property_id
								)
							);
							?></span>
							<input type="hidden" name="<?php echo esc_attr( $target ); ?>[]" value="<?php echo esc_attr( (string) $property_id ); ?>" />
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $new_instance New settings.
	 * @param array<string, mixed> $old_instance Old settings.
	 * @return array<string, mixed>
	 */
	public function update( $new_instance, $old_instance ) {
		$old_instance = WidgetInstanceHelpers::normalize_instance( $old_instance );
		$new_instance = is_array( $new_instance ) ? $new_instance : array();

		$sanitized = array();

		if ( array_key_exists( 'title', $new_instance ) ) {
			$sanitized['title'] = sanitize_text_field( (string) $new_instance['title'] );
		}

		if ( 'hvnly_agent' === $this->id_base ) {
			if ( isset( $new_instance['agent_source'] ) ) {
				$source = sanitize_key( (string) $new_instance['agent_source'] );
				$sanitized['agent_source'] = in_array( $source, array( 'property', 'custom' ), true ) ? $source : 'property';
			}

			if ( isset( $new_instance['widget_layout'] ) ) {
				$layout = sanitize_key( (string) $new_instance['widget_layout'] );
				$sanitized['widget_layout'] = in_array( $layout, array( 'classic', 'compact' ), true ) ? $layout : 'classic';
			}

			if ( isset( $new_instance['selected_properties'] ) ) {
				$sanitized['selected_properties'] = WidgetInstanceHelpers::normalize_id_list( $new_instance['selected_properties'] );
			}

			foreach ( array( 'show_phone', 'show_email', 'show_whatsapp', 'show_social', 'show_rating' ) as $flag ) {
				if ( array_key_exists( $flag, $new_instance ) ) {
					$sanitized[ $flag ] = WidgetInstanceHelpers::checkbox_flag( $new_instance[ $flag ] );
				}
			}
		}

		return WidgetInstanceHelpers::merge_instance( $old_instance, $sanitized );
	}
}
