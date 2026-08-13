<?php
/**
 * Enterprise Cache - Enhanced caching using new database tables
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @since       2.3.0
 */

namespace HvnlyNab\Core;

defined('ABSPATH') || exit;

class EnterpriseCache {

    private static $instance = null;
    private $engine;
    private $cache_table;

    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->engine      = HVNLY_NAB()->engine();
        $this->cache_table = $wpdb->prefix . 'hvnly_section_cache';
    }

    /**
     * Get sections - tries cache table first, falls back to existing method
     */
    public function get_sections( $force_refresh = false ): array {
        if ( ! $force_refresh && \hvnly_is_cache_enabled()) {
            $cached = $this->get_from_cache_table();
            if ($cached !== null) {
                return $cached;
            }
        }

        // Use your existing DnDSections API
        $sections = $this->get_from_existing_api();

        // Store in cache table for future
        if (\hvnly_is_cache_enabled()) {
            $this->store_in_cache_table($sections);
        }

        return $sections;
    }

    private function get_from_cache_table(): ?array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned cache table via $wpdb->prefix.
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT cache_data, expires_at FROM {$wpdb->prefix}hvnly_section_cache
                 WHERE section_key = %s AND (expires_at IS NULL OR expires_at > NOW())
                 ORDER BY id DESC LIMIT 1",
                'all_sections'
            )
        );

        if ($result) {
            return json_decode($result->cache_data, true);
        }

        return null;
    }

    private function get_from_existing_api(): array {
        $request = new \WP_REST_Request('GET', '/hvnlynab/v1/pb-dnd-sections');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

        $response = rest_do_request($request);

        if ($response->is_error()) {
            return get_option('hvnly_property_builder.sections', array());
        }

        $data = $response->get_data();
        return $data['data'] ?? array();
    }

    private function store_in_cache_table( array $sections ): void {
        global $wpdb;

        $cache_data = json_encode($sections);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned cache table write.
        $wpdb->replace($this->cache_table, array(
            'section_key' => 'all_sections',
            'cache_data' => $cache_data,
            'cache_hash' => md5($cache_data),
            'expires_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
        ));
    }

    public function invalidate_sections(): void {
        global $wpdb;

        if ($this->engine) {
            $this->engine->clear_all_cache();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned cache table purge.
        $wpdb->delete($this->cache_table, array( 'section_key' => 'all_sections' ));
        CacheManager::clear_transients_by_pattern('section_');
    }
}
