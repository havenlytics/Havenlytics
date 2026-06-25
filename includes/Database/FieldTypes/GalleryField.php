<?php
/**
 * Gallery Field Handler - FIXED to use correct field names
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

class GalleryField extends BaseFieldType {
    
    public function __construct() {
        parent::__construct('gallery');
        $this->requires_assets = true;
    }
    
    public function render($field, $value, $post_id) {
        $field = $this->prepare_group_field( $field, 'GalleryField' );

        // Get the UNIQUE field name for this gallery
        $field_name = $field['name'] ?? $field['id'] ?? '';
        $group_base_id = $field['group_base_id'] ?? '';
        $metaKey = $field['metaKey'] ?? '';
        
        // Get saved title from the title field (unique to this group)
        $title_field_name = $group_base_id . '_title';
        $saved_title = get_post_meta($post_id, $title_field_name, true);

        // Resolve images via MetaResolver when available.
        $saved_value = '';
        if ( ( $field['metaKey'] ?? '' ) !== '' ) {
            $saved_value = $this->resolve_group_meta( (int) $post_id, $field, $field_name, 'images' );
        }
        if ( empty( $saved_value ) && $field_name !== '' ) {
            $saved_value = get_post_meta( $post_id, $field_name, true );
        }
        
        // Legacy global gallery meta only for non-scoped imports (never cross-section).
        if (
            empty( $saved_value )
            && ( $field['metaKey'] ?? '' ) === 'images'
            && class_exists( '\HvnlyNab\Core\GroupFieldIdentity' )
            && ! \HvnlyNab\Core\GroupFieldIdentity::is_strictly_scoped_field( $field )
            && \HvnlyNab\Core\GroupFieldIdentity::owns_legacy_type_import( $post_id, $field, 'gallery' )
        ) {
            $legacy_value = get_post_meta( $post_id, '_hvnly_property_gallery_images', true );
            if ( ! empty( $legacy_value ) ) {
                $saved_value = $legacy_value;
            }
        }
        
        // Convert to array
        $gallery_images = !empty($saved_value) ? explode(',', $saved_value) : array();
        
        // CRITICAL FIX: Use group_base_id as the gallery identifier, NOT field_name
        // This ensures consistent naming between render and save
        $gallery_id = $group_base_id;
        
        if (empty($gallery_id)) {
            $gallery_id = $field['fieldid'] ?? $field['id'] ?? uniqid('gallery_');
        }
        
        // Create UNIQUE input names based on group_base_id ONLY
        $title_input_name = 'hvnly_gallery_title_' . $gallery_id;
        $caption_input_name = 'hvnly_gallery_caption_' . $gallery_id;
        $ids_input_name = 'hvnly_gallery_ids_' . $gallery_id;
        
        ob_start();
        ?>
<div class="hvnly-gallery-container" data-field-id="<?php echo esc_attr($gallery_id); ?>"
    data-gallery-id="<?php echo esc_attr($gallery_id); ?>" data-title-name="<?php echo esc_attr($title_input_name); ?>"
    data-caption-name="<?php echo esc_attr($caption_input_name); ?>"
    data-ids-name="<?php echo esc_attr($ids_input_name); ?>">

    <!-- GALLERY TITLE FIELD -->
    <div class="hvnly-gallery-title-field" style="margin-bottom: 15px;">
        <label for="gallery_title_<?php echo esc_attr($gallery_id); ?>"
            style="display: block; margin-bottom: 5px; font-weight: 600;">
            <?php echo esc_html($field['label'] ?? 'Gallery Title'); ?>
        </label>
        <input type="text" id="gallery_title_<?php echo esc_attr($gallery_id); ?>"
            name="<?php echo esc_attr($title_field_name); ?>" value="<?php echo esc_attr($saved_title); ?>"
            placeholder="<?php esc_attr_e('Enter gallery title', 'havenlytics'); ?>" class="widefat"
            style="margin-bottom: 15px;" />
    </div>

    <div class="hvnly-gallery-instructions">
        <?php esc_html_e('Add images to your gallery. Drag to reorder.', 'havenlytics'); ?>
    </div>

    <ul class="hvnly-gallery-images" id="hvnly-gallery-list-<?php echo esc_attr($gallery_id); ?>">
        <?php 
        if (!empty($gallery_images)) {
            foreach ($gallery_images as $image_id): 
                if (!empty($image_id)) {
                    $this->render_gallery_item($image_id, $gallery_id, $title_input_name, $caption_input_name, $ids_input_name);
                }
            endforeach; 
        }
        ?>
    </ul>

    <!-- Hidden field that stores the comma-separated image IDs -->
    <input type="hidden" id="hvnly_gallery_<?php echo esc_attr($gallery_id); ?>"
        name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($saved_value); ?>"
        class="hvnly-gallery-hidden" />

    <div class="hvnly-gallery-actions" style="display:flex;align-items:center;flex-wrap:wrap;gap:0.75rem;">
        <button type="button" class="button button-primary hvnly-add-gallery"
            data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
            data-title-name="<?php echo esc_attr($title_input_name); ?>"
            data-caption-name="<?php echo esc_attr($caption_input_name); ?>"
            data-ids-name="<?php echo esc_attr($ids_input_name); ?>"
            data-target-field="#hvnly_gallery_<?php echo esc_attr($gallery_id); ?>" data-type="image"
            style="display:inline-flex;align-items:center;gap:0.4rem;">
            <span class="dashicons dashicons-format-gallery"></span>
            <?php esc_html_e('Manage Images', 'havenlytics'); ?>
        </button>
        <button type="button" class="button hvnly-clear-gallery" data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
            data-target-field="#hvnly_gallery_<?php echo esc_attr($gallery_id); ?>"
            style="display:inline-flex;align-items:center;gap:0.4rem;">
            <span class="dashicons dashicons-trash"></span>
            <?php esc_html_e('Clear All', 'havenlytics'); ?>
        </button>
        <span class="hvnly-gallery-status" id="hvnly-gallery-status-<?php echo esc_attr($gallery_id); ?>">
            <span class="dashicons dashicons-images-alt2"></span>
            <span class="hvnly-gallery-status-count"><?php echo count( $gallery_images ); ?></span>
            <?php esc_html_e('image(s)', 'havenlytics'); ?>
        </span>
    </div>
</div>
<?php
        return ob_get_clean();
    }
    
    /**
     * Render a single gallery item
     */
    private function render_gallery_item($image_id, $gallery_id, $title_input_name, $caption_input_name, $ids_input_name) {
        if ($image = wp_get_attachment_image_src($image_id, 'thumbnail')) {
            $attachment = get_post($image_id);
            $title = $attachment ? $attachment->post_title : '';
            $caption = $attachment ? $attachment->post_excerpt : '';
            ?>
<li class="hvnly-gallery-item" data-id="<?php echo esc_attr($image_id); ?>"
    data-gallery-id="<?php echo esc_attr($gallery_id); ?>">

    <img src="<?php echo esc_url($image[0]); ?>" alt="<?php echo esc_attr($title); ?>" />

    <div class="hvnly-gallery-item-actions">
        <a href="#" class="hvnly-gallery-edit" title="<?php esc_attr_e('Edit Image', 'havenlytics'); ?>">
            <span class="dashicons dashicons-edit"></span>
        </a>
        <a href="#" class="hvnly-gallery-remove" title="<?php esc_attr_e('Remove Image', 'havenlytics'); ?>">
            <span class="dashicons dashicons-no"></span>
        </a>
    </div>

    <input type="hidden" name="<?php echo esc_attr($title_input_name); ?>[]" value="<?php echo esc_attr($title); ?>" />

    <input type="hidden" name="<?php echo esc_attr($caption_input_name); ?>[]"
        value="<?php echo esc_attr($caption); ?>" />

    <input type="hidden" name="<?php echo esc_attr($ids_input_name); ?>[]" value="<?php echo esc_attr($image_id); ?>" />
</li>
<?php
        }
    }
    
    public function save($post_id, $field_name, $value, $extra = null) {
        // Sanitize and save to the unique field name (images)
        $image_ids = explode(',', sanitize_text_field($value));
        $image_ids = array_map('intval', array_filter($image_ids));
        $clean_value = implode(',', $image_ids);
        
        // Get the group_base_id from the extra array or extract from field_name
        $group_base_id = $extra['group_base_id'] ?? '';
        
        // If no group_base_id in extra, try to extract from field_name
        if (empty($group_base_id) && strpos($field_name, '_images') !== false) {
            $group_base_id = str_replace('_images', '', $field_name);
        }
        
        // Save the images to the field name (which should be {group_base_id}_images)
        if (!empty($clean_value)) {
            update_post_meta($post_id, $field_name, $clean_value);
        } else {
            hvnly_safe_delete_post_meta($post_id, $field_name, 'user_save_empty');
        }
        
        // Also handle the title if present in POST
        if (!empty($group_base_id)) {
            $title_field_name = $group_base_id . '_title';
            $title_value = filter_input(INPUT_POST, $title_field_name, FILTER_UNSAFE_RAW);
            if ($title_value !== null) {
                update_post_meta($post_id, $title_field_name, sanitize_text_field($title_value));
            }
        }
    }
    
    public function sanitize($value) {
        $image_ids = explode(',', $value);
        return implode(',', array_map('intval', array_filter($image_ids)));
    }
    
    public function validate($value, $field) {
        if (empty($field['is_required'])) {
            return true;
        }

        $image_ids = array();
        if (is_string($value) && '' !== trim($value)) {
            $image_ids = array_filter(array_map('intval', explode(',', $value)));
        }

        if (empty($image_ids)) {
            return new \WP_Error('required_gallery', sprintf(
                /* translators: %s: gallery field label. */
                __('At least one gallery image is required for "%s".', 'havenlytics'),
                esc_html($field['label'] ?? __('Gallery', 'havenlytics'))
            ));
        }

        return true;
    }
    
    public function enqueue_assets() {
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_script(
            'hvnly-gallery-field',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-gallery-field.js',
            ['jquery', 'jquery-ui-sortable'],
            HVNLYNAB_VERSION,
            true
        );
    }
}