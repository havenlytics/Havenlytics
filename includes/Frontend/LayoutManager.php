<?php
/**
 * Layout Manager for Havenlytics
 *
 * Manages layout structures for property pages including sidebar visibility,
 * responsive behavior, and grid layouts. Follows WooCommerce/EDD architecture.
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
 * Layout Manager Class
 *
 * @since 2.0.0
 */
class LayoutManager {

    /**
     * The single instance of the class
     *
     * @var LayoutManager
     */
    protected static $instance = null;

    /**
     * Current layout context
     *
     * @var string
     */
    private $context = 'single';

    /**
     * Sidebar visibility status
     *
     * @var bool
     */
    private $has_sidebar = null;

    /**
     * Sidebar ID
     *
     * @var string
     */
    private $sidebar_id = 'hvnly_single_property_sidebar_widgets_area';

    /**
     * Layout configuration
     *
     * @var array
     */
    private $layout_config = [];

    /**
     * Responsive breakpoints
     *
     * @var array
     */
    private $breakpoints = [
        'mobile'  => 480,
        'tablet'  => 768,
        'desktop' => 992,
        'large'   => 1200,
    ];

    /**
     * Main Instance
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return LayoutManager
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Set up layout conditions early
        add_action('wp', [$this, 'setup_layout_conditions'], 5);
        
        // Filter layout grid classes
        add_filter('hvnly_layout_grid_classes', [$this, 'filter_layout_grid_classes'], 10, 2);
        
        // Filter main content classes
        add_filter('hvnly_main_content_classes', [$this, 'filter_main_content_classes'], 10, 2);
        
        // Filter sidebar classes
        add_filter('hvnly_sidebar_classes', [$this, 'filter_sidebar_classes'], 10, 2);
        
        // Control sidebar display
        add_filter('hvnly_should_display_sidebar', [$this, 'should_display_sidebar'], 10, 2);
        
        // Add body classes
        add_filter('body_class', [$this, 'add_body_classes']);
        
        // Add responsive sidebar content if needed
        add_action('hvnly_after_main_content', [$this, 'maybe_render_mobile_sidebar'], 20);
    }

    /**
     * Setup layout conditions
     */
    public function setup_layout_conditions() {
        if (is_singular('hvnly_property')) {
            $this->context = 'single';
            $this->sidebar_id = apply_filters('hvnly_single_property_sidebar_id', 'hvnly_single_property_sidebar_widgets_area');
            $this->has_sidebar = $this->check_sidebar_has_widgets();
            $this->layout_config = $this->get_layout_config('single');
        } elseif (is_post_type_archive('hvnly_property') || is_tax(get_object_taxonomies('hvnly_property'))) {
            $this->context = 'archive';
            $this->sidebar_id = apply_filters('hvnly_archive_sidebar_id', 'hvnly_archive_sidebar');
            $this->has_sidebar = $this->check_sidebar_has_widgets();
            $this->layout_config = $this->get_layout_config('archive');
        }
    }

    /**
     * Check if sidebar has active widgets
     *
     * @return bool
     */
    private function check_sidebar_has_widgets() {
        static $has_widgets = null;
        
        if (null === $has_widgets) {
            $has_widgets = is_active_sidebar($this->sidebar_id);
            
            // Allow filtering
            $has_widgets = apply_filters('hvnly_sidebar_has_widgets', $has_widgets, $this->sidebar_id, $this->context);
        }
        
        return $has_widgets;
    }

    /**
     * Get layout configuration for current context
     *
     * @param string $context Layout context
     * @return array
     */
    private function get_layout_config($context = 'single') {
        $configs = [
            'single' => [
                // Grid templates
                'grid_template_desktop'       => 'minmax(0, 2fr) minmax(0, 1fr)',
                'grid_template_desktop_no_sidebar' => 'minmax(0, 1fr)',
                'grid_template_tablet'        => '1fr',
                'grid_template_mobile'         => '1fr',
                
                // Column widths
                'main_width_desktop'           => '2fr',
                'sidebar_width_desktop'         => '1fr',
                'main_width_desktop_no_sidebar' => '100%',
                
                // Content max widths
                'main_content_max_width'        => '800px',
                'main_content_max_width_no_sidebar' => '900px',
                
                // Sidebar behavior
                'sidebar_sticky_desktop'        => true,
                'sidebar_sticky_top'             => '20px',
                
                // Responsive breakpoint thresholds
                'sidebar_hide_breakpoint'        => $this->breakpoints['tablet'],
                'sidebar_below_content_breakpoint' => $this->breakpoints['desktop'],
                
                // Mobile settings
                'show_sidebar_mobile'            => true,
                'sidebar_position_mobile'         => 'bottom', // 'bottom', 'top', 'hidden'
            ],
            'archive' => [
                'grid_template_desktop'          => '300px minmax(0, 1fr)',
                'grid_template_desktop_no_sidebar' => 'minmax(0, 1fr)',
                'grid_template_tablet'           => '1fr',
                'grid_template_mobile'            => '1fr',
                
                'main_width_desktop'              => '1fr',
                'sidebar_width_desktop'            => '300px',
                'main_width_desktop_no_sidebar'    => '100%',
                
                'sidebar_sticky_desktop'           => true,
                'sidebar_sticky_top'                => '30px',
                
                'sidebar_hide_breakpoint'           => $this->breakpoints['tablet'],
                'sidebar_below_content_breakpoint'  => $this->breakpoints['desktop'],
                
                'show_sidebar_mobile'               => true,
                'sidebar_position_mobile'            => 'bottom',
            ]
        ];

        $config = isset($configs[$context]) ? $configs[$context] : $configs['single'];
        
        // Override with settings if no sidebar
        if (!$this->has_sidebar) {
            $config['grid_template_desktop'] = $config['grid_template_desktop_no_sidebar'];
            $config['main_width_desktop'] = $config['main_width_desktop_no_sidebar'];
        }
        
        return apply_filters('hvnly_layout_config', $config, $context);
    }

    /**
     * Filter layout grid classes
     *
     * @param array  $classes Current classes
     * @param string $context Layout context
     * @return array
     */
    public function filter_layout_grid_classes($classes, $context = 'single') {
        // Ensure we have an array
        if (!is_array($classes)) {
            $classes = (array) $classes;
        }

        // Add base class
        $classes[] = 'hvnly-layout-grid';
        $classes[] = 'hvnly-layout-grid--' . $context;
        
        // Add modifier classes based on sidebar status
        if ($context === 'single') {
            if (!$this->has_sidebar) {
                $classes[] = 'hvnly-layout-grid--no-sidebar';
                $classes[] = 'hvnly-layout-grid--fullwidth';
            } else {
                $classes[] = 'hvnly-layout-grid--with-sidebar';
            }
        }

        // Add responsive classes
        $classes[] = 'hvnly-layout-grid--responsive';

        return array_unique($classes);
    }

    /**
     * Filter main content classes
     *
     * @param array  $classes Current classes
     * @param string $context Layout context
     * @return array
     */
    public function filter_main_content_classes($classes, $context = 'single') {
        if (!is_array($classes)) {
            $classes = (array) $classes;
        }

        $classes[] = 'hvnly-main-content';
        $classes[] = 'hvnly-main-content--' . $context;
        
        if ($context === 'single') {
            if (!$this->has_sidebar) {
                $classes[] = 'hvnly-main-content--centered';
                $classes[] = 'hvnly-main-content--fullwidth';
            } else {
                $classes[] = 'hvnly-main-content--with-sidebar';
            }
        }

        return array_unique($classes);
    }

    /**
     * Filter sidebar classes
     *
     * @param array  $classes Current classes
     * @param string $context Layout context
     * @return array
     */
    public function filter_sidebar_classes($classes, $context = 'single') {
        if (!is_array($classes)) {
            $classes = (array) $classes;
        }

        $classes[] = 'hvnly-sidebar';
        $classes[] = 'hvnly-sidebar--' . $context;
        
        if ($context === 'single') {
            if ($this->has_sidebar) {
                $classes[] = 'hvnly-sidebar--active';
                $classes[] = 'hvnly-sidebar--sticky-desktop';
                
                // Add responsive position class
                $position = $this->layout_config['sidebar_position_mobile'] ?? 'bottom';
                $classes[] = 'hvnly-sidebar--mobile-' . $position;
            } else {
                $classes[] = 'hvnly-sidebar--hidden';
            }
        }

        return array_unique($classes);
    }

    /**
     * Determine if sidebar should be displayed
     *
     * @param bool   $should_display Current value
     * @param string $context        Layout context
     * @return bool
     */
    public function should_display_sidebar($should_display, $context = 'single') {
        if ($context === 'single') {
            return $this->has_sidebar;
        }
        return $should_display;
    }

    /**
     * Add layout-specific body classes
     *
     * @param array $classes Body classes
     * @return array
     */
    public function add_body_classes($classes) {
        if (is_singular('hvnly_property')) {
            $classes[] = 'hvnly-layout-single';
            
            if (!$this->has_sidebar) {
                $classes[] = 'hvnly-layout-no-sidebar';
                $classes[] = 'hvnly-layout-fullwidth';
            } else {
                $classes[] = 'hvnly-layout-with-sidebar';
                
                // Add mobile sidebar position class
                $position = $this->layout_config['sidebar_position_mobile'] ?? 'bottom';
                $classes[] = 'hvnly-sidebar-mobile-' . $position;
            }
            
            // Add responsive breakpoint classes for CSS targeting
            $classes[] = 'hvnly-breakpoints-loaded';
            
        } elseif (is_post_type_archive('hvnly_property') || is_tax(get_object_taxonomies('hvnly_property'))) {
            $classes[] = 'hvnly-layout-archive';
        }
        
        return $classes;
    }

    /**
     * Maybe render sidebar at bottom on mobile if configured
     */
    public function maybe_render_mobile_sidebar() {
        if (!is_singular('hvnly_property') || !$this->has_sidebar) {
            return;
        }

        $position = $this->layout_config['sidebar_position_mobile'] ?? 'bottom';
        $show_mobile = $this->layout_config['show_sidebar_mobile'] ?? true;

        // Only render if configured to show at bottom
        // if ($show_mobile && $position === 'bottom') {
        //     echo '<div class="hvnly-sidebar-mobile-container hvnly-sidebar-mobile-bottom">';
        //     echo '<div class="hvnly-sidebar-mobile-inner">';
        //     echo '<h3 class="hvnly-sidebar-mobile-title">' . esc_html__('More Information', 'havenlytics') . '</h3>';
        //     do_action('hvnly_single_property_sidebar');
        //     echo '</div>';
        //     echo '</div>';
        // }
    }

    /**
     * Get sidebar status
     *
     * @return bool
     */
    public function has_sidebar() {
        return $this->has_sidebar;
    }

    /**
     * Get current context
     *
     * @return string
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Get layout configuration
     *
     * @return array
     */
    public function get_config() {
        return $this->layout_config;
    }

    /**
     * Get breakpoints
     *
     * @return array
     */
    public function get_breakpoints() {
        return apply_filters('hvnly_layout_breakpoints', $this->breakpoints);
    }

    /**
     * Reset layout manager state
     */
    public function reset() {
        $this->context = 'single';
        $this->has_sidebar = null;
        $this->sidebar_id = 'hvnly_single_property_sidebar_widgets_area';
        $this->layout_config = [];
    }
}