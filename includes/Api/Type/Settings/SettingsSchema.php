<?php
/**
 * Settings Schema Validation System
 *
 * @package HvnlyNab\Api\Type\Settings
 * @since 2.0.3
 */

namespace HvnlyNab\Api\Type\Settings;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Settings Schema Definition and Validation Class
 *
 * @since 2.0.3
 */
class SettingsSchema {

    /**
     * Schema cache
     *
     * @var array|null
     */
    private static $schema = null;

    /**
     * Valid groups list
     *
     * @var array
     */
    private static $valid_groups = array( 'general', 'design', 'search', 'properties', 'shortcodes', 'property-details', 'map', 'performance', 'contact-agent', 'email', 'permalinks' );

    /**
     * Map provider options
     *
     * @var array
     */
    private static $map_providers = array( 'leaflet', 'openstreetmap', 'google' );

    /**
     * Map style options
     *
     * @var array
     */
    private static $map_styles = array( 'standard', 'satellite', 'hybrid', 'terrain' );

    /**
     * Google map type options
     *
     * @var array
     */
    private static $google_map_types = array( 'roadmap', 'satellite', 'hybrid', 'terrain' );

    /**
     * Zoom level options (min, max)
     *
     * @var array
     */
    private static $zoom_range = array(
		'min' => 1,
		'max' => 20,
	);

    /**
     * Archive container width preset options.
     *
     * @var array<int, string>
     */
    private static $archive_container_width_presets = array(
        'default',
        '1140',
        '1200',
        '1240',
        '1320',
        '1400',
        'full',
        'custom',
    );

    /**
     * Allowed search field types.
     *
     * @var array<int, string>
     */
    private static $search_field_types = array(
        'text',
        'taxonomy',
        'number',
        'range',
        'dropdown',
        'group',
        'checkbox',
    );

    /**
     * Allowed top-level keys on a search field definition.
     *
     * @var array<int, string>
     */
    private static $search_field_top_keys = array(
        'id',
        'type',
        'enabled',
        'title',
        'label',
        'order',
        'is_default',
        'is_locked',
        'config',
    );

    /**
     * Allowed keys inside search field config objects.
     *
     * @var array<int, string>
     */
    private static $search_field_config_keys = array(
        'placeholder',
        'taxonomy',
        'useTaxonomy',
        'isAjax',
        'options',
        'min',
        'max',
        'usePresetValues',
        'minPlaceholder',
        'maxPlaceholder',
        'minOptions',
        'maxOptions',
        'selectAllOption',
        'isLocked',
        'cannotDelete',
        'cannotEdit',
        'subFields',
    );

    /**
     * Allowed sub-field types inside group field config.
     *
     * @var array<int, string>
     */
    private static $search_subfield_types = array(
        'text',
        'number',
        'dropdown',
    );

    /**
     * Allowed keys on group sub-field definitions.
     *
     * @var array<int, string>
     */
    private static $search_subfield_keys = array(
        'id',
        'label',
        'type',
        'min',
        'max',
        'options',
    );

    /**
     * Initialize and return the full schema
     *
     * @return array
     */
    private static function init_schema() {
        if (null !== self::$schema) {
            return self::$schema;
        }

        self::$schema = array(

            // ==============================================
            // MAP GROUP - NEW
            // ==============================================
            'map' => array(
                // Map Provider Configuration
                'hvnly_map_provider' => array(
                    'type' => 'string',
                    'default' => 'leaflet',
                    'sanitize' => 'sanitize_text_field',
                    'validate_callback' => 'validate_map_provider',
                ),
                'hvnly_map_api_key' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ),

                // Map Display Settings
                'hvnly_default_lat' => array(
                    'type' => 'string',
                    'default' => '51.514939',
                    'sanitize' => 'sanitize_text_field',
                    'validate_callback' => 'validate_coordinate',
                ),
                'hvnly_default_lng' => array(
                    'type' => 'string',
                    'default' => '-0.091839',
                    'sanitize' => 'sanitize_text_field',
                    'validate_callback' => 'validate_coordinate',
                ),
                'hvnly_map_zoom' => array(
                    'type' => 'integer',
                    'default' => 12,
                    'sanitize' => 'absint',
                    'validate_callback' => 'validate_zoom_level',
                ),
                'hvnly_map_style' => array(
                    'type' => 'string',
                    'default' => 'standard',
                    'sanitize' => 'sanitize_text_field',
                    'validate_callback' => 'validate_map_style',
                ),

                // Marker Settings
                'hvnly_cluster_markers' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_custom_marker' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_marker_color' => array(
                    'type' => 'string',
                    'default' => '#6C60FE',
                    'sanitize' => 'sanitize_hex_color',
                ),

                // Map UI Settings
                'hvnly_show_fullscreen' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_show_zoom_control' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_show_scroll_wheel' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),

                // Google Maps Specific
                'hvnly_google_map_type' => array(
                    'type' => 'string',
                    'default' => 'roadmap',
                    'sanitize' => 'sanitize_text_field',
                    'validate_callback' => 'validate_google_map_type',
                ),
                // Google Maps Specific
                'hvnly_google_map_type' => array(
                    'type' => 'string',
                    'default' => 'roadmap',
                    'sanitize' => 'sanitize_text_field',
                    'validate_callback' => 'validate_google_map_type',
                ),

                // OpenStreetMap Specific
                'hvnly_osm_tile_url' => array(
                    'type' => 'string',
                    'default' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_osm_attribution' => array(
                    'type' => 'string',
                    'default' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    'sanitize' => 'wp_kses_post',
                ),

            ),

            // ==============================================
            // PERFORMANCE GROUP
            // ==============================================
            'performance' => array(
                'hvnly_cache_enabled' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
            ),

            // ==============================================
            // CONTACT AGENT GROUP
            // ==============================================
            'contact-agent' => array(
                'hvnly_contact_agent_enabled' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_contact_agent_notify_agent' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_contact_agent_notify_admin' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_contact_agent_notify_sender' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_contact_agent_admin_email' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_email',
                ),
                'hvnly_contact_agent_sender_subject' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_contact_agent_sender_intro' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_textarea_field',
                ),
                'hvnly_contact_agent_success_message' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_textarea_field',
                ),
                'hvnly_contact_agent_honeypot_enabled' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_contact_agent_rate_limit_max' => array(
                    'type' => 'integer',
                    'default' => 5,
                    'sanitize' => 'absint',
                ),
                'hvnly_contact_agent_rate_limit_window' => array(
                    'type' => 'integer',
                    'default' => HOUR_IN_SECONDS,
                    'sanitize' => 'absint',
                ),
                'hvnly_workspace_registration_mode' => array(
                    'type' => 'string',
                    'default' => 'open',
                    'sanitize' => 'sanitize_key',
                ),
                'hvnly_workspace_default_role' => array(
                    'type' => 'string',
                    'default' => 'hvnly_agent',
                    'sanitize' => 'sanitize_key',
                ),
                'hvnly_workspace_enabled' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                // Read-only guest tour of the Workspace. Off by default —
                // exposing a preview is an administrator's decision.
                'hvnly_workspace_guest_login' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_page_slug' => array(
                    'type' => 'string',
                    'default' => 'agent-dashboard',
                    'sanitize' => 'sanitize_title',
                ),
                'hvnly_workspace_login_redirect' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_workspace_logout_redirect' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_workspace_perm_create_property' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_perm_edit_own_property' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_perm_delete_own_property' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_perm_submit_property' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_perm_publish_property' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_perm_upload_media' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_perm_edit_profile' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_perm_view_dashboard' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_perm_receive_inquiries' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_perm_reply_inquiries' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_registration' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_approval' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_rejection' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_suspended' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_activated' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_property_submitted' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_property_approved' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_property_rejected' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_new_inquiry' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_password_reset' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_email_profile_updated' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_developer_mode' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_rest_debug' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_debug' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_workspace_laravel_sync_enabled' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
            ),

            // ==============================================
            // EMAIL GROUP
            // ==============================================
            'email' => array(
                'hvnly_email_from_name' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_email_from_address' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_email',
                ),
                'hvnly_email_import_success_enabled' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_email_import_wizard_notify_default' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_email_import_success_subject' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_email_import_success_intro' => array(
                    'type' => 'string',
                    'default' => '',
                    'sanitize' => 'sanitize_textarea_field',
                ),
            ),

            // ==============================================
            // GENERAL GROUP - Currency, Misc
            // ==============================================
            'general' => array(
                // Currency Settings
                'hvnly_EnabledCurrencyFormat' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_thousandSeparator' => array(
                    'type' => 'string',
                    'default' => ',',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_decimalSeparator' => array(
                    'type' => 'string',
                    'default' => '.',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_numberOfDecimals' => array(
                    'type' => 'string',
                    'default' => '0',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_thousandText' => array(
                    'type' => 'string',
                    'default' => 'K',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_millionText' => array(
                    'type' => 'string',
                    'default' => 'M',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_billionText' => array(
                    'type' => 'string',
                    'default' => 'B',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_currencyType' => array(
                    'type' => 'string',
                    'default' => 'USD',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_currencyPositionType' => array(
                    'type' => 'string',
                    'default' => 'LEFT',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_priceOnCallText' => array(
                    'type' => 'string',
                    'default' => 'priceOnCallNone',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_priceFormat' => array(
                    'type' => 'string',
                    'default' => 'comma',
                    'sanitize' => 'sanitize_text_field',
                ),

                // Misc Settings
                'hvnly_EnabledGutenbergEditor' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_defaultCity' => array(
                    'type' => 'string',
                    'default' => 'Birmingham',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_defaultState' => array(
                    'type' => 'string',
                    'default' => 'Scotland',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_countryType' => array(
                    'type' => 'string',
                    'default' => 'GB',
                    'sanitize' => 'sanitize_text_field',
                ),
            ),

            // ==============================================
            // DESIGN GROUP - Colors
            // ==============================================
            'design' => array(
                'hvnly_brandColor' => array(
                    'type' => 'string',
                    'default' => '#6C60FE',
                    'sanitize' => 'sanitize_hex_color',
                ),
                'hvnly_secondaryColor' => array(
                    'type' => 'string',
                    'default' => '#764ba2',
                    'sanitize' => 'sanitize_hex_color',
                ),
                'hvnly_linkColor' => array(
                    'type' => 'string',
                    'default' => '#1E1E2F',
                    'sanitize' => 'sanitize_hex_color',
                ),
                'hvnly_linkHoverColor' => array(
                    'type' => 'string',
                    'default' => '#764ba2',
                    'sanitize' => 'sanitize_hex_color',
                ),
                'hvnly_titleColor' => array(
                    'type' => 'string',
                    'default' => '#1E1E2F',
                    'sanitize' => 'sanitize_hex_color',
                ),
                'hvnly_primaryTextColor' => array(
                    'type' => 'string',
                    'default' => '#1E1E2F',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_secondaryTextColor' => array(
                    'type' => 'string',
                    'default' => '#555555',
                    'sanitize' => 'sanitize_text_field',
                ),
            ),

            // ==============================================
            // SEARCH GROUP - Search Settings
            // ==============================================
            'search' => array(
                // Search Bar Settings
                'hvnly_searchBarTitle' => array(
                    'type' => 'string',
                    'default' => 'Search Properties',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_searchButtonText' => array(
                    'type' => 'string',
                    'default' => 'Search',
                    'sanitize' => 'sanitize_text_field',
                ),

                'hvnly_enableAjaxLoadMore' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_AjaxLoadMoreButtonText' => array(
                    'type' => 'string',
                    'default' => 'Load More',
                    'sanitize' => 'sanitize_text_field',
                ),
                // Hide Options
                'hvnly_hideTopSearchTitle' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_hideTopSearchBar' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_hideLeftSearchBar' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),

                // Layout Settings
                'hvnly_numberOfColumns' => array(
                    'type' => 'string',
                    'default' => '2',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_searchResultPerpage' => array(
                    'type' => 'string',
                    'default' => '6',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_sidebarLayoutType' => array(
                    'type' => 'string',
                    'default' => 'LEFT',
                    'sanitize' => 'sanitize_text_field',
                ),

                // Property View Settings
                'hvnly_propertyViewType' => array(
                    'type' => 'string',
                    'default' => 'GRID',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertyOrderByType' => array(
                    'type' => 'string',
                    'default' => 'TITLE',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertySortByType' => array(
                    'type' => 'string',
                    'default' => 'date',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertyDepartmentOrderByType' => array(
                    'type' => 'string',
                    'default' => 'RENT',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertyThumbnailSize' => array(
                    'type' => 'string',
                    'default' => 'thumbnail',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertiesPerPage' => array(
                    'type' => 'integer',
                    'default' => 6,
                    'sanitize' => 'absint',
                ),
                // Search Fields Configuration
                'hvnly_search_fields' => array(
                    'type' => 'array',
                    'default' => array(),
                    'sanitize' => array( __CLASS__, 'sanitize_search_fields_array' ),
                ),
                'hvnly_main_search_fields' => array(
                    'type' => 'array',
                    'default' => array(),
                    'sanitize' => array( __CLASS__, 'sanitize_search_fields_array' ),
                ),
                'hvnly_top_search_fields' => array(
                    'type' => 'array',
                    'default' => array(),
                    'sanitize' => array( __CLASS__, 'sanitize_search_fields_array' ),
                ),
            ),

            // ==============================================
            // PROPERTIES GROUP - Container & Responsive
            // ==============================================
            'properties' => array(
                // Container Width Settings
                'hvnly_container_width_xs' => array(
                    'type' => 'string',
                    'default' => '100%',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_container_width_sm' => array(
                    'type' => 'string',
                    'default' => '540px',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_container_width_md' => array(
                    'type' => 'string',
                    'default' => '720px',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_container_width_lg' => array(
                    'type' => 'string',
                    'default' => '960px',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_container_width_xl' => array(
                    'type' => 'string',
                    'default' => '1220px',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_container_width_xxl' => array(
                    'type' => 'string',
                    'default' => '1220px',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_container_width_xxxl' => array(
                    'type' => 'string',
                    'default' => '1220px',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_container_width_4k' => array(
                    'type' => 'string',
                    'default' => '1220px',
                    'sanitize' => 'sanitize_text_field',
                ),

                'hvnly_archive_container_width' => array(
                    'type' => 'string',
                    'default' => 'default',
                    'validate_callback' => 'validate_archive_container_width',
                    'sanitize' => 'sanitize_archive_container_width',
                ),
                'hvnly_archive_container_width_custom' => array(
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize' => 'sanitize_archive_container_width_custom',
                ),

                // Responsive View Settings
                'hvnly_propertyColumnsGapGrid' => array(
                    'type' => 'string',
                    'default' => '10',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertyTabletView' => array(
                    'type' => 'string',
                    'default' => '2',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertySmallTabletView' => array(
                    'type' => 'string',
                    'default' => '2',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertyMobileTabletView' => array(
                    'type' => 'string',
                    'default' => '1',
                    'sanitize' => 'sanitize_text_field',
                ),
            ),

            // ==============================================
            // PROPERTY DETAILS GROUP - Single Property Page
            // ==============================================
            'property-details' => array(
                'hvnly_EnableBreadcrumbs' => array(
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_EnableBackToTop' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_EnablePrintButton' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
                'hvnly_EnableSaveProperty' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'rest_sanitize_boolean',
                ),
            ),

            // ==============================================
            // PERMALINKS GROUP
            // ==============================================
            'permalinks' => array(
                'hvnly_property_archive_slug' => array(
                    'type' => 'string',
                    'default' => 'properties',
                    'sanitize' => 'sanitize_title',
                ),
                'hvnly_property_single_slug' => array(
                    'type' => 'string',
                    'default' => 'property',
                    'sanitize' => 'sanitize_title',
                ),
                'hvnly_agent_archive_slug' => array(
                    'type' => 'string',
                    'default' => 'agents',
                    'sanitize' => 'sanitize_title',
                ),
                'hvnly_agent_single_slug' => array(
                    'type' => 'string',
                    'default' => 'agent',
                    'sanitize' => 'sanitize_title',
                ),
                'hvnly_agency_archive_slug' => array(
                    'type' => 'string',
                    'default' => 'agencies',
                    'sanitize' => 'sanitize_title',
                ),
                'hvnly_agency_single_slug' => array(
                    'type' => 'string',
                    'default' => 'agency',
                    'sanitize' => 'sanitize_title',
                ),
            ),

            // ==============================================
            // SHORTCODES GROUP
            // ==============================================
            'shortcodes' => array(
                'hvnly_propertySearch' => array(
                    'type' => 'string',
                    'default' => '[hvnly_property_search]',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertyGrid' => array(
                    'type' => 'string',
                    'default' => '[hvnly_property_grid]',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertyLists' => array(
                    'type' => 'string',
                    'default' => '[hvnly_property_lists]',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertyAgents' => array(
                    'type' => 'string',
                    'default' => '[hvnly_property_agents]',
                    'sanitize' => 'sanitize_text_field',
                ),
                'hvnly_propertyAgencies' => array(
                    'type' => 'string',
                    'default' => '[hvnly_property_agencies]',
                    'sanitize' => 'sanitize_text_field',
                ),
            ),
        );

        return self::$schema;
    }



    /**
     * Get valid groups list
     *
     * @return array
     */
    public static function get_valid_groups() {
        return self::$valid_groups;
    }

    /**
     * Get valid map providers
     *
     * @return array
     */
    public static function get_map_providers() {
        return self::$map_providers;
    }

    /**
     * Get valid map styles
     *
     * @return array
     */
    public static function get_map_styles() {
        return self::$map_styles;
    }

    /**
     * Get valid Google map types
     *
     * @return array
     */
    public static function get_google_map_types() {
        return self::$google_map_types;
    }

    /**
     * Get zoom range (min, max)
     *
     * @return array
     */
    public static function get_zoom_range() {
        return self::$zoom_range;
    }

    /**
     * Validate map provider
     *
     * @param string $value Provider value
     * @return bool
     */
    public static function validate_map_provider( $value ) {
        return in_array($value, self::$map_providers, true);
    }

    /**
     * Validate map style
     *
     * @param string $value Map style value
     * @return bool
     */
    public static function validate_map_style( $value ) {
        return in_array($value, self::$map_styles, true);
    }

    /**
     * Validate Google map type
     *
     * @param string $value Google map type value
     * @return bool
     */
    public static function validate_google_map_type( $value ) {
        return in_array($value, self::$google_map_types, true);
    }

    /**
     * Validate coordinate (latitude or longitude)
     *
     * @param string $value Coordinate value
     * @return bool
     */
    public static function validate_coordinate( $value ) {
        if ( ! is_numeric($value)) {
            return false;
        }

        $coord = (float) $value;

        // Latitude range: -90 to 90, Longitude range: -180 to 180
        // We'll accept both and let the specific use case determine
        return $coord >= -180 && $coord <= 180;
    }

    /**
     * Validate zoom level
     *
     * @param int $value Zoom level
     * @return bool
     */
    public static function validate_zoom_level( $value ) {
        $value = absint($value);
        return $value >= self::$zoom_range['min'] && $value <= self::$zoom_range['max'];
    }

    /**
     * Validate archive container width preset.
     *
     * @param string $value Preset value.
     * @return bool
     */
    public static function validate_archive_container_width( $value ) {
        return in_array( (string) $value, self::$archive_container_width_presets, true);
    }

    /**
     * Sanitize archive container width preset.
     *
     * @param mixed $value Preset value.
     * @return string
     */
    public static function sanitize_archive_container_width( $value ) {
        $value = sanitize_key( (string) $value);
        return in_array($value, self::$archive_container_width_presets, true) ? $value : 'default';
    }

    /**
     * Sanitize custom archive container width (px).
     *
     * @param mixed $value Width in pixels.
     * @return int
     */
    public static function sanitize_archive_container_width_custom( $value ) {
        $width = absint($value);
        if ($width < 320) {
            return 0;
        }
        if ($width > 3000) {
            return 3000;
        }
        return $width;
    }

    /**
     * Sanitize an array of search field definitions.
     *
     * @param mixed $value Field definitions.
     * @return array<int, array<string, mixed>>
     */
    public static function sanitize_search_fields_array( $value ) {
        if ( ! is_array($value)) {
            return array();
        }

        $sanitized = array();
        foreach ($value as $field) {
            $clean_field = self::sanitize_search_field_item($field);
            if (null !== $clean_field) {
                $sanitized[] = $clean_field;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a single search field definition.
     *
     * @param mixed $field Field definition.
     * @return array<string, mixed>|null
     */
    private static function sanitize_search_field_item( $field ) {
        if ( ! is_array($field)) {
            return null;
        }

        $id = isset($field['id']) ? sanitize_key( (string) $field['id']) : '';
        if ('' === $id) {
            return null;
        }

        $type = isset($field['type']) ? sanitize_key( (string) $field['type']) : '';
        if ( ! in_array($type, self::$search_field_types, true)) {
            return null;
        }

        $clean = array(
            'id'      => $id,
            'type'    => $type,
            'enabled' => isset($field['enabled']) ? rest_sanitize_boolean($field['enabled']) : true,
        );

        foreach (self::$search_field_top_keys as $key) {
            if ( ! array_key_exists($key, $field) || in_array($key, array( 'id', 'type', 'enabled' ), true)) {
                continue;
            }

            if ('config' === $key) {
                if (is_array($field['config'])) {
                    $clean['config'] = self::sanitize_search_field_config($field['config'], $type);
                }
                continue;
            }

            if ('order' === $key) {
                $clean['order'] = absint($field['order']);
                continue;
            }

            if (in_array($key, array( 'is_default', 'is_locked' ), true)) {
                $clean[ $key ] = rest_sanitize_boolean($field[ $key ]);
                continue;
            }

            if (in_array($key, array( 'title', 'label' ), true)) {
                $clean[ $key ] = sanitize_text_field( (string) $field[ $key ]);
            }
        }

        return $clean;
    }

    /**
     * Sanitize search field config; unknown keys are dropped.
     *
     * @param array<string, mixed> $config   Raw config.
     * @param string               $type     Parent field type.
     * @return array<string, mixed>
     */
    private static function sanitize_search_field_config( array $config, $type ) {
        $clean = array();

        foreach ($config as $key => $value) {
            if ( ! in_array($key, self::$search_field_config_keys, true)) {
                continue;
            }

            switch ($key) {
                case 'placeholder':
                case 'minPlaceholder':
                case 'maxPlaceholder':
                    $clean[ $key ] = sanitize_text_field( (string) $value);
                    break;

                case 'taxonomy':
                    $taxonomy = sanitize_key( (string) $value);
                    if ('' !== $taxonomy && ( taxonomy_exists($taxonomy) || 0 === strpos($taxonomy, 'hvnly_prop_') )) {
                        $clean[ $key ] = $taxonomy;
                    }
                    break;

                case 'useTaxonomy':
                case 'isAjax':
                case 'usePresetValues':
                case 'selectAllOption':
                case 'isLocked':
                case 'cannotDelete':
                case 'cannotEdit':
                    $clean[ $key ] = rest_sanitize_boolean($value);
                    break;

                case 'min':
                case 'max':
                    $clean[ $key ] = absint($value);
                    break;

                case 'options':
                case 'minOptions':
                case 'maxOptions':
                    $clean[ $key ] = self::sanitize_search_field_options($value);
                    break;

                case 'subFields':
                    if ('group' === $type) {
                        $clean[ $key ] = self::sanitize_search_subfields($value);
                    }
                    break;
            }
        }

        return $clean;
    }

    /**
     * Sanitize preset/options list values.
     *
     * @param mixed $options Options list.
     * @return array<int, mixed>
     */
    private static function sanitize_search_field_options( $options ) {
        if ( ! is_array($options)) {
            return array();
        }

        $clean = array();
        foreach ($options as $option) {
            if (is_string($option) || is_numeric($option)) {
                $clean[] = sanitize_text_field( (string) $option);
                continue;
            }

            if ( ! is_array($option) || ! isset($option['value'])) {
                continue;
            }

            $item = array(
                'value' => sanitize_text_field( (string) $option['value']),
            );

            if (isset($option['label'])) {
                $item['label'] = sanitize_text_field( (string) $option['label']);
            }

            $clean[] = $item;
        }

        return $clean;
    }

    /**
     * Sanitize group sub-field definitions.
     *
     * @param mixed $subfields Sub-field list.
     * @return array<int, array<string, mixed>>
     */
    private static function sanitize_search_subfields( $subfields ) {
        if ( ! is_array($subfields)) {
            return array();
        }

        $clean = array();
        foreach ($subfields as $subfield) {
            if ( ! is_array($subfield)) {
                continue;
            }

            $id = isset($subfield['id']) ? sanitize_key( (string) $subfield['id']) : '';
            if ('' === $id) {
                continue;
            }

            $type = isset($subfield['type']) ? sanitize_key( (string) $subfield['type']) : '';
            if ( ! in_array($type, self::$search_subfield_types, true)) {
                continue;
            }

            $item = array(
                'id'   => $id,
                'type' => $type,
            );

            foreach (self::$search_subfield_keys as $key) {
                if ( ! array_key_exists($key, $subfield) || in_array($key, array( 'id', 'type' ), true)) {
                    continue;
                }

                if ('label' === $key) {
                    $item['label'] = sanitize_text_field( (string) $subfield[ $key ]);
                } elseif (in_array($key, array( 'min', 'max' ), true)) {
                    $item[ $key ] = absint($subfield[ $key ]);
                } elseif ('options' === $key) {
                    $item['options'] = self::sanitize_search_field_options($subfield[ $key ]);
                }
            }

            $clean[] = $item;
        }

        return $clean;
    }

    /**
     * Validate and sanitize data for a specific group
     *
     * @param string $group Group name
     * @param array $data Data to validate
     * @return array Validated data
     */
    public static function validate_group( $group, $data ) {
        $schema = self::init_schema();

        if ( ! isset($schema[ $group ])) {
            return $data;
        }

        $validated_data = array();
        $schema_group   = $schema[ $group ];

        foreach ($schema_group as $field => $field_schema) {
            $value                    = isset($data[ $field ]) ? $data[ $field ] : null;
            $validated_data[ $field ] = self::sanitize_field($value, $field_schema);
        }

        // Pass through any extra fields not in schema
        foreach ($data as $field => $value) {
            if ( ! isset($schema_group[ $field ])) {
                $validated_data[ $field ] = $value;
            }
        }

        return $validated_data;
    }

    /**
     * Sanitize a single field value
     *
     * @param mixed $value Field value
     * @param array $schema Field schema
     * @return mixed Sanitized value
     */
    public static function sanitize_field( $value, $schema ) {
        if (null === $value) {
            return isset($schema['default']) ? $schema['default'] : null;
        }

        if (isset($schema['type']) && ! self::validate_type($value, $schema['type'])) {
            return isset($schema['default']) ? $schema['default'] : null;
        }

        // Apply custom validation if exists
        if (isset($schema['validate_callback']) && method_exists(__CLASS__, $schema['validate_callback'])) {
            if ( ! call_user_func(array( __CLASS__, $schema['validate_callback'] ), $value)) {
                return isset($schema['default']) ? $schema['default'] : null;
            }
        }

        if (isset($schema['sanitize']) && is_callable($schema['sanitize'])) {
            $value = call_user_func($schema['sanitize'], $value);
        } else {
            $value = self::default_sanitize_by_type($value, $schema['type'] ?? 'string');
        }

        return $value;
    }

    /**
     * Validate value type
     *
     * @param mixed $value Value to validate
     * @param string $type Expected type
     * @return bool
     */
    public static function validate_type( $value, $type ) {
        switch ($type) {
            case 'boolean':
                return is_bool($value) || in_array($value, array( 0, 1, '0', '1', 'true', 'false', true, false ), true);
            case 'integer':
                return is_int($value) || ctype_digit( (string) $value);
            case 'string':
                return is_string($value) || is_numeric($value);
            case 'array':
                return is_array($value);
            default:
                return true;
        }
    }

    /**
     * Default sanitization by type
     *
     * @param mixed $value Value to sanitize
     * @param string $type Value type
     * @return mixed
     */
    private static function default_sanitize_by_type( $value, $type ) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return absint($value);
            case 'string':
                return sanitize_text_field($value);
            case 'array':
                return is_array($value) ? $value : array();
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Get default values for a specific group
     *
     * @param string $group Group name
     * @return array
     */
    public static function get_default_group( $group ) {
        $schema = self::init_schema();

        if ( ! isset($schema[ $group ])) {
            return array();
        }

        $defaults = array();
        foreach ($schema[ $group ] as $field => $field_schema) {
            $defaults[ $field ] = $field_schema['default'] ?? null;
        }

        if ('search' === $group && class_exists('HvnlyNab\Api\Type\Settings\DefaultSettingsData')) {
            $search_defaults = DefaultSettingsData::get_default_search_settings();
            $defaults        = array_merge($defaults, $search_defaults);
        }

        // Properties: DefaultSettingsData is the factory SOT (wins over schema field defaults).
        if ('properties' === $group && class_exists('HvnlyNab\Api\Type\Settings\DefaultSettingsData')) {
            $properties_defaults = DefaultSettingsData::get_default_properties_settings();
            $defaults            = array_merge($defaults, $properties_defaults);
        }

        return $defaults;
    }

    /**
     * Get all default values for all groups
     *
     * @return array
     */
    public static function get_all_defaults() {
        $schema       = self::init_schema();
        $all_defaults = array();

        foreach (array_keys($schema) as $group) {
            $all_defaults[ $group ] = self::get_default_group($group);
        }

        return $all_defaults;
    }

    /**
     * Merge existing settings with defaults
     *
     * @param array $existing_settings Existing settings
     * @return array Merged settings
     */
    public static function merge_with_defaults( $existing_settings ) {
        $defaults = self::get_all_defaults();

        foreach ($defaults as $group => $group_defaults) {
            if ( ! isset($existing_settings[ $group ]) || ! is_array($existing_settings[ $group ])) {
                $existing_settings[ $group ] = $group_defaults;
            } else {
                $existing_settings[ $group ] = array_merge($group_defaults, $existing_settings[ $group ]);
            }
        }

        return $existing_settings;
    }
}
