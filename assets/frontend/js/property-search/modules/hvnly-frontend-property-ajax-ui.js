/**
 * Havenlytics AJAX UI Module
 * 
 * @package     Havenlytics
 * @version     2.2.2
 */

(function($) {
    'use strict';

    class HavenlyticsUI {
        constructor(main) {
            this.main = main;
            this.loadingTimeout = null;
            this.debugMode = false;
        }
        
        init() {
            this.hvnly_bindEvents();
        }
        
        hvnly_bindEvents() {
            $(document).on('hvnly_ui_reset_loading', () => {
                this.hideLoading();
            });
        }
        
        getActiveInstanceId() {
            if (this.main.modules.search && this.main.modules.search.currentInstanceId) {
                return this.main.modules.search.currentInstanceId;
            }
            return null;
        }

        resolveInstanceId(instanceId = null) {
            if (instanceId) {
                return instanceId;
            }

            return this.getActiveInstanceId();
        }

        showLoading($targetButton = null, instanceId = null) {
            if (window.HvnlyElementorPreview && window.HvnlyElementorPreview.shouldDisableInteractiveUi && window.HvnlyElementorPreview.shouldDisableInteractiveUi()) {
                return;
            }
            
            // Clear any pending hide timeout
            if (this.loadingTimeout) {
                clearTimeout(this.loadingTimeout);
                this.loadingTimeout = null;
            }

            const resolvedInstanceId = this.resolveInstanceId(instanceId);
            
            const propertyGrid = this.main.modules.utils.resolvePropertyGrid(resolvedInstanceId);
            if (propertyGrid.length) {
                propertyGrid.addClass('hvnly-loading');
            }
            
            // Find the load more button that was clicked
            let $loadMoreBtn = ($targetButton && $targetButton.length) ? $targetButton : null;
            
            if (!$loadMoreBtn || !$loadMoreBtn.length) {
                if (resolvedInstanceId) {
                    $loadMoreBtn = $(`#hvnlyLoadMorePropertyBtn-${resolvedInstanceId}, .hvnly-property-load-more-container[data-instance-id="${resolvedInstanceId}"] .hvnly-property-load-more-btn`);
                }
            }

            if (!$loadMoreBtn || !$loadMoreBtn.length) {
                $loadMoreBtn = $('.hvnly-property-load-more-btn:focus, .hvnly-property-load-more-btn:hover');
            }
            
            if (!$loadMoreBtn.length) {
                $loadMoreBtn = $('#hvnlyLoadMorePropertyBtn');
            }
            
            if (!$loadMoreBtn.length) {
                $loadMoreBtn = $('.hvnly-property-load-more-btn:visible').first();
            }
            
            if ($loadMoreBtn.length) {
                // Add loading class and disable button - using existing CSS
                $loadMoreBtn.addClass('loading');
                $loadMoreBtn.prop('disabled', true);
                
                // Store original HTML to restore later
                if (!$loadMoreBtn.data('original-html')) {
                    $loadMoreBtn.data('original-html', $loadMoreBtn.html());
                }
                
                // Use existing spinner from your CSS (the .hvnly-load-more-spinner class)
                const loadingText = $loadMoreBtn.closest('.hvnly-property-load-more-container').data('loading-text')
                    || (window.hvnly_PROPERTY_ajax && window.hvnly_PROPERTY_ajax.loading_text)
                    || '';
                $loadMoreBtn.html('<span class="hvnly-load-more-spinner"></span> ' + loadingText);
            }
        }
        
        hideLoading(instanceId = null) {
            const resolvedInstanceId = this.resolveInstanceId(instanceId);
            const propertyGrid = this.main.modules.utils.resolvePropertyGrid(resolvedInstanceId);
            if (propertyGrid.length) {
                propertyGrid.removeClass('hvnly-loading');
            }
            
            // Restore all load more buttons
            $('.hvnly-property-load-more-btn').each(function() {
                const $btn = $(this);
                const originalHtml = $btn.data('original-html');
                
                if (originalHtml) {
                    $btn.removeClass('loading');
                    $btn.prop('disabled', false);
                    $btn.html(originalHtml);
                    $btn.removeData('original-html');
                } else {
                    // Fallback: restore based on data attribute
                    const loadMoreContainer = $btn.closest('.hvnly-property-load-more-container');
                    const loadMoreText = loadMoreContainer.data('load-more-text') || (window.hvnly_PROPERTY_ajax && window.hvnly_PROPERTY_ajax.load_more_text) || '';
                    $btn.removeClass('loading');
                    $btn.prop('disabled', false);
                    $btn.html('<span class="hvnly-property-load-more-text">' + loadMoreText + '</span><i class="fas fa-plus"></i>');
                }
            });
        }
        
        showError(message) {
            // Remove existing error messages
            $('.hvnly-ajax-error').remove();
            
            // Don't show error if it's just a cancelled request
            if (message && message === 'abort') {
                return;
            }
            
            const errorDiv = $('<div class="hvnly-ajax-error" role="alert">')
                .text(message || window.hvnly_PROPERTY_ajax?.error_message || window.hvnly_PROPERTY_ajax?.error_text || '');
            
            const propertyGrid = this.main.modules.utils.resolvePropertyGrid(this.getActiveInstanceId());
            if (propertyGrid.length) {
                errorDiv.insertBefore(propertyGrid);
                
                // Auto-hide error after 5 seconds
                setTimeout(() => {
                    errorDiv.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
        
        updateLoadMoreButtonText() {
            const loadMoreContainer = this.main.modules.utils.resolveLoadMoreContainer(this.getActiveInstanceId());
            if (loadMoreContainer.length) {
                const loadMoreText = loadMoreContainer.data('load-more-text') || (window.hvnly_PROPERTY_ajax && window.hvnly_PROPERTY_ajax.load_more_text) || '';
                const loadMoreBtn = $('#hvnlyLoadMorePropertyBtn');
                if (loadMoreBtn.length && !loadMoreBtn.hasClass('loading')) {
                    const originalHtml = '<span class="hvnly-property-load-more-text">' + loadMoreText + '</span><i class="fas fa-plus"></i>';
                    loadMoreBtn.data('original-html', originalHtml);
                    loadMoreBtn.html(originalHtml);
                }
            }
        }
        
        hvnly_showSuccess(message) {
            $('.hvnly-ajax-success').remove();
            
            const successDiv = $('<div class="hvnly-ajax-success" role="status">')
                .text(message || window.hvnly_PROPERTY_ajax?.success_message || window.hvnly_PROPERTY_ajax?.success_text || '')
                .css({
                    background: '#d4edda',
                    color: '#155724',
                    padding: '10px 15px',
                    margin: '10px 0',
                    border: '1px solid #c3e6cb',
                    borderRadius: '4px',
                    fontSize: '14px'
                });
            
            const propertyGrid = this.main.modules.utils.resolvePropertyGrid(this.getActiveInstanceId());
            if (propertyGrid.length) {
                successDiv.insertBefore(propertyGrid);
                setTimeout(() => successDiv.fadeOut(300, () => successDiv.remove()), 3000);
            }
        }
        
        hvnly_resetUI() {
            this.hideLoading();
            $('.hvnly-ajax-error, .hvnly-ajax-success').remove();
            
            const container = $('#hvnly-property-container');
            if (container.length) {
                container.scrollTop(0);
            }
        }
    }

    window.HavenlyticsUI = HavenlyticsUI;

})(jQuery);