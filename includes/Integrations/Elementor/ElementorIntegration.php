<?php
/**
 * Elementor Integration for Havenlytics
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Elementor
 * @since       3.0.0
 */

namespace HvnlyNab\Integrations\Elementor;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Elementor Integration Class
 *
 * Handles registration of Elementor widgets and conditional loading.
 * Only initializes when Elementor plugin is active.
 * This class is designed to be loaded by the Frontend service manager.
 *
 * @since 3.0.0
 */
final class ElementorIntegration {

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Minimum Elementor version required
     */
    private const MIN_ELEMENTOR_VERSION = '3.5.0';

    /**
     * Whether integration is initialized
     *
     * @var bool
     */
    private $initialized = false;

    /**
     * Get singleton instance
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
     * Constructor - private for singleton
     */
    private function __construct() {
        // Only initialize if Elementor is active
        add_action('plugins_loaded', array( $this, 'init' ), 5);
    }

    /**
     * Initialize the integration
     */
    public function init(): void {
        if ($this->initialized) {
            return;
        }

        // Check if Elementor is active
        if ( ! did_action('elementor/loaded') && ! class_exists('\Elementor\Plugin')) {
            add_action('admin_notices', array( $this, 'missing_elementor_notice' ));
            return;
        }

        // Version compatibility check
        if (defined('ELEMENTOR_VERSION') && ! version_compare(ELEMENTOR_VERSION, self::MIN_ELEMENTOR_VERSION, '>=')) {
            add_action('admin_notices', array( $this, 'elementor_version_notice' ));
            return;
        }

        // Initialize Elementor integration
        $this->register_hooks();

        $this->initialized = true;
    }

    /**
     * Register all Elementor hooks
     */
    private function register_hooks(): void {
        // Register widget categories
        add_action('elementor/elements/categories_registered', array( $this, 'register_categories' ));

        // Register widgets
        add_action('elementor/widgets/register', array( $this, 'register_widgets' ));

        // Register Elementor Pro theme locations
        add_action('elementor/theme/register_locations', array( $this, 'register_theme_locations' ));

        // Register AJAX handlers
        $this->register_ajax_handlers();
    }

    /**
     * Register widget categories
     *
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function register_categories( $elements_manager ): void {
        $elements_manager->add_category(
            'havenlytics',
            array(
                'title' => __('Havenlytics', 'havenlytics'),
                'icon'  => 'eicon-home',
            )
        );
    }

    /**
     * Register Elementor widgets
     *
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets( $widgets_manager ): void {
        $widgets = array(
            'HvnlyAllPropertiesWidget.php'     => '\HvnlyNab\Integrations\Elementor\Widgets\HvnlyAllPropertiesWidget',
            'HvnlyPropertyAgentsWidget.php'     => '\HvnlyNab\Integrations\Elementor\Widgets\HvnlyPropertyAgentsWidget',
            'HvnlyPropertyAgenciesWidget.php' => '\HvnlyNab\Integrations\Elementor\Widgets\HvnlyPropertyAgenciesWidget',
        );

        foreach ($widgets as $file => $class) {
            $widget_file = HVNLYNAB_INCLUDES . '/Integrations/Elementor/Widgets/' . $file;

            if ( ! file_exists($widget_file)) {
                continue;
            }

            require_once $widget_file;

            if (class_exists($class)) {
                $widgets_manager->register(new $class());
            }
        }
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers(): void {
        $ajax_handler_file = HVNLYNAB_INCLUDES . '/Integrations/Elementor/AjaxHandler.php';
        if (file_exists($ajax_handler_file)) {
            require_once $ajax_handler_file;
        }
    }

    /**
     * Register theme locations for Elementor Pro
     *
     * @param \ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager $location_manager
     */
    public function register_theme_locations( $location_manager ): void {
        if ( ! class_exists('\ElementorPro\Modules\ThemeBuilder\Classes\Locations_Manager')) {
            return;
        }

        $location_manager->register_core_location('single');
        $location_manager->register_core_location('archive');
    }

    /**
     * Display admin notice if Elementor is missing
     */
    public function missing_elementor_notice(): void {
        if ( ! current_user_can('activate_plugins')) {
            return;
        }

        $elementor_install_url = wp_nonce_url(
            self_admin_url('update.php?action=install-plugin&plugin=elementor'),
            'install-plugin_elementor'
        );

        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s <a href="%s">%s</a> %s</p></div>',
            esc_html__('Havenlytics:', 'havenlytics'),
            esc_html__('Elementor integration requires', 'havenlytics'),
            esc_url($elementor_install_url),
            esc_html__('Elementor', 'havenlytics'),
            esc_html__('to be installed and activated.', 'havenlytics')
        );
    }

    /**
     * Display admin notice for Elementor version
     */
    public function elementor_version_notice(): void {
        if ( ! current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>%s</strong> %s %s+ %s</p></div>',
            esc_html__('Havenlytics:', 'havenlytics'),
            esc_html__('Elementor version', 'havenlytics'),
            esc_html(self::MIN_ELEMENTOR_VERSION),
            esc_html__('is required for full compatibility.', 'havenlytics')
        );
    }

    /**
     * Check if Elementor integration is active
     *
     * @return bool
     */
    public function is_active(): bool {
        return $this->initialized;
    }
}
