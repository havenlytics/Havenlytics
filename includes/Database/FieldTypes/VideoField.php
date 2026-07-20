<?php
/**
 * Video Field Handler - FIXED to prevent duplicate rendering
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

defined( 'ABSPATH' ) || exit;

class VideoField extends BaseFieldType {
    public function __construct() {
        parent::__construct('video');
        $this->requires_assets = true;
    }
    
    public function render($field, $value, $post_id) {
        $field = $this->prepare_group_field( $field, 'VideoField' );

        // $value is the URL already resolved (and gate-checked) by
        // get_field_value() in Havenlytics_Type::render_field().  We use it as
        // the authoritative URL rather than doing a raw DB re-read, so that the
        // type_fallback_claimed gate (which prevents data leakage between group
        // instances) is respected here as well.

        $field_base = $this->get_field_base_name($field);
        if (empty($field_base)) {
            $field_base = '_hvnly_video';
        }

        $title_field     = $field_base . '_title';
        $url_field       = $field_base . '_url';
        $thumbnail_field = $field_base . '_thumbnail';

        $title_value     = $this->resolve_video_subfield_value( $post_id, $field, $field_base, 'title', $title_field );
        $url_value       = $this->resolve_video_subfield_value( $post_id, $field, $field_base, 'url', $url_field, $value );
        $thumbnail_value = $this->resolve_video_subfield_value( $post_id, $field, $field_base, 'thumbnail', $thumbnail_field );

        ob_start();
        ?>
<div class="hvnly-video-field-container hvnly-video-group-wrapper"
    data-field-id="<?php echo esc_attr($field['id']); ?>"
    data-group-base-id="<?php echo esc_attr($field_base); ?>">
    <!-- Video Title Field -->
    <div class="hvnly-video-subfield" data-field-id="<?php echo esc_attr($title_field); ?>"
        data-field-type="text">
        <div class="hvnly-video-subfield-input">
            <label><?php esc_html_e('Video Title', 'havenlytics'); ?></label>
            <input type="text" id="<?php echo esc_attr($title_field); ?>" name="<?php echo esc_attr($title_field); ?>"
                value="<?php echo esc_attr($title_value); ?>"
                placeholder="<?php esc_attr_e('Enter video title', 'havenlytics'); ?>"
                class="hvnly__dyamic_metabox_tab__input widefat" data-field-type="text">
        </div>
    </div>

    <!-- Video URL Field -->
    <div class="hvnly-video-subfield" data-field-id="<?php echo esc_attr($url_field); ?>"
        data-field-type="text">
        <div class="hvnly-video-subfield-input">
            <label><?php esc_html_e('Video URL', 'havenlytics'); ?></label>
            <input type="url" id="<?php echo esc_attr($url_field); ?>" name="<?php echo esc_attr($url_field); ?>"
                value="<?php echo esc_attr($url_value); ?>"
                placeholder="<?php esc_attr_e('Enter full YouTube URL or ID', 'havenlytics'); ?>"
                class="hvnly__dyamic_metabox_tab__input widefat" data-field-type="url">
            <?php if (!empty($url_value)) : ?>
            <div class="hvnly-video-preview" style="margin-top: 10px;">
                <?php 
                $video_id = $this->extract_youtube_id($url_value);
                if ($video_id) : ?>
                <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>" frameborder="0"
                    allowfullscreen style="width: 100%; max-width: 400px; height: 225px; border-radius: 8px;"></iframe>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Video Thumbnail Field -->
    <div class="hvnly-video-subfield" data-field-id="<?php echo esc_attr($thumbnail_field); ?>"
        data-field-type="file">
        <div class="hvnly-video-subfield-input">
            <label><?php esc_html_e('Video Thumbnail', 'havenlytics'); ?></label>
            <div class="hvnly-meta-field" style="display: block; margin: 0px">
                <div class="hvnly-meta-input hvnly-popup-meta-input">
                    <input type="text" id="<?php echo esc_attr($thumbnail_field); ?>"
                        name="<?php echo esc_attr($thumbnail_field); ?>" class="widefat"
                        value="<?php echo esc_attr($thumbnail_value); ?>"
                        placeholder="<?php esc_attr_e('Enter image URL or upload', 'havenlytics'); ?>">
                    <div class="hvnly-preview-container">
                        <?php if (!empty($thumbnail_value)): ?>
                        <div class="hvnly-preview-wrapper">
                            <img src="<?php echo esc_url($thumbnail_value); ?>" alt=""
                                style="max-width: 150px; height: auto;">
                            <button type="button" class="hvnly-remove-preview"
                                data-target="#<?php echo esc_attr($thumbnail_field); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="button hvnly-upload-button"
                        data-target="#<?php echo esc_attr($thumbnail_field); ?>" data-type="image">
                        <?php esc_html_e('Upload Thumbnail', 'havenlytics'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
        return ob_get_clean();
    }
    
    /**
     * Derive the shared base name for this video group's three sub-fields.
     *
     * Priority:
     *  1. group_base_id  — set by the builder config, always the canonical key.
     *  2. Field name suffix stripping — strips _url / _title / _thumbnail from
     *     the field's meta-key name using a suffix (not substr_replace) so that
     *     the suffix is matched at the END of the string only.
     */
    private function get_field_base_name($field) {
        // Priority 1 — group_base_id is always the most reliable source.
        if (!empty($field['group_base_id'])) {
            return $field['group_base_id'];
        }

        // Priority 2 — strip known suffix from field name.
        $field_name = $field['name'] ?? '';
        if (!empty($field_name)) {
            foreach (['_url', '_title', '_thumbnail'] as $suffix) {
                $len = strlen($suffix);
                if (substr($field_name, -$len) === $suffix) {
                    return substr($field_name, 0, -$len);
                }
            }
        }

        return '';
    }

    /**
     * Resolve a video sub-field using the same chain as the frontend (MetaResolver).
     *
     * @param int          $post_id     Post ID.
     * @param array        $field       Builder field config.
     * @param string       $field_base  Group base ID for input names.
     * @param string       $meta_key    title|url|thumbnail.
     * @param string       $primary_key Full meta key for this sub-field.
     * @param mixed        $preset      Optional value already resolved by the metabox.
     * @return string
     */
    private function resolve_video_subfield_value( $post_id, $field, $field_base, $meta_key, $primary_key, $preset = null ) {
        if ( 'url' === $meta_key && null !== $preset && '' !== $preset && false !== $preset ) {
            return (string) $preset;
        }

        $probe = array_merge(
            $field,
            array(
                'group_type'    => $field['group_type'] ?? 'video',
                'metaKey'       => $meta_key,
                'group_base_id' => $field_base,
                'name'          => $primary_key,
            )
        );

        $resolved = $this->resolve_group_meta( (int) $post_id, $probe, $primary_key, $meta_key );
        if ( '' !== $resolved && false !== $resolved && null !== $resolved ) {
            return (string) $resolved;
        }

        $direct = get_post_meta( $post_id, $primary_key, true );
        return ( '' !== $direct && false !== $direct && null !== $direct ) ? (string) $direct : '';
    }

    /**
     * Whether an empty POST value should skip overwriting compatibility-stored data.
     *
     * @param int    $post_id    Post ID.
     * @param string $field_base Group base ID.
     * @param string $meta_key   title|url|thumbnail.
     * @return bool
     */
    private function compatibility_value_exists( $post_id, $field_base, $meta_key, $field = array() ) {
        $primary_key = $field_base . '_' . $meta_key;
        $probe       = array_merge(
            $field,
            array(
                'group_type'    => 'video',
                'metaKey'       => $meta_key,
                'group_base_id' => $field_base,
                'name'          => $primary_key,
            )
        );

        $existing = $this->resolve_group_meta( (int) $post_id, $probe, $primary_key, $meta_key );

        if ( '' === $existing || false === $existing || null === $existing ) {
            return false;
        }

        $direct = get_post_meta( $post_id, $primary_key, true );
        return '' === $direct || false === $direct || null === $direct;
    }
    
    /**
     * Extract YouTube video ID from URL
     */
    private function extract_youtube_id($url) {
        if (empty($url)) return null;
        
        $patterns = [
            '/(?:youtube\.com\/watch\?v=)([^&]+)/',
            '/(?:youtu\.be\/)([^?]+)/',
            '/(?:youtube\.com\/embed\/)([^?]+)/',
            '/(?:youtube\.com\/v\/)([^?]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    public function save($post_id, $field_name, $value, $extra = null) {
        // Determine the field base from the field name
        $field_base = '';
        
        if (strpos($field_name, '_url') !== false) {
            $field_base = str_replace('_url', '', $field_name);
        } elseif (strpos($field_name, '_title') !== false) {
            $field_base = str_replace('_title', '', $field_name);
        } elseif (strpos($field_name, '_thumbnail') !== false) {
            $field_base = str_replace('_thumbnail', '', $field_name);
        }
        
        if (!empty($field_base)) {
            $field_probe = array(
                'group_base_id' => $field_base,
                'group_type'    => 'video',
                'name'          => $field_name,
            );

            // Get all three values from POST
            $title_value = filter_input(INPUT_POST, $field_base . '_title', FILTER_UNSAFE_RAW);
            $url_value = filter_input(INPUT_POST, $field_base . '_url', FILTER_UNSAFE_RAW);
            $thumbnail_value = filter_input(INPUT_POST, $field_base . '_thumbnail', FILTER_UNSAFE_RAW);
            
            if ($title_value !== null && $title_value !== false) {
                $title_value = (string) $title_value;
                if ( '' !== trim( $title_value ) || ! $this->compatibility_value_exists( $post_id, $field_base, 'title', $field_probe ) ) {
                    update_post_meta($post_id, $field_base . '_title', sanitize_text_field($title_value));
                }
            }
            
            if ($url_value !== null && $url_value !== false) {
                $url_value = (string) $url_value;
                if ( '' !== trim( $url_value ) || ! $this->compatibility_value_exists( $post_id, $field_base, 'url', $field_probe ) ) {
                    update_post_meta($post_id, $field_base . '_url', sanitize_text_field($url_value));
                }
            }
            
            if ($thumbnail_value !== null && $thumbnail_value !== false) {
                $thumbnail_value = (string) $thumbnail_value;
                if ( '' !== trim( $thumbnail_value ) || ! $this->compatibility_value_exists( $post_id, $field_base, 'thumbnail', $field_probe ) ) {
                    update_post_meta($post_id, $field_base . '_thumbnail', esc_url_raw($thumbnail_value));
                }
            }
        } else {
            // Fallback: just save the value
            update_post_meta($post_id, $field_name, sanitize_text_field($value));
        }
    }
    
    public function sanitize($value) {
        return sanitize_text_field($value);
    }
    
    public function validate($value, $field) {
        if (empty($field['is_required'])) {
            return true;
        }

        $field_base = $this->get_field_base_name($field);
        $url        = '';

        if (!empty($field_base)) {
            $url_raw = filter_input(INPUT_POST, $field_base . '_url', FILTER_UNSAFE_RAW);
            $url     = is_string($url_raw) ? trim($url_raw) : '';
        } elseif (is_string($value)) {
            $url = trim($value);
        }

        if ('' === $url) {
            return new \WP_Error(
                'required_video',
                sprintf(
                    /* translators: %s: video field label. */
                    __('The video URL for "%s" is required.', 'havenlytics'),
                    esc_html($field['label'] ?? __('Video', 'havenlytics'))
                )
            );
        }

        return true;
    }
    
    public function enqueue_assets() {
        wp_enqueue_media();
        
        wp_enqueue_script(
            'hvnly-video-field',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-video-field.js',
            ['jquery'],
            HVNLYNAB_VERSION,
            true
        );
    }
}