/**
 * Havenlytics Widgets JavaScript
 *
 * @package Havenlytics
 * @since 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize widget functionality
     */
    function initWidgets() {
        // Lazy load images
        initLazyLoading();
        
        // Initialize any interactive elements
        initInteractiveElements();
    }

    /**
     * Initialize lazy loading for widget images
     */
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.add('hvnly-loaded');
                        }
                        imageObserver.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            document.querySelectorAll('.hvnly-featured-thumb img, .hvnly-related-thumb img').forEach(img => {
                if (img.dataset.src) {
                    imageObserver.observe(img);
                }
            });
        } else {
            // Fallback for older browsers
            $('.hvnly-featured-thumb img, .hvnly-related-thumb img').each(function() {
                if (this.dataset.src) {
                    this.src = this.dataset.src;
                }
            });
        }
    }

    /**
     * Initialize interactive elements
     */
    function initInteractiveElements() {
        // Add hover effects
        $('.hvnly-featured-item, .hvnly-related-item').on('mouseenter', function() {
            $(this).addClass('hvnly-hover');
        }).on('mouseleave', function() {
            $(this).removeClass('hvnly-hover');
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initWidgets();
    });

    // Reinitialize after AJAX content load
    $(document).on('hvnly_widgets_loaded', function() {
        initWidgets();
    });

})(jQuery);