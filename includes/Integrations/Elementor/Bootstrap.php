<?php
/**
 * Elementor Bootstrap - Main integration initializer
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Elementor
 * @since       3.0.0
 */

namespace HvnlyNab\Integrations\Elementor;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

final class Bootstrap {

    private static $instance = null;
    private $initialized = false;

    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('elementor/init', [$this, 'init'], 5);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        add_action('elementor/frontend/before_enqueue_styles', [$this, 'register_widget_style_handles'], 1);
        add_action('elementor/frontend/before_enqueue_styles', [$this, 'enqueue_widget_assets'], 5);
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_widget_assets'], 5);
        add_action('elementor/frontend/after_enqueue_scripts', [$this, 'enqueue_widget_scripts'], 20);
        add_action('elementor/preview/enqueue_styles', [$this, 'enqueue_widget_assets'], 5);
        add_action('elementor/preview/enqueue_scripts', [$this, 'enqueue_widget_scripts'], 20);
        add_action('elementor/editor/before_enqueue_styles', [$this, 'register_widget_style_handles'], 1);
        add_action('elementor/editor/before_enqueue_styles', [$this, 'enqueue_editor_panel_assets'], 99);
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_widget_assets'], 5);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_widget_scripts'], 5);
    }

public function init(): void {
    if ($this->initialized) {
        return;
    }

    // Ensure all template functions are loaded
    $this->load_template_functions();

    add_action('elementor/elements/categories_registered', [$this, 'register_category']);
    add_action('elementor/init', [$this, 'promote_havenlytics_category_first'], 999);
    $this->register_ajax_handlers();
    
    $this->initialized = true;
}

/**
 * Load all template functions (same as frontend)
 */
private function load_template_functions(): void {
    $functions_files = [
        'template-functions.php',
        'template-hooks.php',
        'utility-functions.php',
        'property-functions.php',
        'layout-functions.php',
        'ajax-functions.php',
        'shortcode-functions.php',
        'filter-sidebar-functions.php',
        'top-search-functions.php',
        'main-top-search-functions.php',
        'map-functions.php',
        'agent-functions.php',
    ];

    foreach ($functions_files as $file) {
        $file_path = HVNLYNAB_INCLUDES . '/Functions/' . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}

    public function register_category($elements_manager): void {
        $elements_manager->add_category(
            'havenlytics',
            [
                'title'  => __('Havenlytics', 'havenlytics'),
                'icon'   => 'eicon-home',
                'active' => true,
            ]
        );
    }

    /**
     * Move Havenlytics category to the top of the Elementor widgets panel.
     *
     * Elementor's add_category() always appends; reorder after full init (incl. WordPress category).
     */
    public function promote_havenlytics_category_first(): void {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        $elements_manager = \Elementor\Plugin::$instance->elements_manager;
        $elements_manager->get_categories();

        try {
            $reflection = new \ReflectionClass($elements_manager);
            $property   = $reflection->getProperty('categories');
            $property->setAccessible(true);
            $categories = $property->getValue($elements_manager);

            if (!isset($categories['havenlytics'])) {
                return;
            }

            $havenlytics = $categories['havenlytics'];
            unset($categories['havenlytics']);

            $property->setValue(
                $elements_manager,
                ['havenlytics' => $havenlytics] + $categories
            );
        } catch (\ReflectionException $exception) {
            return;
        }
    }

    /**
     * Editor panel styles: custom widget SVG icon (all Elementor versions).
     */
    public function enqueue_editor_panel_assets(): void {
        $editor_css_path = HVNLYNAB_ASSETS_PATH . '/admin/css/elementor-editor.css';
        $icon_url        = HVNLYNAB_ASSETS_URL . '/admin/img/havenlytics-icon20x20.svg';

        if (file_exists($editor_css_path)) {
            wp_enqueue_style(
                'hvnly-elementor-editor',
                HVNLYNAB_ASSETS_URL . '/admin/css/elementor-editor.css',
                ['elementor-editor'],
                HVNLYNAB_VERSION
            );
        }

        $icon_css = sprintf(
            '.elementor-panel .elementor-element[data-library-element-type="hvnly_all_properties"] .icon .eicon-gallery-grid,
            .elementor-panel .elementor-element[data-widget-type="hvnly_all_properties"] .icon .eicon-gallery-grid,
            .elementor-panel .elementor-element[data-library-element-type="hvnly_property_agents"] .icon .eicon-gallery-grid,
            .elementor-panel .elementor-element[data-widget-type="hvnly_property_agents"] .icon .eicon-gallery-grid,
            .elementor-panel .elementor-element[data-library-element-type="hvnly_property_agencies"] .icon .eicon-gallery-grid,
            .elementor-panel .elementor-element[data-widget-type="hvnly_property_agencies"] .icon .eicon-gallery-grid {
                position: relative !important;
                background: transparent !important;
                color: transparent !important;
            }
            .elementor-panel .elementor-element[data-library-element-type="hvnly_all_properties"] .icon .eicon-gallery-grid::before,
            .elementor-panel .elementor-element[data-widget-type="hvnly_all_properties"] .icon .eicon-gallery-grid::before,
            .elementor-panel .elementor-element[data-library-element-type="hvnly_property_agents"] .icon .eicon-gallery-grid::before,
            .elementor-panel .elementor-element[data-widget-type="hvnly_property_agents"] .icon .eicon-gallery-grid::before,
            .elementor-panel .elementor-element[data-library-element-type="hvnly_property_agencies"] .icon .eicon-gallery-grid::before,
            .elementor-panel .elementor-element[data-widget-type="hvnly_property_agencies"] .icon .eicon-gallery-grid::before {
                display: none !important;
            }
            .elementor-panel .elementor-element[data-library-element-type="hvnly_all_properties"] .icon .eicon-gallery-grid::after,
            .elementor-panel .elementor-element[data-widget-type="hvnly_all_properties"] .icon .eicon-gallery-grid::after,
            .elementor-panel .elementor-element[data-library-element-type="hvnly_property_agents"] .icon .eicon-gallery-grid::after,
            .elementor-panel .elementor-element[data-widget-type="hvnly_property_agents"] .icon .eicon-gallery-grid::after,
            .elementor-panel .elementor-element[data-library-element-type="hvnly_property_agencies"] .icon .eicon-gallery-grid::after,
            .elementor-panel .elementor-element[data-widget-type="hvnly_property_agencies"] .icon .eicon-gallery-grid::after {
                content: "";
                position: absolute;
                top: 50%%;
                left: 50%%;
                transform: translate(-50%%, -50%%);
                width: 24px;
                height: 24px;
                background: url(%s) center center no-repeat;
                background-size: contain;
            }',
            esc_url($icon_url)
        );

        wp_add_inline_style('elementor-editor', $icon_css);

        wp_add_inline_style(
            'elementor-editor',
            '#hvnly-property-global-share-popup, .hvnly-property-share-popup-overlay {' .
            'display: none !important; visibility: hidden !important; opacity: 0 !important;' .
            'pointer-events: none !important; z-index: -1 !important; position: absolute !important;' .
            'width: 0 !important; height: 0 !important; overflow: hidden !important;' .
            '} body.hvnly-property-share-popup-open { overflow: visible !important; }'
        );
    }

    public function register_widgets($widgets_manager): void {
        $widgets = [
            'HvnlyAllPropertiesWidget.php' => '\HvnlyNab\Integrations\Elementor\Widgets\HvnlyAllPropertiesWidget',
            'HvnlyPropertyAgentsWidget.php' => '\HvnlyNab\Integrations\Elementor\Widgets\HvnlyPropertyAgentsWidget',
            'HvnlyPropertyAgenciesWidget.php' => '\HvnlyNab\Integrations\Elementor\Widgets\HvnlyPropertyAgenciesWidget',
        ];

        foreach ($widgets as $file => $class) {
            $widget_file = HVNLYNAB_INCLUDES . '/Integrations/Elementor/Widgets/' . $file;

            if (!file_exists($widget_file)) {
                continue;
            }

            require_once $widget_file;

            if (class_exists($class)) {
                $widgets_manager->register(new $class());
            }
        }
    }

    /**
     * Enqueue all styles required by the Property Archive widget (frontend + editor preview).
     */
    public function enqueue_widget_assets(): void {
        if (!$this->should_load_assets()) {
            return;
        }

        if ($this->should_load_property_widget_assets()) {
            $this->enqueue_main_styles();
            $this->enqueue_elementor_widget_stylesheet();
            $this->enqueue_elementor_specific_styles();
            $this->enqueue_editor_compatibility_styles();
            $this->inject_dynamic_styles();
        }

        if ($this->should_load_agent_widget_assets()) {
            $this->register_agent_widget_style_handles();
            $this->enqueue_agent_widget_assets();
            $this->inject_dynamic_styles();
        }

        if ($this->should_load_agency_widget_assets()) {
            $this->register_agency_widget_style_handles();
            $this->enqueue_agency_widget_assets();
            $this->inject_dynamic_styles();
        }
    }

    /**
     * Enqueue scripts for Elementor widget contexts.
     * Editor preview and live frontend use the same archive search JS stack.
     */
    public function enqueue_widget_scripts(): void {
        if (!$this->should_load_assets()) {
            return;
        }

        if ($this->should_load_property_widget_assets()) {
            $is_edit_mode = $this->is_elementor_editor_context();

            $this->register_main_scripts();
            $this->register_elementor_widget_script();
            $this->output_frontend_script_globals();
            $this->enqueue_main_scripts();

            if (!$this->is_archive_stack_localized()) {
                $this->localize_scripts();
            }

            $this->localize_elementor_widget_script($is_edit_mode);
        }

        if ($this->should_load_agent_widget_assets()) {
            $this->register_agent_widget_style_handles();
            $this->enqueue_agent_widget_scripts();
        }

        if ($this->should_load_agency_widget_assets()) {
            $this->register_agency_widget_style_handles();
            $this->enqueue_agency_widget_scripts();
        }
    }

    /**
     * Called from property archive widget render() as a fallback when hooks did not run.
     */
    public function enqueue_widget_assets_for_render(): void {
        $this->enqueue_widget_assets();
        $this->enqueue_widget_scripts();
    }

    /**
     * Called from Property Agents widget render() as a fallback when hooks did not run.
     */
    public function enqueue_agent_widget_assets_for_render(): void {
        $this->register_agent_widget_style_handles();
        $this->enqueue_agent_widget_assets();
        $this->enqueue_agent_widget_scripts();
        $this->inject_dynamic_styles();
    }

    /**
     * Called from Property Agencies widget render() as a fallback when hooks did not run.
     */
    public function enqueue_agency_widget_assets_for_render(): void {
        $this->register_agency_widget_style_handles();
        $this->enqueue_agency_widget_assets();
        $this->enqueue_agency_widget_scripts();
        $this->inject_dynamic_styles();
    }

    /**
     * Check if assets should be loaded
     */
    private function should_load_assets(): bool {
        return $this->should_load_property_widget_assets() || $this->should_load_agent_widget_assets() || $this->should_load_agency_widget_assets();
    }

    /**
     * Whether the Property Archive Elementor widget needs its asset stack.
     */
    private function should_load_property_widget_assets(): bool {
        if ($this->is_elementor_editor_context()) {
            return true;
        }

        return $this->page_has_elementor_widget('hvnly_all_properties');
    }

    /**
     * Whether the Property Agents Elementor widget needs its asset stack.
     */
    private function should_load_agent_widget_assets(): bool {
        if ($this->is_elementor_editor_context()) {
            return true;
        }

        return $this->page_has_elementor_widget('hvnly_property_agents');
    }

    /**
     * Whether the Property Agencies Elementor widget needs its asset stack.
     */
    private function should_load_agency_widget_assets(): bool {
        if ($this->is_elementor_editor_context()) {
            return true;
        }

        return $this->page_has_elementor_widget('hvnly_property_agencies');
    }

    /**
     * Check if the current page Elementor data contains a Havenlytics widget.
     */
    private function page_has_elementor_widget(string $widget_name): bool {
        global $post;

        if (!$post || !$post->ID || '' === $widget_name) {
            return false;
        }

        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);

        return !empty($elementor_data) && strpos($elementor_data, $widget_name) !== false;
    }

    /**
     * True when Elementor editor UI or preview iframe is active.
     */
    private function is_elementor_editor_context(): bool {
        if (isset($_GET['action']) && 'elementor' === sanitize_text_field(wp_unslash($_GET['action']))) {
            return true;
        }

        if (isset($_GET['elementor-preview'])) {
            return true;
        }

        if (!class_exists('\Elementor\Plugin')) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance;

        if (isset($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode()) {
            return true;
        }

        if (isset($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode()) {
            return true;
        }

        return false;
    }

    /**
     * Register style handles (required for Elementor get_style_depends()).
     */
    public function register_widget_style_handles(): void {
        $version = HVNLYNAB_VERSION;

        $css_files = [
            'hvnly-fontawesome-all-frontend' => HVNLYNAB_ASSETS_URL . '/admin/css/fontawesome-all.min.css',
            'hvnly-frontend-default' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-default.css',
            'hvnly-frontend-components' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-components.css',
            'hvnly-frontend-global-grid' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-global-grid.css',
            'hvnly-frontend-property-card-embed' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-cards/hvnly-frontend-property-card-embed.css',
            'hvnly-frontend-property-ajax-filter' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-ajax-filter-search.css',
            'hvnly-frontend-property-archive' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-archive.css',
            'hvnly-frontend-property-map' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-map.css',
            'hvnly-frontend-property-share' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-share.css',
            'hvnly-frontend-property-responsive' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-responsive.css',
        ];

        foreach ($css_files as $handle => $url) {
            if (!wp_style_is($handle, 'registered')) {
                $deps = [];
                if ('hvnly-frontend-property-card-embed' === $handle) {
                    $deps = ['hvnly-frontend-default', 'hvnly-frontend-components'];
                } elseif ('hvnly-frontend-property-ajax-filter' === $handle) {
                    $deps = ['hvnly-frontend-property-card-embed', 'hvnly-frontend-property-map'];
                }
                wp_register_style($handle, $url, $deps, $version);
            }
        }

        $css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-all-properties.css';
        if (file_exists($css_path) && !wp_style_is('hvnly-elementor-archive-widget', 'registered')) {
            wp_register_style(
                'hvnly-elementor-archive-widget',
                HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-all-properties.css',
                ['hvnly-frontend-property-ajax-filter', 'hvnly-frontend-property-archive'],
                $version
            );
        }

        $widgets_css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-elementor-widgets.css';
        if (file_exists($widgets_css_path) && !wp_style_is('hvnly-elementor-widgets', 'registered')) {
            wp_register_style(
                'hvnly-elementor-widgets',
                HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-elementor-widgets.css',
                ['hvnly-frontend-property-ajax-filter', 'hvnly-elementor-archive-widget'],
                $version
            );
        }

        $this->register_agent_widget_style_handles();
    }

    /**
     * Register style/script handles for the Property Agents Elementor widget.
     */
    private function register_agent_widget_style_handles(): void {
        $version = HVNLYNAB_VERSION;

        $agent_css_files = [
            'hvnly-fontawesome-all-frontend' => HVNLYNAB_ASSETS_URL . '/admin/css/fontawesome-all.min.css',
            'hvnly-frontend-default' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-default.css',
            'hvnly-frontend-components' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-components.css',
            'hvnly-frontend-global-grid' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-global-grid.css',
            'hvnly-frontend-property-agent-widget' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-agent-widget.css',
            'hvnly-frontend-cards' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-cards.css',
            'hvnly-frontend-property-agents-archive' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-agents-archive.css',
        ];

        foreach ($agent_css_files as $handle => $url) {
            if (!wp_style_is($handle, 'registered')) {
                wp_register_style($handle, $url, [], $version);
            }
        }

        if (!wp_style_is('hvnly-frontend-cards', 'registered')) {
            wp_register_style(
                'hvnly-frontend-cards',
                HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-cards.css',
                ['hvnly-frontend-default', 'hvnly-frontend-components'],
                $version
            );
        }

        if (!wp_style_is('hvnly-frontend-property-agents-archive', 'registered')) {
            wp_register_style(
                'hvnly-frontend-property-agents-archive',
                HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-agents-archive.css',
                ['hvnly-frontend-default', 'hvnly-frontend-components', 'hvnly-frontend-cards'],
                $version
            );
        }

        if (!wp_style_is('hvnly-frontend-property-agent-widget', 'registered')) {
            wp_register_style(
                'hvnly-frontend-property-agent-widget',
                HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-agent-widget.css',
                ['hvnly-frontend-default', 'hvnly-frontend-components'],
                $version
            );
        }

        $agents_elementor_css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-property-agents.css';
        if (file_exists($agents_elementor_css_path) && !wp_style_is('hvnly-elementor-property-agents', 'registered')) {
            wp_register_style(
                'hvnly-elementor-property-agents',
                HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-property-agents.css',
                ['hvnly-frontend-property-agents-archive', 'hvnly-frontend-cards'],
                $version
            );
        }

        $property_archive_js_path = HVNLYNAB_ASSETS_PATH . '/frontend/js/hvnly-frontend-property-agents-archive.js';
        if (file_exists($property_archive_js_path) && !wp_script_is('hvnly-frontend-property-agents-archive', 'registered')) {
            wp_register_script(
                'hvnly-frontend-property-agents-archive',
                HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-property-agents-archive.js',
                [],
                $version,
                true
            );
        }
    }

    /**
     * Enqueue Property Agents widget CSS stack.
     */
    private function enqueue_agent_widget_assets(): void {
        $this->register_agent_widget_style_handles();

        $handles = [
            'hvnly-fontawesome-all-frontend',
            'hvnly-frontend-default',
            'hvnly-frontend-components',
            'hvnly-frontend-global-grid',
            'hvnly-frontend-property-agent-widget',
            'hvnly-frontend-cards',
            'hvnly-frontend-property-agents-archive',
            'hvnly-elementor-property-agents',
        ];

        foreach ($handles as $handle) {
            if (wp_style_is($handle, 'registered')) {
                wp_enqueue_style($handle);
            }
        }
    }

    /**
     * Enqueue Property Agents widget JS (grid/list toggle).
     */
    private function enqueue_agent_widget_scripts(): void {
        $this->register_agent_widget_style_handles();

        if (wp_script_is('hvnly-frontend-property-agents-archive', 'registered')) {
            wp_enqueue_script('hvnly-frontend-property-agents-archive');
        }
    }

    /**
     * Register style/script handles for the Property Agencies Elementor widget.
     */
    private function register_agency_widget_style_handles(): void {
        $this->register_agent_widget_style_handles();

        $version = HVNLYNAB_VERSION;

        $agencies_elementor_css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-property-agencies.css';
        if (file_exists($agencies_elementor_css_path) && !wp_style_is('hvnly-elementor-property-agencies', 'registered')) {
            wp_register_style(
                'hvnly-elementor-property-agencies',
                HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-property-agencies.css',
                ['hvnly-frontend-property-agents-archive', 'hvnly-frontend-cards'],
                $version
            );
        }
    }

    /**
     * Enqueue Property Agencies widget CSS stack.
     */
    private function enqueue_agency_widget_assets(): void {
        $this->register_agency_widget_style_handles();

        $handles = [
            'hvnly-fontawesome-all-frontend',
            'hvnly-frontend-default',
            'hvnly-frontend-components',
            'hvnly-frontend-global-grid',
            'hvnly-frontend-cards',
            'hvnly-frontend-property-agents-archive',
            'hvnly-elementor-property-agencies',
        ];

        foreach ($handles as $handle) {
            if (wp_style_is($handle, 'registered')) {
                wp_enqueue_style($handle);
            }
        }
    }

    /**
     * Enqueue Property Agencies widget JS (grid/list toggle).
     */
    private function enqueue_agency_widget_scripts(): void {
        $this->register_agency_widget_style_handles();

        if (wp_script_is('hvnly-frontend-property-agents-archive', 'registered')) {
            wp_enqueue_script('hvnly-frontend-property-agents-archive');
        }
    }

    /**
     * Enqueue main CSS styles
     */
    private function enqueue_main_styles(): void {
        $this->register_widget_style_handles();
        $version = HVNLYNAB_VERSION;
        
        // Main property archive CSS
        $css_files = [
            'hvnly-fontawesome-all-frontend' => HVNLYNAB_ASSETS_URL . '/admin/css/fontawesome-all.min.css',
            'hvnly-frontend-default' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-default.css',
            'hvnly-frontend-components' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-components.css',
            'hvnly-frontend-global-grid' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-global-grid.css',
            'hvnly-frontend-property-ajax-filter' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-ajax-filter-search.css',
            'hvnly-frontend-property-archive' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-archive.css',
            'hvnly-frontend-property-map' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-map.css',
            'hvnly-frontend-property-share' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-share.css',
            'hvnly-frontend-property-responsive' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-responsive.css',
        ];
        
        foreach ($css_files as $handle => $url) {
            if (!wp_style_is($handle, 'registered')) {
                wp_register_style($handle, $url, [], $version);
            }
            wp_enqueue_style($handle);
        }
    }

    /**
     * Widget-specific Elementor CSS (results bar, pagination, etc.).
     */
    private function enqueue_elementor_widget_stylesheet(): void {
        $css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-all-properties.css';
        $css_url  = HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-all-properties.css';

        if (!file_exists($css_path)) {
            return;
        }

        if (!wp_style_is('hvnly-elementor-archive-widget', 'registered')) {
            wp_register_style(
                'hvnly-elementor-archive-widget',
                $css_url,
                ['hvnly-frontend-property-ajax-filter', 'hvnly-frontend-property-archive'],
                HVNLYNAB_VERSION
            );
        }

        wp_enqueue_style('hvnly-elementor-archive-widget');
        $this->enqueue_elementor_widgets_stylesheet();
    }

    /**
     * Scoped Elementor widget fixes (columns, map, view controls placement).
     */
    private function enqueue_elementor_widgets_stylesheet(): void {
        $css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-elementor-widgets.css';
        $css_url  = HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-elementor-widgets.css';

        if (!file_exists($css_path)) {
            return;
        }

        if (!wp_style_is('hvnly-elementor-widgets', 'registered')) {
            wp_register_style(
                'hvnly-elementor-widgets',
                $css_url,
                ['hvnly-frontend-property-ajax-filter', 'hvnly-elementor-archive-widget'],
                HVNLYNAB_VERSION
            );
        }

        wp_enqueue_style('hvnly-elementor-widgets');
    }

    /**
     * Editor preview uses the same archive CSS/JS — no layout overrides that fight filter modules.
     */
    private function enqueue_editor_compatibility_styles(): void {
        return;
    }

    /**
     * Inject dynamic design tokens in Elementor contexts.
     */
    private function inject_dynamic_styles(): void {
        if (!class_exists('\HvnlyNab\Core\DynamicStyleGenerator')) {
            return;
        }

        $generator = \HvnlyNab\Core\DynamicStyleGenerator::get_instance();
        if (method_exists($generator, 'inject_dynamic_styles')) {
            $generator->inject_dynamic_styles();
        }
    }

    /**
     * Enqueue Elementor-specific CSS overrides
     */
    private function enqueue_elementor_specific_styles(): void {
        if ($this->is_elementor_editor_context()) {
            wp_add_inline_style(
                'hvnly-frontend-property-share',
                '#hvnly-property-global-share-popup, .hvnly-property-share-popup-overlay {' .
                'display: none !important; visibility: hidden !important; opacity: 0 !important;' .
                'pointer-events: none !important; z-index: -1 !important; position: absolute !important;' .
                'width: 0 !important; height: 0 !important; overflow: hidden !important;' .
                '} body.hvnly-property-share-popup-open { overflow: visible !important; }'
            );
        }

        $elementor_css = "
            /* Havenlytics Elementor Widget Styles */
            .hvnly-all-properties-widget {
                max-width: 100%;
                overflow-x: hidden;
            }
            
            .hvnly-all-properties-widget .hvnly-property-search-form {
                margin-bottom: 30px;
            }
            
            /* Grid Layout */
            .hvnly-all-properties-widget .hvnly-with-sidebar__contents__grids {
                display: grid;
                grid-template-columns: 320px 1fr;
                gap: 30px;
            }
            
            .hvnly-all-properties-widget.hvnly-no-sidebar .hvnly-with-sidebar__contents__grids {
                grid-template-columns: 1fr;
            }
            
            .hvnly-all-properties-widget.hvnly-sidebar-right .hvnly-with-sidebar__contents__grids {
                grid-template-columns: 1fr 320px;
            }
            
            /* Responsive */
            @media (max-width: 1024px) {
                .hvnly-all-properties-widget .hvnly-with-sidebar__contents__grids,
                .hvnly-all-properties-widget.hvnly-sidebar-right .hvnly-with-sidebar__contents__grids {
                    grid-template-columns: 1fr;
                }
            }
            
            @media (max-width: 768px) {
                .hvnly-all-properties-widget .hvnly-property-search-tabs {
                    flex-wrap: wrap;
                }
            }
            
            /* Ensure proper z-index for dropdowns */
            .hvnly-all-properties-widget .hvnly-property-taxonomyDropdown-items {
                z-index: 100;
            }
            
            /* Fix for Elementor editor */
            .elementor-editor-active .hvnly-all-properties-widget .hvnly-property-load-more-btn,
            .elementor-editor-active .hvnly-all-properties-widget .hvnly-property-pagination-item,
            .elementor-preview .hvnly-all-properties-widget .hvnly-property-load-more-btn,
            .elementor-preview .hvnly-all-properties-widget .hvnly-property-pagination-item {
                pointer-events: none;
            }
        ";
        
        wp_add_inline_style('hvnly-frontend-property-ajax-filter', $elementor_css);
    }

    /**
     * Register main JavaScript files
     */
    private function register_main_scripts(): void {
        $version = HVNLYNAB_VERSION;
        
        // Register modular AJAX scripts
        $ajax_modules = [
            'hvnly-frontend-dom-resolver' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-dom-resolver.js',
                'deps' => ['jquery']
            ],
            'hvnly-frontend-property-ajax-utils' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-utils.js',
                'deps' => ['jquery', 'hvnly-frontend-dom-resolver']
            ],
            'hvnly-frontend-property-ajax-filters' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-filters.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils']
            ],
            'hvnly-frontend-property-ajax-search' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-search.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils', 'hvnly-frontend-property-ajax-filters']
            ],
            'hvnly-frontend-property-ajax-pagination' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-pagination.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils']
            ],
            'hvnly-frontend-property-ajax-url' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-url.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils']
            ],
            'hvnly-frontend-property-ajax-ui' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-ui.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils']
            ],
            'hvnly-frontend-property-ajax-root' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/hvnly-frontend-property-ajax-root.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils', 'hvnly-frontend-property-ajax-filters', 'hvnly-frontend-property-ajax-search', 'hvnly-frontend-property-ajax-pagination', 'hvnly-frontend-property-ajax-url', 'hvnly-frontend-property-ajax-ui']
            ],
            'hvnly-frontend-property-map-search' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/hvnly-frontend-property-map-search.js',
                'deps' => ['jquery', 'hvnly-frontend-dom-resolver']
            ],
            'hvnly-frontend-property-ajax-filter-search' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/hvnly-frontend-property-ajax-filter-search.js',
                'deps' => ['jquery', 'hvnly-frontend-property-map-search']
            ],
        ];
        
        foreach ($ajax_modules as $handle => $module) {
            if (!wp_script_is($handle, 'registered')) {
                wp_register_script(
                    $handle,
                    $module['path'],
                    $module['deps'],
                    $version,
                    true
                );
            }
        }
        
        if (!wp_script_is('hvnly-elementor-preview-guard', 'registered')) {
            wp_register_script(
                'hvnly-elementor-preview-guard',
                HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-elementor-preview-guard.js',
                ['jquery'],
                $version,
                true
            );
        }

        $widget_js_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/js/hvnly-all-properties.js';
        if (file_exists($widget_js_path) && !wp_script_is('hvnly-elementor-widgets', 'registered')) {
            wp_register_script(
                'hvnly-elementor-widgets',
                HVNLYNAB_ASSETS_URL . '/frontend/elementor/js/hvnly-all-properties.js',
                ['jquery', 'hvnly-frontend-property-ajax-filter-search'],
                $version,
                true
            );
        }
    }

    /**
     * Register Elementor widget script handle (editor + frontend).
     */
    private function register_elementor_widget_script(): void {
        $version = HVNLYNAB_VERSION;
        $widget_js_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/js/hvnly-all-properties.js';

        if (!file_exists($widget_js_path) || wp_script_is('hvnly-elementor-widgets', 'registered')) {
            return;
        }

        wp_register_script(
            'hvnly-elementor-widgets',
            HVNLYNAB_ASSETS_URL . '/frontend/elementor/js/hvnly-all-properties.js',
            ['jquery', 'hvnly-frontend-property-ajax-filter-search'],
            $version,
            true
        );
    }

    /**
     * Localize Elementor widget script for editor preview vs live frontend.
     */
    private function localize_elementor_widget_script(bool $is_edit_mode): void {
        if (!wp_script_is('hvnly-elementor-widgets', 'registered')) {
            return;
        }

        wp_localize_script(
            'hvnly-elementor-widgets',
            'hvnlyElementor',
            [
                'ajaxUrl'    => admin_url('admin-ajax.php'),
                'nonce'      => wp_create_nonce('hvnly_ajax_request'),
                'pluginUrl'  => HVNLYNAB_URL,
                'assetsUrl'  => HVNLYNAB_ASSETS_URL,
                'isEditMode' => $is_edit_mode,
                'initDelay'  => $is_edit_mode ? 350 : 0,
                'i18n'       => [
                    'loading'      => __('Loading...', 'havenlytics'),
                    'loadMore'     => __('Load More Properties', 'havenlytics'),
                    'noProperties' => __('No properties found.', 'havenlytics'),
                    'error'        => __('An error occurred. Please try again.', 'havenlytics'),
                ],
            ]
        );
    }

    /**
     * Enqueue main JavaScript files
     */
    private function enqueue_main_scripts(): void {
        wp_enqueue_script('jquery');
        wp_enqueue_script('hvnly-elementor-preview-guard');
        wp_enqueue_script('hvnly-frontend-dom-resolver');
        wp_enqueue_script('hvnly-frontend-property-ajax-utils');
        wp_enqueue_script('hvnly-frontend-property-ajax-filters');
        wp_enqueue_script('hvnly-frontend-property-ajax-search');
        wp_enqueue_script('hvnly-frontend-property-ajax-pagination');
        wp_enqueue_script('hvnly-frontend-property-ajax-url');
        wp_enqueue_script('hvnly-frontend-property-ajax-ui');
        wp_enqueue_script('hvnly-frontend-property-ajax-root');
        wp_enqueue_script('hvnly-frontend-property-map-search');
        wp_enqueue_script('hvnly-frontend-property-ajax-filter-search');

        if (wp_script_is('hvnly-elementor-widgets', 'registered')) {
            wp_enqueue_script('hvnly-elementor-widgets');
        }
    }

    /**
     * Whether Frontend Assets already localized the archive AJAX globals.
     */
    private function is_archive_stack_localized(): bool {
        global $wp_scripts;

        if (!($wp_scripts instanceof \WP_Scripts) || !isset($wp_scripts->registered['hvnly-frontend-property-ajax-root'])) {
            return false;
        }

        $data = $wp_scripts->registered['hvnly-frontend-property-ajax-root']->extra['data'] ?? '';

        return is_string($data) && strpos($data, 'hvnly_PROPERTY_ajax') !== false;
    }

    /**
     * Localize scripts with AJAX parameters
     */
    private function localize_scripts(): void {
        $ajax_params = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hvnly_ajax_request'),
            'current_page' => 1,
            'max_pages' => 1,
            'per_page' => 12,
            'properties_per_page' => 12,
            'loading_text' => __('Loading...', 'havenlytics'),
            'load_more_text' => __('Load More Properties', 'havenlytics'),
            'error_message' => __('An error occurred. Please try again.', 'havenlytics'),
            'debug' => ( function_exists( 'hvnly_is_debug_logging_enabled' ) && hvnly_is_debug_logging_enabled() ) ? '1' : '0',
        ];
        
        wp_localize_script('hvnly-frontend-property-ajax-root', 'hvnly_PROPERTY_ajax', $ajax_params);
        
        // Map parameters
        $map_params = $this->get_map_config();
        wp_localize_script('hvnly-frontend-property-map-search', 'hvnly_map_params', $map_params);
    }

    /**
     * Get map configuration
     */
    private function get_map_config(): array {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $map_settings = $settings_manager->get_map_settings();
        
        $map_provider = $map_settings['hvnly_map_provider'] ?? 'leaflet';
        $api_key = $map_settings['hvnly_map_api_key'] ?? '';
        
        if ($map_provider === 'google' && empty($api_key)) {
            $map_provider = 'leaflet';
        }
        
        return [
            'provider' => $map_provider,
            'api_key' => $api_key,
            'enable_google_fallback' => $map_settings['hvnly_enable_google_fallback'] ?? true,
            'default_lat' => (float) ($map_settings['hvnly_default_lat'] ?? 51.514939),
            'default_lng' => (float) ($map_settings['hvnly_default_lng'] ?? -0.091839),
            'zoom_level' => (int) ($map_settings['hvnly_map_zoom'] ?? 12),
            'cluster_markers' => (bool) ($map_settings['hvnly_cluster_markers'] ?? true),
            'map_style' => $map_settings['hvnly_map_style'] ?? 'standard',
            'custom_marker' => (bool) ($map_settings['hvnly_custom_marker'] ?? false),
            'marker_color' => function_exists('hvnly_get_marker_color') ? hvnly_get_marker_color() : ($map_settings['hvnly_marker_color'] ?? '#6C60FE'),
            'marker_uses_brand_color' => function_exists('hvnly_marker_color_is_custom') ? !hvnly_marker_color_is_custom() : false,
            'marker_color_css' => function_exists('hvnly_get_marker_color_css') ? hvnly_get_marker_color_css() : (function_exists('hvnly_get_marker_color') ? hvnly_get_marker_color() : '#6C60FE'),
            'show_fullscreen' => (bool) ($map_settings['hvnly_show_fullscreen'] ?? true),
            'show_zoom_control' => (bool) ($map_settings['hvnly_show_zoom_control'] ?? true),
            'show_scroll_wheel' => (bool) ($map_settings['hvnly_show_scroll_wheel'] ?? true),
            'google_map_type' => $map_settings['hvnly_google_map_type'] ?? 'roadmap',
            'osm_tile_url' => $map_settings['hvnly_osm_tile_url'] ?? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'osm_attribution' => $map_settings['hvnly_osm_attribution'] ?? '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            'plugin_url' => HVNLYNAB_URL,
            'assets_url' => HVNLYNAB_ASSETS_URL,
            'is_single_property' => false,
            'is_property_archive' => true,
        ];
    }

    /**
     * Output AJAX load-more global for Elementor preview iframe.
     */
    private function output_frontend_script_globals(): void {
        if (wp_script_is('jquery', 'registered') && !wp_script_is('jquery', 'enqueued')) {
            wp_enqueue_script('jquery');
        }

        $is_ajax_load_more_enabled = function_exists('hvnly_is_ajax_load_more_enabled')
            ? hvnly_is_ajax_load_more_enabled()
            : true;

        wp_add_inline_script(
            'jquery',
            'window.hvnly_ajax_load_more_enabled = ' . ($is_ajax_load_more_enabled ? 'true' : 'false') . ';',
            'before'
        );
    }

    private function register_ajax_handlers(): void {
        $ajax_handler_file = HVNLYNAB_INCLUDES . '/Integrations/Elementor/AjaxHandler.php';
        if (file_exists($ajax_handler_file)) {
            require_once $ajax_handler_file;
        }
    }
}