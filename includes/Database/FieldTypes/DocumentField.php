<?php
/**
 * Document Field Handler - Property Documents Repeater with URL Type Selector
 * 
 * @package HvnlyNab\Database\FieldTypes
 * @since 2.0.0
 */

namespace HvnlyNab\Database\FieldTypes;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DocumentField
 * 
 * Handles document repeater fields - ONE field that contains multiple documents
 */
class DocumentField extends BaseFieldType {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('property_docs');
        $this->requires_assets = true;
    }
    
/**
 * Get the correct field base name from the field data
 *
 * @param array $field Field configuration
 * @return string Field base name
 */
private function get_field_base_name($field) {
    // Priority 1: Use group_base_id if available
    if (isset($field['group_base_id']) && !empty($field['group_base_id'])) {
        return $field['group_base_id'];
    }
    
    // Priority 2: Use document_base_id if set
    if (isset($field['document_base_id']) && !empty($field['document_base_id'])) {
        return $field['document_base_id'];
    }
    
    // Priority 3: Extract from field name (remove _documents suffix)
    $field_name = $field['name'] ?? $field['id'] ?? '';
    if (!empty($field_name)) {
        if (strpos($field_name, '_documents') !== false) {
            return str_replace('_documents', '', $field_name);
        }
        // Also handle if field is the documents JSON field directly
        if (strpos($field_name, '_documents') === false && 
            (strpos($field_name, 'property_docs_') === 0 || strpos($field_name, 'documents_') === 0)) {
            // This might already be the base ID
            return $field_name;
        }
    }
    
    // Priority 4: Use id as fallback
    if (!empty($field['id']) && strpos($field['id'], '_documents') !== false) {
        return str_replace('_documents', '', $field['id']);
    }
    
    // Ultimate fallback: generate a new base ID
    return 'property_docs_' . time();
}

    /**
     * Render the document field - REPEATER UI
     *
     * @param array $field Field configuration
     * @param mixed $value Current field value
     * @param int $post_id Post ID
     * @return string HTML output
     */
    public function render($field, $value, $post_id) {
        $field = $this->prepare_group_field( $field, 'DocumentField' );

        // Reads only this group's {base}_documents meta (no global legacy fallback).
        $field_base = $this->get_field_base_name( $field );
        
        // Use the correct base for field names
        $field_name = $field_base . '_documents';
        $sidebar_field = $field_base . '_show_in_sidebar';
        
        // Get saved documents
        $documents = $this->get_documents($post_id, $field_name, $field_base, $field);
        
        // Get sidebar status
        $saved_sidebar = get_post_meta($post_id, $sidebar_field, true);
        $show_in_sidebar = $field['show_in_sidebar'] ?? true;
        
        if ($saved_sidebar !== '') {
            $show_in_sidebar = (bool)$saved_sidebar;
        }
        
        ob_start();
        ?>
<div class="hvnly-document-field-container" data-field-id="<?php echo esc_attr($field['id']); ?>"
    data-group-base-id="<?php echo esc_attr($field_base); ?>"
    data-group-id="<?php echo esc_attr($field['group_id'] ?? ''); ?>"
    data-field-name="<?php echo esc_attr($field_name); ?>" data-sidebar-field="<?php echo esc_attr($sidebar_field); ?>"
    data-sidebar-status="<?php echo $show_in_sidebar ? 'visible' : 'hidden'; ?>">

    <div class="hvnly-document-field-header">
        <div class="hvnly-document-field-label">
            <label><?php echo esc_html( hvnly_translate_ui( (string) ( ! empty( $field['label'] ) ? $field['label'] : 'Property Documents' ) ) ); ?></label>
            <p class="description">
                <?php esc_html_e('Add multiple documents. Each document can have an icon, label, and URL.', 'havenlytics'); ?>
            </p>
        </div>

        <!-- Sidebar toggle -->
        <div
            class="hvnly-document-sidebar-group-toggle <?php echo $show_in_sidebar ? 'sidebar-visible' : 'sidebar-hidden'; ?>">
            <label class="hvnly-toggle-switch">
                <input type="checkbox" class="hvnly-document-group-sidebar-toggle"
                    name="<?php echo esc_attr($sidebar_field); ?>" value="1"
                    <?php checked($show_in_sidebar, true); ?> />
                <span class="hvnly-toggle-slider"></span>
            </label>
            <span class="hvnly-toggle-label">
                <?php if ($show_in_sidebar): ?>
                <i class="fas fa-eye"></i> <?php esc_html_e('Show in Sidebar', 'havenlytics'); ?>
                <?php else: ?>
                <i class="fas fa-eye-slash"></i> <?php esc_html_e('Hide from Sidebar', 'havenlytics'); ?>
                <?php endif; ?>
            </span>
            <span class="status-dot"
                style="background-color: <?php echo $show_in_sidebar ? 'var(--hvnly-brand-success, #00B46A)' : 'var(--hvnly-brand-error, #FF4D4F)'; ?>; box-shadow: 0 0 0 2px <?php echo $show_in_sidebar ? 'rgba(0, 180, 106, 0.2)' : 'rgba(255, 77, 79, 0.2)'; ?>;"></span>
        </div>
    </div>

    <div class="hvnly-document-repeater-items">
        <?php 
                if (!empty($documents)) {
                    foreach ($documents as $index => $document) {
                        $this->render_document_item($field_base, $index, $document, $field_name);
                    }
                } else {
                    $this->render_document_item($field_base, 0, ['icon' => '', 'label' => '', 'url' => '', 'url_type' => 'custom'], $field_name);
                }
                ?>
    </div>

    <div class="hvnly-document-repeater-actions">
        <button type="button" class="hvnly-admin-primary-button hvnly-document-add-item">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e('Add New Document', 'havenlytics'); ?>
        </button>
    </div>
</div>
<?php
        return ob_get_clean();
    }
    
    /**
     * Render a single document item with URL type selector
     *
     * @param string $field_base Field base ID
     * @param int $index Item index
     * @param array $document Document data
     * @param string $field_name Field name
     * @return void
     */
    private function render_document_item($field_base, $index, $document, $field_name) {
        $icon = $document['icon'] ?? '';
        $label = $document['label'] ?? '';
        $url = $document['url'] ?? '';
        $url_type = $document['url_type'] ?? $this->detect_url_type($url);
        
        // URL type options
        $url_types = [
            'custom'       => __('Custom URL', 'havenlytics'),
            'pdf'          => __('PDF Document', 'havenlytics'),
            'youtube'      => __('YouTube Video', 'havenlytics'),
            'vimeo'        => __('Vimeo Video', 'havenlytics'),
            'website'      => __('Website Link', 'havenlytics'),
            'map'          => __('Google Maps', 'havenlytics'),
            'virtual_tour' => __('Virtual Tour', 'havenlytics'),
            'floor_plan'   => __('Floor Plan', 'havenlytics'),
            'image'        => __('Image', 'havenlytics'),
            'video'        => __('Video File', 'havenlytics'),
        ];
        ?>
<div class="hvnly-document-repeater-item" data-item-index="<?php echo esc_attr($index); ?>">
    <div class="hvnly-document-item-header">
        <span class="hvnly-document-drag-handle">
            <span class="dashicons dashicons-menu"></span>
        </span>
        <span class="hvnly-document-item-title">
            <?php echo !empty($label) ? esc_html($label) : esc_html__('New Document', 'havenlytics'); ?>
        </span>
        <div class="hvnly-document-item-actions">
            <button type="button" class="button hvnly-document-move-up"
                title="<?php esc_attr_e('Move Up', 'havenlytics'); ?>">
                <span class="dashicons dashicons-arrow-up-alt2"></span>
            </button>
            <button type="button" class="button hvnly-document-move-down"
                title="<?php esc_attr_e('Move Down', 'havenlytics'); ?>">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <button type="button" class="button hvnly-document-remove-item"
                title="<?php esc_attr_e('Remove Document', 'havenlytics'); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>

    <div class="hvnly-document-item-fields">
        <!-- Icon Field -->
        <div class="hvnly-document-field-row">
            <label>
                <?php esc_html_e('Font Awesome Icon', 'havenlytics'); ?>
                <span class="hvnly-tooltip"
                    title="<?php esc_attr_e('Font Awesome 6 icon name (e.g., file-pdf, file-word, file-lines)', 'havenlytics'); ?>">?</span>
            </label>
            <div class="hvnly-document-icon-field">
                <input type="text" class="hvnly-document-icon-input widefat"
                    name="<?php echo esc_attr($field_name); ?>_icons[]" value="<?php echo esc_attr($icon); ?>"
                    placeholder="<?php esc_attr_e('file-pdf', 'havenlytics'); ?>" />
                <div class="hvnly-document-icon-preview">
                    <?php if (!empty($icon)): ?>
                    <i class="fas fa-<?php echo esc_attr($icon); ?>"></i>
                    <?php endif; ?>
                </div>
                <button type="button" class="button hvnly-document-icon-selector">
                    <?php esc_html_e('Select Icon', 'havenlytics'); ?>
                </button>
            </div>
        </div>

        <!-- Label Field -->
        <div class="hvnly-document-field-row">
            <label>
                <?php esc_html_e('Document Label', 'havenlytics'); ?>
                <span class="hvnly-required" aria-hidden="true">*</span>
            </label>
            <input type="text" class="hvnly-document-label-input widefat"
                name="<?php echo esc_attr($field_name); ?>_labels[]" value="<?php echo esc_attr($label); ?>"
                placeholder="<?php esc_attr_e('e.g., Floor Plan, Brochure, EPC', 'havenlytics'); ?>" />
        </div>

        <!-- URL Type Selector -->
        <div class="hvnly-document-field-row">
            <label>
                <?php esc_html_e('URL Type', 'havenlytics'); ?>
            </label>
            <select class="hvnly-document-url-type-select widefat"
                name="<?php echo esc_attr($field_name); ?>_url_types[]">
                <?php foreach ($url_types as $value => $label_text): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($url_type, $value); ?>>
                    <?php echo esc_html($label_text); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- URL Field -->
        <div class="hvnly-document-field-row">
            <label>
                <?php esc_html_e('Document URL', 'havenlytics'); ?>
                <span class="hvnly-required" aria-hidden="true">*</span>
            </label>
            <div class="hvnly-document-url-field">
                <input type="url" class="hvnly-document-url-input widefat"
                    name="<?php echo esc_attr($field_name); ?>_urls[]" value="<?php echo esc_attr($url); ?>"
                    placeholder="<?php echo esc_attr($this->get_url_placeholder($url_type)); ?>" />
                <button type="button" class="button hvnly-document-upload-btn" data-type="document">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Upload', 'havenlytics'); ?>
                </button>
            </div>
            <p class="description hvnly-url-type-hint" data-type="<?php echo esc_attr($url_type); ?>">
                <?php echo esc_html($this->get_url_type_hint($url_type)); ?>
            </p>
        </div>
    </div>
</div>
<?php
    }
    
    /**
     * Get placeholder text for URL type
     *
     * @param string $url_type URL type
     * @return string Placeholder text
     */
    private function get_url_placeholder($url_type) {
        $placeholders = [
            'custom'       => 'https://example.com/document.pdf',
            'pdf'          => 'https://example.com/document.pdf',
            'youtube'      => 'https://youtu.be/xxxx or https://youtube.com/watch?v=xxxx',
            'vimeo'        => 'https://vimeo.com/xxxx',
            'website'      => 'https://example.com',
            'map'          => 'https://maps.google.com/?q=...',
            'virtual_tour' => 'https://example.com/tour',
            'floor_plan'   => 'https://example.com/floor-plan.pdf',
            'image'        => 'https://example.com/image.jpg',
            'video'        => 'https://example.com/video.mp4'
        ];
        
        return $placeholders[$url_type] ?? $placeholders['custom'];
    }
    
    /**
     * Get hint text for URL type
     *
     * @param string $url_type URL type
     * @return string Hint text
     */
    private function get_url_type_hint($url_type) {
        $hints = [
            'custom'       => __('Enter any valid URL', 'havenlytics'),
            'pdf'          => __('Upload a PDF or enter a URL to a PDF file', 'havenlytics'),
            'youtube'      => __('Enter YouTube video URL', 'havenlytics'),
            'vimeo'        => __('Enter Vimeo video URL', 'havenlytics'),
            'website'      => __('Enter website URL', 'havenlytics'),
            'map'          => __('Enter Google Maps URL', 'havenlytics'),
            'virtual_tour' => __('Enter virtual tour URL', 'havenlytics'),
            'floor_plan'   => __('Enter floor plan image URL or PDF', 'havenlytics'),
            'image'        => __('Upload an image or enter image URL', 'havenlytics'),
            'video'        => __('Upload a video file or enter video URL', 'havenlytics'),
        ];
        
        return $hints[$url_type] ?? $hints['custom'];
    }
    
    /**
     * Get documents from post meta with backward compatibility
     *
     * @param int    $post_id    Post ID.
     * @param string $field_name Field name.
     * @param string $field_base Field base ID.
     * @param array  $field      Field config.
     * @return array Array of documents
     */
    private function get_documents( $post_id, $field_name, $field_base, $field = array() ) {
        $probe = array_merge(
            $field,
            array(
                'group_type'    => 'property_docs',
                'group_base_id' => $field_base,
                'metaKey'       => 'documents',
                'name'          => $field_name,
            )
        );

        $value = $this->resolve_group_meta( (int) $post_id, $probe, $field_name, 'documents' );

        if ( ! empty( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) {
                foreach ( $decoded as &$doc ) {
                    if ( ! isset( $doc['url_type'] ) ) {
                        $doc['url_type'] = $this->detect_url_type( $doc['url'] ?? '' );
                    }
                }
                return $decoded;
            }
        }

        if (
            class_exists( '\HvnlyNab\Core\GroupFieldIdentity' )
            && \HvnlyNab\Core\GroupFieldIdentity::is_strictly_scoped_field( $probe )
        ) {
            return array();
        }

        // Backward compatibility: legacy separate fields for this base only.
        $icon_value = get_post_meta($post_id, $field_base . '_icon', true);
        $label_value = get_post_meta($post_id, $field_base . '_label', true);
        $url_value = get_post_meta($post_id, $field_base . '_url', true);
        
        if (!empty($icon_value) || !empty($label_value) || !empty($url_value)) {
            $documents = [];
            
            if (!is_array($icon_value) && !is_array($label_value) && !is_array($url_value)) {
                if (!empty($label_value) || !empty($url_value)) {
                    $documents[] = [
                        'icon' => $icon_value ?: '',
                        'label' => $label_value ?: '',
                        'url' => $url_value ?: '',
                        'url_type' => $this->detect_url_type($url_value)
                    ];
                }
            } else {
                $icons = is_array($icon_value) ? $icon_value : [$icon_value];
                $labels = is_array($label_value) ? $label_value : [$label_value];
                $urls = is_array($url_value) ? $url_value : [$url_value];
                
                $count = max(count($icons), count($labels), count($urls));
                
                for ($i = 0; $i < $count; $i++) {
                    $icon = isset($icons[$i]) ? $icons[$i] : '';
                    $label = isset($labels[$i]) ? $labels[$i] : '';
                    $url = isset($urls[$i]) ? $urls[$i] : '';
                    
                    if (!empty($label) && !empty($url)) {
                        $documents[] = [
                            'icon' => $icon,
                            'label' => $label,
                            'url' => $url,
                            'url_type' => $this->detect_url_type($url)
                        ];
                    }
                }
            }
            
            if (!empty($documents)) {
                update_post_meta($post_id, $field_name, wp_json_encode($documents));

                hvnly_safe_delete_post_meta($post_id, $field_base . '_icon', 'user_save_empty');
                hvnly_safe_delete_post_meta($post_id, $field_base . '_label', 'user_save_empty');
                hvnly_safe_delete_post_meta($post_id, $field_base . '_url', 'user_save_empty');
            }
            
            return $documents;
        }
        
        return [];
    }
    
    /**
     * Detect URL type from URL
     *
     * @param string $url URL to detect
     * @return string URL type
     */
    private function detect_url_type($url) {
        $url = strtolower($url);
        
        if (empty($url)) {
            return 'custom';
        }
        
        if (preg_match('/\.(pdf)($|\?|#)/', $url)) {
            return 'pdf';
        }
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)($|\?|#)/', $url)) {
            return 'image';
        }
        if (preg_match('/\.(mp4|webm|ogg|mov|avi)($|\?|#)/', $url)) {
            return 'video';
        }
        
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            return 'youtube';
        }
        if (strpos($url, 'vimeo.com') !== false) {
            return 'vimeo';
        }
        
        if (strpos($url, 'maps.google.com') !== false || strpos($url, 'goo.gl/maps') !== false) {
            return 'map';
        }
        
        if (strpos($url, 'tour') !== false || strpos($url, 'virtual') !== false || strpos($url, '3d') !== false) {
            return 'virtual_tour';
        }
        
        if (strpos($url, 'floor') !== false || strpos($url, 'plan') !== false) {
            return 'floor_plan';
        }
        
        if (preg_match('/^https?:\/\//', $url)) {
            return 'website';
        }
        
        return 'custom';
    }
    
    /**
     * Save document field data
     *
     * @param int $post_id Post ID
     * @param string $field_name Field name
     * @param mixed $value Field value
     * @param mixed $extra Optional extra parameter for compatibility
     * @return void
     */
    public function save($post_id, $field_name, $value = null, $extra = null) {
        // Get all POST data for this document field
        $icons_raw = filter_input(INPUT_POST, $field_name . '_icons', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [];
        $labels_raw = filter_input(INPUT_POST, $field_name . '_labels', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [];
        $urls_raw = filter_input(INPUT_POST, $field_name . '_urls', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [];
        $url_types_raw = filter_input(INPUT_POST, $field_name . '_url_types', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [];
        
        $icons = array_map('sanitize_text_field', $icons_raw);
        $labels = array_map('sanitize_text_field', $labels_raw);
        $urls = array_map('esc_url_raw', $urls_raw);
        $url_types = array_map('sanitize_text_field', $url_types_raw);
        
        $documents = [];
        $count = max(count($icons), count($labels), count($urls), count($url_types));
        
        for ($i = 0; $i < $count; $i++) {
            $icon = isset($icons[$i]) ? $icons[$i] : '';
            $label = isset($labels[$i]) ? $labels[$i] : '';
            $url = isset($urls[$i]) ? $urls[$i] : '';
            $url_type = isset($url_types[$i]) ? $url_types[$i] : 'custom';
            
            if (!empty($label) && !empty($url)) {
                $documents[] = [
                    'icon' => $icon,
                    'label' => $label,
                    'url' => $url,
                    'url_type' => $url_type
                ];
            }
        }
        
        if (!empty($documents)) {
            update_post_meta($post_id, $field_name, wp_json_encode($documents));
        } else {
            hvnly_safe_delete_post_meta($post_id, $field_name, 'user_save_empty');
        }

        // Save sidebar status
        $sidebar_field = str_replace('_documents', '_show_in_sidebar', $field_name);
        $sidebar_raw = filter_input(INPUT_POST, $sidebar_field, FILTER_UNSAFE_RAW);
        $sidebar_value = $sidebar_raw === '1' ? '1' : '0';
        update_post_meta($post_id, $sidebar_field, $sidebar_value);
    }
    
    /**
     * Sanitize document field value
     *
     * @param mixed $value Field value
     * @return string Sanitized JSON
     */
    public function sanitize($value) {
        if (is_array($value)) {
            return wp_json_encode($value);
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $value;
            }
        }
        
        return wp_json_encode([]);
    }
    
    /**
     * Validate document field
     *
     * @param mixed $value Field value
     * @param array $field Field configuration
     * @return bool|\WP_Error
     */
    public function validate($value, $field) {
        if (empty($field['is_required'])) {
            return true;
        }
        
        $labels_raw = filter_input(INPUT_POST, $field['name'] . '_labels', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [];
        $urls_raw = filter_input(INPUT_POST, $field['name'] . '_urls', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [];
        
        $labels = array_map('sanitize_text_field', $labels_raw);
        $urls = array_map('esc_url_raw', $urls_raw);
        
        $has_valid = false;
        foreach ($labels as $index => $label) {
            if (!empty($label) && !empty($urls[$index])) {
                $has_valid = true;
                break;
            }
        }
        
        if (!$has_valid) {
            $message = sprintf(
                /* translators: %s: document field label. */
                __('At least one document is required for "%s".', 'havenlytics'),
                hvnly_esc_html_ui( (string) $field['label'] )
            );
            return new \WP_Error('required_field', $message);
        }
        
        return true;
    }
    
    /**
     * Check if field requires assets
     *
     * @return bool
     */
    public function requires_assets() {
        return true;
    }
    
    /**
     * Enqueue assets for this field
     *
     * @return void
     */
    public function enqueue_assets() {
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
        
        wp_enqueue_style(
            'hvnly-document-field',
            HVNLYNAB_ASSETS_URL . '/admin/css/hvnly-document-field.css',
            array(),
            defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '1.0.0'
        );
        
        wp_enqueue_script(
            'hvnly-document-field',
            HVNLYNAB_ASSETS_URL . '/admin/js/hvnly-document-field.js',
            array('jquery', 'jquery-ui-sortable'),
            defined('HVNLYNAB_VERSION') ? HVNLYNAB_VERSION : '1.0.0',
            true
        );
    }
}