(function($) {
    'use strict';

    class HvnlyAdvancedIconPicker {
        constructor() {
            this.modal = null;
            this.selectedIcon = null;
            this.iconsCache = null;
            this.currentTab = 'all';
            this.iconsByStyle = {};
            this.init();
        }

        init() {
            this.createModal();
            this.bindEvents();
        }

        createModal() {
            const modalHTML = `
                <div id="hvnly-advanced-icon-picker-modal" class="hvnly-advanced-icon-picker-modal">
                    <div class="hvnly-advanced-icon-picker-content">
                        <div class="hvnly-advanced-icon-picker-sidebar">
                            <ul class="hvnly-library-tabs" id="hvnly-library-tabs">
                                ${this.generateLibraryTabs()}
                            </ul>
                        </div>
                        
                        <div class="hvnly-advanced-icon-picker-main">
                            <div class="hvnly-advanced-icon-picker-header">
                                <h2>${hvnlyAdvancedIconPicker.i18n.selectIcon}</h2>
                                <div class="hvnly-advanced-icon-search-container">
                                    <input type="text" class="hvnly-advanced-icon-search" placeholder="${hvnlyAdvancedIconPicker.i18n.searchIcons}">
                                    <i class="fas fa-search hvnly-search-icon"></i>
                                </div>
                                <button class="hvnly-advanced-icon-picker-close">&times;</button>
                            </div>
                            
                            <div class="hvnly-advanced-icon-picker-body">
                                <div class="hvnly-icons-content">
                                    <div class="hvnly-library-header">
                                        <h3 class="hvnly-library-title" id="hvnly-current-library-title">
                                            <i id="hvnly-tab-icon"></i>
                                            <span id="hvnly-tab-title">All Icons</span>
                                        </h3>
                                        <p class="hvnly-library-description" id="hvnly-tab-description">Browse all Font Awesome icons</p>
                                    </div>
                                    <div class="hvnly-icons-grid-container" id="hvnly-icons-grid-container">
                                        <div class="hvnly-loading-state">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            <p>${hvnlyAdvancedIconPicker.i18n.loading}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="hvnly-advanced-icon-picker-footer">
                                <div class="hvnly-selected-icon-preview" id="hvnly-selected-preview" style="display: none;">
                                    <i id="hvnly-preview-icon"></i>
                                    <span class="hvnly-selected-icon-name" id="hvnly-preview-name"></span>
                                </div>
                                <div class="hvnly-modal-actions">
                                    <button type="button" class="button button-secondary" id="hvnly-modal-cancel">
                                        ${hvnlyAdvancedIconPicker.i18n.cancel}
                                    </button>
                                    <button type="button" class="button button-primary" id="hvnly-modal-select" disabled>
                                        ${hvnlyAdvancedIconPicker.i18n.select}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHTML);
            this.modal = $('#hvnly-advanced-icon-picker-modal');
        }

        generateLibraryTabs() {
            const tabs = [
                { id: 'all', name: 'All Icons', icon: 'fas fa-th-large', count: 0, category: 'main' },
                { id: 'solid', name: 'Solid Icons', icon: 'fas fa-font', count: 0, category: 'styles' },
                { id: 'regular', name: 'Regular Icons', icon: 'far fa-address-book', count: 0, category: 'styles' },
                { id: 'brands', name: 'Brand Icons', icon: 'fab fa-font-awesome', count: 0, category: 'styles' },
                { id: 'popular', name: 'Popular', icon: 'fas fa-fire', count: 50, category: 'categories', new: true },
                { id: 'currency', name: 'Currency', icon: 'fas fa-dollar-sign', count: 25, category: 'categories' },
                { id: 'shopping', name: 'Shopping', icon: 'fas fa-shopping-cart', count: 30, category: 'categories' },
                { id: 'travel', name: 'Travel', icon: 'fas fa-plane', count: 35, category: 'categories' },
                { id: 'business', name: 'Business', icon: 'fas fa-briefcase', count: 40, category: 'categories' },
                { id: 'technology', name: 'Technology', icon: 'fas fa-laptop', count: 45, category: 'categories' }
            ];

            let currentCategory = '';
            let tabsHTML = '';

            tabs.forEach(tab => {
                if (tab.category !== currentCategory) {
                    if (currentCategory !== '') {
                        tabsHTML += '</div>';
                    }
                    tabsHTML += `
                        <div class="hvnly-category-section">
                            <div class="hvnly-category-header">
                                <h4 class="hvnly-category-title">${this.getCategoryTitle(tab.category)}</h4>
                            </div>
                    `;
                    currentCategory = tab.category;
                }

                const newBadge = tab.new ? '<span class="hvnly-library-badge"></span>' : '';
                tabsHTML += `
                    <li class="hvnly-library-tab">
                        <button class="hvnly-library-tab-button ${tab.id === 'all' ? 'hvnly-active' : ''} ${tab.new ? 'hvnly-new' : ''}" 
                                data-tab="${tab.id}" 
                                data-count="${tab.count}">
                            <i class="${tab.icon}"></i>
                            <span>${tab.name}</span>
                            <span class="hvnly-library-icon-count">${tab.count}</span>
                            ${newBadge}
                        </button>
                    </li>
                `;
            });

            if (currentCategory !== '') {
                tabsHTML += '</div>';
            }

            return tabsHTML;
        }

        getCategoryTitle(category) {
            const titles = {
                'main': 'Main',
                'styles': 'Icon Styles',
                'categories': 'Categories'
            };
            return titles[category] || category;
        }

        bindEvents() {
            // Open modal
            $(document).on('click', '.hvnly-icon-picker-trigger', (e) => {
                e.preventDefault();
                this.openModal($(e.target).closest('.hvnly-icon-picker-trigger').data('target'));
            });

            // Clear selection
            $(document).on('click', '.hvnly-icon-clear-trigger', (e) => {
                e.preventDefault();
                this.clearSelection($(e.target).closest('.hvnly-advanced-icon-selector-container'));
            });

            // Modal events
            this.modal.on('click', '.hvnly-advanced-icon-picker-close, #hvnly-modal-cancel', (e) => {
                e.preventDefault();
                this.closeModal();
            });

            this.modal.on('click', '#hvnly-modal-select', (e) => {
                e.preventDefault();
                this.confirmSelection();
            });

            this.modal.on('click', '.hvnly-icon-grid-item', (e) => {
                this.selectIcon($(e.currentTarget));
            });

            // Tab events
            this.modal.on('click', '.hvnly-library-tab-button', (e) => {
                e.preventDefault();
                this.changeTab($(e.currentTarget).data('tab'));
            });

            // Search functionality
            this.modal.on('input', '.hvnly-advanced-icon-search', (e) => {
                const searchTerm = e.target.value.trim();
                this.searchIcons(searchTerm);
            });

            // Click outside to close
            this.modal.on('click', (e) => {
                if (e.target === this.modal[0]) {
                    this.closeModal();
                }
            });

            // Keyboard navigation
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.modal.hasClass('hvnly-active')) {
                    this.closeModal();
                }
            });
        }

        openModal(targetField) {
            this.targetField = targetField;
            this.loadCurrentSelection();
            this.loadIcons();
            this.modal.addClass('hvnly-active');
            $('body').addClass('hvnly-advanced-icon-picker-open');
            
            // Focus search
            setTimeout(() => {
                this.modal.find('.hvnly-advanced-icon-search').focus();
            }, 100);
        }

        closeModal() {
            this.modal.removeClass('hvnly-active');
            $('body').removeClass('hvnly-advanced-icon-picker-open');
            this.selectedIcon = null;
            this.currentTab = 'all';
            this.modal.find('.hvnly-advanced-icon-search').val('');
            this.modal.find('#hvnly-modal-select').prop('disabled', true);
            this.modal.find('#hvnly-selected-preview').hide();
            
            // Reset to all tab
            this.modal.find('.hvnly-library-tab-button').removeClass('hvnly-active');
            this.modal.find('[data-tab="all"]').addClass('hvnly-active');
        }

        loadCurrentSelection() {
            const currentValue = $(`#${this.targetField}`).val();
            if (currentValue) {
                this.selectedIcon = {
                    class: currentValue,
                    name: currentValue.split(' ').pop() || currentValue
                };
                this.updatePreview();
            }
        }

        async loadIcons() {
            this.showLoading();
            
            if (this.iconsCache) {
                this.organizeIconsByStyle();
                this.displayIcons(this.getIconsForTab('all'));
                this.updateTabCounts();
                return;
            }

            try {
                const response = await $.ajax({
                    url: hvnlyAdvancedIconPicker.ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'hvnly_get_icon_library',
                        nonce: hvnlyAdvancedIconPicker.nonce
                    },
                    timeout: 10000
                });

                if (response.success && response.data && Array.isArray(response.data)) {
                    this.iconsCache = response.data;
                    this.organizeIconsByStyle();
                    this.displayIcons(this.getIconsForTab('all'));
                    this.updateTabCounts();
                } else {
                    this.showError('Failed to load icons');
                }
            } catch (error) {
                this.showError('Error loading icons. Please try again.');
            }
        }

        organizeIconsByStyle() {
            // Initialize with empty arrays to prevent undefined errors
            this.iconsByStyle = {
                'all': [],
                'solid': [],
                'regular': [],
                'brands': [],
                'popular': [],
                'currency': [],
                'shopping': [],
                'travel': [],
                'business': [],
                'technology': []
            };

            if (!this.iconsCache || !Array.isArray(this.iconsCache)) {
                return;
            }

            // Populate the categories
            this.iconsByStyle.all = this.iconsCache;
            
            // Style-based categories
            this.iconsByStyle.solid = this.iconsCache.filter(icon => icon.style === 'solid');
            this.iconsByStyle.regular = this.iconsCache.filter(icon => icon.style === 'regular');
            this.iconsByStyle.brands = this.iconsCache.filter(icon => icon.style === 'brands');
            
            // Popular - first 50 icons
            this.iconsByStyle.popular = this.iconsCache.slice(0, 50);
            
            // Category-based filters
            this.iconsByStyle.currency = this.iconsCache.filter(icon => 
                icon.name && (
                    icon.name.toLowerCase().includes('dollar') || 
                    icon.name.toLowerCase().includes('euro') ||
                    icon.name.toLowerCase().includes('pound') ||
                    icon.name.toLowerCase().includes('money') ||
                    icon.name.toLowerCase().includes('currency') ||
                    icon.name.toLowerCase().includes('coin')
                )
            );
            
            this.iconsByStyle.shopping = this.iconsCache.filter(icon => 
                icon.name && (
                    icon.name.toLowerCase().includes('cart') ||
                    icon.name.toLowerCase().includes('shop') ||
                    icon.name.toLowerCase().includes('buy') ||
                    icon.name.toLowerCase().includes('bag') ||
                    icon.name.toLowerCase().includes('price') ||
                    icon.name.toLowerCase().includes('sale')
                )
            );
            
            this.iconsByStyle.travel = this.iconsCache.filter(icon => 
                icon.name && (
                    icon.name.toLowerCase().includes('plane') ||
                    icon.name.toLowerCase().includes('car') ||
                    icon.name.toLowerCase().includes('train') ||
                    icon.name.toLowerCase().includes('bus') ||
                    icon.name.toLowerCase().includes('map') ||
                    icon.name.toLowerCase().includes('location')
                )
            );
            
            this.iconsByStyle.business = this.iconsCache.filter(icon => 
                icon.name && (
                    icon.name.toLowerCase().includes('business') ||
                    icon.name.toLowerCase().includes('office') ||
                    icon.name.toLowerCase().includes('work') ||
                    icon.name.toLowerCase().includes('meeting') ||
                    icon.name.toLowerCase().includes('chart') ||
                    icon.name.toLowerCase().includes('growth')
                )
            );
            
            this.iconsByStyle.technology = this.iconsCache.filter(icon => 
                icon.name && (
                    icon.name.toLowerCase().includes('computer') ||
                    icon.name.toLowerCase().includes('laptop') ||
                    icon.name.toLowerCase().includes('phone') ||
                    icon.name.toLowerCase().includes('tech') ||
                    icon.name.toLowerCase().includes('wifi') ||
                    icon.name.toLowerCase().includes('network')
                )
            );
        }

        updateTabCounts() {
            // Safely update tab counts
            Object.keys(this.iconsByStyle).forEach(tabId => {
                const count = this.iconsByStyle[tabId] ? this.iconsByStyle[tabId].length : 0;
                const countElement = this.modal.find(`[data-tab="${tabId}"] .hvnly-library-icon-count`);
                if (countElement.length) {
                    countElement.text(count);
                }
            });
        }

        getIconsForTab(tabId) {
            return this.iconsByStyle[tabId] || [];
        }

        displayIcons(icons) {
            const container = this.modal.find('#hvnly-icons-grid-container');
            
            if (!icons || !Array.isArray(icons) || icons.length === 0) {
                container.html(`
                    <div class="hvnly-empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>${hvnlyAdvancedIconPicker.i18n.noResults}</p>
                    </div>
                `);
                return;
            }

            const iconsHTML = icons.map(icon => {
                const iconClass = icon.class || '';
                const iconName = icon.name || '';
                const iconStyle = icon.style || 'solid';
                
                return `
                <div class="hvnly-icon-grid-item" 
                     data-icon-class="${this.escapeHtml(iconClass)}" 
                     data-icon-name="${this.escapeHtml(iconName)}"
                     data-style="${iconStyle}">
                    <div class="hvnly-icon-grid-item-icon">
                        <i class="${this.escapeHtml(iconClass)}"></i>
                    </div>
                    <span class="hvnly-icon-grid-name">${this.escapeHtml(iconName)}</span>
                    <div class="hvnly-selection-badge">
                        <i class="fas fa-check"></i>
                    </div>
                    ${iconStyle ? `<span class="hvnly-style-badge">${iconStyle.charAt(0)}</span>` : ''}
                </div>
                `;
            }).join('');

            container.html(`<div class="hvnly-icons-grid">${iconsHTML}</div>`);
            this.highlightSelectedIcon();
        }

        escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) {
                return '';
            }
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        showLoading() {
            this.modal.find('#hvnly-icons-grid-container').html(`
                <div class="hvnly-loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>${hvnlyAdvancedIconPicker.i18n.loading}</p>
                </div>
            `);
        }

        showError(message) {
            this.modal.find('#hvnly-icons-grid-container').html(`
                <div class="hvnly-empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>${message}</p>
                    <button type="button" class="button button-primary" style="margin-top: 10px;" onclick="location.reload()">
                        Reload Page
                    </button>
                </div>
            `);
        }

        changeTab(tabId) {
            this.currentTab = tabId;
            
            // Update active tab
            this.modal.find('.hvnly-library-tab-button').removeClass('hvnly-active');
            this.modal.find(`[data-tab="${tabId}"]`).addClass('hvnly-active');
            
            // Update header
            this.updateTabHeader(tabId);
            
            // Clear search when changing tabs
            this.modal.find('.hvnly-advanced-icon-search').val('');
            
            // Show icons for this tab
            this.displayIcons(this.getIconsForTab(tabId));
        }

        updateTabHeader(tabId) {
            const tabConfig = {
                'all': { title: 'All Icons', description: 'Browse all Font Awesome icons', icon: 'fas fa-th-large' },
                'solid': { title: 'Solid Icons', description: 'Filled solid style icons', icon: 'fas fa-font' },
                'regular': { title: 'Regular Icons', description: 'Outlined regular style icons', icon: 'far fa-address-book' },
                'brands': { title: 'Brand Icons', description: 'Company and brand logos', icon: 'fab fa-font-awesome' },
                'popular': { title: 'Popular Icons', description: 'Most commonly used icons', icon: 'fas fa-fire' },
                'currency': { title: 'Currency Icons', description: 'Money and currency symbols', icon: 'fas fa-dollar-sign' },
                'shopping': { title: 'Shopping Icons', description: 'E-commerce and retail icons', icon: 'fas fa-shopping-cart' },
                'travel': { title: 'Travel Icons', description: 'Transportation and location icons', icon: 'fas fa-plane' },
                'business': { title: 'Business Icons', description: 'Office and corporate icons', icon: 'fas fa-briefcase' },
                'technology': { title: 'Technology Icons', description: 'Devices and tech icons', icon: 'fas fa-laptop' }
            };

            const config = tabConfig[tabId] || tabConfig['all'];
            
            this.modal.find('#hvnly-tab-icon').attr('class', config.icon);
            this.modal.find('#hvnly-tab-title').text(config.title);
            this.modal.find('#hvnly-tab-description').text(config.description);
        }

        selectIcon($iconItem) {
            this.modal.find('.hvnly-icon-grid-item').removeClass('hvnly-selected');
            $iconItem.addClass('hvnly-selected');
            
            this.selectedIcon = {
                class: $iconItem.data('icon-class'),
                name: $iconItem.data('icon-name')
            };
            
            this.updatePreview();
            this.modal.find('#hvnly-modal-select').prop('disabled', false);
        }

        updatePreview() {
            const preview = this.modal.find('#hvnly-selected-preview');
            if (this.selectedIcon) {
                const $previewIcon = preview.find('#hvnly-preview-icon');
                $previewIcon.attr('class', this.selectedIcon.class);
                
                preview.find('#hvnly-preview-name').text(this.selectedIcon.class);
                preview.show();
            } else {
                preview.hide();
            }
        }

        highlightSelectedIcon() {
            if (this.selectedIcon && this.selectedIcon.class) {
                const selectedItem = this.modal.find(`[data-icon-class="${this.escapeHtml(this.selectedIcon.class)}"]`);
                if (selectedItem.length) {
                    selectedItem.addClass('hvnly-selected');
                    this.modal.find('#hvnly-modal-select').prop('disabled', false);
                    this.updatePreview();
                }
            }
        }

        confirmSelection() {
            if (this.selectedIcon) {
                // Update the target field
                const $targetField = $(`#${this.targetField}`);
                $targetField.val(this.selectedIcon.class);
                
                // Update library field
                const $libraryField = $targetField.closest('.hvnly-advanced-icon-selector-container')
                    .find('input[name="hvnly_icon_library"]');
                if ($libraryField.length) {
                    $libraryField.val('font-awesome');
                }
                
                // Update the preview in the form
                this.updateFormPreview(this.targetField, this.selectedIcon);
                
                // Show clear button
                $targetField.closest('.hvnly-advanced-icon-selector-container')
                    .find('.hvnly-icon-clear-trigger').show();
            }
            
            this.closeModal();
        }

        updateFormPreview(fieldId, icon) {
            const container = $(`#${fieldId}`).closest('.hvnly-advanced-icon-selector-container');
            const previewArea = container.find('.hvnly-icon-preview-area');
            
            previewArea.html(`
                <div class="hvnly-icon-selected">
                    <i class="${this.escapeHtml(icon.class)}"></i>
                    <span class="hvnly-icon-name">${this.escapeHtml(icon.class)}</span>
                </div>
            `).addClass('hvnly-has-icon');
        }

        clearSelection($container) {
            const field = $container.find('input[type="hidden"]');
            field.val('');
            
            $container.find('.hvnly-icon-preview-area').html(`
                <div class="hvnly-icon-placeholder">
                    <i class="fas fa-icons"></i>
                    <span>${hvnlyAdvancedIconPicker.i18n.noIconSelected}</span>
                </div>
            `).removeClass('hvnly-has-icon');
            
            $container.find('.hvnly-icon-clear-trigger').hide();
        }

        searchIcons(searchTerm) {
            if (!searchTerm) {
                // If search is empty, show current tab icons
                this.displayIcons(this.getIconsForTab(this.currentTab));
                return;
            }

            // Get icons from current tab or all icons if searching across all
            let iconsToSearch;
            if (this.currentTab === 'all') {
                iconsToSearch = this.iconsCache || [];
            } else {
                iconsToSearch = this.getIconsForTab(this.currentTab);
            }

            // Perform local search
            const filteredIcons = iconsToSearch.filter(icon => {
                if (!icon) return false;
                
                const searchText = (icon.name + ' ' + icon.class + ' ' + (icon.style || '')).toLowerCase();
                return searchText.includes(searchTerm.toLowerCase());
            });

            // Update header to show search results
            if (searchTerm) {
                this.modal.find('#hvnly-tab-title').text(`Search: "${searchTerm}"`);
                this.modal.find('#hvnly-tab-description').text(`Found ${filteredIcons.length} icons`);
                this.modal.find('#hvnly-tab-icon').attr('class', 'fas fa-search');
            } else {
                this.updateTabHeader(this.currentTab);
            }

            this.displayIcons(filteredIcons);
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new HvnlyAdvancedIconPicker();
        $('body').addClass('hvnly-advanced-icon-picker-loaded');
    });

})(jQuery);