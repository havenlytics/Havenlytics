<?php
/**
 * Havenlytics DefaultTabSectionsData
 *
 * @package HvnlyNab\Api\Type\Builders
 * @since 2.0.0
 */

namespace HvnlyNab\Api\Type\Builders;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class DefaultTabSectionsData
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate a UNIQUE group base ID for a specific group
     * Uses microtime + random to ensure uniqueness across multiple groups in same request
     *
     * @param string $group_type Group type
     * @return string Unique base ID
     */
    private static function generate_unique_group_base_id($group_type)
    {
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
     * Get default sections in DnDSections format with PROFESSIONAL IDs
     * Following pattern: sec_property_* for defaults, sec_custom_* for user tabs.
     */
    public static function get_default_sections_for_dnd()
    {
        $metabox_tabs = self::hvnly_metabox_tabs_builder();
        $dnd_sections = [];

        foreach ($metabox_tabs as $index => $tab) {
            // Use the existing professional ID from the tab
            $section_id = $tab['id'] ?? \HvnlyNab\Core\SectionIdentity::generate_custom_section_id();

            // Mark required tabs
            $is_required_tab = ($tab['hvnly__sectiontitle'] ?? '') === 'Basic Info';

            $dnd_sections[$section_id] = [
                'id' => $section_id,
                'title' => sanitize_text_field($tab['hvnly__sectiontitle'] ?? 'Untitled Section'),
                'icon' => sanitize_text_field($tab['icon'] ?? 'fas fa-home'),
                'order' => $index,
                'collapsed' => false,
                'required' => $is_required_tab,
                'fields' => self::convert_fields_to_dnd_format_with_groups($tab['fields'] ?? [], $is_required_tab)
            ];
        }

        return $dnd_sections;
    }

    /**
     * Convert metabox fields to DnDSections format with groups
     * GENERATES UNIQUE group_base_id for EVERY group (CRITICAL FIX)
     */
    private static function convert_fields_to_dnd_format_with_groups($fields, $is_required_tab = false)
    {
        if (!is_array($fields)) {
            return [];
        }

        $dnd_fields = [];
        $processed_groups = []; // Track processed groups by their original ID
        
        // First pass: Identify all groups and generate UNIQUE base IDs for EACH group
        $group_base_map = [];
        
        foreach ($fields as $field) {
            $group_id = $field['group_id'] ?? null;
            $group_type = $field['group_type'] ?? '';
            
            if ($group_id && !empty($group_type) && !isset($group_base_map[$group_id])) {
                // Generate a COMPLETELY UNIQUE group_base_id for THIS group
                // Using microtime + random ensures uniqueness even for multiple groups in same request
                $group_base_map[$group_id] = self::generate_unique_group_base_id($group_type);
            }
        }
        
        // Second pass: Process fields using the mapped base IDs
        foreach ($fields as $index => $field) {
            $field_id = $field['fieldid'] ?? 'fld_' . uniqid();
            $is_locked_field = $is_required_tab && $index < 3;
            $meta_key = $field['metaKey'] ?? '';
            $group_id = $field['group_id'] ?? null;
            $group_type = $field['group_type'] ?? '';
            
            // Check if this is a video group
            if ($group_id && $group_type === 'video') {
                $group_base_id = $group_base_map[$group_id] ?? self::generate_unique_group_base_id('video');
                $current_group_id = $group_id;
                
                $field_type = self::map_field_type($field['input_type'] ?? 'text');
                
                if (($field['metaKey'] ?? '') === 'url') {
                    $field_type = 'video';
                }
                
                $group_position = $field['group_position'] ?? 0;
                $group_total = $field['group_total'] ?? 3;
                
                $dnd_field = [
                    'id' => $group_base_id . '_' . $meta_key,
                    'name' => $group_base_id . '_' . $meta_key,
                    'type' => $field_type,
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'required' => !empty($field['is_required']),
                    'locked' => $is_locked_field,
                    'adminOnly' => false,
                    'enabled' => true,
                    'order' => $index,
                    'group_id' => $current_group_id,
                    'group_type' => 'video',
                    'group_name' => 'Video Information',
                    'group_position' => $group_position,
                    'group_total' => $group_total,
                    'group_base_id' => $group_base_id,
                    'metaKey' => $meta_key,
                    'hidden' => false,
                    'group_collapsed' => false
                ];
                
                $group_key = $current_group_id . '_' . $meta_key;
                if (!isset($processed_groups[$group_key])) {
                    $processed_groups[$group_key] = true;
                    $dnd_fields[] = $dnd_field;
                }
            } 
            // Check if this is a gallery group
            elseif ($group_id && $group_type === 'gallery') {
                $group_base_id = $group_base_map[$group_id] ?? self::generate_unique_group_base_id('gallery');
                $current_group_id = $group_id;
                $group_position = $field['group_position'] ?? 0;
                $group_total = $field['group_total'] ?? 2;
                
                $dnd_field = [
                    'id' => $group_base_id . '_' . $meta_key,
                    'name' => $group_base_id . '_' . $meta_key,
                    'type' => self::map_field_type($field['input_type'] ?? 'text'),
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'required' => !empty($field['is_required']),
                    'locked' => $is_locked_field,
                    'adminOnly' => false,
                    'enabled' => true,
                    'order' => $index,
                    'group_id' => $current_group_id,
                    'group_type' => 'gallery',
                    'group_name' => 'Property Gallery',
                    'group_position' => $group_position,
                    'group_total' => $group_total,
                    'group_base_id' => $group_base_id,
                    'metaKey' => $meta_key,
                    'hidden' => false,
                    'group_collapsed' => false
                ];
                
                $group_key = $current_group_id . '_' . $meta_key;
                if (!isset($processed_groups[$group_key])) {
                    $processed_groups[$group_key] = true;
                    $dnd_fields[] = $dnd_field;
                }
            } 
            // Check if this is a map group
            elseif ($group_id && $group_type === 'map') {
                $group_base_id = $group_base_map[$group_id] ?? self::generate_unique_group_base_id('map');
                $current_group_id = $group_id;
                $is_hidden_field = in_array($meta_key, ['latitude', 'longitude']);
                $group_position = $field['group_position'] ?? 0;
                $group_total = $field['group_total'] ?? 4;
                
                $dnd_field = [
                    'id' => $group_base_id . '_' . $meta_key,
                    'name' => $group_base_id . '_' . $meta_key,
                    'type' => self::map_field_type($field['input_type'] ?? 'text'),
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'required' => !empty($field['is_required']),
                    'locked' => $is_locked_field,
                    'adminOnly' => false,
                    'enabled' => true,
                    'order' => $index,
                    'group_id' => $current_group_id,
                    'group_type' => 'map',
                    'group_name' => 'Map Location',
                    'group_position' => $group_position,
                    'group_total' => $group_total,
                    'group_base_id' => $group_base_id,
                    'metaKey' => $meta_key,
                    'hidden' => $is_hidden_field,
                    'group_collapsed' => false
                ];
                
                if ($meta_key === 'preview') {
                    $dnd_field['address_field_name'] = $group_base_id . '_address';
                    $dnd_field['lat_field_name'] = $group_base_id . '_latitude';
                    $dnd_field['lng_field_name'] = $group_base_id . '_longitude';
                    $dnd_field['map_field_name'] = $group_base_id . '_preview';
                }
                
                $group_key = $current_group_id . '_' . $meta_key;
                if (!isset($processed_groups[$group_key])) {
                    $processed_groups[$group_key] = true;
                    $dnd_fields[] = $dnd_field;
                }
            }
            // Check if this is a property_docs group
            elseif ($group_id && $group_type === 'property_docs') {
                $group_base_id = $group_base_map[$group_id] ?? self::generate_unique_group_base_id('property_docs');
                $current_group_id = $group_id;
                $group_position = $field['group_position'] ?? 0;
                $group_total = $field['group_total'] ?? 3;
                
                $field_type = self::map_field_type($field['input_type'] ?? 'text');
                
                $dnd_field = [
                    'id' => $group_base_id . '_' . $meta_key,
                    'name' => $group_base_id . '_' . $meta_key,
                    'type' => $field_type,
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'required' => !empty($field['is_required']),
                    'locked' => $is_locked_field,
                    'adminOnly' => false,
                    'enabled' => true,
                    'order' => $index,
                    'group_id' => $current_group_id,
                    'group_type' => 'property_docs',
                    'group_name' => 'Property Documents',
                    'group_position' => $group_position,
                    'group_total' => $group_total,
                    'group_base_id' => $group_base_id,
                    'metaKey' => $meta_key,
                    'hidden' => false,
                    'group_collapsed' => false,
                    'show_in_sidebar' => isset($field['show_in_sidebar']) ? (bool)$field['show_in_sidebar'] : true
                ];
                
                $group_key = $current_group_id . '_' . $meta_key;
                if (!isset($processed_groups[$group_key])) {
                    $processed_groups[$group_key] = true;
                    $dnd_fields[] = $dnd_field;
                }
            }
            // Check if this is a faq group
            elseif ($group_id && $group_type === 'faq') {
                $group_base_id = $group_base_map[$group_id] ?? self::generate_unique_group_base_id('faq');
                $current_group_id = $group_id;
                $group_position = $field['group_position'] ?? 0;
                $group_total = $field['group_total'] ?? 2;
                $field_type = ($meta_key === 'faqs') ? 'faq' : self::map_field_type($field['input_type'] ?? 'text');

                $dnd_field = [
                    'id' => $group_base_id . '_' . $meta_key,
                    'name' => $group_base_id . '_' . $meta_key,
                    'type' => $field_type,
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'required' => !empty($field['is_required']),
                    'locked' => $is_locked_field,
                    'adminOnly' => false,
                    'enabled' => true,
                    'order' => $index,
                    'group_id' => $current_group_id,
                    'group_type' => 'faq',
                    'group_name' => 'Frequently Asked Questions',
                    'group_position' => $group_position,
                    'group_total' => $group_total,
                    'group_base_id' => $group_base_id,
                    'metaKey' => $meta_key,
                    'hidden' => false,
                    'group_collapsed' => false,
                    'show_in_sidebar' => true,
                ];

                $group_key = $current_group_id . '_' . $meta_key;
                if (!isset($processed_groups[$group_key])) {
                    $processed_groups[$group_key] = true;
                    $dnd_fields[] = $dnd_field;
                }
            }
            // Check if this is a repeater group
            elseif ($group_id && $group_type === 'repeater') {
                $group_base_id = $group_base_map[$group_id] ?? self::generate_unique_group_base_id('repeater');
                $current_group_id = $group_id;
                $group_position = $field['group_position'] ?? 0;
                $group_total = $field['group_total'] ?? 2;
                $field_type = ($meta_key === 'items') ? 'repeater' : self::map_field_type($field['input_type'] ?? 'text');

                $dnd_field = [
                    'id' => $group_base_id . '_' . $meta_key,
                    'name' => $group_base_id . '_' . $meta_key,
                    'type' => $field_type,
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'required' => !empty($field['is_required']),
                    'locked' => $is_locked_field,
                    'adminOnly' => false,
                    'enabled' => true,
                    'order' => $index,
                    'group_id' => $current_group_id,
                    'group_type' => 'repeater',
                    'group_name' => 'Property Highlights',
                    'group_position' => $group_position,
                    'group_total' => $group_total,
                    'group_base_id' => $group_base_id,
                    'metaKey' => $meta_key,
                    'hidden' => false,
                    'group_collapsed' => false,
                    'show_in_sidebar' => true,
                ];

                $group_key = $current_group_id . '_' . $meta_key;
                if (!isset($processed_groups[$group_key])) {
                    $processed_groups[$group_key] = true;
                    $dnd_fields[] = $dnd_field;
                }
            }
            // Check if this is an agents group
            elseif ($group_id && $group_type === 'agents') {
                $group_base_id = $group_base_map[$group_id] ?? self::generate_unique_group_base_id('agents');
                $current_group_id = $group_id;
                $group_position = $field['group_position'] ?? 0;
                $group_total = $field['group_total'] ?? 2;
                $field_type = ($meta_key === 'agents') ? 'agents' : self::map_field_type($field['input_type'] ?? 'text');

                $dnd_field = [
                    'id' => $group_base_id . '_' . $meta_key,
                    'name' => $group_base_id . '_' . $meta_key,
                    'type' => $field_type,
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'required' => !empty($field['is_required']),
                    'locked' => $is_locked_field,
                    'adminOnly' => false,
                    'enabled' => true,
                    'order' => $index,
                    'group_id' => $current_group_id,
                    'group_type' => 'agents',
                    'group_name' => 'Listing Agents',
                    'group_position' => $group_position,
                    'group_total' => $group_total,
                    'group_base_id' => $group_base_id,
                    'metaKey' => $meta_key,
                    'hidden' => false,
                    'group_collapsed' => false,
                    'show_in_sidebar' => true,
                ];

                $group_key = $current_group_id . '_' . $meta_key;
                if (!isset($processed_groups[$group_key])) {
                    $processed_groups[$group_key] = true;
                    $dnd_fields[] = $dnd_field;
                }
            }
            // Check if this is a features group (checkbox list)
            elseif ($group_id && $group_type === 'features') {
                $group_base_id = $group_base_map[$group_id] ?? self::generate_unique_group_base_id('features');
                $current_group_id = $group_id;
                $group_position = $field['group_position'] ?? 0;
                $group_total = $field['group_total'] ?? 1;

                $dnd_field = [
                    'id' => $group_base_id . '_' . $meta_key,
                    'name' => $group_base_id . '_' . $meta_key,
                    'type' => 'checkbox',
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'required' => !empty($field['is_required']),
                    'locked' => $is_locked_field,
                    'adminOnly' => false,
                    'enabled' => true,
                    'order' => $index,
                    'group_id' => $current_group_id,
                    'group_type' => 'features',
                    'group_name' => 'Property Features',
                    'group_position' => $group_position,
                    'group_total' => $group_total,
                    'group_base_id' => $group_base_id,
                    'metaKey' => $meta_key,
                    'hidden' => false,
                    'group_collapsed' => false,
                    'show_in_sidebar' => true,
                ];

                $group_key = $current_group_id . '_' . $meta_key;
                if (!isset($processed_groups[$group_key])) {
                    $processed_groups[$group_key] = true;
                    $dnd_fields[] = $dnd_field;
                }
            } else {
                // Regular field (not in a group)
                $dnd_field = [
                    'id' => $field_id,
                    'name' => sanitize_text_field($field['name'] ?? $field_id),
                    'type' => self::map_field_type($field['input_type'] ?? 'text'),
                    'label' => sanitize_text_field($field['label'] ?? ''),
                    'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                    'required' => !empty($field['is_required']),
                    'locked' => $is_locked_field,
                    'adminOnly' => false,
                    'enabled' => true,
                    'order' => $index,
                    'hidden' => false
                ];
                $dnd_fields[] = $dnd_field;
            }
        }

        return $dnd_fields;
    }

    /**
     * Map old field types to new standardized types
     */
    private static function map_field_type($old_type)
    {
        $type_mapping = [
            'text' => 'text',
            'number' => 'number',
            'select' => 'select',
            'checkbox' => 'checkbox',
            'file' => 'file',
            'gallery' => 'gallery',
            'map' => 'map',
            'video' => 'video',
            'faq' => 'faq',
            'repeater' => 'repeater',
            'agents' => 'agents',
            'textarea' => 'textarea',
            'image' => 'file',
            'price_label' => 'price_label', 
        ];

        return $type_mapping[$old_type] ?? 'text';
    }

    /**
     * Get property documents group fields with UNIQUE group metadata
     * THREE FIELDS: icon, label, url
     */
    private static function get_property_docs_group_fields()
    {
        $group_id = 'grp_property_docs_' . uniqid();
        $group_base_id = self::generate_unique_group_base_id('property_docs');
        
        // THREE FIELDS - icon, label, url
        return array(
            array(
                'fieldid'     => $group_base_id . '_icon',
                'input_type'  => 'text',
                'label'       => __('Document Icon', 'havenlytics'),
                'name'        => $group_base_id . '_icon',
                'placeholder' => __('e.g., file-pdf, file-word, file-lines', 'havenlytics'),
                'group_type'  => 'property_docs',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'icon',
                'show_in_sidebar' => true,
                'is_required' => false,
                'group_position' => 0,
                'group_total' => 3,
                'description' => __('FontAwesome icon name (e.g., file-pdf, file-word, file-lines)', 'havenlytics'),
            ),
            array(
                'fieldid'     => $group_base_id . '_label',
                'input_type'  => 'text',
                'label'       => __('Document Label', 'havenlytics'),
                'name'        => $group_base_id . '_label',
                'placeholder' => __('e.g., Floor Plan, Brochure, EPC', 'havenlytics'),
                'group_type'  => 'property_docs',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'label',
                'show_in_sidebar' => true,
                'is_required' => true,
                'group_position' => 1,
                'group_total' => 3,
            ),
            array(
                'fieldid'     => $group_base_id . '_url',
                'input_type'  => 'text',
                'label'       => __('Document URL', 'havenlytics'),
                'name'        => $group_base_id . '_url',
                'placeholder' => __('Enter document URL or file path', 'havenlytics'),
                'group_type'  => 'property_docs',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'url',
                'show_in_sidebar' => true,
                'is_required' => true,
                'group_position' => 2,
                'group_total' => 3,
            ),
        );
    }

    /**
     * Get video group fields with UNIQUE group metadata
     * EACH CALL generates a UNIQUE group_base_id
     */
    private static function get_video_group_fields()
    {
        $group_id = 'grp_video_' . uniqid();
        // GENERATE UNIQUE base ID for this specific call (FIX)
        $group_base_id = self::generate_unique_group_base_id('video');
        
        return array(
            array(
                'fieldid'     => $group_base_id . '_title',
                'input_type'  => 'text',
                'label'       => __('Video Title', 'havenlytics'),
                'name'        => $group_base_id . '_title',
                'placeholder' => __('Enter video title', 'havenlytics'),
                'group_type'  => 'video',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'title',
            ),
            array(
                'fieldid'     => $group_base_id . '_url',
                'input_type'  => 'text',
                'label'       => __('Video URL', 'havenlytics'),
                'name'        => $group_base_id . '_url',
                'placeholder' => __('Enter video URL', 'havenlytics'),
                'group_type'  => 'video',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'url',
            ),
            array(
                'fieldid'     => $group_base_id . '_thumbnail',
                'input_type'  => 'file',
                'label'       => __('Video Thumbnail', 'havenlytics'),
                'name'        => $group_base_id . '_thumbnail',
                'placeholder' => __('Upload video thumbnail', 'havenlytics'),
                'file_type'   => 'image',
                'group_type'  => 'video',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'thumbnail',
            ),
        );
    }

    /**
     * Get gallery group fields with UNIQUE group metadata
     * EACH CALL generates a UNIQUE group_base_id
     */
    private static function get_gallery_group_fields()
    {
        $group_id = 'grp_gallery_' . uniqid();
        // GENERATE UNIQUE base ID for this specific call (FIX)
        $group_base_id = self::generate_unique_group_base_id('gallery');
        
        return array(
            array(
                'fieldid'     => $group_base_id . '_title',
                'input_type'  => 'text',
                'label'       => __('Gallery Title', 'havenlytics'),
                'name'        => $group_base_id . '_title',
                'placeholder' => __('Enter gallery title', 'havenlytics'),
                'group_type'  => 'gallery',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'title',
            ),
            array(
                'fieldid'     => $group_base_id . '_images',
                'input_type'  => 'gallery',
                'label'       => __('Gallery Images', 'havenlytics'),
                'name'        => $group_base_id . '_images',
                'placeholder' => __('Upload gallery images', 'havenlytics'),
                'group_type'  => 'gallery',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'images',
            ),
        );
    }

    /**
     * Get map group fields with UNIQUE group metadata
     * EACH CALL generates a UNIQUE group_base_id
     */
    private static function get_map_group_fields()
    {
        $group_id = 'grp_map_' . uniqid();
        // GENERATE UNIQUE base ID for this specific call (FIX)
        $group_base_id = self::generate_unique_group_base_id('map');
        
        $address_field_name = $group_base_id . '_address';
        $latitude_field_name = $group_base_id . '_latitude';
        $longitude_field_name = $group_base_id . '_longitude';
        $preview_field_name = $group_base_id . '_preview';
        
        return array(
            array(
                'fieldid'     => $address_field_name,
                'input_type'  => 'text',
                'label'       => __('Property Map Address', 'havenlytics'),
                'name'        => $address_field_name,
                'placeholder' => __('Enter property map address', 'havenlytics'),
                'is_required' => true,
                'group_type'  => 'map',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'address',
            ),
            array(
                'fieldid'     => $latitude_field_name,
                'input_type'  => 'text',
                'label'       => __('Property Latitude', 'havenlytics'),
                'name'        => $latitude_field_name,
                'placeholder' => __('Enter property latitude', 'havenlytics'),
                'userguide'   => __('Get the property latitude by clicking on Google Maps or visiting https://www.maps.ie/coordinates.html', 'havenlytics'),
                'group_type'  => 'map',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'latitude',
                'hidden'      => true,
            ),
            array(
                'fieldid'     => $longitude_field_name,
                'input_type'  => 'text',
                'label'       => __('Property Longitude', 'havenlytics'),
                'name'        => $longitude_field_name,
                'placeholder' => __('Enter property longitude', 'havenlytics'),
                'userguide'   => __('Get the property longitude by clicking on Google Maps or visiting https://www.maps.ie/coordinates.html', 'havenlytics'),
                'group_type'  => 'map',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'longitude',
                'hidden'      => true,
            ),
            array(
                'fieldid'     => $preview_field_name,
                'input_type'  => 'map',
                'label'       => __('Property Location Map', 'havenlytics'),
                'name'        => $preview_field_name,
                'group_type'  => 'map',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'preview',
                'address_field_name' => $address_field_name,
                'lat_field_name' => $latitude_field_name,
                'lng_field_name' => $longitude_field_name,
                'map_field_name' => $preview_field_name,
            ),
        );
    }

    /**
     * Original metabox tabs data with professional IDs
     * Following pattern: sec_property_* for defaults, sec_custom_* for user tabs.
     */
    public static function hvnly_metabox_tabs_builder()
    {
        $basic_info_id           = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_OVERVIEW;
        $additional_info_id      = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_DETAILS;
        $address_neighborhood_id = \HvnlyNab\Core\SectionIdentity::SEC_ADDRESS_NEIGHBORHOOD;
        $video_id                = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_VIDEO;
        $gallery_id              = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_GALLERY;
        $location_id             = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_LOCATION;
        $documents_id            = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_DOCUMENTS;
        $faq_id                  = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_FAQ;
        $repeater_id             = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_REPEATER;
        $agents_id               = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_AGENTS;
        $features_id             = \HvnlyNab\Core\SectionIdentity::SEC_PROPERTY_FEATURES;
        
        return array(
            // SECTION 0: Basic Info
            array(
                'hvnly__sectiontitle' => __('Basic Info', 'havenlytics'),
                'id'           => $basic_info_id,
                'icon'         => 'fas fa-home',
                'fields'       => array(
                    array(
                        'fieldid'     => '_hvnly_property_price',
                        'input_type'  => 'price_label',
                        'label'       => __('Property Price', 'havenlytics'),
                        'name'        => '_hvnly_property_price',
                        'placeholder' => __('Enter property price', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_reception_rooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Reception Rooms', 'havenlytics'),
                        'name'        => '_hvnly_property_reception_rooms',
                        'placeholder' => __('Enter reception rooms', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_bedrooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Bedrooms', 'havenlytics'),
                        'name'        => '_hvnly_property_bedrooms',
                        'placeholder' => __('Enter property bedrooms', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_bathrooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Bathrooms', 'havenlytics'),
                        'name'        => '_hvnly_property_bathrooms',
                        'placeholder' => __('Enter property bathrooms', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_half_bathrooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Half Baths', 'havenlytics'),
                        'name'        => '_hvnly_property_half_bathrooms',
                        'placeholder' => __('Enter property half baths', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_kitchens',
                        'input_type'  => 'number',
                        'label'       => __('Property Kitchen', 'havenlytics'),
                        'name'        => '_hvnly_property_kitchens',
                        'placeholder' => __('Enter property kitchen', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_total_rooms',
                        'input_type'  => 'number',
                        'label'       => __('Property Total Rooms', 'havenlytics'),
                        'name'        => '_hvnly_property_total_rooms',
                        'placeholder' => __('Enter property total rooms', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_floors',
                        'input_type'  => 'number',
                        'label'       => __('Property Floors', 'havenlytics'),
                        'name'        => '_hvnly_property_floors',
                        'placeholder' => __('Enter property floors', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_year_built',
                        'input_type'  => 'number',
                        'label'       => __('Property Year Built', 'havenlytics'),
                        'name'        => '_hvnly_property_year_built',
                        'placeholder' => __('Enter property year built', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_mls_number',
                        'input_type'  => 'text',
                        'label'       => __('Property MLS Number', 'havenlytics'),
                        'name'        => '_hvnly_property_mls_number',
                        'placeholder' => __('Enter property MLS number', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_garage_sqft',
                        'input_type'  => 'number',
                        'label'       => __('Property Garage Square Footage', 'havenlytics'),
                        'name'        => '_hvnly_property_garage_sqft',
                        'placeholder' => __('Enter property garage square footage', 'havenlytics'),
                        'is_required' => true,
                    ),
                ),
            ),
            
            // SECTION 1: Additional Information
            array(
                'hvnly__sectiontitle' => __('Additional Information', 'havenlytics'),
                'id'           => $additional_info_id,
                'icon'         => 'fas fa-info-circle',
                'fields'       => array(
                    array(
                        'fieldid'     => '_hvnly_property_sqft',
                        'input_type'  => 'number',
                        'label'       => __('Property Area, sq ft', 'havenlytics'),
                        'name'        => '_hvnly_property_sqft',
                        'placeholder' => __('Enter property area in square feet', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_lot_size',
                        'input_type'  => 'text',
                        'label'       => __('Property Lot size, sq ft', 'havenlytics'),
                        'name'        => '_hvnly_property_lot_size',
                        'placeholder' => __('Enter property lot size', 'havenlytics'),
                        'is_required' => true,
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_hoa_fee',
                        'input_type'  => 'text',
                        'label'       => __('Property HOA Fee', 'havenlytics'),
                        'name'        => '_hvnly_property_hoa_fee',
                        'placeholder' => __('Enter property HOA fee', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_annual_tax_amount',
                        'input_type'  => 'number',
                        'label'       => __('Property Annual Tax Amount', 'havenlytics'),
                        'name'        => '_hvnly_property_annual_tax_amount',
                        'placeholder' => __('Enter property annual tax amount', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_heating',
                        'input_type'  => 'select',
                        'label'       => __('Heating', 'havenlytics'),
                        'name'        => '_hvnly_property_heating',
                        'options'     => array(
                            'forced_air' => __('Forced Air', 'havenlytics'),
                            'radiator' => __('Radiator', 'havenlytics'),
                            'heat_pump' => __('Heat Pump', 'havenlytics'),
                            'baseboard' => __('Baseboard', 'havenlytics'),
                            'none' => __('None', 'havenlytics'),
                        ),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_cooling',
                        'input_type'  => 'select',
                        'label'       => __('Cooling', 'havenlytics'),
                        'name'        => '_hvnly_property_cooling',
                        'options'     => array(
                            'central' => __('Central Air', 'havenlytics'),
                            'window' => __('Window Units', 'havenlytics'),
                            'heat_pump' => __('Heat Pump', 'havenlytics'),
                            'baseboard' => __('Baseboard', 'havenlytics'),
                            'none' => __('None', 'havenlytics'),
                        ),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_water',
                        'input_type'  => 'select',
                        'label'       => __('Water Source', 'havenlytics'),
                        'name'        => '_hvnly_property_water',
                        'options'     => array(
                            'city' => __('City', 'havenlytics'),
                            'well' => __('Well', 'havenlytics'),
                            'shared_well' => __('Shared Well', 'havenlytics'),
                            'none' => __('None', 'havenlytics'),
                        ),
                    ),
                ),
            ),
            
            // SECTION 2: Address & Neighborhood
            array(
                'hvnly__sectiontitle' => __('Address & Neighborhood', 'havenlytics'),
                'id'           => $address_neighborhood_id,
                'icon'         => 'fas fa-building',
                'fields'       => array(
                    array(
                        'fieldid'     => '_hvnly_property_reference_number',
                        'input_type'  => 'text',
                        'label'       => __('Property Reference Number', 'havenlytics'),
                        'name'        => '_hvnly_property_reference_number',
                        'placeholder' => __('Enter property reference number', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_building_number',
                        'input_type'  => 'text',
                        'label'       => __('Property Building Number', 'havenlytics'),
                        'name'        => '_hvnly_property_building_number',
                        'placeholder' => __('Enter property building number', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_street',
                        'input_type'  => 'text',
                        'label'       => __('Property Street', 'havenlytics'),
                        'name'        => '_hvnly_property_street',
                        'placeholder' => __('Enter property street', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_address_line_1',
                        'input_type'  => 'text',
                        'label'       => __('Property Address Line 1', 'havenlytics'),
                        'name'        => '_hvnly_property_address_line_1',
                        'placeholder' => __('Enter property address line 1', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_address_line_2',
                        'input_type'  => 'text',
                        'label'       => __('Property Address Line 2', 'havenlytics'),
                        'name'        => '_hvnly_property_address_line_2',
                        'placeholder' => __('Enter property address line 2', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_town_city',
                        'input_type'  => 'text',
                        'label'       => __('Property Town/City', 'havenlytics'),
                        'name'        => '_hvnly_property_town_city',
                        'placeholder' => __('Enter property town/city', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_country_state',
                        'input_type'  => 'text',
                        'label'       => __('Property Country/State', 'havenlytics'),
                        'name'        => '_hvnly_property_country_state',
                        'placeholder' => __('Enter property country/state', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_zip_code',
                        'input_type'  => 'text',
                        'label'       => __('Property Zip Code', 'havenlytics'),
                        'name'        => '_hvnly_property_zip_code',
                        'placeholder' => __('Enter property zip code', 'havenlytics'),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_location',
                        'input_type'  => 'select',
                        'label'       => __('Property Location', 'havenlytics'),
                        'name'        => '_hvnly_property_location',
                        'options'     => self::hvnly_get_property_locations(),
                    ),
                    array(
                        'fieldid'     => '_hvnly_property_country_location',
                        'input_type'  => 'select',
                        'label'       => __('Property Country', 'havenlytics'),
                        'name'        => '_hvnly_property_country_location',
                        'options'     => self::hvnly_get_property_countries(),
                    ),
                ),
            ),
            
            // SECTION 3: Property Video - EACH CALL gets UNIQUE group_base_id
            array(
                'hvnly__sectiontitle' => __('Property Video', 'havenlytics'),
                'id'           => $video_id,
                'icon'         => 'fas fa-video',
                'fields'       => self::get_video_group_fields(),
            ),
            
            // SECTION 4: Property Gallery - EACH CALL gets UNIQUE group_base_id
            array(
                'hvnly__sectiontitle' => __('Property Gallery', 'havenlytics'),
                'id'           => $gallery_id,
                'icon'         => 'fas fa-images',
                'fields'       => self::get_gallery_group_fields(),
            ),
            
            // SECTION 5: Property Location - EACH CALL gets UNIQUE group_base_id
            array(
                'hvnly__sectiontitle' => __('Property Location', 'havenlytics'),
                'id'           => $location_id,
                'icon'         => 'fas fa-map-marker-alt',
                'fields'       => self::get_map_group_fields(),
            ),
            
            // SECTION 6: Property Documents - EACH CALL gets UNIQUE group_base_id
            array(
                'hvnly__sectiontitle' => __('Property Documents', 'havenlytics'),
                'id'           => $documents_id,
                'icon'         => 'fas fa-file-pdf',
                'fields'       => self::get_property_docs_group_fields(),
            ),

            // SECTION 7: FAQ
            array(
                'hvnly__sectiontitle' => __('Frequently Asked Questions', 'havenlytics'),
                'id'           => $faq_id,
                'icon'         => 'fas fa-question-circle',
                'fields'       => self::get_faq_group_fields(),
            ),

            // SECTION 8: Repeater
            array(
                'hvnly__sectiontitle' => __('Property Highlights', 'havenlytics'),
                'id'           => $repeater_id,
                'icon'         => 'fas fa-list',
                'fields'       => self::get_repeater_group_fields(),
            ),

            // SECTION 9: Property Features
            array(
                'hvnly__sectiontitle' => __('Property Listing Features', 'havenlytics'),
                'id'           => $features_id,
                'icon'         => 'fas fa-check-square',
                'fields'       => self::get_features_group_fields(),
            ),

            // SECTION 10: Agents
            array(
                'hvnly__sectiontitle' => __('Listing Assigned Agents', 'havenlytics'),
                'id'           => $agents_id,
                'icon'         => 'fas fa-user-tie',
                'fields'       => self::get_agents_group_fields(),
            )
        );
    }

    /**
     * Get FAQ group fields with UNIQUE group metadata.
     */
    private static function get_faq_group_fields()
    {
        $group_id = 'grp_faq_' . uniqid();
        $group_base_id = self::generate_unique_group_base_id('faq');

        return array(
            array(
                'fieldid'     => $group_base_id . '_title',
                'input_type'  => 'text',
                'label'       => __('Section Title', 'havenlytics'),
                'name'        => $group_base_id . '_title',
                'placeholder' => __('e.g., Frequently Asked Questions', 'havenlytics'),
                'group_type'  => 'faq',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'title',
                'group_position' => 0,
                'group_total' => 2,
            ),
            array(
                'fieldid'     => $group_base_id . '_faqs',
                'input_type'  => 'faq',
                'label'       => __('FAQ Items', 'havenlytics'),
                'name'        => $group_base_id . '_faqs',
                'group_type'  => 'faq',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'faqs',
                'group_position' => 1,
                'group_total' => 2,
            ),
        );
    }

    /**
     * Get repeater group fields with UNIQUE group metadata.
     */
    private static function get_repeater_group_fields()
    {
        $group_id = 'grp_repeater_' . uniqid();
        $group_base_id = self::generate_unique_group_base_id('repeater');

        return array(
            array(
                'fieldid'     => $group_base_id . '_title',
                'input_type'  => 'text',
                'label'       => __('Section Title', 'havenlytics'),
                'name'        => $group_base_id . '_title',
                'placeholder' => __('e.g., Property Highlights', 'havenlytics'),
                'group_type'  => 'repeater',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'title',
                'group_position' => 0,
                'group_total' => 2,
            ),
            array(
                'fieldid'     => $group_base_id . '_items',
                'input_type'  => 'repeater',
                'label'       => __('Repeater Items', 'havenlytics'),
                'name'        => $group_base_id . '_items',
                'group_type'  => 'repeater',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'items',
                'group_position' => 1,
                'group_total' => 2,
            ),
        );
    }

    /**
     * Get agents group fields with UNIQUE group metadata.
     */
    private static function get_agents_group_fields()
    {
        $group_id = 'grp_agents_' . uniqid();
        $group_base_id = self::generate_unique_group_base_id('agents');

        return array(
            array(
                'fieldid'     => $group_base_id . '_title',
                'input_type'  => 'text',
                'label'       => __('Section Title', 'havenlytics'),
                'name'        => $group_base_id . '_title',
                'placeholder' => __('e.g., Listing Agents', 'havenlytics'),
                'group_type'  => 'agents',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'title',
                'group_position' => 0,
                'group_total' => 2,
            ),
            array(
                'fieldid'     => $group_base_id . '_agents',
                'input_type'  => 'agents',
                'label'       => __('Assigned Agents', 'havenlytics'),
                'name'        => $group_base_id . '_agents',
                'group_type'  => 'agents',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'agents',
                'group_position' => 1,
                'group_total' => 2,
            ),
        );
    }

    /**
     * Get property features group field (checkbox list).
     */
    private static function get_features_group_fields()
    {
        $group_id = 'grp_features_' . uniqid();
        $group_base_id = self::generate_unique_group_base_id('features');

        return array(
            array(
                'fieldid'     => $group_base_id . '_features',
                'input_type'  => 'checkbox',
                'label'       => __('Property Features', 'havenlytics'),
                'name'        => $group_base_id . '_features',
                'group_type'  => 'features',
                'group_id'    => $group_id,
                'group_base_id' => $group_base_id,
                'metaKey'     => 'features',
                'group_position' => 0,
                'group_total' => 1,
            ),
        );
    }

    /**
     * Utility methods for options data
     */
    public static function hvnly_get_property_locations()
    {
        return array(
            'urban' => __('Urban', 'havenlytics'),
            'suburban' => __('Suburban', 'havenlytics'),
            'rural' => __('Rural', 'havenlytics'),
            'coastal' => __('Coastal', 'havenlytics'),
        );
    }

    public static function hvnly_get_property_countries()
    {
        return array(
            'us' => __('United States', 'havenlytics'),
            'uk' => __('United Kingdom', 'havenlytics'),
            'ca' => __('Canada', 'havenlytics'),
            'au' => __('Australia', 'havenlytics'),
        );
    }

    public static function get_cf7_forms()
    {
        return array(
            '' => __('Select a Form', 'havenlytics'),
            'form1' => __('Contact Form 1', 'havenlytics'),
            'form2' => __('Contact Form 2', 'havenlytics'),
        );
    }
}