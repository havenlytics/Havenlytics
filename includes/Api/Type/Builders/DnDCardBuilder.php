<?php

/**
 * Property Card Builder Drag & Drop API Handler
 *
 * Handles REST API endpoints for managing property card builder sections
 * with drag and drop functionality for frontend display.
 *
 * @package HvnlyNab\Api\Type\Builders
 * @since 2.0.0
 */

namespace HvnlyNab\Api\Type\Builders;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * DnDCardBuilder class for handling property card builder sections via REST API
 */
class DnDCardBuilder {

    /**
     * Primary storage key
     *
     * @var string
     */
    private $storage_key = 'hvnly_property_card.sections';

    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'hvnlynab/v1';

    /**
     * Route base
     *
     * @var string
     */
    private $route_base = 'pb-card-builder';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array( $this, 'routes' ));
        add_action('wp_enqueue_scripts', array( $this, 'localize_script' ));
        add_filter('hvnly_persist_card_builder_defaults', array( $this, 'seed_default_sections' ));
    }

    /**
     * Localize script for React components
     */
    public function localize_script() {
        wp_localize_script('hvnly-builder', 'hvnlyApiSettings', array(
            'nonce' => wp_create_nonce('wp_rest'),
            'root' => esc_url_raw(rest_url()),
            'namespace' => $this->namespace,
        ));
    }

    /**
     * Register REST API routes
     */
    public function routes() {
        // GET all sections
        register_rest_route($this->namespace, '/' . $this->route_base, array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_all' ),
            'permission_callback' => array( $this, 'get_per' ),
        ));

        // POST save all sections
        register_rest_route($this->namespace, '/' . $this->route_base, array(
            'methods' => 'POST',
            'callback' => array( $this, 'save_all' ),
            'permission_callback' => array( $this, 'create_per' ),
        ));

        // DELETE all sections
        register_rest_route($this->namespace, '/' . $this->route_base, array(
            'methods' => 'DELETE',
            'callback' => array( $this, 'delete_all' ),
            'permission_callback' => array( $this, 'del_per' ),
        ));

        // POST reset to bundled defaults (safe when properties exist)
        register_rest_route($this->namespace, '/' . $this->route_base . '/reset', array(
            'methods' => 'POST',
            'callback' => array( $this, 'reset_defaults' ),
            'permission_callback' => array( $this, 'create_per' ),
        ));
    }

    /**
     * Save all sections at once
     */
    public function save_all( $request ) {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if ( ! wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', 'Invalid nonce', array( 'status' => 403 ));
            }

            $params = $request->get_json_params();

            // Handle both formats: direct sections array or nested under 'sections'
            $sections_data = array();
            if (isset($params['sections']) && is_array($params['sections'])) {
                $sections_data = $params['sections'];
            } elseif (isset($params[0]) && is_array($params[0])) {
                $sections_data = $params;
            } else {
                return new WP_Error('invalid_data', 'Sections data is required', array( 'status' => 400 ));
            }

            $sections = $this->build_sections_storage($sections_data);
            if ( function_exists( 'hvnly_canonicalize_ui_tree' ) ) {
                $sections = hvnly_canonicalize_ui_tree( $sections );
            }
            update_option($this->storage_key, $sections);

            return rest_ensure_response(array(
                'success' => true,
                'data' => array_values($sections),
                'message' => 'Property card layout saved successfully',
                'saved_count' => count($sections),
                'storage_key' => $this->storage_key,
            ));
        } catch (\Exception $e) {
            return new WP_Error('save_failed', $e->getMessage(), array( 'status' => 500 ));
        }
    }

    /**
     * Fetch all sections - ALWAYS returns defaults on first install
     */
    public function get_all( $request ) {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if ( ! wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', 'Invalid nonce', array( 'status' => 403 ));
            }

            $sections = get_option($this->storage_key, array());

            // If no sections exist in database, return defaults
            if (empty($sections)) {
                $default_sections = $this->get_default_sections();
                $default_sections = $this->ensure_legacy_sections($default_sections);

                return rest_ensure_response(array(
                    'success' => true,
                    'data' => $default_sections,
                    'has_defaults' => true,
                    'total_sections' => count($default_sections),
                    'storage_key' => $this->storage_key,
                    'message' => 'Default card layout loaded',
                ));
            }

            // Ensure sections are properly ordered by their order property
            uasort($sections, function ( $a, $b ) {
                return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
            });

            $sections_array = array_values($sections);

            $sections_array = $this->ensure_legacy_sections($sections_array);

            return rest_ensure_response(array(
                'success' => true,
                'data' => $sections_array,
                'has_defaults' => false,
                'total_sections' => count($sections_array),
                'storage_key' => $this->storage_key,
            ));
        } catch (\Exception $e) {
            return new WP_Error('fetch_failed', $e->getMessage(), array( 'status' => 500 ));
        }
    }

    /**
     * Persist bundled default card sections to the database.
     *
     * Used on first property import so listing cards match the default
     * layout instead of any stale configuration left from prior testing.
     *
     * @param bool $unused Filter passthrough (ignored).
     * @return bool True when defaults were saved.
     */
    public function seed_default_sections( $unused = false ) {
        $sections = $this->build_sections_storage($this->get_default_sections());
        $saved    = update_option($this->storage_key, $sections);
        wp_cache_delete($this->storage_key, 'options');

        return (bool) $saved;
    }

    /**
     * Default card field types (mirrors builder fieldTypes.js + archive template mapping).
     *
     * @var array<int, string>
     */
    private static $allowed_card_field_types = array(
        'image',
        'carousel',
        'badge',
        'status',
        'time',
        'favorite',
        'compare',
        'views',
        'title',
        'rating',
        'phone',
        'email',
        'website',
        'address',
        'datetime',
        'price',
        'price-per-sqft',
        'beds',
        'baths',
        'kitchens',
        'floor',
        'rooms',
        'receptions',
        'sqft',
        'gsft',
        'button',
        'view-btn',
        'schedule-tour-button',
        'feature',
        'excerpt',
        'location',
        'schedule-tour',
        'property_type',
        'avatar',
        'tags',
        'garage',
        'comments',
        'url',
        'date',
        'faq-count',
        'repeater-count',
    );

    /**
     * Return bundled card sections in keyed option storage format.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function get_default_sections_for_storage() {
        $instance = new self();

        return $instance->build_sections_storage( $instance->get_default_sections() );
    }

    /**
     * Allowed card field types for save sanitization.
     *
     * @return array<int, string>
     */
    private static function get_allowed_card_field_types() {
        /**
         * Filter allowed Property Card Builder field types (additive for Pro).
         *
         * @param array<int, string> $types Field type slugs.
         */
        $types = apply_filters( 'hvnly_card_builder_field_types', self::$allowed_card_field_types );

        if ( ! is_array( $types ) ) {
            return self::$allowed_card_field_types;
        }

        $clean = array();
        foreach ( $types as $type ) {
            if ( is_string( $type ) && '' !== $type ) {
                $clean[] = sanitize_key( $type );
            }
        }

        return array_values( array_unique( $clean ) );
    }

    /**
     * Normalize a list of sections into the keyed option storage format.
     *
     * @param array $sections_data Indexed array of section definitions.
     * @return array Sections keyed by section ID.
     */
    private function build_sections_storage( array $sections_data ) {
        $sections = array();

        foreach ($sections_data as $index => $section) {
            if ( ! is_array($section)) {
                continue;
            }

            $id = sanitize_text_field($section['id'] ?? 'sec_' . uniqid());

            $sections[ $id ] = array(
                'id' => $id,
                'title' => sanitize_text_field($section['title'] ?? 'Untitled Section'),
                'icon' => sanitize_text_field($section['icon'] ?? 'fas fa-home'),
                'mode' => sanitize_text_field($section['mode'] ?? 'both'),
                'fields' => $this->sanitize_fields($section['fields'] ?? array()),
                'order' => intval($section['order'] ?? $index),
            );
        }

        return $sections;
    }

    /**
     * Get default sections
     *
     * @return array
     */
    private function get_default_sections() {
        static $building = false;
        if ( ! $building && function_exists( 'hvnly_with_english_ui' ) ) {
            $building = true;
            try {
                return hvnly_with_english_ui( function () {
                    return $this->get_default_sections();
                } );
            } finally {
                $building = false;
            }
        }

        return array(
            array(
                'id' => 'image-overlay-top-left',
                'title' => __('Image Overlay Left', 'havenlytics'),
                'icon' => 'fas fa-tags',
                'mode' => 'preset',
                'order' => 0,
                'fields' => array(
                    array(
                        'id' => 'tags-overlay',
                        'type' => 'badge',
                        'label' => __('Badge', 'havenlytics'),
                        'mode' => 'preset',
                        'value' => array(
							'text' => __('Featured', 'havenlytics'),
							'style' => 'primary',
						),
                        'order' => 0,
                    ),
                ),
            ),
            array(
                'id' => 'image-overlay-top-right',
                'title' => __('Image Overlay Right', 'havenlytics'),
                'icon' => 'fas fa-heart',
                'mode' => 'preset',
                'order' => 1,
                'fields' => array(
                    array(
                        'id' => 'favorite-button',
                        'type' => 'favorite',
                        'label' => __('Favorite Button', 'havenlytics'),
                        'mode' => 'preset',
                        'value' => array( 'isFavorite' => false ),
                        'order' => 0,
                    ),
                    array(
                        'id' => 'compare-button',
                        'type' => 'compare',
                        'label' => __('Compare Button', 'havenlytics'),
                        'mode' => 'preset',
                        'value' => array(),
                        'order' => 1,
                    ),
                ),
            ),
            array(
                'id' => 'thumbnail',
                'title' => __('Thumbnail', 'havenlytics'),
                'icon' => 'fas fa-image',
                'mode' => 'preset',
                'order' => 2,
                'fields' => array(
                    array(
                        'id' => 'thumbnail-image',
                        'type' => 'image',
                        'label' => __('Thumbnail Image', 'havenlytics'),
                        'mode' => 'preset',
                        'value' => array(
							'type' => 'single',
							'url' => '',
						),
                        'order' => 0,
                    ),
                ),
            ),
            array(
                'id' => 'image-overlay-bottom-left',
                'title' => __('Image Overlay Bottom Left', 'havenlytics'),
                'icon' => 'fas fa-dollar-sign',
                'mode' => 'preset',
                'order' => 3,
                'fields' => array(
                    array(
                        'id' => 'price-button',
                        'type' => 'price',
                        'label' => __('Price', 'havenlytics'),
                        'mode' => 'preset',
                        'value' => array(
							'value' => '1,250,000',
							'currency' => 'USD',
						),
                        'order' => 0,
                    ),
                ),
            ),
            array(
                'id' => 'image-overlay-bottom-right',
                'title' => __('Image Overlay Bottom Right', 'havenlytics'),
                'icon' => 'fas fa-image',
                'mode' => 'preset',
                'order' => 4,
                'fields' => array(),
            ),
            array(
                'id' => 'avatar',
                'title' => __('Avatar', 'havenlytics'),
                'icon' => 'fas fa-user',
                'mode' => 'both',
                'order' => 5,
                'fields' => array(),
            ),

            array(
                'id' => 'card-title',
                'title' => __('Card Title', 'havenlytics'),
                'icon' => 'fas fa-heading',
                'mode' => 'both',
                'order' => 6,
                'fields' => array(
                    array(
                        'id' => 'title-text',
                        'type' => 'title',
                        'label' => __('Property Title', 'havenlytics'),
                        'mode' => 'both',
                        'value' => array( 'text' => __('Luxury Villa with Ocean View', 'havenlytics') ),
                        'order' => 0,
                    ),

                    array(
                        'id' => 'title-property-location',
                        'type' => 'location',
                        'label' => __('Location', 'havenlytics'),
                        'mode' => 'both',
                        'value' => array( 'text' => __('123 Ocean View Drive, Malibu, CA', 'havenlytics') ),
                        'order' => 3,
                    ),
                ),
            ),
            array(
                'id' => 'card-content',
                'title' => __('Card Content', 'havenlytics'),
                'icon' => 'fas fa-list',
                'mode' => 'preset',
                'order' => 7,
                'fields' => array(
                    array(
                        'id' => 'phone-number',
                        'type' => 'phone',
                        'label' => __('Phone', 'havenlytics'),
                        'mode' => 'preset',
                        'value' => array( 'number' => '+1 234 567 8900' ),
                        'order' => 0,
                    ),
                    array(
                        'id' => 'email-address',
                        'type' => 'email',
                        'label' => __('Email', 'havenlytics'),
                        'mode' => 'preset',
                        'value' => array( 'address' => 'contact@example.com' ),
                        'order' => 1,
                    ),
                    array(
                        'id' => 'website-url',
                        'type' => 'website',
                        'label' => __('Website', 'havenlytics'),
                        'mode' => 'preset',
                        'value' => array( 'url' => 'www.example.com' ),
                        'order' => 2,
                    ),
                    array(
                        'id' => 'content-posted-datetime',
                        'type' => 'datetime',
                        'label' => __('Date/Time', 'havenlytics'),
                        'mode' => 'preset',
                        'value' => array( 'text' => __('Posted 2 days ago', 'havenlytics') ),
                        'order' => 3,
                    ),
                ),
            ),

            array(
                'id' => 'features',
                'title' => __('Features', 'havenlytics'),
                'icon' => 'fas fa-star',
                'mode' => 'both',
                'order' => 9,
                'fields' => array(
                    array(
                        'id' => 'feature-beds',
                        'type' => 'beds',
                        'label' => __('Beds', 'havenlytics'),
                        'mode' => 'both',
                        'value' => array( 'value' => '3' ),
                        'order' => 0,
                    ),
                    array(
                        'id' => 'feature-baths',
                        'type' => 'baths',
                        'label' => __('Baths', 'havenlytics'),
                        'mode' => 'both',
                        'value' => array( 'value' => '2' ),
                        'order' => 1,
                    ),
                    array(
                        'id' => 'feature-receptions',
                        'type' => 'receptions',
                        'label' => __('Receptions', 'havenlytics'),
                        'mode' => 'both',
                        'value' => array( 'value' => '2' ),
                        'order' => 2,
                    ),
                ),
            ),
            array(
                'id' => 'excerpt',
                'title' => __('Excerpt', 'havenlytics'),
                'icon' => 'fas fa-align-left',
                'mode' => 'both',
                'order' => 10,
                'fields' => array(
                    array(
                        'id' => 'property-excerpt',
                        'type' => 'excerpt',
                        'label' => __('Excerpt', 'havenlytics'),
                        'mode' => 'both',
                        'value' => array( 'text' => __('Stunning oceanfront villa with panoramic views, modern amenities, and luxurious finishes. Features include infinity pool, home theater, and smart home technology.', 'havenlytics') ),
                        'order' => 0,
                    ),
                ),
            ),
            array(
                'id' => 'location',
                'title' => __('Location', 'havenlytics'),
                'icon' => 'fas fa-map-marker-alt',
                'mode' => 'both',
                'order' => 11,
                'fields' => array(
                    array(
                        'id' => 'property-location',
                        'type' => 'location',
                        'label' => __('Location', 'havenlytics'),
                        'mode' => 'both',
                        'value' => array( 'text' => __('123 Ocean View Drive, Malibu, CA', 'havenlytics') ),
                        'order' => 0,
                    ),
                ),
            ),
            array(
                'id' => 'card-footer-left',
                'title' => __('Card Footer Left', 'havenlytics'),
                'icon' => 'fas fa-folder',
                'mode' => 'both',
                'order' => 12,
                'fields' => array(
                    array(
                        'id' => 'view-property-button',
                        'type' => 'view-btn',
                        'label' => __('View Property Button', 'havenlytics'),
                        'mode' => 'both',
                        'value' => array(
							'text' => __('View Property', 'havenlytics'),
							'style' => 'primary',
							'type' => 'view-property',
						),
                        'order' => 0,
                    ),
                ),
            ),
            array(
                'id' => 'card-footer-right',
                'title' => __('Card Footer Right', 'havenlytics'),
                'icon' => 'fas fa-eye',
                'mode' => 'both',
                'order' => 13,
                'fields' => array(

                    array(
                        'id' => 'view-count',
                        'type' => 'views',
                        'label' => __('Views', 'havenlytics'),
                        'mode' => 'both',
                        'value' => array( 'count' => 245 ),
                        'order' => 3,
                    ),
                ),
            ),
        );
    }

    /**
     * Reset card layout to bundled defaults (does not require empty site).
     */
    public function reset_defaults( $request ) {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if ( ! wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', 'Invalid nonce', array( 'status' => 403 ));
            }

            $confirm = rest_sanitize_boolean($request->get_param('confirm'));
            if ( ! $confirm || ! function_exists('hvnly_rest_deletion_confirmed') || ! hvnly_rest_deletion_confirmed($confirm)) {
                return new WP_Error(
                    'confirm_required',
                    __('Reset requires confirm=true.', 'havenlytics'),
                    array( 'status' => 400 )
                );
            }

            $this->seed_default_sections(true);

            $sections = get_option($this->storage_key, array());
            if ( ! is_array($sections)) {
                $sections = array();
            }

            uasort($sections, function ( $a, $b ) {
                return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
            });

            $sections_array = array_values($sections);
            $sections_array = $this->ensure_legacy_sections($sections_array);

            return rest_ensure_response(array(
                'success' => true,
                'data' => $sections_array,
                'message' => __('Property card layout reset to defaults successfully.', 'havenlytics'),
                'total_sections' => count($sections_array),
                'storage_key' => $this->storage_key,
            ));
        } catch (\Exception $e) {
            return new WP_Error('reset_failed', $e->getMessage(), array( 'status' => 500 ));
        }
    }

    /**
     * Delete all sections (requires confirm=true; blocked when properties exist).
     */
    public function delete_all( $request ) {
        try {
            $nonce = $request->get_header('X-WP-Nonce');
            if ( ! wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('rest_forbidden', 'Invalid nonce', array( 'status' => 403 ));
            }

            $confirm = rest_sanitize_boolean( $request->get_param( 'confirm' ) );
            if ( ! $confirm || ! function_exists( 'hvnly_rest_deletion_confirmed' ) || ! hvnly_rest_deletion_confirmed( $confirm ) ) {
                return new WP_Error(
                    'confirm_required',
                    __( 'Deletion requires confirm=true.', 'havenlytics' ),
                    array( 'status' => 400 )
                );
            }

            if ( class_exists( '\HvnlyNab\Core\DataPreservation\BatchProcessor' ) ) {
                $count = \HvnlyNab\Core\DataPreservation\BatchProcessor::count_properties();
                if ( $count > 0 ) {
                    return new WP_Error(
                        'properties_exist',
                        sprintf(
                            /* translators: %d is the number of properties that exist. */
                            __( 'Cannot delete card layout while %d properties exist.', 'havenlytics' ),
                            $count
                        ),
                        array( 'status' => 409 )
                    );
                }
            }

            $result = hvnly_safe_delete_option( $this->storage_key, 'admin_confirmed_delete' );

            return rest_ensure_response(array(
                'success' => true,
                'message' => 'Property card layout deleted successfully',
                'data' => array(),
                'deleted' => $result,
            ));
        } catch (\Exception $e) {
            return new WP_Error('delete_failed', $e->getMessage(), array( 'status' => 500 ));
        }
    }

    /**
     * Ensure backward-compatible card sections exist for legacy saved layouts.
     *
     * @param array $sections_data Indexed section list.
     * @return array
     */
    private function ensure_legacy_sections( array $sections_data ) {
        $has_avatar = false;

        foreach ($sections_data as $section) {
            if (is_array($section) && ( $section['id'] ?? '' ) === 'avatar') {
                $has_avatar = true;
                break;
            }
        }

        if ( ! $has_avatar) {
            $sections_data[] = array(
                'id' => 'avatar',
                'title' => __('Avatar', 'havenlytics'),
                'icon' => 'fas fa-user',
                'mode' => 'both',
                'order' => 5,
                'fields' => array(),
            );
        }

        usort($sections_data, function ( $a, $b ) {
            return ( $a['order'] ?? 0 ) - ( $b['order'] ?? 0 );
        });

        if (function_exists('hvnly_ensure_compare_beside_favorite')) {
            $sections_data = array_values(hvnly_ensure_compare_beside_favorite($sections_data));
        }

        return $sections_data;
    }

    /**
     * Sanitize fields array
     */
    private function sanitize_fields( $fields ) {
        if ( ! is_array($fields)) {
            return array();
        }

        $sanitized_fields = array();

        foreach ($fields as $index => $field) {
            if ( ! is_array($field)) {
                continue;
            }

            $field_type = sanitize_key( (string) ( $field['type'] ?? 'text' ));
            if ( ! in_array($field_type, self::get_allowed_card_field_types(), true)) {
                continue;
            }

            $sanitized_field = array(
                'id' => sanitize_text_field($field['id'] ?? 'fld_' . uniqid()),
                'type' => $field_type,
                'label' => sanitize_text_field($field['label'] ?? 'Untitled Field'),
                'mode' => sanitize_text_field($field['mode'] ?? 'both'),
                'value' => $this->sanitize_field_value($field['value'] ?? array()),
                'order' => intval($field['order'] ?? $index),
            );

            $sanitized_fields[] = $sanitized_field;
        }

        return $sanitized_fields;
    }

    /**
     * Sanitize field value
     */
    private function sanitize_field_value( $value ) {
        if (is_array($value)) {
            return array_map(function ( $item ) {
                if (is_array($item)) {
                    return $this->sanitize_field_value($item);
                }
                return sanitize_text_field($item);
            }, $value);
        }

        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value;
        }

        return array();
    }

    /**
     * Permission callback for GET requests
     */
    public function get_per() {
        return current_user_can('manage_options');
    }

    /**
     * Permission callback for POST requests
     */
    public function create_per() {
        return current_user_can('manage_options');
    }

    /**
     * Permission callback for DELETE requests
     */
    public function del_per() {
        return current_user_can('manage_options');
    }
}
