<?php
/**
 * Professional Agent management list table (Users-screen style).
 *
 * @package HvnlyNab\Agent\Admin
 * @since   3.2.0
 */

namespace HvnlyNab\Agent\Admin;

use HvnlyNab\Agent\AgentConstants;
use HvnlyNab\ContactAgent\Admin\InquiryAdminPage;
use HvnlyNab\Workspace\Auth\AgentActivityTracker;
use HvnlyNab\Workspace\Auth\CapabilityRegistrar;
use HvnlyNab\Workspace\Auth\PortalCapabilities;
use HvnlyNab\Workspace\Auth\WorkspaceRegistrationStatus;
use HvnlyNab\Workspace\Health\AgentIdentityHealthAdminPage;
use HvnlyNab\Workspace\Identity\EmailVerificationBadge;

defined( 'ABSPATH' ) || exit;

/**
 * Columns, filters, search, bulk extras, quick-action enrichment.
 *
 * Does not change identity SSOT or Workspace architecture.
 *
 * @since 3.2.0
 */
final class AgentManagementAdminList {

	/**
	 * @var AgentAdminSummaryService
	 */
	private $summaries;

	/**
	 * @var bool
	 */
	private $primed = false;

	/**
	 * @param AgentAdminSummaryService|null $summaries Summaries.
	 */
	public function __construct( ?AgentAdminSummaryService $summaries = null ) {
		$this->summaries = $summaries ? $summaries : new AgentAdminSummaryService();
	}

	/**
	 * @return void
	 */
	public function register(): void {
		$slug = AgentConstants::POST_TYPE;

		add_filter( 'manage_' . $slug . '_posts_columns', array( $this, 'columns' ), 30 );
		add_action( 'manage_' . $slug . '_posts_custom_column', array( $this, 'column_content' ), 30, 2 );
		add_filter( 'list_table_primary_column', array( $this, 'primary_column' ), 10, 2 );
		add_filter( 'manage_edit-' . $slug . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
		add_action( 'restrict_manage_posts', array( $this, 'render_filters' ) );
		add_filter( 'views_edit-' . $slug, array( $this, 'lifecycle_views' ) );
		add_filter( 'posts_search', array( $this, 'extend_search' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . $slug, array( $this, 'bulk_actions' ), 30 );
		add_filter( 'handle_bulk_actions-edit-' . $slug, array( $this, 'handle_bulk' ), 20, 3 );
		add_filter( 'post_row_actions', array( $this, 'quick_actions' ), 30, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'admin_init', array( $this, 'maybe_export' ) );
	}

	/**
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		unset( $hook );
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || AgentConstants::POST_TYPE !== $screen->post_type || 'edit' !== $screen->base ) {
			return;
		}

		$ver         = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.2.0';
		$reg_handle  = 'hvnly-ws-registration-admin';
		$mgmt_handle = 'hvnly-agent-management-admin';

		// Lifecycle badges live in registration-admin CSS — register before depending on it.
		if ( ! wp_style_is( $reg_handle, 'registered' ) ) {
			wp_register_style(
				$reg_handle,
				HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-ws-registration-admin.css',
				array(),
				$ver
			);
		}

		wp_register_style(
			$mgmt_handle,
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-agent-management-admin.css',
			array( $reg_handle ),
			$ver
		);
		wp_enqueue_style( $mgmt_handle );

		$js_handle = 'hvnly-agent-management-admin';
		wp_register_script(
			$js_handle,
			HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-agent-management-admin.js',
			array(),
			$ver,
			true
		);
		wp_enqueue_script( $js_handle );
	}

	/**
	 * Use the consolidated Agent column as the primary (row actions) column.
	 *
	 * @param string $default Default primary column.
	 * @param string $screen_id Screen id.
	 * @return string
	 */
	public function primary_column( string $default, string $screen_id ): string {
		if ( 'edit-' . AgentConstants::POST_TYPE === $screen_id ) {
			return 'hvnly_mgmt_agent';
		}
		return $default;
	}

	/**
	 * Keep title sorting available on the Agent column.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['hvnly_mgmt_agent'] = 'title';
		return $columns;
	}

	/**
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$new = array();
		if ( isset( $columns['cb'] ) ) {
			$new['cb'] = $columns['cb'];
		}
		$new['hvnly_mgmt_agent']     = __( 'Agent', 'havenlytics' );
		$new['hvnly_mgmt_listings']  = __( 'Listings', 'havenlytics' );
		$new['hvnly_mgmt_inquiries'] = __( 'Inquiries', 'havenlytics' );
		$new['hvnly_mgmt_summary']   = __( 'Status', 'havenlytics' );
		$new['hvnly_mgmt_activity']  = __( 'Activity', 'havenlytics' );

		return $new;
	}

	/**
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function column_content( string $column, int $post_id ): void {
		$this->maybe_prime_page();
		$row = $this->summaries->get( $post_id );

		switch ( $column ) {
			case 'hvnly_mgmt_agent':
				$this->render_agent_column( $post_id, $row );
				break;

			case 'hvnly_mgmt_listings':
				$this->render_listings_column( $post_id, $row );
				break;

			case 'hvnly_mgmt_inquiries':
				$this->render_inquiries_column( $post_id, $row );
				break;

			case 'hvnly_mgmt_summary':
				$this->render_summary_column( $post_id, $row );
				break;

			case 'hvnly_mgmt_activity':
				$this->render_activity_column( $row );
				break;

			// Legacy column keys (screen options / cached prefs) — keep rendering.
			case 'hvnly_mgmt_avatar':
			case 'hvnly_mgmt_email':
			case 'hvnly_mgmt_username':
			case 'hvnly_mgmt_agency':
			case 'hvnly_mgmt_properties':
			case 'hvnly_mgmt_published':
			case 'hvnly_mgmt_pending':
			case 'hvnly_mgmt_completeness':
			case 'hvnly_ws_reg_status':
			case 'hvnly_mgmt_last_login':
			case 'hvnly_mgmt_last_activity':
			case 'hvnly_ws_workspace':
			case 'hvnly_mgmt_health':
				break;
		}
	}

	/**
	 * @param int                  $post_id Agent ID.
	 * @param array<string, mixed> $row     Summary row.
	 * @return void
	 */
	private function render_agent_column( int $post_id, array $row ): void {
		$edit = get_edit_post_link( $post_id, 'raw' );
		$name = get_the_title( $post_id );
		if ( '' === $name ) {
			$name = __( '(no title)', 'havenlytics' );
		}

		$email = (string) get_post_meta( $post_id, AgentConstants::META_EMAIL, true );
		if ( '' === $email && ! empty( $row['user_email'] ) ) {
			$email = (string) $row['user_email'];
		}
		$username = (string) ( $row['username'] ?? '' );
		$uid      = absint( $row['user_id'] ?? 0 );
		$user_url = $uid > 0 ? get_edit_user_link( $uid ) : '';
		$agency   = (string) ( $row['agency_names'] ?? '' );

		echo '<div class="hvnly-mgmt-agent">';

		echo '<div class="hvnly-mgmt-agent__avatar">';
		// Single avatar chain: uploaded photo → Gravatar → Havenlytics placeholder.
		$avatar_url = \HvnlyNab\Common\AvatarService::resolve_url( $post_id, $uid, 48, 'thumbnail' );
		echo \HvnlyNab\Common\AvatarService::img_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
			$avatar_url,
			array(
				'class'   => 'hvnly-mgmt-agent__photo',
				'width'   => 48,
				'height'  => 48,
				'alt'     => '',
				'loading' => 'lazy',
			)
		);
		echo '</div>';

		echo '<div class="hvnly-mgmt-agent__body">';
		if ( $edit ) {
			printf(
				'<a class="hvnly-mgmt-agent__name row-title" href="%s">%s</a>',
				esc_url( $edit ),
				esc_html( $name )
			);
		} else {
			printf( '<span class="hvnly-mgmt-agent__name">%s</span>', esc_html( $name ) );
		}

		echo '<div class="hvnly-mgmt-agent__meta">';
		if ( $email ) {
			printf(
				'<a class="hvnly-mgmt-agent__email" href="mailto:%1$s">%2$s</a>',
				esc_attr( $email ),
				esc_html( $email )
			);
		} else {
			echo '<span class="hvnly-mgmt-empty">' . esc_html__( 'No email', 'havenlytics' ) . '</span>';
		}
		if ( $username ) {
			if ( $user_url ) {
				printf(
					'<a class="hvnly-mgmt-agent__user" href="%s">@%s</a>',
					esc_url( $user_url ),
					esc_html( $username )
				);
			} else {
				printf( '<span class="hvnly-mgmt-agent__user">@%s</span>', esc_html( $username ) );
			}
		} else {
			echo '<span class="hvnly-mgmt-empty">' . esc_html__( 'No user', 'havenlytics' ) . '</span>';
		}
		echo '</div>';

		if ( $agency ) {
			printf(
				'<div class="hvnly-mgmt-agent__agency"><span class="dashicons dashicons-building" aria-hidden="true"></span>%s</div>',
				esc_html( $agency )
			);
		} else {
			echo '<div class="hvnly-mgmt-agent__agency hvnly-mgmt-empty">' . esc_html__( 'No agency', 'havenlytics' ) . '</div>';
		}

		echo '</div></div>';
	}

	/**
	 * @param int                  $post_id Agent ID.
	 * @param array<string, mixed> $row     Summary.
	 * @return void
	 */
	private function render_listings_column( int $post_id, array $row ): void {
		$props       = isset( $row['properties'] ) && is_array( $row['properties'] ) ? $row['properties'] : array();
		$hvnly_total = (int) ( $props['total'] ?? 0 );
		$hvnly_pub   = (int) ( $props['published'] ?? 0 );
		$hvnly_pend  = (int) ( $props['pending'] ?? 0 );
		$url         = admin_url( 'edit.php?post_type=' . AgentConstants::PROPERTY_POST_TYPE . '&hvnly_assigned_agent=' . $post_id );

		echo '<div class="hvnly-mgmt-listings">';
		printf(
			'<a class="hvnly-mgmt-listings__total" href="%s" title="%s">%s</a>',
			esc_url( $url ),
			esc_attr__( 'View assigned properties', 'havenlytics' ),
			esc_html( (string) $hvnly_total )
		);
		echo '<div class="hvnly-mgmt-listings__breakdown">';
		printf(
			'<span class="hvnly-mgmt-listings__stat hvnly-mgmt-listings__stat--pub" title="%s"><span class="hvnly-mgmt-dot"></span>%s</span>',
			esc_attr__( 'Published', 'havenlytics' ),
			esc_html( (string) $hvnly_pub )
		);
		printf(
			'<span class="hvnly-mgmt-listings__stat hvnly-mgmt-listings__stat--pend" title="%s"><span class="hvnly-mgmt-dot"></span>%s</span>',
			esc_attr__( 'Pending', 'havenlytics' ),
			esc_html( (string) $hvnly_pend )
		);
		echo '</div></div>';
	}

	/**
	 * @param array<string, mixed> $row Summary.
	 * @return void
	 */
	private function render_inquiries_column( int $post_id, array $row ): void {
		$inq          = isset( $row['inquiries'] ) && is_array( $row['inquiries'] ) ? $row['inquiries'] : array();
		$hvnly_total  = (int) ( $inq['total'] ?? 0 );
		$hvnly_unread = (int) ( $inq['unread'] ?? 0 );
		$tip          = sprintf(
			/* translators: 1: unread 2: open 3: replied 4: archived */
			__( 'Unread %1$d · Open %2$d · Replied %3$d · Archived %4$d', 'havenlytics' ),
			$hvnly_unread,
			(int) ( $inq['open'] ?? 0 ),
			(int) ( $inq['replied'] ?? 0 ),
			(int) ( $inq['archived'] ?? 0 )
		);

		$url = '';
		if ( current_user_can( 'manage_options' ) ) {
			$url = $this->inquiries_url_for_agent( $post_id );
		}

		echo '<div class="hvnly-mgmt-inquiries" title="' . esc_attr( $tip ) . '">';
		if ( '' !== $url ) {
			printf(
				'<a class="hvnly-mgmt-inquiries__total" href="%s" title="%s">%s</a>',
				esc_url( $url ),
				esc_attr__( 'View inquiries for this agent', 'havenlytics' ),
				esc_html( (string) $hvnly_total )
			);
		} else {
			printf( '<span class="hvnly-mgmt-inquiries__total">%s</span>', esc_html( (string) $hvnly_total ) );
		}
		if ( $hvnly_unread > 0 ) {
			printf(
				'<span class="hvnly-mgmt-pill hvnly-mgmt-pill--warn">%s %s</span>',
				esc_html__( 'Unread', 'havenlytics' ),
				esc_html( (string) $hvnly_unread )
			);
		} elseif ( 0 === $hvnly_total ) {
			echo '<span class="hvnly-mgmt-empty">' . esc_html__( 'None', 'havenlytics' ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * Lifecycle + workspace + health + profile completeness.
	 *
	 * @param int                  $post_id Agent ID.
	 * @param array<string, mixed> $row     Summary.
	 * @return void
	 */
	private function render_summary_column( int $post_id, array $row ): void {
		$status    = $row['lifecycle'] ?? WorkspaceRegistrationStatus::get_for_agent( $post_id );
		$ws        = (string) ( $row['workspace'] ?? '—' );
		$flags     = isset( $row['health']['flags'] ) ? (array) $row['health']['flags'] : array();
		$hvnly_pct = (int) ( $row['completeness']['percent'] ?? 0 );
		$miss      = implode( ', ', (array) ( $row['completeness']['missing'] ?? array() ) );
		$level     = $hvnly_pct >= 90 ? 'ok' : ( $hvnly_pct >= 70 ? 'mid' : 'low' );

		echo '<div class="hvnly-mgmt-summary">';
		echo '<div class="hvnly-mgmt-summary__badges">';
		if ( null !== $status && '' !== $status ) {
			echo wp_kses_post( WorkspaceRegistrationStatus::badge_html( (string) $status ) );
		} else {
			echo '<span class="hvnly-mgmt-empty">&mdash;</span>';
		}

		$ws_class = 'hvnly-mgmt-chip';
		if ( __( 'Ready', 'havenlytics' ) === $ws ) {
			$ws_class .= ' hvnly-mgmt-chip--ok';
		} elseif ( in_array( $ws, array( __( 'Suspended', 'havenlytics' ), __( 'Blocked', 'havenlytics' ), __( 'Archived', 'havenlytics' ), __( 'Disabled', 'havenlytics' ) ), true ) ) {
			$ws_class .= ' hvnly-mgmt-chip--bad';
		} elseif ( __( 'Not Linked', 'havenlytics' ) === $ws || __( 'Waiting Approval', 'havenlytics' ) === $ws ) {
			$ws_class .= ' hvnly-mgmt-chip--warn';
		}
		printf( '<span class="%s" title="%s">%s</span>', esc_attr( $ws_class ), esc_attr__( 'Workspace', 'havenlytics' ), esc_html( $ws ) );

		$user_id = absint( get_post_meta( $post_id, AgentConstants::META_LINKED_USER_ID, true ) );
		if ( $user_id > 0 ) {
			// Same SSOT as Users → Email column (`EmailVerificationFactor::is_satisfied`).
			echo wp_kses_post( EmailVerificationBadge::html( $user_id, false ) );
		}

		if ( empty( $flags ) ) {
			echo '<span class="hvnly-mgmt-pill hvnly-mgmt-pill--ok">' . esc_html__( 'Healthy', 'havenlytics' ) . '</span>';
		} else {
			$url   = (string) ( $row['health']['url'] ?? admin_url( 'tools.php?page=' . AgentIdentityHealthAdminPage::PAGE_SLUG ) );
			$label = (string) ( $row['health']['label'] ?? __( 'Issues', 'havenlytics' ) );
			printf(
				'<a class="hvnly-mgmt-pill hvnly-mgmt-pill--error" href="%s">%s</a>',
				esc_url( $url ),
				esc_html( $label )
			);
		}
		echo '</div>';

		printf(
			'<div class="hvnly-mgmt-progress hvnly-mgmt-progress--%1$s" title="%2$s"><div class="hvnly-mgmt-progress__track"><span class="hvnly-mgmt-progress__fill" style="width:%3$s%%"></span></div><span class="hvnly-mgmt-progress__label">%3$s%%</span></div>',
			esc_attr( $level ),
			esc_attr( $miss ? $miss : __( 'Profile complete', 'havenlytics' ) ),
			esc_attr( (string) $hvnly_pct )
		);

		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $row Summary.
	 * @return void
	 */
	private function render_activity_column( array $row ): void {
		$login = (string) ( $row['activity']['login'] ?? '' );
		$act   = (string) ( $row['activity']['activity'] ?? '' );

		echo '<div class="hvnly-mgmt-activity">';
		echo $this->activity_line( __( 'Login', 'havenlytics' ), $login ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes.
		echo $this->activity_line( __( 'Active', 'havenlytics' ), $act ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	/**
	 * @param string $label Label.
	 * @param string $gmt   GMT datetime.
	 * @return string
	 */
	private function activity_line( string $label, string $gmt ): string {
		$rel   = AgentActivityTracker::format_admin( $gmt );
		$empty = ( '' === $gmt || '—' === $rel );

		if ( $empty ) {
			return sprintf(
				'<div class="hvnly-mgmt-activity__row"><span class="hvnly-mgmt-activity__label">%s</span><span class="hvnly-mgmt-empty">%s</span></div>',
				esc_html( $label ),
				esc_html__( 'Never', 'havenlytics' )
			);
		}

		$ts  = strtotime( $gmt . ' UTC' );
		$abs = $ts ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : $gmt;

		return sprintf(
			'<div class="hvnly-mgmt-activity__row"><span class="hvnly-mgmt-activity__label">%s</span><time class="hvnly-mgmt-activity__time" datetime="%s" title="%s">%s</time></div>',
			esc_html( $label ),
			esc_attr( gmdate( 'c', (int) $ts ) ),
			esc_attr( (string) $abs ),
			esc_html( $rel )
		);
	}

	/**
	 * @return void
	 */
	private function maybe_prime_page(): void {
		if ( $this->primed || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || AgentConstants::POST_TYPE !== $screen->post_type ) {
			return;
		}
		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query || empty( $wp_query->posts ) ) {
			return;
		}
		$ids = array();
		foreach ( $wp_query->posts as $post ) {
			if ( $post instanceof \WP_Post ) {
				$ids[] = (int) $post->ID;
			} elseif ( is_numeric( $post ) ) {
				$ids[] = (int) $post;
			}
		}
		$this->summaries->prime( $ids );
		$this->primed = true;
	}

	/**
	 * @return void
	 */
	public function render_filters(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || AgentConstants::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$current = isset( $_GET['hvnly_agent_mgmt'] ) ? sanitize_key( wp_unslash( $_GET['hvnly_agent_mgmt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$options = array(
			''                   => __( 'All management filters', 'havenlytics' ),
			'has_properties'     => __( 'Has Properties', 'havenlytics' ),
			'no_properties'      => __( 'No Properties', 'havenlytics' ),
			'has_user'           => __( 'Has User', 'havenlytics' ),
			'broken_identity'    => __( 'Broken Identity', 'havenlytics' ),
			'profile_incomplete' => __( 'Profile Incomplete', 'havenlytics' ),
		);

		echo '<select name="hvnly_agent_mgmt" id="hvnly_agent_mgmt">';
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Lifecycle view tabs.
	 *
	 * @param array<string, string> $views Views.
	 * @return array<string, string>
	 */
	public function lifecycle_views( array $views ): array {
		$base    = admin_url( 'edit.php?post_type=' . AgentConstants::POST_TYPE );
		$current = isset( $_GET['hvnly_lifecycle'] ) ? sanitize_key( wp_unslash( $_GET['hvnly_lifecycle'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$map = array(
			'approved'  => __( 'Approved', 'havenlytics' ),
			'active'    => __( 'Active', 'havenlytics' ),
			'pending'   => __( 'Pending', 'havenlytics' ),
			'suspended' => __( 'Suspended', 'havenlytics' ),
			'blocked'   => __( 'Blocked', 'havenlytics' ),
			'archived'  => __( 'Archived', 'havenlytics' ),
		);

		foreach ( $map as $slug => $label ) {
			$url                          = add_query_arg( 'hvnly_lifecycle', $slug, $base );
			$class                        = $current === $slug ? 'class="current"' : '';
			$views[ 'hvnly_lc_' . $slug ] = sprintf(
				'<a href="%s" %s>%s</a>',
				esc_url( $url ),
				$class,
				esc_html( $label )
			);
		}

		return $views;
	}

	/**
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function filter_query( $query ): void {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return;
		}
		if ( AgentConstants::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$lifecycle = isset( $_GET['hvnly_lifecycle'] ) ? sanitize_key( wp_unslash( $_GET['hvnly_lifecycle'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $lifecycle && in_array( $lifecycle, WorkspaceRegistrationStatus::all(), true ) ) {
			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = array(
				'key'   => WorkspaceRegistrationStatus::AGENT_META,
				'value' => $lifecycle,
			);
			$query->set( 'meta_query', $meta_query );
		}

		$mgmt = isset( $_GET['hvnly_agent_mgmt'] ) ? sanitize_key( wp_unslash( $_GET['hvnly_agent_mgmt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'has_user' === $mgmt ) {
			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = array(
				'key'     => AgentConstants::META_LINKED_USER_ID,
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			);
			$query->set( 'meta_query', $meta_query );
		} elseif ( 'broken_identity' === $mgmt ) {
			$meta_query   = (array) $query->get( 'meta_query' );
			$meta_query[] = array(
				'key'     => AgentConstants::META_LINKED_USER_ID,
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			);
			$query->set( 'meta_query', $meta_query );
			add_filter( 'posts_results', array( $this, 'filter_broken_identity_results' ), 10, 2 );
		} elseif ( 'profile_incomplete' === $mgmt ) {
			add_filter( 'the_posts', array( $this, 'filter_incomplete_posts' ), 10, 2 );
		} elseif ( 'has_properties' === $mgmt || 'no_properties' === $mgmt ) {
			$query->set( 'hvnly_mgmt_props', $mgmt );
			add_filter( 'posts_clauses', array( $this, 'filter_property_clauses' ), 10, 2 );
		}
	}

	/**
	 * @param \WP_Post[] $posts Posts.
	 * @param \WP_Query  $query Query.
	 * @return \WP_Post[]
	 */
	public function filter_broken_identity_results( $posts, $query ) {
		remove_filter( 'posts_results', array( $this, 'filter_broken_identity_results' ), 10 );
		if ( ! is_array( $posts ) ) {
			return $posts;
		}
		$out = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$uid = absint( get_post_meta( $post->ID, AgentConstants::META_LINKED_USER_ID, true ) );
			if ( $uid > 0 && ! get_userdata( $uid ) ) {
				$out[] = $post;
			}
		}
		return $out;
	}

	/**
	 * @param \WP_Post[] $posts Posts.
	 * @param \WP_Query  $query Query.
	 * @return \WP_Post[]
	 */
	public function filter_incomplete_posts( $posts, $query ) {
		remove_filter( 'the_posts', array( $this, 'filter_incomplete_posts' ), 10 );
		if ( ! is_array( $posts ) || AgentConstants::POST_TYPE !== $query->get( 'post_type' ) ) {
			return $posts;
		}
		$svc = new \HvnlyNab\Agent\AgentProfileCompletenessService();
		$out = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof \WP_Post && $svc->percent( (int) $post->ID ) < 70 ) {
				$out[] = $post;
			}
		}
		return $out;
	}

	/**
	 * @param array<string, string> $clauses Clauses.
	 * @param \WP_Query             $query   Query.
	 * @return array<string, string>
	 */
	public function filter_property_clauses( $clauses, $query ) {
		remove_filter( 'posts_clauses', array( $this, 'filter_property_clauses' ), 10 );
		$mode = $query->get( 'hvnly_mgmt_props' );
		if ( ! in_array( $mode, array( 'has_properties', 'no_properties' ), true ) ) {
			return $clauses;
		}

		global $wpdb;
		$exists = "EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} prop ON prop.ID = pm.post_id
			WHERE pm.meta_key = '" . esc_sql( AgentConstants::META_PROPERTY_AGENTS ) . "'
			AND prop.post_type = '" . esc_sql( AgentConstants::PROPERTY_POST_TYPE ) . "'
			AND prop.post_status IN ('publish','pending','draft')
			AND (
				pm.meta_value LIKE CONCAT('%i:', {$wpdb->posts}.ID, ';%')
				OR pm.meta_value LIKE CONCAT('%[', {$wpdb->posts}.ID, ']%')
				OR pm.meta_value LIKE CONCAT('%[', {$wpdb->posts}.ID, ',%')
				OR pm.meta_value LIKE CONCAT('%,', {$wpdb->posts}.ID, ']%')
				OR pm.meta_value LIKE CONCAT('%,', {$wpdb->posts}.ID, ',%')
			)
		)";

		if ( 'has_properties' === $mode ) {
			$clauses['where'] .= ' AND ' . $exists;
		} else {
			$clauses['where'] .= ' AND NOT ' . $exists;
		}

		return $clauses;
	}

	/**
	 * Search name / username / email / agency.
	 *
	 * @param string    $search Search SQL.
	 * @param \WP_Query $query  Query.
	 * @return string
	 */
	public function extend_search( $search, $query ) {
		if ( ! is_admin() || ! $query instanceof \WP_Query || ! $query->is_main_query() ) {
			return $search;
		}
		if ( AgentConstants::POST_TYPE !== $query->get( 'post_type' ) ) {
			return $search;
		}
		$term = $query->get( 's' );
		if ( ! is_string( $term ) || '' === trim( $term ) ) {
			return $search;
		}

		global $wpdb;
		$like  = '%' . $wpdb->esc_like( $term ) . '%';
		$extra = $wpdb->prepare(
			" OR EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} em
				WHERE em.post_id = {$wpdb->posts}.ID
				AND em.meta_key IN (%s,%s)
				AND em.meta_value LIKE %s
			)
			OR EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} um
				INNER JOIN {$wpdb->users} u ON u.ID = um.meta_value
				WHERE um.post_id = {$wpdb->posts}.ID
				AND um.meta_key = %s
				AND (u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)
			)
			OR EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE tr.object_id = {$wpdb->posts}.ID
				AND tt.taxonomy = %s
				AND t.name LIKE %s
			)",
			AgentConstants::META_EMAIL,
			AgentConstants::META_PHONE,
			$like,
			AgentConstants::META_LINKED_USER_ID,
			$like,
			$like,
			$like,
			AgentConstants::TAXONOMY_AGENCY,
			$like
		);

		if ( '' === $search ) {
			return $search;
		}

		// Inject before closing paren of WP search group.
		return preg_replace( '/\)\s*$/', $extra . ')', $search, 1 );
	}

	/**
	 * @param array<string, string> $actions Bulk actions.
	 * @return array<string, string>
	 */
	public function bulk_actions( $actions ) {
		// Never allow bulk trash/delete — Delete Wizard only.
		unset( $actions['trash'], $actions['delete'] );

		if ( ! $this->can_manage() ) {
			return $actions;
		}
		$actions['hvnly_mgmt_ensure_caps']   = __( 'Ensure Capabilities', 'havenlytics' );
		$actions['hvnly_mgmt_identity_scan'] = __( 'Run Identity Scan', 'havenlytics' );
		$actions['hvnly_mgmt_export']        = __( 'Export Selected', 'havenlytics' );
		return $actions;
	}

	/**
	 * @param string $redirect Redirect.
	 * @param string $doaction Action.
	 * @param int[]  $post_ids IDs.
	 * @return string
	 */
	public function handle_bulk( $redirect, $doaction, $post_ids ) {
		if ( ! $this->can_manage() ) {
			return $redirect;
		}

		$post_ids = array_map( 'absint', (array) $post_ids );

		if ( 'hvnly_mgmt_ensure_caps' === $doaction ) {
			( new CapabilityRegistrar() )->ensure_roles_and_caps();
			$n = 0;
			foreach ( $post_ids as $agent_id ) {
				$uid = absint( get_post_meta( $agent_id, AgentConstants::META_LINKED_USER_ID, true ) );
				if ( $uid <= 0 ) {
					continue;
				}
				$user = get_userdata( $uid );
				if ( ! $user ) {
					continue;
				}
				if ( ! in_array( PortalCapabilities::WP_ROLE_AGENT, (array) $user->roles, true )
					&& ! user_can( $uid, 'manage_options' ) ) {
					$user->add_role( PortalCapabilities::WP_ROLE_AGENT );
				}
				++$n;
			}
			return add_query_arg(
				array(
					'hvnly_mgmt_notice' => 'caps',
					'hvnly_mgmt_n'      => $n,
				),
				$redirect
			);
		}

		if ( 'hvnly_mgmt_identity_scan' === $doaction ) {
			return add_query_arg(
				array(
					'page' => AgentIdentityHealthAdminPage::PAGE_SLUG,
				),
				admin_url( 'tools.php' )
			);
		}

		if ( 'hvnly_mgmt_export' === $doaction ) {
			set_transient(
				'hvnly_mgmt_export_' . get_current_user_id(),
				$post_ids,
				120
			);
			return add_query_arg(
				array(
					'hvnly_mgmt_export' => '1',
					'_wpnonce'          => wp_create_nonce( 'hvnly_mgmt_export' ),
				),
				$redirect
			);
		}

		return $redirect;
	}

	/**
	 * @return void
	 */
	public function maybe_export(): void {
		if ( empty( $_GET['hvnly_mgmt_export'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! $this->can_manage() ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'hvnly_mgmt_export' ) ) {
			return;
		}
		$screen_ok = isset( $_GET['post_type'] ) && AgentConstants::POST_TYPE === $_GET['post_type']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $screen_ok ) {
			return;
		}

		$ids = get_transient( 'hvnly_mgmt_export_' . get_current_user_id() );
		delete_transient( 'hvnly_mgmt_export_' . get_current_user_id() );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return;
		}

		$this->summaries->prime( array_map( 'absint', $ids ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=havenlytics-agents-' . gmdate( 'Ymd-His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			return;
		}
		fputcsv(
			$out,
			array(
				'Agent ID',
				'Name',
				'Email',
				'Username',
				'Agency',
				'Lifecycle',
				'Properties',
				'Published',
				'Pending',
				'Inquiries',
				'Completeness %',
				'Last Login',
			)
		);

		foreach ( $ids as $id ) {
			$id  = absint( $id );
			$row = $this->summaries->get( $id );
			fputcsv(
				$out,
				array(
					$id,
					get_the_title( $id ),
					(string) get_post_meta( $id, AgentConstants::META_EMAIL, true ),
					(string) ( $row['username'] ?? '' ),
					(string) ( $row['agency_names'] ?? '' ),
					(string) ( $row['lifecycle'] ?? '' ),
					(int) ( $row['properties']['total'] ?? 0 ),
					(int) ( $row['properties']['published'] ?? 0 ),
					(int) ( $row['properties']['pending'] ?? 0 ),
					(int) ( $row['inquiries']['total'] ?? 0 ),
					(int) ( $row['completeness']['percent'] ?? 0 ),
					(string) ( $row['activity']['login'] ?? '' ),
				)
			);
		}
		fclose( $out );
		exit;
	}

	/**
	 * Compact Actions menu: Edit + Delete Wizard stay visible; everything else nests under More.
	 *
	 * @param array<string, string> $actions Actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function quick_actions( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || AgentConstants::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		// Immediate trash/delete is blocked — use Delete Wizard.
		unset( $actions['trash'], $actions['delete'] );

		$agent_id = (int) $post->ID;

		// Admins cannot view another agent's Workspace without Login As.
		// Never send them to the generic Workspace page (blank/empty dashboard).
		$actions['hvnly_view_workspace'] = sprintf(
			'<span class="hvnly-mgmt-soon" title="%s">%s</span>',
			esc_attr__( 'Requires Login As (coming soon)', 'havenlytics' ),
			esc_html__( 'View Workspace (soon)', 'havenlytics' )
		);

		$actions['hvnly_login_as'] = sprintf(
			'<span class="hvnly-mgmt-soon" title="%s">%s</span>',
			esc_attr__( 'Coming soon', 'havenlytics' ),
			esc_html__( 'Login As (soon)', 'havenlytics' )
		);

		$actions['hvnly_view_properties'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'edit.php?post_type=' . AgentConstants::PROPERTY_POST_TYPE . '&hvnly_assigned_agent=' . $agent_id ) ),
			esc_html__( 'View Properties', 'havenlytics' )
		);

		if ( current_user_can( 'manage_options' ) ) {
			$actions['hvnly_view_inquiries'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $this->inquiries_url_for_agent( $agent_id ) ),
				esc_html__( 'View Inquiries', 'havenlytics' )
			);

			$actions['hvnly_delete_wizard'] = sprintf(
				'<a href="%s" class="hvnly-mgmt-delete-wizard">%s</a>',
				esc_url( AgentDeleteWizardPage::url( $agent_id, array( 'step' => 1 ) ) ),
				esc_html__( 'Delete Wizard', 'havenlytics' )
			);
		}

		$visible = array();
		if ( isset( $actions['edit'] ) ) {
			$visible['edit'] = $actions['edit'];
			unset( $actions['edit'] );
		}

		$delete_html = '';
		if ( isset( $actions['hvnly_delete_wizard'] ) ) {
			$delete_html = $actions['hvnly_delete_wizard'];
			unset( $actions['hvnly_delete_wizard'] );
		}

		// Nest remaining actions (lifecycle, view, properties, etc.) in a compact menu.
		if ( ! empty( $actions ) ) {
			$items = '';
			foreach ( $actions as $key => $html ) {
				$items .= sprintf(
					'<li class="hvnly-mgmt-actions__item hvnly-mgmt-actions__item--%s">%s</li>',
					esc_attr( sanitize_html_class( (string) $key ) ),
					$html // Already escaped by WP / earlier sprintf.
				);
			}
			$visible['hvnly_actions_menu'] = sprintf(
				'<details class="hvnly-mgmt-actions"><summary class="hvnly-mgmt-actions__summary">%s</summary><ul class="hvnly-mgmt-actions__list">%s</ul></details>',
				esc_html__( 'More', 'havenlytics' ),
				$items
			);
		}

		if ( '' !== $delete_html ) {
			$visible['hvnly_delete_wizard'] = $delete_html;
		}

		return $visible;
	}

	/**
	 * Canonical Marketing Inquiries URL filtered to one agent.
	 *
	 * @param int $agent_id Agent post ID.
	 * @return string
	 */
	private function inquiries_url_for_agent( int $agent_id ): string {
		return add_query_arg(
			array(
				'page'     => InquiryAdminPage::SUBMENU_SLUG,
				'agent_id' => absint( $agent_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @return bool
	 */
	private function can_manage(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return current_user_can( PortalCapabilities::APPROVE_LISTINGS )
			&& current_user_can( PortalCapabilities::PORTAL_ACCESS );
	}

	/**
	 * @return void
	 */
	public function admin_notices(): void {
		if ( empty( $_GET['hvnly_mgmt_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || AgentConstants::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$n = isset( $_GET['hvnly_mgmt_n'] ) ? absint( $_GET['hvnly_mgmt_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: count */
					__( 'Capabilities ensured for %d linked agent user(s).', 'havenlytics' ),
					$n
				)
			)
		);
	}
}
