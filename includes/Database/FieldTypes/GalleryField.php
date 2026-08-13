<?php
/**
 * Gallery Field Handler - FIXED to use correct field names
 *
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

class GalleryField extends BaseFieldType {

    public function __construct() {
        parent::__construct('gallery');
        $this->requires_assets = true;
    }

    public function render( $field, $value, $post_id ) {
        $field = $this->prepare_group_field( $field, 'GalleryField' );

        // Get the UNIQUE field name for this gallery
        $field_name    = $field['name'] ?? $field['id'] ?? '';
        $group_base_id = $field['group_base_id'] ?? '';
        $metaKey       = $field['metaKey'] ?? '';

        // Get saved title from the title field (unique to this group)
        $title_field_name = $group_base_id . '_title';
        $saved_title      = get_post_meta($post_id, $title_field_name, true);

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
        $gallery_images = ! empty($saved_value) ? explode(',', $saved_value) : array();

        // CRITICAL FIX: Use group_base_id as the gallery identifier, NOT field_name
        // This ensures consistent naming between render and save
        $gallery_id = $group_base_id;

        if (empty($gallery_id)) {
            $gallery_id = $field['fieldid'] ?? $field['id'] ?? uniqid('gallery_');
        }

        // Create UNIQUE input names based on group_base_id ONLY
        $title_input_name   = 'hvnly_gallery_title_' . $gallery_id;
        $caption_input_name = 'hvnly_gallery_caption_' . $gallery_id;
        $ids_input_name     = 'hvnly_gallery_ids_' . $gallery_id;

        ob_start();
        $image_count    = count( array_filter( $gallery_images ) );
        $is_empty_class = $image_count < 1 ? ' hvnly-gallery-is-empty' : '';
        $status_suffix  = ( 1 === (int) $image_count )
            ? __( 'Image', 'havenlytics' )
            : __( 'Images', 'havenlytics' );
        ?>
<div class="hvnly-gallery-container<?php echo esc_attr( $is_empty_class ); ?>" data-field-id="<?php echo esc_attr($gallery_id); ?>"
    data-gallery-id="<?php echo esc_attr($gallery_id); ?>" data-title-name="<?php echo esc_attr($title_input_name); ?>"
    data-caption-name="<?php echo esc_attr($caption_input_name); ?>"
    data-ids-name="<?php echo esc_attr($ids_input_name); ?>">

    <div class="hvnly-gallery-title-field">
        <label for="gallery_title_<?php echo esc_attr($gallery_id); ?>">
            <?php echo esc_html( hvnly_translate_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Gallery Title' ) ) ); ?>
        </label>
        <input type="text" id="gallery_title_<?php echo esc_attr($gallery_id); ?>"
            name="<?php echo esc_attr($title_field_name); ?>" value="<?php echo esc_attr($saved_title); ?>"
            placeholder="<?php esc_attr_e('Enter gallery title', 'havenlytics'); ?>" class="widefat" />
    </div>

    <div class="hvnly-gallery-panel">
        <div class="hvnly-gallery-toolbar">
            <div class="hvnly-gallery-toolbar-copy">
                <span class="hvnly-gallery-toolbar-title"><?php esc_html_e( 'Gallery Images', 'havenlytics' ); ?></span>
                <p class="hvnly-gallery-instructions"><?php esc_html_e( 'Drag media cards to reorder. Hover a card to edit or remove.', 'havenlytics' ); ?></p>
            </div>
            <div class="hvnly-gallery-toolbar-controls">
                <button type="button" class="hvnly-gallery-action hvnly-gallery-action-primary hvnly-add-gallery"
                    data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
                    data-title-name="<?php echo esc_attr($title_input_name); ?>"
                    data-caption-name="<?php echo esc_attr($caption_input_name); ?>"
                    data-ids-name="<?php echo esc_attr($ids_input_name); ?>"
                    data-target-field="#hvnly_gallery_<?php echo esc_attr($gallery_id); ?>" data-type="image">
                    <span class="dashicons dashicons-images-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Manage Images', 'havenlytics' ); ?>
                </button>
                <button type="button" class="hvnly-gallery-action hvnly-gallery-action-secondary hvnly-clear-gallery"
                    data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
                    data-target-field="#hvnly_gallery_<?php echo esc_attr($gallery_id); ?>">
                    <?php esc_html_e( 'Clear', 'havenlytics' ); ?>
                </button>
                <span class="hvnly-gallery-status" id="hvnly-gallery-status-<?php echo esc_attr($gallery_id); ?>">
                    <span class="hvnly-gallery-status-count"><?php echo (int) $image_count; ?></span>
                    <span class="hvnly-gallery-status-label"><?php echo esc_html( $status_suffix ); ?></span>
                </span>
            </div>
        </div>

        <div class="hvnly-gallery-stage">
            <ul class="hvnly-gallery-images" id="hvnly-gallery-list-<?php echo esc_attr($gallery_id); ?>">
                <?php
                if ( ! empty($gallery_images)) {
                    foreach ($gallery_images as $image_id) {
                        if ( ! empty($image_id)) {
                            $this->render_gallery_item($image_id, $gallery_id, $title_input_name, $caption_input_name, $ids_input_name);
                        }
                    }
                }
                ?>
            </ul>

            <div class="hvnly-gallery-empty" role="status">
                <div class="hvnly-gallery-empty-visual" aria-hidden="true">
                    <span class="hvnly-gallery-empty-icon">
                        <svg viewBox="0 0 48 48" width="40" height="40" focusable="false">
                            <rect x="6" y="10" width="36" height="28" rx="6" fill="currentColor" opacity="0.12"/>
                            <path d="M14 30l7-8 5 6 4-4 8 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" opacity="0.55"/>
                            <circle cx="18.5" cy="18.5" r="2.5" fill="currentColor" opacity="0.45"/>
                        </svg>
                    </span>
                </div>
                <p class="hvnly-gallery-empty-title"><?php esc_html_e( 'No images yet', 'havenlytics' ); ?></p>
                <p class="hvnly-gallery-empty-subtitle"><?php esc_html_e( 'Add photos from the Media Library to build this gallery.', 'havenlytics' ); ?></p>
                <button type="button" class="hvnly-gallery-action hvnly-gallery-action-primary hvnly-add-gallery"
                    data-gallery-id="<?php echo esc_attr($gallery_id); ?>"
                    data-title-name="<?php echo esc_attr($title_input_name); ?>"
                    data-caption-name="<?php echo esc_attr($caption_input_name); ?>"
                    data-ids-name="<?php echo esc_attr($ids_input_name); ?>"
                    data-target-field="#hvnly_gallery_<?php echo esc_attr($gallery_id); ?>" data-type="image">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e( 'Add Images', 'havenlytics' ); ?>
                </button>
            </div>
        </div>
    </div>

    <input type="hidden" id="hvnly_gallery_<?php echo esc_attr($gallery_id); ?>"
        name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($saved_value); ?>"
        class="hvnly-gallery-hidden" />
</div>
		<?php
        return ob_get_clean();
    }

    /**
     * Render a single gallery item
     */
    private function render_gallery_item( $image_id, $gallery_id, $title_input_name, $caption_input_name, $ids_input_name ) {
        $image = wp_get_attachment_image_src( (int) $image_id, 'medium' );
        if ( ! $image ) {
            $image = wp_get_attachment_image_src( (int) $image_id, 'thumbnail' );
        }
        if ( ! $image ) {
            return;
        }

        $attachment = get_post( $image_id );
        $title      = $attachment ? $attachment->post_title : '';
        $caption    = $attachment ? $attachment->post_excerpt : '';
        $file_path  = get_attached_file( (int) $image_id );
        $filename   = $file_path ? wp_basename( $file_path ) : ( $title ? $title : (string) $image_id );
        ?>
<li class="hvnly-gallery-item" data-id="<?php echo esc_attr($image_id); ?>"
    data-gallery-id="<?php echo esc_attr($gallery_id); ?>">

    <div class="hvnly-gallery-item-media">
        <img src="<?php echo esc_url($image[0]); ?>" alt="<?php echo esc_attr($title); ?>" />
        <div class="hvnly-gallery-item-overlay">
            <div class="hvnly-gallery-item-actions">
                <a href="#" class="hvnly-gallery-edit" title="<?php esc_attr_e('Edit Image', 'havenlytics'); ?>" aria-label="<?php esc_attr_e('Edit Image', 'havenlytics'); ?>">
                    <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                </a>
                <a href="#" class="hvnly-gallery-remove" title="<?php esc_attr_e('Remove Image', 'havenlytics'); ?>" aria-label="<?php esc_attr_e('Remove Image', 'havenlytics'); ?>">
                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                </a>
            </div>
            <div class="hvnly-gallery-item-meta">
                <span class="hvnly-gallery-item-filename"><?php echo esc_html( $filename ); ?></span>
            </div>
        </div>
    </div>

    <input type="hidden" name="<?php echo esc_attr($title_input_name); ?>[]" value="<?php echo esc_attr($title); ?>" />

    <input type="hidden" name="<?php echo esc_attr($caption_input_name); ?>[]"
        value="<?php echo esc_attr($caption); ?>" />

    <input type="hidden" name="<?php echo esc_attr($ids_input_name); ?>[]" value="<?php echo esc_attr($image_id); ?>" />
</li>
		<?php
    }

    public function save( $post_id, $field_name, $value, $extra = null ) {
        // Sanitize and save to the unique field name (images)
        $image_ids   = explode(',', sanitize_text_field($value));
        $image_ids   = array_map('intval', array_filter($image_ids));
        $clean_value = implode(',', $image_ids);

        // Get the group_base_id from the extra array or extract from field_name
        $group_base_id = $extra['group_base_id'] ?? '';

        // If no group_base_id in extra, try to extract from field_name
        if (empty($group_base_id) && strpos($field_name, '_images') !== false) {
            $group_base_id = str_replace('_images', '', $field_name);
        }

        // Save the images to the field name (which should be {group_base_id}_images)
        if ( ! empty($clean_value)) {
            update_post_meta($post_id, $field_name, $clean_value);
        } else {
            hvnly_safe_delete_post_meta($post_id, $field_name, 'user_save_empty');
        }

        // Also handle the title if present in POST
        if ( ! empty($group_base_id)) {
            $title_field_name = $group_base_id . '_title';
            $title_value      = filter_input(INPUT_POST, $title_field_name, FILTER_UNSAFE_RAW);
            if ($title_value !== null) {
                update_post_meta($post_id, $title_field_name, sanitize_text_field($title_value));
            }
        }
    }

    public function sanitize( $value ) {
        $image_ids = explode(',', $value);
        return implode(',', array_map('intval', array_filter($image_ids)));
    }

    public function validate( $value, $field ) {
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
                hvnly_esc_html_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Gallery' ) )
            ));
        }

        return true;
    }

    public function enqueue_assets() {
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_style(
            'hvnly-gallery-field',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-gallery-field.css',
            array(),
            HVNLYNAB_VERSION
        );

        wp_enqueue_script(
            'hvnly-gallery-field',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-gallery-field.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            HVNLYNAB_VERSION,
            true
        );
    }
}
