<?php
/**
 * Agents Section Field Handler
 *
 * @package HvnlyNab\Database\FieldTypes
 * @since   3.0.2
 */

namespace HvnlyNab\Database\FieldTypes;

use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * Property metabox field for selecting agents in a builder agents group.
 */
class AgentsField extends BaseFieldType {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( 'agents' );
		$this->requires_assets = true;
	}

	/**
	 * Render agents picker UI.
	 *
	 * @param array $field   Field configuration.
	 * @param mixed $value   Current field value.
	 * @param int   $post_id Post ID.
	 * @return string
	 */
	public function render( $field, $value, $post_id ) {
		$field = $this->prepare_group_field( $field, 'AgentsField' );

		$group_base_id = $field['group_base_id'] ?? '';
		if ( empty( $group_base_id ) && ! empty( $field['name'] ) ) {
			$group_base_id = str_replace( '_agents', '', (string) $field['name'] );
		}

		$field_name            = $group_base_id . '_agents';
		$title_field_name      = $group_base_id . '_title';
		$sidebar_toggle_name   = AgentConstants::META_HIDE_SIDEBAR_AGENT_WIDGET;
		$assigned_ids          = $this->get_assigned_agent_ids( $post_id, $field_name, $field );
		$available             = $this->get_available_agents( $assigned_ids );
		$saved_title           = (string) $this->resolve_group_meta(
			(int) $post_id,
			array_merge( $field, array( 'metaKey' => 'title', 'name' => $title_field_name ) ),
			$title_field_name,
			'title'
		);
		$hide_sidebar_widget   = '1' === (string) get_post_meta( $post_id, $sidebar_toggle_name, true );
		$container_id          = 'hvnlyAgentsSection_' . preg_replace( '/[^a-zA-Z0-9_-]/', '_', $group_base_id );

		ob_start();
		?>
		<div
			class="hvnly-agents-section-field"
			id="<?php echo esc_attr( $container_id ); ?>"
			data-group-base-id="<?php echo esc_attr( $group_base_id ); ?>"
			data-field-name="<?php echo esc_attr( $field_name ); ?>"
		>
			<div class="hvnly-agents-section-field__title" style="margin-bottom: 15px;">
				<label for="<?php echo esc_attr( $title_field_name ); ?>" style="display: block; margin-bottom: 5px; font-weight: 600;">
					<?php echo esc_html( hvnly_translate_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Section Title' ) ) ); ?>
				</label>
				<input
					type="text"
					id="<?php echo esc_attr( $title_field_name ); ?>"
					name="<?php echo esc_attr( $title_field_name ); ?>"
					value="<?php echo esc_attr( (string) $saved_title ); ?>"
					class="widefat"
					placeholder="<?php esc_attr_e( 'e.g., Listing Agents', 'havenlytics' ); ?>"
				/>
			</div>

			<div class="hvnly-agents-section-field__picker">
				<label class="hvnly-agents-section-field__picker-label">
					<?php esc_html_e( 'Assigned Agents', 'havenlytics' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Select agents to display in this section on the single property page. The first agent is the primary contact.', 'havenlytics' ); ?>
				</p>

				<ul class="hvnly-property-agents-assignment__list hvnly-agents-section-field__list" aria-live="polite">
					<?php
					foreach ( $assigned_ids as $index => $agent_id ) {
						$this->render_selected_item( (int) $agent_id, 0 === (int) $index, $field_name );
					}
					?>
				</ul>

				<p class="hvnly-property-agents-assignment__empty hvnly-agents-section-field__empty<?php echo empty( $assigned_ids ) ? ' is-visible' : ''; ?>">
					<?php esc_html_e( 'No agents selected yet.', 'havenlytics' ); ?>
				</p>

				<select class="widefat hvnly-agents-section-field__select">
					<option value=""><?php esc_html_e( 'Add agent…', 'havenlytics' ); ?></option>
					<?php foreach ( $available as $agent_option ) : ?>
						<option value="<?php echo esc_attr( (string) $agent_option['id'] ); ?>">
							<?php echo esc_html( $agent_option['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<?php if ( empty( $available ) && empty( $assigned_ids ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: admin URL to create agent */
							esc_html__( 'No published agents found. %s', 'havenlytics' ),
							'<a href="' . esc_url( admin_url( 'post-new.php?post_type=' . AgentConstants::POST_TYPE ) ) . '">' . esc_html__( 'Create an agent', 'havenlytics' ) . '</a>'
						);
						?>
					</p>
				<?php endif; ?>
			</div>

			<div class="hvnly-agents-section-field__sidebar-toggle">
				<div class="hvnly-agents-section-field__toggle-header">
					<div>
						<strong><?php esc_html_e( 'Sidebar Agent Widget', 'havenlytics' ); ?></strong>
						<p class="description">
							<?php esc_html_e( 'When this section is used on the property page, you can hide the sidebar Property Agent widget to avoid duplicate agent info.', 'havenlytics' ); ?>
						</p>
					</div>
					<label class="hvnly-agents-section-field__switch" for="<?php echo esc_attr( $sidebar_toggle_name ); ?>">
						<input
							type="checkbox"
							id="<?php echo esc_attr( $sidebar_toggle_name ); ?>"
							name="<?php echo esc_attr( $sidebar_toggle_name ); ?>"
							value="1"
							<?php checked( $hide_sidebar_widget ); ?>
						/>
						<span class="hvnly-agents-section-field__switch-slider" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Hide sidebar agent widget on this property', 'havenlytics' ); ?></span>
					</label>
				</div>
				<p class="hvnly-agents-section-field__toggle-status<?php echo $hide_sidebar_widget ? ' is-hidden' : ' is-visible'; ?>" data-toggle-state="visible">
					<i class="fas fa-eye" aria-hidden="true"></i>
					<?php esc_html_e( 'Sidebar widget will remain visible.', 'havenlytics' ); ?>
				</p>
				<p class="hvnly-agents-section-field__toggle-status<?php echo $hide_sidebar_widget ? ' is-visible' : ' is-hidden'; ?>" data-toggle-state="hidden">
					<i class="fas fa-eye-slash" aria-hidden="true"></i>
					<?php esc_html_e( 'Sidebar agent widget will be hidden on the single property page.', 'havenlytics' ); ?>
				</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save assigned agents and section title.
	 *
	 * @param int         $post_id    Post ID.
	 * @param string      $field_name Field name ({base}_agents).
	 * @param mixed       $value      Unused.
	 * @param mixed|null  $extra      Optional extra data.
	 * @return void
	 */
	public function save( $post_id, $field_name, $value = null, $extra = null ) {
		unset( $value, $extra );

		$group_base_id = str_replace( '_agents', '', $field_name );
		$input_name    = $group_base_id . '_agents';

		$raw_ids = filter_input( INPUT_POST, $input_name, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$agent_ids = array();

		if ( is_array( $raw_ids ) ) {
			foreach ( $raw_ids as $raw_id ) {
				$agent_id = absint( $raw_id );
				if ( $agent_id > 0 && AgentConstants::POST_TYPE === get_post_type( $agent_id ) && ! in_array( $agent_id, $agent_ids, true ) ) {
					$agent_ids[] = $agent_id;
				}
			}
		}

		if ( ! empty( $agent_ids ) ) {
			update_post_meta( $post_id, $field_name, wp_json_encode( $agent_ids ) );
		} else {
			$import_ids = $this->get_import_agent_ids( $post_id );
			if ( empty( $import_ids ) ) {
				if ( function_exists( 'hvnly_safe_delete_post_meta' ) ) {
					hvnly_safe_delete_post_meta( $post_id, $field_name, 'user_save_empty' );
				} else {
					delete_post_meta( $post_id, $field_name );
				}
			}
		}

		$title_field_name = $group_base_id . '_title';
		$title_value      = filter_input( INPUT_POST, $title_field_name, FILTER_UNSAFE_RAW );
		if ( null !== $title_value ) {
			update_post_meta( $post_id, $title_field_name, sanitize_text_field( (string) $title_value ) );
		}

		$hide_sidebar_raw = filter_input( INPUT_POST, AgentConstants::META_HIDE_SIDEBAR_AGENT_WIDGET, FILTER_UNSAFE_RAW );
		if ( null !== $hide_sidebar_raw && '1' === (string) $hide_sidebar_raw ) {
			update_post_meta( $post_id, AgentConstants::META_HIDE_SIDEBAR_AGENT_WIDGET, '1' );
		} else {
			delete_post_meta( $post_id, AgentConstants::META_HIDE_SIDEBAR_AGENT_WIDGET );
		}
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	public function sanitize( $value ) {
		if ( is_array( $value ) ) {
			$ids = array_values( array_filter( array_map( 'absint', $value ) ) );
			return wp_json_encode( $ids );
		}

		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return $value;
			}
		}

		return wp_json_encode( array() );
	}

	/**
	 * @param mixed $value Value.
	 * @param array $field Field config.
	 * @return bool|\WP_Error
	 */
	public function validate( $value, $field ) {
		if ( empty( $field['is_required'] ) ) {
			return true;
		}

		$field_name = $field['name'] ?? '';
		$input_name = $field_name;

		if ( false === strpos( $field_name, '_agents' ) && ! empty( $field['group_base_id'] ) ) {
			$input_name = $field['group_base_id'] . '_agents';
		}

		$raw_ids = filter_input( INPUT_POST, $input_name, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$has_agents = is_array( $raw_ids ) && ! empty( array_filter( array_map( 'absint', $raw_ids ) ) );

		if ( ! $has_agents && ! empty( $value ) ) {
			if ( is_array( $value ) ) {
				$has_agents = ! empty( array_filter( array_map( 'absint', $value ) ) );
			} elseif ( is_string( $value ) ) {
				$decoded = json_decode( $value, true );
				$has_agents = is_array( $decoded ) && ! empty( array_filter( array_map( 'absint', $decoded ) ) );
			}
		}

		if ( ! $has_agents ) {
			return new \WP_Error(
				'required_agents',
				sprintf(
					/* translators: %s: field label */
					__( 'At least one agent is required for "%s".', 'havenlytics' ),
					hvnly_esc_html_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Agents' ) )
				)
			);
		}

		return true;
	}

	/**
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'hvnly-agents-section-field' );
		wp_enqueue_script( 'hvnly-agents-section-field' );
	}

	/**
	 * @param int    $post_id    Post ID.
	 * @param string $field_name Agents meta key.
	 * @return int[]
	 */
	private function get_assigned_agent_ids( $post_id, $field_name, $field ) {
		$stored = $this->resolve_group_meta( (int) $post_id, array_merge(
			$field,
			array(
				'group_type' => 'agents',
				'metaKey'    => 'agents',
				'name'       => $field_name,
			)
		), $field_name, 'agents' );

		$ids = $this->parse_agent_ids( $stored );
		if ( ! empty( $ids ) ) {
			return $ids;
		}

		if (
			class_exists( '\HvnlyNab\Core\GroupFieldIdentity' )
			&& \HvnlyNab\Core\GroupFieldIdentity::is_strictly_scoped_field( $field )
		) {
			return array();
		}

		return $this->get_import_agent_ids( $post_id );
	}

	/**
	 * Agent IDs stored by the property import path.
	 *
	 * @param int $post_id Post ID.
	 * @return int[]
	 */
	private function get_import_agent_ids( $post_id ) {
		return $this->parse_agent_ids( get_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS, true ) );
	}

	/**
	 * @param mixed $stored Raw meta value.
	 * @return int[]
	 */
	private function parse_agent_ids( $stored ) {
		if ( empty( $stored ) ) {
			return array();
		}

		if ( is_string( $stored ) ) {
			$decoded = json_decode( $stored, true );
			if ( is_array( $decoded ) ) {
				return array_values( array_filter( array_map( 'absint', $decoded ) ) );
			}
		}

		if ( is_array( $stored ) ) {
			return array_values( array_filter( array_map( 'absint', $stored ) ) );
		}

		return array();
	}

	/**
	 * @param int[] $exclude_ids Assigned IDs.
	 * @return array<int, array{id: int, label: string}>
	 */
	private function get_available_agents( array $exclude_ids ) {
		$query = new \WP_Query(
			array(
				'post_type'      => AgentConstants::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post__not_in'   => $exclude_ids,
				'no_found_rows'  => true,
			)
		);

		$options = array();

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$position = get_post_meta( $post->ID, AgentConstants::META_POSITION, true );
				$label    = $position ? $post->post_title . ' — ' . $position : $post->post_title;

				$options[] = array(
					'id'    => (int) $post->ID,
					'label' => $label,
				);
			}
		}

		wp_reset_postdata();

		return $options;
	}

	/**
	 * @param int    $agent_id   Agent post ID.
	 * @param bool   $is_primary Primary flag.
	 * @param string $field_name Input array name prefix.
	 * @return void
	 */
	private function render_selected_item( int $agent_id, bool $is_primary, string $field_name ): void {
		$title = get_the_title( $agent_id );
		if ( ! $title ) {
			return;
		}

		$position = get_post_meta( $agent_id, AgentConstants::META_POSITION, true );
		$label    = $position ? $title . ' — ' . $position : $title;
		?>
		<li class="hvnly-property-agents-assignment__item" data-agent-id="<?php echo esc_attr( (string) $agent_id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>[]" value="<?php echo esc_attr( (string) $agent_id ); ?>" />
			<span class="hvnly-property-agents-assignment__item-label">
				<?php if ( $is_primary ) : ?>
					<span class="hvnly-property-agents-assignment__primary-badge"><?php esc_html_e( 'Primary', 'havenlytics' ); ?></span>
				<?php endif; ?>
				<?php echo esc_html( $label ); ?>
			</span>
			<button type="button" class="button-link hvnly-property-agents-assignment__remove" aria-label="<?php esc_attr_e( 'Remove agent', 'havenlytics' ); ?>">
				<?php esc_html_e( 'Remove', 'havenlytics' ); ?>
			</button>
		</li>
		<?php
	}
}
