<?php
/**
 * Admin inquiries page.
 *
 * @package HvnlyNab\ContactAgent\Admin
 * @since   3.0.2
 */

namespace HvnlyNab\ContactAgent\Admin;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ContactAgent\ContactAgentConstants;
use HvnlyNab\ContactAgent\ContactAgentFunctionsLoader;
use HvnlyNab\ContactAgent\Database\InquirySchema;
use HvnlyNab\ContactAgent\InquiryReplyRepository;
use HvnlyNab\ContactAgent\InquiryReplyService;
use HvnlyNab\ContactAgent\InquiryRepository;
use HvnlyNab\ContactAgent\InquiryUnreadCounter;

defined( 'ABSPATH' ) || exit;

/**
 * Property Inquiries admin list and detail views.
 *
 * @since 3.0.2
 */
class InquiryAdminPage {

	/** @var string Parent menu slug. */
	public const MENU_SLUG = 'hvnly_inquiries';

	/** @var string Inquiries submenu slug (must differ from parent or WP hides the submenu). */
	public const SUBMENU_SLUG = 'hvnly_marketing_inquiries';

	/** Admin hook suffix for the Inquiries submenu screen. */
	public const HOOK_INQUIRIES = 'marketing_page_hvnly_marketing_inquiries';

	/** Menu position — grouped before WordPress Posts (5). */
	private const MENU_POSITION = 4.3;

	/** @var InquiryRepository */
	private $repository;

	/** @var InquiryReplyRepository */
	private $reply_repository;

	/** @var InquiryReplyService */
	private $reply_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository       = new InquiryRepository();
		$this->reply_repository = new InquiryReplyRepository();
		$this->reply_service    = new InquiryReplyService( $this->repository, $this->reply_repository );
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		ContactAgentFunctionsLoader::load();

		InquiryUnreadCounter::register();

		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );
		add_action( 'admin_menu', array( $this, 'register_legacy_menu' ), 26 );
		add_action( 'admin_menu', array( $this, 'cleanup_menu' ), 999 );
		add_action( 'admin_init', array( $this, 'maybe_redirect_legacy_inquiries_request' ), 5 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'parent_file', array( $this, 'highlight_parent_menu' ), 10 );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ), 10, 2 );
	}

	/**
	 * @return void
	 */
	public function register_menu(): void {
		$capability = apply_filters( 'hvnly_admin_capability', 'manage_options' );
		$menu_title = esc_html__( 'Marketing', 'havenlytics' );

		add_menu_page(
			esc_html__( 'Marketing', 'havenlytics' ),
			$menu_title,
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-megaphone',
			self::MENU_POSITION
		);

		$submenu_title = esc_html__( 'Inquiries', 'havenlytics' ) . InquiryUnreadCounter::menu_badge_html( InquiryUnreadCounter::get_count() );
		add_submenu_page(
			self::MENU_SLUG,
			esc_html__( 'Property Inquiries', 'havenlytics' ),
			$submenu_title,
			$capability,
			self::SUBMENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Native WordPress menu behavior: remove auto-generated parent duplicate only.
	 *
	 * @return void
	 */
	public function cleanup_menu(): void {
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * @param string $page Admin page query arg.
	 * @return bool
	 */
	private function is_inquiries_admin_page( string $page ): bool {
		return in_array( $page, array( self::MENU_SLUG, self::SUBMENU_SLUG ), true );
	}

	/**
	 * @param string $parent_file Parent menu file.
	 * @return string
	 */
	public function highlight_parent_menu( string $parent_file ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only menu routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( $this->is_inquiries_admin_page( $page ) ) {
			return self::MENU_SLUG;
		}

		return $parent_file;
	}

	/**
	 * @param string|null $submenu_file Submenu file.
	 * @param string      $parent_file  Parent menu file.
	 * @return string|null
	 */
	public function highlight_submenu( $submenu_file, string $parent_file ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only menu routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( self::MENU_SLUG === $parent_file && $this->is_inquiries_admin_page( $page ) ) {
			return self::SUBMENU_SLUG;
		}

		return $submenu_file;
	}

	/**
	 * Redirect legacy CPT inquiries URLs to the canonical Marketing screen.
	 *
	 * Detail views are often opened via edit.php?post_type=hvnly_property&page=hvnly_inquiries&inquiry_id=...
	 * which makes WordPress treat the screen as part of the Havenlytics CPT menu. Redirecting
	 * preserves bookmarks while ensuring the screen truly belongs to Marketing.
	 *
	 * @return void
	 */
	public function maybe_redirect_legacy_inquiries_request(): void {
		if ( ! is_admin() ) {
			return;
		}

		global $pagenow;

		// Only legacy CPT route: edit.php?post_type=hvnly_property&page=hvnly_inquiries...
		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : '';
		if ( 'hvnly_property' !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( self::MENU_SLUG !== $page ) {
			return;
		}

		// Preserve inquiry_id / agent_id when present.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- passthrough for legacy bookmarks only.
		$inquiry_id = isset( $_GET['inquiry_id'] ) ? absint( wp_unslash( (string) $_GET['inquiry_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- passthrough for legacy bookmarks only.
		$agent_id = isset( $_GET['agent_id'] ) ? absint( wp_unslash( (string) $_GET['agent_id'] ) ) : 0;

		$target_args = array( 'page' => self::SUBMENU_SLUG );
		if ( $inquiry_id > 0 ) {
			$target_args['inquiry_id'] = $inquiry_id;
		}
		if ( $agent_id > 0 ) {
			$target_args['agent_id'] = $agent_id;
		}

		wp_safe_redirect( add_query_arg( $target_args, admin_url( 'admin.php' ) ), 301 );
		exit;
	}

	/**
	 * Legacy CPT submenu entry point for bookmarks.
	 *
	 * @return void
	 */
	public function register_legacy_menu(): void {
		$capability = apply_filters( 'hvnly_admin_capability', 'manage_options' );

		$hook = add_submenu_page(
			'edit.php?post_type=hvnly_property',
			esc_html__( 'Property Inquiries', 'havenlytics' ),
			esc_html__( 'Inquiries', 'havenlytics' ),
			$capability,
			self::MENU_SLUG,
			array( $this, 'render_legacy_redirect_placeholder' )
		);
		if ( is_string( $hook ) && '' !== $hook ) {
			add_action( 'load-' . $hook, array( $this, 'redirect_legacy_inquiries' ) );
		}
	}

	/**
	 * @return void
	 */
	public function render_legacy_redirect_placeholder(): void {
		// Intentionally empty; redirect runs on load-{$hook}.
	}

	/**
	 * @return void
	 */
	public function redirect_legacy_inquiries(): void {
		// Safety guard: only redirect when on the legacy CPT screen.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'hvnly_property_page_' . self::MENU_SLUG !== $screen->id ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- passthrough for legacy bookmarks only.
		$inquiry_id = isset( $_GET['inquiry_id'] ) ? absint( wp_unslash( (string) $_GET['inquiry_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- passthrough for legacy bookmarks only.
		$agent_id = isset( $_GET['agent_id'] ) ? absint( wp_unslash( (string) $_GET['agent_id'] ) ) : 0;

		$target_args = array( 'page' => self::SUBMENU_SLUG );
		if ( $inquiry_id > 0 ) {
			$target_args['inquiry_id'] = $inquiry_id;
		}
		if ( $agent_id > 0 ) {
			$target_args['agent_id'] = $agent_id;
		}

		wp_safe_redirect( add_query_arg( $target_args, admin_url( 'admin.php' ) ), 301 );
		exit;
	}

	/**
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if (
			'hvnly_property_page_' . self::MENU_SLUG !== $hook_suffix
			&& 'toplevel_page_' . self::MENU_SLUG !== $hook_suffix
			&& self::HOOK_INQUIRIES !== $hook_suffix
		) {
			return;
		}

		$version = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.0.2';

		$fonts_css_path = HVNLYNAB_ASSETS_PATH . '/admin/css/fonts.css';
		if ( file_exists( $fonts_css_path ) ) {
			wp_enqueue_style(
				'hvnly-admin-fonts',
				HVNLYNAB_ASSETS_URL . '/admin/css/fonts.css',
				array(),
				$version
			);
		}

		wp_enqueue_style(
			'hvnly-inquiries-admin',
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-inquiries-admin.css',
			array( 'hvnly-admin-fonts' ),
			$version
		);

		wp_enqueue_script(
			'hvnly-inquiries-admin',
			HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-inquiries-admin.js',
			array(),
			$version,
			true
		);
	}

	/**
	 * Process row, bulk, and detail actions.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! is_admin() || ! current_user_can( apply_filters( 'hvnly_admin_capability', 'manage_options' ) ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || ! $this->is_inquiries_admin_page( sanitize_key( wp_unslash( (string) $_GET['page'] ) ) ) ) {
			// Bulk actions POST to same page without page in GET during form submit — check POST too.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! isset( $_POST['page'] ) || ! $this->is_inquiries_admin_page( sanitize_key( wp_unslash( (string) $_POST['page'] ) ) ) ) {
				return;
			}
		}

		// Admin reply from detail view.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['hvnly_inquiry_reply'], $_POST['inquiry_id'] ) ) {
			$inquiry_id = absint( wp_unslash( $_POST['inquiry_id'] ) );
			check_admin_referer( 'hvnly_inquiry_reply_' . $inquiry_id );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$message = isset( $_POST['reply_message'] ) ? (string) wp_unslash( $_POST['reply_message'] ) : '';

			$this->process_reply( $inquiry_id, $message );
			return;
		}

		// Single GET actions.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['hvnly_action'], $_GET['inquiry_id'], $_GET['_hvnly_inquiry_nonce'] ) ) {
			$inquiry_id = absint( wp_unslash( $_GET['inquiry_id'] ) );
			$action     = sanitize_key( wp_unslash( $_GET['hvnly_action'] ) );
			$nonce      = sanitize_text_field( wp_unslash( $_GET['_hvnly_inquiry_nonce'] ) );

			if ( ! wp_verify_nonce( $nonce, 'hvnly_inquiry_action_' . $inquiry_id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'havenlytics' ) );
			}

			$this->process_action( $action, array( $inquiry_id ) );
			return;
		}

		// Bulk POST actions.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['action'] ) || isset( $_POST['action2'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = '-1' !== ( $_POST['action'] ?? '-1' ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : sanitize_key( wp_unslash( $_POST['action2'] ?? '' ) );

			if ( ! $action || '-1' === $action ) {
				return;
			}

			check_admin_referer( 'bulk-' . self::MENU_SLUG );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$ids = isset( $_POST['inquiry_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['inquiry_ids'] ) ) : array();
			$ids = array_filter( $ids );

			if ( empty( $ids ) ) {
				return;
			}

			$this->process_action( $action, $ids );
		}
	}

	/**
	 * @param string $action     Action slug.
	 * @param int[]  $inquiry_ids Inquiry IDs.
	 * @return void
	 */
	private function process_action( string $action, array $inquiry_ids ): void {
		$updated = 0;

		foreach ( $inquiry_ids as $inquiry_id ) {
			$inquiry_id = absint( $inquiry_id );
			if ( $inquiry_id <= 0 ) {
				continue;
			}

			switch ( $action ) {
				case 'mark_read':
					$result = $this->repository->update_status( $inquiry_id, ContactAgentConstants::STATUS_READ );
					if ( ! is_wp_error( $result ) ) {
						++$updated;
					}
					break;

				case 'archive':
					$result = $this->repository->update_status( $inquiry_id, ContactAgentConstants::STATUS_ARCHIVED );
					if ( ! is_wp_error( $result ) ) {
						++$updated;
					}
					break;

				case 'delete':
					$result = $this->repository->delete( $inquiry_id );
					if ( ! is_wp_error( $result ) ) {
						++$updated;
					}
					break;
			}
		}

		$messages = array(
			/* translators: %d: number of inquiries marked as read */
			'mark_read' => _n( '%d inquiry marked as read.', '%d inquiries marked as read.', $updated, 'havenlytics' ),
			/* translators: %d: number of inquiries archived */
			'archive'   => _n( '%d inquiry archived.', '%d inquiries archived.', $updated, 'havenlytics' ),
			/* translators: %d: number of inquiries deleted */
			'delete'    => _n( '%d inquiry deleted.', '%d inquiries deleted.', $updated, 'havenlytics' ),
		);

		$message = isset( $messages[ $action ] )
			? sprintf( $messages[ $action ], $updated )
			: __( 'Action completed.', 'havenlytics' );

		$redirect_args = array(
			'post_type'    => 'hvnly_property',
			'page'         => self::MENU_SLUG,
			'hvnly_notice' => rawurlencode( $message ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- passthrough filter only.
		$agent_id = isset( $_REQUEST['agent_id'] ) ? absint( wp_unslash( (string) $_REQUEST['agent_id'] ) ) : 0;
		if ( $agent_id > 0 ) {
			$redirect_args['agent_id'] = $agent_id;
		}

		$redirect = add_query_arg( $redirect_args, admin_url( 'edit.php' ) );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Send an admin reply and redirect back to the inquiry detail view.
	 *
	 * @param int    $inquiry_id Inquiry ID.
	 * @param string $message    Reply message.
	 * @return void
	 */
	private function process_reply( int $inquiry_id, string $message ): void {
		$result = $this->reply_service->send_reply( $inquiry_id, $message, get_current_user_id() );

		$redirect_args = array(
			'post_type'  => 'hvnly_property',
			'page'       => self::MENU_SLUG,
			'inquiry_id' => $inquiry_id,
		);

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['reply_id'] ) ) {
				$redirect_args['hvnly_notice']      = rawurlencode( $result->get_error_message() );
				$redirect_args['hvnly_notice_type'] = 'warning';
			} else {
				$redirect_args['hvnly_notice']      = rawurlencode( $result->get_error_message() );
				$redirect_args['hvnly_notice_type'] = 'error';
			}
		} else {
			$redirect_args['hvnly_notice']      = rawurlencode( __( 'Your reply was sent successfully.', 'havenlytics' ) );
			$redirect_args['hvnly_notice_type'] = 'success';
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * Render list or detail view.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( apply_filters( 'hvnly_admin_capability', 'manage_options' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'havenlytics' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['hvnly_notice'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['hvnly_notice'] ) ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice_type = isset( $_GET['hvnly_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['hvnly_notice_type'] ) ) : 'success';
			$notice_class = 'notice-success';

			if ( 'error' === $notice_type ) {
				$notice_class = 'notice-error';
			} elseif ( 'warning' === $notice_type ) {
				$notice_class = 'notice-warning';
			}

			if ( $notice ) {
				echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
			}
		}

		if ( ! InquirySchema::table_exists() ) {
			$this->render_missing_table_notice();
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$inquiry_id = isset( $_GET['inquiry_id'] ) ? absint( wp_unslash( $_GET['inquiry_id'] ) ) : 0;
		if ( $inquiry_id > 0 && ! isset( $_GET['hvnly_action'] ) ) {
			$this->render_detail( $inquiry_id );
			return;
		}

		$this->render_list();
	}

	/**
	 * @return void
	 */
	private function render_missing_table_notice(): void {
		?>
<div class="wrap hvnly-inquiries-admin">
    <h1><?php esc_html_e( 'Property Inquiries', 'havenlytics' ); ?></h1>
    <div class="notice notice-warning">
        <p><?php esc_html_e( 'The inquiries database table has not been created yet. Visit the dashboard once after updating Havenlytics to run migrations.', 'havenlytics' ); ?>
        </p>
    </div>
</div>
<?php
	}

	/**
	 * @return void
	 */
	private function render_list(): void {
		$list_table = new InquiryListTable( $this->repository );
		$list_table->prepare_items();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$agent_id = isset( $_REQUEST['agent_id'] ) ? absint( wp_unslash( (string) $_REQUEST['agent_id'] ) ) : 0;

		$count_args = array();
		$new_args   = array( 'status' => ContactAgentConstants::STATUS_NEW );
		if ( $agent_id > 0 ) {
			$count_args['agent_id'] = $agent_id;
			$new_args['agent_id']   = $agent_id;
		}

		$total = $this->repository->count( $count_args );
		$new   = $this->repository->count( $new_args );
		?>
<div class="wrap hvnly-inquiries-admin">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Property Inquiries', 'havenlytics' ); ?></h1>
    <hr class="wp-header-end" />

	<?php if ( $agent_id > 0 ) : ?>
		<?php
		$agent_title = get_the_title( $agent_id );
		$agent_name  = is_string( $agent_title ) && '' !== $agent_title ? $agent_title : sprintf( '#%d', $agent_id );
		$clear_url   = remove_query_arg( 'agent_id' );
		?>
    <div class="notice notice-info">
        <p>
			<?php
			printf(
				/* translators: %s: agent display name */
				esc_html__( 'Showing inquiries for agent %s.', 'havenlytics' ),
				esc_html( $agent_name )
			);
			?>
            <a href="<?php echo esc_url( $clear_url ); ?>"><?php esc_html_e( 'Clear filter', 'havenlytics' ); ?></a>
        </p>
    </div>
	<?php endif; ?>

    <div class="hvnly-inquiries-admin__stats">
        <span class="hvnly-inquiries-admin__stat">
            <?php
					printf(
						/* translators: %d: total count */
						esc_html__( 'Total: %d', 'havenlytics' ),
						(int) $total
					);
					?>
        </span>
        <span class="hvnly-inquiries-admin__stat hvnly-inquiries-admin__stat--new">
            <?php
					printf(
						/* translators: %d: new count */
						esc_html__( 'New: %d', 'havenlytics' ),
						(int) $new
					);
					?>
        </span>
    </div>

    <form method="post">
        <input type="hidden" name="page" value="<?php echo esc_attr( self::SUBMENU_SLUG ); ?>" />
		<?php if ( $agent_id > 0 ) : ?>
        <input type="hidden" name="agent_id" value="<?php echo esc_attr( (string) $agent_id ); ?>" />
		<?php endif; ?>
        <?php
				wp_nonce_field( 'bulk-' . self::MENU_SLUG );
				$list_table->display();
				?>
    </form>
</div>
<?php
	}

	/**
	 * @param int $inquiry_id Inquiry ID.
	 * @return void
	 */
	private function render_detail( int $inquiry_id ): void {
		$inquiry = $this->repository->find( $inquiry_id );
		if ( ! $inquiry ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Inquiry not found', 'havenlytics' ) . '</h1></div>';
			return;
		}

		if ( ContactAgentConstants::STATUS_NEW === ( $inquiry['status'] ?? '' ) ) {
			$this->repository->update_status( $inquiry_id, ContactAgentConstants::STATUS_READ );
			$inquiry['status'] = ContactAgentConstants::STATUS_READ;
		}

		$agent    = function_exists( 'hvnly_get_inquiry_agent_profile' ) ? hvnly_get_inquiry_agent_profile( $inquiry ) : array();
		$back_url = add_query_arg(
			array(
				'post_type' => 'hvnly_property',
				'page'      => self::MENU_SLUG,
			),
			admin_url( 'edit.php' )
		);

		$property_id   = (int) ( $inquiry['property_id'] ?? 0 );
		$replies       = $this->reply_repository->list_for_inquiry( $inquiry_id );
		$max_length    = (int) apply_filters( 'hvnly_inquiry_reply_max_length', ContactAgentConstants::REPLY_MESSAGE_MAX_LENGTH );
		$min_length    = (int) apply_filters( 'hvnly_inquiry_reply_min_length', ContactAgentConstants::REPLY_MESSAGE_MIN_LENGTH );
		$can_reply     = is_email( (string) ( $inquiry['sender_email'] ?? '' ) ) && ContactAgentConstants::STATUS_ARCHIVED !== ( $inquiry['status'] ?? '' );
		?>
<div class="wrap hvnly-inquiries-admin hvnly-inquiries-admin--detail">
    <h1>
        <?php
				printf(
					/* translators: %d: inquiry ID */
					esc_html__( 'Inquiry #%d', 'havenlytics' ),
					(int) $inquiry_id
				);
				?>
    </h1>
    <p><a href="<?php echo esc_url( $back_url ); ?>">&larr;
            <?php esc_html_e( 'Back to inquiries', 'havenlytics' ); ?></a></p>

    <div class="hvnly-inquiries-admin__detail-grid">
        <div class="hvnly-inquiries-admin__panel">
            <h2><?php esc_html_e( 'Sender', 'havenlytics' ); ?></h2>
            <dl class="hvnly-inquiries-admin__dl">
                <dt><?php esc_html_e( 'Name', 'havenlytics' ); ?></dt>
                <dd><?php echo esc_html( (string) ( $inquiry['sender_name'] ?? '' ) ); ?></dd>
                <dt><?php esc_html_e( 'Email', 'havenlytics' ); ?></dt>
                <dd>
                    <?php
							$email = (string) ( $inquiry['sender_email'] ?? '' );
							if ( is_email( $email ) ) {
								echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
							} else {
								echo esc_html( $email );
							}
							?>
                </dd>
                <?php if ( ! empty( $inquiry['sender_phone'] ) ) : ?>
                <dt><?php esc_html_e( 'Phone', 'havenlytics' ); ?></dt>
                <dd><a
                        href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', (string) $inquiry['sender_phone'] ) ); ?>"><?php echo esc_html( (string) $inquiry['sender_phone'] ); ?></a>
                </dd>
                <?php endif; ?>
            </dl>
        </div>

        <div class="hvnly-inquiries-admin__panel">
            <h2><?php esc_html_e( 'Property & Agent', 'havenlytics' ); ?></h2>
            <dl class="hvnly-inquiries-admin__dl">
                <dt><?php esc_html_e( 'Property', 'havenlytics' ); ?></dt>
                <dd>
                    <?php
							if ( $property_id > 0 ) {
								$title = get_the_title( $property_id );
								$edit  = get_edit_post_link( $property_id );
								$view  = get_permalink( $property_id );
								if ( $edit ) {
									echo '<a href="' . esc_url( $edit ) . '">' . esc_html( $title ?: '#' . $property_id ) . '</a>';
								} else {
									echo esc_html( $title ?: '#' . $property_id );
								}
								if ( $view ) {
									echo ' <a href="' . esc_url( $view ) . '" target="_blank" rel="noopener noreferrer">(' . esc_html__( 'view', 'havenlytics' ) . ')</a>';
								}
							} else {
								echo '&mdash;';
							}
							?>
                </dd>
                <dt><?php esc_html_e( 'Agent', 'havenlytics' ); ?></dt>
                <dd>
                    <?php
							echo wp_kses_post( $this->render_inquiry_agent_cell( $inquiry, $agent ) );
							?>
                </dd>
                <dt><?php esc_html_e( 'Status', 'havenlytics' ); ?></dt>
                <dd><?php echo esc_html( ucfirst( (string) ( $inquiry['status'] ?? '' ) ) ); ?></dd>
                <dt><?php esc_html_e( 'Received', 'havenlytics' ); ?></dt>
                <dd>
                    <?php
							$timestamp = ! empty( $inquiry['created_at'] ) ? strtotime( (string) $inquiry['created_at'] . ' UTC' ) : false;
							echo esc_html( $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : (string) ( $inquiry['created_at'] ?? '' ) );
							?>
                </dd>
                <dt><?php esc_html_e( 'Source', 'havenlytics' ); ?></dt>
                <dd><?php echo esc_html( $this->format_inquiry_source( (string) ( $inquiry['source'] ?? '' ) ) ); ?></dd>
            </dl>
        </div>
    </div>

    <div class="hvnly-inquiries-admin__panel hvnly-inquiries-admin__panel--conversation">
        <h2><?php esc_html_e( 'Conversation', 'havenlytics' ); ?></h2>

        <div class="hvnly-inquiries-admin__thread">
            <div class="hvnly-inquiries-admin__thread-item hvnly-inquiries-admin__thread-item--inbound">
                <div class="hvnly-inquiries-admin__thread-meta">
                    <strong><?php echo esc_html( (string) ( $inquiry['sender_name'] ?? __( 'Visitor', 'havenlytics' ) ) ); ?></strong>
                    <span><?php echo esc_html( $this->format_inquiry_datetime( (string) ( $inquiry['created_at'] ?? '' ) ) ); ?></span>
                    <span
                        class="hvnly-inquiries-admin__thread-label"><?php esc_html_e( 'Original inquiry', 'havenlytics' ); ?></span>
                </div>
                <div class="hvnly-inquiries-admin__thread-body">
                    <?php echo nl2br( esc_html( (string) ( $inquiry['message'] ?? '' ) ) ); ?></div>
            </div>

            <?php foreach ( $replies as $reply ) : ?>
            <div class="hvnly-inquiries-admin__thread-item hvnly-inquiries-admin__thread-item--outbound">
                <div class="hvnly-inquiries-admin__thread-meta">
                    <strong><?php echo esc_html( (string) ( $reply['author_name'] ?? __( 'Admin', 'havenlytics' ) ) ); ?></strong>
                    <span><?php echo esc_html( $this->format_inquiry_datetime( (string) ( $reply['created_at'] ?? '' ) ) ); ?></span>
                    <?php if ( ! empty( $reply['email_sent'] ) ) : ?>
                    <span
                        class="hvnly-inquiries-admin__thread-label hvnly-inquiries-admin__thread-label--sent"><?php esc_html_e( 'Emailed to visitor', 'havenlytics' ); ?></span>
                    <?php else : ?>
                    <span
                        class="hvnly-inquiries-admin__thread-label hvnly-inquiries-admin__thread-label--failed"><?php esc_html_e( 'Saved only (email failed)', 'havenlytics' ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="hvnly-inquiries-admin__thread-body">
                    <?php echo nl2br( esc_html( (string) ( $reply['message'] ?? '' ) ) ); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ( $can_reply ) : ?>
    <div class="hvnly-inquiries-admin__panel hvnly-inquiries-admin__panel--reply">
        <h2><?php esc_html_e( 'Reply to Visitor', 'havenlytics' ); ?></h2>
        <p class="hvnly-inquiries-admin__reply-help">
            <?php
						printf(
							/* translators: %s: visitor email address */
							esc_html__( 'Your reply will be emailed securely to %s and stored in this conversation thread.', 'havenlytics' ),
							esc_html( (string) $inquiry['sender_email'] )
						);
						?>
        </p>
        <form method="post" class="hvnly-inquiries-admin__reply-form"
            data-max-length="<?php echo esc_attr( (string) $max_length ); ?>">
            <?php wp_nonce_field( 'hvnly_inquiry_reply_' . $inquiry_id ); ?>
            <input type="hidden" name="page" value="<?php echo esc_attr( self::SUBMENU_SLUG ); ?>" />
            <input type="hidden" name="hvnly_inquiry_reply" value="1" />
            <input type="hidden" name="inquiry_id" value="<?php echo esc_attr( (string) $inquiry_id ); ?>" />
            <label class="screen-reader-text"
                for="hvnly-inquiry-reply-message"><?php esc_html_e( 'Reply message', 'havenlytics' ); ?></label>
            <textarea id="hvnly-inquiry-reply-message" name="reply_message" class="hvnly-inquiries-admin__reply-input"
                rows="6" minlength="<?php echo esc_attr( (string) max( 1, $min_length ) ); ?>"
                maxlength="<?php echo esc_attr( (string) $max_length ); ?>" required
                placeholder="<?php esc_attr_e( 'Write your reply to the visitor...', 'havenlytics' ); ?>"></textarea>
            <div class="hvnly-inquiries-admin__reply-footer">
                <span class="hvnly-inquiries-admin__reply-counter">0 /
                    <?php echo esc_html( (string) $max_length ); ?></span>
                <button type="submit" class="button button-primary hvnly-inquiries-admin__reply-submit"
                    data-sending-label="<?php esc_attr_e( 'Sending reply...', 'havenlytics' ); ?>">
                    <?php esc_html_e( 'Send Reply', 'havenlytics' ); ?>
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <p class="hvnly-inquiries-admin__detail-actions">
        <?php if ( ContactAgentConstants::STATUS_ARCHIVED !== ( $inquiry['status'] ?? '' ) ) : ?>
        <a class="button"
            href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'post_type' => 'hvnly_property', 'page' => self::MENU_SLUG, 'hvnly_action' => 'archive', 'inquiry_id' => $inquiry_id ), admin_url( 'edit.php' ) ), 'hvnly_inquiry_action_' . $inquiry_id, '_hvnly_inquiry_nonce' ) ); ?>">
            <?php esc_html_e( 'Archive', 'havenlytics' ); ?>
        </a>
        <?php endif; ?>
        <a class="button button-link-delete"
            href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'post_type' => 'hvnly_property', 'page' => self::MENU_SLUG, 'hvnly_action' => 'delete', 'inquiry_id' => $inquiry_id ), admin_url( 'edit.php' ) ), 'hvnly_inquiry_action_' . $inquiry_id, '_hvnly_inquiry_nonce' ) ); ?>"
            onclick="return confirm('<?php echo esc_js( __( 'Delete this inquiry permanently?', 'havenlytics' ) ); ?>');">
            <?php esc_html_e( 'Delete', 'havenlytics' ); ?>
        </a>
        <?php if ( is_email( (string) ( $inquiry['sender_email'] ?? '' ) ) ) : ?>
        <a class="button"
            href="mailto:<?php echo esc_attr( (string) $inquiry['sender_email'] ); ?>?subject=<?php echo esc_attr( rawurlencode( sprintf( 
				/* translators: %s: property title or "property" if title not available */
				__( 'Re: Inquiry about %s', 'havenlytics' ), get_the_title( $property_id ) ?: __( 'property', 'havenlytics' ) ) ) ); ?>">
            <?php esc_html_e( 'Open in Email Client', 'havenlytics' ); ?>
        </a>
        <?php endif; ?>
    </p>
</div>
<?php
	}

	/**
	 * Format inquiry datetime for admin display.
	 *
	 * @param string $datetime MySQL datetime (UTC).
	 * @return string
	 */
	private function format_inquiry_datetime( string $datetime ): string {
		if ( '' === trim( $datetime ) ) {
			return '';
		}

		$timestamp = strtotime( $datetime . ' UTC' );
		if ( ! $timestamp ) {
			return $datetime;
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Render agent name and contact details for inquiry detail view.
	 *
	 * @param array<string, mixed> $inquiry Inquiry row.
	 * @param array<string, mixed> $agent   Resolved agent profile.
	 * @return string
	 */
	private function render_inquiry_agent_cell( array $inquiry, array $agent ): string {
		if ( empty( $agent['name'] ) ) {
			return '&mdash;';
		}

		$agent_post_id = 0;

		if ( ! empty( $agent['id'] ) && AgentConstants::POST_TYPE === get_post_type( (int) $agent['id'] ) ) {
			$agent_post_id = (int) $agent['id'];
		} elseif ( ! empty( $inquiry['agent_id'] ) && AgentConstants::POST_TYPE === get_post_type( (int) $inquiry['agent_id'] ) ) {
			$agent_post_id = (int) $inquiry['agent_id'];
		}

		$html      = esc_html( (string) $agent['name'] );
		$edit_link = $agent_post_id > 0 ? get_edit_post_link( $agent_post_id ) : '';

		if ( $edit_link ) {
			$html .= '<br /><a href="' . esc_url( $edit_link ) . '">(' . esc_html__( 'Edit Agent', 'havenlytics' ) . ')</a>';
		}

		$profile_url = ! empty( $agent['profile_url'] ) ? (string) $agent['profile_url'] : '';
		if ( '' === $profile_url && $agent_post_id > 0 ) {
			$profile_url = (string) get_permalink( $agent_post_id );
		}

		if ( '' !== $profile_url ) {
			$html .= ' <a href="' . esc_url( $profile_url ) . '" class="hvnly-inquiries-admin__view-link" target="_blank" rel="noopener noreferrer">(' . esc_html__( 'View Profile', 'havenlytics' ) . ')</a>';
		}

		if ( ! empty( $agent['email'] ) && is_email( (string) $agent['email'] ) ) {
			$html .= '<br /><a href="mailto:' . esc_attr( (string) $agent['email'] ) . '">' . esc_html( (string) $agent['email'] ) . '</a>';
		}

		return $html;
	}

	/**
	 * Human-readable inquiry source label.
	 *
	 * @param string $source Stored source slug.
	 * @return string
	 */
	private function format_inquiry_source( string $source ): string {
		return ContactAgentConstants::source_label( $source );
	}
}