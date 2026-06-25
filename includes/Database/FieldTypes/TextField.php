<?php
/**
 * Text Field Handler
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

class TextField extends BaseFieldType {
    public function __construct() {
        parent::__construct('text');
    }
    
    public function render($field, $value, $post_id) {
        $field_name = $field['name'] ?? $field['fieldid'] ?? '';
        $field_id = $field['fieldid'] ?? $field_name;
        $is_required = isset($field['is_required']) && $field['is_required'];
        
        $html = $this->render_label($field);
        
        $html .= sprintf(
            '<input type="text" 
                id="%s" 
                name="%s" 
                value="%s" 
                placeholder="%s" 
                class="hvnly__dyamic_metabox_tab__input widefat" 
                data-field-type="text" 
                %s />',
            esc_attr($field_id),
            esc_attr($field_name),
            esc_attr($value),
            esc_attr($field['placeholder'] ?? ''),
            $is_required ? 'required' : ''
        );
        
        if (!empty($field['userguide'])) {
            $html .= sprintf('<small><i>%s</i></small>', esc_html($field['userguide']));
        }
        
        $html .= $this->render_description($field);
        
        return $html;
    }
    
    public function save($post_id, $field_name, $value, $extra = null) {
        update_post_meta($post_id, $field_name, sanitize_text_field($value));
    }
    
    public function sanitize($value) {
        return sanitize_text_field($value);
    }
    
    public function validate($value, $field) {
        if (isset($field['is_required']) && $field['is_required'] && empty(trim($value))) {
            return new \WP_Error('required_field', sprintf(
                /* translators: %s: field label */
                __('The field "%s" is required.', 'havenlytics'),
                esc_html($field['label'])
            ));
        }
        return true;
    }
}