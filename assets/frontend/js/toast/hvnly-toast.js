/**
 * Havenlytics — Toast Notification Manager.
 *
 * The single toast engine for the whole plugin. Framework-free on purpose:
 * property archive/single pages are server-rendered vanilla JS, while the
 * Agent Workspace is a React SPA, and both need the same notifications. Rather
 * than ship two implementations, this owns the queue, timers, DOM, animation
 * and accessibility, and the Workspace notification service delegates to it.
 *
 * Public API (window.HvnlyToast):
 *   show( options )                  → id
 *   success|error|warning|info|loading( title, message, opts ) → id
 *   dismiss( id ) / dismissAll()
 *   update( id, patch )
 *   subscribe( fn ) → unsubscribe
 *
 * Options:
 *   type       'success'|'error'|'warning'|'info'|'loading'|'favorite'|'unfavorite'
 *   title      string (required)
 *   message    string
 *   icon       string — overrides the type icon
 *   duration   ms; 0 = sticky. Default 4500 (7000 for errors).
 *   dedupeKey  string — replaces an existing toast instead of stacking.
 *   actions    [{ label, onClick, variant: 'primary'|'secondary', dismiss }]
 *   progress   0–100 (loading)
 *   thumbnail  image URL — renders a media slot in place of the icon chip
 *              (used by the Favorite "property toast")
 *
 * @package Havenlytics
 * @since   3.4.0
 */
( function () {
	'use strict';

	if ( window.HvnlyToast ) {
		return;
	}

	var CONTAINER_ID = 'hvnly-toast-region';
	var MAX_VISIBLE = 4;
	var DEFAULT_DURATION = 4500;
	var ERROR_DURATION = 7000;
	var EXIT_MS = 220;

	var ICONS = {
		success: '✓',
		error: '✕',
		warning: '⚠',
		info: 'ℹ',
		loading: '…',
		favorite: '❤️',
		unfavorite: '💔',
	};

	/** @type {Object.<string, object>} Live toasts by id. */
	var toasts = Object.create( null );

	/** @type {Object.<string, number>} Auto-dismiss timers by id. */
	var timers = Object.create( null );

	/** @type {Array<Function>} */
	var listeners = [];

	var seq = 0;
	var container = null;

	// ---------------------------------------------------------------
	// Container
	// ---------------------------------------------------------------

	/**
	 * Create (once) and return the live region that hosts every toast.
	 *
	 * aria-live="polite" is set on the container rather than each toast so
	 * screen readers announce insertions without re-announcing the region.
	 *
	 * @return {HTMLElement} Toast container.
	 */
	function getContainer() {
		if ( container && document.body.contains( container ) ) {
			return container;
		}

		container = document.getElementById( CONTAINER_ID );

		if ( ! container ) {
			container = document.createElement( 'div' );
			container.id = CONTAINER_ID;
			container.className = 'hvnly-toast-region';
			container.setAttribute( 'aria-live', 'polite' );
			container.setAttribute( 'aria-relevant', 'additions' );
			document.body.appendChild( container );
		}

		return container;
	}

	// ---------------------------------------------------------------
	// Timers
	// ---------------------------------------------------------------

	/**
	 * @param {string} id Toast id.
	 * @return {void}
	 */
	function clearTimer( id ) {
		if ( timers[ id ] ) {
			window.clearTimeout( timers[ id ] );
			delete timers[ id ];
		}
	}

	/**
	 * (Re)start the auto-dismiss countdown.
	 *
	 * Always clears first — this is what makes hover/focus pause safe to call
	 * repeatedly, and what stops timers accumulating on rapid re-renders.
	 *
	 * @param {string} id        Toast id.
	 * @param {number} remaining Milliseconds left.
	 * @return {void}
	 */
	function startTimer( id, remaining ) {
		clearTimer( id );

		var toast = toasts[ id ];
		if ( ! toast || ! remaining || remaining <= 0 ) {
			return;
		}

		toast.expiresAt = Date.now() + remaining;
		timers[ id ] = window.setTimeout( function () {
			dismiss( id );
		}, remaining );
	}

	/**
	 * @param {string} id Toast id.
	 * @return {void}
	 */
	function pause( id ) {
		var toast = toasts[ id ];
		if ( ! toast || ! toast.duration ) {
			return;
		}

		// Bank the remaining time so resume continues rather than restarts.
		toast.remaining = Math.max( 0, toast.expiresAt - Date.now() );
		clearTimer( id );

		if ( toast.el ) {
			toast.el.setAttribute( 'data-hvnly-paused', 'true' );
		}
	}

	/**
	 * @param {string} id Toast id.
	 * @return {void}
	 */
	function resume( id ) {
		var toast = toasts[ id ];
		if ( ! toast || ! toast.duration ) {
			return;
		}

		if ( toast.el ) {
			toast.el.removeAttribute( 'data-hvnly-paused' );
		}

		startTimer( id, toast.remaining || toast.duration );
	}

	// ---------------------------------------------------------------
	// Rendering
	// ---------------------------------------------------------------

	/**
	 * @param {object} toast Toast record.
	 * @return {HTMLElement} Toast element.
	 */
	function render( toast ) {
		var el = document.createElement( 'div' );
		el.className = 'hvnly-toast hvnly-toast--' + toast.type;
		el.setAttribute( 'data-hvnly-toast-id', toast.id );
		// Errors interrupt; everything else waits for a pause in speech.
		el.setAttribute( 'role', toast.type === 'error' ? 'alert' : 'status' );

		// Optional media slot. When a thumbnail is supplied it takes the place
		// of the icon chip — the image already identifies the subject, so an
		// emoji beside it is noise. Everything else about the toast (queue,
		// timers, actions, a11y) is unchanged.
		if ( toast.thumbnail ) {
			el.className += ' hvnly-toast--media';

			var media = document.createElement( 'span' );
			media.className = 'hvnly-toast__media';

			var img = document.createElement( 'img' );
			img.src = toast.thumbnail;
			// Decorative: the title and message already carry the meaning.
			img.alt = '';
			img.setAttribute( 'loading', 'lazy' );
			img.setAttribute( 'aria-hidden', 'true' );
			// A broken or removed featured image must not leave an empty box.
			img.addEventListener( 'error', function () {
				if ( media.parentNode ) {
					media.parentNode.removeChild( media );
				}
			} );

			media.appendChild( img );
			el.appendChild( media );
		} else {
			var icon = document.createElement( 'span' );
			icon.className = 'hvnly-toast__icon';
			icon.setAttribute( 'aria-hidden', 'true' );
			icon.textContent = toast.icon || ICONS[ toast.type ] || ICONS.info;
			el.appendChild( icon );
		}

		var body = document.createElement( 'div' );
		body.className = 'hvnly-toast__body';

		var title = document.createElement( 'p' );
		title.className = 'hvnly-toast__title';
		title.textContent = toast.title;
		body.appendChild( title );

		if ( toast.message ) {
			var message = document.createElement( 'p' );
			message.className = 'hvnly-toast__message';
			message.textContent = toast.message;
			body.appendChild( message );
		}

		if ( toast.type === 'loading' && typeof toast.progress === 'number' ) {
			var track = document.createElement( 'div' );
			track.className = 'hvnly-toast__progress';
			var bar = document.createElement( 'span' );
			bar.style.width = Math.max( 0, Math.min( 100, toast.progress ) ) + '%';
			track.appendChild( bar );
			body.appendChild( track );
		}

		if ( toast.actions && toast.actions.length ) {
			var actions = document.createElement( 'div' );
			actions.className = 'hvnly-toast__actions';

			toast.actions.forEach( function ( action, index ) {
				var button = document.createElement( 'button' );
				button.type = 'button';
				var variant = action.variant || ( index === 0 ? 'primary' : 'secondary' );
				// Core UI variants only — never invent toast-only button paint.
				var btnVariants = {
					primary: 1,
					secondary: 1,
					ghost: 1,
					outline: 1,
					success: 1,
					danger: 1,
					warning: 1,
					alert: 1,
				};
				var btnVariant = btnVariants[ variant ] ? variant : 'primary';
				// Additive Core UI primitives — paint owned by hvnly-frontend-components.css.
				// Keep legacy hvnly-toast__action* classes for layout / BC.
				button.className =
					'hvnly-btn hvnly-btn--sm hvnly-btn--' +
					btnVariant +
					' hvnly-toast__action hvnly-toast__action--' +
					variant;
				button.textContent = action.label;

				button.addEventListener( 'click', function ( event ) {
					if ( typeof action.onClick === 'function' ) {
						action.onClick( event, toast.id );
					}
					// Actions close the toast unless they opt out (e.g. a link
					// that opens a new tab and should leave context behind).
					if ( action.dismiss !== false ) {
						dismiss( toast.id );
					}
				} );

				actions.appendChild( button );
			} );

			body.appendChild( actions );
		}

		el.appendChild( body );

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'hvnly-ui-control hvnly-toast__dismiss';
		close.setAttribute( 'aria-label', toast.dismissLabel || ((window.hvnly_map_params && window.hvnly_map_params.i18n && window.hvnly_map_params.i18n.dismissNotification) || (window.hvnly_property_data && window.hvnly_property_data.i18n && window.hvnly_property_data.i18n.dismissNotification) || '') );
		close.innerHTML = '&times;';
		close.addEventListener( 'click', function () {
			dismiss( toast.id );
		} );
		el.appendChild( close );

		// Pause on hover AND on keyboard focus — a keyboard user tabbing to the
		// action must not have the toast disappear mid-reach.
		el.addEventListener( 'mouseenter', function () {
			pause( toast.id );
		} );
		el.addEventListener( 'mouseleave', function () {
			resume( toast.id );
		} );
		el.addEventListener( 'focusin', function () {
			pause( toast.id );
		} );
		el.addEventListener( 'focusout', function ( event ) {
			if ( ! el.contains( event.relatedTarget ) ) {
				resume( toast.id );
			}
		} );

		el.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' ) {
				event.stopPropagation();
				dismiss( toast.id );
			}
		} );

		return el;
	}

	/**
	 * @return {void}
	 */
	function emit() {
		var snapshot = Object.keys( toasts ).map( function ( id ) {
			return toasts[ id ];
		} );

		listeners.forEach( function ( listener ) {
			try {
				listener( snapshot );
			} catch ( e ) {
				// A broken subscriber must never break notifications.
			}
		} );
	}

	// ---------------------------------------------------------------
	// Public API
	// ---------------------------------------------------------------

	/**
	 * @param {object} options Toast options.
	 * @return {string} Toast id.
	 */
	function show( options ) {
		var opts = options || {};
		var type = opts.type || 'info';

		// Rapid repeated clicks on the same control must replace, not stack.
		if ( opts.dedupeKey ) {
			Object.keys( toasts ).forEach( function ( existing ) {
				if ( toasts[ existing ].dedupeKey === opts.dedupeKey ) {
					remove( existing, true );
				}
			} );
		}

		var id = opts.id || 'hvnly-toast-' + ++seq;

		var duration =
			typeof opts.duration === 'number'
				? opts.duration
				: type === 'error'
				? ERROR_DURATION
				: type === 'loading'
				? 0
				: DEFAULT_DURATION;

		var toast = {
			id: id,
			type: type,
			title: String( opts.title || '' ),
			message: opts.message ? String( opts.message ) : '',
			icon: opts.icon || '',
			thumbnail: opts.thumbnail ? String( opts.thumbnail ) : '',
			duration: duration,
			remaining: duration,
			expiresAt: 0,
			progress: typeof opts.progress === 'number' ? opts.progress : undefined,
			actions: Array.isArray( opts.actions ) ? opts.actions : [],
			dedupeKey: opts.dedupeKey || '',
			dismissLabel: opts.dismissLabel || '',
			createdAt: Date.now(),
		};

		toast.el = render( toast );
		toasts[ id ] = toast;

		var region = getContainer();
		// Newest nearest the screen edge.
		region.insertBefore( toast.el, region.firstChild );

		// Force a frame so the enter transition actually runs.
		window.requestAnimationFrame( function () {
			if ( toast.el ) {
				toast.el.setAttribute( 'data-hvnly-visible', 'true' );
			}
		} );

		// Evict the oldest beyond the cap so the stack can't grow unbounded.
		var ids = Object.keys( toasts );
		if ( ids.length > MAX_VISIBLE ) {
			ids.sort( function ( a, b ) {
				return toasts[ a ].createdAt - toasts[ b ].createdAt;
			} )
				.slice( 0, ids.length - MAX_VISIBLE )
				.forEach( function ( old ) {
					dismiss( old );
				} );
		}

		startTimer( id, duration );
		emit();

		return id;
	}

	/**
	 * Remove a toast from the DOM and all bookkeeping.
	 *
	 * @param {string}  id        Toast id.
	 * @param {boolean} immediate Skip the exit animation.
	 * @return {void}
	 */
	function remove( id, immediate ) {
		var toast = toasts[ id ];
		if ( ! toast ) {
			return;
		}

		clearTimer( id );
		delete toasts[ id ];

		var el = toast.el;
		toast.el = null;

		if ( ! el || ! el.parentNode ) {
			return;
		}

		if ( immediate ) {
			el.parentNode.removeChild( el );
			return;
		}

		el.setAttribute( 'data-hvnly-leaving', 'true' );
		el.removeAttribute( 'data-hvnly-visible' );

		// Timeout rather than transitionend: if the element is display:none,
		// in a background tab, or motion is reduced, transitionend never fires
		// and the node would leak.
		window.setTimeout( function () {
			if ( el.parentNode ) {
				el.parentNode.removeChild( el );
			}
		}, EXIT_MS );
	}

	/**
	 * @param {string} id Toast id.
	 * @return {void}
	 */
	function dismiss( id ) {
		if ( ! toasts[ id ] ) {
			return;
		}
		remove( id, false );
		emit();
	}

	/**
	 * @return {void}
	 */
	function dismissAll() {
		Object.keys( toasts ).forEach( function ( id ) {
			remove( id, false );
		} );
		emit();
	}

	/**
	 * Patch a live toast (progress, title, message) in place.
	 *
	 * @param {string} id    Toast id.
	 * @param {object} patch Fields to change.
	 * @return {void}
	 */
	function update( id, patch ) {
		var toast = toasts[ id ];
		if ( ! toast || ! patch ) {
			return;
		}

		Object.keys( patch ).forEach( function ( key ) {
			toast[ key ] = patch[ key ];
		} );

		var el = toast.el;
		if ( ! el ) {
			return;
		}

		if ( typeof patch.title === 'string' ) {
			var title = el.querySelector( '.hvnly-toast__title' );
			if ( title ) {
				title.textContent = patch.title;
			}
		}

		if ( typeof patch.message === 'string' ) {
			var message = el.querySelector( '.hvnly-toast__message' );
			if ( message ) {
				message.textContent = patch.message;
			}
		}

		if ( typeof patch.progress === 'number' ) {
			var bar = el.querySelector( '.hvnly-toast__progress > span' );
			if ( bar ) {
				bar.style.width =
					Math.max( 0, Math.min( 100, patch.progress ) ) + '%';
			}
		}

		emit();
	}

	/**
	 * @param {Function} listener Called with the current toast list.
	 * @return {Function} Unsubscribe.
	 */
	function subscribe( listener ) {
		if ( typeof listener !== 'function' ) {
			return function () {};
		}

		listeners.push( listener );
		listener(
			Object.keys( toasts ).map( function ( id ) {
				return toasts[ id ];
			} )
		);

		return function () {
			var index = listeners.indexOf( listener );
			if ( index !== -1 ) {
				listeners.splice( index, 1 );
			}
		};
	}

	/**
	 * @param {string} type Toast type.
	 * @return {Function} Shorthand creator.
	 */
	function shorthand( type ) {
		return function ( title, message, opts ) {
			var options = opts || {};
			options.type = type;
			options.title = title;
			options.message = message || '';
			return show( options );
		};
	}

	window.HvnlyToast = {
		show: show,
		dismiss: dismiss,
		dismissAll: dismissAll,
		update: update,
		subscribe: subscribe,
		success: shorthand( 'success' ),
		error: shorthand( 'error' ),
		warning: shorthand( 'warning' ),
		info: shorthand( 'info' ),
		loading: shorthand( 'loading' ),
		favorite: shorthand( 'favorite' ),
		unfavorite: shorthand( 'unfavorite' ),
	};
} )();
