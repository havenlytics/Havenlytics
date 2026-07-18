/**
 * Havenlytics Single Property JavaScript - Main File
 * Handles scroll and actions only
 * 
 * @package     Havenlytics
 * @version     2.0.0
 */
(function($) {
    'use strict';

    if (typeof window.hvnlyPropertySingleInitialized !== 'undefined') {
        return;
    }

    // ============================================
    // BASE MODULE CLASS
    // ============================================
    class HavenlyticsBaseModule {
        constructor(parent) {
            this.parent = parent;
            this.initialized = false;
            this.elements = {};
        }

        log(message, type = 'log') {
            if (this.parent.config.debug) {
                // console[type]('[Havenlytics]', message);
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
    }

    // ============================================
    // MAIN PROPERTY SINGLE CLASS
    // ============================================
    class HavenlyticsPropertySingle {
        constructor() {
            this.version = '2.0.0';
            this.initialized = false;
            this.config = {
                debug: window.location.hostname === 'localhost' || window.location.hostname.indexOf('.local') !== -1,
                enableAutoInit: true
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
                        // console.log('Havenlytics Property Single v' + this.version + ' initialized successfully');
                    }
                } catch (error) {
                    // console.error('Havenlytics Property Single initialization error:', error);
                }
            });
        }

        initModules() {
            this.modules = {
                scroll: new HavenlyticsPropertySingleScroll(this),
                actions: new HavenlyticsPropertySingleActions(this)
                // NO MAPS MODULE HERE - maps are separate
            };
        }

        bindEvents() {
            $(document).ready(() => {
                try {
                    this.modules.scroll.init();
                    this.modules.actions.init();
                } catch (error) {
                    if (this.parent.config.debug) {
                        // console.warn('Havenlytics module initialization warning:', error);
                    }
                }
            });
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
            this.initialized = false;
        }
    }

    // ============================================
    // SCROLL MODULE
    // ============================================
    class HavenlyticsPropertySingleScroll extends HavenlyticsBaseModule {
        constructor(parent) {
            super(parent);
            this.elements = {
                scrollProgressBar: '#hvnlyPropertySingleScrollProgressBar',
                scrollTopBtn: '.hvnly-property-single__gallery-scroll-top'
            };
            
            this.lastScrollTop = 0;
            this.removeEventListeners = [];
            this.scrollHandler = null;
        }

        init() {
            try {
                this.elements.scrollProgressBar = this.getElement(this.elements.scrollProgressBar);
                this.elements.scrollTopBtn = this.getElement(this.elements.scrollTopBtn);
                
                this.bindEvents();
                this.initialized = true;
                
                this.log('Scroll module initialized');
            } catch (error) {
                this.log(`Scroll module initialization error: ${error.message}`, 'error');
            }
        }

        bindEvents() {
            this.scrollHandler = () => this.handleScroll();
            window.addEventListener('scroll', this.scrollHandler);
            
            if (this.elements.scrollTopBtn) {
                this.removeEventListeners.push(
                    this.safeAddEventListener(this.elements.scrollTopBtn, 'click', () => {
                        window.scrollTo({
                            top: 0,
                            behavior: 'smooth'
                        });
                    })
                );
                
                const scrollVisibilityHandler = () => {
                    if (window.scrollY > 300) {
                        this.elements.scrollTopBtn.classList.add('hvnly-property-single__gallery-scroll-top--visible');
                    } else {
                        this.elements.scrollTopBtn.classList.remove('hvnly-property-single__gallery-scroll-top--visible');
                    }
                };
                
                window.addEventListener('scroll', scrollVisibilityHandler);
                this.removeEventListeners.push(() => window.removeEventListener('scroll', scrollVisibilityHandler));
            }
        }

        handleScroll() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            
            if (scrollHeight === 0) return;
            
            const scrolled = (scrollTop / scrollHeight) * 100;
            const scrollDirection = scrollTop > this.lastScrollTop ? 'down' : 'up';
            this.lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            
            if (this.elements.scrollProgressBar) {
                this.elements.scrollProgressBar.style.width = Math.min(100, Math.max(0, scrolled)) + '%';
                this.elements.scrollProgressBar.style.background = scrollDirection === 'down' 
                    ? 'linear-gradient(90deg, var(--hvnly-brand-primary) 0%, var(--hvnly-brand-secondary) 100%)'
                    : 'linear-gradient(90deg, var(--hvnly-brand-secondary) 0%, var(--hvnly-brand-primary) 100%)';
            }
        }

        destroy() {
            if (this.scrollHandler) {
                window.removeEventListener('scroll', this.scrollHandler);
            }
            this.removeEventListeners.forEach(remove => remove && remove());
            this.removeEventListeners = [];
            this.initialized = false;
        }
    }

    // ============================================
    // ACTIONS MODULE
    // ============================================
    class HavenlyticsPropertySingleActions extends HavenlyticsBaseModule {
        constructor(parent) {
            super(parent);
            this.elements = {
                saveBtn: '#hvnlyPropertySingleSaveBtn',
                printBtn: '#hvnlyPropertySinglePrintBtn',
                contactForm: '.hvnly-property-single__contact-form'
            };
            
            this.removeEventListeners = [];
        }

        init() {
            try {
                this.elements.saveBtn = this.getElement(this.elements.saveBtn);
                this.elements.printBtn = this.getElement(this.elements.printBtn);
                this.elements.contactForm = this.getElement(this.elements.contactForm);
                
                this.bindEvents();
                this.initialized = true;
                
                this.log('Actions module initialized');
            } catch (error) {
                this.log(`Actions module initialization error: ${error.message}`, 'error');
            }
        }

        bindEvents() {
            if (this.elements.saveBtn) {
                this.removeEventListeners.push(
                    this.safeAddEventListener(this.elements.saveBtn, 'click', () => this.toggleSave())
                );
            }
            
            if (this.elements.printBtn) {
                this.removeEventListeners.push(
                    this.safeAddEventListener(this.elements.printBtn, 'click', () => window.print())
                );
            }
            
            if (this.elements.contactForm) {
                this.removeEventListeners.push(
                    this.safeAddEventListener(this.elements.contactForm, 'submit', (e) => this.handleContactSubmit(e))
                );
            }
        }

        toggleSave() {
            if (!this.elements.saveBtn) return;
            
            this.elements.saveBtn.classList.toggle('hvnly-property-single__action-btn--active');
            const icon = this.elements.saveBtn.querySelector('svg use');
            if (this.elements.saveBtn.classList.contains('hvnly-property-single__action-btn--active')) {
                if (icon) icon.setAttribute('xlink:href', '#hvnly-heart');
                this.elements.saveBtn.innerHTML = '<svg class="hvnly-icon hvnly-icon-thin"><use xlink:href="#hvnly-heart"></use></svg> Saved';
            } else {
                if (icon) icon.setAttribute('xlink:href', '#hvnly-heart-outline');
                this.elements.saveBtn.innerHTML = '<svg class="hvnly-icon hvnly-icon-thin"><use xlink:href="#hvnly-heart-outline"></use></svg> Save Property';
            }
        }

        handleContactSubmit(e) {
            e.preventDefault();
            
            const submitBtn = this.elements.contactForm.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<svg class="hvnly-icon hvnly-icon-thin"><use xlink:href="#hvnly-check-circle"></use></svg> Request Sent!';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                this.elements.contactForm.reset();
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        }

        destroy() {
            this.removeEventListeners.forEach(remove => remove && remove());
            this.removeEventListeners = [];
            this.initialized = false;
        }
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    $(document).ready(function() {
        if (typeof window.hvnlyPropertySingle !== 'undefined') {
            return;
        }
        
        window.hvnlyPropertySingleInitialized = true;
        
        try {
            window.hvnlyPropertySingle = new HavenlyticsPropertySingle();
        } catch (error) {
            // console.error('Failed to initialize Havenlytics Property Single:', error);
        }
    });

})(jQuery);