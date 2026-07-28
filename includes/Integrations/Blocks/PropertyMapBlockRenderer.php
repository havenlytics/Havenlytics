<?php
/**
 * Server render callback for the Property Map block.
 *
 * Dedicated premium map widget. Renders a map container + control toolbar; the
 * block map controller (hvnly-block-map.js) fetches markers from the EXISTING
 * hvnly_get_properties_for_map AJAX endpoint (backend reused, no duplicate query)
 * and renders premium popup cards on the bundled Leaflet map.
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
 * Property Map block renderer.
 *
 * @since 3.5.0
 */
final class PropertyMapBlockRenderer {

    /**
     * @param array  $attributes Block attributes.
     * @param string $content    Inner content (unused).
     * @param object $block      Block instance (unused).
     * @return string
     */
    public static function render($attributes = [], string $content = '', $block = null): string {
        unset($content, $block);

        if (!function_exists('hvnly_get_template_part')) {
            return '';
        }

        $attributes = is_array($attributes) ? $attributes : [];

        $block_id = 'hvnly-block-map-' . substr(md5(wp_json_encode($attributes)), 0, 8);

        $filters = [];
        if (!empty($attributes['featuredOnly'])) {
            $filters['featured_only'] = 'yes';
        }
        if (!empty($attributes['department'])) {
            $filters['department'] = sanitize_title((string) $attributes['department']);
        }
        if (!empty($attributes['status'])) {
            $filters['status'] = sanitize_title((string) $attributes['status']);
        }
        if (!empty($attributes['propertyType'])) {
            $filters['property_type'] = sanitize_title((string) $attributes['propertyType']);
        }

        // "Show all" is a bounded cap (500), not unlimited — map payloads must
        // stay predictable on large sites.
        $per_page = !empty($attributes['showAll'])
            ? 500
            : BlockRenderSupport::clamp((int) ($attributes['postsPerPage'] ?? 48), 1, 200, 48);

        $marker_size = (string) ($attributes['markerSize'] ?? 'md');
        if (!in_array($marker_size, ['sm', 'md', 'lg'], true)) {
            $marker_size = 'md';
        }
        $marker_style  = ('dot' === ($attributes['markerStyle'] ?? 'pin')) ? 'dot' : 'pin';
        $popup_style   = ('compact' === ($attributes['popupStyle'] ?? 'default')) ? 'compact' : 'default';
        $popup_trigger = ('hover' === ($attributes['popupTrigger'] ?? 'click')) ? 'hover' : 'click';

        // Archive-map SSOT: hvnly_get_map_config() resolves the effective
        // global provider (google→leaflet fallback without a key), default
        // center/zoom, OSM tiles and marker color — the block never re-derives
        // provider logic.
        $map_config = function_exists('hvnly_get_map_config') ? hvnly_get_map_config() : [];
        $provider   = isset($map_config['provider']) ? (string) $map_config['provider'] : 'leaflet';
        if (!in_array($provider, ['leaflet', 'openstreetmap', 'google'], true)) {
            $provider = 'leaflet';
        }

        $config = [
            'canvasId'       => $block_id,
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('hvnly_ajax_request'),
            'filters'        => $filters,
            'perPage'        => $per_page,
            'height'         => BlockRenderSupport::clamp((int) ($attributes['height'] ?? 520), 240, 1000, 520),
            'zoom'           => BlockRenderSupport::clamp((int) ($attributes['zoom'] ?? 12), 1, 20, 12),
            'cluster'        => !empty($attributes['clustering']),
            'clusterRadius'  => BlockRenderSupport::clamp((int) ($attributes['clusterRadius'] ?? 48), 20, 180, 48),
            'clusterMaxZoom' => BlockRenderSupport::clamp((int) ($attributes['clusterMaxZoom'] ?? 0), 0, 19, 0),
            'fitBounds'      => !isset($attributes['autoFit']) || !empty($attributes['autoFit']),
            'geolocate'      => !empty($attributes['currentLocation']),
            'scrollWheel'    => !empty($attributes['scrollWheel']),
            'provider'       => $provider,
            'googleMapType'  => isset($map_config['google_map_type']) ? (string) $map_config['google_map_type'] : 'roadmap',
            'tileUrl'        => self::tile_url((string) ($attributes['mapStyle'] ?? 'standard'), $map_config),
            'attribution'    => isset($map_config['osm_attribution']) && '' !== $map_config['osm_attribution']
                ? (string) $map_config['osm_attribution']
                : '&copy; OpenStreetMap contributors',
            'markerColor'    => isset($map_config['marker_color_css']) && '' !== $map_config['marker_color_css']
                ? (string) $map_config['marker_color_css']
                : self::marker_color(),
            'markerColorHex' => isset($map_config['marker_color']) && '' !== $map_config['marker_color']
                ? (string) $map_config['marker_color']
                : self::marker_color(),
            'markerSize'     => $marker_size,
            'markerStyle'    => $marker_style,
            'popupStyle'     => $popup_style,
            'popupWidth'     => BlockRenderSupport::clamp((int) ($attributes['popupWidth'] ?? 300), 240, 360, 300),
            'popupTrigger'   => $popup_trigger,
            'animations'     => !isset($attributes['animations']) || !empty($attributes['animations']),
            'showPrice'      => !isset($attributes['showPrice']) || !empty($attributes['showPrice']),
            'showFavorite'   => !isset($attributes['showFavorite']) || !empty($attributes['showFavorite']),
            'showStatus'     => !isset($attributes['showStatus']) || !empty($attributes['showStatus']),
            'showMeta'       => !isset($attributes['showMeta']) || !empty($attributes['showMeta']),
            'showCta'        => !isset($attributes['showCta']) || !empty($attributes['showCta']),
            'center'         => self::default_center($map_config),
            'interactive'    => true,
            'i18n'           => [
                'loadingMap'            => __( 'Loading map…', 'havenlytics' ),
                'couldNotLoadProperties'=> __( 'Couldn’t load properties.', 'havenlytics' ),
                'retry'                 => __( 'Retry', 'havenlytics' ),
                'save'                  => __( 'Save', 'havenlytics' ),
                'view'                  => __( 'View', 'havenlytics' ),
                'viewProperty'          => __( 'View Property', 'havenlytics' ),
                'untitledProperty'      => __( 'Untitled Property', 'havenlytics' ),
            ],
        ];

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes(['class' => 'hvnly-content-wrapper'])
            : 'class="hvnly-content-wrapper"';

        ob_start();
        echo '<div ' . $wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        // Editor (REST): same map shell, interactions disabled — real tiles + markers.
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $config['interactive'] = false;
            $config['scrollWheel'] = false;
            $config['geolocate']   = false;
            $config['canvasId']    = $block_id . '-editor';
        }

        hvnly_get_template_part('blocks/property-map', null, ['config' => $config]);

        echo '</div>';

        return (string) ob_get_clean();
    }

    /**
     * Resolve a tile URL for the chosen map style (Leaflet/OSM providers).
     *
     * @param string $style      standard|light|dark
     * @param array  $map_config Archive map config (hvnly_get_map_config()).
     * @return string
     */
    private static function tile_url(string $style, array $map_config = []): string {
        switch (sanitize_key($style)) {
            case 'light':
                return 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png';
            case 'dark':
                return 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png';
            default:
                // Archive SSOT first (settings blob), legacy flat option second.
                if (isset($map_config['osm_tile_url']) && is_string($map_config['osm_tile_url']) && '' !== $map_config['osm_tile_url']) {
                    return $map_config['osm_tile_url'];
                }

                $option = get_option('hvnly_map_osm_tile_url');

                return is_string($option) && '' !== $option
                    ? $option
                    : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        }
    }

    /**
     * @return string
     */
    private static function marker_color(): string {
        $color = get_option('hvnly_map_marker_color');

        return is_string($color) && '' !== $color ? $color : '#6c60fe';
    }

    /**
     * @param array $map_config Archive map config (hvnly_get_map_config()).
     * @return array{lat: float, lng: float}
     */
    private static function default_center(array $map_config = []): array {
        // Archive SSOT first (settings blob), legacy flat options second.
        if (isset($map_config['default_lat'], $map_config['default_lng'])
            && is_numeric($map_config['default_lat'])
            && is_numeric($map_config['default_lng'])
        ) {
            return [
                'lat' => (float) $map_config['default_lat'],
                'lng' => (float) $map_config['default_lng'],
            ];
        }

        $lat = get_option('hvnly_default_lat');
        $lng = get_option('hvnly_default_lng');

        return [
            'lat' => is_numeric($lat) ? (float) $lat : 51.514939,
            'lng' => is_numeric($lng) ? (float) $lng : -0.091839,
        ];
    }
}
