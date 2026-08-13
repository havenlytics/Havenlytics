<?php
/**
 * Server render callback for the Property Search block.
 *
 * Property Search is the search-first presentation of the property archive: the
 * top search bar and filter sidebar are enabled by default. It reuses the exact
 * same query + template pipeline as the Property Archive block, so there is no
 * duplicated rendering logic.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Property Search block renderer.
 *
 * @since 3.5.0
 */
final class PropertySearchBlockRenderer {

    /**
     * Render the Property Search block.
     *
     * @param array  $attributes Block attributes.
     * @param string $content    Inner content (unused).
     * @param object $block      Block instance (unused).
     * @return string
     */
    public static function render( $attributes = array(), string $content = '', $block = null ): string {
        unset($content, $block);

        if ( ! class_exists(PropertyArchiveBlockRenderer::class)) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : array();

        // Search-first defaults: always show the search bar; keep the sidebar on
        // unless the editor explicitly disabled it.
        $attributes['showTopSearch'] = true;
        if ( ! isset($attributes['showFilterSidebar'])) {
            $attributes['showFilterSidebar'] = true;
        }

        return PropertyArchiveBlockRenderer::render($attributes);
    }
}
