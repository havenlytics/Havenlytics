/**
 * Havenlytics AJAX Pagination Module - Fixed for Elementor Widget
 * 
 * @package     Havenlytics
 * @version     2.2.6 - Fixed container data update and instance handling
 */

(function($) {
    'use strict';

    class HavenlyticsPagination {
        constructor(main) {
            this.main = main;
            this.cumulativeLoadedCount = 0;
            this.paginationType = 'load-more';
            this.debugMode = false;
        }
        
        init() {
            this.bindPaginationEvents();
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
        
        bindPaginationEvents() {
            const self = this;
            
            $(document).on('click', '.hvnly-property-load-more-btn', function(e) {
                if (self.main.isMapViewActive && self.main.isMapViewActive()) {
                    return;
                }

                e.preventDefault();
                const $button = $(this);
                
                // Get the closest container (works for any button)
                const $container = $button.closest('.hvnly-property-load-more-container');
                
                if (!$container.length) {
                    return;
                }
                
                const instanceId = $container.data('instance-id') || $button.data('instance-id');
                
                self.loadMoreWithContainer($container, instanceId);
                return false;
            });
            
            $(document).on('click', '.hvnly-property-pagination-item', function(e) {
                if ($(this).closest('.hvnly-property--archive').length) {
                    return;
                }

                e.preventDefault();
                const $item = $(this);
                if ($item.hasClass('active') || $item.hasClass('dots')) {
                    return;
                }
                
                const page = $item.data('page');
                const instanceId = $item.data('instance-id');
                
                if (page) {
                    if (self.main.modules.search) {
                        self.main.modules.search.isLoadMore = false;
                        self.main.modules.search.currentInstanceId = instanceId;
                    }
                    self.main.currentPage = parseInt(page);
                    self.main.performAjaxSearch(instanceId);
                }
                return false;
            });
        }
        
        loadMoreWithContainer($container, instanceId = null) {
            if (this.main.isMapViewActive && this.main.isMapViewActive()) {
                return;
            }
            
            if (this.main.isLoading) {
                return;
            }

            if (this.main.modules.search) {
                this.main.modules.search.isLoadMore = true;
                this.main.modules.search.currentInstanceId = instanceId;
            }

            if (this.main.modules.ui && typeof this.main.modules.ui.showLoading === 'function') {
                this.main.modules.ui.showLoading(null, instanceId);
            }
            
            let currentPage = parseInt($container.data('current-page')) || 1;
            let maxPages = parseInt($container.data('max-pages')) || 1;
            let perPage = parseInt($container.data('posts-per-page')) || 12;
            let foundPosts = parseInt($container.data('found-posts')) || 0;
            
            // Try to get foundPosts from counter element if needed
            if (foundPosts === 0) {
                const totalCountSpan = $container.find('.hvnly-property-load-more-info span:last-child');
                if (totalCountSpan.length) {
                    const totalText = totalCountSpan.text();
                    const parsedTotal = parseInt(totalText);
                    if (!isNaN(parsedTotal) && parsedTotal > 0) {
                        foundPosts = parsedTotal;
                        $container.data('found-posts', foundPosts);
                        $container.attr('data-found-posts', foundPosts);
                    }
                }
            }
            
            // Recalculate maxPages if needed
            if (maxPages === 1 && foundPosts > perPage) {
                maxPages = Math.ceil(foundPosts / perPage);
                $container.data('max-pages', maxPages);
                $container.attr('data-max-pages', maxPages);
            }
            
            if (currentPage >= maxPages) {
                if (this.main.modules.ui && typeof this.main.modules.ui.hideLoading === 'function') {
                    this.main.modules.ui.hideLoading(instanceId);
                }
                return;
            }
            
            const newPage = currentPage + 1;
            
            $container.data('current-page', newPage);
            $container.attr('data-current-page', newPage);
            
            this.main.currentPage = newPage;
            this.main.maxPages = maxPages;
            
            this.main.performAjaxSearch(instanceId);
        }
        
        loadMore(instanceId = null) {
            if (this.main.isLoading) {
                return;
            }

            const $container = this.main.modules.utils.resolveLoadMoreContainer(instanceId);
            
            if ($container.length) {
                this.loadMoreWithContainer($container, instanceId);
            }
        }
        
        updatePaginationDisplay(data) {
            const instanceId = data.instance_id || (this.main.modules.search ? this.main.modules.search.currentInstanceId : null);

            if (window.HvnlyDom && typeof window.HvnlyDom.syncListingState === 'function') {
                window.HvnlyDom.syncListingState(data);
            }

            this.main.maxPages = data.max_pages || 1;
            this.main.currentPage = data.current_page || 1;
            
            const $targetContainer = this.main.modules.utils.resolveLoadMoreContainer(instanceId);
            
            if ($targetContainer.length) {
                // CRITICAL FIX: Update container data with server values
                $targetContainer.data('max-pages', data.max_pages);
                $targetContainer.data('found-posts', data.found_posts);
                $targetContainer.data('current-page', data.current_page);
                $targetContainer.data('posts-per-page', data.posts_per_page || $targetContainer.data('posts-per-page'));
                
                // Update HTML attributes
                $targetContainer.attr('data-max-pages', data.max_pages);
                $targetContainer.attr('data-found-posts', data.found_posts);
                $targetContainer.attr('data-current-page', data.current_page);
                
                this.updateLoadMoreSectionForContainer($targetContainer, data);
            }
            
            this.updateCounts(data);
        }
        
        updateLoadMoreSectionForContainer($container, data) {
            if (!$container.length) return;
            
            // Use the data from the container or from the response
            const currentPage = parseInt($container.data('current-page')) || data.current_page || 1;
            const maxPages = data.max_pages || 1;
            
            const shouldShowLoadMore = currentPage < maxPages;
            
            if (shouldShowLoadMore) {
                $container.show();
            } else {
                $container.hide();
            }
        }
        
        shouldShowPagination(data) {
            const foundPosts = data.found_posts || 0;
            const maxPages = data.max_pages || 1;
            const currentPostCount = data.post_count || 0;
            
            if (maxPages <= 1) {
                return false;
            }
            
            if (foundPosts <= this.main.postsPerPage) {
                return false;
            }
            
            if (this.main.currentPage === 1 && currentPostCount === this.main.postsPerPage && foundPosts === this.main.postsPerPage) {
                return false;
            }
            
            return true;
        }
        
        updateCounts(data) {
            const instanceId = data.instance_id || (this.main.modules.search ? this.main.modules.search.currentInstanceId : null);
            
            // Try instance-specific counters first
            if (instanceId) {
                const loadedCountId = `#loadedCount-${instanceId}`;
                const totalCountId = `#totalCount-${instanceId}`;
                
                let $loadedCount = $(loadedCountId);
                let $totalCount = $(totalCountId);
                
                // If not found by ID, try within container
                if (!$loadedCount.length) {
                    const $container = this.main.modules.utils.resolveLoadMoreContainer(instanceId);
                    if ($container.length) {
                        $loadedCount = $container.find('.hvnly-property-load-more-info span:first-child');
                        $totalCount = $container.find('.hvnly-property-load-more-info span:last-child');
                    }
                }
                
                if ($loadedCount.length && $totalCount.length) {
                    this.updateCounterElements($loadedCount, $totalCount, data);
                    return;
                }
            }
            
            // Fallback to default counters
            const $loadedCount = $('#loadedCount');
            const $totalCount = $('#totalCount');
            
            if ($loadedCount.length && $totalCount.length) {
                this.updateCounterElements($loadedCount, $totalCount, data);
            } else {
                const $anyContainer = $('.hvnly-property-load-more-container:visible').first();
                if ($anyContainer.length) {
                    const $containerLoadedCount = $anyContainer.find('.hvnly-property-load-more-info span:first-child');
                    const $containerTotalCount = $anyContainer.find('.hvnly-property-load-more-info span:last-child');
                    if ($containerLoadedCount.length && $containerTotalCount.length) {
                        this.updateCounterElements($containerLoadedCount, $containerTotalCount, data);
                    }
                }
            }
        }
        
        updateCounterElements($loadedCount, $totalCount, data) {
            if ($loadedCount.length && data.found_posts) {
                let cumulativeCount;
                const currentPage = data.current_page || 1;
                const perPage = data.posts_per_page || this.main.postsPerPage;
                const isLoadMore = this.main.modules.search && this.main.modules.search.isLoadMore;
                
                if (isLoadMore) {
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
        
        updateContainerData(containerId, data) {
            const $container = $(`#${containerId}, .hvnly-property-load-more-container[data-instance-id="${containerId}"]`);
            if ($container.length) {
                $container.data('current-page', data.current_page);
                $container.data('max-pages', data.max_pages);
                $container.data('found-posts', data.found_posts);
                $container.data('posts-per-page', data.posts_per_page);
                
                $container.attr('data-current-page', data.current_page);
                $container.attr('data-max-pages', data.max_pages);
                $container.attr('data-found-posts', data.found_posts);
            }
        }
    }

    window.HavenlyticsPagination = HavenlyticsPagination;

})(jQuery);