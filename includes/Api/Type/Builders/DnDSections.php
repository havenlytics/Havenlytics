<?php
/**
 * Enhanced DnDSections with Group Field System
 * 
 * @package HvnlyNab\Api\Type\Builders
 * @since 2.0.0
 */

namespace HvnlyNab\Api\Type\Builders;

use HvnlyNab\Core\SectionIdentity;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use HvnlyNab\Api\Type\Builders\DefaultTabSectionsData;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class DnDSections
{
    private $storage_key = 'hvnly_property_builder.sections';
    private $namespace = 'hvnlynab/v1';
    private $route_base = 'pb-dnd-sections';
    
    // Track generated IDs to ensure uniqueness within the same request
    private $generated_base_ids = [];
    private $generated_group_ids = [];
    
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    /**
     * Register REST API routes
     */
    public function routes()
    {
        register_rest_route($this->namespace, '/' . $this->route_base, [
            'methods' => 'GET',
            'callback' => [$this, 'get_all'],
            'permission_callback' => [$this, 'get_per'],
        ]);

        register_rest_route($this->namespace, '/' . $this->route_base, [
            'methods' => 'POST',
            'callback' => [$this, 'save_all'],
            'permission_callback' => [$this, 'create_per'],
        ]);

        register_rest_route($this->namespace, '/' . $this->route_base, [
            'methods' => 'DELETE',
            'callback' => [$this, 'delete_all'],
            'permission_callback' => [$this, 'del_per'],
        ]);

        register_rest_route($this->namespace, '/' . $this->route_base . '/reset-unified', [
            'methods' => 'POST',
            'callback' => [$this, 'reset_unified'],
            'permission_callback' => [$this, 'create_per'],
        ]);

        register_rest_route($this->namespace, '/master-base-ids', [
            'methods' => 'GET',
            'callback' => [$this, 'get_master_base_ids'],
            'permission_callback' => [$this, 'get_per'],
        ]);
        
        register_rest_route($this->namespace, '/generate-unique-ids', [
            'methods' => 'GET',
            'callback' => [$this, 'generate_unique_ids'],
            'permission_callback' => [$this, 'get_per'],
        ]);

    }

    /**
     * Generate unique IDs for React components
     * This ensures React uses the SAME ID format as PHP
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function generate_unique_ids($request) {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', 'Invalid nonce', ['status' => 403]);
            }
            
            if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
                $unified = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
                $response = $unified->get_id_generation_response();
                return rest_ensure_response($response);
            }
            
            // Fallback generation
            $timestamp = time();
            $micro_suffix = substr(str_replace('.', '', (string)microtime(true)), -6);
            
            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'video' => "video_{$timestamp}_{$micro_suffix}_" . uniqid() . '_' . wp_rand(10000, 99999),
                    'gallery' => "gallery_{$timestamp}_{$micro_suffix}_" . uniqid() . '_' . wp_rand(10000, 99999),
                    'map' => "map_{$timestamp}_{$micro_suffix}_" . uniqid() . '_' . wp_rand(10000, 99999),
                    'property_docs' => "property_docs_{$timestamp}_{$micro_suffix}_" . uniqid() . '_' . wp_rand(10000, 99999),
                    'group_id' => "grp_" . uniqid() . '_' . wp_rand(1000, 9999),
                    'section_id' => SectionIdentity::generate_custom_section_id(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return new WP_Error('generation_failed', $e->getMessage(), ['status' => 500]);
        }
    }


    /**
     * Generate a UNIQUE base ID for a group
     */
    private function generate_unique_base_id($group_type)
    {
        $microtime = microtime(true);
        $timestamp = (int)$microtime;
        $micro_suffix = substr(str_replace('.', '', (string)$microtime), -6);
        $random = wp_rand(10000, 99999);
        $unique_id = uniqid();
        
        // Ensure absolutely no duplicates within this request
        $counter = 0;
        $base_id = '';
        
        switch ($group_type) {
            case 'video':
                $base_id = "video_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
                break;
            case 'gallery':
                $base_id = "gallery_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
                break;
            case 'map':
                $base_id = "map_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
                break;
            case 'property_docs':
                $base_id = "property_docs_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
                break;
            case 'agents':
                $base_id = "agents_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
                break;
            case 'faq':
                $base_id = "faq_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
                break;
            case 'repeater':
                $base_id = "repeater_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
                break;
            default:
                $base_id = "group_{$timestamp}_{$micro_suffix}_{$unique_id}_{$random}";
        }
        
        $temp_id = $base_id;
        while (isset($this->generated_base_ids[$temp_id]) && $counter < 10) {
            $counter++;
            $temp_id = $base_id . '_' . $counter;
        }
        
        $this->generated_base_ids[$temp_id] = true;
        return $temp_id;
    }

    /**
     * Generate a UNIQUE group ID
     */
    private function generate_unique_group_id($group_type)
    {
        $timestamp = time();
        $short_id = substr(uniqid(), -8);
        $random = wp_rand(1000, 9999);
        $group_id = "grp_{$group_type}_{$timestamp}_{$short_id}_{$random}";
        
        $counter = 0;
        $temp_id = $group_id;
        while (isset($this->generated_group_ids[$temp_id]) && $counter < 10) {
            $counter++;
            $temp_id = $group_id . '_' . $counter;
        }
        
        $this->generated_group_ids[$temp_id] = true;
        return $temp_id;
    }

    /**
     * Reset generated IDs cache
     */
    private function reset_generated_ids()
    {
        $this->generated_base_ids = [];
        $this->generated_group_ids = [];
    }

    /**
     * Get master base IDs from UnifiedFieldGenerator
     */
    public function get_master_base_ids($request)
    {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', 'Invalid nonce', ['status' => 403]);
            }

            if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
                $unified_generator = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
                $master_ids = $unified_generator->get_or_create_master_base_ids();
                
                return rest_ensure_response([
                    'success' => true,
                    'data' => [
                        'video' => $master_ids['video'],
                        'gallery' => $master_ids['gallery'],
                        'map' => $master_ids['map'],
                        'property_docs' => $master_ids['property_docs'],
                        'generated_at' => $master_ids['generated_at'] ?? time()
                    ]
                ]);
            }

            $master_ids = get_option('hvnly_master_base_ids', []);
            
            if (empty($master_ids)) {
                $timestamp = time();
                $unique_suffix = substr(uniqid(), -8);
                
                $master_ids = [
                    'video' => "video_{$timestamp}_{$unique_suffix}",
                    'gallery' => "gallery_{$timestamp}_{$unique_suffix}",
                    'map' => "map_{$timestamp}_{$unique_suffix}",
                    'property_docs' => "property_docs_{$timestamp}_{$unique_suffix}",
                    'generated_at' => $timestamp
                ];
                
                update_option('hvnly_master_base_ids', $master_ids);
            }
            
            return rest_ensure_response([
                'success' => true,
                'data' => $master_ids
            ]);
            
        } catch (\Exception $e) {
            return new WP_Error('fetch_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Save all tabs/sections with group support
     */
    public function save_all($request)
    {
        try {
            // Reset generated IDs cache for this save operation
            $this->reset_generated_ids();
            
            $params = $request->get_json_params();

            if (!isset($params['tabs'])) {
                return new \WP_Error('invalid_data', 'Tabs data is required', ['status' => 400]);
            }

            if (!is_array($params['tabs'])) {
                return new \WP_Error('invalid_data', 'Tabs must be an array', ['status' => 400]);
            }

            $before_config = get_option( $this->storage_key, array() );
            if ( ! is_array( $before_config ) ) {
                $before_config = array();
            }

            // Get master base IDs for normalization
            $master_ids = $this->get_master_ids_for_normalization();

            $tabs = [];
            foreach ($params['tabs'] as $index => $tab) {
                if (!is_array($tab)) {
                    continue;
                }

                $id = sanitize_text_field($tab['id'] ?? 'sec_' . uniqid());

                $tab_fields = $tab['fields'] ?? [];
                if ( self::is_basic_info_tab( $tab ) ) {
                    $tab_fields = self::ensure_basic_info_fields( $tab_fields );
                }

                $sanitized_fields = $this->sanitize_fields_with_groups_and_normalize( $tab_fields, $master_ids );
                $sanitized_fields = $this->consolidate_tab_group_fields( $sanitized_fields );

                $tabs[$id] = [
                    'id' => $id,
                    'title' => sanitize_text_field($tab['title'] ?? 'Untitled Section'),
                    'icon' => sanitize_text_field($tab['icon'] ?? 'fas fa-home'),
                    'fields' => $sanitized_fields,
                    'order' => intval($tab['order'] ?? $index),
                    'collapsed' => !empty($tab['collapsed']),
                    'required' => self::is_basic_info_tab( $tab ) ? true : !empty($tab['required'])
                ];
            }

            // Reindex tabs by order
            uasort($tabs, function ($a, $b) {
                return $a['order'] - $b['order'];
            });

            // If the same group_id appears in two different tabs with different bases
            // (rare), give the later tab a fresh group_id so meta keys stay isolated.
            $seen_group_ids = [];
            $fixed_groups   = 0;

            foreach ( $tabs as &$tab ) {
                foreach ( $tab['fields'] as &$field ) {
                    $gid   = $field['group_id'] ?? '';
                    $gtype = $field['group_type'] ?? '';
                    $base  = $field['group_base_id'] ?? '';

                    if ( empty( $gid ) || empty( $gtype ) ) {
                        continue;
                    }

                    if ( ! isset( $seen_group_ids[ $gid ] ) ) {
                        $seen_group_ids[ $gid ] = $base;
                    } elseif ( $seen_group_ids[ $gid ] !== $base ) {
                        $new_base               = $this->generate_unique_base_id( $gtype );
                        $new_gid                = $this->generate_unique_group_id( $gtype );
                        $meta_key               = self::resolve_field_meta_key( $field );
                        $field['group_id']      = $new_gid;
                        $field['group_base_id'] = $new_base;
                        if ( ! empty( $meta_key ) ) {
                            $field['id']   = $new_base . '_' . $meta_key;
                            $field['name'] = $field['id'];
                        }
                        $field = $this->apply_group_base_map_links( $field, $new_base );
                        $seen_group_ids[ $new_gid ] = $new_base;
                        $fixed_groups++;
                    }
                }
                unset( $field );
            }
            unset( $tab );

            // Ensure every group instance has a unique group_base_id (no master-ID collisions).
            if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
                $tabs = \HvnlyNab\Core\GroupFieldIdentity::deduplicate_sections( $tabs );
            }

            // Collapse legacy/canonical section ID pairs via SectionIdentity
            // (sole source of truth) so duplicate alias tabs cannot be re-saved.
            if ( class_exists( '\HvnlyNab\Core\SectionIdentity' ) ) {
                $section_dedupe = SectionIdentity::deduplicate_equivalent_sections( $tabs );
                $tabs           = $section_dedupe['sections'];
            }

            $meta_remap_scheduled = false;
            if ( class_exists( '\HvnlyNab\Core\DataPreservation\BuilderMetaMigrator' ) && ! empty( $before_config ) ) {
                $remap = \HvnlyNab\Core\DataPreservation\BuilderMetaMigrator::build_remap_between_configs(
                    $before_config,
                    $tabs
                );
                if ( ! empty( $remap ) ) {
                    if ( class_exists( '\HvnlyNab\Core\DataPreservation\BackupManager' ) ) {
                        \HvnlyNab\Core\DataPreservation\BackupManager::create_snapshot( 'builder_save_remap' );
                    }
                    \HvnlyNab\Core\DataPreservation\BuilderMetaMigrator::schedule_property_remap( $remap );
                    $meta_remap_scheduled = true;
                }
            }

            if ( function_exists( 'hvnly_canonicalize_ui_tree' ) ) {
                $tabs = hvnly_canonicalize_ui_tree( $tabs );
            }

            $result = update_option($this->storage_key, $tabs);

            // Bust the standardized-field-name cache so the import wizard
            // always uses the freshest canonical base IDs after a builder save.
            wp_cache_delete( 'hvnly_standardized_field_names' );

            return rest_ensure_response([
                'success' => true,
                'data' => array_values($tabs),
                'message' => 'Tabs saved successfully',
                'saved_count' => count($tabs),
                'fixed_duplicate_groups' => $fixed_groups,
                'meta_remap_scheduled'   => $meta_remap_scheduled,
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('save_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Get master IDs for normalization
     */
    private function get_master_ids_for_normalization()
    {
        if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
            $unified_generator = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
            return $unified_generator->get_or_create_master_base_ids();
        }
        
        $master_ids = get_option('hvnly_master_base_ids', []);
        
        if (empty($master_ids)) {
            $timestamp = time();
            $unique_suffix = substr(uniqid(), -8);
            
            $master_ids = [
                'video' => "video_{$timestamp}_{$unique_suffix}",
                'gallery' => "gallery_{$timestamp}_{$unique_suffix}",
                'map' => "map_{$timestamp}_{$unique_suffix}",
                'property_docs' => "property_docs_{$timestamp}_{$unique_suffix}",
                'generated_at' => $timestamp
            ];
            
            update_option('hvnly_master_base_ids', $master_ids);
        }
        
        return $master_ids;
    }

    /**
     * Resolve metaKey suffix for a grouped field.
     *
     * @param array $field Field config.
     * @return string
     */
    private static function resolve_field_meta_key( array $field ): string {
        if ( ! empty( $field['metaKey'] ) ) {
            return sanitize_key( $field['metaKey'] );
        }

        $name = $field['name'] ?? $field['id'] ?? '';
        if ( preg_match( '/_(title|url|thumbnail|images|address|latitude|longitude|preview|icon|label|documents)$/', $name, $matches ) ) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Apply map cross-field name links for a canonical group base id.
     *
     * @param array  $field Field config.
     * @param string $group_base_id Canonical base id.
     * @return array
     */
    private function apply_group_base_map_links( array $field, string $group_base_id ): array {
        if ( ( $field['group_type'] ?? '' ) !== 'map' || empty( $group_base_id ) ) {
            return $field;
        }

        $field['address_field_name'] = $group_base_id . '_address';
        $field['lat_field_name']     = $group_base_id . '_latitude';
        $field['lng_field_name']     = $group_base_id . '_longitude';
        $field['map_field_name']     = $group_base_id . '_preview';

        return $field;
    }

    /**
     * Merge fields that share group_id onto one canonical group_base_id.
     *
     * @param array $fields Tab fields.
     * @return array
     */
    private function consolidate_tab_group_fields( array $fields ): array {
        if ( empty( $fields ) ) {
            return [];
        }

        $group_indexes = [];

        foreach ( $fields as $index => $field ) {
            if ( ! is_array( $field ) ) {
                continue;
            }

            $group_id = $field['group_id'] ?? '';
            if ( empty( $group_id ) ) {
                continue;
            }

            if ( ! isset( $group_indexes[ $group_id ] ) ) {
                $group_indexes[ $group_id ] = [];
            }

            $group_indexes[ $group_id ][] = $index;
        }

        foreach ( $group_indexes as $group_id => $indexes ) {
            if ( count( $indexes ) < 2 ) {
                continue;
            }

            $canonical_base = '';
            foreach ( $indexes as $index ) {
                $base = $fields[ $index ]['group_base_id'] ?? '';
                if ( ! empty( $base ) ) {
                    $canonical_base = $base;
                    break;
                }
            }

            if ( empty( $canonical_base ) ) {
                $group_type = $fields[ $indexes[0] ]['group_type'] ?? 'group';
                $canonical_base = $this->generate_unique_base_id( $group_type );
            }

            usort(
                $indexes,
                function ( $a, $b ) use ( $fields ) {
                    return ( $fields[ $a ]['order'] ?? 0 ) <=> ( $fields[ $b ]['order'] ?? 0 );
                }
            );

            $group_total = count( $indexes );

            foreach ( $indexes as $position => $index ) {
                $field    = $fields[ $index ];
                $meta_key = self::resolve_field_meta_key( $field );

                $fields[ $index ]['group_id']       = $group_id;
                $fields[ $index ]['group_base_id'] = $canonical_base;
                $fields[ $index ]['group_position'] = $position;
                $fields[ $index ]['group_total']    = $group_total;

                if ( ! empty( $meta_key ) ) {
                    $fields[ $index ]['metaKey'] = $meta_key;
                    $fields[ $index ]['id']     = $canonical_base . '_' . $meta_key;
                    $fields[ $index ]['name']   = $fields[ $index ]['id'];
                }

                $fields[ $index ] = $this->apply_group_base_map_links( $fields[ $index ], $canonical_base );
            }
        }

        return $fields;
    }

    /**
     * Sanitize fields with group metadata.
     *
     * IMPORTANT: field names and group_base_ids sent from the React builder are
     * already correct (built as "{group_base_id}_{suffix}").  We must NOT
     * overwrite them with the master base ID because:
     *  a) Each group instance in the builder has its own unique base ID.
     *  b) Overwriting would collapse two video sections to the same meta keys,
     *     causing the second to silently overwrite the first.
     *
     * Master base IDs are only used as a fallback when a field arrives with NO
     * group_base_id (i.e. it is a brand-new field that has not been saved yet).
     */
    private function sanitize_fields_with_groups_and_normalize($fields, $master_ids)
    {
        if (!is_array($fields)) {
            return [];
        }

        $sanitized_fields = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $group_type = $field['group_type'] ?? '';
            $field_id   = $field['id']   ?? 'fld_' . uniqid();
            $field_name = $field['name'] ?? $field_id;
            $field_type = sanitize_key( (string) ( $field['type'] ?? 'text' ) );

            if ( ! $this->is_allowed_property_field_type( $field_type ) ) {
                continue;
            }

            // Determine group_base_id BEFORE deciding field name.
            $group_base_id = '';
            if (!empty($field['group_id'])) {
                if (!empty($field['group_base_id'])) {
                    // Preserve the existing base ID — this is the canonical path.
                    $group_base_id = sanitize_text_field($field['group_base_id']);
                } else {
                    // New field without a base ID: always generate a unique base.
                    // Never reuse the master ID — that would collapse multiple group
                    // instances of the same type into one meta key namespace.
                    $group_base_id = $this->generate_unique_base_id( $group_type );
                }
            }

            // The field name must match its group_base_id prefix.
            // If the stored name already starts with the correct base ID, keep it.
            // If not (e.g. a brand-new field where group_base_id was just assigned),
            // rebuild the name from base + metaKey suffix.
            if (!empty($group_base_id) && !empty($group_type)) {
                $meta_key = sanitize_text_field($field['metaKey'] ?? '');
                if (!empty($meta_key) && strpos($field_name, $group_base_id) !== 0) {
                    // Name does not yet use the correct base — rebuild it.
                    $field_name = $group_base_id . '_' . $meta_key;
                    $field_id   = $field_name;
                }
                // Otherwise trust the name coming from React.
            }

            $enabled_value = isset($field['enabled']) ? (bool)$field['enabled'] : true;

            $sanitized_field = [
                'id'          => sanitize_text_field($field_id),
                'name'        => sanitize_text_field($field_name),
                'type'        => $field_type,
                'label'       => sanitize_text_field($field['label'] ?? ''),
                'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
                'required'    => !empty($field['required']),
                'adminOnly'   => !empty($field['adminOnly']),
                'enabled'     => $enabled_value,
                'locked'      => !empty($field['locked']),
                'order'       => intval($field['order'] ?? 0),
                'hidden'      => !empty($field['hidden']),
            ];

            // Add group metadata if present
            if (!empty($field['group_id'])) {
                $sanitized_field['group_id']       = sanitize_text_field($field['group_id']);
                $sanitized_field['group_type']     = sanitize_text_field($group_type);
                $sanitized_field['group_name']     = sanitize_text_field($field['group_name'] ?? '');
                $sanitized_field['group_position'] = intval($field['group_position'] ?? 0);
                $sanitized_field['group_total']    = intval($field['group_total'] ?? 0);
                $sanitized_field['group_base_id']  = $group_base_id;
                $sanitized_field['metaKey']        = sanitize_text_field($field['metaKey'] ?? '');
                $sanitized_field['group_collapsed'] = !empty($field['group_collapsed']);

                if ($group_type === 'property_docs' && isset($field['show_in_sidebar'])) {
                    $sanitized_field['show_in_sidebar'] = (bool)$field['show_in_sidebar'];
                }
            }

            // Add map relationship fields with current group_base_id
            if (!empty($sanitized_field['group_base_id'])) {
                if (isset($field['address_field_name'])) {
                    $sanitized_field['address_field_name'] = $sanitized_field['group_base_id'] . '_address';
                }
                if (isset($field['lat_field_name'])) {
                    $sanitized_field['lat_field_name'] = $sanitized_field['group_base_id'] . '_latitude';
                }
                if (isset($field['lng_field_name'])) {
                    $sanitized_field['lng_field_name'] = $sanitized_field['group_base_id'] . '_longitude';
                }
                if (isset($field['map_field_name'])) {
                    $sanitized_field['map_field_name'] = $sanitized_field['group_base_id'] . '_preview';
                }
            } else {
                if (!empty($field['address_field_name'])) {
                    $sanitized_field['address_field_name'] = sanitize_text_field($field['address_field_name']);
                }
                if (!empty($field['lat_field_name'])) {
                    $sanitized_field['lat_field_name'] = sanitize_text_field($field['lat_field_name']);
                }
                if (!empty($field['lng_field_name'])) {
                    $sanitized_field['lng_field_name'] = sanitize_text_field($field['lng_field_name']);
                }
                if (!empty($field['map_field_name'])) {
                    $sanitized_field['map_field_name'] = sanitize_text_field($field['map_field_name']);
                }
            }

            // Add conditional fields
            if (!empty($field['condition'])) {
                $sanitized_field['condition'] = [
                    'field' => sanitize_text_field($field['condition']['field'] ?? ''),
                    'value' => sanitize_text_field($field['condition']['value'] ?? '')
                ];
            }

            // Add options for select fields
            if (!empty($field['options']) && is_array($field['options'])) {
                $sanitized_field['options'] = array_map('sanitize_text_field', $field['options']);
            }

            // Add file type for file uploads
            if (!empty($field['fileType'])) {
                $sanitized_field['fileType'] = sanitize_text_field($field['fileType']);
            }

            // Add description/userguide
            if (!empty($field['description'])) {
                $sanitized_field['description'] = sanitize_text_field($field['description']);
            }
            if (!empty($field['userguide'])) {
                $sanitized_field['userguide'] = sanitize_text_field($field['userguide']);
            }

            $sanitized_fields[] = $sanitized_field;
        }

        return $sanitized_fields;
    }

    /**
     * Allowed Add Property Form field types (mirrors FieldRegistry).
     *
     * @return array<int, string>
     */
    private function get_allowed_property_field_types()
    {
        static $allowed_types = null;

        if ( null !== $allowed_types ) {
            return $allowed_types;
        }

        $allowed_types = array(
            'text',
            'textarea',
            'number',
            'select',
            'checkbox',
            'file',
            'gallery',
            'map',
            'video',
            'property_docs',
            'agents',
            'faq',
            'repeater',
            'price_label',
        );

        if ( class_exists( '\HvnlyNab\Database\Database' ) ) {
            $registry = \HvnlyNab\Database\Database::get_field_registry();
            if ( $registry ) {
                $registry->register_default_field_types();
                $registered = $registry->get_field_types();
                if ( ! empty( $registered ) ) {
                    $allowed_types = array_values( array_unique( $registered ) );
                }
            }
        }

        return $allowed_types;
    }

    /**
     * Whether a field type is allowed in the property builder save payload.
     *
     * @param string $field_type Field type identifier.
     * @return bool
     */
    private function is_allowed_property_field_type( $field_type )
    {
        $field_type = sanitize_key( (string) $field_type );

        return '' !== $field_type && in_array( $field_type, $this->get_allowed_property_field_types(), true );
    }

    /**
     * Whether a tab is the required Basic Info section.
     *
     * @param array $tab Tab config.
     * @return bool
     */
    public static function is_basic_info_tab( $tab ) {
        if ( ! is_array( $tab ) ) {
            return false;
        }

        $title = $tab['title'] ?? '';
        $id    = $tab['id'] ?? '';

        return ! empty( $tab['required'] )
            || $title === 'Basic Info'
            || $id === 'basic_info'
            || $id === 'sec_basic_info'
            || $id === SectionIdentity::SEC_PROPERTY_OVERVIEW;
    }

    /**
     * Canonical required Basic Info fields (builder + metabox defaults).
     *
     * @return array
     */
    public static function get_canonical_basic_info_fields() {
        static $building = false;
        if ( ! $building && function_exists( 'hvnly_with_english_ui' ) ) {
            $building = true;
            try {
                return hvnly_with_english_ui( static function () {
                    return self::get_canonical_basic_info_fields();
                } );
            } finally {
                $building = false;
            }
        }

        return [
            [ 'id' => '_hvnly_property_price', 'name' => '_hvnly_property_price', 'type' => 'price_label', 'label' => __( 'Property Price', 'havenlytics' ), 'placeholder' => __( 'Enter property price', 'havenlytics' ), 'required' => true, 'locked' => true, 'enabled' => true, 'order' => 0 ],
            [ 'id' => '_hvnly_property_reception_rooms', 'name' => '_hvnly_property_reception_rooms', 'type' => 'number', 'label' => __( 'Property Reception Rooms', 'havenlytics' ), 'placeholder' => __( 'Enter reception rooms', 'havenlytics' ), 'required' => true, 'locked' => true, 'enabled' => true, 'order' => 1 ],
            [ 'id' => '_hvnly_property_bedrooms', 'name' => '_hvnly_property_bedrooms', 'type' => 'number', 'label' => __( 'Property Bedrooms', 'havenlytics' ), 'placeholder' => __( 'Enter property bedrooms', 'havenlytics' ), 'required' => true, 'locked' => true, 'enabled' => true, 'order' => 2 ],
            [ 'id' => '_hvnly_property_bathrooms', 'name' => '_hvnly_property_bathrooms', 'type' => 'number', 'label' => __( 'Property Bathrooms', 'havenlytics' ), 'placeholder' => __( 'Enter property bathrooms', 'havenlytics' ), 'required' => true, 'locked' => true, 'enabled' => true, 'order' => 3 ],
            [ 'id' => '_hvnly_property_half_bathrooms', 'name' => '_hvnly_property_half_bathrooms', 'type' => 'number', 'label' => __( 'Property Half Baths', 'havenlytics' ), 'placeholder' => __( 'Enter property half baths', 'havenlytics' ), 'required' => true, 'locked' => true, 'enabled' => true, 'order' => 4 ],
            [ 'id' => '_hvnly_property_kitchens', 'name' => '_hvnly_property_kitchens', 'type' => 'number', 'label' => __( 'Property Kitchen', 'havenlytics' ), 'placeholder' => __( 'Enter property kitchen', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 5 ],
            [ 'id' => '_hvnly_property_total_rooms', 'name' => '_hvnly_property_total_rooms', 'type' => 'number', 'label' => __( 'Property Total Rooms', 'havenlytics' ), 'placeholder' => __( 'Enter property total rooms', 'havenlytics' ), 'required' => true, 'locked' => true, 'enabled' => true, 'order' => 6 ],
            [ 'id' => '_hvnly_property_floors', 'name' => '_hvnly_property_floors', 'type' => 'number', 'label' => __( 'Property Floors', 'havenlytics' ), 'placeholder' => __( 'Enter property floors', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 7 ],
            [ 'id' => '_hvnly_property_year_built', 'name' => '_hvnly_property_year_built', 'type' => 'number', 'label' => __( 'Property Year Built', 'havenlytics' ), 'placeholder' => __( 'Enter property year built', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 8 ],
            [ 'id' => '_hvnly_property_mls_number', 'name' => '_hvnly_property_mls_number', 'type' => 'text', 'label' => __( 'Property MLS Number', 'havenlytics' ), 'placeholder' => __( 'Enter property MLS number', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 9 ],
            [ 'id' => '_hvnly_property_garage_sqft', 'name' => '_hvnly_property_garage_sqft', 'type' => 'number', 'label' => __( 'Property Garage Square Footage', 'havenlytics' ), 'placeholder' => __( 'Enter property garage square footage', 'havenlytics' ), 'required' => true, 'locked' => true, 'enabled' => true, 'order' => 10 ],
        ];
    }

    /**
     * Merge stored Basic Info fields with canonical defaults (by meta name).
     *
     * @param array $stored_fields Fields from saved builder config.
     * @return array
     */
    public static function ensure_basic_info_fields( $stored_fields ) {
        $defaults = self::get_canonical_basic_info_fields();
        $stored   = is_array( $stored_fields ) ? $stored_fields : [];
        $merged   = [];

        foreach ( $defaults as $default_field ) {
            $match = null;
            foreach ( $stored as $stored_field ) {
                if ( ! is_array( $stored_field ) ) {
                    continue;
                }
                $stored_name = $stored_field['name'] ?? '';
                $stored_id   = $stored_field['id'] ?? '';
                if ( $stored_name === $default_field['name']
                    || $stored_id === $default_field['id']
                    || $stored_id === $default_field['name']
                    || $stored_name === $default_field['id'] ) {
                    $match = $stored_field;
                    break;
                }
            }

            if ( $match ) {
                $type = ( $default_field['name'] === '_hvnly_property_price' )
                    ? 'price_label'
                    : ( $match['type'] ?? $default_field['type'] );
                $merged[] = array_merge( $default_field, $match, [
                    'id'      => $default_field['id'],
                    'name'    => $default_field['name'],
                    'type'    => $type,
                    'locked'  => $default_field['locked'],
                    'enabled' => isset( $match['enabled'] ) ? (bool) $match['enabled'] : true,
                ] );
            } else {
                $merged[] = $default_field;
            }
        }

        $canonical_names = array_column( $defaults, 'name' );
        foreach ( $stored as $stored_field ) {
            if ( ! is_array( $stored_field ) ) {
                continue;
            }
            $name = $stored_field['name'] ?? '';
            if ( $name && ! in_array( $name, $canonical_names, true ) && empty( $stored_field['locked'] ) ) {
                $merged[] = $stored_field;
            }
        }

        usort( $merged, function ( $a, $b ) {
            return ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 );
        } );

        return $merged;
    }

    /**
     * Fetch all sections with group data
     */
    public function get_all()
    {
        try {
            $sections = get_option($this->storage_key, []);
            $has_defaults = false;

            if (empty($sections) || !is_array($sections)) {
                // Use UnifiedFieldGenerator for fresh configuration
                if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
                    $unified = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
                    $sections = $unified->get_unified_configuration();
                    update_option($this->storage_key, $sections);
                    $has_defaults = true;
                } else {
                    $sections = DefaultTabSectionsData::get_default_sections_for_dnd();
                    $has_defaults = true;
                    update_option($this->storage_key, $sections);
                }
            }

            // Unify split group fields (same group_id, different group_base_id per sub-field).
            foreach ( $sections as $section_id => &$section ) {
                if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
                    $section['fields'] = $this->consolidate_tab_group_fields( $section['fields'] );
                }
            }
            unset( $section );

            // Repair incomplete Basic Info sections (e.g. only price saved).
            $basic_info_repaired = false;
            foreach ( $sections as $section_id => &$section ) {
                if ( self::is_basic_info_tab( $section ) ) {
                    $before        = $section['fields'] ?? [];
                    $merged_fields = self::ensure_basic_info_fields( $before );
                    if ( wp_json_encode( $merged_fields ) !== wp_json_encode( $before ) ) {
                        $basic_info_repaired = true;
                    }
                    $section['fields']   = $merged_fields;
                    $section['required'] = true;
                }
            }
            unset( $section );

            if ( $basic_info_repaired ) {
                update_option( $this->storage_key, $sections );
            }

            // Hydrate predefined select options stripped from saved builder config.
            if ( function_exists( 'hvnly_hydrate_fields_select_options' ) ) {
                foreach ( $sections as $section_id => &$section ) {
                    if ( ! empty( $section['fields'] ) && is_array( $section['fields'] ) ) {
                        $section['fields'] = hvnly_hydrate_fields_select_options( $section['fields'] );
                    }
                }
                unset( $section );
            }

            // Ensure sections are properly ordered
            uasort($sections, function ($a, $b) {
                return ($a['order'] ?? 0) - ($b['order'] ?? 0);
            });

            $sections_array = array_values($sections);

            return rest_ensure_response([
                'success' => true,
                'data' => $sections_array,
                'has_defaults' => $has_defaults,
                'total_sections' => count($sections_array),
                'storage_key' => $this->storage_key
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('fetch_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Delete all sections (requires confirm=true; blocked when properties exist).
     */
    public function delete_all( $request = null )
    {
        try {
            if ( $request instanceof \WP_REST_Request ) {
                $nonce = $request->get_header( 'X-WP-Nonce' );
                if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                    return new \WP_Error( 'rest_forbidden', 'Invalid nonce', array( 'status' => 403 ) );
                }
                $confirm = rest_sanitize_boolean( $request->get_param( 'confirm' ) );
                if ( ! $confirm || ! function_exists( 'hvnly_rest_deletion_confirmed' ) || ! hvnly_rest_deletion_confirmed( $confirm ) ) {
                    return new \WP_Error(
                        'confirm_required',
                        __( 'Deletion requires confirm=true. Use scan/inventory endpoints first.', 'havenlytics' ),
                        array( 'status' => 400 )
                    );
                }
            }

            if ( class_exists( '\HvnlyNab\Core\DataPreservation\BatchProcessor' ) ) {
                $count = \HvnlyNab\Core\DataPreservation\BatchProcessor::count_properties();
                if ( $count > 0 ) {
                    return new \WP_Error(
                        'properties_exist',
                        sprintf(
                            /* translators: %d: property count */
                            __( 'Cannot delete builder sections while %d properties exist.', 'havenlytics' ),
                            $count
                        ),
                        array( 'status' => 409 )
                    );
                }
            }

            $result = hvnly_safe_delete_option( $this->storage_key, 'admin_confirmed_delete' );

            return rest_ensure_response([
                'success' => true,
                'message' => 'All sections deleted successfully',
                'data' => [],
                'deleted' => $result
            ]);
        } catch (\Exception $e) {
            return new \WP_Error('delete_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Reset to UNIFIED configuration - Uses UnifiedFieldGenerator as SINGLE SOURCE OF TRUTH
     */
    public function reset_unified($request)
    {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', 'Invalid nonce', ['status' => 403]);
            }

            $override = rest_sanitize_boolean( $request->get_param( 'override' ) );
            $property_count = 0;
            if ( class_exists( '\HvnlyNab\Core\DataPreservation\BatchProcessor' ) ) {
                $property_count = \HvnlyNab\Core\DataPreservation\BatchProcessor::count_properties();
            }

            if ( $property_count > 0 ) {
                if ( ! $override || ! function_exists( 'hvnly_rest_deletion_confirmed' ) || ! hvnly_rest_deletion_confirmed( $override ) ) {
                    return new \WP_Error(
                        'properties_exist',
                        sprintf(
                            /* translators: %d: number of properties */
                            __( 'Cannot reset unified builder while %d properties exist. Pass override=true to proceed (meta will be remapped).', 'havenlytics' ),
                            $property_count
                        ),
                        array( 'status' => 409 )
                    );
                }
            }

            $current_config = get_option( $this->storage_key, array() );
            if ( ! is_array( $current_config ) ) {
                $current_config = array();
            }

            if ( class_exists( '\HvnlyNab\Core\DataPreservation\BackupManager' ) ) {
                \HvnlyNab\Core\DataPreservation\BackupManager::create_snapshot( 'reset_unified' );
            }

            $this->reset_generated_ids();
            $remap = array();

            if (class_exists('\HvnlyNab\Core\UnifiedFieldGenerator')) {
                $unified_generator = \HvnlyNab\Core\UnifiedFieldGenerator::get_instance();
                $unified_config = $unified_generator->get_unified_configuration();
            } else {
                $unified_config = $this->build_fallback_config();
            }

            if ( class_exists( '\HvnlyNab\Core\DataPreservation\BuilderMetaMigrator' ) && ! empty( $current_config ) ) {
                $remap = \HvnlyNab\Core\DataPreservation\BuilderMetaMigrator::build_remap_between_configs(
                    $current_config,
                    $unified_config
                );
                if ( ! empty( $remap ) ) {
                    \HvnlyNab\Core\DataPreservation\BuilderMetaMigrator::schedule_property_remap( $remap );
                }
            }

            update_option($this->storage_key, $unified_config);
            wp_cache_delete($this->storage_key, 'options');
            wp_cache_delete( 'hvnly_standardized_field_names' );

            return rest_ensure_response([
                'success' => true,
                'data' => array_values($unified_config),
                'message' => $property_count > 0
                    ? __( 'Reset to unified configuration; property meta remap scheduled.', 'havenlytics' )
                    : 'Reset to unified configuration with UNIQUE group IDs',
                'property_count' => $property_count,
                'meta_remap_scheduled' => ! empty( $remap ?? array() ),
            ]);

        } catch (\Exception $e) {
            return new WP_Error('reset_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Build fallback configuration
     */
    private function build_fallback_config()
    {
        static $building = false;
        if ( ! $building && function_exists( 'hvnly_with_english_ui' ) ) {
            $building = true;
            try {
                return hvnly_with_english_ui( function () {
                    return $this->build_fallback_config();
                } );
            } finally {
                $building = false;
            }
        }

        $video_base = $this->generate_unique_base_id('video');
        $gallery_base = $this->generate_unique_base_id('gallery');
        $map_base = $this->generate_unique_base_id('map');
        $docs_base = $this->generate_unique_base_id('property_docs');
        $faq_base = $this->generate_unique_base_id('faq');
        $repeater_base = $this->generate_unique_base_id('repeater');
        $agents_base = $this->generate_unique_base_id('agents');
        $features_base = $this->generate_unique_base_id('features');
        
        $basic_info_id           = SectionIdentity::SEC_PROPERTY_OVERVIEW;
        $additional_info_id      = SectionIdentity::SEC_PROPERTY_DETAILS;
        $address_neighborhood_id = SectionIdentity::SEC_ADDRESS_NEIGHBORHOOD;
        $video_section_id        = SectionIdentity::SEC_PROPERTY_VIDEO;
        $gallery_section_id      = SectionIdentity::SEC_PROPERTY_GALLERY;
        $location_section_id     = SectionIdentity::SEC_PROPERTY_LOCATION;
        $documents_section_id    = SectionIdentity::SEC_PROPERTY_DOCUMENTS;
        $faq_section_id          = SectionIdentity::SEC_PROPERTY_FAQ;
        $repeater_section_id     = SectionIdentity::SEC_PROPERTY_REPEATER;
        $agents_section_id       = SectionIdentity::SEC_PROPERTY_AGENTS;
        $features_section_id     = SectionIdentity::SEC_PROPERTY_FEATURES;
        
        return [
            $basic_info_id => [
                'id' => $basic_info_id,
                'title' => __( 'Basic Info', 'havenlytics' ),
                'icon' => 'fas fa-home',
                'required' => true,
                'order' => 0,
                'collapsed' => false,
                'fields' => $this->get_basic_info_fields_fallback(),
            ],
            $additional_info_id => [
                'id' => $additional_info_id,
                'title' => __( 'Additional Information', 'havenlytics' ),
                'icon' => 'fas fa-info-circle',
                'required' => false,
                'order' => 1,
                'collapsed' => false,
                'fields' => $this->get_additional_info_fields_fallback(),
            ],
            $address_neighborhood_id => [
                'id' => $address_neighborhood_id,
                'title' => __( 'Address & Neighborhood', 'havenlytics' ),
                'icon' => 'fas fa-building',
                'required' => false,
                'order' => 2,
                'collapsed' => false,
                'fields' => $this->get_address_neighborhood_fields_fallback(),
            ],
            $video_section_id => [
                'id' => $video_section_id,
                'title' => __( 'Property Video', 'havenlytics' ),
                'icon' => 'fas fa-video',
                'required' => false,
                'order' => 3,
                'collapsed' => false,
                'fields' => $this->create_video_group_fields_fallback($video_base),
            ],
            $gallery_section_id => [
                'id' => $gallery_section_id,
                'title' => __( 'Property Gallery', 'havenlytics' ),
                'icon' => 'fas fa-images',
                'required' => false,
                'order' => 4,
                'collapsed' => false,
                'fields' => $this->create_gallery_group_fields_fallback($gallery_base),
            ],
            $location_section_id => [
                'id' => $location_section_id,
                'title' => __( 'Property Location', 'havenlytics' ),
                'icon' => 'fas fa-map-marker-alt',
                'required' => false,
                'order' => 5,
                'collapsed' => false,
                'fields' => $this->create_map_group_fields_fallback($map_base),
            ],
            $documents_section_id => [
                'id' => $documents_section_id,
                'title' => __( 'Property Documents', 'havenlytics' ),
                'icon' => 'fas fa-file-pdf',
                'required' => false,
                'order' => 6,
                'collapsed' => false,
                'fields' => $this->create_documents_group_fields_fallback($docs_base),
            ],
            $faq_section_id => [
                'id' => $faq_section_id,
                'title' => __( 'Frequently Asked Questions', 'havenlytics' ),
                'icon' => 'fas fa-question-circle',
                'required' => false,
                'order' => 7,
                'collapsed' => false,
                'fields' => $this->create_faq_group_fields_fallback($faq_base),
            ],
            $repeater_section_id => [
                'id' => $repeater_section_id,
                'title' => __( 'Property Highlights', 'havenlytics' ),
                'icon' => 'fas fa-list',
                'required' => false,
                'order' => 8,
                'collapsed' => false,
                'fields' => $this->create_repeater_group_fields_fallback($repeater_base),
            ],
            $features_section_id => [
                'id' => $features_section_id,
                'title' => __( 'Property Features', 'havenlytics' ),
                'icon' => 'fas fa-check-square',
                'required' => false,
                'order' => 9,
                'collapsed' => false,
                'fields' => $this->create_features_group_fields_fallback($features_base),
            ],
            $agents_section_id => [
                'id' => $agents_section_id,
                'title' => __( 'Listing Agents', 'havenlytics' ),
                'icon' => 'fas fa-user-tie',
                'required' => false,
                'order' => 10,
                'collapsed' => false,
                'fields' => $this->create_agents_group_fields_fallback($agents_base),
            ],
        ];
    }

    private function get_basic_info_fields_fallback()
    {
        return self::get_canonical_basic_info_fields();
    }

    private function get_additional_info_fields_fallback()
    {
        return [
            ['id' => '_hvnly_property_sqft', 'name' => '_hvnly_property_sqft', 'type' => 'number', 'label' => __( 'Property Area, sq ft', 'havenlytics' ), 'placeholder' => __( 'Enter property area in square feet', 'havenlytics' ), 'required' => true, 'locked' => false, 'enabled' => true, 'order' => 0],
            ['id' => '_hvnly_property_lot_size', 'name' => '_hvnly_property_lot_size', 'type' => 'text', 'label' => __( 'Property Lot size, sq ft', 'havenlytics' ), 'placeholder' => __( 'Enter property lot size', 'havenlytics' ), 'required' => true, 'locked' => false, 'enabled' => true, 'order' => 1],
            ['id' => '_hvnly_property_hoa_fee', 'name' => '_hvnly_property_hoa_fee', 'type' => 'text', 'label' => __( 'Property HOA Fee', 'havenlytics' ), 'placeholder' => __( 'Enter property HOA fee', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 2],
            ['id' => '_hvnly_property_annual_tax_amount', 'name' => '_hvnly_property_annual_tax_amount', 'type' => 'number', 'label' => __( 'Property Annual Tax Amount', 'havenlytics' ), 'placeholder' => __( 'Enter property annual tax amount', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 3],
            ['id' => '_hvnly_property_heating', 'name' => '_hvnly_property_heating', 'type' => 'select', 'label' => __( 'Heating', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 4, 'options' => ['forced_air' => __( 'Forced Air', 'havenlytics' ), 'radiator' => __( 'Radiator', 'havenlytics' ), 'heat_pump' => __( 'Heat Pump', 'havenlytics' ), 'baseboard' => __( 'Baseboard', 'havenlytics' ), 'none' => __( 'None', 'havenlytics' )]],
            ['id' => '_hvnly_property_cooling', 'name' => '_hvnly_property_cooling', 'type' => 'select', 'label' => __( 'Cooling', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 5, 'options' => ['central' => __( 'Central Air', 'havenlytics' ), 'window' => __( 'Window Units', 'havenlytics' ), 'heat_pump' => __( 'Heat Pump', 'havenlytics' ), 'baseboard' => __( 'Baseboard', 'havenlytics' ), 'none' => __( 'None', 'havenlytics' )]],
            ['id' => '_hvnly_property_water', 'name' => '_hvnly_property_water', 'type' => 'select', 'label' => __( 'Water Source', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 6, 'options' => ['city' => __( 'City', 'havenlytics' ), 'well' => __( 'Well', 'havenlytics' ), 'shared_well' => __( 'Shared Well', 'havenlytics' ), 'none' => __( 'None', 'havenlytics' )]],
        ];
    }

    private function get_address_neighborhood_fields_fallback()
    {
        return [
            ['id' => '_hvnly_property_reference_number', 'name' => '_hvnly_property_reference_number', 'type' => 'text', 'label' => __( 'Property Reference Number', 'havenlytics' ), 'placeholder' => __( 'Enter property reference number', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 0],
            ['id' => '_hvnly_property_building_number', 'name' => '_hvnly_property_building_number', 'type' => 'text', 'label' => __( 'Property Building Number', 'havenlytics' ), 'placeholder' => __( 'Enter property building number', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 1],
            ['id' => '_hvnly_property_street', 'name' => '_hvnly_property_street', 'type' => 'text', 'label' => __( 'Property Street', 'havenlytics' ), 'placeholder' => __( 'Enter property street', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 2],
            ['id' => '_hvnly_property_address_line_1', 'name' => '_hvnly_property_address_line_1', 'type' => 'text', 'label' => __( 'Property Address Line 1', 'havenlytics' ), 'placeholder' => __( 'Enter property address line 1', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 3],
            ['id' => '_hvnly_property_address_line_2', 'name' => '_hvnly_property_address_line_2', 'type' => 'text', 'label' => __( 'Property Address Line 2', 'havenlytics' ), 'placeholder' => __( 'Enter property address line 2', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 4],
            ['id' => '_hvnly_property_town_city', 'name' => '_hvnly_property_town_city', 'type' => 'text', 'label' => __( 'Property Town/City', 'havenlytics' ), 'placeholder' => __( 'Enter property town/city', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 5],
            ['id' => '_hvnly_property_country_state', 'name' => '_hvnly_property_country_state', 'type' => 'text', 'label' => __( 'Property Country/State', 'havenlytics' ), 'placeholder' => __( 'Enter property country/state', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 6],
            ['id' => '_hvnly_property_zip_code', 'name' => '_hvnly_property_zip_code', 'type' => 'text', 'label' => __( 'Property Zip Code', 'havenlytics' ), 'placeholder' => __( 'Enter property zip code', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 7],
            ['id' => '_hvnly_property_location', 'name' => '_hvnly_property_location', 'type' => 'select', 'label' => __( 'Property Location', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 8, 'options' => function_exists( 'hvnly_get_location_field_options' ) ? hvnly_get_location_field_options() : []],
            ['id' => '_hvnly_property_country_location', 'name' => '_hvnly_property_country_location', 'type' => 'select', 'label' => __( 'Property Country', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 9, 'options' => function_exists( 'hvnly_get_country_field_options' ) ? hvnly_get_country_field_options() : []],
        ];
    }

    private function create_video_group_fields_fallback($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('video');
        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => __( 'Video Title', 'havenlytics' ), 'placeholder' => __( 'Enter video title', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'video', 'group_name' => __( 'Video Information', 'havenlytics' ), 'group_position' => 0, 'group_total' => 3, 'group_base_id' => $group_base_id, 'metaKey' => 'title'],
            ['id' => $group_base_id . '_url', 'name' => $group_base_id . '_url', 'type' => 'video', 'label' => __( 'Video URL', 'havenlytics' ), 'placeholder' => __( 'Enter video URL', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 1, 'group_id' => $group_id, 'group_type' => 'video', 'group_name' => __( 'Video Information', 'havenlytics' ), 'group_position' => 1, 'group_total' => 3, 'group_base_id' => $group_base_id, 'metaKey' => 'url'],
            ['id' => $group_base_id . '_thumbnail', 'name' => $group_base_id . '_thumbnail', 'type' => 'file', 'label' => __( 'Video Thumbnail', 'havenlytics' ), 'placeholder' => __( 'Upload video thumbnail', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 2, 'group_id' => $group_id, 'group_type' => 'video', 'group_name' => __( 'Video Information', 'havenlytics' ), 'group_position' => 2, 'group_total' => 3, 'group_base_id' => $group_base_id, 'metaKey' => 'thumbnail', 'fileType' => 'image'],
        ];
    }

    private function create_gallery_group_fields_fallback($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('gallery');
        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => __( 'Gallery Title', 'havenlytics' ), 'placeholder' => __( 'Enter gallery title', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'gallery', 'group_name' => __( 'Property Gallery', 'havenlytics' ), 'group_position' => 0, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'title'],
            ['id' => $group_base_id . '_images', 'name' => $group_base_id . '_images', 'type' => 'gallery', 'label' => __( 'Gallery Images', 'havenlytics' ), 'placeholder' => __( 'Upload gallery images', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 1, 'group_id' => $group_id, 'group_type' => 'gallery', 'group_name' => __( 'Property Gallery', 'havenlytics' ), 'group_position' => 1, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'images'],
        ];
    }

    private function create_map_group_fields_fallback($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('map');
        return [
            ['id' => $group_base_id . '_address', 'name' => $group_base_id . '_address', 'type' => 'text', 'label' => __( 'Property Map Address', 'havenlytics' ), 'placeholder' => __( 'Enter property map address', 'havenlytics' ), 'required' => true, 'locked' => false, 'enabled' => true, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => __( 'Map Location', 'havenlytics' ), 'group_position' => 0, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'address'],
            ['id' => $group_base_id . '_latitude', 'name' => $group_base_id . '_latitude', 'type' => 'text', 'label' => __( 'Property Latitude', 'havenlytics' ), 'placeholder' => __( 'Enter property latitude', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 1, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => __( 'Map Location', 'havenlytics' ), 'group_position' => 1, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'latitude', 'hidden' => true],
            ['id' => $group_base_id . '_longitude', 'name' => $group_base_id . '_longitude', 'type' => 'text', 'label' => __( 'Property Longitude', 'havenlytics' ), 'placeholder' => __( 'Enter property longitude', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 2, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => __( 'Map Location', 'havenlytics' ), 'group_position' => 2, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'longitude', 'hidden' => true],
            ['id' => $group_base_id . '_preview', 'name' => $group_base_id . '_preview', 'type' => 'map', 'label' => __( 'Map Preview', 'havenlytics' ), 'placeholder' => '', 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 3, 'group_id' => $group_id, 'group_type' => 'map', 'group_name' => __( 'Map Location', 'havenlytics' ), 'group_position' => 3, 'group_total' => 4, 'group_base_id' => $group_base_id, 'metaKey' => 'preview', 'address_field_name' => $group_base_id . '_address', 'lat_field_name' => $group_base_id . '_latitude', 'lng_field_name' => $group_base_id . '_longitude', 'map_field_name' => $group_base_id . '_preview'],
        ];
    }

    private function create_documents_group_fields_fallback($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('property_docs');
        // THREE FIELDS - icon, label, url
        return [
            [
                'id' => $group_base_id . '_icon',
                'name' => $group_base_id . '_icon',
                'type' => 'text',
                'label' => __( 'Document Icon', 'havenlytics' ),
                'placeholder' => __( 'e.g., file-pdf, file-word, file-lines', 'havenlytics' ),
                'required' => false,
                'locked' => false,
                'enabled' => true,
                'order' => 0,
                'group_id' => $group_id,
                'group_type' => 'property_docs',
                'group_name' => __( 'Property Documents', 'havenlytics' ),
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
                'label' => __( 'Document Label', 'havenlytics' ),
                'placeholder' => __( 'e.g., Floor Plan, Brochure, EPC', 'havenlytics' ),
                'required' => true,
                'locked' => false,
                'enabled' => true,
                'order' => 1,
                'group_id' => $group_id,
                'group_type' => 'property_docs',
                'group_name' => __( 'Property Documents', 'havenlytics' ),
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
                'label' => __( 'Document URL', 'havenlytics' ),
                'placeholder' => __( 'Enter document URL or file path', 'havenlytics' ),
                'required' => true,
                'locked' => false,
                'enabled' => true,
                'order' => 2,
                'group_id' => $group_id,
                'group_type' => 'property_docs',
                'group_name' => __( 'Property Documents', 'havenlytics' ),
                'group_position' => 2,
                'group_total' => 3,
                'group_base_id' => $group_base_id,
                'metaKey' => 'url',
                'group_collapsed' => false,
                'show_in_sidebar' => true,
            ],
        ];
    }

    private function create_faq_group_fields_fallback($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('faq');
        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => __( 'Section Title', 'havenlytics' ), 'placeholder' => __( 'e.g., Frequently Asked Questions', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'faq', 'group_name' => __( 'Frequently Asked Questions', 'havenlytics' ), 'group_position' => 0, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'title', 'show_in_sidebar' => true],
            ['id' => $group_base_id . '_faqs', 'name' => $group_base_id . '_faqs', 'type' => 'faq', 'label' => __( 'FAQ Items', 'havenlytics' ), 'placeholder' => '', 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 1, 'group_id' => $group_id, 'group_type' => 'faq', 'group_name' => __( 'Frequently Asked Questions', 'havenlytics' ), 'group_position' => 1, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'faqs', 'show_in_sidebar' => true],
        ];
    }

    private function create_repeater_group_fields_fallback($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('repeater');
        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => __( 'Section Title', 'havenlytics' ), 'placeholder' => __( 'e.g., Property Highlights', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'repeater', 'group_name' => __( 'Property Highlights', 'havenlytics' ), 'group_position' => 0, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'title', 'show_in_sidebar' => true],
            ['id' => $group_base_id . '_items', 'name' => $group_base_id . '_items', 'type' => 'repeater', 'label' => __( 'Repeater Items', 'havenlytics' ), 'placeholder' => '', 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 1, 'group_id' => $group_id, 'group_type' => 'repeater', 'group_name' => __( 'Property Highlights', 'havenlytics' ), 'group_position' => 1, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'items', 'show_in_sidebar' => true],
        ];
    }

    private function create_agents_group_fields_fallback($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('agents');
        return [
            ['id' => $group_base_id . '_title', 'name' => $group_base_id . '_title', 'type' => 'text', 'label' => __( 'Section Title', 'havenlytics' ), 'placeholder' => __( 'e.g., Listing Agents', 'havenlytics' ), 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'agents', 'group_name' => __( 'Listing Agents', 'havenlytics' ), 'group_position' => 0, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'title', 'show_in_sidebar' => true],
            ['id' => $group_base_id . '_agents', 'name' => $group_base_id . '_agents', 'type' => 'agents', 'label' => __( 'Assigned Agents', 'havenlytics' ), 'placeholder' => '', 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 1, 'group_id' => $group_id, 'group_type' => 'agents', 'group_name' => __( 'Listing Agents', 'havenlytics' ), 'group_position' => 1, 'group_total' => 2, 'group_base_id' => $group_base_id, 'metaKey' => 'agents', 'show_in_sidebar' => true],
        ];
    }

    private function create_features_group_fields_fallback($group_base_id)
    {
        $group_id = $this->generate_unique_group_id('features');
        return [
            ['id' => $group_base_id . '_features', 'name' => $group_base_id . '_features', 'type' => 'checkbox', 'label' => __( 'Property Features', 'havenlytics' ), 'placeholder' => '', 'required' => false, 'locked' => false, 'enabled' => true, 'order' => 0, 'group_id' => $group_id, 'group_type' => 'features', 'group_name' => __( 'Property Features', 'havenlytics' ), 'group_position' => 0, 'group_total' => 1, 'group_base_id' => $group_base_id, 'metaKey' => 'features', 'show_in_sidebar' => true],
        ];
    }

    // Permission callbacks
    public function get_per() { return current_user_can('manage_options'); }
    public function create_per() { return current_user_can('manage_options'); }
    public function del_per() { return current_user_can('manage_options'); }
}