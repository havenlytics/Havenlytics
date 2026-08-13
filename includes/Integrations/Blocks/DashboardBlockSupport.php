<?php
/**
 * Support wiring for the HVN: Dashboard block.
 *
 * Purely additive. The block renders the EXISTING Agent Workspace SPA through the
 * existing `[hvnly_agent_dashboard]` shortcode + WorkspaceAssets pipeline. This
 * class only:
 *
 * 1. Triggers the existing Workspace asset enqueue (proper `wp_enqueue_scripts`
 *    timing) when a page contains the Dashboard block — via the existing
 *    `hvnly_workspace_should_enqueue` filter. No assets are duplicated.
 *
 * 2. When the block is embedded on a page that is NOT the canonical Workspace
 *    page, switches the SPA router to HASH mode for that request only (via the
 *    existing `hvnly_workspace_localize_data` filter) so the embedded app routes
 *    within the host page instead of rewriting the address bar to
 *    /agent-dashboard/*. The real Workspace page is never affected.
 *
 * 3. Optionally sets the SPA's initial view from the block's "Default section"
 *    attribute, using the SPA's own URL-hash routing (an inline `before` script
 *    on the existing SPA handle). No SPA code is modified.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Workspace\WorkspaceAvailability;
use HvnlyNab\Workspace\WorkspaceBootstrap;
use HvnlyNab\Workspace\WorkspaceConstants;
use HvnlyNab\Workspace\WorkspaceSettings;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * @since 3.5.0
 */
final class DashboardBlockSupport {

    public const BLOCK_NAME = 'havenlytics/dashboard';

    /**
     * SPA view slugs the block may target as an initial view.
     *
     * @var string[]
     */
    private const VIEW_SLUGS = array(
        'dashboard',
        'properties',
        'saved-properties',
        'inquiries',
        'profile',
        'analytics',
        'notifications',
        'settings',
    );

    /**
     * Whether the SPA is embedded off the canonical Workspace page this request.
     *
     * @var bool
     */
    private static $embedding = false;

    /**
     * Initial SPA view requested by the block instance.
     *
     * @var string
     */
    private static $default_section = 'dashboard';

    /**
     * Whether the existing full-bleed canvas template was applied this request.
     *
     * @var bool
     */
    private static $full_screen = false;

    /**
     * Register additive hooks (idempotent).
     *
     * @return void
     */
    public static function register(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        add_action('wp_enqueue_scripts', array( self::class, 'prepare' ), 9);
        add_filter('hvnly_workspace_localize_data', array( self::class, 'filter_localize' ), 20);

        // Full-bleed parity with the real Workspace: serve the EXISTING canvas
        // template for a page hosting the Dashboard block. Priority 100 runs after
        // WorkspacePage's own canvas filter (99) and never overrides the real
        // Workspace page (guarded below), so the two pipelines never collide.
        add_filter('template_include', array( self::class, 'filter_template' ), 100);
        add_filter('body_class', array( self::class, 'add_body_class' ));
    }

    /**
     * Serve the existing Workspace canvas template for a Dashboard-block page.
     *
     * This is the SAME `template_include` mechanism + the SAME canvas.php the
     * Workspace page uses ({@see \HvnlyNab\Workspace\WorkspacePage::maybe_use_canvas_template()}).
     * It removes the theme header/footer/container so the block renders full-bleed,
     * pixel-identical to the standalone Workspace — without duplicating or altering
     * that pipeline. Opt-out per block via the "Full screen" toggle.
     *
     * @param string $template Resolved template path.
     * @return string
     */
    public static function filter_template( string $template ): string {
        if (is_admin() || ! is_singular()) {
            return $template;
        }

        $post = get_post();
        if ( ! $post instanceof \WP_Post || ! has_block(self::BLOCK_NAME, $post)) {
            return $template;
        }

        // The canonical Workspace page already swaps to canvas via its own filter.
        if (self::is_workspace_page($post)) {
            return $template;
        }

        if ( ! self::read_full_screen($post)) {
            return $template;
        }

        if ( ! class_exists(WorkspaceBootstrap::class)) {
            return $template;
        }
        $loader = WorkspaceBootstrap::get_instance()->get_templates();
        if (null === $loader) {
            return $template;
        }

        $canvas = $loader->locate('canvas');
        if ($canvas !== '' && is_readable($canvas)) {
            self::$full_screen = true;

            return $canvas;
        }

        return $template;
    }

    /**
     * Mirror the Workspace page body classes when the canvas is applied, so any
     * shell CSS keyed off the body behaves identically.
     *
     * @param string[] $classes Body classes.
     * @return string[]
     */
    public static function add_body_class( array $classes ): array {
        if (self::$full_screen) {
            $classes[] = 'hvnly-ws-page';
            $classes[] = 'hvnly-ws-page--block';
        }

        return $classes;
    }

    /**
     * Prepare the existing Workspace enqueue when the page hosts the block.
     *
     * @return void
     */
    public static function prepare(): void {
        if (is_admin()) {
            return;
        }

        $post = get_post();
        if ( ! $post instanceof \WP_Post) {
            return;
        }
        if ( ! has_block(self::BLOCK_NAME, $post)) {
            return;
        }

        // Logged-out visitors get the block's sign-in gate — the SPA never mounts,
        // so there is nothing to enqueue here.
        if ( ! is_user_logged_in()) {
            return;
        }

        // Let the existing pipeline enqueue exactly as it does for the shortcode /
        // Workspace page (CSS-only when Workspace is disabled → unavailable page).
        add_filter('hvnly_workspace_should_enqueue', '__return_true');

        $available = class_exists(WorkspaceAvailability::class) && WorkspaceAvailability::is_available();
        if ( ! $available) {
            return;
        }

        self::$default_section = self::read_default_section($post);
        self::$embedding       = ! self::is_workspace_page($post);

        if (self::$embedding) {
            add_action('hvnly_workspace_assets_enqueued', array( self::class, 'add_embedded_routing_script' ));
        }
    }

    /**
     * Embed-only: switch the SPA router to hash mode so it stays on the host page.
     *
     * Runs for every Workspace localize, but only alters data when THIS request is
     * an embedded Dashboard block (flag set in prepare()). The canonical Workspace
     * page never sets the flag, so its clean routing is untouched.
     *
     * @param array<string, mixed> $data Localized boot data.
     * @return array<string, mixed>
     */
    public static function filter_localize( array $data ): array {
        if ( ! self::$embedding) {
            return $data;
        }

        if ( ! isset($data['routing']) || ! is_array($data['routing'])) {
            $data['routing'] = array();
        }
        $data['routing']['clean'] = false;

        $path = wp_parse_url(home_url(add_query_arg(array())), PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $data['routing']['basePath'] = trailingslashit($path);
        }

        // Forward-facing config for future SPA support (never required today).
        $data['dashboardBlock'] = array(
            'embedded'       => true,
            'defaultSection' => self::$default_section,
        );

        return $data;
    }

    /**
     * Embed-only: keep the browser URL frozen and seed the initial view.
     *
     * WHY: the SPA writes every route to the address bar via
     * `history.pushState`/`replaceState` (see Navigation/history.js — writeViewToHistory,
     * writePropertyFormRoute, cleanWorkspaceUrl, migrateLegacyHashUrl). Nav items are
     * <button onClick={navigate}> and `navigate()` updates React state directly and
     * independently of the URL, so intercepting those two history methods freezes the
     * URL (no `#/dashboard`, no `/route`) while navigation, refresh and the shell keep
     * working. The initial view is seeded through `history.state.hvnlyWsView` — which
     * `readViewIdFromLocation()` reads first — so it needs no URL segment.
     *
     * Printed immediately BEFORE the SPA bundle and ONLY for embedded blocks, so the
     * standalone Workspace page (which never sets the embedding flag) keeps its full
     * History-API routing, deep links, refresh and browser history untouched.
     *
     * @return void
     */
    public static function add_embedded_routing_script(): void {
        if ( ! self::$embedding) {
            return;
        }
        if ( ! wp_script_is(WorkspaceConstants::SCRIPT_HANDLE, 'enqueued')) {
            return;
        }

        $view    = self::$default_section !== '' ? self::$default_section : 'dashboard';
        $view_js = wp_json_encode($view);

        $js = <<<JS
(function(){try{
	if(!window.history||typeof window.history.pushState!=='function'){return;}
	var u=new URL(window.location.href);u.hash='';u.searchParams.delete('hvnly_ws_auth');
	var frozen=u.pathname+(u.search||'');
	var origPush=window.history.pushState.bind(window.history);
	var origReplace=window.history.replaceState.bind(window.history);
	// Freeze the address bar: keep the SPA's state object, ignore its route URL.
	window.history.pushState=function(state,title){return origPush(state,title,frozen);};
	window.history.replaceState=function(state,title){return origReplace(state,title,frozen);};
	// Seed the initial view without touching the URL.
	var v=$view_js;
	if(v){origReplace(Object.assign({},window.history.state||{},{hvnlyWsView:v}),'',frozen);}
}catch(e){}})();
JS;

        wp_add_inline_script(WorkspaceConstants::SCRIPT_HANDLE, $js, 'before');
    }

    /**
     * Read the first Dashboard block's default section attribute from the post.
     *
     * @param \WP_Post $post Post being rendered.
     * @return string A valid view slug (defaults to 'dashboard').
     */
    private static function read_default_section( \WP_Post $post ): string {
        if ( ! function_exists('parse_blocks')) {
            return 'dashboard';
        }

        $found = self::find_block(parse_blocks($post->post_content));
        if (null === $found) {
            return 'dashboard';
        }

        $attrs   = isset($found['attrs']) && is_array($found['attrs']) ? $found['attrs'] : array();
        $section = isset($attrs['defaultSection']) ? (string) $attrs['defaultSection'] : 'dashboard';

        return in_array($section, self::VIEW_SLUGS, true) ? $section : 'dashboard';
    }

    /**
     * Whether the first Dashboard block requests full-screen (default true).
     *
     * @param \WP_Post $post Post being rendered.
     * @return bool
     */
    private static function read_full_screen( \WP_Post $post ): bool {
        if ( ! function_exists('parse_blocks')) {
            return true;
        }

        $found = self::find_block(parse_blocks($post->post_content));
        if (null === $found) {
            return true;
        }

        $attrs = isset($found['attrs']) && is_array($found['attrs']) ? $found['attrs'] : array();

        // Absent attribute → block.json default (true).
        return ! array_key_exists('fullScreen', $attrs) || ! empty($attrs['fullScreen']);
    }

    /**
     * Depth-first search for the first Dashboard block.
     *
     * @param array<int, array<string, mixed>> $blocks Parsed blocks.
     * @return array<string, mixed>|null
     */
    private static function find_block( array $blocks ): ?array {
        foreach ($blocks as $block) {
            if (isset($block['blockName']) && self::BLOCK_NAME === $block['blockName']) {
                return $block;
            }
            if ( ! empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                $nested = self::find_block($block['innerBlocks']);
                if (null !== $nested) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * Whether the given post IS the canonical Workspace page.
     *
     * @param \WP_Post $post Post.
     * @return bool
     */
    private static function is_workspace_page( \WP_Post $post ): bool {
        $page_id = absint(get_option(WorkspaceConstants::PAGE_ID_OPTION, 0));
        if ($page_id > 0 && (int) $post->ID === $page_id) {
            return true;
        }

        $slug = class_exists(WorkspaceSettings::class) ? WorkspaceSettings::get_page_slug() : '';

        return $slug !== '' && $post->post_name === $slug;
    }
}
