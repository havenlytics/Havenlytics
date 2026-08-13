<?php
/**
 * Simple Plugin Conflict Checker
 *
 * Checks for active real estate plugins and displays a notice if found.
 * Provides one-click deactivation buttons for conflicting plugins.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @since       2.0.0
 */

namespace HvnlyNab\Core;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Class ConflictChecker
 *
 * Simple conflict detection with admin notices only.
 * No menus, no settings pages - just a clean notice.
 */
class ConflictChecker {

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * List of known conflicting plugins
     *
     * @var array
     */
    private $conflicting_plugins = array();

    /**
     * Active conflicting plugins found
     *
     * @var array
     */
    private $active_conflicts = array();

    /**
     * Script handle for inline JS
     *
     * @var string
     */
    private $script_handle = 'hvnly-conflict-checker';

    /**
     * Style handle for inline CSS
     *
     * @var string
     */
    private $style_handle = 'hvnly-conflict-checker-styles';

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
     * Private constructor
     */
    private function __construct() {
        $this->define_conflicting_plugins();
        $this->scan_for_conflicts();
        $this->init_hooks();
    }

    /**
     * Define known conflicting real estate plugins
     */
    private function define_conflicting_plugins(): void {
        $this->conflicting_plugins = array(
            // Major real estate plugins that use 'property' post type
            'wp-property' => array(
                'name' => 'WP-Property',
                'slug' => 'wp-property/wp-property.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'easy-property-listings' => array(
                'name' => 'Easy Property Listings',
                'slug' => 'easy-property-listings/easy-property-listings.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'real-estate-manager' => array(
                'name' => 'Real Estate Manager',
                'slug' => 'real-estate-manager/real-estate-manager.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'property-listing-pro' => array(
                'name' => 'Property Listing Pro',
                'slug' => 'property-listing-pro/property-listing-pro.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'realia' => array(
                'name' => 'Realia',
                'slug' => 'realia/realia.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'houzez' => array(
                'name' => 'Houzez',
                'slug' => 'houzez/houzez.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'wp-real-estate' => array(
                'name' => 'WP Real Estate',
                'slug' => 'wp-real-estate/wp-real-estate.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'essential-real-estate' => array(
                'name' => 'Essential Real Estate',
                'slug' => 'essential-real-estate/essential-real-estate.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'real-estate-7' => array(
                'name' => 'Real Estate 7',
                'slug' => 'real-estate-7/real-estate-7.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'propertyhive' => array(
                'name' => 'Property Hive',
                'slug' => 'propertyhive/propertyhive.php',
                'reason' => 'Uses "property" post type which conflicts with Havenlytics.',
            ),
            'estatik' => array(
                'name' => 'Estatik',
                'slug' => 'estatik/estatik.php',
                'reason' => 'Uses similar post type structure that may conflict.',
            ),
            'imobiliaria' => array(
                'name' => 'Imobiliária',
                'slug' => 'imobiliaria/imobiliaria.php',
                'reason' => 'Another real estate plugin detected.',
            ),
        );
    }

    /**
     * Scan for active conflicting plugins
     */
    private function scan_for_conflicts(): void {
        if ( ! function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $this->active_conflicts = array();

        // Check predefined plugins — keyed by plugin file so auto-scan can dedupe.
        foreach ($this->conflicting_plugins as $plugin) {
            $plugin_file = $plugin['slug'];

            if (is_plugin_active($plugin_file)) {
                $this->active_conflicts[ $plugin_file ] = $plugin;
            }
        }

        // Also check for any plugin with 'real estate' or 'property' in its name.
        $all_plugins = get_plugins();
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            if (isset($this->active_conflicts[ $plugin_file ])) {
                continue;
            }

            if ($plugin_file === HVNLYNAB_BASENAME) {
                continue;
            }

            $name = strtolower($plugin_data['Name']);
            $desc = strtolower($plugin_data['Description']);

            if (strpos($name, 'real estate') !== false ||
                strpos($name, 'property') !== false ||
                strpos($desc, 'real estate') !== false ||
                strpos($desc, 'property listing') !== false) {

                if (is_plugin_active($plugin_file)) {
                    $this->active_conflicts[ $plugin_file ] = array(
                        'name' => $plugin_data['Name'],
                        'slug' => $plugin_file,
                        'reason' => __('Another real estate/property plugin is active.', 'havenlytics'),
                        'auto_detected' => true,
                    );
                }
            }
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Only run in admin
        if ( ! is_admin()) {
            return;
        }

        // Check on admin_init
        add_action('admin_init', array( $this, 'check_conflicts' ), 1);

        // Add admin notice
        add_action('admin_notices', array( $this, 'show_conflict_notice' ));

        // Register assets
        add_action('admin_enqueue_scripts', array( $this, 'register_assets' ));

        // Handle deactivation via AJAX
        add_action('wp_ajax_hvnly_deactivate_conflict', array( $this, 'ajax_deactivate_plugin' ));
    }

    /**
     * Register and enqueue assets with inline styles and scripts
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function register_assets( $hook ): void {
        // Notice renders on all admin screens — load assets whenever conflicts exist.
        if (empty($this->active_conflicts)) {
            return;
        }

        wp_register_style($this->style_handle, false);
        wp_enqueue_style($this->style_handle);
        $this->add_inline_styles();

        wp_register_script($this->script_handle, false, array( 'jquery' ), HVNLYNAB_VERSION, true);
        wp_enqueue_script($this->script_handle);
        $this->add_inline_scripts();
    }

    /**
     * Add inline styles for conflict checker
     *
     * @return void
     */
    private function add_inline_styles(): void {
        $custom_css = '
            .hvnly-deactivate-conflict {
                background: #d63638 !important;
                border-color: #d63638 !important;
                color: white !important;
                transition: all 0.2s ease !important;
            }
            .hvnly-deactivate-conflict:hover {
                background: #b32d2e !important;
                border-color: #b32d2e !important;
                transform: translateY(-1px);
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            .hvnly-deactivate-conflict:disabled {
                opacity: 0.7;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }
            .hvnly-deactivate-conflict .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                margin-right: 4px;
                line-height: 1.2;
            }
            .hvnly-deactivate-conflict.loading {
                pointer-events: none;
                opacity: 0.8;
            }
            .hvnly-deactivate-conflict.loading .dashicons {
                animation: hvnly-spin 1.5s linear infinite;
            }
            @keyframes hvnly-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .hvnly-conflict-notice {
                position: relative;
                padding: 15px !important;
                border-left-color: #d63638 !important;
            }
            .hvnly-conflict-notice h3 {
                margin-top: 0;
                color: #1d2327;
            }
            .hvnly-conflict-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .hvnly-conflict-item:last-child {
                border-bottom: none;
            }
            .hvnly-conflict-item strong {
                color: #1d2327;
            }
            .hvnly-conflict-reason {
                margin: 3px 0 0 0;
                color: #666;
                font-size: 12px;
            }
        ';

        wp_add_inline_style($this->style_handle, $custom_css);
    }

    /**
     * Add inline scripts for conflict checker
     *
     * @return void
     */
    private function add_inline_scripts(): void {
        $ajax_nonce         = wp_create_nonce('hvnly_ajax_nonce');
        $confirm_text       = esc_js(__('Are you sure you want to deactivate this plugin?', 'havenlytics'));
        $deactivating_text  = esc_js(__('Deactivating...', 'havenlytics'));
        $deactivate_text    = esc_js(__('Deactivate', 'havenlytics'));
        $failed_text        = esc_js(__('Failed to deactivate plugin.', 'havenlytics'));
        $network_error_text = esc_js(__('Network error. Please try again.', 'havenlytics'));

        $custom_js = "
            (function($) {
                'use strict';
                
                /**
                 * Conflict Checker Module
                 * Handles one-click deactivation of conflicting plugins
                 */
                const HvnlyConflictChecker = {
                    
                    /**
                     * Initialize the module
                     */
                    init: function() {
                        this.bindEvents();
                    },
                    
                    /**
                     * Bind event listeners
                     */
                    bindEvents: function() {
                        $(document).on('click', '.hvnly-deactivate-conflict', this.handleDeactivate.bind(this));
                    },
                    
                    /**
                     * Handle deactivate button click
                     * @param {Event} event - Click event
                     */
                    handleDeactivate: function(event) {
                        event.preventDefault();
                        
                        const button = $(event.currentTarget);
                        const plugin = button.attr('data-plugin');
                        const key = button.attr('data-key');
                        const nonce = button.attr('data-nonce');

                        if (!plugin || !key || !nonce) {
                            return;
                        }
                        
                        if (!confirm('{$confirm_text}')) {
                            return;
                        }
                        
                        this.deactivatePlugin(button, plugin, key, nonce);
                    },
                    
                    /**
                     * Deactivate plugin via AJAX
                     * @param {jQuery} button - The button element
                     * @param {string} plugin - Plugin slug
                     * @param {string} key - Plugin key
                     * @param {string} nonce - Security nonce
                     */
                    deactivatePlugin: function(button, plugin, key, nonce) {
                        const originalText = button.text();
                        
                        // Add loading state
                        button.prop('disabled', true)
                              .addClass('loading')
                              .html('<span class=\"dashicons dashicons-update\"></span> ' + originalText + '...');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'hvnly_deactivate_conflict',
                                plugin: plugin,
                                key: key,
                                nonce: nonce,
                                _ajax_nonce: '{$ajax_nonce}'
                            },
                            success: (response) => {
                                if (response.success) {
                                    this.handleSuccess(button);
                                } else {
                                    this.handleError(button, response.data || '{$failed_text}', originalText);
                                }
                            },
                            error: (xhr, status, error) => {
                                this.handleError(button, '{$network_error_text}', originalText);
                            }
                        });
                    },
                    
                    /**
                     * Handle successful deactivation
                     * @param {jQuery} button - The button element
                     */
                    handleSuccess: function(button) {
                        const row = button.closest('.hvnly-conflict-item');
                        
                        if (row.length) {
                            row.fadeOut(400, function() {
                                row.remove();
                                
                                // If no more conflicts, reload to remove notice
                                if ($('.hvnly-deactivate-conflict').length === 0) {
                                    location.reload();
                                } else {
                                    // Update button states for remaining items
                                    $('.hvnly-deactivate-conflict').prop('disabled', false).removeClass('loading');
                                }
                            });
                        } else {
                            // Fallback: reload the page
                            location.reload();
                        }
                    },
                    
                    /**
                     * Handle deactivation error
                     * @param {jQuery} button - The button element
                     * @param {string} message - Error message
                     * @param {string} originalText - Original button text
                     */
                    handleError: function(button, message, originalText) {
                        alert(message);
                        
                        // Remove loading state
                        button.prop('disabled', false)
                              .removeClass('loading')
                              .text(originalText);
                    }
                };
                
                // Initialize on document ready
                $(document).ready(function() {
                    HvnlyConflictChecker.init();
                });
                
            })(jQuery);
        ";

        wp_add_inline_script($this->script_handle, $custom_js);
    }

    /**
     * Check for conflicts
     */
    public function check_conflicts(): void {
        static $checked = false;

        if ($checked) {
            return;
        }

        $checked = true;
        $this->scan_for_conflicts();
    }

    /**
     * Show simple conflict notice
     */
    public function show_conflict_notice(): void {
        if (empty($this->active_conflicts)) {
            return;
        }

        // Only show to admins
        if ( ! current_user_can('activate_plugins')) {
            return;
        }

        ?>
        <div class="notice notice-error hvnly-conflict-notice" style="padding: 15px; border-left-color: #d63638; position: relative;">
            <div style="display: flex; align-items: flex-start; gap: 15px;">
                <div style="background: #d63638; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">
                    ⚠️
                </div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #1d2327; font-size: 16px;">
                        <?php esc_html_e('Havenlytics Detected Conflicting Plugins', 'havenlytics'); ?>
                    </h3>
                    <p style="margin: 0 0 15px 0; font-size: 14px;">
                        <?php esc_html_e('The following plugins conflict with Havenlytics and should be deactivated:', 'havenlytics'); ?>
                    </p>
                    
                    <div style="background: #f8f9fa; border-radius: 4px; padding: 15px; margin-bottom: 15px;">
                        <?php foreach ($this->active_conflicts as $plugin_file => $plugin) : ?>
                            <div class="hvnly-conflict-item">
                                <div>
                                    <strong><?php echo esc_html($plugin['name']); ?></strong>
                                    <p class="hvnly-conflict-reason">
                                        <?php echo esc_html($plugin['reason']); ?>
                                    </p>
                                </div>
                                <button type="button" class="button button-small hvnly-deactivate-conflict" 
                                        data-plugin="<?php echo esc_attr($plugin['slug']); ?>"
                                        data-key="<?php echo esc_attr($plugin_file); ?>"
                                        data-nonce="<?php echo esc_attr(wp_create_nonce('hvnly_deactivate_' . $plugin_file)); ?>">
                                    <?php esc_html_e('Deactivate', 'havenlytics'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <p style="margin: 0; font-size: 13px; color: #666;">
                        <strong><?php esc_html_e('Note:', 'havenlytics'); ?></strong> 
                        <?php esc_html_e('Running multiple real estate plugins can cause conflicts and site errors. Please deactivate conflicting plugins.', 'havenlytics'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for deactivating plugins
     */
    public function ajax_deactivate_plugin(): void {
        // Security checks
        if ( ! check_ajax_referer('hvnly_ajax_nonce', '_ajax_nonce', false)) {
            wp_send_json_error('Security check failed.');
        }

        if ( ! current_user_can('activate_plugins')) {
            wp_send_json_error('Insufficient permissions.');
        }

        $plugin = isset($_POST['plugin']) ? sanitize_text_field(wp_unslash($_POST['plugin'])) : '';
        $key    = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : '';
        $nonce  = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if (empty($plugin) || empty($key)) {
            wp_send_json_error(__('Invalid plugin request.', 'havenlytics'));
        }

        // Always deactivate using the canonical plugin file path.
        $plugin_file = $plugin;
        if ($key !== $plugin_file && is_plugin_active($key)) {
            $plugin_file = $key;
        }

        if ( ! wp_verify_nonce($nonce, 'hvnly_deactivate_' . $plugin_file)) {
            wp_send_json_error(__('Nonce verification failed.', 'havenlytics'));
        }

        if ( ! is_plugin_active($plugin_file)) {
            wp_send_json_error(__('Plugin not active.', 'havenlytics'));
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        deactivate_plugins($plugin_file, false, is_network_admin());

        // Clear any transients that might be cached
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        wp_send_json_success();
    }

    /**
     * Check if there are any active conflicts
     *
     * @return bool
     */
    public function has_conflicts(): bool {
        return ! empty($this->active_conflicts);
    }
}