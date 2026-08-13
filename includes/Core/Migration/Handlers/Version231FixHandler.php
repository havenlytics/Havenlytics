<?php
/**
 * Version 2.3.1 - Configuration Fix Handler
 *
 * Cleans up duplicate group_base_ids and ensures consistent configuration
 *
 * @package     Havenlytics
 * @subpackage  Core\Migration\Handlers
 * @since       2.3.1
 */

namespace HvnlyNab\Core\Migration\Handlers;

use HvnlyNab\Core\DataPreservation\BatchProcessor;
use HvnlyNab\Core\Migration\Interfaces\MigrationInterface;
use HvnlyNab\Core\Migration\Traits\MigrationTrait;

defined('ABSPATH') || exit;

class Version231FixHandler implements MigrationInterface {

    use MigrationTrait;

    const PROPERTY_BUILDER_KEY = 'hvnly_property_builder.sections';

    const BATCH_KEY   = 'hvnly_migration_231_meta_offset';
    const CONFIG_DONE = 'hvnly_migration_231_config_done';
    const SYNCED_KEY  = 'hvnly_migration_231_synced_count';

    public function get_version(): string {
        return '2.3.1';
    }

    public function get_description(): string {
        return 'Fixes duplicate group_base_ids and ensures consistent configuration across builder and import';
    }

    /**
     * Generate a UNIQUE base ID (same pattern as DnDSections)
     */
    private function generate_unique_base_id( $group_type ) {
        $microtime    = microtime(true);
        $timestamp    = (int) $microtime;
        $micro_suffix = substr(str_replace('.', '', (string) $microtime), -6);
        $random       = wp_rand(10000, 99999);
        $unique_id    = uniqid();

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
    private function generate_unique_group_id( $group_type ) {
        $timestamp = time();
        $short_id  = substr(uniqid(), -8);
        $random    = wp_rand(1000, 9999);
        return "grp_{$group_type}_{$timestamp}_{$short_id}_{$random}";
    }

    /**
     * Check if configuration is valid
     */
    private function validate_config( $config ): array {
        $errors         = array();
        $group_base_map = array();

        foreach ($config as $section_key => $section) {
            $fields = $section['fields'] ?? array();

            foreach ($fields as $field) {
                $group_type    = $field['group_type'] ?? '';
                $group_base_id = $field['group_base_id'] ?? '';

                // Check for duplicate group_base_ids
                if ( ! empty($group_base_id)) {
                    if (isset($group_base_map[ $group_base_id ])) {
                        $errors[] = "Duplicate group_base_id: {$group_base_id}";
                    }
                    $group_base_map[ $group_base_id ] = true;
                }

                // Check map field address_field_name consistency
                if ($group_type === 'map' && isset($field['address_field_name'])) {
                    if ( ! empty($group_base_id) && strpos($field['address_field_name'], $group_base_id) !== 0) {
                        $errors[] = "Map address_field_name mismatch: {$field['address_field_name']} vs base: {$group_base_id}";
                    }
                }
            }
        }

        return array(
            'is_valid' => empty($errors),
            'errors' => $errors,
            'error_count' => count($errors),
        );
    }

    public function is_needed(): bool {
        $config = get_option(self::PROPERTY_BUILDER_KEY, array());

        if (empty($config)) {
            return true;
        }

        if ($this->has_duplicate_group_base_ids($config)) {
            return true;
        }

        $validation = $this->validate_config($config);

        return ! $validation['is_valid'];
    }

    public function up(): bool {
        $this->log('Starting configuration fix migration 2.3.1', 'info', '2.3.1');
        $this->log('In-place duplicate fix; preserving existing group_base_ids and property meta', 'info', '2.3.1');

        if ( ! get_option( self::CONFIG_DONE, false ) ) {
            $this->backup_option( self::PROPERTY_BUILDER_KEY, '2.3.1' );

            $current_config = get_option( self::PROPERTY_BUILDER_KEY, array() );

            if ( empty( $current_config ) ) {
                $this->log( 'Configuration empty, seeding unified defaults', 'info', '2.3.1' );
                $fixed_config = $this->build_fresh_configuration();
            } else {
                $validation = $this->validate_config( $current_config );

                if ( ! $validation['is_valid'] ) {
                    $this->log( 'Found issues: ' . implode( ', ', $validation['errors'] ), 'warning', '2.3.1' );
                }

                $fixed_config = $this->fix_configuration( $current_config );
            }

            $this->safe_update_option( self::PROPERTY_BUILDER_KEY, $fixed_config );
            wp_cache_delete( self::PROPERTY_BUILDER_KEY, 'options' );
            update_option( self::CONFIG_DONE, 1, false );

            $this->log( 'Builder configuration updated (additive / in-place only)', 'info', '2.3.1' );
        }

        $this->log( 'Syncing property meta (add missing keys only, batched)', 'info', '2.3.1' );

        $synced_count = (int) get_option( self::SYNCED_KEY, 0 );

        $result = BatchProcessor::each_property(
            function ( int $property_id ) use ( &$synced_count ) {
                $synced_count += $this->sync_property_meta( $property_id );
            },
            self::BATCH_KEY
        );

        update_option( self::SYNCED_KEY, $synced_count, false );

        $this->log(
            sprintf(
                '231 batch progress: %d properties synced so far (%d/%d)',
                $synced_count,
                $result['offset'],
                $result['total']
            ),
            'info',
            '2.3.1'
        );

        if ( ! $result['complete'] ) {
            return false;
        }

        delete_option( self::CONFIG_DONE );
        delete_option( self::SYNCED_KEY );

        $this->log( "Synced {$synced_count} meta fields across all properties", 'info', '2.3.1' );

        return true;
    }

    /**
     * Build fresh configuration using stable master IDs when available.
     */
    private function build_fresh_configuration(): array {
        $unified = $this->get_unified_builder_config();
        if ($unified !== null) {
            $this->log('Using UnifiedFieldGenerator configuration', 'info', '2.3.1');
            return $unified;
        }

        $video_base   = $this->generate_unique_base_id('video');
        $gallery_base = $this->generate_unique_base_id('gallery');
        $map_base     = $this->generate_unique_base_id('map');
        $docs_base    = $this->generate_unique_base_id('property_docs');

        $basic_info_id           = 'sec_basic_info';
        $additional_info_id      = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $address_neighborhood_id = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $video_section_id        = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $gallery_section_id      = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $location_section_id     = 'hvnly__dyamic_metabox_tab__' . uniqid();
        $documents_section_id    = 'hvnly__dyamic_metabox_tab__' . uniqid();

        return array(
            $basic_info_id => array(
                'id' => $basic_info_id,
                'title' => 'Basic Info',
                'icon' => 'fas fa-home',
                'required' => true,
                'order' => 0,
                'collapsed' => false,
                'fields' => $this->get_basic_info_fields(),
            ),
            $additional_info_id => array(
                'id' => $additional_info_id,
                'title' => 'Additional Information',
                'icon' => 'fas fa-info-circle',
                'required' => false,
                'order' => 1,
                'collapsed' => false,
                'fields' => $this->get_additional_info_fields(),
            ),
            $address_neighborhood_id => array(
                'id' => $address_neighborhood_id,
                'title' => 'Address & Neighborhood',
                'icon' => 'fas fa-building',
                'required' => false,
                'order' => 2,
                'collapsed' => false,
                'fields' => $this->get_address_neighborhood_fields(),
            ),
            $video_section_id => array(
                'id' => $video_section_id,
                'title' => 'Property Video',
                'icon' => 'fas fa-video',
                'required' => false,
                'order' => 3,
                'collapsed' => false,
                'fields' => $this->create_video_group_fields($video_base),
            ),
            $gallery_section_id => array(
                'id' => $gallery_section_id,
                'title' => 'Property Gallery',
                'icon' => 'fas fa-images',
                'required' => false,
                'order' => 4,
                'collapsed' => false,
                'fields' => $this->create_gallery_group_fields($gallery_base),
            ),
            $location_section_id => array(
                'id' => $location_section_id,
                'title' => 'Property Location',
                'icon' => 'fas fa-map-marker-alt',
                'required' => false,
                'order' => 5,
                'collapsed' => false,
                'fields' => $this->create_map_group_fields($map_base),
            ),
            $documents_section_id => array(
                'id' => $documents_section_id,
                'title' => 'Property Documents',
                'icon' => 'fas fa-file-pdf',
                'required' => false,
                'order' => 6,
                'collapsed' => false,
                'fields' => $this->create_documents_group_fields($docs_base),
            ),
        );
    }

    /**
     * Fix existing configuration in-place (never replace standard sections wholesale).
     */
    private function fix_configuration( $config ): array {
        $config = $this->fix_duplicate_group_base_ids_in_config($config);
        $config = $this->fix_map_field_consistency($config);
        $config = $this->merge_missing_standard_sections($config);

        uasort(
            $config,
            static function ( $a, $b ) {
                return ( $a['order'] ?? 999 ) - ( $b['order'] ?? 999 );
            }
        );

        return $config;
    }

    /**
     * Align map sub-field names with group_base_id without changing base IDs.
     *
     * @param array $config Builder sections.
     * @return array
     */
    private function fix_map_field_consistency( array $config ): array {
        foreach ($config as $section_key => &$section) {
            if (empty($section['fields']) || ! is_array($section['fields'])) {
                continue;
            }
            foreach ($section['fields'] as $field_index => &$field) {
                if (( $field['group_type'] ?? '' ) !== 'map') {
                    continue;
                }
                $base_id = $field['group_base_id'] ?? '';
                if ($base_id === '') {
                    continue;
                }
                $expected_address = $base_id . '_address';
                if (( $field['address_field_name'] ?? '' ) !== $expected_address) {
                    $field['address_field_name']       = $expected_address;
                    $field['lat_field_name']           = $base_id . '_latitude';
                    $field['lng_field_name']           = $base_id . '_longitude';
                    $field['map_field_name']           = $base_id . '_preview';
                    $section['fields'][ $field_index ] = $field;
                    $this->log(
                        'Fixed map sub-field names for base ' . $base_id . ' in section ' . ( $section['title'] ?? $section_key ),
                        'info',
                        '2.3.1'
                    );
                }
            }
        }
        unset($section, $field);

        return $config;
    }

    /**
     * Regenerate group IDs for a section
     */
    private function regenerate_section_group_ids( $section ): array {
        if (empty($section['fields'])) {
            return $section;
        }

        $new_fields    = array();
        $group_mapping = array();

        foreach ($section['fields'] as $field) {
            $group_type        = $field['group_type'] ?? '';
            $original_group_id = $field['group_id'] ?? '';
            $meta_key          = $field['metaKey'] ?? '';

            if ( ! empty($group_type) && ! empty($original_group_id)) {
                if ( ! isset($group_mapping[ $original_group_id ])) {
                    $new_group_id                        = $this->generate_unique_group_id($group_type);
                    $new_group_base_id                   = $this->generate_unique_base_id($group_type);
                    $group_mapping[ $original_group_id ] = array(
                        'group_id' => $new_group_id,
                        'group_base_id' => $new_group_base_id,
                    );
                }

                $mapping = $group_mapping[ $original_group_id ];

                $new_field                  = $field;
                $new_field['group_id']      = $mapping['group_id'];
                $new_field['group_base_id'] = $mapping['group_base_id'];
                $new_field['id']            = $mapping['group_base_id'] . '_' . $meta_key;
                $new_field['name']          = $mapping['group_base_id'] . '_' . $meta_key;

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
                $new_fields[] = $field;
            }
        }

        $section['fields'] = $new_fields;
        return $section;
    }

    private function get_basic_info_fields(): array {
        return array(
            array(
				'id' => '_hvnly_property_price',
				'name' => '_hvnly_property_price',
				'type' => 'price_label',
				'label' => 'Property Price',
				'placeholder' => 'Enter property price',
				'required' => true,
				'locked' => true,
				'order' => 0,
			),
            array(
				'id' => '_hvnly_property_reception_rooms',
				'name' => '_hvnly_property_reception_rooms',
				'type' => 'number',
				'label' => 'Property Reception Rooms',
				'placeholder' => 'Enter reception rooms',
				'required' => true,
				'locked' => true,
				'order' => 1,
			),
            array(
				'id' => '_hvnly_property_bedrooms',
				'name' => '_hvnly_property_bedrooms',
				'type' => 'number',
				'label' => 'Property Bedrooms',
				'placeholder' => 'Enter property bedrooms',
				'required' => true,
				'locked' => true,
				'order' => 2,
			),
            array(
				'id' => '_hvnly_property_bathrooms',
				'name' => '_hvnly_property_bathrooms',
				'type' => 'number',
				'label' => 'Property Bathrooms',
				'placeholder' => 'Enter property bathrooms',
				'required' => true,
				'locked' => true,
				'order' => 3,
			),
            array(
				'id' => '_hvnly_property_garage_sqft',
				'name' => '_hvnly_property_garage_sqft',
				'type' => 'number',
				'label' => 'Property Garage Square Footage',
				'placeholder' => 'Enter property garage square footage',
				'required' => true,
				'locked' => true,
				'order' => 4,
			),
        );
    }

    private function get_additional_info_fields(): array {
        return array(
            array(
				'id' => '_hvnly_property_sqft',
				'name' => '_hvnly_property_sqft',
				'type' => 'number',
				'label' => 'Property Area, sq ft',
				'placeholder' => 'Enter property area in square feet',
				'required' => true,
				'locked' => false,
				'order' => 0,
			),
            array(
				'id' => '_hvnly_property_lot_size',
				'name' => '_hvnly_property_lot_size',
				'type' => 'text',
				'label' => 'Property Lot size, sq ft',
				'placeholder' => 'Enter property lot size',
				'required' => true,
				'locked' => false,
				'order' => 1,
			),
        );
    }

    private function get_address_neighborhood_fields(): array {
        return array(
            array(
				'id' => '_hvnly_property_reference_number',
				'name' => '_hvnly_property_reference_number',
				'type' => 'text',
				'label' => 'Property Reference Number',
				'placeholder' => 'Enter property reference number',
				'required' => false,
				'locked' => false,
				'order' => 0,
			),
            array(
				'id' => '_hvnly_property_address_line_1',
				'name' => '_hvnly_property_address_line_1',
				'type' => 'text',
				'label' => 'Property Address Line 1',
				'placeholder' => 'Enter property address line 1',
				'required' => false,
				'locked' => false,
				'order' => 1,
			),
            array(
				'id' => '_hvnly_property_town_city',
				'name' => '_hvnly_property_town_city',
				'type' => 'text',
				'label' => 'Property Town/City',
				'placeholder' => 'Enter property town/city',
				'required' => false,
				'locked' => false,
				'order' => 2,
			),
            array(
				'id' => '_hvnly_property_zip_code',
				'name' => '_hvnly_property_zip_code',
				'type' => 'text',
				'label' => 'Property Zip Code',
				'placeholder' => 'Enter property zip code',
				'required' => false,
				'locked' => false,
				'order' => 3,
			),
        );
    }

    private function create_video_group_fields( $group_base_id ): array {
        $group_id = $this->generate_unique_group_id('video');
        return array(
            array(
				'id' => $group_base_id . '_title',
				'name' => $group_base_id . '_title',
				'type' => 'text',
				'label' => 'Video Title',
				'placeholder' => 'Enter video title',
				'required' => false,
				'locked' => false,
				'order' => 0,
				'group_id' => $group_id,
				'group_type' => 'video',
				'group_name' => 'Video Information',
				'group_position' => 0,
				'group_total' => 3,
				'group_base_id' => $group_base_id,
				'metaKey' => 'title',
			),
            array(
				'id' => $group_base_id . '_url',
				'name' => $group_base_id . '_url',
				'type' => 'video',
				'label' => 'Video URL',
				'placeholder' => 'Enter video URL',
				'required' => false,
				'locked' => false,
				'order' => 1,
				'group_id' => $group_id,
				'group_type' => 'video',
				'group_name' => 'Video Information',
				'group_position' => 1,
				'group_total' => 3,
				'group_base_id' => $group_base_id,
				'metaKey' => 'url',
			),
            array(
				'id' => $group_base_id . '_thumbnail',
				'name' => $group_base_id . '_thumbnail',
				'type' => 'file',
				'label' => 'Video Thumbnail',
				'placeholder' => 'Upload video thumbnail',
				'required' => false,
				'locked' => false,
				'order' => 2,
				'group_id' => $group_id,
				'group_type' => 'video',
				'group_name' => 'Video Information',
				'group_position' => 2,
				'group_total' => 3,
				'group_base_id' => $group_base_id,
				'metaKey' => 'thumbnail',
				'fileType' => 'image',
			),
        );
    }

    private function create_gallery_group_fields( $group_base_id ): array {
        $group_id = $this->generate_unique_group_id('gallery');
        return array(
            array(
				'id' => $group_base_id . '_title',
				'name' => $group_base_id . '_title',
				'type' => 'text',
				'label' => 'Gallery Title',
				'placeholder' => 'Enter gallery title',
				'required' => false,
				'locked' => false,
				'order' => 0,
				'group_id' => $group_id,
				'group_type' => 'gallery',
				'group_name' => 'Property Gallery',
				'group_position' => 0,
				'group_total' => 2,
				'group_base_id' => $group_base_id,
				'metaKey' => 'title',
			),
            array(
				'id' => $group_base_id . '_images',
				'name' => $group_base_id . '_images',
				'type' => 'gallery',
				'label' => 'Gallery Images',
				'placeholder' => 'Upload gallery images',
				'required' => false,
				'locked' => false,
				'order' => 1,
				'group_id' => $group_id,
				'group_type' => 'gallery',
				'group_name' => 'Property Gallery',
				'group_position' => 1,
				'group_total' => 2,
				'group_base_id' => $group_base_id,
				'metaKey' => 'images',
			),
        );
    }

    private function create_map_group_fields( $group_base_id ): array {
        $group_id = $this->generate_unique_group_id('map');
        return array(
            array(
				'id' => $group_base_id . '_address',
				'name' => $group_base_id . '_address',
				'type' => 'text',
				'label' => 'Property Map Address',
				'placeholder' => 'Enter property map address',
				'required' => true,
				'locked' => false,
				'order' => 0,
				'group_id' => $group_id,
				'group_type' => 'map',
				'group_name' => 'Map Location',
				'group_position' => 0,
				'group_total' => 4,
				'group_base_id' => $group_base_id,
				'metaKey' => 'address',
			),
            array(
				'id' => $group_base_id . '_latitude',
				'name' => $group_base_id . '_latitude',
				'type' => 'text',
				'label' => 'Property Latitude',
				'placeholder' => 'Enter property latitude',
				'required' => false,
				'locked' => false,
				'order' => 1,
				'group_id' => $group_id,
				'group_type' => 'map',
				'group_name' => 'Map Location',
				'group_position' => 1,
				'group_total' => 4,
				'group_base_id' => $group_base_id,
				'metaKey' => 'latitude',
				'hidden' => true,
			),
            array(
				'id' => $group_base_id . '_longitude',
				'name' => $group_base_id . '_longitude',
				'type' => 'text',
				'label' => 'Property Longitude',
				'placeholder' => 'Enter property longitude',
				'required' => false,
				'locked' => false,
				'order' => 2,
				'group_id' => $group_id,
				'group_type' => 'map',
				'group_name' => 'Map Location',
				'group_position' => 2,
				'group_total' => 4,
				'group_base_id' => $group_base_id,
				'metaKey' => 'longitude',
				'hidden' => true,
			),
            array(
				'id' => $group_base_id . '_preview',
				'name' => $group_base_id . '_preview',
				'type' => 'map',
				'label' => 'Map Preview',
				'placeholder' => '',
				'required' => false,
				'locked' => false,
				'order' => 3,
				'group_id' => $group_id,
				'group_type' => 'map',
				'group_name' => 'Map Location',
				'group_position' => 3,
				'group_total' => 4,
				'group_base_id' => $group_base_id,
				'metaKey' => 'preview',
				'address_field_name' => $group_base_id . '_address',
				'lat_field_name' => $group_base_id . '_latitude',
				'lng_field_name' => $group_base_id . '_longitude',
				'map_field_name' => $group_base_id . '_preview',
			),
        );
    }

	/**
	 * Sync meta keys for a single property (add missing keys only).
	 *
	 * @param int $property_id Property post ID.
	 * @return int Number of keys synced.
	 */
	private function sync_property_meta( int $property_id ): int {
		$unified      = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
		$standardized = $unified->get_all_standardized_field_names();

		if ( empty( $standardized ) ) {
			return 0;
		}

		$all_meta     = get_post_meta( $property_id );
		$synced_count = 0;

		foreach ( $standardized as $group_type => $fields ) {
			foreach ( $fields as $meta_key => $target_field_name ) {
				if ( isset( $all_meta[ $target_field_name ] ) ) {
					continue;
				}

				$source_field_name = null;
				$pattern           = '/^' . preg_quote( $group_type, '/' ) . '_\d+_\d+_[a-zA-Z0-9]{13}_\d{5}_' . preg_quote( $meta_key, '/' ) . '$/';

				foreach ( array_keys( $all_meta ) as $existing_key ) {
					if ( preg_match( $pattern, $existing_key ) ) {
						$source_field_name = $existing_key;
						break;
					}
				}

				if ( $source_field_name && isset( $all_meta[ $source_field_name ] ) ) {
					$value = $all_meta[ $source_field_name ][0] ?? '';
					if ( $value !== '' && $value !== false && $value !== null ) {
						if ( $this->safe_add_post_meta( $property_id, $target_field_name, $value ) ) {
							++$synced_count;
							$this->log(
                            "Synced {$source_field_name} -> {$target_field_name} for property {$property_id}",
                            'info',
                            '2.3.1'
							);
						}
					}
				}
			}
		}

		return $synced_count;
	}

    private function create_documents_group_fields( $group_base_id ): array {
        $group_id = $this->generate_unique_group_id('property_docs');
        return array(
            array(
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
            ),
        );
    }

    public function down(): bool {
        $backup_id = $this->find_latest_backup();

        if ($backup_id) {
            return $this->restore_from_backup($backup_id);
        }

        return false;
    }

    private function find_latest_backup() {
        global $wpdb;

        $backup_pattern = '_hvnly_backup_2.3.1_%';

        $backups = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 ORDER BY option_id DESC LIMIT 1",
                $backup_pattern
            )
        );

        return ! empty($backups) ? $backups[0] : false;
    }
}
