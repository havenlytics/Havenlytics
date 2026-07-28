<?php
/**
 * Onboarding Wizard — mounts the React Setup Wizard (Sprint 26C).
 *
 * This is a FRONTEND ONLY. It reuses the existing REST settings groups
 * (plugin-settings/{group}) and the existing admin-ajax import engine
 * (hvnly_import_properties). No backend behaviour, option keys, nonces, or the
 * import engine are changed. The legacy PHP import wizard is left intact.
 *
 * @package HvnlyNab\Admin
 */

namespace HvnlyNab\Admin;

use HvnlyNab\Admin\Data\TabData;
use HvnlyNab\Admin\Data\DemoData;
use HvnlyNab\I18n\ScriptTranslations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the React onboarding page and enqueues its bundle.
 */
class OnboardingWizard {

	/**
	 * Post type the wizard configures.
	 *
	 * @var string
	 */
	private $post_type = 'hvnly_property';

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	private $page_slug = 'hvnly-property-onboarding';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the "Get Started" submenu page.
	 *
	 * The page is always registered so its URL keeps working (activation
	 * redirect, bookmarks), but it is hidden from the menu once the site has
	 * content — mirroring the legacy wizard's "locked" behaviour so there is
	 * only ever one onboarding entry, shown only when it is useful.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$hook = add_submenu_page(
			'edit.php?post_type=' . $this->post_type,
			esc_html__( 'Get Started', 'havenlytics' ),
			esc_html__( 'Get Started', 'havenlytics' ),
			'manage_options',
			$this->page_slug,
			array( $this, 'render' )
		);

		if ( is_string( $hook ) && '' !== $hook ) {
			add_action( 'load-' . $hook, array( $this, 'maybe_redirect_completed_setup' ) );
		}

		if ( $this->is_onboarding_locked() ) {
			remove_submenu_page( 'edit.php?post_type=' . $this->post_type, $this->page_slug );
		}
	}

	/**
	 * Block direct URL access once setup has completed (properties exist).
	 *
	 * Fires on load-{hook} before scripts enqueue or the React mount renders.
	 *
	 * @return void
	 */
	public function maybe_redirect_completed_setup(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->is_onboarding_locked() ) {
			return;
		}

		wp_safe_redirect( $this->get_locked_redirect_url() );
		exit;
	}

	/**
	 * Admin URL when onboarding is no longer available.
	 *
	 * Matches the post-activation redirect for sites that already have listings.
	 *
	 * @return string
	 */
	private function get_locked_redirect_url(): string {
		return admin_url( 'edit.php?post_type=' . $this->post_type . '&page=hvnly_property_settings' );
	}

	/**
	 * Whether onboarding should be hidden from the menu.
	 *
	 * True once the demo import has completed or any property is published —
	 * the same signal the legacy Setup Wizard used to hide its entry.
	 *
	 * @return bool
	 */
	private function is_onboarding_locked(): bool {
		if ( get_option( 'hvnly_demo_properties_imported', false ) ) {
			return true;
		}

		return $this->get_existing_property_count() > 0;
	}

	/**
	 * Count meaningful property listings (excludes trash).
	 *
	 * Mirrors {@see \HvnlyNab\Core\HookManager::get_activation_redirect_url()}.
	 *
	 * @return int
	 */
	private function get_existing_property_count(): int {
		$counts = wp_count_posts( $this->post_type );
		$total  = 0;

		foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $status ) {
			$total += isset( $counts->$status ) ? absint( $counts->$status ) : 0;
		}

		return $total;
	}

	/**
	 * The screen id WordPress assigns to the submenu page.
	 *
	 * @return string
	 */
	private function screen_hook(): string {
		return $this->post_type . '_page_' . $this->page_slug;
	}

	/**
	 * Render the mount point. The React app takes over the viewport; a noscript
	 * fallback points users to their Properties when JavaScript is unavailable.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'havenlytics' ) );
		}

		if ( $this->is_onboarding_locked() ) {
			wp_safe_redirect( $this->get_locked_redirect_url() );
			exit;
		}
		?>
		<div id="hvnly-setup-root" class="hvnly-setup-root">
			<noscript>
				<div style="padding:2rem;font-family:sans-serif;">
					<h1><?php esc_html_e( 'JavaScript required', 'havenlytics' ); ?></h1>
					<p><?php esc_html_e( 'The setup wizard needs JavaScript. Please enable it in your browser to continue, or go to your Properties to add listings manually.', 'havenlytics' ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $this->post_type ) ); ?>">
						<?php esc_html_e( 'Go to Properties', 'havenlytics' ); ?>
					</a>
				</div>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Enqueue the React bundle and hand it the existing contracts it reuses.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ): void {
		if ( $this->screen_hook() !== $hook ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$asset_path = HVNLYNAB_PATH . 'build/setup/setup.asset.php';
		$asset      = file_exists( $asset_path )
			? include $asset_path
			: array();

		$deps    = isset( $asset['dependencies'] ) ? (array) $asset['dependencies'] : array();
		$version = isset( $asset['version'] ) ? (string) $asset['version'] : ( defined( 'HVNLYNAB_VERSION' ) ? HVNLYNAB_VERSION : '1.0.0' );
		$script  = isset( $asset['script'] ) ? (string) $asset['script'] : '0.js';
		$style   = isset( $asset['style'] ) ? (string) $asset['style'] : '0.css';

		// Leaflet powers the interactive Map step preview (reuses the bundled
		// front-end library — no CDN, no new dependency). Loaded before the app
		// so window.L is available when the React Map step mounts.
		$leaflet_js = HVNLYNAB_ASSETS_PATH . '/frontend/lib/leaflet/js/leaflet.js';
		if ( file_exists( $leaflet_js ) ) {
			wp_enqueue_style(
				'hvnly-setup-leaflet',
				HVNLYNAB_ASSETS_URL . '/frontend/lib/leaflet/css/leaflet.css',
				array(),
				'1.9.4'
			);
			wp_enqueue_script(
				'hvnly-setup-leaflet',
				HVNLYNAB_ASSETS_URL . '/frontend/lib/leaflet/js/leaflet.js',
				array(),
				'1.9.4',
				true
			);
			$deps[] = 'hvnly-setup-leaflet';
		}

		wp_enqueue_script(
			'hvnly-setup-wizard-app',
			HVNLYNAB_URL . 'build/setup/' . $script,
			$deps,
			$version,
			true
		);

		if ( '' !== $style ) {
			wp_enqueue_style(
				'hvnly-setup-wizard-app',
				HVNLYNAB_URL . 'build/setup/' . $style,
				array(),
				$version
			);
		}

		ScriptTranslations::attach( 'hvnly-setup-wizard-app' );

		wp_localize_script(
			'hvnly-setup-wizard-app',
			'hvnlySetupWizard',
			$this->bootstrap_data()
		);
	}

	/**
	 * Build the bootstrap object handed to the React app. Everything here maps to
	 * EXISTING REST endpoints, admin-ajax actions, nonces, option keys, and data
	 * sources — nothing new is created on the server.
	 *
	 * @return array<string, mixed>
	 */
	private function bootstrap_data(): array {
		$all_settings = get_option( 'hvnly_plugin_settings', array() );
		if ( ! is_array( $all_settings ) ) {
			$all_settings = array();
		}

		$general      = isset( $all_settings['general'] ) && is_array( $all_settings['general'] )
			? $all_settings['general'] : array();
		$contact_agent = isset( $all_settings['contact-agent'] ) && is_array( $all_settings['contact-agent'] )
			? $all_settings['contact-agent'] : array();

		// Canonical map settings (defaults merged) — reused verbatim, no new keys.
		$map = class_exists( '\HvnlyNab\Core\SettingsManager' )
			? \HvnlyNab\Core\SettingsManager::get_instance()->get_map_settings()
			: array();
		if ( ! is_array( $map ) ) {
			$map = array();
		}

		$limit = 25;
		if ( class_exists( DemoData::class ) ) {
			$limit = max( 1, count( DemoData::get_demo_properties_data() ) );
		}

		$currencies = class_exists( TabData::class )
			? TabData::hvnly_get_property_country_currency()
			: array( 'USD' => 'United States dollar ($)' );
		$countries  = class_exists( TabData::class )
			? TabData::hvnly_get_property_countries()
			: array( 'GB' => 'United Kingdom', 'US' => 'United States' );

		return array(
			'restUrl'    => esc_url_raw( rest_url( 'hvnlynab/v1/' ) ),
			'restNonce'  => wp_create_nonce( 'wp_rest' ),
			'ajaxUrl'    => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'importNonce' => wp_create_nonce( 'hvnly_import_nonce' ),
			'importCsrf' => wp_create_nonce( 'hvnly_import_csrf' ),
			'settings'   => array(
				'general'       => array(
					'hvnly_countryType'          => (string) ( $general['hvnly_countryType'] ?? 'GB' ),
					'hvnly_currencyType'         => (string) ( $general['hvnly_currencyType'] ?? 'USD' ),
					'hvnly_currencyPositionType' => (string) ( $general['hvnly_currencyPositionType'] ?? 'LEFT' ),
					'hvnly_thousandSeparator'    => (string) ( $general['hvnly_thousandSeparator'] ?? ',' ),
					'hvnly_decimalSeparator'     => (string) ( $general['hvnly_decimalSeparator'] ?? '.' ),
					'hvnly_numberOfDecimals'     => (string) ( $general['hvnly_numberOfDecimals'] ?? '0' ),
					'hvnly_defaultCity'          => (string) ( $general['hvnly_defaultCity'] ?? '' ),
					'hvnly_defaultState'         => (string) ( $general['hvnly_defaultState'] ?? '' ),
				),
				'contact-agent' => array(
					'hvnly_workspace_enabled'           => ! empty( $contact_agent['hvnly_workspace_enabled'] ),
					'hvnly_workspace_registration_mode' => (string) ( $contact_agent['hvnly_workspace_registration_mode'] ?? 'open' ),
				),
				// Map group — only the first-run keys the onboarding manages. The
				// REST `plugin-settings/map` save merges these key-by-key over the
				// stored group, so sibling map keys (clustering, controls, marker
				// style, google_map_type, fallback) are preserved untouched.
				'map'           => array(
					'hvnly_map_provider'   => (string) ( $map['hvnly_map_provider'] ?? 'leaflet' ),
					'hvnly_map_api_key'    => (string) ( $map['hvnly_map_api_key'] ?? '' ),
					'hvnly_google_map_id'  => (string) ( $map['hvnly_google_map_id'] ?? '' ),
					'hvnly_default_lat'    => (string) ( $map['hvnly_default_lat'] ?? '51.514939' ),
					'hvnly_default_lng'    => (string) ( $map['hvnly_default_lng'] ?? '-0.091839' ),
					'hvnly_map_zoom'       => (int) ( $map['hvnly_map_zoom'] ?? 12 ),
				),
			),
			'defaults'   => array(
				'hvnly_countryType'  => 'GB',
				'hvnly_currencyType' => 'USD',
			),
			'currencies'            => $currencies,
			'countries'             => $countries,
			// Supported map providers (mirrors the Settings → Map provider list;
			// no provider added or removed).
			'mapProviders'          => array(
				array( 'value' => 'leaflet', 'label' => esc_html__( 'Leaflet — OpenStreetMap (free, no API key)', 'havenlytics' ) ),
				array( 'value' => 'openstreetmap', 'label' => esc_html__( 'OpenStreetMap (free, no API key)', 'havenlytics' ) ),
				array( 'value' => 'google', 'label' => esc_html__( 'Google Maps (API key required)', 'havenlytics' ) ),
			),
			// Existing OSM tile config so the interactive preview matches the
			// front-end exactly (reused from SettingsManager, not redefined).
			'osmTileUrl'            => (string) ( $map['hvnly_osm_tile_url'] ?? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' ),
			'osmAttribution'        => (string) ( $map['hvnly_osm_attribution'] ?? '&copy; OpenStreetMap contributors' ),
			// Department keys handed to the EXISTING import engine so the demo
			// import distributes listings across all departments (the engine
			// round-robins array_keys(options[departments]); an empty per-key
			// value makes it fall back to each demo property's own taxonomy
			// fields). Order must match the demo dataset's index rotation.
			'importDepartments'     => array_keys( DemoData::get_department_options() ),
			'maxImportLimit'        => (int) $limit,
			'defaultImportQuantity' => (int) min( 10, $limit ),
			'propertiesUrl'         => esc_url_raw( admin_url( 'edit.php?post_type=' . $this->post_type ) ),
			// Sprint 28D — frontend listings destination (theme-aware). Distinct from
			// propertiesUrl (wp-admin list) used by "Go to my dashboard" / close.
			'viewPropertiesUrl'     => esc_url_raw( $this->get_view_properties_url() ),
			'builderUrl'            => esc_url_raw( admin_url( 'edit.php?post_type=' . $this->post_type . '&page=hvnly_property_builder' ) ),
			'settingsUrl'           => esc_url_raw( admin_url( 'edit.php?post_type=' . $this->post_type . '&page=hvnly_property_settings' ) ),
			'logo'                  => esc_url_raw( HVNLYNAB_ASSETS_URL . '/admin/img/havenlytics-icon20x20.svg' ),
		);
	}

	/**
	 * Frontend URL for "View Properties" on the Finish screen (Sprint 28D).
	 *
	 * Havenlytics Realty (and child themes) use the homepage for search/listings.
	 * Every other theme goes to the property post-type archive (permalink-aware).
	 *
	 * @return string Absolute URL.
	 */
	private function get_view_properties_url(): string {
		if ( $this->is_havenlytics_realty_theme() ) {
			return home_url( '/' );
		}

		return $this->get_property_archive_url();
	}

	/**
	 * Whether the active theme is Havenlytics Realty (or a child of it).
	 *
	 * Server-side only: stylesheet / template slug, then theme Name as a soft match.
	 *
	 * @return bool
	 */
	private function is_havenlytics_realty_theme(): bool {
		$is_realty  = false;
		$stylesheet = (string) get_stylesheet();
		$template   = (string) get_template();
		$slugs      = array( 'havenlytics-realty', 'havenlytics_realty' );

		if ( in_array( $stylesheet, $slugs, true ) || in_array( $template, $slugs, true ) ) {
			$is_realty = true;
		}

		// Child themes sometimes keep a custom stylesheet slug — detect parent pack.
		if ( ! $is_realty ) {
			$theme = wp_get_theme();
			if ( $theme instanceof \WP_Theme ) {
				$parent = $theme->parent();
				if ( $parent instanceof \WP_Theme ) {
					$parent_slug = (string) $parent->get_stylesheet();
					if ( in_array( $parent_slug, $slugs, true ) ) {
						$is_realty = true;
					}
				}

				if ( ! $is_realty ) {
					$name = strtolower( (string) $theme->get( 'Name' ) );
					if ( false !== strpos( $name, 'havenlytics realty' ) ) {
						$is_realty = true;
					}
				}
			}
		}

		/**
		 * Filter whether the active theme is treated as Havenlytics Realty for
		 * onboarding “View Properties” (homepage vs property archive).
		 *
		 * @since 3.2.2
		 *
		 * @param bool $is_realty Default detection result.
		 */
		return (bool) apply_filters( 'hvnly_setup_is_havenlytics_realty_theme', $is_realty );
	}

	/**
	 * Property listing archive URL — uses WP archive link / existing helpers.
	 *
	 * @return string Absolute URL.
	 */
	private function get_property_archive_url(): string {
		$url = '';

		if ( function_exists( 'hvnly_get_search_page_url' ) ) {
			$candidate = hvnly_get_search_page_url();
			if ( is_string( $candidate ) && '' !== $candidate ) {
				$url = $candidate;
			}
		}

		if ( '' === $url ) {
			$archive = get_post_type_archive_link( $this->post_type );
			if ( is_string( $archive ) && '' !== $archive ) {
				$url = $archive;
			}
		}

		if ( '' === $url && class_exists( '\HvnlyNab\Core\PermalinkSettings' ) ) {
			$slug = \HvnlyNab\Core\PermalinkSettings::get_property_archive_slug();
			if ( is_string( $slug ) && '' !== $slug ) {
				$url = home_url( '/' . trim( $slug, '/' ) . '/' );
			}
		}

		if ( '' === $url ) {
			$url = home_url( '/properties/' );
		}

		return $url;
	}
}
