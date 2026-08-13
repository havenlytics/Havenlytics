<?php
/**
 * Field Processing Utility
 *
 * @package Havenlytics
 * @since 2.0.0
 */

namespace HvnlyNab\Utilities;

// Security check
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Class FieldProcessor
 *
 * Utility class for processing field data
 */
class FieldProcessor {

    /**
     * Process field data from property builder
     *
     * @param array $field_data Raw field data
     * @return array Processed field data
     */
    public static function process_field_data( $field_data ) {
        $processed = array();

        foreach ($field_data as $field) {
            // Skip invalid fields
            if ( ! is_array($field) || empty($field['id'])) {
                continue;
            }

            // Process field based on type
            $type            = $field['type'] ?? 'text';
            $processed_field = self::process_field_by_type($field, $type);

            if ($processed_field) {
                $processed[] = $processed_field;
            }
        }

        return $processed;
    }

    /**
     * Process field based on type
     *
     * @param array $field Raw field data
     * @param string $type Field type
     * @return array|null Processed field data
     */
	private static function process_field_by_type( $field, $type ) {
		switch ($type) {
			case 'video':
				return self::process_video_field($field);

			case 'map':
				return self::process_map_field($field);

			case 'gallery':
				return self::process_gallery_field($field);

			case 'document':
				return self::process_document_field($field);

			default:
				return self::process_standard_field($field);
		}
	}
	/**
	 * Process document field
	 *
	 * @param array $field Raw field data
	 * @return array Processed field data
	 */
	private static function process_document_field( $field ) {
		$base_id       = $field['id'];
		$group_id      = $field['group_id'] ?? 'grp_document_' . uniqid();
		$timestamp     = time();
		$unique        = substr(md5($base_id . $timestamp), 0, 8);
		$group_base_id = 'property_docs_' . $timestamp . '_' . $unique;

		// Create related fields for document
		$related_fields = array(
			array(
				'id' => $group_base_id . '_icon',
				'name' => $group_base_id . '_icon',
				'type' => 'text',
				'label' => __('Document Icon', 'havenlytics'),
				'placeholder' => __('e.g., file-pdf, file-word, file-alt', 'havenlytics'),
				'order' => $field['order'] ?? 0,
				'group_id' => $group_id,
				'group_type' => 'document',
				'group_name' => $field['label'] ?? __('Property Document', 'havenlytics'),
				'group_position' => 0,
				'group_total' => 3,
				'group_base_id' => $group_base_id,
				'metaKey' => 'icon',
			),
			array(
				'id' => $group_base_id . '_label',
				'name' => $group_base_id . '_label',
				'type' => 'text',
				'label' => __('Document Label', 'havenlytics'),
				'placeholder' => __('e.g., Floor Plan, Brochure, EPC', 'havenlytics'),
				'required' => true,
				'order' => ( $field['order'] ?? 0 ) + 1,
				'group_id' => $group_id,
				'group_type' => 'document',
				'group_name' => $field['label'] ?? __('Property Document', 'havenlytics'),
				'group_position' => 1,
				'group_total' => 3,
				'group_base_id' => $group_base_id,
				'metaKey' => 'label',
			),
			array(
				'id' => $group_base_id . '_url',
				'name' => $group_base_id . '_url',
				'type' => 'text',
				'label' => __('Document URL', 'havenlytics'),
				'placeholder' => __('Enter document URL or file path', 'havenlytics'),
				'required' => true,
				'order' => ( $field['order'] ?? 0 ) + 2,
				'group_id' => $group_id,
				'group_type' => 'document',
				'group_name' => $field['label'] ?? __('Property Document', 'havenlytics'),
				'group_position' => 2,
				'group_total' => 3,
				'group_base_id' => $group_base_id,
				'metaKey' => 'url',
			),
		);

		// Add show_in_sidebar setting to the first field
		$related_fields[0]['show_in_sidebar'] = $field['show_in_sidebar'] ?? true;

		return $related_fields;
	}
    /**
     * Process video field
     *
     * @param array $field Raw field data
     * @return array Processed field data
     */
    private static function process_video_field( $field ) {
        $base_id = $field['id'];

        // Create related fields for video
        $related_fields = array(
            array(
                'id' => $base_id . '_type',
                'name' => $base_id . '_type',
                'type' => 'select',
                'label' => __('Select Video Type', 'havenlytics'),
                'options' => array(
                    'proprety_youtube' => __('YouTube', 'havenlytics'),
                    'proprety_vimeo' => __('Vimeo', 'havenlytics'),
                ),
                'order' => $field['order'] ?? 0,
            ),
            array(
                'id' => $base_id . '_title',
                'name' => $base_id . '_title',
                'type' => 'text',
                'label' => __('Video Title', 'havenlytics'),
                'placeholder' => __('Enter video title', 'havenlytics'),
                'order' => ( $field['order'] ?? 0 ) + 1,
            ),
            array(
                'id' => $base_id . '_url',
                'name' => $base_id . '_url',
                'type' => 'text',
                'label' => __('Video URL', 'havenlytics'),
                'placeholder' => __('Enter video URL', 'havenlytics'),
                'order' => ( $field['order'] ?? 0 ) + 2,
            ),
            array(
                'id' => $base_id . '_thumbnail',
                'name' => $base_id . '_thumbnail',
                'type' => 'file',
                'label' => __('Video Thumbnail', 'havenlytics'),
                'placeholder' => __('Upload video thumbnail', 'havenlytics'),
                'file_type' => 'image',
                'order' => ( $field['order'] ?? 0 ) + 3,
            ),
        );

        // Mark the first field as the main video field
        $related_fields[0]['is_main_video_field'] = true;

        return $related_fields;
    }

    /**
     * Process map field
     *
     * @param array $field Raw field data
     * @return array Processed field data
     */
    private static function process_map_field( $field ) {
        $base_id = $field['id'];

        // Create related fields for map
        $related_fields = array(
            array(
                'id' => $base_id . '_address',
                'name' => $base_id . '_address',
                'type' => 'text',
                'label' => __('Map Address', 'havenlytics'),
                'placeholder' => __('Enter map address', 'havenlytics'),
                'order' => $field['order'] ?? 0,
            ),
            array(
                'id' => $base_id . '_latitude',
                'name' => $base_id . '_latitude',
                'type' => 'text',
                'label' => __('Latitude', 'havenlytics'),
                'placeholder' => __('Enter latitude', 'havenlytics'),
                'order' => ( $field['order'] ?? 0 ) + 1,
            ),
            array(
                'id' => $base_id . '_longitude',
                'name' => $base_id . '_longitude',
                'type' => 'text',
                'label' => __('Longitude', 'havenlytics'),
                'placeholder' => __('Enter longitude', 'havenlytics'),
                'order' => ( $field['order'] ?? 0 ) + 2,
            ),
            array(
                'id' => $base_id . '_preview',
                'name' => $base_id . '_preview',
                'type' => 'map',
                'label' => __('Map Location', 'havenlytics'),
                'order' => ( $field['order'] ?? 0 ) + 3,
            ),
        );

        // Mark the last field as the main map field
        $related_fields[3]['is_main_map_field'] = true;

        return $related_fields;
    }

    /**
     * Process gallery field
     *
     * @param array $field Raw field data
     * @return array Processed field data
     */
    private static function process_gallery_field( $field ) {
        // Gallery fields are processed as-is
        return $field;
    }

    /**
     * Process standard field
     *
     * @param array $field Raw field data
     * @return array Processed field data
     */
    private static function process_standard_field( $field ) {
        // Standard fields are processed as-is
        return $field;
    }

    /**
     * Flatten complex fields into individual fields
     *
     * @param array $fields Fields array
     * @return array Flattened fields array
     */
    public static function flatten_fields( $fields ) {
        $flattened = array();

        foreach ($fields as $field) {
            if (is_array($field) && isset($field[0])) {
                // This is a complex field with sub-fields
                foreach ($field as $sub_field) {
                    $flattened[] = $sub_field;
                }
            } else {
                // This is a simple field
                $flattened[] = $field;
            }
        }

        return $flattened;
    }

    /**
     * Group fields by type
     *
     * @param array $fields Fields array
     * @return array Grouped fields array
     */
    public static function group_fields_by_type( $fields ) {
        $grouped = array();

        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';

            if ( ! isset($grouped[ $type ])) {
                $grouped[ $type ] = array();
            }

            $grouped[ $type ][] = $field;
        }

        return $grouped;
    }
}
