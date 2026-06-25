/**
 * Havenlytics Document Field - Repeater Handler with URL Type Selector
 * @package Havenlytics
 * @version 2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsDocumentField {
        constructor() {
            this.iconModal = null;
            this.strings = {
                confirmRemove: 'Remove this document?',
                confirmRemoveAll: 'Remove all documents? This will clear all data.',
                uploadTitle: 'Select Document',
                uploadButton: 'Use this document',
                newDocument: 'New Document',
                selectIcon: 'Select Icon',
                searchIcons: 'Search icons...',
                default: 'Default',
                showInSidebar: 'Show in Sidebar',
                hideFromSidebar: 'Hide from Sidebar'
            };
            
            this.icons = {
                // Original icons (preserved)
                'file-pdf': 'PDF',
                'file-word': 'Word',
                'file-excel': 'Excel',
                'file-powerpoint': 'PowerPoint',
                'file-image': 'Image',
                'file-video': 'Video',
                'file-audio': 'Audio',
                'file-archive': 'Archive',
                'file-code': 'Code',
                'file-contract': 'Contract',
                'file-invoice': 'Invoice',
                'file-lines': 'Document',
                'file': 'File',
                'folder': 'Folder',
                'folder-open': 'Folder Open',
                'paperclip': 'Attachment',
                'link': 'Link',
                'download': 'Download',
                'print': 'Print',
                'copy': 'Copy',
                'youtube': 'YouTube',
                'vimeo': 'Vimeo',
                'map-marker-alt': 'Map',
                'globe': 'Website',
                'vr-cardboard': 'Virtual Tour',
                'draw-polygon': 'Floor Plan',
                
                // Additional Real Estate Icons
                'home': 'Home',
                'house': 'House',
                'building': 'Building',
                'landmark': 'Landmark',
                'city': 'City',
                'tree': 'Tree/Landscaping',
                'water': 'Waterfront',
                'mountain': 'Mountain View',
                'binoculars': 'View',
                'compass': 'Compass/Direction',
                'location-dot': 'Location',
                'map': 'Map',
                'map-pin': 'Map Pin',
                'route': 'Route/Directions',
                'signs-post': 'Street Sign',
                'ruler': 'Ruler/Measurements',
                'ruler-combined': 'Square Footage',
                'cubes': 'Development',
                'hammer': 'Renovation',
                'wrench': 'Maintenance',
                'paint-roller': 'Paint/Painting',
                'key': 'Key',
                'key-skeleton': 'Property Keys',
                'lock': 'Lock/Security',
                'lock-open': 'Open House',
                'shield': 'Security/Insurance',
                'camera': 'Property Photos',
                'camera-retro': 'Photography',
                'video': 'Video Tour',
                'panorama': 'Panoramic View',
                'images': 'Gallery',
                'chart-line': 'Market Trends',
                'chart-pie': 'Investment Analysis',
                'chart-simple': 'Statistics',
                'percent': 'Percentage/Rate',
                'dollar-sign': 'Price',
                'sack-dollar': 'Investment',
                'hand-holding-dollar': 'Financing',
                'handshake': 'Agreement/Sold',
                'file-signature': 'Contract/Signing',
                'stamp': 'Approved/Stamped',
                'check-double': 'Verified',
                'clipboard-list': 'Checklist',
                'clipboard-check': 'Inspection',
                'calculator': 'Mortgage Calc',
                'scale-balanced': 'Legal/Compliance',
                'gavel': 'Auction/Legal',
                'house-chimney': 'Chimney/Home',
                'house-crack': 'Fixer Upper',
                'house-flood-water': 'Flood Zone',
                'house-tree': 'Tree House',
                'house-laptop': 'Smart Home',
                'solar-panel': 'Solar Energy',
                'fan': 'Ceiling Fan',
                'temperature-high': 'HVAC/Heating',
                'temperature-low': 'AC/Cooling',
                'fire': 'Fireplace',
                'fire-extinguisher': 'Safety',
                'pool': 'Swimming Pool',
                'hot-tub-person': 'Hot Tub/Spa',
                'umbrella-beach': 'Beach Property',
                'tent': 'Camping/Land',
                'mountain-sun': 'Scenic View',
                'wheat-awn': 'Farm/Land',
                'cow': 'Ranch/Livestock',
                'horse': 'Equestrian',
                'dog': 'Pet Friendly',
                'car': 'Garage/Parking',
                'car-garage': 'Garage',
                'bicycle': 'Bike Friendly',
                'bus': 'Public Transport',
                'train': 'Train Access',
                'airport': 'Airport Access',
                'school': 'School District',
                'graduation-cap': 'University',
                'hospital': 'Hospital/Medical',
                'clinic-medical': 'Medical Center',
                'store': 'Retail/Commercial',
                'utensils': 'Restaurant Nearby',
                'shopping-cart': 'Shopping',
                'cart-shopping': 'Grocery',
                'dumbbell': 'Fitness Center',
                'park': 'Park/Recreation',
                'golf-ball-tee': 'Golf Course',
                'futbol': 'Sports Field',
                'basketball': 'Basketball Court',
                'table-tennis-paddle-ball': 'Game Room',
                'gamepad': 'Entertainment',
                'wifi': 'High-Speed Internet',
                'tv': 'Cable/Satellite',
                'thermostat': 'Smart Thermostat',
                'blender': 'Kitchen Appliance',
                'microwave': 'Microwave',
                'oven': 'Oven',
                'refrigerator': 'Refrigerator',
                'washing-machine': 'Laundry',
                'dryer': 'Dryer',
                'bed': 'Bedrooms',
                'bed-front': 'Master Bedroom',
                'bath': 'Bathrooms',
                'shower': 'Shower',
                'toilet': 'Toilet',
                'sofa': 'Living Room',
                'chair': 'Furniture',
                'lamp-desk': 'Lighting',
                'lightbulb': 'Light Fixture',
                'window-maximize': 'Windows',
                'door-open': 'Door/Entry',
                'stairs': 'Stairs',
                'elevator': 'Elevator',
                'wheelchair': 'Accessibility',
                'baby-carriage': 'Family Friendly',
                'people-group': 'Community',
                'users': 'Neighbors',
                'hand': 'For Sale By Owner',
                'hand-peace': 'Peaceful',
                'hand-heart': 'Care Services',
                'star': 'Featured',
                'crown': 'Luxury',
                'gem': 'Premium Property',
                'award': 'Award Winning',
                'medal': 'Top Rated',
                'flag': 'New Listing',
                'tag': 'Price Reduced',
                'sale': 'For Sale',
                'sign-hanging': 'Open House',
                'bell': 'Notifications',
                'clock': 'Age of Home',
                'calendar': 'Year Built',
                'calendar-check': 'Available Date',
                'calendar-days': 'Listing Date',
                'sun': 'Sun Exposure',
                'moon': 'Night View',
                'cloud-sun': 'Weather',
                'seedling': 'Garden/Yard',
                'leaf': 'Green Living',
                'recycle': 'Eco-Friendly',
                'bolt': 'Electric Vehicle Charging',
                'gas-pump': 'Gas/Natural Gas',
                'oil-well': 'Oil/Gas Rights',
                'trash-can': 'Waste Management',
                'dumpster': 'Construction/Dumpster',
                'helmet-safety': 'Safety Gear',
                'hard-hat': 'Construction',
                'paintbrush': 'Painting',
                'brush': 'Landscaping',
                'broom': 'Cleaning',
                'screwdriver-wrench': 'Maintenance',
                'toolbox': 'Tools/Workshop'
            };
            
            this.urlTypes = {
                'custom': {
                    label: 'Custom URL',
                    placeholder: 'https://example.com/document.pdf',
                    hint: 'Enter any valid URL',
                    icon: 'link',
                    showUpload: false
                },
                'pdf': {
                    label: 'PDF Document',
                    placeholder: 'https://example.com/document.pdf',
                    hint: 'Upload a PDF or enter a URL to a PDF file',
                    icon: 'file-pdf',
                    showUpload: true
                },
                'youtube': {
                    label: 'YouTube Video',
                    placeholder: 'https://youtu.be/xxxx or https://youtube.com/watch?v=xxxx',
                    hint: 'Enter YouTube video URL',
                    icon: 'youtube',
                    showUpload: false
                },
                'vimeo': {
                    label: 'Vimeo Video',
                    placeholder: 'https://vimeo.com/xxxx',
                    hint: 'Enter Vimeo video URL',
                    icon: 'vimeo',
                    showUpload: false
                },
                'website': {
                    label: 'Website Link',
                    placeholder: 'https://example.com',
                    hint: 'Enter website URL',
                    icon: 'globe',
                    showUpload: false
                },
                'map': {
                    label: 'Google Maps',
                    placeholder: 'https://maps.google.com/?q=...',
                    hint: 'Enter Google Maps URL',
                    icon: 'map-marker-alt',
                    showUpload: false
                },
                'virtual_tour': {
                    label: 'Virtual Tour',
                    placeholder: 'https://example.com/tour',
                    hint: 'Enter virtual tour URL',
                    icon: 'vr-cardboard',
                    showUpload: false
                },
                'floor_plan': {
                    label: 'Floor Plan',
                    placeholder: 'https://example.com/floor-plan.pdf',
                    hint: 'Enter floor plan image URL or PDF',
                    icon: 'draw-polygon',
                    showUpload: true
                },
                'image': {
                    label: 'Image',
                    placeholder: 'https://example.com/image.jpg',
                    hint: 'Upload an image or enter image URL',
                    icon: 'file-image',
                    showUpload: true
                },
                'video': {
                    label: 'Video File',
                    placeholder: 'https://example.com/video.mp4',
                    hint: 'Upload a video file or enter video URL',
                    icon: 'file-video',
                    showUpload: true
                }
            };
            
            this.init();
        }

        init() {
            // console.log('HvnlyDocumentField initializing...');
            this.initializeFields();
            this.bindEvents();
            this.makeSortable();
            this.initUrlTypeSelectors();
            this.updateAllHiddenFields();
        }

        initializeFields() {
            $('.hvnly-document-field-container').each((index, container) => {
                const $container = $(container);
                const fieldId = $container.data('field-id') || `document-field-${index}`;
                
                if (!$container.attr('id')) {
                    $container.attr('id', fieldId);
                }
                
                // Initialize sidebar toggle state with icons
                this.updateSidebarToggleUI($container);
            });
            
            this.updateButtonStates();
            this.initIconPreviews();
        }

        bindEvents() {
            $(document)
                .off('click.hvnly-document')
                .on('click.hvnly-document', '.hvnly-document-add-item', (e) => this.addDocument(e))
                .on('click.hvnly-document', '.hvnly-document-remove-item', (e) => this.removeDocument(e))
                .on('click.hvnly-document', '.hvnly-document-move-up', (e) => this.moveDocument(e, 'up'))
                .on('click.hvnly-document', '.hvnly-document-move-down', (e) => this.moveDocument(e, 'down'))
                .on('click.hvnly-document', '.hvnly-document-icon-selector', (e) => this.openIconSelector(e))
                .on('click.hvnly-document', '.hvnly-document-upload-btn', (e) => this.uploadDocument(e))
                .on('input.hvnly-document', '.hvnly-document-icon-input', (e) => this.updateIconPreview(e))
                .on('input change.hvnly-document', '.hvnly-document-label-input', (e) => this.updateHiddenFields(e))
                .on('input change.hvnly-document', '.hvnly-document-url-input', (e) => this.updateHiddenFields(e))
                .on('change.hvnly-document', '.hvnly-document-url-type-select', (e) => this.handleUrlTypeChange(e))
                .on('change.hvnly-document', '.hvnly-document-group-sidebar-toggle', (e) => this.updateSidebarStatus(e));
        }

        makeSortable() {
            $('.hvnly-document-repeater-items').each(function() {
                const $container = $(this);
                
                if ($container.data('sortable-initialized')) {
                    return;
                }
                
                $container.sortable({
                    items: '.hvnly-document-repeater-item',
                    handle: '.hvnly-document-drag-handle',
                    cursor: 'move',
                    scrollSensitivity: 40,
                    forcePlaceholderSize: true,
                    placeholder: 'hvnly-document-placeholder',
                    tolerance: 'pointer',
                    opacity: 0.6,
                    start: function(e, ui) {
                        ui.placeholder.height(ui.item.height());
                    },
                    update: (e, ui) => {
                        const $container = $(e.target).closest('.hvnly-document-field-container');
                        if (window.HvnlyDocumentFieldInstance) {
                            window.HvnlyDocumentFieldInstance.updateItemIndexes($container);
                            window.HvnlyDocumentFieldInstance.updateHiddenFields({ currentTarget: $container });
                            window.HvnlyDocumentFieldInstance.updateButtonStates();
                            window.HvnlyDocumentFieldInstance.updateItemTitles($container);
                        }
                    }
                });
                
                $container.data('sortable-initialized', true);
            });
        }

        addDocument(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $container = $button.closest('.hvnly-document-field-container');
            const $itemsContainer = $container.find('.hvnly-document-repeater-items');
            const $firstItem = $itemsContainer.find('.hvnly-document-repeater-item:first');
            
            if (!$firstItem.length) return;
            
            const $newItem = $firstItem.clone();
            const newIndex = $itemsContainer.find('.hvnly-document-repeater-item').length;
            
            $newItem.attr('data-item-index', newIndex);
            $newItem.find('input').val('');
            $newItem.find('.hvnly-document-icon-preview').empty();
            $newItem.find('.hvnly-document-item-title').text(this.strings.newDocument);
            
            // Set default URL type to custom for new items
            $newItem.find('.hvnly-document-url-type-select').val('custom');
            
            $itemsContainer.append($newItem);
            
            this.updateItemIndexes($container);
            this.updateHiddenFields({ currentTarget: $container });
            this.updateButtonStates();
            this.makeSortable();
            this.updateUploadButtonVisibility($newItem.find('.hvnly-document-url-type-select'));
        }

        removeDocument(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $item = $button.closest('.hvnly-document-repeater-item');
            const $container = $item.closest('.hvnly-document-field-container');
            const $itemsContainer = $container.find('.hvnly-document-repeater-items');
            
            if ($itemsContainer.find('.hvnly-document-repeater-item').length <= 1) {
                if (confirm(this.strings.confirmRemoveAll)) {
                    $item.find('input').val('');
                    $item.find('.hvnly-document-icon-preview').empty();
                    $item.find('.hvnly-document-item-title').text(this.strings.newDocument);
                    $item.find('.hvnly-document-url-type-select').val('custom');
                    this.updateHiddenFields({ currentTarget: $container });
                    this.updateUploadButtonVisibility($item.find('.hvnly-document-url-type-select'));
                }
                return;
            }
            
            if (confirm(this.strings.confirmRemove)) {
                $item.remove();
                this.updateItemIndexes($container);
                this.updateHiddenFields({ currentTarget: $container });
                this.updateButtonStates();
            }
        }

        moveDocument(e, direction) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $item = $button.closest('.hvnly-document-repeater-item');
            const $container = $item.closest('.hvnly-document-field-container');
            
            if (direction === 'up') {
                const $prevItem = $item.prev('.hvnly-document-repeater-item');
                if ($prevItem.length) {
                    $item.insertBefore($prevItem);
                }
            } else {
                const $nextItem = $item.next('.hvnly-document-repeater-item');
                if ($nextItem.length) {
                    $item.insertAfter($nextItem);
                }
            }
            
            this.updateItemIndexes($container);
            this.updateHiddenFields({ currentTarget: $container });
            this.updateButtonStates();
            this.updateItemTitles($container);
        }

        updateItemIndexes($container) {
            $container.find('.hvnly-document-repeater-item').each((index, item) => {
                $(item).attr('data-item-index', index);
            });
        }

        updateItemTitles($container) {
            $container.find('.hvnly-document-repeater-item').each((index, item) => {
                const $item = $(item);
                const label = $item.find('.hvnly-document-label-input').val();
                $item.find('.hvnly-document-item-title').text(label || this.strings.newDocument);
            });
        }

        updateButtonStates() {
            $('.hvnly-document-repeater-items').each((idx, container) => {
                const $itemsContainer = $(container);
                const $items = $itemsContainer.find('.hvnly-document-repeater-item');
                
                $items.each((index, item) => {
                    const $item = $(item);
                    const $upButton = $item.find('.hvnly-document-move-up');
                    const $downButton = $item.find('.hvnly-document-move-down');
                    
                    $upButton.prop('disabled', index === 0);
                    $downButton.prop('disabled', index === $items.length - 1);
                });
            });
        }

        updateHiddenFields(e) {
            const $container = $(e.currentTarget).closest('.hvnly-document-field-container');
            const $hiddenField = $container.find('.hvnly-documents-hidden-field');
            const documents = [];
            
            $container.find('.hvnly-document-repeater-item').each((index, item) => {
                const $item = $(item);
                const icon = $item.find('.hvnly-document-icon-input').val();
                const label = $item.find('.hvnly-document-label-input').val();
                const url = $item.find('.hvnly-document-url-input').val();
                const urlType = $item.find('.hvnly-document-url-type-select').val();
                
                if (label && url) {
                    documents.push({ icon, label, url, url_type: urlType });
                }
            });
            
            $hiddenField.val(JSON.stringify(documents));
            this.updateItemTitles($container);
        }

        updateAllHiddenFields() {
            $('.hvnly-document-field-container').each((index, container) => {
                this.updateHiddenFields({ currentTarget: $(container) });
            });
        }

        updateSidebarStatus(e) {
            const $checkbox = $(e.currentTarget);
            const $container = $checkbox.closest('.hvnly-document-field-container');
            
            // Update the toggle UI based on checkbox state
            this.updateSidebarToggleUI($container);
            
            // Trigger hidden fields update to save the sidebar status
            this.updateHiddenFields({ currentTarget: $container });
        }

        updateSidebarToggleUI($container) {
            const $checkbox = $container.find('.hvnly-document-group-sidebar-toggle');
            const $toggleWrapper = $container.find('.hvnly-document-sidebar-group-toggle');
            const $toggleLabel = $toggleWrapper.find('.hvnly-toggle-label');
            
            if ($checkbox.length && $toggleLabel.length) {
                // Clear existing content and add icon + text
                $toggleLabel.empty();
                
                if ($checkbox.is(':checked')) {
                    $toggleLabel.append('<i class="fas fa-eye"></i> ');
                    $toggleLabel.append(document.createTextNode(this.strings.showInSidebar));
                    $toggleWrapper.removeClass('sidebar-hidden').addClass('sidebar-visible');
                    $container.attr('data-sidebar-status', 'visible');
                } else {
                    $toggleLabel.append('<i class="fas fa-eye-slash"></i> ');
                    $toggleLabel.append(document.createTextNode(this.strings.hideFromSidebar));
                    $toggleWrapper.removeClass('sidebar-visible').addClass('sidebar-hidden');
                    $container.attr('data-sidebar-status', 'hidden');
                }
                
                // Update status dot visibility/color
                let $statusDot = $toggleWrapper.find('.status-dot');
                if (!$statusDot.length) {
                    $statusDot = $('<span class="status-dot"></span>');
                    $toggleWrapper.append($statusDot);
                }
                
                if ($checkbox.is(':checked')) {
                    $statusDot.css({
                        'background-color': 'var(--hvnly-brand-success, #00B46A)',
                        'box-shadow': '0 0 0 2px rgba(0, 180, 106, 0.2)'
                    });
                } else {
                    $statusDot.css({
                        'background-color': 'var(--hvnly-brand-error, #FF4D4F)',
                        'box-shadow': '0 0 0 2px rgba(255, 77, 79, 0.2)'
                    });
                }
            }
        }

        initIconPreviews() {
            $('.hvnly-document-icon-input').each((index, input) => {
                const $input = $(input);
                const $preview = $input.siblings('.hvnly-document-icon-preview');
                const iconValue = $input.val();
                
                if (iconValue) {
                    const cleanIcon = iconValue.replace(/^fa-/, '');
                    $preview.html(`<i class="fas fa-${cleanIcon}"></i>`);
                }
            });
        }

        updateIconPreview(e) {
            const $input = $(e.currentTarget);
            const $preview = $input.siblings('.hvnly-document-icon-preview');
            const iconValue = $input.val().trim();
            
            $preview.empty();
            if (iconValue) {
                const cleanIcon = iconValue.replace(/^fa-/, '');
                $preview.html(`<i class="fas fa-${cleanIcon}"></i>`);
            }
            
            this.updateHiddenFields(e);
        }

        initUrlTypeSelectors() {
            $('.hvnly-document-url-type-select').each((index, select) => {
                this.updateUploadButtonVisibility($(select));
            });
        }

        updateUploadButtonVisibility($select) {
            const $row = $select.closest('.hvnly-document-field-row').next('.hvnly-document-field-row');
            const $hint = $row.find('.hvnly-url-type-hint');
            const $urlInput = $row.find('.hvnly-document-url-input');
            const $uploadBtn = $row.find('.hvnly-document-upload-btn');
            const urlType = $select.val();
            
            if (this.urlTypes[urlType]) {
                $urlInput.attr('placeholder', this.urlTypes[urlType].placeholder);
                $hint.text(this.urlTypes[urlType].hint);
                $hint.attr('data-type', urlType);
                
                // Show/hide upload button based on URL type
                if (this.urlTypes[urlType].showUpload) {
                    $uploadBtn.show();
                } else {
                    $uploadBtn.hide();
                }
            }
        }

        handleUrlTypeChange(e) {
            const $select = $(e.currentTarget);
            this.updateUploadButtonVisibility($select);
            this.updateHiddenFields(e);
        }

        openIconSelector(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $container = $button.closest('.hvnly-document-icon-field');
            const $input = $container.find('.hvnly-document-icon-input');
            
            if (!this.iconModal) {
                this.iconModal = this.createIconModal();
                $('body').append(this.iconModal);
                $('body').append('<div class="hvnly-modal-overlay"></div>');
            }
            
            const $modal = this.iconModal;
            const $overlay = $('.hvnly-modal-overlay');
            const self = this;
            
            const $iconsGrid = $modal.find('.hvnly-icon-selector-grid');
            $iconsGrid.empty();
            
            $iconsGrid.append(`
                <div class="hvnly-icon-item" data-icon="">
                    <i class="fas fa-file"></i>
                    <span>${this.strings.default}</span>
                </div>
            `);
            
            Object.keys(this.icons).sort().forEach(icon => {
                $iconsGrid.append(`
                    <div class="hvnly-icon-item" data-icon="${icon}">
                        <i class="fas fa-${icon}"></i>
                        <span>${self.icons[icon]}</span>
                    </div>
                `);
            });
            
            $iconsGrid.find('.hvnly-icon-item').off('click').on('click', function() {
                const icon = $(this).data('icon');
                $input.val(icon).trigger('input');
                self.closeIconModal();
            });
            
            const $searchInput = $modal.find('.hvnly-icon-search');
            $searchInput.off('input').on('input', function() {
                const searchTerm = $(this).val().toLowerCase();
                
                $iconsGrid.find('.hvnly-icon-item').each((idx, item) => {
                    const $item = $(item);
                    const icon = $item.data('icon');
                    const label = self.icons[icon] || '';
                    
                    if (icon.includes(searchTerm) || label.toLowerCase().includes(searchTerm) || searchTerm === '') {
                        $item.show();
                    } else {
                        $item.hide();
                    }
                });
            });
            
            // Show modal and overlay
            $overlay.fadeIn(200);
            $modal.css('display', 'block').hide().fadeIn(200);
            
            // Center the modal
            this.centerModal($modal);
            
            // Clear search
            $searchInput.val('').trigger('input');
        }

        closeIconModal() {
            const $modal = this.iconModal;
            const $overlay = $('.hvnly-modal-overlay');
            
            if ($modal) {
                $modal.fadeOut(200);
            }
            if ($overlay) {
                $overlay.fadeOut(200);
            }
        }

        centerModal($modal) {
            const modalWidth = $modal.outerWidth();
            const modalHeight = $modal.outerHeight();
            const windowWidth = $(window).width();
            const windowHeight = $(window).height();
            
            const left = (windowWidth - modalWidth) / 2;
            const top = (windowHeight - modalHeight) / 2;
            
            $modal.css({
                left: left + 'px',
                top: top + 'px',
                position: 'fixed'
            });
        }

        createIconModal() {
            const $modal = $(`
                <div class="hvnly-icon-selector-modal" style="display:none;">
                    <div class="hvnly-icon-selector-header">
                        <h3>${this.strings.selectIcon}</h3>
                        <button type="button" class="hvnly-icon-selector-close">&times;</button>
                    </div>
                    <div class="hvnly-icon-selector-search">
                        <input type="text" placeholder="${this.strings.searchIcons}" class="hvnly-icon-search" />
                    </div>
                    <div class="hvnly-icon-selector-grid"></div>
                </div>
            `);
            
            $modal.find('.hvnly-icon-selector-close').on('click', () => {
                this.closeIconModal();
            });
            
            // Close on overlay click
            $(document).on('click', '.hvnly-modal-overlay', () => {
                this.closeIconModal();
            });
            
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    this.closeIconModal();
                }
            });
            
            return $modal;
        }

        uploadDocument(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $urlField = $button.closest('.hvnly-document-url-field');
            const $input = $urlField.find('.hvnly-document-url-input');
            const $select = $button.closest('.hvnly-document-field-row').prev('.hvnly-document-field-row').find('.hvnly-document-url-type-select');
            const urlType = $select.val();
            
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                alert('Media uploader is not available.');
                return;
            }
            
            // Set allowed file types based on URL type
            let allowedTypes = [];
            if (urlType === 'pdf') {
                allowedTypes = ['application/pdf'];
            } else if (urlType === 'image') {
                allowedTypes = ['image'];
            } else if (urlType === 'video') {
                allowedTypes = ['video'];
            } else if (urlType === 'floor_plan') {
                allowedTypes = ['application/pdf', 'image'];
            }
            
            const frame = wp.media({
                title: this.strings.uploadTitle,
                button: { text: this.strings.uploadButton },
                library: { 
                    type: allowedTypes
                },
                multiple: false
            });
            
            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url).trigger('change');
            });
            
            frame.open();
        }
    }

    $(document).ready(function() {
        window.HvnlyDocumentFieldInstance = new HavenlyticsDocumentField();
    });

    $(document).on('click', '.hvnly__dyamic_metabox_tab__nav a', function() {
        setTimeout(function() {
            if (window.HvnlyDocumentFieldInstance) {
                window.HvnlyDocumentFieldInstance.makeSortable();
                window.HvnlyDocumentFieldInstance.updateButtonStates();
                window.HvnlyDocumentFieldInstance.initIconPreviews();
                window.HvnlyDocumentFieldInstance.initUrlTypeSelectors();
                window.HvnlyDocumentFieldInstance.updateAllHiddenFields();
                
                // Re-initialize sidebar toggle UI for all containers
                $('.hvnly-document-field-container').each((index, container) => {
                    window.HvnlyDocumentFieldInstance.updateSidebarToggleUI($(container));
                });
            }
        }, 300);
    });

})(jQuery);