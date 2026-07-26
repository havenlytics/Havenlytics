/**
 * Havenlytics AJAX Filters Module
 * 
 * @package     Havenlytics
 * @version     2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsFilters {
        constructor(main) {
            this.main = main;
        }
        
        init() {
            this.utils = this.main.modules.utils;
            this.bindFilterEvents();
            this.initMultiSelectDropdowns();
        }
        
        bindFilterEvents() {
            const self = this;
            
            // Handle property type link clicks
            $(document).on('click', '.hvnly-property-type-link', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $link = $(this);
                const termSlug = $link.data('term-slug');
                const termId = $link.data('term-id');
                const taxonomy = $link.data('taxonomy') || 'hvnly_prop_types';
                
                // Find the corresponding checkbox in sidebar
                let $checkbox = null;
                
                // Try to find by slug first
                $checkbox = $(`#hvnly-filter-sidebar input[name="${taxonomy}[]"][value="${termSlug}"]`);
                
                // If not found, try to find by data-term-id
                if (!$checkbox.length && termId) {
                    $checkbox = $(`#hvnly-filter-sidebar input[name="${taxonomy}[]"][data-term-id="${termId}"]`);
                }
                
                if ($checkbox.length) {
                    // Check the checkbox if not already checked
                    if (!$checkbox.is(':checked')) {
                        $checkbox.prop('checked', true);
                        $checkbox.closest('.hvnly-property-filter-checkbox').addClass('checked');
                        
                        // Trigger change event which will automatically trigger AJAX search
                        $checkbox.trigger('change');
                    }
                } else {
                    // If checkbox not found, trigger search directly
                    self.main.currentPage = 1;
                    self.main.performAjaxSearch();
                }
                
                return false;
            });



            $(document).on('click', '.hvnly-property-search-tab', function(e) {
                e.preventDefault();
                const $tab = $(this);
                const value = $tab.data('value');
                
                $('.hvnly-property-search-tab').removeClass('active');
                $tab.addClass('active');
                
                $('#department').val(value);
                
                self.main.currentPage = 1;
                self.main.performAjaxSearch();
            });
            
            $(document).on('change', '.hvnly-property-tax-multichebox input[type="checkbox"]', function() {
                self.updateSelectedItems($(this).closest('.hvnly-property-tax-multichebox'));
                self.main.currentPage = 1;
                self.main.performAjaxSearch();
            });
            
            $(document).on('change', '#hvnly-property-search-bedrooms, #hvnly-property-search-bathrooms, #hvnly-property-search-min-price, #hvnly-property-search-max-price', function() {
                self.main.currentPage = 1;
                self.main.performAjaxSearch();
            });
            
            $(document).on('change', '#hvnly-filter-sidebar input[type="checkbox"], #hvnly-filter-sidebar select', 
                this.utils.debounce(function(e) {
                    if ($(e.target).is('input[type="checkbox"]')) {
                        const $checkbox = $(e.target).closest('.hvnly-property-filter-checkbox');
                        if (e.target.checked) {
                            $checkbox.addClass('checked');
                        } else {
                            $checkbox.removeClass('checked');
                        }
                    }
                    
                    self.main.currentPage = 1;
                    self.main.handleFilterChange();
                }, 300)
            );
            
            $(document).on('click', '.hvnly-apply-filters', function() {
                self.main.currentPage = 1;
                self.main.handleFilterChange();
            });
            
            $(document).on('click', '.hvnly-property-reset-filters-btn', function() {
                self.resetFilters();
            });
            
            $(document).on('change', '.hvnly-property-sort-select', function() {
                self.main.currentPage = 1;
                self.main.performAjaxSearch();
            });
            
            $(document).on('click', '.hvnly-property-view-btn', function(e) {
                e.preventDefault();
                const $button = $(this);
                const viewType = $button.data('view');
                
                $('.hvnly-property-view-btn').removeClass('active');
                $button.addClass('active');
                
                $('#view-type-input').val(viewType);
                
                if (window.havenlyticsFrontend && window.havenlyticsFrontend.setView) {
                    window.havenlyticsFrontend.setView(viewType);
                }
                
                self.main.modules.url.updateBrowserUrl();
                
                if (viewType !== 'map') {
                    self.main.currentPage = 1;
                    self.main.performAjaxSearch();
                }
            });
        }
        
        resetFilters() {
            this.main.modules.ui.showLoading();
            
            $('#hvnly-property-search-form__box')[0].reset();
            
            const filterSidebar = $('#hvnly-filter-sidebar');
            if (filterSidebar.length) {
                filterSidebar.find('input, select').each(function() {
                    if ($(this).is('input[type="checkbox"]')) {
                        $(this).prop('checked', false);
                        $(this).trigger('change');
                    } else if ($(this).is('select')) {
                        $(this).prop('selectedIndex', 0);
                    } else {
                        $(this).val('');
                    }
                });
            }
            
            $('.hvnly-property-tax-multichebox').each(function() {
                const container = $(this);
                const dropdown = container.find('.hvnly-property-taxonomyDropdown-items');
                
                dropdown.find('input[type="checkbox"]').prop('checked', false);
                
                const selectedContainer = container.find('.hvnly-property-selected-items-container-tags');
                selectedContainer.empty();
                
                container.find('.hvnly-property-form-input-multiselect').val('');
                dropdown.hide();
            });
            
            $('.hvnly-property-filter-checkbox').removeClass('checked');
            
            $('.hvnly-property-view-btn').removeClass('active');
            $('[data-view="grid"]').addClass('active');
            $('#view-type-input').val('grid');
            
            // Reset the layout classes
            const propertyGrid = this.main.modules.utils.resolvePropertyGrid(
                    this.main.modules.search ? this.main.modules.search.currentInstanceId : null
                );
            if (propertyGrid.length) {
                propertyGrid.removeClass('hvnly-list-view list-view hvnly-grid-view grid-view map-view');
                propertyGrid.addClass('hvnly-grid-view grid-view');
                propertyGrid.css({
                    'display': 'grid',
                    'gridTemplateColumns': 'repeat(auto-fill, minmax(350px, 1fr))',
                    'gap': '20px'
                });
            }
            
            // Also reset the view in the frontend instance
            if (window.havenlyticsFrontend && window.havenlyticsFrontend.setView) {
                window.havenlyticsFrontend.setView('grid');
            }
            
            this.main.currentPage = 1;
            
            setTimeout(() => {
                this.main.performAjaxSearch();
            }, 100);
        }
        
        initMultiSelectDropdowns() {
            const self = this;
            
            $('.hvnly-property-tax-multichebox').each(function() {
                const container = $(this);
                const input = container.find('.hvnly-property-form-input-multiselect');
                const dropdown = container.find('.hvnly-property-taxonomyDropdown-items');
                
                self.updateSelectedItems(container);
                
                input.on('focus', function() {
                    dropdown.show();
                });
                
                input.on('input', function() {
                    const searchValue = $(this).val();
                    const value = searchValue ? searchValue.toLowerCase() : '';
                    
                    const items = dropdown.find('li');
                    items.each(function() {
                        const text = $(this).text().toLowerCase();
                        $(this).toggle(text.includes(value));
                    });
                });
                
                container.find('.hvnly-property-dropdown-tags-close').on('click', function() {
                    dropdown.hide();
                });
                
                container.find('.hvnly-property-dropdown-reset').on('click', function() {
                    dropdown.find('input[type="checkbox"]').prop('checked', false);
                    self.updateSelectedItems(container);
                    self.main.currentPage = 1;
                    self.main.performAjaxSearch();
                });
                
                $(document).on('click', function(e) {
                    if (!container.is(e.target) && container.has(e.target).length === 0) {
                        dropdown.hide();
                    }
                });
            });
        }
        
        updateSelectedItems(container) {
            const selectedContainer = container.find('.hvnly-property-selected-items-container-tags');
            const dropdown = container.find('.hvnly-property-taxonomyDropdown-items');
            
            if (!selectedContainer.length || !dropdown.length) return;
            
            const checkboxes = dropdown.find('input[type="checkbox"]:checked');
            const selectedItems = [];
            
            checkboxes.each(function() {
                const label = $(this).closest('label');
                const labelText = label.find('.hvnly-property-checkbox-mlt-label').text();
                selectedItems.push({
                    text: labelText,
                    value: this.value
                });
            });
            
            selectedContainer.empty();
            selectedItems.forEach(item => {
                const itemElement = $('<div class="hvnly-property-selected-single-item"></div>');
                // Use text nodes — never inject label text via .html() (DOM XSS).
                itemElement.append(document.createTextNode(item.text));
                const removeBtn = $('<span class="hvnly-property-selected-single-item-remove"></span>')
                    .attr('data-value', item.value)
                    .text('×');
                itemElement.append(removeBtn);
                selectedContainer.append(itemElement);
            });
            
            const self = this;
            selectedContainer.find('.hvnly-property-selected-single-item-remove').on('click', function(e) {
                e.stopPropagation();
                const value = $(this).data('value');
                const checkbox = dropdown.find(`input[type="checkbox"][value="${value}"]`);
                if (checkbox.length) {
                    checkbox.prop('checked', false);
                    self.updateSelectedItems(container);
                    self.main.currentPage = 1;
                    self.main.performAjaxSearch();
                }
            });
        }
    }

    window.HavenlyticsFilters = HavenlyticsFilters;

})(jQuery);