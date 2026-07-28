<?php
/**
 * Gutenberg block registration for Havenlytics.
 *
 * Registers native block-editor blocks that reuse the existing Havenlytics
 * rendering, query, template and caching layers. Blocks are an additional UI
 * layer only — they do not duplicate Elementor widget or shortcode logic.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\I18n\ScriptTranslations;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Block Registrar.
 *
 * Singleton that registers all Havenlytics blocks on `init`, adds the
 * "Havenlytics" block category to the post editor, and registers the shared
 * editor script handle each block references from its block.json.
 *
 * @since 3.5.0
 */
final class BlockRegistrar {

    /**
     * Block category slug (shared with the Elementor integration).
     */
    public const CATEGORY = 'havenlytics';

    /**
     * Shared editor script handle referenced by every block.json `editorScript`.
     */
    private const EDITOR_HANDLE = 'hvnly-blocks-editor';

    /**
     * Render callbacks keyed by block name (from block.json `name`).
     *
     * Keyed by name rather than directory so registration works regardless of
     * where the build tool emits each block.json.
     *
     * @var array<string, callable>
     */
    private const RENDERERS = [
        'havenlytics/property-archive'    => [PropertyArchiveBlockRenderer::class, 'render'],
        'havenlytics/property-search'     => [PropertySearchBlockRenderer::class, 'render'],
        'havenlytics/agents'              => [AgentsBlockRenderer::class, 'render'],
        'havenlytics/agency'              => [AgencyBlockRenderer::class, 'render'],
        'havenlytics/featured-properties' => [FeaturedPropertiesBlockRenderer::class, 'render'],
        'havenlytics/property-carousel'   => [PropertyCarouselBlockRenderer::class, 'render'],
        'havenlytics/property-map'        => [PropertyMapBlockRenderer::class, 'render'],
        'havenlytics/authentication'      => [AuthenticationBlockRenderer::class, 'render'],
        'havenlytics/dashboard'           => [DashboardBlockRenderer::class, 'render'],
        'havenlytics/saved-properties'    => [SavedPropertiesBlockRenderer::class, 'render'],
        'havenlytics/property-inquiry'    => [PropertyInquiryBlockRenderer::class, 'render'],
    ];

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Absolute path to the block source root (build output).
     *
     * @var string
     */
    private $build_dir;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor — private for singleton.
     */
    private function __construct() {
        $this->build_dir = trailingslashit(HVNLYNAB_PATH) . 'build/blocks/';

        add_filter('block_categories_all', [$this, 'register_category'], 10, 1);
        add_filter('allowed_block_types_all', [$this, 'filter_allowed_block_types_for_widgets'], 10, 2);
        add_action('init', [$this, 'register_blocks']);

        // Frontend + editor asset loading (reuses the existing pipeline).
        BlockAssets::get_instance();

        // Additive support for the Authentication block (first/last name capture).
        AuthBlockSupport::register();

        // Additive support for the Dashboard block (reuses the Workspace SPA).
        DashboardBlockSupport::register();

        // Editor-only rich pickers for Property Inquiry (no Inquiry backend changes).
        InquiryBlockEditorSupport::register();
    }

    /**
     * Registered Havenlytics block names (from block.json `name`).
     *
     * @return array<int, string>
     */
    public static function get_block_names(): array {
        return array_keys(self::RENDERERS);
    }

    /**
     * Hide Havenlytics Gutenberg blocks from the Widgets / Customizer widget editors.
     *
     * Official WordPress approach via `allowed_block_types_all` + editor context name
     * (`core/edit-widgets`, `core/customize-widgets`). Post, page, and Site Editor
     * contexts are unchanged. Classic `WP_Widget` sidebar widgets are unaffected.
     *
     * @param bool|string[]               $allowed_block_types  Allowed types, or true for all.
     * @param \WP_Block_Editor_Context    $editor_context       Current editor context.
     * @return bool|string[]
     */
    public function filter_allowed_block_types_for_widgets($allowed_block_types, $editor_context) {
        if (!is_object($editor_context) || empty($editor_context->name)) {
            return $allowed_block_types;
        }

        $widget_contexts = ['core/edit-widgets', 'core/customize-widgets'];
        if (!in_array((string) $editor_context->name, $widget_contexts, true)) {
            return $allowed_block_types;
        }

        $hvnly_blocks = self::get_block_names();
        if ($hvnly_blocks === []) {
            return $allowed_block_types;
        }

        // Default is true (all registered blocks). Convert to an explicit list, then remove ours.
        if (true === $allowed_block_types) {
            $allowed_block_types = array_keys(\WP_Block_Type_Registry::get_instance()->get_all_registered());
        }

        if (!is_array($allowed_block_types)) {
            return $allowed_block_types;
        }

        return array_values(array_diff($allowed_block_types, $hvnly_blocks));
    }

    /**
     * Add the "Havenlytics" category to the block inserter.
     *
     * Mirrors the Elementor "Havenlytics" category so blocks and widgets group
     * consistently. Only adds the category once.
     *
     * @param array $categories Existing block categories.
     * @return array
     */
    public function register_category(array $categories): array {
        foreach ($categories as $category) {
            if (isset($category['slug']) && $category['slug'] === self::CATEGORY) {
                return $categories;
            }
        }

        array_unshift(
            $categories,
            [
                'slug'  => self::CATEGORY,
                'title' => __('Havenlytics', 'havenlytics'),
                'icon'  => null,
            ]
        );

        return $categories;
    }

    /**
     * Register all Havenlytics blocks.
     *
     * Registers the shared editor script handle from the compiled asset
     * manifest, then registers each block from its block.json with a PHP
     * render callback (dynamic blocks — markup is produced server-side by the
     * existing template system).
     *
     * @return void
     */
    public function register_blocks(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        $this->register_editor_script();

        $registry = \WP_Block_Type_Registry::get_instance();

        foreach ($this->discover_block_dirs() as $dir) {
            $name = $this->read_block_name($dir);

            if ($name === '' || !isset(self::RENDERERS[$name])) {
                continue;
            }

            // Skip if this block name is already registered (e.g. a duplicate
            // block.json exists in both flat and nested build locations).
            if ($registry->is_registered($name)) {
                continue;
            }

            register_block_type(
                $dir,
                [
                    'render_callback' => self::RENDERERS[$name],
                ]
            );
        }
    }

    /**
     * Find every block.json directory under the build folder.
     *
     * Tolerant of the build tool's output layout — block.json may sit directly
     * under build/blocks/<slug>/ or under a nested build/blocks/blocks/<slug>/
     * depending on how @wordpress/scripts copies metadata. Duplicates are keyed
     * out so a block is never registered twice.
     *
     * @return array<int, string> Absolute directory paths containing block.json.
     */
    private function discover_block_dirs(): array {
        $found = [];

        foreach (['*/block.json', 'blocks/*/block.json'] as $pattern) {
            foreach ((array) glob($this->build_dir . $pattern) as $file) {
                $found[dirname($file)] = dirname($file);
            }
        }

        return array_values($found);
    }

    /**
     * Read the block name from a block.json in the given directory.
     *
     * @param string $dir Directory containing block.json.
     * @return string Block name, or '' if unreadable.
     */
    private function read_block_name(string $dir): string {
        $file = $dir . '/block.json';

        if (!is_readable($file)) {
            return '';
        }

        $json = file_get_contents($file);

        if (!is_string($json)) {
            return '';
        }

        $data = json_decode($json, true);

        return is_array($data) && isset($data['name']) ? (string) $data['name'] : '';
    }

    /**
     * Register the compiled editor script handle referenced by block.json.
     *
     * Reads the wp-scripts asset manifest (dependencies + version hash) and the
     * patched numeric bundle filename, matching the loader pattern used by the
     * plugin's other React builds.
     *
     * @return void
     */
    private function register_editor_script(): void {
        if (wp_script_is(self::EDITOR_HANDLE, 'registered')) {
            return;
        }

        $asset_file = $this->build_dir . 'blocks.asset.php';

        if (!file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

        $dependencies = isset($asset['dependencies']) && is_array($asset['dependencies'])
            ? $asset['dependencies']
            : [];
        $version = isset($asset['version']) ? (string) $asset['version'] : HVNLYNAB_VERSION;
        $script  = isset($asset['script']) ? (string) $asset['script'] : '0.js';

        wp_register_script(
            self::EDITOR_HANDLE,
            trailingslashit(HVNLYNAB_URL) . 'build/blocks/' . $script,
            $dependencies,
            $version,
            true
        );

        ScriptTranslations::attach(self::EDITOR_HANDLE);

        // Editor-only styles emitted by the same build (numeric CSS bundle).
        if (isset($asset['style'])) {
            wp_register_style(
                self::EDITOR_HANDLE,
                trailingslashit(HVNLYNAB_URL) . 'build/blocks/' . (string) $asset['style'],
                [],
                $version
            );
        }
    }
}
