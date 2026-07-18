<?php

namespace HvnlyNab\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class Menu
{
    private const PARENT_SLUG = 'edit.php?post_type=hvnly_property';
    private const CAPABILITY = 'manage_options';

    /**
     * Standalone module page slugs (canonical admin.php?page=...).
     */
    private const PAGE_SLUG_PROPERTY_BUILDER = 'hvnly_property_builder';
    private const PAGE_SLUG_ANALYTICS        = 'hvnly_property_reports_analytics';
    private const SUBMENU_SLUG_ANALYTICS     = 'hvnly_analytics_overview';

    /** Admin hook suffix for the Overview submenu screen. */
    private const HOOK_ANALYTICS_OVERVIEW = 'analytics_page_hvnly_analytics_overview';

    /** Menu positions — grouped before WordPress Posts (5). */
    private const MENU_POSITION_BUILDER  = 4.1;
    private const MENU_POSITION_ANALYTICS = 4.2;

    /**
     * Legacy CPT submenu URLs (must continue working via redirect).
     */
    private const LEGACY_URL_PROPERTY_BUILDER = 'edit.php?post_type=hvnly_property&page=hvnly_property_builder';
    private const LEGACY_URL_ANALYTICS        = 'edit.php?post_type=hvnly_property&page=hvnly_property_reports_analytics';

    public function __construct()
    {
        // Standalone module menus/pages (canonical; no redirects).
        add_action( 'admin_menu', [ $this, 'register_module_menus' ], 8 );

        add_action('admin_menu', [$this, 'register_submenus']);
        add_action( 'admin_menu', [ $this, 'cleanup_legacy_submenu_entries' ], 999 );
        add_action('current_screen', [$this, 'suppress_other_notices']);
        add_filter('admin_body_class', [$this, 'builder_admin_body_class']);
        add_filter( 'parent_file', [ $this, 'highlight_analytics_parent' ], 10 );
        add_filter( 'submenu_file', [ $this, 'highlight_analytics_submenu' ], 10, 2 );
    }

    /**
     * Register standalone module menus/pages (no redirects).
     *
     * @return void
     */
    public function register_module_menus(): void {
        $capability = apply_filters( 'hvnly_admin_capability', self::CAPABILITY );

        add_menu_page(
            esc_html__( 'Property Builder', 'havenlytics' ),
            esc_html__( 'Property Builder', 'havenlytics' ),
            $capability,
            self::PAGE_SLUG_PROPERTY_BUILDER,
            [ $this, 'render_builder' ],
            'dashicons-layout',
            self::MENU_POSITION_BUILDER
        );

        add_menu_page(
            esc_html__( 'Analytics', 'havenlytics' ),
            esc_html__( 'Analytics', 'havenlytics' ),
            $capability,
            self::PAGE_SLUG_ANALYTICS,
            [ $this, 'render_reports' ],
            'dashicons-chart-area',
            self::MENU_POSITION_ANALYTICS
        );

        add_submenu_page(
            self::PAGE_SLUG_ANALYTICS,
            esc_html__( 'Overview', 'havenlytics' ),
            esc_html__( 'Overview', 'havenlytics' ),
            $capability,
            self::SUBMENU_SLUG_ANALYTICS,
            [ $this, 'render_reports' ]
        );
    }

    public function register_submenus(): void
    {
        $capability = apply_filters('hvnly_admin_capability', self::CAPABILITY);

        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Settings', 'havenlytics'),
            esc_html__('Settings', 'havenlytics'),
            $capability,
            'hvnly_property_settings',
            [$this, 'render_settings']
        );

        add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Documentation', 'havenlytics'),
            esc_html__('Documentation', 'havenlytics'),
            $capability,
            DocumentationPage::PAGE_SLUG,
            [DocumentationPage::class, 'render']
        );

        $legacy_builder_hook = add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Property Builder', 'havenlytics'),
            esc_html__('Property Builder', 'havenlytics'),
            $capability,
            'hvnly_property_builder',
            [ $this, 'render_legacy_redirect_placeholder' ]
        );
        if ( is_string( $legacy_builder_hook ) && $legacy_builder_hook !== '' ) {
            add_action( 'load-' . $legacy_builder_hook, [ $this, 'redirect_legacy_builder' ] );
        }

        $legacy_analytics_hook = add_submenu_page(
            self::PARENT_SLUG,
            esc_html__('Analytics', 'havenlytics'),
            esc_html__('Analytics', 'havenlytics'),
            $capability,
            'hvnly_property_reports_analytics',
            [ $this, 'render_legacy_redirect_placeholder' ]
        );
        if ( is_string( $legacy_analytics_hook ) && $legacy_analytics_hook !== '' ) {
            add_action( 'load-' . $legacy_analytics_hook, [ $this, 'redirect_legacy_analytics' ] );
        }

        
        // Cache menu item removed - now handled by CacheAdmin.php
    }

    /**
     * Hide legacy submenu entries from the Havenlytics CPT menu while keeping URLs working.
     *
     * The pages remain registered (for backward-compatible bookmarks), but are no longer
     * shown under the Havenlytics (Properties) menu once module top-level entries exist.
     *
     * @return void
     */
    public function cleanup_legacy_submenu_entries(): void {
        // WordPress auto-adds a duplicate submenu matching the top-level slug.
        remove_submenu_page( self::PAGE_SLUG_PROPERTY_BUILDER, self::PAGE_SLUG_PROPERTY_BUILDER );
        // No Property Builder submenus in wp-admin (tabs live inside React app).
        remove_submenu_page( self::PAGE_SLUG_PROPERTY_BUILDER, self::PAGE_SLUG_PROPERTY_BUILDER . '&tab=add-property-form' );
        remove_submenu_page( self::PAGE_SLUG_PROPERTY_BUILDER, self::PAGE_SLUG_PROPERTY_BUILDER . '&tab=property-card-layout' );

        // WordPress auto-adds a duplicate Analytics submenu matching the top-level slug.
        remove_submenu_page( self::PAGE_SLUG_ANALYTICS, self::PAGE_SLUG_ANALYTICS );

        remove_submenu_page( self::PARENT_SLUG, 'hvnly_property_builder' );
        remove_submenu_page( self::PARENT_SLUG, 'hvnly_property_reports_analytics' );
        remove_submenu_page( self::PARENT_SLUG, 'hvnly_inquiries' );
    }

    /**
     * Keep Analytics highlighted on the Overview screen.
     *
     * @param string $parent_file Parent menu file.
     * @return string
     */
    public function highlight_analytics_parent( string $parent_file ): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only menu routing.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
        if ( in_array( $page, [ self::PAGE_SLUG_ANALYTICS, self::SUBMENU_SLUG_ANALYTICS ], true ) ) {
            return self::PAGE_SLUG_ANALYTICS;
        }

        return $parent_file;
    }

    /**
     * Highlight the Overview submenu on the Analytics screen.
     *
     * @param string|null $submenu_file Submenu file.
     * @param string      $parent_file  Parent menu file.
     * @return string|null
     */
    public function highlight_analytics_submenu( $submenu_file, string $parent_file ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only menu routing.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
        if ( self::PAGE_SLUG_ANALYTICS === $parent_file && in_array( $page, [ self::PAGE_SLUG_ANALYTICS, self::SUBMENU_SLUG_ANALYTICS ], true ) ) {
            return self::SUBMENU_SLUG_ANALYTICS;
        }

        return $submenu_file;
    }

    /**
     * Legacy entry points exist for bookmarks; we redirect on load-{$hook}.
     *
     * @return void
     */
    public function render_legacy_redirect_placeholder(): void {
        // Intentionally empty.
    }

    public function redirect_legacy_builder(): void {
        // Safety guard: only redirect when on the legacy CPT screen.
        // Prevents accidental redirect loops if this callback is ever attached to
        // a canonical `toplevel_page_*` load hook by WordPress or another plugin.
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && 'hvnly_property_page_hvnly_property_builder' !== $screen->id ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- passthrough for legacy bookmarks only.
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';

        $target = add_query_arg(
            array_filter(
                array(
                    'page' => self::PAGE_SLUG_PROPERTY_BUILDER,
                    'tab'  => $tab ?: null,
                )
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $target, 301 );
        exit;
    }

    public function redirect_legacy_analytics(): void {
        // Safety guard: only redirect when on the legacy CPT screen.
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( $screen && 'hvnly_property_page_hvnly_property_reports_analytics' !== $screen->id ) {
            return;
        }

        $target = add_query_arg(
            array(
                'page' => self::SUBMENU_SLUG_ANALYTICS,
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $target, 301 );
        exit;
    }

    public function render_settings(): void
    {
        ?>
        <div id="HvnlyNab_admin_dashboard_wrap" class="HvnlyNab_admin_dashboard_wrap hvnly-admin-is-loading">
            <?php AdminPreloader::render( 'settings' ); ?>
            <div id="HvnlyNab_admin_dashboard" class="HvnlyNab_admin_dashboard"></div>
        </div>
        <?php
    }

    public function render_builder(): void
    {
        ?>
        <div id="HvnlyNab_property_builder_render" class="HvnlyNab_property_builder_render hvnly-admin-is-loading">
            <?php AdminPreloader::render( 'builder' ); ?>
            <div id="hvnly-builder-app-root" class="hvnly-builder-app-root"></div>
        </div>
        <?php
    }

    public function render_reports(): void
    {
        ?>
        <div id="HvnlyNab_reports_analytics_wrap" class="HvnlyNab_reports_analytics_wrap hvnly-admin-is-loading">
            <?php AdminPreloader::render( 'analytics' ); ?>
            <div id="HvnlyNab_reports_analytics_render" class="HvnlyNab_reports_analytics_render"></div>
        </div>
        <?php
    }

    public function builder_admin_body_class(string $classes): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if (
            $screen
            && in_array(
                $screen->id,
                [
                    'hvnly_property_page_hvnly_property_builder',
                    'hvnly_property_page_hvnly_property_settings',
                    'hvnly_property_page_hvnly_property_reports_analytics',
                    'toplevel_page_' . self::PAGE_SLUG_PROPERTY_BUILDER,
                    'toplevel_page_' . self::PAGE_SLUG_ANALYTICS,
                    self::HOOK_ANALYTICS_OVERVIEW,
                ],
                true
            )
        ) {
            $classes .= ' hvnly-admin-page-loading';
        }

        return $classes;
    }

    public function suppress_other_notices(\WP_Screen $screen): void
    {
        if (!$screen) return;

        if (in_array($screen->id, $this->get_plugin_screens(), true)) {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
            add_action('admin_notices', [$this, 'plugin_admin_notices']);
        }
    }

    private function get_plugin_screens(): array
    {
        $screens = ['edit-hvnly_property', 'hvnly_property'];
        foreach (get_object_taxonomies('hvnly_property', 'names') as $tax) {
            $screens[] = "edit-{$tax}";
            $screens[] = $tax;
        }
        $screens[] = 'hvnly_property_page_hvnly_property_settings';
        $screens[] = 'hvnly_property_page_hvnly-property-onboarding';
        $screens[] = 'hvnly_property_page_' . DocumentationPage::PAGE_SLUG;
        $screens[] = 'hvnly_property_page_hvnly_property_builder';
        $screens[] = 'hvnly_property_page_hvnly_property_reports_analytics';
        $screens[] = 'hvnly_property_page_hvnly_inquiries';
        $screens[] = 'toplevel_page_' . self::PAGE_SLUG_PROPERTY_BUILDER;
        $screens[] = 'toplevel_page_' . self::PAGE_SLUG_ANALYTICS;
        $screens[] = self::HOOK_ANALYTICS_OVERVIEW;
        $screens[] = 'toplevel_page_hvnly_inquiries';
        $screens[] = 'edit-hvnly_agent';
        $screens[] = 'hvnly_agent';
        $screens[] = 'edit-hvnly_agent_agency';
        $screens[] = 'hvnly_agent_agency';
        // Cache screen removed - now handled by CacheAdmin.php
        return $screens;
    }

    public function plugin_admin_notices(): void
    {
        Assets::render_missing_build_asset_notice();
    }
}