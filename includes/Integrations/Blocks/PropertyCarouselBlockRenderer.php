<?php
/**
 * Server render callback for the Property Carousel block.
 *
 * Dedicated premium carousel (responsive per-breakpoint counts, center mode,
 * autoplay, vertically-centered arrows, dots, touch) that REUSES the Property
 * Card Builder for every slide. Backend reused: PropertyQueryExecutor.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

use HvnlyNab\Frontend\Query\PropertyQueryExecutor;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Property Carousel block renderer.
 *
 * @since 3.5.0
 */
final class PropertyCarouselBlockRenderer {

    /**
     * @param array  $attributes Block attributes.
     * @param string $content    Inner content (unused).
     * @param object $block      Block instance (unused).
     * @return string
     */
    public static function render( $attributes = array(), string $content = '', $block = null ): string {
        unset($content, $block);

        if ( ! class_exists(PropertyQueryExecutor::class) || ! function_exists('hvnly_get_template_part')) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : array();

        $per_page = BlockRenderSupport::clamp( (int) ( $attributes['postsPerPage'] ?? 9 ), 1, 24, 9);
        $block_id = 'hvnly-block-carousel-' . substr(md5(wp_json_encode($attributes)), 0, 8);

        $data = array( 'widget_id' => $block_id );
        if ( ! empty($attributes['orderby'])) {
            $data['orderby'] = sanitize_key( (string) $attributes['orderby']);
        }
        if ( ! empty($attributes['featuredOnly'])) {
            $data['featured_only'] = 'yes';
        }

        $query = PropertyQueryExecutor::query($data, 1, $per_page, array(
            'widget_id'      => $block_id,
            'filter_context' => 'ssr',
        ));

        $header          = BlockRenderSupport::header_args($attributes);
        $header['title'] = $header['title'] !== ''
            ? $header['title']
            : sanitize_text_field( (string) ( $attributes['title'] ?? '' ));

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes(array( 'class' => 'hvnly-content-wrapper' ))
            : 'class="hvnly-content-wrapper"';

        ob_start();
        echo '<div ' . $wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        hvnly_get_template_part('blocks/carousel', null, array(
            'query'          => $query,
            'header'         => $header,
            'visible'        => BlockRenderSupport::clamp( (int) ( $attributes['visibleSlides'] ?? 3 ), 1, 5, 3),
            'visible_tablet' => BlockRenderSupport::clamp( (int) ( $attributes['visibleTablet'] ?? 2 ), 1, 4, 2),
            'visible_mobile' => BlockRenderSupport::clamp( (int) ( $attributes['visibleMobile'] ?? 1 ), 1, 2, 1),
            'autoplay'       => ! empty($attributes['autoplay']),
            'center'         => ! empty($attributes['centerMode']),
            'show_nav'       => ! isset($attributes['showNav']) || ! empty($attributes['showNav']),
            'show_dots'      => ! isset($attributes['showDots']) || ! empty($attributes['showDots']),
        ));

        echo '</div>';

        return (string) ob_get_clean();
    }
}
