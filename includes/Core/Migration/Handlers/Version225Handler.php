<?php
/**
 * Version 2.2.5 Migration Handler
 *
 * Fixes duplicate group_base_id issue for multiple groups of the same type.
 * Converts old ID pattern to NEW UNIQUE pattern with microtime + random
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       2.2.5
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Version225Handler
 *
 * Fixes duplicate group_base_id issue by regenerating configuration with unique IDs.
 */
class Version225Handler implements MigrationInterface {

    use MigrationTrait;

    /**
     * Property builder storage key.
     *
     * @var string
     */
    const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';

    /**
     * Get the target version.
     */
    public function get_version(): string {
        return '2.2.5';
    }

    /**
     * Get migration description.
     */
    public function get_description(): string {
        return 'Fixes duplicate group_base_id issue and converts to UNIQUE ID pattern';
    }

    /**
     * Generate a UNIQUE base ID matching the new pattern
     */
    private function generate_unique_base_id($group_type) {
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
            default:
                return "group_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
        }
    }

    /**
     * Generate a UNIQUE group ID
     */
    private function generate_unique_group_id($group_type) {
        $timestamp = time();
        $short_id = substr(uniqid(), -8);
        $random = wp_rand(1000, 9999);
        return "grp_{$group_type}_{$timestamp}_{$short_id}_{$random}";
    }

    /**
     * Check if a base ID is already using the new unique pattern
     */
    private function is_new_unique_pattern($base_id): bool {
        $patterns = [
            '/^video_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^gallery_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^map_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
            '/^property_docs_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}$/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $base_id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if migration is needed.
     *
     * Only runs for empty builder config or duplicate group_base_id values.
     * Legacy ID patterns are preserved when data is already stored under them.
     */
    public function is_needed(): bool {
        $sections = get_option(self::PROPERTY_BUILDER_KEY, []);

        if ( $this->builder_config_is_empty( $sections ) ) {
            if ( $this->site_has_published_properties() ) {
                $this->log(
                    'Skipping 2.2.5 empty-builder seed — published properties exist; attempting recovery instead.',
                    'warning',
                    '2.2.5'
                );
                $this->attempt_builder_config_recovery();
                return false;
            }

            return true;
        }

        return $this->has_duplicate_group_base_ids($sections);
    }

    /**
     * Execute the migration - Regenerate configuration with UNIQUE IDs
     */
    public function up(): bool {
        $this->log('Starting migration to version ' . $this->get_version(), 'info', '2.2.5');
        $this->log('In-place duplicate fix; preserving existing section and field IDs', 'info', '2.2.5');

        $backup_id = $this->backup_option(self::PROPERTY_BUILDER_KEY, '2.2.5');
        if ($backup_id) {
            $this->log('Backup created: ' . $backup_id, 'info', '2.2.5');
        }

        $sections = get_option(self::PROPERTY_BUILDER_KEY, []);

        if (empty($sections)) {
            $unified = $this->get_unified_builder_config();
            $new_sections = $unified ?? $this->build_fresh_configuration();
            $this->log('Seeded empty builder from unified defaults', 'info', '2.2.5');
        } else {
            $before_sections = $sections;
            $new_sections    = $this->fix_duplicate_group_base_ids_in_config($sections);
            $new_sections    = $this->merge_missing_standard_sections($new_sections );

            if ( class_exists( '\HvnlyNab\Core\DataPreservation\BuilderMetaMigrator' ) ) {
                $remap = \HvnlyNab\Core\DataPreservation\BuilderMetaMigrator::build_remap_between_configs(
                    $before_sections,
                    $new_sections
                );
                if ( ! empty( $remap ) ) {
                    \HvnlyNab\Core\DataPreservation\BuilderMetaMigrator::schedule_property_remap( $remap );
                    $this->log(
                        'Scheduled 2.3.6 meta remap for ' . count( $remap ) . ' base ID change(s) from 2.2.5',
                        'info',
                        '2.2.5'
                    );
                }
            }
        }

        uasort(
            $new_sections,
            static function ($a, $b) {
                return ( $a['order'] ?? 999 ) - ( $b['order'] ?? 999 );
            }
        );

        $this->safe_update_option(self::PROPERTY_BUILDER_KEY, $new_sections);
        wp_cache_delete(self::PROPERTY_BUILDER_KEY, 'options');

        $this->cleanup_backups(5);

        $this->log('Migration completed successfully', 'info', '2.2.5');
        return true;
    }
    
    /**
     * Build fresh configuration with UNIQUE IDs for standard sections
     */
    private function build_fresh_configuration(): array {
        $unified = $this->get_unified_builder_config();
        if ($unified !== null) {
            return $unified;
        }

        $video_base = $this->generate_unique_base_id('video');
        $gallery_base = $this->generate_unique_base_id('gallery');
        $map_base = $this->generate_unique_base_id('map');
        $docs_base = $this->generate_unique_base_id('property_docs');
        
        $basic_info_id = 'sec_basic_info';
        $additional_info_id = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $address_neighborhood_id = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $video_section_id = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $gallery_section_id = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $location_section_id = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $documents_section_id = 'hvnly__dyamic_metabox_tab__' . uniqid();
        
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
            $video_section_id => [
                'id' => $video_section_id,
                'title' => 'Property Video',
                'icon' => 'fas fa-video',
                'required' => false,
                'order' => 3,
                'collapsed' => false,
                'fields' => $this->create_video_group_fields($video_base),
            ],
            $gallery_section_id => [
                'id' => $gallery_section_id,
                'title' => 'Property Gallery',
                'icon' => 'fas fa-images',
                'required' => false,
                'order' => 4,
                'collapsed' => false,
                'fields' => $this->create_gallery_group_fields($gallery_base),
            ],
            $location_section_id => [
                'id' => $location_section_id,
                'title' => 'Property Location',
                'icon' => 'fas fa-map-marker-alt',
                'required' => false,
                'order' => 5,
                'collapsed' => false,
                'fields' => $this->create_map_group_fields($map_base),
            ],
            $documents_section_id => [
                'id' => $documents_section_id,
                'title' => 'Property Documents',
                'icon' => 'fas fa-file-pdf',
                'required' => false,
                'order' => 6,
                'collapsed' => false,
                'fields' => $this->create_documents_group_fields($docs_base),
            ],
        ];
    }
    
    /**
     * Regenerate a custom section with UNIQUE IDs for all its groups
     */
    private function regenerate_section_with_unique_ids($section): array {
        if (empty($section['fields'])) {
            return $section;
        }
        
        $new_fields = [];
        $group_mapping = [];
        
        foreach ($section['fields'] as $field) {
            $group_type = $field['group_type'] ?? '';
            $original_group_id = $field['group_id'] ?? '';
            $meta_key = $field['metaKey'] ?? '';
            
            if (!empty($group_type) && !empty($original_group_id)) {
                // Check if we already created a mapping for this group
                if (!isset($group_mapping[$original_group_id])) {
                    $new_group_id = $this->generate_unique_group_id($group_type);
                    $new_group_base_id = $this->generate_unique_base_id($group_type);
                    $group_mapping[$original_group_id] = [
                        'group_id' => $new_group_id,
                        'group_base_id' => $new_group_base_id
                    ];
                }
                
                $mapping = $group_mapping[$original_group_id];
                
                // Create updated field with new IDs
                $new_field = $field;
                $new_field['group_id'] = $mapping['group_id'];
                $new_field['group_base_id'] = $mapping['group_base_id'];
                $new_field['id'] = $mapping['group_base_id'] . '_' . $meta_key;
                $new_field['name'] = $mapping['group_base_id'] . '_' . $meta_key;
                
                // Update map relationship fields
                if ($group_type === 'map') {
                    if (isset($new_field['address_field_name'])) {
                        $new_field['address_field_name'] = $mapping['group_base_id'] . '_address';
                    }
                    if (isset($new_field['lat_field_name'])) {
                        $new_field['lat_field_name'] = $mapping['group_base_id'] . '_latitude';
                    }
                    if (isset($new_field['lng_field_name'])) {
                        $new_field['lng_field_name'] = $mapping['group_base_id'] . '_longitude';
                    }
                    if (isset($new_field['map_field_name'])) {
                        $new_field['map_field_name'] = $mapping['group_base_id'] . '_preview';
                    }
                }
                
                $new_fields[] = $new_field;
            } else {
                // Non-group field, keep as is
                $new_fields[] = $field;
            }
        }
        
        $section['fields'] = $new_fields;
        return $section;
    }
    
    /**
     * Get basic info fields
     */
    private function get_basic_info_fields(): array {
        return [
            ['id' => '_hvnly_property_price', 'name' => '_hvnly_property_price', 'type' => 'price_label', 'label' => 'Property Price', 'placeholder' => 'Enter property price', 'required' => true, 'locked' => true, 'order' => 0],
            ['id' => '_hvnly_property_reception_rooms', 'name' => '_hvnly_property_reception_rooms', 'type' => 'number', 'label' => 'Property Reception Rooms', 'placeholder' => 'Enter reception rooms', 'required' => true, 'locked' => true, 'order' => 1],
            ['id' => '_hvnly_property_bedrooms', 'name' => '_hvnly_property_bedrooms', 'type' => 'number', 'label' => 'Property Bedrooms', 'placeholder' => 'Enter property bedrooms', 'required' => true, 'locked' => true, 'order' => 2],
            ['id' => '_hvnly_property_bathrooms', 'name' => '_hvnly_property_bathrooms', 'type' => 'number', 'label' => 'Property Bathrooms', 'placeholder' => 'Enter property bathrooms', 'required' => true, 'locked' => true, 'order' => 3],
            ['id' => '_hvnly_property_half_bathrooms', 'name' => '_hvnly_property_half_bathrooms', 'type' => 'number', 'label' => 'Property Half Baths', 'placeholder' => 'Enter property half baths', 'required' => true, 'locked' => true, 'order' => 4],
            ['id' => '_hvnly_property_kitchens', 'name' => '_hvnly_property_kitchens', 'type' => 'number', 'label' => 'Property Kitchen', 'placeholder' => 'Enter property kitchen', 'required' => false, 'locked' => false, 'order' => 5],
            ['id' => '_hvnly_property_total_rooms', 'name' => '_hvnly_property_total_rooms', 'type' => 'number', 'label' => 'Property Total Rooms', 'placeholder' => 'Enter property total rooms', 'required' => true, 'locked' => true, 'order' => 6],
            ['id' => '_hvnly_property_floors', 'name' => '_hvnly_property_floors', 'type' => 'number', 'label' => 'Property Floors', 'placeholder' => 'Enter property floors', 'required' => false, 'locked' => false, 'order' => 7],
            ['id' => '_hvnly_property_year_built', 'name' => '_hvnly_property_year_built', 'type' => 'number', 'label' => 'Property Year Built', 'placeholder' => 'Enter property year built', 'required' => false, 'locked' => false, 'order' => 8],
            ['id' => '_hvnly_property_mls_number', 'name' => '_hvnly_property_mls_number', 'type' => 'text', 'label' => 'Property MLS Number', 'placeholder' => 'Enter property MLS number', 'required' => false, 'locked' => false, 'order' => 9],
            ['id' => '_hvnly_property_garage_sqft', 'name' => '_hvnly_property_garage_sqft', 'type' => 'number', 'label' => 'Property Garage Square Footage', 'placeholder' => 'Enter property garage square footage', 'required' => true, 'locked' => true, 'order' => 10],
        ];
    }
    
    /**
     * Get additional info fields
     */
    private function get_additional_info_fields(): array {
        return [
            ['id' => '_hvnly_property_sqft', 'name' => '_hvnly_property_sqft', 'type' => 'number', 'label' => 'Property Area, sq ft', 'placeholder' => 'Enter property area in square feet', 'required' => true, 'locked' => false, 'order' => 0],
            ['id' => '_hvnly_property_lot_size', 'name' => '_hvnly_property_lot_size', 'type' => 'text', 'label' => 'Property Lot size, sq ft', 'placeholder' => 'Enter property lot size', 'required' => true, 'locked' => false, 'order' => 1],
            ['id' => '_hvnly_property_hoa_fee', 'name' => '_hvnly_property_hoa_fee', 'type' => 'text', 'label' => 'Property HOA Fee', 'placeholder' => 'Enter property HOA fee', 'required' => false, 'locked' => false, 'order' => 2],
            ['id' => '_hvnly_property_annual_tax_amount', 'name' => '_hvnly_property_annual_tax_amount', 'type' => 'number', 'label' => 'Property Annual Tax Amount', 'placeholder' => 'Enter property annual tax amount', 'required' => false, 'locked' => false, 'order' => 3],
            ['id' => '_hvnly_property_heating', 'name' => '_hvnly_property_heating', 'type' => 'select', 'label' => 'Heating', 'required' => false, 'locked' => false, 'order' => 4, 'options' => ['forced_air' => 'Forced Air', 'radiator' => 'Radiator', 'heat_pump' => 'Heat Pump', 'baseboard' => 'Baseboard', 'none' => 'None']],
            ['id' => '_hvnly_property_cooling', 'name' => '_hvnly_property_cooling', 'type' => 'select', 'label' => 'Cooling', 'required' => false, 'locked' => false, 'order' => 5, 'options' => ['central' => 'Central Air', 'window' => 'Window Units', 'heat_pump' => 'Heat Pump', 'baseboard' => 'Baseboard', 'none' => 'None']],
            ['id' => '_hvnly_property_water', 'name' => '_hvnly_property_water', 'type' => 'select', 'label' => 'Water Source', 'required' => false, 'locked' => false, 'order' => 6, 'options' => ['city' => 'City', 'well' => 'Well', 'shared_well' => 'Shared Well', 'none' => 'None']],
        ];
    }
    
    /**
     * Get address & neighborhood fields
     */
    private function get_address_neighborhood_fields(): array {
        return [
            ['id' => '_hvnly_property_reference_number', 'name' => '_hvnly_property_reference_number', 'type' => 'text', 'label' => 'Property Reference Number', 'placeholder' => 'Enter property reference number', 'required' => false, 'locked' => false, 'order' => 0],
            ['id' => '_hvnly_property_building_number', 'name' => '_hvnly_property_building_number', 'type' => 'text', 'label' => 'Property Building Number', 'placeholder' => 'Enter property building number', 'required' => false, 'locked' => false, 'order' => 1],
            ['id' => '_hvnly_property_street', 'name' => '_hvnly_property_street', 'type' => 'text', 'label' => 'Property Street', 'placeholder' => 'Enter property street', 'required' => false, 'locked' => false, 'order' => 2],
            ['id' => '_hvnly_property_address_line_1', 'name' => '_hvnly_property_address_line_1', 'type' => 'text', 'label' => 'Property Address Line 1', 'placeholder' => 'Enter property address line 1', 'required' => false, 'locked' => false, 'order' => 3],
            ['id' => '_hvnly_property_address_line_2', 'name' => '_hvnly_property_address_line_2', 'type' => 'text', 'label' => 'Property Address Line 2', 'placeholder' => 'Enter property address line 2', 'required' => false, 'locked' => false, 'order' => 4],
            ['id' => '_hvnly_property_town_city', 'name' => '_hvnly_property_town_city', 'type' => 'text', 'label' => 'Property Town/City', 'placeholder' => 'Enter property town/city', 'required' => false, 'locked' => false, 'order' => 5],
            ['id' => '_hvnly_property_country_state', 'name' => '_hvnly_property_country_state', 'type' => 'text', 'label' => 'Property Country/State', 'placeholder' => 'Enter property country/state', 'required' => false, 'locked' => false, 'order' => 6],
            ['id' => '_hvnly_property_zip_code', 'name' => '_hvnly_property_zip_code', 'type' => 'text', 'label' => 'Property Zip Code', 'placeholder' => 'Enter property zip code', 'required' => false, 'locked' => false, 'order' => 7],
            ['id' => '_hvnly_property_location', 'name' => '_hvnly_property_location', 'type' => 'select', 'label' => 'Property Location', 'required' => false, 'locked' => false, 'order' => 8, 'options' => ['new-york' => 'New York', 'los-angeles' => 'Los Angeles', 'chicago' => 'Chicago', 'miami' => 'Miami', 'san-francisco' => 'San Francisco', 'austin' => 'Austin']],
            ['id' => '_hvnly_property_country_location', 'name' => '_hvnly_property_country_location', 'type' => 'select', 'label' => 'Property Country', 'required' => false, 'locked' => false, 'order' => 9, 'options' => ['US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia']],
        ];
    }
    
    /**
     * Create video group fields with UNIQUE base ID
     */
    private function create_video_group_fields($group_base_id): array {
        $group_id = $this->generate_unique_group_id('video');
        
        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => 'Video Title', 'placeholder' => 'Enter video title', 'required' => false, 'locked' => false, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'video', 'group_name' => 'Video Information', 'group_position' => 0, 'group_total' => 3, 'group_base_id' => $group_base_id, 'metaKey' => 'title'],
            ['id' => $group_base_id . '_url', 'name' => $group_base_id . '_url', 'type' => 'video', 'label' => 'Video URL', 'placeholder' => 'Enter video URL', 'required' => false, 'locked' => false, 'order' => 1, 'group_id' => $group_id, 'group_type' => 'video', 'group_name' => 'Video Information', 'group_position' => 1, 'group_total' => 3, 'group_base_id' => $group_base_id, 'metaKey' => 'url'],
            ['id' => $group_base_id . '_thumbnail', 'name' => $group_base_id . '_thumbnail', 'type' => 'file', 'label' => 'Video Thumbnail', 'placeholder' => 'Upload video thumbnail', 'required' => false, 'locked' => false, 'order' => 2, 'group_id' => $group_id, 'group_type' => 'video', 'group_name' => 'Video Information', 'group_position' => 2, 'group_total' => 3, 'group_base_id' => $group_base_id, 'metaKey' => 'thumbnail', 'fileType' => 'image'],
        ];
    }
    
    /**
     * Create gallery group fields with UNIQUE base ID
     */
    private function create_gallery_group_fields($group_base_id): array {
        $group_id = $this->generate_unique_group_id('gallery');
        
        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => 'Gallery Title', 'placeholder' => 'Enter gallery title', 'required' => false, 'locked' => false, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'gallery', 'group_name' => 'Property Gallery', 'group_position' => 0, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'title'],
            ['id' => $group_base_id . '_images', 'name' => $group_base_id . '_images', 'type' => 'gallery', 'label' => 'Gallery Images', 'placeholder' => 'Upload gallery images', 'required' => false, 'locked' => false, 'order' => 1, 'group_id' => $group_id, 'group_type' => 'gallery', 'group_name' => 'Property Gallery', 'group_position' => 1, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'images'],
        ];
    }
    
    /**
     * Create map group fields with UNIQUE base ID
     */
    private function create_map_group_fields($group_base_id): array {
        $group_id = $this->generate_unique_group_id('map');
        
        return [
            ['id' => $group_base_id . '_address', 'name' => $group_base_id . '_address', 'type' => 'text', 'label' => 'Property Map Address', 'placeholder' => 'Enter property map address', 'required' => true, 'locked' => false, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => 'Map Location', 'group_position' => 0, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'address'],
            ['id' => $group_base_id . '_latitude', 'name' => $group_base_id . '_latitude', 'type' => 'text', 'label' => 'Property Latitude', 'placeholder' => 'Enter property latitude', 'required' => false, 'locked' => false, 'order' => 1, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => 'Map Location', 'group_position' => 1, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'latitude', 'hidden' => true],
            ['id' => $group_base_id . '_longitude', 'name' => $group_base_id . '_longitude', 'type' => 'text', 'label' => 'Property Longitude', 'placeholder' => 'Enter property longitude', 'required' => false, 'locked' => false, 'order' => 2, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => 'Map Location', 'group_position' => 2, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'longitude', 'hidden' => true],
            ['id' => $group_base_id . '_preview', 'name' => $group_base_id . '_preview', 'type' => 'map', 'label' => 'Map Preview', 'placeholder' => '', 'required' => false, 'locked' => false, 'order' => 3, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => 'Map Location', 'group_position' => 3, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'preview', 'address_field_name' => $group_base_id . '_address', 'lat_field_name' => $group_base_id . '_latitude', 'lng_field_name' => $group_base_id . '_longitude', 'map_field_name' => $group_base_id . '_preview'],
        ];
    }
    
    /**
     * Create documents group fields with UNIQUE base ID - SINGLE FIELD
     */
    private function create_documents_group_fields($group_base_id): array
    {
        $group_id = $this->generate_unique_group_id('property_docs');
        
        return [
            [
                'id' => $group_base_id . '_documents',
                'name' => $group_base_id . '_documents',
                'type' => 'property_docs',
                'label' => 'Property Documents',
                'placeholder' => '',
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
                'group_total' => 1,
                'group_base_id' => $group_base_id,
                'metaKey' => 'documents',
                'group_collapsed' => false,
                'show_in_sidebar' => true,
            ],
        ];
    }

    /**
     * Find the latest backup for this migration version.
     */
    private function find_latest_backup() {
        global $wpdb;
        
        $backup_pattern = '_hvnly_backup_2.2.5_%';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration backup option lookup.
        $backups = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 ORDER BY option_id DESC LIMIT 1",
                $backup_pattern
            )
        );
        
        return !empty($backups) ? $backups[0] : false;
    }

    /**
     * Rollback the migration.
     */
    public function down(): bool {
        $this->log('Rolling back migration to version ' . $this->get_version());
        
        $backup_id = $this->find_latest_backup();
        
        if ($backup_id) {
            $restored = $this->restore_from_backup($backup_id);
            if ($restored) {
                $this->log('Successfully rolled back from backup: ' . $backup_id);
                delete_option('hvnly_migration_2.2.5_completed');
                return true;
            }
        }
        
        $this->log('No backup found for rollback');
        return false;
    }
}