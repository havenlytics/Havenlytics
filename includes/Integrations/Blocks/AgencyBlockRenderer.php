<?php
/**
 * Server render callback for the Agency block.
 *
 * Renders the shared agencies archive partial using the existing
 * AgencyArchiveQuery service (agencies are taxonomy terms, so the query returns
 * an aggregated array structure rather than a WP_Query). Same partial and data
 * shape as the Elementor agencies widget and [hvnly_property_agencies] shortcode.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Agent\AgencyArchiveQuery;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agency block renderer.
 *
 * @since 3.5.0
 */
final class AgencyBlockRenderer {

    /**
     * Render the Agency block.
     *
     * @param array  $attributes Block attributes.
     * @param string $content    Inner content (unused).
     * @param object $block      Block instance (unused).
     * @return string
     */
    public static function render($attributes = [], string $content = '', $block = null): string {
        unset($content, $block);

        if (!class_exists(AgencyArchiveQuery::class) || !function_exists('hvnly_get_template_part')) {
            return '';
        }

        if (function_exists('hvnly_load_template_functions')) {
            hvnly_load_template_functions();
        }

        $attributes = is_array($attributes) ? $attributes : [];

        $columns  = self::clamp((int) ($attributes['columns'] ?? 4), 1, 4, 4);
        $per_page = self::clamp((int) ($attributes['postsPerPage'] ?? 12), 1, 48, 12);

        $orderby = (string) ($attributes['orderby'] ?? 'name');
        $orderby = in_array($orderby, ['name', 'date'], true) ? $orderby : 'name';

        $order = strtoupper((string) ($attributes['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $show_header   = !empty($attributes['showHeader']);
        $show_search   = !empty($attributes['showSearch']);
        $show_controls = !empty($attributes['showViewControls']);

        $default_view = (string) ($attributes['defaultView'] ?? 'grid');
        $default_view = in_array($default_view, ['grid', 'list'], true) ? $default_view : 'grid';

        $block_id = 'hvnly-block-agencies-' . substr(md5(wp_json_encode($attributes)), 0, 8);

        $query = AgencyArchiveQuery::query_agencies([
            'per_page' => $per_page,
            'orderby'  => $orderby,
            'order'    => $order,
        ]);

        $wrapper_classes = [
            'hvnly-content-wrapper',
            'hvnly-property-agencies-block',
            'hvnly-block-' . $block_id,
        ];

        $wrapper_attributes = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes([
                'class'               => implode(' ', $wrapper_classes),
                'data-widget-id'      => $block_id,
                'data-columns'        => (string) $columns,
                'data-posts-per-page' => (string) $per_page,
                'data-default-view'   => $default_view,
            ])
            : 'class="' . esc_attr(implode(' ', $wrapper_classes)) . '"';

        $view_filter = static function ($view) use ($default_view) {
            if (!empty($_GET['view'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return $view;
            }

            return $default_view;
        };
        add_filter('hvnly_property_archive_view_type', $view_filter, 20);

        ob_start();

        echo '<div ' . $wrapper_attributes . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        do_action('hvnly_before_archive_agency');

        hvnly_get_template_part('property-archive/partials/agencies-archive', null, [
            'query'              => $query,
            'show_header'        => $show_header,
            'title'              => (string) ($attributes['title'] ?? ''),
            'subtitle'           => (string) ($attributes['subtitle'] ?? ''),
            'show_search'        => $show_search,
            'show_view_controls' => $show_controls,
            'columns'            => $columns,
            'per_page'           => $per_page,
            'instance_id'        => $block_id,
            'search_action'      => self::search_action_url(),
            'wrapper_class'      => '',
            'card_context'       => 'agencies_archive',
        ]);

        do_action('hvnly_after_archive_agency');
        echo '</div>';

        remove_filter('hvnly_property_archive_view_type', $view_filter, 20);

        return (string) ob_get_clean();
    }

    /**
     * Resolve the search form action URL for the current page.
     *
     * @return string
     */
    private static function search_action_url(): string {
        global $post;

        if ($post instanceof \WP_Post && $post->ID > 0) {
            $permalink = get_permalink($post->ID);

            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }

        return home_url('/');
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
}
