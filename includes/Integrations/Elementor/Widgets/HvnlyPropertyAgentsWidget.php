<?php
/**
 * HVN: Property Agents — Elementor archive widget.
 *
 * Mirrors the native /agents/ archive layout and card system.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Elementor\Widgets
 * @since       3.0.2
 */

namespace HvnlyNab\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use HvnlyNab\Agent\AgentConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor widget: agent archive grid matching archive templates.
 */
class HvnlyPropertyAgentsWidget extends Widget_Base {

	/** @var \WP_Query|null */
	private $original_wp_query = null;

	/** @var \WP_Post|null */
	private $original_post = null;

	public function get_name(): string {
		return 'hvnly_property_agents';
	}

	public function get_title(): string {
		return __( 'HVN: Property Agents', 'havenlytics' );
	}

	public function get_icon(): string {
		// eicon-gallery-grid is overridden in elementor-editor.css with the Havenlytics SVG.
		return 'eicon-gallery-grid';
	}

	public function get_categories(): array {
		return array( 'havenlytics' );
	}

	/**
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'havenlytics', 'agent', 'agents', 'archive', 'real estate', 'broker', 'realtor' );
	}

	/**
	 * @return array<int, string>
	 */
	public function get_style_depends(): array {
		return array(
			'hvnly-fontawesome-all-frontend',
			'hvnly-frontend-default',
			'hvnly-frontend-components',
			'hvnly-frontend-global-grid',
			'hvnly-frontend-property-agent-widget',
			'hvnly-frontend-cards',
			'hvnly-frontend-property-agents-archive',
			'hvnly-elementor-property-agents',
		);
	}

	/**
	 * @return array<int, string>
	 */
	public function get_script_depends(): array {
		return array( 'hvnly-frontend-property-agents-archive' );
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'display_section',
			array(
				'label' => __( 'Display Settings', 'havenlytics' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'show_header',
			array(
				'label'   => __( 'Show Header', 'havenlytics' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Title', 'havenlytics' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Real Estate Agents', 'havenlytics' ),
				'condition'   => array(
					'show_header' => 'yes',
				),
			)
		);

		$this->add_control(
			'subtitle',
			array(
				'label'       => __( 'Subtitle', 'havenlytics' ),
				'type'        => Controls_Manager::TEXTAREA,
				'default'     => __( 'Browse professional agents and connect with the right expert for your property journey.', 'havenlytics' ),
				'condition'   => array(
					'show_header' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_search',
			array(
				'label'   => __( 'Show Search', 'havenlytics' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_view_controls',
			array(
				'label'   => __( 'Show View Controls', 'havenlytics' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'posts_per_page',
			array(
				'label'   => __( 'Agents Per Page', 'havenlytics' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 48,
				'step'    => 1,
				'default' => 12,
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Grid Columns', 'havenlytics' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '4',
				'options' => array(
					'1' => __( '1 Column', 'havenlytics' ),
					'2' => __( '2 Columns', 'havenlytics' ),
					'3' => __( '3 Columns', 'havenlytics' ),
					'4' => __( '4 Columns', 'havenlytics' ),
				),
			)
		);

		$this->add_control(
			'default_view',
			array(
				'label'   => __( 'Default View', 'havenlytics' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid' => __( 'Grid', 'havenlytics' ),
					'list' => __( 'List', 'havenlytics' ),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'query_section',
			array(
				'label' => __( 'Query', 'havenlytics' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'orderby',
			array(
				'label'   => __( 'Order By', 'havenlytics' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'title',
				'options' => array(
					'title' => __( 'Name', 'havenlytics' ),
					'date'  => __( 'Date Added', 'havenlytics' ),
				),
			)
		);

		$this->add_control(
			'order',
			array(
				'label'   => __( 'Order', 'havenlytics' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'ASC',
				'options' => array(
					'ASC'  => __( 'Ascending', 'havenlytics' ),
					'DESC' => __( 'Descending', 'havenlytics' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		if ( function_exists( 'hvnly_load_template_functions' ) ) {
			hvnly_load_template_functions();
		}

		if ( class_exists( '\HvnlyNab\Integrations\Elementor\Bootstrap' ) ) {
			\HvnlyNab\Integrations\Elementor\Bootstrap::get_instance()->enqueue_agent_widget_assets_for_render();
		}

		$settings  = $this->get_settings_for_display();
		$widget_id = $this->get_id();

		$this->save_original_query_state();

		$view_filter = function ( $view ) use ( $settings ) {
			if ( ! empty( $_GET['view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return $view;
			}

			$default = isset( $settings['default_view'] ) ? sanitize_key( (string) $settings['default_view'] ) : 'grid';

			return in_array( $default, array( 'grid', 'list' ), true ) ? $default : $view;
		};

		add_filter( 'hvnly_property_archive_view_type', $view_filter, 20 );

		$agent_query = $this->build_agent_query( $settings );
		$columns     = max( 1, min( 4, absint( $settings['columns'] ?? 4 ) ) );

		$wrapper_classes = array(
			'hvnly-content-wrapper',
			'hvnly-property-agents-widget',
			'hvnly-elementor-widget',
			'hvnly-widget-' . sanitize_html_class( $widget_id ),
		);

		global $wp_query;
		$original_wp_query = $wp_query;
		$wp_query          = $agent_query;

		$search_url = $this->get_search_action_url();

		do_action( 'hvnly_before_archive_agent' );
		?>
		<div
			class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"
			data-widget-id="<?php echo esc_attr( $widget_id ); ?>"
			data-columns="<?php echo esc_attr( (string) $columns ); ?>"
			data-posts-per-page="<?php echo esc_attr( (string) absint( $settings['posts_per_page'] ?? 12 ) ); ?>"
			data-default-view="<?php echo esc_attr( (string) ( $settings['default_view'] ?? 'grid' ) ); ?>"
		>
			<?php
			hvnly_get_template_part(
				'property-archive/partials/agents-archive',
				null,
				array(
					'query'              => $agent_query,
					'show_header'        => ( 'yes' === ( $settings['show_header'] ?? 'yes' ) ),
					'title'              => (string) ( $settings['title'] ?? '' ),
					'subtitle'           => (string) ( $settings['subtitle'] ?? '' ),
					'show_search'        => ( 'yes' === ( $settings['show_search'] ?? 'yes' ) ),
					'show_view_controls' => ( 'yes' === ( $settings['show_view_controls'] ?? 'yes' ) ),
					'columns'            => $columns,
					'per_page'           => absint( $settings['posts_per_page'] ?? 12 ),
					'instance_id'        => 'elementor-agents-' . $widget_id,
					'search_action'      => $search_url,
					'wrapper_class'      => '',
					'card_context'       => 'agents_archive',
				)
			);
			?>
		</div>
		<?php
		do_action( 'hvnly_after_archive_agent' );

		remove_filter( 'hvnly_property_archive_view_type', $view_filter, 20 );

		$wp_query = $original_wp_query;
		wp_reset_postdata();
		$this->restore_original_query_state();
	}

	/**
	 * @param array<string, mixed> $settings Widget settings.
	 * @return \WP_Query
	 */
	private function build_agent_query( array $settings ): \WP_Query {
		$paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$per_page = max( 1, absint( $settings['posts_per_page'] ?? 12 ) );
		$orderby  = isset( $settings['orderby'] ) ? sanitize_key( (string) $settings['orderby'] ) : 'title';
		$order    = isset( $settings['order'] ) ? strtoupper( sanitize_key( (string) $settings['order'] ) ) : 'ASC';

		if ( ! in_array( $orderby, array( 'title', 'date' ), true ) ) {
			$orderby = 'title';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'ASC';
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query_args = array(
			'post_type'              => AgentConstants::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $paged,
			'orderby'                => $orderby,
			'order'                  => $order,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		return new \WP_Query( $query_args );
	}

	/**
	 * Search form posts back to the current Elementor page.
	 *
	 * @return string
	 */
	private function get_search_action_url(): string {
		global $post;

		if ( $post instanceof \WP_Post && $post->ID > 0 ) {
			$url = get_permalink( $post );
			if ( $url ) {
				return (string) $url;
			}
		}

		return home_url( add_query_arg( array(), isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	private function save_original_query_state(): void {
		global $wp_query, $post;

		$this->original_wp_query = $wp_query;
		$this->original_post   = $post instanceof \WP_Post ? $post : null;
	}

	private function restore_original_query_state(): void {
		global $wp_query, $post;

		if ( $this->original_wp_query instanceof \WP_Query ) {
			$wp_query = $this->original_wp_query;
		}

		if ( $this->original_post instanceof \WP_Post ) {
			$post = $this->original_post;
		}
	}
}
