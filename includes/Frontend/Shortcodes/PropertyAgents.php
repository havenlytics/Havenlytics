<?php
/**
 * Property Agents shortcode — same markup as native /agents/ archive.
 *
 * @package     Havenlytics
 * @subpackage  Frontend\Shortcodes
 * @since       3.0.2
 */

namespace HvnlyNab\Frontend\Shortcodes;

use HvnlyNab\Agent\AgentConstants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the shared agents archive partial on any page.
 */
class PropertyAgents extends AbstractShortcode {

	/** @var string */
	protected $tag = 'hvnly_property_agents';

	/** @var bool */
	protected $enable_cache = false;

	/** @var array<string, mixed> */
	protected $default_atts = array(
		'posts_per_page'     => 12,
		'columns'            => 4,
		'orderby'            => 'title',
		'order'              => 'ASC',
		'show_header'        => 'yes',
		'title'              => '',
		'subtitle'           => '',
		'show_search'        => 'yes',
		'show_view_controls' => 'yes',
		'default_view'       => 'grid',
		'class'              => '',
	);

	/**
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @param string               $content Enclosed content.
	 * @return string
	 */
	public function render( $atts, $content = '' ) {
		$this->before_render();

		if ( function_exists( 'hvnly_load_template_functions' ) ) {
			hvnly_load_template_functions();
		}

		$atts = $this->parse_attributes( $atts );

		$view_filter = function ( $view ) use ( $atts ) {
			if ( ! empty( $_GET['view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return $view;
			}

			$default = sanitize_key( (string) ( $atts['default_view'] ?? 'grid' ) );

			return in_array( $default, array( 'grid', 'list' ), true ) ? $default : 'grid';
		};

		add_filter( 'hvnly_property_archive_view_type', $view_filter, 20 );

		$agent_query = $this->build_agent_query( $atts );
		$columns     = max( 1, min( 4, absint( $atts['columns'] ) ) );
		$search_url  = $this->get_search_action_url();

		do_action( 'hvnly_before_archive_agent' );

		ob_start();

		$wrapper_classes = array_filter(
			array(
				'hvnly-content-wrapper',
				'hvnly-property-agents-shortcode',
				'' !== trim( (string) $atts['class'] ) ? sanitize_html_class( (string) $atts['class'] ) : '',
			)
		);

		?>
		<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>">
			<?php
			hvnly_get_template_part(
				'property-archive/partials/agents-archive',
				null,
				array(
					'query'              => $agent_query,
					'show_header'        => ( 'yes' === $atts['show_header'] ),
					'title'              => (string) $atts['title'],
					'subtitle'           => (string) $atts['subtitle'],
					'show_search'        => ( 'yes' === $atts['show_search'] ),
					'show_view_controls' => ( 'yes' === $atts['show_view_controls'] ),
					'columns'            => $columns,
					'per_page'           => absint( $atts['posts_per_page'] ),
					'instance_id'        => 'shortcode-agents',
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

		wp_reset_postdata();

		$output = ob_get_clean();

		$this->after_render();

		return $output;
	}

	/**
	 * @param array<string, mixed> $atts Parsed shortcode attributes.
	 * @return \WP_Query
	 */
	private function build_agent_query( array $atts ): \WP_Query {
		$paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		$per_page = max( 1, absint( $atts['posts_per_page'] ) );
		$orderby  = sanitize_key( (string) ( $atts['orderby'] ?? 'title' ) );
		$order    = strtoupper( sanitize_key( (string) ( $atts['order'] ?? 'ASC' ) ) );

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
	 * Search form and pagination base URL — current page permalink.
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

	/**
	 * @param array<string, mixed> $atts Attributes.
	 * @return array<string, mixed>
	 */
	protected function validate_attributes( $atts ) {
		$atts = parent::validate_attributes( $atts );

		$atts['posts_per_page'] = max( 1, min( 100, absint( $atts['posts_per_page'] ) ) );
		$atts['columns']        = max( 1, min( 4, absint( $atts['columns'] ) ) );

		foreach ( array( 'show_header', 'show_search', 'show_view_controls' ) as $flag ) {
			$atts[ $flag ] = ( 'yes' === $atts[ $flag ] ) ? 'yes' : 'no';
		}

		$atts['default_view'] = in_array( $atts['default_view'], array( 'grid', 'list' ), true )
			? $atts['default_view']
			: 'grid';

		return $atts;
	}
}
