<?php
/**
 * Version 2.2.2 Migration Handler - Complete Field Normalization
 *
 * Normalizes ALL field structures across import, builder, and frontend.
 * Ensures consistent data storage and retrieval for all property fields.
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       2.2.2
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\DataPreservation\BatchProcessor;
use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Version222Handler
 *
 * Complete field normalization across all plugin components.
 *
 * @since 2.2.2
 */
class Version222Handler implements MigrationInterface {

    use MigrationTrait;

    /**
     * Property builder storage key.
     *
     * @var string
     */
    const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';

    /**
     * Card builder storage key.
     *
     * @var string
     */
    const CARD_BUILDER_KEY = 'hvnly_property_card.sections';

    const BATCH_KEY    = 'hvnly_migration_222_offset';
    const CONFIG_DONE  = 'hvnly_migration_222_config_done';
    const STATS_KEY    = 'hvnly_migration_222_stats';

    /**
     * Get the target version.
     *
     * @since 2.2.2
     * @return string
     */
    public function get_version(): string {
        return '2.2.2';
    }

    /**
     * Get migration description.
     *
     * @since 2.2.2
     * @return string
     */
    public function get_description(): string {
        return 'Complete field normalization across import, builder, and frontend';
    }

    /**
     * Check if migration is needed.
     *
     * @since 2.2.2
     * @return bool
     */
    public function is_needed(): bool {
        // Always run on first install of 2.2.2
        $version_completed = get_option('hvnly_migration_2.2.2_completed', false);
        
        if ($version_completed) {
            return false;
        }
        
        // Check if there are any properties that need migration
        $properties = get_posts([
            'post_type' => 'hvnly_property',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);
        
        return !empty($properties);
    }

    /**
     * Execute the complete migration.
     *
     * @since 2.2.2
     * @return bool
     */
    public function up(): bool {
        $this->log('========================================');
        $this->log('Starting COMPLETE migration to version ' . $this->get_version());
        $this->log('========================================');

        if ( ! get_option( self::CONFIG_DONE, false ) ) {
            $backup_id      = $this->backup_option( self::PROPERTY_BUILDER_KEY, '2.2.2' );
            $card_backup_id = $this->backup_option( self::CARD_BUILDER_KEY, '2.2.2' );

            if ( $backup_id ) {
                $this->log( 'Property builder backup created: ' . $backup_id );
            }
            if ( $card_backup_id ) {
                $this->log( 'Card builder backup created: ' . $card_backup_id );
            }

            $this->log( 'STEP 1: Normalizing property builder configuration...' );
            $this->normalize_builder_configuration();

            $this->log( 'STEP 2: Normalizing card builder configuration...' );
            $this->normalize_card_builder_configuration();

            update_option( self::CONFIG_DONE, 1, false );
        }

        $migration_stats = get_option(
            self::STATS_KEY,
            array(
                'properties_processed' => 0,
                'fields_migrated'      => 0,
                'documents_migrated'   => 0,
                'videos_migrated'      => 0,
                'galleries_migrated'   => 0,
                'maps_migrated'        => 0,
            )
        );

        $this->log( 'STEP 3: Migrating existing properties (batched)...' );

        $result = BatchProcessor::each_property(
            function ( int $property_id ) use ( &$migration_stats ) {
                $this->log( "Migrating property ID: {$property_id}" );
                $migration_stats['fields_migrated'] += $this->migrate_property_groups( $property_id, $migration_stats );
                $this->normalize_price_field( $property_id );
                $this->ensure_property_id( $property_id );
            },
            self::BATCH_KEY
        );

        $migration_stats['properties_processed'] = $result['offset'];
        update_option( self::STATS_KEY, $migration_stats, false );

        $this->log(
            sprintf(
                '222 batch progress: %d/%d properties',
                $result['offset'],
                $result['total']
            )
        );

        if ( ! $result['complete'] ) {
            return false;
        }

        $this->log( 'STEP 4: Creating unified meta index...' );
        $this->create_unified_meta_index();

        $this->log( 'STEP 5: Orphan scan (non-destructive)...' );
        $cleaned_count = $this->cleanup_orphaned_meta();

        update_option( 'hvnly_migration_2.2.2_completed', true );
        update_option( 'hvnly_migration_2.2.2_stats', $migration_stats );

        delete_option( self::CONFIG_DONE );
        delete_option( self::STATS_KEY );

        $this->log( '========================================' );
        $this->log(
            sprintf(
                'Migration COMPLETE! Processed: %d properties, %d fields, Cleaned: %d orphaned entries',
                $migration_stats['properties_processed'],
                $migration_stats['fields_migrated'],
                $cleaned_count
            )
        );
        $this->log( '========================================' );

        $this->cleanup_backups( 5 );

        return true;
    }

    /**
     * Normalize the property builder configuration.
     *
     * @since 2.2.2
     * @return bool
     */
    private function normalize_builder_configuration(): bool {
        $sections = get_option(self::PROPERTY_BUILDER_KEY, []);
        
        if (empty($sections)) {
            // Create default configuration with proper structure
            $sections = $this->get_default_normalized_configuration();
            update_option(self::PROPERTY_BUILDER_KEY, $sections);
            $this->log('Created default normalized configuration');
            return true;
        }
        
        $updated = false;
        
        foreach ($sections as &$section) {
            if (!isset($section['fields']) || !is_array($section['fields'])) {
                continue;
            }
            
            foreach ($section['fields'] as &$field) {
                // Normalize group fields
                if (!empty($field['group_id'])) {
                    $normalized = $this->normalize_group_field($field);
                    if ($normalized !== $field) {
                        $field = $normalized;
                        $updated = true;
                    }
                }
                
                // Normalize regular fields
                if (empty($field['group_id'])) {
                    $normalized = $this->normalize_regular_field($field);
                    if ($normalized !== $field) {
                        $field = $normalized;
                        $updated = true;
                    }
                }
            }
        }
        
        if ($updated) {
            update_option(self::PROPERTY_BUILDER_KEY, $sections);
            $this->log('Builder configuration normalized');
        }
        
        return $updated;
    }

    /**
     * Normalize a group field.
     *
     * @since 2.2.2
     * @param array $field Field configuration.
     * @return array Normalized field.
     */
    private function normalize_group_field(array $field): array {
        // Ensure consistent group_base_id
        if (empty($field['group_base_id'])) {
            $timestamp = time();
            $unique_suffix = substr(uniqid(), -8);
            $group_type = $field['group_type'] ?? 'unknown';
            
            switch ($group_type) {
                case 'video':
                    $field['group_base_id'] = "video_{$timestamp}_{$unique_suffix}";
                    break;
                case 'gallery':
                    $field['group_base_id'] = "gallery_{$timestamp}_{$unique_suffix}";
                    break;
                case 'map':
                    $field['group_base_id'] = "map_{$timestamp}_{$unique_suffix}";
                    break;
                case 'property_docs':
                    $field['group_base_id'] = "property_docs_{$timestamp}_{$unique_suffix}";
                    break;
                default:
                    $field['group_base_id'] = "group_{$timestamp}_{$unique_suffix}";
            }
        }
        
        // Ensure consistent metaKey
        if (empty($field['metaKey'])) {
            $name_parts = explode('_', $field['name'] ?? $field['id'] ?? '');
            $field['metaKey'] = end($name_parts);
        }
        
        // Ensure consistent name format (use group_base_id + metaKey)
        if (!empty($field['group_base_id']) && !empty($field['metaKey'])) {
            $expected_name = $field['group_base_id'] . '_' . $field['metaKey'];
            if (($field['name'] ?? '') !== $expected_name) {
                $field['name'] = $expected_name;
            }
        }
        
        return $field;
    }

    /**
     * Normalize a regular field.
     *
     * @since 2.2.2
     * @param array $field Field configuration.
     * @return array Normalized field.
     */
    private function normalize_regular_field(array $field): array {
        // Ensure consistent name format for standard fields
        $standard_fields = [
            '_hvnly_property_price' => 'price_label',
            '_hvnly_property_bedrooms' => 'number',
            '_hvnly_property_bathrooms' => 'number',
            '_hvnly_property_reception_rooms' => 'number',
            '_hvnly_property_sqft' => 'number',
            '_hvnly_property_year_built' => 'number',
            '_hvnly_property_location' => 'select',
            '_hvnly_property_country_location' => 'select',
        ];
        
        $field_name = $field['name'] ?? $field['id'] ?? '';
        
        if (isset($standard_fields[$field_name])) {
            $field['type'] = $standard_fields[$field_name];
        }
        
        return $field;
    }

    /**
     * Normalize card builder configuration.
     *
     * @since 2.2.2
     * @return bool
     */
    private function normalize_card_builder_configuration(): bool {
        $sections = get_option(self::CARD_BUILDER_KEY, []);
        
        if (empty($sections)) {
            return false;
        }
        
        // Card builder normalization logic
        $updated = false;
        
        foreach ($sections as &$section) {
            if (isset($section['fields']) && is_array($section['fields'])) {
                foreach ($section['fields'] as &$field) {
                    if (isset($field['type']) && $field['type'] === 'price') {
                        $field['type'] = 'price_label';
                        $updated = true;
                    }
                }
            }
        }
        
        if ($updated) {
            update_option(self::CARD_BUILDER_KEY, $sections);
            $this->log('Card builder configuration normalized');
        }
        
        return $updated;
    }

    /**
     * Migrate all existing properties to normalized format.
     *
     * @since 2.2.2
     * @return array Migration statistics.
     */
    private function migrate_all_properties(): array {
        global $wpdb;
        
        $properties = get_posts([
            'post_type' => 'hvnly_property',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids'
        ]);
        
        $stats = [
            'properties_processed' => count($properties),
            'fields_migrated' => 0,
            'documents_migrated' => 0,
            'videos_migrated' => 0,
            'galleries_migrated' => 0,
            'maps_migrated' => 0
        ];
        
        foreach ($properties as $property_id) {
            $this->log("Migrating property ID: {$property_id}");
            
            // Migrate all group fields
            $stats['fields_migrated'] += $this->migrate_property_groups($property_id, $stats);
            
            // Ensure price field is properly formatted
            $this->normalize_price_field($property_id);
            
            // Generate property ID if missing
            $this->ensure_property_id($property_id);
        }
        
        return $stats;
    }

    /**
     * Migrate property groups to normalized format.
     *
     * @since 2.2.2
     * @param int $property_id Property ID.
     * @param array &$stats Statistics reference.
     * @return int Number of fields migrated.
     */
    private function migrate_property_groups(int $property_id, array &$stats): int {
        global $wpdb;
        
        $all_meta = get_post_meta($property_id);
        $migrated_count = 0;
        
        // Track processed groups to avoid duplicates
        $processed_groups = [];
        
        // PATTERN 1: Migrate video groups
        $video_patterns = ['/^video_\d+_([a-f0-9]+)_/', '/^video_([a-f0-9]+)_/'];
        $video_groups = $this->find_groups_by_pattern($all_meta, $video_patterns);
        
        foreach ($video_groups as $group_base => $fields) {
            if (in_array($group_base, $processed_groups)) {
                continue;
            }
            
            $processed_groups[] = $group_base;
            
            // Extract video data
            $title = $fields['title'] ?? '';
            $url = $fields['url'] ?? '';
            $thumbnail = $fields['thumbnail'] ?? '';
            
            if (!empty($url)) {
                // Create normalized field names
                $timestamp = time();
                $unique_suffix = substr(uniqid(), -8);
                $new_base = "video_{$timestamp}_{$unique_suffix}";
                
                $new_title_key = $new_base . '_title';
                $new_url_key = $new_base . '_url';
                $new_thumbnail_key = $new_base . '_thumbnail';
                
                // Copy to new keys if they don't exist
                if (!isset($all_meta[$new_url_key])) {
                    update_post_meta($property_id, $new_title_key, $title);
                    update_post_meta($property_id, $new_url_key, $url);
                    update_post_meta($property_id, $new_thumbnail_key, $thumbnail);
                    $migrated_count += 3;
                    $stats['videos_migrated']++;
                    
                    $this->log("  - Migrated video group: {$group_base} -> {$new_base}");
                }
            }
        }
        
        // PATTERN 2: Migrate gallery groups
        $gallery_patterns = ['/^gallery_\d+_([a-f0-9]+)_/', '/^gallery_([a-f0-9]+)_/'];
        $gallery_groups = $this->find_groups_by_pattern($all_meta, $gallery_patterns);
        
        foreach ($gallery_groups as $group_base => $fields) {
            if (in_array($group_base, $processed_groups)) {
                continue;
            }
            
            $processed_groups[] = $group_base;
            
            $title = $fields['title'] ?? '';
            $images = $fields['images'] ?? '';
            
            if (!empty($images)) {
                $timestamp = time();
                $unique_suffix = substr(uniqid(), -8);
                $new_base = "gallery_{$timestamp}_{$unique_suffix}";
                
                $new_title_key = $new_base . '_title';
                $new_images_key = $new_base . '_images';
                
                if (!isset($all_meta[$new_images_key])) {
                    update_post_meta($property_id, $new_title_key, $title);
                    update_post_meta($property_id, $new_images_key, $images);
                    $migrated_count += 2;
                    $stats['galleries_migrated']++;
                    
                    $this->log("  - Migrated gallery group: {$group_base} -> {$new_base}");
                }
            }
        }
        
        // PATTERN 3: Migrate map groups
        $map_patterns = ['/^map_\d+_([a-f0-9]+)_/', '/^map_([a-f0-9]+)_/'];
        $map_groups = $this->find_groups_by_pattern($all_meta, $map_patterns);
        
        foreach ($map_groups as $group_base => $fields) {
            if (in_array($group_base, $processed_groups)) {
                continue;
            }
            
            $processed_groups[] = $group_base;
            
            $address = $fields['address'] ?? '';
            $latitude = $fields['latitude'] ?? '';
            $longitude = $fields['longitude'] ?? '';
            
            if (!empty($address) || (!empty($latitude) && !empty($longitude))) {
                $timestamp = time();
                $unique_suffix = substr(uniqid(), -8);
                $new_base = "map_{$timestamp}_{$unique_suffix}";
                
                $new_address_key = $new_base . '_address';
                $new_latitude_key = $new_base . '_latitude';
                $new_longitude_key = $new_base . '_longitude';
                $new_preview_key = $new_base . '_preview';
                
                if (!isset($all_meta[$new_address_key])) {
                    update_post_meta($property_id, $new_address_key, $address);
                    update_post_meta($property_id, $new_latitude_key, $latitude);
                    update_post_meta($property_id, $new_longitude_key, $longitude);
                    update_post_meta($property_id, $new_preview_key, '');
                    $migrated_count += 4;
                    $stats['maps_migrated']++;
                    
                    $this->log("  - Migrated map group: {$group_base} -> {$new_base}");
                }
            }
        }
        
        // PATTERN 4: Migrate property documents groups
        $docs_patterns = ['/^property_docs_\d+_([a-f0-9]+)_/', '/^property_docs_([a-f0-9]+)_/'];
        $docs_groups = $this->find_groups_by_pattern($all_meta, $docs_patterns);
        
        foreach ($docs_groups as $group_base => $fields) {
            if (in_array($group_base, $processed_groups)) {
                continue;
            }
            
            $processed_groups[] = $group_base;
            
            $icons = $fields['icon'] ?? '';
            $labels = $fields['label'] ?? '';
            $urls = $fields['url'] ?? '';
            
            if (!empty($labels) && !empty($urls)) {
                // Build documents array
                $documents = [];
                $icon_array = is_array($icons) ? $icons : ($icons ? [$icons] : []);
                $label_array = is_array($labels) ? $labels : ($labels ? [$labels] : []);
                $url_array = is_array($urls) ? $urls : ($urls ? [$urls] : []);
                
                $count = max(count($label_array), count($url_array));
                
                for ($i = 0; $i < $count; $i++) {
                    $label = $label_array[$i] ?? '';
                    $url = $url_array[$i] ?? '';
                    $icon = $icon_array[$i] ?? 'file-pdf';
                    
                    if (!empty($label) && !empty($url)) {
                        $documents[] = [
                            'icon' => $icon,
                            'label' => $label,
                            'url' => $url,
                            'url_type' => 'custom'
                        ];
                    }
                }
                
                if (!empty($documents)) {
                    $timestamp = time();
                    $unique_suffix = substr(uniqid(), -8);
                    $new_base = "property_docs_{$timestamp}_{$unique_suffix}";
                    
                    $documents_json = wp_json_encode($documents);
                    $json_field = $new_base . '_documents';
                    
                    if (!isset($all_meta[$json_field])) {
                        update_post_meta($property_id, $json_field, $documents_json);
                        $migrated_count++;
                        $stats['documents_migrated']++;
                        
                        $this->log("  - Migrated documents group: {$group_base} -> {$new_base} ({$count} documents)");
                    }
                }
            }
        }
        
        return $migrated_count;
    }

    /**
     * Find groups by meta key patterns.
     *
     * @since 2.2.2
     * @param array $all_meta All post meta.
     * @param array $patterns Regex patterns.
     * @return array Found groups.
     */
    private function find_groups_by_pattern(array $all_meta, array $patterns): array {
        $groups = [];
        
        foreach ($all_meta as $meta_key => $meta_value) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $meta_key, $matches)) {
                    $group_base = $matches[0];
                    $field_type = $this->get_field_type_from_key($meta_key);
                    
                    if (!isset($groups[$group_base])) {
                        $groups[$group_base] = [];
                    }
                    
                    $value = is_array($meta_value) ? ($meta_value[0] ?? '') : $meta_value;
                    $groups[$group_base][$field_type] = $value;
                    
                    break;
                }
            }
        }
        
        return $groups;
    }

    /**
     * Get field type from meta key.
     *
     * @since 2.2.2
     * @param string $meta_key Meta key.
     * @return string Field type.
     */
    private function get_field_type_from_key(string $meta_key): string {
        if (strpos($meta_key, '_title') !== false) {
            return 'title';
        }
        if (strpos($meta_key, '_url') !== false && strpos($meta_key, '_thumbnail') === false) {
            return 'url';
        }
        if (strpos($meta_key, '_thumbnail') !== false) {
            return 'thumbnail';
        }
        if (strpos($meta_key, '_images') !== false) {
            return 'images';
        }
        if (strpos($meta_key, '_address') !== false) {
            return 'address';
        }
        if (strpos($meta_key, '_latitude') !== false) {
            return 'latitude';
        }
        if (strpos($meta_key, '_longitude') !== false) {
            return 'longitude';
        }
        if (strpos($meta_key, '_preview') !== false) {
            return 'preview';
        }
        if (strpos($meta_key, '_icon') !== false) {
            return 'icon';
        }
        if (strpos($meta_key, '_label') !== false) {
            return 'label';
        }
        if (strpos($meta_key, '_documents') !== false) {
            return 'documents';
        }
        
        return 'unknown';
    }

    /**
     * Normalize price field to JSON format.
     *
     * @since 2.2.2
     * @param int $property_id Property ID.
     */
    private function normalize_price_field(int $property_id): void {
        $price_value = get_post_meta($property_id, '_hvnly_property_price', true);
        
        if (empty($price_value)) {
            return;
        }
        
        // Check if already JSON format
        if (is_string($price_value) && strlen($price_value) > 0 && $price_value[0] === '{') {
            return;
        }
        
        // Check if it's a price on call value
        $price_on_call_values = [
            'priceOnCall', 'fixedPrice', 'guidePrice', 'offersOver', 'priceOnCallNone'
        ];
        
        if (in_array($price_value, $price_on_call_values)) {
            $json_value = json_encode([
                '__type' => 'custom_label',
                'value' => $price_value,
                'label' => $this->get_price_label($price_value)
            ]);
            update_post_meta($property_id, '_hvnly_property_price', $json_value);
            $this->log("  - Normalized price field: {$price_value} -> JSON format");
        } elseif (is_numeric($price_value)) {
            // Already numeric, keep as is
            return;
        }
    }

    /**
     * Get price label from value.
     *
     * @since 2.2.2
     * @param string $value Price value.
     * @return string Label.
     */
    private function get_price_label(string $value): string {
        $labels = [
            'priceOnCall' => __('Price on Call', 'havenlytics'),
            'fixedPrice' => __('Fixed Price', 'havenlytics'),
            'guidePrice' => __('Guide Price', 'havenlytics'),
            'offersOver' => __('Offers Over', 'havenlytics'),
            'priceOnCallNone' => __('Price on Request', 'havenlytics'),
        ];
        
        return $labels[$value] ?? ucwords(str_replace(['_', '-'], ' ', $value));
    }

    /**
     * Ensure property has a unique ID.
     *
     * @since 2.2.2
     * @param int $property_id Property ID.
     */
    private function ensure_property_id(int $property_id): void {
        $property_id_value = get_post_meta($property_id, '_hvnly_unique_property_id', true);
        
        if (empty($property_id_value)) {
            if (function_exists('hvnlyMain') && method_exists(hvnlyMain(), 'Helper')) {
                $property_id_value = hvnlyMain()->Helper->generate_property_id($property_id);
                $this->log("  - Generated property ID: {$property_id_value}");
            }
        }
    }

    /**
     * Create unified meta index for faster lookups.
     *
     * @since 2.2.2
     */
    private function create_unified_meta_index(): void {
        global $wpdb;
        
        $index = [
            'price_field' => '_hvnly_property_price',
            'bedrooms_field' => '_hvnly_property_bedrooms',
            'bathrooms_field' => '_hvnly_property_bathrooms',
            'reception_rooms_field' => '_hvnly_property_reception_rooms',
            'sqft_field' => '_hvnly_property_sqft',
            'year_built_field' => '_hvnly_property_year_built',
            'property_id_field' => '_hvnly_unique_property_id',
            'gallery_field' => '_hvnly_property_gallery_images',
            'video_url_field' => '_hvnly_property_youtube_video_url',
            'map_lat_field' => '_hvnly_property_location_Latitude',
            'map_lng_field' => '_hvnly_property_location_Longitude',
            'map_address_field' => '_hvnly_property_map_location_address',
        ];
        
        update_option('hvnly_unified_meta_index', $index);
        $this->log('Unified meta index created');
    }

    /**
     * Clean up orphaned meta data.
     *
     * @since 2.2.2
     * @return int Number of entries cleaned.
     */
    private function cleanup_orphaned_meta(): int {
        // Non-destructive policy: never delete group meta keys automatically.
        // Orphan cleanup caused data loss when builder IDs changed after migration.
        $this->log(
            'Orphan meta cleanup skipped (preserves all video/gallery/map/documents meta keys)',
            'info',
            '2.2.2'
        );
        return 0;
    }

    /**
     * Get current field names from builder.
     *
     * @since 2.2.2
     * @return array
     */
    private function get_current_field_names(): array {
        $sections = get_option(self::PROPERTY_BUILDER_KEY, []);
        $field_names = [];
        
        foreach ($sections as $section) {
            if (empty($section['fields'])) {
                continue;
            }
            
            foreach ($section['fields'] as $field) {
                if (!empty($field['name'])) {
                    $field_names[] = $field['name'];
                }
                if (!empty($field['id'])) {
                    $field_names[] = $field['id'];
                }
            }
        }
        
        // Add system fields
        $system_fields = [
            '_hvnly_unique_property_id',
            '_hvnly_property_featured',
            '_hvnly_property_views',
            '_thumbnail_id',
            '_edit_lock',
            '_edit_last',
        ];
        
        return array_unique(array_merge($field_names, $system_fields));
    }

    /**
     * Check if key is a system key.
     *
     * @since 2.2.2
     * @param string $key Meta key.
     * @return bool
     */
    private function is_system_key(string $key): bool {
        $system_patterns = [
            '_wp_',
            '_edit_',
            '_encloseme',
            '_pingme',
            '_oembed_',
            '_thumbnail_id',
            '_hvnly_importing',
        ];
        
        foreach ($system_patterns as $pattern) {
            if (strpos($key, $pattern) === 0) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get default normalized configuration.
     *
     * @since 2.2.2
     * @return array
     */
    private function get_default_normalized_configuration(): array {
        $timestamp = time();
        $unique_suffix = substr(uniqid(), -8);
        
        return [
            'sec_basic_info' => [
                'id' => 'sec_basic_info',
                'title' => 'Basic Info',
                'icon' => 'fas fa-home',
                'required' => true,
                'order' => 0,
                'collapsed' => false,
                'fields' => [
                    [
                        'id' => '_hvnly_property_price',
                        'name' => '_hvnly_property_price',
                        'type' => 'price_label',
                        'label' => 'Property Price',
                        'placeholder' => 'Enter property price',
                        'required' => true,
                        'locked' => true,
                        'order' => 0,
                    ],
                    [
                        'id' => '_hvnly_property_bedrooms',
                        'name' => '_hvnly_property_bedrooms',
                        'type' => 'number',
                        'label' => 'Bedrooms',
                        'placeholder' => 'Enter number of bedrooms',
                        'required' => true,
                        'locked' => true,
                        'order' => 1,
                    ],
                    [
                        'id' => '_hvnly_property_bathrooms',
                        'name' => '_hvnly_property_bathrooms',
                        'type' => 'number',
                        'label' => 'Bathrooms',
                        'placeholder' => 'Enter number of bathrooms',
                        'required' => true,
                        'locked' => true,
                        'order' => 2,
                    ],
                    [
                        'id' => '_hvnly_property_reception_rooms',
                        'name' => '_hvnly_property_reception_rooms',
                        'type' => 'number',
                        'label' => 'Reception Rooms',
                        'placeholder' => 'Enter number of reception rooms',
                        'required' => true,
                        'locked' => true,
                        'order' => 3,
                    ],
                    [
                        'id' => '_hvnly_property_sqft',
                        'name' => '_hvnly_property_sqft',
                        'type' => 'number',
                        'label' => 'Square Feet',
                        'placeholder' => 'Enter property square footage',
                        'required' => false,
                        'locked' => false,
                        'order' => 4,
                    ],
                    [
                        'id' => '_hvnly_property_year_built',
                        'name' => '_hvnly_property_year_built',
                        'type' => 'number',
                        'label' => 'Year Built',
                        'placeholder' => 'Enter year built',
                        'required' => false,
                        'locked' => false,
                        'order' => 5,
                    ],
                ],
            ],
        ];
    }

    /**
     * Rollback the migration.
     *
     * @since 2.2.2
     * @return bool
     */
    public function down(): bool {
        $this->log('Rolling back migration to version ' . $this->get_version());
        
        // Find most recent backup
        $backup_id = $this->find_latest_backup();
        
        if ($backup_id) {
            $restored = $this->restore_from_backup($backup_id);
            if ($restored) {
                $this->log('Successfully rolled back from backup: ' . $backup_id);
                delete_option('hvnly_migration_2.2.2_completed');
                delete_option('hvnly_migration_2.2.2_stats');
                delete_option('hvnly_unified_meta_index');
                return true;
            }
        }
        
        $this->log('No backup found for rollback');
        return false;
    }

    /**
     * Find the latest backup for this migration version.
     *
     * @since 2.2.2
     * @return string|false
     */
    private function find_latest_backup() {
        global $wpdb;
        
        $backup_pattern = '_hvnly_backup_2.2.2_%';
        
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
}