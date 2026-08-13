<?php
/**
 * Workspace settings (feature flag + page slug + registration policy).
 *
 * Registration UI lives on Contact Agent settings; values sync into
 * {@see WorkspaceConstants::OPTION_KEY} and are also readable from
 * `hvnly_plugin_settings['contact-agent']`.
 *
 * @package HvnlyNab\Workspace
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace;

use HvnlyNab\Workspace\Auth\PortalCapabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Reads Workspace configuration from options / filters / constants.
 *
 * @since 3.2.0
 */
final class WorkspaceSettings {

	/** @var string Contact Agent settings key for registration mode. */
	public const SETTING_REGISTRATION_MODE = 'hvnly_workspace_registration_mode';

	/** @var string Contact Agent settings key for default WP role slug. */
	public const SETTING_DEFAULT_ROLE = 'hvnly_workspace_default_role';

	/**
	 * Register settings sync hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'hvnly_settings_updated', array( __CLASS__, 'sync_from_plugin_settings' ), 20, 2 );
	}

	/**
	 * When Agent Workspace settings save, mirror into workspace option.
	 *
	 * @param string               $group Group key.
	 * @param array<string, mixed> $data  Group data.
	 * @return void
	 */
	public static function sync_from_plugin_settings( $group, $data = array() ): void {
		if ( 'contact-agent' !== $group || ! is_array( $data ) ) {
			return;
		}

		$stored = get_option( WorkspaceConstants::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		if ( array_key_exists( 'hvnly_workspace_enabled', $data ) ) {
			$stored['enabled'] = ! empty( $data['hvnly_workspace_enabled'] );
		}

		if ( isset( $data['hvnly_workspace_page_slug'] ) && (string) $data['hvnly_workspace_page_slug'] !== '' ) {
			$stored['page_slug'] = sanitize_title( (string) $data['hvnly_workspace_page_slug'] );
		}

		if ( isset( $data['hvnly_workspace_login_redirect'] ) ) {
			$stored['login_redirect'] = sanitize_text_field( (string) $data['hvnly_workspace_login_redirect'] );
		}

		if ( isset( $data['hvnly_workspace_logout_redirect'] ) ) {
			$stored['logout_redirect'] = sanitize_text_field( (string) $data['hvnly_workspace_logout_redirect'] );
		}

		if ( isset( $data[ self::SETTING_REGISTRATION_MODE ] ) ) {
			$stored['registration_mode'] = self::normalize_registration_mode(
				(string) $data[ self::SETTING_REGISTRATION_MODE ]
			);
		}

		if ( isset( $data[ self::SETTING_DEFAULT_ROLE ] ) ) {
			$stored['default_registration_role'] = self::normalize_registration_role(
				(string) $data[ self::SETTING_DEFAULT_ROLE ]
			);
		}

		if ( array_key_exists( 'hvnly_workspace_guest_login', $data ) ) {
			$stored['guest_login_enabled'] = ! empty( $data['hvnly_workspace_guest_login'] );
		}

		if ( array_key_exists( 'hvnly_workspace_perm_publish_property', $data ) ) {
			$stored['agents_can_direct_publish'] = ! empty( $data['hvnly_workspace_perm_publish_property'] );
		}

		$perm_keys = array(
			'create_property'   => 'hvnly_workspace_perm_create_property',
			'edit_own_property' => 'hvnly_workspace_perm_edit_own_property',
			'delete_own_property' => 'hvnly_workspace_perm_delete_own_property',
			'submit_property'   => 'hvnly_workspace_perm_submit_property',
			'publish_property'  => 'hvnly_workspace_perm_publish_property',
			'upload_media'      => 'hvnly_workspace_perm_upload_media',
			'edit_profile'      => 'hvnly_workspace_perm_edit_profile',
			'view_dashboard'    => 'hvnly_workspace_perm_view_dashboard',
			'receive_inquiries' => 'hvnly_workspace_perm_receive_inquiries',
			'reply_inquiries'   => 'hvnly_workspace_perm_reply_inquiries',
		);

		$perms = isset( $stored['permissions'] ) && is_array( $stored['permissions'] ) ? $stored['permissions'] : array();
		foreach ( $perm_keys as $short => $setting_key ) {
			if ( array_key_exists( $setting_key, $data ) ) {
				$perms[ $short ] = ! empty( $data[ $setting_key ] );
			}
		}
		$stored['permissions'] = $perms;

		update_option( WorkspaceConstants::OPTION_KEY, $stored, false );
	}

	/**
	 * Default settings.
	 *
	 * @return array{enabled:bool,page_slug:string,registration_mode:string,default_registration_role:string,agents_can_direct_publish:bool}
	 */
	public static function defaults(): array {
		return array(
			'enabled'                    => false,
			'page_slug'                  => WorkspaceConstants::PAGE_SLUG,
			'registration_mode'          => 'open',
			'default_registration_role'  => PortalCapabilities::WP_ROLE_AGENT,
			'agents_can_direct_publish'  => false,
			// Read-only guest tour. Off by default — exposing a Workspace
			// preview is an administrator's decision, not a default.
			'guest_login_enabled'        => false,
		);
	}

	/**
	 * Merged settings array.
	 *
	 * @return array{enabled:bool,page_slug:string,registration_mode:string,default_registration_role:string,agents_can_direct_publish:bool}
	 */
	public static function get(): array {
		$stored = get_option( WorkspaceConstants::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings                              = array_merge( self::defaults(), $stored );
		$settings['enabled']                   = (bool) $settings['enabled'];
		$settings['agents_can_direct_publish'] = ! empty( $settings['agents_can_direct_publish'] );
		$settings['page_slug']                 = sanitize_title( (string) $settings['page_slug'] );
		$settings['registration_mode']         = self::normalize_registration_mode(
			isset( $settings['registration_mode'] ) ? (string) $settings['registration_mode'] : 'open'
		);
		$settings['default_registration_role'] = self::normalize_registration_role(
			isset( $settings['default_registration_role'] ) ? (string) $settings['default_registration_role'] : PortalCapabilities::WP_ROLE_AGENT
		);

		// Prefer Agent Workspace (contact-agent group) settings when present.
		$from_ui = self::registration_from_plugin_settings();
		if ( null !== $from_ui['mode'] ) {
			$settings['registration_mode'] = $from_ui['mode'];
		}
		if ( null !== $from_ui['role'] ) {
			$settings['default_registration_role'] = $from_ui['role'];
		}
		if ( null !== $from_ui['enabled'] ) {
			$settings['enabled'] = $from_ui['enabled'];
		}
		if ( null !== $from_ui['page_slug'] && $from_ui['page_slug'] !== '' ) {
			$settings['page_slug'] = $from_ui['page_slug'];
		}

		if ( $settings['page_slug'] === '' ) {
			$settings['page_slug'] = WorkspaceConstants::PAGE_SLUG;
		}

		/**
		 * Filter Workspace settings.
		 *
		 * @since 3.2.0
		 *
		 * @param array $settings Settings.
		 */
		$filtered = apply_filters( 'hvnly_workspace_settings', $settings );

		return is_array( $filtered ) ? array_merge( $settings, $filtered ) : $settings;
	}

	/**
	 * Whether the Workspace shell is enabled.
	 *
	 * Feature-flag SSOT: Havenlytics Settings → Enable Workspace
	 * (`hvnly_workspace_enabled` / mirrored workspace option).
	 *
	 * Optional wp-config emergency kill:
	 *   define( 'HVNLY_WORKSPACE_ENABLED', false );
	 * forces the Workspace OFF. A constant set to `true` does NOT override
	 * Settings — administrators must be able to disable the feature from the UI.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		// Admin Settings are the global feature toggle (contact-agent + option).
		$enabled = (bool) self::get()['enabled'];

		// Emergency kill only — never force ON over Settings OFF.
		if ( defined( 'HVNLY_WORKSPACE_ENABLED' ) && ! HVNLY_WORKSPACE_ENABLED ) {
			$enabled = false;
		}

		/**
		 * Filter Workspace feature flag.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $enabled Whether enabled.
		 */
		return (bool) apply_filters( 'hvnly_workspace_enabled', $enabled );
	}

	/**
	 * Whether administrators are bounced from the Workspace to wp-admin.
	 *
	 * **Default changed to false in 3.4.0.** During the initial Workspace
	 * rollout administrators were redirected to wp-admin to reduce maintenance
	 * surface while the SPA was unfinished. The Workspace is now a core
	 * surface, so administrators use it exactly like agents while keeping every
	 * WordPress capability.
	 *
	 * This is the single choke-point for that policy — both the page guard
	 * ({@see WorkspacePage::maybe_redirect_admins_to_admin()}) and the
	 * Workspace login handler
	 * ({@see \HvnlyNab\Workspace\Auth\SessionAuthController::login()}) read it,
	 * so the two can never disagree.
	 *
	 * The long-standing `hvnly_workspace_redirect_admins_to_wpadmin` filter is
	 * retained rather than removed: a site that deliberately wants the old
	 * behaviour can restore it with `__return_true`.
	 *
	 * @since 3.4.0
	 *
	 * @return bool
	 */
	public static function should_redirect_admins_to_wpadmin(): bool {
		/**
		 * Filter whether administrators are redirected from the Workspace to wp-admin.
		 *
		 * @since 3.2.4
		 * @since 3.4.0 Default changed from true to false — administrators now use the Workspace.
		 *
		 * @param bool $redirect Whether to redirect administrators. Default false.
		 */
		return (bool) apply_filters( 'hvnly_workspace_redirect_admins_to_wpadmin', false );
	}

	/**
	 * Whether wp-config forces Workspace offline.
	 *
	 * @return bool
	 */
	public static function is_forced_offline_by_constant(): bool {
		return defined( 'HVNLY_WORKSPACE_ENABLED' ) && ! HVNLY_WORKSPACE_ENABLED;
	}

	/**
	 * Page slug for the Agent Dashboard page.
	 *
	 * @return string
	 */
	public static function get_page_slug(): string {
		return (string) self::get()['page_slug'];
	}

	/**
	 * Absolute base URL of the Workspace page (always trailing-slashed).
	 *
	 * @return string
	 */
	public static function get_base_url(): string {
		$page_id = absint( get_option( WorkspaceConstants::PAGE_ID_OPTION, 0 ) );
		$base    = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/' . self::get_page_slug() . '/' );
		if ( ! is_string( $base ) || $base === '' ) {
			$base = home_url( '/' . self::get_page_slug() . '/' );
		}

		return trailingslashit( $base );
	}

	/**
	 * Path component of the Workspace base URL, e.g. `/agent-dashboard/`.
	 *
	 * This is the SPA router basename and is subdirectory-install aware
	 * (it includes any WordPress install path segment).
	 *
	 * @return string
	 */
	public static function get_base_path(): string {
		$path = wp_parse_url( self::get_base_url(), PHP_URL_PATH );

		return ( is_string( $path ) && $path !== '' ) ? trailingslashit( $path ) : '/';
	}

	/**
	 * Whether clean History-API routing is available.
	 *
	 * Clean `/agent-dashboard/dashboard` URLs require pretty permalinks (a
	 * non-empty permalink_structure) so the catch-all rewrite rule can resolve
	 * sub-paths. On plain permalinks the SPA falls back to hash routing so deep
	 * links never 404.
	 *
	 * @return bool
	 */
	public static function is_clean_routing(): bool {
		$clean = '' !== (string) get_option( 'permalink_structure', '' );

		/**
		 * Filter whether the Workspace SPA uses clean History-API URLs.
		 *
		 * @since 3.2.4
		 *
		 * @param bool $clean Whether clean routing is active.
		 */
		return (bool) apply_filters( 'hvnly_workspace_clean_routing', $clean );
	}

	/**
	 * Build an absolute Workspace deep-link URL, routing-mode aware.
	 *
	 * Accepts a route with or without a leading `#/` or `/` so existing callers
	 * that passed hash fragments keep working. In clean mode the route becomes a
	 * real path segment (`/agent-dashboard/dashboard`); otherwise it stays a hash
	 * (`/agent-dashboard/#/dashboard`). Query args are placed where the SPA reads
	 * them (on the base in hash mode, on the route in clean mode).
	 *
	 * @param string               $route Route path, e.g. 'dashboard', 'property/5/edit', or legacy '#/dashboard'.
	 * @param array<string, mixed> $query Optional query args (e.g. login/key for password reset).
	 * @return string
	 */
	public static function route_url( string $route = '', array $query = array() ): string {
		$route = ltrim( $route, '#/' );
		$base  = self::get_base_url();

		if ( self::is_clean_routing() ) {
			$url = $route !== '' ? $base . $route : $base;
			if ( ! empty( $query ) ) {
				$url = add_query_arg( $query, $url );
			}

			return esc_url_raw( $url );
		}

		$url = $base;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}
		if ( $route !== '' ) {
			$url .= '#/' . $route;
		}

		return esc_url_raw( $url );
	}

	/**
	 * When true, agents who can edit may publish directly (skip pending review).
	 *
	 * Default false — submit for review. Override via filter or constant.
	 *
	 * @return bool
	 */
	public static function agents_can_direct_publish(): bool {
		if ( defined( 'HVNLY_WORKSPACE_AGENTS_CAN_PUBLISH' ) ) {
			$allowed = (bool) HVNLY_WORKSPACE_AGENTS_CAN_PUBLISH;
		} else {
			$plugin = get_option( 'hvnly_plugin_settings', array() );
			if ( is_array( $plugin ) && isset( $plugin['contact-agent']['hvnly_workspace_perm_publish_property'] ) ) {
				$allowed = ! empty( $plugin['contact-agent']['hvnly_workspace_perm_publish_property'] );
			} else {
				$stored  = self::get();
				$allowed = ! empty( $stored['agents_can_direct_publish'] );
			}
		}

		/**
		 * Filter whether Workspace agents may publish without admin approval.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $allowed Whether direct publish is allowed.
		 */
		return (bool) apply_filters( 'hvnly_workspace_agents_can_direct_publish', $allowed );
	}

	/**
	 * Workspace registration policy.
	 *
	 * Modes: disabled | open | approval
	 *
	 * Priority: HVNLY_WORKSPACE_REGISTRATION → Contact Agent / option → filter.
	 * Independent of WordPress “Anyone can register” (Membership).
	 *
	 * @return string
	 */
	public static function get_registration_mode(): string {
		if ( defined( 'HVNLY_WORKSPACE_REGISTRATION' ) ) {
			$mode = self::normalize_registration_mode( (string) HVNLY_WORKSPACE_REGISTRATION );
		} else {
			$mode = self::normalize_registration_mode( (string) self::get()['registration_mode'] );
		}

		/**
		 * Filter Workspace registration mode.
		 *
		 * @since 3.2.0
		 *
		 * @param string $mode disabled|open|approval
		 */
		return self::normalize_registration_mode(
			(string) apply_filters( 'hvnly_workspace_registration_mode', $mode )
		);
	}

	/**
	 * WordPress role assigned on Workspace registration (default: Agent / hvnly_agent).
	 *
	 * @return string
	 */
	public static function get_default_registration_role(): string {
		$role = self::normalize_registration_role( (string) self::get()['default_registration_role'] );

		/**
		 * Filter default WP role for Workspace registration.
		 *
		 * @since 3.2.0
		 *
		 * @param string $role Role slug.
		 */
		return self::normalize_registration_role(
			(string) apply_filters( 'hvnly_workspace_default_registration_role', $role )
		);
	}

	/**
	 * Whether new Workspace accounts may be created.
	 *
	 * @return bool
	 */
	public static function is_registration_enabled(): bool {
		return in_array( self::get_registration_mode(), array( 'open', 'approval' ), true );
	}

	/**
	 * Absolute URL after Workspace logout.
	 *
	 * Empty setting → Workspace login hash on the Workspace page.
	 *
	 * @return string
	 */
	public static function get_logout_redirect_url(): string {
		$stored   = self::get();
		$custom   = isset( $stored['logout_redirect'] ) ? trim( (string) $stored['logout_redirect'] ) : '';
		$fallback = self::route_url( 'login' );

		if ( $custom !== '' ) {
			$url = esc_url_raw( $custom );
			if ( $url !== '' ) {
				$url = wp_validate_redirect( $url, $fallback );
				/**
				 * Filter Workspace logout redirect URL.
				 *
				 * @since 3.2.0
				 *
				 * @param string $url Redirect URL.
				 */
				$url = (string) apply_filters( 'hvnly_workspace_logout_redirect', $url );
				return (string) wp_validate_redirect( $url, $fallback );
			}
		}

		$url = $fallback;

		/**
		 * Filter Workspace logout redirect URL.
		 *
		 * @since 3.2.0
		 *
		 * @param string $url Redirect URL.
		 */
		$url = (string) apply_filters( 'hvnly_workspace_logout_redirect', $url );
		return (string) wp_validate_redirect( $url, $fallback );
	}

	/**
	 * @return array{mode:?string,role:?string,enabled:?bool,page_slug:?string}
	 */
	private static function registration_from_plugin_settings(): array {
		$out = array(
			'mode'      => null,
			'role'      => null,
			'enabled'   => null,
			'page_slug' => null,
		);

		$stored = get_option( 'hvnly_plugin_settings', array() );
		if ( ! is_array( $stored ) || empty( $stored['contact-agent'] ) || ! is_array( $stored['contact-agent'] ) ) {
			return $out;
		}

		$group = $stored['contact-agent'];
		if ( isset( $group[ self::SETTING_REGISTRATION_MODE ] ) && (string) $group[ self::SETTING_REGISTRATION_MODE ] !== '' ) {
			$out['mode'] = self::normalize_registration_mode( (string) $group[ self::SETTING_REGISTRATION_MODE ] );
		}
		if ( isset( $group[ self::SETTING_DEFAULT_ROLE ] ) && (string) $group[ self::SETTING_DEFAULT_ROLE ] !== '' ) {
			$out['role'] = self::normalize_registration_role( (string) $group[ self::SETTING_DEFAULT_ROLE ] );
		}
		if ( array_key_exists( 'hvnly_workspace_enabled', $group ) ) {
			$out['enabled'] = ! empty( $group['hvnly_workspace_enabled'] );
		}
		if ( isset( $group['hvnly_workspace_page_slug'] ) && (string) $group['hvnly_workspace_page_slug'] !== '' ) {
			$out['page_slug'] = sanitize_title( (string) $group['hvnly_workspace_page_slug'] );
		}

		return $out;
	}

	/**
	 * @param string $mode Raw mode.
	 * @return string
	 */
	private static function normalize_registration_mode( string $mode ): string {
		$mode = strtolower( trim( $mode ) );
		$map  = array(
			'disabled' => 'disabled',
			'off'      => 'disabled',
			'closed'   => 'disabled',
			'open'     => 'open',
			'enabled'  => 'open',
			'on'       => 'open',
			'approval' => 'approval',
			'pending'  => 'approval',
			'admin'    => 'approval',
		);
		return isset( $map[ $mode ] ) ? $map[ $mode ] : 'disabled';
	}

	/**
	 * Only allow known Workspace roles for now (Agent). Future: agency_owner etc.
	 *
	 * @param string $role Raw role.
	 * @return string
	 */
	private static function normalize_registration_role( string $role ): string {
		$role    = sanitize_key( $role );
		$allowed = array(
			PortalCapabilities::WP_ROLE_AGENT,
			'agent', // UI alias → hvnly_agent.
		);

		if ( 'agent' === $role || '' === $role ) {
			return PortalCapabilities::WP_ROLE_AGENT;
		}

		if ( in_array( $role, $allowed, true ) ) {
			return $role;
		}

		return PortalCapabilities::WP_ROLE_AGENT;
	}
}
