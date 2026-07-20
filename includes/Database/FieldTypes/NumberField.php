<?php
/**
 * Number Field Handler
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

class NumberField extends BaseFieldType {
    public function __construct() {
        parent::__construct('number');
    }
    
    public function render($field, $value, $post_id) {
        $html = $this->render_label($field);
        
        $html .= sprintf(
            '<input type="number" 
                id="%s" 
                name="%s" 
                value="%s" 
                placeholder="%s" 
                class="hvnly__dyamic_metabox_tab__input" 
                data-field-type="number" %s />',
            esc_attr($field['fieldid']),
            esc_attr($field['name']),
            esc_attr($value),
            esc_attr($field['placeholder'] ?? ''),
            isset($field['is_required']) && $field['is_required'] ? 'required' : ''
        );
        
        if (!empty($field['userguide'])) {
            $html .= sprintf('<small><i>%s</i></small>', esc_html($field['userguide']));
        }
        
        $html .= $this->render_description($field);
        
        return $html;
    }
    
    public function save($post_id, $field_name, $value, $extra = null) {
        update_post_meta($post_id, $field_name, floatval($value));
    }
    
    public function sanitize($value) {
        return floatval($value);
    }
    
    public function validate($value, $field) {
        if (isset($field['is_required']) && $field['is_required'] && (empty($value) && $value !== '0')) {
            return new \WP_Error('required_field', sprintf(
                /* translators: %s: field label */
                __('The field "%s" is required.', 'havenlytics'),
                esc_html($field['label'])
            ));
        }
        
        if (!empty($value) && !is_numeric($value)) {
            return new \WP_Error('invalid_number', sprintf(
                /* translators: %s: field label */
                __('"%s" must be a valid number.', 'havenlytics'),
                esc_html($field['label'])
            ));
        }
        
        return true;
    }
}