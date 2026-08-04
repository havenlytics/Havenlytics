<?php
/**
 * Dynamic Style Generator for Havenlytics
 *
 * Generates dynamic CSS from settings with cache support.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @since       2.1.0
 */

namespace HvnlyNab\Core;

use HvnlyNab\Api\Type\Settings\DefaultSettingsData;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class DynamicStyleGenerator
{
    private static $instance = null;
    private $settings_manager = null;
    const CACHE_KEY = 'hvnly_dynamic_css';
    private $css_injected = false;
    
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct()
    {
        $this->settings_manager = SettingsManager::get_instance();
        add_action('wp_enqueue_scripts', [$this, 'inject_dynamic_styles'], 999);
        add_action('hvnly_settings_updated', [$this, 'clear_css_cache']);
        add_action('update_option_hvnly_plugin_settings', [$this, 'clear_css_cache']);
    }
    
    public function clear_css_cache()
    {
        delete_transient(self::CACHE_KEY);
        $this->css_injected = false;
    }
    
    private function get_design_settings()
    {
        return $this->settings_manager->get_design_settings();
    }

    /**
     * Factory property defaults (container widths included).
     * Sole numeric SOT: DefaultSettingsData — DSG does not invent width literals.
     *
     * @return array<string, mixed>
     */
    private function get_factory_properties_defaults()
    {
        if ( class_exists( DefaultSettingsData::class ) ) {
            return DefaultSettingsData::get_default_properties_settings();
        }

        return array();
    }
    
    private function get_properties_settings()
    {
        $settings = $this->settings_manager->load_settings();
        $defaults = $this->get_factory_properties_defaults();
        $properties_settings = isset($settings['properties']) && is_array($settings['properties'])
            ? $settings['properties']
            : array();

        // Database wins; factory fills missing keys only.
        return wp_parse_args($properties_settings, $defaults);
    }
    
    public function generate_css()
    {
        $cached_css = get_transient(self::CACHE_KEY);
        if ($cached_css !== false) {
            return $cached_css;
        }
        
        $design = $this->get_design_settings();
        $properties = $this->get_properties_settings();
        $factory = $this->get_factory_properties_defaults();
        
        // Design values
        $brand_color = $design['hvnly_brandColor'] ?? '#6C60FE';
        $secondary_color = $design['hvnly_secondaryColor'] ?? '#764ba2';
        $link_color = $design['hvnly_linkColor'] ?? '#1E1E2F';
        $link_hover_color = $design['hvnly_linkHoverColor'] ?? '#764ba2';
        $title_color = $design['hvnly_titleColor'] ?? '#1E1E2F';
        $primary_text_color = $design['hvnly_primaryTextColor'] ?? '#1E1E2F';
        $secondary_text_color = $design['hvnly_secondaryTextColor'] ?? '#555555';
        $body_color = $design['hvnly_bodyColor'] ?? '#333333';
        $body_font_size = intval($design['hvnly_bodyFontSize'] ?? 16);
        $body_font_weights = $design['hvnly_bodyFontWeights'] ?? '400';
        $title_font_weights = $design['hvnly_titleFontWeights'] ?? '600';
        
        // Container widths from DB-merged properties; missing keys use factory only.
        $container_xs   = $properties['hvnly_container_width_xs'] ?? ( $factory['hvnly_container_width_xs'] ?? '' );
        $container_sm   = $properties['hvnly_container_width_sm'] ?? ( $factory['hvnly_container_width_sm'] ?? '' );
        $container_md   = $properties['hvnly_container_width_md'] ?? ( $factory['hvnly_container_width_md'] ?? '' );
        $container_lg   = $properties['hvnly_container_width_lg'] ?? ( $factory['hvnly_container_width_lg'] ?? '' );
        $container_xl   = $properties['hvnly_container_width_xl'] ?? ( $factory['hvnly_container_width_xl'] ?? '' );
        $container_xxl  = $properties['hvnly_container_width_xxl'] ?? ( $factory['hvnly_container_width_xxl'] ?? '' );
        $container_xxxl = $properties['hvnly_container_width_xxxl'] ?? ( $factory['hvnly_container_width_xxxl'] ?? '' );
        $container_4k   = $properties['hvnly_container_width_4k'] ?? ( $factory['hvnly_container_width_4k'] ?? '' );
        
        // Build CSS
        $css = $this->generate_root_variables(
            $brand_color, $secondary_color, $link_color, $link_hover_color,
            $title_color, $primary_text_color, $secondary_text_color,
            $body_color, $body_font_size,
            $container_xs, $container_sm, $container_md, $container_lg,
            $container_xl, $container_xxl, $container_xxxl, $container_4k
        );
        
        // Add container width CSS rules
        $css .= $this->generate_container_width_css(
            $container_xs, $container_sm, $container_md, $container_lg,
            $container_xl, $container_xxl, $container_xxxl, $container_4k
        );

        $css .= $this->generate_archive_container_width_css($properties);
        $css .= $this->generate_search_archive_layout_css();
        
        $css .= $this->generate_typography_styles($body_font_size, $body_color, $body_font_weights, $title_font_weights);
        $css .= $this->generate_link_styles($link_color, $link_hover_color);
        $css .= $this->generate_button_styles($brand_color, $secondary_color);
        $css .= $this->generate_card_styles();
        $css .= $this->generate_utility_classes();
        
        $css = $this->minify_css($css);
        set_transient(self::CACHE_KEY, $css, DAY_IN_SECONDS);
        
        return $css;
    }
    
    /**
     * Generate container width CSS rules directly
     * This ensures the CSS works even if variables are not applied correctly
     */
    private function generate_container_width_css(
        $container_xs, $container_sm, $container_md, $container_lg,
        $container_xl, $container_xxl, $container_xxxl, $container_4k
    ) {
        return "
        /* Base container */
        .hvnly-container {
            width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
            transition: max-width 0.3s ease;
        }
        
        /* Extra Small devices (0 - 479px) */
        @media (max-width: 479px) {
            .hvnly-container {
                max-width: {$container_xs};
                padding: 0 0.75rem;
            }
        }
        
        /* Small devices (480px - 575px) */
        @media (min-width: 480px) and (max-width: 575px) {
            .hvnly-container {
                max-width: {$container_sm};
                padding: 0 0.75rem;
            }
        }
        
        /* Medium small devices (576px - 767px) */
        @media (min-width: 576px) and (max-width: 767px) {
            .hvnly-container {
                max-width: {$container_sm};
                padding: 0 0.75rem;
            }
        }
        
        /* Tablet devices (768px - 991px) */
        @media (min-width: 768px) and (max-width: 991px) {
            .hvnly-container {
                max-width: {$container_md};
                padding: 0 0.5rem;
            }
        }
        
        /* Laptop devices (992px - 1199px) */
        @media (min-width: 992px) and (max-width: 1199px) {
            .hvnly-container {
                max-width: {$container_lg};
                padding: 0 0.5rem;
            }
        }
        
        /* Desktop devices (1200px - 1439px) */
        @media (min-width: 1200px) and (max-width: 1439px) {
            .hvnly-container {
                max-width: {$container_xl};
                padding: 0 0.5rem;
            }
        }
        
        /* Large Desktop (1440px - 1919px) */
        @media (min-width: 1440px) and (max-width: 1919px) {
            .hvnly-container {
                max-width: {$container_xxl};
                padding: 0 0.5rem;
            }
        }
        
        /* HD / Full HD (1920px - 2559px) */
        @media (min-width: 1920px) and (max-width: 2559px) {
            .hvnly-container {
                max-width: {$container_xxxl};
                padding: 0 0.5rem;
            }
        }
        
        /* 4K / Ultra HD (2560px and above) */
        @media (min-width: 2560px) {
            .hvnly-container {
                max-width: {$container_4k};
                padding: 0 1rem;
            }
        }
        
        /* Container Fluid */
        .hvnly-container-fluid {
            width: 100%;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        @media (max-width: 479px) {
            .hvnly-container-fluid {
                padding: 0 0.75rem;
            }
        }

        {$this->generate_template_container_max_width_css(
            $container_xs, $container_sm, $container_md, $container_lg,
            $container_xl, $container_xxl, $container_xxxl, $container_4k
        )}";
    }

    /**
     * Resolve archive max-width from properties settings.
     *
     * @param array<string, mixed> $properties Properties settings group.
     * @return string Empty when default (no override).
     */
    private function resolve_archive_container_max_width(array $properties)
    {
        $preset = isset($properties['hvnly_archive_container_width'])
            ? sanitize_key((string) $properties['hvnly_archive_container_width'])
            : 'default';

        if ('default' === $preset || '' === $preset) {
            return '';
        }

        if ('full' === $preset) {
            return '100%';
        }

        if ('custom' === $preset) {
            $custom = absint($properties['hvnly_archive_container_width_custom'] ?? 0);
            if ($custom < 320) {
                return '';
            }
            return $custom . 'px';
        }

        $allowed = ['1140', '1200', '1240', '1320', '1400'];
        if (in_array($preset, $allowed, true)) {
            return $preset . 'px';
        }

        return '';
    }

    /**
     * Archive-only container width override (property archive, taxonomies, search results).
     *
     * @param array<string, mixed> $properties Properties settings group.
     * @return string
     */
    private function generate_archive_container_width_css(array $properties)
    {
        $max_width = $this->resolve_archive_container_max_width($properties);
        if ('' === $max_width) {
            return '';
        }

        $selectors = implode(
            ', ',
            array(
                'body.hvnly-property-archive .hvnly-container',
                'body.hvnly-archive-property .hvnly-container',
                'body.hvnly-layout-archive .hvnly-container',
                'body.hvnly-shortcode-property-search .hvnly-container',
                'body.hvnly-property-archive .hvnly-property--archive',
            )
        );

        if ('100%' === $max_width) {
            return "
        /* Archive container width — full width */
        {$selectors} {
            max-width: {$max_width};
        }";
        }

        return "
        /* Archive container width — preset/custom */
        @media (min-width: 768px) {
            {$selectors} {
                max-width: {$max_width};
            }
        }";
    }

    /**
     * Archive/search grid columns and filter sidebar position from Search Result settings.
     */
    private function generate_search_archive_layout_css()
    {
        if ( null === $this->settings_manager ) {
            $this->settings_manager = SettingsManager::get_instance();
        }

        $columns = $this->settings_manager->get_archive_grid_columns();
        $layout  = $this->settings_manager->get_search_sidebar_layout();

        $css = "
        @media (min-width: 992px) {
            body.hvnly-property-archive .hvnly-property-grid-view.hvnly-grid-view,
            body.hvnly-archive-property .hvnly-property-grid-view.hvnly-grid-view,
            body.hvnly-layout-archive .hvnly-property-grid-view.hvnly-grid-view,
            body.hvnly-shortcode-property-search .hvnly-property-grid-view.hvnly-grid-view {
                grid-template-columns: repeat({$columns}, 1fr) !important;
            }
        }";

        if ( 'RIGHT' === $layout ) {
            $css .= "
        @media (min-width: 992px) {
            .hvnly-with-sidebar__contents__grids.hvnly-search-sidebar-right {
                grid-template-columns: 1fr 320px !important;
            }
            .hvnly-with-sidebar__contents__grids.hvnly-search-sidebar-right .hvnly-property-filter-sidebar {
                order: 2;
            }
        }";
        }

        return $css;
    }

    /**
     * Responsive max-width for single/archive template containers (settings-driven).
     */
    private function generate_template_container_max_width_css(
        $container_xs, $container_sm, $container_md, $container_lg,
        $container_xl, $container_xxl, $container_xxxl, $container_4k
    ) {
        $selectors = implode(
            ', ',
            array(
                '.hvnly-property-single__container',
                '.hvnly-agent-single__container',
                '.hvnly-property--archive',
            )
        );

        return "
        /* Template containers — same breakpoints as .hvnly-container */
        @media (max-width: 479px) {
            {$selectors} { max-width: {$container_xs}; }
        }
        @media (min-width: 480px) and (max-width: 575px) {
            {$selectors} { max-width: {$container_sm}; }
        }
        @media (min-width: 576px) and (max-width: 767px) {
            {$selectors} { max-width: {$container_sm}; }
        }
        @media (min-width: 768px) and (max-width: 991px) {
            {$selectors} { max-width: {$container_md}; }
        }
        @media (min-width: 992px) and (max-width: 1199px) {
            {$selectors} { max-width: {$container_lg}; }
        }
        @media (min-width: 1200px) and (max-width: 1439px) {
            {$selectors} { max-width: {$container_xl}; }
        }
        @media (min-width: 1440px) and (max-width: 1919px) {
            {$selectors} { max-width: {$container_xxl}; }
        }
        @media (min-width: 1920px) and (max-width: 2559px) {
            {$selectors} { max-width: {$container_xxxl}; }
        }
        @media (min-width: 2560px) {
            {$selectors} { max-width: {$container_4k}; }
        }";
    }
    
    private function hex_to_rgb_components($hex)
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '108, 96, 254';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "{$r}, {$g}, {$b}";
    }

    private function generate_root_variables(
        $brand_color, $secondary_color, $link_color, $link_hover_color,
        $title_color, $primary_text_color, $secondary_text_color,
        $body_color, $body_font_size,
        $container_xs, $container_sm, $container_md,
        $container_lg, $container_xl, $container_xxl,
        $container_xxxl, $container_4k
    ) {
        $primary_rgb   = $this->hex_to_rgb_components($brand_color);
        $secondary_rgb = $this->hex_to_rgb_components($secondary_color);

        return ":root {
            --hvnly-brand-primary: {$brand_color};
            --hvnly-brand-secondary: {$secondary_color};
            --hvnly-primary-color: {$brand_color};
            --hvnly-secondary-color: {$secondary_color};
            --hvnly-primary-rgb: {$primary_rgb};
            --hvnly-secondary-rgb: {$secondary_rgb};
            --hvnly-text-color: {$primary_text_color};
            --hvnly-link-color: {$link_color};
            --hvnly-link-hover-color: {$link_hover_color};
            --hvnly-title-color: {$title_color};
            --hvnly-text-primary: {$primary_text_color};
            --hvnly-text-secondary: {$secondary_text_color};
            --hvnly-body-color: {$body_color};
            --hvnly-body-font-size: {$body_font_size}px;
            --hvnly-container-xs: {$container_xs};
            --hvnly-container-sm: {$container_sm};
            --hvnly-container-md: {$container_md};
            --hvnly-container-lg: {$container_lg};
            --hvnly-container-xl: {$container_xl};
            --hvnly-container-xxl: {$container_xxl};
            --hvnly-container-xxxl: {$container_xxxl};
            --hvnly-container-4k: {$container_4k};
        }";
    }
    
    private function generate_typography_styles($body_font_size, $body_color, $body_font_weights, $title_font_weights)
    {
        return "
        body,
        .hvnly-property-card,
        .hvnly-property-details,
        .hvnly-search-results {
            color: {$body_color};
            font-size: {$body_font_size}px;
        }
        
        h1, h2, h3, h4, h5, h6,
        .hvnly-property-title,
        .hvnly-section-title {
            color: var(--hvnly-title-color);
        }
        
        body {
            font-weight: {$body_font_weights};
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-weight: {$title_font_weights};
        }";
    }
    
    private function generate_link_styles($link_color, $link_hover_color)
    {
        return "
        a,
        .hvnly-link {
            color: {$link_color};
            transition: color 0.3s ease;
        }
        
        a:hover,
        .hvnly-link:hover {
            color: {$link_hover_color};
        }";
    }
    
    private function generate_button_styles($brand_color, $secondary_color)
    {
        return "
        .hvnly-btn-primary,
        .hvnly-button-primary,
        .hvnly-submit-btn {
            background: {$brand_color};
            border-color: {$brand_color};
        }
        
        .hvnly-btn-primary:hover,
        .hvnly-button-primary:hover,
        .hvnly-submit-btn:hover {
            background: {$secondary_color};
            border-color: {$secondary_color};
        }
        
        .hvnly-price {
            color: var(--hvnly-brand-primary);
        }
        
        .hvnly-badge,
        .hvnly-featured-badge {
            background: var(--hvnly-brand-primary);
        }";
    }
    
    private function generate_card_styles()
    {
        return "
        .hvnly-card,
        .hvnly-property-card {
            border-radius: var(--hvnly-border-radius-md);
            box-shadow: var(--hvnly-shadow-card);
        }
        
        .hvnly-property-price {
            color: var(--hvnly-brand-primary);
        }";
    }
    
    private function generate_utility_classes()
    {
        return "
        .hvnly-text-primary { color: var(--hvnly-brand-primary); }
        .hvnly-text-secondary { color: var(--hvnly-brand-secondary); }
        .hvnly-text-muted { color: var(--hvnly-text-secondary); }
        .hvnly-bg-primary { background: var(--hvnly-brand-primary); }
        .hvnly-bg-secondary { background: var(--hvnly-brand-secondary); }
        .hvnly-border-primary { border-color: var(--hvnly-brand-primary); }
        .hvnly-border-secondary { border-color: var(--hvnly-brand-secondary); }";
    }
    
    private function minify_css($css)
    {
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
        $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
        return trim($css);
    }
    
    public function inject_dynamic_styles()
    {
        if (is_admin()) {
            return;
        }
        
        if ($this->css_injected) {
            return;
        }
        
        if (!wp_style_is('hvnly-frontend-default', 'registered')) {
            return;
        }
        
        $css = $this->generate_css();
        
        if (!empty($css)) {
            wp_add_inline_style('hvnly-frontend-default', $css);
            $this->css_injected = true;
        }
    }
}