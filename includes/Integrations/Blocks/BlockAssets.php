<?php
/**
 * Frontend + editor asset loading for Havenlytics blocks.
 *
 * Archive / Search / Agents / Agency reuse the Elementor Bootstrap stack.
 * Featured / Carousel / Map add only premium shell CSS + controllers on top of
 * the same canonical Card Builder handles the archive already uses — never a
 * parallel copy of the same files.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Integrations\Elementor\Bootstrap;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * @since 3.5.0
 */
final class BlockAssets {

    private const PROPERTY_BLOCKS = array(
        'havenlytics/property-archive',
        'havenlytics/property-search',
        // Featured reuses archive/property-loop.php — needs the same archive JS
        // (grid layout chrome + in-card gallery init) as Search/Archive.
        'havenlytics/featured-properties',
        // Carousel slides are Card Builder cards too — without the archive JS
        // the in-card gallery never initializes (dead prev/next arrows).
        'havenlytics/property-carousel',
        // Saved Properties renders the SAME Card Builder cards (grid/list/compact)
        // and reuses the favorite toggle + in-card gallery + image un-blur JS.
        'havenlytics/saved-properties',
    );

    private const AGENTS_BLOCK = 'havenlytics/agents';
    private const AGENCY_BLOCK = 'havenlytics/agency';

    private const FEATURED_BLOCK  = 'havenlytics/featured-properties';
    private const CAROUSEL_BLOCK  = 'havenlytics/property-carousel';
    private const MAP_BLOCK       = 'havenlytics/property-map';
    private const AUTH_BLOCK      = 'havenlytics/authentication';
    private const DASHBOARD_BLOCK = 'havenlytics/dashboard';
    private const SAVED_BLOCK     = 'havenlytics/saved-properties';
    private const INQUIRY_BLOCK   = 'havenlytics/property-inquiry';

    /**
     * Canonical Card Builder CSS handles (same as archive / Elementor).
     * Do not register parallel handles for these files.
     */
    private const CARD_BUILDER_STYLES = array(
        'hvnly-fontawesome-all-frontend',
        'hvnly-frontend-default',
        'hvnly-frontend-components',
        'hvnly-frontend-cards',
        'hvnly-frontend-property-card-embed',
        'hvnly-frontend-property-responsive',
    );

    /**
     * @var self|null
     */
    private static $instance = null;

    /**
     * @var bool
     */
    private $handles_registered = false;

    /**
     * @return self
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array( $this, 'enqueue_frontend' ), 20);
        add_action('enqueue_block_assets', array( $this, 'enqueue_editor' ));
    }

    /**
     * Register premium-only handles (shell CSS + controllers).
     * Card Builder files are registered via Bootstrap / Assets — not here.
     *
     * @return void
     */
    private function register_handles(): void {
        if ($this->handles_registered) {
            return;
        }
        $this->handles_registered = true;

        $front = trailingslashit(HVNLYNAB_URL) . 'assets/frontend/';
        $ver   = HVNLYNAB_VERSION;

        $this->ensure_card_builder_handles_registered();

        // Premium shell only (no Card Builder deps — callers enqueue the
        // canonical card stack first when cards are present, so map-only
        // pages do not pull embed CSS).
        wp_register_style(
            'hvnly-block-premium',
            $front . 'blocks/css/hvnly-blocks.css',
            array(),
            self::file_version('frontend/blocks/css/hvnly-blocks.css', $ver)
        );

        // filemtime cache-bust: the controllers changed within a plugin
        // version, and browsers holding ?ver=3.5.0 kept executing the OLD
        // file (stale pointer-capture bug). mtime makes every on-disk change
        // reach the browser immediately.
        wp_register_script(
            'hvnly-block-carousel',
            $front . 'blocks/js/hvnly-block-carousel.js',
            array(),
            self::file_version('frontend/blocks/js/hvnly-block-carousel.js', $ver),
            true
        );
        wp_localize_script(
            'hvnly-block-carousel',
            'hvnly_block_carousel',
            array(
                'i18n' => array(
                    'goToSlide' =>
                        /* translators: %d: Slide number. */
                        __( 'Go to slide %d', 'havenlytics' ),
                ),
            )
        );

        // Authentication block — dedicated shell CSS + controller. Reuses the
        // existing SessionAuthController AJAX endpoints; no card stack needed.
        wp_register_style(
            'hvnly-block-auth',
            $front . 'blocks/css/hvnly-block-auth.css',
            array( 'hvnly-frontend-components' ),
            self::file_version('frontend/blocks/css/hvnly-block-auth.css', $ver)
        );
        wp_register_script(
            'hvnly-block-auth',
            $front . 'blocks/js/hvnly-block-auth.js',
            array(),
            self::file_version('frontend/blocks/js/hvnly-block-auth.js', $ver),
            true
        );

        // Dashboard block chrome (gate + editor panel). The live dashboard is the
        // existing Workspace SPA, enqueued by the existing Workspace pipeline.
        wp_register_style(
            'hvnly-block-dashboard',
            $front . 'blocks/css/hvnly-block-dashboard.css',
            array(),
            self::file_version('frontend/blocks/css/hvnly-block-dashboard.css', $ver)
        );

        // Saved Properties block chrome (header / empty state / gate / pager).
        // The cards themselves use the canonical Card Builder handles above.
        wp_register_style(
            'hvnly-block-saved-properties',
            $front . 'blocks/css/hvnly-block-saved-properties.css',
            array(),
            self::file_version('frontend/blocks/css/hvnly-block-saved-properties.css', $ver)
        );

        // Property Inquiry Form block chrome. Form field styles come from the
        // existing hvnly-frontend-contact-agent stylesheet; agent header uses cards.
        if ( ! wp_style_is('hvnly-frontend-contact-agent', 'registered')) {
            wp_register_style(
                'hvnly-frontend-contact-agent',
                HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-contact-agent.css',
                array(),
                $ver
            );
        }
        if ( ! wp_script_is('hvnly-frontend-contact-agent', 'registered')) {
            wp_register_script(
                'hvnly-frontend-contact-agent',
                HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-contact-agent.js',
                array( 'jquery' ),
                $ver,
                true
            );
        }
        if ( ! wp_style_is('hvnly-frontend-cards', 'registered')) {
            wp_register_style(
                'hvnly-frontend-cards',
                HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-cards.css',
                array(),
                $ver
            );
        }
        wp_register_style(
            'hvnly-block-inquiry',
            $front . 'blocks/css/hvnly-block-inquiry.css',
            array( 'hvnly-frontend-contact-agent', 'hvnly-frontend-cards', 'hvnly-frontend-components' ),
            self::file_version('frontend/blocks/css/hvnly-block-inquiry.css', $ver)
        );
        wp_register_script(
            'hvnly-block-inquiry',
            $front . 'blocks/js/hvnly-block-inquiry.js',
            array( 'hvnly-frontend-contact-agent' ),
            self::file_version('frontend/blocks/js/hvnly-block-inquiry.js', $ver),
            true
        );

        wp_register_style('hvnly-block-leaflet', $front . 'lib/leaflet/css/leaflet.css', array(), $ver);
        wp_register_script('hvnly-block-leaflet', $front . 'lib/leaflet/js/leaflet.js', array(), $ver, true);
        wp_register_style('hvnly-block-leaflet-cluster', $front . 'lib/leaflet/css/MarkerCluster.css', array( 'hvnly-block-leaflet' ), $ver);
        wp_register_style('hvnly-block-leaflet-cluster-default', $front . 'lib/leaflet/css/MarkerCluster.Default.css', array( 'hvnly-block-leaflet-cluster' ), $ver);
        wp_register_script('hvnly-block-leaflet-cluster', $front . 'lib/leaflet/js/leaflet.markercluster.js', array( 'hvnly-block-leaflet' ), $ver, true);

        // Deps follow the GLOBAL map provider (archive-map SSOT: hvnly_get_map_config).
        $map_deps = 'google' === self::effective_map_provider()
            ? array( self::register_google_maps_handle() )
            : array( 'hvnly-block-leaflet', 'hvnly-block-leaflet-cluster' );

        wp_register_script(
            'hvnly-block-map',
            $front . 'blocks/js/hvnly-block-map.js',
            array_filter($map_deps),
            self::file_version('frontend/blocks/js/hvnly-block-map.js', $ver),
            true
        );
    }

    /**
     * Version string that changes whenever the file on disk changes.
     *
     * @param string $relative Path relative to the assets dir.
     * @param string $base     Base plugin version.
     * @return string
     */
    private static function file_version( string $relative, string $base ): string {
        $path  = trailingslashit(HVNLYNAB_ASSETS_PATH) . $relative;
        $mtime = is_readable($path) ? (int) filemtime($path) : 0;

        return $mtime > 0 ? $base . '.' . $mtime : $base;
    }

    /**
     * Effective global map provider (google falls back to leaflet without a
     * key — same rule the Archive Map applies via hvnly_get_map_config()).
     *
     * @return string leaflet|openstreetmap|google
     */
    public static function effective_map_provider(): string {
        if (function_exists('hvnly_get_map_config')) {
            $config   = hvnly_get_map_config();
            $provider = isset($config['provider']) ? (string) $config['provider'] : 'leaflet';

            return in_array($provider, array( 'leaflet', 'openstreetmap', 'google' ), true) ? $provider : 'leaflet';
        }

        return 'leaflet';
    }

    /**
     * Register the SAME Google Maps handle the Archive Map uses (identical URL
     * + ready callback), only when the archive has not registered it already —
     * one API load per page regardless of which surface asked first.
     *
     * @return string Registered handle ('' when no API key).
     */
    private static function register_google_maps_handle(): string {
        if (wp_script_is('hvnly-google-maps', 'registered') || wp_script_is('hvnly-google-maps', 'enqueued')) {
            return 'hvnly-google-maps';
        }

        $api_key = function_exists('hvnly_get_google_maps_api_key') ? hvnly_get_google_maps_api_key() : '';
        if ('' === $api_key) {
            return '';
        }

        wp_register_script(
            'hvnly-google-maps',
            'https://maps.googleapis.com/maps/api/js?key=' . urlencode($api_key) . '&libraries=places&loading=async&callback=initHvnlyMap',
            array(),
            HVNLYNAB_VERSION,
            array( 'strategy' => 'async' )
        );

        // Same ready bridge as the Archive Map (Assets.php) — consumers listen
        // for hvnlyGoogleMapsLoaded.
        wp_add_inline_script('hvnly-google-maps', '
            function initHvnlyMap() {
                window.dispatchEvent(new Event("hvnlyGoogleMapsLoaded"));
                if (window.HavenlyticsPropertyMap && typeof window.HavenlyticsPropertyMap.onGoogleMapsReady === "function") {
                    window.HavenlyticsPropertyMap.onGoogleMapsReady();
                }
            }
        ');

        return 'hvnly-google-maps';
    }

    /**
     * Register canonical Card Builder style handles if not already present.
     *
     * @return void
     */
    private function ensure_card_builder_handles_registered(): void {
        if (class_exists(Bootstrap::class)) {
            Bootstrap::get_instance()->register_widget_style_handles();
        }

        $ver   = HVNLYNAB_VERSION;
        $front = HVNLYNAB_ASSETS_URL . '/frontend';
        $admin = HVNLYNAB_ASSETS_URL . '/admin';

        if ( ! wp_style_is('hvnly-fontawesome-all-frontend', 'registered')) {
            wp_register_style(
                'hvnly-fontawesome-all-frontend',
                $admin . '/css/fontawesome-all.min.css',
                array(),
                $ver
            );
        }

        if ( ! wp_style_is('hvnly-frontend-default', 'registered')) {
            wp_register_style(
                'hvnly-frontend-default',
                $front . '/css/hvnly-frontend-default.css',
                array(),
                $ver
            );
        }

        if ( ! wp_style_is('hvnly-frontend-components', 'registered')) {
            wp_register_style(
                'hvnly-frontend-components',
                $front . '/css/hvnly-frontend-components.css',
                array( 'hvnly-frontend-default' ),
                $ver
            );
        }

        if ( ! wp_style_is('hvnly-frontend-cards', 'registered')) {
            wp_register_style(
                'hvnly-frontend-cards',
                $front . '/css/hvnly-frontend-cards.css',
                array( 'hvnly-frontend-default', 'hvnly-frontend-components' ),
                $ver
            );
        }

        if ( ! wp_style_is('hvnly-frontend-property-card-embed', 'registered')) {
            wp_register_style(
                'hvnly-frontend-property-card-embed',
                $front . '/css/property-cards/hvnly-frontend-property-card-embed.css',
                array( 'hvnly-frontend-default', 'hvnly-frontend-components' ),
                $ver
            );
        }

        if ( ! wp_style_is('hvnly-frontend-property-responsive', 'registered')) {
            wp_register_style(
                'hvnly-frontend-property-responsive',
                $front . '/css/hvnly-frontend-property-responsive.css',
                array( 'hvnly-frontend-property-card-embed' ),
                $ver
            );
        }
    }

    /**
     * Enqueue the archive Card Builder stack once (idempotent).
     *
     * @return void
     */
    private function enqueue_card_builder_styles(): void {
        $this->ensure_card_builder_handles_registered();

        foreach (self::CARD_BUILDER_STYLES as $handle) {
            wp_enqueue_style($handle);
        }

        add_filter('hvnly_favorites_should_enqueue', '__return_true');
        add_filter('hvnly_compare_should_enqueue', '__return_true');
        if (class_exists(\HvnlyNab\Favorites\FavoritesAssets::class)) {
            \HvnlyNab\Favorites\FavoritesAssets::ensure_enqueued();
        }
    }

    /**
     * Frontend enqueue.
     *
     * @return void
     */
    public function enqueue_frontend(): void {
        if (is_admin()) {
            return;
        }

        $post = get_post();

        if ( ! $post instanceof \WP_Post) {
            return;
        }

        $this->register_handles();

        $has_bootstrap = class_exists(Bootstrap::class);

        if ($has_bootstrap && $this->post_has_any_block($post, self::PROPERTY_BLOCKS)) {
            Bootstrap::get_instance()->enqueue_property_widget_assets_for_render();
        }
        if ($has_bootstrap && has_block(self::AGENTS_BLOCK, $post)) {
            Bootstrap::get_instance()->enqueue_agent_widget_assets_for_render();
        }
        if ($has_bootstrap && has_block(self::AGENCY_BLOCK, $post)) {
            Bootstrap::get_instance()->enqueue_agency_widget_assets_for_render();
        }

        $has_featured  = has_block(self::FEATURED_BLOCK, $post);
        $has_carousel  = has_block(self::CAROUSEL_BLOCK, $post);
        $has_map       = has_block(self::MAP_BLOCK, $post);
        $has_auth      = has_block(self::AUTH_BLOCK, $post);
        $has_dashboard = has_block(self::DASHBOARD_BLOCK, $post);
        $has_saved     = has_block(self::SAVED_BLOCK, $post);
        $has_inquiry   = has_block(self::INQUIRY_BLOCK, $post);

        if ($has_featured || $has_carousel) {
            $this->enqueue_card_builder_styles();
            wp_enqueue_style('hvnly-block-premium');
            wp_enqueue_script('hvnly-block-carousel');
        }

        if ($has_map) {
            // Map has no property cards; fav + premium shell + provider stack.
            // Provider CSS FIRST so the premium shell wins cascade-order ties
            // against Leaflet's element rules (e.g. .leaflet-container a).
            add_filter('hvnly_favorites_should_enqueue', '__return_true');
            if (class_exists(\HvnlyNab\Favorites\FavoritesAssets::class)) {
                \HvnlyNab\Favorites\FavoritesAssets::ensure_enqueued();
            }
            if ('google' !== self::effective_map_provider()) {
                wp_enqueue_style('hvnly-block-leaflet');
                wp_enqueue_style('hvnly-block-leaflet-cluster');
                wp_enqueue_style('hvnly-block-leaflet-cluster-default');
                wp_enqueue_script('hvnly-block-leaflet-cluster');
            }
            wp_enqueue_style('hvnly-block-premium');
            wp_enqueue_script('hvnly-block-map'); // deps pull leaflet OR google
        }

        if ($has_auth) {
            $this->enqueue_auth_assets();
        }

        if ($has_dashboard) {
            // Block chrome (gate + editor panel). The live SPA is enqueued by the
            // existing Workspace pipeline via DashboardBlockSupport.
            wp_enqueue_style('hvnly-block-dashboard');

            // Signed-out visitors get the Authentication block inside the gate, so
            // its controller + styles must be present too.
            if ( ! is_user_logged_in()) {
                $this->enqueue_auth_assets();
            }
        }

        if ($has_saved) {
            // Cards + favorite toggle + un-blur JS already loaded via
            // PROPERTY_BLOCKS above. Add the block's own chrome, plus the premium
            // shell + carousel controller for the optional carousel layout (inert
            // on grid/list — it only binds [data-hvnly-block-carousel]).
            wp_enqueue_style('hvnly-block-saved-properties');
            wp_enqueue_style('hvnly-block-premium');
            wp_enqueue_script('hvnly-block-carousel');

            // Signed-out visitors get the reused Authentication block gate.
            if ( ! is_user_logged_in()) {
                $this->enqueue_auth_assets();
            }
        }

        if ($has_inquiry) {
            $this->enqueue_inquiry_assets();
        }
    }

    /**
     * Enqueue the existing Contact Agent stack + inquiry block chrome.
     *
     * @return void
     */
    private function enqueue_inquiry_assets(): void {
        $ver = HVNLYNAB_VERSION;

        if ( ! wp_style_is('hvnly-frontend-contact-agent', 'registered')) {
            wp_register_style(
                'hvnly-frontend-contact-agent',
                HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-contact-agent.css',
                array(),
                $ver
            );
        }

        if ( ! wp_script_is('hvnly-frontend-contact-agent', 'registered')) {
            wp_register_script(
                'hvnly-frontend-contact-agent',
                HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-contact-agent.js',
                array( 'jquery' ),
                $ver,
                true
            );
        }

        // Agent card partial needs the shared cards stylesheet.
        if ( ! wp_style_is('hvnly-frontend-cards', 'registered')) {
            wp_register_style(
                'hvnly-frontend-cards',
                HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-cards.css',
                array(),
                $ver
            );
        }
        wp_enqueue_style('hvnly-frontend-cards');
        wp_enqueue_style('hvnly-frontend-contact-agent');
        wp_enqueue_style('hvnly-block-inquiry');
        wp_enqueue_script('hvnly-frontend-contact-agent');

        if ( ! wp_script_is('hvnly-block-inquiry', 'registered')) {
            wp_register_script(
                'hvnly-block-inquiry',
                HVNLYNAB_ASSETS_URL . '/frontend/blocks/js/hvnly-block-inquiry.js',
                array( 'hvnly-frontend-contact-agent' ),
                self::file_version('frontend/blocks/js/hvnly-block-inquiry.js', $ver),
                true
            );
        }
        wp_enqueue_script('hvnly-block-inquiry');

        if (function_exists('hvnly_localize_contact_agent_script')) {
            hvnly_localize_contact_agent_script(0);
        }
    }

    /**
     * Enqueue + localize the Authentication block controller.
     *
     * The block posts to the existing SessionAuthController AJAX actions, so the
     * localized config carries only the ajax URL, fresh per-action nonces, the
     * action names and UI strings — all sourced from the existing controller.
     *
     * @return void
     */
    private function enqueue_auth_assets(): void {
        wp_enqueue_style('hvnly-block-auth');
        wp_enqueue_script('hvnly-block-auth');

        if (class_exists(AuthBlockSupport::class)) {
            wp_localize_script(
                'hvnly-block-auth',
                'HvnlyAuthBlock',
                AuthBlockSupport::localize_data()
            );
        }
    }

    /**
     * Editor enqueue — one Bootstrap stack, one Card Builder stack, one premium shell.
     *
     * @return void
     */
    public function enqueue_editor(): void {
        if ( ! is_admin()) {
            return;
        }

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen && method_exists($screen, 'is_block_editor') && ! $screen->is_block_editor()) {
                return;
            }
        }

        $post = get_post();

        if ( ! $post instanceof \WP_Post) {
            return;
        }

        $this->register_handles();

        // Archive / Search / Agents / Agency + Card Builder (canonical handles).
        if (class_exists(Bootstrap::class)) {
            Bootstrap::get_instance()->enqueue_blocks_editor_styles();
        }

        // Ensure Card Builder is present even if Bootstrap registration order
        // skipped embed (e.g. edge cases). wp_enqueue_style is idempotent.
        $this->enqueue_card_builder_styles();

        // Provider CSS before the premium shell (cascade-order ties must go to
        // Havenlytics), then the shell once — no parallel card CSS copies.
        // The editor follows the SAME global provider as the frontend.
        if ('google' !== self::effective_map_provider()) {
            wp_enqueue_style('hvnly-block-leaflet');
            wp_enqueue_style('hvnly-block-leaflet-cluster');
            wp_enqueue_style('hvnly-block-leaflet-cluster-default');
            wp_enqueue_script('hvnly-block-leaflet');
            wp_enqueue_script('hvnly-block-leaflet-cluster');
        }
        wp_enqueue_style('hvnly-block-premium');
        wp_enqueue_style('hvnly-block-auth');
        wp_enqueue_style('hvnly-block-dashboard');
        wp_enqueue_style('hvnly-block-saved-properties');
        $this->enqueue_inquiry_assets();
        wp_enqueue_script('hvnly-block-map'); // deps pull leaflet OR google
        wp_enqueue_script('hvnly-block-carousel');
    }

    /**
     * @param \WP_Post $post  Post to inspect.
     * @param string[] $names Block names.
     * @return bool
     */
    private function post_has_any_block( \WP_Post $post, array $names ): bool {
        foreach ($names as $name) {
            if (has_block($name, $post)) {
                return true;
            }
        }

        return false;
    }
}
