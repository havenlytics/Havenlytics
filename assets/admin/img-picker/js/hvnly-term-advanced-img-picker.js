/**
 * Advanced Term Image Picker JavaScript - Havenlytics Design System
 * Complete solution for taxonomy image upload functionality
 * 
 * @package Havenlytics
 * @since 1.0.0
 */

(function($) {
    'use strict';

    class HvnlyTermImagePicker {
        constructor() {
            this.uploader = null;
            this.init();
        }

        init() {
            this.bindEvents();
            this.initializeExistingStates();
        }

        bindEvents() {
            // Handle image upload button click
            $(document).on('click', '.hvnly-term-img-upload-trigger', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleUploadClick(e.target);
            });

            // Handle image clear button click
            $(document).on('click', '.hvnly-term-img-clear-trigger', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleClearClick(e.target);
            });

            // Handle action button clicks in overlay
            $(document).on('click', '.hvnly-term-img-action-btn', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleActionClick(e.target);
            });

            // Handle preview area click for upload when no image
            $(document).on('click', '.hvnly-term-img-preview-area:not(.hvnly-term-has-image)', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $container = $(e.target).closest('.hvnly-term-advanced-img-upload-container');
                $container.find('.hvnly-term-img-upload-trigger').trigger('click');
            });

            // Keyboard navigation support
            $(document).on('keydown', '.hvnly-term-img-action-btn', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(e.target).trigger('click');
                }
            });
        }

        initializeExistingStates() {
            $('.hvnly-term-advanced-img-upload-container').each((index, container) => {
                this.initializeContainer($(container));
            });
        }

        initializeContainer($container) {
            const $hiddenInput = $container.find('.hvnly-term-img-id-input');
            const $clearButton = $container.find('.hvnly-term-img-clear-trigger');
            const $previewArea = $container.find('.hvnly-term-img-preview-area');
            const imageId = $hiddenInput.val();

            if (imageId && imageId !== '') {
                $clearButton.show();
                $previewArea.addClass('hvnly-term-has-image');
                
                if ($previewArea.find('img').length === 0) {
                    this.loadImagePreview(imageId, $previewArea);
                } else {
                    this.enhanceExistingPreview($previewArea);
                }
            } else {
                $clearButton.hide();
                $previewArea.removeClass('hvnly-term-has-image');
            }
        }

        enhanceExistingPreview($previewArea) {
            // Add overlay to existing images that don't have it
            if ($previewArea.find('.hvnly-term-img-overlay').length === 0) {
                $previewArea.find('.hvnly-term-img-selected').append(`
                    <div class="hvnly-term-img-overlay">
                        <div class="hvnly-term-img-actions">
                            <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-edit" data-tooltip="${this.getTranslation('changeImage', 'Change Image')}">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-remove" data-tooltip="${this.getTranslation('removeImage', 'Remove Image')}">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                `);
            }
        }

        loadImagePreview(imageId, $previewArea) {
            // Use WordPress media to fetch attachment details
            const attachment = wp.media.attachment(imageId);
            if (attachment && attachment.get('url')) {
                const attachmentData = attachment.toJSON();
                $previewArea.html(`
                    <div class="hvnly-term-img-selected">
                        <img src="${attachmentData.url}" alt="${attachmentData.alt || this.getTranslation('taxonomyImage', 'Taxonomy Image')}" />
                        <div class="hvnly-term-img-overlay">
                            <div class="hvnly-term-img-actions">
                                <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-edit" data-tooltip="${this.getTranslation('changeImage', 'Change Image')}">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-remove" data-tooltip="${this.getTranslation('removeImage', 'Remove Image')}">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                `);
                $previewArea.addClass('hvnly-term-has-image');
            } else {
                // Fallback: try to get image URL via AJAX or show placeholder
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_attachment_url',
                        attachment_id: imageId
                    },
                    success: (response) => {
                        if (response.success && response.data.url) {
                            $previewArea.html(`
                                <div class="hvnly-term-img-selected">
                                    <img src="${response.data.url}" alt="${this.getTranslation('taxonomyImage', 'Taxonomy Image')}" />
                                    <div class="hvnly-term-img-overlay">
                                        <div class="hvnly-term-img-actions">
                                            <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-edit" data-tooltip="${this.getTranslation('changeImage', 'Change Image')}">
                                                <span class="dashicons dashicons-edit"></span>
                                            </button>
                                            <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-remove" data-tooltip="${this.getTranslation('removeImage', 'Remove Image')}">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `);
                            $previewArea.addClass('hvnly-term-has-image');
                        } else {
                            this.showErrorPreview($previewArea, imageId);
                        }
                    },
                    error: () => {
                        this.showErrorPreview($previewArea, imageId);
                    }
                });
            }
        }

        showErrorPreview($previewArea, imageId) {
            $previewArea.html(`
                <div class="hvnly-term-img-placeholder">
                    <span class="dashicons dashicons-warning"></span>
                    <span>${this.getTranslation('unableToLoad', 'Unable to load image')}</span>
                    <small>ID: ${imageId}</small>
                </div>
            `);
        }

        handleUploadClick(button) {
            const $button = $(button);
            const targetField = $button.data('target');
            
            this.openMediaUploader(targetField, $button);
        }

        handleClearClick(button) {
            const $button = $(button);
            const $container = $button.closest('.hvnly-term-advanced-img-upload-container');
            
            this.clearImageSelection($container);
        }

        handleActionClick(button) {
            const $button = $(button);
            const $container = $button.closest('.hvnly-term-advanced-img-upload-container');
            
            if ($button.hasClass('hvnly-term-img-action-edit')) {
                // Edit/Change image
                const targetField = $container.find('.hvnly-term-img-id-input').attr('id');
                this.openMediaUploader(targetField, $container.find('.hvnly-term-img-upload-trigger'));
            } else if ($button.hasClass('hvnly-term-img-action-remove')) {
                // Remove image
                this.clearImageSelection($container);
            }
        }

        openMediaUploader(targetField, $button) {
            // Create or reuse media uploader
            if (this.uploader) {
                this.uploader.open();
                return;
            }

            this.uploader = wp.media.frames.file_frame = wp.media({
                title: this.getTranslation('selectImage', 'Select Taxonomy Image'),
                button: {
                    text: this.getTranslation('useThisImage', 'Use This Image')
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // Handle selection
            this.uploader.on('select', () => {
                const attachment = this.uploader.state().get('selection').first().toJSON();
                this.handleImageSelection(attachment, targetField, $button);
            });

            // Handle close
            this.uploader.on('close', () => {
                // Clean up uploader instance
                this.uploader = null;
            });

            // Open the uploader
            this.uploader.open();
        }

        handleImageSelection(attachment, targetField, $button) {
            const $container = $button.closest('.hvnly-term-advanced-img-upload-container');
            const $hiddenInput = $(`#${targetField}`);
            
            // Update the hidden input
            $hiddenInput.val(attachment.id);

            // Update preview
            this.updatePreview($container, attachment);

            // Show clear button
            $container.find('.hvnly-term-img-clear-trigger').show();

            // Show success feedback
            this.showSuccessState($container);
        }

        updatePreview($container, attachment) {
            const $previewArea = $container.find('.hvnly-term-img-preview-area');
            
            $previewArea.html(`
                <div class="hvnly-term-img-selected">
                    <img src="${attachment.url}" alt="${attachment.alt || this.getTranslation('taxonomyImage', 'Taxonomy Image')}" />
                    <div class="hvnly-term-img-overlay">
                        <div class="hvnly-term-img-actions">
                            <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-edit" data-tooltip="${this.getTranslation('changeImage', 'Change Image')}">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="hvnly-term-img-action-btn hvnly-term-img-action-remove" data-tooltip="${this.getTranslation('removeImage', 'Remove Image')}">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                </div>
            `);
            
            $previewArea.addClass('hvnly-term-has-image');
        }

        clearImageSelection($container) {
            const $hiddenInput = $container.find('.hvnly-term-img-id-input');
            const $previewArea = $container.find('.hvnly-term-img-preview-area');
            const $clearButton = $container.find('.hvnly-term-img-clear-trigger');

            // Clear hidden field
            $hiddenInput.val('');

            // Reset preview
            $previewArea.html(`
                <div class="hvnly-term-img-placeholder">
                    <span class="dashicons dashicons-format-image"></span>
                    <span>${this.getTranslation('noImageSelected', 'No image selected')}</span>
                </div>
            `);
            
            $previewArea.removeClass('hvnly-term-has-image');

            // Hide clear button
            $clearButton.hide();

            // Show feedback
            this.showTemporaryMessage($container, this.getTranslation('imageRemoved', 'Image removed successfully'), 'success');
        }

        showSuccessState($container) {
            $container.addClass('hvnly-term-img-success');
            
            setTimeout(() => {
                $container.removeClass('hvnly-term-img-success');
            }, 2000);
        }

        showTemporaryMessage($container, message, type = 'info') {
            // Create a temporary message element
            const $message = $(`<div class="notice notice-${type} is-dismissible" style="margin: 10px 0;"><p>${message}</p></div>`);
            $container.prepend($message);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                $message.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        getTranslation(key, fallback) {
            return (window.hvnlyTermAdvancedImgPicker && window.hvnlyTermAdvancedImgPicker.i18n && window.hvnlyTermAdvancedImgPicker.i18n[key]) || fallback;
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        // Wait for WordPress media to be available
        if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
            new HvnlyTermImagePicker();
        } else {
            // Retry initialization if WordPress media isn't ready yet
            const initInterval = setInterval(() => {
                if (typeof wp !== 'undefined' && typeof wp.media !== 'undefined') {
                    clearInterval(initInterval);
                    new HvnlyTermImagePicker();
                }
            }, 100);

            // Timeout after 5 seconds
            setTimeout(() => {
                clearInterval(initInterval);
            }, 5000);
        }
    });

    // Make available globally
    window.HvnlyTermImagePicker = HvnlyTermImagePicker;

})(jQuery);