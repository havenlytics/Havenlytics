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
		add_action( 'admin_notices', array( $this, 'identity_admin_notices' ) );

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
		$current     = AgentFields::get_availability( (int) $post->ID );
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
	 * Show identity link validation errors after save.
	 *
	 * @return void
	 */
	public function identity_admin_notices(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || AgentConstants::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$key = 'hvnly_agent_identity_save_error_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( ! is_string( $msg ) || '' === $msg ) {
			return;
		}
		delete_transient( $key );
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_account_box( $post ): void {

		$linked_user = absint( get_post_meta( $post->ID, AgentConstants::META_LINKED_USER_ID, true ) );
		$occupied    = array();
		if ( class_exists( '\HvnlyNab\Workspace\Auth\AgentProvisioner' ) ) {
			$occupied = ( new \HvnlyNab\Workspace\Auth\AgentProvisioner() )->get_occupied_user_links( (int) $post->ID );
		}

		$user = $linked_user > 0 ? get_userdata( $linked_user ) : false;

		?>
		<div class="hvnly-agent-workspace-account">
			<h4 class="hvnly-agent-workspace-account__title"><?php esc_html_e( 'Workspace Account', 'havenlytics' ); ?></h4>

			<?php if ( $user ) : ?>
				<table class="widefat striped hvnly-agent-workspace-account__table">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Status', 'havenlytics' ); ?></th>
							<td><span class="hvnly-agent-workspace-account__ok"><?php esc_html_e( 'Linked', 'havenlytics' ); ?></span></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Username', 'havenlytics' ); ?></th>
							<td><?php echo esc_html( $user->user_login ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Email', 'havenlytics' ); ?></th>
							<td><?php echo esc_html( $user->user_email ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Role', 'havenlytics' ); ?></th>
							<td>
								<?php
								$names = array();
								foreach ( (array) $user->roles as $role_slug ) {
									$wp_roles = wp_roles();
									$names[]  = isset( $wp_roles->role_names[ $role_slug ] )
										? translate_user_role( $wp_roles->role_names[ $role_slug ] )
										: $role_slug;
								}
								echo esc_html( implode( ', ', $names ) );
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Registration Status', 'havenlytics' ); ?></th>
							<td>
								<?php
								if ( class_exists( '\HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus' ) ) {
									$status = \HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus::get_for_user( $linked_user );
									echo wp_kses_post( \HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus::badge_html( (string) $status ) );
								} else {
									echo '&mdash;';
								}
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last Login', 'havenlytics' ); ?></th>
							<td>
								<?php
								if ( class_exists( '\HvnlyNab\Workspace\Auth\AgentActivityTracker' ) ) {
									$gmt = (string) get_user_meta( $linked_user, \HvnlyNab\Workspace\Auth\AgentActivityTracker::META_LAST_LOGIN, true );
									$rel = \HvnlyNab\Workspace\Auth\AgentActivityTracker::format_admin( $gmt );
									echo ( '' === $gmt || '—' === $rel )
										? '<span class="description">' . esc_html__( 'Never', 'havenlytics' ) . '</span>'
										: esc_html( $rel );
								} else {
									echo '&mdash;';
								}
								?>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="hvnly-agent-workspace-account__actions">
					<?php
					$edit_user = get_edit_user_link( $linked_user );
					if ( $edit_user ) {
						printf(
							'<a class="button" href="%s">%s</a> ',
							esc_url( $edit_user ),
							esc_html__( 'Open User', 'havenlytics' )
						);
					}
					if ( class_exists( '\HvnlyNab\Workspace\Auth\AgentIdentityAdminBridge' ) && current_user_can( 'edit_users' ) ) {
						printf(
							'<a class="button" href="%s">%s</a> ',
							esc_url( \HvnlyNab\Workspace\Auth\AgentIdentityAdminBridge::send_password_setup_url( (int) $post->ID ) ),
							esc_html__( 'Send Password Setup Email', 'havenlytics' )
						);
					}
					$reset_url = wp_lostpassword_url();
					printf(
						'<a class="button" href="%s">%s</a>',
						esc_url( $reset_url ),
						esc_html__( 'Reset Password', 'havenlytics' )
					);
					?>
				</p>
			<?php else : ?>
				<p class="hvnly-agent-workspace-account__missing">
					<strong><?php esc_html_e( 'Status:', 'havenlytics' ); ?></strong>
					<?php esc_html_e( 'No Login Account', 'havenlytics' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Publish this Agent with a valid email to create a linked Workspace account automatically, or create one below.', 'havenlytics' ); ?>
				</p>
				<?php if ( current_user_can( 'create_users' ) && class_exists( '\HvnlyNab\Workspace\Auth\AgentIdentityAdminBridge' ) ) : ?>
					<?php
					$agent_email = (string) get_post_meta( $post->ID, AgentConstants::META_EMAIL, true );
					if ( '' === $agent_email || ! is_email( $agent_email ) ) :
						?>
						<p class="notice notice-warning inline">
							<?php esc_html_e( 'Set a valid Agent email (Contact fields) before creating a Workspace account.', 'havenlytics' ); ?>
						</p>
					<?php else : ?>
						<p class="hvnly-agent-workspace-account__actions">
							<?php
							/*
							 * IMPORTANT: Never nest a <form> inside the WP #post form.
							 * A nested </form> closes #post early; side metaboxes render before
							 * normal field metaboxes, so Agent fields + nonce never submit.
							 */
							printf(
								'<a class="button button-primary" href="%s">%s</a>',
								esc_url( \HvnlyNab\Workspace\Auth\AgentIdentityAdminBridge::create_workspace_account_url( (int) $post->ID, true ) ),
								esc_html__( 'Create Workspace Account', 'havenlytics' )
							);
							?>
							<span class="description">
								<?php esc_html_e( 'Sends a WordPress password-setup email (no plain-text password).', 'havenlytics' ); ?>
							</span>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<hr class="hvnly-agent-workspace-account__divider" />

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
					'exclude'           => array_map( 'intval', array_keys( $occupied ) ),
				)
			);

			?>

			<span class="description"><?php esc_html_e( '1:1 identity link for Workspace. Each WordPress user may link to only one Agent CPT.', 'havenlytics' ); ?></span>

		</p>

		<?php if ( ! empty( $occupied ) ) : ?>
			<p class="description" style="color:#996800;">
				<?php
				esc_html_e( 'Already linked (cannot select):', 'havenlytics' );
				echo ' ';
				$bits = array();
				foreach ( $occupied as $uid => $aid ) {
					$u      = get_userdata( (int) $uid );
					$bits[] = sprintf(
						/* translators: 1: user login, 2: agent ID */
						esc_html__( '%1$s → Agent #%2$d', 'havenlytics' ),
						$u ? $u->user_login : (string) $uid,
						(int) $aid
					);
				}
				echo esc_html( implode( '; ', $bits ) );
				?>
			</p>
		<?php endif; ?>

		<?php if ( $linked_user > 0 ) : ?>
			<p class="hvnly-agent-metabox__field">
				<label>
					<input type="checkbox" name="hvnly_confirm_identity_relink" value="1" />
					<?php esc_html_e( 'I confirm changing or clearing this linked WordPress user (identity lock).', 'havenlytics' ); ?>
				</label>
			</p>
		<?php endif; ?>

		<?php
	}



	/**

	 * @param int                  $post_id Post ID.

	 * @param array<string, mixed> $field   Field config.

	 * @return void

	 */

	private function render_field( int $post_id, array $field ): void {

		$meta_key = (string) $field['key'];

		$value = get_post_meta( $post_id, $meta_key, true );

		$id = str_replace( array( '_', '-' ), '', $meta_key );

		$type = (string) $field['type'];

		$full = ! empty( $field['full_width'] );

		$class = 'hvnly-agent-metabox__field' . ( $full ? ' hvnly-agent-metabox__field--full' : '' );

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


