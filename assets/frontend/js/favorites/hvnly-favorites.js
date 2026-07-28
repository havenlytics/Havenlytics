/**
 * Havenlytics — Favorites (saved properties).
 *
 * One delegated listener owns every heart button on the page, including cards
 * injected later by AJAX search. State is persisted two ways:
 *
 *   signed in : REST (hvnly/v1/favorites), authoritative
 *   guest     : localStorage, merged into the account on first login
 *
 * No jQuery dependency — the module binds natively and only *listens* for the
 * legacy jQuery event when jQuery happens to be present.
 *
 * @package
 * @since   3.4.0
 */
( function () {
	'use strict';

	const cfg = window.hvnlyFavoritesData || {};

	const SELECTOR =
		'[data-hvnly-favorite], .hvnly-property--grid-list--favorite';
	const STORAGE_KEY = cfg.storageKey || 'hvnly_guest_favorites';
	const MAX_GUEST = parseInt( cfg.maxGuest, 10 ) || 100;
	const i18n = cfg.i18n || {};

	/**
	 * Favorite toasts carry an action, so they sit at the long end of the
	 * 6–8s band — enough time to read the property name and reach the button
	 * without feeling sticky. Hovering pauses it regardless.
	 */
	const FAVORITE_TOAST_MS = 7000;

	/**
	 * Saved ids, most recently saved first.
	 *
	 * Order matters: the guest list is capped at MAX_GUEST and we must drop
	 * the *oldest* saves. An object cannot carry this — JS orders integer-like
	 * keys numerically, so Object.keys() would return id order, not save
	 * order, and the cap would discard the wrong entries.
	 *
	 * @type {number[]}
	 */
	let order = [];

	/**
	 * Membership lookup mirroring `order`.
	 *
	 * @type {Object.<string, boolean>}
	 */
	let saved = Object.create( null );

	/** @type {HTMLElement|null} */
	let liveRegion = null;

	/** @type {boolean} */
	const isLoggedIn = !! cfg.isLoggedIn;

	// -------------------------------------------------------------------
	// Storage
	// -------------------------------------------------------------------

	/**
	 * Whether localStorage is usable.
	 *
	 * Safari private mode and hardened browser settings throw on access
	 * rather than returning null, so this must be a try/catch, not a
	 * truthiness check.
	 *
	 * @return {boolean} True when localStorage can be read and written.
	 */
	function hasStorage() {
		try {
			const probe = '__hvnly_probe__';
			window.localStorage.setItem( probe, '1' );
			window.localStorage.removeItem( probe );
			return true;
		} catch ( e ) {
			return false;
		}
	}

	const storageAvailable = hasStorage();

	/**
	 * Read the guest favorite ids from localStorage.
	 *
	 * @return {number[]} Saved property ids.
	 */
	function readGuest() {
		if ( ! storageAvailable ) {
			return [];
		}

		try {
			const raw = window.localStorage.getItem( STORAGE_KEY );
			if ( ! raw ) {
				return [];
			}

			const parsed = JSON.parse( raw );
			if ( ! Array.isArray( parsed ) ) {
				return [];
			}

			return parsed
				.map( function ( id ) {
					return parseInt( id, 10 );
				} )
				.filter( function ( id ) {
					return id > 0;
				} );
		} catch ( e ) {
			// Corrupt payload — drop it rather than breaking every heart.
			return [];
		}
	}

	/**
	 * Persist the guest favorite ids.
	 *
	 * @param {number[]} ids Property ids.
	 * @return {void}
	 */
	function writeGuest( ids ) {
		if ( ! storageAvailable ) {
			return;
		}

		try {
			window.localStorage.setItem(
				STORAGE_KEY,
				JSON.stringify( ids.slice( 0, MAX_GUEST ) )
			);
		} catch ( e ) {
			// Quota exceeded — favorites degrade to session-only.
		}
	}

	/**
	 * Remove the guest list (after a successful merge).
	 *
	 * @return {void}
	 */
	function clearGuest() {
		if ( ! storageAvailable ) {
			return;
		}

		try {
			window.localStorage.removeItem( STORAGE_KEY );
		} catch ( e ) {
			// Non-fatal.
		}
	}

	// -------------------------------------------------------------------
	// State helpers
	// -------------------------------------------------------------------

	/**
	 * Replace the whole state. Input is expected newest-first.
	 *
	 * @param {number[]} ids Property ids.
	 * @return {void}
	 */
	function setState( ids ) {
		order = [];
		saved = Object.create( null );

		( ids || [] ).forEach( function ( raw ) {
			const id = parseInt( raw, 10 );
			if ( id > 0 && ! saved[ String( id ) ] ) {
				saved[ String( id ) ] = true;
				order.push( id );
			}
		} );
	}

	/**
	 * Mark a property saved (newest first).
	 *
	 * @param {number} id Property id.
	 * @return {void}
	 */
	function addState( id ) {
		if ( saved[ String( id ) ] ) {
			return;
		}
		saved[ String( id ) ] = true;
		order.unshift( id );
	}

	/**
	 * Mark a property unsaved.
	 *
	 * @param {number} id Property id.
	 * @return {void}
	 */
	function removeState( id ) {
		if ( ! saved[ String( id ) ] ) {
			return;
		}
		delete saved[ String( id ) ];

		const index = order.indexOf( id );
		if ( index !== -1 ) {
			order.splice( index, 1 );
		}
	}

	/**
	 * @return {number[]} Currently saved ids, newest first.
	 */
	function currentIds() {
		return order.slice();
	}

	/**
	 * @param {number|string} id Property id.
	 * @return {boolean} Whether the property is saved.
	 */
	function isSaved( id ) {
		return saved[ String( id ) ] === true;
	}

	// -------------------------------------------------------------------
	// REST
	// -------------------------------------------------------------------

	/**
	 * Call the favorites REST API.
	 *
	 * @param {string}  method HTTP method.
	 * @param {string}  path   Path appended to the endpoint root.
	 * @param {Object=} body   JSON body.
	 * @return {Promise<Object>} Resolved payload.
	 */
	function request( method, path, body ) {
		if ( ! cfg.restUrl ) {
			return Promise.reject( new Error( 'favorites_rest_unavailable' ) );
		}

		const options = {
			method,
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'X-WP-Nonce': cfg.nonce || '',
			},
		};

		if ( body ) {
			options.headers[ 'Content-Type' ] = 'application/json';
			options.body = JSON.stringify( body );
		}

		return window
			.fetch( cfg.restUrl + ( path || '' ), options )
			.then( function ( response ) {
				return response
					.json()
					.catch( function () {
						return {};
					} )
					.then( function ( json ) {
						if ( ! response.ok ) {
							const err = new Error(
								json.message ||
									i18n.error ||
									'favorites_request_failed'
							);
							err.code = json.code || '';
							err.status = response.status;
							throw err;
						}
						return json;
					} );
			} );
	}

	// -------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------

	/**
	 * Apply saved/unsaved presentation to one button.
	 *
	 * @param {HTMLElement} button   Favorite button.
	 * @param {boolean}     favorite Whether the property is saved.
	 * @return {void}
	 */
	function paint( button, favorite ) {
		button.classList.toggle( 'is-favorited', favorite );
		button.setAttribute( 'aria-pressed', favorite ? 'true' : 'false' );

		const label = favorite ? i18n.remove : i18n.add;
		if ( label ) {
			button.setAttribute( 'aria-label', label );
		}

		const icon = button.querySelector( 'i' );
		if ( icon ) {
			icon.classList.toggle( 'fas', favorite );
			icon.classList.toggle( 'far', ! favorite );
			// The old handler set an inline colour; clear any leftover so CSS
			// owns the visual state.
			icon.style.color = '';
		}
	}

	/**
	 * Re-apply state to every favorite button currently in the DOM.
	 *
	 * Idempotent and cheap — safe to call after any AJAX render.
	 *
	 * @return {void}
	 */
	function hydrate() {
		const buttons = document.querySelectorAll( SELECTOR );

		for ( let i = 0; i < buttons.length; i++ ) {
			const button = buttons[ i ];
			const id = button.getAttribute( 'data-property-id' );
			if ( ! id ) {
				continue;
			}
			paint( button, isSaved( id ) );
		}
	}

	/**
	 * Announce a change to assistive technology.
	 *
	 * Only used as the fallback when the toast manager is unavailable — the
	 * toast region is itself an aria-live region, so announcing through both
	 * would read every change out twice.
	 *
	 * @param {string} message Message text.
	 * @return {void}
	 */
	function announce( message ) {
		if ( ! message ) {
			return;
		}

		if ( ! liveRegion ) {
			liveRegion = document.createElement( 'div' );
			liveRegion.className = 'hvnly-favorites__live';
			liveRegion.setAttribute( 'role', 'status' );
			liveRegion.setAttribute( 'aria-live', 'polite' );
			liveRegion.setAttribute( 'aria-atomic', 'true' );
			// Visually hidden without display:none, which screen readers skip.
			liveRegion.style.cssText =
				'position:absolute;width:1px;height:1px;margin:-1px;padding:0;' +
				'overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0;';
			document.body.appendChild( liveRegion );
		}

		liveRegion.textContent = message;
	}

	/**
	 * @return {object|null} Toast manager, when loaded.
	 */
	function toastManager() {
		return window.HvnlyToast || null;
	}

	/**
	 * Presentation data for a property, read from any button that renders it.
	 *
	 * Server-rendered as data attributes so the toast can name and picture the
	 * property with no extra request — and so it works for guests, who never
	 * touch the REST API. A theme that overrides the button template without
	 * the attributes degrades to a title-only toast rather than breaking.
	 *
	 * @param {number} id Property id.
	 * @return {{title: string, thumb: string}} Property display data.
	 */
	function propertyMeta( id ) {
		var buttons = buttonsFor( id );

		for ( var i = 0; i < buttons.length; i++ ) {
			var title = buttons[ i ].getAttribute( 'data-property-title' );
			var thumb = buttons[ i ].getAttribute( 'data-property-thumb' );

			if ( title || thumb ) {
				return { title: title || '', thumb: thumb || '' };
			}
		}

		return { title: '', thumb: '' };
	}

	/**
	 * The single call-to-action shown on the favorite toast.
	 *
	 * Deliberately one button: signed-in users go to Saved Properties, guests
	 * are offered sign-in. Guests are encouraged, never forced — the save has
	 * already succeeded locally by the time this renders.
	 *
	 * @return {Array<object>} Zero or one action.
	 */
	function primaryAction() {
		var t = cfg.toast || {};

		if ( isLoggedIn ) {
			return cfg.savedPropertiesUrl
				? [
						{
							label: t.viewFavorites || '',
							variant: 'primary',
							onClick: function () {
								window.location.href = cfg.savedPropertiesUrl;
							},
						},
				  ]
				: [];
		}

		return cfg.loginUrl
			? [
					{
						label: t.login || '',
						variant: 'primary',
						onClick: function () {
							window.location.href = cfg.loginUrl;
						},
					},
			  ]
			: [];
	}

	/**
	 * Feedback for a favorite that was just added.
	 *
	 * @param {number} id Property id.
	 * @return {void}
	 */
	function toastAdded( id ) {
		var toast = toastManager();
		var t = cfg.toast || {};

		if ( ! toast ) {
			announce( isLoggedIn ? i18n.saved : i18n.guestSaved );
			return;
		}

		var meta = propertyMeta( id );

		toast.show( {
			type: 'favorite',
			title: t.addedTitle || '',
			// The property name is the message line: one line, truncated.
			message: meta.title,
			thumbnail: meta.thumb,
			actions: primaryAction(),
			duration: FAVORITE_TOAST_MS,
			// One toast per property: hammering the heart replaces rather
			// than stacks.
			dedupeKey: 'hvnly-favorite-' + id,
			dismissLabel: t.dismiss || '',
		} );
	}

	/**
	 * Feedback for a favorite that was just removed, with Undo.
	 *
	 * @param {number} id Property id.
	 * @return {void}
	 */
	function toastRemoved( id ) {
		var toast = toastManager();
		var t = cfg.toast || {};

		if ( ! toast ) {
			announce( i18n.removed );
			return;
		}

		var meta = propertyMeta( id );

		toast.show( {
			type: 'unfavorite',
			title: t.removedTitle || '',
			message: meta.title,
			thumbnail: meta.thumb,
			actions: [
				{
					label: t.undo || '',
					variant: 'secondary',
					onClick: function () {
						// Re-add through the normal path so REST, local
						// storage and every button for this property all
						// stay in sync.
						applyToggle( id, true );
					},
				},
			],
			duration: FAVORITE_TOAST_MS,
			dedupeKey: 'hvnly-favorite-' + id,
			dismissLabel: t.dismiss || '',
		} );
	}

	/**
	 * Notify other scripts that favorites changed.
	 *
	 * @param {number}  id       Property id.
	 * @param {boolean} favorite Resulting state.
	 * @return {void}
	 */
	function emit( id, favorite ) {
		const detail = {
			propertyId: id,
			favorited: favorite,
			ids: currentIds(),
		};

		try {
			document.dispatchEvent(
				new CustomEvent( 'hvnly:favorites:changed', { detail } )
			);
		} catch ( e ) {
			// IE-style CustomEvent failure — non-fatal.
		}
	}

	// -------------------------------------------------------------------
	// Toggle
	// -------------------------------------------------------------------

	/**
	 * Toggle a property, optimistically, rolling back on failure.
	 *
	 * @param {HTMLElement} button Button that was activated.
	 * @return {void}
	 */
	function applyToggle( id, next, quiet ) {
		id = parseInt( id, 10 );

		if ( ! id || id <= 0 ) {
			return;
		}

		const wasSaved = isSaved( id );

		// Already in the requested state — nothing to persist, no toast.
		if ( wasSaved === next ) {
			return;
		}

		// Optimistic paint: every button for this property, since the same
		// listing can appear more than once (grid + map popup, carousels).
		if ( next ) {
			addState( id );
		} else {
			removeState( id );
		}
		syncButtonsFor( id, next );

		const notify = function () {
			if ( quiet ) {
				return;
			}
			if ( next ) {
				toastAdded( id );
			} else {
				toastRemoved( id );
			}
		};

		if ( ! isLoggedIn ) {
			// Enforce the cap in memory too, otherwise the in-page state and
			// what we persist would drift apart after MAX_GUEST saves.
			if ( order.length > MAX_GUEST ) {
				order.slice( MAX_GUEST ).forEach( function ( dropped ) {
					delete saved[ String( dropped ) ];
					syncButtonsFor( dropped, false );
				} );
				order = order.slice( 0, MAX_GUEST );
			}

			writeGuest( currentIds() );
			notify();
			emit( id, next );
			return;
		}

		setBusy( id, true );

		const call = next
			? request( 'POST', '', { property_id: id } )
			: request( 'DELETE', '/' + id );

		call.then( function () {
			notify();
			emit( id, next );
		} )
			.catch( function ( error ) {
				// Roll back to the server's truth.
				if ( wasSaved ) {
					addState( id );
				} else {
					removeState( id );
				}
				syncButtonsFor( id, wasSaved );

				const message =
					error && error.code === 'hvnly_favorites_limit_reached'
						? i18n.limitReached || error.message
						: i18n.error || '';

				const toast = toastManager();
				if ( toast ) {
					toast.error(
						( cfg.toast && cfg.toast.errorTitle ) || '',
						message,
						{ dedupeKey: 'hvnly-favorite-' + id }
					);
				} else {
					announce( message );
				}
			} )
			.then( function () {
				setBusy( id, false );
			} );
	}

	/**
	 * Mark every button for a property busy / idle.
	 *
	 * Keyed by property rather than by the clicked node, so an in-flight
	 * request also guards the same listing rendered elsewhere on the page.
	 *
	 * @param {number}  id   Property id.
	 * @param {boolean} busy Busy state.
	 * @return {void}
	 */
	function setBusy( id, busy ) {
		buttonsFor( id ).forEach( function ( button ) {
			if ( busy ) {
				button.setAttribute( 'data-hvnly-busy', '1' );
			} else {
				button.removeAttribute( 'data-hvnly-busy' );
			}
		} );
	}

	/**
	 * Toggle a property from a clicked button.
	 *
	 * @param {HTMLElement} button Button that was activated.
	 * @return {void}
	 */
	function toggle( button ) {
		const id = parseInt( button.getAttribute( 'data-property-id' ), 10 );

		if (
			! id ||
			id <= 0 ||
			button.getAttribute( 'data-hvnly-busy' ) === '1'
		) {
			return;
		}

		applyToggle( id, ! isSaved( id ) );
	}

	/**
	 * Every favorite button pointing at one property.
	 *
	 * The same listing can appear more than once on a page (grid, map popup,
	 * carousel), so state changes always apply to all of them.
	 *
	 * @param {number} id Property id.
	 * @return {HTMLElement[]} Matching buttons.
	 */
	function buttonsFor( id ) {
		const nodes = document.querySelectorAll(
			'[data-property-id="' + String( id ).replace( /"/g, '' ) + '"]'
		);

		const matched = [];
		for ( let i = 0; i < nodes.length; i++ ) {
			if ( nodes[ i ].matches( SELECTOR ) ) {
				matched.push( nodes[ i ] );
			}
		}

		return matched;
	}

	/**
	 * Paint every button pointing at one property.
	 *
	 * @param {number}  id       Property id.
	 * @param {boolean} favorite Resulting state.
	 * @return {void}
	 */
	function syncButtonsFor( id, favorite ) {
		buttonsFor( id ).forEach( function ( button ) {
			paint( button, favorite );
		} );
	}

	// -------------------------------------------------------------------
	// Guest → account merge
	// -------------------------------------------------------------------

	/**
	 * Merge any locally-held guest favorites into the signed-in account.
	 *
	 * Runs once per login. The local list is only cleared after the server
	 * confirms, so a failed merge is retried on the next page load rather
	 * than silently losing the visitor's saves.
	 *
	 * @return {void}
	 */
	function mergeGuestFavorites() {
		const pending = readGuest();
		if ( ! pending.length ) {
			return;
		}

		request( 'POST', '/merge', { property_ids: pending } )
			.then( function ( json ) {
				clearGuest();

				// Re-read the authoritative set so the UI reflects exactly
				// what was stored (invalid or over-limit ids were dropped).
				return request('GET').then( function ( idsJson ) {
					const ids =
						( idsJson && idsJson.data && idsJson.data.ids ) || [];
					setState( ids );
					hydrate();

					try {
						document.dispatchEvent(
							new CustomEvent( 'hvnly:favorites:merged', {
								detail: ( json && json.data ) || {},
							} )
						);
					} catch ( e ) {
						// Non-fatal.
					}
				} );
			} )
			.catch( function () {
				// Keep the local list for the next attempt.
			} );
	}

	// -------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------

	/**
	 * @param {Event} event Click event.
	 * @return {void}
	 */
	function onClick( event ) {
		const target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}

		const button = target.closest( SELECTOR );
		if ( ! button ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		toggle( button );
	}

	/**
	 * @return {void}
	 */
	function init() {
		if ( isLoggedIn ) {
			setState( cfg.ids || [] );
		} else {
			setState( readGuest() );
		}

		hydrate();

		// One delegated listener for the whole document — survives every
		// AJAX re-render without rebinding.
		document.addEventListener( 'click', onClick, false );

		// Legacy jQuery custom event fired by the AJAX search modules.
		// jQuery-triggered events do not reach native listeners, so this has
		// to go through jQuery when it is present.
		if ( window.jQuery ) {
			window
				.jQuery( document )
				.on( 'hvnly-properties-updated', function () {
					hydrate();
				} );
		}

		document.addEventListener( 'hvnly:favorites:refresh', hydrate, false );

		if ( isLoggedIn ) {
			mergeGuestFavorites();
		}
	}

	/**
	 * Public API for themes and third-party integrations.
	 */
	window.hvnlyFavorites = {
		hydrate,
		isSaved,
		getIds: currentIds,
		// Works whether or not a button for the property is on this page.
		toggle( propertyId ) {
			applyToggle( propertyId, ! isSaved( propertyId ) );
		},
		set( propertyId, favorite ) {
			applyToggle( propertyId, !! favorite );
		},
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init, { once: true } );
	} else {
		init();
	}
} )();
