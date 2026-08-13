<?php
/**
 * Price on Call Text REST API Endpoint
 *
 * Handles CRUD operations for custom price on call text options.
 *
 * @package HvnlyNab\Api\Type\Settings
 * @since 2.1.3
 */

namespace HvnlyNab\Api\Type\Settings;

use WP_REST_Request;
use WP_REST_Response;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

class PriceOnCallTextAPI {

    private $namespace           = 'hvnlynab/v1';
    private $route_base          = 'price-on-call-texts';
    private $option_key          = 'hvnly_price_on_call_custom_options';
    private const DEFAULT_MAX_ID = 5;

    public function __construct() {
        add_action('rest_api_init', array( $this, 'routes' ), 5);
    }

    public function routes() {
        // GET all - anyone can read
        register_rest_route($this->namespace, '/' . $this->route_base, array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_all' ),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));

        // POST create
        register_rest_route($this->namespace, '/' . $this->route_base, array(
            'methods' => 'POST',
            'callback' => array( $this, 'create' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args' => array(
                'label' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // PUT update
        register_rest_route($this->namespace, '/' . $this->route_base . '/(?P<id>[0-9]+)', array(
            'methods' => 'PUT',
            'callback' => array( $this, 'update' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args' => array(
                'label' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // DELETE
        register_rest_route($this->namespace, '/' . $this->route_base . '/(?P<id>[0-9]+)', array(
            'methods' => 'DELETE',
            'callback' => array( $this, 'delete' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ));
    }

    /**
     * Check permission for write operations
     */
    public function check_permission(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Get default options
     */
    private function get_default_options(): array {
        return array(
            array(
				'id' => 1,
				'label' => __('None', 'havenlytics'),
				'value' => 'priceOnCallNone',
				'is_default' => 1,
				'order' => 1,
			),
            array(
				'id' => 2,
				'label' => __('Price On Call', 'havenlytics'),
				'value' => 'priceOnCall',
				'is_default' => 1,
				'order' => 2,
			),
            array(
				'id' => 3,
				'label' => __('Fixed Price', 'havenlytics'),
				'value' => 'fixedPrice',
				'is_default' => 1,
				'order' => 3,
			),
            array(
				'id' => 4,
				'label' => __('Guide Price', 'havenlytics'),
				'value' => 'guidePrice',
				'is_default' => 1,
				'order' => 4,
			),
            array(
				'id' => 5,
				'label' => __('Offers Over', 'havenlytics'),
				'value' => 'offersOver',
				'is_default' => 1,
				'order' => 5,
			),
        );
    }

    /**
     * Get all options (default + custom)
     */
    private function get_all_options(): array {
        $defaults = $this->get_default_options();
        $custom   = get_option($this->option_key, array());

        $all_options = $defaults;

        if ( ! empty($custom) && is_array($custom)) {
            $max_default_id = self::DEFAULT_MAX_ID;
            $next_id        = $max_default_id + 1;

            foreach ($custom as $custom_option) {
                if ( ! isset($custom_option['id'])) {
                    $custom_option['id'] = $next_id++;
                }
                $custom_option['is_default'] = 0;
                // Clean up label
                if (isset($custom_option['label'])) {
                    $custom_option['label'] = trim(preg_replace('/\s+/', ' ', $custom_option['label']));
                }
                $all_options[] = $custom_option;
            }
        }

        usort($all_options, function ( $a, $b ) {
            return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
        });

        return $all_options;
    }

    /**
     * Get all price on call texts
     */
    public function get_all( WP_REST_Request $request ): WP_REST_Response {
        $options = $this->get_all_options();

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $options,
        ), 200);
    }

    /**
     * Create a new price on call text
     */
    public function create( $request ): WP_REST_Response {
        $params = $request->get_json_params();

        if (empty($params['label'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Label is required', 'havenlytics'),
            ), 400);
        }

        $label = sanitize_text_field($params['label']);
        $value = sanitize_title($label);

        // Remove 'priceoncall' from value if it appears (prevent duplicates)
        $value = str_replace('priceoncall', '', $value);
        $value = trim($value, '-');

        // Check if value already exists
        $existing_options = $this->get_all_options();
        foreach ($existing_options as $option) {
            if ($option['value'] === $value) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('Option already exists', 'havenlytics'),
                ), 409);
            }
        }

        $custom_options = get_option($this->option_key, array());

        $max_id    = self::DEFAULT_MAX_ID;
        $max_order = self::DEFAULT_MAX_ID;

        foreach ($custom_options as $opt) {
            if (isset($opt['id']) && $opt['id'] > $max_id) {
                $max_id = $opt['id'];
            }
            if (isset($opt['order']) && $opt['order'] > $max_order) {
                $max_order = $opt['order'];
            }
        }

        $new_id    = $max_id + 1;
        $new_order = $max_order + 1;

        $new_option = array(
            'id' => $new_id,
            'label' => $label,
            'value' => $value,
            'is_default' => 0,
            'order' => $new_order,
        );

        $custom_options[] = $new_option;
        update_option($this->option_key, $custom_options, false);

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $new_option,
            'message' => sprintf(
                /* translators: %s: price on call label */
                __('"%s" created successfully', 'havenlytics'), $label),
        ), 201);
    }

    /**
     * Update a price on call text
     */
    public function update( $request ): WP_REST_Response {
        $id     = absint($request->get_param('id'));
        $params = $request->get_json_params();

        if (empty($params['label'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Label is required', 'havenlytics'),
            ), 400);
        }

        // Prevent modification of default options
        if ($id >= 1 && $id <= self::DEFAULT_MAX_ID) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Default options cannot be modified', 'havenlytics'),
            ), 403);
        }

        $custom_options = get_option($this->option_key, array());

        if ( ! is_array($custom_options)) {
            $custom_options = array();
        }

        $found_index = -1;
        $old_option  = null;

        foreach ($custom_options as $index => $option) {
            if (isset($option['id']) && (int) $option['id'] === $id) {
                $found_index = $index;
                $old_option  = $option;
                break;
            }
        }

        if ($found_index === -1) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Option not found', 'havenlytics'),
            ), 404);
        }

        $label     = sanitize_text_field($params['label']);
        $new_value = sanitize_title($label);

        // Clean up the value
        $new_value = str_replace('priceoncall', '', $new_value);
        $new_value = trim($new_value, '-');

        // Prevent duplicate VALUE
        foreach ($custom_options as $opt) {
            if (
                isset($opt['value'], $opt['id']) &&
                $opt['value'] === $new_value &&
                (int) $opt['id'] !== $id
            ) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('Option already exists', 'havenlytics'),
                ), 409);
            }
        }

        // Clean the label
        $clean_label = trim(preg_replace('/\s+/', ' ', $label));

        // Update option
        $custom_options[ $found_index ]['label'] = $clean_label;
        $custom_options[ $found_index ]['value'] = $new_value;

        update_option($this->option_key, $custom_options, false);

        // Update all properties that were using the OLD value to use the NEW value
        if ($old_option && $old_option['value'] !== $new_value) {
            $this->update_property_price_on_call_texts($old_option['value'], $new_value, $clean_label);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'data' => $custom_options[ $found_index ],
            'message' => sprintf(
                /* translators: %s: price on call label */
                __('"%s" updated successfully', 'havenlytics'), $clean_label),
        ), 200);
    }

    /**
     * Delete a price on call text
     */
    public function delete( $request ): WP_REST_Response {
        $id = absint($request->get_param('id'));

        // Prevent deleting default options
        if ($id >= 1 && $id <= self::DEFAULT_MAX_ID) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Default options cannot be deleted', 'havenlytics'),
            ), 403);
        }

        $custom_options = get_option($this->option_key, array());

        if ( ! is_array($custom_options)) {
            $custom_options = array();
        }

        $found_index    = -1;
        $deleted_option = null;

        foreach ($custom_options as $index => $option) {
            if (isset($option['id']) && (int) $option['id'] === $id) {
                $found_index    = $index;
                $deleted_option = $option;
                break;
            }
        }

        if ($found_index === -1 || ! $deleted_option) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Option not found', 'havenlytics'),
            ), 404);
        }

        // Remove option safely
        unset($custom_options[ $found_index ]);

        // Re-index array
        $custom_options = array_values($custom_options);

        // Reorder properly (keep stable ordering)
        foreach ($custom_options as $index => &$option) {
            if (isset($option['is_default']) && $option['is_default']) {
                continue;
            }
            $option['order'] = $index + 6;
        }
        unset($option);

        update_option($this->option_key, $custom_options, false);

        // Sync property meta safely
        if ( ! empty($deleted_option['value'])) {
            $this->update_property_price_on_call_texts(
                $deleted_option['value'],
                'priceOnCallNone',
                __('Price on Request', 'havenlytics')
            );
        }

        // Fix plugin settings safely
        $settings = get_option('hvnly_plugin_settings', array());

        if ( ! is_array($settings)) {
            $settings = array();
        }

        $current = isset($settings['general']['hvnly_priceOnCallText']) ? $settings['general']['hvnly_priceOnCallText'] : null;

        if ($current === ( $deleted_option['value'] ?? null )) {
            $settings['general']['hvnly_priceOnCallText'] = 'priceOnCallNone';
            update_option('hvnly_plugin_settings', $settings);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => sprintf(
                /* translators: %s: price on call label */
                __('"%s" deleted successfully', 'havenlytics'),
                isset($deleted_option['label']) ? $deleted_option['label'] : ''
            ),
            'data' => array(
                'id' => $id,
                'replaced_with' => 'priceOnCallNone',
            ),
        ), 200);
    }

    /**
     * Update all properties that use a specific price on call text value
     * This ensures frontend displays the updated/deleted option correctly
     * FIXED: Also handles JSON stored values from price_label field
     *
     * @param string $old_value The old price on call text value
     * @param string $new_value The new price on call text value to replace with
     * @param string $new_label The new label to use in JSON
     * @return void
     */
    private function update_property_price_on_call_texts( string $old_value, string $new_value, string $new_label = '' ): void {
        global $wpdb;

        // Update properties with direct meta value (old format)
        $properties_direct = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_hvnly_property_price' 
                AND meta_value = %s",
                $old_value
            )
        );

        // Update properties with JSON stored value (new format from price_label field)
        $json_pattern    = '%"value":"' . $old_value . '"%';
        $properties_json = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_hvnly_property_price' 
                AND meta_value LIKE %s",
                $json_pattern
            )
        );

        // Merge and deduplicate
        $properties = array_unique(array_merge($properties_direct, $properties_json));

        if (empty($properties)) {
            return;
        }

        // Update each property's price meta to the new value
        foreach ($properties as $property_id) {
            $current_value = get_post_meta($property_id, '_hvnly_property_price', true);

            if ( ! empty($current_value) && is_string($current_value) && $current_value[0] === '{') {
                // It's a JSON stored custom label
                $decoded = json_decode($current_value, true);
                if (is_array($decoded) && isset($decoded['value']) && $decoded['value'] === $old_value) {
                    // Update the JSON with new value and label
                    $decoded['value'] = $new_value;
                    if ( ! empty($new_label)) {
                        $decoded['label'] = $new_label;
                    }
                    $new_json = wp_json_encode($decoded);
                    update_post_meta($property_id, '_hvnly_property_price', $new_json);
                }
            } else {
                // Direct value
                update_post_meta($property_id, '_hvnly_property_price', $new_value);
            }
        }

        // Targeted invalidation — price-label text affects listing presentation, not every cache layer.
        if (function_exists('HVNLY_NAB') && HVNLY_NAB()->engine()) {
            HVNLY_NAB()->engine()->clear_transients_by_pattern('search_');
            HVNLY_NAB()->engine()->clear_transients_by_pattern('sidebar_');
        }
        if (class_exists('\HvnlyNab\Frontend\Query\PropertyQueryCache')) {
            \HvnlyNab\Frontend\Query\PropertyQueryCache::invalidate_all();
        }
    }
}
