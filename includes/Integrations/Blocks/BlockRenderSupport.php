<?php
/**
 * Small shared helpers for premium block renderers.
 *
 * @package     Havenlytics
 * @subpackage  Integrations\Blocks
 * @since       3.5.0
 */

namespace HvnlyNab\Integrations\Blocks;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * @since 3.5.0
 */
final class BlockRenderSupport {

    /**
     * Clamp an integer into a range with a fallback.
     *
     * @param int $value   Raw value.
     * @param int $min     Minimum.
     * @param int $max     Maximum.
     * @param int $default Fallback when out of range.
     * @return int
     */
    public static function clamp(int $value, int $min, int $max, int $default): int {
        if ($value < $min || $value > $max) {
            return $default;
        }

        return $value;
    }

    /**
     * Build the section-header args from block attributes.
     *
     * @param array $attributes Block attributes.
     * @return array<string, mixed>
     */
    public static function header_args(array $attributes): array {
        $align = isset($attributes['sectionAlign']) ? (string) $attributes['sectionAlign'] : 'left';
        if (!in_array($align, ['left', 'center', 'right'], true)) {
            $align = 'left';
        }

        $target = isset($attributes['sectionButtonTarget']) ? (string) $attributes['sectionButtonTarget'] : '_self';
        if (!in_array($target, ['_self', '_blank'], true)) {
            $target = '_self';
        }

        return [
            'show'          => !isset($attributes['showHeader']) || !empty($attributes['showHeader']),
            'subtitle'      => sanitize_text_field((string) ($attributes['sectionSubtitle'] ?? '')),
            'title'         => sanitize_text_field((string) ($attributes['sectionTitle'] ?? '')),
            'description'   => sanitize_text_field((string) ($attributes['sectionDescription'] ?? '')),
            'align'         => $align,
            'button_show'   => !empty($attributes['sectionButtonShow']),
            'button_text'   => sanitize_text_field((string) ($attributes['sectionButtonText'] ?? '')),
            'button_url'    => esc_url_raw((string) ($attributes['sectionButtonUrl'] ?? '')),
            'button_target' => $target,
        ];
    }
}
