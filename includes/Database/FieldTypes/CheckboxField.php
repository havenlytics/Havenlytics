<?php
/**
 * Checkbox Field Handler - Enhanced with Simple List Repeater
 *
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */


namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

class CheckboxField extends BaseFieldType {
    public function __construct() {
        parent::__construct('checkbox');
        $this->requires_assets = true;
    }

    public function render( $field, $value, $post_id ) {
        // Get saved list items
        $list_items = ! empty($value) ? json_decode($value, true) : array();

        // If no items, create a default empty item
        if (empty($list_items)) {
            $list_items = array( '' );
        }

        $field_id    = $field['id'];
        $field_name  = $field['name'];
        $field_label = isset( $field['label'] ) ? (string) $field['label'] : '';
        $search_id   = 'hvnly-checkbox-repeater-search-' . sanitize_html_class( (string) $field_id );
        $has_values  = (bool) array_filter( array_map( 'strval', $list_items ) );

        ob_start();
        ?>
<div class="hvnly-checkbox-repeater-field<?php echo $has_values ? '' : ' is-empty'; ?>" data-field-id="<?php echo esc_attr($field_id); ?>">
    <div class="hvnly-checkbox-repeater-header">
        <div class="hvnly-checkbox-repeater-label">
            <label><?php echo '' !== $field_label ? esc_html( hvnly_translate_ui( $field_label ) ) : ''; ?></label>
            <p class="description"><?php echo ! empty( $field['description'] ) ? esc_html( hvnly_translate_ui( (string) $field['description'] ) ) : esc_html__( 'Add listing features as a simple ordered list. Use the arrows to reorder.', 'havenlytics' ); ?></p>
        </div>

        <div class="hvnly-checkbox-repeater-toolbar">
            <label class="screen-reader-text" for="<?php echo esc_attr( $search_id ); ?>"><?php esc_html_e( 'Filter features', 'havenlytics' ); ?></label>
            <div class="hvnly-checkbox-repeater-search-wrap">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="search"
                    id="<?php echo esc_attr( $search_id ); ?>"
                    class="hvnly-checkbox-repeater-search"
                    placeholder="<?php esc_attr_e( 'Search features…', 'havenlytics' ); ?>"
                    autocomplete="off" />
            </div>
        </div>
    </div>

    <div class="hvnly-checkbox-repeater-empty" <?php echo $has_values ? 'hidden' : ''; ?>>
        <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
        <p class="hvnly-checkbox-repeater-empty-title"><?php esc_html_e( 'No features yet', 'havenlytics' ); ?></p>
        <p class="hvnly-checkbox-repeater-empty-subtitle"><?php esc_html_e( 'Add amenities or highlights for this property. They appear as a list on the listing.', 'havenlytics' ); ?></p>
    </div>

    <div class="hvnly-checkbox-repeater-items" role="list">
        <?php foreach ($list_items as $item_index => $item_value) : ?>
        <div class="hvnly-checkbox-repeater-item" data-item-index="<?php echo esc_attr($item_index); ?>" role="listitem">
            <div class="hvnly-checkbox-repeater-item-content">
                <input type="text" name="<?php echo esc_attr($field_name); ?>[<?php echo esc_attr($item_index); ?>]"
                    value="<?php echo esc_attr($item_value); ?>"
                    placeholder="<?php esc_attr_e('Enter list item', 'havenlytics'); ?>" class="widefat"
                    aria-label="<?php echo esc_attr( sprintf( /* translators: %d: feature row number */ __( 'Feature %d', 'havenlytics' ), (int) $item_index + 1 ) ); ?>" />
            </div>
            <div class="hvnly-checkbox-repeater-item-actions">
                <button type="button" class="button hvnly-checkbox-repeater-move-up"
                    title="<?php esc_attr_e('Move Up', 'havenlytics'); ?>"
                    aria-label="<?php esc_attr_e('Move Up', 'havenlytics'); ?>">
                    <span class="dashicons dashicons-arrow-up" aria-hidden="true"></span>
                </button>
                <button type="button" class="button hvnly-checkbox-repeater-move-down"
                    title="<?php esc_attr_e('Move Down', 'havenlytics'); ?>"
                    aria-label="<?php esc_attr_e('Move Down', 'havenlytics'); ?>">
                    <span class="dashicons dashicons-arrow-down" aria-hidden="true"></span>
                </button>
                <button type="button" class="button hvnly-checkbox-repeater-remove-item"
                    title="<?php esc_attr_e('Remove', 'havenlytics'); ?>"
                    aria-label="<?php esc_attr_e('Remove feature', 'havenlytics'); ?>">
                    <span class="dashicons dashicons-no" aria-hidden="true"></span>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="hvnly-checkbox-repeater-no-results" hidden>
        <?php esc_html_e( 'No features match your search.', 'havenlytics' ); ?>
    </p>

    <div class="hvnly-checkbox-repeater-actions">
        <button type="button" class="button button-primary hvnly-checkbox-repeater-add-item">
            <span class="dashicons dashicons-plus" aria-hidden="true"></span>
            <?php esc_html_e('Add List Item', 'havenlytics'); ?>
        </button>
    </div>

    <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($value); ?>"
        class="hvnly-checkbox-repeater-hidden" />
</div>
		<?php

        return ob_get_clean();
    }

    public function save( $post_id, $field_name, $value, $extra = null ) {
        if (empty($value)) {
            hvnly_safe_delete_post_meta($post_id, $field_name, 'user_save_empty');
        } else {
            update_post_meta($post_id, $field_name, $value);
        }
    }

    public function sanitize( $value ) {
        if (is_array($value)) {
            // Sanitize the array data
            $sanitized = array();
            foreach ($value as $item_index => $item_value) {
                $sanitized[] = sanitize_text_field($item_value);
            }
            return json_encode(array_filter($sanitized));
        }
        return sanitize_text_field($value);
    }

    public function validate( $value, $field ) {
        // Checkbox repeater fields can be optional
        return true;
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'hvnly-checkbox-repeater-field',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-checkbox-repeater-field.css',
            array( 'hvnly-admin-metabox' ),
            defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '1.0.0'
        );

        // Enqueue the checkbox repeater JS
        wp_enqueue_script(
            'hvnly-checkbox-repeater-field',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-checkbox-repeater-field.js',
            array( 'jquery' ),
            defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '1.0.0',
            true
        );
    }
}
