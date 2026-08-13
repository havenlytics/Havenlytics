<?php
/**
 * Base Field Type Interface
 *
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

// Security check
if ( ! defined('ABSPATH')) {
    exit;
}

/**
 * Interface FieldTypeInterface
 */
interface FieldTypeInterface {
    /**
     * Render field HTML
     *
     * @param array $field Field configuration
     * @param mixed $value Current field value
     * @param int $post_id Post ID
     * @return string HTML output
     */
    public function render( $field, $value, $post_id );

    /**
     * Save field data
     *
     * @param int $post_id Post ID
     * @param string $field_name Field name
     * @param mixed $value Field value
     * @return void
     */
    public function save( $post_id, $field_name, $value );

    /**
     * Sanitize field value
     *
     * @param mixed $value Field value
     * @return mixed Sanitized value
     */
    public function sanitize( $value );

    /**
     * Validate field value
     *
     * @param mixed $value Field value
     * @param array $field Field configuration
     * @return bool|WP_Error True if valid, WP_Error otherwise
     */
    public function validate( $value, $field );

    /**
     * Get field type
     *
     * @return string Field type
     */
    public function get_type();

    /**
     * Check if field requires assets
     *
     * @return bool True if requires assets
     */
    public function requires_assets();

    /**
     * Enqueue field-specific assets
     *
     * @return void
     */
    public function enqueue_assets();
}

/**
 * Abstract Base Field Type
 */
abstract class BaseFieldType implements FieldTypeInterface {
    protected $type;
    protected $requires_assets = false;

    public function __construct( $type ) {
        $this->type = $type;
    }

    public function get_type() {
        return $this->type;
    }

    public function requires_assets() {
        return $this->requires_assets;
    }

    public function enqueue_assets() {
        // Default: no assets required
    }

    /**
     * Get field wrapper class
     *
     * @param string $field_type Field type
     * @return string CSS class
     */
    protected function get_wrapper_class( $field_type ) {
        return 'checkbox' === $field_type ? 'hvnly__dyamic_metabox_tab__checkbox' : '';
    }

    /**
     * Render field label
     *
     * @param array $field Field configuration
     * @return string Label HTML
     */
    protected function render_label( $field ) {
        // Skip label for map fields - they handle their own labels
        if ($field['type'] === 'map') {
            return '';
        }

        // Skip label for checkbox fields
        if ($field['type'] === 'checkbox') {
            return '';
        }

        $required = isset($field['is_required']) && $field['is_required'] ? '<span class="required">*</span>' : '';
        $label    = isset( $field['label'] ) ? (string) $field['label'] : '';
        return sprintf(
            '<label>%s%s</label>',
            '' !== $label ? hvnly_esc_html_ui( $label ) : '',
            $required
        );
    }

    /**
     * Render field description
     *
     * @param array $field Field configuration
     * @return string Description HTML
     */
    protected function render_description( $field ) {
        if (empty($field['description'])) {
            return '';
        }

        return sprintf(
            '<p class="description">%s</p>',
            hvnly_esc_html_ui( (string) $field['description'] )
        );
    }

    /**
     * Hydrate section/base identity and log group field reads (WP_DEBUG).
     *
     * @param array  $field      Field config.
     * @param string $context    Log context label.
     * @param string $section_id Optional section ID when missing from field.
     * @return array
     */
    protected function prepare_group_field( array $field, string $context = '', string $section_id = '' ): array {
        if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
            $field = \HvnlyNab\Core\GroupFieldIdentity::hydrate_field_identity( $field, $section_id );
            if ( $context !== '' ) {
                \HvnlyNab\Core\GroupFieldIdentity::log_group_identity( $field, $context );
            }
        }

        return $field;
    }

    /**
     * Resolve post meta for a scoped group field.
     *
     * @param int    $post_id     Post ID.
     * @param array  $field       Field config.
     * @param string $primary_key Meta key.
     * @param string $meta_key    metaKey suffix.
     * @return mixed
     */
    protected function resolve_group_meta( int $post_id, array $field, string $primary_key = '', string $meta_key = '' ) {
        if ( class_exists( '\HvnlyNab\Core\GroupFieldIdentity' ) ) {
            return \HvnlyNab\Core\GroupFieldIdentity::resolve_value( $post_id, $field, $primary_key, $meta_key );
        }

        $key = $primary_key !== '' ? $primary_key : (string) ( $field['name'] ?? $field['id'] ?? '' );

        return $key !== '' ? get_post_meta( $post_id, $key, true ) : '';
    }
}
