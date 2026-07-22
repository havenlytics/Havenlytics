<?php
/**
 * Server render callback for the Property Archive block.
 *
 * Maps block attributes onto the shared property query + template layers so the
 * block produces markup identical to the archive page, the [hvnly_property_search]
 * shortcode, and the Elementor archive widget. No query, template, card, or
 * caching logic is duplicated here — everything delegates to existing services.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Frontend\Query\PropertyQueryArgsBuilder;
use HvnlyNab\Frontend\Query\PropertyQueryExecutor;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Property Archive block renderer.
 *
 * @since 3.5.0
 */
final class PropertyArchiveBlockRenderer {

    /**
     * Render the Property Archive block.
     *
     * @param array  $attributes Block attributes (validated against block.json).
     * @param string $content    Inner block content (unused — dynamic block).
     * @param object $block      Block instance (WP_Block), when available.
     * @return string Rendered HTML.
     */
    public static function render($attributes = [], string $content = '', $block = null): string {
        unset($content, $block);

        if (!class_exists(PropertyQueryExecutor::class)) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : [];

        $columns   = self::clamp((int) ($attributes['columns'] ?? 2), 1, 4, 2);
        $per_page  = self::clamp((int) ($attributes['postsPerPage'] ?? 12), 1, 48, 12);
        $view_type = self::sanitize_view($attributes['defaultView'] ?? 'grid');

        $show_top_search = !empty($attributes['showTopSearch']);
        $show_sidebar    = !empty($attributes['showFilterSidebar']);
        $sidebar_right   = ($attributes['sidebarPosition'] ?? 'left') === 'right';

        // A stable, unique instance id keeps pagination/AJAX state per block.
        $block_id = 'hvnly-block-' . substr(md5(wp_json_encode($attributes)), 0, 8);

        $data = self::build_query_data($attributes, $block_id);
        $page = PropertyQueryArgsBuilder::resolve_paged($block_id, $data, 1);

        $query = PropertyQueryExecutor::query($data, $page, $per_page, [
            'widget_id'      => $block_id,
            'filter_context' => 'ssr',
        ]);

        $wrapper_classes = [
            'hvnly-content-wrapper',
            'hvnly-property-archive__content-wrapper',
            'hvnly-property-archive-block',
            'hvnly-block-' . $block_id,
        ];

        if (!$show_sidebar) {
            $wrapper_classes[] = 'hvnly-no-sidebar';
        }

        if ($sidebar_right) {
            $wrapper_classes[] = 'hvnly-sidebar-right';
        }

        // get_block_wrapper_attributes() applies block supports (spacing,
        // color, border, typography, align) so theme.json / global styles work.
        $wrapper_attributes = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes([
                'class' => implode(' ', $wrapper_classes),
                'style' => '--hvnly-grid-columns: ' . $columns . ';',
                'data-widget-id'      => $block_id,
                'data-columns'        => (string) $columns,
                'data-posts-per-page' => (string) $per_page,
                'data-default-view'   => $view_type,
                'data-view-type'      => $view_type,
            ])
            : 'class="' . esc_attr(implode(' ', $wrapper_classes)) . '"';

        global $wp_query, $post;
        $original_wp_query = $wp_query;
        $original_post     = $post;

        $wp_query = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        ob_start();

        echo '<div ' . $wrapper_attributes . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ($show_top_search && function_exists('hvnly_get_template_part')) {
            add_filter('hvnly_should_show_top_search_bar', '__return_true');
            hvnly_get_template_part('search/search', 'filters');
            remove_filter('hvnly_should_show_top_search_bar', '__return_true');
        }

        echo '<div class="hvnly-with-sidebar__contents__grids">';

        if ($show_sidebar && function_exists('hvnly_get_template_part')) {
            hvnly_get_template_part('search/filter', 'sidebar');
        }

        echo '<section class="hvnly-property--grid--listings">';

        self::render_view_controls($query, $per_page);

        if (function_exists('hvnly_get_template_part')) {
            hvnly_get_template_part('archive/property', 'loop');
        }

        self::render_pagination($query, $block_id, $per_page);

        echo '</section>';
        echo '</div>';
        echo '</div>';

        // Restore global query state so nested loops never bleed out.
        $wp_query = $original_wp_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $post     = $original_post;     // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        wp_reset_postdata();

        return (string) ob_get_clean();
    }

    /**
     * Translate block attributes into the shared query filter payload.
     *
     * Uses the same filter keys the unified PropertyQueryArgsBuilder already
     * understands (department, property_type, location, status, orderby,
     * featured_only, price/room ranges). Current URL filters still take
     * precedence via the builder, matching archive/widget behaviour.
     *
     * @param array  $attributes Block attributes.
     * @param string $block_id   Block instance id.
     * @return array
     */
    private static function build_query_data(array $attributes, string $block_id): array {
        $current = function_exists('hvnly_get_current_filters') ? hvnly_get_current_filters() : [];
        $data    = is_array($current) ? $current : [];

        if (empty($data['orderby']) && !empty($attributes['orderby'])) {
            $data['orderby'] = sanitize_key((string) $attributes['orderby']);
        }

        if (empty($data['department']) && !empty($attributes['department'])) {
            $data['department'] = sanitize_title((string) $attributes['department']);
        }

        self::maybe_apply_terms($data, 'property_type', $attributes['propertyType'] ?? []);
        self::maybe_apply_terms($data, 'status', $attributes['status'] ?? []);
        self::maybe_apply_terms($data, 'location', $attributes['location'] ?? []);

        if (!empty($attributes['featuredOnly'])) {
            $data['featured_only'] = 'yes';
        }

        $data['widget_id'] = $block_id;

        return $data;
    }

    /**
     * Apply an array of term slugs to a builder filter key when non-empty.
     *
     * @param array  $data  Filter payload (by reference).
     * @param string $key   Builder filter key.
     * @param mixed  $terms Term slugs from the block attribute.
     * @return void
     */
    private static function maybe_apply_terms(array &$data, string $key, $terms): void {
        if (!empty($data[$key])) {
            return; // URL filter wins.
        }

        if (!is_array($terms) || empty($terms)) {
            return;
        }

        $clean = array_values(array_filter(array_map('sanitize_title', $terms)));

        if (!empty($clean)) {
            $data[$key] = $clean;
        }
    }

    /**
     * Render the result count + view controls using shared partials.
     *
     * @param \WP_Query $query    The property query.
     * @param int       $per_page Posts per page.
     * @return void
     */
    private static function render_view_controls(\WP_Query $query, int $per_page): void {
        if (!function_exists('hvnly_get_template_part')) {
            return;
        }

        $total   = (int) $query->found_posts;
        $current = max(1, (int) $query->get('paged'));
        $start   = (($current - 1) * $per_page) + 1;
        $end     = min($current * $per_page, $total);
        $filters = function_exists('hvnly_get_current_filters') ? hvnly_get_current_filters() : [];

        echo '<div class="hvnly-property--view--controls">';
        hvnly_get_template_part('search/result', 'count', [
            'total_properties' => $total,
            'start'            => $total > 0 ? $start : 0,
            'end'              => $end,
            'current_filters'  => $filters,
        ]);
        hvnly_get_template_part('archive/view', 'controls');
        echo '</div>';
    }

    /**
     * Render pagination using the shared pagination renderer.
     *
     * @param \WP_Query $query    The property query.
     * @param string    $block_id Block instance id.
     * @param int       $per_page Posts per page.
     * @return void
     */
    private static function render_pagination(\WP_Query $query, string $block_id, int $per_page): void {
        if (!function_exists('hvnly_render_property_listing_pagination')) {
            return;
        }

        $total     = (int) $query->found_posts;
        $current   = max(1, (int) $query->get('paged'));
        $max_pages = (int) $query->max_num_pages;

        if ($max_pages <= 1 && $total > $per_page) {
            $max_pages = (int) ceil($total / $per_page);
        }

        hvnly_render_property_listing_pagination([
            'current_page' => $current,
            'max_pages'    => $max_pages,
            'per_page'     => $per_page,
            'found_posts'  => $total,
            'instance_id'  => $block_id,
        ]);
    }

    /**
     * Clamp an integer into a range with a fallback.
     *
     * @param int $value   Raw value.
     * @param int $min     Minimum.
     * @param int $max     Maximum.
     * @param int $default Fallback when out of range.
     * @return int
     */
    private static function clamp(int $value, int $min, int $max, int $default): int {
        if ($value < $min || $value > $max) {
            return $default;
        }

        return $value;
    }

    /**
     * Sanitize the view type attribute.
     *
     * @param string $view Raw view value.
     * @return string grid|list|map
     */
    private static function sanitize_view(string $view): string {
        $view = strtolower(sanitize_key($view));

        return in_array($view, ['grid', 'list', 'map'], true) ? $view : 'grid';
    }
}
