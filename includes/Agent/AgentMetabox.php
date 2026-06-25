<?php

/**

 * Agent profile metaboxes.

 *

 * @package HvnlyNab\Agent

 * @since   3.0.2

 */



namespace HvnlyNab\Agent;



defined( 'ABSPATH' ) || exit;



/**

 * Admin fields for agent contact and social profiles.

 *

 * @since 3.0.2

 */

class AgentMetabox {



	/** @var string */

	private const NONCE_ACTION = 'hvnly_agent_profile_save';



	/** @var string */

	private const NONCE_FIELD = 'hvnly_agent_profile_nonce';



	/**

	 * Register hooks.

	 */

	public function __construct() {

		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );

		add_action( 'save_post_' . AgentConstants::POST_TYPE, array( $this, 'save' ), 10, 2 );

		add_action( 'edit_form_after_title', array( $this, 'render_title_help' ) );

	}



	/**

	 * @return void

	 */

	public function register_meta_boxes(): void {

		foreach ( AgentFields::group_labels() as $group => $label ) {

			add_meta_box(

				'hvnly-agent-' . $group,

				esc_html( $label ),

				array( $this, 'render_group_box' ),

				AgentConstants::POST_TYPE,

				'normal',

				'professional' === $group ? 'high' : 'default',

				array( 'group' => $group )

			);

		}



		add_meta_box(

			'hvnly-agent-availability',

			esc_html__( 'Availability Status', 'havenlytics' ),

			array( $this, 'render_availability_box' ),

			AgentConstants::POST_TYPE,

			'side',

			'high'

		);



		add_meta_box(

			'hvnly-agent-account',

			esc_html__( 'WordPress Account', 'havenlytics' ),

			array( $this, 'render_account_box' ),

			AgentConstants::POST_TYPE,

			'side',

			'default'

		);



		add_meta_box(

			'hvnly-agent-photo-help',

			esc_html__( 'Profile Photo', 'havenlytics' ),

			array( $this, 'render_photo_help_box' ),

			AgentConstants::POST_TYPE,

			'side',

			'high'

		);

	}



	/**

	 * @param \WP_Post $post Post object.

	 * @return void

	 */

	public function render_title_help( $post ): void {

		if ( AgentConstants::POST_TYPE !== $post->post_type ) {

			return;

		}

		?>

		<p class="description" style="margin:8px 0 16px;">

			<?php esc_html_e( 'Enter the agent’s full name as the title. Use the main content editor below for the agent biography.', 'havenlytics' ); ?>

		</p>

		<?php

	}



	/**

	 * @param \WP_Post $post Post object.

	 * @return void

	 */

	public function render_photo_help_box( $post ): void {

		unset( $post );

		?>

		<p><?php esc_html_e( 'Set the profile photo using the Featured Image panel. This image appears on property pages and agent profiles.', 'havenlytics' ); ?></p>

		<?php

	}



	/**

	 * @param \WP_Post $post  Post object.

	 * @param array    $box   Metabox args.

	 * @return void

	 */

	public function render_group_box( $post, array $box ): void {

		$group = isset( $box['args']['group'] ) ? (string) $box['args']['group'] : '';

		$groups = AgentFields::field_groups();



		if ( ! isset( $groups[ $group ] ) ) {

			return;

		}



		if ( 'professional' === $group ) {

			wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		}



		$modifier = 'social' === $group ? ' hvnly-agent-metabox--social' : '';

		echo '<div class="hvnly-agent-metabox' . esc_attr( $modifier ) . '">';



		foreach ( $groups[ $group ] as $field ) {

			$this->render_field( $post->ID, $field );

		}



		echo '</div>';

	}



	/**

	 * @param \WP_Post $post Post object.

	 * @return void

	 */

	public function render_availability_box( $post ): void {
		$current = AgentFields::get_availability( (int) $post->ID );
		$definitions = function_exists( 'hvnly_get_agent_availability_definitions' )
			? hvnly_get_agent_availability_definitions()
			: array();
		?>
		<fieldset class="hvnly-agent-availability-fieldset">
			<legend class="screen-reader-text"><?php esc_html_e( 'Availability Status', 'havenlytics' ); ?></legend>
			<?php foreach ( $definitions as $slug => $definition ) : ?>
				<label class="hvnly-agent-availability-option hvnly-agent-availability-option--<?php echo esc_attr( $slug ); ?>">
					<input
						type="radio"
						name="hvnly_agent_availability"
						value="<?php echo esc_attr( $slug ); ?>"
						<?php checked( $current, $slug ); ?>
					/>
					<span class="hvnly-agent-availability-option__dot" aria-hidden="true"></span>
					<span class="hvnly-agent-availability-option__text">
						<strong><?php echo esc_html( (string) $definition['label'] ); ?></strong>
						<span class="description"><?php echo esc_html( (string) $definition['description'] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Shown to visitors on agent cards and contact forms so they know whether inquiries are being accepted.', 'havenlytics' ); ?></p>
		<?php
	}

	/**
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_account_box( $post ): void {

		$linked_user = absint( get_post_meta( $post->ID, AgentConstants::META_LINKED_USER_ID, true ) );

		?>

		<p class="hvnly-agent-metabox__field">

			<label for="hvnlyagentlinkeduser"><strong><?php esc_html_e( 'Linked WordPress User', 'havenlytics' ); ?></strong></label>

			<?php

			wp_dropdown_users(

				array(

					'name'              => AgentConstants::META_LINKED_USER_ID,

					'id'                => 'hvnlyagentlinkeduser',

					'selected'          => $linked_user,

					'show_option_none'  => __( '— None —', 'havenlytics' ),

					'option_none_value' => '0',

					'class'             => 'widefat',

				)

			);

			?>

			<span class="description"><?php esc_html_e( 'Optional. Links this profile to a WordPress user for legacy compatibility and future agent dashboard access.', 'havenlytics' ); ?></span>

		</p>

		<?php

	}



	/**

	 * @param int                  $post_id Post ID.

	 * @param array<string, mixed> $field   Field config.

	 * @return void

	 */

	private function render_field( int $post_id, array $field ): void {

		$meta_key = (string) $field['key'];

		$value    = get_post_meta( $post_id, $meta_key, true );

		$id       = str_replace( array( '_', '-' ), '', $meta_key );

		$type     = (string) $field['type'];

		$full     = ! empty( $field['full_width'] );

		$class    = 'hvnly-agent-metabox__field' . ( $full ? ' hvnly-agent-metabox__field--full' : '' );

		?>

		<p class="<?php echo esc_attr( $class ); ?>">

			<label for="<?php echo esc_attr( $id ); ?>">

				<strong><?php echo esc_html( (string) $field['label'] ); ?></strong>

			</label>

			<?php if ( 'textarea' === $type ) : ?>

				<textarea

					class="widefat"

					id="<?php echo esc_attr( $id ); ?>"

					name="<?php echo esc_attr( $meta_key ); ?>"

					rows="3"

					placeholder="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>"

				><?php echo esc_textarea( (string) $value ); ?></textarea>

			<?php else : ?>

				<input

					type="<?php echo esc_attr( $type ); ?>"

					class="widefat"

					id="<?php echo esc_attr( $id ); ?>"

					name="<?php echo esc_attr( $meta_key ); ?>"

					value="<?php echo esc_attr( (string) $value ); ?>"

					placeholder="<?php echo esc_attr( (string) ( $field['placeholder'] ?? '' ) ); ?>"

				/>

			<?php endif; ?>

		</p>

		<?php

	}



	/**

	 * @param int      $post_id Post ID.

	 * @param \WP_Post $post    Post object.

	 * @return void

	 */

	public function save( int $post_id, $post ): void {

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {

			return;

		}



		if ( wp_is_post_revision( $post_id ) ) {

			return;

		}



		if ( ! function_exists( 'hvnly_current_user_can_edit_post_type' ) || ! hvnly_current_user_can_edit_post_type( $post_id, AgentConstants::POST_TYPE ) ) {

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



		unset( $post );



		AgentFields::save_from_request( $post_id );

		AgentFields::save_availability_from_request( $post_id );



		/**

		 * Fires after an agent profile is saved.

		 *

		 * @since 3.0.2

		 *

		 * @param int $post_id Agent post ID.

		 */

		do_action( 'hvnly_agent_profile_saved', $post_id );

	}

}


