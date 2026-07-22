<?php
/**
 * Agent custom post type registration.
 *
 * @package HvnlyNab\Agent
 * @since   3.0.2
 */

namespace HvnlyNab\Agent;

use HvnlyNab\Database\Base\Custom_Posts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the {@see AgentConstants::POST_TYPE} CPT (menu via {@see AgentAdminMenu}).
 *
 * @since 3.0.2
 */
class AgentPostType extends Custom_Posts {

	/**
	 * @var string
	 */
	private $slug = AgentConstants::POST_TYPE;

	/**
	 * Register hooks beyond the parent constructor.
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'manage_' . $this->slug . '_posts_columns', array( $this, 'admin_columns' ), 10 );
		add_action( 'manage_' . $this->slug . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		// Sprint 20A owns the professional column set at priority 30.
	}

	/**
	 * {@inheritdoc}
	 */
	public function register_custom_post_type(): void {
		$single_slug = class_exists( '\HvnlyNab\Core\PermalinkSettings' )
			? \HvnlyNab\Core\PermalinkSettings::get_agent_single_slug()
			: apply_filters( 'hvnly_agent_rewrite_slug', 'agent' );
		$archive_slug = class_exists( '\HvnlyNab\Core\PermalinkSettings' )
			? \HvnlyNab\Core\PermalinkSettings::get_agent_archive_slug()
			: $single_slug;
		// Block editor for this CPT remains gated by PluginGutenbergSupport
		// (use_block_editor_for_post_type). REST must stay enabled so block
		// Inspector pickers can use /wp/v2/hvnly_agent.

		$settings = array(
			'description'         => __( 'Real estate agent profiles for Havenlytics.', 'havenlytics' ),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			// Always expose in REST (picker / blocks). Not the same as Gutenberg editing.
			'show_in_rest'        => true,
			'has_archive'         => $archive_slug,
			'menu_icon'           => 'dashicons-businessman',
			'hierarchical'        => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			'rewrite'             => array(
				'slug'       => sanitize_title( (string) $single_slug ),
				'with_front' => false,
			),
			'query_var'           => true,
		);

		$labels = array(
			'name'               => esc_html__( 'Agents', 'havenlytics' ),
			'singular_name'      => esc_html__( 'Agent', 'havenlytics' ),
			'menu_name'          => esc_html__( 'Agents', 'havenlytics' ),
			'all_items'          => esc_html__( 'All Agents', 'havenlytics' ),
			'add_new'            => esc_html__( 'Add Agent', 'havenlytics' ),
			'add_new_item'       => esc_html__( 'Add New Agent', 'havenlytics' ),
			'edit_item'          => esc_html__( 'Edit Agent', 'havenlytics' ),
			'new_item'           => esc_html__( 'New Agent', 'havenlytics' ),
			'view_item'          => esc_html__( 'View Agent Profile', 'havenlytics' ),
			'search_items'       => esc_html__( 'Search Agents', 'havenlytics' ),
			'not_found'          => esc_html__( 'No agents found.', 'havenlytics' ),
			'not_found_in_trash' => esc_html__( 'No agents found in Trash.', 'havenlytics' ),
		);

		$this->init(
			$this->slug,
			esc_html__( 'Agent', 'havenlytics' ),
			esc_html__( 'Agents', 'havenlytics' ),
			$settings,
			$labels
		);

		/**
		 * Fires after the Havenlytics Agent post type is registered.
		 *
		 * @since 3.0.2
		 */
		do_action( 'hvnly_agent_post_type_registered', $this->slug );
	}

	/**
	 * @param string[] $columns Existing columns.
	 * @return string[]
	 */
	public function admin_columns( array $columns ): array {
		$new = array();

		if ( isset( $columns['cb'] ) ) {
			$new['cb'] = $columns['cb'];
		}

		$new['hvnly_agent_photo']    = esc_html__( 'Photo', 'havenlytics' );
		$new['title']                = esc_html__( 'Full Name', 'havenlytics' );
		$new['hvnly_agent_position'] = esc_html__( 'Position', 'havenlytics' );
		$new['hvnly_agent_company']  = esc_html__( 'Company', 'havenlytics' );
		$new['hvnly_agent_email']    = esc_html__( 'Email', 'havenlytics' );
		$new['hvnly_agent_phone']    = esc_html__( 'Phone', 'havenlytics' );
		$new['hvnly_agent_availability'] = esc_html__( 'Availability', 'havenlytics' );
		$new['date']                 = $columns['date'] ?? esc_html__( 'Date', 'havenlytics' );

		return $new;
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function admin_column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'hvnly_agent_photo':
				$uid        = absint( get_post_meta( $post_id, AgentConstants::META_LINKED_USER_ID, true ) );
				$avatar_url = \HvnlyNab\Common\AvatarService::resolve_url( $post_id, $uid, 40, 'thumbnail' );
				echo \HvnlyNab\Common\AvatarService::img_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
					$avatar_url,
					array(
						'class'  => 'hvnly-agent-admin-photo',
						'width'  => 40,
						'height' => 40,
						'alt'    => '',
					)
				);
				break;

			case 'hvnly_agent_position':
				$position = get_post_meta( $post_id, AgentConstants::META_POSITION, true );
				echo esc_html( $position ? (string) $position : '—' );
				break;

			case 'hvnly_agent_company':
				$company = get_post_meta( $post_id, AgentConstants::META_COMPANY, true );
				echo esc_html( $company ? (string) $company : '—' );
				break;

			case 'hvnly_agent_email':
				$email = get_post_meta( $post_id, AgentConstants::META_EMAIL, true );
				if ( $email && is_email( (string) $email ) ) {
					echo '<a href="mailto:' . esc_attr( (string) $email ) . '">' . esc_html( (string) $email ) . '</a>';
				} else {
					echo '&mdash;';
				}
				break;

			case 'hvnly_agent_phone':
				$phone = get_post_meta( $post_id, AgentConstants::META_PHONE, true );
				echo esc_html( $phone ? (string) $phone : '—' );
				break;

			case 'hvnly_agent_availability':
				if ( function_exists( 'hvnly_render_agent_availability_badge' ) ) {
					hvnly_render_agent_availability_badge(
						array(
							'agent_id' => $post_id,
							'context'  => 'admin',
							'echo'     => true,
						)
					);
				} else {
					echo '&mdash;';
				}
				break;
		}
	}

	/**
	 * @param string[] $actions Row actions.
	 * @param \WP_Post $post    Post object.
	 * @return string[]
	 */
	public function row_actions( array $actions, $post ): array {
		if ( AgentConstants::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		if ( isset( $actions['view'] ) ) {
			$actions['view'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				esc_url( get_permalink( $post ) ),
				esc_attr(
					sprintf(
						/* translators: %s: agent name */
						__( 'View profile for %s', 'havenlytics' ),
						get_the_title( $post )
					)
				),
				esc_html__( 'View Profile', 'havenlytics' )
			);
		}

		return $actions;
	}
}
