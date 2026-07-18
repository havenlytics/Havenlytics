<?php
/**
 * Property assigned agents metabox.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI for assigning one or more Agent CPT profiles to a property.
 *
 * @since 3.0.2
 */
class PropertyAgentAssignment {

	/** @var string */
	private const NONCE_ACTION = 'hvnly_property_agents_save';

	/** @var string */
	private const NONCE_FIELD = 'hvnly_property_agents_nonce';

	/** @var string */
	private const INPUT_NAME = 'hvnly_property_agents';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . AgentConstants::PROPERTY_POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_admin_list_by_assigned_agent' ) );
		add_action( 'admin_notices', array( $this, 'assigned_agent_filter_notice' ) );
	}

	/**
	 * Honor ?hvnly_assigned_agent= on the Properties admin list.
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function filter_admin_list_by_assigned_agent( $query ): void {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : '';
		if ( AgentConstants::PROPERTY_POST_TYPE !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$agent_id = isset( $_GET['hvnly_assigned_agent'] ) ? absint( wp_unslash( (string) $_GET['hvnly_assigned_agent'] ) ) : 0;
		if ( $agent_id <= 0 ) {
			return;
		}

		if ( AgentConstants::POST_TYPE !== get_post_type( $agent_id ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$ids = AgentPropertiesQuery::get_assigned_property_ids(
			$agent_id,
			array( 'publish', 'pending', 'draft', 'private' )
		);

		$query->set( 'post__in', ! empty( $ids ) ? $ids : array( 0 ) );
		$query->set( 'orderby', 'post__in' );
	}

	/**
	 * Surface the active assigned-agent filter on the Properties list.
	 *
	 * @return void
	 */
	public function assigned_agent_filter_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit' !== $screen->base || AgentConstants::PROPERTY_POST_TYPE !== $screen->post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$agent_id = isset( $_GET['hvnly_assigned_agent'] ) ? absint( wp_unslash( (string) $_GET['hvnly_assigned_agent'] ) ) : 0;
		if ( $agent_id <= 0 || AgentConstants::POST_TYPE !== get_post_type( $agent_id ) ) {
			return;
		}

		$clear_url = remove_query_arg( 'hvnly_assigned_agent' );
		$title     = get_the_title( $agent_id );
		$name      = is_string( $title ) && '' !== $title ? $title : sprintf( '#%d', $agent_id );

		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: agent display name */
					__( 'Showing properties assigned to %s.', 'havenlytics' ),
					$name
				)
			),
			esc_url( $clear_url ),
			esc_html__( 'Clear filter', 'havenlytics' )
		);
	}

	/**
	 * @return void
	 */
	public function register_meta_box(): void {
		add_meta_box(
			'hvnly-property-assigned-agents',
			esc_html__( 'Assigned Agents', 'havenlytics' ),
			array( $this, 'render' ),
			AgentConstants::PROPERTY_POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || AgentConstants::PROPERTY_POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'hvnly-property-agent-assignment',
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-property-agent-assignment.css',
			array(),
			defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2'
		);

		wp_enqueue_script(
			'hvnly-property-agent-assignment',
			HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-property-agent-assignment.js',
			array(),
			defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2',
			true
		);

		wp_localize_script(
			'hvnly-property-agent-assignment',
			'hvnlyPropertyAgentAssignment',
			array(
				'i18n' => array(
					'addAgent'      => __( 'Add agent…', 'havenlytics' ),
					'remove'        => __( 'Remove', 'havenlytics' ),
					'primaryHint'   => __( 'Primary agent', 'havenlytics' ),
					'alreadyAdded'  => __( 'This agent is already assigned.', 'havenlytics' ),
					'noAgents'      => __( 'No agents selected.', 'havenlytics' ),
				),
			)
		);
	}

	/**
	 * @param \WP_Post $post Property post.
	 * @return void
	 */
	public function render( $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$assigned_ids = PropertyAgentResolver::get_assigned_agent_ids( (int) $post->ID );
		$has_meta_key = metadata_exists( 'post', $post->ID, AgentConstants::META_PROPERTY_AGENTS );
		$legacy_user  = absint( get_post_meta( $post->ID, AgentConstants::META_PROPERTY_AGENT_LEGACY, true ) );
		$available    = $this->get_available_agents( $assigned_ids );

		?>
		<div class="hvnly-property-agents-assignment" id="hvnlyPropertyAgentsAssignment">
			<p class="description">
				<?php esc_html_e( 'Assign one or more agents to this property. The first agent is the primary contact.', 'havenlytics' ); ?>
			</p>

			<?php if ( ! $has_meta_key && $legacy_user > 0 ) : ?>
				<div class="hvnly-property-agents-assignment__legacy-notice">
					<p>
						<?php
						printf(
							/* translators: %s: WordPress user display name */
							esc_html__( 'Legacy agent assignment detected (user: %s). Saving new assignments below will not remove the legacy user meta.', 'havenlytics' ),
							esc_html( get_the_author_meta( 'display_name', $legacy_user ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<ul class="hvnly-property-agents-assignment__list" id="hvnlyPropertyAgentsList" aria-live="polite">
				<?php
				if ( ! empty( $assigned_ids ) ) {
					foreach ( $assigned_ids as $index => $agent_id ) {
						$this->render_selected_item( (int) $agent_id, 0 === (int) $index );
					}
				}
				?>
			</ul>

			<p class="hvnly-property-agents-assignment__empty<?php echo empty( $assigned_ids ) ? ' is-visible' : ''; ?>" id="hvnlyPropertyAgentsEmpty">
				<?php esc_html_e( 'No agents assigned yet.', 'havenlytics' ); ?>
			</p>

			<label class="screen-reader-text" for="hvnlyPropertyAgentPicker">
				<?php esc_html_e( 'Add agent', 'havenlytics' ); ?>
			</label>
			<select id="hvnlyPropertyAgentPicker" class="widefat">
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
		<?php
	}

	/**
	 * @param int  $agent_id  Agent post ID.
	 * @param bool $is_primary Whether this is the primary agent.
	 * @return void
	 */
	private function render_selected_item( int $agent_id, bool $is_primary ): void {
		$title = get_the_title( $agent_id );
		if ( ! $title ) {
			return;
		}

		$position = get_post_meta( $agent_id, AgentConstants::META_POSITION, true );
		$label    = $position ? $title . ' — ' . $position : $title;
		?>
		<li class="hvnly-property-agents-assignment__item" data-agent-id="<?php echo esc_attr( (string) $agent_id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( self::INPUT_NAME ); ?>[]" value="<?php echo esc_attr( (string) $agent_id ); ?>" />
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

	/**
	 * @param int[] $exclude_ids Already assigned IDs.
	 * @return array<int, array{id: int, label: string}>
	 */
	private function get_available_agents( array $exclude_ids ): array {
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
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save( int $post_id, $post ): void {
		unset( $post );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! function_exists( 'hvnly_current_user_can_edit_post_type' ) || ! hvnly_current_user_can_edit_post_type( $post_id, AgentConstants::PROPERTY_POST_TYPE ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_ids = isset( $_POST[ self::INPUT_NAME ] ) ? wp_unslash( $_POST[ self::INPUT_NAME ] ) : array();
		$ids     = PropertyAgentResolver::sanitize_agent_id_list( $raw_ids );

		update_post_meta( $post_id, AgentConstants::META_PROPERTY_AGENTS, $ids );

		/**
		 * Fires after property agent assignments are saved.
		 *
		 * @since 3.0.2
		 *
		 * @param int   $post_id    Property post ID.
		 * @param int[] $agent_ids  Assigned Agent CPT IDs (ordered).
		 */
		do_action( 'hvnly_property_agents_saved', $post_id, $ids );
	}
}
