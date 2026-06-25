/**
 * Havenlytics AJAX Utilities Module
 * 
 * @package     Havenlytics
 * @version     2.2.2
 */

(function($) {
    'use strict';

    class HavenlyticsUtils {
        constructor(main) {
            this.main = main;
            this.debugMode = false;
        }
        
        init() {
            // Initialization logic if needed
        }
        
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        getFormData() {
            const nonce = this.main.nonce || window.hvnly_PROPERTY_ajax?.nonce || '';
            
            const data = {
                action: 'hvnly_search_properties',
                nonce: nonce,
                page: this.main.currentPage
            };
            
            const searchForm = $('#hvnly-property-search-form__box');
            if (searchForm.length) {
                const formData = new FormData(searchForm[0]);
                
                for (let [key, value] of formData.entries()) {
                    if (key.includes('[]')) {
                        const cleanKey = key.replace('[]', '');
                        if (!data[cleanKey]) data[cleanKey] = [];
                        data[cleanKey].push(value);
                    } else {
                        data[key] = value;
                    }
                }
            }
            
            this.collectSidebarFilterData(data);
            this.collectTopSearchData(data);
            this.collectAdditionalData(data);

            // Always send the active page last so form hidden `paged=1` cannot override it.
            data.page = this.main.currentPage;
            data.paged = this.main.currentPage;

            const $pagedInput = $('#paged-input');
            if ($pagedInput.length) {
                $pagedInput.val(this.main.currentPage);
            }

            return data;
        }
        
        collectSidebarFilterData(data) {
            const filterSidebar = $('#hvnly-filter-sidebar');
            if (!filterSidebar.length) return;
            
            const minPrice = filterSidebar.find('select[name="min_price"]').val();
            const maxPrice = filterSidebar.find('select[name="max_price"]').val();
            if (minPrice) data.min_price = minPrice;
            if (maxPrice) data.max_price = maxPrice;
            
            const bedrooms = filterSidebar.find('select[name="bedrooms"]').val();
            const reception_rooms = filterSidebar.find('select[name="reception_rooms"]').val();
            const bathrooms = filterSidebar.find('select[name="bathrooms"]').val();
            if (bedrooms) data.bedrooms = bedrooms;
            if (bathrooms) data.bathrooms = bathrooms;
            if (reception_rooms) data.reception_rooms = reception_rooms;
            
            const garages = filterSidebar.find('select[name="garages"]').val();
            if (garages) data.garages = garages;
            
            const amenities = filterSidebar.find('input[name="amenities[]"]:checked').map(function() {
                return this.value;
            }).get();
            if (amenities.length) data.amenities = amenities;
            
            const taxonomies = ['hvnly_prop_types', 'hvnly_prop_locations', 'hvnly_prop_features', 'hvnly_prop_reviews', 'hvnly_prop_tags', 'hvnly_prop_badges', 'hvnly_prop_status'];
            
            taxonomies.forEach(taxonomy => {
                const terms = filterSidebar.find(`input[name="${taxonomy}[]"]:checked`).map(function() {
                    return this.value;
                }).get();
                
                if (terms.length) {
                    data[taxonomy] = terms;
                }
            });
            
            const propertyIds = filterSidebar.find('input[name="property_ids[]"]:checked').map(function() {
                return this.value;
            }).get();
            
            if (propertyIds.length) {
                data.property_ids = propertyIds;
            }
        }
        
        collectTopSearchData(data) {
            const topSearchDropdowns = $('.hvnly-property-tax-multichebox');
            if (!topSearchDropdowns.length) return;
            
            const topPropertyTypes = $('.hvnly-property-tax-multichebox input[name="property_type[]"]:checked').map(function() {
                return this.value;
            }).get();
            if (topPropertyTypes.length) {
                data.hvnly_prop_types = topPropertyTypes;
            }
            
            const topLocations = $('.hvnly-property-tax-multichebox input[name="location[]"]:checked').map(function() {
                return this.value;
            }).get();
            if (topLocations.length) {
                data.hvnly_prop_locations = topLocations;
            }
        }
        
        collectAdditionalData(data) {
            const sortSelect = $('.hvnly-property-sort-select');
            if (sortSelect.length && sortSelect.val()) {
                data.orderby = sortSelect.val();
            }

            const instanceId = this.main.modules.search && this.main.modules.search.currentInstanceId
                ? this.main.modules.search.currentInstanceId
                : null;

            const $widget = instanceId
                ? $(`.hvnly-all-properties-widget[data-widget-id="${instanceId}"]`).first()
                : $('.hvnly-all-properties-widget').first();

            if ($widget.length) {
                if ($widget.data('featured-only') === 'yes') {
                    data.featured_only = 'yes';
                }

                const widgetId = $widget.attr('data-widget-id');
                if (widgetId) {
                    data.widget_id = widgetId;
                }
            }

            const activeView = $('.hvnly-property-view-btn.active');
            if (activeView.length) {
                data.view_type = activeView.data('view');
            } else {
                const viewInput = $('#hvnly-view-type-input').val() || $('#view-type-input').val();
                data.view_type = viewInput || 'grid';
            }

            return data;
        }

        resolvePropertyGrid(instanceId) {
            return window.HvnlyDom.resolvePropertyGrid(instanceId);
        }

        resolveMapPlaceholder(instanceId) {
            return window.HvnlyDom.resolveMapPlaceholder(instanceId);
        }

        resolveLoadMoreContainer(instanceId) {
            return window.HvnlyDom.resolveLoadMoreContainer(instanceId);
        }

        resolvePaginationContainer(instanceId) {
            return window.HvnlyDom.resolvePaginationContainer(instanceId);
        }

        resolveResultsCountHeader(instanceId) {
            return window.HvnlyDom.resolveResultsCountHeader(instanceId);
        }
    }

    window.HavenlyticsUtils = HavenlyticsUtils;

})(jQuery);