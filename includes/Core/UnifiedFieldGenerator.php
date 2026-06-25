<?php
/**
 * Unified Field Generator - SINGLE SOURCE OF TRUTH
 * 
 * Both import process AND builder reset use THIS SAME class.
 * This ensures 100% identical field naming across the entire plugin.
 *
 * @package     Havenlytics
 * @subpackage  Core
 * @since       2.2.2
 */

namespace HvnlyNab\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UnifiedFieldGenerator
 * 
 * SINGLE SOURCE OF TRUTH for generating all dynamic fields.
 * NOW INCLUDES ALL 7 SECTIONS:
 * 0. Basic Info (REQUIRED - NO GROUPS)
 * 1. Additional Information
 * 2. Address & Neighborhood
 * 3. Property Video (Group - 3 fields)
 * 4. Property Gallery (Group - 2 fields)
 * 5. Property Location (Group - 4 fields)
 * 6. Property Documents (Group - 3 fields: icon, label, url)
 */
class UnifiedFieldGenerator
{
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Master base IDs cache
     */
    private $master_ids = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get STANDARDIZED field names based on current builder configuration
     * This ensures import uses EXACTLY the same field names as the builder
     * 
     * @param string $group_type Group type (video, gallery, map, property_docs)
     * @param string $meta_key Meta key (title, url, images, etc.)
     * @return string The standardized field name to use
     */
    public function get_standardized_field_name($group_type, $meta_key) {
        // Get current builder configuration
        $builder_config = get_option('hvnly_property_builder.sections', []);
        
        // Find the group in the configuration
        foreach ($builder_config as $section) {
            $fields = $section['fields'] ?? [];
            foreach ($fields as $field) {
                if (($field['group_type'] ?? '') === $group_type && ($field['metaKey'] ?? '') === $meta_key) {
                    // Found the field - return its configured name
                    return $field['name'] ?? '';
                }
            }
        }
        
        // Fallback: generate using master IDs
        $master_ids = $this->get_or_create_master_base_ids();
        $base_id = $master_ids[$group_type] ?? $this->generate_unique_base_id($group_type);
        return $base_id . '_' . $meta_key;
    }

    /**
     * Get ALL standardized field names from builder configuration
     * Used during import to ensure field names match exactly
     * 
     * @return array Associative array of field names by group type and meta key
     */
    public function get_all_standardized_field_names() {
        $builder_config = get_option('hvnly_property_builder.sections', []);
        $standardized_names = [];
        
        foreach ($builder_config as $section) {
            $fields = $section['fields'] ?? [];
            foreach ($fields as $field) {
                $group_type = $field['group_type'] ?? '';
                $meta_key = $field['metaKey'] ?? '';
                $field_name = $field['name'] ?? '';
                
                if (!empty($group_type) && !empty($meta_key) && !empty($field_name)) {
                    if (!isset($standardized_names[$group_type])) {
                        $standardized_names[$group_type] = [];
                    }
                    $standardized_names[$group_type][$meta_key] = $field_name;
                }
            }
        }
        
        return $standardized_names;
    }


    /**
     * Get OR CREATE master base IDs
     * 
     * This ensures import and builder reset use the SAME IDs
     * 
     * @return array
     */
    public function get_or_create_master_base_ids()
    {
        if ($this->master_ids !== null) {
            return $this->master_ids;
        }
        
        $master_ids = get_option('hvnly_master_base_ids', []);
        
        if (empty($master_ids) || !isset($master_ids['video']) || !isset($master_ids['gallery']) ||
            !isset($master_ids['map']) || !isset($master_ids['property_docs'])) {
            $timestamp = time();
            $unique_suffix = substr(uniqid(), -8);

            $master_ids = [
                'video' => "video_{$timestamp}_{$unique_suffix}",
                'gallery' => "gallery_{$timestamp}_{$unique_suffix}",
                'map' => "map_{$timestamp}_{$unique_suffix}",
                'property_docs' => "property_docs_{$timestamp}_{$unique_suffix}",
                'faq' => "faq_{$timestamp}_{$unique_suffix}",
                'repeater' => "repeater_{$timestamp}_{$unique_suffix}",
                'agents' => "agents_{$timestamp}_{$unique_suffix}",
                'features' => "features_{$timestamp}_{$unique_suffix}",
                'generated_at' => $timestamp,
                'unique_suffix' => $unique_suffix
            ];

            update_option('hvnly_master_base_ids', $master_ids);
            update_option('hvnly_unified_config_version', HVNLYNAB_VERSION);
        } else {
            $needs_save = false;
            $timestamp = time();
            $unique_suffix = substr(uniqid(), -8);

            foreach (array('faq', 'repeater', 'agents', 'features') as $group_type) {
                if (empty($master_ids[$group_type])) {
                    $master_ids[$group_type] = "{$group_type}_{$timestamp}_{$unique_suffix}";
                    $needs_save = true;
                }
            }

            if ($needs_save) {
                update_option('hvnly_master_base_ids', $master_ids);
            }
        }
        
        $this->master_ids = $master_ids;
        return $this->master_ids;
    }
    
    /**
     * Get master base IDs (without creating if not exists)
     * 
     * @return array
     */
    public function get_master_base_ids()
    {
        if ($this->master_ids !== null) {
            return $this->master_ids;
        }
        
        $this->master_ids = get_option('hvnly_master_base_ids', []);
        return $this->master_ids;
    }
    
    /**
     * Generate a UNIQUE base ID for a group
     * Uses microtime + random + uniqid to ensure uniqueness across multiple groups in same request
     *
     * @param string $group_type Type of group (video, gallery, map, property_docs)
     * @param string $existing_base_id Optional existing base ID to preserve
     * @return string Unique base ID
     */
    public function generate_unique_base_id($group_type, $existing_base_id = '')
    {
        // If an existing base ID is provided and it's already unique, preserve it
        if (!empty($existing_base_id) && $this->is_unique_base_id($existing_base_id)) {
            return $existing_base_id;
        }
        
        // Generate a COMPLETELY UNIQUE base ID for THIS SPECIFIC group
        $microtime = microtime(true);
        $timestamp = (int)$microtime;
        $micro_suffix = substr(str_replace('.', '', (string)$microtime), -6);
        $random = wp_rand(10000, 99999);
        $unique_id = uniqid();
        
        switch ($group_type) {
            case 'video':
                return "video_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'gallery':
                return "gallery_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'map':
                return "map_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'property_docs':
                return "property_docs_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'faq':
                return "faq_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'repeater':
                return "repeater_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'agents':
                return "agents_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            case 'features':
                return "features_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
            default:
                return "group_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
        }
    }
    
    /**
     * Generate a UNIQUE group ID
     *
     * @param string $group_type Group type
     * @return string Unique group ID
     */
    private function generate_unique_group_id($group_type)
    {
        $timestamp = time();
        $short_id = substr(uniqid(), -8);
        $random = wp_rand(1000, 9999);
        return "grp_{$group_type}_{$timestamp}_{$short_id}_{$random}";
    }
    
    /**
     * Check if a base ID is already unique
     * 
     * @param string $base_id Base ID to check
     * @return bool
     */
    private function is_unique_base_id($base_id)
    {
        $patterns = [
            '/^video_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^gallery_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^map_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^property_docs_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^faq_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^repeater_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^agents_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^features_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $base_id)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the unified builder configuration - ALL 7 SECTIONS
     *
     * Uses the stored master base IDs so every reset produces the same
     * field names as the initial generation.  Section IDs are now stable
     * string constants so the metabox tabs survive resets without losing
     * their mapping to stored post-meta data.
     *
     * @return array
     */
    public function get_unified_configuration()
    {
        // Always use the stored master IDs — never generate fresh ones here.
        // get_or_create_master_base_ids() creates them on first call and then
        // returns the same values on every subsequent call.
        $master_ids = $this->get_or_create_master_base_ids();

        $video_base    = $master_ids['video'];
        $gallery_base  = $master_ids['gallery'];
        $map_base      = $master_ids['map'];
        $docs_base     = $master_ids['property_docs'];
        $faq_base      = $master_ids['faq'] ?? '';
        $repeater_base = $master_ids['repeater'] ?? '';
        $agents_base   = $master_ids['agents'] ?? '';
        $features_base = $master_ids['features'] ?? '';

        // Stable section IDs — never change across resets or re-installs.
        $basic_info_id           = SectionIdentity::SEC_PROPERTY_OVERVIEW;
        $additional_info_id      = SectionIdentity::SEC_PROPERTY_DETAILS;
        $address_neighborhood_id = SectionIdentity::SEC_ADDRESS_NEIGHBORHOOD;
        $video_id                = SectionIdentity::SEC_PROPERTY_VIDEO;
        $gallery_id              = SectionIdentity::SEC_PROPERTY_GALLERY;
        $location_id             = SectionIdentity::SEC_PROPERTY_LOCATION;
        $documents_id            = SectionIdentity::SEC_PROPERTY_DOCUMENTS;
        $faq_id                  = SectionIdentity::SEC_PROPERTY_FAQ;
        $repeater_id             = SectionIdentity::SEC_PROPERTY_REPEATER;
        $agents_id               = SectionIdentity::SEC_PROPERTY_AGENTS;
        $features_id             = SectionIdentity::SEC_PROPERTY_FEATURES;
        
        return [
            $basic_info_id => [
                'id' => $basic_info_id,
                'title' => 'Basic Info',
                'icon' => 'fas fa-home',
                'required' => true,
                'order' => 0,
                'collapsed' => false,
                'fields' => $this->get_basic_info_fields(),
            ],
            $additional_info_id => [
                'id' => $additional_info_id,
                'title' => 'Additional Information',
                'icon' => 'fas fa-info-circle',
                'required' => false,
                'order' => 1,
                'collapsed' => false,
                'fields' => $this->get_additional_info_fields(),
            ],
            $address_neighborhood_id => [
                'id' => $address_neighborhood_id,
                'title' => 'Address & Neighborhood',
                'icon' => 'fas fa-building',
                'required' => false,
                'order' => 2,
                'collapsed' => false,
                'fields' => $this->get_address_neighborhood_fields(),
            ],
            $video_id => [
                'id' => $video_id,
                'title' => 'Property Video',
                'icon' => 'fas fa-video',
                'required' => false,
                'order' => 3,
                'collapsed' => false,
                'fields' => $this->create_video_group_fields($video_base),
            ],
            $gallery_id => [
                'id' => $gallery_id,
                'title' => 'Property Gallery',
                'icon' => 'fas fa-images',
                'required' => false,
                'order' => 4,
                'collapsed' => false,
                'fields' => $this->create_gallery_group_fields($gallery_base),
            ],
            $location_id => [
                'id' => $location_id,
                'title' => 'Property Location',
                'icon' => 'fas fa-map-marker-alt',
                'required' => false,
                'order' => 5,
                'collapsed' => false,
                'fields' => $this->create_map_group_fields($map_base),
            ],
            $documents_id => [
                'id' => $documents_id,
                'title' => 'Property Documents',
                'icon' => 'fas fa-file-pdf',
                'required' => false,
                'order' => 6,
                'collapsed' => false,
                'fields' => $this->create_documents_group_fields($docs_base),
            ],
            $faq_id => [
                'id' => $faq_id,
                'title' => 'Frequently Asked Questions',
                'icon' => 'fas fa-question-circle',
                'required' => false,
                'order' => 7,
                'collapsed' => false,
                'fields' => $this->create_faq_group_fields($faq_base),
            ],
            $repeater_id => [
                'id' => $repeater_id,
                'title' => 'Property Highlights',
                'icon' => 'fas fa-list',
                'required' => false,
                'order' => 8,
                'collapsed' => false,
                'fields' => $this->create_repeater_group_fields($repeater_base),
            ],
            $features_id => [
                'id' => $features_id,
                'title' => 'Property Features',
                'icon' => 'fas fa-check-square',
                'required' => false,
                'order' => 9,
                'collapsed' => false,
                'fields' => $this->create_features_group_fields($features_base),
            ],
            $agents_id => [
                'id' => $agents_id,
                'title' => 'Listing Agents',
                'icon' => 'fas fa-user-tie',
                'required' => false,
                'order' => 10,
                'collapsed' => false,
                'fields' => $this->create_agents_group_fields($agents_base),
            ],
        ];
    }
    
    /**
     * Get basic info fields
     */
    public function get_basic_info_fields()
    {
        return [
            ['id' => '_hvnly_property_price', 'name' => '_hvnly_property_price', 'type' => 'price_label', 'label' => 'Property Price', 'placeholder' => 'Enter property price', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false],
            ['id' => '_hvnly_property_reception_rooms', 'name' => '_hvnly_property_reception_rooms', 'type' => 'number', 'label' => 'Property Reception Rooms', 'placeholder' => 'Enter reception rooms', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 1, 'hidden' => false],
            ['id' => '_hvnly_property_bedrooms', 'name' => '_hvnly_property_bedrooms', 'type' => 'number', 'label' => 'Property Bedrooms', 'placeholder' => 'Enter property bedrooms', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 2, 'hidden' => false],
            ['id' => '_hvnly_property_bathrooms', 'name' => '_hvnly_property_bathrooms', 'type' => 'number', 'label' => 'Property Bathrooms', 'placeholder' => 'Enter property bathrooms', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 3, 'hidden' => false],
            ['id' => '_hvnly_property_half_bathrooms', 'name' => '_hvnly_property_half_bathrooms', 'type' => 'number', 'label' => 'Property Half Baths', 'placeholder' => 'Enter property half baths', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 4, 'hidden' => false],
            ['id' => '_hvnly_property_kitchens', 'name' => '_hvnly_property_kitchens', 'type' => 'number', 'label' => 'Property Kitchen', 'placeholder' => 'Enter property kitchen', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 5, 'hidden' => false],
            ['id' => '_hvnly_property_total_rooms', 'name' => '_hvnly_property_total_rooms', 'type' => 'number', 'label' => 'Property Total Rooms', 'placeholder' => 'Enter property total rooms', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 6, 'hidden' => false],
            ['id' => '_hvnly_property_floors', 'name' => '_hvnly_property_floors', 'type' => 'number', 'label' => 'Property Floors', 'placeholder' => 'Enter property floors', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 7, 'hidden' => false],
            ['id' => '_hvnly_property_year_built', 'name' => '_hvnly_property_year_built', 'type' => 'number', 'label' => 'Property Year Built', 'placeholder' => 'Enter property year built', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 8, 'hidden' => false],
            ['id' => '_hvnly_property_mls_number', 'name' => '_hvnly_property_mls_number', 'type' => 'text', 'label' => 'Property MLS Number', 'placeholder' => 'Enter property MLS number', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 9, 'hidden' => false],
            ['id' => '_hvnly_property_garage_sqft', 'name' => '_hvnly_property_garage_sqft', 'type' => 'number', 'label' => 'Property Garage Square Footage', 'placeholder' => 'Enter property garage square footage', 'required' => true, 'locked' => true, 'adminOnly' => false, 'enabled' => true, 'order' => 10, 'hidden' => false],
        ];
    }
    
    /**
     * Get additional information fields
     */
    public function get_additional_info_fields()
    {
        return [
            ['id' => '_hvnly_property_sqft', 'name' => '_hvnly_property_sqft', 'type' => 'number', 'label' => 'Property Area, sq ft', 'placeholder' => 'Enter property area in square feet', 'required' => true, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false],
            ['id' => '_hvnly_property_lot_size', 'name' => '_hvnly_property_lot_size', 'type' => 'text', 'label' => 'Property Lot size, sq ft', 'placeholder' => 'Enter property lot size', 'required' => true, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 1, 'hidden' => false],
            ['id' => '_hvnly_property_hoa_fee', 'name' => '_hvnly_property_hoa_fee', 'type' => 'text', 'label' => 'Property HOA Fee', 'placeholder' => 'Enter property HOA fee', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 2, 'hidden' => false],
            ['id' => '_hvnly_property_annual_tax_amount', 'name' => '_hvnly_property_annual_tax_amount', 'type' => 'number', 'label' => 'Property Annual Tax Amount', 'placeholder' => 'Enter property annual tax amount', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 3, 'hidden' => false],
            ['id' => '_hvnly_property_heating', 'name' => '_hvnly_property_heating', 'type' => 'select', 'label' => 'Heating', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 4, 'hidden' => false, 'options' => ['forced_air' => 'Forced Air', 'radiator' => 'Radiator', 'heat_pump' => 'Heat Pump', 'baseboard' => 'Baseboard', 'none' => 'None']],
            ['id' => '_hvnly_property_cooling', 'name' => '_hvnly_property_cooling', 'type' => 'select', 'label' => 'Cooling', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 5, 'hidden' => false, 'options' => ['central' => 'Central Air', 'window' => 'Window Units', 'heat_pump' => 'Heat Pump', 'baseboard' => 'Baseboard', 'none' => 'None']],
            ['id' => '_hvnly_property_water', 'name' => '_hvnly_property_water', 'type' => 'select', 'label' => 'Water Source', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 6, 'hidden' => false, 'options' => ['city' => 'City', 'well' => 'Well', 'shared_well' => 'Shared Well', 'none' => 'None']],
        ];
    }
    
    /**
     * Get address & neighborhood fields
     */
    public function get_address_neighborhood_fields()
    {
        return [
            ['id' => '_hvnly_property_reference_number', 'name' => '_hvnly_property_reference_number', 'type' => 'text', 'label' => 'Property Reference Number', 'placeholder' => 'Enter property reference number', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false],
            ['id' => '_hvnly_property_building_number', 'name' => '_hvnly_property_building_number', 'type' => 'text', 'label' => 'Property Building Number', 'placeholder' => 'Enter property building number', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 1, 'hidden' => false],
            ['id' => '_hvnly_property_street', 'name' => '_hvnly_property_street', 'type' => 'text', 'label' => 'Property Street', 'placeholder' => 'Enter property street', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 2, 'hidden' => false],
            ['id' => '_hvnly_property_address_line_1', 'name' => '_hvnly_property_address_line_1', 'type' => 'text', 'label' => 'Property Address Line 1', 'placeholder' => 'Enter property address line 1', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 3, 'hidden' => false],
            ['id' => '_hvnly_property_address_line_2', 'name' => '_hvnly_property_address_line_2', 'type' => 'text', 'label' => 'Property Address Line 2', 'placeholder' => 'Enter property address line 2', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 4, 'hidden' => false],
            ['id' => '_hvnly_property_town_city', 'name' => '_hvnly_property_town_city', 'type' => 'text', 'label' => 'Property Town/City', 'placeholder' => 'Enter property town/city', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 5, 'hidden' => false],
            ['id' => '_hvnly_property_country_state', 'name' => '_hvnly_property_country_state', 'type' => 'text', 'label' => 'Property Country/State', 'placeholder' => 'Enter property country/state', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 6, 'hidden' => false],
            ['id' => '_hvnly_property_zip_code', 'name' => '_hvnly_property_zip_code', 'type' => 'text', 'label' => 'Property Zip Code', 'placeholder' => 'Enter property zip code', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 7, 'hidden' => false],
            ['id' => '_hvnly_property_location', 'name' => '_hvnly_property_location', 'type' => 'select', 'label' => 'Property Location', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 8, 'hidden' => false, 'options' => $this->get_property_locations()],
            ['id' => '_hvnly_property_country_location', 'name' => '_hvnly_property_country_location', 'type' => 'select', 'label' => 'Property Country', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 9, 'hidden' => false, 'options' => $this->get_property_countries()],
        ];
    }
    
    private function get_property_locations()
    {
        return [
            'new-york' => 'New York', 'los-angeles' => 'Los Angeles', 'chicago' => 'Chicago',
            'miami' => 'Miami', 'san-francisco' => 'San Francisco', 'austin' => 'Austin',
            'dallas' => 'Dallas', 'seattle' => 'Seattle', 'boston' => 'Boston',
            'london' => 'London', 'paris' => 'Paris', 'tokyo' => 'Tokyo',
            'dubai' => 'Dubai', 'singapore' => 'Singapore', 'sydney' => 'Sydney',
        ];
    }
    
    private function get_property_countries()
    {
        return [
            'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada',
            'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France',
            'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands',
            'AE' => 'United Arab Emirates', 'SG' => 'Singapore', 'JP' => 'Japan',
        ];
    }
    
    /**
     * Create video group fields (3 fields)
     */
    public function create_video_group_fields($group_base_id)
    {
        $group_id = 'grp_video_' . substr(uniqid(), -8);
        
        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => 'Video Title', 'placeholder' => 'Enter video title', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'video', 'group_name' => 'Video Information', 'group_position' => 0, 'group_total' => 3, 'group_base_id' => $group_base_id, 'metaKey' => 'title', 'group_collapsed' => false],
            ['id' => $group_base_id . '_url', 'name' => $group_base_id . '_url', 'type' => 'video', 'label' => 'Video URL', 'placeholder' => 'Enter video URL', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 1, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'video', 'group_name' => 'Video Information', 'group_position' => 1, 'group_total' => 3, 'group_base_id' => $group_base_id, 'metaKey' => 'url', 'group_collapsed' => false],
            ['id' => $group_base_id . '_thumbnail', 'name' => $group_base_id . '_thumbnail', 'type' => 'file', 'label' => 'Video Thumbnail', 'placeholder' => 'Upload video thumbnail', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 2, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'video', 'group_name' => 'Video Information', 'group_position' => 2, 'group_total' => 3, 'group_base_id' => $group_base_id, 'metaKey' => 'thumbnail', 'group_collapsed' => false, 'fileType' => 'image'],
        ];
    }
    
    /**
     * Create gallery group fields (2 fields)
     */
    public function create_gallery_group_fields($group_base_id)
    {
        $group_id = 'grp_gallery_' . substr(uniqid(), -8);
        
        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => 'Gallery Title', 'placeholder' => 'Enter gallery title', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'gallery', 'group_name' => 'Property Gallery', 'group_position' => 0, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'title', 'group_collapsed' => false],
            ['id' => $group_base_id . '_images', 'name' => $group_base_id . '_images', 'type' => 'gallery', 'label' => 'Gallery Images', 'placeholder' => 'Upload gallery images', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 1, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'gallery', 'group_name' => 'Property Gallery', 'group_position' => 1, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'images', 'group_collapsed' => false],
        ];
    }
    
    /**
     * Create map group fields (4 fields)
     */
    public function create_map_group_fields($group_base_id)
    {
        $group_id = 'grp_map_' . substr(uniqid(), -8);
        
        return [
            ['id' => $group_base_id . '_address', 'name' => $group_base_id . '_address', 'type' => 'text', 'label' => 'Property Map Address', 'placeholder' => 'Enter property map address', 'required' => true, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => 'Map Location', 'group_position' => 0, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'address', 'group_collapsed' => false],
            ['id' => $group_base_id . '_latitude', 'name' => $group_base_id . '_latitude', 'type' => 'text', 'label' => 'Property Latitude', 'placeholder' => 'Enter property latitude', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 1, 'hidden' => true, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => 'Map Location', 'group_position' => 1, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'latitude', 'group_collapsed' => false],
            ['id' => $group_base_id . '_longitude', 'name' => $group_base_id . '_longitude', 'type' => 'text', 'label' => 'Property Longitude', 'placeholder' => 'Enter property longitude', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 2, 'hidden' => true, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => 'Map Location', 'group_position' => 2, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'longitude', 'group_collapsed' => false],
            ['id' => $group_base_id . '_preview', 'name' => $group_base_id . '_preview', 'type' => 'map', 'label' => 'Map Preview', 'placeholder' => '', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 3, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => 'Map Location', 'group_position' => 3, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'preview', 'group_collapsed' => false, 'address_field_name' => $group_base_id . '_address', 'lat_field_name' => $group_base_id . '_latitude', 'lng_field_name' => $group_base_id . '_longitude', 'map_field_name' => $group_base_id . '_preview'],
        ];
    }
    
    /**
     * Create documents group fields - THREE FIELDS (icon, label, url)
     * This matches the React group definition
     */
    public function create_documents_group_fields($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('property_docs');
        
        // THREE FIELDS - icon, label, url
        return [
            [
                'id' => $group_base_id . '_icon',
                'name' => $group_base_id . '_icon',
                'type' => 'text',
                'label' => 'Document Icon',
                'placeholder' => 'e.g., file-pdf, file-word, file-lines',
                'required' => false,
                'locked' => false,
                'adminOnly' => false,
                'enabled' => true,
                'order' => 0,
                'hidden' => false,
                'group_id' => $group_id,
                'group_type' => 'property_docs',
                'group_name' => 'Property Documents',
                'group_position' => 0,
                'group_total' => 3,
                'group_base_id' => $group_base_id,
                'metaKey' => 'icon',
                'group_collapsed' => false,
                'show_in_sidebar' => true,
            ],
            [
                'id' => $group_base_id . '_label',
                'name' => $group_base_id . '_label',
                'type' => 'text',
                'label' => 'Document Label',
                'placeholder' => 'e.g., Floor Plan, Brochure, EPC',
                'required' => true,
                'locked' => false,
                'adminOnly' => false,
                'enabled' => true,
                'order' => 1,
                'hidden' => false,
                'group_id' => $group_id,
                'group_type' => 'property_docs',
                'group_name' => 'Property Documents',
                'group_position' => 1,
                'group_total' => 3,
                'group_base_id' => $group_base_id,
                'metaKey' => 'label',
                'group_collapsed' => false,
                'show_in_sidebar' => true,
            ],
            [
                'id' => $group_base_id . '_url',
                'name' => $group_base_id . '_url',
                'type' => 'text',
                'label' => 'Document URL',
                'placeholder' => 'Enter document URL or file path',
                'required' => true,
                'locked' => false,
                'adminOnly' => false,
                'enabled' => true,
                'order' => 2,
                'hidden' => false,
                'group_id' => $group_id,
                'group_type' => 'property_docs',
                'group_name' => 'Property Documents',
                'group_position' => 2,
                'group_total' => 3,
                'group_base_id' => $group_base_id,
                'metaKey' => 'url',
                'group_collapsed' => false,
                'show_in_sidebar' => true,
            ],
        ];
    }
    
    /**
     * Create FAQ group fields (title + faq data field).
     */
    public function create_faq_group_fields($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('faq');

        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => 'Section Title', 'placeholder' => 'e.g., Frequently Asked Questions', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'faq', 'group_name' => 'Frequently Asked Questions', 'group_position' => 0, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'title', 'group_collapsed' => false, 'show_in_sidebar' => true],
            ['id' => $group_base_id . '_faqs', 'name' => $group_base_id . '_faqs', 'type' => 'faq', 'label' => 'FAQ Items', 'placeholder' => '', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 1, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'faq', 'group_name' => 'Frequently Asked Questions', 'group_position' => 1, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'faqs', 'group_collapsed' => false, 'show_in_sidebar' => true],
        ];
    }

    /**
     * Create repeater group fields (title + repeater data field).
     */
    public function create_repeater_group_fields($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('repeater');

        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => 'Section Title', 'placeholder' => 'e.g., Property Highlights', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'repeater', 'group_name' => 'Property Highlights', 'group_position' => 0, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'title', 'group_collapsed' => false, 'show_in_sidebar' => true],
            ['id' => $group_base_id . '_items', 'name' => $group_base_id . '_items', 'type' => 'repeater', 'label' => 'Repeater Items', 'placeholder' => '', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 1, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'repeater', 'group_name' => 'Property Highlights', 'group_position' => 1, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'items', 'group_collapsed' => false, 'show_in_sidebar' => true],
        ];
    }

    /**
     * Create agents group fields (title + agents data field).
     */
    public function create_agents_group_fields($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('agents');

        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => 'Section Title', 'placeholder' => 'e.g., Listing Agents', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'agents', 'group_name' => 'Listing Agents', 'group_position' => 0, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'title', 'group_collapsed' => false, 'show_in_sidebar' => true],
            ['id' => $group_base_id . '_agents', 'name' => $group_base_id . '_agents', 'type' => 'agents', 'label' => 'Assigned Agents', 'placeholder' => '', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 1, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'agents', 'group_name' => 'Listing Agents', 'group_position' => 1, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'agents', 'group_collapsed' => false, 'show_in_sidebar' => true],
        ];
    }

    /**
     * Create property features group field (checkbox list).
     */
    public function create_features_group_fields($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('features');

        return [
            ['id' => $group_base_id . '_features', 'name' => $group_base_id . '_features', 'type' => 'checkbox', 'label' => 'Property Features', 'placeholder' => '', 'required' => false, 'locked' => false, 'adminOnly' => false, 'enabled' => true, 'order' => 0, 'hidden' => false, 'group_id' => $group_id, 'group_type' => 'features', 'group_name' => 'Property Features', 'group_position' => 0, 'group_total' => 1, 'group_base_id' => $group_base_id, 'metaKey' => 'features', 'group_collapsed' => false, 'show_in_sidebar' => true],
        ];
    }

    /**
     * Normalize a field name to use master base ID
     */
    public function normalize_field_name($field_name, $group_type)
    {
        $master_ids = $this->get_or_create_master_base_ids();
        
        $master_base = '';
        switch ($group_type) {
            case 'video': $master_base = $master_ids['video'] ?? ''; break;
            case 'gallery': $master_base = $master_ids['gallery'] ?? ''; break;
            case 'map': $master_base = $master_ids['map'] ?? ''; break;
            case 'property_docs': $master_base = $master_ids['property_docs'] ?? ''; break;
            case 'faq': $master_base = $master_ids['faq'] ?? ''; break;
            case 'repeater': $master_base = $master_ids['repeater'] ?? ''; break;
            case 'agents': $master_base = $master_ids['agents'] ?? ''; break;
            case 'features': $master_base = $master_ids['features'] ?? ''; break;
            default: return $field_name;
        }
        
        if (empty($master_base)) return $field_name;
        
        $suffix_patterns = ['_url', '_title', '_thumbnail', '_images', '_address', '_latitude', '_longitude', '_preview', '_icon', '_label', '_documents', '_faqs', '_items', '_agents', '_features'];
        
        foreach ($suffix_patterns as $pattern) {
            if (strpos($field_name, $pattern) !== false) {
                return $master_base . $pattern;
            }
        }
        
        return $field_name;
    }
    
    /**
     * Validate configuration for consistency
     */
    public function validate_configuration($config)
    {
        $errors = [];
        
        foreach ($config as $section_key => $section) {
            $fields = $section['fields'] ?? [];
            
            foreach ($fields as $field) {
                $group_type = $field['group_type'] ?? '';
                $group_id = $field['group_id'] ?? '';
                $group_total = $field['group_total'] ?? 0;
                $meta_key = $field['metaKey'] ?? '';
                
                if ($group_type === 'property_docs') {
                    if ($group_total !== 3) {
                        $errors[] = "Property docs group {$group_id} has group_total = {$group_total}, should be 3";
                    }
                    if (!in_array($meta_key, ['icon', 'label', 'url'])) {
                        $errors[] = "Property docs group {$group_id} has metaKey = {$meta_key}, should be icon/label/url";
                    }
                }
                
                if ($group_type === 'map') {
                    $group_base_id = $field['group_base_id'] ?? '';
                    if (isset($field['address_field_name']) && !empty($group_base_id)) {
                        $expected = $group_base_id . '_address';
                        if ($field['address_field_name'] !== $expected) {
                            $errors[] = "Map group {$group_id} address_field_name mismatch";
                        }
                    }
                }
            }
        }
        
        return ['is_valid' => empty($errors), 'errors' => $errors, 'error_count' => count($errors)];
    }
}