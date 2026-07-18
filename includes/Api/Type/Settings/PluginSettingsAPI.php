<?php
/**
 * Plugin Settings REST API Endpoint
 *
 * @package HvnlyNab\Api\Type\Settings
 * @since 2.0.0
 */

namespace HvnlyNab\Api\Type\Settings;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginSettingsAPI
 *
 * Handles REST API endpoints for plugin settings management.
 *
 * @package HvnlyNab\Api\Type\Settings
 * @since 2.0.0
 */
class PluginSettingsAPI {

	/**
	 * Storage key for WordPress options.
	 *
	 * @var string
	 */
	private $storage_key = 'hvnly_plugin_settings';

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'hvnlynab/v1';

	/**
	 * REST API route base.
	 *
	 * @var string
	 */
	private $route_base = 'plugin-settings';

	/**
	 * Valid settings groups.
	 *
	 * @var array
	 */
	private $valid_groups = array( 'general', 'design', 'search', 'properties', 'shortcodes', 'property-details', 'map', 'performance', 'contact-agent', 'email', 'permalinks' );

	/**
	 * Boolean fields requiring proper conversion.
	 *
	 * @var array
	 */
	private $boolean_fields = array(
		'hvnly_hideTopSearchTitle',
		'hvnly_hideTopSearchBar',
		'hvnly_hideLeftSearchBar',
		'hvnly_enableAjaxLoadMore',
		'hvnly_EnableBreadcrumbs',
		'hvnly_EnableBackToTop',
		'hvnly_EnablePrintButton',
		'hvnly_EnableSaveProperty',
		'hvnly_EnabledCurrencyFormat',
		'hvnly_EnabledGutenbergEditor',
		// Performance / feature toggles
		'hvnly_cache_enabled',
		'hvnly_contact_agent_notify_agent',
		'hvnly_contact_agent_notify_admin',
		'hvnly_contact_agent_honeypot_enabled',
		'hvnly_email_import_success_enabled',
		'hvnly_email_import_wizard_notify_default',
	);

	/**
	 * Constructor.
	 * Initializes REST API hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function routes() {
		// GET all settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->route_base,
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_all_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// SPECIFIC ROUTES - Must be registered BEFORE parameterized routes.
		register_rest_route(
			$this->namespace,
			'/' . $this->route_base . '/save-all',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_all_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->route_base . '/reset-all',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_all_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
        
        // Route for syncing view type (GRID/LIST)
        register_rest_route(
            $this->namespace,
            '/' . $this->route_base . '/sync-view-type',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'sync_view_type'),
                'permission_callback' => array($this, 'check_permission'),
            )
        );
        
        // Route for syncing AJAX Load More status with Preloader
        register_rest_route(
            $this->namespace,
            '/' . $this->route_base . '/sync-ajax-load-more',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'sync_ajax_load_more'),
                'permission_callback' => array($this, 'check_permission'),
            )
        );
        
		// PARAMETERIZED ROUTES.
		register_rest_route(
			$this->namespace,
			'/' . $this->route_base . '/(?P<group>[a-z-]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings_by_group' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'group' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_group' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->route_base . '/(?P<group>[a-z-]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_settings_group' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'group' => array(
						'required'          => true,
						'validate_callback' => array( $this, 'validate_group' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->route_base . '/(?P<group>[a-z-]+)/reset',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'reset_settings_group' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Validate settings group parameter.
	 *
	 * @param string $param The group parameter to validate.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_group( $param ) {
		if ( in_array( $param, $this->valid_groups, true ) ) {
			return true;
		}

		return new WP_Error(
			'invalid_group',
			sprintf(
				/* translators: 1: Invalid group name, 2: List of valid group names */
				__( 'Invalid settings group "%1$s". Valid groups: %2$s', 'havenlytics' ),
				$param,
				implode( ', ', $this->valid_groups )
			),
			array( 'status' => 400 )
		);
	}

	/**
	 * Check permissions for REST API requests.
	 *
	 * @param WP_REST_Request|null $request The request object.
	 * @return bool True if user has permission, false otherwise.
	 */
	public function check_permission( $request = null ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( $request && in_array( $request->get_method(), array( 'POST', 'PUT', 'DELETE', 'PATCH' ), true ) ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get all plugin settings.
	 *
	 * @return WP_REST_Response Response object containing all settings.
	 */
	public function get_all_settings() {
		if ( function_exists( 'hvnly_maybe_migrate_legacy_settings' ) ) {
			hvnly_maybe_migrate_legacy_settings();
		}

		$settings      = get_option( $this->storage_key, array() );
		$has_defaults  = false;
		$needs_update  = false;

		if ( empty( $settings ) || ! is_array( $settings ) ) {
			if ( class_exists( 'HvnlyNab\Api\Type\Settings\DefaultSettingsData' ) ) {
				$settings     = DefaultSettingsData::get_default_settings();
				$has_defaults = true;
				$needs_update = true;
			} else {
				$settings = array();
			}
		}

		// Ensure search fields exist.
		if ( ! isset( $settings['search'] ) || ! is_array( $settings['search'] ) ) {
			$settings['search'] = array();
			$needs_update       = true;
		}

		// Ensure hvnly_search_fields exists and has defaults if empty.
		if ( empty( $settings['search']['hvnly_search_fields'] ) ) {
			if ( class_exists( 'HvnlyNab\Api\Type\Settings\DefaultSettingsData' ) ) {
				$default_search = DefaultSettingsData::get_default_search_settings();
				if ( isset( $default_search['hvnly_search_fields'] ) ) {
					$settings['search']['hvnly_search_fields'] = $default_search['hvnly_search_fields'];
					$needs_update = true;
				}
			}
		}

		// Save if we made changes.
		if ( $needs_update ) {
			update_option( $this->storage_key, $settings );
		}

		if ( class_exists( 'HvnlyNab\Api\Type\Settings\SettingsSchema' ) ) {
			$settings = SettingsSchema::merge_with_defaults( $settings );
		}

		if ( ! isset( $settings['performance'] ) || ! is_array( $settings['performance'] ) ) {
			$settings['performance'] = array();
		}
		$settings['performance']['hvnly_cache_enabled'] = function_exists( 'hvnly_is_cache_enabled' )
			? \hvnly_is_cache_enabled()
			: (bool) get_option( 'hvnly_cache_enabled', 0 );

		return rest_ensure_response(
			array(
				'success'      => true,
				'data'         => $settings,
				'has_defaults' => $has_defaults,
			)
		);
	}

	/**
	 * Get settings by group
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_settings_by_group($request) {
		$group = $request->get_param('group');
		$settings = get_option($this->storage_key, []);
		
		if (!is_array($settings)) {
			$settings = [];
		}
		
		// Special handling for search group - ensure fields exist
		if ($group === 'search') {
			if (!isset($settings['search']) || !is_array($settings['search'])) {
				$settings['search'] = [];
			}
			
			// Ensure hvnly_search_fields exist (left sidebar filter fields)
			if (empty($settings['search']['hvnly_search_fields'])) {
				if (class_exists('HvnlyNab\Api\Type\Settings\DefaultSettingsData')) {
					$default_search = DefaultSettingsData::get_default_search_settings();
					if (isset($default_search['hvnly_search_fields'])) {
						$settings['search']['hvnly_search_fields'] = $default_search['hvnly_search_fields'];
						update_option($this->storage_key, $settings);
					}
				}
			}
			
			// Ensure hvnly_top_search_fields exist (top search additional fields)
			if (empty($settings['search']['hvnly_top_search_fields'])) {
				if (class_exists('HvnlyNab\Api\Type\Settings\DefaultSettingsData')) {
					$default_search = DefaultSettingsData::get_default_search_settings();
					if (isset($default_search['hvnly_top_search_fields'])) {
						$settings['search']['hvnly_top_search_fields'] = $default_search['hvnly_top_search_fields'];
						update_option($this->storage_key, $settings);
					}
				}
			}
		}
		// Special handling for map group - ensure defaults exist
		if ($group === 'map') {
			if (!isset($settings['map']) || !is_array($settings['map']) || empty($settings['map'])) {
				if (class_exists('HvnlyNab\Api\Type\Settings\DefaultSettingsData')) {
					$settings['map'] = DefaultSettingsData::get_default_map_settings();
					update_option($this->storage_key, $settings);
				}
			}
		}
		if ( 'contact-agent' === $group ) {
			if ( ! isset( $settings['contact-agent'] ) || ! is_array( $settings['contact-agent'] ) || empty( $settings['contact-agent'] ) ) {
				if ( class_exists( 'HvnlyNab\Api\Type\Settings\DefaultSettingsData' ) ) {
					$settings['contact-agent'] = DefaultSettingsData::get_default_contact_agent_settings();
					update_option( $this->storage_key, $settings );
				}
			}
		}
		if ( 'email' === $group ) {
			if ( ! isset( $settings['email'] ) || ! is_array( $settings['email'] ) || empty( $settings['email'] ) ) {
				if ( class_exists( 'HvnlyNab\Api\Type\Settings\DefaultSettingsData' ) ) {
					$settings['email'] = DefaultSettingsData::get_default_email_settings();
					update_option( $this->storage_key, $settings );
				}
			}
		}
		if ('performance' === $group) {
			if (!isset($settings['performance']) || !is_array($settings['performance'])) {
				$settings['performance'] = array();
			}
			$settings['performance']['hvnly_cache_enabled'] = function_exists('hvnly_is_cache_enabled')
				? \hvnly_is_cache_enabled()
				: (bool) get_option('hvnly_cache_enabled', 0);

			return rest_ensure_response([
				'success' => true,
				'data' => $settings['performance'],
			]);
		}
		// If settings exist for this group, return them
		if (isset($settings[$group])) {
			return rest_ensure_response([
				'success' => true,
				'data' => $settings[$group]
			]);
		}
		
		// No settings exist, return defaults
		$default_data = [];
		
		if (class_exists('HvnlyNab\Api\Type\Settings\SettingsSchema')) {
			$default_data = SettingsSchema::get_default_group($group);
		}
		
		if (empty($default_data) && class_exists('HvnlyNab\Api\Type\Settings\DefaultSettingsData')) {
			$method = 'get_default_' . str_replace('-', '_', $group) . '_settings';
			if (method_exists('HvnlyNab\Api\Type\Settings\DefaultSettingsData', $method)) {
				$default_data = DefaultSettingsData::$method();
			}
		}
		
		if (!empty($default_data)) {
			return rest_ensure_response([
				'success' => true,
				'data' => $default_data,
			]);
		}
		
		return new WP_Error('invalid_group', 'Invalid settings group', ['status' => 400]);
	}

	/**
	 * Update settings by group
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings_group($request) {
		$group = $request->get_param('group');
		$params = $request->get_json_params();
		
		if (empty($params)) {
			return new WP_Error('invalid_data', 'Settings data is required', ['status' => 400]);
		}
		
		// Convert boolean values properly
		foreach ($params as $key => $value) {
			if (in_array($key, $this->boolean_fields, true)) {
				if (is_bool($value)) {
					$params[$key] = $value;
				} elseif (is_string($value)) {
					$params[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
				} else {
					$params[$key] = (bool) $value;
				}
			}
		}
		
		$all_settings = get_option($this->storage_key, []);
		if (!is_array($all_settings)) {
			$all_settings = [];
		}
		
		// For search group, merge the fields properly
		if ($group === 'search') {
			if (!isset($all_settings[$group]) || !is_array($all_settings[$group])) {
				$all_settings[$group] = [];
			}
			
			// Handle hvnly_search_fields (left sidebar filter fields)
			if (isset($params['hvnly_search_fields'])) {
				$all_settings[$group]['hvnly_search_fields'] = $params['hvnly_search_fields'];
			}
			
			// Handle hvnly_top_search_fields (top search additional fields)
			if (isset($params['hvnly_top_search_fields'])) {
				$all_settings[$group]['hvnly_top_search_fields'] = $params['hvnly_top_search_fields'];
			}

			// Handle hvnly_main_search_fields (main search bar fields)
			if (isset($params['hvnly_main_search_fields'])) {
				$all_settings[$group]['hvnly_main_search_fields'] = $params['hvnly_main_search_fields'];
			}
			
			// Merge other fields (non-array fields)
			foreach ($params as $key => $value) {
				if (!in_array($key, ['hvnly_search_fields', 'hvnly_top_search_fields', 'hvnly_main_search_fields'], true)) {
					$all_settings[$group][$key] = $value;
				}
			}
		} elseif ($group === 'map') {
			// For map group, initialize if not exists
			if (!isset($all_settings[$group]) || !is_array($all_settings[$group])) {
				$all_settings[$group] = array();
			}
			
			// Update all map settings
			foreach ($params as $key => $value) {
				// Cast zoom to integer
				if ($key === 'hvnly_map_zoom') {
					$all_settings[$group][$key] = (int) $value;
				} else {
					$all_settings[$group][$key] = $value;
				}
			}
		} elseif ( $group === 'contact-agent' ) {
			if ( ! isset( $all_settings[ $group ] ) || ! is_array( $all_settings[ $group ] ) ) {
				$all_settings[ $group ] = array();
			}

			foreach ( $params as $key => $value ) {
				if ( in_array( $key, array( 'hvnly_contact_agent_rate_limit_max', 'hvnly_contact_agent_rate_limit_window' ), true ) ) {
					$all_settings[ $group ][ $key ] = max( 1, (int) $value );
				} elseif ( 'hvnly_contact_agent_admin_email' === $key ) {
					$all_settings[ $group ][ $key ] = sanitize_email( (string) $value );
				} else {
					$all_settings[ $group ][ $key ] = $value;
				}
			}

			if ( class_exists( '\HvnlyNab\ContactAgent\ContactAgentSettings' ) ) {
				\HvnlyNab\ContactAgent\ContactAgentSettings::clear_cache();
			}
		} elseif ( 'email' === $group ) {
			if ( ! isset( $all_settings[ $group ] ) || ! is_array( $all_settings[ $group ] ) ) {
				$all_settings[ $group ] = array();
			}

			foreach ( $params as $key => $value ) {
				if ( 'hvnly_email_from_address' === $key ) {
					$all_settings[ $group ][ $key ] = sanitize_email( (string) $value );
				} elseif ( 'hvnly_email_from_name' === $key || 'hvnly_email_import_success_subject' === $key ) {
					$all_settings[ $group ][ $key ] = sanitize_text_field( (string) $value );
				} elseif ( 'hvnly_email_import_success_intro' === $key ) {
					$all_settings[ $group ][ $key ] = sanitize_textarea_field( (string) $value );
				} elseif ( in_array( $key, array( 'hvnly_email_import_success_enabled', 'hvnly_email_import_wizard_notify_default' ), true ) ) {
					$all_settings[ $group ][ $key ] = rest_sanitize_boolean( $value );
				} else {
					$all_settings[ $group ][ $key ] = $value;
				}
			}

			if ( class_exists( '\HvnlyNab\Email\EmailSettings' ) ) {
				\HvnlyNab\Email\EmailSettings::clear_cache();
			}
		} elseif ( 'permalinks' === $group ) {
			if ( ! class_exists( '\HvnlyNab\Core\PermalinkSettings' ) ) {
				return new \WP_Error(
					'hvnly_permalink_settings_unavailable',
					__( 'Permalink settings are unavailable.', 'havenlytics' ),
					array( 'status' => 500 )
				);
			}

			$saved = \HvnlyNab\Core\PermalinkSettings::save_group( $params );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}

			$all_settings[ $group ] = $saved;
		} elseif ($group === 'performance') {
			if (!isset($all_settings[$group]) || !is_array($all_settings[$group])) {
				$all_settings[$group] = array();
			}

			foreach ($params as $key => $value) {
				$all_settings[$group][$key] = $value;
			}

			if ( array_key_exists( 'hvnly_cache_enabled', $params ) ) {
				if ( function_exists( 'hvnly_sync_cache_enabled_setting' ) ) {
					\hvnly_sync_cache_enabled_setting( ! empty( $params['hvnly_cache_enabled'] ) );
				} else {
					update_option(
						'hvnly_cache_enabled',
						! empty( $params['hvnly_cache_enabled'] ) ? 1 : 0,
						false
					);
				}
			}
		}else {
			// For non-search groups, deep-merge with existing settings.
			if ( isset( $all_settings[ $group ] ) && is_array( $all_settings[ $group ] ) ) {
				if ( function_exists( 'hvnly_deep_merge_settings' ) ) {
					$all_settings[ $group ] = hvnly_deep_merge_settings( $all_settings[ $group ], $params );
				} else {
					$all_settings[ $group ] = array_merge( $all_settings[ $group ], $params );
				}
			} else {
				$all_settings[ $group ] = $params;
			}
		}

		if ( class_exists( 'HvnlyNab\Api\Type\Settings\SettingsSchema' ) && isset( $all_settings[ $group ] ) && is_array( $all_settings[ $group ] ) ) {
			$all_settings[ $group ] = SettingsSchema::validate_group( $group, $all_settings[ $group ] );
		}
		
		update_option($this->storage_key, $all_settings);
		
		do_action('hvnly_settings_updated', $group, $all_settings[$group]);
		
		return rest_ensure_response([
			'success' => true,
			'data' => $all_settings[$group],
			'message' => sprintf(
				/* translators: %s: Settings group name (e.g., General, Design, Search) */
				__('%s settings saved successfully', 'havenlytics'),
				ucfirst($group)
			)
		]);
	}

	/**
	 * Reset settings group to defaults.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function reset_settings_group( $request ) {
		$group = $request->get_param( 'group' );

		if ( ! in_array( $group, $this->valid_groups, true ) ) {
			return new WP_Error( 'invalid_group', 'Invalid settings group', array( 'status' => 400 ) );
		}

		$default_data = array();

		if ( class_exists( 'HvnlyNab\Api\Type\Settings\SettingsSchema' ) ) {
			$default_data = SettingsSchema::get_default_group( $group );
		}

		if ( empty( $default_data ) && class_exists( 'HvnlyNab\Api\Type\Settings\DefaultSettingsData' ) ) {
			$method = 'get_default_' . str_replace( '-', '_', $group ) . '_settings';
			if ( method_exists( 'HvnlyNab\Api\Type\Settings\DefaultSettingsData', $method ) ) {
				$default_data = DefaultSettingsData::$method();
			}
		}

		$all_settings = get_option( $this->storage_key, array() );
		if ( ! is_array( $all_settings ) ) {
			$all_settings = array();
		}

		$all_settings[ $group ] = $default_data;
		update_option( $this->storage_key, $all_settings );

		if ( 'performance' === $group ) {
			if ( function_exists( 'hvnly_sync_cache_enabled_setting' ) ) {
				\hvnly_sync_cache_enabled_setting( ! empty( $default_data['hvnly_cache_enabled'] ) );
			} else {
				update_option(
					'hvnly_cache_enabled',
					! empty( $default_data['hvnly_cache_enabled'] ) ? 1 : 0,
					false
				);
			}
		}

		if ( 'permalinks' === $group && class_exists( '\HvnlyNab\Core\RewriteRulesManager' ) ) {
			\HvnlyNab\Core\RewriteRulesManager::schedule_flush();
		}

		/**
		 * Fires after settings are updated.
		 *
		 * @since 2.0.0
		 *
		 * @param string $group The settings group that was reset.
		 * @param array  $data  The default settings data.
		 */
		do_action( 'hvnly_settings_updated', $group, $default_data );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $default_data,
				'message' => sprintf(
					/* translators: %s: Settings group name */
					__( '%s settings reset to defaults', 'havenlytics' ),
					ucfirst( $group )
				),
			)
		);
	}

	/**
	 * Save all settings at once.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function save_all_settings( $request ) {
		$params = $request->get_json_params();

		if ( empty( $params ) ) {
			return new WP_Error( 'invalid_data', 'Settings data is required', array( 'status' => 400 ) );
		}

		if ( isset( $params['tabs'] ) && is_array( $params['tabs'] ) ) {
			$all_settings = $params['tabs'];
		} else {
			$all_settings = $params;
		}

		// Convert boolean values for all groups.
		foreach ( $all_settings as $group => $settings_data ) {
			if ( is_array( $settings_data ) ) {
				foreach ( $settings_data as $key => $value ) {
					if ( in_array( $key, $this->boolean_fields, true ) ) {
						$all_settings[ $group ][ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
					}
				}
			}
		}

		// Validate each group if schema exists.
		if ( class_exists( 'HvnlyNab\Api\Type\Settings\SettingsSchema' ) ) {
			$validated_settings = array();
			foreach ( $this->valid_groups as $group ) {
				if ( isset( $all_settings[ $group ] ) ) {
					$validated_settings[ $group ] = SettingsSchema::validate_group( $group, $all_settings[ $group ] );
				}
			}
			$all_settings = $validated_settings;
		}

		$existing = get_option( $this->storage_key, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		if ( function_exists( 'hvnly_deep_merge_settings' ) ) {
			$all_settings = hvnly_deep_merge_settings( $existing, $all_settings );
		} else {
			$all_settings = array_replace_recursive( $existing, $all_settings );
		}

		update_option( $this->storage_key, $all_settings );

		if ( isset( $all_settings['performance']['hvnly_cache_enabled'] ) ) {
			if ( function_exists( 'hvnly_sync_cache_enabled_setting' ) ) {
				\hvnly_sync_cache_enabled_setting( ! empty( $all_settings['performance']['hvnly_cache_enabled'] ) );
			} else {
				update_option(
					'hvnly_cache_enabled',
					! empty( $all_settings['performance']['hvnly_cache_enabled'] ) ? 1 : 0,
					false
				);
			}
		}

		/**
		 * Fires after all settings are updated.
		 *
		 * @since 2.0.0
		 *
		 * @param string $group The settings group identifier ('all' for all settings).
		 * @param array  $data  The complete settings data.
		 */
		do_action( 'hvnly_settings_updated', 'all', $all_settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $all_settings,
				'message' => __( 'All settings saved successfully', 'havenlytics' ),
			)
		);
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response Response object containing default settings.
	 */
	public function reset_all_settings( $request ) {
		$default_settings = array();

		if ( class_exists( 'HvnlyNab\Api\Type\Settings\SettingsSchema' ) ) {
			$default_settings = SettingsSchema::get_all_defaults();
		}

		if ( empty( $default_settings ) && class_exists( 'HvnlyNab\Api\Type\Settings\DefaultSettingsData' ) ) {
			$default_settings = DefaultSettingsData::get_default_settings();
		}

		update_option( $this->storage_key, $default_settings );

		if ( isset( $default_settings['performance']['hvnly_cache_enabled'] ) ) {
			if ( function_exists( 'hvnly_sync_cache_enabled_setting' ) ) {
				\hvnly_sync_cache_enabled_setting( ! empty( $default_settings['performance']['hvnly_cache_enabled'] ) );
			} else {
				update_option(
					'hvnly_cache_enabled',
					! empty( $default_settings['performance']['hvnly_cache_enabled'] ) ? 1 : 0,
					false
				);
			}
		}

		/**
		 * Fires after all settings are reset to defaults.
		 *
		 * @since 2.0.0
		 *
		 * @param string $group The settings group identifier ('all' for all settings).
		 * @param array  $data  The default settings data.
		 */
		do_action( 'hvnly_settings_updated', 'all', $default_settings );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $default_settings,
				'message' => __( 'All settings reset to defaults', 'havenlytics' ),
			)
		);
	}

    /**
     * Sync property view type across settings.
     *
     * @since 2.2.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function sync_view_type($request) {
        $params = $request->get_json_params();
        
        if (empty($params) || !isset($params['view_type'])) {
            return new WP_Error(
                'invalid_data',
                __('View type is required', 'havenlytics'),
                array('status' => 400)
            );
        }
        
        $view_type = sanitize_text_field($params['view_type']);
        $allowed_views = array('GRID', 'LIST');
        
        if (!in_array($view_type, $allowed_views, true)) {
            return new WP_Error(
                'invalid_view_type',
                __('Invalid view type. Must be GRID or LIST.', 'havenlytics'),
                array('status' => 400)
            );
        }
        
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $synced = $settings_manager->sync_property_view_type($view_type);
        
        // Get updated settings after sync
        $all_settings = get_option('hvnly_plugin_settings', array());
        
        return rest_ensure_response(array(
            'success' => true,
            'synced' => $synced,
            'data' => array(
                'search' => isset($all_settings['search']) ? $all_settings['search'] : array(),
            ),
            'message' => sprintf(
                /* translators: %s: View type (GRID or LIST) */
                __('Property view synced to %s', 'havenlytics'),
                $view_type
            ),
        ));
    }
    
	/**
	 * Sync AJAX Load More status in search settings.
	 *
	 * @since 2.2.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function sync_ajax_load_more($request) {
		$params = $request->get_json_params();
		
		if (empty($params) || !isset($params['ajax_load_more_enabled'])) {
			return new WP_Error(
				'invalid_data',
				__('AJAX Load More status is required', 'havenlytics'),
				array('status' => 400)
			);
		}
		
		$ajax_load_more_enabled = (bool) $params['ajax_load_more_enabled'];
		
		$settings = get_option('hvnly_plugin_settings', array());
		if (!is_array($settings)) {
			$settings = array();
		}
		
		if (!isset($settings['search']) || !is_array($settings['search'])) {
			$settings['search'] = array();
		}
		
		$settings['search']['hvnly_enableAjaxLoadMore'] = $ajax_load_more_enabled;
		update_option('hvnly_plugin_settings', $settings);
		
		$settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
		$settings_manager->clear_cache();
		
		$all_settings = get_option('hvnly_plugin_settings', array());
		
		return rest_ensure_response(array(
			'success' => true,
			'synced' => true,
			'data' => array(
				'search' => isset($all_settings['search']) ? $all_settings['search'] : array(),
			),
			'message' => $ajax_load_more_enabled
				? __('AJAX Load More enabled', 'havenlytics')
				: __('AJAX Load More disabled', 'havenlytics'),
		));
	}

}