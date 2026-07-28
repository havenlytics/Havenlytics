<?php

namespace HvnlyNab\Admin;

use HvnlyNab\I18n\ScriptTranslations;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

final class Assets
{
    private const ADMIN_FONTS_HANDLE = 'hvnly-admin-fonts';

    private const ADMIN_FONTS_CSS = '/admin/css/fonts.css';

    /**
     * React admin screens and their required build bundle files.
     *
     * @var array<string, array{folder: string, asset: string, script: string, style: string}>
     */
    private const REACT_BUILD_PAGES = [
        'hvnly_property_page_hvnly_property_settings' => [
            'folder' => 'settings',
            'asset'  => 'settings.asset.php',
            'script' => '0.js',
            'style'  => '0.css',
        ],
        'hvnly_property_page_hvnly_property_builder' => [
            'folder' => 'builder',
            'asset'  => 'builder.asset.php',
            'script' => '0.js',
            'style'  => '0.css',
        ],
        'toplevel_page_hvnly_property_builder' => [
            'folder' => 'builder',
            'asset'  => 'builder.asset.php',
            'script' => '0.js',
            'style'  => '0.css',
        ],
        'hvnly_property_page_hvnly_property_reports_analytics' => [
            'folder' => 'reports',
            'asset'  => 'reports.asset.php',
            'script' => '0.js',
            'style'  => '0.css',
        ],
        'toplevel_page_hvnly_property_reports_analytics' => [
            'folder' => 'reports',
            'asset'  => 'reports.asset.php',
            'script' => '0.js',
            'style'  => '0.css',
        ],
    ];

    /**
     * Reports bundle config (Analytics top-level + Overview submenu).
     *
     * Overview must not be keyed by a locale-dependent hook suffix.
     *
     * @var array{handle: string, folder: string, asset: string, script: string, style: string, localize: bool}
     */
    private const REPORTS_PAGE_ASSETS = [
        'handle'   => 'hvlynab-reports-admin',
        'folder'   => 'reports',
        'asset'    => 'reports.asset.php',
        'script'   => 'reports.js',
        'style'    => 'reports.css',
        'localize' => true,
    ];

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts_callback']);
        add_action('admin_init', [$this, 'register_cache_settings']);
        add_action('admin_notices', [self::class, 'render_missing_build_asset_notice']);
    }

    /**
     * Register cache settings
     */
    public function register_cache_settings(): void
    {
        register_setting(
            'hvnly_cache_settings',
            'hvnly_cache_enabled',
            [
                'sanitize_callback' => 'absint',
            ]
        );

        register_setting(
            'hvnly_cache_settings',
            'hvnly_cache_ttl',
            [
                'sanitize_callback' => 'absint',
            ]
        );

        register_setting(
            'hvnly_cache_settings',
            'hvnly_cache_compression',
            [
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        register_setting(
            'hvnly_cache_settings',
            'hvnly_cache_debug',
            [
                'sanitize_callback' => 'absint',
            ]
        );
    }

    /**
     * Enqueue JS/CSS only on specific admin pages
     */
    public function admin_enqueue_scripts_callback($hook_suffix)
    {
        $hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : '';

        // Overview submenu hook prefix is locale-dependent (translated parent title).
        $is_analytics_overview = Menu::is_analytics_overview_request( $hook_suffix );

        $allowed_pages = [
            'hvnly_property_page_hvnly_property_settings',
            'hvnly_property_page_hvnly_property_builder',
            'hvnly_property_page_hvnly_property_reports_analytics',
            'toplevel_page_hvnly_property_builder',
            'toplevel_page_hvnly_property_reports_analytics',
            'toplevel_page_hvnly_inquiries',
            'marketing_page_hvnly_marketing_inquiries',
            'hvnly_property_page_hvnly_property_cache',
            'hvnly_property_page_hvnly-property-setup',
        ];

        $is_plugin_admin_page = in_array( $hook_suffix, $allowed_pages, true ) || $is_analytics_overview;

        $default_version = defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '1.0.0';

        wp_enqueue_style(
            'hvnly-admin-fontawesome-all',
            HVNLYNAB_ASSETS_URL . '/admin/css/fontawesome-all.min.css',
            [],
            $default_version
        );

        // Scoped admin menu styling for Havenlytics custom menus only.
        $menu_css_path = HVNLYNAB_ASSETS_PATH . '/admin/css/hvnly-admin-menu.css';
        if (file_exists($menu_css_path)) {
            wp_enqueue_style(
                'hvnly-admin-menu',
                HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-menu.css',
                [],
                $default_version
            );
        }

        if ( $is_plugin_admin_page ) {
            $this->enqueue_admin_fonts_styles($default_version);
        }

        if ($hook_suffix === 'hvnly_property_page_hvnly-property-setup') {
            $this->enqueue_setup_assets($default_version);
            return;
        }

        if ($hook_suffix === 'hvnly_property_page_hvnly_property_cache') {
            $this->enqueue_cache_assets($default_version);
            return;
        }

        if (
            $hook_suffix === 'hvnly_property_page_hvnly_property_builder'
            || $hook_suffix === 'hvnly_property_page_hvnly_property_settings'
            || $hook_suffix === 'hvnly_property_page_hvnly_property_reports_analytics'
            || $hook_suffix === 'toplevel_page_hvnly_property_builder'
            || $hook_suffix === 'toplevel_page_hvnly_property_reports_analytics'
            || $is_analytics_overview
            || $hook_suffix === 'toplevel_page_hvnly_inquiries'
            || $hook_suffix === 'marketing_page_hvnly_marketing_inquiries'
        ) {
            $this->enqueue_admin_boot_styles($default_version);
        }

        // Agent CPT screens (list + edit + add-new) need brand tokens for menu states.
        // Prefer current_screen: post.php edit URLs omit ?post_type=.
        $screen             = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $screen_post_type   = ( $screen && isset( $screen->post_type ) ) ? (string) $screen->post_type : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only enqueue routing.
        $query_post_type    = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( (string) $_GET['post_type'] ) ) : '';
        $is_agent_admin_cpt = in_array( 'hvnly_agent', array( $screen_post_type, $query_post_type ), true );
        if (
            in_array( $hook_suffix, [ 'edit.php', 'post.php', 'post-new.php' ], true )
            && $is_agent_admin_cpt
        ) {
            $this->enqueue_admin_boot_styles($default_version);
        }

        if ( ! $is_plugin_admin_page ) {
            return;
        }

        $pages = [
            'hvnly_property_page_hvnly_property_settings' => [
                'handle'   => 'hvlynab-settings-admin',
                'folder'   => 'settings',
                'asset'    => 'settings.asset.php',
                'script'   => 'settings.js',
                'style'    => 'settings.css',
                'localize' => true,
            ],
            'hvnly_property_page_hvnly_property_builder' => [
                'handle'   => 'hvlynab-builder-admin',
                'folder'   => 'builder',
                'asset'    => 'builder.asset.php',
                'script'   => 'builder.js',
                'style'    => 'builder.css',
                'localize' => true,
            ],
            'toplevel_page_hvnly_property_builder' => [
                'handle'   => 'hvlynab-builder-admin',
                'folder'   => 'builder',
                'asset'    => 'builder.asset.php',
                'script'   => 'builder.js',
                'style'    => 'builder.css',
                'localize' => true,
            ],
            'hvnly_property_page_hvnly_property_reports_analytics' => self::REPORTS_PAGE_ASSETS,
            'toplevel_page_hvnly_property_reports_analytics'       => self::REPORTS_PAGE_ASSETS,
        ];

        if ( isset( $pages[ $hook_suffix ] ) ) {
            $this->enqueue_page_assets( $pages[ $hook_suffix ], $default_version );
        } elseif ( $is_analytics_overview ) {
            $this->enqueue_page_assets( self::REPORTS_PAGE_ASSETS, $default_version );
        }
    }

    private function enqueue_setup_assets($version): void
    {
        wp_enqueue_style(
            'hvnly-setup-wizard',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-setup-wizard.css',
            [],
            $version
        );

        wp_enqueue_script(
            'hvnly-setup-wizard',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-setup-wizard.js',
            ['jquery', 'wp-util'],
            $version,
            true
        );

        $tab_structure = \HvnlyNab\Admin\Data\TabData::hvnly_metabox_tabs_builder();
        $field_names = $this->extract_field_names($tab_structure);

        wp_localize_script('hvnly-setup-wizard', 'hvnlysetupWizard', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hvnly_setup_nonce'),
            'setuped' => get_option('hvnly_demo_properties_setuped', false),
            'setupCount' => get_option('hvnly_demo_properties_count', 0),
            'totalProperties' => wp_count_posts('hvnly_property')->publish ?? 0,
            'strings' => [
                'confirmsetup' => __('Are you sure you want to setup demo properties? This will create sample listings with realistic data.', 'havenlytics'),
                'setuping' => __('setuping properties...', 'havenlytics'),
                'complete' => __('setup complete!', 'havenlytics'),
                'error' => __('An error occurred during setup.', 'havenlytics'),
                'viewProperties' => __('View Properties', 'havenlytics'),
                'setupMore' => __('setup More', 'havenlytics'),
            ],
        ]);
    }

    private function extract_field_names($tabs) {
        $fields = [];
        foreach ($tabs as $tab) {
            if (isset($tab['fields']) && is_array($tab['fields'])) {
                foreach ($tab['fields'] as $field) {
                    if (isset($field['name'])) {
                        $fields[] = $field['name'];
                    }
                }
            }
        }
        return $fields;
    }

    private function enqueue_cache_assets($version): void
    {
        $css_file = HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-cache.css';
        if (file_exists(str_replace(HVNLYNAB_URL, HVNLYNAB_PATH, $css_file))) {
            wp_enqueue_style(
                'hvnly-admin-cache',
                $css_file,
                [],
                $version
            );
        }

        $js_file = HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-admin-cache.js';
        if (file_exists(str_replace(HVNLYNAB_URL, HVNLYNAB_PATH, $js_file))) {
            wp_enqueue_script(
                'hvnly-admin-cache',
                $js_file,
                ['jquery', 'wp-util'],
                $version,
                true
            );

            wp_localize_script('hvnly-admin-cache', 'hvnlyCacheAdmin', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('hvnly_cache_nonce'),
                'clearingText' => __('Clearing cache...', 'havenlytics'),
                'clearedText' => __('Cache cleared successfully!', 'havenlytics'),
                'errorText' => __('Error clearing cache', 'havenlytics')
            ]);
        }
    }

    private function enqueue_admin_boot_styles(string $version): void
    {
        $css_file = HVNLYNAB_ASSETS_PATH . '/admin/css/hvnly-admin-boot.css';
        if (!file_exists($css_file)) {
            return;
        }

        wp_enqueue_style(
            'hvnly-admin-boot',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-boot.css',
            [],
            $version
        );
    }

    private function enqueue_admin_fonts_styles(string $version): void
    {
        $css_file = HVNLYNAB_ASSETS_PATH . self::ADMIN_FONTS_CSS;
        if (!file_exists($css_file)) {
            return;
        }

        wp_enqueue_style(
            self::ADMIN_FONTS_HANDLE,
            HVNLYNAB_ASSETS_URL . self::ADMIN_FONTS_CSS,
            [],
            $version
        );
    }

    /** @deprecated 3.0.4 Use enqueue_admin_boot_styles(). */
    private function enqueue_builder_boot_styles(string $version): void
    {
        $this->enqueue_admin_boot_styles($version);
    }

    /** @deprecated 3.0.4 Use enqueue_admin_boot_styles(). */
    private function enqueue_settings_boot_styles(string $version): void
    {
        $this->enqueue_admin_boot_styles($version);
    }

    private function enqueue_page_assets($page, $version): void
    {
        $asset_file = HVNLYNAB_BUILD_PATH . '/' . $page['folder'] . '/' . $page['asset'];
        
        if (!file_exists($asset_file)) {
            // Debug code removed for production - asset file missing silently ignored
            return;
        }

        $main_asset = require $asset_file;

        $script_name = $main_asset['script'] ?? $page['script'];
        $style_name  = $main_asset['style'] ?? $page['style'];

        $dependencies = $this->normalize_script_dependencies($main_asset['dependencies'] ?? []);
        $chunk_handles = $this->enqueue_numeric_webpack_chunks(
            $page['folder'],
            $page['handle'],
            $script_name,
            $style_name,
            $main_asset['version'] ?? $version,
            $dependencies
        );

        $js_file = HVNLYNAB_BUILD_URL . '/' . $page['folder'] . '/' . $script_name;
        wp_enqueue_script(
            $page['handle'],
            $js_file,
            array_merge($dependencies, $chunk_handles['scripts']),
            $main_asset['version'] ?? $version,
            true
        );

        // Required for React __() / @wordpress/i18n — must use the real enqueue handle.
        ScriptTranslations::attach($page['handle']);

        $css_file = HVNLYNAB_BUILD_PATH . '/' . $page['folder'] . '/' . $style_name;
        if (file_exists($css_file)) {
            wp_enqueue_style(
                $page['handle'],
                HVNLYNAB_BUILD_URL . '/' . $page['folder'] . '/' . $style_name,
                array_merge(['hvnly-admin-boot'], $chunk_handles['styles']),
                $main_asset['version'] ?? $version
            );
        }

        $this->enqueue_admin_boot_fallback_script($page['handle']);

        if (!empty($page['localize'])) {
            if ( 'settings' === ( $page['folder'] ?? '' ) && class_exists( '\HvnlyNab\Core\Installer' ) ) {
                // Recreate any missing auto-pages when an admin opens Settings.
                \HvnlyNab\Core\Installer::ensure_required_pages();
            }

            $current_user = wp_get_current_user();

            wp_localize_script(
                $page['handle'],
                'HavenlyticsData',
                [
                    'hvnlyNabApi' => [
                        'url' => esc_url_raw(rest_url('hvnlynab/v1')),
                        'nonce' => wp_create_nonce('wp_rest')
                    ],
                    'ajax' => [
                        'url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('hvnlyNab_ajax')
                    ],
                    'importExport' => [
                        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                        'nonce'   => class_exists( '\HvnlyNab\ImportExport\ImportExportModule' )
                            ? \HvnlyNab\ImportExport\ImportExportModule::create_nonce()
                            : wp_create_nonce( 'hvnly_ie' ),
                        'limits'  => class_exists( '\HvnlyNab\ImportExport\Capability\MigrationLimits' )
                            ? \HvnlyNab\ImportExport\Capability\MigrationLimits::public_status()
                            : null,
                    ],
                    'csvTransfer' => [
                        'ajaxUrl' => esc_url_raw( set_url_scheme( admin_url( 'admin-ajax.php' ) ) ),
                        'nonce'   => class_exists( '\HvnlyNab\CsvTransfer\CsvTransferModule' )
                            ? \HvnlyNab\CsvTransfer\CsvTransferModule::create_nonce()
                            : wp_create_nonce( 'hvnly_csv' ),
                    ],
                    'user' => [
                        'id' => $current_user->ID,
                        'name' => $current_user->display_name,
                        'email' => $current_user->user_email,
                        'caps' => array_keys(array_filter($current_user->allcaps)),
                        'avatar' => \HvnlyNab\Common\AvatarService::resolve_for_user((int) $current_user->ID, 36)
                    ],
                    'urls' => [
                        'admin' => admin_url(),
                        'site' => home_url(),
                        'assets' => HVNLYNAB_ASSETS_URL . '/'
                    ],
                    'settings' => [
                        'date_format' => get_option('date_format'),
                        'time_format' => get_option('time_format')
                    ],
                    'themeRecommendation' => $this->get_theme_recommendation_data(),
                    'debug' => function_exists( 'hvnly_is_debug_logging_enabled' ) && hvnly_is_debug_logging_enabled(),
                    // Settings footer + cache-safe boot payload (never hardcode).
                    'version' => defined( 'HVNLYNAB_VERSION' ) ? (string) HVNLYNAB_VERSION : '',
                    'pluginVersion' => defined( 'HVNLYNAB_VERSION' ) ? (string) HVNLYNAB_VERSION : '',
                    // PHP-localized boot chrome stages (Option A — no Jed for preloader text).
                    'boot' => array(
                        'stages' => array(
                            'loading'                 => __( 'Loading…', 'havenlytics' ),
                            'loading_settings'        => __( 'Loading settings…', 'havenlytics' ),
                            'ready'                   => __( 'Ready', 'havenlytics' ),
                            'loading_configuration'   => __( 'Loading configuration…', 'havenlytics' ),
                            'configuration_loaded'    => __( 'Configuration loaded', 'havenlytics' ),
                            'loading_analytics'       => __( 'Loading analytics…', 'havenlytics' ),
                            'loading_charts'          => __( 'Loading charts and reports…', 'havenlytics' ),
                        ),
                    ),
                ]
            );
        }
    }

    /**
     * Theme-recommendation state + action URLs for the Settings sidebar card.
     *
     * Mirrors the display rules in ThemeRecommendationNotice: the card shows
     * for 'installed' (with an Activate action) and 'not_installed' (with an
     * Install action), and is hidden entirely when the theme is active.
     *
     * @since 3.2.0
     * @return array<string,string>
     */
    private function get_theme_recommendation_data(): array
    {
        $slug = ThemeRecommendationNotice::get_theme_slug();

        /*
         * Activation URL — matches WordPress core's themes.php activate flow:
         * action=activate, stylesheet=<slug>, _wpnonce=<switch-theme_<slug>>.
         *
         * NOTE: wp_nonce_url() returns esc_html()'d output (ampersands become
         * "&#038;"), which is correct for echoing directly into HTML but
         * corrupts the query string once the value is passed through
         * wp_localize_script (JSON) and assigned as a JS href — the browser
         * does not decode the entities, so `stylesheet`/`_wpnonce` are lost.
         * For a URL consumed by JS we build it with add_query_arg (+ the core
         * nonce) so the ampersands stay raw, then esc_url_raw() for the
         * non-display context. This is the WordPress-recommended pattern for
         * nonce'd URLs outside of direct HTML output.
         */
        $activate_url = add_query_arg(
            array(
                'action'     => 'activate',
                'stylesheet' => $slug,
                '_wpnonce'   => wp_create_nonce('switch-theme_' . $slug),
            ),
            admin_url('themes.php')
        );

        return [
            'state'        => ThemeRecommendationNotice::get_theme_state(),
            'installUrl'   => esc_url_raw(admin_url('theme-install.php?search=havenlytics')),
            'activateUrl'  => esc_url_raw($activate_url),
            'themesUrl'    => esc_url_raw(admin_url('themes.php')),
            'learnMoreUrl' => 'https://havenlytics.com/property-theme/',
        ];
    }

    /**
     * Dismiss PHP boot chrome if React fails to mount (prevents stuck full-screen preloader).
     */
    private function enqueue_admin_boot_fallback_script(string $handle): void
    {
        wp_add_inline_script(
            $handle,
            "(function(){var bootDismissed=false;function dismissHvnlyAdminBoot(){if(bootDismissed){return;}bootDismissed=true;var preloader=document.getElementById('hvnly-admin-static-preloader');if(preloader){preloader.remove();}['HvnlyNab_admin_dashboard_wrap','HvnlyNab_property_builder_render','HvnlyNab_reports_analytics_wrap'].forEach(function(id){document.getElementById(id)?.classList.remove('hvnly-admin-is-loading');});document.body.classList.remove('hvnly-admin-page-loading');}window.setTimeout(function(){if(document.body.classList.contains('hvnly-admin-page-loading')){dismissHvnlyAdminBoot();}},20000);})();",
            'after'
        );
    }

    /**
     * Pre-register numeric webpack chunk files (1.js, 2.js, …) when present.
     * Prevents blank admin screens when async chunks were omitted from SVN/ZIP deploys.
     *
     * @param string               $folder        Build subfolder (builder|settings).
     * @param string               $handle_prefix Parent script handle.
     * @param string               $entry_script  Entry bundle filename (e.g. 0.js).
     * @param string               $entry_style   Entry stylesheet filename (e.g. 0.css).
     * @param string               $version       Asset version hash.
     * @param array<int, string>   $dependencies  Base script dependencies.
     * @return array{scripts: array<int, string>, styles: array<int, string>}
     */
    private function enqueue_numeric_webpack_chunks(
        string $folder,
        string $handle_prefix,
        string $entry_script,
        string $entry_style,
        string $version,
        array $dependencies
    ): array {
        $build_dir = HVNLYNAB_BUILD_PATH . '/' . $folder;
        if (!is_dir($build_dir)) {
            return ['scripts' => [], 'styles' => []];
        }

        $script_handles = [];
        $style_handles  = [];
        $script_files   = [];
        $style_files    = [];

        foreach (scandir($build_dir) ?: [] as $file) {
            if (preg_match('/^(\d+)\.js$/', $file, $matches)) {
                if ($file === $entry_script) {
                    continue;
                }
                $script_files[(int) $matches[1]] = $file;
            } elseif (preg_match('/^(\d+)\.css$/', $file, $matches)) {
                if ($file === $entry_style) {
                    continue;
                }
                $style_files[(int) $matches[1]] = $file;
            }
        }

        ksort($script_files, SORT_NUMERIC);
        ksort($style_files, SORT_NUMERIC);

        $prev_script_handle = '';
        foreach ($script_files as $index => $chunk_file) {
            $chunk_handle = $handle_prefix . '-chunk-' . $index;
            wp_enqueue_script(
                $chunk_handle,
                HVNLYNAB_BUILD_URL . '/' . $folder . '/' . $chunk_file,
                $prev_script_handle === '' ? $dependencies : [$prev_script_handle],
                $version,
                true
            );
            $script_handles[] = $chunk_handle;
            $prev_script_handle = $chunk_handle;
        }

        $prev_style_handle = '';
        foreach ($style_files as $index => $chunk_file) {
            $chunk_handle = $handle_prefix . '-chunk-css-' . $index;
            wp_enqueue_style(
                $chunk_handle,
                HVNLYNAB_BUILD_URL . '/' . $folder . '/' . $chunk_file,
                $prev_style_handle === '' ? [] : [$prev_style_handle],
                $version
            );
            $style_handles[] = $chunk_handle;
            $prev_style_handle = $chunk_handle;
        }

        return [
            'scripts' => $script_handles,
            'styles'  => $style_handles,
        ];
    }

    /**
     * Admin bundles externalize React (window.React / window.ReactDOM). Ensure core
     * vendor scripts are registered and keep react + react-dom + wp-i18n in the dependency list.
     *
     * @param array<int, string> $dependencies Asset dependencies from the build manifest.
     * @return array<int, string>
     */
    private function normalize_script_dependencies(array $dependencies): array
    {
        $this->ensure_react_runtime_scripts();

        $normalized = array_values(array_unique($dependencies));

        if (!in_array('wp-element', $normalized, true)) {
            $normalized[] = 'wp-element';
        }

        // React admin UI uses @wordpress/i18n (__ / _x / _n). Keep the dependency
        // even if an older asset.php omitted it so locale data can load.
        if (!in_array('wp-i18n', $normalized, true)) {
            $normalized[] = 'wp-i18n';
        }

        if (!in_array('react', $normalized, true)) {
            array_unshift($normalized, 'react', 'react-dom');
        } elseif (!in_array('react-dom', $normalized, true)) {
            $normalized[] = 'react-dom';
        }

        return $normalized;
    }

    /**
     * Register WordPress core React vendor scripts when missing (some admin screens).
     *
     * Both react and react-dom must exist — bundles externalize React. Other plugins
     * sometimes register only `react`, which leaves react-dom missing and breaks mounts.
     */
    private function ensure_react_runtime_scripts(): void
    {
        $suffix = function_exists('wp_scripts_get_suffix') ? wp_scripts_get_suffix() : '.min';
        $base   = includes_url('js/dist/vendor/');

        if (!wp_script_is('react', 'registered')) {
            wp_register_script(
                'react',
                $base . 'react' . $suffix . '.js',
                [],
                '18.3.1.1',
                true
            );
        }

        if (!wp_script_is('react-dom', 'registered')) {
            wp_register_script(
                'react-dom',
                $base . 'react-dom' . $suffix . '.js',
                ['react'],
                '18.3.1.1',
                true
            );
        }
    }

    /**
     * Surface missing React build assets on Havenlytics Settings / Builder screens.
     *
     * @return void
     */
    public static function render_missing_build_asset_notice(): void
    {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ( ! $screen ) {
            return;
        }

        $build_page = self::resolve_react_build_page( $screen );
        if ( null === $build_page ) {
            return;
        }

        $missing = self::get_missing_react_build_asset_paths_for_page( $build_page );
        if ($missing === []) {
            return;
        }

        $paths_markup = '';
        foreach ($missing as $relative_path) {
            $paths_markup .= '<li><code>' . esc_html($relative_path) . '</code></li>';
        }

        printf(
            '<div class="notice notice-error is-dismissible hvnly-missing-build-assets-notice"><p><strong>%1$s</strong> %2$s</p><ul>%3$s</ul></div>',
            esc_html__('Havenlytics:', 'havenlytics'),
            esc_html__(
                'Build assets are missing. Please reinstall the plugin or upload the official release ZIP.',
                'havenlytics'
            ),
            $paths_markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paths escaped per item.
        );
    }

    /**
     * Resolve React build metadata for the current admin screen.
     *
     * Analytics Overview uses a locale-dependent screen ID; match via page slug.
     *
     * @param \WP_Screen $screen Current screen.
     * @return array{folder: string, asset: string, script: string, style: string}|null
     */
    private static function resolve_react_build_page( \WP_Screen $screen ): ?array {
        if ( isset( self::REACT_BUILD_PAGES[ $screen->id ] ) ) {
            return self::REACT_BUILD_PAGES[ $screen->id ];
        }

        if ( Menu::is_analytics_overview_request() ) {
            return self::REACT_BUILD_PAGES['toplevel_page_hvnly_property_reports_analytics'];
        }

        return null;
    }

    /**
     * @param string $screen_id Current admin screen ID.
     * @return string[] Plugin-relative paths that are missing on disk.
     */
    private static function get_missing_react_build_asset_paths(string $screen_id): array
    {
        if ( ! isset( self::REACT_BUILD_PAGES[ $screen_id ] ) ) {
            return [];
        }

        return self::get_missing_react_build_asset_paths_for_page( self::REACT_BUILD_PAGES[ $screen_id ] );
    }

    /**
     * @param array{folder: string, asset: string, script: string, style: string} $page Build page config.
     * @return string[] Plugin-relative paths that are missing on disk.
     */
    private static function get_missing_react_build_asset_paths_for_page( array $page ): array
    {
        if (!defined('HVNLYNAB_BUILD_PATH')) {
            return [];
        }

        $build_dir  = HVNLYNAB_BUILD_PATH . '/' . $page['folder'];
        $asset_file = $build_dir . '/' . $page['asset'];
        $script     = $page['script'];
        $style      = $page['style'];

        if (is_readable($asset_file)) {
            $manifest = include $asset_file;
            if (is_array($manifest)) {
                if (!empty($manifest['script']) && is_string($manifest['script'])) {
                    $script = $manifest['script'];
                }
                if (!empty($manifest['style']) && is_string($manifest['style'])) {
                    $style = $manifest['style'];
                }
            }
        }

        $required_files = [
            $asset_file,
            $build_dir . '/' . $script,
            $build_dir . '/' . $style,
        ];

        $missing = [];
        foreach ($required_files as $absolute_path) {
            if (!is_readable($absolute_path)) {
                $missing[] = self::to_plugin_relative_path($absolute_path);
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param string $absolute_path Absolute file path.
     * @return string Path relative to the plugin root.
     */
    private static function to_plugin_relative_path(string $absolute_path): string
    {
        $plugin_root = wp_normalize_path(HVNLYNAB_PATH);
        $normalized  = wp_normalize_path($absolute_path);

        if (strpos($normalized, $plugin_root) === 0) {
            return ltrim(substr($normalized, strlen($plugin_root)), '/');
        }

        return $normalized;
    }
}