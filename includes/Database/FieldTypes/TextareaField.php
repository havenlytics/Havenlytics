<?php
/**
 * Textarea Field Handler
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

class TextareaField extends BaseFieldType {
    public function __construct() {
        parent::__construct('textarea');
    }
    
    public function render($field, $value, $post_id) {
        $html = $this->render_label($field);
        
        $html .= sprintf(
            '<textarea name="%s" 
                placeholder="%s" 
                class="hvnly__dyamic_metabox_tab__textarea" 
                data-field-type="textarea" %s>%s</textarea>',
            esc_attr($field['name']),
            hvnly_esc_attr_ui( (string) ( $field['placeholder'] ?? '' ) ),
            isset($field['is_required']) && $field['is_required'] ? 'required' : '',
            esc_textarea($value)
        );
        
        $html .= $this->render_description($field);
        
        return $html;
    }
    
    public function save($post_id, $field_name, $value, $extra = null) {
        update_post_meta($post_id, $field_name, $value);
    }
    
    public function sanitize($value) {
        return sanitize_textarea_field($value);
    }
    
    public function validate($value, $field) {
        if (isset($field['is_required']) && $field['is_required'] && empty(trim($value))) {
            return new \WP_Error('required_field', sprintf(
                /* translators: %s: field label */
                __('The field "%s" is required.', 'havenlytics'),
                hvnly_esc_html_ui( (string) $field['label'] )
            ));
        }
        return true;
    }
}