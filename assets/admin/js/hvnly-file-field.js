/**
 * Havenlytics File Field Handler
 * @package Havenlytics
 * @version 2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsFileField {
        constructor(fieldId, $container) {
            this.fieldId = fieldId;
            this.$container = $container;
            this.$input = $container.find('input[type="url"]');
            this.$uploadButton = $container.find('.hvnly-upload-button');
            this.fileType = this.$uploadButton.data('type') || 'file';
            this.initialized = false;
            this.mediaFrame = null;
            this.isSelecting = false;

            this.init();
        }

        init() {
            if (this.initialized) return;
            this.bindEvents();
            this.updatePreview();
            this.initialized = true;
        }

        bindEvents() {
            const self = this;
            
            // Upload button
            this.$uploadButton.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openFileUploader();
                return false;
            });

            // Manual input
            this.$input.on('input', function() {
                self.updatePreview();
            });

            // Remove preview
            this.$container.on('click', '.hvnly-remove-preview', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.removeFile();
                return false;
            });
        }

        openFileUploader() {
            const self = this;
            
            // Check if media library is available
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('Media uploader is not available. Please check your WordPress installation.');
                return;
            }

            // If already selecting, prevent multiple frames
            if (this.isSelecting) {
                return;
            }

            // If frame exists and is not closed, just open it
            if (this.mediaFrame && this.mediaFrame.el && this.mediaFrame.el.parentNode) {
                this.mediaFrame.open();
                return;
            }

            this.isSelecting = true;

            // Set allowed file types
            let allowedTypes = null;
            if (this.fileType === 'image') {
                allowedTypes = 'image';
            } else if (this.fileType === 'pdf') {
                allowedTypes = 'application/pdf';
            }

            // Create new media frame
            this.mediaFrame = wp.media({
                title: this.fileType === 'image' ? 'Select or Upload Image' : 'Select or Upload File',
                button: {
                    text: 'Select File'
                },
                multiple: false,
                library: allowedTypes ? { type: allowedTypes } : null
            });

            // Handle file selection
            this.mediaFrame.on('select', function() {
                // Check if frame and state still exist
                if (!self.mediaFrame || !self.mediaFrame.state) {
                    self.isSelecting = false;
                    return;
                }
                
                // Get the selected attachment
                const selection = self.mediaFrame.state().get('selection');
                if (!selection || selection.length === 0) {
                    self.isSelecting = false;
                    return;
                }
                
                const attachment = selection.first().toJSON();
                if (!attachment || !attachment.url) {
                    self.isSelecting = false;
                    return;
                }
                
                const fileUrl = attachment.url;
                
                // Validate file type
                let isValid = true;
                if (self.fileType === 'pdf') {
                    isValid = fileUrl.toLowerCase().endsWith('.pdf') || attachment.mime === 'application/pdf';
                    if (!isValid) {
                        alert('Please select a valid PDF file.');
                        self.isSelecting = false;
                        return;
                    }
                } else if (self.fileType === 'image') {
                    isValid = /\.(jpe?g|png|gif|webp|bmp)$/i.test(fileUrl) || (attachment.mime && attachment.mime.startsWith('image/'));
                    if (!isValid) {
                        alert('Please select a valid image file (JPG, PNG, GIF, WEBP, BMP).');
                        self.isSelecting = false;
                        return;
                    }
                }
                
                // Update the input field
                self.$input.val(fileUrl);
                self.$input.trigger('change');
                
                // Update the preview
                self.updatePreview();
                
                // Close the modal safely
                if (self.mediaFrame && typeof self.mediaFrame.close === 'function') {
                    self.mediaFrame.close();
                }
                
                // Reset frame reference
                setTimeout(function() {
                    if (self.mediaFrame) {
                        self.mediaFrame = null;
                    }
                    self.isSelecting = false;
                }, 100);
            });

            // Handle modal close
            this.mediaFrame.on('close', function() {
                self.isSelecting = false;
                setTimeout(function() {
                    if (self.mediaFrame) {
                        self.mediaFrame = null;
                    }
                }, 100);
            });

            // Handle escape key
            this.mediaFrame.on('escape', function() {
                self.isSelecting = false;
                setTimeout(function() {
                    if (self.mediaFrame) {
                        self.mediaFrame = null;
                    }
                }, 100);
            });

            // Open the modal
            this.mediaFrame.open();
        }

        updatePreview() {
            const $container = this.$container.find('.hvnly-preview-container');
            const val = this.$input.val();
            const inputId = this.$input.attr('id');

            $container.empty();
            if (!val) return;

            let previewHTML = '';

            // Check if it's an image based on file type OR URL extension
            const isImageUrl = /\.(jpe?g|png|gif|webp|bmp)$/i.test(val);
            const isPdfUrl = /\.pdf$/i.test(val);
            
            // For image type OR if the URL points to an image file
            if ((this.fileType === 'image' || isImageUrl) && isImageUrl) {
                previewHTML = `
                    <div class="hvnly-preview-wrapper" style="position: relative; display: inline-block; margin-top: 10px;">
                        <img src="${this.escapeHtml(val)}" alt="Preview" style="max-width: 200px; max-height: 200px; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 4px; background: #f9f9f9;" />
                        <button type="button" class="hvnly-remove-preview button-link" data-target="#${inputId}" style="position: absolute; top: -10px; right: -10px; background: #fff; border: 1px solid #ddd; border-radius: 50%; cursor: pointer; padding: 2px 5px; line-height: 1; z-index: 1;">
                            <span class="dashicons dashicons-no-alt" style="font-size: 16px; color: red;"></span>
                        </button>
                    </div>`;
            }
            // PDF preview
            else if (this.fileType === 'pdf' && isPdfUrl) {
                const filename = this.getFilenameFromUrl(val);
                previewHTML = `
                    <div class="hvnly-preview-wrapper" style="position: relative; display: inline-block; margin-top: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">
                            <span class="dashicons dashicons-media-document" style="font-size: 24px; color: #d63638;"></span>
                            <a href="${this.escapeHtml(val)}" target="_blank" style="text-decoration: none;">${this.escapeHtml(filename)}</a>
                        </div>
                        <button type="button" class="hvnly-remove-preview button-link" data-target="#${inputId}" style="position: absolute; top: -10px; right: -10px; background: #fff; border: 1px solid #ddd; border-radius: 50%; cursor: pointer; padding: 2px 5px; line-height: 1; z-index: 1;">
                            <span class="dashicons dashicons-no-alt" style="font-size: 16px; color: red;"></span>
                        </button>
                    </div>`;
            }
            // Generic file preview (including images when file type is 'file')
            else {
                const filename = this.getFilenameFromUrl(val);
                
                // If it's actually an image but file type is 'file', still show image preview
                if (isImageUrl) {
                    previewHTML = `
                        <div class="hvnly-preview-wrapper" style="position: relative; display: inline-block; margin-top: 10px;">
                            <img src="${this.escapeHtml(val)}" alt="Preview" style="max-width: 200px; max-height: 200px; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 4px; background: #f9f9f9;" />
                            <button type="button" class="hvnly-remove-preview button-link" data-target="#${inputId}" style="position: absolute; top: -10px; right: -10px; background: #fff; border: 1px solid #ddd; border-radius: 50%; cursor: pointer; padding: 2px 5px; line-height: 1; z-index: 1;">
                                <span class="dashicons dashicons-no-alt" style="font-size: 16px; color: red;"></span>
                            </button>
                        </div>`;
                } else {
                    previewHTML = `
                        <div class="hvnly-preview-wrapper" style="position: relative; display: inline-block; margin-top: 10px;">
                            <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">
                                <span class="dashicons dashicons-media-default" style="font-size: 24px;"></span>
                                <a href="${this.escapeHtml(val)}" target="_blank" style="text-decoration: none;">${this.escapeHtml(filename)}</a>
                            </div>
                            <button type="button" class="hvnly-remove-preview button-link" data-target="#${inputId}" style="position: absolute; top: -10px; right: -10px; background: #fff; border: 1px solid #ddd; border-radius: 50%; cursor: pointer; padding: 2px 5px; line-height: 1; z-index: 1;">
                                <span class="dashicons dashicons-no-alt" style="font-size: 16px; color: red;"></span>
                            </button>
                        </div>`;
                }
            }

            if (previewHTML) {
                $container.html(previewHTML);
            }
        }

        removeFile() {
            this.$input.val('');
            this.updatePreview();
            this.$input.trigger('change');
        }

        getFilenameFromUrl(url) {
            try {
                const urlObj = new URL(url);
                const pathname = urlObj.pathname;
                return pathname.split('/').pop() || 'file';
            } catch(e) {
                return url.split('/').pop() || 'file';
            }
        }

        escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    }

    function initFileFields() {
        $('[data-field-type="file"]').each(function(index, element) {
            const $field = $(element);

            // Skip thumbnail inputs nested inside a video group wrapper
            if ($field.closest('.hvnly-video-field-container').length > 0) {
                return;
            }

            const $metaInput = $field.find('.hvnly-meta-input');
            if ($metaInput.length && !$metaInput.data('hvnly-initialized')) {
                const fieldId = $field.data('field-id') || `file-${index}`;
                new HavenlyticsFileField(fieldId, $metaInput);
                $metaInput.data('hvnly-initialized', true);
            }
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initFileFields();
    });

    // Re-initialize when metabox tabs are switched (fields may have been hidden)
    $(document).on('click', '.hvnly__dyamic_metabox_tab__nav a', function() {
        setTimeout(initFileFields, 300);
    });

})(jQuery);