<?php
/**
 * Hook Manager - Centralized hook registration and management
 *
 * Coordinates all WordPress hook registrations for the Havenlytics plugin.
 * Provides a single point of management for actions, filters, AJAX handlers,
 * and scheduled events to ensure consistent hook implementation.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Core;

// Prevent direct access to WordPress files
defined( 'ABSPATH' ) || exit;

/**
 * Import required classes for hook functionality
 */
use HvnlyNab\Core\CacheInvalidator;
use HvnlyNab\Core\RewriteRulesManager;
use HvnlyNab\I18n\TranslationFallback;

/**
 * Hook Manager Class
 *
 * Centralized manager for all WordPress hooks and AJAX handlers in the plugin.
 * Organizes hooks by category and provides consistent security and error handling
 * across all hook implementations.
 *
 * Key responsibilities:
 * - Register all plugin hooks in organized categories
 * - Handle AJAX requests with security validation
 * - Manage scheduled events and cron jobs
 * - Coordinate cache invalidation triggers
 * - Provide plugin action links and activation redirects
 *
 * @since 2.0.0
 */
class HookManager {

	/**
	 * Register all plugin hooks
	 *
	 * Main entry point that coordinates registration of all hook categories.
	 * Called during plugin bootstrap to set up all WordPress integrations.
	 *
	 * @return void
	 */
	public function register_hooks() {
		/**
		 * Register activation and plugin action link hooks
		 */
		$this->register_activation_hooks();

		/**
		 * Deferred rewrite rule flushing for property permalinks.
		 */
		RewriteRulesManager::register();

		/**
		 * Register AJAX endpoint handlers for admin interfaces
		 */
		$this->register_ajax_hooks();

		/**
		 * Register cache-related hooks for data consistency
		 */
		$this->register_cache_hooks();

		/**
		 * Register localization and textdomain hooks
		 */
		$this->register_localization_hooks();

		/**
		 * Register admin-specific hooks and scripts
		 */
		$this->register_admin_hooks();

		/**
		 * Register scheduled events and cron jobs
		 */
		$this->register_cron_hooks();
	}

	/**
	 * Register activation and plugin action link hooks
	 *
	 * Handles plugin activation redirect and adds custom links to the
	 * plugins list page for quick access to settings and tools.
	 *
	 * @return void
	 */
	private function register_activation_hooks() {
		/**
		 * Run after admin menus register (Menu.php + PropertyImportWizard.php) so redirect
		 * targets exist and user_can_access_admin_page() will succeed.
		 */
		add_action( 'admin_menu', array( $this, 'maybe_redirect_after_activation' ), 99 );

		/**
		 * TGMPA activates plugins mid-request during admin_init. Flag on activated_plugin
		 * and run a late admin_menu pass so redirect still fires on the same request.
		 */
		add_action( 'activated_plugin', array( $this, 'on_plugin_activated' ), 10, 2 );

		add_filter( 'plugin_action_links_' . HVNLYNAB_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Whether a one-time post-activation redirect is pending.
	 *
	 * @return bool
	 */
	private function has_activation_redirect_flag() {
		if ( get_transient( 'hvnly_activation_redirect' ) ) {
			return true;
		}

		return (bool) get_option( 'hvnly_activation_redirect', false );
	}

	/**
	 * Persist the one-time activation redirect flag (option + transient).
	 *
	 * @return void
	 */
	private function set_activation_redirect_flag() {
		if ( get_option( 'hvnly_activation_redirect_done' ) ) {
			return;
		}

		update_option( 'hvnly_activation_redirect', 1 );
		set_transient( 'hvnly_activation_redirect', 1, HOUR_IN_SECONDS );
	}

	/**
	 * Clear redirect flags after redirect or when already on a destination page.
	 *
	 * @return void
	 */
	private function clear_activation_redirect_flag() {
		delete_transient( 'hvnly_activation_redirect' );
		delete_option( 'hvnly_activation_redirect' );
		update_option( 'hvnly_activation_redirect_done', true );
	}

	/**
	 * Detect TGMPA plugin activation screen / request parameters.
	 *
	 * @return bool
	 */
	private function is_tgmpa_activation_context() {
		$page = sanitize_key( filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) );
		if ( 'tgmpa-install-plugins' === $page ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- context detection only.
		if ( isset( $_GET['tgmpa-activate'] ) || isset( $_REQUEST['tgmpa-activate'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Set redirect flag when this plugin is activated (covers TGMPA + plugins.php).
	 *
	 * @param string $plugin       Plugin basename.
	 * @param bool   $network_wide Network activation flag.
	 * @return void
	 */
	public function on_plugin_activated( $plugin, $network_wide ) {
		if ( $network_wide || ! is_admin() ) {
			return;
		}

		if ( HVNLYNAB_BASENAME !== $plugin ) {
			return;
		}

		/**
		 * Fresh installs set a one-time onboarding redirect flag from the activation
		 * hook (havenlytics.php) using the authoritative pre-install check. install()
		 * has already written the DB version by the time this runs, so never treat a
		 * legitimately pending fresh-install flag as a stale update flag — just ensure
		 * the redirect can still fire in the same request (covers TGMPA).
		 */
		if ( $this->has_activation_redirect_flag() && ! get_option( 'hvnly_activation_redirect_done' ) ) {
			add_action( 'admin_menu', array( $this, 'maybe_redirect_after_activation' ), 9999 );
			return;
		}

		/**
		 * Plugin updates also fire activated_plugin — never hijack admin navigation on upgrade.
		 */
		if ( get_option( \HvnlyNab\Core\Installer::DB_VERSION_KEY, '' ) ) {
			\HvnlyNab\Core\Installer::clear_stale_activation_redirect_flags();
			return;
		}

		$this->set_activation_redirect_flag();

		/**
		 * TGMPA completes activation during admin_init. Schedule a late pass so redirect
		 * can run in the same request after the flag is set.
		 */
		add_action( 'admin_menu', array( $this, 'maybe_redirect_after_activation' ), 9999 );
	}

	/**
	 * Resolve the one-time post-activation redirect URL from property state.
	 *
	 * Fresh installs (no listings in any meaningful status) are guided into the
	 * React onboarding ("Get Started"); any site that already has at least one
	 * property — published, draft, pending or private — is sent to Settings
	 * instead.
	 *
	 * @return string Admin URL for onboarding (no properties) or Settings dashboard.
	 */
	private function get_activation_redirect_url() {
		$counts = wp_count_posts( 'hvnly_property' );

		$existing_properties = 0;
		foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $status ) {
			$existing_properties += isset( $counts->$status ) ? absint( $counts->$status ) : 0;
		}

		if ( $existing_properties > 0 ) {
			return admin_url( 'edit.php?post_type=hvnly_property&page=hvnly_property_settings' );
		}

		return admin_url( 'edit.php?post_type=hvnly_property&page=hvnly-property-onboarding' );
	}

	/**
	 * OLD REDIRECT METHOD - Kept for reference only
	 *
	 * @deprecated Replaced with conditional redirect based on property count
	 */
	/*
	public function maybe_redirect_after_activation() {
		if ( get_option( 'hvnly_activation_redirect', false ) && current_user_can( 'manage_options' ) ) {
			delete_option( 'hvnly_activation_redirect' );
			update_option( 'hvnly_activation_redirect_done', true );

			if ( isset( $_GET['page'] ) && strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'hvnly_property_' ) === 0 ) {
				return;
			}

			wp_safe_redirect( admin_url( 'edit.php?post_type=hvnly_property&page=hvnly_property_settings' ) );
			exit;
		}
	}
	*/

	/**
	 *  REDIRECT METHOD - Only redirects ONCE after activation, not on every page load
	 *
	 * Handles post-activation redirect with intelligent routing:
	 * - If no properties exist -> Import Wizard for quick setup (ONCE)
	 * - If properties exist -> Settings dashboard (ONCE)
	 *
	 * The redirect ONLY happens when:
	 * 1. The activation flag is set (set during plugin activation)
	 * 2. AND we haven't redirected before (hvnly_activation_redirect_done is false)
	 *
	 * This ensures users can navigate away from the redirect page normally afterward.
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function maybe_redirect_after_activation() {
		static $redirect_attempted = false;

		if ( $redirect_attempted ) {
			return;
		}

		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->has_activation_redirect_flag() ) {
			return;
		}

		if ( get_option( 'hvnly_activation_redirect_done', false ) ) {
			$this->clear_activation_redirect_flag();
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( wp_doing_cron() ) {
			return;
		}

		$page = sanitize_key( filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) );
		if ( ! empty( $page ) ) {
			if ( strpos( $page, 'hvnly_property_' ) === 0 || 'hvnly-property-onboarding' === $page ) {
				$this->clear_activation_redirect_flag();
				return;
			}
		}

		$post_type = sanitize_key( filter_input( INPUT_GET, 'post_type', FILTER_UNSAFE_RAW ) );
		if ( ! empty( $post_type ) && 'hvnly_property' === $post_type ) {
			$page_check = sanitize_key( filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) );

			if ( empty( $page_check ) ) {
				$this->clear_activation_redirect_flag();
				return;
			}
		}

		$redirect_url = $this->get_activation_redirect_url();

		$redirect_attempted = true;
		$this->clear_activation_redirect_flag();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$context     = $this->is_tgmpa_activation_context() ? 'tgmpa' : 'standard';
			$log_message = sprintf(
				'Havenlytics: ONE-TIME activation redirect (%s) - Destination: %s',
				sanitize_text_field( $context ),
				esc_url_raw( $redirect_url )
			);
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			//error_log( $log_message );
		}

		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	/**
	 * Register AJAX endpoint handlers
	 *
	 * Sets up AJAX endpoints for cache management and settings updates.
	 * All endpoints include security validation and permission checks.
	 *
	 * @return void
	 */
	private function register_ajax_hooks() {
		/**
		 * Cache clear/stats/settings AJAX is owned by CacheAdmin (single source of truth).
		 * Handlers below remain for backward compatibility if called directly.
		 */

		if ( class_exists( '\HvnlyNab\ContactAgent\ContactAgentAjaxRegistrar' ) ) {
			\HvnlyNab\ContactAgent\ContactAgentAjaxRegistrar::register();
		}
	}

	/**
	 * Register cache-related hooks
	 *
	 * Sets up cache invalidation triggers when property data changes.
	 * Ensures cached data remains consistent with database content.
	 *
	 * @return void
	 */
	private function register_cache_hooks() {
		/**
		 * Invalidate cache when properties are saved or deleted
		 */
		add_action( 'save_post_hvnly_property', array( CacheInvalidator::class, 'invalidate' ) );
		add_action( 'delete_post', array( CacheInvalidator::class, 'invalidate' ) );
	}

	/**
	 * Register scheduled event hooks
	 *
	 * Sets up daily cache cleanup and other recurring maintenance tasks.
	 * Uses WordPress cron system for scheduled execution.
	 *
	 * @return void
	 */
	private function register_cron_hooks() {
		/**
		 * Schedule daily cache cleanup if not already scheduled
		 */
		if ( ! wp_next_scheduled( 'hvnly_daily_cache_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'hvnly_daily_cache_cleanup' );
		}

		/**
		 * Hook the cache cleanup function to the scheduled event
		 */
		add_action( 'hvnly_daily_cache_cleanup', array( CacheManager::class, 'clear_expired_cache' ) );
	}

	/**
	 * Register localization and internationalization hooks
	 *
	 * Sets up script translations and backward compatibility for custom language files.
	 *
	 * @return void
	 */
	private function register_localization_hooks() {
		/**
		 * Never surface empty msgstr as blank UI — fall back to English source.
		 */
		TranslationFallback::register();

		/**
		 * Register JS translations in admin
		 */
		add_action( 'init', array( $this, 'localization_setup' ) );

		/**
		 * Persist English msgids only; recover if translated labels were saved.
		 */
		add_action( 'init', 'hvnly_maybe_normalize_stored_ui_labels', 20 );
		add_action( 'update_option_WPLANG', 'hvnly_invalidate_locale_caches', 10, 0 );
		add_filter( 'pre_update_option_hvnly_plugin_settings', 'hvnly_pre_update_canonicalize_option', 10, 1 );
		add_filter( 'pre_update_option_hvnly_property_builder.sections', 'hvnly_pre_update_canonicalize_option', 10, 1 );
		add_filter( 'pre_update_option_hvnly_property_card.sections', 'hvnly_pre_update_canonicalize_option', 10, 1 );

		/**
		 * Handle textdomain loading warnings in debug mode
		 */
		add_action( 'doing_it_wrong_trigger_error', array( $this, 'textdomain_notice' ), 10, 3 );
	}

	/**
	 * Register admin-specific hooks
	 *
	 * Sets up hooks that are only needed in the WordPress admin area.
	 * Includes admin scripts, styles, and admin-only functionality.
	 *
	 * @return void
	 */
	private function register_admin_hooks() {
		/**
		 * Only register admin hooks if in admin area
		 */
		if ( ! is_admin() ) {
			return;
		}

		/**
		 * Enqueue admin scripts and styles
		 */
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
	 * Handle cache-related AJAX requests
	 *
	 * Processes AJAX requests for cache management operations.
	 * Includes security validation, permission checks, and proper response handling.
	 *
	 * @return void Sends JSON response and terminates execution
	 */
	public function handle_cache_ajax() {
		/**
		 * Verify nonce for security
		 */
		if ( ! check_ajax_referer( 'hvnly_cache_nonce', 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Security verification failed', 'havenlytics' ) );
		}

		if ( ! function_exists( 'hvnly_is_cache_enabled' ) || ! \hvnly_is_cache_enabled() ) {
			wp_send_json_error( esc_html__( 'Cache system is disabled.', 'havenlytics' ), 403 );
		}

		/**
		 * Check user permissions
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'havenlytics' ) );
		}

		/**
		 * Get and sanitize the action parameter
		 */
		$action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING );
		$action = $action ? sanitize_key( $action ) : '';

		/**
		 * Route to appropriate handler based on action
		 */
		switch ( $action ) {
			case 'hvnly_get_cache_stats':
				/**
				 * Retrieve cache statistics and performance metrics
				 */
				$stats       = CacheManager::get_cache_stats();
				$performance = HVNLY_NAB()->engine()->get_performance_metrics();
				wp_send_json_success(
					array(
						'stats'       => $stats,
						'performance' => $performance,
					)
				);
				break;

			case 'hvnly_clear_cache':
				/**
				 * Clear specific cache types or all cache
				 */
				$cache_type = filter_input( INPUT_POST, 'cache_type', FILTER_SANITIZE_STRING );
				$cache_type = $cache_type ? sanitize_key( $cache_type ) : 'all';
				switch ( $cache_type ) {
					case 'search':
						CacheManager::clear_transients_by_pattern( 'search_' );
						break;
					case 'terms':
						CacheManager::clear_transients_by_pattern( 'terms_' );
						break;
					case 'all':
					default:
						CacheManager::clear_all_cache();
						break;
				}
				wp_send_json_success( esc_html__( 'Cache cleared successfully', 'havenlytics' ) );
				break;

			default:
				wp_send_json_error( esc_html__( 'Unknown action', 'havenlytics' ) );
		}
	}

	/**
	 * Handle cache settings update AJAX requests
	 *
	 * Processes AJAX requests to update cache configuration settings.
	 * Validates input and updates both WordPress options and engine configuration.
	 *
	 * @return void Sends JSON response and terminates execution
	 */
	public function handle_cache_settings_ajax() {
		/**
		 * Verify nonce for security
		 */
		if ( ! check_ajax_referer( 'hvnly_cache_nonce', 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Security verification failed', 'havenlytics' ) );
		}

		if ( ! function_exists( 'hvnly_is_cache_enabled' ) || ! \hvnly_is_cache_enabled() ) {
			wp_send_json_error( esc_html__( 'Cache system is disabled.', 'havenlytics' ), 403 );
		}

		/**
		 * Check user permissions
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'havenlytics' ) );
		}

		/**
		 * Safely get settings from POST data with fallback using filter_input
		 * This is the WordPress.org recommended pattern for handling POST arrays
		 */
		$raw_settings = filter_input( INPUT_POST, 'settings', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY ) ?: array();
		$settings     = $this->sanitize_settings_array( $raw_settings );

		/**
		 * Update each setting in WordPress options
		 */
		foreach ( $settings as $key => $value ) {
			$option_name = 'hvnly_' . sanitize_key( $key );
			update_option( $option_name, $value );
		}

		/**
		 * Prepare configuration for engine update
		 */
		$new_config = array(
			'cache_ttl'          => isset( $settings['cache_ttl'] ) ? absint( $settings['cache_ttl'] ) : HOUR_IN_SECONDS * 6,
			'cache_compression'  => isset( $settings['cache_compression'] ) ? (bool) $settings['cache_compression'] : false,
			'enable_performance' => isset( $settings['cache_debug'] ) ? (bool) $settings['cache_debug'] : false,
		);

		/**
		 * Update engine configuration if engine is available
		 */
		if ( HVNLY_NAB()->engine() ) {
			HVNLY_NAB()->engine()->update_config( $new_config );
		}

		wp_send_json_success( esc_html__( 'Settings updated successfully', 'havenlytics' ) );
	}

	/**
	 * Sanitize settings array recursively
	 *
	 * @param array $settings Raw settings array.
	 * @return array Sanitized settings array.
	 */
	private function sanitize_settings_array( array $settings ): array {
		$sanitized = array();

		foreach ( $settings as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_settings_array( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = absint( $value );
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = (bool) $value;
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Set up plugin localization and translations.
	 *
	 * Loads PHP translations from the plugin languages directory (Poedit / Loco /
	 * manual .mo installs). WordPress.org language packs in WP_LANG_DIR continue
	 * to work via just-in-time loading.
	 *
	 * JavaScript / React script translations are attached at enqueue time for each
	 * real script handle (see Admin\Assets, OnboardingWizard, WorkspaceAssets,
	 * BlockRegistrar). Do not call wp_set_script_translations here for a stale
	 * handle — that prevents React admin UI from receiving locale JSON.
	 *
	 * @return void
	 */
	public function localization_setup() {
		load_plugin_textdomain(
			'havenlytics',
			false,
			dirname( HVNLYNAB_BASENAME ) . '/languages'
		);
	}

	/**
	 * Add custom action links to plugins page
	 *
	 * Provides quick access to plugin settings, documentation, and tools
	 * directly from the WordPress plugins list page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function plugin_action_links( $links ) {
		/**
		 * Add settings link
		 */
		// $settings_link = sprintf(
		//  '<a href="%s">%s</a>',
		//  esc_url( admin_url( 'edit.php?post_type=hvnly_property&page=hvnly_property_settings' ) ),
		//  esc_html__( 'Settings', 'havenlytics' )
		// );
		// array_unshift( $links, $settings_link );

		/**
		 * Add documentation link (opens in new tab)
		 */
		$docs_link = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://havenlytics.com/documentation/' ),
			esc_html__( 'Docs', 'havenlytics' )
		);
		$links[]   = $docs_link;

		/**
		 * Add import wizard link for quick setup (only if no properties exist)
		 * This is just a link, not a redirect - user can click it if they want
		 */
		$property_count  = wp_count_posts( 'hvnly_property' );
		$published_count = isset( $property_count->publish ) ? absint( $property_count->publish ) : 0;

		if ( 0 === $published_count ) {
			$import_link = sprintf(
				'<a href="%s" style="color:#46b450;font-weight:500;">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=hvnly_property&page=hvnly-property-onboarding' ) ),
				esc_html__( 'Get Started', 'havenlytics' )
			);
			$links[]     = $import_link;
		}

		/**
		 * Add cache clearing link for administrators (only when cache is enabled).
		 */
		if ( current_user_can( 'manage_options' ) && function_exists( 'hvnly_is_cache_enabled' ) && \hvnly_is_cache_enabled() ) {
			$cache_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'edit.php?post_type=hvnly_property&page=hvnly_property_cache' ), 'hvnly_clear_cache' ) ),
				esc_html__( 'Clear Cache', 'havenlytics' )
			);
			$links[]    = $cache_link;
		}

		return $links;
	}

	/**
	 * Handle textdomain loading warnings
	 *
	 * Suppresses unnecessary textdomain loading warnings in debug mode
	 * to maintain clean error logs while preserving functionality.
	 *
	 * @param bool   $trigger Whether to trigger the error.
	 * @param string $function The function name that triggered the warning.
	 * @param string $message The warning message.
	 * @return bool Whether to trigger the error.
	 */
	public function textdomain_notice( $trigger, $function, $message ) {
		/**
		 * Only modify behavior in debug mode
		 */
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return $trigger;
		}

		$text_domain = 'havenlytics';

		/**
		 * Suppress warnings about just-in-time textdomain loading for our domain
		 */
		if (
			strpos( $function, '_load_textdomain_just_in_time' ) !== false &&
			strpos( $message, $text_domain ) !== false
		) {
			return false;
		}

		return $trigger;
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * Placeholder method for admin asset enqueuing.
	 * Can be extended to add specific admin scripts and styles.
	 *
	 * @return void
	 */
	public function admin_scripts() {
		// Placeholder for admin script enqueuing
		// Can be extended to add specific admin assets
	}
}
