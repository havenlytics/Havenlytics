<?php
/**
 * Server render callback for the Featured Properties block.
 *
 * Premium section (header + grid / carousel) that REUSES the
 * Property Card Builder for every card (all configured fields appear). Backend
 * reused: PropertyQueryExecutor for the featured set. Only the layout is bespoke.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Frontend\Query\PropertyQueryExecutor;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Featured Properties block renderer.
 *
 * @since 3.5.0
 */
final class FeaturedPropertiesBlockRenderer {

    /**
     * @param array  $attributes Block attributes.
     * @param string $content    Inner content (unused).
     * @param object $block      Block instance (unused).
     * @return string
     */
    public static function render($attributes = [], string $content = '', $block = null): string {
        unset($content, $block);

        if (!class_exists(PropertyQueryExecutor::class) || !function_exists('hvnly_get_template_part')) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : [];

        $layout = (string) ($attributes['layout'] ?? 'grid');
        // Spotlight control removed — keep saved blocks working as grid.
        if ('spotlight' === $layout) {
            $layout = 'grid';
        }
        if (!in_array($layout, ['grid', 'carousel'], true)) {
            $layout = 'grid';
        }
        $per_page = BlockRenderSupport::clamp((int) ($attributes['postsPerPage'] ?? 6), 1, 24, 6);
        $columns  = BlockRenderSupport::clamp((int) ($attributes['columns'] ?? 3), 1, 4, 3);

        $block_id = 'hvnly-block-featured-' . substr(md5(wp_json_encode($attributes)), 0, 8);

        $data = ['widget_id' => $block_id, 'featured_only' => 'yes'];
        if (!empty($attributes['orderby'])) {
            $data['orderby'] = sanitize_key((string) $attributes['orderby']);
        }

        $query = PropertyQueryExecutor::query($data, 1, $per_page, [
            'widget_id'      => $block_id,
            'filter_context' => 'ssr',
        ]);

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes([
                'class'        => 'hvnly-content-wrapper',
                // Same column token Property Search / Archive set on the block wrapper.
                'style'        => '--hvnly-grid-columns: ' . $columns . ';',
                'data-columns' => (string) $columns,
            ])
            : 'class="hvnly-content-wrapper" style="--hvnly-grid-columns: ' . esc_attr((string) $columns) . ';" data-columns="' . esc_attr((string) $columns) . '"';

        ob_start();
        echo '<div ' . $wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        hvnly_get_template_part('blocks/featured-properties', null, [
            'query'    => $query,
            'layout'   => $layout,
            'columns'  => $columns,
            'header'   => BlockRenderSupport::header_args($attributes),
            'carousel' => [
                'visible'        => BlockRenderSupport::clamp((int) ($attributes['visibleSlides'] ?? 3), 1, 5, 3),
                'visible_tablet' => 2,
                'visible_mobile' => 1,
                'autoplay'       => !empty($attributes['autoplay']),
                'center'         => false,
            ],
        ]);

        echo '</div>';

        return (string) ob_get_clean();
    }
}
