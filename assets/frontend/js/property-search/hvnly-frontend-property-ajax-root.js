/**
 * Havenlytics AJAX Main Controller 
 * 
 * @package     Havenlytics
 * @version     2.2.3
 */

(function($) {
    'use strict';

    class HavenlyticsAJAX {
        constructor() {
            this.ajaxUrl = window.hvnly_PROPERTY_ajax?.ajax_url || window.ajaxurl;
            this.nonce = window.hvnly_PROPERTY_ajax?.nonce || '';
            this.currentPage = parseInt(window.hvnly_PROPERTY_ajax?.current_page) || 1;
            this.maxPages = parseInt(window.hvnly_PROPERTY_ajax?.max_pages) || 1;
            this.isLoading = false;
            this.debugMode = false;
            
            this.postsPerPage = parseInt(window.hvnly_PROPERTY_ajax?.properties_per_page) || 
                                parseInt(window.hvnly_PROPERTY_ajax?.per_page) || 
                                12;
            
            this.paginationType = this.detectPaginationType();
            
            this.modules = {};
            this.initModules();
            this.init();
        }
        
        detectPaginationType() {
            if (window.HvnlyDom) {
                const loadMore = window.HvnlyDom.resolveLoadMoreContainer(null);
                if (loadMore.length && loadMore.is(':visible')) {
                    return 'load-more';
                }

                const pagination = window.HvnlyDom.resolvePaginationContainer(null);
                if (pagination.length && pagination.is(':visible')) {
                    return 'traditional';
                }
            }

            return 'load-more';
        }
        
        initModules() {
            this.modules.utils = new HavenlyticsUtils(this);
            this.modules.ui = new HavenlyticsUI(this);
            this.modules.url = new HavenlyticsURL(this);
            this.modules.pagination = new HavenlyticsPagination(this);
            this.modules.filters = new HavenlyticsFilters(this);
            this.modules.search = new HavenlyticsSearch(this);
        }
        
        init() {
            Object.values(this.modules).forEach(module => {
                if (typeof module.init === 'function') {
                    module.init();
                }
            });
            this.setDefaultActiveView();
        }
        
        setDefaultActiveView() {
            const urlParams = new URLSearchParams(window.location.search);
            const urlViewType = urlParams.get('hvnly_view_type') || urlParams.get('view_type') || urlParams.get('view');
            const activeButton = $('.hvnly-property-view-btn.active');
            const defaultViewType = urlViewType || (activeButton.length ? activeButton.data('view') : 'grid');
            
            if (urlViewType) {
                $('.hvnly-property-view-btn').removeClass('active');
                $(`.hvnly-property-view-btn[data-view="${defaultViewType}"]`).addClass('active');
            }

            $('#hvnly-view-type-input').val(defaultViewType);
            $('#view-type-input').val(defaultViewType);
        }

        isMapViewActive() {
            if ($('.hvnly-property-view-btn.active[data-view="map"]').length) {
                return true;
            }

            const viewType = $('#hvnly-view-type-input').val() || $('#view-type-input').val();
            return viewType === 'map';
        }

        /**
         * Agent/agency single pages render assigned listings server-side only.
         *
         * @return {boolean}
         */
        isProfileAssignedListingsPage() {
            const $profileSection = $('#hvnly-agent-properties, #hvnly-agency-properties');
            if (!$profileSection.length) {
                return false;
            }

            if ($('[id^="hvnly-property-grid"]').length || $('.hvnly-property-grid-view').length || $('#hvnly-property-search-form__box').length) {
                return false;
            }

            return true;
        }
        
        performAjaxSearch(instanceId = null) {
            if (window.HvnlyElementorPreview && window.HvnlyElementorPreview.shouldDisableInteractiveUi && window.HvnlyElementorPreview.shouldDisableInteractiveUi()) {
                return;
            }

            if (this.isMapViewActive()) {
                return;
            }

            // Agent/agency profile listings are SSR-only (no archive AJAX on those pages).
            if (this.isProfileAssignedListingsPage()) {
                return;
            }
            
            if (this.isLoading) {
                return;
            }
            
            if (this.modules.search) {
                if (!this.modules.search.isLoadMore) {
                    this.modules.search.isLoadMore = false;
                }
                this.modules.search.currentInstanceId = instanceId;
            }
            
            this.modules.search.performAjaxSearch();
        }
        
        handleFilterChange() {
            this.currentPage = 1;
            if (this.modules.search) {
                this.modules.search.isLoadMore = false;
                this.modules.search.currentInstanceId = null;
            }
            this.performAjaxSearch();
        }
        
        handleSearch() {
            this.currentPage = 1;
            if (this.modules.search) {
                this.modules.search.isLoadMore = false;
                this.modules.search.currentInstanceId = null;
            }
            this.performAjaxSearch();
        }
        
        getFormData() {
            if (this.modules.utils && typeof this.modules.utils.getFormData === 'function') {
                return this.modules.utils.getFormData();
            }
            return {};
        }
        
        reinitializeFrontend() {
            setTimeout(() => {
                if (window.havenlyticsFrontend) {
                    if (typeof window.havenlyticsFrontend.initPropertyGalleryCarousels === 'function') {
                        window.havenlyticsFrontend.initPropertyGalleryCarousels();
                    }
                    if (typeof window.havenlyticsFrontend.initFavoriteButtons === 'function') {
                        window.havenlyticsFrontend.initFavoriteButtons();
                    }
                    if (typeof window.havenlyticsFrontend.initMultiSelectDropdowns === 'function') {
                        window.havenlyticsFrontend.initMultiSelectDropdowns();
                    }
                    if (typeof window.havenlyticsFrontend.initCheckboxes === 'function') {
                        window.havenlyticsFrontend.initCheckboxes();
                    }
                }
                $(document).trigger('hvnly_ajax_reinitialize_complete');
            }, 300);
        }
        
        hvnly_resetFilters() {
            this.currentPage = 1;
            if (this.modules.search) {
                this.modules.search.isLoadMore = false;
                this.modules.search.currentInstanceId = null;
            }
            
            if (this.modules.filters && typeof this.modules.filters.hvnly_resetFilters === 'function') {
                this.modules.filters.hvnly_resetFilters();
            } else {
                $('.hvnly-filter-input, .hvnly-filter-select, input.hvnly-filter').val('');
                $('.hvnly-filter-checkbox').prop('checked', false);
            }
            
            this.performAjaxSearch();
        }
        
        hvnly_getCurrentState() {
            return {
                currentPage: this.currentPage,
                maxPages: this.maxPages,
                paginationType: this.paginationType,
                isLoading: this.isLoading,
                postsPerPage: this.postsPerPage
            };
        }
    }

    $(document).ready(function() {
        window.havenlyticsAJAX = new HavenlyticsAJAX();
        
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.hvnly_path) {
                window.location.href = event.state.hvnly_path;
            }
        });
        
        $(document).on('hvnly_reset_filters', function() {
            if (window.havenlyticsAJAX && typeof window.havenlyticsAJAX.hvnly_resetFilters === 'function') {
                window.havenlyticsAJAX.hvnly_resetFilters();
            }
        });
    });

})(jQuery);