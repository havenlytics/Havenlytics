<?php
/**
 * Taxonomy request review admin page.
 *
 * @package HvnlyNab\Workspace\TaxonomyRequests\Admin
 * @since   3.3.0
 */

namespace HvnlyNab\Workspace\TaxonomyRequests\Admin;

use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestConstants;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestLogRepository;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestRegistry;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestRepository;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestSchema;
use HvnlyNab\Workspace\TaxonomyRequests\TaxonomyRequestService;

defined( 'ABSPATH' ) || exit;

final class TaxonomyRequestsAdminPage {

	public const PARENT_MENU_SLUG = 'edit.php?post_type=hvnly_property';
	public const MENU_SLUG        = 'hvnly_taxonomy_requests';

	/** @var TaxonomyRequestRepository */
	private $repository;

	/** @var TaxonomyRequestLogRepository */
	private $logs;

	/** @var TaxonomyRequestRegistry */
	private $registry;

	/** @var TaxonomyRequestService */
	private $service;

	public function __construct() {
		$this->repository = new TaxonomyRequestRepository();
		$this->logs       = new TaxonomyRequestLogRepository();
		$this->registry   = new TaxonomyRequestRegistry();
		$this->service    = new TaxonomyRequestService( $this->repository, $this->logs, $this->registry );
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 30 );
		add_action( 'admin_menu', array( $this, 'position_menu_item' ), 999 );
		add_action( 'admin_init', array( $this, 'redirect_legacy_url' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		foreach ( array( 'approve', 'reject', 'merge', 'delete' ) as $action ) {
			add_action( 'admin_post_hvnly_taxonomy_request_' . $action, array( $this, 'handle_action' ) );
		}
	}

	public function menu(): void {
		add_submenu_page(
			self::PARENT_MENU_SLUG,
			esc_html__( 'Taxonomy Requests', 'havenlytics' ),
			esc_html__( 'Taxonomy Requests', 'havenlytics' ),
			'manage_categories',
			self::MENU_SLUG,
			array( $this, 'render' )
		);

		/*
		 * Keep the Havenlytics menu clean until the feature is actually used.
		 * The page is always registered above (so it stays routable and
		 * permission-checked for authorized direct-URL access); we only strip
		 * the VISIBLE link when no taxonomy requests exist. This is re-evaluated
		 * on every admin page load, so the link appears automatically once the
		 * first request is created and disappears again if all are removed — no
		 * cache clearing or manual refresh required.
		 */
		if ( ! $this->repository->has_any() ) {
			remove_submenu_page( self::PARENT_MENU_SLUG, self::MENU_SLUG );
		}
	}

	/**
	 * Place Taxonomy Requests after the taxonomy screens, before Settings.
	 */
	public function position_menu_item(): void {
		global $submenu;
		if ( empty( $submenu[ self::PARENT_MENU_SLUG ] ) || ! is_array( $submenu[ self::PARENT_MENU_SLUG ] ) ) {
			return;
		}
		$items = $submenu[ self::PARENT_MENU_SLUG ];
		$ours  = null;
		foreach ( $items as $index => $item ) {
			if ( self::MENU_SLUG === ( $item[2] ?? '' ) ) {
				$ours = $item;
				unset( $items[ $index ] );
				break;
			}
		}
		if ( null === $ours ) {
			return;
		}
		$ordered  = array();
		$inserted = false;
		foreach ( $items as $item ) {
			if ( ! $inserted && 'hvnly_property_settings' === ( $item[2] ?? '' ) ) {
				$ordered[] = $ours;
				$inserted  = true;
			}
			$ordered[] = $item;
		}
		if ( ! $inserted ) {
			$ordered[] = $ours;
		}
		$submenu[ self::PARENT_MENU_SLUG ] = array_values( $ordered ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering our own submenu entry.
	}

	/**
	 * Keep previously shared admin.php links (notifications, emails, bookmarks) working.
	 */
	public function redirect_legacy_url(): void {
		global $pagenow;
		if ( 'admin.php' !== $pagenow ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( self::MENU_SLUG !== $page ) {
			return;
		}
		$args = array();
		foreach ( array( 'request_id', 'term_search', 'request_status', 'taxonomy_filter', 'agent_id', 'date_from', 'date_to', 's', 'paged', 'orderby', 'order' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL routing.
			if ( isset( $_GET[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only URL routing.
				$args[ $key ] = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );
			}
		}
		wp_safe_redirect( add_query_arg( $args, self::url() ) );
		exit;
	}

	/**
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}
		wp_enqueue_style(
			'hvnly-taxonomy-requests',
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-taxonomy-requests.css',
			array(),
			HVNLYNAB_VERSION
		);
	}

	public function handle_action(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Authentication required.', 'havenlytics' ), '', array( 'response' => 401 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		$request_id = isset( $_POST['request_id'] ) ? absint( wp_unslash( $_POST['request_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- action is covered by per-record nonce.
		$action = isset( $_POST['taxonomy_request_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['taxonomy_request_action'] ) ) : '';
		if ( ! in_array( $action, array( 'approve', 'reject', 'merge', 'delete' ), true ) || $request_id <= 0 ) {
			wp_die( esc_html__( 'Invalid taxonomy request action.', 'havenlytics' ), '', array( 'response' => 400 ) );
		}
		check_admin_referer( 'hvnly_taxonomy_request_' . $action . '_' . $request_id );

		switch ( $action ) {
			case 'approve':
				$result = $this->service->approve( $request_id, get_current_user_id() );
				break;
			case 'reject':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
				$reason = isset( $_POST['rejection_reason'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['rejection_reason'] ) ) : '';
				$result = $this->service->reject( $request_id, get_current_user_id(), $reason );
				break;
			case 'merge':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
				$term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
				$result  = $this->service->merge( $request_id, get_current_user_id(), $term_id );
				break;
			default:
				$result = $this->service->delete( $request_id, get_current_user_id() );
		}

		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Taxonomy request updated.', 'havenlytics' );
		set_transient(
			'hvnly_taxonomy_request_notice_' . get_current_user_id(),
			array(
				'message' => sanitize_text_field( $message ),
				'type'    => is_wp_error( $result ) ? 'error' : 'success',
			),
			MINUTE_IN_SECONDS
		);
		$target = self::url();
		if ( is_wp_error( $result ) ) {
			$target = add_query_arg( 'request_id', $request_id, $target );
		}
		wp_safe_redirect( $target );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_categories' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to review taxonomy requests.', 'havenlytics' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route.
		$request_id = isset( $_GET['request_id'] ) ? absint( wp_unslash( $_GET['request_id'] ) ) : 0;
		?>
		<div class="wrap hvnly-txr-wrap">
			<h1 class="wp-heading-inline">
				<span class="hvnly-txr-heading-icon" aria-hidden="true"><span class="dashicons dashicons-tag"></span></span>
				<?php esc_html_e( 'Taxonomy Requests', 'havenlytics' ); ?>
			</h1>
			<hr class="wp-header-end">
			<?php
			$this->notice();
			if ( ! TaxonomyRequestSchema::tables_exist() ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Taxonomy request storage is unavailable. Run Havenlytics migrations.', 'havenlytics' ) . '</p></div>';
			} elseif ( $request_id > 0 ) {
				$this->detail( $request_id );
			} else {
				$this->listing();
			}
			?>
		</div>
		<?php
	}

	private function notice(): void {
		$key    = 'hvnly_taxonomy_request_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( $key );
		$class = 'error' === ( $notice['type'] ?? '' ) ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $notice['message'] ?? '' ) ) . '</p></div>';
	}

	private function listing(): void {
		$table = new TaxonomyRequestsListTable( $this->repository, $this->registry );
		$table->prepare_items();
		?>
		<p class="hvnly-txr-subtitle"><?php esc_html_e( 'Review taxonomy term requests submitted by Workspace agents. Approve, reject, or merge them into existing terms.', 'havenlytics' ); ?></p>
		<form method="get" class="hvnly-txr-card hvnly-txr-list-card">
			<input type="hidden" name="post_type" value="hvnly_property" />
			<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
			<?php $table->search_box( esc_html__( 'Search requests', 'havenlytics' ), 'taxonomy-request' ); ?>
			<?php $table->display(); ?>
		</form>
		<?php
	}

	private function detail( int $id ): void {
		$request = $this->repository->find( $id );
		if ( ! $request ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Taxonomy request not found.', 'havenlytics' ) . '</p></div>';
			return;
		}
		$user  = get_userdata( (int) $request['user_id'] );
		$reg   = $this->registry->get( (string) $request['taxonomy'] );
		$state = (string) $request['status'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only term search.
		$term_search = isset( $_GET['term_search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['term_search'] ) ) : (string) $request['requested_name'];
		$suggestions = $this->service->suggestions( (string) $request['taxonomy'], (string) $request['requested_name'] );
		?>
		<a class="hvnly-txr-back" href="<?php echo esc_url( self::url() ); ?>">
			<span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
			<?php esc_html_e( 'Back to all requests', 'havenlytics' ); ?>
		</a>

		<section class="hvnly-txr-card" aria-label="<?php esc_attr_e( 'Request summary', 'havenlytics' ); ?>">
			<div class="hvnly-txr-card__header">
				<h2 class="hvnly-txr-card__title">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<?php echo esc_html( sprintf( /* translators: %d: request ID. */ __( 'Request #%d', 'havenlytics' ), $id ) ); ?>
				</h2>
				<?php echo wp_kses_post( self::status_badge( $state ) ); ?>
			</div>
			<div class="hvnly-txr-card__body">
				<div class="hvnly-txr-header-grid">
					<div class="hvnly-txr-field">
						<span class="hvnly-txr-field__label"><?php esc_html_e( 'Requested Term', 'havenlytics' ); ?></span>
						<span class="hvnly-txr-field__value hvnly-txr-field__value--lead"><?php echo esc_html( (string) $request['requested_name'] ); ?></span>
					</div>
					<div class="hvnly-txr-field">
						<span class="hvnly-txr-field__label"><?php esc_html_e( 'Taxonomy', 'havenlytics' ); ?></span>
						<span class="hvnly-txr-field__value"><?php echo esc_html( $reg ? $reg['label'] : (string) $request['taxonomy'] ); ?></span>
					</div>
					<div class="hvnly-txr-field">
						<span class="hvnly-txr-field__label"><?php esc_html_e( 'Requested By', 'havenlytics' ); ?></span>
						<span class="hvnly-txr-field__value hvnly-txr-requester">
							<?php if ( $user instanceof \WP_User ) : ?>
								<?php echo get_avatar( $user->ID, 24 ); ?>
								<?php echo esc_html( $user->display_name ); ?>
							<?php else : ?>
								<?php echo esc_html( '#' . (int) $request['agent_id'] ); ?>
							<?php endif; ?>
						</span>
					</div>
					<div class="hvnly-txr-field">
						<span class="hvnly-txr-field__label"><?php esc_html_e( 'Created', 'havenlytics' ); ?></span>
						<span class="hvnly-txr-field__value"><?php echo esc_html( $this->format_date( (string) $request['created_at'] ) ); ?></span>
					</div>
					<div class="hvnly-txr-field">
						<span class="hvnly-txr-field__label"><?php esc_html_e( 'Last Updated', 'havenlytics' ); ?></span>
						<span class="hvnly-txr-field__value"><?php echo esc_html( $this->format_date( (string) $request['updated_at'] ) ); ?></span>
					</div>
				</div>
			</div>
		</section>

		<section class="hvnly-txr-card" aria-label="<?php esc_attr_e( 'Request details', 'havenlytics' ); ?>">
			<div class="hvnly-txr-card__header">
				<h2 class="hvnly-txr-card__title">
					<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
					<?php esc_html_e( 'Request Details', 'havenlytics' ); ?>
				</h2>
			</div>
			<div class="hvnly-txr-card__body">
				<span class="hvnly-txr-field__label"><?php esc_html_e( 'Description', 'havenlytics' ); ?></span>
				<p class="hvnly-txr-longtext"><?php echo '' !== trim( (string) $request['description'] ) ? nl2br( esc_html( (string) $request['description'] ) ) : esc_html__( 'No description provided.', 'havenlytics' ); ?></p>
				<span class="hvnly-txr-field__label"><?php esc_html_e( 'Reason', 'havenlytics' ); ?></span>
				<p class="hvnly-txr-longtext"><?php echo '' !== trim( (string) $request['reason'] ) ? nl2br( esc_html( (string) $request['reason'] ) ) : esc_html__( 'No reason provided.', 'havenlytics' ); ?></p>
				<?php if ( ! empty( $suggestions ) ) : ?>
					<span class="hvnly-txr-field__label"><?php esc_html_e( 'Similar Existing Terms', 'havenlytics' ); ?></span>
					<ul class="hvnly-txr-suggestions">
						<?php foreach ( $suggestions as $suggestion ) : ?>
							<li><span class="hvnly-txr-chip"><?php echo esc_html( (string) $suggestion['name'] ); ?> <code><?php echo esc_html( (string) $suggestion['slug'] ); ?></code></span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( TaxonomyRequestConstants::STATUS_PENDING === $state ) : ?>
			<section class="hvnly-txr-card" aria-label="<?php esc_attr_e( 'Moderation actions', 'havenlytics' ); ?>">
				<div class="hvnly-txr-card__header">
					<h2 class="hvnly-txr-card__title">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'Review Actions', 'havenlytics' ); ?>
					</h2>
				</div>
				<div class="hvnly-txr-card__body">
					<div class="hvnly-txr-actions">
						<div class="hvnly-txr-action">
							<h3 class="hvnly-txr-action__title"><?php esc_html_e( 'Approve', 'havenlytics' ); ?></h3>
							<p class="hvnly-txr-action__hint"><?php esc_html_e( 'Creates the requested term and notifies the agent.', 'havenlytics' ); ?></p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php $this->form_fields( 'approve', $id ); ?>
								<button type="submit" class="button button-primary"><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Approve & Create Term', 'havenlytics' ); ?></button>
							</form>
						</div>
						<div class="hvnly-txr-action">
							<h3 class="hvnly-txr-action__title"><?php esc_html_e( 'Reject', 'havenlytics' ); ?></h3>
							<p class="hvnly-txr-action__hint"><?php esc_html_e( 'Declines the request. The reason is shared with the agent.', 'havenlytics' ); ?></p>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php $this->form_fields( 'reject', $id ); ?>
								<label class="screen-reader-text" for="rejection-reason"><?php esc_html_e( 'Rejection reason', 'havenlytics' ); ?></label>
								<textarea id="rejection-reason" name="rejection_reason" rows="3" maxlength="2000" placeholder="<?php esc_attr_e( 'Rejection reason (required)…', 'havenlytics' ); ?>" required></textarea>
								<button type="submit" class="button"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span><?php esc_html_e( 'Reject Request', 'havenlytics' ); ?></button>
							</form>
						</div>
						<div class="hvnly-txr-action">
							<h3 class="hvnly-txr-action__title"><?php esc_html_e( 'Merge', 'havenlytics' ); ?></h3>
							<p class="hvnly-txr-action__hint"><?php esc_html_e( 'Resolves the request by pointing to an existing term.', 'havenlytics' ); ?></p>
							<form method="get" class="hvnly-txr-term-search">
								<input type="hidden" name="post_type" value="hvnly_property" />
								<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />
								<input type="hidden" name="request_id" value="<?php echo esc_attr( (string) $id ); ?>" />
								<label class="screen-reader-text" for="term-search"><?php esc_html_e( 'Find an existing term', 'havenlytics' ); ?></label>
								<input id="term-search" type="search" name="term_search" value="<?php echo esc_attr( $term_search ); ?>" placeholder="<?php esc_attr_e( 'Search terms…', 'havenlytics' ); ?>" />
								<button type="submit" class="button"><?php esc_html_e( 'Find', 'havenlytics' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px">
								<?php $this->form_fields( 'merge', $id ); ?>
								<label class="screen-reader-text" for="merge-term"><?php esc_html_e( 'Merge into existing term', 'havenlytics' ); ?></label>
								<?php $this->term_select( (string) $request['taxonomy'], $term_search ); ?>
								<button type="submit" class="button"><span class="dashicons dashicons-randomize" aria-hidden="true"></span><?php esc_html_e( 'Merge Into Term', 'havenlytics' ); ?></button>
							</form>
						</div>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<section class="hvnly-txr-card" aria-label="<?php esc_attr_e( 'Danger zone', 'havenlytics' ); ?>">
				<div class="hvnly-txr-card__body">
					<div class="hvnly-txr-danger-zone">
						<div>
							<h3 class="hvnly-txr-action__title"><?php esc_html_e( 'Delete Request', 'havenlytics' ); ?></h3>
							<p class="hvnly-txr-action__hint" style="margin:0"><?php esc_html_e( 'Removes the request permanently. The audit history is preserved.', 'havenlytics' ); ?></p>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php $this->form_fields( 'delete', $id ); ?>
							<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete Permanently', 'havenlytics' ); ?></button>
						</form>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<section class="hvnly-txr-card" aria-label="<?php esc_attr_e( 'Audit timeline', 'havenlytics' ); ?>">
			<div class="hvnly-txr-card__header">
				<h2 class="hvnly-txr-card__title">
					<span class="dashicons dashicons-backup" aria-hidden="true"></span>
					<?php esc_html_e( 'Audit Timeline', 'havenlytics' ); ?>
				</h2>
			</div>
			<div class="hvnly-txr-card__body">
				<?php $this->timeline( $id ); ?>
			</div>
		</section>
		<?php
	}

	private function timeline( int $id ): void {
		$logs = $this->logs->for_request( $id );
		if ( empty( $logs ) ) {
			echo '<p class="hvnly-txr-muted">' . esc_html__( 'No audit events recorded yet.', 'havenlytics' ) . '</p>';
			return;
		}
		$icons = array(
			'submitted' => 'dashicons-upload',
			'approved'  => 'dashicons-yes',
			'rejected'  => 'dashicons-no-alt',
			'merged'    => 'dashicons-randomize',
			'deleted'   => 'dashicons-trash',
		);
		echo '<ol class="hvnly-txr-timeline">';
		foreach ( $logs as $log ) {
			$action = sanitize_key( (string) $log['action'] );
			$icon   = $icons[ $action ] ?? 'dashicons-marker';
			$actor  = get_userdata( (int) $log['actor_id'] );
			$name   = $actor instanceof \WP_User ? $actor->display_name : '#' . (int) $log['actor_id'];
			?>
			<li>
				<span class="hvnly-txr-timeline__icon hvnly-txr-timeline__icon--<?php echo esc_attr( $action ); ?>" aria-hidden="true"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></span>
				<div class="hvnly-txr-timeline__head">
					<span class="hvnly-txr-timeline__action"><?php echo esc_html( $action ); ?></span>
					<span class="hvnly-txr-timeline__meta">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: user display name, 2: formatted date and time. */
								__( 'by %1$s · %2$s', 'havenlytics' ),
								$name,
								$this->format_date( (string) $log['created_at'] )
							)
						);
						?>
					</span>
					<?php if ( '' !== (string) $log['previous_status'] || '' !== (string) $log['new_status'] ) : ?>
						<span class="hvnly-txr-timeline__meta"><?php echo esc_html( trim( (string) $log['previous_status'] . ' → ' . (string) $log['new_status'], ' →' ) ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( '' !== trim( (string) $log['reason'] ) ) : ?>
					<p class="hvnly-txr-timeline__reason"><?php echo esc_html( (string) $log['reason'] ); ?></p>
				<?php endif; ?>
			</li>
			<?php
		}
		echo '</ol>';
	}

	private function form_fields( string $action, int $id ): void {
		wp_nonce_field( 'hvnly_taxonomy_request_' . $action . '_' . $id );
		echo '<input type="hidden" name="action" value="' . esc_attr( 'hvnly_taxonomy_request_' . $action ) . '">';
		echo '<input type="hidden" name="taxonomy_request_action" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="request_id" value="' . esc_attr( (string) $id ) . '">';
	}

	private function term_select( string $taxonomy, string $search ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 50,
				'search'     => $search,
				'orderby'    => 'name',
			)
		);
		echo '<select id="merge-term" name="term_id" required><option value="">' . esc_html__( 'Select a term…', 'havenlytics' ) . '</option>';
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				echo '<option value="' . esc_attr( (string) $term->term_id ) . '">' . esc_html( $term->name ) . '</option>';
			}
		}
		echo '</select>';
	}

	private function format_date( string $value ): string {
		$timestamp = strtotime( $value . ' UTC' );
		return $timestamp ? (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : $value;
	}

	/**
	 * Render a colored status badge.
	 *
	 * @param string $status Request status.
	 * @return string
	 */
	public static function status_badge( string $status ): string {
		$status = sanitize_key( $status );
		$labels = array(
			TaxonomyRequestConstants::STATUS_PENDING    => __( 'Pending', 'havenlytics' ),
			TaxonomyRequestConstants::STATUS_PROCESSING => __( 'Processing', 'havenlytics' ),
			TaxonomyRequestConstants::STATUS_APPROVED   => __( 'Approved', 'havenlytics' ),
			TaxonomyRequestConstants::STATUS_REJECTED   => __( 'Rejected', 'havenlytics' ),
			TaxonomyRequestConstants::STATUS_MERGED     => __( 'Merged', 'havenlytics' ),
			TaxonomyRequestConstants::STATUS_DELETED    => __( 'Deleted', 'havenlytics' ),
		);
		$label  = $labels[ $status ] ?? ucfirst( $status );
		return '<span class="hvnly-txr-badge hvnly-txr-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Canonical admin URL for the request list or a request detail.
	 *
	 * @param int $request_id Request ID.
	 * @return string
	 */
	public static function url( int $request_id = 0 ): string {
		$args = array(
			'post_type' => 'hvnly_property',
			'page'      => self::MENU_SLUG,
		);
		if ( $request_id > 0 ) {
			$args['request_id'] = $request_id;
		}
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}
}
