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

        $youtube_id      = ! empty( $url_value ) ? $this->extract_youtube_id( $url_value ) : null;
        $hero_image_url  = '';
        $hero_source     = 'empty';

        if ( ! empty( $thumbnail_value ) ) {
            $hero_image_url = $thumbnail_value;
            $hero_source    = 'custom';
        } elseif ( $youtube_id ) {
            $hero_image_url = 'https://img.youtube.com/vi/' . $youtube_id . '/hqdefault.jpg';
            $hero_source    = 'youtube';
        }

        $has_hero     = '' !== $hero_image_url;
        $can_preview  = (bool) $youtube_id;
        $has_custom   = ! empty( $thumbnail_value );
        $embed_title  = $title_value ? $title_value : __( 'Property Video', 'havenlytics' );

        ob_start();
        ?>
<div class="hvnly-video-field-container hvnly-video-group-wrapper<?php echo $has_hero ? '' : ' is-empty'; ?><?php echo $can_preview ? ' has-video-url' : ''; ?><?php echo $has_custom ? ' has-custom-thumb' : ''; ?>"
    data-field-id="<?php echo esc_attr($field['id']); ?>"
    data-group-base-id="<?php echo esc_attr($field_base); ?>"
    data-hero-source="<?php echo esc_attr( $hero_source ); ?>">

    <div class="hvnly-video-layout">
        <div class="hvnly-video-col hvnly-video-col--media">
            <section class="hvnly-video-media" aria-label="<?php esc_attr_e( 'Video thumbnail', 'havenlytics' ); ?>">
                <div class="hvnly-video-hero<?php echo $has_hero ? '' : ' is-empty'; ?>">
                    <div class="hvnly-preview-container hvnly-video-hero-media" aria-live="polite">
                        <?php if ( $has_hero ) : ?>
                        <div class="hvnly-preview-wrapper hvnly-video-hero-frame">
                            <img src="<?php echo esc_url( $hero_image_url ); ?>"
                                alt="<?php esc_attr_e( 'Video thumbnail', 'havenlytics' ); ?>"
                                class="hvnly-video-hero-image"
                                data-hero-source="<?php echo esc_attr( $hero_source ); ?>">
                        </div>
                        <?php else : ?>
                        <div class="hvnly-video-hero-empty">
                            <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                            <p class="hvnly-video-hero-empty-title"><?php esc_html_e( 'No thumbnail yet', 'havenlytics' ); ?></p>
                            <p class="hvnly-video-hero-empty-subtitle"><?php esc_html_e( 'Upload a poster image, or paste a YouTube URL to use its default frame.', 'havenlytics' ); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <button type="button"
                        class="hvnly-video-play-overlay"
                        <?php disabled( ! $can_preview ); ?>
                        aria-label="<?php esc_attr_e( 'Preview Video', 'havenlytics' ); ?>"
                        <?php echo $can_preview ? '' : ' hidden'; ?>>
                        <span class="hvnly-video-play-btn" aria-hidden="true">
                            <svg class="hvnly-video-play-icon" viewBox="0 0 24 24" width="28" height="28" focusable="false">
                                <path fill="currentColor" d="M8.5 6.2v11.6c0 .7.8 1.1 1.4.7l9-5.8c.5-.4.5-1.1 0-1.4l-9-5.8c-.6-.4-1.4 0-1.4.7z"/>
                            </svg>
                        </span>
                    </button>
                </div>

                <div class="hvnly-video-actions" role="toolbar" aria-label="<?php esc_attr_e( 'Thumbnail actions', 'havenlytics' ); ?>">
                    <button type="button" class="hvnly-upload-button hvnly-video-action hvnly-video-action-primary"
                        data-target="#<?php echo esc_attr($thumbnail_field); ?>" data-type="image">
                        <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                        <span class="hvnly-video-action-label hvnly-video-action-label--replace"><?php echo $has_custom ? esc_html__( 'Replace Thumbnail', 'havenlytics' ) : esc_html__( 'Upload Thumbnail', 'havenlytics' ); ?></span>
                    </button>
                    <button type="button" class="hvnly-video-action hvnly-video-preview-toggle"
                        <?php disabled( ! $can_preview ); ?>
                        aria-expanded="false"
                        aria-controls="hvnly-video-inline-preview-<?php echo esc_attr( $field_base ); ?>">
                        <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                        <?php esc_html_e( 'Preview Video', 'havenlytics' ); ?>
                    </button>
                    <button type="button" class="hvnly-remove-preview hvnly-video-action hvnly-video-remove-thumb"
                        data-target="#<?php echo esc_attr($thumbnail_field); ?>"
                        <?php disabled( ! $has_custom ); ?>
                        aria-label="<?php esc_attr_e( 'Remove Thumbnail', 'havenlytics' ); ?>">
                        <?php esc_html_e( 'Remove', 'havenlytics' ); ?>
                    </button>
                </div>
            </section>
        </div>

        <div class="hvnly-video-col hvnly-video-col--fields">
            <header class="hvnly-video-header">
                <div class="hvnly-video-header-icon" aria-hidden="true">
                    <span class="dashicons dashicons-video-alt3"></span>
                </div>
                <div class="hvnly-video-header-copy">
                    <h3 class="hvnly-video-header-title"><?php esc_html_e( 'Property Video', 'havenlytics' ); ?></h3>
                    <p class="hvnly-video-header-help"><?php esc_html_e( 'Set the listing poster and YouTube link. Preview only when you need to verify the embed.', 'havenlytics' ); ?></p>
                </div>
            </header>

            <div class="hvnly-video-fields-primary">
                <!-- Video Title Field -->
                <div class="hvnly-video-subfield" data-field-id="<?php echo esc_attr($title_field); ?>"
                    data-field-type="text">
                    <div class="hvnly-video-subfield-input">
                        <label for="<?php echo esc_attr($title_field); ?>"><?php esc_html_e('Video Title', 'havenlytics'); ?></label>
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
                        <label for="<?php echo esc_attr($url_field); ?>"><?php esc_html_e('Video URL', 'havenlytics'); ?></label>
                        <input type="url" id="<?php echo esc_attr($url_field); ?>" name="<?php echo esc_attr($url_field); ?>"
                            value="<?php echo esc_attr($url_value); ?>"
                            placeholder="<?php esc_attr_e('Enter full YouTube URL or ID', 'havenlytics'); ?>"
                            class="hvnly__dyamic_metabox_tab__input widefat" data-field-type="url">
                    </div>
                </div>
            </div>

            <!-- Thumbnail URL stays in DOM for save / media target; collapsed as Advanced. -->
            <details class="hvnly-video-advanced">
                <summary class="hvnly-video-advanced-summary">
                    <span><?php esc_html_e( 'Advanced', 'havenlytics' ); ?></span>
                </summary>
                <div class="hvnly-video-subfield hvnly-video-subfield--thumbnail" data-field-id="<?php echo esc_attr($thumbnail_field); ?>"
                    data-field-type="file">
                    <div class="hvnly-video-subfield-input">
                        <div class="hvnly-meta-field">
                            <div class="hvnly-meta-input hvnly-popup-meta-input">
                                <label class="hvnly-video-thumb-url-label" for="<?php echo esc_attr($thumbnail_field); ?>"><?php esc_html_e('Thumbnail URL', 'havenlytics'); ?></label>
                                <p class="description hvnly-video-advanced-help"><?php esc_html_e( 'Usually set via Upload / Replace. Edit manually only if you need a direct image URL.', 'havenlytics' ); ?></p>
                                <input type="text" id="<?php echo esc_attr($thumbnail_field); ?>"
                                    name="<?php echo esc_attr($thumbnail_field); ?>" class="widefat"
                                    value="<?php echo esc_attr($thumbnail_value); ?>"
                                    placeholder="<?php esc_attr_e('Enter image URL or upload', 'havenlytics'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        </div>
    </div>

    <div class="hvnly-video-inline-preview"
        id="hvnly-video-inline-preview-<?php echo esc_attr( $field_base ); ?>"
        hidden>
        <div class="hvnly-video-inline-preview-bar">
            <span class="hvnly-video-inline-preview-label"><?php esc_html_e( 'Video Preview', 'havenlytics' ); ?></span>
            <button type="button" class="button-link hvnly-video-inline-preview-close">
                <?php esc_html_e( 'Close preview', 'havenlytics' ); ?>
            </button>
        </div>
        <div class="hvnly-video-preview" data-role="embed" data-embed-title="<?php echo esc_attr( $embed_title ); ?>"></div>
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
                    hvnly_esc_html_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Video' ) )
                )
            );
        }

        return true;
    }
    
    public function enqueue_assets() {
        wp_enqueue_media();

        wp_enqueue_style(
            'hvnly-video-field',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-video-field.css',
            array( 'hvnly-admin-metabox' ),
            HVNLYNAB_VERSION
        );
        
        wp_enqueue_script(
            'hvnly-video-field',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-video-field.js',
            ['jquery'],
            HVNLYNAB_VERSION,
            true
        );
    }
}
