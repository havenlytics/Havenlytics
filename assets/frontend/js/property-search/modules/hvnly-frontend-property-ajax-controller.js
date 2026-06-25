/**
 * Havenlytics AJAX Main Controller 
 * 
 * @package     Havenlytics
 * @version     2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsAJAX {
        constructor() {

            this.ajaxUrl = window.hvnly_PROPERTY_ajax?.ajax_url || window.ajaxurl;
            this.nonce = window.hvnly_PROPERTY_ajax?.nonce || '';
            this.currentPage = parseInt(window.hvnly_PROPERTY_ajax?.current_page) || 1;
            this.maxPages = parseInt(window.hvnly_PROPERTY_ajax?.max_pages) || 1;
            this.postsPerPage = parseInt(window.hvnly_PROPERTY_ajax?.per_page) || 12;
            this.isLoading = false;
            
            // Initialize modules in correct order
            this.modules = {};
            this.initModules();
            this.init();
        }
        
        initModules() {
            // Initialize modules in correct dependency order
            this.modules.utils = new HavenlyticsUtils(this);
            this.modules.ui = new HavenlyticsUI(this);
            this.modules.url = new HavenlyticsURL(this);
            this.modules.pagination = new HavenlyticsPagination(this);
            this.modules.filters = new HavenlyticsFilters(this);
            this.modules.search = new HavenlyticsSearch(this);
        }
        
        init() {
            // Initialize all modules after they're all created
            Object.values(this.modules).forEach(module => {
                if (typeof module.init === 'function') {
                    module.init();
                }
            });

            // Set default active view
            this.setDefaultActiveView();
        }
        
        setDefaultActiveView() {
            // Get view type from URL or default to 'grid'
            const urlParams = new URLSearchParams(window.location.search);
            const urlViewType = urlParams.get('view_type');
            const defaultViewType = urlViewType || 'grid';
            
            // Set the active view button
            $(`.hvnly-property-view-btn[data-view="${defaultViewType}"]`).addClass('active');
            
            // Update hidden input
            $('#view-type-input').val(defaultViewType);
        }
        
        // Main method to trigger search
        performAjaxSearch() {
            if (this.isLoading) return;
            
            //   Ensure search module knows this is not a load more operation
            // unless explicitly set by the loadMore method
            if (this.modules.search && !this.modules.search.isLoadMore) {
                // For regular searches and pagination clicks, reset any existing load more flag
                this.modules.search.isLoadMore = false;
            }
            
            this.modules.search.performAjaxSearch();
        }
        
        // Main method to handle filter changes
        handleFilterChange() {
            this.currentPage = 1;
            //   Reset load more flag for filter changes
            if (this.modules.search) {
                this.modules.search.isLoadMore = false;
            }
            this.performAjaxSearch();
        }
        
        // Main method to handle search
        handleSearch() {
            this.currentPage = 1;
            //   Reset load more flag for new searches
            if (this.modules.search) {
                this.modules.search.isLoadMore = false;
            }
            this.performAjaxSearch();
        }
        
        // Public method for external access
        reinitializeFrontend() {
            setTimeout(() => {
                if (window.havenlyticsFrontend) {
                    window.havenlyticsFrontend.initPropertyGalleryCarousels();
                    window.havenlyticsFrontend.initFavoriteButtons();
                    window.havenlyticsFrontend.initMultiSelectDropdowns();
                    window.havenlyticsFrontend.initCheckboxes();
                }
            }, 300);
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        window.havenlyticsAJAX = new HavenlyticsAJAX();
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function(event) {
            if (event.state && event.state.path) {
                window.location.href = event.state.path;
            }
        });
    });

})(jQuery);