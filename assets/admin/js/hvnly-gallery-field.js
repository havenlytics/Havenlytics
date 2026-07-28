/**
 * Havenlytics Gallery Field Handler 
 * @package Havenlytics
 * @version 2.0.0
 */

(function($) {
    'use strict';

    const i18n = (window.HvnlyGalleryField && window.HvnlyGalleryField.i18n) || {};
    const t = (key, fallback) => (i18n[key] && String(i18n[key])) || fallback;

    class HavenlyticsGalleryField {
        constructor(galleryId, $container) {
            this.galleryId = galleryId;
            this.$container = $container;
            
            // Get unique input names from container data
            this.titleInputName = $container.data('title-name') || 'hvnly_gallery_title_' + galleryId;
            this.captionInputName = $container.data('caption-name') || 'hvnly_gallery_caption_' + galleryId;
            this.idsInputName = $container.data('ids-name') || 'hvnly_gallery_ids_' + galleryId;
            
            this.$imagesList = $(`#hvnly-gallery-list-${galleryId}`);
            this.$hiddenInput = $(`#hvnly_gallery_${galleryId}`);
            this.$addButton = $container.find('.hvnly-add-gallery');
            
            this.initialized = false;
            this.init();
        }

        init() {
            if (this.initialized) return;

            try {
                this.makeSortable();
                this.bindEvents();
                this.checkRequiredAndShowError();
                this.updateImageCount();
                this.initialized = true;
            } catch (error) {
                // silent
            }
        }

        /**
         * Check if gallery has images and show/hide error border
         */
        checkRequiredAndShowError() {
            const $container = this.$container;
            const $fieldWrapper = $container.closest('.hvnly__dyamic_metabox_tab__field');
            
            if (!$fieldWrapper.length) return;
            
            // Check if this gallery is required
            const isRequired = $fieldWrapper.find('.required').length > 0 || 
                               $fieldWrapper.attr('data-is-required') === 'true' ||
                               $fieldWrapper.data('is-required') === true;
            
            if (isRequired) {
                const $imagesList = this.$imagesList;
                const hasImages = $imagesList.find('.hvnly-gallery-item').length > 0;
                
                if (!hasImages) {
                    $fieldWrapper.addClass('hvnly-field-error');
                } else {
                    $fieldWrapper.removeClass('hvnly-field-error');
                }
            }
        }

        makeSortable() {
            if ($.ui && $.ui.sortable && this.$imagesList.length) {
                this.$imagesList.sortable({
                    items: 'li.hvnly-gallery-item',
                    cursor: 'move',
                    scrollSensitivity: 40,
                    forcePlaceholderSize: true,
                    placeholder: 'hvnly-gallery-placeholder',
                    update: () => {
                        this.updateGalleryOrder();
                        this.checkRequiredAndShowError();
                    }
                });
            }
        }

        bindEvents() {
            // Direct event binding on the button
            this.$addButton.off('click').on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // console.log('Add button clicked for gallery:', this.galleryId);
                this.openMediaGallery();
            });

            // Use event delegation for dynamically added items
            this.$container.on('click', '.hvnly-gallery-remove', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $item = $(e.currentTarget).closest('li.hvnly-gallery-item');

                if ($item.data('gallery-id') === this.galleryId) {
                    $item.remove();

                    // updateGalleryOrder handles count + hidden-input update
                    this.updateGalleryOrder();
                    this.checkRequiredAndShowError();
                    this.$hiddenInput.trigger('change');
                }
            });

            this.$container.on('click', '.hvnly-gallery-edit', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $item = $(e.currentTarget).closest('li.hvnly-gallery-item');
                
                if ($item.data('gallery-id') === this.galleryId) {
                    this.editImage($item);
                }
            });

            this.$container.on('click', '.hvnly-clear-gallery', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.clearGallery();
            });
        }

        openMediaGallery() {
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert(t('mediaUnavailable', 'Media library is not available.'));
                return;
            }

            const frame = wp.media({
                title: t('manageTitle', 'Manage Gallery Images'),
                button: { text: t('updateGallery', 'Update Gallery') },
                multiple: true,
                library: { type: 'image' }
            });

            // Pre-select images already in the gallery so the user sees the current state.
            frame.on('open', () => {
                const selection = frame.state().get('selection');
                this.$imagesList.find('li.hvnly-gallery-item').each(function() {
                    const id = parseInt($(this).data('id'), 10);
                    if (id) {
                        const attachment = wp.media.attachment(id);
                        attachment.fetch();
                        selection.add(attachment);
                    }
                });
            });

            // On confirm: sync the gallery to exactly what is selected
            // (supports both adding new images and removing deselected ones).
            frame.on('select', () => {
                const selection = frame.state().get('selection');
                const selectedIds = new Set();

                selection.each((attachment) => {
                    selectedIds.add(attachment.id);
                });

                // Remove items that were deselected in the media picker.
                this.$imagesList.find('li.hvnly-gallery-item').each((i, item) => {
                    const $item = $(item);
                    if (!selectedIds.has(parseInt($item.data('id'), 10))) {
                        $item.remove();
                    }
                });

                // Append newly selected items (not already in the list).
                selection.each((attachment) => {
                    const attachmentData = attachment.toJSON();
                    if (
                        this.isImageAttachment(attachmentData) &&
                        !this.$imagesList.find(`li[data-id="${attachmentData.id}"]`).length
                    ) {
                        this.addImageToGallery(attachmentData);
                    }
                });

                this.updateGalleryOrder();
                this.checkRequiredAndShowError();
            });

            frame.open();
        }

        addImageToGallery(attachment) {
            const thumbnailUrl = attachment.sizes?.thumbnail?.url || attachment.url;
            
            // CRITICAL FIX: Use this.galleryId (which is group_base_id), not this.galleryId + '_images'
            const galleryIdForFields = this.galleryId;  // This should be the group_base_id
            
            const $item = $(`
                <li class="hvnly-gallery-item" 
                    data-id="${attachment.id}" 
                    data-gallery-id="${this.galleryId}">
                    
                    <img src="${thumbnailUrl}" alt="${attachment.title || ''}" />
                    
                    <div class="hvnly-gallery-item-actions">
                        <a href="#" class="hvnly-gallery-edit" title="${t('editRemoveImage', 'Edit/Remove Image')}">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                        <a href="#" class="hvnly-gallery-remove" title="${t('editRemoveImage', 'Edit/Remove Image')}">
                            <span class="dashicons dashicons-no"></span>
                        </a>
                    </div>
                    
                    <input type="hidden" name="hvnly_gallery_title_${galleryIdForFields}[]" value="${attachment.title || ''}" />
                    <input type="hidden" name="hvnly_gallery_caption_${galleryIdForFields}[]" value="${attachment.caption || ''}" />
                    <input type="hidden" name="hvnly_gallery_ids_${galleryIdForFields}[]" value="${attachment.id}" />
                </li>
            `);

            this.$imagesList.append($item);
        }

        isImageAttachment(attachment) {
            if (attachment.mime && attachment.mime.startsWith('image/')) {
                return true;
            }
            if (attachment.filename) {
                return /\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i.test(attachment.filename);
            }
            if (attachment.url) {
                return /\.(jpg|jpeg|png|gif|webp|bmp|svg)$/i.test(attachment.url);
            }
            return false;
        }

        editImage($item) {
            const attachmentId = $item.data('id');

            const frame = wp.media({
                title: t('editImage', 'Edit Image'),
                button: { text: t('updateImage', 'Update Image') },
                multiple: false,
                library: { 
                    type: 'image',
                    post__in: [attachmentId] 
                }
            });

            frame.on('open', () => {
                const selection = frame.state().get('selection');
                const attachment = wp.media.attachment(attachmentId);
                attachment.fetch();
                selection.add(attachment);
            });

            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                
                if (this.isImageAttachment(attachment)) {
                    $item.find('img').attr('src', attachment.sizes?.thumbnail?.url || attachment.url);
                    $item.find(`input[name="${this.titleInputName}[]"]`).val(attachment.title || '');
                    $item.find(`input[name="${this.captionInputName}[]"]`).val(attachment.caption || '');
                }
            });

            frame.open();
        }

        clearGallery() {
            if (confirm(t('confirmClearAll', 'Are you sure you want to remove all images from the gallery?'))) {
                this.$imagesList.empty();
                this.$hiddenInput.val('');

                $(`input[name="${this.titleInputName}[]"]`).remove();
                $(`input[name="${this.captionInputName}[]"]`).remove();
                $(`input[name="${this.idsInputName}[]"]`).remove();

                this.updateGalleryOrder();
                this.checkRequiredAndShowError();
                this.$hiddenInput.trigger('change');
            }
        }

        updateGalleryOrder() {
            const ids = [];
            this.$imagesList.find('li.hvnly-gallery-item').each(function() {
                ids.push($(this).data('id'));
            });

            const idsString = ids.join(',');
            this.$hiddenInput.val(idsString);
            this.$hiddenInput.trigger('change');
            this.updateImageCount();
        }

        /**
         * Update the image count shown in the status badge next to the buttons.
         */
        updateImageCount() {
            const count = this.$imagesList.find('li.hvnly-gallery-item').length;
            $(`#hvnly-gallery-status-${this.galleryId} .hvnly-gallery-status-count`).text(count);
        }
    }

    // Initialize all galleries when DOM is ready
    $(document).ready(function() {
        // console.log('Initializing gallery fields...');
        
        $('.hvnly-gallery-container').each(function(index, element) {
            try {
                const $container = $(element);
                const galleryId = $container.data('gallery-id') || $container.data('field-id');
                
                if (galleryId) {
                    if (!$container.data('gallery-initialized')) {
                        new HavenlyticsGalleryField(galleryId, $container);
                        $container.data('gallery-initialized', true);
                        // console.log('Gallery initialized:', galleryId);
                    }
                }
            } catch (error) {
                // console.error('Error initializing gallery:', error);
            }
        });
    });

    // Re-initialize when tabs are switched
    $(document).on('click', '.hvnly__dyamic_metabox_tab__nav a', function() {
        setTimeout(function() {
            $('.hvnly-gallery-container').each(function(index, element) {
                const $container = $(element);
                const galleryId = $container.data('gallery-id');
                
                if (galleryId && !$container.data('gallery-initialized')) {
                    try {
                        new HavenlyticsGalleryField(galleryId, $container);
                        $container.data('gallery-initialized', true);
                        // console.log('Gallery initialized after tab switch:', galleryId);
                    } catch (error) {
                        // console.error('Error initializing gallery after tab switch:', error);
                    }
                } else if (galleryId && $container.data('gallery-initialized')) {
                    // Re-check required status for existing galleries
                    const galleryField = $container.data('gallery-instance');
                    if (galleryField && galleryField.checkRequiredAndShowError) {
                        galleryField.checkRequiredAndShowError();
                    } else {
                        // Create a temporary instance to check
                        const tempGallery = new HavenlyticsGalleryField(galleryId, $container);
                        tempGallery.checkRequiredAndShowError();
                    }
                }
            });
        }, 300);
    });

})(jQuery);