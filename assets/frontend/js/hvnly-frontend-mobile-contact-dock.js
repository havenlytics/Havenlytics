/**
 * Havenlytics — Mobile Contact Dock controller
 *
 * Handles entrance animation, viewport gating, and soft-minimize when the
 * visitor reaches an on-page inquiry / contact section (Option B pill).
 *
 * Contact URLs are rendered server-side; this file does not invent tel/wa links.
 *
 * @package Havenlytics
 * @since   3.5.0
 */
(function () {
	'use strict';

	var SELECTOR = '[data-hvnly-mobile-contact-dock]';
	var BODY_CLASS = 'hvnly-has-mobile-contact-dock';

	/** Sections that mean the visitor already sees contact UI nearby. */
	var INQUIRY_SELECTORS = [
		'.hvnly-agents-section__contact-panel',
		'.hvnly-agents-section__form-col',
		'.js-hvnly-contact-agent-form-inline',
		'.hvnly-agent-sidebar .js-hvnly-contact-agent-form',
		'.hvnly-contact-agent__panel',
		'.hvnly-block-inquiry',
		'#hvnly-contact-agent-modal.hvnly-contact-agent-modal--open',
		'.hvnly-contact-agent-modal.is-open'
	].join(',');

	/**
	 * @param {HTMLElement} dock
	 * @returns {number}
	 */
	function readMaxWidth(dock) {
		var raw = dock.getAttribute('data-max-width');
		var parsed = raw ? parseInt(raw, 10) : 991;
		return isNaN(parsed) || parsed < 320 ? 991 : parsed;
	}

	/**
	 * @param {number} maxWidth
	 * @returns {boolean}
	 */
	function isMobileViewport(maxWidth) {
		return window.matchMedia('(max-width: ' + maxWidth + '.98px)').matches;
	}

	/**
	 * Soft-minimize into a compact pill near inquiry sections.
	 *
	 * @param {HTMLElement} dock
	 * @param {boolean} compact
	 */
	function setCompact(dock, compact) {
		if (compact) {
			dock.classList.add('is-compact');
		} else {
			dock.classList.remove('is-compact');
		}
	}

	/**
	 * Reveal with Realty search-dock spring (CSS handles motion).
	 * First appearance / becoming visible only — class toggle, no replay on scroll.
	 *
	 * @param {HTMLElement} dock
	 */
	function reveal(dock) {
		// Double rAF so the hidden transform/opacity paint before transition.
		window.requestAnimationFrame(function () {
			window.requestAnimationFrame(function () {
				dock.classList.add('is-visible');
			});
		});
	}

	/**
	 * Hide with the reverse of the same spring (fade + scale down + settle down).
	 *
	 * @param {HTMLElement} dock
	 */
	function conceal(dock) {
		dock.classList.remove('is-visible');
	}

	/**
	 * Observe inquiry / contact sections; debounce class toggles to avoid flicker.
	 *
	 * @param {HTMLElement} dock
	 * @returns {IntersectionObserver|null}
	 */
	function observeInquirySections(dock) {
		var targets = document.querySelectorAll(INQUIRY_SELECTORS);
		if (!targets.length || typeof IntersectionObserver === 'undefined') {
			return null;
		}

		var visible = new Set();
		var timer = null;

		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						visible.add(entry.target);
					} else {
						visible.delete(entry.target);
					}
				});

				if (timer) {
					window.clearTimeout(timer);
				}

				// Short debounce prevents rapid expand/collapse while scrolling.
				timer = window.setTimeout(function () {
					setCompact(dock, visible.size > 0);
				}, 120);
			},
			{
				root: null,
				rootMargin: '-12% 0px -28% 0px',
				threshold: 0.18
			}
		);

		targets.forEach(function (el) {
			observer.observe(el);
		});

		return observer;
	}

	/**
	 * Sync body class + data attr used by CSS padding / scroll-top offset.
	 *
	 * @param {HTMLElement} dock
	 * @param {boolean} active
	 */
	function syncBodyState(dock, active) {
		var maxWidth = readMaxWidth(dock);
		if (active) {
			document.body.classList.add(BODY_CLASS);
			document.body.setAttribute('data-hvnly-mcd-max', String(maxWidth));
		} else {
			document.body.classList.remove(BODY_CLASS);
			document.body.removeAttribute('data-hvnly-mcd-max');
			conceal(dock);
			dock.classList.remove('is-compact');
		}
	}

	/**
	 * Boot a single dock instance.
	 *
	 * @param {HTMLElement} dock
	 */
	function initDock(dock) {
		if (dock.getAttribute('data-hvnly-mcd-ready') === '1') {
			return;
		}
		dock.setAttribute('data-hvnly-mcd-ready', '1');

		var maxWidth = readMaxWidth(dock);
		var inquiryObserver = null;

		function applyViewport() {
			var mobile = isMobileViewport(maxWidth);
			syncBodyState(dock, mobile);

			if (mobile) {
				reveal(dock);
				if (!inquiryObserver) {
					inquiryObserver = observeInquirySections(dock);
				}
			} else if (inquiryObserver) {
				inquiryObserver.disconnect();
				inquiryObserver = null;
			}
		}

		applyViewport();

		var mq = window.matchMedia('(max-width: ' + maxWidth + '.98px)');
		var onChange = function () {
			applyViewport();
		};

		if (typeof mq.addEventListener === 'function') {
			mq.addEventListener('change', onChange);
		} else if (typeof mq.addListener === 'function') {
			mq.addListener(onChange);
		}

		// Hide while Fancybox / fullscreen gallery is open if those classes appear.
		document.addEventListener(
			'click',
			function () {
				window.setTimeout(function () {
					var fancyOpen = document.querySelector(
						'.hvnly-property-single__fancybox-popup--active, .fancybox-is-open, .fancybox__container'
					);
					if (fancyOpen && isMobileViewport(maxWidth)) {
						conceal(dock);
					} else if (isMobileViewport(maxWidth)) {
						reveal(dock);
					}
				}, 50);
			},
			true
		);
	}

	function boot() {
		var docks = document.querySelectorAll(SELECTOR);
		if (!docks.length) {
			return;
		}
		docks.forEach(function (dock) {
			initDock(dock);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	// Ajax-loaded single property fragments (future / Elementor re-render).
	document.addEventListener('hvnly:property:loaded', boot);
	document.addEventListener('hvnlyPropertyLoaded', boot);
})();
