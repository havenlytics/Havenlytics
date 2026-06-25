/**
 * Havenlytics AJAX URL Module
 * 
 * @package     Havenlytics
 * @version     2.0.3
 */

(function($) {
    'use strict';

    class HavenlyticsURL {
        constructor(main) {
            this.main = main;
        }
        
        init() {
            this.utils = this.main.modules.utils;
            this.bindURLEvents();
            if (window.HvnlyElementorPreview && window.HvnlyElementorPreview.shouldDisableInteractiveUi()) {
                return;
            }
            setTimeout(() => this.restoreFiltersFromUrl(), 500);
        }
        
        bindURLEvents() {
            window.addEventListener('popstate', (event) => {
                if (event.state && event.state.path) {
                    window.location.href = event.state.path;
                }
            });
        }
        
        updateBrowserUrl() {
            const data = this.utils.getFormData();
            const params = [];
            
            // Handle property_ids parameter
            if (data.property_ids && Array.isArray(data.property_ids) && data.property_ids.length > 0) {
                const cleanValue = data.property_ids
                    .filter(val => val && val !== '')
                    .map(val => encodeURIComponent(val))
                    .join(',');
                if (cleanValue) {
                    params.push(`property_ids=${cleanValue}`);
                }
            }

            if (data.hvnly_prop_tags && Array.isArray(data.hvnly_prop_tags) && data.hvnly_prop_tags.length > 0) {
                const tagIds = [];
                data.hvnly_prop_tags.forEach(slug => {
                    const checkbox = $(`#hvnly-filter-sidebar input[name="hvnly_prop_tags[]"][value="${slug}"]`);
                    if (checkbox.length) {
                        const termId = checkbox.data('term-id');
                        if (termId) {
                            tagIds.push(termId);
                        }
                    }
                });
                
                if (tagIds.length > 0) {
                    params.push(`in_tag=${tagIds.join(',')}`);
                }
            }

            if (data.hvnly_prop_badges && Array.isArray(data.hvnly_prop_badges) && data.hvnly_prop_badges.length > 0) {
                const badgeIds = [];
                data.hvnly_prop_badges.forEach(slug => {
                    const checkbox = $(`#hvnly-filter-sidebar input[name="hvnly_prop_badges[]"][value="${slug}"]`);
                    if (checkbox.length) {
                        const termId = checkbox.data('term-id');
                        if (termId) {
                            badgeIds.push(termId);
                        }
                    }
                });

                if (badgeIds.length > 0) {
                    params.push(`in_badge=${badgeIds.join(',')}`);
                }
            }
            
            if (data.hvnly_prop_status && Array.isArray(data.hvnly_prop_status) && data.hvnly_prop_status.length > 0) {
                const statusIds = [];
                data.hvnly_prop_status.forEach(slug => {
                    const checkbox = $(`#hvnly-filter-sidebar input[name="hvnly_prop_status[]"][value="${slug}"]`);
                    if (checkbox.length) {
                        const termId = checkbox.data('term-id');
                        if (termId) {
                            statusIds.push(termId);
                        }
                    }
                });
                
                if (statusIds.length > 0) {
                    params.push(`in_status=${statusIds.join(',')}`);
                }
            }
            
            if (data.hvnly_prop_features && Array.isArray(data.hvnly_prop_features) && data.hvnly_prop_features.length > 0) {
                const featureIds = [];
                data.hvnly_prop_features.forEach(slug => {
                    const checkbox = $(`#hvnly-filter-sidebar input[name="hvnly_prop_features[]"][value="${slug}"]`);
                    if (checkbox.length) {
                        const termId = checkbox.data('term-id');
                        if (termId) {
                            featureIds.push(termId);
                        }
                    }
                });
                
                if (featureIds.length > 0) {
                    params.push(`in_feature=${featureIds.join(',')}`);
                }
            }
            
            if (data.department && data.department !== '') {
                params.push(`department=${encodeURIComponent(data.department)}`);
            }
            
            if (data.hvnly_prop_types && Array.isArray(data.hvnly_prop_types) && data.hvnly_prop_types.length > 0) {
                const cleanValue = data.hvnly_prop_types
                    .filter(val => val && val !== '')
                    .map(val => encodeURIComponent(val))
                    .join(',');
                if (cleanValue) {
                    params.push(`property_type=${cleanValue}`);
                }
            }
            
            if (data.hvnly_prop_locations && Array.isArray(data.hvnly_prop_locations) && data.hvnly_prop_locations.length > 0) {
                const cleanValue = data.hvnly_prop_locations
                    .filter(val => val && val !== '')
                    .map(val => encodeURIComponent(val))
                    .join(',');
                if (cleanValue) {
                    params.push(`location=${cleanValue}`);
                }
            }
            
            const standardParams = [
                'address_keyword', 'min_price', 'max_price', 'bedrooms', 'bathrooms', 'reception_rooms', 'garages'
            ];
            
            standardParams.forEach(param => {
                if (data[param] && data[param] !== '') {
                    params.push(`${param}=${encodeURIComponent(data[param])}`);
                }
            });
            
            if (data.view_type && data.view_type !== 'grid') {
                params.push(`view_type=${encodeURIComponent(data.view_type)}`);
            } else {
                params.push(`view_type=grid`);
            }
            
            if (data.orderby && data.orderby !== 'date') {
                params.push(`orderby=${encodeURIComponent(data.orderby)}`);
            } else {
                params.push(`orderby=date`);
            }
            
            if (this.main.currentPage && this.main.currentPage >= 1) {
                params.push(`paged=${this.main.currentPage}`);
            } else {
                params.push(`paged=1`);
            }
            
            const baseUrl = window.location.origin + window.location.pathname;
            const queryString = params.join('&');
            const newUrl = baseUrl + (queryString ? '?' + queryString : '');
            
            // FIX: Safe pushState with try-catch to handle browser extension conflicts
            try {
                if (window.history && typeof window.history.pushState === 'function') {
                    window.history.pushState({ path: newUrl }, '', newUrl);
                }
            } catch (e) {
                // Browser extension blocked pushState - silently fail, don't break functionality
                // Only log in debug mode for troubleshooting
                // if (window.hvnly_PROPERTY_ajax && window.hvnly_PROPERTY_ajax.debug === '1') {
                //     console.warn('Havenlytics: Unable to update browser URL (extension conflict):', e.message);
                // }
            }
        }
        
        restoreFiltersFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);

            // Term IDs — must match updateBrowserUrl() keys: in_tag, in_badge, in_status, in_feature
            const termIdTaxonomyParams = {
                in_tag: 'hvnly_prop_tags',
                in_badge: 'hvnly_prop_badges',
                in_status: 'hvnly_prop_status',
                in_feature: 'hvnly_prop_features',
            };

            Object.entries(termIdTaxonomyParams).forEach(([urlParam, fieldName]) => {
                this.restoreTermIdTaxonomy(urlParams, urlParam, fieldName);
            });

            // Slug lists — must match updateBrowserUrl() keys: property_type, location
            const slugTaxonomyParams = {
                property_type: 'hvnly_prop_types',
                location: 'hvnly_prop_locations',
            };

            Object.entries(slugTaxonomyParams).forEach(([urlParam, fieldName]) => {
                this.restoreSlugTaxonomy(urlParams, urlParam, fieldName, {
                    syncMultichebox: urlParam,
                });
            });

            // Legacy slug taxonomy keys (older shared URLs; not written by updateBrowserUrl)
            const legacySlugTaxonomyParams = {
                feature: 'hvnly_prop_features',
                review: 'hvnly_prop_reviews',
                tag: 'hvnly_prop_tags',
                badge: 'hvnly_prop_badges',
                status: 'hvnly_prop_status',
                amenity: 'amenities',
                department: 'hvnly_prop_depts',
            };

            Object.entries(legacySlugTaxonomyParams).forEach(([urlParam, fieldName]) => {
                this.restoreSlugTaxonomy(urlParams, urlParam, fieldName);
            });

            // Restore property_ids from URL
            if (urlParams.has('property_ids')) {
                const paramValue = urlParams.get('property_ids');
                const decodedValue = decodeURIComponent(paramValue);
                const values = decodedValue.split(',').filter(val => val !== '');

                if (values.length > 0) {
                    values.forEach(value => {
                        const checkbox = $(`#hvnly-filter-sidebar input[name="property_ids[]"][value="${value}"]`);
                        if (checkbox.length) {
                            checkbox.prop('checked', true);
                            checkbox.closest('.hvnly-property-filter-checkbox').addClass('checked');
                        }
                    });
                }
            }

            if (urlParams.has('property_type[]') || urlParams.has('location[]')) {
                ['property_type[]', 'location[]'].forEach(arrayParam => {
                    const values = urlParams.getAll(arrayParam);
                    if (values.length > 0) {
                        const internalParam = arrayParam === 'property_type[]' ? 'hvnly_prop_types' : 'hvnly_prop_locations';
                        
                        values.forEach(value => {
                            const checkbox = $(`#hvnly-filter-sidebar input[name="${internalParam}[]"][value="${value}"]`);
                            if (checkbox.length) {
                                checkbox.prop('checked', true);
                                checkbox.closest('.hvnly-property-filter-checkbox').addClass('checked');
                            }
                            
                            if (arrayParam === 'property_type[]') {
                                $(`.hvnly-property-tax-multichebox input[name="property_type[]"][value="${value}"]`).prop('checked', true);
                            }
                            if (arrayParam === 'location[]') {
                                $(`.hvnly-property-tax-multichebox input[name="location[]"][value="${value}"]`).prop('checked', true);
                            }
                        });
                    }
                });
            }
            
            const standardFields = [
                'address_keyword', 'department', 'min_price', 'max_price',
                'bedrooms', 'bathrooms', 'reception_rooms', 'garages'
            ];
            
            standardFields.forEach(field => {
                if (urlParams.has(field)) {
                    const value = urlParams.get(field);
                    $(`[name="${field}"]`).val(value);
                }
            });
            
            // orderby is written by updateBrowserUrl(); sort is a legacy alias (see hvnly_get_current_filters)
            if (urlParams.has('orderby')) {
                $('.hvnly-property-sort-select').val(urlParams.get('orderby'));
            } else if (urlParams.has('sort')) {
                $('.hvnly-property-sort-select').val(urlParams.get('sort'));
            }

            // view_type is written by updateBrowserUrl(); view is a legacy alias
            if (urlParams.has('view_type')) {
                this.restoreViewType(urlParams.get('view_type'));
            } else if (urlParams.has('view')) {
                this.restoreViewType(urlParams.get('view'));
            }
            
            if (urlParams.has('paged')) {
                const pagedValue = urlParams.get('paged');
                this.main.currentPage = parseInt(pagedValue) || 1;
            }
            
            const runSearchAfterRestore = this.shouldRunSearchAfterUrlRestore(urlParams);
            const restoredViewType = urlParams.get('view_type') || urlParams.get('view') || 'grid';

            setTimeout(() => {
                $('.hvnly-property-tax-multichebox').each((index, element) => {
                    const input = $(element).find('.hvnly-property-form-input-multiselect')[0];
                    if (input && window.havenlyticsFrontend && window.havenlyticsFrontend.updateSelectedItemsDisplay) {
                        window.havenlyticsFrontend.updateSelectedItemsDisplay(input);
                    }
                });
                
                $('.hvnly-property-filter-checkbox').each(function() {
                    const input = $(this).find('input');
                    $(this).toggleClass('checked', input.is(':checked'));
                });

                if (!runSearchAfterRestore || restoredViewType === 'map') {
                    return;
                }

                if (this.main.modules.search) {
                    this.main.modules.search.isLoadMore = false;
                    this.main.modules.search.currentInstanceId = null;
                }

                this.main.performAjaxSearch();
            }, 100);
        }

        /**
         * Whether restored URL params require a search refresh (vs. server-rendered defaults).
         *
         * @param {URLSearchParams} urlParams
         * @return {boolean}
         */
        shouldRunSearchAfterUrlRestore(urlParams) {
            if (!urlParams || urlParams.toString() === '') {
                return false;
            }

            if ($('#hvnly-agent-properties, #hvnly-agency-properties').length
                && !$('#hvnly-property-grid').length
                && !$('#hvnly-property-search-form__box').length) {
                return false;
            }

            const filterParams = [
                'property_ids', 'in_tag', 'in_badge', 'in_status', 'in_feature',
                'department', 'property_type', 'location',
                'address_keyword', 'min_price', 'max_price', 'bedrooms', 'bathrooms', 'reception_rooms', 'garages',
                'feature', 'review', 'tag', 'badge', 'status', 'amenity',
            ];

            if (filterParams.some((key) => urlParams.has(key))) {
                return true;
            }

            if (urlParams.has('property_type[]') || urlParams.has('location[]')) {
                return true;
            }

            const paged = parseInt(urlParams.get('paged'), 10);
            if (paged > 1) {
                return true;
            }

            const orderby = urlParams.get('orderby') || urlParams.get('sort');
            if (orderby && orderby !== 'date') {
                return true;
            }

            return false;
        }

        /**
         * Restore sidebar taxonomy checkboxes from comma-separated term IDs in the URL.
         *
         * @param {URLSearchParams} urlParams
         * @param {string} urlParam URL key (e.g. in_tag)
         * @param {string} fieldName Form field base (e.g. hvnly_prop_tags)
         */
        restoreTermIdTaxonomy(urlParams, urlParam, fieldName) {
            if (!urlParams.has(urlParam)) {
                return;
            }

            const values = decodeURIComponent(urlParams.get(urlParam))
                .split(',')
                .map((val) => val.trim())
                .filter((val) => val !== '');

            values.forEach((termId) => {
                const checkbox = $(`#hvnly-filter-sidebar input[name="${fieldName}[]"][data-term-id="${termId}"]`);
                if (checkbox.length) {
                    checkbox.prop('checked', true);
                    checkbox.closest('.hvnly-property-filter-checkbox').addClass('checked');
                }
            });
        }

        /**
         * Restore sidebar taxonomy checkboxes from comma-separated slugs in the URL.
         *
         * @param {URLSearchParams} urlParams
         * @param {string} urlParam URL key (e.g. property_type)
         * @param {string} fieldName Form field base (e.g. hvnly_prop_types)
         * @param {{ syncMultichebox?: string }} options
         */
        restoreSlugTaxonomy(urlParams, urlParam, fieldName, options = {}) {
            if (!urlParams.has(urlParam)) {
                return;
            }

            const values = decodeURIComponent(urlParams.get(urlParam))
                .split(',')
                .map((val) => val.trim())
                .filter((val) => val !== '');

            values.forEach((value) => {
                const checkbox = $(`#hvnly-filter-sidebar input[name="${fieldName}[]"][value="${value}"]`);
                if (checkbox.length) {
                    checkbox.prop('checked', true);
                    checkbox.closest('.hvnly-property-filter-checkbox').addClass('checked');
                }

                if (options.syncMultichebox === 'property_type') {
                    $(`.hvnly-property-tax-multichebox input[name="property_type[]"][value="${value}"]`).prop('checked', true);
                }
                if (options.syncMultichebox === 'location') {
                    $(`.hvnly-property-tax-multichebox input[name="location[]"][value="${value}"]`).prop('checked', true);
                }
            });
        }

        /**
         * Restore active view toggle from URL.
         *
         * @param {string} viewType
         */
        restoreViewType(viewType) {
            if (!viewType) {
                return;
            }

            $('.hvnly-property-view-btn').removeClass('active');
            $(`.hvnly-property-view-btn[data-view="${viewType}"]`).addClass('active');
            $('#view-type-input').val(viewType);
        }
    }

    window.HavenlyticsURL = HavenlyticsURL;

})(jQuery);