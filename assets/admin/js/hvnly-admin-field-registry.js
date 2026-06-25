/**
 * Havenlytics Field Registry - With file type restrictions
 * @package Havenlytics
 * @version 2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsFieldRegistry {
        constructor() {
            this.version = '2.0.0';
            this.initialized = false;
            
            $(() => {
                this.init();
            });
        }

        init() {
            if (this.initialized) return;
            
            this.initializeFileUploads();
            this.initializePreviewContainers();
            this.bindEvents();
            
            this.initialized = true;
            
            // console.log('Havenlytics Field Registry initialized');
        }

        /**
         * Initialize file upload functionality
         */
        initializeFileUploads() {
            // Initialize previews for existing inputs
            $('.hvnly-meta-input input[type="text"]').each((index, input) => {
                const $input = $(input);
                if ($input.hasClass('widefat')) {
                    this.updatePreview($input);
                }
            });
        }

        /**
         * Initialize preview containers
         */
        initializePreviewContainers() {
            // Find all preview containers and initialize them
            $('.hvnly-preview-container').each((index, container) => {
                const $container = $(container);
                const $input = $container.siblings('input[type="text"]');
                if ($input.length) {
                    this.updatePreview($input);
                }
            });
        }

        /**
         * Bind all events
         */
        bindEvents() {
            // Upload button click
            $(document).on('click', '.hvnly-upload-button', (e) => {
                e.preventDefault();
                this.handleUploadClick(e.currentTarget);
            });

            // Remove preview button click
            $(document).on('click', '.hvnly-remove-preview', (e) => {
                e.preventDefault();
                this.handleRemovePreview(e.currentTarget);
            });

            // Manual input change
            $(document).on('input', '.hvnly-meta-input input[type="text"]', (e) => {
                this.handleManualInput(e.currentTarget);
            });
        }

        /**
         * Handle upload button click
         */
        handleUploadClick(button) {
            const $button = $(button);
            const targetSelector = $button.data('target');
            const $input = $(targetSelector);
            const type = $button.data('type') || 'file'; // 'image', 'pdf', or 'file'
            
            if (!$input.length || typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                return;
            }

            // Create or get existing frame
            let frame = $input.data('media-frame');
            
            if (!frame) {
                // Set allowed file types based on field type
                let allowedTypes = [];
                let mimeTypes = '';
                let title = 'Select File';
                
                switch (type) {
                    case 'image':
                        // Only allow images for video thumbnails
                        allowedTypes = ['image'];
                        mimeTypes = 'image';
                        title = 'Select Image';
                        break;
                        
                    case 'pdf':
                        // Only allow PDF files
                        allowedTypes = ['application/pdf'];
                        mimeTypes = 'application/pdf';
                        title = 'Select PDF';
                        break;
                        
                    case 'file':
                        // Allow images and PDFs for general file fields
                        allowedTypes = ['image', 'application/pdf'];
                        mimeTypes = 'image,application/pdf';
                        title = 'Select File';
                        break;
                        
                    default:
                        allowedTypes = [];
                        mimeTypes = '';
                        title = 'Select File';
                }
                
                frame = wp.media({
                    title: title,
                    button: { text: 'Use this file' },
                    library: { type: allowedTypes },
                    multiple: false
                });

                // Store frame reference with type
                $input.data('media-frame', frame);
                $input.data('file-type', type);

                // Handle selection
                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    
                    // Validate file type
                    if (!this.validateFileType(attachment.url, type)) {
                        alert(this.getErrorMessage(type));
                        return;
                    }
                    
                    $input.val(attachment.url).trigger('change');
                    this.updatePreview($input);
                    frame.close(); // Close after selection
                });
            }

            frame.open();
        }

        /**
         * Validate file type based on field type
         */
        validateFileType(url, type) {
            const urlLower = url.toLowerCase();
            
            switch (type) {
                case 'image':
                    // Only allow image files for video thumbnails
                    return /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(urlLower);
                    
                case 'pdf':
                    // Only allow PDF files
                    return urlLower.endsWith('.pdf');
                    
                case 'file':
                    // Allow images and PDFs for general file fields
                    return /\.(jpg|jpeg|png|gif|webp|bmp|pdf)$/i.test(urlLower);
                    
                default:
                    return true;
            }
        }

        /**
         * Get error message for file type validation
         */
        getErrorMessage(type) {
            switch (type) {
                case 'image':
                    return 'Please select a valid image file (JPG, PNG, GIF, WebP, BMP)';
                    
                case 'pdf':
                    return 'Please select a PDF file';
                    
                case 'file':
                    return 'Please select an image (JPG, PNG, GIF, WebP, BMP) or PDF file';
                    
                default:
                    return 'Please select a valid file';
            }
        }

        /**
         * Handle remove preview button click
         */
        handleRemovePreview(button) {
            const $button = $(button);
            const target = $button.data('target');
            const $input = target.startsWith('#') ? $(target) : $('#' + target);
            
            if ($input.length) {
                $input.val('');
                this.updatePreview($input);
            }
        }

        /**
         * Handle manual input changes
         */
        handleManualInput(input) {
            const $input = $(input);
            clearTimeout($input.data('inputTimeout'));
            $input.data('inputTimeout', setTimeout(() => {
                this.updatePreview($input);
            }, 500));
        }

        /**
         * Update preview based on input value
         */
        updatePreview($input) {
            const value = $input.val();
            let $previewContainer = $input.siblings('.hvnly-preview-container');
            
            if (!$previewContainer.length) {
                // Create preview container if it doesn't exist
                const $metaInput = $input.closest('.hvnly-meta-input');
                if ($metaInput.length) {
                    $previewContainer = $('<div class="hvnly-preview-container"></div>');
                    $input.after($previewContainer);
                } else {
                    return;
                }
            }

            // Clear existing preview
            $previewContainer.empty();

            if (!value) return;

            // Get file type from button
            const $button = $input.siblings('.hvnly-upload-button');
            const type = $button.length ? $button.data('type') || 'file' : 'file';
            
            // Check if file type is valid for this field
            if (!this.validateFileType(value, type)) {
                const errorHTML = `
                    <div class="hvnly-preview-wrapper" style="color: #dc3232; font-style: italic;">
                        Invalid file type for this field
                    </div>
                `;
                $previewContainer.html(errorHTML);
                return;
            }

            // Check if it's an image
            if (this.isImageUrl(value)) {
                this.createImagePreview($previewContainer, value, $input.attr('id'));
            } else if (value.toLowerCase().endsWith('.pdf')) {
                this.createPdfPreview($previewContainer, value, $input.attr('id'));
            } else {
                this.createFilePreview($previewContainer, value, $input.attr('id'));
            }
        }

        /**
         * Check if URL is an image
         */
        isImageUrl(url) {
            return /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(url);
        }

        /**
         * Create image preview
         */
        createImagePreview($container, url, inputId) {
            const previewHTML = `
                <div class="hvnly-preview-wrapper">
                    <img src="${url}" alt="" style="max-width: 150px; height: auto;" />
                    <button type="button" class="hvnly-remove-preview" data-target="${inputId}">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;
            $container.html(previewHTML);
        }

        /**
         * Create PDF preview
         */
        createPdfPreview($container, url, inputId) {
            const filename = url.split('/').pop();
            const previewHTML = `
                <div class="hvnly-preview-wrapper">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="color: #ff6b6b;">
                            <span class="dashicons dashicons-media-document" style="font-size: 32px;"></span>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; color: #333;">${filename}</div>
                            <div style="font-size: 12px; color: #666;">PDF Document</div>
                        </div>
                    </div>
                    <button type="button" class="hvnly-remove-preview" data-target="${inputId}" style="margin-top: 8px;">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;
            $container.html(previewHTML);
        }

        /**
         * Create file preview
         */
        createFilePreview($container, url, inputId) {
            const filename = url.split('/').pop();
            const previewHTML = `
                <div class="hvnly-preview-wrapper">
                    <a href="${url}" target="_blank">${filename}</a>
                    <button type="button" class="hvnly-remove-preview" data-target="${inputId}">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;
            $container.html(previewHTML);
        }

        /**
         * Public method to reinitialize
         */
        reinitialize() {
            this.initialized = false;
            this.init();
        }
    }

    

    // Initialize global instance
    $(() => {
        window.HavenlyticsFieldRegistry = window.HavenlyticsFieldRegistry || new HavenlyticsFieldRegistry();
    });

})(jQuery);