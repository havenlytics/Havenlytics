<?php
/**
 * File Field Handler
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

class FileField extends BaseFieldType {
    
    public function __construct() {
        parent::__construct('file');
        $this->requires_assets = true;
    }
    
    /**
     * Render the file field HTML
     *
     * @param array $field Field configuration.
     * @param mixed $value Current field value.
     * @param int $post_id Current post ID.
     * @return string Rendered HTML.
     */
    public function render($field, $value, $post_id) {
        $file_type   = $field['file_type'] ?? $field['fileType'] ?? 'file';
        $field_id    = $field['fieldid'] ?? '';
        $field_name  = $field['name'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $label       = $field['label'] ?? '';
        $description = isset($field['description']) ? wp_kses_post($field['description']) : '';
        
        ob_start();
        ?>
<div data-field-type="file" data-field-id="<?php echo esc_attr( $field_id ); ?>" class="hvnly-file-field-wrapper">
    <?php if (!empty($label)) : ?>
    <label for="<?php echo esc_attr( $field_id ); ?>" class="hvnly-field-label">
        <?php echo esc_html( hvnly_translate_ui( (string) $label ) ); ?>
        <?php if (isset($field['is_required']) && $field['is_required']) : ?>
        <span class="hvnly-required-star" style="color: #d63638;">*</span>
        <?php endif; ?>
    </label>
    <?php endif; ?>

    <div class="hvnly-meta-field" style="display: block; margin: 0;">
        <div class="hvnly-meta-input">
            <input type="url" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>"
                class="widefat hvnly-file-input" value="<?php echo esc_attr($value); ?>"
                placeholder="<?php echo esc_attr( hvnly_translate_ui( (string) $placeholder ) ); ?>" style="margin-bottom: 10px;" />

            <div class="hvnly-preview-container"></div>

            <button type="button" class="button hvnly-upload-button" data-target="#<?php echo esc_attr( $field_id ); ?>"
                data-type="<?php echo esc_attr($file_type); ?>" style="margin-top: 10px;">
                <?php echo esc_html( $this->get_upload_button_text($file_type) ); ?>
            </button>
        </div>
    </div>

    <?php if (!empty($description)) : ?>
    <p class="description hvnly-field-description"><?php echo wp_kses_post( $description ); ?></p>
    <?php endif; ?>
</div>
<?php
        
        return ob_get_clean();
    }
    
    /**
     * Get upload button text based on file type
     *
     * @param string $file_type File type (image, pdf, file).
     * @return string Button text.
     */
    private function get_upload_button_text($file_type) {
        switch ($file_type) {
            case 'image':
                return __('Upload Image', 'havenlytics');
            case 'pdf':
                return __('Upload PDF', 'havenlytics');
            default:
                return __('Upload File', 'havenlytics');
        }
    }
    
    /**
     * Save file field data
     *
     * @param int $post_id Post ID.
     * @param string $field_name Field name.
     * @param mixed $value Field value.
     * @param mixed $extra Optional extra parameter (for interface compatibility).
     * @return void
     */
    public function save($post_id, $field_name, $value, $extra = null) {
        if (!empty($value)) {
            update_post_meta($post_id, $field_name, esc_url_raw($value));
        } else {
            hvnly_safe_delete_post_meta($post_id, $field_name, 'user_save_empty');
        }
    }
    
    /**
     * Sanitize file field value
     *
     * @param mixed $value Field value.
     * @return string Sanitized URL.
     */
    public function sanitize($value) {
        if (empty($value)) {
            return '';
        }
        return esc_url_raw(trim($value));
    }
    
    /**
     * Validate file field value
     *
     * @param mixed $value Field value.
     * @param array $field Field configuration.
     * @return bool|\WP_Error
     */
    public function validate($value, $field) {
        // Check if required
        if (isset($field['is_required']) && $field['is_required'] && empty($value)) {
            return new \WP_Error(
                'required_field',
                sprintf(
                    /* translators: %s: file field label. */
                    __('The file field "%s" is required.', 'havenlytics'),
                    hvnly_esc_html_ui( (string) $field['label'] )
                )
            );
        }
        
        // Validate URL if value exists
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            return new \WP_Error(
                'invalid_url',
                sprintf(
                    /* translators: %s: file field label. */
                    __('Please enter a valid URL for "%s".', 'havenlytics'),
                    hvnly_esc_html_ui( (string) $field['label'] )
                )
            );
        }
        
        return true;
    }
    
    /**
     * Enqueue required assets
     */
    public function enqueue_assets() {
        wp_enqueue_media();
        wp_enqueue_style('dashicons');
    }
}