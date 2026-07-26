/**
 * Havenlytics Single Property Map Module
 * Handles map initialization for single property pages using the configured provider
 * 
 * @package     Havenlytics
 * @version     2.2.2
 */
(function() {
    'use strict';

    // Prevent multiple initializations
    if (window.hvnlySingleMapModuleInitialized) {
        return;
    }
    window.hvnlySingleMapModuleInitialized = true;

    // Get map config from PHP localized data
    const mapConfig = window.hvnly_map_params || {};
    const mapProvider = mapConfig.provider || 'leaflet';
    const apiKey = mapConfig.api_key || '';
    const usesBrandColor = mapConfig.marker_uses_brand_color === true
        || mapConfig.marker_uses_brand_color === '1'
        || mapConfig.marker_uses_brand_color === 1;
    const markerColorCss = mapConfig.marker_color_css
        || (usesBrandColor ? 'var(--hvnly-primary-color)' : mapConfig.marker_color);
    const markerColorHex = (function() {
        if (usesBrandColor || !mapConfig.marker_color) {
            const token = getComputedStyle(document.documentElement).getPropertyValue('--hvnly-primary-color').trim();
            return token || '#6C60FE';
        }
        return mapConfig.marker_color;
    })();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMapModule);
    } else {
        initMapModule();
    }

    window.addEventListener('load', function() {
        setTimeout(initMapModule, 500);
    });

    function initMapModule() {
        if (window.hvnlySingleMapsInitialized) {
            return;
        }

        const mapContainers = document.querySelectorAll('.hvnly-property-single__map');

        if (mapContainers.length === 0) {
            return;
        }

        let needsInitialization = false;
        mapContainers.forEach(container => {
            if (!container.classList.contains('map-initialized')) {
                needsInitialization = true;
            }
        });

        if (!needsInitialization) {
            return;
        }

        addMapStyles();
        // Font Awesome is enqueued as hvnly-fontawesome-all-frontend (local assets/admin/css/fontawesome-all.min.css).

        // Initialize based on provider setting from admin
        if (mapProvider === 'google' && apiKey && apiKey !== '') {
            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                initializeGoogleMaps(mapContainers);
            } else {
                // Load Google Maps script dynamically
                window.initHvnlySingleMap = function() {
                    initializeGoogleMaps(mapContainers);
                };
                
                if (!document.querySelector('script[src*="maps.googleapis.com/api/js"]')) {
                    const script = document.createElement('script');
                    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places&callback=initHvnlySingleMap`;
                    script.async = true;
                    script.defer = true;
                    document.head.appendChild(script);
                } else {
                    // Script exists but not loaded yet, wait for callback
                    setTimeout(() => {
                        if (typeof google !== 'undefined' && typeof google.maps !== 'undefined') {
                            initializeGoogleMaps(mapContainers);
                        }
                    }, 1000);
                }
            }
        } else if (mapProvider === 'openstreetmap') {
            // OpenStreetMap uses Leaflet with OSM tiles
            if (typeof L === 'undefined') {
                setTimeout(initMapModule, 500);
                return;
            }
            initializeLeafletMaps(mapContainers, true);
        } else {
            // Default: Leaflet with OSM
            if (typeof L === 'undefined') {
                setTimeout(initMapModule, 500);
                return;
            }
            initializeLeafletMaps(mapContainers, false);
        }

        window.hvnlySingleMapsInitialized = true;
    }

    function initializeGoogleMaps(containers) {
        containers.forEach((container, index) => {
            if (container.classList.contains('map-initialized')) {
                return;
            }

            const lat = parseFloat(container.getAttribute('data-lat') || container.getAttribute('data-latitude'));
            const lng = parseFloat(container.getAttribute('data-lng') || container.getAttribute('data-longitude'));
            const address = container.getAttribute('data-address') || '';
            const title = container.getAttribute('data-title') || 'Property Location';

            if (isNaN(lat) || isNaN(lng)) {
                showMapError(container, 'Invalid coordinates');
                return;
            }

            const map = new google.maps.Map(container, {
                center: { lat: lat, lng: lng },
                zoom: parseInt(mapConfig.zoom_level) || 14,
                mapTypeId: mapConfig.google_map_type || 'roadmap',
                fullscreenControl: mapConfig.show_fullscreen !== false,
                zoomControl: mapConfig.show_zoom_control !== false,
                scrollwheel: mapConfig.show_scroll_wheel !== false
            });

            // Custom marker with home icon (same as archive map)
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
                map: map,
                title: title,
                icon: svgMarker,
                label: {
                    text: '🏠',
                    color: markerColorHex,
                    fontSize: '16px',
                    fontWeight: 'bold'
                },
                animation: google.maps.Animation.DROP
            });

            const infoWindow = new google.maps.InfoWindow({
                content: createPopupContent(title, address, lat, lng)
            });

            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });

            container.classList.add('map-initialized');
        });
    }

    function initializeLeafletMaps(containers, isOpenStreetMap = false) {
        const tileUrl = isOpenStreetMap ? (mapConfig.osm_tile_url || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png') : 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        const attribution = mapConfig.osm_attribution || '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

        containers.forEach((container, index) => {
            if (container.classList.contains('leaflet-container') || container.classList.contains('map-initialized')) {
                return;
            }

            const lat = parseFloat(container.getAttribute('data-lat') || container.getAttribute('data-latitude'));
            const lng = parseFloat(container.getAttribute('data-lng') || container.getAttribute('data-longitude'));
            const address = container.getAttribute('data-address') || '';
            const title = container.getAttribute('data-title') || 'Property Location';

            if (isNaN(lat) || isNaN(lng)) {
                showMapError(container, 'Invalid coordinates');
                return;
            }

            if (container.offsetHeight === 0) {
                const responsiveHeight = getComputedStyle(container).getPropertyValue('--hvnly-media-height').trim();
                container.style.height = responsiveHeight || '360px';
            }

            const map = L.map(container, {
                center: [lat, lng],
                zoom: parseInt(mapConfig.zoom_level) || 14,
                scrollWheelZoom: mapConfig.show_scroll_wheel !== false,
                zoomControl: mapConfig.show_zoom_control !== false
            });

            L.tileLayer(tileUrl, {
                attribution: attribution,
                maxZoom: 19
            }).addTo(map);

            // Custom marker with home icon (matching archive map design)
            const customIcon = L.divIcon({
                className: 'custom-leaflet-marker',
                html: `<div class="custom-marker-pin"><div class="marker-pin" style="background: ${markerColorCss}"><i class="fas fa-home" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(45deg); color: white; font-size: 14px;"></i></div><div class="marker-shadow"></div></div>`,
                iconSize: [42, 42],
                iconAnchor: [21, 42],
                popupAnchor: [0, -42]
            });

            const marker = L.marker([lat, lng], { icon: customIcon }).addTo(map);

            const popupContent = createPopupContent(title, address, lat, lng);
            marker.bindPopup(popupContent, {
                maxWidth: 300,
                minWidth: 280,
                className: 'hvnly-property-popup'
            }).openPopup();

            setTimeout(() => {
                map.invalidateSize();
                map.fitBounds([[lat, lng]], {
                    padding: [50, 50],
                    maxZoom: 16
                });
            }, 100);

            container.classList.add('map-initialized');
        });
    }

    function createPopupContent(title, address, lat, lng) {
        const escapedTitle = escapeHtml(title);
        const escapedAddress = escapeHtml(address);
        
        return `
            <div class="hvnly-property-popup-content">
                <div class="hvnly-popup-details">
                    <h4 class="hvnly-popup-title">${escapedTitle}</h4>
                    ${escapedAddress ? `<p class="hvnly-popup-address"><i class="fas fa-map-marker-alt"></i><span>${escapedAddress}</span></p>` : ''}
                    <div class="hvnly-popup-coordinates">${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
                </div>
            </div>
        `;
    }

    function addMapStyles() {
        const styleId = 'hvnly-single-map-styles';
        if (document.getElementById(styleId)) return;

        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            .hvnly-property-single__map {
                height: var(--hvnly-media-height, 360px);
                width: 100%;
                background: #f5f5f5;
                border-radius: 12px;
                overflow: hidden;
                position: relative;
                z-index: 1;
            }
            
            .hvnly-property-single__map .leaflet-container,
            .hvnly-property-single__map .gm-style {
                height: 100%;
                width: 100%;
                border-radius: 12px;
            }
            
            .hvnly-popup-coordinates {
                font-size: 11px;
                color: #999;
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid #eee;
            }
            
            .custom-leaflet-marker {
                background: transparent !important;
                border: none !important;
            }
            
            .custom-marker-pin {
                position: relative;
                width: 42px;
                height: 42px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .marker-pin {
                position: absolute;
                width: 36px;
                height: 36px;
                background: ${markerColorCss};
                border-radius: 50% 50% 50% 0;
                border: 3px solid #ffffff;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                transform: rotate(-45deg);
                transition: all 0.3s ease;
                cursor: pointer;
            }
            
            .marker-shadow {
                position: absolute;
                bottom: -8px;
                left: 50%;
                transform: translateX(-50%);
                width: 24px;
                height: 8px;
                background: rgba(0, 0, 0, 0.2);
                border-radius: 50%;
                filter: blur(4px);
            }
            
            /* Fix for marker pin icon */
            .marker-pin i {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(45deg);
                color: white;
                font-size: 14px;
            }
        `;
        document.head.appendChild(style);
    }

    function showMapError(container, message) {
        container.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; background: #f8f8f8; border-radius: 12px; padding: 20px; text-align: center; color: #666;">
                <i class="fas fa-map-marker-alt" style="font-size: 48px; color: #999; margin-bottom: 15px; opacity: 0.5;"></i>
                <h4 style="margin: 0 0 10px 0; color: #333;">${escapeHtml(message)}</h4>
                <p style="margin: 0;">Please check the property coordinates.</p>
            </div>
        `;
        container.classList.add('map-initialized');
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Global callback for Google Maps
    window.initHvnlySingleMap = function() {
        const mapContainers = document.querySelectorAll('.hvnly-property-single__map');
        initializeGoogleMaps(mapContainers);
    };
})();