<?php

namespace HvnlyNab\Database\Traits\Taxonomy_Fields;

if ( ! defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Advanced Image Manager Trait for Taxonomy Terms
 *
 * Provides reusable image upload functionality for custom taxonomies
 * Features: Media library integration, image preview, admin columns
 *
 * @package Havenlytics
 * @since 2.0.0
 */
trait Hvnly_Advanced_Img_Manager {
    /**
     * Taxonomy slug for image management
     *
     * @var string
     */
    protected $hvnly_taxonomy_slug;
    /**
     * Image manager configuration
     *
     * @var array Configuration settings for image upload functionality
     */
    private $hvnly_term_img_config = array(
        'meta_key' => '_hvnly_term_advanced_image_data',
        'field_name' => 'hvnly_term_advanced_image_selection',
        'preview_size' => 'thumbnail',
    );

    /**
     * Initialize the advanced image manager for taxonomy
     *
     * @param string $taxonomy_slug The taxonomy slug to initialize image upload for
     * @return void
     */
    public function hvnly_initialize_img_manager( $taxonomy_slug ) {
        $this->hvnly_taxonomy_slug = $taxonomy_slug;

        // Register all hooks for image management
        $this->hvnly_register_img_manager_hooks();

        // Add admin column for images in taxonomy list
        add_filter("manage_edit-{$taxonomy_slug}_columns", array( $this, 'hvnly_add_img_admin_column' ));
        add_filter("manage_{$taxonomy_slug}_custom_column", array( $this, 'hvnly_display_img_admin_column' ), 10, 3);
    }

    /**
     * Register all WordPress hooks for image management
     *
     * @return void
     */
    private function hvnly_register_img_manager_hooks() {
        $slug = $this->hvnly_taxonomy_slug;

        // Form field hooks - Add image fields to taxonomy forms
        add_action("{$slug}_add_form_fields", array( $this, 'hvnly_render_img_selection_field' ));
        add_action("{$slug}_edit_form_fields", array( $this, 'hvnly_render_img_editing_field' ), 10, 2);

        // Data persistence hooks - Save image data when terms are created/updated
        add_action("created_{$slug}", array( $this, 'hvnly_persist_img_selection' ));
        add_action("edited_{$slug}", array( $this, 'hvnly_update_img_selection' ));

        // Asset management hooks - Enqueue required scripts and styles
        add_action('admin_enqueue_scripts', array( $this, 'hvnly_enqueue_img_assets' ));
    }

    /**
     * Render image selection field for new term form
     *
     * @return void
     */
    public function hvnly_render_img_selection_field() {
        wp_nonce_field('hvnly_term_advanced_img_nonce', 'hvnly_term_advanced_img_nonce_field');
        ?>
        <div class="form-field hvnly-term-advanced-img-upload-wrap">
            <label for="<?php echo esc_attr($this->hvnly_term_img_config['field_name']); ?>">
                <?php esc_html_e('Upload Image', 'havenlytics'); ?>
            </label>
            
            <div class="hvnly-term-advanced-img-upload-container">
                <input type="hidden" 
                        id="<?php echo esc_attr($this->hvnly_term_img_config['field_name']); ?>" 
                        name="<?php echo esc_attr($this->hvnly_term_img_config['field_name']); ?>" 
                        value=""
                        class="hvnly-term-img-id-input">

                <div class="hvnly-term-img-selection-interface">
                    <div class="hvnly-term-img-preview-area" id="hvnly-term-img-preview-area">
                        <div class="hvnly-term-img-placeholder">
                            <span class="dashicons dashicons-format-image"></span>
                            <span><?php esc_html_e('No image selected', 'havenlytics'); ?></span>
                        </div>
                    </div>
                    
                    <div class="hvnly-term-img-controls">
                        <button type="button" 
                                class="button button-primary hvnly-term-img-upload-trigger" 
                                data-target="<?php echo esc_attr($this->hvnly_term_img_config['field_name']); ?>">
                            <span class="dashicons dashicons-format-image" style="vertical-align:middle;margin-top:-2px;"></span>
                            <?php esc_html_e('Upload Image', 'havenlytics'); ?>
                        </button>
                        
                        <button type="button" 
                                class="button button-secondary hvnly-term-img-clear-trigger"
                                style="display:none;">
                            <span class="dashicons dashicons-trash" style="vertical-align:middle;margin-top:-2px;"></span>
                            <?php esc_html_e('Remove Image', 'havenlytics'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <p class="description">
                <?php esc_html_e('Upload an image to represent this term.', 'havenlytics'); ?>
            </p>
        </div>
        <?php
        // Output the JS for media uploader
        $this->hvnly_output_img_upload_js();
    }

    /**
     * Render image editing field for existing term form
     *
     * @param object $term The term object being edited
     * @param string $taxonomy The taxonomy slug
     * @return void
     */
    public function hvnly_render_img_editing_field( $term, $taxonomy = '' ) {
        // Ensure we have the term object
        if ( ! is_object($term)) {
            return;
        }

        wp_nonce_field('hvnly_term_advanced_img_nonce', 'hvnly_term_advanced_img_nonce_field');

        $img_data = $this->hvnly_retrieve_img_data($term->term_id);
        $img_id   = $img_data['id'] ?? '';
        $img_url  = $img_id ? wp_get_attachment_image_url($img_id, 'medium') : '';
        ?>
        <tr class="form-field hvnly-term-advanced-img-upload-wrap">
            <th scope="row">
                <label for="<?php echo esc_attr($this->hvnly_term_img_config['field_name']); ?>">
                    <?php esc_html_e('Upload Image', 'havenlytics'); ?>
                </label>
            </th>
            <td>
                <div class="hvnly-term-advanced-img-upload-container">
                    <input type="hidden" 
                            id="<?php echo esc_attr($this->hvnly_term_img_config['field_name']); ?>" 
                            name="<?php echo esc_attr($this->hvnly_term_img_config['field_name']); ?>" 
                            value="<?php echo esc_attr($img_id); ?>"
                            class="hvnly-term-img-id-input">
                    
                    <div class="hvnly-term-img-selection-interface">
                        <div class="hvnly-term-img-preview-area" id="hvnly-term-img-preview-area">
                            <?php if ($img_url) : ?>
                                <div class="hvnly-term-img-selected">
                                    <img src="<?php echo esc_url($img_url); ?>" alt="<?php esc_attr_e('Upload Image', 'havenlytics'); ?>" />
                                    <div class="hvnly-term-img-overlay">
                                        <div class="hvnly-term-img-actions">
                                            <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-edit" data-tooltip="<?php esc_attr_e('Change Image', 'havenlytics'); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                            <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-remove" data-tooltip="<?php esc_attr_e('Remove Image', 'havenlytics'); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="hvnly-term-img-placeholder">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <span><?php esc_html_e('No image selected', 'havenlytics'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="hvnly-term-img-controls">
                            <button type="button" 
                                    class="button button-primary hvnly-term-img-upload-trigger" 
                                    data-target="<?php echo esc_attr($this->hvnly_term_img_config['field_name']); ?>">
                                <span class="dashicons dashicons-format-image" style="vertical-align:middle;margin-top:-2px;"></span>
                                <?php esc_html_e('Upload Image', 'havenlytics'); ?>
                            </button>
                            
                            <button type="button" 
                                    class="button button-secondary hvnly-term-img-clear-trigger"
                                    <?php echo empty($img_url) ? 'style="display: none;"' : ''; ?>>
                                <span class="dashicons dashicons-trash" style="vertical-align:middle;margin-top:-2px;"></span>
                                <?php esc_html_e('Remove Image', 'havenlytics'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <p class="description">
                    <?php esc_html_e('Upload an image to represent this term.', 'havenlytics'); ?>
                </p>
            </td>
        </tr>
        <?php
        // Output the JS for media uploader
        $this->hvnly_output_img_upload_js();
    }

    /**
     * Save image selection when creating new term
     *
     * @param int $term_id The ID of the term being created
     * @return void
     */
    public function hvnly_persist_img_selection( $term_id ) {
        if ( ! $this->hvnly_verify_img_nonce()) {
            return;
        }

        $this->hvnly_process_img_submission($term_id);
    }

    /**
     * Update image selection when editing term
     *
     * @param int $term_id The ID of the term being updated
     * @return void
     */
    public function hvnly_update_img_selection( $term_id ) {
        if ( ! $this->hvnly_verify_img_nonce()) {
            return;
        }

        $this->hvnly_process_img_submission($term_id);
    }

    /**
     * Process image form submission data
     *
     * @param int $term_id The ID of the term
     * @return void
     */
    private function hvnly_process_img_submission( $term_id ) {
        $field_name = $this->hvnly_term_img_config['field_name'];

        //  Use filter_input for POST data
        $img_id_raw = filter_input(INPUT_POST, $field_name, FILTER_UNSAFE_RAW);
        $img_id     = $img_id_raw ? absint($img_id_raw) : 0;

        $img_data = array(
            'id' => $img_id,
            'selected_at' => current_time('mysql'),
            'version' => '1.0.0',
        );

        if ( ! empty($img_id)) {
            update_term_meta($term_id, $this->hvnly_term_img_config['meta_key'], $img_data);
        } else {
            delete_term_meta($term_id, $this->hvnly_term_img_config['meta_key']);
        }
    }

    /**
     * Verify nonce for security
     *
     * @return bool True if nonce is valid, false otherwise
     */
    private function hvnly_verify_img_nonce() {
        $nonce_raw = filter_input(INPUT_POST, 'hvnly_term_advanced_img_nonce_field', FILTER_UNSAFE_RAW);
        $nonce     = $nonce_raw ? sanitize_text_field($nonce_raw) : '';

        return $nonce && wp_verify_nonce($nonce, 'hvnly_term_advanced_img_nonce');
    }

    /**
     * Retrieve image data for a term
     *
     * @param int $term_id The ID of the term
     * @return array Image data array
     */
    private function hvnly_retrieve_img_data( $term_id ) {
        $img_data = get_term_meta($term_id, $this->hvnly_term_img_config['meta_key'], true);
        return is_array($img_data) ? $img_data : array();
    }

    /**
     * Enqueue required assets for image upload functionality
     *
     * @param string $hook The current admin page hook
     * @return void
     */
    public function hvnly_enqueue_img_assets( $hook ) {
        if ( ! $this->hvnly_is_taxonomy_admin_page()) {
            return;
        }

        // Enqueue WordPress media uploader
        wp_enqueue_media();
        wp_enqueue_style('wp-admin');

        // Enqueue our custom CSS
        wp_enqueue_style(
            'hvnly-term-advanced-img-picker',
            $this->hvnly_get_img_asset_url('css/hvnly-term-advanced-img-picker.css'),
            array(),
            HVNLYNAB_VERSION
        );

        // Enqueue our custom JS
        wp_enqueue_script(
            'hvnly-term-advanced-img-picker',
            $this->hvnly_get_img_asset_url('js/hvnly-term-advanced-img-picker.js'),
            array( 'jquery' ),
            HVNLYNAB_VERSION,
            true
        );

        // Localize script with configuration
        wp_localize_script('hvnly-term-advanced-img-picker', 'hvnlyTermAdvancedImgPicker', array(
            'i18n' => array(
                'selectImage' => __('Select Upload Image', 'havenlytics'),
                'useThisImage' => __('Use This Image', 'havenlytics'),
                'removeImage' => __('Remove Image', 'havenlytics'),
                'noImageSelected' => __('No image selected', 'havenlytics'),
                'changeImage' => __('Change Image', 'havenlytics'),
            ),
        ));
    }

    /**
     * Check if current page is taxonomy admin page
     *
     * @return bool True if current page is taxonomy admin page
     */
    private function hvnly_is_taxonomy_admin_page() {
        $screen = get_current_screen();
        return $screen && $screen->taxonomy === $this->hvnly_taxonomy_slug;
    }

    /**
     * Get asset URL with fallback
     *
     * @param string $path The asset path
     * @return string Full URL to the asset
     */
    private function hvnly_get_img_asset_url( $path ) {
        return HVNLYNAB_ASSETS_URL . '/admin/img-picker/' . ltrim($path, '/');
    }

    /**
     * Add image column to taxonomy admin list
     *
     * @param array $columns Existing columns
     * @return array Modified columns with image column added
     */
    public function hvnly_add_img_admin_column( $columns ) {
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[ $key ] = $value;
            if ($key === 'cb') {
                $new_columns['hvnly_term_img'] = __('Image', 'havenlytics');
            }
        }

        return $new_columns;
    }

    /**
     * Display image in admin column
     *
     * @param string $content Column content
     * @param string $column_name Column name
     * @param int $term_id Term ID
     * @return string Modified column content
     */
    public function hvnly_display_img_admin_column( $content, $column_name, $term_id ) {
        if ($column_name !== 'hvnly_term_img') {
            return $content;
        }

        $img_data = $this->hvnly_retrieve_img_data($term_id);
        $img_id   = $img_data['id'] ?? '';

        if ( ! empty($img_id)) {
            $img_url = wp_get_attachment_image_url($img_id, 'thumbnail');
            return $img_url ? '<img src="' . esc_url($img_url) . '" alt="' . esc_attr__('Upload Image', 'havenlytics') . '" style="max-width:50px;height:auto;border-radius:4px;" />' : '';
        }

        return '<span class="dashicons dashicons-format-image" style="color:#ccc;font-size:32px;"></span>';
    }

    /**
     * Output image upload JavaScript
     *
     * @return void
     */
    private function hvnly_output_img_upload_js() {
        // Only output JS once per page load
        static $js_output = false;

        if ($js_output) {
            return;
        }

        $js_output = true;
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var hvnly_term_img_uploader;

                function hvnly_term_init_img_uploader(button) {
                    // If the uploader object has already been created, reopen the dialog
                    if (hvnly_term_img_uploader) {
                        hvnly_term_img_uploader.open();
                        return;
                    }

                    // Extend the wp.media object
                    hvnly_term_img_uploader = wp.media.frames.file_frame = wp.media({
                        title: '<?php esc_html_e('Select Upload Image', 'havenlytics'); ?>',
                        button: {
                            text: '<?php esc_html_e('Use This Image', 'havenlytics'); ?>'
                        },
                        multiple: false,
                        library: {
                            type: 'image'
                        }
                    });

                    // When a file is selected, grab the URL and set it as the text field's value
                    hvnly_term_img_uploader.on('select', function() {
                        var attachment = hvnly_term_img_uploader.state().get('selection').first().toJSON();
                        var targetField = $(button).data('target');
                        var container = $(button).closest('.hvnly-term-advanced-img-upload-container');

                        // Store the attachment ID in the hidden field
                        $('#' + targetField).val(attachment.id);

                        // Update preview with enhanced UI
                        container.find('.hvnly-term-img-preview-area').html(
                            '<div class="hvnly-term-img-selected">' +
                            '<img src="' + attachment.url + '" />' +
                            '<div class="hvnly-term-img-overlay">' +
                            '<div class="hvnly-term-img-actions">' +
                            '<button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-edit" data-tooltip="<?php esc_attr_e('Change Image', 'havenlytics'); ?>">' +
                            '<span class="dashicons dashicons-edit"></span>' +
                            '</button>' +
                            '<button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-remove" data-tooltip="<?php esc_attr_e('Remove Image', 'havenlytics'); ?>">' +
                            '<span class="dashicons dashicons-trash"></span>' +
                            '</button>' +
                            '</div>' +
                            '</div>' +
                            '</div>'
                        );

                        // Show remove button
                        container.find('.hvnly-term-img-clear-trigger').show();
                        
                        // Add has-image class
                        container.find('.hvnly-term-img-preview-area').addClass('hvnly-term-has-image');
                    });

                    // Open the uploader dialog
                    hvnly_term_img_uploader.open();
                }

                // Handle image upload button click
                $('body').on('click', '.hvnly-term-img-upload-trigger', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    hvnly_term_init_img_uploader(this);
                });

                // Handle image clear button click
                $('body').on('click', '.hvnly-term-img-clear-trigger', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var container = $(this).closest('.hvnly-term-advanced-img-upload-container');
                    var targetField = container.find('.hvnly-term-img-id-input').attr('id');
                    
                    // Clear the hidden field
                    $('#' + targetField).val('');
                    
                    // Reset preview
                    container.find('.hvnly-term-img-preview-area').html(
                        '<div class="hvnly-term-img-placeholder">' +
                        '<span class="dashicons dashicons-format-image"></span>' +
                        '<span><?php esc_html_e('No image selected', 'havenlytics'); ?></span>' +
                        '</div>'
                    );
                    
                    // Remove has-image class
                    container.find('.hvnly-term-img-preview-area').removeClass('hvnly-term-has-image');
                    
                    // Hide remove button
                    $(this).hide();
                });

                // Handle action button clicks in overlay
                $('body').on('click', '.hvnly-term-img-action-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $button = $(this);
                    var $container = $button.closest('.hvnly-term-advanced-img-upload-container');
                    
                    if ($button.hasClass('hvnly-term-img-action-edit')) {
                        // Edit/Change image - trigger upload
                        $container.find('.hvnly-term-img-upload-trigger').trigger('click');
                    } else if ($button.hasClass('hvnly-term-img-action-remove')) {
                        // Remove image - trigger clear
                        $container.find('.hvnly-term-img-clear-trigger').trigger('click');
                    }
                });

                // Handle preview area click for upload when no image
                $('body').on('click', '.hvnly-term-img-preview-area:not(.hvnly-term-has-image)', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $container = $(this).closest('.hvnly-term-advanced-img-upload-container');
                    $container.find('.hvnly-term-img-upload-trigger').trigger('click');
                });

                // Initialize remove button visibility
                $('.hvnly-term-advanced-img-upload-container').each(function() {
                    var $container = $(this);
                    var hasValue = $container.find('.hvnly-term-img-id-input').val() !== '';
                    var $clearBtn = $container.find('.hvnly-term-img-clear-trigger');
                    var $previewArea = $container.find('.hvnly-term-img-preview-area');
                    
                    if (hasValue) {
                        $clearBtn.show();
                        $previewArea.addClass('hvnly-term-has-image');
                    } else {
                        $clearBtn.hide();
                        $previewArea.removeClass('hvnly-term-has-image');
                    }
                });
            });
        </script>
        <?php
    }
}