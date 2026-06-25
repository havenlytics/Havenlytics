/**
 * Havenlytics Single Gallery Carousel or Thumbnail JavaScript
 * Handles image loading, carousels, and lightbox functionality for property galleries.
 * 
 * @author      Havenlytics
 * @package     Havenlytics
 * @version     2.0.0
 */

(function($) {
    'use strict';

    // Prevent multiple initializations
    if (typeof window.hvnlyGalleryInitialized !== 'undefined') {
        return;
    }
    window.hvnlyGalleryInitialized = true;

    class HavenlyticsFrontendSingleGallery {
        constructor() {
            this.version = '2.0.4';
            this.initialized = false;
            this.config = {
                debug: window.location.hostname === 'localhost' || window.location.hostname.indexOf('.local') !== -1,
                enableAutoInit: true,
                thumbnailSize: 150,
                transitionSpeed: 300, // Reduced from 800ms to 300ms for instant feel
            };
            
            this.init();
        }

        init() {
            if (this.initialized) {
                return;
            }

            $(document).ready(() => {
                try {
                    this.initModules();
                    this.bindEvents();
                    this.initialized = true;
                    
                    if (this.config.debug) {
                        // console.log('Havenlytics Gallery v' + this.version + ' initialized');
                    }
                } catch (error) {
                    // console.error('Gallery initialization error:', error);
                }
            });
        }

        initModules() {
            this.modules = {
                gallery: new HavenlyticsSingleGalleryModule(this),
                fancybox: new HavenlyticsSingleFancyboxModule(this),
            };
            this.carousels = [];
        }

        initCarousels() {
            this.carousels = [];

            if (document.getElementById('hvnlyPropertySingleCarouselTrack')) {
                const legacyCarousel = new HavenlyticsSingleCarouselModule(this);
                legacyCarousel.init();
                if (legacyCarousel.initialized) {
                    this.carousels.push(legacyCarousel);
                }
            }

            document.querySelectorAll('[data-hvnly-property-carousel]').forEach((root) => {
                const carousel = new HavenlyticsSingleCarouselModule(this, root);
                carousel.init();
                if (carousel.initialized) {
                    this.carousels.push(carousel);
                }
            });
        }

        bindEvents() {
            $(document).ready(() => {
                try {
                    this.modules.gallery.init();
                    this.modules.fancybox.init();
                    this.initCarousels();
                    this.initGalleryCards();
                    this.bindGalleryPopupEvents();
                } catch (error) {
                    if (this.config.debug) {
                        // console.warn('Gallery module warning:', error);
                    }
                }
            });
            
            // Handle window resize with debounce
            let resizeTimer;
            $(window).on('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    this.handleResize();
                }, 250);
            });
        }

        bindGalleryPopupEvents() {
            document.addEventListener('click', (e) => {
                const galleryLink = e.target.closest('.hvnly-gallery-popup-trigger');
                if (!galleryLink) return;
                
                e.preventDefault();
                
                const galleryId = galleryLink.getAttribute('data-gallery-id');
                const imageIndex = parseInt(galleryLink.getAttribute('data-image-index')) || 0;
                
                if (galleryId) {
                    this.openSpecificGallery(galleryId, imageIndex);
                }
            });
        }

        openSpecificGallery(galleryId, startIndex = 0) {
            const galleryContainer = document.getElementById(galleryId);
            if (!galleryContainer) return;
            
            const galleryItems = galleryContainer.querySelectorAll('.hvnly-property-single__gallery-item');
            const galleryData = [];
            
            galleryItems.forEach((item, index) => {
                const fullImage = item.getAttribute('data-full-image');
                const imageTitle = item.getAttribute('data-image-title');
                const imageAlt = item.getAttribute('data-image-alt');
                const thumbnailImage = item.querySelector('img')?.src || fullImage;
                
                if (fullImage) {
                    galleryData.push({
                        index: index,
                        src: fullImage,
                        thumbnail: thumbnailImage,
                        alt: imageAlt || 'Property Image',
                        title: imageTitle || 'Property Image',
                        description: imageTitle || 'Property Image'
                    });
                }
            });
            
            if (this.modules.fancybox && this.modules.fancybox.initialized && galleryData.length > 0) {
                this.modules.fancybox.openGalleryFromExternal(galleryData, startIndex);
            }
        }

        initGalleryCards() {
            const galleryCards = document.querySelectorAll('[data-gallery-card]');
            
            galleryCards.forEach(card => {
                const galleryItems = card.querySelectorAll('[data-gallery-property]');
                
                galleryItems.forEach(item => {
                    const link = item.querySelector('.hvnly-property-single__gallery-link');
                    if (link) {
                        link.addEventListener('click', (e) => {
                            e.preventDefault();
                            this.handleGalleryCardClick(item);
                        });
                    }
                });
            });
        }

        handleGalleryCardClick(clickedItem) {
            const galleryContainer = clickedItem.closest('.hvnly-property-single__gallery-field');
            if (!galleryContainer) return;
            
            const galleryItems = galleryContainer.querySelectorAll('[data-full-image]');
            
            if (galleryItems.length === 0) {
                const fallbackItems = galleryContainer.querySelectorAll('[data-image-index], [data-gallery-property]');
                if (fallbackItems.length === 0) {
                    // console.warn('No gallery items found');
                    return;
                }
                return this.handleFallbackGalleryClick(clickedItem, galleryContainer);
            }
            
            const galleryData = [];
            let clickedIndex = 0;
            
            galleryItems.forEach((item, index) => {
                const fullImage = item.getAttribute('data-full-image') || 
                                 item.querySelector('img')?.src || 
                                 item.href;
                const imageTitle = item.getAttribute('data-image-title') || 
                                  item.querySelector('img')?.alt || 
                                  'Property Image';
                const imageAlt = item.getAttribute('data-image-alt') || 
                                item.querySelector('img')?.alt || 
                                'Property Image';
                const thumbnailImage = item.querySelector('img')?.src || fullImage;
                const itemIndex = parseInt(item.getAttribute('data-image-index')) || index;
                
                if (fullImage) {
                    galleryData.push({
                        index: itemIndex,
                        src: fullImage,
                        thumbnail: thumbnailImage,
                        alt: imageAlt,
                        title: imageTitle,
                        description: imageTitle
                    });
                    
                    if (item === clickedItem || 
                        item.contains(clickedItem) || 
                        (clickedItem.getAttribute('data-full-image') === fullImage)) {
                        clickedIndex = galleryData.length - 1;
                    }
                }
            });
            
            galleryData.sort((a, b) => (a.index || 0) - (b.index || 0));
            
            galleryData.forEach((item, index) => {
                item.index = index;
            });
            
            if (this.modules.fancybox && this.modules.fancybox.initialized && galleryData.length > 0) {
                this.modules.fancybox.openGalleryFromExternal(galleryData, clickedIndex);
            }
        }

        handleFallbackGalleryClick(clickedItem, galleryContainer) {
            const galleryLinks = galleryContainer.querySelectorAll('a[href]');
            const galleryData = [];
            let clickedIndex = 0;
            
            galleryLinks.forEach((link, index) => {
                const href = link.getAttribute('href');
                if (href && (href.match(/\.(jpg|jpeg|png|gif|webp|bmp)$/i) || href.includes('wp-content/uploads'))) {
                    const img = link.querySelector('img');
                    const imageTitle = link.getAttribute('title') || 
                                     link.getAttribute('aria-label') || 
                                     img?.alt || 
                                     'Property Image';
                    const imageAlt = img?.alt || imageTitle;
                    const thumbnailImage = img?.src || href;
                    
                    galleryData.push({
                        index: index,
                        src: href,
                        thumbnail: thumbnailImage,
                        alt: imageAlt,
                        title: imageTitle,
                        description: imageTitle
                    });
                    
                    if (link === clickedItem || link.contains(clickedItem)) {
                        clickedIndex = galleryData.length - 1;
                    }
                }
            });
            
            if (this.modules.fancybox && this.modules.fancybox.initialized && galleryData.length > 0) {
                this.modules.fancybox.openGalleryFromExternal(galleryData, clickedIndex);
            }
        }

        handleResize() {
            Object.values(this.modules).forEach(module => {
                if (typeof module.handleResize === 'function') {
                    try {
                        module.handleResize();
                    } catch (error) {
                        if (this.config.debug) {
                            // console.warn('Resize handler error:', error);
                        }
                    }
                }
            });

            if (Array.isArray(this.carousels)) {
                this.carousels.forEach((carousel) => {
                    if (carousel && typeof carousel.handleResize === 'function') {
                        try {
                            carousel.handleResize();
                        } catch (error) {
                            if (this.config.debug) {
                                // console.warn('Carousel resize handler error:', error);
                            }
                        }
                    }
                });
            }
        }

        destroy() {
            Object.values(this.modules).forEach(module => {
                if (typeof module.destroy === 'function') {
                    try {
                        module.destroy();
                    } catch (error) {
                        // console.error('Module destruction error:', error);
                    }
                }
            });

            if (Array.isArray(this.carousels)) {
                this.carousels.forEach((carousel) => {
                    if (carousel && typeof carousel.destroy === 'function') {
                        try {
                            carousel.destroy();
                        } catch (error) {
                            // console.error('Carousel destruction error:', error);
                        }
                    }
                });
            }

            this.carousels = [];
            this.initialized = false;
        }
    }

    // Base Gallery Module Class
    class HavenlyticsBaseGalleryModule {
        constructor(parent) {
            this.parent = parent;
            this.initialized = false;
            this.elements = {};
        }

        log(message, type = 'log') {
            if (this.parent.config.debug) {
                // console[type]('[Havenlytics Gallery]', message);
            }
        }

        getElement(selector, required = false) {
            const element = document.querySelector(selector);
            if (required && !element) {
                throw new Error(`Required element not found: ${selector}`);
            }
            return element;
        }

        getAllElements(selector) {
            return document.querySelectorAll(selector);
        }

        safeAddEventListener(element, event, handler) {
            if (element && typeof handler === 'function') {
                element.addEventListener(event, handler);
                return () => element.removeEventListener(event, handler);
            }
            return null;
        }

        getThumbnailUrl(originalUrl) {
            if (!originalUrl) return '';
            return originalUrl.replace(/(\/\d{3,4}x\d{3,4}\/)/, `/${this.parent.config.thumbnailSize}x${this.parent.config.thumbnailSize}/`);
        }
    }

    // Gallery Module - FIXED for instant image changes
    class HavenlyticsSingleGalleryModule extends HavenlyticsBaseGalleryModule {
        constructor(parent) {
            super(parent);
            this.elements = {
                galleryMain: '#hvnlyPropertySingleGalleryMain',
                slides: '.hvnly-property-single__gallery-slide',
                indicators: '.hvnly-property-single__gallery-indicator',
                counter: '#hvnlyPropertySingleGalleryCounter',
                prevBtn: '.hvnly-property-single__gallery-btn--prev',
                nextBtn: '.hvnly-property-single__gallery-btn--next',
                thumbnailContainer: '#hvnlyPropertySingleGalleryThumbnails'
            };
            
            this.currentIndex = 0;
            this.totalSlides = 0;
            this.autoPlayInterval = null;
            this.autoPlayDelay = 5000;
            this.isHovering = false;
            this.isTransitioning = false;
            this.removeEventListeners = [];
        }

        init() {
            try {
                this.elements.galleryMain = this.getElement(this.elements.galleryMain);
                if (!this.elements.galleryMain) {
                    this.log('Gallery element not found', 'warn');
                    return;
                }

                this.elements.slides = this.getAllElements(this.elements.slides);
                this.elements.indicators = this.getAllElements(this.elements.indicators);
                this.elements.counter = this.getElement(this.elements.counter);
                this.elements.prevBtn = this.getElement(this.elements.prevBtn);
                this.elements.nextBtn = this.getElement(this.elements.nextBtn);
                this.elements.thumbnailContainer = this.getElement(this.elements.thumbnailContainer);

                this.totalSlides = this.elements.slides.length;
                
                if (this.totalSlides === 0) {
                    this.log('No gallery slides found', 'warn');
                    return;
                }

                this.createThumbnails();
                this.bindEvents();
                this.startAutoPlay();
                this.updateGallery();
                this.initialized = true;

                this.log(`Gallery initialized with ${this.totalSlides} slides`);
            } catch (error) {
                this.log(`Gallery initialization error: ${error.message}`, 'error');
            }
        }

        createThumbnails() {
            if (!this.elements.thumbnailContainer) return;

            this.elements.thumbnailContainer.innerHTML = '';
            
            this.elements.slides.forEach((slide, index) => {
                const thumb = document.createElement('div');
                thumb.className = `hvnly-property-single__thumbnail ${index === 0 ? 'hvnly-property-single__thumbnail--active' : ''}`;
                thumb.setAttribute('data-index', index);
                thumb.setAttribute('role', 'button');
                thumb.setAttribute('aria-label', `View image ${index + 1}`);
                thumb.setAttribute('tabindex', '0');
                
                const bgImage = slide.style.backgroundImage;
                if (bgImage) {
                    const imageUrl = this.extractImageUrl(bgImage);
                    const thumbUrl = this.getThumbnailUrl(imageUrl);
                    thumb.style.backgroundImage = `url('${thumbUrl}')`;
                }
                
                thumb.addEventListener('click', () => {
                    this.goToSlide(index);
                });
                
                thumb.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.goToSlide(index);
                    }
                });
                
                this.elements.thumbnailContainer.appendChild(thumb);
            });
        }

        extractImageUrl(backgroundImage) {
            return backgroundImage.replace('url("', '').replace('")', '').replace(/^'|'$/g, '');
        }

        bindEvents() {
            if (this.elements.prevBtn) {
                this.elements.prevBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.prevSlide();
                });
            }
            
            if (this.elements.nextBtn) {
                this.elements.nextBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.nextSlide();
                });
            }
            
            this.elements.indicators.forEach(indicator => {
                indicator.addEventListener('click', (e) => {
                    const index = parseInt(e.target.getAttribute('data-index'));
                    if (!isNaN(index)) {
                        this.goToSlide(index);
                    }
                });
            });
            
            if (this.elements.galleryMain) {
                this.elements.galleryMain.addEventListener('click', (e) => {
                    if (e.target.closest('.hvnly-property-single__gallery-slide') || 
                        e.target === this.elements.galleryMain) {
                        if (this.parent.modules.fancybox) {
                            this.parent.modules.fancybox.openGallery();
                        }
                    }
                });
                
                this.elements.galleryMain.addEventListener('mouseenter', () => {
                    this.isHovering = true;
                    this.stopAutoPlay();
                });
                
                this.elements.galleryMain.addEventListener('mouseleave', () => {
                    this.isHovering = false;
                    setTimeout(() => {
                        if (!this.isHovering) {
                            this.startAutoPlay();
                        }
                    }, 1000);
                });
            }
            
            document.addEventListener('keydown', (e) => {
                if (document.activeElement.closest('.hvnly-property-single__gallery')) {
                    if (e.key === 'ArrowLeft') {
                        e.preventDefault();
                        this.prevSlide();
                    } else if (e.key === 'ArrowRight') {
                        e.preventDefault();
                        this.nextSlide();
                    }
                }
            });
        }

        updateGallery() {
            if (this.isTransitioning || !this.elements.slides.length) return;
            this.isTransitioning = true;
            
            this.currentIndex = Math.max(0, Math.min(this.currentIndex, this.totalSlides - 1));
            
            // INSTANT update - remove all animations
            this.elements.slides.forEach((slide, index) => {
                if (index === this.currentIndex) {
                    slide.style.opacity = '1';
                    slide.style.zIndex = '2';
                    slide.setAttribute('aria-hidden', 'false');
                } else {
                    slide.style.opacity = '0';
                    slide.style.zIndex = '1';
                    slide.setAttribute('aria-hidden', 'true');
                }
            });
            
            // Update indicators instantly
            if (this.elements.indicators) {
                this.elements.indicators.forEach((indicator, index) => {
                    if (index === this.currentIndex) {
                        indicator.classList.add('hvnly-property-single__gallery-indicator--active');
                        indicator.setAttribute('aria-current', 'true');
                    } else {
                        indicator.classList.remove('hvnly-property-single__gallery-indicator--active');
                        indicator.setAttribute('aria-current', 'false');
                    }
                });
            }
            
            // Update counter instantly
            if (this.elements.counter) {
                this.elements.counter.textContent = `${this.currentIndex + 1}/${this.totalSlides}`;
            }
            
            // Update thumbnails instantly
            if (this.elements.thumbnailContainer) {
                const thumbnails = this.elements.thumbnailContainer.querySelectorAll('.hvnly-property-single__thumbnail');
                thumbnails.forEach((thumb, index) => {
                    if (index === this.currentIndex) {
                        thumb.classList.add('hvnly-property-single__thumbnail--active');
                        thumb.setAttribute('aria-current', 'true');
                    } else {
                        thumb.classList.remove('hvnly-property-single__thumbnail--active');
                        thumb.setAttribute('aria-current', 'false');
                    }
                });
                
                // Scroll to active thumbnail
                const activeThumb = thumbnails[this.currentIndex];
                if (activeThumb && this.elements.thumbnailContainer.scrollTo) {
                    const containerWidth = this.elements.thumbnailContainer.offsetWidth;
                    const thumbLeft = activeThumb.offsetLeft;
                    const thumbWidth = activeThumb.offsetWidth;
                    
                    this.elements.thumbnailContainer.scrollTo({
                        left: thumbLeft - (containerWidth / 2) + (thumbWidth / 2),
                        behavior: 'smooth'
                    });
                }
            }
            
            this.isTransitioning = false;
        }

        prevSlide() {
            if (this.isTransitioning || this.totalSlides === 0) return;
            this.currentIndex = (this.currentIndex - 1 + this.totalSlides) % this.totalSlides;
            this.updateGallery();
            this.resetAutoPlay();
        }

        nextSlide() {
            if (this.isTransitioning || this.totalSlides === 0) return;
            this.currentIndex = (this.currentIndex + 1) % this.totalSlides;
            this.updateGallery();
            this.resetAutoPlay();
        }

        goToSlide(index) {
            if (this.isTransitioning || index === this.currentIndex || index < 0 || index >= this.totalSlides) return;
            this.currentIndex = index;
            this.updateGallery();
            this.resetAutoPlay();
        }

        startAutoPlay() {
            this.stopAutoPlay();
            if (this.totalSlides <= 1) return;
            
            this.autoPlayInterval = setInterval(() => {
                if (!this.isHovering && !this.isTransitioning && this.totalSlides > 1) {
                    this.nextSlide();
                }
            }, this.autoPlayDelay);
        }

        stopAutoPlay() {
            if (this.autoPlayInterval) {
                clearInterval(this.autoPlayInterval);
                this.autoPlayInterval = null;
            }
        }

        resetAutoPlay() {
            this.stopAutoPlay();
            if (!this.isHovering && this.totalSlides > 1) {
                this.startAutoPlay();
            }
        }

        getAllImages() {
            const images = [];
            this.elements.slides.forEach((slide, index) => {
                const bgImage = slide.style.backgroundImage;
                if (bgImage) {
                    const imageUrl = this.extractImageUrl(bgImage);
                    images.push({
                        src: imageUrl,
                        alt: `Property Image ${index + 1}`,
                        title: `Image ${index + 1}`,
                        description: `Property view ${index + 1}`
                    });
                }
            });
            return images;
        }

        destroy() {
            this.stopAutoPlay();
            this.initialized = false;
        }
    }

// Fancybox Module - With smooth autoplay, fixed expand icon, and slide animations
class HavenlyticsSingleFancyboxModule extends HavenlyticsBaseGalleryModule {
    constructor(parent) {
        super(parent);
        this.elements = {
            galleryPopup: '#hvnlyPropertySingleFancyboxGallery',
            videoPopup: '#hvnlyPropertySingleFancyboxVideo'
        };
        
        this.galleryImages = [];
        this.currentGalleryIndex = 0;
        this.isAnimating = false;
        this.currentPopup = null;
        this.removeEventListeners = [];
        this.previousFocus = null;
        this.isExternalGallery = false;
        this.thumbAnimationTimeout = null;
        
        // Autoplay properties
        this.autoplayInterval = null;
        this.autoplayDelay = 4000; // 4 seconds between slides
        this.isAutoplayPaused = false;
        this.autoplayEnabled = true;
    }

    init() {
        try {
            this.elements.galleryPopup = this.getElement(this.elements.galleryPopup);
            
            if (!this.elements.galleryPopup) {
                this.log('Gallery popup not found', 'warn');
                return;
            }
            
            this.bindEvents();
            this.initialized = true;
            this.log('Fancybox initialized with autoplay and slide animations');
        } catch (error) {
            this.log(`Fancybox error: ${error.message}`, 'error');
        }
    }

    bindEvents() {
        if (this.elements.galleryPopup) {
            const galleryClose = this.elements.galleryPopup.querySelector('.hvnly-property-single__fancybox-close');
            const galleryFullscreen = this.elements.galleryPopup.querySelector('.hvnly-property-single__fancybox-fullscreen');
            const galleryPrev = this.elements.galleryPopup.querySelector('.hvnly-property-single__fancybox-nav--prev');
            const galleryNext = this.elements.galleryPopup.querySelector('.hvnly-property-single__fancybox-nav--next');
            const galleryCounter = this.elements.galleryPopup.querySelector('.hvnly-property-single__fancybox-counter');
            const galleryCaption = this.elements.galleryPopup.querySelector('.hvnly-property-single__fancybox-caption');
            const galleryImg = this.elements.galleryPopup.querySelector('.hvnly-property-single__fancybox-img');
            const propertyButton = this.elements.galleryPopup.querySelector('.hvnly-property-single__fancybox-property-button');
            
            this.elements.closeBtn = galleryClose;
            this.elements.fullscreenBtn = galleryFullscreen;
            this.elements.prevBtn = galleryPrev;
            this.elements.nextBtn = galleryNext;
            this.elements.counter = galleryCounter;
            this.elements.caption = galleryCaption;
            this.elements.image = galleryImg;
            this.elements.propertyButton = propertyButton;
            
            if (galleryClose) {
                galleryClose.addEventListener('click', () => this.closePopup(this.elements.galleryPopup));
            }
            
            // Fixed fullscreen button
            if (galleryFullscreen) {
                galleryFullscreen.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleFullscreen(this.elements.galleryPopup);
                });
            }
            
            if (galleryPrev) {
                galleryPrev.addEventListener('click', () => {
                    this.pauseAutoplay();
                    this.showPrev();
                });
            }
            
            if (galleryNext) {
                galleryNext.addEventListener('click', () => {
                    this.pauseAutoplay();
                    this.showNext();
                });
            }
            
            // Mouse enter/leave for popup content - hover to pause
            const popupContent = this.elements.galleryPopup.querySelector('.hvnly-property-single__fancybox-content');
            if (popupContent) {
                popupContent.addEventListener('mouseenter', () => {
                    this.pauseAutoplay();
                });
                
                popupContent.addEventListener('mouseleave', () => {
                    this.resumeAutoplay();
                });
            }
            
            // Also pause on thumbnail hover
            const thumbnailsContainer = document.getElementById('hvnlyPropertySingleFancyboxThumbnails');
            if (thumbnailsContainer) {
                thumbnailsContainer.addEventListener('mouseenter', () => {
                    this.pauseAutoplay();
                });
                
                thumbnailsContainer.addEventListener('mouseleave', () => {
                    this.resumeAutoplay();
                });
            }
            
            this.elements.galleryPopup.addEventListener('click', (e) => {
                if (e.target === this.elements.galleryPopup) {
                    this.closePopup(this.elements.galleryPopup);
                }
            });
        }
        
        document.addEventListener('keydown', (e) => {
            if (!this.currentPopup) return;
            
            if (e.key === "Escape") {
                if (this.currentPopup === 'gallery' && this.elements.galleryPopup) {
                    this.closePopup(this.elements.galleryPopup);
                } else if (this.currentPopup === 'video' && this.elements.videoPopup) {
                    this.closePopup(this.elements.videoPopup);
                }
            }
            
            if (this.currentPopup === 'gallery' && !this.isAnimating) {
                if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    this.pauseAutoplay();
                    this.showNext();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this.pauseAutoplay();
                    this.showPrev();
                }
            }
        });
    }

    openGallery() {
        if (!this.elements.galleryPopup) return;
        
        this.currentPopup = 'gallery';
        this.isExternalGallery = false;
        this.updateGalleryImages();
        this.elements.galleryPopup.classList.add('hvnly-property-single__fancybox-popup--active');
        document.body.style.overflow = 'hidden';
        this.trapFocus(this.elements.galleryPopup);
        
        // Start autoplay when opened
        this.startAutoplay();
    }

    openGalleryFromExternal(images, startIndex = 0) {
        if (!this.elements.galleryPopup) return;
        
        this.currentPopup = 'gallery';
        this.isExternalGallery = true;
        this.galleryImages = images;
        this.currentGalleryIndex = startIndex;
        
        this.updateExternalGalleryImages();
        this.elements.galleryPopup.classList.add('hvnly-property-single__fancybox-popup--active');
        document.body.style.overflow = 'hidden';
        this.trapFocus(this.elements.galleryPopup);
        
        // Start autoplay when opened
        this.startAutoplay();
    }

    closePopup(popup) {
        if (!popup) return;
        
        // Stop autoplay when closed
        this.stopAutoplay();
        
        popup.classList.remove('hvnly-property-single__fancybox-popup--active');
        document.body.style.overflow = '';
        this.currentPopup = null;
        this.isExternalGallery = false;
        
        if (this.thumbAnimationTimeout) {
            clearTimeout(this.thumbAnimationTimeout);
            this.thumbAnimationTimeout = null;
        }
        
        this.restoreFocus();
        
        // Exit fullscreen if active
        if (popup.classList.contains('hvnly-property-single__fancybox-popup--fullscreen')) {
            popup.classList.remove('hvnly-property-single__fancybox-popup--fullscreen');
            this.updateFullscreenIcons(popup);
        }
    }

    // Fixed toggle fullscreen method
    toggleFullscreen(popup) {
        if (!popup) return;
        
        // Toggle fullscreen class
        if (popup.classList.contains('hvnly-property-single__fancybox-popup--fullscreen')) {
            popup.classList.remove('hvnly-property-single__fancybox-popup--fullscreen');
        } else {
            popup.classList.add('hvnly-property-single__fancybox-popup--fullscreen');
        }
        
        // Update icons
        this.updateFullscreenIcons(popup);
        
        // Force resize for images/video
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 100);
    }

    // Fixed update fullscreen icons method
    updateFullscreenIcons(popup) {
        const fullscreenBtn = popup.querySelector('.hvnly-property-single__fancybox-fullscreen');
        if (!fullscreenBtn) return;
        
        const isFullscreen = popup.classList.contains('hvnly-property-single__fancybox-popup--fullscreen');
        
        // Find both icons
        const expandIcon = fullscreenBtn.querySelector('.hvnly-expand');
        const compressIcon = fullscreenBtn.querySelector('.hvnly-compress');
        
        if (expandIcon && compressIcon) {
            if (isFullscreen) {
                expandIcon.style.display = 'none';
                compressIcon.style.display = 'block';
            } else {
                expandIcon.style.display = 'block';
                compressIcon.style.display = 'none';
            }
        } else {
            // Fallback: try to find by tag
            const allSvgs = fullscreenBtn.querySelectorAll('svg');
            allSvgs.forEach((svg, index) => {
                const use = svg.querySelector('use');
                if (use) {
                    const href = use.getAttribute('xlink:href') || use.getAttribute('href');
                    if (href) {
                        if (href.includes('expand')) {
                            svg.style.display = isFullscreen ? 'none' : 'block';
                        } else if (href.includes('compress')) {
                            svg.style.display = isFullscreen ? 'block' : 'none';
                        }
                    }
                }
            });
        }
    }

    trapFocus(element) {
        this.previousFocus = document.activeElement;
        
        const focusableElements = element.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstFocusable = focusableElements[0];
        
        if (firstFocusable) {
            setTimeout(() => firstFocusable.focus(), 100);
        }
    }

    restoreFocus() {
        if (this.previousFocus && typeof this.previousFocus.focus === 'function') {
            this.previousFocus.focus();
        }
    }

    updateGalleryImages() {
        if (!this.parent.modules.gallery) return;
        
        this.galleryImages = this.parent.modules.gallery.getAllImages();
        this.currentGalleryIndex = this.parent.modules.gallery.currentIndex;
        
        const galleryThumbnails = document.getElementById('hvnlyPropertySingleFancyboxThumbnails');
        if (!galleryThumbnails) return;
        
        galleryThumbnails.innerHTML = '';
        
        this.galleryImages.forEach((image, index) => {
            const thumb = this.createThumbnail(image, index);
            galleryThumbnails.appendChild(thumb);
        });
        
        this.updateLightbox();
    }

    updateExternalGalleryImages() {
        const galleryThumbnails = document.getElementById('hvnlyPropertySingleFancyboxThumbnails');
        if (!galleryThumbnails) return;
        
        galleryThumbnails.innerHTML = '';
        
        this.galleryImages.forEach((image, index) => {
            const thumb = this.createThumbnail(image, index);
            galleryThumbnails.appendChild(thumb);
        });
        
        this.updateLightbox();
    }

    createThumbnail(image, index) {
        const thumb = document.createElement('div');
        thumb.className = `hvnly-property-single__fancybox-thumb ${index === this.currentGalleryIndex ? 'hvnly-property-single__fancybox-thumb--active' : ''}`;
        thumb.setAttribute('role', 'button');
        thumb.setAttribute('aria-label', `View image ${index + 1}`);
        thumb.setAttribute('tabindex', '0');
        thumb.setAttribute('data-index', index);
        
        const img = document.createElement('img');
        img.src = image.thumbnail || this.getThumbnailUrl(image.src) || image.src;
        img.alt = image.alt;
        img.loading = 'lazy';
        thumb.appendChild(img);
        
        thumb.addEventListener('click', () => {
            if (this.isAnimating) return;
            this.pauseAutoplay();
            this.navigateTo(index);
        });
        
        thumb.addEventListener('mouseenter', () => {
            this.pauseAutoplay();
        });
        
        thumb.addEventListener('mouseleave', () => {
            this.resumeAutoplay();
        });
        
        thumb.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (this.isAnimating) return;
                this.pauseAutoplay();
                this.navigateTo(index);
            }
        });
        
        return thumb;
    }

    updateLightbox() {
        if (this.galleryImages.length === 0 || !this.elements.galleryPopup) return;
        
        const image = this.galleryImages[this.currentGalleryIndex];
        
        if (this.elements.image) {
            this.elements.image.src = image.src;
            this.elements.image.alt = image.alt;
        }
        
        if (this.elements.caption) {
            this.elements.caption.textContent = image.description || image.title;
        }
        
        if (this.elements.counter) {
            this.elements.counter.textContent = `${this.currentGalleryIndex + 1} / ${this.galleryImages.length}`;
        }
        
        if (this.elements.propertyButton) {
            this.elements.propertyButton.href = window.location.href;
        }
        
        document.querySelectorAll('.hvnly-property-single__fancybox-thumb').forEach((thumb, idx) => {
            const isActive = idx === this.currentGalleryIndex;
            thumb.classList.toggle('hvnly-property-single__fancybox-thumb--active', isActive);
            thumb.setAttribute('aria-current', isActive ? 'true' : 'false');
            thumb.setAttribute('tabindex', isActive ? '0' : '-1');
        });
        
        this.scrollToActiveThumbnail();
    }

    scrollToActiveThumbnail() {
        const activeThumb = document.querySelector('.hvnly-property-single__fancybox-thumb--active');
        const galleryThumbnails = document.getElementById('hvnlyPropertySingleFancyboxThumbnails');
        
        if (activeThumb && galleryThumbnails) {
            const container = galleryThumbnails;
            const thumbOffset = activeThumb.offsetTop;
            const thumbHeight = activeThumb.offsetHeight;
            const containerHeight = container.offsetHeight;
            
            let scrollPosition = thumbOffset - (containerHeight / 2) + (thumbHeight / 2);
            scrollPosition = Math.max(0, Math.min(scrollPosition, container.scrollHeight - containerHeight));
            
            if (container.scrollTo) {
                container.scrollTo({
                    top: scrollPosition,
                    behavior: 'smooth'
                });
            } else {
                container.scrollTop = scrollPosition;
            }
        }
    }

    // Updated navigateTo method with smooth slide animations
    navigateTo(index) {
        if (this.isAnimating || index === this.currentGalleryIndex || index < 0 || index >= this.galleryImages.length) return;
        
        this.isAnimating = true;
        const direction = index > this.currentGalleryIndex ? 'next' : 'prev';
        const galleryImg = this.elements.image;
        
        if (!galleryImg) {
            this.isAnimating = false;
            return;
        }
        
        // Remove any existing animation classes
        galleryImg.classList.remove(
            'hvnly-property-single__fancybox-img--slide-out-left',
            'hvnly-property-single__fancybox-img--slide-out-right',
            'hvnly-property-single__fancybox-img--slide-in-left',
            'hvnly-property-single__fancybox-img--slide-in-right'
        );
        
        // Apply slide-out animation based on direction
        if (direction === 'next') {
            galleryImg.classList.add('hvnly-property-single__fancybox-img--slide-out-left');
        } else {
            galleryImg.classList.add('hvnly-property-single__fancybox-img--slide-out-right');
        }
        
        // Short timeout to allow slide-out to start
        setTimeout(() => {
            // Update to new image
            this.currentGalleryIndex = index;
            const image = this.galleryImages[this.currentGalleryIndex];
            
            // Update image source
            galleryImg.src = image.src;
            galleryImg.alt = image.alt;
            
            // Update caption
            if (this.elements.caption) {
                this.elements.caption.textContent = image.description || image.title;
            }
            
            // Update counter
            if (this.elements.counter) {
                this.elements.counter.textContent = `${this.currentGalleryIndex + 1} / ${this.galleryImages.length}`;
            }
            
            // Remove slide-out classes
            galleryImg.classList.remove(
                'hvnly-property-single__fancybox-img--slide-out-left',
                'hvnly-property-single__fancybox-img--slide-out-right'
            );
            
            // Apply slide-in animation based on direction
            if (direction === 'next') {
                galleryImg.classList.add('hvnly-property-single__fancybox-img--slide-in-right');
            } else {
                galleryImg.classList.add('hvnly-property-single__fancybox-img--slide-in-left');
            }
            
            // Force a reflow to ensure animation plays
            void galleryImg.offsetWidth;
            
            // Update active thumbnail
            document.querySelectorAll('.hvnly-property-single__fancybox-thumb').forEach((thumb, idx) => {
                const isActive = idx === this.currentGalleryIndex;
                thumb.classList.toggle('hvnly-property-single__fancybox-thumb--active', isActive);
                thumb.setAttribute('aria-current', isActive ? 'true' : 'false');
                thumb.setAttribute('tabindex', isActive ? '0' : '-1');
            });
            
            this.scrollToActiveThumbnail();
            
            // Remove slide-in classes after animation completes
            setTimeout(() => {
                galleryImg.classList.remove(
                    'hvnly-property-single__fancybox-img--slide-in-right',
                    'hvnly-property-single__fancybox-img--slide-in-left'
                );
                this.isAnimating = false;
            }, 400); // Match CSS transition time
            
        }, 50); // Small delay to allow slide-out to be visible
    }

    showNext() {
        if (this.galleryImages.length === 0 || this.isAnimating) return;
        const nextIndex = (this.currentGalleryIndex + 1) % this.galleryImages.length;
        this.navigateTo(nextIndex);
    }

    showPrev() {
        if (this.galleryImages.length === 0 || this.isAnimating) return;
        const prevIndex = (this.currentGalleryIndex - 1 + this.galleryImages.length) % this.galleryImages.length;
        this.navigateTo(prevIndex);
    }

    // Autoplay methods
    startAutoplay() {
        this.stopAutoplay();
        if (!this.autoplayEnabled || this.galleryImages.length <= 1) return;
        
        this.autoplayInterval = setInterval(() => {
            if (!this.isAutoplayPaused && !this.isAnimating && this.currentPopup === 'gallery') {
                this.showNext();
            }
        }, this.autoplayDelay);
        
        this.log('Autoplay started');
    }

    stopAutoplay() {
        if (this.autoplayInterval) {
            clearInterval(this.autoplayInterval);
            this.autoplayInterval = null;
        }
    }

    pauseAutoplay() {
        this.isAutoplayPaused = true;
    }

    resumeAutoplay() {
        this.isAutoplayPaused = false;
    }

    destroy() {
        this.stopAutoplay();
        if (this.thumbAnimationTimeout) {
            clearTimeout(this.thumbAnimationTimeout);
            this.thumbAnimationTimeout = null;
        }
        
        this.removeEventListeners.forEach(remove => remove && remove());
        this.removeEventListeners = [];
        this.initialized = false;
    }
}

    // Carousel Module (keep as is)
    class HavenlyticsSingleCarouselModule extends HavenlyticsBaseGalleryModule {
        constructor(parent, rootElement = null) {
            super(parent);
            this.root = rootElement;

            if (rootElement) {
                this.elements = {
                    track: rootElement.querySelector('[data-hvnly-carousel-track]'),
                    prevBtn: rootElement.querySelector('[data-hvnly-carousel-prev]'),
                    nextBtn: rootElement.querySelector('[data-hvnly-carousel-next]'),
                    dotsContainer: rootElement.querySelector('[data-hvnly-carousel-dots]'),
                    container: rootElement.querySelector('[data-hvnly-carousel-container]'),
                };
                const visibleAttr = rootElement.getAttribute('data-visible-slides');
                this.fixedVisibleSlides = visibleAttr ? parseInt(visibleAttr, 10) : 1;
                this.autoPlayEnabled = rootElement.getAttribute('data-autoplay') !== '0';
            } else {
                this.elements = {
                    track: '#hvnlyPropertySingleCarouselTrack',
                    prevBtn: '#hvnlyPropertySingleCarouselPrev',
                    nextBtn: '#hvnlyPropertySingleCarouselNext',
                    dotsContainer: '#hvnlyPropertySingleCarouselDots',
                    container: null,
                };
                this.fixedVisibleSlides = null;
                this.autoPlayEnabled = true;
            }
            
            this.currentIndex = 0;
            this.totalSlides = 0;
            this.visibleSlides = 3;
            this.isAnimating = false;
            this.slideWidth = 0;
            this.gap = 30;
            
            this.autoPlayInterval = null;
            this.autoPlayDelay = 4000;
            this.isHovering = false;
            this.userInteracted = false;
            this.removeEventListeners = [];
            this.resizeHandler = null;
            this.touchStartX = 0;
        }

        init() {
            try {
                if (!this.root) {
                    this.elements.track = this.getElement(this.elements.track);
                    if (!this.elements.track) {
                        this.log('Carousel track not found', 'warn');
                        return;
                    }

                    this.elements.prevBtn = this.getElement(this.elements.prevBtn);
                    this.elements.nextBtn = this.getElement(this.elements.nextBtn);
                    this.elements.dotsContainer = this.getElement(this.elements.dotsContainer);
                    this.elements.container = this.elements.track.parentElement;
                }

                if (!this.elements.track) {
                    this.log('Carousel track not found', 'warn');
                    return;
                }

                this.elements.slides = Array.from(this.elements.track.children);
                this.totalSlides = this.elements.slides.length;
                
                if (this.totalSlides === 0) {
                    this.log('No carousel slides found', 'warn');
                    return;
                }

                this.visibleSlides = this.getVisibleSlides();
                this.slideWidth = this.calculateSlideWidth();
                this.createDots();
                this.bindEvents();
                this.updateCarousel();
                this.updateDots();
                this.startAutoPlay();
                this.initialized = true;

                this.log(`Carousel initialized with ${this.totalSlides} slides`);
            } catch (error) {
                this.log(`Carousel error: ${error.message}`, 'error');
            }
        }

        getVisibleSlides() {
            if (typeof this.fixedVisibleSlides === 'number' && !isNaN(this.fixedVisibleSlides)) {
                return Math.max(1, this.fixedVisibleSlides);
            }

            if (!window.matchMedia) return 3;
            
            if (window.matchMedia('(min-width: 1024px)').matches) return 3;
            if (window.matchMedia('(min-width: 768px)').matches) return 2;
            return 1;
        }

        calculateSlideWidth() {
            if (!this.elements.slides.length || !this.elements.track) return 0;
            
            const container = this.elements.track.parentElement;
            if (!container) return 0;
            
            const containerWidth = container.offsetWidth;
            const containerStyle = window.getComputedStyle(container);
            const containerPadding = parseFloat(containerStyle.paddingLeft) + parseFloat(containerStyle.paddingRight);
            
            const firstSlide = this.elements.slides[0];
            const slideStyle = window.getComputedStyle(firstSlide);
            const slidePadding = parseFloat(slideStyle.paddingLeft) + parseFloat(slideStyle.paddingRight);
            const slideMargin = parseFloat(slideStyle.marginRight) || 0;
            
            const availableWidth = containerWidth - containerPadding;
            const slideWidth = (availableWidth / this.visibleSlides) - slidePadding - slideMargin;
            
            return Math.max(0, slideWidth);
        }

        bindEvents() {
            if (this.elements.prevBtn) {
                this.elements.prevBtn.addEventListener('click', () => {
                    this.userInteracted = true;
                    this.prev();
                });
            }
            
            if (this.elements.nextBtn) {
                this.elements.nextBtn.addEventListener('click', () => {
                    this.userInteracted = true;
                    this.next();
                });
            }
            
            if (this.elements.container) {
                this.elements.container.addEventListener('mouseenter', () => {
                    this.isHovering = true;
                    this.stopAutoPlay();
                });
                
                this.elements.container.addEventListener('mouseleave', () => {
                    this.isHovering = false;
                    if (!this.userInteracted) {
                        setTimeout(() => {
                            if (!this.isHovering) {
                                this.startAutoPlay();
                            }
                        }, 1000);
                    }
                });

                const onTouchStart = (event) => {
                    if (!event.changedTouches || !event.changedTouches.length) {
                        return;
                    }
                    this.touchStartX = event.changedTouches[0].screenX;
                };

                const onTouchEnd = (event) => {
                    if (!event.changedTouches || !event.changedTouches.length) {
                        return;
                    }

                    const touchEndX = event.changedTouches[0].screenX;
                    const diff = this.touchStartX - touchEndX;

                    if (Math.abs(diff) < 40) {
                        return;
                    }

                    this.userInteracted = true;
                    if (diff > 0) {
                        this.next();
                    } else {
                        this.prev();
                    }
                };

                this.elements.container.addEventListener('touchstart', onTouchStart, { passive: true });
                this.elements.container.addEventListener('touchend', onTouchEnd, { passive: true });
                this.removeEventListeners.push(
                    () => this.elements.container.removeEventListener('touchstart', onTouchStart),
                    () => this.elements.container.removeEventListener('touchend', onTouchEnd)
                );
            }
            
            this.resizeHandler = () => {
                clearTimeout(this.resizeTimeout);
                this.resizeTimeout = setTimeout(() => {
                    this.handleResize();
                }, 250);
            };
            
            window.addEventListener('resize', this.resizeHandler);
        }

        handleResize() {
            const newVisibleSlides = this.getVisibleSlides();
            if (newVisibleSlides !== this.visibleSlides) {
                this.visibleSlides = newVisibleSlides;
                this.slideWidth = this.calculateSlideWidth();
                this.createDots();
                this.updateCarousel();
                this.updateDots();
            }
        }

        createDots() {
            if (!this.elements.dotsContainer) return;
            
            this.elements.dotsContainer.innerHTML = '';
            const totalPages = Math.max(1, this.totalSlides - this.visibleSlides + 1);
            
            for (let i = 0; i < totalPages; i++) {
                const dot = document.createElement('button');
                dot.className = 'hvnly-property-single__carousel-dot';
                if (i === 0) dot.classList.add('hvnly-property-single__carousel-dot--active');
                dot.setAttribute('aria-label', `Go to slide group ${i + 1} of ${totalPages}`);
                dot.setAttribute('tabindex', '0');
                
                dot.addEventListener('click', () => {
                    this.userInteracted = true;
                    this.goToSlide(i);
                });
                
                dot.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.userInteracted = true;
                        this.goToSlide(i);
                    }
                });
                
                this.elements.dotsContainer.appendChild(dot);
            }
        }

        updateCarousel() {
            if (this.isAnimating || !this.elements.track || this.totalSlides === 0) return;
            this.isAnimating = true;
            
            const maxIndex = Math.max(0, this.totalSlides - this.visibleSlides);
            this.currentIndex = Math.min(this.currentIndex, maxIndex);
            
            const firstSlide = this.elements.slides[0];
            if (!firstSlide) {
                this.isAnimating = false;
                return;
            }
            
            const slideStyle = window.getComputedStyle(firstSlide);
            const slideMargin = parseFloat(slideStyle.marginRight) || 0;
            const slidePadding = parseFloat(slideStyle.paddingLeft) + parseFloat(slideStyle.paddingRight);
            const slideOccupiedWidth = this.slideWidth + slidePadding + slideMargin;
            const offset = -(this.currentIndex * slideOccupiedWidth);
            
            this.elements.track.style.transform = `translateX(${offset}px)`;
            this.elements.track.style.transition = 'transform 0.3s ease';
            
            this.elements.slides.forEach((slide, index) => {
                const isVisible = index >= this.currentIndex && index < this.currentIndex + this.visibleSlides;
                slide.setAttribute('aria-hidden', !isVisible);
                slide.setAttribute('tabindex', isVisible ? '0' : '-1');
            });
            
            this.updateButtons();
            
            setTimeout(() => {
                this.isAnimating = false;
            }, 300);
        }

        updateButtons() {
            const maxIndex = Math.max(0, this.totalSlides - this.visibleSlides);
            
            if (this.elements.prevBtn) {
                const isDisabled = this.currentIndex === 0;
                this.elements.prevBtn.disabled = isDisabled;
                this.elements.prevBtn.setAttribute('aria-disabled', isDisabled);
            }
            
            if (this.elements.nextBtn) {
                const isDisabled = this.currentIndex >= maxIndex;
                this.elements.nextBtn.disabled = isDisabled;
                this.elements.nextBtn.setAttribute('aria-disabled', isDisabled);
            }
        }

        updateDots() {
            if (!this.elements.dotsContainer) return;
            
            const dots = Array.from(this.elements.dotsContainer.children);
            const totalPages = Math.max(1, this.totalSlides - this.visibleSlides + 1);
            const currentPage = Math.min(this.currentIndex, totalPages - 1);
            
            dots.forEach((dot, index) => {
                const isActive = index === currentPage;
                dot.classList.toggle('hvnly-property-single__carousel-dot--active', isActive);
                dot.setAttribute('aria-current', isActive ? 'true' : 'false');
            });
        }

        prev() {
            if (this.currentIndex > 0) {
                this.currentIndex--;
                this.updateCarousel();
                this.updateDots();
            }
        }

        next() {
            const maxIndex = Math.max(0, this.totalSlides - this.visibleSlides);
            if (this.currentIndex < maxIndex) {
                this.currentIndex++;
                this.updateCarousel();
                this.updateDots();
            } else if (this.totalSlides > this.visibleSlides) {
                this.currentIndex = 0;
                this.updateCarousel();
                this.updateDots();
            }
        }

        goToSlide(index) {
            const maxIndex = Math.max(0, this.totalSlides - this.visibleSlides);
            index = Math.min(index, maxIndex);
            
            if (index !== this.currentIndex) {
                this.currentIndex = index;
                this.updateCarousel();
                this.updateDots();
            }
        }

        startAutoPlay() {
            this.stopAutoPlay();
            if (!this.autoPlayEnabled || this.totalSlides <= this.visibleSlides) return;
            
            this.autoPlayInterval = setInterval(() => {
                if (!this.isHovering && !this.userInteracted) {
                    this.next();
                }
            }, this.autoPlayDelay);
        }

        stopAutoPlay() {
            if (this.autoPlayInterval) {
                clearInterval(this.autoPlayInterval);
                this.autoPlayInterval = null;
            }
        }

        destroy() {
            this.stopAutoPlay();
            this.removeEventListeners.forEach(remove => remove && remove());
            if (this.resizeHandler) {
                window.removeEventListener('resize', this.resizeHandler);
            }
            this.initialized = false;
        }
    }

    // Initialize only once
    $(document).ready(function() {
        if (typeof window.hvnlyFrontendSingleGallery === 'undefined') {
            try {
                window.hvnlyFrontendSingleGallery = new HavenlyticsFrontendSingleGallery();
            } catch (error) {
                // console.error('Failed to initialize gallery:', error);
            }
        }
    });

})(jQuery);

// REMOVED ALL DUPLICATE CODE AT THE BOTTOM