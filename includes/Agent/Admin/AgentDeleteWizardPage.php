<?php
/**
 * Agent Delete Wizard — multi-step safe removal (Sprint 20B).
 *
 * Never immediate delete. Audit → transfer target → preview → transfer → delete.
 *
 * @package HvnlyNab\Agent\Admin
 * @since   3.2.0
 */

namespace HvnlyNab\Agent\Admin;

use HvnlyNab\Agent\AgentAdminMenu;
use HvnlyNab\Agent\AgentConstants;

defined( 'ABSPATH' ) || exit;

/**
 * Administrator-only Delete Wizard UI + POST handlers.
 *
 * @since 3.2.0
 */
final class AgentDeleteWizardPage {

	public const PAGE_SLUG     = 'hvnly_agent_delete_wizard';
	public const NONCE_ACTION  = 'hvnly_agent_delete_wizard';
	public const CAPABILITY    = 'manage_options';
	public const TRANSIENT_TTL = 3600;

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_hvnly_agent_delete_wizard', array( $this, 'handle_post' ) );
		AgentDeleteExecutor::register_guards();
	}

	/**
	 * @return void
	 */
	public function register_menu(): void {
		add_submenu_page(
			AgentAdminMenu::MENU_SLUG,
			__( 'Delete Agent', 'havenlytics' ),
			__( 'Delete Wizard', 'havenlytics' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$ver = defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '3.2.0';
		wp_enqueue_style(
			'hvnly-agent-delete-wizard',
			HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-agent-delete-wizard.css',
			array(),
			$ver
		);
	}

	/**
	 * Wizard URL helper.
	 *
	 * @param int                  $agent_id Agent CPT.
	 * @param array<string, mixed> $extra    Query args.
	 * @return string
	 */
	public static function url( int $agent_id, array $extra = array() ): string {
		$args = array_merge(
			array(
				'post_type' => AgentConstants::POST_TYPE,
				'page'      => self::PAGE_SLUG,
				'agent_id'  => absint( $agent_id ),
			),
			$extra
		);
		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * @return void
	 */
	public function handle_post(): void {
		$this->assert_admin();
		check_admin_referer( self::NONCE_ACTION );

		$agent_id = isset( $_POST['agent_id'] ) ? absint( $_POST['agent_id'] ) : 0;
		$step     = isset( $_POST['wizard_step'] ) ? sanitize_key( wp_unslash( (string) $_POST['wizard_step'] ) ) : '';
		$action   = isset( $_POST['wizard_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['wizard_action'] ) ) : '';

		if ( $agent_id <= 0 || AgentConstants::POST_TYPE !== get_post_type( $agent_id ) ) {
			$this->redirect_notice( 0, 'error', __( 'Invalid Agent.', 'havenlytics' ), 1 );
		}

		$state = $this->get_state( $agent_id );

		if ( 'choose_target' === $action ) {
			$mode      = isset( $_POST['transfer_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['transfer_mode'] ) ) : '';
			$to_agent  = isset( $_POST['target_agent_id'] ) ? absint( $_POST['target_agent_id'] ) : 0;
			$transfer  = new AgentOwnershipTransferService();
			$target    = $transfer->resolve_target( $mode, $to_agent );
			if ( is_wp_error( $target ) ) {
				$this->redirect_notice( $agent_id, 'error', $target->get_error_message(), 2 );
			}
			if ( 'agent' === $mode && $to_agent === $agent_id ) {
				$this->redirect_notice( $agent_id, 'error', __( 'Cannot transfer an Agent to itself.', 'havenlytics' ), 2 );
			}
			$state['target'] = $target;
			$state['step']   = 3;
			$this->save_state( $agent_id, $state );
			wp_safe_redirect( self::url( $agent_id, array( 'step' => 3 ) ) );
			exit;
		}

		if ( 'run_transfer' === $action ) {
			$target = isset( $state['target'] ) && is_array( $state['target'] ) ? $state['target'] : null;
			if ( ! $target ) {
				$this->redirect_notice( $agent_id, 'error', __( 'Choose a transfer target first.', 'havenlytics' ), 2 );
			}

			$inventory = ( new AgentDeleteInventoryService() )->inventory( $agent_id );
			if ( is_wp_error( $inventory ) ) {
				$this->redirect_notice( $agent_id, 'error', $inventory->get_error_message(), 1 );
			}

			$result = ( new AgentOwnershipTransferService() )->transfer( $agent_id, $target, $inventory );
			if ( is_wp_error( $result ) ) {
				$this->redirect_notice( $agent_id, 'error', $result->get_error_message(), 3 );
			}

			$state['transfer']  = $result;
			$state['inventory'] = $inventory;
			$state['step']      = 5;
			$this->save_state( $agent_id, $state );
			wp_safe_redirect( self::url( $agent_id, array( 'step' => 5 ) ) );
			exit;
		}

		if ( 'run_delete' === $action ) {
			$target   = isset( $state['target'] ) && is_array( $state['target'] ) ? $state['target'] : null;
			$transfer = isset( $state['transfer'] ) && is_array( $state['transfer'] ) ? $state['transfer'] : null;
			if ( ! $target || ! $transfer || empty( $transfer['ok'] ) ) {
				$this->redirect_notice(
					$agent_id,
					'error',
					__( 'Cannot delete: transfer has not completed successfully.', 'havenlytics' ),
					3
				);
			}

			$deleted = ( new AgentDeleteExecutor() )->execute(
				$agent_id,
				$target,
				array(
					'transfer'  => $transfer,
					'inventory' => $state['inventory'] ?? array(),
				)
			);

			$this->clear_state( $agent_id );

			if ( is_wp_error( $deleted ) ) {
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'               => self::PAGE_SLUG,
							'hvnly_delete_notice' => 'error',
							'hvnly_delete_msg'   => rawurlencode( $deleted->get_error_message() ),
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'                => self::PAGE_SLUG,
						'hvnly_delete_notice' => 'success',
						'hvnly_delete_msg'    => rawurlencode(
							__( 'Agent deleted. Ownership was transferred and identity links were removed.', 'havenlytics' )
						),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$this->redirect_notice( $agent_id, 'error', __( 'Unknown wizard action.', 'havenlytics' ), 1 );
	}

	/**
	 * @return void
	 */
	public function render(): void {
		$this->assert_admin();

		$notice = isset( $_GET['hvnly_delete_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['hvnly_delete_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin notice passthrough only.
		$raw_msg = filter_input( INPUT_GET, 'hvnly_delete_msg', FILTER_UNSAFE_RAW );
		if ( is_string( $raw_msg ) && '' !== $raw_msg ) {
			$msg = sanitize_text_field( rawurldecode( wp_unslash( $raw_msg ) ) );
		}

		if ( $notice && $msg ) {
			$class = 'success' === $notice ? 'notice-success' : 'notice-error';
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( $msg )
			);
			if ( 'success' === $notice ) {
				printf(
					'<p><a class="button button-primary" href="%s">%s</a></p>',
					esc_url( admin_url( 'edit.php?post_type=' . AgentConstants::POST_TYPE ) ),
					esc_html__( 'Back to Agents', 'havenlytics' )
				);
				return;
			}
		}

		$agent_id = isset( $_GET['agent_id'] ) ? absint( $_GET['agent_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $agent_id <= 0 ) {
			$this->render_picker();
			return;
		}

		$post = get_post( $agent_id );
		if ( ! $post || AgentConstants::POST_TYPE !== $post->post_type ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Invalid Agent.', 'havenlytics' ) . '</p></div></div>';
			return;
		}

		$state = $this->get_state( $agent_id );
		$step  = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $step <= 0 ) {
			$step = absint( $state['step'] ?? 1 );
		}
		if ( $step < 1 || $step > 5 ) {
			$step = 1;
		}

		// Step 5 requires completed transfer.
		if ( 5 === $step && ( empty( $state['transfer']['ok'] ) || empty( $state['target'] ) ) ) {
			$step = ! empty( $state['target'] ) ? 3 : 1;
		}
		if ( 3 === $step && empty( $state['target'] ) ) {
			$step = 2;
		}

		$inventory = ( new AgentDeleteInventoryService() )->inventory( $agent_id );
		if ( is_wp_error( $inventory ) ) {
			echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html( $inventory->get_error_message() ) . '</p></div></div>';
			return;
		}

		echo '<div class="wrap hvnly-agent-delete-wizard">';
		echo '<h1>' . esc_html__( 'Delete Agent Wizard', 'havenlytics' ) . '</h1>';
		$this->render_steps_nav( $step );
		printf(
			'<p class="hvnly-dw-agent">%s <strong>%s</strong> <span class="description">(ID %d)</span></p>',
			esc_html__( 'Removing:', 'havenlytics' ),
			esc_html( (string) $inventory['agent']['title'] ),
			(int) $agent_id
		);

		if ( 1 === $step ) {
			$this->render_step_audit( $agent_id, $inventory );
		} elseif ( 2 === $step ) {
			$this->render_step_target( $agent_id, $inventory );
		} elseif ( 3 === $step || 4 === $step ) {
			$this->render_step_preview( $agent_id, $inventory, $state );
		} elseif ( 5 === $step ) {
			$this->render_step_delete( $agent_id, $state );
		}

		echo '</div>';
	}

	/**
	 * @return void
	 */
	private function render_picker(): void {
		echo '<div class="wrap hvnly-agent-delete-wizard">';
		echo '<h1>' . esc_html__( 'Delete Agent Wizard', 'havenlytics' ) . '</h1>';
		echo '<p>' . esc_html__( 'Open this wizard from an Agent row action, or choose an Agent below.', 'havenlytics' ) . '</p>';
		$q = new \WP_Query(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 100,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);
		echo '<ul class="ul-disc">';
		foreach ( $q->posts as $p ) {
			printf(
				'<li><a href="%s">%s</a> <span class="description">(ID %d)</span></li>',
				esc_url( self::url( (int) $p->ID, array( 'step' => 1 ) ) ),
				esc_html( get_the_title( $p ) ),
				(int) $p->ID
			);
		}
		echo '</ul></div>';
	}

	/**
	 * @param int $current Current step.
	 * @return void
	 */
	private function render_steps_nav( int $current ): void {
		$labels = array(
			1 => __( '1. Audit', 'havenlytics' ),
			2 => __( '2. Transfer target', 'havenlytics' ),
			3 => __( '3. Preview', 'havenlytics' ),
			4 => __( '4. Transfer', 'havenlytics' ),
			5 => __( '5. Delete', 'havenlytics' ),
		);
		echo '<ol class="hvnly-dw-steps">';
		foreach ( $labels as $n => $label ) {
			$class = $n === $current ? 'is-current' : ( $n < $current ? 'is-done' : '' );
			printf( '<li class="%s">%s</li>', esc_attr( $class ), esc_html( $label ) );
		}
		echo '</ol>';
	}

	/**
	 * @param int                  $agent_id  Agent.
	 * @param array<string, mixed> $inventory Inventory.
	 * @return void
	 */
	private function render_step_audit( int $agent_id, array $inventory ): void {
		$props = $inventory['properties']['counts'] ?? array();
		$user  = $inventory['user'] ?? array();

		echo '<div class="hvnly-dw-panel">';
		echo '<h2>' . esc_html__( 'Ownership audit', 'havenlytics' ) . '</h2>';
		echo '<table class="widefat striped hvnly-dw-table"><tbody>';
		$this->row( __( 'Linked user', 'havenlytics' ), sprintf(
			'%s (%s) — lifecycle: %s',
			(string) ( $user['login'] ?? '—' ),
			(string) ( $user['email'] ?? '—' ),
			(string) ( $user['lifecycle'] ?: '—' )
		) );
		$this->row( __( 'Properties (total)', 'havenlytics' ), (string) ( $props['total'] ?? 0 ) );
		$this->row( __( 'Published', 'havenlytics' ), (string) ( $props['publish'] ?? 0 ) );
		$this->row( __( 'Pending', 'havenlytics' ), (string) ( $props['pending'] ?? 0 ) );
		$this->row( __( 'Draft', 'havenlytics' ), (string) ( $props['draft'] ?? 0 ) );
		$this->row( __( 'Rejected', 'havenlytics' ), (string) ( $props['rejected'] ?? 0 ) );
		$this->row( __( 'Trash', 'havenlytics' ), (string) ( $props['trash'] ?? 0 ) );
		$this->row( __( 'Inquiries', 'havenlytics' ), (string) ( $inventory['inquiries']['total'] ?? 0 ) );
		$this->row( __( 'Notifications', 'havenlytics' ), (string) ( $inventory['notifications']['total'] ?? 0 ) );
		$this->row(
			__( 'Media (attachments by user)', 'havenlytics' ),
			(string) ( $inventory['media']['attachments'] ?? 0 )
		);
		$this->row( __( 'CRM (future)', 'havenlytics' ), (string) ( $inventory['crm_future']['note'] ?? '' ) );
		echo '</tbody></table>';

		$items = $inventory['properties']['items'] ?? array();
		if ( ! empty( $items ) && is_array( $items ) ) {
			echo '<h3>' . esc_html__( 'Property details', 'havenlytics' ) . '</h3>';
			echo '<ul class="ul-disc">';
			foreach ( $items as $bucket => $list ) {
				if ( ! is_array( $list ) || empty( $list ) ) {
					continue;
				}
				foreach ( $list as $item ) {
					printf(
						'<li>#%d %s <em>(%s)</em></li>',
						(int) ( $item['id'] ?? 0 ),
						esc_html( (string) ( $item['title'] ?? '' ) ),
						esc_html( (string) $bucket )
					);
				}
			}
			echo '</ul>';
		}

		printf(
			'<p><a class="button button-primary" href="%s">%s</a> <a class="button" href="%s">%s</a></p>',
			esc_url( self::url( $agent_id, array( 'step' => 2 ) ) ),
			esc_html__( 'Continue — choose transfer target', 'havenlytics' ),
			esc_url( admin_url( 'edit.php?post_type=' . AgentConstants::POST_TYPE ) ),
			esc_html__( 'Cancel', 'havenlytics' )
		);
		echo '</div>';
	}

	/**
	 * @param int                  $agent_id  Agent.
	 * @param array<string, mixed> $inventory Inventory.
	 * @return void
	 */
	private function render_step_target( int $agent_id, array $inventory ): void {
		unset( $inventory );
		$agents = get_posts(
			array(
				'post_type'              => AgentConstants::POST_TYPE,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 200,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'exclude'                => array( $agent_id ),
				'no_found_rows'          => true,
			)
		);

		echo '<div class="hvnly-dw-panel">';
		echo '<h2>' . esc_html__( 'Transfer ownership', 'havenlytics' ) . '</h2>';
		echo '<p>' . esc_html__( 'Data cannot be orphaned. Choose where everything should go.', 'havenlytics' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="hvnly_agent_delete_wizard" />';
		echo '<input type="hidden" name="wizard_action" value="choose_target" />';
		echo '<input type="hidden" name="wizard_step" value="2" />';
		echo '<input type="hidden" name="agent_id" value="' . esc_attr( (string) $agent_id ) . '" />';
		wp_nonce_field( self::NONCE_ACTION );

		echo '<fieldset class="hvnly-dw-fieldset">';
		echo '<label><input type="radio" name="transfer_mode" value="agent" checked="checked" /> ';
		echo esc_html__( 'Transfer to another Agent', 'havenlytics' ) . '</label>';
		echo '<p><select name="target_agent_id" class="regular-text">';
		echo '<option value="0">' . esc_html__( '— Select Agent —', 'havenlytics' ) . '</option>';
		foreach ( $agents as $a ) {
			$hvnly_uid = absint( get_post_meta( $a->ID, AgentConstants::META_LINKED_USER_ID, true ) );
			if ( $hvnly_uid <= 0 ) {
				continue;
			}
			printf(
				'<option value="%d">%s (ID %d · user %s)</option>',
				(int) $a->ID,
				esc_html( get_the_title( $a ) ),
				(int) $a->ID,
				esc_html( (string) $hvnly_uid )
			);
		}
		echo '</select></p>';

		echo '<label><input type="radio" name="transfer_mode" value="administrator" /> ';
		echo esc_html__( 'Transfer to Administrator (you)', 'havenlytics' ) . '</label>';
		echo '</fieldset>';

		printf(
			'<p><button type="submit" class="button button-primary">%s</button> <a class="button" href="%s">%s</a></p>',
			esc_html__( 'Continue to preview', 'havenlytics' ),
			esc_url( self::url( $agent_id, array( 'step' => 1 ) ) ),
			esc_html__( 'Back', 'havenlytics' )
		);
		echo '</form></div>';
	}

	/**
	 * @param int                  $agent_id  Agent.
	 * @param array<string, mixed> $inventory Inventory.
	 * @param array<string, mixed> $state     Wizard state.
	 * @return void
	 */
	private function render_step_preview( int $agent_id, array $inventory, array $state ): void {
		$target = isset( $state['target'] ) && is_array( $state['target'] ) ? $state['target'] : array();
		$props  = $inventory['properties']['counts'] ?? array();

		echo '<div class="hvnly-dw-panel">';
		echo '<h2>' . esc_html__( 'Preview transfer', 'havenlytics' ) . '</h2>';
		echo '<table class="widefat striped hvnly-dw-table"><tbody>';
		$this->row(
			__( 'Target', 'havenlytics' ),
			sprintf(
				'%s — %s (agent #%d · user #%d)',
				(string) ( $target['mode'] ?? '' ),
				(string) ( $target['label'] ?? '' ),
				(int) ( $target['agent_id'] ?? 0 ),
				(int) ( $target['user_id'] ?? 0 )
			)
		);
		$this->row( __( 'Properties to transfer', 'havenlytics' ), (string) ( $props['total'] ?? 0 ) );
		$this->row( __( 'Inquiries', 'havenlytics' ), (string) ( $inventory['inquiries']['total'] ?? 0 ) );
		$this->row( __( 'Notifications', 'havenlytics' ), (string) ( $inventory['notifications']['total'] ?? 0 ) );
		$this->row(
			__( 'Identity to remove', 'havenlytics' ),
			sprintf(
				'Agent CPT #%d · WP User #%d (%s)',
				$agent_id,
				(int) ( $inventory['user']['id'] ?? 0 ),
				(string) ( $inventory['user']['login'] ?? '—' )
			)
		);
		echo '</tbody></table>';

		echo '<p class="description">' . esc_html__(
			'Step 4 will transfer ownership. Step 5 (after success) permanently deletes the Agent CPT and linked user.',
			'havenlytics'
		) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' .
			esc_js( __( 'Transfer ownership now? This cannot be undone from the wizard UI (rollback only on failure).', 'havenlytics' ) ) .
			'\');">';
		echo '<input type="hidden" name="action" value="hvnly_agent_delete_wizard" />';
		echo '<input type="hidden" name="wizard_action" value="run_transfer" />';
		echo '<input type="hidden" name="wizard_step" value="4" />';
		echo '<input type="hidden" name="agent_id" value="' . esc_attr( (string) $agent_id ) . '" />';
		wp_nonce_field( self::NONCE_ACTION );

		printf(
			'<p><button type="submit" class="button button-primary">%s</button> <a class="button" href="%s">%s</a></p>',
			esc_html__( 'Step 4 — Execute transfer', 'havenlytics' ),
			esc_url( self::url( $agent_id, array( 'step' => 2 ) ) ),
			esc_html__( 'Back', 'havenlytics' )
		);
		echo '</form></div>';
	}

	/**
	 * @param int                  $agent_id Agent.
	 * @param array<string, mixed> $state    State.
	 * @return void
	 */
	private function render_step_delete( int $agent_id, array $state ): void {
		$transfer = $state['transfer'] ?? array();
		$target   = $state['target'] ?? array();

		echo '<div class="hvnly-dw-panel">';
		echo '<h2>' . esc_html__( 'Transfer complete — confirm delete', 'havenlytics' ) . '</h2>';
		echo '<div class="notice notice-success inline"><p>' . esc_html__(
			'Ownership transfer succeeded. You may now permanently delete this Agent.',
			'havenlytics'
		) . '</p></div>';

		echo '<table class="widefat striped hvnly-dw-table"><tbody>';
		$this->row( __( 'Properties moved', 'havenlytics' ), (string) ( $transfer['properties']['moved'] ?? 0 ) );
		$this->row( __( 'Inquiries moved', 'havenlytics' ), (string) ( $transfer['inquiries'] ?? 0 ) );
		$this->row( __( 'Notifications moved', 'havenlytics' ), (string) ( $transfer['notifications'] ?? 0 ) );
		$this->row( __( 'Held by', 'havenlytics' ), (string) ( $target['label'] ?? '' ) );
		echo '</tbody></table>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' .
			esc_js( __( 'Permanently delete this Agent CPT and linked WordPress user? This cannot be undone.', 'havenlytics' ) ) .
			'\');">';
		echo '<input type="hidden" name="action" value="hvnly_agent_delete_wizard" />';
		echo '<input type="hidden" name="wizard_action" value="run_delete" />';
		echo '<input type="hidden" name="wizard_step" value="5" />';
		echo '<input type="hidden" name="agent_id" value="' . esc_attr( (string) $agent_id ) . '" />';
		wp_nonce_field( self::NONCE_ACTION );

		printf(
			'<p><button type="submit" class="button button-primary button-hero hvnly-dw-danger">%s</button></p>',
			esc_html__( 'Step 5 — Delete Agent & User', 'havenlytics' )
		);
		echo '</form></div>';
	}

	/**
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private function row( string $label, string $value ): void {
		printf(
			'<tr><th scope="row">%s</th><td>%s</td></tr>',
			esc_html( $label ),
			esc_html( $value )
		);
	}

	/**
	 * @param int $agent_id Agent.
	 * @return array<string, mixed>
	 */
	private function get_state( int $agent_id ): array {
		$key  = $this->state_key( $agent_id );
		$data = get_transient( $key );
		return is_array( $data ) ? $data : array( 'step' => 1 );
	}

	/**
	 * @param int                  $agent_id Agent.
	 * @param array<string, mixed> $state    State.
	 * @return void
	 */
	private function save_state( int $agent_id, array $state ): void {
		set_transient( $this->state_key( $agent_id ), $state, self::TRANSIENT_TTL );
	}

	/**
	 * @param int $agent_id Agent.
	 * @return void
	 */
	private function clear_state( int $agent_id ): void {
		delete_transient( $this->state_key( $agent_id ) );
	}

	/**
	 * @param int $agent_id Agent.
	 * @return string
	 */
	private function state_key( int $agent_id ): string {
		return 'hvnly_dw_' . get_current_user_id() . '_' . absint( $agent_id );
	}

	/**
	 * @return void
	 */
	private function assert_admin(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Administrator capability required.', 'havenlytics' ), 403 );
		}
	}

	/**
	 * @param int    $agent_id Agent.
	 * @param string $type     Notice type.
	 * @param string $message  Message.
	 * @param int    $step     Step.
	 * @return void
	 */
	private function redirect_notice( int $agent_id, string $type, string $message, int $step ): void {
		$args = array(
			'page'                => self::PAGE_SLUG,
			'hvnly_delete_notice' => $type,
			'hvnly_delete_msg'    => rawurlencode( $message ),
		);
		if ( $agent_id > 0 ) {
			$args['agent_id'] = $agent_id;
			$args['step']     = $step;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
