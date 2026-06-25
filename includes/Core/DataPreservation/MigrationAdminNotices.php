<?php
/**
 * Admin notices and retry UI for failed migrations.
 *
 * @package HvnlyNab\Core\DataPreservation
 * @since   3.0.2
 */

namespace HvnlyNab\Core\DataPreservation;

defined( 'ABSPATH' ) || exit;

class MigrationAdminNotices {

	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'maybe_recover_builder_config' ), 5 );
		add_action( 'admin_notices', array( __CLASS__, 'render_failure_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_builder_recovery_notice' ) );
		add_action( 'admin_post_hvnly_retry_migrations', array( __CLASS__, 'handle_retry' ) );
		add_action( 'admin_post_hvnly_restore_backup', array( __CLASS__, 'handle_restore_backup' ) );
	}

	/**
	 * Auto-restore property builder from snapshot when config was wiped but properties remain.
	 *
	 * @return void
	 */
	public static function maybe_recover_builder_config(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! self::builder_config_needs_recovery() ) {
			return;
		}

		if ( get_option( 'hvnly_builder_auto_recovery_attempted', false ) ) {
			return;
		}

		update_option( 'hvnly_builder_auto_recovery_attempted', 1, false );

		if ( BackupManager::restore_latest() && ! self::builder_config_needs_recovery() ) {
			update_option( 'hvnly_builder_auto_recovery_success', 1, false );
		}
	}

	/**
	 * @return bool
	 */
	private static function builder_config_needs_recovery(): bool {
		$sections = get_option( 'hvnly_property_builder.sections', array() );
		if ( ! is_array( $sections ) || empty( $sections ) ) {
			$needs_recovery = true;
		} else {
			$needs_recovery = true;
			foreach ( $sections as $section ) {
				if ( is_array( $section ) && ! empty( $section['fields'] ) ) {
					$needs_recovery = false;
					break;
				}
			}
		}

		if ( ! $needs_recovery ) {
			return false;
		}

		$counts = wp_count_posts( 'hvnly_property' );

		return isset( $counts->publish ) && (int) $counts->publish > 0;
	}

	/**
	 * @return void
	 */
	public static function render_builder_recovery_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( get_option( 'hvnly_builder_auto_recovery_success', false ) ) {
			delete_option( 'hvnly_builder_auto_recovery_success' );
			echo '<div class="notice notice-success is-dismissible"><p>';
			esc_html_e( 'Havenlytics restored your Property Builder configuration from a pre-update backup.', 'havenlytics' );
			echo '</p></div>';
			return;
		}

		if ( ! self::builder_config_needs_recovery() ) {
			return;
		}

		$snapshot = get_option( 'hvnly_latest_snapshot_id', '' );
		if ( ! is_string( $snapshot ) || $snapshot === '' ) {
			return;
		}

		$restore = wp_nonce_url(
			admin_url( 'admin-post.php?action=hvnly_restore_backup' ),
			'hvnly_restore_backup'
		);

		echo '<div class="notice notice-warning"><p><strong>';
		esc_html_e( 'Havenlytics: Property Builder configuration appears incomplete.', 'havenlytics' );
		echo '</strong> ';
		esc_html_e( 'Your property data is still in the database. Restore the pre-update backup to recover builder sections and fields.', 'havenlytics' );
		echo ' ';
		printf(
			'<a href="%1$s" class="button button-primary" onclick="return confirm(\'%2$s\');">%3$s</a>',
			esc_url( $restore ),
			esc_js( __( 'Restore Property Builder from backup?', 'havenlytics' ) ),
			esc_html__( 'Restore backup', 'havenlytics' )
		);
		echo '</p></div>';
	}

	public static function render_failure_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! get_option( 'hvnly_migration_failed', false ) ) {
			return;
		}

		$error = get_option( 'hvnly_migration_error', '' );
		$step  = get_option( 'hvnly_migration_last_step', '' );
		$retry = wp_nonce_url(
			admin_url( 'admin-post.php?action=hvnly_retry_migrations' ),
			'hvnly_retry_migrations'
		);
		$restore = wp_nonce_url(
			admin_url( 'admin-post.php?action=hvnly_restore_backup' ),
			'hvnly_restore_backup'
		);
		$snapshot = get_option( 'hvnly_latest_snapshot_id', '' );

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'Havenlytics: Database migration did not complete.', 'havenlytics' );
		echo '</strong> ';
		if ( $step ) {
			printf(
				/* translators: %s: migration version step */
				esc_html__( 'Last step: %s.', 'havenlytics' ),
				esc_html( $step )
			);
			echo ' ';
		}
		if ( $error ) {
			echo esc_html( $error );
			echo ' ';
		}
		printf(
			'<a href="%1$s" class="button button-primary">%2$s</a>',
			esc_url( $retry ),
			esc_html__( 'Retry migrations', 'havenlytics' )
		);
		if ( is_string( $snapshot ) && $snapshot !== '' ) {
			echo ' ';
			printf(
				'<a href="%1$s" class="button" onclick="return confirm(\'%2$s\');">%3$s</a>',
				esc_url( $restore ),
				esc_js( __( 'Restore builder and settings from the pre-migration backup?', 'havenlytics' ) ),
				esc_html__( 'Restore backup', 'havenlytics' )
			);
		}
		echo '</p></div>';
	}

	public static function handle_restore_backup(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'havenlytics' ) );
		}
		check_admin_referer( 'hvnly_restore_backup' );

		$restored = BackupManager::restore_latest();

		if ( $restored ) {
			add_settings_error( 'hvnly_backup', 'restored', __( 'Havenlytics backup restored successfully.', 'havenlytics' ), 'updated' );
		} else {
			add_settings_error( 'hvnly_backup', 'failed', __( 'No backup snapshot found or restore failed.', 'havenlytics' ), 'error' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'plugins.php' ) );
		exit;
	}

	public static function handle_retry(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'havenlytics' ) );
		}
		check_admin_referer( 'hvnly_retry_migrations' );

		delete_option( 'hvnly_migration_failed' );
		delete_option( 'hvnly_migration_error' );

		if ( class_exists( '\HvnlyNab\Core\Migration\Migrator' ) ) {
			$success = \HvnlyNab\Core\Migration\Migrator::run( true );
			if ( $success && class_exists( '\HvnlyNab\Core\Installer' ) ) {
				$db = get_option( \HvnlyNab\Core\Installer::DB_VERSION_KEY, '0.0.0' );
				if ( version_compare( $db, HVNLYNAB_VERSION, '<' ) ) {
					\HvnlyNab\Core\Installer::update( $db, HVNLYNAB_VERSION );
				}
			}
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'plugins.php' ) );
		exit;
	}
}
