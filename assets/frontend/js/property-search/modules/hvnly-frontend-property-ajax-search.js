/**
 * Havenlytics AJAX Search Module - Fixed for Elementor Widget
 * 
 * @package     Havenlytics
 * @version     2.2.3 - Fixed instance ID handling and data propagation
 */

(function($) {
    'use strict';

    class HavenlyticsSearch {
        constructor(main) {
            this.main = main;
            this.isLoadMore = false;
            this.currentInstanceId = null;
            this.cumulativeLoadedCount = 0;
            this.paginationType = 'load-more';
            this.debugMode = false;
        }
        
        init() {
            this.utils = this.main.modules.utils;
            this.ui = this.main.modules.ui;
            this.bindSearchEvents();
            this.initCumulativeCount();
            this.detectPaginationType();
        }
        
        detectPaginationType() {
            const loadMore = this.main.modules.utils.resolveLoadMoreContainer(null);
            if (loadMore.length && loadMore.is(':visible')) {
                this.paginationType = 'load-more';
            } else {
                const pagination = this.main.modules.utils.resolvePaginationContainer(null);
                if (pagination.length && pagination.is(':visible')) {
                    this.paginationType = 'traditional';
                }
            }
        }
        
        initCumulativeCount() {
            const $activeContainer = this.main.modules.utils.resolveLoadMoreContainer(null);
            
            if ($activeContainer.length) {
                const loadedCountSpan = $activeContainer.find('.hvnly-property-load-more-info span:first-child');
                if (loadedCountSpan.length) {
                    this.cumulativeLoadedCount = parseInt(loadedCountSpan.text()) || 0;
                }
            }
        }
        
        bindSearchEvents() {
            const self = this;
            
            $(document).on('submit', '#hvnly-property-search-form__box', function(e) {
                e.preventDefault();
                self.main.handleSearch();
            });
            
            $(document).on('input', '.hvnly-ajax-search input[name="address_keyword"]', 
                this.utils.debounce(function(e) {
                    self.main.currentPage = 1;
                    self.isLoadMore = false;
                    self.currentInstanceId = null;
                    self.main.performAjaxSearch();
                }, 300)
            );
        }
        
        performAjaxSearch() {
            if (window.HvnlyElementorPreview && window.HvnlyElementorPreview.shouldDisableInteractiveUi && window.HvnlyElementorPreview.shouldDisableInteractiveUi()) {
                return;
            }
            
            if (this.main.isLoading) {
                return;
            }
            
            this.main.isLoading = true;
            this.ui.showLoading(null, this.currentInstanceId);
            
            const data = this.utils.getFormData();
            data.page = this.main.currentPage;
            
            // CRITICAL FIX: Add instance_id to request for proper handling
            if (this.currentInstanceId) {
                data.instance_id = this.currentInstanceId;
            }
            
            // Also pass per_page if available from container
            if (this.currentInstanceId) {
                const $container = this.main.modules.utils.resolveLoadMoreContainer(this.currentInstanceId);
                if ($container.length) {
                    const perPage = $container.data('posts-per-page');
                    if (perPage) {
                        data.per_page = perPage;
                    }
                }
            }
            
            $.ajax({
                url: this.main.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        // Pass the instance ID to the response data
                        if (this.currentInstanceId) {
                            response.data.instance_id = this.currentInstanceId;
                        }
                        this.handleSearchSuccess(response.data);
                    } else {
                        const errorMsg = response.data || 'Unknown error';
                        this.ui.showError('Failed to load properties: ' + errorMsg);
                    }
                },
                error: (xhr, status, error) => {
                    this.ui.showError('An error occurred while loading properties. Please try again.');
                },
                complete: () => {
                    const completedInstanceId = this.currentInstanceId;
                    this.main.isLoading = false;
                    this.ui.hideLoading(completedInstanceId);
                    if (this.isLoadMore) {
                        this.isLoadMore = false;
                    }
                }
            });
        }
        
        handleSearchSuccess(data) {
            this.updatePropertyGrid(data);
            
            if (this.main.modules.pagination && typeof this.main.modules.pagination.updatePaginationDisplay === 'function') {
                this.main.modules.pagination.updatePaginationDisplay(data);
            } else {
                if (window.HvnlyDom && typeof window.HvnlyDom.syncListingState === 'function') {
                    window.HvnlyDom.syncListingState(data);
                } else {
                    this.updatePagination(data);
                }
                this.updateLoadMoreSection(data);
            }
            
            this.updateCounts(data);

            this.main.modules.url.updateBrowserUrl();

            this.reinitializeFrontend();
            $(document).trigger('hvnly-properties-updated', [data]);
        }
        
        updatePropertyGrid(data) {
            const propertyGrid = this.main.modules.utils.resolvePropertyGrid(this.currentInstanceId);
            
            if (propertyGrid.length && data.html) {
                if (this.isLoadMore && this.main.currentPage > 1) {
                    propertyGrid.append(data.html);
                } else {
                    propertyGrid.html(data.html);
                }
                
                this.updateGridDisplayStyle();
            }
        }

        updateGridDisplayStyle() {
            const propertyGrid = this.main.modules.utils.resolvePropertyGrid(this.currentInstanceId);
            const activeView = $('.hvnly-property-view-btn.active');
            
            if (activeView.length && propertyGrid.length) {
                const viewType = activeView.data('view');
                
                propertyGrid.removeAttr('style');
                
                if (viewType === 'grid') {
                    propertyGrid.css({
                        'display': 'grid',
                        'gridTemplateColumns': 'repeat(auto-fill, minmax(350px, 1fr))',
                        'gap': '20px'
                    });
                } else if (viewType === 'list') {
                    propertyGrid.css({
                        'display': 'flex',
                        'flexDirection': 'column',
                        'gap': '20px'
                    });
                } else if (viewType === 'map') {
                    propertyGrid.css('display', 'none');
                }
            }
        }
        
        updatePagination(data) {
            const paginationContainer = this.main.modules.utils.resolvePaginationContainer(this.currentInstanceId);
            if (paginationContainer.length && data.pagination_html) {
                paginationContainer.html(data.pagination_html);
            }
        }
        
        updateResultsCount(data) {
            if (window.HvnlyDom && typeof window.HvnlyDom.syncListingState === 'function') {
                window.HvnlyDom.syncListingState(data);
                return;
            }

            const resultsCount = this.main.modules.utils.resolveResultsCountHeader(this.currentInstanceId);
            if (resultsCount.length && data.results_count_html) {
                resultsCount.replaceWith(data.results_count_html);
            }
        }
        
        updateLoadMoreSection(data) {
            const $loadMoreContainer = this.main.modules.utils.resolveLoadMoreContainer(this.currentInstanceId);
            
            if (!$loadMoreContainer.length) return;

            this.main.maxPages = data.max_pages;
            this.main.currentPage = data.current_page;
            
            // Update container data attributes
            $loadMoreContainer.data('max-pages', data.max_pages);
            $loadMoreContainer.data('current-page', data.current_page);
            $loadMoreContainer.data('found-posts', data.found_posts);
            
            // Also update HTML attributes for CSS selectors
            $loadMoreContainer.attr('data-max-pages', data.max_pages);
            $loadMoreContainer.attr('data-current-page', data.current_page);
            $loadMoreContainer.attr('data-found-posts', data.found_posts);

            if (data.current_page >= data.max_pages) {
                $loadMoreContainer.hide();
            } else {
                $loadMoreContainer.show();
            }
        }
        
        updateCounts(data) {
            // Use instance-specific IDs if available
            let loadedCountSelector = '#loadedCount';
            let totalCountSelector = '#totalCount';
            
            if (this.currentInstanceId) {
                loadedCountSelector = `#loadedCount-${this.currentInstanceId}`;
                totalCountSelector = `#totalCount-${this.currentInstanceId}`;
            }
            
            const loadedCount = $(loadedCountSelector);
            const totalCount = $(totalCountSelector);
            
            // Also try to find within the container
            if (!loadedCount.length && this.currentInstanceId) {
                const $container = this.main.modules.utils.resolveLoadMoreContainer(this.currentInstanceId);
                if ($container.length) {
                    const altLoadedCount = $container.find('.hvnly-property-load-more-info span:first-child');
                    const altTotalCount = $container.find('.hvnly-property-load-more-info span:last-child');
                    if (altLoadedCount.length && altTotalCount.length) {
                        this.updateCounterElements(altLoadedCount, altTotalCount, data);
                        return;
                    }
                }
            }
            
            if (loadedCount.length && totalCount.length) {
                this.updateCounterElements(loadedCount, totalCount, data);
            }
        }
        
        updateCounterElements($loadedCount, $totalCount, data) {
            if ($loadedCount.length && data.found_posts) {
                let cumulativeCount;
                const currentPage = data.current_page || 1;
                const perPage = data.posts_per_page || this.main.postsPerPage;
                
                if (this.isLoadMore) {
                    cumulativeCount = this.cumulativeLoadedCount + (data.post_count || 0);
                } else {
                    cumulativeCount = Math.min(currentPage * perPage, data.found_posts);
                }
                
                cumulativeCount = Math.min(cumulativeCount, data.found_posts);
                this.cumulativeLoadedCount = cumulativeCount;
                $loadedCount.text(cumulativeCount);
            }
            
            if ($totalCount.length && data.found_posts) {
                $totalCount.text(data.found_posts);
            }
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
            }, 300);
        }
    }

    window.HavenlyticsSearch = HavenlyticsSearch;

})(jQuery);