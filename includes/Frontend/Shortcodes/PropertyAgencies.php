<?php

/**

 * Property Agencies shortcode — same markup as HVN: Property Agency Elementor widget.

 *

 * @package     Havenlytics

 * @subpackage  Frontend\Shortcodes

 * @since       3.0.3

 */



namespace HvnlyNab\Frontend\Shortcodes;



use HvnlyNab\Agent\AgencyArchiveQuery;



if ( ! defined( 'ABSPATH' ) ) {

	exit;

}



/**

 * Renders the shared agencies archive partial on any page.

 */

class PropertyAgencies extends AbstractShortcode {



	/** @var string */

	protected $tag = 'hvnly_property_agencies';



	/** @var bool */

	protected $enable_cache = false;



	/** @var array<string, mixed> */

	protected $default_atts = array(

		'posts_per_page'     => 12,

		'columns'            => 4,

		'orderby'            => 'name',

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

		if ( function_exists( 'hvnly_enqueue_property_agencies_listing_assets' ) ) {

			hvnly_enqueue_property_agencies_listing_assets();

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

		$agency_query = $this->build_agency_query( $atts );

		$columns = max( 1, min( 4, absint( $atts['columns'] ) ) );

		$search_url = $this->get_search_action_url();

		$instance_id = 'shortcode-agencies';

		do_action( 'hvnly_before_archive_agency' );

		ob_start();

		$wrapper_classes = array_filter(

			array(

				'hvnly-content-wrapper',

				'hvnly-property-agencies-widget',

				'hvnly-property-agencies-shortcode',

				'' !== trim( (string) $atts['class'] ) ? sanitize_html_class( (string) $atts['class'] ) : '',

			)

		);

		?>

		<div

			class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"

			data-instance-id="<?php echo esc_attr( $instance_id ); ?>"

			data-columns="<?php echo esc_attr( (string) $columns ); ?>"

			data-posts-per-page="<?php echo esc_attr( (string) absint( $atts['posts_per_page'] ) ); ?>"

			data-default-view="<?php echo esc_attr( (string) ( $atts['default_view'] ?? 'grid' ) ); ?>"

		>

			<?php

			hvnly_get_template_part(

				'property-archive/partials/agencies-archive',

				null,

				array(

					'query'              => $agency_query,

					'show_header'        => ( 'yes' === $atts['show_header'] ),

					'title'              => '' !== (string) $atts['title']

						? (string) $atts['title']

						: __( 'Real Estate Agencies', 'havenlytics' ),

					'subtitle'           => '' !== (string) $atts['subtitle']

						? (string) $atts['subtitle']

						: __( 'Explore trusted agencies and their teams of property professionals.', 'havenlytics' ),

					'show_search'        => ( 'yes' === $atts['show_search'] ),

					'show_view_controls' => ( 'yes' === $atts['show_view_controls'] ),

					'columns'            => $columns,

					'per_page'           => absint( $atts['posts_per_page'] ),

					'instance_id'        => $instance_id,

					'search_action'      => $search_url,

					'wrapper_class'      => '',

					'card_context'       => 'agencies_archive',

				)

			);

			?>

		</div>

		<?php

		do_action( 'hvnly_after_archive_agency' );

		remove_filter( 'hvnly_property_archive_view_type', $view_filter, 20 );

		$output = ob_get_clean();

		$this->after_render();

		return $output;
	}



	/**

	 * @param array<string, mixed> $atts Parsed shortcode attributes.

	 * @return array<string, mixed>

	 */

	private function build_agency_query( array $atts ): array {

		$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

		$per_page = max( 1, absint( $atts['posts_per_page'] ) );

		$orderby = sanitize_key( (string) ( $atts['orderby'] ?? 'name' ) );

		$order = strtoupper( sanitize_key( (string) ( $atts['order'] ?? 'ASC' ) ) );

		if ( ! in_array( $orderby, array( 'name', 'date' ), true ) ) {

			$orderby = 'name';

		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {

			$order = 'ASC';

		}

		$query_args = array(

			'per_page' => $per_page,

			'paged'    => $paged,

			'orderby'  => $orderby,

			'order'    => $order,

		);

		if ( class_exists( AgencyArchiveQuery::class ) ) {

			return AgencyArchiveQuery::query_agencies( $query_args );

		}

		if ( function_exists( 'hvnly_get_agency_archive_query' ) ) {

			return hvnly_get_agency_archive_query( $query_args );

		}

		return array(

			'items'        => array(),

			'total'        => 0,

			'max_pages'    => 1,

			'current_page' => $paged,

			'per_page'     => $per_page,

		);
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

		$atts['columns'] = max( 1, min( 4, absint( $atts['columns'] ) ) );

		foreach ( array( 'show_header', 'show_search', 'show_view_controls' ) as $flag ) {

			$atts[ $flag ] = ( 'yes' === $atts[ $flag ] ) ? 'yes' : 'no';

		}

		$atts['default_view'] = in_array( $atts['default_view'], array( 'grid', 'list' ), true )

			? $atts['default_view']

			: 'grid';

		if ( ! in_array( $atts['orderby'], array( 'name', 'date' ), true ) ) {

			$atts['orderby'] = 'name';

		}

		return $atts;
	}
}

