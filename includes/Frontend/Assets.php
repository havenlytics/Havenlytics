<?php

/**
 * Assets Manager for Havenlytics 
 *
 * @package     Havenlytics
 * @subpackage  Frontend
 * @copyright   Copyright (c) 2025, Havenlytics
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.0.0
 */

namespace HvnlyNab\Frontend;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assets class
 *
 * @since 2.0.0
 */
class Assets
{
    private $is_property_archive = false;
    private $is_single_property = false;
    private $is_single_agent = false;
    private $is_agent_archive = false;
    private $is_agency_archive = false;
    private $is_agency_single = false;
    private $is_property_taxonomy = false;
    private $is_property_search_page = false;
    private $has_property_shortcode = false; 
    private $has_agents_shortcode = false;
    private $has_agencies_shortcode = false;
    private $has_property_block = false;
    private $ajax_params = null;
    private $map_params = null;
    private $map_localize_params = null;
    private $localization_done = false; 
    
    // Elementor Integration Properties
    private $has_elementor_widget = false;
    private $elementor_assets_enqueued = false;
    
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'setup_page_conditions'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        // Late: beat theme global button CSS (Astra, Kadence, Hello, etc.)
        add_action('wp_enqueue_scripts', [$this, 'enqueue_theme_button_isolation'], 999);

        add_action('template_redirect', [$this, 'start_output_buffering'], 1);

        // After setup_page_conditions so Elementor/widget flags are available.
        add_action('wp_enqueue_scripts', [$this, 'output_frontend_script_globals'], 2);
        
        // Elementor Integration Hooks
        add_action('elementor/frontend/before_enqueue_scripts', [$this, 'enqueue_elementor_assets'], 5);
        add_action('elementor/preview/enqueue_scripts', [$this, 'enqueue_elementor_assets'], 5);
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_elementor_editor_assets']);
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_elementor_styles'], 5);
        add_action('elementor/frontend/after_enqueue_styles', [$this, 'enqueue_theme_button_isolation'], 20);
    }

    /**
     * Output frontend globals used by archive search scripts.
     *
     * @return void
     */
    public function output_frontend_script_globals()
    {
        $has_shortcode = false;

        if (is_singular()) {
            global $post;

            if (!empty($post->post_content) && has_shortcode($post->post_content, 'hvnly_property_search')) {
                $has_shortcode = true;
            }
        }

        if (
            !$has_shortcode &&
            !$this->has_elementor_widget &&
            !$this->is_elementor_editor_mode() &&
            !is_post_type_archive('hvnly_property') &&
            !is_tax(get_object_taxonomies('hvnly_property')) &&
            !is_singular('hvnly_property')
        ) {
            return;
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

    /**
     * Setup page conditions early
     */
    public function setup_page_conditions()
    {
        $this->is_single_property = is_singular('hvnly_property');
        $this->is_single_agent = is_singular('hvnly_agent');
        $this->is_agent_archive = is_post_type_archive('hvnly_agent');
        $this->is_agency_archive = function_exists('hvnly_is_agency_archive_index') && hvnly_is_agency_archive_index();
        $this->is_agency_single = is_tax('hvnly_agent_agency') && !$this->is_agency_archive;
        $this->is_property_archive = is_post_type_archive('hvnly_property');
        $this->is_property_taxonomy = is_tax(get_object_taxonomies('hvnly_property'));
        
        // Check if this is our property search page
        $this->is_property_search_page = $this->is_property_search_page();
        
        // Check if page contains property shortcodes
        $this->has_property_shortcode = $this->has_property_shortcodes();

        // Check if page contains agents shortcode
        $this->has_agents_shortcode = $this->has_agents_shortcode();

        // Check if page contains agencies shortcode
        $this->has_agencies_shortcode = $this->has_agencies_shortcode();
        
        // Check if page contains Elementor widget
        $this->has_elementor_widget = $this->has_elementor_widgets();

        // Check if page contains Gutenberg property archive/search blocks
        $this->has_property_block = $this->has_property_blocks();
    }

    /**
     * Check if current page has Gutenberg Property Archive or Property Search blocks.
     *
     * Mirrors Elementor/shortcode detection so the existing map asset pipeline
     * (Leaflet/Google + apply_map_search_script_dependencies) runs for blocks.
     *
     * @return bool
     */
    private function has_property_blocks() {
        global $post;

        if ( ! $post instanceof \WP_Post || ! function_exists( 'has_block' ) ) {
            return false;
        }

        return has_block( 'havenlytics/property-archive', $post )
            || has_block( 'havenlytics/property-search', $post );
    }
    
    /**
     * Check if current page has any property shortcodes
     *
     * @return bool
     */
    private function has_property_shortcodes() {
        global $post;
        
        if (!$post || !isset($post->post_content)) {
            return false;
        }
        
        $property_shortcodes = [
            'hvnly_property_grid',
            'hvnly_property_list',
            'hvnly_property_search',
            'hvnly_featured_properties',
            'hvnly_properties'
        ];
        
        foreach ($property_shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if current page has the agents archive shortcode.
     *
     * @return bool
     */
    private function has_agents_shortcode() {
        global $post;

        if ( ! $post || ! isset( $post->post_content ) ) {
            return false;
        }

        if ( has_shortcode( $post->post_content, 'hvnly_property_agents' ) ) {
            return true;
        }

        if ( class_exists( '\HvnlyNab\Setup\PageInstaller' ) ) {
            $agents_page_id = \HvnlyNab\Setup\PageInstaller::get_page_id( 'property_agents' );
            if ( $agents_page_id && is_page( $agents_page_id ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current page has the agencies archive shortcode.
     *
     * @return bool
     */
    private function has_agencies_shortcode() {
        global $post;

        if ( ! $post || ! isset( $post->post_content ) ) {
            return false;
        }

        if ( has_shortcode( $post->post_content, 'hvnly_property_agencies' ) ) {
            return true;
        }

        if ( class_exists( '\HvnlyNab\Setup\PageInstaller' ) ) {
            $agencies_page_id = \HvnlyNab\Setup\PageInstaller::get_page_id( 'property_agencies' );
            if ( $agencies_page_id && is_page( $agencies_page_id ) ) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Check if current page has Elementor widget
     *
     * @return bool
     */
    private function has_elementor_widgets() {
        global $post;
        
        if (!$post || !$post->ID) {
            return false;
        }
        
        // Check Elementor data for our widgets
        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);
        if (!empty($elementor_data) && (
            strpos($elementor_data, 'hvnly_all_properties') !== false
            || strpos($elementor_data, 'hvnly_property_agents') !== false
            || strpos($elementor_data, 'hvnly_property_agencies') !== false
        )) {
            return true;
        }
        
        // Check if we're in Elementor editor mode
        if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
            return true;
        }
        
        if (isset($_GET['elementor-preview'])) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if the current page's Elementor data contains a specific Havenlytics widget.
     *
     * @param string $widget_name Elementor widget name slug.
     * @return bool
     */
    private function page_has_elementor_widget($widget_name) {
        global $post;

        if (!$post || !$post->ID || '' === $widget_name) {
            return false;
        }

        $elementor_data = get_post_meta($post->ID, '_elementor_data', true);

        return !empty($elementor_data) && strpos($elementor_data, $widget_name) !== false;
    }
    
    /**
     * Check if we're in Elementor editor mode
     *
     * @return bool
     */
    private function is_elementor_editor_mode() {
        if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
            return true;
        }
        if (isset($_GET['elementor-preview'])) {
            return true;
        }
        return false;
    }
    
    /**
     * Check if current page is the property search page
     *
     * @return bool
     */
    private function is_property_search_page() {
        global $post;
        
        // Check by page ID if we have the page installer class
        if (class_exists('\HvnlyNab\Setup\PageInstaller')) {
            $grid_page_id = \HvnlyNab\Setup\PageInstaller::get_page_id('property_grid');
            $lists_page_id = \HvnlyNab\Setup\PageInstaller::get_page_id('property_lists');
            
            if (($grid_page_id && is_page($grid_page_id)) || ($lists_page_id && is_page($lists_page_id))) {
                return true;
            }
        }
        
        // Fallback: Check if post content contains the shortcodes
        if ($post && isset($post->post_content)) {
            if (has_shortcode($post->post_content, 'hvnly_property_grid') || 
                has_shortcode($post->post_content, 'hvnly_property_lists') ||
                has_shortcode($post->post_content, 'hvnly_property_search')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Start output buffering to remove source maps
     */
    public function start_output_buffering()
    {
        ob_start(function($buffer) {
            // Remove all sourceURL comments
            $buffer = preg_replace('/\/\/# sourceURL=[^\n\r]*[\n\r]*/m', '', $buffer);
            return $buffer;
        });
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_styles()
    {
        // Register all styles
        $this->register_styles();
        
        // Always enqueue base styles
        wp_enqueue_style('hvnly-fontawesome-all-frontend');
        wp_enqueue_style('hvnly-frontend-default');
        wp_enqueue_style('hvnly-frontend-components');
        wp_enqueue_style('hvnly-frontend-global-grid');
        
        // Inject dynamic CSS from design settings
        $this->inject_dynamic_css();
        
        // Conditional styles
        if ($this->is_single_property) {
            // Agents section CSS is bundled in property-single.css; drop legacy style handle.
            wp_dequeue_style( 'hvnly-frontend-agents-section' );
            wp_deregister_style( 'hvnly-frontend-agents-section' );

            wp_enqueue_style('hvnly-frontend-property-single');
            wp_enqueue_style('hvnly-frontend-widgets');

            if (function_exists('hvnly_is_contact_agent_enabled') && hvnly_is_contact_agent_enabled()) {
                wp_enqueue_style('hvnly-frontend-contact-agent');
            }

            if (class_exists('\HvnlyNab\Frontend\MobileContactDock') && \HvnlyNab\Frontend\MobileContactDock::should_enqueue_assets()) {
                wp_enqueue_style('hvnly-frontend-mobile-contact-dock');
            }
        }

        if ($this->is_single_agent) {
            wp_enqueue_style('hvnly-frontend-property-single');

            if (function_exists('hvnly_is_contact_agent_enabled') && hvnly_is_contact_agent_enabled()) {
                wp_enqueue_style('hvnly-frontend-contact-agent');
            }
        }

        if ($this->is_property_archive_listing_page() || $this->page_has_elementor_widget('hvnly_property_agents') || $this->has_agents_shortcode) {
            wp_enqueue_style('hvnly-frontend-property-agent-widget');
            wp_enqueue_style('hvnly-frontend-cards');
            wp_enqueue_style('hvnly-frontend-property-agents-archive');
        }

        if ($this->page_has_elementor_widget('hvnly_property_agents') || $this->is_elementor_editor_mode()) {
            $agents_css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-property-agents.css';
            if (file_exists($agents_css_path)) {
                if (!wp_style_is('hvnly-elementor-property-agents', 'registered')) {
                    wp_register_style(
                        'hvnly-elementor-property-agents',
                        HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-property-agents.css',
                        ['hvnly-frontend-property-agents-archive', 'hvnly-frontend-cards'],
                        HVNLYNAB_VERSION
                    );
                }
                wp_enqueue_style('hvnly-elementor-property-agents');
            }
        }

        if ($this->should_load_agencies_listing_assets()) {
            $this->enqueue_agencies_listing_assets();
        }

        if ($this->is_single_property || $this->has_elementor_widget) {
            wp_enqueue_style('hvnly-frontend-cards');
        }

        if ($this->is_agency_single) {
            wp_enqueue_style('hvnly-frontend-property-single');
            wp_enqueue_script('hvnly-frontend-property-single-gallery');
        }
        
        // Load archive styles on archive, taxonomy, search page, agent listings, shortcodes, or Elementor widget.
        if ($this->is_single_agent || $this->is_agency_single || $this->is_property_archive || $this->is_property_taxonomy || $this->is_property_search_page || $this->has_property_shortcode || $this->has_agencies_shortcode || $this->has_elementor_widget || $this->is_elementor_editor_mode()) {
            wp_enqueue_style('hvnly-frontend-property-ajax-filter');
            wp_enqueue_style('hvnly-frontend-property-archive');
            wp_enqueue_style('hvnly-frontend-property-map');
        }

        // Enqueue filter styles on pages with potential search forms
        if (is_page() || is_home() || is_front_page() || $this->is_property_archive || $this->is_property_taxonomy || $this->is_property_search_page || $this->has_property_shortcode || $this->has_agencies_shortcode || $this->has_elementor_widget) {
            wp_enqueue_style('hvnly-frontend-property-ajax-filter');
        }
        
        // Enqueue Elementor widget styles if needed
        if ($this->has_elementor_widget || $this->is_elementor_editor_mode()) {
            $this->enqueue_elementor_styles();
        }
        
        // =============================================
        // NEW: Enqueue Elementor Widget CSS (ADDED)
        // This ensures custom Elementor widget CSS loads
        // =============================================
        if ($this->has_elementor_widget || $this->is_elementor_editor_mode()) {
            $this->enqueue_elementor_widget_css();
        }
        
        // Always load responsive CSS last
        wp_enqueue_style('hvnly-frontend-property-responsive');

        // Haven Design System v2 skin (@since 3.7.0) — single property ONLY.
        // Enqueued last so it cascades after every legacy stylesheet; legacy
        // CSS stays enqueued underneath (dequeue this one handle = rollback).
        if ($this->is_single_property) {
            if (!wp_style_is('hvnly-frontend-single-property-v2', 'registered')) {
                wp_register_style(
                    'hvnly-frontend-single-property-v2',
                    HVNLYNAB_ASSETS_URL . '/frontend/css/single-property/single-property.css',
                    ['hvnly-frontend-property-single', 'hvnly-frontend-property-responsive'],
                    HVNLYNAB_VERSION
                );
            }
            wp_enqueue_style('hvnly-frontend-single-property-v2');

            // Hero image: the property's featured image is painted onto the
            // header's image LAYER (the container ::before). The gradient
            // scrim and all layout live in CSS; this only supplies the URL,
            // so no featured image simply falls back to a neutral tint.
            // Presentation only; no markup change.
            $hvnly_hero_url = get_the_post_thumbnail_url(get_queried_object_id(), 'large');
            if ($hvnly_hero_url) {
                $hvnly_hero_css = sprintf(
                    'body.hvnly-property-single .hvnly-property-single__header .hvnly-property-single__container::before{background-image:url(%s);}',
                    esc_url($hvnly_hero_url)
                );
                wp_add_inline_style('hvnly-frontend-single-property-v2', $hvnly_hero_css);
            }
        }
    }
    
    /**
     * Enqueue theme button isolation CSS late so global theme `button` /
     * `input[type=submit]` rules (Astra, Kadence, Hello Elementor, etc.)
     * cannot restyle Havenlytics controls. Safe no-op when base styles
     * were not registered (non-frontend contexts).
     *
     * @return void
     */
    public function enqueue_theme_button_isolation()
    {
        if (!wp_style_is('hvnly-frontend-default', 'registered') && !wp_style_is('hvnly-frontend-default', 'enqueued')) {
            return;
        }

        if (!wp_style_is('hvnly-frontend-theme-button-isolation', 'registered')) {
            wp_register_style(
                'hvnly-frontend-theme-button-isolation',
                HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-theme-button-isolation.css',
                ['hvnly-frontend-default', 'hvnly-frontend-components'],
                HVNLYNAB_VERSION
            );
        }

        // Only when Havenlytics frontend CSS is actually in play.
        if (
            wp_style_is('hvnly-frontend-default', 'enqueued')
            || wp_style_is('hvnly-frontend-components', 'enqueued')
            || wp_style_is('hvnly-frontend-property-single', 'enqueued')
            || wp_style_is('hvnly-frontend-property-ajax-filter', 'enqueued')
        ) {
            wp_enqueue_style('hvnly-frontend-theme-button-isolation');
        }
    }

    /**
     * Register all CSS files
     */
    private function register_styles()
    {
        $styles = [
            'hvnly-fontawesome-all-frontend' => [
                'path' => HVNLYNAB_ASSETS_URL . '/admin/css/fontawesome-all.min.css',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-default' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-default.css',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-theme-button-isolation' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-theme-button-isolation.css',
                'deps' => ['hvnly-frontend-default', 'hvnly-frontend-components'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-components' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-components.css',
                'deps' => ['hvnly-frontend-default'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-global-grid' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-global-grid.css',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-property-card-embed' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-cards/hvnly-frontend-property-card-embed.css',
                'deps' => ['hvnly-frontend-default', 'hvnly-frontend-components'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-property-ajax-filter' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-ajax-filter-search.css',
                'deps' => ['hvnly-frontend-property-card-embed', 'hvnly-frontend-property-map'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-property-single' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-single.css',
                'deps' => ['hvnly-frontend-default'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-mobile-contact-dock' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-mobile-contact-dock.css',
                'deps' => ['hvnly-frontend-default', 'hvnly-fontawesome-all-frontend'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-widgets' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-widgets.css',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-contact-agent' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-contact-agent.css',
                'deps' => ['hvnly-frontend-property-single', 'hvnly-frontend-widgets'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-property-archive' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-archive.css',
                'deps' => ['hvnly-frontend-global-grid'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-property-map' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-map.css',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-property-responsive' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-responsive.css',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-property-agent-widget' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-agent-widget.css',
                'deps' => ['hvnly-frontend-widgets'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-property-agents-archive' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-property-agents-archive.css',
                'deps' => ['hvnly-frontend-default', 'hvnly-frontend-components', 'hvnly-frontend-cards'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-frontend-cards' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/css/hvnly-frontend-cards.css',
                'deps' => ['hvnly-frontend-default', 'hvnly-frontend-components'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            'hvnly-elementor-property-agents' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-property-agents.css',
                'deps' => ['hvnly-frontend-property-agents-archive', 'hvnly-frontend-cards'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
            // =============================================
            // NEW: Register Elementor Widget CSS (ADDED)
            // =============================================
            'hvnly-elementor-widgets' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-elementor-widgets.css',
                'deps' => ['hvnly-frontend-property-ajax-filter'],
                'ver' => HVNLYNAB_VERSION,
                'media' => 'all'
            ],
        ];
        
        foreach ($styles as $handle => $style) {
            wp_register_style(
                $handle,
                $style['path'],
                $style['deps'],
                $style['ver'],
                $style['media']
            );
        }
    }
    
    /**
     * Enqueue Elementor styles
     */
    public function enqueue_elementor_styles() {
        if ($this->elementor_assets_enqueued && did_action('elementor/frontend/after_enqueue_styles')) {
            return;
        }

        wp_enqueue_style('hvnly-frontend-property-ajax-filter');
        wp_enqueue_style('hvnly-frontend-property-archive');
        wp_enqueue_style('hvnly-frontend-property-map');

        $css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-all-properties.css';
        $css_url  = HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-all-properties.css';

        if (file_exists($css_path)) {
            if (!wp_style_is('hvnly-elementor-archive-widget', 'registered')) {
                wp_register_style(
                    'hvnly-elementor-archive-widget',
                    $css_url,
                    ['hvnly-frontend-property-ajax-filter', 'hvnly-frontend-property-archive'],
                    HVNLYNAB_VERSION
                );
            }
            wp_enqueue_style('hvnly-elementor-archive-widget');
        }

        $widgets_css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-elementor-widgets.css';
        if (file_exists($widgets_css_path)) {
            if (!wp_style_is('hvnly-elementor-widgets', 'registered')) {
                wp_register_style(
                    'hvnly-elementor-widgets',
                    HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-elementor-widgets.css',
                    ['hvnly-frontend-property-ajax-filter', 'hvnly-elementor-archive-widget'],
                    HVNLYNAB_VERSION
                );
            }
            wp_enqueue_style('hvnly-elementor-widgets');
        }
    }

    /**
     * =============================================
     * NEW METHOD: Enqueue Elementor Widget CSS (ADDED)
     * This ensures our custom Elementor widget CSS loads
     * =============================================
     */
    public function enqueue_elementor_widget_css() {
        $this->enqueue_elementor_styles();
    }
    
    /**
     * Enqueue Elementor editor assets
     */
    public function enqueue_elementor_editor_assets() {
        $editor_css_path = HVNLYNAB_ASSETS_PATH . '/admin/css/elementor-editor.css';
        if (file_exists($editor_css_path)) {
            wp_enqueue_style(
                'hvnly-elementor-editor',
                HVNLYNAB_ASSETS_URL . '/admin/css/elementor-editor.css',
                ['elementor-editor'],
                HVNLYNAB_VERSION
            );
        }

        $brand_color = function_exists('hvnly_get_brand_color') ? hvnly_get_brand_color() : '#6C60FE';

        wp_add_inline_style('elementor-editor', '
            .elementor-control-hvnly_shortcode_info .elementor-control-field {
                background: #f0f6fc;
                padding: 12px;
                border-radius: 4px;
                border-left: 3px solid ' . esc_attr($brand_color) . ';
            }
            .elementor-control-hvnly_shortcode_info .elementor-control-title {
                color: #1e1e2f;
                font-weight: 600;
            }
            .elementor-panel .elementor-element .icon .eicon-gallery-grid {
                color: ' . esc_attr($brand_color) . ';
            }
        ');
    }
    
    /**
     * Register Elementor widget scripts
     */
    private function register_elementor_scripts() {
        // Check multiple possible paths for Elementor JS
        $js_path_v1 = HVNLYNAB_ASSETS_PATH . '/elementor/js/hvnly-all-properties.js';
        $js_url_v1 = HVNLYNAB_ASSETS_URL . '/elementor/js/hvnly-all-properties.js';
        
        $js_path_v2 = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/js/hvnly-all-properties.js';
        $js_url_v2 = HVNLYNAB_ASSETS_URL . '/frontend/elementor/js/hvnly-all-properties.js';
        
        $elementor_widget_deps = ['jquery', 'hvnly-frontend-property-ajax-filter-search'];

        if (file_exists($js_path_v1)) {
            wp_register_script(
                'hvnly-elementor-widgets',
                $js_url_v1,
                $elementor_widget_deps,
                HVNLYNAB_VERSION,
                true
            );
        } elseif (file_exists($js_path_v2)) {
            wp_register_script(
                'hvnly-elementor-widgets',
                $js_url_v2,
                $elementor_widget_deps,
                HVNLYNAB_VERSION,
                true
            );
        } else {
            // Fallback: Use existing AJAX search script for Elementor
            wp_register_script(
                'hvnly-elementor-widgets',
                HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/hvnly-frontend-property-ajax-filter-search.js',
                ['jquery'],
                HVNLYNAB_VERSION,
                true
            );
        }
        
        // Localize on live frontend only — editor preview localized in Elementor Bootstrap (full archive stack).
        if ( ! $this->is_elementor_editor_mode() ) {
            wp_localize_script('hvnly-elementor-widgets', 'hvnlyElementor', [
                'ajaxUrl'    => admin_url('admin-ajax.php'),
                'nonce'      => wp_create_nonce('hvnly_ajax_request'),
                'pluginUrl'  => HVNLYNAB_URL,
                'assetsUrl'  => HVNLYNAB_ASSETS_URL,
                'isEditMode' => false,
                'initDelay'  => 0,
                'i18n'       => [
                    'loading'      => __('Loading...', 'havenlytics'),
                    'loadMore'     => __('Load More Properties', 'havenlytics'),
                    'noProperties' => __('No properties found.', 'havenlytics'),
                    'error'        => __('An error occurred. Please try again.', 'havenlytics'),
                ],
            ]);
        }
    }
    
    /**
     * Enqueue Elementor widget assets (called when needed)
     */
    public function enqueue_elementor_assets() {
        if ($this->elementor_assets_enqueued) {
            return;
        }
        
        $this->register_elementor_scripts();
        
        // Only enqueue if not already enqueued by main flow
        if (!wp_script_is('hvnly-elementor-widgets', 'enqueued')) {
            wp_enqueue_script('hvnly-elementor-widgets');
        }
        
        // Ensure jQuery is available
        if (!wp_script_is('jquery', 'enqueued')) {
            wp_enqueue_script('jquery');
        }
        
        $this->elementor_assets_enqueued = true;
    }
    
    /**
     * Inject dynamic CSS from design settings
     * This method generates and injects dynamic CSS based on design settings
     *
     * @return void
     */
    private function inject_dynamic_css()
    {
        // Check if DynamicStyleGenerator class exists
        if (class_exists('HvnlyNab\Core\DynamicStyleGenerator')) {
            $generator = \HvnlyNab\Core\DynamicStyleGenerator::get_instance();
            $generator->inject_dynamic_styles();
        }
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts()
    {
        // Register all scripts first
        $this->register_scripts();

        // Map tile/provider assets before map controller so Leaflet/Google load first.
        if ( $this->should_load_maps() ) {
            $this->enqueue_map_assets();
            $this->apply_map_search_script_dependencies();
        }
        
        // Always enqueue jQuery
        wp_enqueue_script('jquery');
        
        // Property archive search stack — same order/handles as native archive template.
        if ( $this->should_enqueue_property_search_scripts() ) {
            wp_enqueue_script( 'hvnly-elementor-preview-guard' );
            wp_enqueue_script( 'hvnly-frontend-dom-resolver' );
            wp_enqueue_script( 'hvnly-frontend-property-map-search' );
            wp_enqueue_script( 'hvnly-frontend-property-ajax-utils' );
            wp_enqueue_script( 'hvnly-frontend-property-ajax-filters' );
            wp_enqueue_script( 'hvnly-frontend-property-ajax-search' );
            wp_enqueue_script( 'hvnly-frontend-property-ajax-pagination' );
            wp_enqueue_script( 'hvnly-frontend-property-ajax-url' );
            wp_enqueue_script( 'hvnly-frontend-property-ajax-ui' );
            wp_enqueue_script( 'hvnly-frontend-property-ajax-root' );
            wp_enqueue_script( 'hvnly-frontend-property-ajax-filter-search' );
            $this->localize_ajax_scripts_once();
        }

        // Enqueue single property scripts (without filter search)
        if ($this->is_single_property) {
            $this->enqueue_single_property_scripts();
            
            // Even if AJAX system is not loaded, we need map params for the single map
            $map_localize_params = $this->get_map_localize_params();
            wp_localize_script('hvnly-frontend-property-single-map', 'hvnly_map_params', $map_localize_params);
        }

        if ($this->is_single_agent) {
            wp_enqueue_script('hvnly-frontend-agent-single');
            wp_localize_script('hvnly-frontend-agent-single', 'hvnly_agent_single', [
                'i18n' => [
                    'listingsMatchDepartment' =>
                        /* translators: %d: Number of listings matching the selected department. */
                        __( '%d listing(s) match this department.', 'havenlytics' ),
                    'noListingsMatchDepartment' => __( 'No listings match this department.', 'havenlytics' ),
                ],
            ]);

            if (function_exists('hvnly_is_contact_agent_enabled') && hvnly_is_contact_agent_enabled()) {
                wp_enqueue_script('hvnly-frontend-contact-agent');

                if (function_exists('hvnly_localize_contact_agent_script')) {
                    hvnly_localize_contact_agent_script(0);
                }
            }
        }

        if ($this->is_property_archive_listing_page() || $this->page_has_elementor_widget('hvnly_property_agents') || $this->has_agents_shortcode || $this->is_elementor_editor_mode()) {
            wp_enqueue_script('hvnly-frontend-property-agents-archive');
        }

        if ($this->should_load_agencies_listing_assets()) {
            wp_enqueue_script('hvnly-frontend-property-agents-archive');
        }
        
        // Elementor widget helper script (archive stack loaded above when widget is present).
        if ( $this->has_elementor_widget || $this->is_elementor_editor_mode() ) {
            $this->enqueue_elementor_assets();
        }
    }

    /**
     * Ensure map-search waits for Leaflet (or Google) before executing.
     */
    private function apply_map_search_script_dependencies() {
        if ( ! wp_script_is( 'hvnly-frontend-property-map-search', 'registered' ) ) {
            return;
        }

        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $map_settings     = $settings_manager->get_map_settings();
        $map_provider     = isset( $map_settings['hvnly_map_provider'] ) ? $map_settings['hvnly_map_provider'] : 'leaflet';
        $api_key          = isset( $map_settings['hvnly_map_api_key'] ) ? $map_settings['hvnly_map_api_key'] : '';

        $deps = array( 'jquery', 'hvnly-frontend-dom-resolver' );

        if ( $map_provider === 'google' && ! empty( $api_key ) ) {
            $deps[] = 'hvnly-google-maps';
        } else {
            $deps[] = 'hvnly-frontend-property-leaflet';
            $deps[] = 'hvnly-frontend-property-leaflet-markercluster';
            $deps[] = 'hvnly-frontend-property-leaflet-fullscreen';
        }

        wp_scripts()->registered['hvnly-frontend-property-map-search']->deps = $deps;
    }

    /**
     * Whether to load the full property search JS stack (archive + Elementor widget).
     */
    private function should_enqueue_property_search_scripts(): bool {
        return $this->is_single_agent
            || $this->is_agency_single
            || $this->is_property_archive
            || $this->is_property_taxonomy
            || $this->is_property_search_page
            || $this->has_property_shortcode
            || $this->has_property_block
            || $this->has_elementor_widget
            || $this->is_elementor_editor_mode();
    }

    /**
     * Whether the current request is an agent/agency archive template page.
     *
     * @return bool
     */
    private function is_property_archive_listing_page(): bool {
        return $this->is_agent_archive
            || $this->is_agency_archive
            || $this->is_agency_single;
    }

    /**
     * Whether agencies archive markup assets should load (native, shortcode, or Elementor widget).
     *
     * @return bool
     */
    private function should_load_agencies_listing_assets(): bool {
        return $this->is_agency_archive
            || $this->has_agencies_shortcode
            || $this->page_has_elementor_widget('hvnly_property_agencies');
    }

    /**
     * Enqueue the same CSS stack as the HVN: Property Agency Elementor widget.
     *
     * @return void
     */
    private function enqueue_agencies_listing_assets(): void {
        if (class_exists('\HvnlyNab\Integrations\Elementor\Bootstrap')) {
            \HvnlyNab\Integrations\Elementor\Bootstrap::get_instance()->enqueue_agency_widget_assets_for_render();
            return;
        }

        wp_enqueue_style('hvnly-frontend-cards');
        wp_enqueue_style('hvnly-frontend-property-agents-archive');

        $agencies_css_path = HVNLYNAB_ASSETS_PATH . '/frontend/elementor/css/hvnly-property-agencies.css';
        if (file_exists($agencies_css_path)) {
            if (!wp_style_is('hvnly-elementor-property-agencies', 'registered')) {
                wp_register_style(
                    'hvnly-elementor-property-agencies',
                    HVNLYNAB_ASSETS_URL . '/frontend/elementor/css/hvnly-property-agencies.css',
                    ['hvnly-frontend-property-agents-archive', 'hvnly-frontend-cards'],
                    HVNLYNAB_VERSION
                );
            }
            wp_enqueue_style('hvnly-elementor-property-agencies');
        }
    }
    
    /**
     * Register all JavaScript files
     */
    private function register_scripts()
    {
        // Main property scripts
        $scripts = [
            'hvnly-frontend-property-map-search' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/hvnly-frontend-property-map-search.js',
                'deps' => ['jquery', 'hvnly-frontend-dom-resolver'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-ajax-filter-search' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/hvnly-frontend-property-ajax-filter-search.js',
                'deps' => ['jquery', 'hvnly-frontend-property-map-search'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-single-gallery' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-property-single-gallery.js',
                'deps' => ['jquery'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-single-video' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-property-single-video.js',
                'deps' => ['jquery'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-single-map' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-property-single-map.js',
                'deps' => ['jquery'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-single' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-property-single.js',
                'deps' => ['jquery'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-mobile-contact-dock' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-mobile-contact-dock.js',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-contact-agent' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-contact-agent.js',
                'deps' => ['jquery'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-agents-section-js' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-agents-section.js',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-agent-single' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-agent-single.js',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-agents-archive' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-frontend-property-agents-archive.js',
                'deps' => [],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true,
            ],
            'hvnly-elementor-preview-guard' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/hvnly-elementor-preview-guard.js',
                'deps' => ['jquery'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
        ];
        
        foreach ($scripts as $handle => $script) {
            wp_register_script(
                $handle,
                $script['path'],
                $script['deps'],
                $script['ver'],
                $script['in_footer']
            );
        }
        
        // Register modular AJAX scripts
        $this->register_modular_ajax_scripts();
    }
    
    /**
     * Register modular AJAX scripts
     */
    private function register_modular_ajax_scripts()
    {
        $ajax_modules = [
            'hvnly-frontend-dom-resolver' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-dom-resolver.js',
                'deps' => ['jquery'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-ajax-utils' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-utils.js',
                'deps' => ['jquery', 'hvnly-frontend-dom-resolver'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-ajax-filters' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-filters.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-ajax-search' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-search.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils', 'hvnly-frontend-property-ajax-filters'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-ajax-pagination' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-pagination.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-ajax-url' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-url.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-ajax-ui' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/modules/hvnly-frontend-property-ajax-ui.js',
                'deps' => ['jquery', 'hvnly-frontend-property-ajax-utils'],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ],
            'hvnly-frontend-property-ajax-root' => [
                'path' => HVNLYNAB_ASSETS_URL . '/frontend/js/property-search/hvnly-frontend-property-ajax-root.js',
                'deps' => [
                    'jquery',
                    'hvnly-frontend-property-ajax-utils',
                    'hvnly-frontend-property-ajax-filters',
                    'hvnly-frontend-property-ajax-search',
                    'hvnly-frontend-property-ajax-pagination',
                    'hvnly-frontend-property-ajax-url',
                    'hvnly-frontend-property-ajax-ui'
                ],
                'ver' => HVNLYNAB_VERSION,
                'in_footer' => true
            ]
        ];
        
        foreach ($ajax_modules as $handle => $module) {
            wp_register_script(
                $handle,
                $module['path'],
                $module['deps'],
                $module['ver'],
                $module['in_footer']
            );
        }
    }
    
    /**
     * Get AJAX parameters (cached)
     */
    private function get_ajax_params()
    {
        if ($this->ajax_params === null) {
            global $wp_query;
            
            // Get custom properties per page from settings
            $properties_per_page = 6;
            if (function_exists('hvnly_get_properties_per_page')) {
                $properties_per_page = hvnly_get_properties_per_page();
            }
            
            // Sanitize view_type from URL
            $view_type = 'grid';
            if (isset($_GET['view_type'])) {
                $view_type = sanitize_text_field(wp_unslash($_GET['view_type']));
                // Allow only specific values
                $allowed_view_types = ['grid', 'list', 'map'];
                if (!in_array($view_type, $allowed_view_types, true)) {
                    $view_type = 'grid';
                }
            }
            
            $this->ajax_params = [
                'ajax_url'           => esc_url(admin_url('admin-ajax.php')),
                'nonce'              => '', // Will be set in localization with fresh nonce
                'current_page'       => max(1, absint(get_query_var('paged'))),
                'max_pages'          => absint($wp_query->max_num_pages ?? 1),
                'found_posts'        => absint($wp_query->found_posts ?? 0),
                'post_count'         => absint($wp_query->post_count ?? 0),
                'per_page'           => absint($properties_per_page),
                'properties_per_page'=> absint($properties_per_page),
                'loading_text'       => esc_html__('Loading...', 'havenlytics'),
                'load_more_text'     => esc_html__('Load More Properties', 'havenlytics'),
                'error_text'         => esc_html__('An error occurred. Please try again.', 'havenlytics'),
                'error_message'      => esc_html__('An error occurred. Please try again.', 'havenlytics'),
                'success_message'    => esc_html__('Success!', 'havenlytics'),
                'success_text'       => esc_html__('Success!', 'havenlytics'),
                'unknown_error'      => esc_html__('Unknown error', 'havenlytics'),
                'failed_load_properties' => esc_html__('Failed to load properties:', 'havenlytics') . ' ',
                'load_error'         => esc_html__('An error occurred while loading properties. Please try again.', 'havenlytics'),
                'properties_found_text' => esc_html__('properties found', 'havenlytics'),
                'view_type'          => $view_type,
                'debug'              => ( function_exists( 'hvnly_is_debug_logging_enabled' ) && hvnly_is_debug_logging_enabled() ) ? '1' : '0',
                'is_archive'         => $this->is_property_archive,
                'is_taxonomy'        => $this->is_property_taxonomy,
                'has_shortcode'      => $this->has_property_shortcode 
            ];
        }
        
        return $this->ajax_params;
    }
    
    /**
     * Get map parameters (cached)
     */
    private function get_map_params()
    {
        if ($this->map_params === null) {
            $this->map_params = [
                'default_lat' => $this->sanitize_coordinate(get_option('hvnly_default_lat', '40.7128')),
                'default_lng' => $this->sanitize_coordinate(get_option('hvnly_default_lng', '-74.0060')),
                'zoom_level' => absint(get_option('hvnly_map_zoom', 12)),
                'cluster_markers' => (bool) get_option('hvnly_cluster_markers', true),
                'map_provider' => sanitize_text_field(get_option('hvnly_map_provider', 'leaflet')),
                'api_key' => sanitize_text_field(get_option('hvnly_map_api_key', '')),
            ];
        }
        
        return $this->map_params;
    }
    
    /**
     * Sanitize coordinate values (lat/lng)
     */
    private function sanitize_coordinate($coord)
    {
        $coord = sanitize_text_field($coord);
        if (is_numeric($coord)) {
            return (float) $coord;
        }
        return $coord;
    }
    
    /**
     * Get extended map parameters (cached)
     */
    private function get_map_localize_params()
    {
        if ($this->map_localize_params === null) {
            $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
            $map_settings = $settings_manager->get_map_settings();
            
            $map_provider = isset($map_settings['hvnly_map_provider']) ? $map_settings['hvnly_map_provider'] : 'leaflet';
            $api_key = isset($map_settings['hvnly_map_api_key']) ? $map_settings['hvnly_map_api_key'] : '';
            $enable_fallback = isset($map_settings['hvnly_enable_google_fallback']) ? (bool) $map_settings['hvnly_enable_google_fallback'] : true;
            
            $final_provider = $map_provider;
            if ($map_provider === 'google' && empty($api_key)) {
                $final_provider = 'leaflet';
            }
            
            $this->map_localize_params = [
                'provider' => $final_provider,
                'api_key' => $api_key,
                'enable_google_fallback' => $enable_fallback,
                'default_lat' => isset($map_settings['hvnly_default_lat']) ? (float) $map_settings['hvnly_default_lat'] : 51.514939,
                'default_lng' => isset($map_settings['hvnly_default_lng']) ? (float) $map_settings['hvnly_default_lng'] : -0.091839,
                'zoom_level' => isset($map_settings['hvnly_map_zoom']) ? absint($map_settings['hvnly_map_zoom']) : 12,
                'cluster_markers' => isset($map_settings['hvnly_cluster_markers']) ? (bool) $map_settings['hvnly_cluster_markers'] : true,
                'map_style' => isset($map_settings['hvnly_map_style']) ? sanitize_text_field($map_settings['hvnly_map_style']) : 'standard',
                'custom_marker' => isset($map_settings['hvnly_custom_marker']) ? (bool) $map_settings['hvnly_custom_marker'] : false,
                'marker_color' => function_exists('hvnly_get_marker_color') ? hvnly_get_marker_color() : (isset($map_settings['hvnly_marker_color']) ? sanitize_text_field($map_settings['hvnly_marker_color']) : '#6C60FE'),
                'marker_uses_brand_color' => function_exists('hvnly_marker_color_is_custom') ? !hvnly_marker_color_is_custom() : false,
                'marker_color_css' => function_exists('hvnly_get_marker_color_css') ? hvnly_get_marker_color_css() : (function_exists('hvnly_get_marker_color') ? hvnly_get_marker_color() : '#6C60FE'),
                'show_fullscreen' => isset($map_settings['hvnly_show_fullscreen']) ? (bool) $map_settings['hvnly_show_fullscreen'] : true,
                'show_zoom_control' => isset($map_settings['hvnly_show_zoom_control']) ? (bool) $map_settings['hvnly_show_zoom_control'] : true,
                'show_scroll_wheel' => isset($map_settings['hvnly_show_scroll_wheel']) ? (bool) $map_settings['hvnly_show_scroll_wheel'] : true,
                'google_map_type' => isset($map_settings['hvnly_google_map_type']) ? sanitize_text_field($map_settings['hvnly_google_map_type']) : 'roadmap',
                'osm_tile_url' => isset($map_settings['hvnly_osm_tile_url']) ? sanitize_text_field($map_settings['hvnly_osm_tile_url']) : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'osm_attribution' => isset($map_settings['hvnly_osm_attribution']) ? sanitize_textarea_field($map_settings['hvnly_osm_attribution']) : '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                'plugin_url' => esc_url(HVNLYNAB_URL),
                'assets_url' => esc_url(HVNLYNAB_ASSETS_URL),
                'is_single_property' => $this->is_single_property,
                'is_property_archive' => $this->is_property_archive,
                'debug' => ( function_exists( 'hvnly_is_debug_logging_enabled' ) && hvnly_is_debug_logging_enabled() ) ? '1' : '0',
                'i18n' => [
                    'requestTimedOut' => __( 'Request timed out. Please try again.', 'havenlytics' ),
                    'errorLoadingProperties' => __( 'Error loading properties: ', 'havenlytics' ),
                    'mapPlaceholderMissing' => __( 'Map placeholder not found on this page.', 'havenlytics' ),
                    'mapContainerMissing' => __( 'Map container not found. Please try again.', 'havenlytics' ),
                    'mapConfigError' => __( 'Configuration error: Unable to load map data.', 'havenlytics' ),
                    'mapContainerInitMissing' => __( 'Map container not found during initialization.', 'havenlytics' ),
                    'googleMapsMissing' => __( 'Google Maps library not loaded. Please refresh the page.', 'havenlytics' ),
                    'mapLibraryMissing' => __( 'Map library not loaded. Please refresh the page.', 'havenlytics' ),
                    'noPropertiesForMap' => __( 'No properties available for map view.', 'havenlytics' ),
                    'noValidLocations' => __( 'No properties with valid location data found.', 'havenlytics' ),
                    'noValidLocationsForSearch' => __( 'No properties with valid location data found for your search criteria.', 'havenlytics' ),
                    'foundWithoutCoordinates' =>
                        /* translators: %d: Number of properties found without map coordinates. */
                        __( 'Found %d properties, but none have map coordinates saved. Add latitude/longitude in each property\'s map location field.', 'havenlytics' ),
                    'noPropertiesMatchedMapFilters' => __( 'No properties matched your current search filters for map view.', 'havenlytics' ),
                    'unableToLoadMapProperties' => __( 'Unable to load map properties. Please refresh and try again.', 'havenlytics' ),
                    'errorInitMap' => __( 'Error initializing map: ', 'havenlytics' ),
                    'errorInitMarkers' => __( 'Error initializing map markers: ', 'havenlytics' ),
                    'untitledProperty' => __( 'Untitled Property', 'havenlytics' ),
                    'toggleFullscreen' => __( 'Toggle Fullscreen', 'havenlytics' ),
                    'exitFullscreen' => __( 'Exit Fullscreen', 'havenlytics' ),
                    'fullScreen' => __( 'Full Screen', 'havenlytics' ),
                    'exitFullScreen' => __( 'Exit Full Screen', 'havenlytics' ),
                    'invalidCoordinates' => __( 'Invalid coordinates', 'havenlytics' ),
                    'propertyAtLocation' => __( 'Property at this location', 'havenlytics' ),
                    'propertyLocation' => __( 'Property Location', 'havenlytics' ),
                    'propertiesAtLocation' => __( 'Properties at this location', 'havenlytics' ),
                    'propertyImage' => __( 'Property Image', 'havenlytics' ),
                    'dismissNotification' => __( 'Dismiss notification', 'havenlytics' ),
                    'loadingMap' => __( 'Loading map…', 'havenlytics' ),
                    'couldNotLoadProperties' => __( 'Couldn’t load properties.', 'havenlytics' ),
                    'retry' => __( 'Retry', 'havenlytics' ),
                    'mapUnavailable' => __( 'Map Unavailable', 'havenlytics' ),
                    'save' => __( 'Save', 'havenlytics' ),
                    'view' => __( 'View', 'havenlytics' ),
                    'viewProperty' => __( 'View Property', 'havenlytics' ),
                ],
            ];
        }
        
        return $this->map_localize_params;
    }
    
    /**
     * Enqueue modular AJAX system
     */
    private function enqueue_modular_ajax_system()
    {
        wp_enqueue_script('hvnly-frontend-dom-resolver');
        wp_enqueue_script('hvnly-frontend-property-map-search');
        wp_enqueue_script('hvnly-frontend-property-ajax-utils');
        wp_enqueue_script('hvnly-frontend-property-ajax-filters');
        wp_enqueue_script('hvnly-frontend-property-ajax-search');
        wp_enqueue_script('hvnly-frontend-property-ajax-pagination');
        wp_enqueue_script('hvnly-frontend-property-ajax-url');
        wp_enqueue_script('hvnly-frontend-property-ajax-ui');
        wp_enqueue_script('hvnly-frontend-property-ajax-root');
    }
    
    /**
     * Enqueue single property scripts
     */
    private function enqueue_single_property_scripts()
    {
        $property_id = get_the_ID();

        wp_enqueue_script('hvnly-frontend-property-single-gallery');
        wp_enqueue_script('hvnly-frontend-property-single-video');
        
        // Ensure Leaflet is loaded before map script
        $this->enqueue_leaflet_assets();
        // Local Font Awesome (already registered/enqueued site-wide; reinforce for single map icons).
        wp_enqueue_style('hvnly-fontawesome-all-frontend');
        wp_enqueue_script('hvnly-frontend-property-single-map');
        wp_enqueue_script('hvnly-frontend-property-single');

        if (class_exists('\HvnlyNab\Frontend\MobileContactDock') && \HvnlyNab\Frontend\MobileContactDock::should_enqueue_assets()) {
            wp_enqueue_style('hvnly-frontend-mobile-contact-dock');
            wp_enqueue_script('hvnly-frontend-mobile-contact-dock');
        }

        if (function_exists('hvnly_is_contact_agent_enabled') && hvnly_is_contact_agent_enabled()) {
            wp_enqueue_script('hvnly-frontend-contact-agent');

            if (function_exists('hvnly_localize_contact_agent_script')) {
                hvnly_localize_contact_agent_script(absint($property_id));
            }
        }

        // Localize property data for single property pages
        wp_localize_script('hvnly-frontend-property-single-gallery', 'hvnly_property_data', [
            'property_id' => absint($property_id),
            'gallery_images' => $this->get_property_gallery_images($property_id),
            'i18n' => [
                'propertyImage' => __( 'Property Image', 'havenlytics' ),
                'viewImage' =>
                    /* translators: %d: Image number in the gallery. */
                    __( 'View image %d', 'havenlytics' ),
                'goToSlide' =>
                    /* translators: 1: Current slide group number, 2: Total slide groups. */
                    __( 'Go to slide group %1$d of %2$d', 'havenlytics' ),
                'dismissNotification' => __( 'Dismiss notification', 'havenlytics' ),
                'videoCannotPlay' => __( 'Video cannot be played', 'havenlytics' ),
            ],
        ]);
    }
    
    /**
     * Get property gallery images
     */
    private function get_property_gallery_images($property_id)
    {
        return [];
    }
    
    /**
     * Localize scripts for AJAX - ONCE ONLY
     * All scripts share the same global data via the root script
     */
    private function localize_ajax_scripts_once()
    {
        if ($this->localization_done === true) {
            return;
        }

        $ajax_nonce = wp_create_nonce('hvnly_ajax_request');
        
        global $wp_query;

        $ajax_params = $this->get_ajax_params();
        $map_localize_params = $this->get_map_localize_params();
        
        $ajax_params['nonce'] = $ajax_nonce;
        
        $ajax_params['current_page'] = max(1, absint(get_query_var('paged')));
        $ajax_params['max_pages'] = absint($wp_query->max_num_pages ?? 1);
        $ajax_params['found_posts'] = absint($wp_query->found_posts ?? 0);
        $ajax_params['post_count'] = absint($wp_query->post_count ?? 0);

        if (wp_script_is('hvnly-frontend-property-ajax-root', 'enqueued')) {
            wp_localize_script('hvnly-frontend-property-ajax-root', 'hvnly_PROPERTY_ajax', $ajax_params);
            wp_localize_script('hvnly-frontend-property-ajax-root', 'hvnly_map_params', $map_localize_params);
        }
        
        if (wp_script_is('hvnly-frontend-property-map-search', 'enqueued')) {
            wp_localize_script('hvnly-frontend-property-map-search', 'hvnlyFrontend', [
                'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                'ajax_nonce' => $ajax_nonce,
                'plugin_url' => esc_url(HVNLYNAB_URL)
            ]);
            wp_localize_script('hvnly-frontend-property-map-search', 'hvnly_map_params', $map_localize_params);
            wp_localize_script('hvnly-frontend-property-map-search', 'hvnly_PROPERTY_ajax', $ajax_params);
        }
        
        $this->localization_done = true;
    }
    
    /**
     * Check if maps should be loaded
     */
    private function should_load_maps()
    {
        if (
            $this->is_property_archive
            || $this->is_property_taxonomy
            || $this->is_property_search_page
            || $this->has_property_shortcode
            || $this->has_property_block
            || $this->has_elementor_widget
            || $this->is_elementor_editor_mode()
        ) {
            return true;
        }
        
        if ($this->is_single_property) {
            $property_id = get_the_ID();
            
            $lat = $this->get_property_coordinate($property_id, 'latitude');
            $lng = $this->get_property_coordinate($property_id, 'longitude');
            
            return !empty($lat) && !empty($lng);
        }
        
        return false;
    }
    
    /**
     * Get property coordinate safely
     */
    private function get_property_coordinate($property_id, $type)
    {
        $meta_key = $type === 'latitude' ? 'latitude' : 'longitude';

        if ( function_exists( 'hvnly_resolve_field_meta' ) ) {
            $coord = hvnly_resolve_field_meta(
                (int) $property_id,
                array( 'group_type' => 'map', 'metaKey' => $meta_key )
            );
            if ( ! empty( $coord ) && is_numeric( $coord ) ) {
                return (float) $coord;
            }
        }

        return '';
    }
    
    /**
     * Enqueue map assets
     */
    private function enqueue_map_assets()
    {
        $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
        $map_settings = $settings_manager->get_map_settings();
        
        $map_provider = isset($map_settings['hvnly_map_provider']) ? $map_settings['hvnly_map_provider'] : 'leaflet';
        $api_key = isset($map_settings['hvnly_map_api_key']) ? $map_settings['hvnly_map_api_key'] : '';
        
        wp_enqueue_style(
            'hvnly-frontend-property-map',
            esc_url(HVNLYNAB_ASSETS_URL . '/frontend/css/property-search/hvnly-frontend-property-map.css'),
            [],
            HVNLYNAB_VERSION
        );

        if ($map_provider === 'google' && !empty($api_key)) {
            wp_enqueue_script(
                'hvnly-google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . urlencode($api_key) . '&libraries=places&loading=async&callback=initHvnlyMap',
                array(),
                HVNLYNAB_VERSION,
                ['strategy' => 'async']
            );
            
            wp_add_inline_script('hvnly-google-maps', '
                function initHvnlyMap() {
                    window.dispatchEvent(new Event("hvnlyGoogleMapsLoaded"));
                    if (window.HavenlyticsPropertyMap && typeof window.HavenlyticsPropertyMap.onGoogleMapsReady === "function") {
                        window.HavenlyticsPropertyMap.onGoogleMapsReady();
                    }
                }
            ');
            
        } else {
            $this->enqueue_leaflet_assets();
        }
    }
    
    /**
     * Enqueue Leaflet map assets
     */
    private function enqueue_leaflet_assets()
    {
        if ( ! wp_style_is( 'hvnly-frontend-property-leaflet', 'registered' ) ) {
            wp_register_style(
                'hvnly-frontend-property-leaflet',
                esc_url(HVNLYNAB_ASSETS_URL . '/frontend/lib/leaflet/css/leaflet.css'),
                [],
                '1.9.4'
            );
        }
        if ( ! wp_script_is( 'hvnly-frontend-property-leaflet', 'registered' ) ) {
            wp_register_script(
                'hvnly-frontend-property-leaflet',
                esc_url(HVNLYNAB_ASSETS_URL . '/frontend/lib/leaflet/js/leaflet.js'),
                [],
                '1.9.4',
                true
            );
        }

        wp_enqueue_style('hvnly-frontend-property-leaflet');
        wp_enqueue_script('hvnly-frontend-property-leaflet');

        if ( ! wp_style_is( 'hvnly-frontend-property-leaflet-markercluster', 'registered' ) ) {
            wp_register_style(
                'hvnly-frontend-property-leaflet-markercluster',
                esc_url(HVNLYNAB_ASSETS_URL . '/frontend/lib/leaflet/css/MarkerCluster.css'),
                [],
                '1.5.3'
            );
        }
        if ( ! wp_style_is( 'hvnly-frontend-property-leaflet-markercluster-default', 'registered' ) ) {
            wp_register_style(
                'hvnly-frontend-property-leaflet-markercluster-default',
                esc_url(HVNLYNAB_ASSETS_URL . '/frontend/lib/leaflet/css/MarkerCluster.Default.css'),
                [],
                '1.5.3'
            );
        }
        if ( ! wp_script_is( 'hvnly-frontend-property-leaflet-markercluster', 'registered' ) ) {
            wp_register_script(
                'hvnly-frontend-property-leaflet-markercluster',
                esc_url(HVNLYNAB_ASSETS_URL . '/frontend/lib/leaflet/js/leaflet.markercluster.js'),
                ['hvnly-frontend-property-leaflet'],
                '1.5.3',
                true
            );
        }

        wp_enqueue_style('hvnly-frontend-property-leaflet-markercluster');
        wp_enqueue_style('hvnly-frontend-property-leaflet-markercluster-default');
        wp_enqueue_script('hvnly-frontend-property-leaflet-markercluster');

        if ( ! wp_style_is( 'hvnly-frontend-property-leaflet-fullscreen', 'registered' ) ) {
            wp_register_style(
                'hvnly-frontend-property-leaflet-fullscreen',
                esc_url(HVNLYNAB_ASSETS_URL . '/frontend/lib/leaflet/css/Control.FullScreen.css'),
                [],
                '2.4.0'
            );
        }
        if ( ! wp_script_is( 'hvnly-frontend-property-leaflet-fullscreen', 'registered' ) ) {
            wp_register_script(
                'hvnly-frontend-property-leaflet-fullscreen',
                esc_url(HVNLYNAB_ASSETS_URL . '/frontend/lib/leaflet/js/Control.FullScreen.min.js'),
                ['hvnly-frontend-property-leaflet'],
                '2.4.0',
                true
            );
        }

        wp_enqueue_style('hvnly-frontend-property-leaflet-fullscreen');
        wp_enqueue_script('hvnly-frontend-property-leaflet-fullscreen');
    }
}