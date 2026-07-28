<?php
/**
 * Select Field Handler
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

class SelectField extends BaseFieldType {
    public function __construct() {
        parent::__construct('select');
    }
    
    public function render($field, $value, $post_id) {
        if ( function_exists( 'hvnly_hydrate_select_field_options' ) ) {
            $field = hvnly_hydrate_select_field_options( $field );
        }

        $html = $this->render_label($field);
        
        $options_html = '';
        if (!empty($field['options'])) {
            foreach ($field['options'] as $option_value => $option_label) {
                $selected = selected($value, $option_value, false);
                $options_html .= sprintf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($option_value),
                    $selected,
                    '' !== (string) $option_label
                        ? hvnly_esc_html_ui( (string) $option_label )
                        : ''
                );
            }
        }
        
        $html .= sprintf(
            '<select name="%s" 
                class="hvnly__dyamic_metabox_tab__select" 
                data-field-type="select" %s>%s</select>',
            esc_attr($field['name']),
            isset($field['is_required']) && $field['is_required'] ? 'required' : '',
            $options_html
        );
        
        $html .= $this->render_description($field);
        
        return $html;
    }
    
    public function save($post_id, $field_name, $value, $extra = null) {
        update_post_meta($post_id, $field_name, $value);
    }
    
    public function sanitize($value) {
        return sanitize_text_field($value);
    }
    
    public function validate($value, $field) {
        if (isset($field['is_required']) && $field['is_required'] && empty($value)) {
            return new \WP_Error('required_field', sprintf(
                /* translators: %s: field label */
                __('The field "%s" is required.', 'havenlytics'),
                hvnly_esc_html_ui( (string) $field['label'] )
            ));
        }
        return true;
    }
}