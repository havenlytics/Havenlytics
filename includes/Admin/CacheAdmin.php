<?php

namespace HvnlyNab\Admin;

defined('ABSPATH') || exit;

class CacheAdmin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_hvnly_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_hvnly_get_cache_stats', [$this, 'ajax_get_cache_stats']);
        add_action('wp_ajax_hvnly_update_cache_settings', [$this, 'ajax_update_cache_settings']);
        add_action('wp_ajax_hvnly_clear_shortcode_cache', [$this, 'ajax_clear_shortcode_cache']);
        add_action('wp_ajax_hvnly_clear_dynamic_css', [$this, 'ajax_clear_dynamic_css']);
    }

    /**
     * Whether the cache system is enabled (single source of truth).
     *
     * @return bool
     */
    private function is_cache_system_enabled()
    {
        return function_exists('hvnly_is_cache_enabled')
            ? \hvnly_is_cache_enabled()
            : (bool) get_option('hvnly_cache_enabled', 0);
    }

    public function add_admin_menu()
    {
        if (!$this->is_cache_system_enabled()) {
            return;
        }

        add_submenu_page(
            'edit.php?post_type=hvnly_property',
            __('Cache Management', 'havenlytics'),
            __('Cache', 'havenlytics'),
            'manage_options',
            'hvnly_property_cache',
            [$this, 'cache_admin_page']
        );
    }

    public function register_settings()
    {
        if (!$this->is_cache_system_enabled()) {
            return;
        }

        register_setting('hvnly_cache_settings', 'hvnly_cache_enabled', [
            'type' => 'boolean',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ]);

        register_setting('hvnly_cache_settings', 'hvnly_cache_ttl', [
            'type' => 'integer',
            'default' => HOUR_IN_SECONDS * 6,
            'sanitize_callback' => 'absint'
        ]);

        register_setting('hvnly_cache_settings', 'hvnly_cache_compression', [
            'type' => 'boolean',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ]);

        register_setting('hvnly_cache_settings', 'hvnly_cache_debug', [
            'type' => 'boolean',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ]);
    }

    public function enqueue_scripts($hook)
    {
        if ('hvnly_property_page_hvnly_property_cache' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'hvnly-admin-cache',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-admin-cache.js',
            ['jquery'],
            HVNLYNAB_VERSION,
            true
        );

        wp_localize_script('hvnly-admin-cache', 'hvnlyCacheAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hvnly_cache_nonce'),
            'clearingText' => __('Clearing...', 'havenlytics'),
            'clearedText' => __('Cache cleared successfully!', 'havenlytics'),
            'errorText' => __('Error clearing cache', 'havenlytics'),
            'timeoutText' => __('Request timed out. Please try again.', 'havenlytics'),
            'networkErrorText' => __('Network error. Please try again.', 'havenlytics'),
            'confirmRequired' => [
                'hvnly-clear-cache',
                'hvnly-clear-shortcode-cache',
                'hvnly-clear-dynamic-css',
            ],
        ]);

        wp_enqueue_style(
            'hvnly-admin-cache',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-cache.css',
            [],
            HVNLYNAB_VERSION
        );

        wp_add_inline_style('hvnly-admin-cache', '
            .hvnly-cache-action-cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin-bottom:20px}
            .hvnly-cache-action-card{background:#f8f9fa;border:1px solid var(--hvnly-border-color,#dee2e6);border-radius:12px;padding:20px;display:flex;flex-direction:column;gap:16px}
            .hvnly-cache-action-card .card-header h3{margin:0 0 8px;font-size:16px;font-weight:600;color:var(--hvnly-text-primary,#1d2327)}
            .hvnly-cache-action-card .card-desc{margin:0;font-size:13px;line-height:1.5;color:var(--hvnly-text-secondary,#646970)}
            .hvnly-cache-action-card .card-meta{margin:0;font-size:12px;color:var(--hvnly-text-secondary,#646970);font-style:italic}
            .hvnly-cache-action-card .card-actions{display:flex;flex-wrap:wrap;gap:10px}
            .hvnly-cache-action-card .card-actions .button{min-width:0;flex:1 1 auto}
            .hvnly-cache-action-card .card-actions .button.is-disabled,.hvnly-cache-action-card .card-actions .button:disabled{opacity:.55;cursor:not-allowed}
            .hvnly-cache-action-card--system{background:linear-gradient(135deg,rgba(108,96,254,.04),rgba(255,255,255,1))}
            .hvnly-cache-toolbar{display:flex;justify-content:flex-end;padding-top:8px;border-top:1px solid var(--hvnly-border-color,#dee2e6)}
            .hvnly-cache-stats h2,.hvnly-cache-actions h2,.hvnly-cache-performance h2,.hvnly-cache-settings h2{margin:0 0 16px;font-size:18px;font-weight:600}
            .hvnly-cache-performance .perf-grid{margin-top:0}
            @media(max-width:960px){.hvnly-cache-action-cards{grid-template-columns:1fr}}
        ');
    }

    public function cache_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'havenlytics'));
        }

        if (!$this->is_cache_system_enabled()) {
            wp_safe_redirect(admin_url('edit.php?post_type=hvnly_property&page=hvnly_property_settings'));
            exit;
        }

        $cache_stats = HVNLY_NAB()->engine()->get_cache_stats();
        $performance_metrics = HVNLY_NAB()->engine()->get_performance_metrics();

        $cache_enabled = function_exists('hvnly_is_cache_enabled') ? \hvnly_is_cache_enabled() : (bool) get_option('hvnly_cache_enabled', 0);
        $cache_ttl = (int) get_option('hvnly_cache_ttl', HOUR_IN_SECONDS * 6);
        $cache_compression = (bool) get_option('hvnly_cache_compression', 0);
        $cache_debug = (bool) get_option('hvnly_cache_debug', 0);

        $stat_items = [
            'cache_size_human' => [
                'label' => __('Total Cache Size', 'havenlytics'),
                'value' => $cache_stats['cache_size_human'],
            ],
            'search_cache_count' => [
                'label' => __('Main Search Cache', 'havenlytics'),
                'value' => number_format($cache_stats['search_cache_count']),
            ],
            'term_cache_count' => [
                'label' => __('Term Cache', 'havenlytics'),
                'value' => number_format($cache_stats['term_cache_count']),
            ],
            'cache_hit_rate' => [
                'label' => __('Cache Hit Rate', 'havenlytics'),
                'value' => esc_html($cache_stats['cache_hit_rate']) . '%',
            ],
            'sidebar_cache_count' => [
                'label' => __('Sidebar Cache', 'havenlytics'),
                'value' => number_format($cache_stats['sidebar_cache_count']),
            ],
            'total_cached_items' => [
                'label' => __('Total Cached Items', 'havenlytics'),
                'value' => number_format($cache_stats['total_cached_items']),
            ],
        ];

        $performance_items = [
            'cache_hits' => [
                'label' => __('Cache Hits', 'havenlytics'),
                'value' => number_format($performance_metrics['cache_hits']),
            ],
            'cache_misses' => [
                'label' => __('Cache Misses', 'havenlytics'),
                'value' => number_format($performance_metrics['cache_misses']),
            ],
            'queries_executed' => [
                'label' => __('Queries Executed', 'havenlytics'),
                'value' => number_format($performance_metrics['queries_executed']),
            ],
            'average_query_time' => [
                'label' => __('Average Query Time', 'havenlytics'),
                'value' => round($performance_metrics['average_query_time'], 4) . 's',
            ],
            'cache_efficiency' => [
                'label' => __('Cache Efficiency', 'havenlytics'),
                'value' => esc_html($performance_metrics['cache_efficiency']) . '%',
            ],
            'memory_usage' => [
                'label' => __('Memory Usage', 'havenlytics'),
                'value' => size_format($performance_metrics['memory_usage']),
            ],
            'total_queries_saved' => [
                'label' => __('Total Queries Saved', 'havenlytics'),
                'value' => number_format($performance_metrics['total_queries_saved']),
            ],
        ];

?>
        <div class="wrap hvnly-cache-admin">
            <h1><?php esc_html_e('Havenlytics Cache Management', 'havenlytics'); ?></h1>

            <!-- Cache Statistics -->
            <div class="hvnly-cache-stats">
                <h2><?php esc_html_e('Cache Overview', 'havenlytics'); ?></h2>
                <div class="stats-grid" id="hvnly-cache-stats-grid">
                    <?php foreach ($stat_items as $stat_key => $stat_item) : ?>
                        <div class="stat-card" data-stat="<?php echo esc_attr($stat_key); ?>">
                            <h3><?php echo esc_html($stat_item['label']); ?></h3>
                            <div class="stat-value"><?php echo esc_html($stat_item['value']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="hvnly-cache-actions">
                <h2><?php esc_html_e('Cache Actions', 'havenlytics'); ?></h2>
                <div class="action-buttons hvnly-cache-action-cards">

                    <div class="hvnly-cache-action-card">
                        <div class="card-header">
                            <h3><?php esc_html_e('Search Cache', 'havenlytics'); ?></h3>
                            <p class="card-desc"><?php esc_html_e('Property search results and AJAX listing responses cached for faster page loads.', 'havenlytics'); ?></p>
                        </div>
                        <div class="card-actions">
                            <button type="button" id="hvnly-clear-search-cache" class="button" data-default-label="<?php esc_attr_e('Clear Main Search Cache', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear Main Search Cache', 'havenlytics'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="hvnly-cache-action-card">
                        <div class="card-header">
                            <h3><?php esc_html_e('Sidebar Cache', 'havenlytics'); ?></h3>
                            <p class="card-desc"><?php esc_html_e('Filter sidebar data and taxonomy term lists used in property search filters.', 'havenlytics'); ?></p>
                        </div>
                        <div class="card-actions">
                            <button type="button" id="hvnly-clear-sidebar-cache" class="button" data-default-label="<?php esc_attr_e('Clear Sidebar Cache', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear Sidebar Cache', 'havenlytics'); ?>
                            </button>
                            <button type="button" id="hvnly-clear-terms-cache" class="button" data-default-label="<?php esc_attr_e('Clear Terms Cache', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear Terms Cache', 'havenlytics'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="hvnly-cache-action-card">
                        <div class="card-header">
                            <h3><?php esc_html_e('Shortcode Cache', 'havenlytics'); ?></h3>
                            <p class="card-desc"><?php esc_html_e('Rendered output for property grid, list, and search shortcodes.', 'havenlytics'); ?></p>
                            <p class="card-meta" id="hvnly-last-cleared-shortcode"><?php echo esc_html($this->get_last_cleared_label('hvnly_cache_last_cleared_shortcode')); ?></p>
                        </div>
                        <div class="card-actions">
                            <button type="button" id="hvnly-clear-shortcode-cache" class="button button-secondary" data-default-label="<?php esc_attr_e('Clear All Shortcode Cache', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear All Shortcode Cache', 'havenlytics'); ?>
                            </button>
                            <button type="button" id="hvnly-clear-grid-shortcode" class="button" data-default-label="<?php esc_attr_e('Clear Grid Shortcode', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear Grid Shortcode', 'havenlytics'); ?>
                            </button>
                            <button type="button" id="hvnly-clear-list-shortcode" class="button" data-default-label="<?php esc_attr_e('Clear List Shortcode', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear List Shortcode', 'havenlytics'); ?>
                            </button>
                            <button type="button" id="hvnly-clear-search-shortcode" class="button" data-default-label="<?php esc_attr_e('Clear Search Shortcode', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear Search Shortcode', 'havenlytics'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="hvnly-cache-action-card hvnly-cache-action-card--system">
                        <div class="card-header">
                            <h3><?php esc_html_e('System Cache', 'havenlytics'); ?></h3>
                            <p class="card-desc"><?php esc_html_e('Dynamic CSS, global transients, and all Havenlytics cache layers.', 'havenlytics'); ?></p>
                            <p class="card-meta" id="hvnly-last-cleared-all"><?php echo esc_html($this->get_last_cleared_label('hvnly_cache_last_cleared_all')); ?></p>
                            <p class="card-meta" id="hvnly-last-cleared-css"><?php echo esc_html($this->get_last_cleared_label('hvnly_cache_last_cleared_css')); ?></p>
                        </div>
                        <div class="card-actions">
                            <button type="button" id="hvnly-clear-dynamic-css" class="button button-secondary" data-default-label="<?php esc_attr_e('Clear Dynamic CSS Cache', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear Dynamic CSS Cache', 'havenlytics'); ?>
                            </button>
                            <button type="button" id="hvnly-clear-cache" class="button button-primary" data-default-label="<?php esc_attr_e('Clear All Cache', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear All Cache', 'havenlytics'); ?>
                            </button>
                        </div>
                    </div>

                </div>
                <div class="hvnly-cache-toolbar">
                    <button type="button" id="hvnly-refresh-stats" class="button" data-default-label="<?php esc_attr_e('Refresh Stats', 'havenlytics'); ?>">
                        <?php esc_html_e('Refresh Stats', 'havenlytics'); ?>
                    </button>
                </div>
            </div>

            <!-- Cache Settings -->
            <div class="hvnly-cache-settings">
                <h2><?php esc_html_e('Cache Settings', 'havenlytics'); ?></h2>
                <form method="post" action="options.php" id="hvnly-cache-settings-form">
                    <?php settings_fields('hvnly_cache_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Caching', 'havenlytics'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hvnly_cache_enabled" value="1" <?php echo checked($cache_enabled, 1, false); ?> />
                                    <?php esc_html_e('Enable property search and term caching', 'havenlytics'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, listings use cached AJAX responses. When disabled, all data loads live from the database.', 'havenlytics'); ?>
                                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=hvnly_property&page=hvnly_property_settings')); ?>">
                                        <?php esc_html_e('Manage in Settings → Performance', 'havenlytics'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Cache Duration', 'havenlytics'); ?></th>
                            <td>
                                <select name="hvnly_cache_ttl">
                                    <?php
                                    $ttl_options = [
                                        3600 => __('1 Hour', 'havenlytics'),
                                        7200 => __('2 Hours', 'havenlytics'),
                                        21600 => __('6 Hours', 'havenlytics'),
                                        43200 => __('12 Hours', 'havenlytics'),
                                        86400 => __('24 Hours', 'havenlytics')
                                    ];

                                    foreach ($ttl_options as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php echo selected($cache_ttl, $value, false); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('How long should cached data be stored?', 'havenlytics'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Cache Compression', 'havenlytics'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hvnly_cache_compression" value="1" <?php echo checked($cache_compression, 1, false); ?> />
                                    <?php esc_html_e('Compress cached data to save database space', 'havenlytics'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Debug Mode', 'havenlytics'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="hvnly_cache_debug" value="1" <?php echo checked($cache_debug, 1, false); ?> />
                                    <?php esc_html_e('Enable debug logging for cache operations', 'havenlytics'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Settings', 'havenlytics')); ?>
                </form>
            </div>

            <!-- Performance Metrics -->
            <div class="hvnly-cache-performance">
                <h2><?php esc_html_e('Performance Metrics', 'havenlytics'); ?></h2>
                <div class="stats-grid perf-grid" id="hvnly-cache-performance-grid">
                    <?php foreach ($performance_items as $perf_key => $perf_item) : ?>
                        <div class="stat-card" data-perf="<?php echo esc_attr($perf_key); ?>">
                            <h3><?php echo esc_html($perf_item['label']); ?></h3>
                            <div class="stat-value"><?php echo esc_html($perf_item['value']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
<?php
    }

    public function ajax_clear_cache()
    {
        check_ajax_referer('hvnly_cache_nonce', 'nonce');

        if (!$this->is_cache_system_enabled()) {
            wp_send_json_error(esc_html__('Cache system is disabled.', 'havenlytics'), 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $cache_type = sanitize_key($_POST['cache_type'] ?? 'all');
        $cleared_all = false;

        switch ($cache_type) {
            case 'search':
                HVNLY_NAB()->engine()->clear_transients_by_pattern('search_');
                break;
            case 'sidebar':
                HVNLY_NAB()->engine()->clear_transients_by_pattern('sidebar_');
                break;
            case 'terms':
                HVNLY_NAB()->engine()->clear_transients_by_pattern('terms_');
                break;
            case 'all':
            default:
                HVNLY_NAB()->engine()->clear_all_cache();
                update_option('hvnly_cache_last_cleared_all', time(), false);
                $cleared_all = true;
                break;
        }

        $response = [
            'message' => esc_html__('Cache cleared successfully', 'havenlytics'),
        ];

        if ($cleared_all) {
            $response['last_cleared'] = [
                'all' => $this->get_last_cleared_label('hvnly_cache_last_cleared_all'),
            ];
        }

        wp_send_json_success($response);
    }

    public function ajax_get_cache_stats()
    {
        check_ajax_referer('hvnly_cache_nonce', 'nonce');

        if (!$this->is_cache_system_enabled()) {
            wp_send_json_error(esc_html__('Cache system is disabled.', 'havenlytics'), 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $stats = HVNLY_NAB()->engine()->get_cache_stats();
        $performance = HVNLY_NAB()->engine()->get_performance_metrics();

        wp_send_json_success([
            'stats' => $stats,
            'performance' => $performance,
            'last_cleared' => [
                'all' => $this->get_last_cleared_label('hvnly_cache_last_cleared_all'),
                'shortcode' => $this->get_last_cleared_label('hvnly_cache_last_cleared_shortcode'),
                'css' => $this->get_last_cleared_label('hvnly_cache_last_cleared_css'),
            ],
        ]);
    }

    public function ajax_update_cache_settings() {
        check_ajax_referer('hvnly_cache_nonce', 'nonce');

        if (!$this->is_cache_system_enabled()) {
            wp_send_json_error(esc_html__('Cache system is disabled.', 'havenlytics'), 403);
        }

        if (! current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $settings_raw = filter_input(INPUT_POST, 'settings', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [];

        $settings = [];
        foreach ($settings_raw as $key => $value) {
            $safe_key = sanitize_key($key);
            $safe_value = $this->sanitize_setting($safe_key, $value);
            $settings[$safe_key] = $safe_value;
            update_option('hvnly_' . $safe_key, $safe_value);
        }

        $new_config = [
            'cache_ttl' => absint($settings['cache_ttl'] ?? HOUR_IN_SECONDS * 6),
            'cache_compression' => !empty($settings['cache_compression']),
            'enable_performance' => !empty($settings['cache_debug']),
        ];

        HVNLY_NAB()->engine()->update_config($new_config);

        wp_send_json_success(esc_html__('Settings updated successfully', 'havenlytics'));
    }

    public function ajax_clear_shortcode_cache() {
        check_ajax_referer('hvnly_cache_nonce', 'nonce');

        if (!$this->is_cache_system_enabled()) {
            wp_send_json_error(esc_html__('Cache system is disabled.', 'havenlytics'), 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $shortcode_type = sanitize_key($_POST['shortcode_type'] ?? 'all');

        if (class_exists('HvnlyNab\Frontend\Shortcodes\Registry')) {
            if ($shortcode_type === 'all') {
                \HvnlyNab\Frontend\Shortcodes\Registry::clear_all_caches();
                update_option('hvnly_cache_last_cleared_shortcode', time(), false);
                wp_send_json_success([
                    'message' => esc_html__('All shortcode caches cleared successfully', 'havenlytics'),
                    'last_cleared' => [
                        'shortcode' => $this->get_last_cleared_label('hvnly_cache_last_cleared_shortcode'),
                    ],
                ]);
            } else {
                \HvnlyNab\Frontend\Shortcodes\Registry::clear_cache($shortcode_type);
                $message = sprintf(
                    /* translators: %s: shortcode type name */
                    esc_html__('%s shortcode cache cleared successfully', 'havenlytics'),
                    esc_html($shortcode_type)
                );
                wp_send_json_success(['message' => $message]);
            }
        } else {
            HVNLY_NAB()->engine()->clear_transients_by_pattern('hvnly_property_grid_%');
            HVNLY_NAB()->engine()->clear_transients_by_pattern('hvnly_property_list_%');
            HVNLY_NAB()->engine()->clear_transients_by_pattern('hvnly_featured_properties_%');
            HVNLY_NAB()->engine()->clear_transients_by_pattern('hvnly_property_search_%');
            if ($shortcode_type === 'all') {
                update_option('hvnly_cache_last_cleared_shortcode', time(), false);
            }
            wp_send_json_success([
                'message' => esc_html__('Shortcode caches cleared via pattern', 'havenlytics'),
                'last_cleared' => $shortcode_type === 'all' ? [
                    'shortcode' => $this->get_last_cleared_label('hvnly_cache_last_cleared_shortcode'),
                ] : [],
            ]);
        }
    }

    /**
     * AJAX handler for clearing dynamic CSS cache
     */
    public function ajax_clear_dynamic_css()
    {
        check_ajax_referer('hvnly_cache_nonce', 'nonce');

        if (!$this->is_cache_system_enabled()) {
            wp_send_json_error(esc_html__('Cache system is disabled.', 'havenlytics'), 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Clear the dynamic CSS cache
        delete_transient('hvnly_dynamic_css');
        
        // Clear using CacheManager if available
        if (class_exists('HvnlyNab\Core\CacheManager')) {
            \HvnlyNab\Core\CacheManager::delete_transient('hvnly_dynamic_css');
        }
        
        // Clear style generator cache
        if (class_exists('HvnlyNab\Core\DynamicStyleGenerator')) {
            $generator = \HvnlyNab\Core\DynamicStyleGenerator::get_instance();
            $generator->clear_css_cache();
        }
        
        // Clear via engine if available
        if (function_exists('HVN') && HVNLY_NAB()->engine()) {
            HVNLY_NAB()->engine()->clear_transients_by_pattern('hvnly_dynamic_css');
        }
        
        update_option('hvnly_cache_last_cleared_css', time(), false);

        wp_send_json_success([
            'message' => esc_html__('Dynamic CSS cache cleared successfully', 'havenlytics'),
            'last_cleared' => [
                'css' => $this->get_last_cleared_label('hvnly_cache_last_cleared_css'),
            ],
        ]);
    }

    /**
     * Human-readable last-cleared timestamp for admin UI.
     *
     * @param string $option_key
     * @return string
     */
    private function get_last_cleared_label($option_key)
    {
        $timestamp = (int) get_option($option_key, 0);

        if ($timestamp <= 0) {
            return __('Never cleared', 'havenlytics');
        }

        return sprintf(
            /* translators: %s: formatted date/time */
            __('Last cleared: %s', 'havenlytics'),
            wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp)
        );
    }

    private function sanitize_setting($key, $value)
    {
        switch ($key) {
            case 'cache_ttl':
                return absint($value);
            case 'cache_enabled':
            case 'cache_compression':
            case 'cache_debug':
                return !empty($value) ? 1 : 0;
            default:
                return sanitize_text_field($value);
        }
    }
}