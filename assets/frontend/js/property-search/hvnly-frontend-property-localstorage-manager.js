/**
 * Havenlytics LocalStorage View Manager
 * 
 * Manages view preference persistence using localStorage
 * 
 * @package     Havenlytics
 * @version     2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsLocalStorageManager {
        constructor() {
            this.storageKey = 'hvnly_view_type';
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.applySavedViewOnLoad();
        }
        
        bindEvents() {
            const self = this;
            
            // Save view when changed
            $(document).on('click', '.hvnly-property-view-btn', function(e) {
                const viewType = $(this).data('view');
                self.saveViewPreference(viewType);
            });
            
            // Clear on reset
            $(document).on('click', '.hvnly-property-reset-filters-btn', function() {
                self.clearViewPreference();
            });
        }
        
        saveViewPreference(viewType) {
            try {
                if (viewType === 'grid' || viewType === 'list' || viewType === 'map') {
                    localStorage.setItem(this.storageKey, viewType);
                    return true;
                }
            } catch (e) {
                // console.warn('Could not save view preference to localStorage:', e);
            }
            return false;
        }
        
        getSavedViewPreference() {
            try {
                const saved = localStorage.getItem(this.storageKey);
                if (saved && (saved === 'grid' || saved === 'list' || saved === 'map')) {
                    return saved;
                }
            } catch (e) {
                // console.warn('Could not get view preference from localStorage:', e);
            }
            return null;
        }
        
        clearViewPreference() {
            try {
                localStorage.removeItem(this.storageKey);
                return true;
            } catch (e) {
                // console.warn('Could not clear view preference from localStorage:', e);
            }
            return false;
        }
        
        applySavedViewOnLoad() {
            const savedView = this.getSavedViewPreference();
            if (savedView) {
                // Wait for DOM to be fully loaded
                $(document).ready(function() {
                    setTimeout(function() {
                        // Update the view type input
                        const viewTypeInput = $('#view-type-input');
                        if (viewTypeInput.length) {
                            viewTypeInput.val(savedView);
                        }
                        
                        // Update active button
                        $('.hvnly-property-view-btn').removeClass('active');
                        $(`.hvnly-property-view-btn[data-view="${savedView}"]`).addClass('active');
                        
                        // Apply the view if frontend is loaded
                        if (window.havenlyticsFrontend && window.havenlyticsFrontend.setView) {
                            window.havenlyticsFrontend.setView(savedView);
                        }
                    }, 200);
                });
            }
        }
        
        // Helper method to get view with fallback priority
        getViewWithFallback() {
            const saved = this.getSavedViewPreference();
            if (saved) return saved;
            
            // Check URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const urlView = urlParams.get('view_type');
            if (urlView && (urlView === 'grid' || urlView === 'list' || urlView === 'map')) {
                return urlView;
            }
            
            // Default
            return 'grid';
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        window.havenlyticsLocalStorage = new HavenlyticsLocalStorageManager();
    });

})(jQuery);