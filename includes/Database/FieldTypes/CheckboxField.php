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
    
    public function render($field, $value, $post_id) {
        // Get saved list items
        $list_items = !empty($value) ? json_decode($value, true) : [];
        
        // If no items, create a default empty item
        if (empty($list_items)) {
            $list_items = [''];
        }
        
        $field_id = $field['id'];
        $field_name = $field['name'];
        $field_label = isset( $field['label'] ) ? (string) $field['label'] : '';
        
        ob_start();
        ?>
<div class="hvnly-checkbox-repeater-field" data-field-id="<?php echo esc_attr($field_id); ?>">
    <div class="hvnly-checkbox-repeater-label">
        <label><?php echo '' !== $field_label ? esc_html( hvnly_translate_ui( $field_label ) ) : ''; ?></label>
        <p class="description"><?php echo ! empty( $field['description'] ) ? esc_html( hvnly_translate_ui( (string) $field['description'] ) ) : ''; ?></p>
    </div>

    <div class="hvnly-checkbox-repeater-items">
        <?php foreach ($list_items as $item_index => $item_value): ?>
        <div class="hvnly-checkbox-repeater-item" data-item-index="<?php echo esc_attr($item_index); ?>">
            <div class="hvnly-checkbox-repeater-item-content">
                <input type="text" name="<?php echo esc_attr($field_name); ?>[<?php echo esc_attr($item_index); ?>]"
                    value="<?php echo esc_attr($item_value); ?>"
                    placeholder="<?php esc_attr_e('Enter list item', 'havenlytics'); ?>" class="widefat" />
            </div>
            <div class="hvnly-checkbox-repeater-item-actions">
                <button type="button" class="button hvnly-checkbox-repeater-move-up"
                    title="<?php esc_attr_e('Move Up', 'havenlytics'); ?>">
                    <span class="dashicons dashicons-arrow-up"></span>
                </button>
                <button type="button" class="button hvnly-checkbox-repeater-move-down"
                    title="<?php esc_attr_e('Move Down', 'havenlytics'); ?>">
                    <span class="dashicons dashicons-arrow-down"></span>
                </button>
                <button type="button" class="button hvnly-checkbox-repeater-remove-item">
                    <span class="dashicons dashicons-no"></span>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="hvnly-checkbox-repeater-actions">
        <button type="button" class="button button-primary hvnly-checkbox-repeater-add-item">
            <span class="dashicons dashicons-plus"></span>
            <?php esc_html_e('Add List Item', 'havenlytics'); ?>
        </button>
    </div>

    <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($value); ?>"
        class="hvnly-checkbox-repeater-hidden" />
</div>
<?php
        
        return ob_get_clean();
    }
    
    public function save($post_id, $field_name, $value, $extra = null) {
        if (empty($value)) {
            hvnly_safe_delete_post_meta($post_id, $field_name, 'user_save_empty');
        } else {
            update_post_meta($post_id, $field_name, $value);
        }
    }
    
    public function sanitize($value) {
        if (is_array($value)) {
            // Sanitize the array data
            $sanitized = [];
            foreach ($value as $item_index => $item_value) {
                $sanitized[] = sanitize_text_field($item_value);
            }
            return json_encode(array_filter($sanitized)); 
        }
        return sanitize_text_field($value);
    }
    
    public function validate($value, $field) {
        // Checkbox repeater fields can be optional
        return true;
    }
    
    public function enqueue_assets() {
        // Enqueue the checkbox repeater JS
        wp_enqueue_script(
            'hvnly-checkbox-repeater-field',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-checkbox-repeater-field.js',
            ['jquery'],
            defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '1.0.0',
            true
        );
    }
}