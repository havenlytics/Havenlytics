/**
 * Havenlytics Property Image Optimizer
 * Handles progressive image loading, blur removal, and performance optimization
 * 
 * @package     Havenlytics
 * @version     2.0.0
 */

(function($) {
    'use strict';

    class HavenlyticsPropertyImageOptimizer {
        constructor() {
            this.initialized = false;
            this.imageObserver = null;
            this.mutationObserver = null;
            this.carousels = new Map();
            this.imagesProcessed = new Set();
            this.init();
        }
        
        /**
         * Initialize the image optimizer system
         */
        init() {
            if (this.initialized) return;
            
            this.initializeImageLoadingSystem();
            this.initializePropertyGalleryCarousels();
            this.initializeEventListeners();
            this.setupDynamicContentObserver();
            
            this.initialized = true;
            
            // Process initial images immediately for fast-loading images
            this.processAllImages();
        }
        
        /**
         * Initialize image loading and blur removal system
         */
        initializeImageLoadingSystem() {
            // Define image selectors for property-related images
            this.imageSelectors = [
                '.hvnly-property-grid-list-thumb img',
                '.hvnly-property-carousel-slide img',
                '.hvnly-property-gallery img',
                '.hvnly-property-thumbnail img',
                '.hvnly-property-featured-image img',
                '.hvnly-property-main-image img',
                '.hvnly-property-agent-avatar img',
                '.hvnly-property-logo img',
                '.hvnly-property-badge img'
            ].join(', ');
        }
        
        /**
         * Process all images on the page
         */
        processAllImages() {
            const images = document.querySelectorAll(this.imageSelectors);
            
            images.forEach(img => {
                // Process immediately with zero delay
                this.optimizeImageLoading(img);
            });
            
            // Also check for background images
            this.processBackgroundImages();
        }
        
        /**
         * Optimize individual image loading - UPDATED FOR IMMEDIATE BLUR REMOVAL
         * @param {HTMLImageElement} img - The image element to optimize
         */
        optimizeImageLoading(img) {
            // Skip if already processed
            if (this.imagesProcessed.has(img) || img.classList.contains('hvnly-optimized')) {
                return;
            }
            
            // Mark as processed immediately
            this.imagesProcessed.add(img);
            img.classList.add('hvnly-optimized');
            
            // Add loading class to parent for CSS effects
            const parent = this.getImageParentContainer(img);
            if (parent) {
                parent.classList.add('hvnly-image-loading');
            }
            
            // CRITICAL FIX: Multiple immediate checks for loaded state
            const checkImageLoaded = () => {
                if (img.complete && img.naturalHeight !== 0) {
                    // Image already loaded AND valid
                    this.markImageAsOptimized(img);
                    return true;
                }
                return false;
            };
            
            // Check immediately
            if (!checkImageLoaded()) {
                // Setup load and error handlers
                const onLoad = () => {
                    this.markImageAsOptimized(img);
                };
                
                const onError = () => {
                    this.handleImageError(img);
                };
                
                // Add listeners with immediate cleanup
                img.addEventListener('load', onLoad, { once: true });
                img.addEventListener('error', onError, { once: true });
                
                // CRITICAL: Multiple checks for fast-loading images
                const checkAgain = () => {
                    if (!checkImageLoaded()) {
                        // Check one more time after a microtask
                        requestAnimationFrame(() => {
                            if (!checkImageLoaded()) {
                                // Final check after a short delay
                                setTimeout(() => {
                                    checkImageLoaded();
                                }, 50);
                            }
                        });
                    }
                };
                
                // Check after microtask
                requestAnimationFrame(checkAgain);
                
                // Handle lazy loading
                this.setupLazyLoading(img);
                
                // Add performance monitoring
                this.addPerformanceMonitoring(img);
            }
        }
        
        /**
         * Mark image as fully loaded and optimized - UPDATED FOR IMMEDIATE BLUR REMOVAL
         * @param {HTMLImageElement} img - The image element
         */
        markImageAsOptimized(img) {
            // CRITICAL FIX: Force remove all blur-related classes immediately
            img.classList.remove('hvnly-loading', 'hvnly-lazy-loading');
            
            // CRITICAL FIX: Force add loaded class with higher specificity
            img.classList.add('hvnly-loaded');
            
            // Set data attribute as backup for CSS targeting
            img.setAttribute('data-loaded', 'true');
            
            // Mark parent container
            const parent = this.getImageParentContainer(img);
            if (parent) {
                parent.classList.remove('hvnly-image-loading');
                parent.classList.add('hvnly-image-loaded');
                
                // CRITICAL FIX: Force immediate style recalculation
                void parent.offsetWidth;
                
                // Add animation class for smooth transition
                parent.classList.add('hvnly-image-visible');
                
                // Dispatch custom event
                const event = new CustomEvent('hvnly-image-optimized', {
                    detail: { 
                        image: img,
                        parent: parent,
                        loadTime: performance.now()
                    }
                });
                parent.dispatchEvent(event);
            }
            
            // Add to optimized images log
            this.logImageOptimization(img);
            
            // Trigger any callbacks
            this.triggerImageCallbacks(img);
            
            // CRITICAL FIX: Force immediate style recalculation
            this.forceStyleRecalculation(img);
            
            // CRITICAL FIX: Double-check blur removal after a short delay
            setTimeout(() => {
                this.ensureBlurRemoved(img);
            }, 10);
        }
        
        /**
         * CRITICAL FIX: New method to ensure blur is definitely removed
         */
        ensureBlurRemoved(img) {
            // Force remove all blur-related classes
            img.classList.add('hvnly-loaded');
            img.setAttribute('data-loaded', 'true');
            
            // Force style update
            img.style.filter = 'blur(0)';
            img.style.opacity = '1';
            
            // Check parent container
            const parent = this.getImageParentContainer(img);
            if (parent) {
                parent.classList.add('hvnly-image-loaded');
                parent.classList.remove('hvnly-image-loading');
            }
        }
        
        /**
         * Force style recalculation to ensure CSS updates immediately
         */
        forceStyleRecalculation(img) {
            // Multiple methods to force CSS update
            void img.offsetWidth;
            getComputedStyle(img).getPropertyValue('filter');
            
            // Also force parent recalculation
            const parent = this.getImageParentContainer(img);
            if (parent) {
                void parent.offsetWidth;
                getComputedStyle(parent).getPropertyValue('opacity');
            }
        }
        
        /**
         * Handle image loading errors
         * @param {HTMLImageElement} img - The failed image
         */
        handleImageError(img) {
            // Add error class for styling
            img.classList.add('hvnly-image-error');
            img.classList.remove('hvnly-loading', 'hvnly-lazy-loading');
            
            const parent = this.getImageParentContainer(img);
            if (parent) {
                parent.classList.remove('hvnly-image-loading');
                parent.classList.add('hvnly-image-error');
                
                // Try fallback image if available
                this.tryFallbackImage(img, parent);
            }
            
            // Even on error, remove blur effect
            img.classList.add('hvnly-loaded');
            img.setAttribute('data-loaded', 'true');
        }
        
        /**
         * Try loading a fallback image
         */
        tryFallbackImage(img, parent) {
            const fallbackSrc = img.dataset.fallbackSrc || 
                               parent.dataset.fallbackSrc || 
                               this.getDefaultFallbackImage();
            
            if (fallbackSrc && fallbackSrc !== img.src) {
                setTimeout(() => {
                    // Reset classes before trying fallback
                    img.classList.remove('hvnly-image-error');
                    parent.classList.remove('hvnly-image-error');
                    
                    // Try fallback
                    img.src = fallbackSrc;
                }, 1000);
            }
        }
        
        /**
         * Get default fallback image
         */
        getDefaultFallbackImage() {
            return window.hvnlyPropertySettings?.fallbackImage || 
                   '/wp-content/plugins/havenlytics/assets/images/placeholder.jpg';
        }
        
        /**
         * Setup lazy loading for images
         */
        setupLazyLoading(img) {
            if (img.loading === 'lazy' || img.dataset.lazy === 'true') {
                img.classList.add('hvnly-lazy-loading');
                
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const lazyImage = entry.target;
                            
                            // Start loading
                            if (lazyImage.dataset.src && !lazyImage.src) {
                                lazyImage.src = lazyImage.dataset.src;
                                lazyImage.classList.add('hvnly-lazy-loading-active');
                            }
                            
                            observer.unobserve(lazyImage);
                        }
                    });
                }, {
                    rootMargin: '100px 0px',
                    threshold: 0.01
                });
                
                observer.observe(img);
            }
        }
        
        /**
         * Get parent container for image
         */
        getImageParentContainer(img) {
            return img.closest(
                '.hvnly-property-grid-list-thumb, ' +
                '.hvnly-property-carousel-slide, ' +
                '.hvnly-property-gallery-item, ' +
                '.hvnly-property-thumbnail, ' +
                '.hvnly-property-image-container'
            );
        }
        
        /**
         * Initialize property gallery carousels with optimization
         */
        initializePropertyGalleryCarousels() {
            const carousels = document.querySelectorAll('.hvnly-property-carousel-gallery');
            
            carousels.forEach((carousel, index) => {
                if (carousel.hasAttribute('data-optimized')) {
                    return;
                }
                
                this.optimizeCarousel(carousel);
                carousel.setAttribute('data-optimized', 'true');
            });
        }
        
        /**
         * Optimize individual carousel
         */
        optimizeCarousel(carousel) {
            const track = carousel.querySelector('.hvnly-property-carousel-track');
            const slides = carousel.querySelectorAll('.hvnly-property-carousel-slide');
            const prevBtn = carousel.querySelector('.hvnly-property-carousel-prev-btn');
            const nextBtn = carousel.querySelector('.hvnly-property-carousel-next-btn');
            const indicators = carousel.querySelectorAll('.hvnly-property-carousel-indicator');

            if (!track || slides.length === 0) return;

            // Create carousel state object
            const carouselState = {
                currentIndex: 0,
                totalSlides: slides.length,
                track: track,
                slides: slides,
                indicators: indicators,
                isDragging: false,
                startX: 0
            };
            
            // Store carousel state with unique ID
            const carouselId = 'carousel-' + Math.random().toString(36).substr(2, 9);
            carousel.dataset.carouselId = carouselId;
            this.carousels.set(carouselId, carouselState);

            // CRITICAL FIX: Optimize all slide images IMMEDIATELY
            slides.forEach(slide => {
                const img = slide.querySelector('img');
                if (img) {
                    // Process immediately without delay
                    this.optimizeImageLoading(img);
                    
                    // CRITICAL FIX: Force immediate blur removal for carousel images
                    setTimeout(() => {
                        this.ensureBlurRemoved(img);
                    }, 0);
                }
            });

            const updateCarousel = (state) => {
                state.track.style.transform = `translateX(-${state.currentIndex * 100}%)`;
                
                // Update indicators
                state.indicators.forEach((indicator, idx) => {
                    indicator.classList.toggle('hvnly-property-carousel-active', idx === state.currentIndex);
                });
                
                // Preload adjacent images
                this.preloadAdjacentImages(state.slides, state.currentIndex, state.totalSlides);
                
                // Mark visible slide
                this.markVisibleSlide(state.slides[state.currentIndex]);
            };

            // Navigation functions
            const nextSlide = (state) => {
                state.currentIndex = (state.currentIndex + 1) % state.totalSlides;
                updateCarousel(state);
            };

            const prevSlide = (state) => {
                state.currentIndex = (state.currentIndex - 1 + state.totalSlides) % state.totalSlides;
                updateCarousel(state);
            };

            // Setup navigation
            if (nextBtn) {
                nextBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    nextSlide(carouselState);
                });
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    prevSlide(carouselState);
                });
            }

            // Setup indicators
            indicators.forEach((indicator, idx) => {
                indicator.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    carouselState.currentIndex = idx;
                    updateCarousel(carouselState);
                });
            });

            // Setup swipe/touch
            this.setupCarouselSwipe(carousel, carouselState, nextSlide, prevSlide);

            // Initial update
            updateCarousel(carouselState);
            
            // Setup autoplay if enabled
            this.setupCarouselAutoplay(carousel, carouselState, () => nextSlide(carouselState));
        }
        
        /**
         * Preload adjacent images for smooth transitions
         */
        preloadAdjacentImages(slides, currentIndex, totalSlides) {
            const preloadIndexes = [
                (currentIndex - 1 + totalSlides) % totalSlides,
                (currentIndex + 1) % totalSlides
            ];
            
            preloadIndexes.forEach(index => {
                const slide = slides[index];
                const img = slide?.querySelector('img');
                if (img && !img.classList.contains('hvnly-loaded')) {
                    // Trigger load if not already loading
                    if (img.dataset.src && !img.src) {
                        img.src = img.dataset.src;
                    }
                    
                    // CRITICAL FIX: Ensure blur is removed for preloaded images
                    img.onload = () => {
                        this.ensureBlurRemoved(img);
                    };
                }
            });
        }
        
        /**
         * Mark visible slide
         */
        markVisibleSlide(slide) {
            slide.classList.add('hvnly-slide-visible');
            
            // Ensure image is optimized
            const img = slide.querySelector('img');
            if (img) {
                this.optimizeImageLoading(img);
                
                // CRITICAL FIX: Force immediate blur removal for visible slides
                setTimeout(() => {
                    this.ensureBlurRemoved(img);
                }, 0);
            }
        }
        
        /**
         * Setup carousel swipe functionality
         */
        setupCarouselSwipe(carousel, state, nextSlide, prevSlide) {
            const handleStart = (e) => {
                state.startX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
                state.isDragging = true;
                state.track.style.transition = 'none';
            };

            const handleMove = (e) => {
                if (!state.isDragging) return;
                
                const currentX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
                const diff = state.startX - currentX;
                
                state.track.style.transform = `translateX(calc(-${state.currentIndex * 100}% - ${diff}px))`;
            };

            const handleEnd = (e) => {
                if (!state.isDragging) return;
                
                const endX = e.type === 'touchend' ? e.changedTouches[0].clientX : e.clientX;
                const diff = state.startX - endX;
                const swipeThreshold = 50;
                
                state.track.style.transition = 'transform 0.3s ease';
                
                if (Math.abs(diff) > swipeThreshold) {
                    if (diff > 0) {
                        nextSlide(state);
                    } else {
                        prevSlide(state);
                    }
                } else {
                    // Return to original position
                    state.track.style.transform = `translateX(-${state.currentIndex * 100}%)`;
                }
                
                state.isDragging = false;
            };

            // Mouse events
            carousel.addEventListener('mousedown', handleStart);
            
            // Use document-level listeners with carousel reference
            const handleMoveWithState = (e) => {
                if (state.isDragging) {
                    handleMove(e);
                }
            };
            
            const handleEndWithState = (e) => {
                if (state.isDragging) {
                    handleEnd(e);
                    state.isDragging = false;
                }
            };
            
            document.addEventListener('mousemove', handleMoveWithState);
            document.addEventListener('mouseup', handleEndWithState);

            // Touch events
            carousel.addEventListener('touchstart', handleStart, { passive: true });
            document.addEventListener('touchmove', handleMoveWithState, { passive: true });
            document.addEventListener('touchend', handleEndWithState);
            
            // Store cleanup references
            state.cleanupHandlers = {
                mousemove: handleMoveWithState,
                mouseup: handleEndWithState,
                touchmove: handleMoveWithState,
                touchend: handleEndWithState
            };
        }
        
        /**
         * Setup carousel autoplay
         */
        setupCarouselAutoplay(carousel, state, nextSlideCallback) {
            const autoplayEnabled = carousel.dataset.autoplay !== 'false';
            const autoplaySpeed = parseInt(carousel.dataset.autoplaySpeed) || 5000;
            
            if (!autoplayEnabled) return;
            
            let autoplayInterval = setInterval(() => {
                nextSlideCallback();
            }, autoplaySpeed);
            
            state.autoplayInterval = autoplayInterval;

            // Pause on hover
            carousel.addEventListener('mouseenter', () => {
                clearInterval(autoplayInterval);
            });

            carousel.addEventListener('mouseleave', () => {
                autoplayInterval = setInterval(() => {
                    nextSlideCallback();
                }, autoplaySpeed);
                state.autoplayInterval = autoplayInterval;
            });
        }
        
        /**
         * Setup observer for dynamic content
         */
        setupDynamicContentObserver() {
            this.mutationObserver = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.addedNodes.length) {
                        mutation.addedNodes.forEach((node) => {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                // Process images in new content
                                const images = node.querySelectorAll(this.imageSelectors);
                                images.forEach(img => {
                                    this.optimizeImageLoading(img);
                                    
                                    // CRITICAL FIX: Force immediate blur removal for new images
                                    setTimeout(() => {
                                        this.ensureBlurRemoved(img);
                                    }, 0);
                                });
                                
                                // Check for new carousels
                                const newCarousels = node.querySelectorAll('.hvnly-property-carousel-gallery:not([data-optimized])');
                                newCarousels.forEach(carousel => {
                                    this.optimizeCarousel(carousel);
                                    carousel.setAttribute('data-optimized', 'true');
                                });
                                
                                // Check if node itself is an image
                                if (node.tagName === 'IMG' && node.classList.contains('hvnly-property')) {
                                    this.optimizeImageLoading(node);
                                    
                                    // CRITICAL FIX: Force immediate blur removal
                                    setTimeout(() => {
                                        this.ensureBlurRemoved(node);
                                    }, 0);
                                }
                            }
                        });
                    }
                });
            });
            
            // Start observing
            this.mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        
        /**
         * Initialize event listeners
         */
        initializeEventListeners() {
            // Listen for AJAX/SPA updates
            $(document).on(
                'hvnly-properties-updated hvnly-gallery-updated hvnly-ajax-complete',
                () => {
                    // Refresh immediately for fast updates
                    this.refresh();
                }
            );
            
            // Listen for image optimization events
            document.addEventListener('hvnly-image-optimized', (e) => {
                this.onImageOptimized(e.detail);
            });
            
            // Window resize with debounce
            window.addEventListener('resize', this.debounce(() => {
                this.handleResponsiveImages();
            }, 250));
            
            // Add a global force refresh for debugging
            window.addEventListener('hvnly-force-image-refresh', () => {
                this.forceRefreshAllImages();
            });
        }
        
        /**
         * Force refresh all images (debugging helper)
         */
        forceRefreshAllImages() {
            // Remove all loaded classes
            document.querySelectorAll('.hvnly-loaded').forEach(el => {
                el.classList.remove('hvnly-loaded');
            });
            
            document.querySelectorAll('.hvnly-image-loaded').forEach(el => {
                el.classList.remove('hvnly-image-loaded');
            });
            
            // Reset processed images
            this.imagesProcessed.clear();
            
            // Reprocess all images
            this.processAllImages();
        }
        
        /**
         * Handle responsive image switching
         */
        handleResponsiveImages() {
            const viewportWidth = window.innerWidth;
            const images = document.querySelectorAll('img[data-srcset]');
            
            images.forEach(img => {
                if (img.dataset.srcset) {
                    const sources = img.dataset.srcset.split(',');
                    let bestSrc = img.src;
                    let bestWidth = 0;
                    
                    sources.forEach(source => {
                        const [url, width] = source.trim().split(' ');
                        const cleanWidth = parseInt(width);
                        
                        if (cleanWidth <= viewportWidth && cleanWidth > bestWidth) {
                            bestWidth = cleanWidth;
                            bestSrc = url;
                        }
                    });
                    
                    if (bestSrc !== img.src) {
                        this.switchImageSource(img, bestSrc);
                    }
                }
            });
        }
        
        /**
         * Switch image source with optimization
         */
        switchImageSource(img, newSrc) {
            const newImg = new Image();
            newImg.onload = () => {
                img.src = newSrc;
                this.markImageAsOptimized(img);
                // CRITICAL FIX: Ensure blur is removed for switched images
                this.ensureBlurRemoved(img);
            };
            newImg.onerror = () => {
                this.handleImageError(img);
            };
            newImg.src = newSrc;
        }
        
        /**
         * Process background images
         */
        processBackgroundImages() {
            const elements = document.querySelectorAll('[data-bg-image]');
            
            elements.forEach(el => {
                const bgImage = el.dataset.bgImage;
                if (bgImage) {
                    this.preloadBackgroundImage(el, bgImage);
                }
            });
        }
        
        /**
         * Preload background image
         */
        preloadBackgroundImage(element, imageUrl) {
            const img = new Image();
            img.onload = () => {
                element.style.backgroundImage = `url(${imageUrl})`;
                element.classList.add('hvnly-bg-loaded');
            };
            img.onerror = () => {
                element.classList.add('hvnly-bg-error');
            };
            img.src = imageUrl;
        }
        
        /**
         * Add performance monitoring to images
         */
        addPerformanceMonitoring(img) {
            const startTime = performance.now();
            
            img.addEventListener('load', () => {
                const loadTime = performance.now() - startTime;
                
                // Store performance data
                img.dataset.loadTime = loadTime.toFixed(0);
            }, { once: true });
        }
        
        /**
         * Log image optimization
         */
        logImageOptimization(img) {
            if (!img) {
                return;
            }
            img.dataset.optimized = 'true';
        }
        
        /**
         * Trigger callbacks for optimized images
         */
        triggerImageCallbacks(img) {
            // Execute any registered callbacks
            if (window.hvnlyImageCallbacks && Array.isArray(window.hvnlyImageCallbacks)) {
                window.hvnlyImageCallbacks.forEach(callback => {
                    if (typeof callback === 'function') {
                        callback(img);
                    }
                });
            }
        }
        
        /**
         * Handle image optimized event
         */
        onImageOptimized(detail) {
            // Custom logic when images are optimized
            const { image, parent, loadTime } = detail;
            
            // Add animation class
            parent.classList.add('hvnly-image-animated');
            
            // CRITICAL FIX: Ensure blur is gone by double-checking
            setTimeout(() => {
                this.ensureBlurRemoved(image);
                
                if (parent.classList.contains('hvnly-image-loading')) {
                    parent.classList.remove('hvnly-image-loading');
                }
                if (!image.classList.contains('hvnly-loaded')) {
                    image.classList.add('hvnly-loaded');
                }
            }, 10);
            
            // Trigger any analytics
            if (window.hvnlyAnalytics) {
                this.trackImagePerformance(image, loadTime);
            }
        }
        
        /**
         * Track image performance for analytics
         */
        trackImagePerformance(img, loadTime) {
            // Implementation for analytics tracking
            const data = {
                event: 'image_optimized',
                src: img.src,
                loadTime: loadTime,
                timestamp: Date.now()
            };
            
            // Send to analytics if configured
            if (typeof window.hvnlyAnalytics === 'function') {
                window.hvnlyAnalytics('image_load', data);
            }
        }
        
        /**
         * Debounce function for performance
         */
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
        
        /**
         * PUBLIC METHODS
         */
        
        /**
         * Refresh and re-optimize all images
         */
        refresh() {
            this.processAllImages();
            this.initializePropertyGalleryCarousels();
            
            // CRITICAL FIX: Force immediate blur removal for all images
            setTimeout(() => {
                document.querySelectorAll(this.imageSelectors).forEach(img => {
                    this.ensureBlurRemoved(img);
                });
            }, 0);
            
            // Dispatch refresh event
            const event = new CustomEvent('hvnly-image-optimizer-refreshed');
            document.dispatchEvent(event);
        }
        
        /**
         * Preload specific images
         * @param {Array} urls - Array of image URLs to preload
         */
        preloadImages(urls) {
            urls.forEach(url => {
                const img = new Image();
                img.src = url;
                
                // Add to preloaded cache
                this.imagesProcessed.add(url);
            });
        }
        
        /**
         * Optimize specific element and its children
         * @param {HTMLElement} element - The element to optimize
         */
        optimizeElement(element) {
            const images = element.querySelectorAll(this.imageSelectors);
            images.forEach(img => {
                this.optimizeImageLoading(img);
                
                // CRITICAL FIX: Force immediate blur removal
                setTimeout(() => {
                    this.ensureBlurRemoved(img);
                }, 0);
            });
            
            const carousels = element.querySelectorAll('.hvnly-property-carousel-gallery:not([data-optimized])');
            carousels.forEach(carousel => {
                this.optimizeCarousel(carousel);
                carousel.setAttribute('data-optimized', 'true');
            });
        }
        
        /**
         * Get optimization statistics
         */
        getStats() {
            return {
                totalProcessed: this.imagesProcessed.size,
                carouselsOptimized: this.carousels.size,
                timestamp: Date.now()
            };
        }
        
        /**
         * Cleanup and destroy instance
         */
        destroy() {
            // Cleanup all carousel event listeners
            this.carousels.forEach((state, carouselId) => {
                const carousel = document.querySelector(`[data-carousel-id="${carouselId}"]`);
                if (carousel && state.cleanupHandlers) {
                    document.removeEventListener('mousemove', state.cleanupHandlers.mousemove);
                    document.removeEventListener('mouseup', state.cleanupHandlers.mouseup);
                    document.removeEventListener('touchmove', state.cleanupHandlers.touchmove);
                    document.removeEventListener('touchend', state.cleanupHandlers.touchend);
                    
                    if (state.autoplayInterval) {
                        clearInterval(state.autoplayInterval);
                    }
                }
            });
            
            if (this.mutationObserver) {
                this.mutationObserver.disconnect();
            }
            
            this.carousels.clear();
            this.imagesProcessed.clear();
            this.initialized = false;
        }
    }

    /**
     * Initialize and expose globally
     */
    $(document).ready(function() {
        // Create global instance
        window.HavenlyticsPropertyImageOptimizer = new HavenlyticsPropertyImageOptimizer();
        
        // Global helper functions
        window.hvnlyRefreshPropertyImages = function() {
            if (window.HavenlyticsPropertyImageOptimizer) {
                window.HavenlyticsPropertyImageOptimizer.refresh();
            }
        };
        
        window.hvnlyOptimizeElementImages = function(element) {
            if (window.HavenlyticsPropertyImageOptimizer && element) {
                window.HavenlyticsPropertyImageOptimizer.optimizeElement(element);
            }
        };
        
        window.hvnlyPreloadPropertyImages = function(urls) {
            if (window.HavenlyticsPropertyImageOptimizer && urls) {
                window.HavenlyticsPropertyImageOptimizer.preloadImages(urls);
            }
        };
        
        // CRITICAL FIX: Enhanced debug helper to force remove blur
        window.hvnlyForceRemoveImageBlur = function() {
            document.querySelectorAll('.hvnly-property-grid-list-thumb img, .hvnly-property-carousel-slide img').forEach(img => {
                img.classList.add('hvnly-loaded');
                img.setAttribute('data-loaded', 'true');
                img.style.filter = 'blur(0)';
                img.style.opacity = '1';
                
                const parent = img.closest('.hvnly-property-grid-list-thumb, .hvnly-property-carousel-slide');
                if (parent) {
                    parent.classList.remove('hvnly-image-loading');
                    parent.classList.add('hvnly-image-loaded');
                }
            });
        };
        
        // Compatibility with existing frontend
        if (window.havenlyticsFrontend) {
            // Extend existing frontend with image optimization
            window.havenlyticsFrontend.optimizePropertyImages = function() {
                if (window.HavenlyticsPropertyImageOptimizer) {
                    window.HavenlyticsPropertyImageOptimizer.refresh();
                }
            };
            
            // Auto-optimize after frontend initialization
            setTimeout(() => {
                if (window.HavenlyticsPropertyImageOptimizer) {
                    window.HavenlyticsPropertyImageOptimizer.processAllImages();
                    
                    // CRITICAL FIX: Force immediate blur removal after initialization
                    setTimeout(() => {
                        document.querySelectorAll('.hvnly-property-grid-list-thumb img, .hvnly-property-carousel-slide img').forEach(img => {
                            window.HavenlyticsPropertyImageOptimizer.ensureBlurRemoved(img);
                        });
                    }, 100);
                }
            }, 100);
        }
    });

    /**
     * Fallback initialization for direct script inclusion
     */
    const initHavenlyticsPropertyImageOptimizer = function() {
        if (!window.HavenlyticsPropertyImageOptimizer) {
            window.HavenlyticsPropertyImageOptimizer = new HavenlyticsPropertyImageOptimizer();
            
            // CRITICAL FIX: Force immediate blur removal after initialization
            setTimeout(() => {
                document.querySelectorAll('.hvnly-property-grid-list-thumb img, .hvnly-property-carousel-slide img').forEach(img => {
                    window.HavenlyticsPropertyImageOptimizer.ensureBlurRemoved(img);
                });
            }, 100);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHavenlyticsPropertyImageOptimizer);
    } else {
        initHavenlyticsPropertyImageOptimizer();
    }

})(jQuery);