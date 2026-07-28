/**
 * Havenlytics Property Map JavaScript
 * 
 * @package     Havenlytics
 * @description Main map controller for property listings with support for Leaflet and Google Maps
 * @author      Havenlytics
 * @version     2.3.1 - Unified posts-per-page, Load More spinner, and pagination execution
 * @since       2.0.0
 * 
 * @features
 * - Multiple map providers (Leaflet/OpenStreetMap & Google Maps)
 * - Marker clustering for better performance with many properties
 * - Fullscreen map mode
 * - AJAX pagination with Load More and Traditional pagination
 * - Cumulative property loading for map view
 * - Dynamic filter integration
 * - Property popups with images and details
 * - Browser URL state management
 * - Responsive map container
 * - Elementor widget instance support
 */
(function($) {
    'use strict';

    const MAP_AJAX_TIMEOUT_MS = 30000;
    const HVNLY_PRIMARY_COLOR_VAR = 'var(--hvnly-primary-color)';

    function hvnlyResolveMarkerCssColor(configuredColor, usesBrandColor) {
        if (usesBrandColor || !configuredColor) {
            return HVNLY_PRIMARY_COLOR_VAR;
        }
        return configuredColor;
    }

    function hvnlyResolveMarkerHexColor(configuredColor, usesBrandColor) {
        if (usesBrandColor || !configuredColor) {
            const token = getComputedStyle(document.documentElement).getPropertyValue('--hvnly-primary-color').trim();
            return token || '#6C60FE';
        }
        return configuredColor;
    }

    /** @deprecated Use hvnlyResolveMarkerHexColor */
    function hvnlyResolvePrimaryColor(configuredColor) {
        return hvnlyResolveMarkerHexColor(configuredColor, false);
    }
    
    /**
     * HavenlyticsPropertyMap Class
     * 
     * Main controller class for map functionality including initialization,
     * marker management, AJAX loading, and user interactions.
     * 
     * @class HavenlyticsPropertyMap
     * @since 2.0.0
     */
    class HavenlyticsPropertyMap {
        
        /**
         * Constructor - Initializes map properties and configuration
         * 
         * @since 2.0.0
         */
        constructor() {
            // Core map properties
            this.map = null;                    // Map instance (Leaflet or Google)
            this.markers = [];                  // Array of marker objects
            this.isMapInitialized = false;      // Flag for map initialization status
            this.isLoading = false;              // Flag for map data loading state
            this.isLoadingContainer = false;     // Flag for map container AJAX state
            this.clusterGroups = [];             // Marker cluster groups for Leaflet
            
            // Pagination properties
            this.currentPage = 1;                // Current page number
            this.maxPages = 1;                   // Maximum number of pages
            this.allMapProperties = [];          // All properties loaded for map
            this.propertiesPerPage = 0;          // Resolved from container / global plugin setting
            this.propertiesByPage = {};          // Cache for properties by page number
            this.responsesByPage = {};           // Cache for full map AJAX payloads by page number
            this.maxLoadedPage = 0;              // Highest page number loaded
            this.refreshTimer = null;             // Timer for debounced refresh
            this.fetchTimer = null;               // Timer for fetch debounce
            this.currentInstanceId = null;        // Current widget instance ID
            
            // AJAX configuration
            this.nonce = this.resolveAjaxNonce();
            this.ajaxUrl = window.hvnly_PROPERTY_ajax?.ajax_url || window.hvnlyFrontend?.ajax_url || window.ajaxurl;
            this.debugMode = this.shouldEnableMapDebug();
            
            // Load map configuration from localized data
            this.mapConfig = window.hvnly_map_params || {};
            this.i18n = this.mapConfig.i18n || {};
            this.t = (key, fallback) => (this.i18n[key] || fallback || key);
            
            // Determine map provider based on settings and API key availability
            this.mapProvider = this.mapConfig.provider || 'leaflet';
            this.apiKey = this.mapConfig.api_key || '';
            
            if (this.mapProvider === 'google' && (!this.apiKey || this.apiKey === '')) {
                this.mapProvider = 'leaflet';
            }

            if (this.mapProvider === 'openstreetmap') {
                this.mapProvider = 'leaflet';
            }
            
            this.debugLog('constructor', {
                mapProvider: this.mapProvider,
                ajaxUrl: this.ajaxUrl,
                hasNonce: !!this.nonce,
                hasMapParams: !!window.hvnly_map_params,
                hasPropertyAjax: !!window.hvnly_PROPERTY_ajax,
                leafletLoaded: typeof L !== 'undefined',
                googleLoaded: typeof google !== 'undefined',
                mapConfig: this.mapConfig
            });
            
            // Track cumulative properties for Load More functionality
            this.cumulativeProperties = [];      // Accumulated properties across pages
            this.cumulativePagesLoaded = 0;       // Total pages loaded cumulatively
            this.totalFoundPosts = 0;              // Total number of properties found
            
            this.init();
        }

        /**
         * Resolve AJAX nonce from localized scripts.
         *
         * @returns {string}
         */
        resolveAjaxNonce() {
            return window.hvnly_PROPERTY_ajax?.nonce
                || window.hvnlyFrontend?.ajax_nonce
                || '';
        }

        /**
         * Enable verbose map logging from settings or URL flag.
         *
         * @returns {boolean}
         */
        shouldEnableMapDebug() {
            if (/[?&]hvnly_map_debug=1(?:&|$)/.test(window.location.search)) {
                return true;
            }

            return window.hvnly_map_params?.debug === '1'
                || window.hvnly_map_params?.debug === 1
                || window.hvnly_PROPERTY_ajax?.debug === '1'
                || window.hvnly_PROPERTY_ajax?.debug === 1;
        }

        /**
         * Console debug helper for map troubleshooting.
         *
         * @param {string} label
         * @param {*} payload
         */
        debugLog(label, payload = null) {
            if (!this.debugMode) {
                return;
            }

            // Avoid console.log in production bundles; debug mode uses console.debug.
            // eslint-disable-next-line no-console
            const log = (typeof console !== 'undefined' && typeof console.debug === 'function') ? console.debug : null;
            if (!log) {
                return;
            }

            if (payload !== null && payload !== undefined) {
                // eslint-disable-next-line no-console
                log('[Havenlytics Map]', label, payload);
            } else {
                // eslint-disable-next-line no-console
                log('[Havenlytics Map]', label);
            }
        }

        /**
         * Console warning for map failures (opt-in via map debug flag).
         *
         * @param {string} message
         * @param {*} details
         */
        mapWarn(message, details = null) {
            if (!this.debugMode) {
                return;
            }
            if (details !== null && details !== undefined) {
                // eslint-disable-next-line no-console
                console.warn('[Havenlytics Map]', message, details);
            } else {
                // eslint-disable-next-line no-console
                console.warn('[Havenlytics Map]', message);
            }
       }
        
        /**
         * Initialize all map components and event bindings
         * 
         * @since 2.0.0
         */
        init() {
            this.initializeComponents();
            this.bindEvents();
        }

        /**
         * Whether map view is the active listing view.
         *
         * @returns {boolean}
         */
        isMapViewActive() {
            if ($('.hvnly-property-view-btn.active[data-view="map"]').length) {
                return true;
            }

            const viewType = $('#hvnly-view-type-input').val() || $('#view-type-input').val();
            return viewType === 'map';
        }

        /**
         * Resolve the load-more widget instance ID from DOM or cached state.
         *
         * @param {string|null} preferredId
         * @returns {string|null}
         */
        resolveInstanceId(preferredId = null) {
            if (preferredId) {
                this.currentInstanceId = preferredId;
                return preferredId;
            }

            if (this.currentInstanceId) {
                return this.currentInstanceId;
            }

            const $container = $('.hvnly-property-load-more-container').first();
            if ($container.length) {
                const instanceId = $container.data('instance-id') || $container.attr('data-instance-id') || null;
                if (instanceId) {
                    this.currentInstanceId = instanceId;
                }
                return instanceId;
            }

            return null;
        }

        /**
         * Resolve property grid element for widget instance or archive fallback.
         *
         * @param {string|null} instanceId
         * @returns {HTMLElement|null}
         */
        resolvePropertyGridElement(instanceId = null) {
            return window.HvnlyDom.resolvePropertyGridElement(this.resolveInstanceId(instanceId));
        }

        /**
         * Resolve map placeholder element for widget instance or archive fallback.
         *
         * @param {string|null} instanceId
         * @returns {HTMLElement|null}
         */
        resolveMapPlaceholderElement(instanceId = null) {
            return window.HvnlyDom.resolveMapPlaceholder(this.resolveInstanceId(instanceId));
        }

        /**
         * Find the load-more container for the current or given instance.
         *
         * @param {string|null} instanceId
         * @returns {jQuery}
         */
        getLoadMoreContainer(instanceId = null) {
            return window.HvnlyDom.resolveLoadMoreContainer(instanceId || this.resolveInstanceId());
        }

        /**
         * Resolve posts-per-page from load-more container or global plugin setting.
         *
         * @param {string|null} instanceId
         * @returns {number}
         */
        resolvePostsPerPage(instanceId = null) {
            const $container = this.getLoadMoreContainer(instanceId || this.currentInstanceId);

            if ($container.length) {
                const fromContainer = parseInt(
                    $container.attr('data-posts-per-page') || $container.data('posts-per-page'),
                    10
                );

                if (!isNaN(fromContainer) && fromContainer > 0) {
                    this.propertiesPerPage = fromContainer;
                    return fromContainer;
                }
            }

            if (window.havenlyticsAJAX && window.havenlyticsAJAX.postsPerPage) {
                const fromMain = parseInt(window.havenlyticsAJAX.postsPerPage, 10);
                if (!isNaN(fromMain) && fromMain > 0) {
                    this.propertiesPerPage = fromMain;
                    return fromMain;
                }
            }

            const fromLocalized = parseInt(window.hvnly_PROPERTY_ajax?.properties_per_page, 10) ||
                parseInt(window.hvnly_PROPERTY_ajax?.per_page, 10);

            if (!isNaN(fromLocalized) && fromLocalized > 0) {
                this.propertiesPerPage = fromLocalized;
                return fromLocalized;
            }

            this.propertiesPerPage = 12;
            return this.propertiesPerPage;
        }

        /**
         * Show the same Load More spinner used by Grid/List views.
         *
         * @param {jQuery|null} $button
         */
        showLoadMoreLoading($button = null) {
            if ($button && $button.length) {
                $button.focus();
            }

            if (window.havenlyticsAJAX &&
                window.havenlyticsAJAX.modules &&
                window.havenlyticsAJAX.modules.ui &&
                typeof window.havenlyticsAJAX.modules.ui.showLoading === 'function') {
                window.havenlyticsAJAX.modules.ui.showLoading($button);
            }
        }

        /**
         * Hide Load More spinner and map overlay loading states.
         */
        hideLoadMoreLoading() {
            const mapLoading = document.querySelector('.hvnly-map-loading');
            if (mapLoading) {
                mapLoading.style.display = 'none';
            }

            const $grid = window.HvnlyDom.resolvePropertyGrid(this.resolveInstanceId());
            if ($grid && $grid.length) {
                $grid.removeClass('hvnly-loading');
            }

            if (window.havenlyticsAJAX &&
                window.havenlyticsAJAX.modules &&
                window.havenlyticsAJAX.modules.ui &&
                typeof window.havenlyticsAJAX.modules.ui.hideLoading === 'function') {
                window.havenlyticsAJAX.modules.ui.hideLoading();
            }

            this.isLoading = false;
        }
        
        /**
         * Initialize map components
         * 
         * @since 2.0.0
         */
        initializeComponents() {
            this.initMapView();
        }
        
        /**
         * Bind all event listeners for map interactions
         * - Load More button clicks
         * - Pagination clicks
         * - Filter changes
         * - Search form submissions
         * 
         * @since 2.0.0
         */
        bindEvents() {
            this.bindMapPaginationEvents();
            this.bindFilterEvents();
        }
        
        /**
         * Bind pagination events (Load More and Traditional pagination)
         * FIXED: Now supports widget instance IDs
         * 
         * @since 2.0.0
         */
        bindMapPaginationEvents() {
            const self = this;
            
            /**
             * Load More Button Click Handler - capture phase so grid pagination does not run in map view
             */
            document.addEventListener('click', function(e) {
                const button = e.target.closest('.hvnly-property-load-more-btn');
                if (!button) {
                    return;
                }

                if (!self.isMapViewActive()) {
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();

                const $button = $(button);
                const $container = $button.closest('.hvnly-property-load-more-container');
                const instanceId = $container.data('instance-id') || $button.data('instance-id');

                self.loadMoreMapProperties(instanceId, $button).catch(function() {
                    self.hideLoadMoreLoading();
                });
            }, true);
            
            /**
             * Load More Button Click Handler - FIXED to support instance IDs
             * Triggers loading of additional properties when Load More button is clicked
             */
            $(document).on('click', '.hvnly-property-load-more-btn', function(e) {
                if (self.isMapViewActive()) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return false;
                }
            });
            
            /**
             * Traditional Pagination Click Handler - FIXED to support instance IDs
             * Navigates to specific page when pagination number is clicked
             */
            $(document).on('click', '.hvnly-property-pagination-item', function(e) {
                if ($(this).closest('.hvnly-property--archive').length) {
                    return;
                }

                if (self.isMapViewActive()) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    
                    if ($(this).hasClass('active') || $(this).hasClass('dots')) return;
                    
                    const page = $(this).data('page');
                    const instanceId = $(this).data('instance-id');
                    
                    if (page) {
                        self.showMapPage(parseInt(page), instanceId);
                    }
                    
                    return false;
                }
            });
        }
        
        /**
         * Bind filter events for dynamic property filtering
         * 
         * @since 2.0.0
         */
        bindFilterEvents() {
            const self = this;
            
            /**
             * Sort Order Change Handler
             * Refreshes map when sort order is changed
             */
            $(document).on('change', '.hvnly-property-sort-select', function() {
                if (self.isMapViewActive()) {
                    self.resetMapState();
                    self.refreshMapWithCurrentFilters();
                }
            });
            
            /**
             * Filter Sidebar Change Handler
             * Handles changes to checkboxes and selects in filter sidebar
             */
            $(document).on('change', '#hvnly-filter-sidebar input, #hvnly-filter-sidebar select', function() {
                if (self.isMapViewActive()) {
                    self.resetMapState();
                    setTimeout(() => self.refreshMapWithCurrentFilters(), 100);
                }
            });
            
            /**
             * Taxonomy Multi-Select Change Handler
             * Handles changes to taxonomy dropdowns in top search
             */
            $(document).on('change', '.hvnly-property-tax-multichebox input', function() {
                if (self.isMapViewActive()) {
                    self.resetMapState();
                    setTimeout(() => self.refreshMapWithCurrentFilters(), 100);
                }
            });
            
            /**
             * Keyword Search Input Handler
             * Debounced search input for address/keyword filtering
             */
            $(document).on('input', '.hvnly-ajax-search input[name="address_keyword"]', function() {
                if (self.isMapViewActive()) {
                    self.resetMapState();
                    setTimeout(() => self.refreshMapWithCurrentFilters(), 300);
                }
            });
            
            /**
             * Search Form Submit Handler
             * Prevents default form submission and triggers AJAX search
             */
            $(document).on('submit', '#hvnly-property-search-form__box', function(e) {
                if (self.isMapViewActive()) {
                    e.preventDefault();
                    self.resetMapState();
                    self.refreshMapWithCurrentFilters();
                }
            });
            
            /**
             * Reset Filters Button Handler
             * Clears all filters and refreshes the map
             */
            $(document).on('click', '.hvnly-property-reset-filters-btn', function() {
                if (self.isMapViewActive()) {
                    if (self.refreshTimer) {
                        clearTimeout(self.refreshTimer);
                    }
                    
                    self.refreshTimer = setTimeout(() => {
                        self.resetMapState();
                        self.refreshMapWithCurrentFilters();
                    }, 500);
                }
            });
        }
        
        /**
         * Reset all map state variables
         * Called when filters change or map view is toggled
         * 
         * @since 2.2.2
         */
        resetMapState() {
            this.currentPage = 1;
            this.maxPages = 1;
            this.propertiesByPage = {};
            this.responsesByPage = {};
            this.maxLoadedPage = 0;
            this.cumulativeProperties = [];
            this.cumulativePagesLoaded = 0;
            this.totalFoundPosts = 0;
        }
        
        /**
         * Refresh map with current filter values
         * Debounced to prevent multiple rapid requests
         * 
         * @since 2.0.0
         */
        refreshMapWithCurrentFilters() {
            if (this.refreshTimer) {
                clearTimeout(this.refreshTimer);
            }
            
            this.refreshTimer = setTimeout(() => {
                this.clearMap();
                this.fetchPropertiesForMap();
            }, 300);
        }
        
        /**
         * Clear all markers and layers from the map
         * 
         * @since 2.0.0
         */
        clearMap() {
            if (this.map) {
                // Google Maps cleanup
                if (this.mapProvider === 'google') {
                    if (this.markers && this.markers.length) {
                        this.markers.forEach(marker => {
                            if (marker && typeof marker.setMap === 'function') {
                                marker.setMap(null);
                            }
                        });
                    }
                } 
                // Leaflet/OpenStreetMap cleanup
                else {
                    if (this.markers && this.markers.length) {
                        this.markers.forEach(marker => {
                            if (marker && typeof this.map.removeLayer === 'function') {
                                try {
                                    this.map.removeLayer(marker);
                                } catch(e) {}
                            }
                        });
                    }
                    
                    if (this.clusterGroups && this.clusterGroups.length) {
                        this.clusterGroups.forEach(group => {
                            if (group && typeof this.map.removeLayer === 'function') {
                                try {
                                    this.map.removeLayer(group);
                                } catch(e) {}
                            }
                        });
                    }
                }
                
                this.markers = [];
                this.clusterGroups = [];
                this.allMapProperties = [];
            }
        }
        
        /**
         * Fetch a single page of properties via AJAX
         * 
         * @param {number} page - Page number to fetch
         * @returns {Promise} Promise resolving to the AJAX response
         * @since 2.0.0
         */
        fetchSinglePage(page) {
            return new Promise((resolve, reject) => {
                this.resolvePostsPerPage(this.currentInstanceId);
                this.nonce = this.resolveAjaxNonce();

                const formData = new FormData();
                formData.append('action', 'hvnly_get_properties_for_map');
                formData.append('nonce', this.nonce);
                formData.append('page', page);
                formData.append('per_page', Math.max(1, this.propertiesPerPage || 12));
                
                // Add instance ID if available
                if (this.currentInstanceId) {
                    formData.append('instance_id', this.currentInstanceId);
                }
                
                // Collect current filter values
                const currentFilters = window.havenlyticsAJAX?.getFormData ? window.havenlyticsAJAX.getFormData() : null;
                if (currentFilters) {
                    for (let key in currentFilters) {
                        if (key !== 'action' && key !== 'nonce' && key !== 'page' && key !== 'per_page' && key !== 'instance_id') {
                            if (Array.isArray(currentFilters[key])) {
                                currentFilters[key].forEach(value => {
                                    formData.append(key + '[]', value);
                                });
                            } else {
                                formData.append(key, currentFilters[key]);
                            }
                        }
                    }
                } else {
                    this.collectAllFilterValues(formData);
                }

                const debugPayload = {};
                for (const [key, value] of formData.entries()) {
                    if (key === 'nonce') {
                        debugPayload[key] = value ? '[present]' : '[missing]';
                    } else {
                        debugPayload[key] = value;
                    }
                }
                this.debugLog('fetchSinglePage request', debugPayload);
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), MAP_AJAX_TIMEOUT_MS);
                
                fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal
                })
                .then(response => {
                    clearTimeout(timeoutId);
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    clearTimeout(timeoutId);
                    this.debugLog('fetchSinglePage response', data);
                    if (data.success) {
                        // Update maxPages from server response - CRITICAL for Load More
                        if (data.data.max_pages) {
                            this.maxPages = data.data.max_pages;
                        }
                        if (data.data.found_posts) {
                            this.totalFoundPosts = data.data.found_posts;
                        }
                        if (data.data.posts_per_page) {
                            this.propertiesPerPage = parseInt(data.data.posts_per_page, 10);
                        }
                    }
                    resolve(data);
                })
                .catch(error => {
                    clearTimeout(timeoutId);
                    this.debugLog('fetchSinglePage error', error);
                    reject(error);
                });
            });
        }
        
        /**
         * Load all properties cumulatively up to a target page
         * Used for Load More and Traditional pagination in map view
         * 
         * @param {number} targetPage - Target page number to load up to
         * @param {string|null} instanceId - Widget instance ID for Elementor
         * @param {Object} options - Loading options
         * @param {boolean} options.appendOnly - Load More: append markers for targetPage only
         * @since 2.0.0
         * @updated 2.2.9 - Fixed map bounds update after loading
         */
        async loadCumulativeToPage(targetPage, instanceId = null, options = {}) {
            if (targetPage < 1) {
                return;
            }

            const appendOnly = options.appendOnly === true;

            if (this.isLoading) {
                return;
            }

            // Store instance ID for this load operation
            this.resolveInstanceId(instanceId);
            this.resolvePostsPerPage(this.currentInstanceId);
            
            this.isLoading = true;
            
            // Initial map loads use the map overlay; Load More uses the shared button spinner.
            const mapLoading = document.querySelector('.hvnly-map-loading');
            if (!appendOnly && mapLoading) {
                mapLoading.style.display = 'flex';
            }
            
            try {
                if (appendOnly) {
                    const targetPageNum = parseInt(targetPage, 10);
                    let pageResponse = this.responsesByPage[targetPageNum] || null;
                    let newProperties = this.propertiesByPage[targetPageNum] || null;

                    if (!pageResponse) {
                        const data = await this.fetchSinglePage(targetPageNum);
                        if (data && data.success && data.data) {
                            pageResponse = data.data;
                            this.responsesByPage[targetPageNum] = pageResponse;
                            newProperties = Array.isArray(pageResponse.properties) ? pageResponse.properties : [];
                            if (newProperties.length) {
                                this.propertiesByPage[targetPageNum] = newProperties;
                            }
                        } else {
                            newProperties = [];
                        }
                    } else if (!Array.isArray(newProperties)) {
                        newProperties = Array.isArray(pageResponse.properties) ? pageResponse.properties : [];
                        if (newProperties.length) {
                            this.propertiesByPage[targetPageNum] = newProperties;
                        }
                    }

                    if (Array.isArray(newProperties) && newProperties.length > 0) {
                        if (!this.isMapInitialized || !this.map) {
                            await this.initializePropertiesMap(newProperties);
                            this.allMapProperties = newProperties.slice();
                        } else {
                            this.addPropertiesToMap(newProperties, { updateBounds: false });
                            this.allMapProperties = this.allMapProperties.concat(newProperties);
                        }
                    }

                    this.currentPage = targetPageNum;
                    this.maxLoadedPage = Math.max(this.maxLoadedPage, targetPageNum);
                    this.cumulativePagesLoaded = targetPageNum;

                    if (pageResponse) {
                        pageResponse.current_page = targetPageNum;
                        if (!pageResponse.instance_id && this.currentInstanceId) {
                            pageResponse.instance_id = this.currentInstanceId;
                        }
                        this.updateMainPaginationDisplay(pageResponse);
                    }
                } else {
                    let allProperties = [];
                    let pagesToLoad = [];
                    
                    // Determine which pages need to be fetched
                    for (let page = 1; page <= targetPage; page++) {
                        if (this.propertiesByPage[page]) {
                            allProperties = allProperties.concat(this.propertiesByPage[page]);
                        } else {
                            pagesToLoad.push(page);
                        }
                    }
                    
                    // Fetch missing pages
                    for (const page of pagesToLoad) {
                        const data = await this.fetchSinglePage(page);
                        if (data && data.success && data.data) {
                            this.responsesByPage[page] = data.data;
                            if (data.data.properties) {
                                this.propertiesByPage[page] = data.data.properties;
                                allProperties = allProperties.concat(data.data.properties);
                            }
                        } else if (page === 1 && data && !data.success) {
                            const errorMessage = typeof data.data === 'string'
                                ? data.data
                                : (this.t('unableToLoadMapProperties') || this.t('errorLoadingProperties') || '');
                            this.showMapError(errorMessage);
                            return;
                        }
                    }
                    
                    if (allProperties.length > 0) {
                        // Clear existing markers and add new ones
                        this.clearMap();
                        
                        // Initialize or update map with the properties
                        if (!this.isMapInitialized || !this.map) {
                            await this.initializePropertiesMap(allProperties);
                        } else {
                            this.addPropertiesToMap(allProperties);
                        }
                        
                        this.allMapProperties = allProperties;
                        
                        // Force map to update bounds after a short delay
                        // This ensures the map zooms to the correct location
                        setTimeout(() => {
                            this.updateMapBounds();
                        }, 300);
                    } else {
                        if (targetPage === 1) {
                            const pagePayload = this.responsesByPage[1] || {};
                            const foundPosts = parseInt(pagePayload.found_posts, 10) || 0;
                            const withCoords = parseInt(pagePayload.posts_with_coordinates, 10) || 0;
                            const scanned = parseInt(pagePayload.posts_scanned, 10) || 0;

                            let message = this.t('noValidLocationsForSearch') || this.t('noValidLocations');
                            if (foundPosts > 0 && withCoords === 0) {
                                message = (this.t('foundWithoutCoordinates') || '')
                                    .replace('%d', String(foundPosts))
                                    .replace('{count}', String(foundPosts));
                            } else if (foundPosts === 0) {
                                message = this.t('noPropertiesMatchedMapFilters') || this.t('noPropertiesForMap');
                            }

                            this.debugLog('loadCumulativeToPage empty result', {
                                foundPosts,
                                scanned,
                                withCoords,
                                pagePayload
                            });

                            this.showMapError(message, {
                                foundPosts,
                                scanned,
                                withCoords,
                                debug: pagePayload.debug || null
                            });
                        }
                    }
                    
                    this.currentPage = targetPage;
                    this.maxLoadedPage = Math.max(this.maxLoadedPage, targetPage);
                    this.cumulativePagesLoaded = targetPage;
                    
                    // Update pagination UI from the already-fetched target page response
                    const paginationPayload = this.responsesByPage[targetPage];
                    if (paginationPayload) {
                        paginationPayload.current_page = targetPage;
                        if (!paginationPayload.instance_id && this.currentInstanceId) {
                            paginationPayload.instance_id = this.currentInstanceId;
                        }
                        this.updateMainPaginationDisplay(paginationPayload);
                    }
                }
                
            } catch (error) {
                if (error.name === 'AbortError') {
                    this.showMapError(this.t('requestTimedOut'));
                } else {
                    this.showMapError(this.t('errorLoadingProperties') + error.message);
                }
            } finally {
                this.hideLoadMoreLoading();
            }
        }
        
        /**
         * Initialize map view when user clicks the map view button
         * 
         * @since 2.0.0
         */
        initMapView() {
            const self = this;
            let mapViewInitialized = false;
        
            /**
             * Map View Button Click Handler
             * Switches from grid/list view to map view
             */
            $(document).on('click', '.hvnly-property-view-btn[data-view="map"]', function(e) {
                e.preventDefault();
                
                if (self.isLoading || self.isLoadingContainer) {
                    return;
                }
            
                // Update active button state
                $('.hvnly-property-view-btn').removeClass('active');
                $(this).addClass('active');
            
                // Update hidden input
                $('#view-type-input').val('map');
            
                // Update browser URL
                if (window.havenlyticsAJAX && typeof window.havenlyticsAJAX.updateBrowserUrl === 'function') {
                    window.havenlyticsAJAX.updateBrowserUrl();
                } else {
                    self.updateBrowserUrlFallback();
                }
            
                // Show map without destroying an existing instance (filter/full reload still clears via refreshMapWithCurrentFilters)
                setTimeout(() => {
                    self.loadPropertyMap();
                }, 100);
            });
        
            // Check if map view is active on page load
            const activeViewBtn = document.querySelector('.hvnly-property-view-btn.active[data-view="map"]');
            if (activeViewBtn && !mapViewInitialized) {
                mapViewInitialized = true;
                setTimeout(() => {
                    self.loadPropertyMap();
                }, 500);
            }
        }
        
        /**
         * Fallback method to update browser URL when AJAX module is not available
         * 
         * @since 2.0.0
         */
        updateBrowserUrlFallback() {
            const url = new URL(window.location);
            url.searchParams.set('view_type', 'map');
            window.history.pushState({}, '', url);
        }
        
        /**
         * Load the property map container
         * Creates map container via AJAX or fallback
         * 
         * @since 2.0.0
         */
        loadPropertyMap() {
            const self = this;
        
            if (this.isLoadingContainer) {
                return;
            }
        
            this.isLoadingContainer = true;
        
            const propertyGrid = this.resolvePropertyGridElement();
            const mapPlaceholder = this.resolveMapPlaceholderElement();
        
            this.debugLog('loadPropertyMap', {
                hasPropertyGrid: !!propertyGrid,
                hasMapPlaceholder: !!mapPlaceholder,
                mapProvider: this.mapProvider,
                instanceId: this.currentInstanceId
            });

            if (!propertyGrid || !mapPlaceholder) {
                this.isLoadingContainer = false;
                this.showMapError(this.t('mapPlaceholderMissing'));
                return;
            }
        
            // Hide grid and show map placeholder
            propertyGrid.style.display = 'none';
            mapPlaceholder.style.display = 'block';
            
            // Check if map container already exists
            const existingMapView = mapPlaceholder.querySelector('.hvnly-map-view');
            const hasLiveMapContainer = !!document.getElementById('hvnly-properties-map')
                && !mapPlaceholder.querySelector('.hvnly-map-error');

            if (existingMapView && hasLiveMapContainer) {
                this.isLoadingContainer = false;
                if (this.map) {
                    if (this.mapProvider === 'google') {
                        setTimeout(() => this.updateMapBounds(), 100);
                    } else if (typeof this.map.invalidateSize === 'function') {
                        this.map.invalidateSize(true);
                        this.reflowLeafletMap();
                    } else {
                        this.reflowLeafletMap();
                    }
                }
                this.fetchPropertiesForMap();
                return;
            }
        
            // Create new map container via AJAX
            mapPlaceholder.innerHTML = '';
        
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                timeout: MAP_AJAX_TIMEOUT_MS,
                data: {
                    action: 'hvnly_render_map_container',
                    nonce: this.nonce,
                    provider: this.mapProvider
                },
                success: function(response) {
                    if (response.success && response.data.html) {
                        mapPlaceholder.innerHTML = response.data.html;
                        self.attachMapEvents();
                        self.waitForMapContainerReady().then(() => {
                            self.fetchPropertiesForMap();
                        });
                    } else {
                        self.createFallbackMapContainer(mapPlaceholder);
                        self.waitForMapContainerReady().then(() => {
                            self.fetchPropertiesForMap();
                        });
                    }
                    self.isLoadingContainer = false;
                },
                error: function() {
                    self.createFallbackMapContainer(mapPlaceholder);
                    self.waitForMapContainerReady().then(() => {
                        self.fetchPropertiesForMap();
                    });
                    self.isLoadingContainer = false;
                }
            });
        }
        
        /**
         * Attach map-specific event handlers (fullscreen button)
         * 
         * @since 2.0.0
         */
        attachMapEvents() {
            const self = this;
            const fullscreenBtn = document.querySelector('.hvnly-map-fullscreen-btn');
            if (fullscreenBtn) {
                const newBtn = fullscreenBtn.cloneNode(true);
                fullscreenBtn.parentNode.replaceChild(newBtn, fullscreenBtn);
                newBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.toggleFullscreen();
                });
            }
        }
        
        /**
         * Create fallback map container when AJAX fails
         * 
         * @param {HTMLElement} container - Container element for the map
         * @since 2.0.0
         */
        createFallbackMapContainer(container) {
            if (!container) {
                container = this.resolveMapPlaceholderElement();
            }
            if (!container) return;
            
            const showFullscreen = this.mapConfig.show_fullscreen !== false;
            const showZoomControl = this.mapConfig.show_zoom_control !== false;
            const showScrollWheel = this.mapConfig.show_scroll_wheel !== false;
            const mapZoom = this.mapConfig.zoom_level || 12;
            
            let mapHtml = '';
            
            if (this.mapProvider === 'google') {
                mapHtml = `
                    <div class="hvnly-map-view hvnly-google-map" id="hvnly-map-view-google" data-provider="google" data-zoom="${mapZoom}" data-scroll-wheel="${showScrollWheel}" style="height: 550px; min-height: 550px;">
                        <div class="hvnly-map-container" style="height: 100%;">
                            <div id="hvnly-properties-map" class="hvnly-properties-map" data-zoom-control="${showZoomControl}" style="height: 100%; width: 100%;"></div>
                            ${showFullscreen ? `<button id="hvnly-google-map-fullscreen-btn" class="hvnly-map-fullscreen-btn hvnly-google-map-fullscreen-btn" title="${this.t('toggleFullscreen')}" data-fullscreen-target="hvnly-properties-map"><i class="fas fa-expand"></i></button>` : ''}
                            <div class="hvnly-map-loading" style="display: none;">
                                <div class="hvnly-map-loader"><i class="fas fa-map-marked-alt"></i><span>Loading Properties Map...</span></div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                mapHtml = `
                    <div class="hvnly-map-view hvnly-leaflet-map" id="hvnly-map-view-leaflet" data-provider="leaflet" data-zoom="${mapZoom}" data-scroll-wheel="${showScrollWheel}" style="height: 550px; min-height: 550px;">
                        <div class="hvnly-map-container" style="height: 100%;">
                            <div id="hvnly-properties-map" class="hvnly-properties-map" data-zoom-control="${showZoomControl}" style="height: 100%; width: 100%;"></div>
                            ${showFullscreen ? `<button id="hvnly-leaflet-map-fullscreen-btn" class="hvnly-map-fullscreen-btn hvnly-leaflet-map-fullscreen-btn" title="${this.t('toggleFullscreen')}" data-fullscreen-target="hvnly-properties-map"><i class="fas fa-expand"></i></button>` : ''}
                            <div class="hvnly-map-loading" style="display: none;">
                                <div class="hvnly-map-loader"><i class="fas fa-map-marked-alt"></i><span>Loading Properties Map...</span></div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = mapHtml;
            this.attachMapEvents();
        }
        
        /**
         * Remove existing map view from DOM
         * 
         * @since 2.0.0
         */
        removeExistingMapView() {
            if (this.map && typeof this.map.remove === 'function') {
                try {
                    this.map.remove();
                } catch (removeError) {}
            }

            this.map = null;
            this.markers = [];
            this.clusterGroups = [];
            this.isMapInitialized = false;

            const mapPlaceholder = this.resolveMapPlaceholderElement();
            if (mapPlaceholder) {
                mapPlaceholder.innerHTML = '';
            }

            this.hideLoadMoreLoading();
        }
        
        /**
         * Toggle fullscreen mode for the map
         * 
         * @since 2.0.0
         */
        toggleFullscreen() {
            const mapContainer = document.getElementById('hvnly-properties-map');
            
            if (!mapContainer) {
                return;
            }
            
            let fullscreenBtn;
            if (this.mapProvider === 'google') {
                fullscreenBtn = document.getElementById('hvnly-google-map-fullscreen-btn');
            } else {
                fullscreenBtn = document.getElementById('hvnly-leaflet-map-fullscreen-btn');
            }
            
            if (!fullscreenBtn) {
                return;
            }
            
            if (!document.fullscreenElement) {
                // Enter fullscreen
                if (mapContainer.requestFullscreen) {
                    mapContainer.requestFullscreen();
                } else if (mapContainer.webkitRequestFullscreen) {
                    mapContainer.webkitRequestFullscreen();
                } else if (mapContainer.msRequestFullscreen) {
                    mapContainer.msRequestFullscreen();
                } else if (mapContainer.mozRequestFullScreen) {
                    mapContainer.mozRequestFullScreen();
                }
                
                fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
                fullscreenBtn.title = this.t('exitFullscreen');
            } else {
                // Exit fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                }
                
                fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
                fullscreenBtn.title = this.t('toggleFullscreen');
            }
        }
        
        /**
         * Fetch properties for map display - FIXED to load from page 1
         * Uses cumulative loading up to current page
         * 
         * @since 2.0.0
         * @updated 2.2.9 - Fixed map initialization with correct coordinates
         */
        fetchPropertiesForMap() {
            const self = this;
            
            // Clear any existing timeout
            if (this.fetchTimer) {
                clearTimeout(this.fetchTimer);
            }
            
            this.fetchTimer = setTimeout(() => {
                const mapContainer = document.getElementById('hvnly-properties-map');
                if (!mapContainer) {
                    this.hideLoadMoreLoading();
                    this.showMapError(this.t('mapContainerMissing'));
                    return;
                }
                
                const ajaxUrl = this.ajaxUrl || window.hvnly_PROPERTY_ajax?.ajax_url || window.hvnlyFrontend?.ajax_url || window.ajaxurl;
                this.nonce = this.resolveAjaxNonce();
                
                if (!ajaxUrl) {
                    this.hideLoadMoreLoading();
                    this.showMapError(this.t('mapConfigError'));
                    return;
                }
                
                // Reset map state before loading new properties
                this.resetMapState();

                const instanceId = this.resolveInstanceId();
                this.resolvePostsPerPage(instanceId);
                
                // Load properties starting from page 1 with proper instance ID
                // This ensures we get the correct property coordinates from the start
                this.loadCumulativeToPage(1, instanceId);
                
            }, 150);
        }
        
        /**
         * Collect all filter values from search form and sidebar
         * 
         * @param {FormData} formData - FormData object to append values to
         * @since 2.0.0
         */
        collectAllFilterValues(formData) {
            // Collect from main search form
            const searchForm = $('#hvnly-property-search-form__box');
            if (searchForm.length) {
                const formElements = searchForm.find('input, select');
                formElements.each(function() {
                    const $element = $(this);
                    const name = $element.attr('name');
                    
                    if (name) {
                        if ($element.is(':checkbox')) {
                            if ($element.is(':checked')) {
                                formData.append(name, $element.val());
                            }
                        } else if ($element.is('select')) {
                            const value = $element.val();
                            if (value) {
                                formData.append(name, value);
                            }
                        } else {
                            const value = $element.val();
                            if (value) {
                                formData.append(name, value);
                            }
                        }
                    }
                });
            }
            
            // Collect from filter sidebar
            const filterSidebar = $('#hvnly-filter-sidebar');
            if (filterSidebar.length) {
                // Property IDs
                filterSidebar.find('input[name="property_ids[]"]:checked').each(function() {
                    formData.append('property_ids[]', $(this).val());
                });
                
                // Badges
                filterSidebar.find('input[name="hvnly_prop_badges[]"]:checked').each(function() {
                    formData.append('hvnly_prop_badges[]', $(this).val());
                });

                // Price range
                const minPrice = filterSidebar.find('select[name="min_price"]').val();
                const maxPrice = filterSidebar.find('select[name="max_price"]').val();
                if (minPrice) formData.append('min_price', minPrice);
                if (maxPrice) formData.append('max_price', maxPrice);
                
                // Bedrooms, Bathrooms, Reception Rooms
                const bedrooms = filterSidebar.find('select[name="bedrooms"]').val();
                const reception_rooms = filterSidebar.find('select[name="reception_rooms"]').val();
                const bathrooms = filterSidebar.find('select[name="bathrooms"]').val();
                if (bedrooms) formData.append('bedrooms', bedrooms);
                if (bathrooms) formData.append('bathrooms', bathrooms);
                if (reception_rooms) formData.append('reception_rooms', reception_rooms);
                
                // Garages
                const garages = filterSidebar.find('select[name="garages"]').val();
                if (garages) formData.append('garages', garages);
                
                // Amenities
                filterSidebar.find('input[name="amenities[]"]:checked').each(function() {
                    formData.append('amenities[]', $(this).val());
                });
                
                const taxonomies = ['hvnly_prop_types', 'hvnly_prop_locations', 'hvnly_prop_features', 'hvnly_prop_reviews', 'hvnly_prop_tags', 'hvnly_prop_badges', 'hvnly_prop_status'];
                
                taxonomies.forEach(taxonomy => {
                    filterSidebar.find(`input[name="${taxonomy}[]"]:checked`).each(function() {
                        formData.append(`${taxonomy}[]`, $(this).val());
                    });
                });
            }
            
            // Collect from top search dropdowns
            const topSearchDropdowns = $('.hvnly-property-tax-multichebox');
            if (topSearchDropdowns.length) {
                topSearchDropdowns.find('input[name="property_type[]"]:checked').each(function() {
                    formData.append('hvnly_prop_types[]', $(this).val());
                });
                
                topSearchDropdowns.find('input[name="location[]"]:checked').each(function() {
                    formData.append('hvnly_prop_locations[]', $(this).val());
                });
            }
            
            // Sort order
            const sortSelect = $('.hvnly-property-sort-select');
            if (sortSelect.length && sortSelect.val()) {
                formData.append('orderby', sortSelect.val());
            }
            
            // View type
            const activeView = $('.hvnly-property-view-btn.active');
            if (activeView.length) {
                formData.append('view_type', activeView.data('view'));
            } else {
                formData.append('view_type', 'grid');
            }
            
            // Department
            const department = $('#department').val();
            if (department) {
                formData.append('department', department);
            }
        }
        
        /**
         * Update main pagination display (results count and pagination controls)
         * 
         * @param {Object} data - Response data from server
         * @since 2.0.0
         */
        updateMainPaginationDisplay(data) {
            const instanceId = this.resolveInstanceId(data.instance_id || null);
            const payload = Object.assign({}, data, {
                instance_id: instanceId || data.instance_id || null
            });

            if (payload.max_pages) {
                this.maxPages = parseInt(payload.max_pages, 10);
            }

            if (payload.current_page) {
                this.currentPage = parseInt(payload.current_page, 10);
            }

            if (window.havenlyticsAJAX && window.havenlyticsAJAX.modules && window.havenlyticsAJAX.modules.search && instanceId) {
                window.havenlyticsAJAX.modules.search.currentInstanceId = instanceId;
            }

            this.applyLoadMoreContainerState(payload, instanceId);
            
            if (window.havenlyticsAJAX && window.havenlyticsAJAX.modules &&
                window.havenlyticsAJAX.modules.pagination &&
                typeof window.havenlyticsAJAX.modules.pagination.updatePaginationDisplay === 'function') {
                
                window.havenlyticsAJAX.modules.pagination.updatePaginationDisplay(payload);
            } else if (window.HvnlyDom && typeof window.HvnlyDom.syncListingState === 'function') {
                window.HvnlyDom.syncListingState(payload);
            } else {
                const resultsCount = window.HvnlyDom
                    ? window.HvnlyDom.resolveResultsCountHeader(instanceId)
                    : $('#hvnly-results-count');
                if (resultsCount.length && payload.results_count_html) {
                    resultsCount.replaceWith(payload.results_count_html);
                }

                const paginationContainer = window.HvnlyDom
                    ? window.HvnlyDom.resolvePaginationContainer(instanceId)
                    : $('#hvnly-property-pagination');
                if (paginationContainer.length && payload.pagination_html) {
                    paginationContainer.html(payload.pagination_html);
                    paginationContainer.show();
                }
            }
            
            this.updateMapLoadMoreCount(payload, instanceId);
        }

        /**
         * Update load-more container attributes and visibility for map responses.
         *
         * @param {Object} data
         * @param {string|null} instanceId
         */
        applyLoadMoreContainerState(data, instanceId = null) {
            const $container = this.getLoadMoreContainer(instanceId);
            if (!$container.length) {
                return;
            }

            if (data.max_pages) {
                this.maxPages = parseInt(data.max_pages, 10);
                $container.data('max-pages', this.maxPages);
                $container.attr('data-max-pages', this.maxPages);
            }

            const currentPage = parseInt(data.current_page, 10) || this.maxLoadedPage || this.currentPage || 1;
            this.currentPage = currentPage;
            $container.data('current-page', currentPage);
            $container.attr('data-current-page', currentPage);

            if (data.found_posts) {
                $container.data('found-posts', data.found_posts);
                $container.attr('data-found-posts', data.found_posts);
            }

            if (data.posts_per_page) {
                this.propertiesPerPage = parseInt(data.posts_per_page, 10);
                $container.data('posts-per-page', this.propertiesPerPage);
                $container.attr('data-posts-per-page', this.propertiesPerPage);
            }

            const shouldShowLoadMore = currentPage < this.maxPages;
            if (shouldShowLoadMore) {
                $container.show();
            } else {
                $container.hide();
            }
        }
        
        /**
         * Update Load More button visibility and count display
         * 
         * @param {Object} data - Response data from server
         * @since 2.0.0
         * @updated 2.2.2 - Fixed maxPages update
         */
        updateMapLoadMoreCount(data, instanceId = null) {
            const resolvedId = instanceId || this.resolveInstanceId();
            let loadedCount = resolvedId ? $(`#loadedCount-${resolvedId}`) : $();
            let totalCount = resolvedId ? $(`#totalCount-${resolvedId}`) : $();

            if (!loadedCount.length) {
                loadedCount = $('#loadedCount');
            }
            if (!totalCount.length) {
                totalCount = $('#totalCount');
            }

            const $container = this.getLoadMoreContainer(resolvedId);
            if (!loadedCount.length && $container.length) {
                loadedCount = $container.find('.hvnly-property-load-more-info span').first();
            }
            if (!totalCount.length && $container.length) {
                totalCount = $container.find('.hvnly-property-load-more-info span').last();
            }
            
            if (loadedCount.length && data.found_posts) {
                const displayLoaded = data.loaded_count ||
                    Math.min(this.maxLoadedPage * (parseInt(data.posts_per_page, 10) || this.propertiesPerPage), data.found_posts);
                loadedCount.text(displayLoaded);
            }
            
            if (totalCount.length && data.found_posts) {
                totalCount.text(data.found_posts);
            }
        }
        
        /**
         * Update Load More section visibility
         * 
         * @param {Object} data - Response data from server
         * @since 2.0.0
         * @updated 2.2.2 - Fixed maxPages synchronization
         */
        updateLoadMoreSection(data) {
            const instanceId = this.resolveInstanceId(data.instance_id || null);
            this.applyLoadMoreContainerState(data, instanceId);
            this.updateMapLoadMoreCount(data, instanceId);
        }
        
        /**
         * Sync map pagination counters from the load-more container / last API response.
         *
         * @param {string|null} instanceId
         */
        syncPaginationStateFromContainer(instanceId = null) {
            this.resolveInstanceId(instanceId);

            const cachedPage = this.maxLoadedPage || 1;
            const cachedResponse = this.responsesByPage[cachedPage] || this.responsesByPage[1];

            if (cachedResponse) {
                if (cachedResponse.max_pages) {
                    this.maxPages = parseInt(cachedResponse.max_pages, 10);
                }
                if (cachedResponse.found_posts) {
                    this.totalFoundPosts = parseInt(cachedResponse.found_posts, 10);
                }
            }

            if (this.maxLoadedPage > 0) {
                this.currentPage = this.maxLoadedPage;
                return;
            }

            const $container = this.getLoadMoreContainer(this.currentInstanceId);
            if (!$container.length) {
                return;
            }

            const maxPages = parseInt($container.attr('data-max-pages') || $container.data('max-pages'), 10);
            const currentPage = parseInt($container.attr('data-current-page') || $container.data('current-page'), 10);

            if (!isNaN(maxPages) && maxPages > 0) {
                this.maxPages = maxPages;
            }

            if (!isNaN(currentPage) && currentPage > 0) {
                this.currentPage = currentPage;
            }
        }

        /**
         * Clear grid load-more button loading state when map view owns the request.
         * @deprecated Use hideLoadMoreLoading()
         */
        clearGridLoadMoreLoadingState() {
            this.hideLoadMoreLoading();
        }

        /**
         * @deprecated Use hideLoadMoreLoading()
         */
        clearAllLoadMoreLoadingState() {
            this.hideLoadMoreLoading();
        }
        
        /**
         * Load more properties for map view - FIXED to support instance ID
         * Increments current page and loads cumulative properties
         * 
         * @param {string|null} instanceId - Widget instance ID for Elementor
         * @param {jQuery|null} $button - Clicked Load More button
         * @since 2.0.0
         * @updated 2.3.1 - Shared spinner + reliable page increment
         */
        async loadMoreMapProperties(instanceId = null, $button = null) {
            try {
                this.resolveInstanceId(instanceId);
                this.resolvePostsPerPage(this.currentInstanceId);
                this.syncPaginationStateFromContainer(this.currentInstanceId);

                const loadedPage = this.maxLoadedPage || parseInt(this.currentPage, 10) || 1;
                const maxPages = parseInt(this.maxPages, 10) || 1;
                const nextPage = loadedPage + 1;

                if (nextPage > maxPages) {
                    return;
                }

                if (this.isLoading) {
                    return;
                }

                this.showLoadMoreLoading($button);

                await this.loadCumulativeToPage(nextPage, this.currentInstanceId, { appendOnly: true });
            } catch (error) {
                if (error.name === 'AbortError') {
                    this.showMapError(this.t('requestTimedOut'));
                } else {
                    this.showMapError(this.t('errorLoadingProperties') + error.message);
                }
            } finally {
                this.hideLoadMoreLoading();
            }
        }
        
        /**
         * Navigate to specific page in map view - FIXED to support instance ID
         * 
         * @param {number} page - Page number to navigate to
         * @param {string|null} instanceId - Widget instance ID for Elementor
         * @since 2.0.0
         * @updated 2.2.8 - Added instanceId parameter
         */
        showMapPage(page, instanceId = null) {
            // Store instance ID for the load operation
            if (instanceId) {
                this.currentInstanceId = instanceId;
            }
            this.loadCumulativeToPage(page, this.currentInstanceId);
        }
        
        /**
         * Wait until the map container is visible and has layout dimensions.
         *
         * Leaflet computes marker positions from container size at init time.
         * If the map tab was hidden, markers appear stacked in the top-left until invalidateSize().
         *
         * @param {number} maxAttempts Maximum polling attempts.
         * @param {number} intervalMs   Delay between attempts in ms.
         * @returns {Promise<HTMLElement|null>}
         */
        waitForMapContainerReady(maxAttempts = 30, intervalMs = 50) {
            return new Promise((resolve) => {
                let attempts = 0;

                const check = () => {
                    const mapPlaceholder = this.resolveMapPlaceholderElement();
                    const mapContainer = document.getElementById('hvnly-properties-map');

                    if (!mapContainer) {
                        if (++attempts >= maxAttempts) {
                            resolve(null);
                            return;
                        }
                        setTimeout(check, intervalMs);
                        return;
                    }

                    const placeholderVisible = !mapPlaceholder
                        || (mapPlaceholder.style.display !== 'none' && mapPlaceholder.offsetParent !== null);
                    const containerVisible = mapContainer.offsetParent !== null
                        || mapContainer.getClientRects().length > 0;
                    const hasSize = mapContainer.offsetWidth > 200 && mapContainer.offsetHeight > 200;

                    if (placeholderVisible && containerVisible && hasSize) {
                        resolve(mapContainer);
                        return;
                    }

                    if (++attempts >= maxAttempts) {
                        resolve(mapContainer);
                        return;
                    }

                    setTimeout(check, intervalMs);
                };

                check();
            });
        }

        /**
         * Validate latitude/longitude for map display.
         *
         * @param {number} lat
         * @param {number} lng
         * @returns {boolean}
         */
        isValidMapCoordinate(lat, lng) {
            if (isNaN(lat) || isNaN(lng)) {
                return false;
            }
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) {
                return false;
            }
            if (Math.abs(lat) < 0.0001 && Math.abs(lng) < 0.0001) {
                return false;
            }
            return true;
        }

        /**
         * Wait for layout stabilization using double requestAnimationFrame.
         *
         * @returns {Promise<void>}
         * @since 3.0.1
         */
        waitForLayoutStable() {
            return new Promise((resolve) => {
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        resolve();
                    });
                });
            });
        }

        /**
         * Invalidate Leaflet map size and wait for layout before marker operations.
         *
         * @returns {Promise<void>}
         * @since 3.0.1
         */
        async prepareLeafletMapForMarkers() {
            if (!this.map || this.mapProvider === 'google' || typeof L === 'undefined') {
                return;
            }

            if (typeof this.map.invalidateSize === 'function') {
                this.map.invalidateSize(true);
            }

            await this.waitForLayoutStable();
        }

        /**
         * Refresh marker clusters after the map container layout has stabilized.
         *
         * @since 3.0.1
         */
        refreshLeafletClustersAfterLayout() {
            if (!this.map || this.mapProvider === 'google' || typeof L === 'undefined') {
                return;
            }

            const runRefresh = () => {
                if (!this.map) {
                    return;
                }
                if (typeof this.map.invalidateSize === 'function') {
                    this.map.invalidateSize(true);
                }
            };

            this.waitForLayoutStable().then(() => {
                runRefresh();
                setTimeout(runRefresh, 300);
            });
        }

        /**
         * Re-measure Leaflet map after the tab becomes visible and refit markers.
         *
         * @since 3.0.0
         */
        reflowLeafletMap() {
            if (!this.map || this.mapProvider === 'google' || typeof L === 'undefined') {
                return;
            }

            const runReflow = () => {
                if (!this.map) {
                    return;
                }
                if (typeof this.map.invalidateSize === 'function') {
                    this.map.invalidateSize(true);
                }
                this.fitLeafletToMarkers();
            };

            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    runReflow();
                    setTimeout(runReflow, 200);
                });
            });
        }

        /**
         * Initialize map with properties (Leaflet or Google Maps)
         *
         * @param {Array} properties - Array of property objects with coordinates
         * @since 2.0.0
         */
        async initializePropertiesMap(properties) {
            const mapContainer = document.getElementById('hvnly-properties-map');
            
            if (!mapContainer) {
                this.showMapError(this.t('mapContainerInitMissing'));
                return;
            }
            
            // Check if required libraries are loaded
            if (this.mapProvider === 'google' && typeof google === 'undefined') {
                this.showMapError(this.t('googleMapsMissing'));
                return;
            } else if ((this.mapProvider === 'leaflet' || this.mapProvider === 'openstreetmap') && typeof L === 'undefined') {
                this.showMapError(this.t('mapLibraryMissing'));
                return;
            }
            
            if (!properties || !properties.length) {
                this.showMapError(this.t('noPropertiesForMap'));
                return;
            }
            
            try {
                // Clean up existing map
                if (this.map) {
                    if (this.mapProvider === 'google') {
                        this.markers.forEach(marker => {
                            if (marker && typeof marker.setMap === 'function') {
                                marker.setMap(null);
                            }
                        });
                        this.markers = [];
                    } else {
                        if (this.map && typeof this.map.remove === 'function') {
                            this.map.remove();
                        }
                        this.markers = [];
                        this.clusterGroups = [];
                    }
                    this.map = null;
                }
                
                // Filter valid properties (with coordinates)
                const validProperties = properties.filter(property => {
                    if (property.lat === undefined || property.lat === null || property.lng === undefined || property.lng === null || property.lat === '' || property.lng === '') {
                        return false;
                    }

                    const lat = parseFloat(property.lat);
                    let lng = parseFloat(property.lng);

                    if (lng > 180) lng -= 360;
                    if (lng < -180) lng += 360;
                    property.lng = lng;

                    return this.isValidMapCoordinate(lat, lng);
                });
                
                if (validProperties.length === 0) {
                    this.showMapError(this.t('noValidLocations'));
                    return;
                }
                
                this.allMapProperties = validProperties;
                
                // Initialize appropriate map provider
                if (this.mapProvider === 'google') {
                    this.initializeGoogleMap(validProperties);
                } else {
                    await this.initializeLeafletMap(validProperties);
                }
                
                this.isMapInitialized = true;
                
            } catch (error) {
                this.showMapError(this.t('errorInitMap') + error.message);
            }
        }
        
        /**
         * Initialize Google Map with properties
         * 
         * @param {Array} properties - Array of property objects
         * @since 2.0.0
         */
        initializeGoogleMap(properties) {
            const mapContainer = document.getElementById('hvnly-properties-map');
            const firstProperty = properties[0];
            const centerLat = parseFloat(firstProperty.lat);
            const centerLng = parseFloat(firstProperty.lng);
            const defaultZoom = parseInt(this.mapConfig.zoom_level) || 12;
            const mapType = this.mapConfig.google_map_type || 'roadmap';
            
            this.map = new google.maps.Map(mapContainer, {
                center: { lat: centerLat, lng: centerLng },
                zoom: defaultZoom,
                mapTypeId: mapType,
                fullscreenControl: false,
                zoomControl: this.mapConfig.show_zoom_control !== false,
                zoomControlOptions: { position: google.maps.ControlPosition.TOP_RIGHT },
                scrollwheel: this.mapConfig.show_scroll_wheel !== false
            });
            
            this.addGoogleMarkersToMap(properties);
            this.addGoogleMapControls();
        }
        
        /**
         * Ensure the Leaflet container is clean before creating a new map instance.
         *
         * @param {HTMLElement|null} mapContainer
         */
        prepareLeafletContainer(mapContainer) {
            if (!mapContainer || typeof L === 'undefined') {
                return;
            }

            if (mapContainer._leaflet_id) {
                try {
                    const existingMap = L.DomUtil.get(mapContainer);
                    if (existingMap && typeof existingMap.remove === 'function') {
                        existingMap.remove();
                    }
                } catch (cleanupError) {}

                mapContainer.innerHTML = '';
                mapContainer.className = 'hvnly-properties-map';
                mapContainer.removeAttribute('tabindex');
            }
        }

        /**
         * Initialize Leaflet/OpenStreetMap with properties
         *
         * @param {Array} properties - Array of property objects
         * @since 2.0.0
         */
        async initializeLeafletMap(properties) {
            await this.waitForMapContainerReady();

            const mapContainer = document.getElementById('hvnly-properties-map');
            const firstProperty = properties[0];
            const centerLat = parseFloat(firstProperty.lat);
            const centerLng = parseFloat(firstProperty.lng);
            const defaultZoom = parseInt(this.mapConfig.zoom_level, 10) || 12;

            const tileUrl = this.mapConfig.osm_tile_url || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
            const attribution = this.mapConfig.osm_attribution || '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

            this.prepareLeafletContainer(mapContainer);

            if (mapContainer) {
                mapContainer.style.height = '550px';
                mapContainer.style.minHeight = '550px';
            }

            await this.waitForMapContainerReady();

            this.map = L.map('hvnly-properties-map', {
                zoomControl: false,
                scrollWheelZoom: this.mapConfig.show_scroll_wheel !== false
            });

            const tileLayer = L.tileLayer(tileUrl, {
                attribution: attribution,
                maxZoom: 19,
            });
            tileLayer.addTo(this.map);

            this.map.setView([centerLat, centerLng], defaultZoom);

            if (this.mapConfig.show_zoom_control !== false) {
                L.control.zoom({ position: 'topright' }).addTo(this.map);
            }

            const self = this;

            await new Promise((resolve) => {
                let settled = false;
                const finish = () => {
                    if (!settled) {
                        settled = true;
                        resolve();
                    }
                };
                const timeoutId = setTimeout(finish, 5000);

                if (!self.map || typeof self.map.whenReady !== 'function') {
                    clearTimeout(timeoutId);
                    finish();
                    return;
                }

                self.map.whenReady(() => {
                    self.prepareLeafletMapForMarkers()
                        .then(() => {
                            self.addMarkersToMap(properties, { skipAutoFit: true });
                            self.addMapControls(self.map);
                            self.refreshLeafletClustersAfterLayout();
                        })
                        .catch((initError) => {
                            self.showMapError(self.t('errorInitMarkers') + initError.message);
                        })
                        .finally(() => {
                            clearTimeout(timeoutId);
                            finish();
                        });
                });
            });

            this.reflowLeafletMap();
        }
        
        /**
         * Add properties to existing map (for cumulative loading)
         * 
         * @param {Array} properties - Array of property objects to add
         * @param {Object} options - Options
         * @param {boolean} options.updateBounds - Whether to refit map bounds after adding markers
         * @since 2.0.0
         */
        addPropertiesToMap(properties, options = {}) {
            if (!this.map) {
                return;
            }
            
            const validProperties = properties.filter(property => {
                if (property.lat === undefined || property.lat === null || property.lng === undefined || property.lng === null || property.lat === '' || property.lng === '') {
                    return false;
                }

                const lat = parseFloat(property.lat);
                let lng = parseFloat(property.lng);

                if (lng > 180) lng -= 360;
                if (lng < -180) lng += 360;
                property.lng = lng;

                return this.isValidMapCoordinate(lat, lng);
            });
            
            if (validProperties.length === 0) {
                return;
            }
            
            if (this.mapProvider === 'google') {
                this.addGoogleMarkersToMap(validProperties);
            } else {
                this.addMarkersToMap(validProperties);
            }
            
            if (options.updateBounds !== false) {
                setTimeout(() => this.updateMapBounds(), 100);
            }
        }
        
        /**
         * Add Google Maps markers for properties
         * 
         * @param {Array} properties - Array of property objects
         * @since 2.0.0
         */
        addGoogleMarkersToMap(properties) {
            if (!this.map) return;
            
            const usesBrandColor = this.mapConfig.marker_uses_brand_color === true
                || this.mapConfig.marker_uses_brand_color === '1'
                || this.mapConfig.marker_uses_brand_color === 1;
            const markerColorHex = hvnlyResolveMarkerHexColor(this.mapConfig.marker_color, usesBrandColor);
            
            const infoWindow = new google.maps.InfoWindow({
                maxWidth: 300,
                pixelOffset: new google.maps.Size(0, -5),
                disableAutoPan: false
            });
            
            properties.forEach((property) => {
                const lat = parseFloat(property.lat);
                const lng = parseFloat(property.lng);
                
                // Custom SVG marker
                const svgMarker = {
                    path: `M21 0C9.4 0 0 9.4 0 21c0 11.6 9.4 21 21 21s21-9.4 21-21c0-11.6-9.4-21-21-21zm0 10c6.1 0 11 4.9 11 11s-4.9 11-11 11-11-4.9-11-11 4.9-11 11-11z`,
                    fillColor: markerColorHex,
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 3,
                    scale: 1.2,
                    anchor: new google.maps.Point(21, 42),
                    labelOrigin: new google.maps.Point(21, 23)
                };
                
                const marker = new google.maps.Marker({
                    position: { lat: lat, lng: lng },
                    map: this.map,
                    title: property.title,
                    icon: svgMarker,
                    label: {
                        text: '🏠',
                        color: markerColorHex,
                        fontSize: '16px',
                        fontWeight: 'bold'
                    },
                    optimized: true,
                    animation: google.maps.Animation.DROP
                });
                
                const popupContent = this.createStyledPopupContent(property);
                
                marker.addListener('click', () => {
                    infoWindow.close();
                    infoWindow.setContent(popupContent);
                    infoWindow.open(this.map, marker);
                });
                
                this.markers.push(marker);
            });
            
            if (this.currentPage === 1 && this.markers.length > 0) {
                setTimeout(() => this.updateMapBounds(), 500);
            }
        }
        
        /**
         * Create styled popup content for property marker
         * 
         * @param {Object} property - Property data object
         * @returns {string} HTML content for popup
         * @since 2.0.0
         */
        createStyledPopupContent(property) {
            const title = this.escapeHtml(property.title || this.t('untitledProperty'));
            const price = property.price ? this.escapeHtml(property.price) : '';
            const address = property.address ? this.escapeHtml(property.address) : '';
            const bedrooms = property.bedrooms ? parseInt(property.bedrooms) : 0;
            const bathrooms = property.bathrooms ? parseInt(property.bathrooms) : 0;
            const thumbnail = property.thumbnail || '';
            const link = property.link || '#';
            
            return `
                <div class="hvnly-property-popup-content">
                    <div class="hvnly-popup-image">
                        ${thumbnail ? `<img src="${thumbnail}" alt="${title}" loading="lazy" onerror="this.parentElement.innerHTML='<div class=\'hvnly-popup-no-image\'><i class=\'fas fa-home\'></i></div>'">` : '<div class="hvnly-popup-no-image"><i class="fas fa-home"></i></div>'}
                    </div>
                    <div class="hvnly-popup-details">
                        <h4 class="hvnly-popup-title">${title}</h4>
                        ${price ? `<p class="hvnly-popup-price">${price}</p>` : ''}
                        ${address ? `<p class="hvnly-popup-address"><i class="fas fa-map-marker-alt"></i><span>${address}</span></p>` : ''}
                        <div class="hvnly-popup-features">
                            ${bedrooms > 0 ? `<div class="hvnly-popup-feature"><i class="fas fa-bed"></i> ${bedrooms} Bed</div>` : ''}
                            ${bathrooms > 0 ? `<div class="hvnly-popup-feature"><i class="fas fa-bath"></i> ${bathrooms} Bath</div>` : ''}
                        </div>
                        <div class="hvnly-popup-actions">
                            <a href="${link}" class="hvnly-popup-view-btn" target="_blank">
                                View Details <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }
        
        /**
         * Escape HTML special characters
         * 
         * @param {string} str - String to escape
         * @returns {string} Escaped string
         * @since 2.0.0
         */
        escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        /**
         * Group properties that share the same map coordinates.
         *
         * @param {Array} properties
         * @returns {Array<{lat: number, lng: number, properties: Array}>}
         */
        groupPropertiesByLocation(properties) {
            const groups = new Map();

            properties.forEach((property) => {
                const lat = parseFloat(property.lat);
                const lng = parseFloat(property.lng);

                if (!this.isValidMapCoordinate(lat, lng)) {
                    return;
                }

                const key = `${lat.toFixed(5)}_${lng.toFixed(5)}`;
                if (!groups.has(key)) {
                    groups.set(key, { lat, lng, properties: [] });
                }
                groups.get(key).properties.push(property);
            });

            return Array.from(groups.values());
        }

        /**
         * Build a modern Leaflet divIcon for single or stacked property markers.
         *
         * @param {string} markerColor
         * @param {Object} options
         * @param {boolean} options.isStack
         * @param {number} options.count
         * @param {number} options.animationDelay
         * @returns {L.DivIcon}
         */
        createLeafletMarkerIcon(markerColor, options = {}) {
            const isStack = options.isStack === true;
            const count = parseInt(options.count, 10) || 0;
            const animationDelay = parseInt(options.animationDelay, 10) || 0;
            const stackBadge = isStack && count > 1
                ? `<span class="hvnly-marker-pin__count">${count}</span>`
                : '';

            return L.divIcon({
                className: 'custom-leaflet-marker hvnly-marker-pin-wrapper',
                html: `
                    <div class="hvnly-marker-pin${isStack ? ' hvnly-marker-pin--stack' : ''}"
                         style="--hvnly-marker-color:${markerColor};--hvnly-marker-delay:${animationDelay}ms">
                        <div class="hvnly-marker-pin__body">
                            <span class="hvnly-marker-pin__icon" aria-hidden="true"></span>
                            ${stackBadge}
                        </div>
                        <span class="hvnly-marker-pin__ring"></span>
                        <span class="hvnly-marker-pin__shadow"></span>
                    </div>
                `,
                iconSize: [46, 54],
                iconAnchor: [23, 54],
                popupAnchor: [0, -50]
            });
        }

        /**
         * Popup content when multiple properties share one coordinate.
         *
         * @param {Array} properties
         * @returns {string}
         */
        createStackPopupContent(properties) {
            const count = properties.length;
            const header = `
                <div class="hvnly-stack-popup__header">
                    <span class="hvnly-stack-popup__badge">${count}</span>
                    <strong>${count === 1 ? this.t('propertyAtLocation') : (count + ' ' + this.t('propertiesAtLocation'))}</strong>
                </div>
            `;

            const list = properties.map((property) => {
                const title = this.escapeHtml(property.title || this.t('untitledProperty'));
                const price = property.price ? this.escapeHtml(property.price) : '';
                const link = property.link || '#';
                const thumbnail = property.thumbnail || '';

                return `
                    <a href="${link}" class="hvnly-stack-popup__item" target="_blank" rel="noopener">
                        ${thumbnail
                            ? `<span class="hvnly-stack-popup__thumb"><img src="${thumbnail}" alt="${title}" loading="lazy"></span>`
                            : '<span class="hvnly-stack-popup__thumb hvnly-stack-popup__thumb--empty"><i class="fas fa-home"></i></span>'
                        }
                        <span class="hvnly-stack-popup__meta">
                            <span class="hvnly-stack-popup__title">${title}</span>
                            ${price ? `<span class="hvnly-stack-popup__price">${price}</span>` : ''}
                        </span>
                        <i class="fas fa-chevron-right hvnly-stack-popup__arrow"></i>
                    </a>
                `;
            }).join('');

            return `<div class="hvnly-stack-popup">${header}<div class="hvnly-stack-popup__list">${list}</div></div>`;
        }

        /**
         * Get property IDs already rendered on the Leaflet map.
         *
         * @returns {Set<number|string>}
         */
        getRenderedPropertyIds() {
            const ids = new Set();

            this.markers.forEach((marker) => {
                const options = marker.options || {};
                if (Array.isArray(options.propertyIds)) {
                    options.propertyIds.forEach((id) => ids.add(id));
                } else if (options.propertyId) {
                    ids.add(options.propertyId);
                }
            });

            return ids;
        }
        
        /**
         * Add Leaflet markers — one pin per unique location; stack badge only when coordinates match.
         * 
         * @param {Array} properties - Array of property objects
         * @param {Object} options - Options
         * @param {boolean} options.skipAutoFit - Skip automatic bounds fit after adding markers
         * @since 2.0.0
         */
        addMarkersToMap(properties, options = {}) {
            if (!this.map) return;

            const usesBrandColor = this.mapConfig.marker_uses_brand_color === true
                || this.mapConfig.marker_uses_brand_color === '1'
                || this.mapConfig.marker_uses_brand_color === 1;
            const markerColorCss = this.mapConfig.marker_color_css
                || hvnlyResolveMarkerCssColor(this.mapConfig.marker_color, usesBrandColor);
            const stackSameLocation = this.mapConfig.cluster_markers !== false;
            const renderedIds = this.getRenderedPropertyIds();
            const locationGroups = this.groupPropertiesByLocation(
                properties.filter((property) => !renderedIds.has(property.id))
            );

            let markerIndex = this.markers.length;

            locationGroups.forEach((group) => {
                const { lat, lng, properties: groupProperties } = group;
                const isStack = stackSameLocation && groupProperties.length > 1;
                const animationDelay = Math.min(markerIndex * 70, 420);
                markerIndex += 1;

                const customIcon = this.createLeafletMarkerIcon(markerColorCss, {
                    isStack,
                    count: groupProperties.length,
                    animationDelay
                });

                const markerOptions = {
                    icon: customIcon
                };

                if (isStack) {
                    markerOptions.propertyIds = groupProperties.map((property) => property.id);
                } else {
                    markerOptions.propertyId = groupProperties[0].id;
                }

                const marker = L.marker([lat, lng], markerOptions);

                const popupContent = isStack
                    ? this.createStackPopupContent(groupProperties)
                    : this.createStyledPopupContent(groupProperties[0]);

                marker.bindPopup(popupContent, {
                    maxWidth: 320,
                    minWidth: isStack ? 280 : 280,
                    className: isStack ? 'hvnly-property-popup hvnly-property-popup--stack' : 'hvnly-property-popup',
                    autoPan: true,
                    autoPanPadding: L.point(50, 50)
                });

                marker.addTo(this.map);
                this.markers.push(marker);
            });

            if (this.markers.length > 0) {
                this.refreshLeafletClustersAfterLayout();
            }

            if (this.markers.length > 0 && options.skipAutoFit !== true) {
                this.fitLeafletToMarkers();
            }
        }
        
        /**
         * Collect lat/lng bounds from Leaflet markers and cluster groups.
         *
         * @returns {L.LatLngBounds|null}
         */
        getLeafletMarkerBounds() {
            if (typeof L === 'undefined') {
                return null;
            }

            let bounds = null;

            const extendBounds = (latLng) => {
                if (!latLng || isNaN(latLng.lat) || isNaN(latLng.lng)) {
                    return;
                }
                if (!this.isValidMapCoordinate(latLng.lat, latLng.lng)) {
                    return;
                }
                bounds = bounds ? bounds.extend(latLng) : L.latLngBounds(latLng, latLng);
            };

            const properties = this.allMapProperties && this.allMapProperties.length
                ? this.allMapProperties
                : [];

            properties.forEach((property) => {
                const lat = parseFloat(property.lat);
                const lng = parseFloat(property.lng);
                if (this.isValidMapCoordinate(lat, lng)) {
                    extendBounds(L.latLng(lat, lng));
                }
            });

            if (this.clusterGroups && this.clusterGroups.length > 0) {
                this.clusterGroups.forEach((group) => {
                    if (group && typeof group.getBounds === 'function') {
                        const groupBounds = group.getBounds();
                        if (groupBounds && typeof groupBounds.isValid === 'function' && groupBounds.isValid()) {
                            bounds = bounds ? bounds.extend(groupBounds) : groupBounds;
                        }
                    }
                });
            }

            this.markers.forEach((marker) => {
                if (marker && typeof marker.getLatLng === 'function') {
                    extendBounds(marker.getLatLng());
                }
            });

            if (!bounds || typeof bounds.isValid !== 'function' || !bounds.isValid()) {
                return null;
            }

            return bounds;
        }
        
        /**
         * Fit Leaflet map to all visible markers after layout is ready.
         *
         * @param {number} retry Retry count when the map container has no dimensions yet.
         * @since 3.0.0
         */
        fitLeafletToMarkers(retry = 0) {
            if (!this.map || this.mapProvider === 'google' || typeof L === 'undefined') {
                return;
            }

            if (this.markers.length === 0) {
                return;
            }

            const self = this;

            const applyFit = () => {
                if (!self.map) {
                    return;
                }

                if (typeof self.map.invalidateSize === 'function') {
                    self.map.invalidateSize(true);
                }

                const bounds = self.getLeafletMarkerBounds();
                if (!bounds || typeof bounds.isValid !== 'function' || !bounds.isValid()) {
                    return;
                }

                const northEast = bounds.getNorthEast();
                const southWest = bounds.getSouthWest();
                const isSinglePoint = northEast && southWest && (
                    (typeof northEast.equals === 'function' && northEast.equals(southWest)) ||
                    (Math.abs(northEast.lat - southWest.lat) < 0.000001 &&
                     Math.abs(northEast.lng - southWest.lng) < 0.000001)
                );

                if (isSinglePoint) {
                    const zoom = parseInt(self.mapConfig.zoom_level, 10) || 14;
                    self.map.setView(bounds.getCenter(), zoom, { animate: false });
                    return;
                }

                self.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16, animate: false });
            };

            applyFit();

            if (retry === 0) {
                setTimeout(() => self.fitLeafletToMarkers(1), 350);
            }
        }
        
        /**
         * Update map bounds to fit all markers
         * 
         * @since 2.0.0
         */
        updateMapBounds() {
            if (!this.map || this.markers.length === 0) return;
            
            try {
                if (this.mapProvider === 'google') {
                    if (typeof google !== 'undefined' && google.maps) {
                        const bounds = new google.maps.LatLngBounds();
                        this.markers.forEach(marker => {
                            if (marker && typeof marker.getPosition === 'function') {
                                const position = marker.getPosition();
                                if (position) bounds.extend(position);
                            }
                        });
                        if (!bounds.isEmpty()) {
                            this.map.fitBounds(bounds, { top: 50, right: 50, bottom: 50, left: 50 });
                        }
                    }
                } else {
                    this.fitLeafletToMarkers();
                }
            } catch (boundsError) {}
        }
        
        /**
         * Add custom controls to Leaflet map (fullscreen button)
         * 
         * @param {Object} map - Leaflet map instance
         * @since 2.0.0
         */
        addMapControls(map) {
            const self = this;
            const fullscreenControl = L.control({ position: 'topright' });
            
            fullscreenControl.onAdd = function(map) {
                const div = L.DomUtil.create('div', 'leaflet-control leaflet-control-custom');
                const fullscreenButton = document.createElement('a');
                fullscreenButton.id = 'hvnly-leaflet-map-fullscreen-btn';
                fullscreenButton.href = '#';
                fullscreenButton.title = self.t('toggleFullscreen');
                fullscreenButton.className = 'custom-fullscreen-button';
                fullscreenButton.innerHTML = '<i class="fas fa-expand"></i>';
                
                L.DomEvent.disableClickPropagation(fullscreenButton);
                
                fullscreenButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    self.toggleFullscreen();
                });
                
                div.appendChild(fullscreenButton);
                return div;
            };
            
            fullscreenControl.addTo(map);
        }
        
        /**
         * Add custom controls to Google Map (fullscreen button)
         * 
         * @since 2.0.0
         */
        addGoogleMapControls() {
            const controlContainer = document.createElement('div');
            controlContainer.className = 'hvnly-google-map-controls-container';
            const fullscreenButton = document.createElement('div');
            fullscreenButton.id = 'hvnly-google-map-fullscreen-btn';
            fullscreenButton.className = 'hvnly-map-fullscreen-btn hvnly-google-map-fullscreen-btn';
            fullscreenButton.innerHTML = '<i class="fas fa-expand"></i>';
            fullscreenButton.title = self.t('toggleFullscreen');
            
            fullscreenButton.addEventListener('click', () => {
                this.toggleFullscreen();
            });
            
            controlContainer.appendChild(fullscreenButton);
            this.map.controls[google.maps.ControlPosition.TOP_LEFT].push(controlContainer);
        }
        
        /**
         * Display error message in map container
         * 
         * @param {string} message - Error message to display
         * @since 2.0.0
         */
        showMapError(message, details = null) {
            this.mapWarn(message, details);
            this.hideLoadMoreLoading();

            const mapPlaceholder = this.resolveMapPlaceholderElement();
            const mapView = mapPlaceholder
                ? mapPlaceholder.querySelector('.hvnly-map-view')
                : document.querySelector('.hvnly-map-view');

            if (mapView) {
                mapView.innerHTML = `
                    <div class="hvnly-map-error">
                        <div class="hvnly-map-error-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3>${this.t('mapUnavailable')}</h3>
                        <p>${message}</p>
                        <button class="hvnly-retry-map-btn" onclick="window.HavenlyticsPropertyMap.loadPropertyMap()">
                            <i class="fas fa-redo"></i> ${this.t('retry')}
                        </button>
                    </div>
                `;
            }
        }
        
        /**
         * Callback when Google Maps is ready
         * 
         * @since 2.0.0
         */
        onGoogleMapsReady() {
            if (this.mapProvider === 'google' && !this.isMapInitialized && this.allMapProperties.length > 0) {
                this.initializeGoogleMap(this.allMapProperties);
            }
        }
        
        /**
         * Callback when Google Maps authentication fails
         * Falls back to Leaflet provider
         * 
         * @since 2.0.0
         */
        onGoogleMapsAuthFailure() {
            if (this.map) {
                if (this.markers) {
                    this.markers.forEach(marker => {
                        if (marker && typeof marker.setMap === 'function') {
                            marker.setMap(null);
                        }
                    });
                }
                this.map = null;
            }
            
            this.mapProvider = 'leaflet';
            this.markers = [];
            this.clusterGroups = [];
            this.isMapInitialized = false;
            this.loadPropertyMap();
        }
    }
    
    /**
     * Initialize map when document is ready
     */
    $(document).ready(function() {
        if (typeof $ === 'undefined') {
            return;
        }
        
        setTimeout(function() {
            window.HavenlyticsPropertyMap = new HavenlyticsPropertyMap();
            window.HvnlyPropertyMap = window.HavenlyticsPropertyMap;
        }, 100);
    });
    
    /**
     * Global callback for Google Maps authentication failure
     */
    window.gm_authFailure = function() {
        const mapInstance = window.HavenlyticsPropertyMap || window.HvnlyPropertyMap;
        if (mapInstance && typeof mapInstance.onGoogleMapsAuthFailure === 'function') {
            mapInstance.onGoogleMapsAuthFailure();
        } else if (mapInstance) {
            mapInstance.mapProvider = 'leaflet';
            if (mapInstance.loadPropertyMap) {
                mapInstance.loadPropertyMap();
            }
        }
    };
})(jQuery);