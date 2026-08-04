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

        // Design tokens (same handle as Assets) for consistent Havenlytics admin UI.
        $boot_css = HVNLYNAB_ASSETS_PATH . '/admin/css/hvnly-admin-boot.css';
        if (file_exists($boot_css)) {
            wp_enqueue_style(
                'hvnly-admin-boot',
                HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-boot.css',
                [],
                HVNLYNAB_VERSION
            );
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
            'i18n' => [
                'success' => __('Success', 'havenlytics'),
                'failed' => __('Failed', 'havenlytics'),
                'statsUpdated' => __('Stats Updated', 'havenlytics'),
                'statsRefreshed' => __('Cache statistics have been refreshed.', 'havenlytics'),
                'statsRefreshFailed' => __('Failed to refresh statistics.', 'havenlytics'),
                'clearAllTitle' => __('Clear All Cache', 'havenlytics'),
                'clearAllConfirm' => __('Are you sure you want to clear <strong>all</strong> Havenlytics cache? This removes search results, sidebar filters, terms, and shortcode data.', 'havenlytics'),
                'noCacheTitle' => __('No Cache to Clear', 'havenlytics'),
                'noCacheBody' => __('All cache is already empty.', 'havenlytics'),
                'clearSearchTitle' => __('Clear Search Cache', 'havenlytics'),
                'clearSearchConfirm' => __('This will clear all cached property search results. Recent searches may be slower until the cache rebuilds.', 'havenlytics'),
                'clearSidebarTitle' => __('Clear Sidebar Cache', 'havenlytics'),
                'clearSidebarConfirm' => __('This will clear all cached sidebar filter data.', 'havenlytics'),
                'clearTermsTitle' => __('Clear Terms Cache', 'havenlytics'),
                'clearTermsConfirm' => __('This will clear all cached taxonomy terms used in filters.', 'havenlytics'),
                'clearShortcodeTitle' => __('Clear All Shortcode Cache', 'havenlytics'),
                'clearShortcodeConfirm' => __('This will clear all cached shortcode outputs (grid, list, search). Fresh content will render on the next page load.', 'havenlytics'),
                'clearCssTitle' => __('Clear Dynamic CSS Cache', 'havenlytics'),
                'clearCssConfirm' => __('This will clear generated dynamic CSS. Styles will regenerate on the next frontend request.', 'havenlytics'),
                'settingsSaved' => __('Settings Saved', 'havenlytics'),
                'settingsSavedBody' => __('Cache settings have been saved.', 'havenlytics'),
                'allEmpty' => __('All caches are empty', 'havenlytics'),
                'insufficientPermissions' => __('Insufficient permissions', 'havenlytics'),
                'error' => __('Error', 'havenlytics'),
                'close' => __('Close', 'havenlytics'),
                'dismiss' => __('Dismiss', 'havenlytics'),
                'cacheCleared' => __('Cache Cleared', 'havenlytics'),
                'shortcodeCacheCleared' => __('Shortcode Cache Cleared', 'havenlytics'),
                'cssCacheCleared' => __('CSS Cache Cleared', 'havenlytics'),
            ],
            'confirmRequired' => [
                'hvnly-clear-cache',
                'hvnly-clear-shortcode-cache',
                'hvnly-clear-dynamic-css',
            ],
        ]);

        wp_enqueue_style(
            'hvnly-admin-cache',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-admin-cache.css',
            ['hvnly-admin-boot'],
            HVNLYNAB_VERSION
        );
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

        $health_key = isset($cache_stats['cache_health']) ? (string) $cache_stats['cache_health'] : 'idle';
        $status_key = isset($cache_stats['cache_status']) ? (string) $cache_stats['cache_status'] : ($cache_enabled ? 'active' : 'disabled');

        $health_labels = [
            'healthy'  => __('Healthy', 'havenlytics'),
            'warming'  => __('Warming', 'havenlytics'),
            'idle'     => __('Idle', 'havenlytics'),
            'disabled' => __('Disabled', 'havenlytics'),
        ];
        $status_labels = [
            'active'   => __('Active', 'havenlytics'),
            'disabled' => __('Disabled', 'havenlytics'),
        ];

        $avg_query = (float) ($performance_metrics['average_query_time'] ?? 0);
        $avg_query_display = $avg_query > 0 ? (round($avg_query, 4) . 's') : '—';

        $stat_items = [
            'cache_status' => [
                'label' => __('Cache Status', 'havenlytics'),
                'value' => $status_labels[$status_key] ?? $status_labels['active'],
                'mod'   => 'status-' . $status_key,
            ],
            'cache_health' => [
                'label' => __('Cache Health', 'havenlytics'),
                'value' => $health_labels[$health_key] ?? $health_labels['idle'],
                'mod'   => 'health-' . $health_key,
            ],
            'cache_size_human' => [
                'label' => __('Cache Size', 'havenlytics'),
                'value' => $cache_stats['cache_size_human'],
                'mod'   => 'size',
            ],
            'total_cached_items' => [
                'label' => __('Total Cached Items', 'havenlytics'),
                'value' => number_format($cache_stats['total_cached_items']),
                'mod'   => 'items',
            ],
            'cache_hit_rate' => [
                'label' => __('Cache Hit Rate', 'havenlytics'),
                'value' => esc_html($cache_stats['cache_hit_rate']) . '%',
                'mod'   => 'hitrate',
            ],
            'search_cache_count' => [
                'label' => __('Search Cache', 'havenlytics'),
                'value' => number_format($cache_stats['search_cache_count']),
                'mod'   => 'search',
            ],
            'sidebar_cache_count' => [
                'label' => __('Sidebar Cache', 'havenlytics'),
                'value' => number_format($cache_stats['sidebar_cache_count']),
                'mod'   => 'sidebar',
            ],
            'term_cache_count' => [
                'label' => __('Term Cache', 'havenlytics'),
                'value' => number_format($cache_stats['term_cache_count']),
                'mod'   => 'terms',
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
            'total_queries_saved' => [
                'label' => __('Queries Saved', 'havenlytics'),
                'value' => number_format($performance_metrics['total_queries_saved']),
            ],
            'average_query_time' => [
                'label' => __('Average Query Time', 'havenlytics'),
                'value' => $avg_query_display,
            ],
            'cache_efficiency' => [
                'label' => __('Cache Efficiency', 'havenlytics'),
                'value' => esc_html($performance_metrics['cache_efficiency']) . '%',
            ],
            'memory_usage' => [
                'label' => __('Memory Usage', 'havenlytics'),
                'value' => size_format($performance_metrics['memory_usage']),
            ],
            'queries_executed' => [
                'label' => __('Queries Executed', 'havenlytics'),
                'value' => number_format($performance_metrics['queries_executed']),
            ],
        ];

        $object_cache_label = !empty($cache_stats['object_cache'])
            ? __('External object cache detected (Redis / Memcached compatible).', 'havenlytics')
            : __('Using WordPress transients (database / default object cache).', 'havenlytics');

?>
        <div class="wrap hvnly-cache-admin">
            <header class="hvnly-cache-hero">
                <div class="hvnly-cache-hero__copy">
                    <p class="hvnly-cache-hero__eyebrow"><?php esc_html_e('Performance', 'havenlytics'); ?></p>
                    <h1><?php esc_html_e('Cache Management', 'havenlytics'); ?></h1>
                    <p class="hvnly-cache-hero__desc"><?php esc_html_e('Monitor Havenlytics cache layers, clear stale data, and tune TTL — without affecting builders, REST, or front-end templates.', 'havenlytics'); ?></p>
                    <p class="hvnly-cache-hero__meta"><?php echo esc_html($object_cache_label); ?></p>
                </div>
                <div class="hvnly-cache-hero__badges">
                    <span class="hvnly-cache-badge hvnly-cache-badge--<?php echo esc_attr($status_key); ?>" data-stat-badge="cache_status"><?php echo esc_html($status_labels[$status_key] ?? ''); ?></span>
                    <span class="hvnly-cache-badge hvnly-cache-badge--<?php echo esc_attr($health_key); ?>" data-stat-badge="cache_health"><?php echo esc_html($health_labels[$health_key] ?? ''); ?></span>
                </div>
            </header>

            <section class="hvnly-cache-stats" aria-labelledby="hvnly-cache-overview-heading">
                <div class="hvnly-cache-section-head">
                    <h2 id="hvnly-cache-overview-heading"><?php esc_html_e('Cache Overview', 'havenlytics'); ?></h2>
                    <button type="button" id="hvnly-refresh-stats" class="button hvnly-cache-refresh" data-default-label="<?php esc_attr_e('Refresh Stats', 'havenlytics'); ?>">
                        <?php esc_html_e('Refresh Stats', 'havenlytics'); ?>
                    </button>
                </div>
                <div class="stats-grid" id="hvnly-cache-stats-grid">
                    <?php foreach ($stat_items as $stat_key => $stat_item) : ?>
                        <div class="stat-card <?php echo esc_attr('stat-card--' . ($stat_item['mod'] ?? 'default')); ?>" data-stat="<?php echo esc_attr($stat_key); ?>">
                            <h3><?php echo esc_html($stat_item['label']); ?></h3>
                            <div class="stat-value"><?php echo esc_html($stat_item['value']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="hvnly-cache-actions" aria-labelledby="hvnly-cache-actions-heading">
                <div class="hvnly-cache-section-head">
                    <h2 id="hvnly-cache-actions-heading"><?php esc_html_e('Quick Actions', 'havenlytics'); ?></h2>
                </div>
                <div class="action-buttons hvnly-cache-action-cards">

                    <div class="hvnly-cache-action-card">
                        <div class="card-header">
                            <span class="card-icon" aria-hidden="true"><span class="dashicons dashicons-search"></span></span>
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
                            <span class="card-icon" aria-hidden="true"><span class="dashicons dashicons-filter"></span></span>
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
                            <span class="card-icon" aria-hidden="true"><span class="dashicons dashicons-shortcode"></span></span>
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
                            <span class="card-icon" aria-hidden="true"><span class="dashicons dashicons-admin-generic"></span></span>
                            <h3><?php esc_html_e('System Cache', 'havenlytics'); ?></h3>
                            <p class="card-desc"><?php esc_html_e('Dynamic CSS, global transients, and all Havenlytics cache layers.', 'havenlytics'); ?></p>
                            <p class="card-meta" id="hvnly-last-cleared-all"><?php echo esc_html($this->get_last_cleared_label('hvnly_cache_last_cleared_all')); ?></p>
                            <p class="card-meta" id="hvnly-last-cleared-css"><?php echo esc_html($this->get_last_cleared_label('hvnly_cache_last_cleared_css')); ?></p>
                        </div>
                        <div class="card-actions">
                            <button type="button" id="hvnly-clear-dynamic-css" class="button button-secondary" data-default-label="<?php esc_attr_e('Clear Dynamic CSS Cache', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear Dynamic CSS Cache', 'havenlytics'); ?>
                            </button>
                            <button type="button" id="hvnly-clear-cache" class="button button-primary button-danger" data-default-label="<?php esc_attr_e('Clear All Cache', 'havenlytics'); ?>">
                                <?php esc_html_e('Clear All Cache', 'havenlytics'); ?>
                            </button>
                        </div>
                    </div>

                </div>
            </section>

            <section class="hvnly-cache-settings" aria-labelledby="hvnly-cache-settings-heading">
                <div class="hvnly-cache-section-head">
                    <h2 id="hvnly-cache-settings-heading"><?php esc_html_e('Cache Settings', 'havenlytics'); ?></h2>
                </div>
                <form method="post" action="options.php" id="hvnly-cache-settings-form" class="hvnly-cache-settings-form">
                    <?php settings_fields('hvnly_cache_settings'); ?>
                    <div class="hvnly-cache-settings-grid">
                        <div class="hvnly-cache-setting-card">
                            <div class="setting-copy">
                                <h3><?php esc_html_e('Enable Caching', 'havenlytics'); ?></h3>
                                <p class="description">
                                    <?php esc_html_e('When enabled, listings use cached AJAX responses. When disabled, all data loads live from the database.', 'havenlytics'); ?>
                                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=hvnly_property&page=hvnly_property_settings')); ?>">
                                        <?php esc_html_e('Manage in Settings → Performance', 'havenlytics'); ?>
                                    </a>
                                </p>
                            </div>
                            <label class="hvnly-cache-switch">
                                <input type="checkbox" name="hvnly_cache_enabled" value="1" <?php echo checked($cache_enabled, 1, false); ?> />
                                <span class="hvnly-cache-switch__ui" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e('Enable property search and term caching', 'havenlytics'); ?></span>
                            </label>
                        </div>

                        <div class="hvnly-cache-setting-card">
                            <div class="setting-copy">
                                <h3><?php esc_html_e('Cache Duration', 'havenlytics'); ?></h3>
                                <p class="description"><?php esc_html_e('How long should cached data be stored?', 'havenlytics'); ?></p>
                            </div>
                            <select name="hvnly_cache_ttl" class="hvnly-cache-select">
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
                        </div>

                        <div class="hvnly-cache-setting-card">
                            <div class="setting-copy">
                                <h3><?php esc_html_e('Cache Compression', 'havenlytics'); ?></h3>
                                <p class="description"><?php esc_html_e('Compress cached data to save database space', 'havenlytics'); ?></p>
                            </div>
                            <label class="hvnly-cache-switch">
                                <input type="checkbox" name="hvnly_cache_compression" value="1" <?php echo checked($cache_compression, 1, false); ?> />
                                <span class="hvnly-cache-switch__ui" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e('Compress cached data to save database space', 'havenlytics'); ?></span>
                            </label>
                        </div>

                        <div class="hvnly-cache-setting-card">
                            <div class="setting-copy">
                                <h3><?php esc_html_e('Debug Mode', 'havenlytics'); ?></h3>
                                <p class="description"><?php esc_html_e('Enable debug logging for cache operations', 'havenlytics'); ?></p>
                            </div>
                            <label class="hvnly-cache-switch">
                                <input type="checkbox" name="hvnly_cache_debug" value="1" <?php echo checked($cache_debug, 1, false); ?> />
                                <span class="hvnly-cache-switch__ui" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e('Enable debug logging for cache operations', 'havenlytics'); ?></span>
                            </label>
                        </div>
                    </div>
                    <?php submit_button(__('Save Settings', 'havenlytics'), 'primary', 'submit', false, ['class' => 'button button-primary hvnly-cache-save']); ?>
                </form>
            </section>

            <section class="hvnly-cache-performance" aria-labelledby="hvnly-cache-perf-heading">
                <div class="hvnly-cache-section-head">
                    <h2 id="hvnly-cache-perf-heading"><?php esc_html_e('Performance Metrics', 'havenlytics'); ?></h2>
                    <p class="hvnly-cache-section-note"><?php esc_html_e('Live counters from actual cache hits and misses — not estimated placeholders.', 'havenlytics'); ?></p>
                </div>
                <div class="stats-grid perf-grid" id="hvnly-cache-performance-grid">
                    <?php foreach ($performance_items as $perf_key => $perf_item) : ?>
                        <div class="stat-card" data-perf="<?php echo esc_attr($perf_key); ?>">
                            <h3><?php echo esc_html($perf_item['label']); ?></h3>
                            <div class="stat-value"><?php echo esc_html($perf_item['value']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
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
            wp_send_json_error( __( 'Insufficient permissions', 'havenlytics' ) );
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
            wp_send_json_error( __( 'Insufficient permissions', 'havenlytics' ) );
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
            wp_send_json_error( __( 'Insufficient permissions', 'havenlytics' ) );
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
            wp_send_json_error( __( 'Insufficient permissions', 'havenlytics' ) );
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
            wp_send_json_error( __( 'Insufficient permissions', 'havenlytics' ) );
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
        if (function_exists('HVNLY_NAB') && HVNLY_NAB()->engine()) {
            HVNLY_NAB()->engine()->clear_transients_by_pattern('dynamic_css');
            delete_transient('hvnly_dynamic_css');
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