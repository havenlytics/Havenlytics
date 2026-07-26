/**
 * Havenlytics Frontend JavaScript - Refactored for Templates
 * 
 * @package     Havenlytics
 * @version     2.2.0
 */

(function($) {
    'use strict';

    class HavenlyticsFrontend {
        constructor() {
            this.init();
        }
        
        init() {
            this.initializeComponents();
            this.bindEvents();
        }
        
        initializeComponents() {
            this.initViewToggle();
            this.initFilterToggles();
            this.initRangeSliders();
            this.initMobileMenu();
            this.initSearchTabs();
            this.initFavoriteButtons();
            this.initCheckboxes();
            this.initMultiSelectDropdowns();
            this.initPropertyGalleryCarousels();
        }
        
        bindEvents() {
            this.bindGlobalEvents();
        }
        
        // ======================
        // VIEW TOGGLE FUNCTIONALITY
        // ======================
        initViewToggle() {
            // Always available for AJAX reset / external callers.
            this.setView = (viewType, contextBtn = null) => {
                const elements = window.HvnlyDom.resolveViewElements(contextBtn);
                const propertyGrid = elements.propertyGrid;
                const mapPlaceholder = elements.mapPlaceholder;

                if (!propertyGrid) return;

                this.applyViewLayout(propertyGrid, viewType, mapPlaceholder);
            };

            // Style every grid-mode listing grid (Archive / Search / Featured can coexist).
            this.applyDefaultGridLayoutToAll();

            const viewButtons = document.querySelectorAll('.hvnly-property-view-btn');

            if (!viewButtons.length) return;

            const activeButton = document.querySelector('.hvnly-property-view-btn.active');
            if (activeButton) {
                this.setView(activeButton.getAttribute('data-view'), activeButton);
            }

            viewButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const viewType = button.getAttribute('data-view');
                    const listings = button.closest('.hvnly-property--grid--listings')
                        || button.closest('.hvnly-all-properties-widget')
                        || document;

                    listings.querySelectorAll('.hvnly-property-view-btn').forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');

                    this.setView(viewType, button);
                });
            });
        }

        /**
         * Apply Search/Archive grid chrome to every listing grid currently in grid mode.
         */
        applyDefaultGridLayoutToAll() {
            const grids = (window.HvnlyDom && typeof window.HvnlyDom.resolveAllPropertyGrids === 'function')
                ? window.HvnlyDom.resolveAllPropertyGrids().get()
                : Array.from(document.querySelectorAll('.hvnly-property-grid-view'));

            grids.forEach((grid) => {
                if (!grid) {
                    return;
                }
                if (grid.classList.contains('hvnly-list-view') || grid.classList.contains('list-view') || grid.classList.contains('map-view')) {
                    return;
                }
                if (!grid.classList.contains('hvnly-grid-view') && !grid.classList.contains('grid-view')) {
                    return;
                }
                this.applyViewLayout(grid, 'grid', null);
            });
        }

        /**
         * Column count from the grid or nearest Archive/Search/Featured host
         * (same data-columns token Elementor / block wrappers already set).
         *
         * @param {HTMLElement} propertyGrid
         * @returns {number} 0 when unset (fallback to auto-fill).
         */
        resolveGridColumnCount(propertyGrid) {
            if (!propertyGrid || !propertyGrid.getAttribute) {
                return 0;
            }

            const fromGrid = parseInt(propertyGrid.getAttribute('data-columns'), 10);
            if (!isNaN(fromGrid) && fromGrid >= 1 && fromGrid <= 6) {
                return fromGrid;
            }

            const host = propertyGrid.closest
                ? propertyGrid.closest('[data-columns]')
                : null;
            if (host) {
                const fromHost = parseInt(host.getAttribute('data-columns'), 10);
                if (!isNaN(fromHost) && fromHost >= 1 && fromHost <= 6) {
                    return fromHost;
                }
            }

            return 0;
        }

        /**
         * @param {HTMLElement} propertyGrid
         * @param {string} viewType
         * @param {HTMLElement|null} mapPlaceholder
         */
        applyViewLayout(propertyGrid, viewType, mapPlaceholder) {
            propertyGrid.classList.remove('hvnly-grid-view', 'grid-view', 'hvnly-list-view', 'list-view', 'map-view');

            if (viewType === 'grid') {
                propertyGrid.classList.add('hvnly-grid-view', 'grid-view');
                propertyGrid.style.display = 'grid';
                propertyGrid.style.gap = '20px';

                // Honor Featured / Archive / Search / Elementor columns when present.
                const cols = this.resolveGridColumnCount(propertyGrid);
                if (cols) {
                    propertyGrid.setAttribute('data-columns', String(cols));
                    propertyGrid.style.gridTemplateColumns = 'repeat(' + cols + ', minmax(0, 1fr))';
                } else {
                    propertyGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(350px, 1fr))';
                }

                if (mapPlaceholder) {
                    mapPlaceholder.style.display = 'none';
                }
            } else if (viewType === 'list') {
                propertyGrid.classList.add('hvnly-list-view', 'list-view');
                propertyGrid.style.display = 'flex';
                propertyGrid.style.flexDirection = 'column';
                propertyGrid.style.gap = '20px';

                if (mapPlaceholder) {
                    mapPlaceholder.style.display = 'none';
                }
            } else if (viewType === 'map') {
                propertyGrid.classList.add('map-view');
                propertyGrid.style.display = 'none';

                if (mapPlaceholder) {
                    mapPlaceholder.style.display = 'block';
                    this.triggerMapLoad();
                }
            }
        }

        /**
         * Load map view - delegates to map JS handler
         */
        loadMapView(contextBtn = null) {
            const elements = window.HvnlyDom.resolveViewElements(contextBtn);
            const mapPlaceholder = elements.mapPlaceholder;

            if (!mapPlaceholder) return;
            
            mapPlaceholder.style.display = 'block';
            this.triggerMapLoad();
        }

        triggerMapLoad() {
            const mapInstance = window.HavenlyticsPropertyMap || window.HvnlyPropertyMap;
            
            if (mapInstance && typeof mapInstance.loadPropertyMap === 'function') {
                setTimeout(() => {
                    mapInstance.loadPropertyMap();
                }, 100);
            }
        }
        
        // ======================
        // FILTER TOGGLES
        // ======================
        initFilterToggles() {
            const self = this;
            
            $(document).on('click', '.hvnly-property-filter-group-header', function() {
                const $header = $(this);
                const $group = $header.closest('.hvnly-property-filter-group');
                const $content = $header.next('.hvnly-property-filter-group-content');
                
                if ($content.length) {
                    const isCollapsed = $group.hasClass('collapsed');
                    
                    if (isCollapsed) {
                        $group.removeClass('collapsed');
                        $content.slideDown(300);
                        $header.find('.hvnly-property-filter-group-toggle i').removeClass('fa-chevron-down').addClass('fa-chevron-up');
                    } else {
                        $group.addClass('collapsed');
                        $content.slideUp(300);
                        $header.find('.hvnly-property-filter-group-toggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
                    }
                }
            });
            
            this.initializeFilterGroups();
        }

        initializeFilterGroups() {
            $('.hvnly-property-filter-group').each(function() {
                const $group = $(this);
                const $content = $group.find('.hvnly-property-filter-group-content');
                const $header = $group.find('.hvnly-property-filter-group-header');
                
                $group.addClass('collapsed');
                $content.hide();
                $header.find('.hvnly-property-filter-group-toggle i').removeClass('fa-chevron-up').addClass('fa-chevron-down');
            });
        }
      
        // ======================
        // RANGE SLIDERS
        // ======================
        initRangeSliders() {
            const rangeSliders = document.querySelectorAll('.hvnly-property-range');
            const progress = document.getElementById('hvnly-price-progress');
            
            const updateProgress = () => {
                if (!progress) return;
                
                const minInput = document.querySelector('.hvnly-property-range[data-filter="min_price"]');
                const maxInput = document.querySelector('.hvnly-property-range[data-filter="max_price"]');
                
                if (minInput && maxInput) {
                    const minVal = parseInt(minInput.value);
                    const maxVal = parseInt(maxInput.value);
                    const maxRange = parseInt(maxInput.max);
                    
                    const left = (minVal / maxRange) * 100;
                    const right = 100 - (maxVal / maxRange) * 100;
                    
                    progress.style.left = left + '%';
                    progress.style.right = right + '%';
                }
            };
            
            rangeSliders.forEach(slider => {
                const values = slider.closest('.hvnly-property-range-slider').querySelector('.hvnly-property-range-values');
                
                slider.addEventListener('input', () => {
                    const minInput = document.querySelector('.hvnly-property-range[data-filter="min_price"]');
                    const maxInput = document.querySelector('.hvnly-property-range[data-filter="max_price"]');
                    
                    if (slider.getAttribute('data-filter') === 'min_price' && minInput && maxInput) {
                        const minVal = parseInt(minInput.value);
                        const maxVal = parseInt(maxInput.value);
                        if (minVal > maxVal) {
                            minInput.value = maxVal;
                        }
                    }
                    
                    if (slider.getAttribute('data-filter') === 'max_price' && minInput && maxInput) {
                        const minVal = parseInt(minInput.value);
                        const maxVal = parseInt(maxInput.value);
                        if (maxVal < minVal) {
                            maxInput.value = minVal;
                        }
                    }
                    
                    if (values) {
                        const minValue = values.querySelector('span:first-child');
                        const maxValue = values.querySelector('span:last-child');
                        
                        if (slider.getAttribute('data-filter') === 'min_price' && minValue) {
                            minValue.textContent = this.formatPrice(slider.value);
                        } else {
                            if (maxValue) maxValue.textContent = this.formatPrice(slider.value);
                        }
                    }
                    
                    updateProgress();
                });
            });
            
            updateProgress();
        }

        formatPrice(price) {
            const numericPrice = parseInt(price);
            return '$' + numericPrice.toLocaleString();
        }

        // ======================
        // MOBILE MENU
        // ======================
        initMobileMenu() {
            const mobileMenuToggle = document.querySelector('.hvnly-mobile-menu-toggle');
            const mobileMenu = document.querySelector('.hvnly-mobile-menu');
            
            if (mobileMenuToggle && mobileMenu) {
                mobileMenuToggle.addEventListener('click', () => {
                    mobileMenu.classList.toggle('active');
                    mobileMenuToggle.classList.toggle('active');
                });
            }
        }

        // ======================
        // SEARCH TABS
        // ======================
        initSearchTabs() {
            const searchTabs = document.querySelectorAll('.hvnly-search-tab');

            searchTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const value = tab.getAttribute('data-value');

                    searchTabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    const propertyTypeInput = document.getElementById('property_type');
                    if (propertyTypeInput) {
                        propertyTypeInput.value = value;
                    }
                });
            });
        }

        // ======================
        // FAVORITE BUTTONS
        // ======================
        // 3.4.0: favorites moved to the dedicated hvnly-favorites.js module,
        // which persists state (REST for signed-in users, localStorage for
        // guests) and binds via delegation on document.
        //
        // The methods below are retained as no-ops purely for backward
        // compatibility — hvnly-frontend-property-ajax-root.js,
        // -ajax-search.js and -ajax-controller.js all call
        // initFavoriteButtons() after re-rendering results, and the
        // controller call site is unguarded, so removing it would throw.
        //
        // The previous implementation re-queried every button and did
        // `button.replaceWith(button.cloneNode(true))` to unbind. Delegated
        // listeners need no rebinding, and that clone would actively destroy
        // the server-rendered `aria-pressed` / `is-favorited` state. It must
        // stay gone.
        initFavoriteButtons() {
            // Delegated in hvnly-favorites.js — nothing to bind here.
            // Re-hydrate freshly injected cards from the canonical state.
            if (window.hvnlyFavorites && typeof window.hvnlyFavorites.hydrate === 'function') {
                window.hvnlyFavorites.hydrate();
            }
        }

        setupFavoriteButtonListeners() {
            this.initFavoriteButtons();
        }

        // ======================
        // CHECKBOXES
        // ======================
        initCheckboxes() {
            // Initialize states
            this._initCheckboxStates();
            
            // Remove ALL click handlers first
            $(document).off('click', '.hvnly-property-checkbox-label');
            $(document).off('click', '.hvnly-property-filter-checkbox');
            
            // Use event delegation with proper handling
            $(document).on('click', '.hvnly-property-checkbox-label', function(e) {
                const $label = $(this);
                const $input = $label.find('input[type="checkbox"]');
                
                if ($input.length) {
                    // Check if we clicked on the input itself
                    if (e.target === $input[0]) {
                        // Browser handles it, just update UI
                        const $checkbox = $input.closest('.hvnly-property-filter-checkbox');
                        setTimeout(() => {
                            $checkbox.toggleClass('checked', $input.is(':checked'));
                        }, 0);
                        return true;
                    }
                    
                    // Otherwise, toggle manually
                    e.preventDefault();
                    const newState = !$input.is(':checked');
                    $input.prop('checked', newState);
                    $input.closest('.hvnly-property-filter-checkbox').toggleClass('checked', newState);
                    $input.trigger('change');
                    return false;
                }
            });
            
            // Update after AJAX
            $(document).on('hvnly-properties-updated', () => {
                this._initCheckboxStates();
            });
        }

        _initCheckboxStates() {
            $('.hvnly-property-filter-checkbox').each(function() {
                const $checkbox = $(this);
                const $input = $checkbox.find('input[type="checkbox"]');
                $checkbox.toggleClass('checked', $input.is(':checked'));
            });
        }
        
        // ======================
        // MULTI-SELECT DROPDOWNS
        // ======================
        initMultiSelectDropdowns() {
            const multiSelectInputs = document.querySelectorAll('.hvnly-property-form-input-multiselect');
            
            multiSelectInputs.forEach(input => {
                this.updateSelectedItemsDisplay(input);
                
                input.addEventListener('focus', () => {
                    const dropdown = this.getDropdownForInput(input);
                    if (dropdown) {
                        dropdown.style.display = 'block';
                    }
                });

                input.addEventListener('input', () => {
                    const value = input.value.toLowerCase();
                    const dropdown = this.getDropdownForInput(input);
                    if (dropdown) {
                        const items = dropdown.querySelectorAll('li');
                        items.forEach(item => {
                            const text = item.textContent.toLowerCase();
                            if (text.includes(value)) {
                                item.style.display = 'block';
                            } else {
                                item.style.display = 'none';
                            }
                        });
                    }
                });
                
                const dropdown = this.getDropdownForInput(input);
                if (dropdown) {
                    const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', () => {
                            this.updateSelectedItemsDisplay(input);
                        });
                    });
                }
            });

            const closeButtons = document.querySelectorAll('.hvnly-property-dropdown-tags-close');
            closeButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const dropdown = button.previousElementSibling;
                    if (dropdown && dropdown.classList.contains('.hvnly-property-taxonomyDropdown-items')) {
                        dropdown.style.display = 'none';
                    }
                });
            });

            const resetButtons = document.querySelectorAll('.hvnly-property-dropdown-reset');
            resetButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const dropdown = button.closest('.hvnly-property-taxonomyDropdown-items');
                    if (dropdown) {
                        const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });
                        const input = this.getInputForDropdown(dropdown);
                        if (input) {
                            this.updateSelectedItemsDisplay(input);
                        }
                    }
                });
            });
            
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.hvnly-property-tax-multichebox')) {
                    document.querySelectorAll('.hvnly-property-taxonomyDropdown-items').forEach(dropdown => {
                        dropdown.style.display = 'none';
                    });
                }
            });
        }

        getDropdownForInput(input) {
            const container = input.closest('.hvnly-property-tax-multichebox');
            if (container) {
                return container.querySelector('.hvnly-property-taxonomyDropdown-items');
            }
            return null;
        }

        getInputForDropdown(dropdown) {
            const container = dropdown.closest('.hvnly-property-tax-multichebox');
            if (container) {
                return container.querySelector('.hvnly-property-form-input-multiselect');
            }
            return null;
        }

        updateSelectedItemsDisplay(input) {
            const container = input.closest('.hvnly-property-tax-multichebox');
            if (!container) return;
            
            const selectedContainer = container.querySelector('.hvnly-property-selected-items-container-tags');
            const dropdown = container.querySelector('.hvnly-property-taxonomyDropdown-items');
            
            if (!selectedContainer || !dropdown) return;
            
            const checkboxes = dropdown.querySelectorAll('input[type="checkbox"]:checked');
            const selectedItems = [];
            
            checkboxes.forEach(checkbox => {
                const label = checkbox.closest('label');
                const labelText = label.querySelector('.hvnly-property-checkbox-mlt-label').textContent;
                selectedItems.push({
                    text: labelText,
                    value: checkbox.value
                });
            });
            
            selectedContainer.textContent = '';
            selectedItems.forEach(item => {
                const itemElement = document.createElement('div');
                itemElement.className = 'hvnly-property-selected-single-item';
                // Use textContent — never inject label text via innerHTML (DOM XSS).
                itemElement.appendChild(document.createTextNode(item.text));
                const removeBtn = document.createElement('span');
                removeBtn.className = 'hvnly-property-selected-single-item-remove';
                removeBtn.setAttribute('data-value', item.value);
                removeBtn.textContent = '×';
                itemElement.appendChild(removeBtn);
                selectedContainer.appendChild(itemElement);
            });
            
            const self = this;
            selectedContainer.querySelectorAll('.hvnly-property-selected-single-item-remove').forEach(removeBtn => {
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const value = this.getAttribute('data-value');
                    const checkbox = dropdown.querySelector(`input[type="checkbox"][value="${value}"]`);
                    if (checkbox) {
                        checkbox.checked = false;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                        self.updateSelectedItemsDisplay(input);
                    }
                });
            });
        }

        // ======================
        // PROPERTY GALLERY CAROUSELS WITH BLUR REMOVAL
        // ======================
        initPropertyGalleryCarousels(options) {
            let opts = options;
            if (typeof options === 'boolean') {
                opts = { force: options, elementorMode: options };
            } else if (!options || typeof options !== 'object') {
                opts = {};
            }

            const root = opts.root && opts.root.querySelectorAll ? opts.root : document;
            const force = !!opts.force;
            const elementorAutoplay =
                !!opts.elementorMode ||
                !!(window.HvnlyElementorPreview && window.HvnlyElementorPreview.isPreviewContext && window.HvnlyElementorPreview.isPreviewContext());

            const carousels = root.querySelectorAll('.hvnly-property-carousel-gallery');
            
            carousels.forEach((carousel) => {
                if (force) {
                    if (carousel._hvnlyAutoplayInterval) {
                        clearInterval(carousel._hvnlyAutoplayInterval);
                        carousel._hvnlyAutoplayInterval = null;
                    }
                    carousel.removeAttribute('data-initialized');
                }

                if (carousel.hasAttribute('data-initialized')) {
                    return;
                }
                
                const track = carousel.querySelector('.hvnly-property-carousel-track');
                const slides = carousel.querySelectorAll('.hvnly-property-carousel-slide');
                const prevBtn = carousel.querySelector('.hvnly-property-carousel-prev-btn');
                const nextBtn = carousel.querySelector('.hvnly-property-carousel-next-btn');
                const indicators = carousel.querySelectorAll('.hvnly-property-carousel-indicator');

                if (!track) {
                    return;
                }

                let currentIndex = 0;
                const totalSlides = slides.length;
                const isSingleSlide = totalSlides <= 1;
                
                carousel.setAttribute('data-initialized', 'true');

                const loadImagesAndRemoveBlur = () => {
                    if (elementorAutoplay) {
                        slides.forEach((slide) => slide.classList.add('hvnly-image-loaded'));
                        return Promise.resolve();
                    }

                    const imagePromises = [];
                    
                    slides.forEach((slide, slideIndex) => {
                        const bgImage = slide.style.backgroundImage;
                        if (bgImage && bgImage !== 'none') {
                            const imageUrl = bgImage.slice(5, -2);
                            
                            const promise = new Promise((resolve) => {
                                const img = new Image();
                                img.onload = () => {
                                    setTimeout(() => {
                                        slide.classList.add('hvnly-image-loaded');
                                    }, 100 + (slideIndex * 50));
                                    resolve();
                                };
                                img.onerror = () => {
                                    setTimeout(() => {
                                        slide.classList.add('hvnly-image-loaded');
                                    }, 100 + (slideIndex * 50));
                                    resolve();
                                };
                                img.src = imageUrl;
                            });
                            
                            imagePromises.push(promise);
                        } else {
                            slide.classList.add('hvnly-image-loaded');
                        }
                    });
                    
                    return Promise.all(imagePromises);
                };

                loadImagesAndRemoveBlur();

                if (!isSingleSlide) {
                    const randomInterval = Math.floor(Math.random() * 5000) + 3000;

                    const updateCarousel = () => {
                        track.style.transform = `translateX(-${currentIndex * 100}%)`;
                        
                        indicators.forEach((indicator, idx) => {
                            if (idx === currentIndex) {
                                indicator.classList.add('hvnly-property-carousel-active');
                            } else {
                                indicator.classList.remove('hvnly-property-carousel-active');
                            }
                        });
                    };

                    const nextSlide = () => {
                        currentIndex = (currentIndex + 1) % totalSlides;
                        updateCarousel();
                    };

                    const prevSlide = () => {
                        currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
                        updateCarousel();
                    };

                    const randomSlide = () => {
                        let randomIndex;
                        do {
                            randomIndex = Math.floor(Math.random() * totalSlides);
                        } while (randomIndex === currentIndex && totalSlides > 1);
                        
                        currentIndex = randomIndex;
                        updateCarousel();
                    };

                    updateCarousel();

                    if (nextBtn) {
                        nextBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            nextSlide();
                        });
                    }

                    if (prevBtn) {
                        prevBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            prevSlide();
                        });
                    }

                    indicators.forEach((indicator, idx) => {
                        indicator.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            currentIndex = idx;
                            updateCarousel();
                        });
                    });

                    let startX = 0;
                    let currentX = 0;
                    let isDragging = false;

                    const handleTouchStart = (e) => {
                        startX = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
                        isDragging = true;
                        track.style.transition = 'none';
                    };

                    const handleTouchMove = (e) => {
                        if (!isDragging) return;
                        
                        currentX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
                        const diff = startX - currentX;
                        
                        track.style.transform = `translateX(calc(-${currentIndex * 100}% - ${diff}px))`;
                    };

                    const handleTouchEnd = (e) => {
                        if (!isDragging) return;
                        
                        const endX = e.type === 'touchend' ? e.changedTouches[0].clientX : e.clientX;
                        const diff = startX - endX;
                        const swipeThreshold = 50;
                        
                        track.style.transition = 'transform 0.3s ease';
                        
                        if (Math.abs(diff) > swipeThreshold) {
                            if (diff > 0) {
                                nextSlide();
                            } else {
                                prevSlide();
                            }
                        } else {
                            updateCarousel();
                        }
                        
                        isDragging = false;
                    };

                    carousel.addEventListener('mousedown', handleTouchStart);
                    document.addEventListener('mousemove', handleTouchMove);
                    document.addEventListener('mouseup', handleTouchEnd);

                    carousel.addEventListener('touchstart', handleTouchStart, { passive: true });
                    document.addEventListener('touchmove', handleTouchMove, { passive: true });
                    document.addEventListener('touchend', handleTouchEnd);

                    carousel._hvnlyAutoplayInterval = setInterval(() => {
                        randomSlide();
                    }, randomInterval);

                    // In Elementor preview the cursor often sits over the canvas — keep autoplay running.
                    if (!elementorAutoplay) {
                        carousel.addEventListener('mouseenter', () => {
                            if (carousel._hvnlyAutoplayInterval) {
                                clearInterval(carousel._hvnlyAutoplayInterval);
                                carousel._hvnlyAutoplayInterval = null;
                            }
                        });

                        carousel.addEventListener('mouseleave', () => {
                            if (carousel._hvnlyAutoplayInterval) {
                                clearInterval(carousel._hvnlyAutoplayInterval);
                            }
                            carousel._hvnlyAutoplayInterval = setInterval(() => {
                                randomSlide();
                            }, randomInterval);
                        });
                    }
                }
            });
        }

        bindGlobalEvents() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeAllModals();
                    this.closeAllDropdowns();
                }
            });
            
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.hvnly-property-tax-multichebox')) {
                    this.closeAllDropdowns();
                }
                
                if (!e.target.closest('.hvnly-modal')) {
                    this.closeAllModals();
                }
            });
        }

        closeAllModals() {
            const modals = document.querySelectorAll('.hvnly-modal');
            modals.forEach(modal => {
                modal.style.display = 'none';
            });
        }

        closeAllDropdowns() {
            const dropdowns = document.querySelectorAll('.hvnly-property-taxonomyDropdown-items');
            dropdowns.forEach(dropdown => {
                dropdown.style.display = 'none';
            });
        }
    }

    $(document).ready(function() {
        window.havenlyticsFrontend = new HavenlyticsFrontend();
    });

    const initHavenlyticsFilterToggle = function() {
        const filterToggle = document.getElementById('hvnly-property-filter-toggle');
        const additionalFilters = document.getElementById('hvnly-property-additional-filters');
        
        if (filterToggle && additionalFilters) {
            filterToggle.addEventListener('click', function() {
                const isActive = additionalFilters.classList.contains('active');
                
                if (isActive) {
                    additionalFilters.classList.remove('active');
                    filterToggle.classList.remove('active');
                } else {
                    additionalFilters.classList.add('active');
                    filterToggle.classList.add('active');
                }
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHavenlyticsFilterToggle);
    } else {
        initHavenlyticsFilterToggle();
    }

})(jQuery);