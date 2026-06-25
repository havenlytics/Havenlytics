<?php
/**
 * Settings Manager for Havenlytics
 *
 * Handles retrieving and caching plugin settings for frontend use.
 * Provides backward compatibility for both snake_case and camelCase field names.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @since       2.0.3
 */

namespace HvnlyNab\Core;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Manager class
 * 
 * Handles retrieving and caching plugin settings for frontend use
 *
 * @since 2.0.3
 */
class SettingsManager
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;
    
    /**
     * Cached settings
     *
     * @var array|null
     */
    private $cached_settings = null;
    
    /**
     * Storage key for settings
     *
     * @var string
     */
    private $storage_key = 'hvnly_plugin_settings';
    
    /**
     * Share platform configurations
     *
     * @var array|null
     */
    private $share_platforms = null;
    
    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct()
    {
        add_action('init', [$this, 'load_settings'], 5);
        add_action('hvnly_settings_updated', [$this, 'clear_cache']);
    }
    
    /**
     * Load and cache all settings
     *
     * @return array
     */
    public function load_settings()
    {
        if (null !== $this->cached_settings) {
            return $this->cached_settings;
        }

        if ( function_exists( 'hvnly_maybe_migrate_legacy_settings' ) ) {
            hvnly_maybe_migrate_legacy_settings();
        }
        
        $settings = get_option($this->storage_key, []);
        
        // Ensure all required groups exist
        $default_groups = ['general', 'design', 'search', 'properties', 'shortcodes', 'property-details', 'contact-agent'];
        foreach ($default_groups as $group) {
            if (!isset($settings[$group]) || !is_array($settings[$group])) {
                $settings[$group] = [];
            }
        }
        
        $this->cached_settings = $settings;
        
        return $this->cached_settings;
    }
    
    /**
     * Get current property view type (single source of truth)
     *
     * @since 2.2.0
     * @return string 'GRID' or 'LIST'
     */
    public function get_current_property_view_type() {
        $settings = $this->load_settings();
        
        $search_view = isset($settings['search']['hvnly_propertyViewType']) 
            ? sanitize_text_field($settings['search']['hvnly_propertyViewType']) 
            : 'GRID';
        
        $allowed_views = array('GRID', 'LIST');
        if (!in_array($search_view, $allowed_views, true)) {
            $search_view = 'GRID';
        }
        
        return $search_view;
    }

    /**
     * Sync property view type across search settings.
     *
     * @since 2.2.0
     * @param string $view_type 'GRID' or 'LIST'
     * @return bool True if sync was performed, false otherwise
     */
    public function sync_property_view_type($view_type) {
        $allowed_views = array('GRID', 'LIST');
        if (!in_array($view_type, $allowed_views, true)) {
            return false;
        }
        
        $settings = get_option('hvnly_plugin_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }
        
        if (!isset($settings['search']) || !is_array($settings['search'])) {
            $settings['search'] = array();
        }
        
        $current_search_view = isset($settings['search']['hvnly_propertyViewType']) 
            ? $settings['search']['hvnly_propertyViewType'] 
            : 'GRID';
        
        if ($current_search_view === $view_type) {
            return false;
        }
        
        $settings['search']['hvnly_propertyViewType'] = $view_type;
        update_option('hvnly_plugin_settings', $settings);
        $this->clear_cache();
        
        do_action('hvnly_property_view_type_synced', $view_type, $settings);
        
        return true;
    }

    /**
     * Get general settings
     *
     * @return array
     */
    public function get_general_settings()
    {
        $settings = $this->load_settings();
        
        $defaults = [
            // Currency settings
            'hvnly_EnabledCurrencyFormat' => false,
            'hvnly_thousandSeparator' => ',',
            'hvnly_decimalSeparator' => '.',
            'hvnly_numberOfDecimals' => '0',
            'hvnly_thousandText' => 'K',
            'hvnly_millionText' => 'M',
            'hvnly_billionText' => 'B',
            'hvnly_currencyType' => 'USD',
            'hvnly_currencyPositionType' => 'LEFT',
            'hvnly_priceOnCallText' => 'priceOnCallNone',
            'hvnly_priceFormat' => 'comma',
            
            // Social share settings
            'hvnly_EnabledSocialShare' => true,
            'hvnly_enableSocialShare' => [],
            
            // Misc settings
            'hvnly_EnabledGutenbergEditor' => false,
            'hvnly_defaultCity' => 'Birmingham',
            'hvnly_defaultState' => 'Scotland',
            'hvnly_countryType' => 'GB',
        ];
        
        $general_settings = isset($settings['general']) ? $settings['general'] : [];
        
        return wp_parse_args($general_settings, $defaults);
    }

    /**
     * Get entire design settings group with backward compatibility
     *
     * @return array
     */
    public function get_design_settings()
    {
        $settings = $this->load_settings();
        
        $defaults = [
            'hvnly_brandColor' => '#6C60FE',
            'hvnly_secondaryColor' => '#764ba2',
            'hvnly_linkColor' => '#1E1E2F',
            'hvnly_linkHoverColor' => '#764ba2',
            'hvnly_titleColor' => '#1E1E2F',
            'hvnly_primaryTextColor' => '#1E1E2F',
            'hvnly_secondaryTextColor' => '#555555',
        ];
        
        $design_settings = isset($settings['design']) ? $settings['design'] : [];
        
        // Normalize settings - ensure all keys have hvnly_ prefix
        $normalized_settings = [];
        foreach ($design_settings as $key => $value) {
            // If key doesn't have hvnly_ prefix, add it
            if (strpos($key, 'hvnly_') !== 0) {
                $new_key = 'hvnly_' . $key;
                $normalized_settings[$new_key] = $value;
            } else {
                $normalized_settings[$key] = $value;
            }
        }
        
        // Merge with defaults
        return wp_parse_args($normalized_settings, $defaults);
    }
    
    /**
     * Get a specific design setting
     *
     * @param string $key     Setting key (with or without hvnly_ prefix)
     * @param mixed  $default Default value
     * @return mixed
     */
    public function get_design_setting($key, $default = null)
    {
        $design = $this->get_design_settings();
        
        // Ensure key has hvnly_ prefix for lookup
        $prefixed_key = 'hvnly_' . ltrim($key, 'hvnly_');
        
        if (isset($design[$prefixed_key])) {
            return $design[$prefixed_key];
        }
        
        // Try without prefix as fallback
        if (isset($design[$key])) {
            return $design[$key];
        }
        
        return $default;
    }

    /**
     * Get brand color
     *
     * @return string
     */
    public function get_brand_color()
    {
        return $this->get_design_setting('brandColor', '#6C60FE');
    }
    
    /**
     * Get search settings
     *
     * @return array
     */
    public function get_search_settings()
    {
        $settings = $this->load_settings();
        $defaults = [
            'hvnly_searchBarTitle' => __('Search Properties', 'havenlytics'),
            'hvnly_searchButtonText' => __('Search', 'havenlytics'),
            'hvnly_hideTopSearchTitle' => false,
            'hvnly_hideTopSearchBar' => false,
            'hvnly_hideLeftSearchBar' => false,
            'hvnly_numberOfColumns' => '2',
            'hvnly_searchResultPerpage' => '6',
            'hvnly_sidebarLayoutType' => 'LEFT',
            'hvnly_propertyViewType' => 'GRID',
            'hvnly_propertyOrderByType' => 'TITLE',
            'hvnly_propertySortByType' => 'date',
            'hvnly_propertyDepartmentOrderByType' => 'RENT',
            'hvnly_propertyThumbnailSize' => 'thumbnail',
            'hvnly_enableAjaxLoadMore' => true,
            'hvnly_AjaxLoadMoreButtonText' => __('Load More', 'havenlytics'),
            'hvnly_propertiesPerPage' => 6,
            'search_fields' => [],
        ];
        
        $search_settings = isset($settings['search']) ? $settings['search'] : [];
        
        return wp_parse_args($search_settings, $defaults);
    }
    
    /**
     * Check if AJAX Load More is enabled
     *
     * @return bool
     * @since 2.0.3
     */
    public function is_ajax_load_more_enabled()
    {
        $search_settings = $this->get_search_settings();
        return isset($search_settings['hvnly_enableAjaxLoadMore']) 
            ? (bool) $search_settings['hvnly_enableAjaxLoadMore'] 
            : true;
    }
    /**
     * Get number of properties to display per page
     *
     * @return int
     * @since 2.0.3
     */
    public function get_properties_per_page()
    {
        $search_settings = $this->get_search_settings();
        if ( isset( $search_settings['hvnly_propertiesPerPage'] ) && $search_settings['hvnly_propertiesPerPage'] !== '' ) {
            return absint( $search_settings['hvnly_propertiesPerPage'] );
        }
        if ( isset( $search_settings['hvnly_searchResultPerpage'] ) && $search_settings['hvnly_searchResultPerpage'] !== '' ) {
            return absint( $search_settings['hvnly_searchResultPerpage'] );
        }
        return 6;
    }

    /**
     * Grid columns on property archive/search results (grid view).
     *
     * @return int
     */
    public function get_archive_grid_columns()
    {
        $search_settings = $this->get_search_settings();
        $columns         = isset( $search_settings['hvnly_numberOfColumns'] )
            ? absint( $search_settings['hvnly_numberOfColumns'] )
            : 2;

        return max( 1, min( 4, $columns ) );
    }

    /**
     * Filter sidebar layout for archive search (LEFT or RIGHT).
     *
     * @return string LEFT|RIGHT
     */
    public function get_search_sidebar_layout()
    {
        $search_settings = $this->get_search_settings();
        $layout          = isset( $search_settings['hvnly_sidebarLayoutType'] )
            ? strtoupper( (string) $search_settings['hvnly_sidebarLayoutType'] )
            : 'LEFT';

        return in_array( $layout, array( 'LEFT', 'RIGHT' ), true ) ? $layout : 'LEFT';
    }

    /**
     * Get map settings
     *
     * @since 2.2.0
     * @return array
     */
    public function get_map_settings()
    {
        $settings = $this->load_settings();
        
        $defaults = [
            // Map Provider Configuration
            'hvnly_map_provider' => 'leaflet',
            'hvnly_map_api_key' => '',
            
            // Map Display Settings
            'hvnly_default_lat' => '51.514939',
            'hvnly_default_lng' => '-0.091839',
            'hvnly_map_zoom' => 12,
            'hvnly_map_style' => 'standard',
            
            // Marker Settings
            'hvnly_cluster_markers' => true,
            'hvnly_custom_marker' => false,
            'hvnly_marker_color' => '#6C60FE',
            
            // Map UI Settings
            'hvnly_show_fullscreen' => true,
            'hvnly_show_zoom_control' => true,
            'hvnly_show_scroll_wheel' => true,
            
            // Google Maps Specific
            'hvnly_google_map_type' => 'roadmap',
            'hvnly_google_map_id' => '',

            // OpenStreetMap Specific
            'hvnly_osm_tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'hvnly_osm_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        ];
        
        $map_settings = isset($settings['map']) ? $settings['map'] : [];
        
        return wp_parse_args($map_settings, $defaults);
    }

    /**
     * Get map provider
     *
     * @since 2.2.0
     * @return string
     */
    public function get_map_provider()
    {
        $map_settings = $this->get_map_settings();
        $provider = $map_settings['hvnly_map_provider'] ?? 'leaflet';
        $allowed    = array( 'leaflet', 'openstreetmap', 'google' );

        return in_array( $provider, $allowed, true ) ? $provider : 'leaflet';
    }

    /**
     * Merge and persist map settings (single source of truth: hvnly_plugin_settings[map]).
     *
     * @since 3.0.0
     * @param array $partial Partial map settings to merge.
     * @return array Updated map settings.
     */
    public function update_map_settings( array $partial ) {
        $allowed_providers = array( 'leaflet', 'openstreetmap', 'google' );
        $storage_key       = 'hvnly_plugin_settings';
        $all_settings      = get_option( $storage_key, array() );

        if ( ! is_array( $all_settings ) ) {
            $all_settings = array();
        }

        $map_settings = isset( $all_settings['map'] ) && is_array( $all_settings['map'] )
            ? $all_settings['map']
            : array();

        $map_settings = wp_parse_args( $map_settings, $this->get_map_settings() );

        if ( isset( $partial['hvnly_map_provider'] ) ) {
            $provider = sanitize_text_field( (string) $partial['hvnly_map_provider'] );
            if ( in_array( $provider, $allowed_providers, true ) ) {
                $map_settings['hvnly_map_provider'] = $provider;
            }
        }

        if ( array_key_exists( 'hvnly_map_api_key', $partial ) ) {
            $map_settings['hvnly_map_api_key'] = sanitize_text_field( (string) $partial['hvnly_map_api_key'] );
        }

        if ( array_key_exists( 'hvnly_google_map_id', $partial ) ) {
            $map_settings['hvnly_google_map_id'] = sanitize_text_field( (string) $partial['hvnly_google_map_id'] );
        }

        if ( isset( $partial['hvnly_default_lat'] ) ) {
            $map_settings['hvnly_default_lat'] = (string) floatval( $partial['hvnly_default_lat'] );
        }

        if ( isset( $partial['hvnly_default_lng'] ) ) {
            $map_settings['hvnly_default_lng'] = (string) floatval( $partial['hvnly_default_lng'] );
        }

        if ( isset( $partial['hvnly_map_zoom'] ) ) {
            $map_settings['hvnly_map_zoom'] = absint( $partial['hvnly_map_zoom'] );
        }

        $all_settings['map'] = $map_settings;
        update_option( $storage_key, $all_settings );

        do_action( 'hvnly_settings_updated', 'map', $map_settings );

        return $map_settings;
    }

    /**
     * Check if Google Maps should be used
     *
     * @since 2.2.0
     * @return bool
     */
    public function should_use_google_maps()
    {
        $map_settings = $this->get_map_settings();
        $provider = $map_settings['hvnly_map_provider'] ?? 'leaflet';
        $api_key = $map_settings['hvnly_map_api_key'] ?? '';
        
        return ($provider === 'google' && !empty($api_key));
    }

    /**
     * Get OpenStreetMap tile URL
     *
     * @return string
     */
    public function get_osm_tile_url() {
        $map_settings = $this->get_map_settings();
        return isset($map_settings['hvnly_osm_tile_url']) 
            ? $map_settings['hvnly_osm_tile_url'] 
            : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    }

    /**
     * Get OpenStreetMap attribution
     *
     * @return string
     */
    public function get_osm_attribution() {
        $map_settings = $this->get_map_settings();
        return isset($map_settings['hvnly_osm_attribution']) 
            ? $map_settings['hvnly_osm_attribution'] 
            : '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    }


    /**
     * Get Google Maps API key
     *
     * @since 2.2.0
     * @return string
     */
    public function get_google_maps_api_key()
    {
        $map_settings = $this->get_map_settings();
        
        if ($this->should_use_google_maps()) {
            return $map_settings['hvnly_map_api_key'] ?? '';
        }
        
        return '';
    }

    /**
     * Get default latitude
     *
     * @since 2.2.0
     * @return float
     */
    public function get_default_latitude()
    {
        $map_settings = $this->get_map_settings();
        return (float) ($map_settings['hvnly_default_lat'] ?? 40.7128);
    }

    /**
     * Get default longitude
     *
     * @since 2.2.0
     * @return float
     */
    public function get_default_longitude()
    {
        $map_settings = $this->get_map_settings();
        return (float) ($map_settings['hvnly_default_lng'] ?? -74.0060);
    }

    /**
     * Get map zoom level
     *
     * @since 2.2.0
     * @return int
     */
    public function get_map_zoom()
    {
        $map_settings = $this->get_map_settings();
        return absint($map_settings['hvnly_map_zoom'] ?? 12);
    }

    /**
     * Check if marker clustering is enabled
     *
     * @since 2.2.0
     * @return bool
     */
    public function is_marker_clustering_enabled()
    {
        $map_settings = $this->get_map_settings();
        return (bool) ($map_settings['hvnly_cluster_markers'] ?? true);
    }

    /**
     * Get map style
     *
     * @since 2.2.0
     * @return string
     */
    public function get_map_style()
    {
        $map_settings = $this->get_map_settings();
        return $map_settings['hvnly_map_style'] ?? 'standard';
    }

    /**
     * Get Google Maps map type
     *
     * @since 2.2.0
     * @return string
     */
    public function get_google_map_type()
    {
        $map_settings = $this->get_map_settings();
        return $map_settings['hvnly_google_map_type'] ?? 'roadmap';
    }

    /**
     * Check if fullscreen button should be shown
     *
     * @since 2.2.0
     * @return bool
     */
    public function is_fullscreen_enabled()
    {
        $map_settings = $this->get_map_settings();
        return (bool) ($map_settings['hvnly_show_fullscreen'] ?? true);
    }

    /**
     * Check if zoom controls should be shown
     *
     * @since 2.2.0
     * @return bool
     */
    public function is_zoom_control_enabled()
    {
        $map_settings = $this->get_map_settings();
        return (bool) ($map_settings['hvnly_show_zoom_control'] ?? true);
    }

    /**
     * Check if scroll wheel zoom is enabled
     *
     * @since 2.2.0
     * @return bool
     */
    public function is_scroll_wheel_enabled()
    {
        $map_settings = $this->get_map_settings();
        return (bool) ($map_settings['hvnly_show_scroll_wheel'] ?? true);
    }

    /**
     * Get marker color for custom markers
     *
     * @since 2.2.0
     * @return string
     */
    public function get_marker_color()
    {
        $map_settings = $this->get_map_settings();
        $brand_default = $this->get_design_setting('brandColor', '#6C60FE');
        $color = $map_settings['hvnly_marker_color'] ?? $brand_default;

        if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
            $color = $brand_default;
        }

        return $color;
    }

    /**
     * Check if custom marker is enabled
     *
     * @since 2.2.0
     * @return bool
     */
    public function is_custom_marker_enabled()
    {
        $map_settings = $this->get_map_settings();
        return (bool) ($map_settings['hvnly_custom_marker'] ?? false);
    }





    /**
     * Get secondary color
     *
     * @return string
     */
    public function get_secondary_color()
    {
        return $this->get_design_setting('secondaryColor', '#764ba2');
    }
    
    /**
     * Get link color
     *
     * @return string
     */
    public function get_link_color()
    {
        return $this->get_design_setting('linkColor', '#1E1E2F');
    }
    
    /**
     * Get title color
     *
     * @return string
     */
    public function get_title_color()
    {
        return $this->get_design_setting('titleColor', '#1E1E2F');
    }
    
    /**
     * Get property details settings
     *
     * @return array
     */
    public function get_property_details_settings()
    {
        $settings = $this->load_settings();
        
        $defaults = [
            'hvnly_EnableBreadcrumbs' => true,
            'hvnly_EnableBackToTop' => true,
            'hvnly_EnablePrintButton' => true,
            'hvnly_EnableSaveProperty' => true,
            'hvnly_EnableShareButton' => true,
        ];
        
        $property_details = isset($settings['property-details']) ? $settings['property-details'] : [];
        
        return wp_parse_args($property_details, $defaults);
    }
    
    /**
     * Check if breadcrumbs are enabled
     *
     * @return bool
     * @since 2.0.3
     */
    public function is_breadcrumb_enabled()
    {
        $property_details = $this->get_property_details_settings();
        return isset($property_details['hvnly_EnableBreadcrumbs']) 
            ? (bool) $property_details['hvnly_EnableBreadcrumbs'] 
            : true;
    }

    /**
     * Check if back to top button is enabled
     *
     * @return bool
     * @since 2.0.3
     */
    public function is_back_to_top_enabled()
    {
        $property_details = $this->get_property_details_settings();
        return isset($property_details['hvnly_EnableBackToTop']) 
            ? (bool) $property_details['hvnly_EnableBackToTop'] 
            : true;
    }

    /**
     * Check if print button is enabled
     *
     * @return bool
     * @since 2.0.3
     */
    public function is_print_button_enabled()
    {
        $property_details = $this->get_property_details_settings();
        return isset($property_details['hvnly_EnablePrintButton']) 
            ? (bool) $property_details['hvnly_EnablePrintButton'] 
            : true;
    }
    
    /**
     * Check if save property button is enabled
     *
     * @return bool
     * @since 2.0.3
     */
    public function is_save_property_enabled()
    {
        $property_details = $this->get_property_details_settings();
        return isset($property_details['hvnly_EnableSaveProperty']) 
            ? (bool) $property_details['hvnly_EnableSaveProperty'] 
            : true;
    }

    /**
     * Check if share button is enabled
     *
     * @return bool
     * @since 2.0.3
     */
    public function is_share_button_enabled()
    {
        $property_details = $this->get_property_details_settings();
        return isset($property_details['hvnly_EnableShareButton']) 
            ? (bool) $property_details['hvnly_EnableShareButton'] 
            : true;
    }
    
    /**
     * Clear settings cache
     */
    public function clear_cache()
    {
        $this->cached_settings = null;
        $this->share_platforms = null;
    }
    
    // ========================================================
    // SOCIAL SHARE FUNCTIONS
    // ========================================================
    
    /**
     * Get enabled social share platforms from settings
     *
     * @return array Array of enabled platform keys
     * @since 2.0.3
     */
    public function get_enabled_share_platforms()
    {
        $general_settings = $this->get_general_settings();
        
        // Check if social share is enabled globally
        $is_enabled = isset($general_settings['hvnly_EnabledSocialShare']) 
            ? ! empty($general_settings['hvnly_EnabledSocialShare']) 
            : false;
        
        if ( ! $is_enabled ) {
            return array();
        }
        
        // Get enabled platforms from settings
        $platforms = isset($general_settings['hvnly_enableSocialShare']) 
            ? (array) $general_settings['hvnly_enableSocialShare'] 
            : array();
        
        // Default platforms if none set
        if ( empty( $platforms ) ) {
            $platforms = array( 'facebook', 'twitter', 'linkedin', 'pinterest', 'email', 'whatsapp' );
        }
        
        return apply_filters( 'hvnly_enabled_share_platforms', $platforms );
    }
    
    /**
     * Get share button configurations for all platforms
     *
     * @return array Array of platform configurations with URLs, icons, colors
     * @since 2.0.3
     */
    public function get_share_platform_configs()
    {
        if ( null !== $this->share_platforms ) {
            return $this->share_platforms;
        }
        
        $configs = array(
            'facebook'  => array(
                'name'      => 'Facebook',
                'icon'      => 'fab fa-facebook-f',
                'url'       => 'https://www.facebook.com/sharer/sharer.php?u={url}',
                'color'     => '#1877f2',
                'order'     => 1,
            ),
            'twitter'   => array(
                'name'      => 'Twitter',
                'icon'      => 'fab fa-twitter',
                'url'       => 'https://twitter.com/intent/tweet?url={url}&text={title}',
                'color'     => '#1da1f2',
                'order'     => 2,
            ),
            'linkedin'  => array(
                'name'      => 'LinkedIn',
                'icon'      => 'fab fa-linkedin-in',
                'url'       => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
                'color'     => '#0a66c2',
                'order'     => 3,
            ),
            'pinterest' => array(
                'name'      => 'Pinterest',
                'icon'      => 'fab fa-pinterest-p',
                'url'       => 'https://pinterest.com/pin/create/button/?url={url}&media={image}&description={title}',
                'color'     => '#bd081c',
                'order'     => 4,
            ),
            'reddit'    => array(
                'name'      => 'Reddit',
                'icon'      => 'fab fa-reddit-alien',
                'url'       => 'https://reddit.com/submit?url={url}&title={title}',
                'color'     => '#ff4500',
                'order'     => 5,
            ),
            'tumblr'    => array(
                'name'      => 'Tumblr',
                'icon'      => 'fab fa-tumblr',
                'url'       => 'https://www.tumblr.com/widgets/share/tool?canonicalUrl={url}&title={title}',
                'color'     => '#35465c',
                'order'     => 6,
            ),
            'viber'     => array(
                'name'      => 'Viber',
                'icon'      => 'fab fa-viber',
                'url'       => 'viber://forward?text={title} {url}',
                'color'     => '#7360f2',
                'order'     => 7,
            ),
            'telegram'  => array(
                'name'      => 'Telegram',
                'icon'      => 'fab fa-telegram-plane',
                'url'       => 'https://t.me/share/url?url={url}&text={title}',
                'color'     => '#26a5e4',
                'order'     => 8,
            ),
            'whatsapp'  => array(
                'name'      => 'WhatsApp',
                'icon'      => 'fab fa-whatsapp',
                'url'       => 'https://api.whatsapp.com/send?text={title}%20{url}',
                'color'     => '#25d366',
                'order'     => 9,
            ),
            'line'      => array(
                'name'      => 'Line',
                'icon'      => 'fab fa-line',
                'url'       => 'https://line.me/R/msg/text/?{title}%20{url}',
                'color'     => '#00b900',
                'order'     => 10,
            ),
            'email'     => array(
                'name'      => 'Email',
                'icon'      => 'fas fa-envelope',
                'url'       => 'mailto:?subject={title}&body=Check%20out%20this%20property%3A%20{url}',
                'color'     => '#666666',
                'order'     => 11,
            ),
            'copy_link' => array(
                'name'      => 'Copy Link',
                'icon'      => 'fas fa-link',
                'url'       => '#',
                'color'     => '#6C60FE',
                'order'     => 12,
                'is_copy'   => true,
            ),
        );
        
        $this->share_platforms = apply_filters( 'hvnly_share_platform_configs', $configs );
        
        return $this->share_platforms;
    }
    
    /**
     * Get share URL for a specific platform
     *
     * @param string $platform Platform key (facebook, twitter, etc.)
     * @param string $url      URL to share
     * @param string $title    Title to share
     * @param string $image    Image URL to share (for pinterest)
     * @return string Share URL
     * @since 2.0.3
     */
    public function get_share_url( $platform, $url, $title = '', $image = '' )
    {
        $configs = $this->get_share_platform_configs();
        
        if ( ! isset( $configs[ $platform ] ) ) {
            return '#';
        }
        
        $share_url = $configs[ $platform ]['url'];
        
        // Replace placeholders
        $share_url = str_replace( '{url}', rawurlencode( $url ), $share_url );
        $share_url = str_replace( '{title}', rawurlencode( $title ), $share_url );
        $share_url = str_replace( '{image}', rawurlencode( $image ), $share_url );
        
        return apply_filters( 'hvnly_share_url', $share_url, $platform, $url, $title, $image );
    }
    
    /**
     * Check if social sharing is enabled
     *
     * @return bool
     * @since 2.0.3
     */
    public function is_social_sharing_enabled()
    {
        $general_settings = $this->get_general_settings();
        return ! empty( $general_settings['hvnly_EnabledSocialShare'] );
    }
    
    /**
     * Get share data for a property
     *
     * @param int    $property_id Property ID
     * @param string $title       Custom title (optional)
     * @param string $image       Custom image URL (optional)
     * @return array Share data with URL, title, image, and platforms
     * @since 2.0.3
     */
    public function get_share_data( $property_id = 0, $title = '', $image = '' )
    {
        if ( empty( $property_id ) ) {
            $property_id = get_the_ID();
        }
        
        $share_url   = get_permalink( $property_id );
        $share_title = ! empty( $title ) ? $title : get_the_title( $property_id );
        $share_image = ! empty( $image ) ? $image : get_the_post_thumbnail_url( $property_id, 'large' );
        
        $enabled_platforms = $this->get_enabled_share_platforms();
        $all_configs       = $this->get_share_platform_configs();
        
        $platforms = array();
        foreach ( $enabled_platforms as $platform_key ) {
            if ( isset( $all_configs[ $platform_key ] ) ) {
                $platforms[ $platform_key ] = $all_configs[ $platform_key ];
                $platforms[ $platform_key ]['share_url'] = $this->get_share_url( 
                    $platform_key, 
                    $share_url, 
                    $share_title, 
                    $share_image 
                );
            }
        }
        
        // Sort platforms by order
        uasort( $platforms, function( $a, $b ) {
            $order_a = isset( $a['order'] ) ? $a['order'] : 999;
            $order_b = isset( $b['order'] ) ? $b['order'] : 999;
            return $order_a - $order_b;
        } );
        
        return array(
            'url'         => $share_url,
            'title'       => $share_title,
            'image'       => $share_image,
            'property_id' => $property_id,
            'platforms'   => $platforms,
            'is_enabled'  => $this->is_social_sharing_enabled(),
        );
    }
}