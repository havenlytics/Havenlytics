/**
 * Havenlytics Map Field Handler – per-instance live location sync.
 * @version 3.1.2
 */

(function ($) {
    'use strict';

    const mapI18n = (window.hvnlyMapFieldParams && window.hvnlyMapFieldParams.i18n) || {};
    const t = (key, fallback) => (mapI18n[key] && String(mapI18n[key])) || fallback;

    const COORD_PRECISION = 6;
    const ADDRESS_DEBOUNCE_MS = 500;
    const COORD_DEBOUNCE_MS = 300;
    const REVERSE_GEOCODE_DEBOUNCE_MS = 300;

    const mapInstances = new Map();
    const fieldToMapMap = new Map();

    class MapFieldController {
        constructor(config) {
            this.mapId = config.mapId;
            this.type = config.type;
            this.$container = config.$container;
            this.$wrapper = config.$wrapper;
            this.$latInput = config.$latInput;
            this.$lngInput = config.$lngInput;
            this.$addressInput = config.$addressInput;
            this.defaultZoom = config.defaultZoom || 13;
            this.tileUrl = config.tileUrl;
            this.tileAttribution = config.tileAttribution;
            this.googleApiKey = config.googleApiKey;
            this.jsCallback = config.jsCallback;

            this.map = null;
            this.marker = null;
            this.isUpdating = false;
            this.skipReverseGeocode = false;
            this.skipForwardGeocode = false;
            this.addressDebounceTimer = null;
            this.coordDebounceTimer = null;
            this.reverseGeocodeTimer = null;
            this.suggestDebounceTimer = null;
            this.reverseGeocodeSeq = 0;
            this.fieldSyncBound = false;
            this.autocompleteBound = false;

            this._leafletDragHandler = null;
            this._leafletDragEndHandler = null;
            this._leafletClickHandler = null;
            this.eventNs =
                '.hvnlyMapCtrl_' + String(this.mapId).replace(/[^a-zA-Z0-9_]/g, '_');
        }

        clampLat(lat) {
            const n = parseFloat(lat);
            if (isNaN(n)) return null;
            return Math.min(90, Math.max(-90, n));
        }

        clampLng(lng) {
            const n = parseFloat(lng);
            if (isNaN(n)) return null;
            return Math.min(180, Math.max(-180, n));
        }

        formatCoord(value) {
            const n = parseFloat(value);
            if (isNaN(n)) return '';
            return n.toFixed(COORD_PRECISION);
        }

        setFieldValue($field, value) {
            if (!$field || !$field.length) return;
            if ($field.val() === value) return;
            $field.val(value);
        }

        updateLiveDisplays(lat, lng, address) {
            const $info = this.$wrapper.find('.coordinates-info');
            if ($info.length) {
                $info.find('.current-lat').text(this.formatCoord(lat));
                $info.find('.current-lng').text(this.formatCoord(lng));
            }
            if (typeof address === 'string') {
                const $live = this.$wrapper.find('.hvnly-map-current-address');
                if ($live.length) {
                    $live.text(address || t('noAddressSet', 'No address set'));
                }
            }
            if (this.type === 'leaflet' && this.marker && this.marker.bindPopup) {
                this.marker.bindPopup(
                    t('locationPopup', 'Location') + '<br>' + t('latLabel', 'Lat:') + ' ' + this.formatCoord(lat) + '<br>' + t('lngLabel', 'Lng:') + ' ' + this.formatCoord(lng)
                );
            }
        }

        /** Live field + preview update without blocking map events. */
        updateFieldsLive(lat, lng, addressOverride) {
            const latVal = this.clampLat(lat);
            const lngVal = this.clampLng(lng);
            if (latVal === null || lngVal === null) return;

            this.setFieldValue(this.$latInput, this.formatCoord(latVal));
            this.setFieldValue(this.$lngInput, this.formatCoord(lngVal));

            const address =
                addressOverride !== undefined ? addressOverride : this.$addressInput.val();
            if (addressOverride !== undefined) {
                this.setFieldValue(this.$addressInput, addressOverride);
            }

            this.updateLiveDisplays(latVal, lngVal, address);
        }

        /** Instant UI sync – used on input-driven coordinate changes. */
        syncCoordinatesToFields(lat, lng, addressOverride) {
            if (this.isUpdating) return;
            this.isUpdating = true;
            this.updateFieldsLive(lat, lng, addressOverride);
            setTimeout(() => {
                this.isUpdating = false;
            }, 80);
        }

        moveMarkerTo(lat, lng, zoom) {
            const latVal = this.clampLat(lat);
            const lngVal = this.clampLng(lng);
            if (latVal === null || lngVal === null) return;

            if (this.type === 'leaflet' && this.map && this.marker) {
                const z = zoom || this.map.getZoom() || this.defaultZoom;
                this.map.setView([latVal, lngVal], z);
                this.marker.setLatLng([latVal, lngVal]);
                return;
            }
            if (this.type === 'google' && this.map && this.marker) {
                this.map.setCenter({ lat: latVal, lng: lngVal });
                if (zoom) this.map.setZoom(zoom);
                this.marker.setPosition({ lat: latVal, lng: lngVal });
            }
        }

        /** Marker moved on map – update fields immediately, reverse-geocode address. */
        onMapPositionChanged(lat, lng) {
            this.updateFieldsLive(lat, lng);
            if (!this.skipReverseGeocode) {
                this.scheduleReverseGeocode(lat, lng);
            }
        }

        /** Lat/lng inputs or external coordinate change – move marker + reverse geocode. */
        onInputCoordinatesChanged(lat, lng) {
            if (this.isUpdating || this.skipForwardGeocode) return;
            this.syncCoordinatesToFields(lat, lng);
            this.moveMarkerTo(lat, lng);
            if (!this.skipReverseGeocode) {
                this.scheduleReverseGeocode(lat, lng);
            }
        }

        /** Address suggestion or geocode button – all fields known. */
        onLocationResolved(address, lat, lng) {
            this.skipReverseGeocode = true;
            this.skipForwardGeocode = true;

            this.updateFieldsLive(lat, lng, address);
            this.moveMarkerTo(lat, lng, 15);

            const self = this;
            setTimeout(function () {
                self.skipReverseGeocode = false;
                self.skipForwardGeocode = false;
            }, 600);
        }

        onAddressInputChanged(address) {
            const trimmed = String(address || '').trim();
            if (trimmed.length < 3) return;
            if (this.skipForwardGeocode || this.isUpdating) return;

            this.forwardGeocode(trimmed)
                .then((result) => {
                    if (!result) return;
                    this.onLocationResolved(result.address || trimmed, result.lat, result.lng);
                })
                .catch(() => {});
        }

        isLikelyCoordinateString(value) {
            if (!value || typeof value !== 'string') return false;
            return /^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/.test(value.trim());
        }

        scheduleReverseGeocode(lat, lng) {
            if (this.reverseGeocodeTimer) {
                clearTimeout(this.reverseGeocodeTimer);
            }
            const self = this;
            const requestId = ++this.reverseGeocodeSeq;
            this.reverseGeocodeTimer = setTimeout(function () {
                self.reverseGeocodeTimer = null;
                if (self.skipReverseGeocode) return;

                self.reverseGeocode(lat, lng)
                    .then(function (address) {
                        if (!address || self.skipReverseGeocode) return;
                        if (requestId !== self.reverseGeocodeSeq) return;
                        if (self.isLikelyCoordinateString(address)) return;

                        self.skipForwardGeocode = true;
                        self.updateFieldsLive(lat, lng, address);
                        setTimeout(function () {
                            self.skipForwardGeocode = false;
                        }, 500);
                    })
                    .catch(function () {});
            }, REVERSE_GEOCODE_DEBOUNCE_MS);
        }

        forwardGeocodeNominatim(address) {
            const url =
                'https://nominatim.openstreetmap.org/search?format=json&q=' +
                encodeURIComponent(address) +
                '&limit=1';
            return $.ajax({ url: url, timeout: 8000 }).then(function (data) {
                if (!data || !data.length) return null;
                return {
                    lat: parseFloat(data[0].lat),
                    lng: parseFloat(data[0].lon),
                    address: data[0].display_name || address,
                };
            });
        }

        forwardGeocode(address) {
            const self = this;
            if (this.type === 'google' && typeof google !== 'undefined' && google.maps?.Geocoder) {
                return new Promise(function (resolve) {
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ address: address }, function (results, status) {
                        if (status === 'OK' && results && results[0] && results[0].geometry) {
                            const loc = results[0].geometry.location;
                            resolve({
                                lat: loc.lat(),
                                lng: loc.lng(),
                                address: results[0].formatted_address || address,
                            });
                        } else {
                            self.forwardGeocodeNominatim(address).then(resolve);
                        }
                    });
                });
            }

            return this.forwardGeocodeNominatim(address);
        }

        reverseGeocodeNominatim(lat, lng) {
            const url =
                'https://nominatim.openstreetmap.org/reverse?format=json&lat=' +
                encodeURIComponent(lat) +
                '&lon=' +
                encodeURIComponent(lng) +
                '&zoom=18';
            return $.ajax({ url: url, timeout: 5000 }).then(function (data) {
                return data && data.display_name ? data.display_name : null;
            });
        }

        reverseGeocode(lat, lng) {
            const self = this;
            if (this.type === 'google' && typeof google !== 'undefined' && google.maps?.Geocoder) {
                return new Promise(function (resolve) {
                    const geocoder = new google.maps.Geocoder();
                    geocoder.geocode({ location: { lat: lat, lng: lng } }, function (results, status) {
                        if (status === 'OK' && results && results[0] && results[0].formatted_address) {
                            resolve(results[0].formatted_address);
                        } else {
                            self.reverseGeocodeNominatim(lat, lng).then(resolve);
                        }
                    });
                });
            }

            return this.reverseGeocodeNominatim(lat, lng);
        }

        bindFieldSync() {
            if (this.fieldSyncBound) return;
            this.fieldSyncBound = true;
            const ns = this.eventNs;
            const self = this;

            const onCoordInput = function () {
                if (self.isUpdating || self.skipForwardGeocode) return;
                clearTimeout(self.coordDebounceTimer);
                self.coordDebounceTimer = setTimeout(function () {
                    const lat = self.clampLat(self.$latInput.val());
                    const lng = self.clampLng(self.$lngInput.val());
                    if (lat === null || lng === null) return;
                    self.onInputCoordinatesChanged(lat, lng);
                }, COORD_DEBOUNCE_MS);
            };

            this.$latInput.off('input' + ns + ' change' + ns);
            this.$lngInput.off('input' + ns + ' change' + ns);
            this.$addressInput.off('input' + ns);

            this.$latInput.on('input' + ns + ' change' + ns, onCoordInput);
            this.$lngInput.on('input' + ns + ' change' + ns, onCoordInput);

            this.$addressInput.on('input' + ns, function () {
                if (self.isUpdating || self.skipReverseGeocode || self.skipForwardGeocode) return;
                const query = self.$addressInput.val().trim();
                clearTimeout(self.addressDebounceTimer);
                if (query.length < 3) return;
                self.addressDebounceTimer = setTimeout(function () {
                    self.onAddressInputChanged(query);
                }, ADDRESS_DEBOUNCE_MS);
            });
        }

        bindAutocomplete() {
            if (this.autocompleteBound || this.type === 'google') return;
            this.autocompleteBound = true;

            const ns = this.eventNs;
            const self = this;
            const $input = this.$addressInput;
            let $suggestions = $input.siblings('.hvnly-address-suggestions');

            if (!$suggestions.length) {
                $suggestions = $('<div class="hvnly-address-suggestions"></div>').css({
                    display: 'none',
                    position: 'absolute',
                    top: '100%',
                    left: 0,
                    right: 0,
                    background: '#fff',
                    border: '1px solid #ccc',
                    borderTop: 'none',
                    zIndex: 10000,
                    maxHeight: '200px',
                    overflowY: 'auto',
                    boxShadow: '0 2px 5px rgba(0,0,0,0.1)',
                });
                $input.parent().css('position', 'relative').append($suggestions);
            }

            $input.off('input' + ns + 'Suggest');
            $input.on('input' + ns + 'Suggest', function () {
                if (self.isUpdating || self.skipForwardGeocode) return;
                const query = $input.val().trim();
                clearTimeout(self.suggestDebounceTimer);
                if (query.length < 3) {
                    $suggestions.hide().empty();
                    return;
                }
                self.suggestDebounceTimer = setTimeout(function () {
                    self.fetchSuggestions(query, $suggestions);
                }, ADDRESS_DEBOUNCE_MS);
            });

            $suggestions.off('click' + ns);
            $suggestions.on('click' + ns, 'div[data-lat]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const $item = $(this);
                const lat = parseFloat($item.attr('data-lat'));
                const lng = parseFloat($item.attr('data-lng'));
                const displayName = $item.attr('data-displayname');
                if (isNaN(lat) || isNaN(lng)) return;
                self.onLocationResolved(displayName, lat, lng);
                $suggestions.hide().empty();
            });
        }

        fetchSuggestions(query, $container) {
            const self = this;
            const url =
                'https://nominatim.openstreetmap.org/search?format=json&q=' +
                encodeURIComponent(query) +
                '&limit=5';
            $.ajax({
                url: url,
                success: function (data) {
                    $container.empty();
                    if (!data || !data.length) {
                        $container
                            .append(
                                '<div style="padding:10px;color:#666;">' + t('noAddressesFound', 'No addresses found') + '</div>'
                            )
                            .show();
                        return;
                    }
                    data.forEach(function (suggestion) {
                        const lat = parseFloat(suggestion.lat);
                        const lng = parseFloat(suggestion.lon);
                        $('<div></div>')
                            .css({
                                padding: '8px 12px',
                                cursor: 'pointer',
                                borderBottom: '1px solid #eee',
                                fontSize: '13px',
                            })
                            .text(suggestion.display_name)
                            .attr('data-lat', lat)
                            .attr('data-lng', lng)
                            .attr('data-displayname', suggestion.display_name)
                            .appendTo($container);
                    });
                    $container.show();
                },
                error: function () {
                    $container
                        .html(
                            '<div style="padding:10px;color:#d63638;">Error loading suggestions</div>'
                        )
                        .show();
                },
            });
        }

        bindGeocodeButton() {
            const self = this;
            const ns = this.eventNs;
            const $button = this.$wrapper.find('.hvnly-geocode-address-btn');
            if (!$button.length) return;

            $button.off('click' + ns);
            $button.on('click' + ns, function (e) {
                e.preventDefault();
                const address = self.$addressInput.val().trim();
                if (!address) {
                    alert(t('enterAddressFirst', 'Please enter an address first.'));
                    return;
                }
                $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + t('searching', 'Searching…'));
                Promise.resolve(self.forwardGeocode(address))
                    .then(function (result) {
                        if (result) {
                            self.onLocationResolved(result.address || address, result.lat, result.lng);
                            alert(t('locationFound', 'Location found!'));
                        } else {
                            alert(t('addressNotFound', 'Address not found.'));
                        }
                    })
                    .catch(function () {
                        alert(t('searchError', 'Error searching for address.'));
                    })
                    .finally(function () {
                        $button
                            .prop('disabled', false)
                            .html('<i class="fas fa-map-pin"></i> ' + t('getCoordinates', 'Get Coordinates from Address'));
                    });
            });
        }

        bindLeafletMapEvents() {
            if (!this.marker || !this.map) return;
            const self = this;

            if (this._leafletDragHandler) {
                this.marker.off('drag', this._leafletDragHandler);
            }
            if (this._leafletDragEndHandler) {
                this.marker.off('dragend', this._leafletDragEndHandler);
            }
            if (this._leafletClickHandler) {
                this.map.off('click', this._leafletClickHandler);
            }

            this._leafletDragHandler = function (e) {
                const ll = e.target.getLatLng();
                self.updateFieldsLive(ll.lat, ll.lng);
            };

            this._leafletDragEndHandler = function (e) {
                const ll = e.target.getLatLng();
                self.onMapPositionChanged(ll.lat, ll.lng);
            };

            this._leafletClickHandler = function (e) {
                self.marker.setLatLng(e.latlng);
                self.onMapPositionChanged(e.latlng.lat, e.latlng.lng);
            };

            this.marker.on('drag', this._leafletDragHandler);
            this.marker.on('dragend', this._leafletDragEndHandler);
            this.map.on('click', this._leafletClickHandler);
        }

        bindGoogleMapEvents() {
            if (!this.marker || !this.map) return;
            const self = this;

            google.maps.event.clearListeners(this.marker, 'drag');
            google.maps.event.clearListeners(this.marker, 'dragend');
            google.maps.event.clearListeners(this.map, 'click');

            this.marker.addListener('drag', function () {
                const pos = self.marker.getPosition();
                const lat = pos.lat();
                const lng = pos.lng();
                self.updateFieldsLive(lat, lng);
                if (!self.skipReverseGeocode) {
                    self.scheduleReverseGeocode(lat, lng);
                }
            });

            this.marker.addListener('dragend', function () {
                const pos = self.marker.getPosition();
                self.onMapPositionChanged(pos.lat(), pos.lng());
            });

            this.map.addListener('click', function (e) {
                self.marker.setPosition(e.latLng);
                self.onMapPositionChanged(e.latLng.lat(), e.latLng.lng());
            });
        }

        registerFieldMappings() {
            const self = this;
            [this.$latInput, this.$lngInput, this.$addressInput].forEach(function ($input) {
                const id = $input.attr('id');
                if (id) fieldToMapMap.set(id, self.mapId);
            });
        }

        initLeafletMap(initialLat, initialLng) {
            this.$container.empty();
            try {
                const map = L.map(this.$container.attr('id')).setView(
                    [initialLat, initialLng],
                    this.defaultZoom
                );
                L.tileLayer(this.tileUrl, { attribution: this.tileAttribution }).addTo(map);

                const brandColor = (window.hvnlyMapFieldParams && window.hvnlyMapFieldParams.brandColor) || '#6C60FE';
                const customIcon = L.divIcon({
                    className: 'hvnly-fontawesome-marker',
                    html: `<i class="fas fa-map-marker-alt" style="font-size: 28px; color: ${brandColor};"></i>`,
                    iconSize: [28, 28],
                    iconAnchor: [14, 28],
                    popupAnchor: [0, -28],
                });

                const marker = L.marker([initialLat, initialLng], {
                    draggable: true,
                    icon: customIcon,
                }).addTo(map);

                this.map = map;
                this.marker = marker;
                this.$container.data('leaflet-map', map);

                this.bindFieldSync();
                this.bindAutocomplete();
                this.bindGeocodeButton();
                this.bindLeafletMapEvents();
                this.updateLiveDisplays(
                    initialLat,
                    initialLng,
                    this.$addressInput.val() || ''
                );

                setTimeout(function () {
                    map.invalidateSize();
                }, 200);
            } catch (error) {
                this.$container.html(
                    '<div style="padding:20px;text-align:center;color:red;">Map error</div>'
                );
            }
        }

        initGoogleMap(finalLat, finalLng) {
            if (typeof google === 'undefined' || !google.maps) return;

            try {
                this.$container.empty();
                const map = new google.maps.Map(this.$container[0], {
                    center: { lat: finalLat, lng: finalLng },
                    zoom: this.defaultZoom,
                    mapTypeId: 'roadmap',
                });
                const marker = new google.maps.Marker({
                    position: { lat: finalLat, lng: finalLng },
                    map: map,
                    draggable: true,
                });

                this.map = map;
                this.marker = marker;

                this.bindFieldSync();
                this.bindGeocodeButton();
                this.bindGoogleMapEvents();
                this.updateLiveDisplays(finalLat, finalLng, this.$addressInput.val() || '');
            } catch (error) {
                this.$container.html(
                    '<div style="padding:20px;text-align:center;color:red;">Error: ' +
                        error.message +
                        '</div>'
                );
            }
        }

        waitForLeaflet(callback, attempts) {
            let count = attempts || 0;
            if (typeof L !== 'undefined') {
                callback();
                return;
            }
            if (count >= 50) return;
            const self = this;
            setTimeout(function () {
                self.waitForLeaflet(callback, count + 1);
            }, 100);
        }
    }

    function resolveMapInputs($container) {
        const $wrapper = $container.closest('.hvnly-map-field-wrapper');
        const latFieldName = $container.data('lat-field-name');
        const lngFieldName = $container.data('lng-field-name');
        const addressFieldName = $container.data('address-field-name');

        return {
            $wrapper: $wrapper,
            $latInput: $wrapper.find('[name="' + latFieldName + '"]').first(),
            $lngInput: $wrapper.find('[name="' + lngFieldName + '"]').first(),
            $addressInput: $wrapper.find('[name="' + addressFieldName + '"]').first(),
        };
    }

    function registerController(controller) {
        mapInstances.set(controller.mapId, controller);
        controller.registerFieldMappings();
    }

    function initLeafletMap($container) {
        const mapId = $container.attr('id');
        if (!mapId) return;

        if (mapInstances.has(mapId)) {
            const existing = mapInstances.get(mapId);
            if (existing.map && existing.marker) {
                setTimeout(function () {
                    existing.map.invalidateSize();
                }, 100);
            }
            return;
        }

        const inputs = resolveMapInputs($container);
        if (!inputs.$latInput.length || !inputs.$lngInput.length || !inputs.$addressInput.length) {
            return;
        }

        const initialLat = parseFloat($container.data('initial-lat')) || 51.505;
        const initialLng = parseFloat($container.data('initial-lng')) || -0.09;
        const defaultZoom = parseInt($container.data('default-zoom'), 10) || 13;
        const finalLat =
            inputs.$latInput.val() && !isNaN(parseFloat(inputs.$latInput.val()))
                ? parseFloat(inputs.$latInput.val())
                : initialLat;
        const finalLng =
            inputs.$lngInput.val() && !isNaN(parseFloat(inputs.$lngInput.val()))
                ? parseFloat(inputs.$lngInput.val())
                : initialLng;

        const controller = new MapFieldController({
            mapId: mapId,
            type: 'leaflet',
            $container: $container,
            $wrapper: inputs.$wrapper,
            $latInput: inputs.$latInput,
            $lngInput: inputs.$lngInput,
            $addressInput: inputs.$addressInput,
            defaultZoom: defaultZoom,
            tileUrl:
                $container.data('tile-url') ||
                'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            tileAttribution: $container.data('tile-attribution') || '© OpenStreetMap contributors',
        });

        registerController(controller);
        controller.waitForLeaflet(function () {
            controller.initLeafletMap(finalLat, finalLng);
        });
    }

    function initGoogleMap($container) {
        const mapId = $container.attr('id');
        if (!mapId || mapInstances.has(mapId)) return;

        const inputs = resolveMapInputs($container);
        if (!inputs.$latInput.length || !inputs.$lngInput.length || !inputs.$addressInput.length) {
            return;
        }

        const initialLat = parseFloat($container.data('initial-lat')) || 51.505;
        const initialLng = parseFloat($container.data('initial-lng')) || -0.09;
        const defaultZoom = parseInt($container.data('default-zoom'), 10) || 13;
        const googleApiKey = $container.data('google-api-key');
        const jsCallback = $container.data('js-callback') || 'initGoogleMap_' + mapId;

        const finalLat =
            inputs.$latInput.val() && !isNaN(parseFloat(inputs.$latInput.val()))
                ? parseFloat(inputs.$latInput.val())
                : initialLat;
        const finalLng =
            inputs.$lngInput.val() && !isNaN(parseFloat(inputs.$lngInput.val()))
                ? parseFloat(inputs.$lngInput.val())
                : initialLng;

        const controller = new MapFieldController({
            mapId: mapId,
            type: 'google',
            $container: $container,
            $wrapper: inputs.$wrapper,
            $latInput: inputs.$latInput,
            $lngInput: inputs.$lngInput,
            $addressInput: inputs.$addressInput,
            defaultZoom: defaultZoom,
            googleApiKey: googleApiKey,
            jsCallback: jsCallback,
        });

        registerController(controller);

        window[jsCallback] = function () {
            controller.initGoogleMap(finalLat, finalLng);
        };

        if (!$('#google-maps-api-script').length) {
            const script = document.createElement('script');
            script.id = 'google-maps-api-script';
            script.src =
                'https://maps.googleapis.com/maps/api/js?key=' +
                googleApiKey +
                '&callback=' +
                jsCallback;
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        } else if (typeof google !== 'undefined' && google.maps) {
            controller.initGoogleMap(finalLat, finalLng);
        } else {
            setTimeout(function () {
                if (typeof google !== 'undefined' && google.maps) {
                    controller.initGoogleMap(finalLat, finalLng);
                } else {
                    $container.html(
                        '<div style="padding:20px;text-align:center;color:red;">Google Maps failed to load</div>'
                    );
                }
            }, 2000);
        }
    }

    function initAllMaps() {
        $('.hvnly-leaflet-map-container').each(function () {
            initLeafletMap($(this));
        });
        $('.hvnly-google-map-container').each(function () {
            initGoogleMap($(this));
        });
    }

    $(document).ready(function () {
        setTimeout(initAllMaps, 200);

        $(document).on('click', '.hvnly__dyamic_metabox_tab__nav a', function () {
            setTimeout(initAllMaps, 400);
        });
    });
})(jQuery);
