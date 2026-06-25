<?php
/**
 * Admin inquiries list table.
 *
 * @package HvnlyNab\ContactAgent\Admin
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent\Admin;

use HvnlyNab\ContactAgent\ContactAgentConstants;
use HvnlyNab\ContactAgent\InquiryRepository;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * @since 3.0.2
 */
class InquiryListTable extends \WP_List_Table {

	/** @var InquiryRepository */
	private $repository;

	/**
	 * @param InquiryRepository|null $repository Optional repository for tests.
	 */
	public function __construct( ?InquiryRepository $repository = null ) {
		parent::__construct(
			array(
				'singular' => 'inquiry',
				'plural'   => 'inquiries',
				'ajax'     => false,
			)
		);

		$this->repository = $repository ?? new InquiryRepository();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_columns(): array {
		return array(
			'cb'          => '<input type="checkbox" />',
			'created_at'  => esc_html__( 'Date', 'havenlytics' ),
			'property_id' => esc_html__( 'Property', 'havenlytics' ),
			'agent_id'    => esc_html__( 'Agent', 'havenlytics' ),
			'sender_name' => esc_html__( 'Sender', 'havenlytics' ),
			'message'     => esc_html__( 'Message', 'havenlytics' ),
			'status'      => esc_html__( 'Status', 'havenlytics' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_sortable_columns(): array {
		return array(
			'created_at'  => array( 'created_at', true ),
			'property_id' => array( 'property_id', false ),
			'status'      => array( 'status', false ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_bulk_actions(): array {
		return array(
			'mark_read' => esc_html__( 'Mark as read', 'havenlytics' ),
			'archive'   => esc_html__( 'Archive', 'havenlytics' ),
			'delete'    => esc_html__( 'Delete', 'havenlytics' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function extra_tablenav( $which ): void {
		if ( 'top' !== $which ) {
			return;
		}

		$status = isset( $_REQUEST['inquiry_status'] )
			? sanitize_key( wp_unslash( $_REQUEST['inquiry_status'] ) )
			: '';

		$statuses = array(
			''                                     => esc_html__( 'All statuses', 'havenlytics' ),
			ContactAgentConstants::STATUS_NEW      => esc_html__( 'New', 'havenlytics' ),
			ContactAgentConstants::STATUS_READ     => esc_html__( 'Read', 'havenlytics' ),
			ContactAgentConstants::STATUS_REPLIED  => esc_html__( 'Replied', 'havenlytics' ),
			ContactAgentConstants::STATUS_ARCHIVED => esc_html__( 'Archived', 'havenlytics' ),
		);
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="filter-by-inquiry-status"><?php esc_html_e( 'Filter by status', 'havenlytics' ); ?></label>
			<select name="inquiry_status" id="filter-by-inquiry-status">
				<?php foreach ( $statuses as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( esc_html__( 'Filter', 'havenlytics' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepare_items(): void {
		$per_page     = 20;
		$current_page = max( 1, $this->get_pagenum() );

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order   = isset( $_REQUEST['order'] ) ? strtoupper( sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) : 'DESC';

		$status = isset( $_REQUEST['inquiry_status'] )
			? sanitize_key( wp_unslash( $_REQUEST['inquiry_status'] ) )
			: '';

		$args = array(
			'limit'   => $per_page,
			'offset'  => ( $current_page - 1 ) * $per_page,
			'orderby' => $orderby,
			'order'   => $order,
		);

		if ( $status ) {
			$args['status'] = $status;
		}

		$this->items = $this->repository->list( $args );

		$count_args = $status ? array( 'status' => $status ) : array();
		$total      = $this->repository->count( $count_args );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function column_default( $item, $column_name ) {
		if ( ! is_array( $item ) ) {
			return '';
		}

		switch ( $column_name ) {
			case 'created_at':
				return esc_html( $this->format_date( (string) ( $item['created_at'] ?? '' ) ) );

			case 'property_id':
				return $this->render_property_cell( (int) ( $item['property_id'] ?? 0 ) );

			case 'agent_id':
				return $this->render_agent_cell( $item );

			case 'sender_name':
				return $this->render_sender_cell( $item );

			case 'message':
				$message = isset( $item['message'] ) ? wp_strip_all_tags( (string) $item['message'] ) : '';
				$excerpt = wp_html_excerpt( $message, 80, '&hellip;' );
				return esc_html( $excerpt );

			case 'status':
				return $this->render_status_badge( (string) ( $item['status'] ?? '' ) );

			default:
				return '';
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function column_cb( $item ): string {
		if ( ! is_array( $item ) || empty( $item['id'] ) ) {
			return '';
		}

		return sprintf(
			'<input type="checkbox" name="inquiry_ids[]" value="%d" />',
			(int) $item['id']
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function column_created_at( $item ): string {
		if ( ! is_array( $item ) ) {
			return '';
		}

		$id   = (int) ( $item['id'] ?? 0 );
		$date = $this->format_date( (string) ( $item['created_at'] ?? '' ) );

		$actions = $this->get_row_actions( $id, (string) ( $item['status'] ?? '' ) );

		return sprintf( '<strong><a href="%s">%s</a></strong>%s', esc_url( $this->detail_url( $id ) ), esc_html( $date ), $this->row_actions( $actions ) );
	}

	/**
	 * @param int    $inquiry_id Inquiry ID.
	 * @param string $status     Current status.
	 * @return array<string, string>
	 */
	private function get_row_actions( int $inquiry_id, string $status ): array {
		$actions = array(
			'view' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->detail_url( $inquiry_id ) ),
				esc_html__( 'View', 'havenlytics' )
			),
		);

		if ( ContactAgentConstants::STATUS_NEW === $status ) {
			$actions['read'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( 'mark_read', $inquiry_id ) ),
				esc_html__( 'Mark read', 'havenlytics' )
			);
		}

		if ( ContactAgentConstants::STATUS_ARCHIVED !== $status ) {
			$actions['archive'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->action_url( 'archive', $inquiry_id ) ),
				esc_html__( 'Archive', 'havenlytics' )
			);
		}

		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( $this->action_url( 'delete', $inquiry_id ) ),
			esc_attr__( 'Delete this inquiry permanently?', 'havenlytics' ),
			esc_html__( 'Delete', 'havenlytics' )
		);

		return $actions;
	}

	/**
	 * @param int $inquiry_id Inquiry ID.
	 * @return string
	 */
	private function detail_url( int $inquiry_id ): string {
		return add_query_arg(
			array(
				'post_type'  => 'hvnly_property',
				'page'       => InquiryAdminPage::MENU_SLUG,
				'inquiry_id' => $inquiry_id,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * @param string $action     Action slug.
	 * @param int    $inquiry_id Inquiry ID.
	 * @return string
	 */
	private function action_url( string $action, int $inquiry_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'post_type'  => 'hvnly_property',
					'page'       => InquiryAdminPage::MENU_SLUG,
					'hvnly_action' => $action,
					'inquiry_id' => $inquiry_id,
				),
				admin_url( 'edit.php' )
			),
			'hvnly_inquiry_action_' . $inquiry_id,
			'_hvnly_inquiry_nonce'
		);
	}

	/**
	 * @param int $property_id Property ID.
	 * @return string
	 */
	private function render_property_cell( int $property_id ): string {
		if ( $property_id <= 0 ) {
			return '&mdash;';
		}

		$title = get_the_title( $property_id );
		if ( ! $title ) {
			return esc_html( '#' . $property_id );
		}

		$edit_link = get_edit_post_link( $property_id );
		$view_link = get_permalink( $property_id );

		$html = $edit_link
			? '<a href="' . esc_url( $edit_link ) . '">' . esc_html( $title ) . '</a>'
			: esc_html( $title );

		if ( $view_link ) {
			$html .= ' <a href="' . esc_url( $view_link ) . '" class="hvnly-inquiries-admin__view-link" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View', 'havenlytics' ) . '</a>';
		}

		return $html;
	}

	/**
	 * @param array<string, mixed> $item Inquiry row.
	 * @return string
	 */
	private function render_agent_cell( array $item ): string {
		if ( function_exists( 'hvnly_get_inquiry_agent_profile' ) ) {
			$agent = hvnly_get_inquiry_agent_profile( $item );
			if ( ! empty( $agent['name'] ) ) {
				return esc_html( (string) $agent['name'] );
			}
		}

		$agent_id = isset( $item['agent_id'] ) ? (int) $item['agent_id'] : 0;
		return $agent_id > 0 ? esc_html( '#' . $agent_id ) : '&mdash;';
	}

	/**
	 * @param array<string, mixed> $item Inquiry row.
	 * @return string
	 */
	private function render_sender_cell( array $item ): string {
		$name  = isset( $item['sender_name'] ) ? (string) $item['sender_name'] : '';
		$email = isset( $item['sender_email'] ) ? (string) $item['sender_email'] : '';
		$phone = isset( $item['sender_phone'] ) ? (string) $item['sender_phone'] : '';

		if ( ! $name && ! $email ) {
			return '&mdash;';
		}

		$html = $name ? '<strong>' . esc_html( $name ) . '</strong>' : '';

		if ( $email && is_email( $email ) ) {
			$html .= $html ? '<br />' : '';
			$html .= '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
		}

		if ( $phone ) {
			$html .= '<br /><span class="hvnly-inquiries-admin__phone">' . esc_html( $phone ) . '</span>';
		}

		return $html;
	}

	/**
	 * @param string $status Status slug.
	 * @return string
	 */
	private function render_status_badge( string $status ): string {
		$labels = array(
			ContactAgentConstants::STATUS_NEW      => esc_html__( 'New', 'havenlytics' ),
			ContactAgentConstants::STATUS_READ     => esc_html__( 'Read', 'havenlytics' ),
			ContactAgentConstants::STATUS_REPLIED  => esc_html__( 'Replied', 'havenlytics' ),
			ContactAgentConstants::STATUS_ARCHIVED => esc_html__( 'Archived', 'havenlytics' ),
		);

		$label = $labels[ $status ] ?? esc_html( ucfirst( $status ) );
		$class = 'hvnly-inquiries-admin__status hvnly-inquiries-admin__status--' . sanitize_html_class( $status );

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * @param string $datetime MySQL datetime (UTC).
	 * @return string
	 */
	private function format_date( string $datetime ): string {
		if ( '' === trim( $datetime ) ) {
			return '—';
		}

		$timestamp = strtotime( $datetime . ' UTC' );
		if ( ! $timestamp ) {
			return esc_html( $datetime );
		}

		return esc_html(
			wp_date(
				get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
				$timestamp
			)
		);
	}
}
