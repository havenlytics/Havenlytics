/**
 * Havenlytics Video Field Handler 
 * @package Havenlytics
 * @version 2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsVideoField {
        constructor(containerId, $container) {
            this.containerId = containerId;
            this.$container = $container;
            this.$inputs = {
                title:     $container.find('input[name*="_title"]'),
                // The URL input is rendered with type="url", so we must NOT
                // filter on data-field-type="text" — that selector finds nothing.
                url:       $container.find('input[type="url"][name*="_url"], input[data-field-type="url"][name*="_url"]'),
                thumbnail: $container.find('input[type="text"][name*="_thumbnail"]')
            };
            this.initialized = false;
            this.mediaFrame = null;
            this.isSelecting = false;

            this.init();
        }

        init() {
            if (this.initialized) return;
            
            this.bindEvents();
            this.updateThumbnailPreview();
            this.initialized = true;
        }

        bindEvents() {
            const self = this;
            
            // Upload button for thumbnail
            this.$container.find('.hvnly-upload-button').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openFileUploader($(this));
                return false;
            });

            // Remove preview button
            this.$container.on('click', '.hvnly-remove-preview', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.removeThumbnail();
                return false;
            });

            // URL input change - update YouTube thumbnail preview
            if (this.$inputs.url.length) {
                this.$inputs.url.on('input change', function() {
                    self.updateThumbnailPreview();
                });
            }
        }

        openFileUploader($button) {
            const self = this;
            const targetSelector = $button.data('target');
            const $targetInput = $(targetSelector);
            
            if (!$targetInput.length) {
                return;
            }

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

            // Create new media frame
            this.mediaFrame = wp.media({
                title: 'Select or Upload Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
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
                
                // Validate image type
                const isValid = /\.(jpe?g|png|gif|webp|bmp)$/i.test(fileUrl) || (attachment.mime && attachment.mime.startsWith('image/'));
                if (!isValid) {
                    alert('Please select a valid image file (JPG, PNG, GIF, WEBP, BMP).');
                    self.isSelecting = false;
                    return;
                }
                
                // Update the input field
                $targetInput.val(fileUrl);
                $targetInput.trigger('change');
                
                // Update the preview
                self.updateThumbnailPreview();
                
                // Close the modal
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

        updateThumbnailPreview() {
            const $previewContainer = this.$container.find('.hvnly-preview-container');
            const $thumbnailInput = this.$inputs.thumbnail;
            
            if (!$thumbnailInput.length || !$previewContainer.length) return;
            
            const val = $thumbnailInput.val();
            const inputId = $thumbnailInput.attr('id');
            
            $previewContainer.empty();
            if (!val) return;
            
            let previewHTML = '';
            
            // Check if it's an image URL
            const isImageUrl = /\.(jpe?g|png|gif|webp|bmp)$/i.test(val);
            
            // Also check if it's a YouTube URL for thumbnail extraction
            const isYoutubeUrl = val.includes('youtube.com/watch') || val.includes('youtu.be/');
            
            if (isYoutubeUrl) {
                // Extract YouTube video ID
                let videoId = '';
                const patterns = [
                    /(?:youtube\.com\/watch\?v=)([^&]+)/,
                    /(?:youtu\.be\/)([^?]+)/,
                    /(?:youtube\.com\/embed\/)([^?]+)/
                ];
                
                for (const pattern of patterns) {
                    const match = val.match(pattern);
                    if (match) {
                        videoId = match[1];
                        break;
                    }
                }
                
                if (videoId) {
                    const thumbnailUrl = `https://img.youtube.com/vi/${videoId}/mqdefault.jpg`;
                    previewHTML = `
                        <div class="hvnly-preview-wrapper" style="position: relative; display: inline-block; margin-top: 10px;">
                            <img src="${this.escapeHtml(thumbnailUrl)}" alt="YouTube Thumbnail" style="max-width: 200px; max-height: 200px; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 4px; background: #f9f9f9;" />
                            <button type="button" class="hvnly-remove-preview button-link" data-target="#${inputId}" style="position: absolute; top: -10px; right: -10px; background: #fff; border: 1px solid #ddd; border-radius: 50%; cursor: pointer; padding: 2px 5px; line-height: 1; z-index: 1;">
                                <span class="dashicons dashicons-no-alt" style="font-size: 16px; color: red;"></span>
                            </button>
                        </div>`;
                } else {
                    previewHTML = `
                        <div class="hvnly-preview-wrapper" style="position: relative; display: inline-block; margin-top: 10px;">
                            <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">
                                <span class="dashicons dashicons-format-video" style="font-size: 24px; color: #d63638;"></span>
                                <span>YouTube Video URL</span>
                            </div>
                            <button type="button" class="hvnly-remove-preview button-link" data-target="#${inputId}" style="position: absolute; top: -10px; right: -10px; background: #fff; border: 1px solid #ddd; border-radius: 50%; cursor: pointer; padding: 2px 5px; line-height: 1; z-index: 1;">
                                <span class="dashicons dashicons-no-alt" style="font-size: 16px; color: red;"></span>
                            </button>
                        </div>`;
                }
            } else if (isImageUrl) {
                previewHTML = `
                    <div class="hvnly-preview-wrapper" style="position: relative; display: inline-block; margin-top: 10px;">
                        <img src="${this.escapeHtml(val)}" alt="Preview" style="max-width: 200px; max-height: 200px; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 4px; background: #f9f9f9;" />
                        <button type="button" class="hvnly-remove-preview button-link" data-target="#${inputId}" style="position: absolute; top: -10px; right: -10px; background: #fff; border: 1px solid #ddd; border-radius: 50%; cursor: pointer; padding: 2px 5px; line-height: 1; z-index: 1;">
                            <span class="dashicons dashicons-no-alt" style="font-size: 16px; color: red;"></span>
                        </button>
                    </div>`;
            } else if (val) {
                const filename = this.getFilenameFromUrl(val);
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
            
            if (previewHTML) {
                $previewContainer.html(previewHTML);
            }
        }

        removeThumbnail() {
            if (this.$inputs.thumbnail.length) {
                this.$inputs.thumbnail.val('');
                this.updateThumbnailPreview();
                this.$inputs.thumbnail.trigger('change');
            }
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

    // Initialize all video field containers
    $(document).ready(function() {
        $('.hvnly-video-field-container').each(function(index, element) {
            const $container = $(element);
            const containerId = $container.data('field-id') || `video-container-${index}`;
            
            if (!$container.attr('id')) {
                $container.attr('id', containerId);
            }
            
            // Store instance on the element
            if (!$container.data('hvnlyVideoField')) {
                $container.data('hvnlyVideoField', new HavenlyticsVideoField(containerId, $container));
            }
        });
    });

    // Re-initialize when tabs are switched
    $(document).on('click', '.hvnly__dyamic_metabox_tab__nav a', function() {
        setTimeout(function() {
            $('.hvnly-video-field-container').each(function(index, element) {
                const $container = $(element);
                if (!$container.data('hvnlyVideoField')) {
                    const containerId = $container.data('field-id') || $container.attr('id') || `video-container-${index}`;
                    $container.data('hvnlyVideoField', new HavenlyticsVideoField(containerId, $container));
                }
            });
        }, 300);
    });

})(jQuery);