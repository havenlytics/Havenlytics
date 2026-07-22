<?php
/**
 * Server render callback for the Agents block.
 *
 * Renders the shared agents archive partial with a query built by the shared
 * AgentArchiveQuery service — the same partial and data shape used by the
 * Elementor agents widget and the [hvnly_property_agents] shortcode.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Agent\AgentArchiveQuery;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agents block renderer.
 *
 * @since 3.5.0
 */
final class AgentsBlockRenderer {

    /**
     * Render the Agents block.
     *
     * @param array  $attributes Block attributes.
     * @param string $content    Inner content (unused).
     * @param object $block      Block instance (unused).
     * @return string
     */
    public static function render($attributes = [], string $content = '', $block = null): string {
        unset($content, $block);

        if (!class_exists(AgentArchiveQuery::class) || !function_exists('hvnly_get_template_part')) {
            return '';
        }

        if (function_exists('hvnly_load_template_functions')) {
            hvnly_load_template_functions();
        }

        $attributes = is_array($attributes) ? $attributes : [];

        $columns  = self::clamp((int) ($attributes['columns'] ?? 4), 1, 4, 4);
        $per_page = self::clamp((int) ($attributes['postsPerPage'] ?? 12), 1, 48, 12);

        $orderby = (string) ($attributes['orderby'] ?? 'title');
        $orderby = in_array($orderby, ['title', 'date'], true) ? $orderby : 'title';

        $order = strtoupper((string) ($attributes['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $show_header   = !empty($attributes['showHeader']);
        $show_search   = !empty($attributes['showSearch']);
        $show_controls = !empty($attributes['showViewControls']);

        $default_view = (string) ($attributes['defaultView'] ?? 'grid');
        $default_view = in_array($default_view, ['grid', 'list'], true) ? $default_view : 'grid';

        $block_id = 'hvnly-block-agents-' . substr(md5(wp_json_encode($attributes)), 0, 8);

        $query = AgentArchiveQuery::build_query([
            'per_page' => $per_page,
            'orderby'  => $orderby,
            'order'    => $order,
        ]);

        $wrapper_classes = [
            'hvnly-content-wrapper',
            'hvnly-property-agents-block',
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

        global $wp_query, $post;
        $original_wp_query = $wp_query;
        $original_post     = $post;

        $wp_query = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

        $view_filter = static function ($view) use ($default_view) {
            if (!empty($_GET['view'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return $view;
            }

            return $default_view;
        };
        add_filter('hvnly_property_archive_view_type', $view_filter, 20);

        ob_start();

        echo '<div ' . $wrapper_attributes . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        do_action('hvnly_before_archive_agent');

        hvnly_get_template_part('property-archive/partials/agents-archive', null, [
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
            'card_context'       => 'agents_archive',
        ]);

        do_action('hvnly_after_archive_agent');
        echo '</div>';

        remove_filter('hvnly_property_archive_view_type', $view_filter, 20);

        $wp_query = $original_wp_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        $post     = $original_post;     // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        wp_reset_postdata();

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
