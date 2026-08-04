<?php
/**
 * Havenlytics Default Settings Data
 *
 * @package HvnlyNab\Api\Type\Settings
 * @since 2.0.3
 */

namespace HvnlyNab\Api\Type\Settings;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default Settings Data Class
 * 
 * Provides default values for all plugin settings.
 * 
 * @since 2.0.3
 */
class DefaultSettingsData
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static $instance = null;
    
    /**
     * Cached defaults
     *
     * @var array|null
     */
    private static $cached_defaults = null;

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
     * Get default map settings
     *
     * @since 2.2.0
     * @return array
     */
    public static function get_default_map_settings()
    {
        return [
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

            // OpenStreetMap Specific
            'hvnly_osm_tile_url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'hvnly_osm_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        ];
    }

    /**
     * Get all default settings
     *
     * @return array
     */
    public static function get_default_settings()
    {
        if (null !== self::$cached_defaults) {
            return self::$cached_defaults;
        }

        static $building = false;
        if ( ! $building && function_exists( 'hvnly_with_english_ui' ) ) {
            $building = true;
            try {
                self::$cached_defaults = hvnly_with_english_ui( static function () {
                    self::$cached_defaults = null;
                    return self::get_default_settings();
                } );
                return self::$cached_defaults;
            } finally {
                $building = false;
            }
        }
        
        $schema_defaults = SettingsSchema::get_all_defaults();
        
        $legacy_defaults = [
            'general' => self::get_default_general_settings(),
            'design' => self::get_default_design_settings(),
            'search' => self::get_default_search_settings(),
            'properties' => self::get_default_properties_settings(),
            'shortcodes' => self::get_default_shortcodes_settings(),
            'property-details' => self::get_default_property_details_settings(),
            'map' => self::get_default_map_settings(), 
            'performance' => self::get_default_performance_settings(),
            'contact-agent' => self::get_default_contact_agent_settings(),
            'email' => self::get_default_email_settings(),
        ];
        
        $merged_defaults = [];
        $all_groups = array_unique(array_merge(array_keys($schema_defaults), array_keys($legacy_defaults)));
        
        foreach ($all_groups as $group) {
            $merged_defaults[$group] = [];
            
            if (isset($legacy_defaults[$group])) {
                $merged_defaults[$group] = $legacy_defaults[$group];
            }
            
            if (isset($schema_defaults[$group])) {
                $schema_for_merge = $schema_defaults[$group];
                if ('search' === $group) {
                    foreach (array('hvnly_search_fields', 'hvnly_main_search_fields', 'hvnly_top_search_fields') as $search_fields_key) {
                        unset($schema_for_merge[$search_fields_key]);
                    }
                }
                $merged_defaults[$group] = array_merge($merged_defaults[$group], $schema_for_merge);
            }
        }
        
        self::$cached_defaults = $merged_defaults;
        
        return $merged_defaults;
    }

    /**
     * Get default performance settings
     *
     * @return array
     */
    public static function get_default_performance_settings()
    {
        return [
            'hvnly_cache_enabled' => false,
        ];
    }

    /**
     * Get default Contact Agent settings.
     *
     * @since 3.0.2
     * @return array
     */
    public static function get_default_contact_agent_settings()
    {
        return [
            'hvnly_contact_agent_enabled'           => true,
            'hvnly_contact_agent_notify_agent'      => true,
            'hvnly_contact_agent_notify_admin'      => true,
            'hvnly_contact_agent_notify_sender'     => true,
            'hvnly_contact_agent_admin_email'       => '',
            'hvnly_contact_agent_sender_subject'    => '',
            'hvnly_contact_agent_sender_intro'      => '',
            'hvnly_contact_agent_success_message'   => '',
            'hvnly_contact_agent_honeypot_enabled'  => true,
            'hvnly_contact_agent_rate_limit_max'      => 5,
            'hvnly_contact_agent_rate_limit_window' => HOUR_IN_SECONDS,
            'hvnly_workspace_enabled'               => false,
            'hvnly_workspace_guest_login'           => false,
            'hvnly_workspace_page_slug'             => 'agent-dashboard',
            'hvnly_workspace_login_redirect'        => '',
            'hvnly_workspace_logout_redirect'       => '',
            'hvnly_workspace_registration_mode'     => 'open',
            'hvnly_workspace_default_role'          => 'hvnly_agent',
            'hvnly_workspace_perm_create_property'  => true,
            'hvnly_workspace_perm_edit_own_property'=> true,
            'hvnly_workspace_perm_delete_own_property' => true,
            'hvnly_workspace_perm_submit_property'  => true,
            'hvnly_workspace_perm_publish_property' => false,
            'hvnly_workspace_perm_upload_media'     => true,
            'hvnly_workspace_perm_edit_profile'     => true,
            'hvnly_workspace_perm_view_dashboard'   => true,
            'hvnly_workspace_perm_receive_inquiries'=> true,
            'hvnly_workspace_perm_reply_inquiries'  => true,
            'hvnly_workspace_email_registration'    => true,
            'hvnly_workspace_email_approval'        => true,
            'hvnly_workspace_email_rejection'       => true,
            'hvnly_workspace_email_suspended'       => true,
            'hvnly_workspace_email_activated'       => true,
            'hvnly_workspace_email_property_submitted' => true,
            'hvnly_workspace_email_property_approved'  => true,
            'hvnly_workspace_email_property_rejected'  => true,
            'hvnly_workspace_email_new_inquiry'     => true,
            'hvnly_workspace_email_password_reset'  => true,
            'hvnly_workspace_email_profile_updated' => true,
            'hvnly_workspace_developer_mode'        => false,
            'hvnly_workspace_rest_debug'            => false,
            'hvnly_workspace_debug'                 => false,
            'hvnly_workspace_laravel_sync_enabled'  => false,
        ];
    }

    /**
     * Get default Email settings.
     *
     * @since 3.0.2
     * @return array
     */
    public static function get_default_email_settings()
    {
        return [
            'hvnly_email_from_name'                    => '',
            'hvnly_email_from_address'                 => '',
            'hvnly_email_import_success_enabled'       => true,
            'hvnly_email_import_wizard_notify_default' => true,
            // Legacy option keys stay unchanged for existing installations.
            'hvnly_email_import_success_subject'       => __('Property setup complete — {{site_name}}', 'havenlytics'),
            'hvnly_email_import_success_intro'         => __('Your property setup on {{site_name}} is complete. {{import_count}} listings are ready to review.', 'havenlytics'),
        ];
    }

    /**
     * Get default general settings
     *
     * @return array
     */
    public static function get_default_general_settings()
    {
        return [
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
            
            'hvnly_EnabledGutenbergEditor' => false,
            'hvnly_defaultCity' => 'Birmingham',
            'hvnly_defaultState' => 'Scotland',
            'hvnly_countryType' => 'GB',
            'hvnly_hideLeftSearchBar' => false,  
            'hvnly_hideTopSearchBar' => false,    
            'hvnly_hideTopSearchTitle' => false,  
        ];
    }

    /**
     * Get default design settings
     *
     * @return array
     */
    public static function get_default_design_settings()
    {
        return [
            'hvnly_brandColor' => '#6C60FE',
            'hvnly_secondaryColor' => '#764ba2',
            'hvnly_linkColor' => '#1E1E2F',
            'hvnly_linkHoverColor' => '#764ba2',
            'hvnly_titleColor' => '#1E1E2F',
            'hvnly_primaryTextColor' => '#1E1E2F',
            'hvnly_secondaryTextColor' => '#555555',
        ];
    }

    /**
     * Get default search settings
     *
     * @return array
     */
    public static function get_default_search_settings()
    {
        return [
            'hvnly_searchBarTitle' => __('Search Properties', 'havenlytics'),
            'hvnly_searchButtonText' => __('Search', 'havenlytics'),
            'hvnly_enableAjaxLoadMore' => true,
            'hvnly_AjaxLoadMoreButtonText' => __('Load More', 'havenlytics'),
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
            'hvnly_propertiesPerPage' => 6,
            'hvnly_main_search_fields' => self::get_default_main_search_fields(),
            'hvnly_top_search_fields' => self::get_default_top_search_fields(),
            'hvnly_search_fields' => self::get_default_search_fields(),
        ];
    }
    
    /**
     * Get default main search fields configuration (Keyword, Property Type, Location)
     *
     * @since 2.1.0
     * @return array
     */
    private static function get_default_main_search_fields()
    {
        return [
            [
                'id' => 'keyword_search',
                'title' => __('Keyword Search', 'havenlytics'),
                'type' => 'text',
                'enabled' => true,
                'order' => 1,
                'is_default' => true,
                'config' => [
                    'placeholder' => __('Search Address', 'havenlytics'),
                    'isAjax' => true,
                ]
            ],
            [
                'id' => 'property_type',
                'title' => __('Property Type', 'havenlytics'),
                'type' => 'taxonomy',
                'enabled' => true,
                'order' => 2,
                'is_default' => true,
                'config' => [
                    'taxonomy' => 'hvnly_prop_types',
                    'placeholder' => __('Property Type', 'havenlytics'),
                    'useTaxonomy' => true,
                ]
            ],
            [
                'id' => 'location',
                'title' => __('Location', 'havenlytics'),
                'type' => 'taxonomy',
                'enabled' => true,
                'order' => 3,
                'is_default' => true,
                'config' => [
                    'taxonomy' => 'hvnly_prop_locations',
                    'placeholder' => __('Search Location', 'havenlytics'),
                    'useTaxonomy' => true,
                ]
            ],
        ];
    }
    
    /**
     * Get default top search fields configuration
     *
     * @since 2.1.0
     * @return array
     */
    private static function get_default_top_search_fields()
    {
        return [
            [
                'id' => 'bedrooms',
                'title' => __('Bedrooms', 'havenlytics'),
                'type' => 'number',
                'enabled' => true,
                'order' => 1,
                'is_default' => true,
                'config' => [
                    'placeholder' => __('Any', 'havenlytics'),
                    'options' => ['1', '2', '3', '4', '5'],
                    'min' => 1,
                    'max' => 10,
                    'usePresetValues' => true
                ]
            ],
            [
                'id' => 'bathrooms',
                'title' => __('Bathrooms', 'havenlytics'),
                'type' => 'number',
                'enabled' => true,
                'order' => 2,
                'is_default' => true,
                'config' => [
                    'placeholder' => __('Any', 'havenlytics'),
                    'options' => ['1', '2', '3', '4', '5'],
                    'min' => 1,
                    'max' => 10,
                    'usePresetValues' => true
                ]
            ],
            [
                'id' => 'min_price',
                'title' => __('Min Price', 'havenlytics'),
                'type' => 'range',
                'enabled' => true,
                'order' => 3,
                'is_default' => true,
                'config' => [
                    'placeholder' => __('Any', 'havenlytics'),
                    'options' => ['50000', '75000', '100000', '200000', '300000', '400000', '500000'],
                    'usePresetValues' => true
                ]
            ],
            [
                'id' => 'max_price',
                'title' => __('Max Price', 'havenlytics'),
                'type' => 'range',
                'enabled' => true,
                'order' => 4,
                'is_default' => true,
                'config' => [
                    'placeholder' => __('Any', 'havenlytics'),
                    'options' => ['500000', '600000', '700000', '800000', '900000', '1000000'],
                    'usePresetValues' => true
                ]
            ]
        ];
    }

    /**
     * Get default search fields configuration - DYNAMIC VERSION with config support
     *
     * @since 2.1.0
     * @return array
     */
    private static function get_default_search_fields()
    {
        return [
            [
                'id' => 'price',
                'title' => __('Price', 'havenlytics'),
                'type' => 'range',
                'enabled' => true,
                'order' => 1,
                'is_default' => true,
                'is_locked' => false,
                'config' => [
                    'minPlaceholder' => __('Min Price', 'havenlytics'),
                    'maxPlaceholder' => __('Max Price', 'havenlytics'),
                    'minOptions' => ['100000', '200000', '300000', '400000', '500000', '600000', '700000', '800000', '900000', '1000000'],
                    'maxOptions' => ['200000', '300000', '400000', '500000', '600000', '700000', '800000', '900000', '1000000', '1500000'],
                    'usePresetValues' => true
                ]
            ],
            [
                'id' => 'status',
                'title' => __('Status', 'havenlytics'),
                'type' => 'dropdown',
                'enabled' => true,
                'order' => 2,
                'is_default' => true,
                'is_locked' => false,
                'config' => [
                    'useTaxonomy' => true,
                    'taxonomy' => 'hvnly_prop_status',
                    'placeholder' => __('Select Status', 'havenlytics')
                ]
            ],
            [
                'id' => 'bedrooms_bathrooms',
                'title' => __('Bedrooms & Bathrooms', 'havenlytics'),
                'type' => 'group',
                'enabled' => true,
                'order' => 3,
                'is_default' => true,
                'is_locked' => false,
                'config' => [
                    'subFields' => [
                        [
                            'id' => 'bedrooms', 
                            'label' => __('Bedrooms', 'havenlytics'), 
                            'type' => 'number',
                            'min' => 0,
                            'max' => 10,
                            'options' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10']
                        ],
                        [
                            'id' => 'bathrooms', 
                            'label' => __('Bathrooms', 'havenlytics'), 
                            'type' => 'number',
                            'min' => 0,
                            'max' => 8,
                            'options' => ['0', '1', '2', '3', '4', '5', '6', '7', '8']
                        ]
                    ]
                ]
            ],
            [
                'id' => 'reception_rooms',
                'title' => __('Reception Rooms', 'havenlytics'),
                'type' => 'number',
                'enabled' => true,
                'order' => 4,
                'is_default' => true,
                'is_locked' => false,
                'config' => [
                    'min' => 0,
                    'max' => 10,
                    'placeholder' => __('Any', 'havenlytics'),
                    'options' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10']
                ]
            ],
            [
                'id' => 'property_types',
                'title' => __('Property Types', 'havenlytics'),
                'type' => 'checkbox',
                'enabled' => true,
                'order' => 5,
                'is_default' => true,
                'is_locked' => false,
                'config' => [
                    'useTaxonomy' => true,
                    'taxonomy' => 'hvnly_prop_types',
                    'selectAllOption' => true
                ]
            ],
            [
                'id' => 'locations',
                'title' => __('Locations', 'havenlytics'),
                'type' => 'dropdown',
                'enabled' => true,
                'order' => 6,
                'is_default' => true,
                'is_locked' => false,
                'config' => [
                    'useTaxonomy' => true,
                    'taxonomy' => 'hvnly_prop_locations',
                    'placeholder' => __('Select Location', 'havenlytics')
                ]
            ],
            [
                'id' => 'features',
                'title' => __('Features', 'havenlytics'),
                'type' => 'checkbox',
                'enabled' => true,
                'order' => 7,
                'is_default' => true,
                'is_locked' => false,
                'config' => [
                    'useTaxonomy' => true,
                    'taxonomy' => 'hvnly_prop_features',
                    'selectAllOption' => true
                ]
            ],
            [
                'id' => 'tags',
                'title' => __('Tags', 'havenlytics'),
                'type' => 'dropdown',
                'enabled' => true,
                'order' => 8,
                'is_default' => true,
                'is_locked' => false,
                'config' => [
                    'useTaxonomy' => true,
                    'taxonomy' => 'hvnly_prop_tags',
                    'placeholder' => __('Select Tags', 'havenlytics')
                ]
            ],
            [
                'id' => 'badges',
                'title' => __('Badges', 'havenlytics'),
                'type' => 'checkbox',
                'enabled' => true,
                'order' => 9,
                'is_default' => true,
                'is_locked' => false,
                'config' => [
                    'useTaxonomy' => true,
                    'taxonomy' => 'hvnly_prop_badges',
                    'selectAllOption' => true
                ]
            ],
            [
                'id' => 'property_id',
                'title' => __('Property ID', 'havenlytics'),
                'type' => 'text',
                'enabled' => true,
                'order' => 10,
                'is_default' => true,
                'is_locked' => true,
                'config' => [
                    'isLocked' => true,
                    'cannotDelete' => true,
                    'cannotEdit' => true
                ]
            ]
        ];
    }

    /**
     * Get default properties settings
     *
     * @return array
     */
    public static function get_default_properties_settings()
    {
        return [
            'hvnly_container_width_xs' => '100%',
            'hvnly_container_width_sm' => '540px',
            'hvnly_container_width_md' => '720px',
            'hvnly_container_width_lg' => '960px',
            'hvnly_container_width_xl' => '1220px',
            'hvnly_container_width_xxl' => '1220px',
            'hvnly_container_width_xxxl' => '1220px',
            'hvnly_container_width_4k' => '1220px',
            'hvnly_archive_container_width' => 'default',
            'hvnly_archive_container_width_custom' => 0,
            'hvnly_propertyColumnsGapGrid' => '10',
            'hvnly_propertyTabletView' => '2',
            'hvnly_propertySmallTabletView' => '2',
            'hvnly_propertyMobileTabletView' => '1',
        ];
    }

    /**
     * Get default property details settings
     *
     * @return array
     */
    public static function get_default_property_details_settings()
    {
        return [
            'hvnly_EnableBreadcrumbs' => true,
            'hvnly_EnableBackToTop' => false,
            'hvnly_EnablePrintButton' => false,
            'hvnly_EnableSaveProperty' => false,
        ];
    }

    /**
     * Get default shortcodes settings
     *
     * @return array
     */
    public static function get_default_shortcodes_settings()
    {
        return [
            'hvnly_propertySearch' => '[hvnly_property_search]',
            'hvnly_propertyGrid' => '[hvnly_property_grid]',
            'hvnly_propertyLists' => '[hvnly_property_lists]',
            'hvnly_propertyAgents'   => '[hvnly_property_agents]',
            'hvnly_propertyAgencies' => '[hvnly_property_agencies]',
        ];
    }

    /**
     * Get a specific group's defaults
     *
     * @param string $group Group name
     * @return array
     */
    public static function get_group_defaults($group)
    {
        $all_defaults = self::get_default_settings();
        
        if (isset($all_defaults[$group])) {
            return $all_defaults[$group];
        }
        
        $method = 'get_default_' . str_replace('-', '_', $group) . '_settings';
        if (method_exists(__CLASS__, $method)) {
            return self::$method();
        }
        
        return [];
    }

    /**
     * Clear cached defaults
     */
    public static function clear_cache()
    {
        self::$cached_defaults = null;
    }
}