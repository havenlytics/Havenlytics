<?php
/**
 * Map Helper Functions for Havenlytics
 *
 * @package     Havenlytics
 * @subpackage  Functions
 * @since       2.2.0
 */

if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Get map provider
 *
 * @return string
 */
function hvnly_get_map_provider() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    $provider         = $map_settings['hvnly_map_provider'] ?? 'leaflet';

    $allowed_providers = array( 'leaflet', 'openstreetmap', 'google' );
    if ( ! in_array($provider, $allowed_providers, true)) {
        $provider = 'leaflet';
    }

    return apply_filters('hvnly_map_provider', $provider);
}

/**
 * Check if Google Maps should be used
 *
 * @return bool
 */
function hvnly_use_google_maps() {
    $provider = hvnly_get_map_provider();
    $api_key  = hvnly_get_google_maps_api_key();
    return ( $provider === 'google' && ! empty($api_key) );
}

/**
 * Check if OpenStreetMap should be used
 *
 * @return bool
 */
function hvnly_use_openstreetmap() {
    $provider = hvnly_get_map_provider();
    return ( $provider === 'openstreetmap' );
}

/**
 * Check if Leaflet should be used
 *
 * @return bool
 */
function hvnly_use_leaflet() {
    $provider = hvnly_get_map_provider();
    return ( $provider === 'leaflet' );
}

/**
 * Get Google Maps API key
 *
 * @return string
 */
function hvnly_get_google_maps_api_key() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    $api_key          = $map_settings['hvnly_map_api_key'] ?? '';

    return apply_filters('hvnly_google_maps_api_key', $api_key);
}

/**
 * Google Cloud Map ID (required for Advanced Markers; optional for classic markers).
 *
 * @return string
 */
function hvnly_get_google_map_id() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    $map_id           = $map_settings['hvnly_google_map_id'] ?? '';

    return apply_filters( 'hvnly_google_map_id', $map_id );
}

/**
 * Update map settings in the canonical plugin settings store.
 *
 * @param array $partial Partial map settings (e.g. hvnly_map_provider, hvnly_map_api_key).
 * @return array Updated map settings.
 */
function hvnly_update_map_settings( array $partial ) {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $updated          = $settings_manager->update_map_settings( $partial );

    return apply_filters( 'hvnly_update_map_settings', $updated, $partial );
}

/**
 * Get OpenStreetMap tile URL
 *
 * @return string
 */
function hvnly_get_osm_tile_url() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    $tile_url         = $map_settings['hvnly_osm_tile_url'] ?? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

    return apply_filters('hvnly_osm_tile_url', $tile_url);
}

/**
 * Get OpenStreetMap attribution
 *
 * @return string
 */
function hvnly_get_osm_attribution() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    $attribution      = $map_settings['hvnly_osm_attribution'] ?? '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

    return apply_filters('hvnly_osm_attribution', $attribution);
}

/**
 * Get map zoom level
 *
 * @return int
 */
function hvnly_get_map_zoom() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    return absint($map_settings['hvnly_map_zoom'] ?? 12);
}

/**
 * Get default latitude
 *
 * @return float
 */
function hvnly_get_default_latitude() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    return (float) ( $map_settings['hvnly_default_lat'] ?? 51.514939 );
}

/**
 * Get default longitude
 *
 * @return float
 */
function hvnly_get_default_longitude() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    return (float) ( $map_settings['hvnly_default_lng'] ?? -0.091839 );
}

/**
 * Check if marker clustering is enabled
 *
 * @return bool
 */
function hvnly_is_marker_clustering_enabled() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    return (bool) ( $map_settings['hvnly_cluster_markers'] ?? true );
}

/**
 * Check if fullscreen button should be shown
 *
 * @return bool
 */
function hvnly_is_fullscreen_enabled() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    return (bool) ( $map_settings['hvnly_show_fullscreen'] ?? true );
}

/**
 * Check if zoom control should be shown
 *
 * @return bool
 */
function hvnly_is_zoom_control_enabled() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    return (bool) ( $map_settings['hvnly_show_zoom_control'] ?? true );
}

/**
 * Check if scroll wheel zoom is enabled
 *
 * @return bool
 */
function hvnly_is_scroll_wheel_enabled() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    return (bool) ( $map_settings['hvnly_show_scroll_wheel'] ?? true );
}

/**
 * Get Google Maps map type
 *
 * @return string
 */
function hvnly_get_google_map_type() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    $type             = $map_settings['hvnly_google_map_type'] ?? 'roadmap';

    $allowed_types = array( 'roadmap', 'satellite', 'hybrid', 'terrain' );
    if ( ! in_array($type, $allowed_types, true)) {
        $type = 'roadmap';
    }

    return $type;
}

/**
 * Get marker color
 *
 * @return string
 */
function hvnly_get_marker_color() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    $brand_default    = function_exists('hvnly_get_brand_color') ? hvnly_get_brand_color() : '#6C60FE';

    if ( ! function_exists('hvnly_marker_color_is_custom') || ! hvnly_marker_color_is_custom()) {
        return $brand_default;
    }

    $color = $map_settings['hvnly_marker_color'] ?? $brand_default;

    if ( ! preg_match('/^#[a-f0-9]{6}$/i', $color)) {
        $color = $brand_default;
    }

    return $color;
}

/**
 * Check if custom marker is enabled
 *
 * @return bool
 */
function hvnly_is_custom_marker_enabled() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    return (bool) ( $map_settings['hvnly_custom_marker'] ?? false );
}

/**
 * Get map style (for Leaflet/OSM)
 *
 * @return string
 */
function hvnly_get_map_style() {
    $settings_manager = \HvnlyNab\Core\SettingsManager::get_instance();
    $map_settings     = $settings_manager->get_map_settings();
    return $map_settings['hvnly_map_style'] ?? 'standard';
}

/**
 * Get map configuration array for JS localization
 *
 * @return array
 */
function hvnly_get_map_config() {
    $provider = hvnly_get_map_provider();
    $api_key  = hvnly_get_google_maps_api_key();

    // If Google Maps selected but no API key, fallback to Leaflet
    if ($provider === 'google' && empty($api_key)) {
        $provider = 'leaflet';
    }

    return array(
        'provider' => $provider,
        'api_key' => $api_key,
        'default_lat' => hvnly_get_default_latitude(),
        'default_lng' => hvnly_get_default_longitude(),
        'zoom_level' => hvnly_get_map_zoom(),
        'cluster_markers' => hvnly_is_marker_clustering_enabled(),
        'google_map_type' => hvnly_get_google_map_type(),
        'osm_tile_url' => hvnly_get_osm_tile_url(),
        'osm_attribution' => hvnly_get_osm_attribution(),
        'show_fullscreen' => hvnly_is_fullscreen_enabled(),
        'show_zoom_control' => hvnly_is_zoom_control_enabled(),
        'show_scroll_wheel' => hvnly_is_scroll_wheel_enabled(),
        'marker_color' => hvnly_get_marker_color(),
        'marker_uses_brand_color' => function_exists('hvnly_marker_color_is_custom') ? ! hvnly_marker_color_is_custom() : false,
        'marker_color_css' => function_exists('hvnly_get_marker_color_css') ? hvnly_get_marker_color_css() : hvnly_get_marker_color(),
        'custom_marker' => hvnly_is_custom_marker_enabled(),
        'map_style' => hvnly_get_map_style(),
        'plugin_url' => HVNLYNAB_URL,
        'assets_url' => HVNLYNAB_ASSETS_URL,
    );
}

// /**
//  * Debug function to log map configuration (only in debug mode)
//  *
//  * @return void
//  */
// function hvnly_debug_map_config() {
//     if (!defined('WP_DEBUG') || !WP_DEBUG) {
//         return;
//     }

//     $config = hvnly_get_map_config();
//     error_log('Havenlytics Map Config: ' . json_encode([
//         'provider' => $config['provider'],
//         'zoom_level' => $config['zoom_level'],
//         'cluster_markers' => $config['cluster_markers'],
//         'has_api_key' => !empty($config['api_key'])
//     ]));
// }
