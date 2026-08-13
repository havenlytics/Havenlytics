<?php
/**
 * Workspace asset registration and conditional enqueue.
 *
 * @package HvnlyNab\Workspace
 * @since   3.2.0
 */

namespace HvnlyNab\Workspace;

use HvnlyNab\I18n\ScriptTranslations;

defined( 'ABSPATH' ) || exit;

/**
 * Scalable Workspace asset loader.
 *
 * @since 3.2.0
 */
final class WorkspaceAssets {

	/**
	 * Whether scripts/styles have been registered this request.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Whether assets have been enqueued this request.
	 *
	 * @var bool
	 */
	private static $enqueued = false;

	/**
	 * @var WorkspacePage|null
	 */
	private $page;

	/**
	 * @param WorkspacePage|null $page Page helper for request detection.
	 */
	public function __construct( ?WorkspacePage $page = null ) {
		$this->page = $page;
	}

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ), 20 );
		add_action( 'hvnly_workspace_enqueue_assets', array( $this, 'enqueue' ) );
		add_filter( 'hvnly_workspace_should_enqueue', array( $this, 'filter_should_enqueue' ), 5 );
		add_action( 'wp_head', array( $this, 'print_branding_favicon' ), 4 );
	}

	/**
	 * First-paint Workspace favicon before SPA boot.
	 *
	 * @return void
	 */
	public function print_branding_favicon(): void {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		$branding = WorkspaceBranding::get_public( is_user_logged_in() ? get_current_user_id() : 0 );
		$url      = isset( $branding['faviconUrl'] ) ? (string) $branding['faviconUrl'] : '';
		if ( '' === $url ) {
			return;
		}

		$type = false !== stripos( $url, '.ico' ) ? 'image/x-icon' : 'image/png';
		printf(
			'<link rel="icon" id="hvnly-ws-favicon" type="%1$s" href="%2$s" />' . "\n",
			esc_attr( $type ),
			esc_url( $url )
		);
	}

	/**
	 * Conditionally enqueue on Workspace surfaces.
	 *
	 * @return void
	 */
	public function maybe_enqueue(): void {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		$this->enqueue();
	}

	/**
	 * Default enqueue gate: workspace page/shortcode (including disabled → unavailable UI).
	 *
	 * Assets still load when the Administrator has disabled Workspace so the
	 * branded unavailable page (PHP + SPA gate) can render. Never leave the
	 * Agent Dashboard URL as a blank document.
	 *
	 * @param bool $should Incoming value.
	 * @return bool
	 */
	public function filter_should_enqueue( bool $should ): bool {
		if ( $should ) {
			return true;
		}

		if ( WorkspaceShortcode::has_rendered() ) {
			return true;
		}

		if ( $this->page && $this->page->is_workspace_page() ) {
			return true;
		}

		if ( $this->content_has_shortcode() ) {
			return true;
		}

		return false;
	}

	/**
	 * Whether Workspace assets should load on this request.
	 *
	 * @return bool
	 */
	public function should_enqueue(): bool {
		/**
		 * Filter whether Workspace CSS/JS should enqueue.
		 *
		 * @since 3.2.0
		 *
		 * @param bool $should Whether to enqueue.
		 */
		return (bool) apply_filters( 'hvnly_workspace_should_enqueue', false );
	}

	/**
	 * Detect shortcode in the main queried post (before render).
	 *
	 * @return bool
	 */
	private function content_has_shortcode(): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_shortcode( (string) $post->post_content, WorkspaceConstants::SHORTCODE );
	}

	/**
	 * Register script and style handles (idempotent).
	 *
	 * @return bool True when handles were registered (build present).
	 */
	public function register(): bool {
		if ( self::$registered ) {
			return $this->is_build_available();
		}

		self::$registered = true;

		if ( ! $this->is_build_available() ) {
			return false;
		}

		$asset = $this->get_asset_data();
		if ( empty( $asset ) ) {
			return false;
		}

		$script_rel = isset( $asset['script'] ) ? (string) $asset['script'] : '';
		$style_rel  = isset( $asset['style'] ) ? (string) $asset['style'] : '';
		$deps       = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? $asset['dependencies']
			: array();
		$version    = isset( $asset['version'] ) ? (string) $asset['version'] : WorkspaceConstants::MODULE_VERSION;

		$build_url = trailingslashit( HVNLYNAB_BUILD_URL . '/' . WorkspaceConstants::BUILD_FOLDER );

		/*
		 * Shared toast manager. The SPA has no toast implementation of its
		 * own any more — WorkspaceNotificationService forwards to this single
		 * engine — so it is a hard dependency, not an enhancement.
		 */
		if ( class_exists( '\HvnlyNab\Favorites\FavoritesAssets' ) ) {
			\HvnlyNab\Favorites\FavoritesAssets::register_toast_assets();
			$deps[] = \HvnlyNab\Favorites\FavoritesAssets::TOAST_SCRIPT_HANDLE;
		}

		if ( $style_rel !== '' ) {
			/*
			 * Core design tokens must load before Workspace CSS.
			 * DynamicStyleGenerator injects Brand Primary onto this handle;
			 * Workspace must never redefine --hvnly-brand-* itself.
			 */
			$this->ensure_core_design_tokens_registered();

			wp_register_style(
				WorkspaceConstants::STYLE_HANDLE,
				$build_url . $style_rel,
				array( 'hvnly-frontend-default' ),
				$version
			);
			wp_style_add_data( WorkspaceConstants::STYLE_HANDLE, 'rtl', 'replace' );
		}

		if ( $script_rel !== '' ) {
			wp_register_script(
				WorkspaceConstants::SCRIPT_HANDLE,
				$build_url . $script_rel,
				$deps,
				$version,
				true
			);

			ScriptTranslations::attach( WorkspaceConstants::SCRIPT_HANDLE );
		}

		/**
		 * Fires after Workspace asset handles are registered.
		 *
		 * @since 3.2.0
		 *
		 * @param array $asset Asset.php payload.
		 */
		do_action( 'hvnly_workspace_assets_registered', $asset );

		return true;
	}

	/**
	 * Ensure Core frontend design-token stylesheet is registered.
	 *
	 * Workspace CSS depends on `hvnly-frontend-default` so Brand Primary from
	 * Settings (DynamicStyleGenerator inline on that handle) cascades into the SPA.
	 * Usually Frontend\Assets already registered it; this is a hard guarantee.
	 *
	 * @return void
	 */
	private function ensure_core_design_tokens_registered(): void {
		if ( wp_style_is( 'hvnly-frontend-default', 'registered' ) || wp_style_is( 'hvnly-frontend-default', 'enqueued' ) ) {
			return;
		}

		if ( ! defined( 'HVNLYNAB_ASSETS_URL' ) || ! defined( 'HVNLYNAB_VERSION' ) ) {
			return;
		}

		wp_register_style(
			'hvnly-frontend-default',
			HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-default.css',
			array(),
			HVNLYNAB_VERSION
		);
	}

	/**
	 * Enqueue registered Workspace assets and localize boot config.
	 *
	 * When Workspace is disabled ({@see WorkspaceAvailability}), only CSS is
	 * loaded for the server-rendered unavailable page. The SPA script is never
	 * enqueued — that is the hard guarantee that no React route can run.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( self::$enqueued ) {
			return;
		}

		if ( ! $this->register() ) {
			return;
		}

		self::$enqueued = true;

		if ( wp_style_is( WorkspaceConstants::STYLE_HANDLE, 'registered' ) ) {
			wp_enqueue_style( WorkspaceConstants::STYLE_HANDLE );
		}

		// Toast styles + button paint from Core components.
		if ( class_exists( '\HvnlyNab\Favorites\FavoritesAssets' ) ) {
			\HvnlyNab\Favorites\FavoritesAssets::enqueue_toast_assets();
		}

		// Hard kill switch: no SPA / media / password meter while offline.
		if ( ! WorkspaceAvailability::is_available() ) {
			/**
			 * Fires after Workspace unavailable (CSS-only) assets are enqueued.
			 *
			 * @since 3.3.0
			 */
			do_action( 'hvnly_workspace_unavailable_assets_enqueued' );
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'password-strength-meter' );

		if ( wp_script_is( WorkspaceConstants::SCRIPT_HANDLE, 'registered' ) ) {
			wp_enqueue_script( WorkspaceConstants::SCRIPT_HANDLE );
			wp_localize_script(
				WorkspaceConstants::SCRIPT_HANDLE,
				WorkspaceConstants::LOCALIZE_OBJECT,
				$this->get_localize_data()
			);
		}

		/**
		 * Fires after Workspace assets are enqueued.
		 *
		 * @since 3.2.0
		 */
		do_action( 'hvnly_workspace_assets_enqueued' );
	}

	/**
	 * Boot config for the SPA (no secrets / no REST calls required for shell).
	 *
	 * @return array<string, mixed>
	 */
	public function get_localize_data(): array {
		$data = array(
			'version'       => WorkspaceConstants::MODULE_VERSION,
			'pluginVersion' => defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '',
			'mountId'       => WorkspaceConstants::MOUNT_ID,
			// String '1'/'0' — wp_localize_script historically stringifies bools
			// (false → ""), which broke the SPA kill switch. Always send explicit digits.
			'enabled'       => WorkspaceAvailability::is_available() ? '1' : '0',
			'devMode'       => ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG )
				|| ( defined( 'HVNLY_WORKSPACE_DEV' ) && HVNLY_WORKSPACE_DEV ),
			'debug'         => ( defined( 'HVNLY_WORKSPACE_DEBUG' ) && HVNLY_WORKSPACE_DEBUG )
				|| ( class_exists( '\HvnlyNab\Workspace\Security\WorkspaceSecurity' )
					&& \HvnlyNab\Workspace\Security\WorkspaceSecurity::is_debug() ),
			'assets'        => array(
				'iconUrl' => defined( 'HVNLYNAB_ASSETS_URL' )
					? esc_url_raw( trailingslashit( HVNLYNAB_ASSETS_URL ) . 'admin/img/havenlytics-icon.svg' )
					: '',
				// Shared default avatar illustration — the single placeholder every
				// avatar surface falls back to (never a broken/empty image).
				'avatarPlaceholder' => esc_url_raw( \HvnlyNab\Common\AvatarService::placeholder_url() ),
			),
			'urls'          => array(
				'website' => 'https://havenlytics.com/',
				'docs'    => 'https://havenlytics.com/documentation/',
				'support' => 'https://havenlytics.com/support/',
				'home'    => esc_url_raw( home_url( '/' ) ),
			),
			'rest'          => array(
				'namespace' => WorkspaceConstants::REST_NAMESPACE,
				'root'      => esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'me'        => esc_url_raw( rest_url( WorkspaceConstants::REST_NAMESPACE . '/me' ) ),
			),
			// SPA router basename + mode. `clean` = History-API paths
			// (/agent-dashboard/dashboard); falls back to hash routing on plain
			// permalinks so deep links never 404. See WorkspacePage rewrite rule.
			'routing'       => array(
				'basePath' => WorkspaceSettings::get_base_path(),
				'clean'    => WorkspaceSettings::is_clean_routing(),
			),
			'i18n'          => array(
				'locale'           => get_locale(),
				'brandName'        => __( 'Havenlytics', 'havenlytics' ),
				'workspaceLabel'   => __( 'Workspace', 'havenlytics' ),
				'accountLabel'     => __( 'Account', 'havenlytics' ),
				'dashboardLabel'   => __( 'Dashboard', 'havenlytics' ),
				'propertiesLabel'  => __( 'Properties', 'havenlytics' ),
				'savedPropertiesLabel' => __( 'Saved Properties', 'havenlytics' ),
				'profileLabel'     => __( 'Profile', 'havenlytics' ),
				'analyticsLabel'   => __( 'Analytics', 'havenlytics' ),
				'notificationsLabel' => __( 'Notifications', 'havenlytics' ),
				'inquiriesLabel'   => __( 'Inquiries', 'havenlytics' ),
				'settingsLabel'    => __( 'Settings', 'havenlytics' ),
				'uiComponentsLabel' => __( 'UI Components', 'havenlytics' ),
				'developerLabel'   => __( 'Developer', 'havenlytics' ),
				'comingSoon'       => __( 'Coming Soon', 'havenlytics' ),
				'comingInPro'      => __( 'Coming in Pro', 'havenlytics' ),
				'proLicenseRequired' => __( 'Requires an active Pro license', 'havenlytics' ),
				'brandingProHint'  => __( 'Customize Workspace branding for your brokerage. Changes apply live; click Save settings to persist.', 'havenlytics' ),
				'brandingLockedHint' => __( 'Customize Workspace branding for your brokerage. These controls unlock with Havenlytics Pro.', 'havenlytics' ),
				'brandingReadOnlyHint' => __( 'You can view Workspace branding, but you do not have permission to change it.', 'havenlytics' ),
				'readyTitle'       => __( 'Workspace Ready', 'havenlytics' ),
				'readySubtitle'    => __( 'Shell foundation is mounted. Feature modules arrive in later sprints.', 'havenlytics' ),
				'toggleSidebar'    => __( 'Toggle sidebar', 'havenlytics' ),
				'skipToContent'    => __( 'Skip to main content', 'havenlytics' ),
				'footerNote'       => __( 'Havenlytics Workspace', 'havenlytics' ),
				'footerDocumentation' => __( 'Documentation', 'havenlytics' ),
				'footerSupport'    => __( 'Support', 'havenlytics' ),
				'footerWebsite'    => __( 'Website', 'havenlytics' ),
				'footerVersion'    => __( 'Version', 'havenlytics' ),
				'unauthorizedTitle' => __( 'Sign in required', 'havenlytics' ),
				'unauthorizedSubtitle' => __( 'You must be logged in to use the Workspace. Login UI arrives in a later sprint.', 'havenlytics' ),
				'forbiddenTitle'   => __( 'Workspace access unavailable', 'havenlytics' ),
				'forbiddenSubtitle' => __( 'Your account is signed in but does not have Workspace access yet.', 'havenlytics' ),
				'identityLoading'  => __( 'Loading identity…', 'havenlytics' ),
				'identityError'    => __( 'Unable to load identity.', 'havenlytics' ),
			),
			'user'          => array(
				'isLoggedIn' => is_user_logged_in(),
			),
			'pro'           => WorkspaceProCapabilities::localize_payload(),
			'branding'      => WorkspaceBranding::get_public( is_user_logged_in() ? get_current_user_id() : 0 ),
		);

		// Prefer custom dashboard name for shell boot i18n.
		$custom_name = trim( (string) ( $data['branding']['dashboardName'] ?? '' ) );
		if ( '' !== $custom_name ) {
			$data['i18n']['brandName'] = $custom_name;
		}
		if ( ! empty( $data['branding']['dashboardLogoUrl'] ) ) {
			$data['assets']['iconUrl'] = (string) $data['branding']['dashboardLogoUrl'];
		}

		$unavailable                                      = WorkspaceAvailability::unavailable_copy();
		$data['i18n']['authWorkspaceUnavailableTitle']    = $unavailable['title'];
		$data['i18n']['authWorkspaceUnavailableSubtitle'] = $unavailable['description'];
		$data['i18n']['authLinkHome']                     = $unavailable['home_label'];
		$data['i18n']['authContactAdmin']                 = $unavailable['contact_label'];
		$data['i18n']['authSupportContactLabel']          = $unavailable['support_label'];
		$data['i18n']['authSubmitLogout']                 = $unavailable['logout_label'];

		// Ship the full resolved identity on the logged-in page load so the SPA
		// can initialize 'ready' synchronously — no blocking /me round-trip, and
		// therefore no branded loader flash on the very first login. The SPA still
		// re-fetches /me in the background to reconcile. Anonymous loads omit it.
		// Skip when Workspace is disabled — the unavailable gate does not need /me.
		if ( is_user_logged_in() && WorkspaceAvailability::is_available() ) {
			try {
				$identity = ( new Api\MeController() )->build_identity_payload();
				if ( ! empty( $identity ) ) {
					$data['identity'] = $identity;
				}
			} catch ( \Throwable $e ) {
				// Never let identity resolution block the shell from booting; the
				// SPA falls back to its /me round-trip when identity is absent.
				unset( $e );
			}
		}

		/**
		 * Filter Workspace SPA localization payload.
		 *
		 * @since 3.2.0
		 *
		 * @param array $data Localized data.
		 */
		return (array) apply_filters( 'hvnly_workspace_localize_data', $data );
	}

	/**
	 * Whether compiled Workspace build artifacts exist.
	 *
	 * @return bool
	 */
	public function is_build_available(): bool {
		$path = $this->get_asset_php_path();
		return $path !== '' && is_readable( $path );
	}

	/**
	 * Absolute path to workspace.asset.php.
	 *
	 * @return string
	 */
	private function get_asset_php_path(): string {
		return HVNLYNAB_BUILD_PATH . '/' . WorkspaceConstants::BUILD_FOLDER . '/' . WorkspaceConstants::ASSET_FILE;
	}

	/**
	 * Load @wordpress/scripts asset.php payload.
	 *
	 * @return array<string, mixed>
	 */
	private function get_asset_data(): array {
		$path = $this->get_asset_php_path();
		if ( $path === '' || ! is_readable( $path ) ) {
			return array();
		}

		$asset = include $path;
		return is_array( $asset ) ? $asset : array();
	}
}
