/**
 * HVN: Authentication block controller.
 *
 * Frontend UI only. Posts to the existing Workspace SessionAuthController AJAX
 * actions (hvnly_ws_login / hvnly_ws_register / hvnly_ws_lostpassword /
 * hvnly_ws_logout) using the SAME transport contract as the Workspace SPA
 * (src/workspace/Auth/api.js): url-encoded body, credentials same-origin,
 * per-action nonce, booleans as '1', one retry on invalid_nonce.
 *
 * No authentication logic lives here — this only collects fields, validates on
 * the client for UX, calls the existing endpoints, and navigates.
 *
 * @package Havenlytics
 * @since   3.5.0
 */
( function () {
	'use strict';

	/**
	 * Localized shared config (ajaxUrl + fresh nonces + action names + i18n).
	 * Provided by BlockAssets via wp_localize_script.
	 *
	 * @return {Object}
	 */
	function cfg() {
		return ( typeof window !== 'undefined' && window.HvnlyAuthBlock ) || {};
	}

	function t( key, fallback ) {
		var i18n = cfg().i18n || {};
		return i18n[ key ] || fallback;
	}

	function actionFor( name ) {
		var actions = cfg().actions || {};
		var map = {
			login: actions.login || 'hvnly_ws_login',
			register: actions.register || 'hvnly_ws_register',
			forgot: actions.lostPassword || 'hvnly_ws_lostpassword',
			logout: actions.logout || 'hvnly_ws_logout',
			refresh: actions.refreshNonces || 'hvnly_ws_auth_nonces',
		};
		return map[ name ] || '';
	}

	function nonceForAction( action ) {
		var c = cfg();
		var actions = c.actions || {};
		var nonces = c.nonces || {};
		var byAction = {};
		byAction[ actions.login || 'hvnly_ws_login' ] = nonces.login;
		byAction[ actions.register || 'hvnly_ws_register' ] = nonces.register;
		byAction[ actions.lostPassword || 'hvnly_ws_lostpassword' ] = nonces.lostPassword;
		byAction[ actions.logout || 'hvnly_ws_logout' ] = nonces.logout;
		return byAction[ action ] || nonces.login || '';
	}

	function applyNonces( nonces ) {
		if ( ! nonces || typeof nonces !== 'object' ) {
			return;
		}
		if ( ! window.HvnlyAuthBlock ) {
			window.HvnlyAuthBlock = {};
		}
		var prev = window.HvnlyAuthBlock.nonces || {};
		window.HvnlyAuthBlock.nonces = Object.assign( {}, prev, nonces );
	}

	/**
	 * Refresh nonces from the public refresh endpoint.
	 *
	 * @return {Promise<void>}
	 */
	function refreshNonces() {
		var c = cfg();
		var url = c.ajaxUrl || '';
		var action = actionFor( 'refresh' );
		if ( ! url || ! action ) {
			return Promise.resolve();
		}
		var body = new URLSearchParams();
		body.set( 'action', action );
		return fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		} )
			.then( function ( r ) {
				return r.json().catch( function () {
					return null;
				} );
			} )
			.then( function ( json ) {
				if ( json && json.data && json.data.nonces ) {
					applyNonces( json.data.nonces );
				}
			} )
			.catch( function () {} );
	}

	/**
	 * POST an auth action (mirrors SPA postAuthAction, incl. invalid_nonce retry).
	 *
	 * @param {string} action
	 * @param {Object} fields
	 * @param {Object} [options]
	 * @return {Promise<{ok:boolean,message:string,data:Object,code:(string|null)}>}
	 */
	function postAuthAction( action, fields, options ) {
		fields = fields || {};
		options = options || {};
		var c = cfg();
		var url = c.ajaxUrl || '';
		var allowRetry = options.retry !== false;

		if ( ! url || ! action ) {
			return Promise.resolve( {
				ok: false,
				message: t( 'genericError', 'Something went wrong. Please try again.' ),
				data: {},
				code: 'missing_config',
			} );
		}

		var body = new URLSearchParams();
		body.set( 'action', action );
		body.set( 'nonce', nonceForAction( action ) );
		Object.keys( fields ).forEach( function ( key ) {
			var value = fields[ key ];
			if ( typeof value === 'boolean' ) {
				if ( value ) {
					body.set( key, '1' );
				}
				return;
			}
			if ( value === undefined || value === null ) {
				return;
			}
			body.set( key, String( value ) );
		} );

		return fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		} )
			.then( function ( response ) {
				return response.json().catch( function () {
					return null;
				} );
			} )
			.then( function ( json ) {
				var ok = !! ( json && json.success );
				var payload = json && json.data && typeof json.data === 'object' ? json.data : {};
				var message = plain( payload.message ) || ( ok ? '' : t( 'genericError', 'Something went wrong. Please try again.' ) );
				var code = payload.code ? String( payload.code ) : null;

				if ( payload.nonces ) {
					applyNonces( payload.nonces );
				}

				if ( ! ok && code === 'invalid_nonce' && allowRetry ) {
					return refreshNonces().then( function () {
						return postAuthAction( action, fields, { retry: false } );
					} );
				}

				return { ok: ok, message: message, data: payload, code: code };
			} )
			.catch( function () {
				return {
					ok: false,
					message: t( 'networkError', 'Network error. Please check your connection and try again.' ),
					data: {},
					code: 'network_error',
				};
			} );
	}

	/**
	 * Strip any HTML from server messages (defense in depth — server sends plain).
	 *
	 * @param {*} value
	 * @return {string}
	 */
	function plain( value ) {
		if ( typeof value !== 'string' || value === '' ) {
			return '';
		}
		return value.replace( /<[^>]*>/g, ' ' ).replace( /\s+/g, ' ' ).trim();
	}

	function cacheBust( url ) {
		try {
			var parsed = new URL( url, window.location.origin );
			parsed.searchParams.set( 'hvnly_auth', String( Date.now() ) );
			return parsed.pathname + parsed.search + parsed.hash;
		} catch ( e ) {
			var sep = url.indexOf( '?' ) >= 0 ? '&' : '?';
			return url + sep + 'hvnly_auth=' + Date.now();
		}
	}

	function navigate( url ) {
		window.location.assign( cacheBust( url ) );
	}

	function reload() {
		navigate( window.location.href.split( '#' )[ 0 ] );
	}

	/**
	 * Simple password strength score 0..4 (length + character classes).
	 *
	 * @param {string} pw
	 * @return {number}
	 */
	function strength( pw ) {
		if ( ! pw ) {
			return 0;
		}
		var score = 0;
		if ( pw.length >= 8 ) {
			score++;
		}
		if ( pw.length >= 12 ) {
			score++;
		}
		if ( /[a-z]/.test( pw ) && /[A-Z]/.test( pw ) ) {
			score++;
		}
		if ( /\d/.test( pw ) && /[^A-Za-z0-9]/.test( pw ) ) {
			score++;
		}
		return Math.min( 4, score );
	}

	var STRENGTH_LABELS = [ 'Weak', 'Weak', 'Fair', 'Good', 'Strong' ];

	function AuthBlock( root ) {
		this.root = root;
		this.mode = root.getAttribute( 'data-mode' ) || 'auto';
		this.afterLogin = root.getAttribute( 'data-after-login' ) || '';
		this.afterRegister = root.getAttribute( 'data-after-register' ) || '';
		this.registrationMode = root.getAttribute( 'data-registration-mode' ) || 'disabled';
		this.messageEl = root.querySelector( '[data-hvnly-auth-message]' );
		this.titleEl = root.querySelector( '[data-hvnly-auth-title]' );
		this.subtitleEl = root.querySelector( '[data-hvnly-auth-subtitle]' );
		this.forms = {
			login: root.querySelector( '[data-hvnly-auth-form="login"]' ),
			register: root.querySelector( '[data-hvnly-auth-form="register"]' ),
			forgot: root.querySelector( '[data-hvnly-auth-form="forgot"]' ),
		};
		this.busy = false;
		this.bind();
	}

	AuthBlock.prototype.copy = function ( view ) {
		var map = {
			login: {
				title: t( 'loginTitle', 'Sign in' ),
				subtitle: t( 'loginSubtitle', 'Access your account.' ),
			},
			register: {
				title: t( 'registerTitle', 'Create account' ),
				subtitle: t( 'registerSubtitle', 'Create your account to get started.' ),
			},
			forgot: {
				title: t( 'forgotTitle', 'Forgot password' ),
				subtitle: t( 'forgotSubtitle', 'We will email you a link to reset your password.' ),
			},
		};
		return map[ view ] || map.login;
	};

	AuthBlock.prototype.showView = function ( view ) {
		var self = this;
		[ 'login', 'register', 'forgot' ].forEach( function ( key ) {
			var form = self.forms[ key ];
			if ( form ) {
				form.hidden = key !== view;
			}
		} );

		var copy = this.copy( view );
		if ( this.titleEl ) {
			this.titleEl.textContent = copy.title;
		}
		if ( this.subtitleEl ) {
			this.subtitleEl.textContent = copy.subtitle;
		}

		// Tabs reflect the active view.
		var tabs = this.root.querySelectorAll( '[data-hvnly-auth-tab]' );
		Array.prototype.forEach.call( tabs, function ( tab ) {
			var active = tab.getAttribute( 'data-hvnly-auth-tab' ) === view;
			tab.classList.toggle( 'is-active', active );
			tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
		} );

		this.clearMessage();

		// Focus the first input of the shown form (not on initial mount).
		if ( this._mounted ) {
			var input = this.forms[ view ] && this.forms[ view ].querySelector( 'input' );
			if ( input ) {
				try {
					input.focus();
				} catch ( e ) {}
			}
		}
	};

	AuthBlock.prototype.setMessage = function ( text, kind ) {
		if ( ! this.messageEl ) {
			return;
		}
		this.messageEl.textContent = text || '';
		this.messageEl.hidden = ! text;
		this.messageEl.className = 'hvnly-auth__message' + ( kind ? ' is-' + kind : '' );
	};

	AuthBlock.prototype.clearMessage = function () {
		this.setMessage( '', '' );
	};

	AuthBlock.prototype.setBusy = function ( form, busy ) {
		this.busy = busy;
		var btn = form ? form.querySelector( 'button[type="submit"]' ) : null;
		if ( btn ) {
			btn.disabled = busy;
			btn.classList.toggle( 'is-loading', busy );
		}
	};

	AuthBlock.prototype.fieldError = function ( input, text ) {
		var field = input.closest( '.hvnly-auth__field' );
		if ( ! field ) {
			return;
		}
		var err = field.querySelector( '[data-hvnly-auth-field-error]' );
		input.classList.toggle( 'is-invalid', !! text );
		input.setAttribute( 'aria-invalid', text ? 'true' : 'false' );
		if ( err ) {
			err.textContent = text || '';
			err.hidden = ! text;
		}
	};

	AuthBlock.prototype.clearFieldErrors = function ( form ) {
		var self = this;
		Array.prototype.forEach.call( form.querySelectorAll( 'input' ), function ( input ) {
			self.fieldError( input, '' );
		} );
	};

	AuthBlock.prototype.val = function ( form, name ) {
		var el = form.querySelector( '[name="' + name + '"]' );
		return el ? el.value.trim() : '';
	};

	AuthBlock.prototype.bind = function () {
		var self = this;

		// View navigation (links + tabs).
		this.root.addEventListener( 'click', function ( e ) {
			var goto = e.target.closest ? e.target.closest( '[data-hvnly-auth-goto]' ) : null;
			if ( goto && self.root.contains( goto ) ) {
				e.preventDefault();
				self.showView( goto.getAttribute( 'data-hvnly-auth-goto' ) );
				return;
			}
			var tab = e.target.closest ? e.target.closest( '[data-hvnly-auth-tab]' ) : null;
			if ( tab && self.root.contains( tab ) ) {
				e.preventDefault();
				self.showView( tab.getAttribute( 'data-hvnly-auth-tab' ) );
				return;
			}
			var toggle = e.target.closest ? e.target.closest( '[data-hvnly-auth-toggle]' ) : null;
			if ( toggle && self.root.contains( toggle ) ) {
				e.preventDefault();
				self.togglePassword( toggle );
			}
		} );

		// Password strength meter.
		var pwInput = this.forms.register
			? this.forms.register.querySelector( '[name="pass1"]' )
			: null;
		if ( pwInput ) {
			pwInput.addEventListener( 'input', function () {
				self.updateStrength( pwInput );
			} );
		}

		if ( this.forms.login ) {
			this.forms.login.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				self.submitLogin();
			} );
		}
		if ( this.forms.register ) {
			this.forms.register.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				self.submitRegister();
			} );
		}
		if ( this.forms.forgot ) {
			this.forms.forgot.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				self.submitForgot();
			} );
		}

		// Logout (account panel).
		var logoutBtn = this.root.querySelector( '[data-hvnly-auth-logout]' );
		if ( logoutBtn ) {
			logoutBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				self.submitLogout( logoutBtn );
			} );
		}

		// Establish the initial view from the server-rendered baseline.
		var initial = this.root.getAttribute( 'data-initial-view' ) || 'login';
		this.showView( initial );
		this._mounted = true;
	};

	AuthBlock.prototype.togglePassword = function ( toggle ) {
		var field = toggle.closest( '.hvnly-auth__field' );
		var input = field ? field.querySelector( 'input' ) : null;
		if ( ! input ) {
			return;
		}
		var show = input.type === 'password';
		input.type = show ? 'text' : 'password';
		toggle.setAttribute( 'aria-pressed', show ? 'true' : 'false' );
		toggle.setAttribute(
			'aria-label',
			show ? t( 'hidePassword', 'Hide password' ) : t( 'showPassword', 'Show password' )
		);
		toggle.classList.toggle( 'is-visible', show );
	};

	AuthBlock.prototype.updateStrength = function ( input ) {
		var field = input.closest( '.hvnly-auth__field' );
		var wrap = field ? field.querySelector( '[data-hvnly-auth-strength]' ) : null;
		if ( ! wrap ) {
			return;
		}
		var score = strength( input.value );
		wrap.hidden = input.value.length === 0;
		wrap.setAttribute( 'data-score', String( score ) );
		var fill = wrap.querySelector( '[data-hvnly-auth-strength-fill]' );
		var label = wrap.querySelector( '[data-hvnly-auth-strength-label]' );
		if ( fill ) {
			fill.style.width = ( score / 4 ) * 100 + '%';
		}
		if ( label ) {
			label.textContent = t( 'strength' + STRENGTH_LABELS[ score ], STRENGTH_LABELS[ score ] );
		}
	};

	AuthBlock.prototype.resolveRedirect = function ( setting, serverRedirect ) {
		// Block setting wins when configured; '' means "reload current page".
		if ( setting ) {
			navigate( setting );
			return;
		}
		if ( serverRedirect ) {
			navigate( serverRedirect );
			return;
		}
		reload();
	};

	AuthBlock.prototype.submitLogin = function () {
		var self = this;
		var form = this.forms.login;
		if ( this.busy || ! form ) {
			return;
		}
		this.clearFieldErrors( form );

		var log = this.val( form, 'log' );
		var pwd = form.querySelector( '[name="pwd"]' ) ? form.querySelector( '[name="pwd"]' ).value : '';
		var remember = !! ( form.querySelector( '[name="rememberme"]' ) && form.querySelector( '[name="rememberme"]' ).checked );

		var invalid = false;
		if ( ! log ) {
			this.fieldError( form.querySelector( '[name="log"]' ), t( 'required', 'This field is required.' ) );
			invalid = true;
		}
		if ( ! pwd ) {
			this.fieldError( form.querySelector( '[name="pwd"]' ), t( 'required', 'This field is required.' ) );
			invalid = true;
		}
		if ( invalid ) {
			return;
		}

		this.setBusy( form, true );
		this.clearMessage();

		postAuthAction( actionFor( 'login' ), { log: log, pwd: pwd, rememberme: remember } ).then(
			function ( res ) {
				self.setBusy( form, false );
				if ( res.ok ) {
					self.setMessage( res.message || t( 'loginSuccess', 'Signed in. Redirecting…' ), 'success' );
					self.resolveRedirect( self.afterLogin, res.data && res.data.redirect );
				} else {
					self.setMessage( res.message, 'error' );
				}
			}
		);
	};

	AuthBlock.prototype.submitRegister = function () {
		var self = this;
		var form = this.forms.register;
		if ( this.busy || ! form ) {
			return;
		}
		this.clearFieldErrors( form );

		var fields = {
			user_login: this.val( form, 'user_login' ),
			user_email: this.val( form, 'user_email' ),
			first_name: this.val( form, 'first_name' ),
			last_name: this.val( form, 'last_name' ),
		};
		var pass1El = form.querySelector( '[name="pass1"]' );
		var pass2El = form.querySelector( '[name="pass2"]' );
		var pass1 = pass1El ? pass1El.value : '';
		var pass2 = pass2El ? pass2El.value : '';

		var invalid = false;
		if ( ! fields.user_login ) {
			this.fieldError( form.querySelector( '[name="user_login"]' ), t( 'required', 'This field is required.' ) );
			invalid = true;
		}
		if ( ! fields.user_email ) {
			this.fieldError( form.querySelector( '[name="user_email"]' ), t( 'required', 'This field is required.' ) );
			invalid = true;
		} else if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( fields.user_email ) ) {
			this.fieldError( form.querySelector( '[name="user_email"]' ), t( 'invalidEmail', 'Enter a valid email address.' ) );
			invalid = true;
		}
		if ( pass1.length < 8 ) {
			this.fieldError( pass1El, t( 'passwordTooShort', 'Use a password with at least 8 characters.' ) );
			invalid = true;
		}
		if ( pass1 !== pass2 ) {
			this.fieldError( pass2El, t( 'passwordMismatch', 'Passwords do not match.' ) );
			invalid = true;
		}
		var terms = form.querySelector( '[name="hvnly_auth_terms"]' );
		if ( terms && ! terms.checked ) {
			this.setMessage( t( 'termsRequired', 'Please accept the terms and conditions.' ), 'error' );
			invalid = true;
		}
		if ( invalid ) {
			return;
		}

		fields.pass1 = pass1;
		fields.pass2 = pass2;
		// Marker so the additive user_register hook only stores names for this block.
		fields.hvnly_auth_block = '1';

		this.setBusy( form, true );
		this.clearMessage();

		postAuthAction( actionFor( 'register' ), fields ).then( function ( res ) {
			self.setBusy( form, false );
			if ( ! res.ok ) {
				self.setMessage( res.message, 'error' );
				return;
			}
			var status = res.data && res.data.status;
			if ( status === 'pending' ) {
				// Approval mode — no session yet; show pending message, no redirect.
				self.setMessage(
					res.message || t( 'registerPending', 'Account created and awaiting administrator approval.' ),
					'success'
				);
				if ( self.forms.register ) {
					self.forms.register.reset();
				}
				return;
			}
			self.setMessage( res.message || t( 'registerSuccess', 'Account created. Redirecting…' ), 'success' );
			self.resolveRedirect( self.afterRegister, res.data && res.data.redirect );
		} );
	};

	AuthBlock.prototype.submitForgot = function () {
		var self = this;
		var form = this.forms.forgot;
		if ( this.busy || ! form ) {
			return;
		}
		this.clearFieldErrors( form );

		var user = this.val( form, 'user_login' );
		if ( ! user ) {
			this.fieldError( form.querySelector( '[name="user_login"]' ), t( 'required', 'This field is required.' ) );
			return;
		}

		this.setBusy( form, true );
		this.clearMessage();

		postAuthAction( actionFor( 'forgot' ), { user_login: user } ).then( function ( res ) {
			self.setBusy( form, false );
			self.setMessage(
				res.message || t( 'forgotSuccess', 'Check your email for the reset link.' ),
				res.ok ? 'success' : 'error'
			);
			if ( res.ok ) {
				form.reset();
			}
		} );
	};

	AuthBlock.prototype.submitLogout = function ( btn ) {
		var self = this;
		if ( this.busy ) {
			return;
		}
		this.busy = true;
		btn.disabled = true;
		btn.classList.add( 'is-loading' );

		postAuthAction( actionFor( 'logout' ), {} ).then( function ( res ) {
			var redirect = ( res.data && res.data.redirect ) || ( cfg().logoutRedirect || '' );
			if ( redirect ) {
				navigate( redirect );
			} else {
				reload();
			}
		} );
	};

	function init( context ) {
		var scope = context && context.querySelectorAll ? context : document;
		var roots = scope.querySelectorAll( '[data-hvnly-auth]' );
		Array.prototype.forEach.call( roots, function ( root ) {
			if ( root.getAttribute( 'data-hvnly-auth-ready' ) === '1' ) {
				return;
			}
			root.setAttribute( 'data-hvnly-auth-ready', '1' );
			// eslint-disable-next-line no-new
			new AuthBlock( root );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	window.hvnlyInitBlockAuth = init;
} )();
