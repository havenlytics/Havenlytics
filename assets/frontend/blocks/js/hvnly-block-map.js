/**
 * Havenlytics Property Map block controller.
 *
 * Follows the GLOBAL map provider (Archive Map SSOT): Leaflet, OpenStreetMap
 * Direct (both Leaflet-rendered) or Google Maps. Provider resolution happens
 * server-side via hvnly_get_map_config(); this file only consumes
 * cfg.provider. Markers/popups reuse one shared HTML builder so the card is
 * identical on every provider. Reuses the existing
 * hvnly_get_properties_for_map AJAX endpoint.
 *
 * Iframe-safe: every DOM lookup is resolved against the root node's own
 * document/window; a root is only marked ready after a successful build.
 *
 * @package Havenlytics
 * @since   3.5.0
 */
( function () {
	'use strict';

	var MARKER_SIZES = {
		sm: { pin: [ 38, 48 ], pinAnchor: [ 19, 43 ], pinPopup: [ 0, -40 ], dot: 18 },
		md: { pin: [ 46, 58 ], pinAnchor: [ 23, 52 ], pinPopup: [ 0, -48 ], dot: 24 },
		lg: { pin: [ 54, 68 ], pinAnchor: [ 27, 61 ], pinPopup: [ 0, -56 ], dot: 30 },
	};

	// Entrance stagger constants — the arrival window below is DERIVED from
	// these (not an arbitrary delay): capped index × step + duration.
	var ENTER_STEP_MS = 35;
	var ENTER_DURATION_MS = 500;
	var ENTER_MAX_INDEX = 20;

	function esc( str ) {
		return String( str == null ? '' : str ).replace( /[&<>"']/g, function ( c ) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#39;',
			}[ c ];
		} );
	}

	function show( cfg, key ) {
		// Absent key (old saved blocks) => shown. Only an explicit false hides.
		return cfg[ key ] !== false;
	}

	/**
	 * Sync heart buttons injected into a freshly opened popup with the shared
	 * favorites module (same hydrate used after AJAX card renders).
	 *
	 * @return {void}
	 */
	function hydratePopupFavorites() {
		if ( window.hvnlyFavorites && typeof window.hvnlyFavorites.hydrate === 'function' ) {
			window.hvnlyFavorites.hydrate();
		}
	}

	function prefersReducedMotion( win ) {
		return !! (
			win.matchMedia && win.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
		);
	}

	/**
	 * Google readiness = the Map CONSTRUCTOR exists, not the namespace.
	 * With `loading=async` the API script evaluates its bootstrap first (so
	 * `google.maps` exists) while the actual modules keep loading; only the
	 * `callback` (initHvnlyMap → hvnlyGoogleMapsLoaded — the Archive Map's
	 * bridge) guarantees `google.maps.Map` is constructible. Checking the
	 * namespace alone hits the gap and throws "Map is not a constructor".
	 *
	 * @param {Window} win Window to inspect.
	 * @return {Object|null} The ready google namespace, or null.
	 */
	function googleReady( win ) {
		var g =
			win.google && win.google.maps && typeof win.google.maps.Map === 'function'
				? win.google
				: null;
		if ( ! g && window.google && window.google.maps && typeof window.google.maps.Map === 'function' ) {
			g = window.google;
		}
		return g;
	}

	function popupHtml( p, cfg ) {
		var stats = '';
		if ( show( cfg, 'showMeta' ) ) {
			if ( p.bedrooms ) {
				stats +=
					'<span class="hvnly-block-map__popup-stat"><i class="fas fa-bed" aria-hidden="true"></i>' +
					esc( p.bedrooms ) +
					'</span>';
			}
			if ( p.bathrooms ) {
				stats +=
					'<span class="hvnly-block-map__popup-stat"><i class="fas fa-bath" aria-hidden="true"></i>' +
					esc( p.bathrooms ) +
					'</span>';
			}
			if ( p.area ) {
				stats +=
					'<span class="hvnly-block-map__popup-stat"><i class="fas fa-vector-square" aria-hidden="true"></i>' +
					esc( p.area ) +
					'</span>';
			}
		}

		var img = p.thumbnail
			? '<img class="hvnly-block-map__popup-img" src="' +
			  esc( p.thumbnail ) +
			  '" alt="' +
			  esc( p.title ) +
			  '" loading="lazy">'
			: '<div class="hvnly-block-map__popup-img hvnly-block-map__popup-img--empty"></div>';

		var i18n = cfg.i18n || {};
		var t = function ( key, fallback ) {
			return i18n[ key ] || fallback || key;
		};
		var title = p.title ? esc( p.title ) : '';

		var status =
			show( cfg, 'showStatus' ) && p.status
				? '<span class="hvnly-block-map__popup-status">' + esc( p.status ) + '</span>'
				: '';

		var fav = show( cfg, 'showFavorite' )
			? '<button type="button" class="hvnly-property--grid-list--favorite hvnly-block-map__popup-fav"' +
			  ' data-hvnly-favorite="1" data-property-id="' +
			  esc( p.id ) +
			  '"' +
			  ' data-property-title="' +
			  title +
			  '" data-property-thumb="' +
			  esc( p.thumbnail ) +
			  '"' +
			  ' aria-pressed="false" aria-label="' +
			  esc( t('save') ) +
			  ' ' +
			  ( title || t('untitledProperty') ) +
			  '"><i class="far fa-heart" aria-hidden="true"></i></button>'
			: '';

		var price =
			show( cfg, 'showPrice' ) && p.price
				? '<span class="hvnly-block-map__popup-price">' + esc( p.price ) + '</span>'
				: '';

		var cta = show( cfg, 'showCta' )
			? '<a class="hvnly-block-map__popup-cta" href="' +
			  esc( p.link ) +
			  '" aria-label="' +
			  esc( t('view') ) +
			  ' ' +
			  ( title || t('untitledProperty') ) +
			  '"><span>' +
			  esc( t('viewProperty') ) +
			  '</span><i class="fas fa-arrow-right" aria-hidden="true"></i></a>'
			: '';

		var cardClass =
			'hvnly-block-map__popup' +
			( cfg.popupStyle === 'compact' ? ' hvnly-block-map__popup--compact' : '' );

		return (
			'<div class="' +
			cardClass +
			'" role="group" aria-label="' +
			( title || t('untitledProperty') ) +
			'" style="--hvnly-block-map-popup-w:' +
			parseInt( cfg.popupWidth || 300, 10 ) +
			'px">' +
			'<div class="hvnly-block-map__popup-media">' +
			img +
			status +
			fav +
			price +
			'</div>' +
			'<div class="hvnly-block-map__popup-body">' +
			'<h3 class="hvnly-block-map__popup-title">' +
			'<a class="hvnly-block-map__popup-title-link" href="' +
			esc( p.link ) +
			'">' +
			title +
			'</a></h3>' +
			( p.address
				? '<div class="hvnly-block-map__popup-addr"><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>' +
				  esc( p.address ) +
				  '</span></div>'
				: '' ) +
			( stats ? '<div class="hvnly-block-map__popup-stats">' + stats + '</div>' : '' ) +
			cta +
			'</div></div>'
		);
	}

	function markerHtml( brand, style, index ) {
		var vars =
			'--hvnly-block-map-pin:' +
			esc( brand ) +
			';--hvnly-mkr-i:' +
			Math.min( parseInt( index || 0, 10 ), ENTER_MAX_INDEX );

		if ( style === 'dot' ) {
			return (
				'<span class="hvnly-block-map__marker-inner" style="' +
				vars +
				'">' +
				'<span class="hvnly-block-map__marker-pulse"></span>' +
				'<span class="hvnly-block-map__marker-dotpin"></span>' +
				'</span>'
			);
		}

		return (
			'<span class="hvnly-block-map__marker-inner" style="' +
			vars +
			'">' +
			'<span class="hvnly-block-map__marker-shadow"></span>' +
			'<span class="hvnly-block-map__marker-pulse"></span>' +
			'<span class="hvnly-block-map__marker-pin"><span class="hvnly-block-map__marker-dot"></span></span>' +
			'</span>'
		);
	}

	function request( cfg, onDone, onError ) {
		var body = 'action=hvnly_get_properties_for_map';
		body += '&nonce=' + encodeURIComponent( cfg.nonce || '' );
		body += '&per_page=' + encodeURIComponent( cfg.perPage || 48 );
		var f = cfg.filters || {};
		Object.keys( f ).forEach( function ( k ) {
			if ( f[ k ] !== '' && f[ k ] != null ) {
				body +=
					'&' +
					encodeURIComponent( k ) +
					'=' +
					encodeURIComponent( f[ k ] );
			}
		} );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', cfg.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.onload = function () {
			try {
				var res = JSON.parse( xhr.responseText );
				if ( res && res.success && res.data && res.data.properties ) {
					onDone( res.data.properties );
					return;
				}
			} catch ( e ) {
				// fall through to error
			}
			onError();
		};
		xhr.onerror = function () {
			onError();
		};
		xhr.send( body );
	}

	function teardown( root ) {
		if ( root._hvnlyBlockMap ) {
			try {
				if ( typeof root._hvnlyBlockMap.remove === 'function' ) {
					root._hvnlyBlockMap.remove(); // Leaflet
				}
			} catch ( e ) {
				// already gone
			}
			root._hvnlyBlockMap = null;
		}
		if ( root._hvnlyBlockMapRO ) {
			root._hvnlyBlockMapRO.disconnect();
			root._hvnlyBlockMapRO = null;
		}
	}

	function renderError( root, loading, retryFn ) {
		if ( ! loading ) {
			return;
		}
		var cfg = {};
		try {
			cfg = JSON.parse( root.getAttribute( 'data-config' ) || '{}' );
		} catch ( e ) {
			cfg = {};
		}
		var i18n = cfg.i18n || {};
		var loadingLabel = i18n.loadingMap || '';
		var errorLabel = i18n.couldNotLoadProperties || '';
		var retryLabel = i18n.retry || '';
		loading.hidden = false;
		loading.classList.add( 'is-error' );
		loading.innerHTML =
			'<span class="hvnly-block-map__error-text">' +
			errorLabel +
			'</span>' +
			'<button type="button" class="hvnly-block-map__error-retry">' +
			retryLabel +
			'</button>';
		var retry = loading.querySelector( '.hvnly-block-map__error-retry' );
		if ( retry ) {
			retry.addEventListener( 'click', function () {
				loading.classList.remove( 'is-error' );
				loading.innerHTML = loadingLabel;
				retryFn();
			} );
		}
	}

	/**
	 * Add the entrance-window class and remove it after the stagger completes.
	 * The removal delay is DERIVED from the animation constants (capped index
	 * × step + duration) — deterministic, not an arbitrary timeout.
	 */
	function runArrival( root, win, markerCount, animations ) {
		if ( ! animations || prefersReducedMotion( win ) ) {
			return;
		}
		root.classList.add( 'is-arriving' );
		var total =
			Math.min( markerCount, ENTER_MAX_INDEX ) * ENTER_STEP_MS +
			ENTER_DURATION_MS +
			50;
		win.setTimeout( function () {
			root.classList.remove( 'is-arriving' );
		}, total );
	}

	/* ------------------------------------------------------------------ */
	/* Leaflet / OpenStreetMap Direct                                       */
	/* ------------------------------------------------------------------ */
	function buildLeaflet( root, cfg, canvas, doc, win ) {
		var L = win.L || window.L;
		if ( ! L ) {
			return false;
		}

		var interactive = cfg.interactive !== false;
		var brand = cfg.markerColor || '#6c60fe';
		var animations = cfg.animations !== false;
		var markerStyle = cfg.markerStyle === 'dot' ? 'dot' : 'pin';
		var sizeKey = MARKER_SIZES[ cfg.markerSize ] ? cfg.markerSize : 'md';
		var size = MARKER_SIZES[ sizeKey ];
		var popupWidth = Math.min( 360, Math.max( 240, parseInt( cfg.popupWidth || 300, 10 ) ) );
		// Never wider than the viewport (small screens): clamping HERE keeps
		// Leaflet's inline content width and the card width in agreement.
		popupWidth = Math.min( popupWidth, Math.max( 220, ( win.innerWidth || 1024 ) - 56 ) );
		cfg.popupWidth = popupWidth;
		var hoverMode =
			cfg.popupTrigger === 'hover' &&
			interactive &&
			! ( win.matchMedia && win.matchMedia( '(hover: none)' ).matches );
		var center =
			cfg.center && cfg.center.lat
				? [ cfg.center.lat, cfg.center.lng ]
				: [ 51.514939, -0.091839 ];

		if ( ! animations ) {
			root.classList.add( 'hvnly-block-map--no-anim' );
		}

		var map = L.map( canvas, {
			zoomControl: false,
			scrollWheelZoom: interactive && !! cfg.scrollWheel,
			dragging: interactive,
			doubleClickZoom: interactive,
			boxZoom: interactive,
			keyboard: interactive,
			tap: interactive,
		} ).setView( center, cfg.zoom || 12 );

		L.tileLayer( cfg.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: cfg.attribution || '&copy; OpenStreetMap contributors',
			maxZoom: 19,
		} ).addTo( map );

		var clusterOpts = {
			showCoverageOnHover: false,
			spiderfyOnMaxZoom: true,
			maxClusterRadius: Math.min( 180, Math.max( 20, parseInt( cfg.clusterRadius || 48, 10 ) ) ),
		};
		var clusterMaxZoom = parseInt( cfg.clusterMaxZoom || 0, 10 );
		if ( clusterMaxZoom > 0 ) {
			clusterOpts.disableClusteringAtZoom = clusterMaxZoom;
		}

		var group =
			cfg.cluster && typeof L.markerClusterGroup === 'function'
				? L.markerClusterGroup( clusterOpts )
				: L.layerGroup();

		var loading = root.querySelector( '.hvnly-block-map__loading' );
		var activeMarker = null;
		var firstMarker = null;

		function makeIcon( index ) {
			var iconSize, iconAnchor, popupAnchor;
			if ( markerStyle === 'dot' ) {
				var d = size.dot + 10;
				iconSize = [ d, d ];
				iconAnchor = [ d / 2, d / 2 ];
				popupAnchor = [ 0, -( size.dot / 2 + 12 ) ];
			} else {
				iconSize = size.pin;
				iconAnchor = size.pinAnchor;
				popupAnchor = size.pinPopup;
			}
			return L.divIcon( {
				className:
					'hvnly-block-map__marker hvnly-block-map__marker--' +
					sizeKey +
					' hvnly-block-map__marker--' +
					markerStyle,
				html: markerHtml( cfg.markerColor || brand, markerStyle, index ),
				iconSize: iconSize,
				iconAnchor: iconAnchor,
				popupAnchor: popupAnchor,
			} );
		}

		// Keep Leaflet's coordinate math in sync with the real canvas size —
		// replaces timeout-based invalidateSize guessing. pendingFit: when the
		// map builds inside a not-yet-laid-out container (hidden tab, editor
		// iframe mid-layout) fitBounds runs against a zero-width viewport —
		// re-fit once real dimensions arrive.
		var pendingFitBounds = null;
		if ( win.ResizeObserver ) {
			var ro = new win.ResizeObserver( function () {
				map.invalidateSize();
				if ( pendingFitBounds && map.getSize().x > 0 ) {
					map.fitBounds( pendingFitBounds, { padding: [ 48, 48 ], maxZoom: 15 } );
					pendingFitBounds = null;
				}
			} );
			ro.observe( canvas );
			root._hvnlyBlockMapRO = ro;
		} else {
			map.invalidateSize();
		}

		function wirePopupImages( m ) {
			// Deterministic popup geometry: width is fixed (min=max) and the
			// media box reserves its height via aspect-ratio, so Leaflet
			// measures the final size at open. The image-load update covers
			// the remaining case without moving the map.
			m.on( 'popupopen', function ( e ) {
				var el = e.popup.getElement();
				if ( ! el ) {
					return;
				}
				hydratePopupFavorites();
				Array.prototype.forEach.call(
					el.querySelectorAll( 'img.hvnly-block-map__popup-img' ),
					function ( image ) {
						if ( ! image.complete && ! image._hvnlyWired ) {
							image._hvnlyWired = true;
							image.addEventListener( 'load', function () {
								e.popup.update();
							} );
						}
					}
				);
			} );
		}

		function wireHover( m ) {
			var closeTimer = null;
			m.on( 'mouseover', function () {
				win.clearTimeout( closeTimer );
				m.openPopup();
			} );
			m.on( 'mouseout', function () {
				closeTimer = win.setTimeout( function () {
					m.closePopup();
				}, 300 );
			} );
			m.on( 'popupopen', function ( e ) {
				var el = e.popup.getElement();
				if ( ! el || el._hvnlyHoverWired ) {
					return;
				}
				el._hvnlyHoverWired = true;
				el.addEventListener( 'mouseenter', function () {
					win.clearTimeout( closeTimer );
				} );
				el.addEventListener( 'mouseleave', function () {
					closeTimer = win.setTimeout( function () {
						m.closePopup();
					}, 300 );
				} );
			} );
		}

		function loadMarkers() {
			request(
				cfg,
				function ( markers ) {
					if ( loading ) {
						loading.hidden = true;
					}
					group.clearLayers();
					var bounds = [];
					var index = 0;
					firstMarker = null;

					markers.forEach( function ( p ) {
						if ( ! p.lat || ! p.lng ) {
							return;
						}
						var latlng = [ parseFloat( p.lat ), parseFloat( p.lng ) ];
						bounds.push( latlng );
						var m = L.marker( latlng, {
							icon: makeIcon( index ),
							riseOnHover: true,
						} );
						index++;

						m.on( 'mouseover', function () {
							var el = m.getElement();
							if ( el ) {
								el.classList.add( 'is-hover' );
							}
						} );
						m.on( 'mouseout', function () {
							var el = m.getElement();
							if ( el && el !== ( activeMarker && activeMarker.getElement() ) ) {
								el.classList.remove( 'is-hover' );
							}
						} );
						m.on( 'click', function () {
							if ( activeMarker && activeMarker !== m ) {
								var prev = activeMarker.getElement();
								if ( prev ) {
									prev.classList.remove( 'is-active', 'is-hover' );
								}
							}
							activeMarker = m;
							var el = m.getElement();
							if ( el ) {
								el.classList.add( 'is-active' );
							}
						} );
						m.on( 'popupclose', function () {
							var el = m.getElement();
							if ( el ) {
								el.classList.remove( 'is-active', 'is-hover' );
							}
							if ( activeMarker === m ) {
								activeMarker = null;
							}
						} );

						m.bindPopup( popupHtml( p, cfg ), {
							// popup-host = the provider-neutral scope every
							// shared card rule is written against; the
							// leaflet-popup class keeps the Leaflet chrome
							// rules matching.
							className:
								'hvnly-block-map__leaflet-popup hvnly-block-map__popup-host' +
								( animations ? '' : ' hvnly-block-map__leaflet-popup--no-anim' ),
							closeButton: interactive && ! hoverMode,
							minWidth: popupWidth,
							maxWidth: popupWidth,
							autoPan: interactive,
							autoPanPadding: [ 28, 28 ],
							autoClose: interactive,
							closeOnClick: interactive,
						} );

						wirePopupImages( m );
						if ( hoverMode ) {
							wireHover( m );
						}

						group.addLayer( m );
						if ( ! firstMarker ) {
							firstMarker = m;
						}
					} );

					runArrival( root, win, index, animations );
					map.addLayer( group );

					if ( cfg.fitBounds !== false && bounds.length ) {
						map.invalidateSize();
						map.fitBounds( bounds, { padding: [ 48, 48 ], maxZoom: 15 } );
						if ( map.getSize().x === 0 ) {
							pendingFitBounds = bounds;
						}
					}

					// Editor preview: open one real popup so the canvas matches
					// the frontend. whenReady fires after the initial view is
					// settled — no arbitrary delay.
					if ( ! interactive && firstMarker ) {
						map.whenReady( function () {
							firstMarker.openPopup();
							var el = firstMarker.getElement();
							if ( el ) {
								el.classList.add( 'is-active' );
							}
							activeMarker = firstMarker;
						} );
					}
				},
				function () {
					renderError( root, loading, loadMarkers );
				}
			);
		}

		loadMarkers();

		if ( interactive ) {
			root.querySelectorAll( '[data-hvnly-block-map-action]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var action = btn.getAttribute( 'data-hvnly-block-map-action' );
					if ( action === 'zoom-in' ) {
						map.zoomIn();
					} else if ( action === 'zoom-out' ) {
						map.zoomOut();
					} else if ( action === 'fit' ) {
						if ( group.getLayers().length ) {
							var b = L.featureGroup( group.getLayers() ).getBounds();
							if ( b.isValid() ) {
								map.fitBounds( b, { padding: [ 48, 48 ], maxZoom: 15 } );
							}
						}
					} else if ( action === 'locate' && win.navigator.geolocation ) {
						win.navigator.geolocation.getCurrentPosition( function ( pos ) {
							map.setView(
								[ pos.coords.latitude, pos.coords.longitude ],
								14
							);
							L.circleMarker(
								[ pos.coords.latitude, pos.coords.longitude ],
								{
									radius: 8,
									color: brand,
									fillColor: brand,
									fillOpacity: 0.55,
									weight: 2,
								}
							).addTo( map );
						} );
					}
				} );
			} );
		} else {
			root.classList.add( 'is-editor-preview' );
		}

		root._hvnlyBlockMap = map;

		return true;
	}

	/* ------------------------------------------------------------------ */
	/* Google Maps — mirrors the Archive Map implementation                 */
	/* (google.maps.Marker + SVG pin + InfoWindow + shared card HTML).      */
	/* ------------------------------------------------------------------ */
	function buildGoogle( root, cfg, canvas, doc, win ) {
		var g = googleReady( win );
		if ( ! g ) {
			return false;
		}

		var interactive = cfg.interactive !== false;
		var animations = cfg.animations !== false;
		var reduced = prefersReducedMotion( win );
		var markerHex = cfg.markerColorHex || cfg.markerColor || '#6c60fe';
		var popupWidth = Math.min( 360, Math.max( 240, parseInt( cfg.popupWidth || 300, 10 ) ) );
		popupWidth = Math.min( popupWidth, Math.max( 220, ( win.innerWidth || 1024 ) - 56 ) );
		cfg.popupWidth = popupWidth;
		var hoverMode =
			cfg.popupTrigger === 'hover' &&
			interactive &&
			! ( win.matchMedia && win.matchMedia( '(hover: none)' ).matches );
		var center =
			cfg.center && cfg.center.lat
				? { lat: parseFloat( cfg.center.lat ), lng: parseFloat( cfg.center.lng ) }
				: { lat: 51.514939, lng: -0.091839 };

		if ( ! animations ) {
			root.classList.add( 'hvnly-block-map--no-anim' );
		}

		var map = new g.maps.Map( canvas, {
			center: center,
			zoom: cfg.zoom || 12,
			mapTypeId: cfg.googleMapType || 'roadmap',
			disableDefaultUI: true,
			scrollwheel: interactive && !! cfg.scrollWheel,
			gestureHandling: interactive ? 'greedy' : 'none',
			clickableIcons: false,
		} );

		var loading = root.querySelector( '.hvnly-block-map__loading' );
		var allMarkers = [];
		// Same SVG teardrop path the Archive Map uses for its Google markers.
		var pinPath =
			'M21 0C9.4 0 0 9.4 0 21c0 11.6 9.4 21 21 21s21-9.4 21-21c0-11.6-9.4-21-21-21zm0 10c6.1 0 11 4.9 11 11s-4.9 11-11 11-11-4.9-11-11 4.9-11 11-11z';
		var sizeScale = { sm: 1, md: 1.2, lg: 1.4 }[ cfg.markerSize ] || 1.2;

		var infoWindow = new g.maps.InfoWindow( {
			maxWidth: popupWidth,
			pixelOffset: new g.maps.Size( 0, -6 ),
			disableAutoPan: ! interactive,
		} );

		infoWindow.addListener( 'domready', function () {
			hydratePopupFavorites();
		} );

		// The SAME shared card, wrapped in the provider-neutral scope class
		// the card CSS is written against (Leaflet gets it via the popup
		// className option). One renderer, one design, two containers.
		function hostContent( p ) {
			return (
				'<div class="hvnly-block-map__popup-host">' +
				popupHtml( p, cfg ) +
				'</div>'
			);
		}

		// Resize parity with the Leaflet path — keep Google's projection in
		// sync with the real canvas size, re-fit if built at zero width.
		var pendingFitBounds = null;
		if ( win.ResizeObserver ) {
			var ro = new win.ResizeObserver( function () {
				g.maps.event.trigger( map, 'resize' );
				if ( pendingFitBounds && canvas.clientWidth > 0 ) {
					map.fitBounds( pendingFitBounds, 48 );
					pendingFitBounds = null;
				}
			} );
			ro.observe( canvas );
			root._hvnlyBlockMapRO = ro;
		}

		function loadMarkers() {
			request(
				cfg,
				function ( markers ) {
					if ( loading ) {
						loading.hidden = true;
					}
					var bounds = new g.maps.LatLngBounds();
					var count = 0;
					var firstMarker = null;
					var firstProperty = null;

					markers.forEach( function ( p ) {
						if ( ! p.lat || ! p.lng ) {
							return;
						}
						var pos = { lat: parseFloat( p.lat ), lng: parseFloat( p.lng ) };
						bounds.extend( pos );

						var marker = new g.maps.Marker( {
							position: pos,
							map: map,
							title: p.title || '',
							icon: {
								path: pinPath,
								fillColor: markerHex,
								fillOpacity: 1,
								strokeColor: '#ffffff',
								strokeWeight: 3,
								scale: sizeScale * 0.75,
								anchor: new g.maps.Point( 21, 42 ),
							},
							optimized: true,
							// Native Google entrance (Archive Map parity);
							// suppressed for reduced motion / animations off.
							animation:
								animations && ! reduced ? g.maps.Animation.DROP : null,
						} );

						var open = function () {
							infoWindow.close();
							infoWindow.setContent( hostContent( p ) );
							infoWindow.open( map, marker );
						};

						marker.addListener( 'click', open );
						if ( hoverMode ) {
							var closeTimer = null;
							marker.addListener( 'mouseover', function () {
								win.clearTimeout( closeTimer );
								open();
							} );
							marker.addListener( 'mouseout', function () {
								closeTimer = win.setTimeout( function () {
									infoWindow.close();
								}, 300 );
							} );
						}

						allMarkers.push( marker );
						count++;
						if ( ! firstMarker ) {
							firstMarker = marker;
							firstProperty = p;
						}
					} );

					if ( cfg.fitBounds !== false && count > 0 ) {
						map.fitBounds( bounds, 48 );
						if ( canvas.clientWidth === 0 ) {
							pendingFitBounds = bounds;
						}
					}

					// Editor preview parity: open one real popup.
					if ( ! interactive && firstMarker && firstProperty ) {
						g.maps.event.addListenerOnce( map, 'idle', function () {
							infoWindow.setContent( hostContent( firstProperty ) );
							infoWindow.open( map, firstMarker );
						} );
					}
				},
				function () {
					renderError( root, loading, loadMarkers );
				}
			);
		}

		loadMarkers();

		if ( interactive ) {
			root.querySelectorAll( '[data-hvnly-block-map-action]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var action = btn.getAttribute( 'data-hvnly-block-map-action' );
					if ( action === 'zoom-in' ) {
						map.setZoom( map.getZoom() + 1 );
					} else if ( action === 'zoom-out' ) {
						map.setZoom( map.getZoom() - 1 );
					} else if ( action === 'fit' ) {
						if ( allMarkers.length ) {
							var b = new g.maps.LatLngBounds();
							allMarkers.forEach( function ( m ) {
								b.extend( m.getPosition() );
							} );
							map.fitBounds( b, 48 );
						}
					} else if ( action === 'locate' && win.navigator.geolocation ) {
						win.navigator.geolocation.getCurrentPosition( function ( pos ) {
							map.setCenter( {
								lat: pos.coords.latitude,
								lng: pos.coords.longitude,
							} );
							map.setZoom( 14 );
						} );
					}
				} );
			} );
		} else {
			root.classList.add( 'is-editor-preview' );
		}

		root._hvnlyBlockMap = map;

		return true;
	}

	/**
	 * @param {HTMLElement} root Block root ([data-hvnly-block-map]).
	 * @return {boolean} true when the map was built successfully.
	 */
	function build( root ) {
		var doc = root.ownerDocument || document;
		var win = doc.defaultView || window;

		var cfg;
		try {
			cfg = JSON.parse( root.getAttribute( 'data-config' ) || '{}' );
		} catch ( e ) {
			cfg = {};
		}

		// Scoped lookup: works in the frontend AND inside the editor iframe,
		// where document.getElementById would search the wrong document.
		var canvas = root.querySelector( '.hvnly-block-map__canvas' );
		if ( ! canvas ) {
			return false;
		}

		teardown( root );

		if ( cfg.provider === 'google' ) {
			if ( ! googleReady( win ) ) {
				// Not ready = either the API is still loading OR only its
				// async bootstrap has evaluated (namespace present, Map
				// constructor absent). Either way, the ONLY reliable signal
				// is the Archive Map's ready bridge (initHvnlyMap →
				// hvnlyGoogleMapsLoaded), which Google fires when the full
				// API is constructible. Wait for it — the root stays
				// unmarked so init() re-enters cleanly, and the flag resets
				// so a still-not-ready re-entry re-arms the listener.
				if ( ! root._hvnlyAwaitingGoogle ) {
					root._hvnlyAwaitingGoogle = true;
					win.addEventListener(
						'hvnlyGoogleMapsLoaded',
						function () {
							root._hvnlyAwaitingGoogle = false;
							init( doc );
						},
						{ once: true }
					);
				}
				return false;
			}
			return buildGoogle( root, cfg, canvas, doc, win );
		}

		// 'leaflet' and 'openstreetmap' both render on the bundled Leaflet.
		return buildLeaflet( root, cfg, canvas, doc, win );
	}

	function init( context ) {
		var scope = context && context.querySelectorAll ? context : document;
		var roots = scope.querySelectorAll( '[data-hvnly-block-map]' );
		Array.prototype.forEach.call( roots, function ( root ) {
			if ( root.getAttribute( 'data-hvnly-block-map-ready' ) === '1' ) {
				return;
			}
			// Mark ready ONLY on success: a failed attempt (provider lib not
			// yet loaded, canvas not present) must stay retryable.
			if ( build( root ) ) {
				root.setAttribute( 'data-hvnly-block-map-ready', '1' );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	window.hvnlyInitBlockMaps = init;
} )();
