<?php
/**
 * Agency taxonomy for Agent CPT.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

defined( 'ABSPATH' ) || exit;

/**
 * Registers {@see AgentConstants::TAXONOMY_AGENCY} and extended term fields.
 *
 * @since 3.0.2
 */
final class AgentAgencyTaxonomy {

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy' ), 9 );
		add_action( 'init', array( $this, 'register_term_meta' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_save_term_meta_from_request' ) );

		foreach ( array( 'created', 'edited' ) as $hook ) {
			add_action( AgentConstants::TAXONOMY_AGENCY . "_{$hook}", array( $this, 'save_term_meta' ), 10, 2 );
		}

		add_action( 'created_term', array( $this, 'save_term_meta_on_term_hook' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'save_term_meta_on_term_hook' ), 10, 3 );

		add_action( AgentConstants::TAXONOMY_AGENCY . '_add_form_fields', array( $this, 'render_add_fields' ) );
		add_action( AgentConstants::TAXONOMY_AGENCY . '_edit_form_fields', array( $this, 'render_edit_fields' ), 10, 2 );

		add_filter( 'manage_edit-' . AgentConstants::TAXONOMY_AGENCY . '_columns', array( $this, 'term_columns' ) );
		add_filter( 'manage_' . AgentConstants::TAXONOMY_AGENCY . '_custom_column', array( $this, 'term_column_content' ), 10, 3 );
	}

	/**
	 * @return void
	 */
	public function register_taxonomy(): void {
		$single_slug = class_exists( '\HvnlyNab\Core\PermalinkSettings' )
			? \HvnlyNab\Core\PermalinkSettings::get_agency_single_slug()
			: 'agency';

		$labels = array(
			'name'              => esc_html__( 'Agencies', 'havenlytics' ),
			'singular_name'     => esc_html__( 'Agency', 'havenlytics' ),
			'search_items'      => esc_html__( 'Search Agencies', 'havenlytics' ),
			'all_items'         => esc_html__( 'All Agencies', 'havenlytics' ),
			'parent_item'       => esc_html__( 'Parent Agency', 'havenlytics' ),
			'parent_item_colon' => esc_html__( 'Parent Agency:', 'havenlytics' ),
			'edit_item'         => esc_html__( 'Edit Agency', 'havenlytics' ),
			'update_item'       => esc_html__( 'Update Agency', 'havenlytics' ),
			'add_new_item'      => esc_html__( 'Add New Agency', 'havenlytics' ),
			'new_item_name'     => esc_html__( 'New Agency Name', 'havenlytics' ),
			'menu_name'         => esc_html__( 'Agencies', 'havenlytics' ),
		);

		register_taxonomy(
			AgentConstants::TAXONOMY_AGENCY,
			array( AgentConstants::POST_TYPE ),
			array(
				'labels'            => $labels,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => false,
				'hierarchical'      => true,
				'rewrite'           => array(
					'slug'         => sanitize_title( (string) $single_slug ),
					'with_front'   => false,
					'hierarchical' => true,
				),
				'capabilities'      => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);

		do_action( 'hvnly_agent_agency_taxonomy_registered', AgentConstants::TAXONOMY_AGENCY );
	}

	/**
	 * Register agency term meta for reliable persistence.
	 *
	 * @return void
	 */
	public function register_term_meta(): void {
		if ( ! taxonomy_exists( AgentConstants::TAXONOMY_AGENCY ) ) {
			return;
		}

		$auth = static function (): bool {
			$tax = get_taxonomy( AgentConstants::TAXONOMY_AGENCY );

			return $tax && current_user_can( $tax->cap->edit_terms );
		};

		register_term_meta(
			AgentConstants::TAXONOMY_AGENCY,
			AgencyFields::META_LOGO_ID,
			array(
				'type'              => 'integer',
				'single'            => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => false,
				'auth_callback'     => $auth,
			)
		);

		foreach ( AgencyFields::field_groups() as $fields ) {
			foreach ( $fields as $field ) {
				$key  = (string) $field['key'];
				$type = (string) $field['type'];

				if ( AgencyFields::META_LOGO_ID === $key || 'media' === $type ) {
					continue;
				}

				register_term_meta(
					AgentConstants::TAXONOMY_AGENCY,
					$key,
					array(
						'type'              => 'string',
						'single'            => true,
						'sanitize_callback' => 'sanitize_text_field',
						'show_in_rest'      => false,
						'auth_callback'     => $auth,
					)
				);
			}
		}
	}

	/**
	 * Fallback save when editing a term from term.php (editedtag).
	 *
	 * @return void
	 */
	public function maybe_save_term_meta_from_request(): void {
		if ( ! isset( $_POST['action'], $_POST['taxonomy'] ) ) {
			return;
		}

		$action   = sanitize_key( wp_unslash( (string) $_POST['action'] ) );
		$taxonomy = sanitize_key( wp_unslash( (string) $_POST['taxonomy'] ) );

		if ( AgentConstants::TAXONOMY_AGENCY !== $taxonomy || 'editedtag' !== $action ) {
			return;
		}

		$term_id = isset( $_POST['tag_ID'] ) ? absint( wp_unslash( $_POST['tag_ID'] ) ) : 0;
		if ( $term_id <= 0 ) {
			return;
		}

		check_admin_referer( 'update-tag_' . $term_id );
		AgencyFields::save_from_request( $term_id );
	}

	/**
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || AgentConstants::TAXONOMY_AGENCY !== $screen->taxonomy ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'hvnly-agent-admin',
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-agent-admin.css',
			array(),
			defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2'
		);
		wp_enqueue_script(
			'hvnly-agency-admin',
			HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-agency-admin.js',
			array( 'jquery', 'media-upload', 'media-views' ),
			defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2',
			true
		);
		wp_localize_script(
			'hvnly-agency-admin',
			'hvnlyAgencyAdmin',
			array(
				'selectLogo' => __( 'Select Agency Logo', 'havenlytics' ),
				'useLogo'    => __( 'Use this logo', 'havenlytics' ),
				'removeLogo' => __( 'Remove logo', 'havenlytics' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public function render_add_fields(): void {
		$this->output_term_fields();
	}

	/**
	 * @param \WP_Term $term Term object.
	 * @return void
	 */
	public function render_edit_fields( $term ): void {
		$this->output_term_fields( $term, true );
	}

	/**
	 * @param \WP_Term|null $term    Term object.
	 * @param bool          $is_edit Edit screen.
	 * @return void
	 */
	private function output_term_fields( $term = null, bool $is_edit = false ): void {
		$term_id = $term instanceof \WP_Term ? (int) $term->term_id : 0;
		$profile = $term_id ? AgencyFields::get_profile( $term_id ) : array();

		wp_nonce_field( 'hvnly_agency_term_save', 'hvnly_agency_term_nonce' );

		$groups = array(
			'profile' => __( 'Agency Profile', 'havenlytics' ),
			'map'     => __( 'Location', 'havenlytics' ),
			'contact' => __( 'Contact Details', 'havenlytics' ),
			'social'  => __( 'Social Profiles', 'havenlytics' ),
		);

		foreach ( AgencyFields::field_groups() as $group_key => $fields ) {
			$heading = $groups[ $group_key ] ?? ucfirst( $group_key );

			if ( $is_edit ) {
				echo '<tr class="form-field hvnly-agency-fields__group"><th colspan="2"><h3 class="hvnly-agency-fields__heading">' . esc_html( $heading ) . '</h3></th></tr>';
			} else {
				echo '<div class="hvnly-agency-fields__group"><h3 class="hvnly-agency-fields__heading">' . esc_html( $heading ) . '</h3>';
			}

			if ( 'map' === $group_key ) {
				if ( $is_edit ) {
					echo '<tr class="form-field"><td colspan="2"><p class="description">';
					esc_html_e( 'Coordinates are optional. When set, the map uses your Havenlytics → Settings → Map configuration automatically.', 'havenlytics' );
					echo '</p></td></tr>';
				} else {
					echo '<p class="description">';
					esc_html_e( 'Coordinates are optional. When set, the map uses your Havenlytics → Settings → Map configuration automatically.', 'havenlytics' );
					echo '</p>';
				}
			}

			foreach ( $fields as $field ) {
				$this->render_field_row( $field, $profile, $is_edit );
			}

			if ( ! $is_edit ) {
				echo '</div>';
			}
		}
	}

	/**
	 * @param array<string, mixed> $field   Field config.
	 * @param array<string, mixed> $profile Agency profile values.
	 * @param bool                 $is_edit Edit screen.
	 * @return void
	 */
	private function render_field_row( array $field, array $profile, bool $is_edit ): void {
		$key   = (string) $field['key'];
		$label = (string) $field['label'];
		$type  = (string) $field['type'];
		$value = '';

		if ( AgencyFields::META_LOGO_ID === $key ) {
			$value = isset( $profile['logo_id'] ) ? (string) $profile['logo_id'] : '';
		} elseif ( AgencyFields::META_MAP_LAT === $key ) {
			$value = isset( $profile['map_lat'] ) ? (string) $profile['map_lat'] : '';
		} elseif ( AgencyFields::META_MAP_LNG === $key ) {
			$value = isset( $profile['map_lng'] ) ? (string) $profile['map_lng'] : '';
		} else {
			$short = str_replace( 'hvnly_agency_', '', $key );
			$value = isset( $profile[ $short ] ) ? (string) $profile[ $short ] : '';
		}

		if ( 'media' === $type ) {
			$this->render_logo_field( $key, $label, absint( $value ), $is_edit );
			return;
		}

		if ( $is_edit ) {
			echo '<tr class="form-field"><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			$this->render_input( $field, $value );
			echo '</td></tr>';
			return;
		}

		echo '<div class="form-field"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label>';
		$this->render_input( $field, $value );
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $field Field config.
	 * @param string               $value Current value.
	 * @return void
	 */
	private function render_input( array $field, string $value ): void {
		$key         = (string) $field['key'];
		$type        = (string) $field['type'];
		$placeholder = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';

		if ( 'select' === $type ) {
			echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" class="regular-text">';
			foreach ( (array) ( $field['options'] ?? array() ) as $opt_value => $opt_label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( (string) $opt_value ),
					selected( $value, (string) $opt_value, false ),
					esc_html( (string) $opt_label )
				);
			}
			echo '</select>';
			return;
		}

		if ( 'textarea' === $type ) {
			printf(
				'<textarea name="%s" id="%s" rows="3" class="large-text" placeholder="%s">%s</textarea>',
				esc_attr( $key ),
				esc_attr( $key ),
				esc_attr( $placeholder ),
				esc_textarea( $value )
			);
			return;
		}

		printf(
			'<input type="%s" name="%s" id="%s" value="%s" class="regular-text" placeholder="%s" />',
			esc_attr( 'url' === $type ? 'url' : ( 'email' === $type ? 'email' : 'text' ) ),
			esc_attr( $key ),
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	/**
	 * @param string $key     Meta key.
	 * @param string $label   Field label.
	 * @param int    $logo_id Attachment ID.
	 * @param bool   $is_edit Edit screen.
	 * @return void
	 */
	private function render_logo_field( string $key, string $label, int $logo_id, bool $is_edit ): void {
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

		if ( $is_edit ) {
			echo '<tr class="form-field"><th scope="row"><label>' . esc_html( $label ) . '</label></th><td>';
		} else {
			echo '<div class="form-field"><label>' . esc_html( $label ) . '</label>';
		}
		?>
		<div class="hvnly-agency-logo-field" data-hvnly-agency-logo>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $logo_id ); ?>" />
			<div class="hvnly-agency-logo-field__preview">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
				<?php endif; ?>
			</div>
			<p>
				<button type="button" class="button hvnly-agency-logo-field__select"><?php esc_html_e( 'Upload Logo', 'havenlytics' ); ?></button>
				<button type="button" class="button-link-delete hvnly-agency-logo-field__remove" <?php echo $logo_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'havenlytics' ); ?></button>
			</p>
		</div>
		<?php
		if ( $is_edit ) {
			echo '</td></tr>';
		} else {
			echo '</div>';
		}
	}

	/**
	 * @param int $term_id Term ID.
	 * @param int $tt_id   Term taxonomy row ID.
	 * @return void
	 */
	public function save_term_meta( int $term_id, $tt_id = 0 ): void {
		unset( $tt_id );
		AgencyFields::save_from_request( $term_id );
	}

	/**
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy row ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function save_term_meta_on_term_hook( int $term_id, $tt_id, string $taxonomy ): void {
		unset( $tt_id );

		if ( AgentConstants::TAXONOMY_AGENCY !== $taxonomy ) {
			return;
		}

		AgencyFields::save_from_request( $term_id );
	}

	/**
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public function term_columns( array $columns ): array {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'cb' === $key ) {
				$new['hvnly_agency_logo'] = esc_html__( 'Logo', 'havenlytics' );
			}
		}

		if ( ! isset( $new['hvnly_agency_logo'] ) ) {
			$new = array_merge(
				array( 'hvnly_agency_logo' => esc_html__( 'Logo', 'havenlytics' ) ),
				$new
			);
		}

		$new['hvnly_agency_email'] = esc_html__( 'Email', 'havenlytics' );
		$new['hvnly_agency_phone'] = esc_html__( 'Phone', 'havenlytics' );

		return $new;
	}

	/**
	 * @param string $content     Column content.
	 * @param string $column_name Column key.
	 * @param int    $term_id     Term ID.
	 * @return string
	 */
	public function term_column_content( string $content, string $column_name, $term_id ): string {
		$term_id  = absint( $term_id );
		$rendered = $this->render_term_column( $column_name, $term_id );

		return '' !== $rendered ? $rendered : $content;
	}

	/**
	 * @param string $column_name Column key.
	 * @param int    $term_id     Term ID.
	 * @return void
	 * @deprecated 3.0.2 Use term_column_content() via the manage_{taxonomy}_custom_column filter.
	 */
	public function term_column_output( string $column_name, int $term_id ): void {
		$output = $this->render_term_column( $column_name, $term_id );

		if ( '' !== $output ) {
			echo wp_kses_post( $output );
		}
	}

	/**
	 * @param string $column_name Column key.
	 * @param int    $term_id     Term ID.
	 * @return string
	 */
	private function render_term_column( string $column_name, int $term_id ): string {
		if ( $term_id <= 0 ) {
			return '';
		}

		$profile = AgencyFields::get_profile( $term_id );

		if ( 'hvnly_agency_logo' === $column_name ) {
			return AgencyFields::get_admin_list_logo_html( $term_id );
		}

		if ( 'hvnly_agency_email' === $column_name ) {
			return ! empty( $profile['email'] ) ? esc_html( (string) $profile['email'] ) : '&mdash;';
		}

		if ( 'hvnly_agency_phone' === $column_name ) {
			return ! empty( $profile['phone'] ) ? esc_html( (string) $profile['phone'] ) : '&mdash;';
		}

		return '';
	}
}
