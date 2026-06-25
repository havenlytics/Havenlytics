/**
 * Havenlytics Property Import Wizard – unified map manager.
 * Leaflet/OSM + Google with bi-directional location sync.
 */
(function (window) {
    'use strict';

    const COORD_PRECISION = 6;
    const SUGGEST_DEBOUNCE_MS = 320;
    const COORD_DEBOUNCE_MS = 250;
    const REVERSE_GEOCODE_DEBOUNCE_MS = 300;
    const FORWARD_GEOCODE_DEBOUNCE_MS = 500;

    const ImportMapManager = {
        engine: null,
        leafletMap: null,
        leafletMarker: null,
        googleMap: null,
        googleMarker: null,
        googleMarkerIsAdvanced: false,
        placesAutocomplete: null,
        placesAutocompleteElement: null,
        googlePlacesElementActive: false,
        initTimer: null,
        isInitializing: false,
        googleLoadPromise: null,
        placesSetupDone: false,
        addressUiBound: false,
        activeProvider: null,
        syncLock: false,
        skipReverseGeocode: false,
        skipForwardGeocode: false,
        coordDebounceTimer: null,
        suggestDebounceTimer: null,
        reverseGeocodeTimer: null,
        forwardGeocodeTimer: null,
        reverseGeocodeSeq: 0,
        pendingForwardQuery: null,
        lastSynced: { lat: null, lng: null, address: '' },
        googleGeocodeFailed: false,

        isLeafletEngine() {
            return this.engine === 'leaflet';
        },

        isGoogleEngine() {
            return this.engine === 'google';
        },

        usesLeafletProvider(provider) {
            return provider === 'leaflet' || provider === 'openstreetmap';
        },

        clampLat(lat) {
            const n = parseFloat(lat);
            if (isNaN(n)) return null;
            return Math.min(90, Math.max(-90, n));
        },

        clampLng(lng) {
            const n = parseFloat(lng);
            if (isNaN(n)) return null;
            return Math.min(180, Math.max(-180, n));
        },

        formatCoord(value) {
            const n = parseFloat(value);
            if (isNaN(n)) return '';
            return n.toFixed(COORD_PRECISION);
        },

        coordsEqual(a, b, epsilon) {
            const eps = typeof epsilon === 'number' ? epsilon : 0.000001;
            return Math.abs(a - b) <= eps;
        },

        getFieldElements() {
            return {
                address: document.getElementById('map-address'),
                lat: document.getElementById('map-latitude'),
                lng: document.getElementById('map-longitude'),
                results: document.getElementById('autocomplete-results'),
            };
        },

        readCoordinatesFromInputs() {
            const { lat, lng } = this.getFieldElements();
            const cfg = window.hvnlyImportWizard || {};
            const defaultLat = parseFloat(cfg.defaultLat) || 30.2672;
            const defaultLng = parseFloat(cfg.defaultLng) || -97.7431;
            const latVal = this.clampLat(lat?.value);
            const lngVal = this.clampLng(lng?.value);
            return {
                lat: latVal !== null ? latVal : defaultLat,
                lng: lngVal !== null ? lngVal : defaultLng,
            };
        },

        withSyncLock(fn) {
            if (this.syncLock) return;
            this.syncLock = true;
            try {
                fn();
            } finally {
                window.requestAnimationFrame(() => {
                    this.syncLock = false;
                });
            }
        },

        setInputValue(input, value) {
            if (!input || input.value === value) return;
            input.value = value;
        },

        rememberState(lat, lng, address) {
            this.lastSynced.lat = lat;
            this.lastSynced.lng = lng;
            this.lastSynced.address = address || '';
        },

        applyFromCoordinates(lat, lng, options) {
            const opts = options || {};
            const latVal = this.clampLat(lat);
            const lngVal = this.clampLng(lng);
            if (latVal === null || lngVal === null) return;

            if (
                !opts.force &&
                this.lastSynced.lat !== null &&
                this.coordsEqual(this.lastSynced.lat, latVal) &&
                this.coordsEqual(this.lastSynced.lng, lngVal)
            ) {
                return;
            }

            this.withSyncLock(() => {
                const fields = this.getFieldElements();
                const latStr = this.formatCoord(latVal);
                const lngStr = this.formatCoord(lngVal);

                this.setInputValue(fields.lat, latStr);
                this.setInputValue(fields.lng, lngStr);

                if (opts.address) {
                    this.setInputValue(fields.address, opts.address);
                    this.syncGoogleAutocompleteValue(opts.address);
                    this.rememberState(latVal, lngVal, opts.address);
                } else {
                    this.rememberState(latVal, lngVal, fields.address?.value || '');
                }

                this.panTo(latVal, lngVal, { silent: true });
            });

            if (!opts.skipReverseGeocode && !this.skipReverseGeocode) {
                this.scheduleReverseGeocode(latVal, lngVal);
            }

            if (!opts.isDefaultSeed && typeof window.hvnlyImportWizardMarkLocationModified === 'function') {
                window.hvnlyImportWizardMarkLocationModified();
            }
        },

        applyFromSuggestion(address, lat, lng) {
            this.skipReverseGeocode = true;
            this.skipForwardGeocode = true;
            this.clearSuggestionResults();

            this.applyFromCoordinates(lat, lng, {
                address,
                skipReverseGeocode: true,
                force: true,
            });

            window.setTimeout(() => {
                this.skipReverseGeocode = false;
                this.skipForwardGeocode = false;
            }, 600);
        },

        commitAddressGeocode(address) {
            const trimmed = String(address || '').trim();
            if (trimmed.length < 3) return;
            if (this.skipForwardGeocode || this.syncLock) return;
            if (trimmed === this.lastSynced.address) return;

            this.forwardGeocode(trimmed)
                .then((result) => {
                    if (!result) return;
                    this.skipReverseGeocode = true;
                    this.applyFromCoordinates(result.lat, result.lng, {
                        address: result.address || trimmed,
                        skipReverseGeocode: true,
                        force: true,
                    });
                    window.setTimeout(() => {
                        this.skipReverseGeocode = false;
                    }, 600);
                })
                .catch(() => {
                    this.showPlacesNotice(this.getString('geocodeFailed'), true);
                });
        },

        scheduleReverseGeocode(lat, lng) {
            if (this.reverseGeocodeTimer) {
                clearTimeout(this.reverseGeocodeTimer);
            }
            const seq = ++this.reverseGeocodeSeq;

            this.reverseGeocodeTimer = setTimeout(() => {
                this.reverseGeocodeTimer = null;
                if (this.skipReverseGeocode || this.syncLock) return;

                this.reverseGeocode(lat, lng)
                    .then((address) => {
                        if (seq !== this.reverseGeocodeSeq) return;
                        if (!address || this.skipReverseGeocode || this.syncLock) return;

                        const fields = this.getFieldElements();
                        if (fields.address && fields.address.value !== address) {
                            this.skipForwardGeocode = true;
                            this.withSyncLock(() => {
                                this.setInputValue(fields.address, address);
                                this.syncGoogleAutocompleteValue(address);
                                this.rememberState(lat, lng, address);
                            });
                            window.setTimeout(() => {
                                this.skipForwardGeocode = false;
                            }, 500);
                        }
                    })
                    .catch(() => {
                        /* optional */
                    });
            }, REVERSE_GEOCODE_DEBOUNCE_MS);
        },

        canUseGoogleGeocoder() {
            return (
                this.activeProvider === 'google' &&
                !this.googleGeocodeFailed &&
                typeof window.google !== 'undefined' &&
                window.google.maps?.Geocoder
            );
        },

        showFallbackNotice() {
            if (this.googleGeocodeFailed) return;
            this.googleGeocodeFailed = true;
            this.showPlacesNotice(this.getString('googleGeocodeFallback'), false);
        },

        googleForwardGeocode(address) {
            return new Promise((resolve) => {
                const geocoder = new window.google.maps.Geocoder();
                geocoder.geocode({ address }, (results, status) => {
                    if (status === 'OK' && results?.[0]?.geometry?.location) {
                        const loc = results[0].geometry.location;
                        resolve({
                            lat: loc.lat(),
                            lng: loc.lng(),
                            address: results[0].formatted_address || address,
                        });
                    } else {
                        resolve(null);
                    }
                });
            });
        },

        nominatimForwardGeocode(address) {
            const url =
                'https://nominatim.openstreetmap.org/search?format=json&q=' +
                encodeURIComponent(address) +
                '&limit=1';
            return fetch(url, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((data) => {
                    if (!data?.length) return null;
                    return {
                        lat: parseFloat(data[0].lat),
                        lng: parseFloat(data[0].lon),
                        address: data[0].display_name || address,
                    };
                })
                .catch(() => null);
        },

        forwardGeocode(address) {
            if (this.canUseGoogleGeocoder()) {
                return this.googleForwardGeocode(address).then((result) => {
                    if (result) return result;
                    this.showFallbackNotice();
                    return this.nominatimForwardGeocode(address);
                });
            }
            return this.nominatimForwardGeocode(address);
        },

        googleReverseGeocode(lat, lng) {
            return new Promise((resolve) => {
                const geocoder = new window.google.maps.Geocoder();
                geocoder.geocode({ location: { lat, lng } }, (results, status) => {
                    if (status === 'OK' && results?.[0]?.formatted_address) {
                        resolve(results[0].formatted_address);
                    } else {
                        resolve(null);
                    }
                });
            });
        },

        nominatimReverseGeocode(lat, lng) {
            const url =
                'https://nominatim.openstreetmap.org/reverse?format=json&lat=' +
                encodeURIComponent(lat) +
                '&lon=' +
                encodeURIComponent(lng) +
                '&zoom=18';
            return fetch(url, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((data) => (data?.display_name ? data.display_name : null))
                .catch(() => null);
        },

        reverseGeocode(lat, lng) {
            if (this.canUseGoogleGeocoder()) {
                return this.googleReverseGeocode(lat, lng).then((address) => {
                    if (address) return address;
                    this.showFallbackNotice();
                    return this.nominatimReverseGeocode(lat, lng);
                });
            }
            return this.nominatimReverseGeocode(lat, lng);
        },

        getString(key) {
            const strings = window.hvnlyImportWizard?.strings || {};
            const defaults = {
                geocodeFailed: 'Could not find that address. Try selecting a suggestion or adjust the marker.',
                googleGeocodeFallback:
                    'Google geocoding is unavailable. Using OpenStreetMap search instead.',
                placesUnavailable:
                    'Address autocomplete is unavailable. Suggestions will use OpenStreetMap.',
                mapLoadFailed:
                    'Unable to load Google Maps. Showing OpenStreetMap instead.',
                loadingMap: 'Loading map…',
            };
            return strings[key] || defaults[key] || '';
        },

        bindCoordinateFieldSync() {
            if (this.addressUiBound) return;
            this.addressUiBound = true;

            const fields = this.getFieldElements();
            if (!fields.lat || !fields.lng) return;

            const onCoordInput = () => {
                if (this.syncLock || this.skipForwardGeocode) return;
                if (this.coordDebounceTimer) clearTimeout(this.coordDebounceTimer);
                this.coordDebounceTimer = setTimeout(() => {
                    this.coordDebounceTimer = null;
                    const latInput = this.clampLat(fields.lat.value);
                    const lngInput = this.clampLng(fields.lng.value);
                    if (latInput === null || lngInput === null) return;
                    this.applyFromCoordinates(latInput, lngInput, { force: true });
                }, COORD_DEBOUNCE_MS);
            };

            fields.lat.addEventListener('input', onCoordInput);
            fields.lat.addEventListener('change', onCoordInput);
            fields.lng.addEventListener('input', onCoordInput);
            fields.lng.addEventListener('change', onCoordInput);

            const initial = this.readCoordinatesFromInputs();
            this.rememberState(initial.lat, initial.lng, fields.address?.value || '');
        },

        clearSuggestionResults() {
            const { results } = this.getFieldElements();
            if (!results) return;
            results.classList.remove('active');
            results.innerHTML = '';
        },

        renderSuggestions(items) {
            const { results } = this.getFieldElements();
            if (!results) return;

            results.innerHTML = '';
            if (!items?.length) {
                results.classList.remove('active');
                return;
            }

            items.forEach((place) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'autocomplete-item';
                item.textContent = place.label;
                item.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                });
                item.addEventListener('click', () => {
                    this.applyFromSuggestion(place.label, place.lat, place.lng);
                });
                results.appendChild(item);
            });
            results.classList.add('active');
        },

        fetchNominatimSuggestions(query) {
            const url =
                'https://nominatim.openstreetmap.org/search?format=json&q=' +
                encodeURIComponent(query) +
                '&limit=5';
            return fetch(url, { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then((data) => {
                    if (!Array.isArray(data)) return [];
                    return data.map((place) => ({
                        label: place.display_name,
                        lat: parseFloat(place.lat),
                        lng: parseFloat(place.lon),
                    }));
                })
                .catch(() => []);
        },

        scheduleSuggestions(query) {
            if (this.suggestDebounceTimer) clearTimeout(this.suggestDebounceTimer);
            if (query.length < 3) {
                this.clearSuggestionResults();
                return;
            }

            this.suggestDebounceTimer = setTimeout(() => {
                this.suggestDebounceTimer = null;
                if (this.googlePlacesElementActive) return;

                this.fetchNominatimSuggestions(query).then((items) => {
                    this.renderSuggestions(items);
                });
            }, SUGGEST_DEBOUNCE_MS);
        },

        setupAddressAutocomplete() {
            const fields = this.getFieldElements();
            const addressInput = fields.address;
            if (!addressInput) return;

            if (!addressInput.dataset.hvnlyImportAddressBound) {
                addressInput.dataset.hvnlyImportAddressBound = '1';

                addressInput.addEventListener('input', () => {
                    if (this.syncLock || this.skipForwardGeocode) return;
                    const query = addressInput.value.trim();
                    this.scheduleSuggestions(query);
                });

                addressInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.clearSuggestionResults();
                        this.commitAddressGeocode(addressInput.value.trim());
                    }
                });

                addressInput.addEventListener('blur', () => {
                    window.setTimeout(() => {
                        this.clearSuggestionResults();
                        const query = addressInput.value.trim();
                        if (query.length >= 3 && query !== this.lastSynced.address && !this.googlePlacesElementActive) {
                            this.commitAddressGeocode(query);
                        }
                    }, 220);
                });
            }
        },

        handleDocumentClick(e) {
            const fields = ImportMapManager.getFieldElements();
            if (!fields.address || !fields.results) return;
            if (!fields.address.contains(e.target) && !fields.results.contains(e.target)) {
                fields.results.classList.remove('active');
            }
        },

        syncGoogleAutocompleteValue(value) {
            if (this.placesAutocompleteElement && 'value' in this.placesAutocompleteElement) {
                this.placesAutocompleteElement.value = value;
            }
        },

        teardownGooglePlacesUi() {
            const addressInput = document.getElementById('map-address');
            const stepPanel = document.getElementById('step-panel-2');

            if (this.placesAutocompleteElement?.parentNode) {
                this.placesAutocompleteElement.remove();
                this.placesAutocompleteElement = null;
            }

            if (stepPanel) {
                stepPanel.querySelectorAll('.hvnly-google-place-autocomplete').forEach((element) => {
                    element.remove();
                });
            } else {
                document.querySelectorAll('.hvnly-google-place-autocomplete').forEach((element) => {
                    element.remove();
                });
            }

            this.placesAutocomplete = null;
            this.googlePlacesElementActive = false;
            this.placesSetupDone = false;
            if (addressInput) {
                addressInput.removeAttribute('hidden');
                addressInput.removeAttribute('aria-hidden');
                delete addressInput.dataset.hvnlyPlacesBound;
            }
        },

        cleanupProviderExclusiveUi(provider) {
            this.hidePlacesNotice();
            if (provider !== 'google') {
                this.teardownGooglePlacesUi();
            }
        },

        async handleGooglePlaceSelection(place) {
            if (!place?.fetchFields) return;
            try {
                await place.fetchFields({ fields: ['formattedAddress', 'location'] });
                const loc = place.location;
                if (!loc) return;
                const lat = typeof loc.lat === 'function' ? loc.lat() : loc.lat;
                const lng = typeof loc.lng === 'function' ? loc.lng() : loc.lng;
                const addressInput = document.getElementById('map-address');
                const formatted = place.formattedAddress || addressInput?.value || '';
                this.applyFromSuggestion(formatted, lat, lng);
            } catch (err) {
                this.showPlacesNotice(this.getString('placesUnavailable'), true);
            }
        },

        bindGooglePlaceAutocompleteEvents(pac) {
            const onLegacySelect = async (event) => {
                const place = event.place;
                if (place) {
                    await this.handleGooglePlaceSelection(place);
                }
            };

            const onModernSelect = async (event) => {
                const prediction = event.placePrediction;
                if (!prediction?.toPlace) return;
                await this.handleGooglePlaceSelection(prediction.toPlace());
            };

            pac.addEventListener('gmp-select', onModernSelect);
            pac.addEventListener('gmp-placeselect', onLegacySelect);

            pac.addEventListener('gmp-error', () => {
                this.showPlacesNotice(this.getString('placesUnavailable'), true);
            });
        },

        async setupGooglePlaces() {
            const addressInput = document.getElementById('map-address');
            if (!addressInput || typeof window.google === 'undefined' || !window.google.maps) {
                return false;
            }
            if (this.placesSetupDone) return true;

            this.hidePlacesNotice();
            this.teardownGooglePlacesUi();

            if (window.google.maps.importLibrary) {
                try {
                    const { PlaceAutocompleteElement } = await window.google.maps.importLibrary('places');
                    if (PlaceAutocompleteElement) {
                        const pac = new PlaceAutocompleteElement({});
                        pac.classList.add('hvnly-google-place-autocomplete');
                        addressInput.setAttribute('hidden', 'hidden');
                        addressInput.parentNode.insertBefore(pac, addressInput.nextSibling);

                        this.bindGooglePlaceAutocompleteEvents(pac);

                        this.placesAutocompleteElement = pac;
                        this.googlePlacesElementActive = true;
                        this.placesSetupDone = true;
                        return true;
                    }
                } catch (err) {
                    /* fall through to legacy */
                }
            }

            return this.setupLegacyPlacesAutocomplete(addressInput);
        },

        setupLegacyPlacesAutocomplete(addressInput) {
            if (!window.google.maps.places?.Autocomplete) {
                this.showPlacesNotice(this.getString('placesUnavailable'), false);
                return false;
            }

            try {
                this.placesAutocomplete = new window.google.maps.places.Autocomplete(addressInput, {
                    fields: ['geometry', 'formatted_address'],
                });
                addressInput.dataset.hvnlyPlacesBound = '1';
                this.placesAutocomplete.addListener('place_changed', () => {
                    const place = this.placesAutocomplete.getPlace();
                    if (!place?.geometry?.location) return;
                    const lat = place.geometry.location.lat();
                    const lng = place.geometry.location.lng();
                    const formatted = place.formatted_address || addressInput.value;
                    this.applyFromSuggestion(formatted, lat, lng);
                });
                this.placesSetupDone = true;
                return true;
            } catch (err) {
                this.showPlacesNotice(this.getString('placesUnavailable'), true);
                return false;
            }
        },

        createLeafletIcon() {
            return window.L.divIcon({
                className: 'hvnly-import-map-marker',
                html: '<span class="hvnly-import-map-marker__pin"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></span>',
                iconSize: [34, 44],
                iconAnchor: [17, 44],
            });
        },

        destroy() {
            if (this.initTimer) {
                clearTimeout(this.initTimer);
                this.initTimer = null;
            }
            [this.addressDebounceTimer, this.coordDebounceTimer, this.reverseGeocodeTimer, this.suggestDebounceTimer, this.forwardGeocodeTimer].forEach((timer) => {
                if (timer) clearTimeout(timer);
            });
            this.addressDebounceTimer = null;
            this.coordDebounceTimer = null;
            this.reverseGeocodeTimer = null;
            this.suggestDebounceTimer = null;
            this.forwardGeocodeTimer = null;

            this.teardownGooglePlacesUi();
            this.clearSuggestionResults();

            if (this.isGoogleEngine()) {
                try {
                    if (this.googleMap && window.google?.maps?.event) {
                        window.google.maps.event.clearInstanceListeners(this.googleMap);
                    }
                    if (this.googleMarker && window.google?.maps?.event) {
                        window.google.maps.event.clearInstanceListeners(this.googleMarker);
                    }
                } catch (e) {
                    /* noop */
                }
            } else if (this.isLeafletEngine() && this.leafletMap) {
                try {
                    this.leafletMap.off?.();
                    this.leafletMap.remove();
                } catch (e) {
                    /* noop */
                }
            }

            this.engine = null;
            this.leafletMap = null;
            this.leafletMarker = null;
            this.googleMap = null;
            this.googleMarker = null;
            this.googleMarkerIsAdvanced = false;
            this.googleGeocodeFailed = false;
        },

        replaceMapContainer() {
            const mapContainer = document.getElementById('import-map');
            if (!mapContainer || !mapContainer.parentNode) return null;
            const fresh = document.createElement('div');
            fresh.id = 'import-map';
            fresh.className = 'hvnly--property--import-map-canvas';
            fresh.setAttribute('role', 'application');
            fresh.setAttribute('aria-label', 'Property location map');
            mapContainer.parentNode.replaceChild(fresh, mapContainer);
            return fresh;
        },

        getMapLoaderHtml(message) {
            const text = message || this.getString('loadingMap');
            return (
                '<div class="hvnly-map-loading">' +
                '<div class="hvnly-map-loader hvnly-map-loader--pulse" aria-hidden="true">' +
                '<i class="fas fa-map-marked-alt"></i></div>' +
                '<span>' + text + '</span></div>'
            );
        },

        showLoading(message) {
            const mapContainer = document.getElementById('import-map');
            if (!mapContainer) return;
            mapContainer.innerHTML = this.getMapLoaderHtml(message);
        },

        showMessage(message) {
            const mapContainer = document.getElementById('import-map');
            if (!mapContainer) return;
            const safe = String(message)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            mapContainer.innerHTML = '<div class="hvnly-map-message">' + safe + '</div>';
        },

        showPlacesNotice(message, isError) {
            const notice = document.getElementById('map-places-notice');
            if (!notice) return;
            notice.textContent = message;
            notice.hidden = false;
            notice.classList.toggle('is-error', !!isError);
        },

        hidePlacesNotice() {
            const notice = document.getElementById('map-places-notice');
            if (notice) {
                notice.hidden = true;
                notice.textContent = '';
                notice.classList.remove('is-error');
            }
        },

        getTileConfig(provider) {
            const cfg = window.hvnlyImportWizard || {};
            if (provider === 'openstreetmap') {
                return {
                    url: cfg.osmTileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                    attribution:
                        cfg.osmAttribution ||
                        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                };
            }
            return {
                url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                attribution: '&copy; OpenStreetMap contributors',
            };
        },

        waitForLeaflet(maxAttempts) {
            const limit = maxAttempts || 40;
            return new Promise((resolve, reject) => {
                let attempts = 0;
                const check = () => {
                    if (typeof window.L !== 'undefined') {
                        resolve();
                        return;
                    }
                    if (attempts++ >= limit) {
                        reject(new Error('Leaflet not available'));
                        return;
                    }
                    setTimeout(check, 100);
                };
                check();
            });
        },

        bindGoogleAuthFailureHandler() {
            if (window.hvnlyGmAuthFailureBound) return;
            window.hvnlyGmAuthFailureBound = true;
            window.gm_authFailure = () => {
                this.showPlacesNotice(
                    'Google Maps could not authenticate this API key. Check key restrictions and enabled APIs.',
                    true
                );
            };
        },

        loadGoogleMaps(apiKey) {
            this.bindGoogleAuthFailureHandler();

            if (typeof window.google !== 'undefined' && window.google.maps) {
                return Promise.resolve();
            }
            if (this.googleLoadPromise) {
                return this.googleLoadPromise;
            }

            this.googleLoadPromise = new Promise((resolve, reject) => {
                const timeout = setTimeout(() => reject(new Error('Google Maps load timeout')), 20000);

                window.hvnlyGoogleMapsCallback = function () {
                    clearTimeout(timeout);
                    resolve();
                };

                if (!document.querySelector('script[data-hvnly-gmaps="1"]')) {
                    const script = document.createElement('script');
                    script.dataset.hvnlyGmaps = '1';
                    script.src =
                        'https://maps.googleapis.com/maps/api/js?key=' +
                        encodeURIComponent(apiKey) +
                        '&loading=async&libraries=places,marker&callback=hvnlyGoogleMapsCallback';
                    script.async = true;
                    script.defer = true;
                    script.onerror = function () {
                        clearTimeout(timeout);
                        reject(new Error('Google Maps script failed to load'));
                    };
                    document.head.appendChild(script);
                } else {
                    const start = Date.now();
                    const poll = () => {
                        if (typeof window.google !== 'undefined' && window.google.maps) {
                            clearTimeout(timeout);
                            resolve();
                        } else if (Date.now() - start > 20000) {
                            clearTimeout(timeout);
                            reject(new Error('Google Maps load timeout'));
                        } else {
                            setTimeout(poll, 100);
                        }
                    };
                    poll();
                }
            }).catch((err) => {
                this.googleLoadPromise = null;
                throw err;
            });

            return this.googleLoadPromise;
        },

        onMapCoordinatesChanged(lat, lng) {
            this.applyFromCoordinates(lat, lng, { force: true });
        },

        async createLeafletMap(lat, lng, provider) {
            await this.waitForLeaflet();
            const mapContainer = document.getElementById('import-map');
            if (!mapContainer) return;

            mapContainer.innerHTML = '';
            const tiles = this.getTileConfig(provider);

            this.leafletMap = window.L.map(mapContainer, { preferCanvas: true }).setView([lat, lng], 13);
            window.L.tileLayer(tiles.url, { attribution: tiles.attribution }).addTo(this.leafletMap);
            this.leafletMarker = window.L.marker([lat, lng], {
                draggable: true,
                icon: this.createLeafletIcon(),
            }).addTo(this.leafletMap);
            this.engine = 'leaflet';
        },

        getGoogleMapId() {
            const cfg = window.hvnlyImportWizard || {};
            return (cfg.googleMapId || '').trim();
        },

        async createGoogleMarker(lat, lng) {
            const position = { lat, lng };
            const mapId = this.getGoogleMapId();

            if (mapId && window.google.maps.importLibrary) {
                try {
                    const { AdvancedMarkerElement } = await window.google.maps.importLibrary('marker');
                    this.googleMarker = new AdvancedMarkerElement({
                        map: this.googleMap,
                        position,
                        gmpDraggable: true,
                    });
                    this.googleMarkerIsAdvanced = true;
                    this.googleMarker.addListener('dragend', () => {
                        const pos = this.googleMarker.position;
                        const latVal = typeof pos.lat === 'function' ? pos.lat() : pos.lat;
                        const lngVal = typeof pos.lng === 'function' ? pos.lng() : pos.lng;
                        this.onMapCoordinatesChanged(latVal, lngVal);
                    });
                    return;
                } catch (e) {
                    /* fallback */
                }
            }

            this.googleMarker = new window.google.maps.Marker({
                position,
                map: this.googleMap,
                draggable: true,
            });
            this.googleMarkerIsAdvanced = false;
            this.googleMarker.addListener('dragend', () => {
                const pos = this.googleMarker.getPosition();
                this.onMapCoordinatesChanged(pos.lat(), pos.lng());
            });
        },

        async createGoogleMap(lat, lng, apiKey) {
            await this.loadGoogleMaps(apiKey);
            const mapContainer = document.getElementById('import-map');
            if (!mapContainer) return;

            mapContainer.innerHTML = '';
            const mapOptions = {
                center: { lat, lng },
                zoom: 13,
                mapTypeControl: false,
                streetViewControl: false,
            };
            const mapId = this.getGoogleMapId();
            if (mapId) {
                mapOptions.mapId = mapId;
            }
            this.googleMap = new window.google.maps.Map(mapContainer, mapOptions);
            await this.createGoogleMarker(lat, lng);

            this.googleMap.addListener('click', (e) => {
                const clickLat = e.latLng.lat();
                const clickLng = e.latLng.lng();
                if (this.googleMarkerIsAdvanced) {
                    this.googleMarker.position = { lat: clickLat, lng: clickLng };
                } else {
                    this.googleMarker.setPosition(e.latLng);
                }
                this.onMapCoordinatesChanged(clickLat, clickLng);
            });

            this.engine = 'google';
        },

        scheduleInvalidate() {
            [100, 350, 700].forEach((delay) => {
                setTimeout(() => {
                    if (!this.engine) return;
                    if (this.isGoogleEngine() && this.googleMap && window.google?.maps?.event) {
                        window.google.maps.event.trigger(this.googleMap, 'resize');
                        const coords = this.readCoordinatesFromInputs();
                        this.googleMap.setCenter({ lat: coords.lat, lng: coords.lng });
                    } else if (this.isLeafletEngine() && this.leafletMap?.invalidateSize) {
                        this.leafletMap.invalidateSize(true);
                    }
                }, delay);
            });
        },

        panTo(lat, lng, options) {
            const opts = options || {};
            if (this.isLeafletEngine() && this.leafletMap && this.leafletMarker) {
                this.leafletMap.setView([lat, lng], this.leafletMap.getZoom() || 13);
                this.leafletMarker.setLatLng([lat, lng]);
                return;
            }
            if (this.isGoogleEngine() && this.googleMap) {
                const position = { lat, lng };
                if (!opts.silent) {
                    this.googleMap.panTo(position);
                } else {
                    this.googleMap.setCenter(position);
                }
                if (this.googleMarker) {
                    if (this.googleMarkerIsAdvanced) {
                        this.googleMarker.position = position;
                    } else {
                        this.googleMarker.setPosition(position);
                    }
                }
            }
        },

        bindLeafletHandlers() {
            if (!this.leafletMarker || !this.leafletMap) return;
            this.leafletMarker.on('dragend', (e) => {
                const ll = e.target.getLatLng();
                this.onMapCoordinatesChanged(ll.lat, ll.lng);
            });
            this.leafletMap.on('click', (e) => {
                this.leafletMarker.setLatLng(e.latlng);
                this.onMapCoordinatesChanged(e.latlng.lat, e.latlng.lng);
            });
        },

        async initLeafletProvider(provider, lat, lng) {
            this.teardownGooglePlacesUi();
            this.hidePlacesNotice();
            await this.createLeafletMap(lat, lng, provider);
            this.bindLeafletHandlers();
            this.setupAddressAutocomplete();
        },

        async initGoogleProvider(lat, lng) {
            this.clearSuggestionResults();
            const apiKey =
                document.getElementById('google-api-key')?.value ||
                window.hvnlyImportWizard?.googleApiKey ||
                '';
            if (!apiKey) {
                this.showMessage('Enter your Google Maps API key and click Save & Load Map.');
                return;
            }

            try {
                await this.createGoogleMap(lat, lng, apiKey);
                await this.setupGooglePlaces();
                if (!this.googlePlacesElementActive) {
                    this.setupAddressAutocomplete();
                }
            } catch (err) {
                this.showPlacesNotice(this.getString('mapLoadFailed'), true);
                await this.initLeafletProvider('openstreetmap', lat, lng);
            }
        },

        async init(provider, lat, lng) {
            this.activeProvider = provider;
            this.googleGeocodeFailed = false;
            this.cleanupProviderExclusiveUi(provider);
            this.bindCoordinateFieldSync();
            this.destroy();
            this.replaceMapContainer();
            this.showLoading();

            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

            if (this.usesLeafletProvider(provider)) {
                await this.initLeafletProvider(provider, lat, lng);
            } else if (provider === 'google') {
                await this.initGoogleProvider(lat, lng);
            }

            this.scheduleInvalidate();
        },

        scheduleInit(provider, isActiveCheck) {
            if (this.initTimer) clearTimeout(this.initTimer);
            this.initTimer = setTimeout(async () => {
                this.initTimer = null;
                if (typeof isActiveCheck === 'function' && !isActiveCheck()) return;
                if (this.isInitializing) return;
                this.isInitializing = true;
                try {
                    this.bindCoordinateFieldSync();
                    const coords = this.readCoordinatesFromInputs();
                    await this.init(provider, coords.lat, coords.lng);
                } catch (e) {
                    this.showMessage('Unable to load the map. Please try switching the provider or refreshing the page.');
                } finally {
                    this.isInitializing = false;
                }
            }, 120);
        },

        /** @deprecated */
        onCoordinatesChanged(lat, lng) {
            this.onMapCoordinatesChanged(lat, lng);
        },

        /** @deprecated */
        setupLeafletAddressAutocomplete() {
            this.setupAddressAutocomplete();
        },

        /** @deprecated */
        getCoordinatesFromInputs() {
            return this.readCoordinatesFromInputs();
        },

        bindLocationFieldSync() {
            this.bindCoordinateFieldSync();
            this.setupAddressAutocomplete();
        },

        resetLeafletAutocompleteBinding() {
            /* no-op – kept for wizard compatibility */
        },
    };

    ImportMapManager.handleDocumentClick = ImportMapManager.handleDocumentClick.bind(ImportMapManager);
    document.addEventListener('click', ImportMapManager.handleDocumentClick);

    window.HvnlyImportMapManager = ImportMapManager;
})(window);
