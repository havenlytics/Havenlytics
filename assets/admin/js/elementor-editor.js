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
            this.initialized = false;
            this.groupBaseId = $container.data('group-base-id') || '';

            this.init();
        }

        init() {
            if (this.initialized) return;

            this.bindEvents();
            this.updatePreviews();
            this.initialized = true;
        }

        bindEvents() {
            // File upload buttons
            this.$container.on('click', '.hvnly-upload-button', (e) => {
                e.preventDefault();
                this.openFileUploader($(e.currentTarget));
            });

            // Remove preview
            this.$container.on('click', '.hvnly-remove-preview', (e) => {
                e.preventDefault();
                this.removePreview($(e.currentTarget));
            });

            // Handle input changes
            this.$container.on('change', 'input[type="text"]', (e) => {
                const $input = $(e.currentTarget);
                this.updateSinglePreview($input);
            });
        }

        openFileUploader($button) {
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('Media uploader is not available.');
                return;
            }

            const targetSelector = $button.data('target');
            const $input = $(targetSelector);
            
            if (!$input.length) {
                // console.error('Target input not found:', targetSelector);
                return;
            }

            const type = $button.data('type') || 'image';

            const frame = wp.media({
                title: type === 'image' ? 'Select Image' : 'Select File',
                button: { text: 'Use this file' },
                library: { type: type === 'image' ? 'image' : '' },
                multiple: false
            });

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url).trigger('change');
            });

            frame.open();
        }

        updatePreviews() {
            this.$container.find('.hvnly-preview-container').each((index, container) => {
                const $previewContainer = $(container);
                const $input = $previewContainer.siblings('input[type="text"]');
                if ($input.length && $input.val()) {
                    this.showPreview($input, $input.val());
                }
            });
        }

        updateSinglePreview($input) {
            const $previewContainer = $input.siblings('.hvnly-preview-container');
            const val = $input.val();

            $previewContainer.empty();
            if (!val) return;

            this.showPreview($input, val);
        }

        showPreview($input, url) {
            const $previewContainer = $input.siblings('.hvnly-preview-container');
            const inputId = $input.attr('id');
            
            const previewHTML = `
                <div class="hvnly-preview-wrapper">
                    <img src="${url}" alt="" style="max-width:150px; height:auto;" />
                    <button type="button" class="hvnly-remove-preview" data-target="#${inputId}">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>`;

            $previewContainer.html(previewHTML);
        }

        removePreview($button) {
            const targetSelector = $button.data('target');
            const $input = $(targetSelector);
            
            if ($input.length) {
                $input.val('');
            }
            
            $button.closest('.hvnly-preview-wrapper').remove();
        }
    }

    // Initialize all video field containers
    $(document).ready(() => {
        $('.hvnly-video-field-container').each((index, element) => {
            const $container = $(element);
            const containerId = $container.data('field-id') || `video-container-${index}`;
            
            if (!$container.attr('id')) {
                $container.attr('id', containerId);
            }
            
            new HavenlyticsVideoField(containerId, $container);
        });
    });

    // Re-initialize when tabs are switched
    $(document).on('click', '.hvnly__dyamic_metabox_tab__nav a', function() {
        setTimeout(() => {
            $('.hvnly-video-field-container').each((index, element) => {
                const $container = $(element);
                const containerId = $container.data('field-id') || $container.attr('id') || `video-container-${index}`;
                new HavenlyticsVideoField(containerId, $container);
            });
        }, 300);
    });

})(jQuery);