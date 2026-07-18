<?php
/**
 * Taxonomy requests admin list.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests\Admin
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests\Admin;

use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestConstants;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestRegistry;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestRepository;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class TaxonomyRequestsListTable extends \WP_List_Table {

	/** @var TaxonomyRequestRepository */
	private $repository;

	/** @var TaxonomyRequestRegistry */
	private $registry;

	public function __construct( TaxonomyRequestRepository $repository, TaxonomyRequestRegistry $registry ) {
		parent::__construct( array(
			'singular' => 'taxonomy_request',
			'plural' => 'taxonomy_requests',
			'ajax' => false,
		) );
		$this->repository = $repository;
		$this->registry   = $registry;
	}

	public function get_columns(): array {
		return array(
			'id'             => esc_html__( 'ID', 'havenlytics' ),
			'agent_id'       => esc_html__( 'Agent', 'havenlytics' ),
			'taxonomy'       => esc_html__( 'Taxonomy', 'havenlytics' ),
			'requested_name' => esc_html__( 'Requested Name', 'havenlytics' ),
			'description'    => esc_html__( 'Description', 'havenlytics' ),
			'reason'         => esc_html__( 'Reason', 'havenlytics' ),
			'status'         => esc_html__( 'Status', 'havenlytics' ),
			'created_at'     => esc_html__( 'Created', 'havenlytics' ),
			'updated_at'     => esc_html__( 'Updated', 'havenlytics' ),
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'id'             => array( 'id', false ),
			'agent_id'       => array( 'agent_id', false ),
			'taxonomy'       => array( 'taxonomy', false ),
			'requested_name' => array( 'requested_name', false ),
			'status'         => array( 'status', false ),
			'created_at'     => array( 'created_at', true ),
			'updated_at'     => array( 'updated_at', false ),
		);
	}

	public function prepare_items(): void {
		$page            = max( 1, $this->get_pagenum() );
		$per_page        = 20;
		$args            = $this->filters();
		$args['limit']   = $per_page;
		$args['offset']  = ( $page - 1 ) * $per_page;
		$args['orderby'] = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( (string) $_GET['orderby'] ) ) : 'created_at'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args['order']   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( (string) $_GET['order'] ) ) : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->items = $this->repository->list( $args );
		if ( function_exists( 'cache_users' ) ) {
			cache_users( array_values( array_unique( array_filter( array_map( static function ( array $item ): int {
				return absint( $item['user_id'] ?? 0 );
			}, $this->items ) ) ) ) );
		}
		$total                 = $this->repository->count( $args );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page' => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function filters(): array {
		return array(
			'status'    => isset( $_GET['request_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['request_status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'taxonomy'  => isset( $_GET['taxonomy_filter'] ) ? sanitize_key( wp_unslash( (string) $_GET['taxonomy_filter'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'agent_id'  => isset( $_GET['agent_id'] ) ? absint( wp_unslash( $_GET['agent_id'] ) ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	public function no_items(): void {
		?>
		<div class="hvnly-txr-empty">
			<span class="dashicons dashicons-tag" aria-hidden="true"></span>
			<p class="hvnly-txr-empty__title"><?php esc_html_e( 'No taxonomy requests found', 'havenlytics' ); ?></p>
			<p class="hvnly-txr-empty__text"><?php esc_html_e( 'When Workspace agents request new terms from the property form, their requests will appear here for review. Try clearing the filters if you expected results.', 'havenlytics' ); ?></p>
		</div>
		<?php
	}

	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}
		$filters = $this->filters();
		?>
		<div class="alignleft actions hvnly-txr-filters">
			<label class="screen-reader-text" for="request-status"><?php esc_html_e( 'Filter by status', 'havenlytics' ); ?></label>
			<select id="request-status" name="request_status">
				<option value=""><?php esc_html_e( 'All statuses', 'havenlytics' ); ?></option>
				<?php foreach ( TaxonomyRequestConstants::statuses() as $status ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $filters['status'], $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="taxonomy-filter"><?php esc_html_e( 'Filter by taxonomy', 'havenlytics' ); ?></label>
			<select id="taxonomy-filter" name="taxonomy_filter">
				<option value=""><?php esc_html_e( 'All taxonomies', 'havenlytics' ); ?></option>
				<?php foreach ( $this->registry->all() as $taxonomy => $item ) : ?>
					<option value="<?php echo esc_attr( $taxonomy ); ?>" <?php selected( $filters['taxonomy'], $taxonomy ); ?>><?php echo esc_html( $item['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<label for="taxonomy-agent-id" class="screen-reader-text"><?php esc_html_e( 'Agent ID', 'havenlytics' ); ?></label>
			<input id="taxonomy-agent-id" type="number" min="1" name="agent_id" value="<?php echo esc_attr( (string) $filters['agent_id'] ); ?>" placeholder="<?php esc_attr_e( 'Agent ID', 'havenlytics' ); ?>" />
			<label for="taxonomy-date-from" class="screen-reader-text"><?php esc_html_e( 'Created after', 'havenlytics' ); ?></label>
			<input id="taxonomy-date-from" type="date" name="date_from" value="<?php echo esc_attr( (string) $filters['date_from'] ); ?>" />
			<label for="taxonomy-date-to" class="screen-reader-text"><?php esc_html_e( 'Created before', 'havenlytics' ); ?></label>
			<input id="taxonomy-date-to" type="date" name="date_to" value="<?php echo esc_attr( (string) $filters['date_to'] ); ?>" />
			<?php submit_button( esc_html__( 'Filter', 'havenlytics' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function column_default( $item, $column_name ) {
		if ( ! is_array( $item ) ) {
			return '';
		}
		switch ( $column_name ) {
			case 'id':
				return $this->id_column( $item );
			case 'agent_id':
				return $this->agent_column( $item );
			case 'taxonomy':
				$registered = $this->registry->get( (string) $item['taxonomy'] );
				return esc_html( $registered ? $registered['label'] : (string) $item['taxonomy'] );
			case 'requested_name':
				return '<span class="hvnly-txr-name">' . esc_html( (string) $item['requested_name'] ) . '</span>';
			case 'description':
			case 'reason':
				$excerpt = wp_html_excerpt( wp_strip_all_tags( (string) $item[ $column_name ] ), 80, '…' );
				return '' !== $excerpt ? esc_html( $excerpt ) : '<span class="hvnly-txr-muted">&mdash;</span>';
			case 'status':
				return TaxonomyRequestsAdminPage::status_badge( (string) $item['status'] );
			case 'created_at':
			case 'updated_at':
				return '<span class="hvnly-txr-muted">' . esc_html( $this->date( (string) $item[ $column_name ] ) ) . '</span>';
			default:
				return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
		}
	}

	private function id_column( array $item ): string {
		$id     = absint( $item['id'] );
		$url    = TaxonomyRequestsAdminPage::url( $id );
		$review = '<a class="hvnly-txr-review-link" href="' . esc_url( $url ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span>' . esc_html__( 'Review', 'havenlytics' ) . '</a>';
		return '<strong><a href="' . esc_url( $url ) . '">#' . esc_html( (string) $id ) . '</a></strong>' .
			$this->row_actions( array( 'review' => $review ) );
	}

	private function agent_column( array $item ): string {
		$user = get_userdata( absint( $item['user_id'] ) );
		if ( $user instanceof \WP_User ) {
			return esc_html( $user->display_name ) . '<br><small>#' . esc_html( (string) absint( $item['agent_id'] ) ) . '</small>';
		}
		return '#' . esc_html( (string) absint( $item['agent_id'] ) );
	}

	private function date( string $value ): string {
		$timestamp = strtotime( $value . ' UTC' );
		return $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : $value;
	}
}
